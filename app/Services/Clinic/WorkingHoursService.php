<?php

namespace App\Services\Clinic;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\BranchBlackout;
use App\Models\BranchOpeningHour;
use App\Models\Doctor;
use App\Models\DoctorShift;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Working hours — the single place that answers "when is this branch open?"
 * and "when does this doctor work?", and the one rule that ties them together:
 * a doctor can never be scheduled outside their branch's open window.
 *
 * Storage of record:
 *   - branch   → `branch_availability_rules` (one row per weekday, 0=Sun..6=Sat).
 *                `branch_opening_hours` is mirrored from it because the public
 *                storefront's "open now" badge reads that table instead.
 *   - doctor   → `doctors.working_hours` JSON: [{day:int, start:'H:i', end:'H:i'}]
 *   - override → `doctor_shifts` (a single date beats the weekly pattern)
 *
 * A branch with no rules at all is "unconfigured", not "closed": it imposes no
 * window. That keeps branches that were never set up bookable instead of
 * silently going dark the moment this guard shipped.
 */
class WorkingHoursService
{
    public const DAYS = [
        0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
    ];

    public const DAYS_AR = [
        0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء',
        4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
    ];

    protected function tz(): string
    {
        return config('app.timezone', 'Asia/Kuwait');
    }

    /* ================= who can be booked ================= */

    /**
     * The doctor behind an id, or null when nobody may be booked into them.
     *
     * Global scopes are dropped on purpose: `BelongsToBranchScope` narrows the
     * doctors table to `user_id = <me>` for a doctor-role user, which would
     * make every colleague look like they don't exist — an empty slot picker
     * with no explanation. Schedules aren't PHI; who is on shift is the same
     * answer for everyone at the branch.
     *
     * Dropping the scopes also drops SoftDeletingScope, so `deleted_at` and
     * `is_active` have to be restated here — otherwise a deleted or switched-off
     * doctor stays bookable through every guard that calls this.
     */
    public function bookableDoctor(?int $doctorId): ?Doctor
    {
        if (! $doctorId) {
            return null;
        }

        return Doctor::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->find($doctorId);
    }

    /** Ids of every doctor at a branch who can currently take bookings. */
    public function bookableDoctorIds(int $branchId): array
    {
        return Doctor::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('branch_id', $branchId)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * False when the branch itself is switched off. The public branch list
     * filters on this already, but deep links, v2, WhatsApp and follow-up
     * auto-booking all reach the slot grid without passing through it.
     */
    public function branchIsBookable(int $branchId): bool
    {
        // Deliberately not memoized. A static cache here would answer from a
        // stale read after an admin toggles the branch mid-request, and on a
        // long-running worker it would outlive the request entirely. This is a
        // primary-key lookup; nextDates' own 5-minute cache absorbs the rest.
        return (bool) Branch::query()->withoutGlobalScopes()
            ->whereKey($branchId)
            ->value('is_available');
    }

    public function dayName(int $dow): string
    {
        return app()->getLocale() === 'ar'
            ? (self::DAYS_AR[$dow] ?? (string) $dow)
            : (self::DAYS[$dow] ?? (string) $dow);
    }

    /* ================= time helpers ================= */

    /** 'HH:MM', 'HH:MM:SS' or a Carbon → minutes past midnight, or null. */
    public function toMinutes(mixed $time): ?int
    {
        if ($time instanceof \DateTimeInterface) {
            return ((int) $time->format('H') * 60) + (int) $time->format('i');
        }
        $s = trim((string) $time);
        if (! preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $s, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h > 23 || $i > 59) {
            return null;
        }

        return ($h * 60) + $i;
    }

    /** Minutes past midnight → 'HH:MM' (wraps past 24h for overnight windows). */
    public function toHm(int $minutes): string
    {
        $minutes = (($minutes % 1440) + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /* ================= branch side ================= */

    /** All availability rules for a branch, keyed by day_of_week. */
    public function branchRules(int $branchId): Collection
    {
        return BranchAvailabilityRule::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy(fn ($r) => (int) $r->day_of_week);
    }

    /** True when the branch has never had its hours configured. */
    public function branchIsUnconfigured(int $branchId): bool
    {
        return $this->branchRules($branchId)->isEmpty();
    }

    /**
     * The branch's window for one weekday.
     *
     * @return array{is_open: bool, open: int, close: int}|null
     *                                                          null = unconfigured (no constraint). `close` may exceed 1440 for an
     *                                                          overnight window (e.g. open 17:00, close 01:00 → 1020..1500).
     */
    public function branchWindow(int $branchId, int $dow, ?Collection $rules = null): ?array
    {
        $rules ??= $this->branchRules($branchId);
        if ($rules->isEmpty()) {
            return null;
        }

        $rule = $rules->get($dow);
        if (! $rule) {
            // Other days are configured but this one isn't → the branch is
            // closed that day (that's exactly how AvailabilityService reads it).
            return ['is_open' => false, 'open' => 0, 'close' => 0];
        }

        $open = $this->toMinutes($rule->open_at);
        $close = $this->toMinutes($rule->close_at);
        if ($open === null || $close === null) {
            return ['is_open' => false, 'open' => 0, 'close' => 0];
        }
        if ($close <= $open) {
            $close += 1440; // overnight
        }

        return ['is_open' => (bool) $rule->is_open, 'open' => $open, 'close' => $close];
    }

    /** Human label for a branch day window, e.g. "09:00–17:00" or "Closed". */
    public function branchWindowLabel(?array $window): string
    {
        if ($window === null) {
            return app()->getLocale() === 'ar' ? 'غير محدد' : 'not set';
        }
        if (! $window['is_open']) {
            return app()->getLocale() === 'ar' ? 'مغلق' : 'closed';
        }

        return $this->toHm($window['open']).'–'.$this->toHm($window['close']);
    }

    /**
     * The 7-row editor payload for the v2 Branches form.
     *
     * @return array{days: array<int, array>, settings: array}
     */
    public function branchHoursPayload(int $branchId): array
    {
        $rules = $branchId > 0 ? $this->branchRules($branchId) : collect();
        $days = [];

        foreach (array_keys(self::DAYS) as $dow) {
            $rule = $rules->get($dow);
            $days[] = [
                'day' => $dow,
                'label' => self::DAYS[$dow],
                'label_ar' => self::DAYS_AR[$dow],
                // An unconfigured branch is pre-filled with the Kuwait norm
                // (Sat–Thu, Friday off) rather than "closed all week", which
                // would read as a deliberate setting nobody made.
                'is_open' => $rule ? (bool) $rule->is_open : $dow !== 5,
                'open_at' => $rule ? substr((string) $rule->open_at, 0, 5) : '09:00',
                'close_at' => $rule ? substr((string) $rule->close_at, 0, 5) : '17:00',
            ];
        }

        $first = $rules->first();

        return [
            'days' => $days,
            'settings' => [
                'slot_length_minutes' => (int) ($first->slot_length_minutes ?? 30),
                'slot_step_minutes' => (int) ($first->slot_step_minutes ?? 30),
                'lead_time_minutes' => (int) ($first->lead_time_minutes ?? 60),
            ],
            'configured' => $rules->isNotEmpty(),
        ];
    }

    /**
     * Persist a branch's weekly hours + appointment settings.
     *
     * Returns the knock-on impact so the caller can tell the admin what the
     * change did to people already on the schedule.
     *
     * @param  array  $days  [['day'=>int,'is_open'=>bool,'open_at'=>'H:i','close_at'=>'H:i'], ...]
     * @return array{adjusted_doctors: array<string>, closed_out_doctors: array<string>, affected_bookings: int}
     */
    public function saveBranchHours(Branch $branch, array $days, array $settings): array
    {
        $length = max(5, (int) ($settings['slot_length_minutes'] ?? 30));
        $step = max(5, (int) ($settings['slot_step_minutes'] ?? 30));
        $lead = max(0, (int) ($settings['lead_time_minutes'] ?? 0));

        DB::transaction(function () use ($branch, $days, $length, $step, $lead) {
            foreach ($days as $row) {
                $dow = (int) ($row['day'] ?? -1);
                if (! array_key_exists($dow, self::DAYS)) {
                    continue;
                }
                $isOpen = (bool) ($row['is_open'] ?? false);
                $open = $this->toMinutes($row['open_at'] ?? null) ?? 540;
                $close = $this->toMinutes($row['close_at'] ?? null) ?? 1020;

                BranchAvailabilityRule::withoutGlobalScopes()->updateOrCreate(
                    ['branch_id' => $branch->id, 'day_of_week' => $dow],
                    [
                        'is_open' => $isOpen,
                        'open_at' => $this->toHm($open).':00',
                        'close_at' => $this->toHm($close).':00',
                        'slot_length_minutes' => $length,
                        'slot_step_minutes' => $step,
                        'lead_time_minutes' => $lead,
                    ],
                );

                // Mirror into branch_opening_hours — the public site's
                // "open now" badge reads that table, and a branch whose
                // booking window says one thing while the storefront says
                // another is the worst of both.
                BranchOpeningHour::withoutGlobalScopes()->updateOrCreate(
                    ['branch_id' => $branch->id, 'day_of_week' => $dow],
                    [
                        'is_closed' => ! $isOpen,
                        'opens_at' => $this->toHm($open).':00',
                        'closes_at' => $this->toHm($close).':00',
                    ],
                );
            }
        });

        $this->bumpScheduleVersion($branch->id);

        return $this->reconcileBranchDoctors($branch);
    }

    /**
     * After a branch's hours change, pull every doctor at that branch back
     * inside the new window: clamp overhanging start/end times and drop days
     * the branch no longer opens. Bookings are only reported, never touched —
     * moving someone's appointment silently would be worse than telling staff.
     */
    public function reconcileBranchDoctors(Branch $branch): array
    {
        $rules = $this->branchRules($branch->id);
        $adjusted = [];
        $closedOut = [];

        $doctors = Doctor::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('branch_id', $branch->id)
            ->get();

        foreach ($doctors as $doctor) {
            $before = $this->normalizeDoctorHours((array) ($doctor->working_hours ?? []));
            $after = $this->clampHoursToBranch($before, $branch->id, $rules);

            if ($before === $after) {
                continue;
            }

            $doctor->working_hours = $after;
            $doctor->saveQuietly();

            if ($after === []) {
                $closedOut[] = $doctor->name;
            } else {
                $adjusted[] = $doctor->name;
            }

            activity('doctors')
                ->performedOn($doctor)
                ->causedBy(auth()->user())
                ->event('updated')
                ->withProperties([
                    'attributes' => ['working_hours' => $after],
                    'old' => ['working_hours' => $before],
                ])
                ->log("Working hours re-fitted to branch hours for {$doctor->name}");
        }

        return [
            'adjusted_doctors' => $adjusted,
            'closed_out_doctors' => $closedOut,
            'affected_bookings' => $this->countBookingsOutsideHours($branch->id),
        ];
    }

    /** Upcoming confirmed/pending bookings that no longer fit the branch window. */
    public function countBookingsOutsideHours(int $branchId): int
    {
        $rules = $this->branchRules($branchId);
        if ($rules->isEmpty()) {
            return 0;
        }

        $bookings = Booking::query()->withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereIn('status', [Booking::S_CONFIRMED, Booking::S_PENDING])
            ->whereDate('res_date', '>=', now($this->tz())->toDateString())
            ->get(['id', 'res_date', 'res_time', 'doctor_id']);

        $count = 0;
        foreach ($bookings as $b) {
            $minutes = $this->toMinutes($b->res_time);
            if ($minutes === null) {
                continue;
            }
            $dow = (int) Carbon::parse($b->res_date, $this->tz())->dayOfWeek;
            $window = $this->branchWindow($branchId, $dow, $rules);
            if ($window === null) {
                continue;
            }
            // The appointment has to *finish* inside the window — testing only
            // its start time let a 16:55 booking that runs to 17:10 pass as if
            // it fit a window that closes at 17:00.
            $end = $minutes + $this->slotLength($branchId, $dow, $b->doctor_id ? (int) $b->doctor_id : null);
            if (! $window['is_open'] || $minutes < $window['open'] || $end > $window['close']) {
                $count++;
            }
        }

        return $count;
    }

    /* ================= doctor side ================= */

    /**
     * Coerce whatever the form sent into the canonical shape:
     * [['day'=>int, 'start'=>'H:i', 'end'=>'H:i'], ...] sorted by day, one row
     * per day, rows with missing/invalid times dropped.
     */
    public function normalizeDoctorHours(array $raw): array
    {
        $out = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $day = (int) ($row['day'] ?? -1);
            if (! array_key_exists($day, self::DAYS)) {
                continue;
            }
            // An explicit is_open=false row means "not working that day".
            if (array_key_exists('is_open', $row) && ! $row['is_open']) {
                continue;
            }
            $start = $this->toMinutes($row['start'] ?? null);
            $end = $this->toMinutes($row['end'] ?? null);
            if ($start === null || $end === null || $end <= $start) {
                continue;
            }
            $out[$day] = ['day' => $day, 'start' => $this->toHm($start), 'end' => $this->toHm($end)];
        }

        ksort($out);

        return array_values($out);
    }

    /** The 7-row editor payload for the v2 Doctors form. */
    public function doctorHoursPayload(?Doctor $doctor, ?int $branchId): array
    {
        $hours = collect($this->normalizeDoctorHours((array) ($doctor->working_hours ?? [])))
            ->keyBy(fn ($r) => (int) $r['day']);
        $rules = $branchId ? $this->branchRules($branchId) : collect();
        $rows = [];

        foreach (array_keys(self::DAYS) as $dow) {
            $row = $hours->get($dow);
            $window = $branchId ? $this->branchWindow($branchId, $dow, $rules) : null;
            $rows[] = [
                'day' => $dow,
                'label' => self::DAYS[$dow],
                'label_ar' => self::DAYS_AR[$dow],
                'is_open' => (bool) $row,
                'start' => $row['start'] ?? ($window && $window['is_open'] ? $this->toHm($window['open']) : '09:00'),
                'end' => $row['end'] ?? ($window && $window['is_open'] ? $this->toHm(min($window['close'], 1439)) : '17:00'),
            ];
        }

        return $rows;
    }

    /**
     * Branch windows for every weekday, as the doctor form needs them: which
     * days are bookable at all and the min/max time each day allows.
     */
    public function branchWindowsForForm(int $branchId): array
    {
        $rules = $this->branchRules($branchId);
        $out = [];

        foreach (array_keys(self::DAYS) as $dow) {
            $w = $this->branchWindow($branchId, $dow, $rules);
            $out[] = [
                'day' => $dow,
                'configured' => $w !== null,
                'is_open' => $w === null ? true : $w['is_open'],
                'open' => $w && $w['is_open'] ? $this->toHm($w['open']) : null,
                'close' => $w && $w['is_open'] ? $this->toHm($w['close']) : null,
                'overnight' => (bool) ($w && $w['close'] > 1440),
            ];
        }

        return $out;
    }

    /**
     * The rule the whole feature exists for: every doctor window must sit
     * inside its branch's open window for that weekday.
     *
     * @return array<string, string> validation errors keyed `working_hours.{day}`
     */
    public function validateDoctorHours(array $hours, ?int $branchId): array
    {
        $errors = [];
        if (! $branchId) {
            return $errors;
        }

        $rules = $this->branchRules($branchId);
        if ($rules->isEmpty()) {
            return $errors; // unconfigured branch imposes no window
        }

        foreach ($this->normalizeDoctorHours($hours) as $row) {
            $dow = (int) $row['day'];
            $window = $this->branchWindow($branchId, $dow, $rules);
            if ($window === null) {
                continue;
            }

            $day = $this->dayName($dow);

            if (! $window['is_open']) {
                $errors["working_hours.{$dow}"] = app()->getLocale() === 'ar'
                    ? "الفرع مغلق يوم {$day}، لا يمكن جدولة الطبيب في هذا اليوم."
                    : "The branch is closed on {$day}, so the doctor cannot work that day.";

                continue;
            }

            [$start, $end] = $this->doctorMinutesWithinWindow($row, $window);

            if ($start < $window['open'] || $end > $window['close']) {
                $label = $this->toHm($window['open']).'–'.$this->toHm($window['close']);
                $errors["working_hours.{$dow}"] = app()->getLocale() === 'ar'
                    ? "ساعات الطبيب يوم {$day} خارج ساعات عمل الفرع ({$label})."
                    : "{$day} hours fall outside the branch's opening hours ({$label}).";
            }
        }

        return $errors;
    }

    /**
     * Doctor start/end in the window's frame of reference. For an overnight
     * branch (17:00–01:00) a doctor working 00:00–01:00 belongs to the tail of
     * the window, not the morning before it opened.
     *
     * @return array{0:int,1:int}
     */
    protected function doctorMinutesWithinWindow(array $row, array $window): array
    {
        $start = $this->toMinutes($row['start']) ?? 0;
        $end = $this->toMinutes($row['end']) ?? 0;
        if ($end <= $start) {
            $end += 1440;
        }
        if ($window['close'] > 1440 && $start < $window['open']) {
            $start += 1440;
            $end += 1440;
        }

        return [$start, $end];
    }

    /** Clamp a doctor's hours into the branch's windows; drop closed days. */
    public function clampHoursToBranch(array $hours, int $branchId, ?Collection $rules = null): array
    {
        $rules ??= $this->branchRules($branchId);
        if ($rules->isEmpty()) {
            return $this->normalizeDoctorHours($hours);
        }

        $out = [];
        foreach ($this->normalizeDoctorHours($hours) as $row) {
            $dow = (int) $row['day'];
            $window = $this->branchWindow($branchId, $dow, $rules);
            if ($window === null) {
                $out[] = $row;

                continue;
            }
            if (! $window['is_open']) {
                continue; // branch shut that day → doctor is off
            }

            [$start, $end] = $this->doctorMinutesWithinWindow($row, $window);
            $start = max($start, $window['open']);
            $end = min($end, $window['close']);
            if ($end <= $start) {
                continue; // no overlap left at all
            }

            $out[] = ['day' => $dow, 'start' => $this->toHm($start), 'end' => $this->toHm($end)];
        }

        return $out;
    }

    /**
     * The doctor's window on a concrete date, as minutes past that date's
     * midnight. A `doctor_shifts` row for the date overrides the weekly
     * pattern (same precedence AvailabilityService uses).
     *
     * @return array{0:?int,1:?int}
     */
    public function doctorWindowForDate(int $doctorId, int $branchId, string $date): array
    {
        $shift = DoctorShift::query()->withoutGlobalScopes()
            ->where('doctor_id', $doctorId)
            ->where('branch_id', $branchId)
            ->whereDate('shift_date', $date)
            ->where('is_cancelled', 0)
            ->first();

        if ($shift) {
            $start = $this->toMinutes($shift->start_time);
            $end = $this->toMinutes($shift->end_time);
            if ($start === null || $end === null) {
                return [null, null];
            }

            return [$start, $end <= $start ? $end + 1440 : $end];
        }

        $doctor = $this->bookableDoctor($doctorId);
        if (! $doctor) {
            return [null, null];
        }

        $dow = (int) Carbon::parse($date, $this->tz())->dayOfWeek;
        foreach ($this->normalizeDoctorHours((array) ($doctor->working_hours ?? [])) as $row) {
            if ((int) $row['day'] !== $dow) {
                continue;
            }
            $start = $this->toMinutes($row['start']);
            $end = $this->toMinutes($row['end']);

            return [$start, $end <= $start ? $end + 1440 : $end];
        }

        return [null, null];
    }

    /* ================= booking guards ================= */

    /**
     * How long one appointment takes: the doctor's own length when they have
     * one, else the branch's for that weekday, else the config default.
     *
     * A dermatologist booking 60-minute laser sessions and a GP doing 10-minute
     * follow-ups can't share one branch-wide number, so the doctor wins.
     */
    public function slotLength(int $branchId, int $dow, ?int $doctorId = null): int
    {
        if ($doctorId && ($own = $this->doctorSlotMinutes($doctorId))) {
            return $own;
        }

        $rule = $this->branchRules($branchId)->get($dow);
        $minutes = (int) ($rule?->slot_length_minutes ?: $rule?->slot_step_minutes ?: config('booking.slot_interval', 30));

        return max(5, $minutes);
    }

    /** The doctor's own appointment length, or null to inherit the branch's. */
    public function doctorSlotMinutes(?int $doctorId): ?int
    {
        if (! $doctorId) {
            return null;
        }

        // Deliberately not memoized: each slot-generation pass resolves the
        // length once, and a process-lifetime cache would go stale the moment
        // an admin edits the doctor.
        $minutes = (int) Doctor::query()->withoutGlobalScopes()
            ->whereKey($doctorId)->value('default_slot_minutes');

        return $minutes >= 5 ? $minutes : null;
    }

    /**
     * Why this date+time can't be booked, or null if it can. Every booking
     * entry point runs this so a crafted POST can't do what the UI won't.
     */
    public function bookingProblem(
        int $branchId,
        int $doctorId,
        string $date,
        string $time,
        ?int $ignoreBookingId = null,
    ): ?string {
        $ar = app()->getLocale() === 'ar';
        $minutes = $this->toMinutes($time);
        if ($minutes === null) {
            return $ar ? 'وقت غير صالح.' : 'Invalid time.';
        }

        try {
            $day = Carbon::parse($date, $this->tz())->startOfDay();
        } catch (\Throwable) {
            return $ar ? 'تاريخ غير صالح.' : 'Invalid date.';
        }

        $dow = (int) $day->dayOfWeek;
        $length = $this->slotLength($branchId, $dow, $doctorId);
        $start = $day->copy()->addMinutes($minutes);
        $end = $start->copy()->addMinutes($length);

        // 1) The past.
        if ($start->lt(now($this->tz()))) {
            return $ar ? 'لا يمكن الحجز في وقت مضى.' : 'That time is in the past.';
        }

        // 2) The doctor must exist, still be active, and belong to this branch.
        $doctor = $this->bookableDoctor($doctorId);
        if (! $doctor) {
            return $ar ? 'الطبيب غير متاح للحجز.' : 'That doctor is not available for booking.';
        }
        if ((int) $doctor->branch_id !== $branchId) {
            return $ar ? 'الطبيب لا ينتمي لهذا الفرع.' : "That doctor doesn't work at this branch.";
        }

        // 2b) The branch itself must be switched on.
        if (! $this->branchIsBookable($branchId)) {
            return $ar ? 'هذا الفرع لا يستقبل حجوزات حالياً.' : 'That branch is not accepting bookings.';
        }

        // 3) Branch closure for the whole date.
        $blackout = BranchBlackout::query()->withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereDate('date', $day->toDateString())
            ->exists();
        if ($blackout) {
            return $ar ? 'الفرع مغلق في هذا التاريخ.' : 'The branch is closed on that date.';
        }

        // 4) Branch weekly window (skipped entirely when unconfigured).
        $window = $this->branchWindow($branchId, $dow);
        if ($window !== null) {
            $dayName = $this->dayName($dow);
            if (! $window['is_open']) {
                return $ar
                    ? "الفرع مغلق يوم {$dayName}."
                    : "The branch is closed on {$dayName}.";
            }
            $label = $this->toHm($window['open']).'–'.$this->toHm($window['close']);
            $s = $minutes;
            $e = $minutes + $length;
            if ($window['close'] > 1440 && $s < $window['open']) {
                $s += 1440;
                $e += 1440;
            }
            if ($s < $window['open'] || $e > $window['close']) {
                return $ar
                    ? "خارج ساعات عمل الفرع يوم {$dayName} ({$label})."
                    : "Outside the branch's opening hours on {$dayName} ({$label}).";
            }
        }

        // 5) Doctor window for that specific date.
        [$docStart, $docEnd] = $this->doctorWindowForDate($doctorId, $branchId, $day->toDateString());
        if ($docStart === null || $docEnd === null) {
            return $ar
                ? 'الطبيب لا يعمل في هذا اليوم.'
                : "The doctor doesn't work on that day.";
        }
        // An overnight shift (20:00–02:00) is stored as 1200..1560, so a 01:00
        // booking arrives as 60 and has to be lifted into the same day-span
        // before it can be compared — exactly as the branch check does above.
        $ds = $minutes;
        $de = $minutes + $length;
        if ($docEnd > 1440 && $ds < $docStart) {
            $ds += 1440;
            $de += 1440;
        }
        if ($ds < $docStart || $de > $docEnd) {
            $label = $this->toHm($docStart).'–'.$this->toHm($docEnd);
            return $ar
                ? "خارج ساعات عمل الطبيب ({$label})."
                : "Outside the doctor's working hours ({$label}).";
        }

        // 6) Double-booking.
        $clash = Booking::query()->withoutGlobalScopes()
            ->where('doctor_id', $doctorId)
            ->whereIn('status', [Booking::S_CONFIRMED, Booking::S_PENDING])
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->where('res_start', '<', $end->toDateTimeString())
            ->where('res_end', '>', $start->toDateTimeString())
            ->exists();
        if ($clash) {
            return $ar ? 'الطبيب لديه حجز آخر في هذا الوقت.' : 'The doctor already has a booking at that time.';
        }

        // 7) The time has to be one the grid actually offers. Without this the
        // window check alone accepts 11:07 on a 15-minute grid, and that
        // appointment then straddles — and silently eats — two real slots.
        $origin = $this->slotGridOrigin($branchId, $doctorId, $day->toDateString());
        if ($origin !== null) {
            $step = $this->slotStep($branchId, $dow, $doctorId);
            $offset = $minutes - $origin;
            if ($offset < 0 || $offset % $step !== 0) {
                return $ar
                    ? "الوقت غير متاح للحجز — المواعيد كل {$step} دقيقة."
                    : "That isn't an offered appointment time (slots run every {$step} minutes).";
            }
        }

        return null;
    }

    /**
     * The first minute of the day's slot grid, or null when the branch is
     * unconfigured and there is no grid to be on.
     *
     * Mirrors how AvailabilityService lays the grid out: it starts at the
     * branch's opening time, and when the doctor comes on later it jumps to
     * their start rounded up to the next step boundary.
     */
    public function slotGridOrigin(int $branchId, int $doctorId, string $date): ?int
    {
        $dow = (int) Carbon::parse($date, $this->tz())->dayOfWeek;

        $window = $this->branchWindow($branchId, $dow);
        if ($window === null || ! $window['is_open']) {
            return null;
        }

        $origin = $window['open'];
        $step = $this->slotStep($branchId, $dow, $doctorId);

        // doctorWindowForDate, not the weekly pattern — a doctor_shifts row for
        // this date moves the grid's start just as the weekly hours would.
        [$docStart] = $this->doctorWindowForDate($doctorId, $branchId, $date);
        if ($docStart !== null && $docStart > $origin) {
            $origin = (int) (ceil($docStart / $step) * $step);
        }

        return $origin;
    }

    /**
     * The gap between one slot start and the next — the doctor's own
     * appointment length when they have one, else the branch's step.
     */
    public function slotStep(int $branchId, int $dow, ?int $doctorId = null): int
    {
        if ($doctorId && ($own = $this->doctorSlotMinutes($doctorId))) {
            return $own;
        }

        $rule = $this->branchRules($branchId)->get($dow);

        return max(5, (int) ($rule?->slot_step_minutes ?: config('booking.slot_interval', 30)));
    }

    /**
     * Run a booking write with the schedule guard applied atomically.
     *
     * bookingProblem() and the write that follows it are separate statements,
     * so two receptionists clicking the same slot — or two visits closing at
     * once and auto-booking the same follow-up — both pass the check before
     * either writes, and the guard blocks neither. Locking the doctor row
     * serialises bookings for that doctor, the narrowest scope that makes the
     * check and the write one step. The re-check inside the lock is the part
     * that actually matters: the loser now sees the winner's row.
     *
     * @param  callable  $write  runs only if the slot is still free
     * @return array{0: bool, 1: ?string, 2: mixed} [ok, problem, whatever $write returned]
     */
    public function guardedBooking(
        int $branchId,
        int $doctorId,
        string $date,
        string $time,
        ?int $ignoreBookingId,
        callable $write,
    ): array {
        return DB::transaction(function () use ($branchId, $doctorId, $date, $time, $ignoreBookingId, $write) {
            Doctor::query()->withoutGlobalScopes()
                ->whereKey($doctorId)
                ->lockForUpdate()
                ->first();

            $problem = $this->bookingProblem($branchId, $doctorId, $date, $time, $ignoreBookingId);
            if ($problem !== null) {
                return [false, $problem, null];
            }

            return [true, null, $write()];
        });
    }

    /**
     * Bookable times for a doctor on a date, as 'H:i' strings.
     *
     * When the branch has rules we defer to AvailabilityService so the v2
     * admin, the public site and WhatsApp all offer the same grid. Only an
     * unconfigured branch falls back to walking the doctor's own hours.
     */
    public function slotsFor(int $branchId, int $doctorId, string $date, int $fallbackStep = 15, ?int $ignoreBookingId = null): array
    {
        if (! $this->branchIsUnconfigured($branchId)) {
            return collect(app(AvailabilityService::class)->timesFor($branchId, $date, 1, $doctorId, $ignoreBookingId))
                ->map(fn ($s) => substr((string) $s['value'], 0, 5))
                ->values()->all();
        }

        [$docStart, $docEnd] = $this->doctorWindowForDate($doctorId, $branchId, $date);
        if ($docStart === null || $docEnd === null) {
            return [];
        }

        $day = Carbon::parse($date, $this->tz())->startOfDay();
        if (BranchBlackout::query()->withoutGlobalScopes()
            ->where('branch_id', $branchId)->whereDate('date', $day->toDateString())->exists()) {
            return [];
        }

        $step = max(5, $fallbackStep);
        $length = $this->slotLength($branchId, (int) $day->dayOfWeek, $doctorId);
        $now = now($this->tz());

        $taken = Booking::query()->withoutGlobalScopes()
            ->where('doctor_id', $doctorId)
            ->whereIn('status', [Booking::S_CONFIRMED, Booking::S_PENDING])
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->whereNotNull('res_start')
            ->whereDate('res_date', '>=', $day->copy()->subDay()->toDateString())
            ->whereDate('res_date', '<=', $day->copy()->addDay()->toDateString())
            ->get(['res_start', 'res_end']);

        $slots = [];
        for ($m = $docStart; $m + $length <= $docEnd; $m += $step) {
            $start = $day->copy()->addMinutes($m);
            if ($start->lt($now)) {
                continue; // never offer a time that has already passed
            }
            $end = $start->copy()->addMinutes($length);

            $clash = $taken->contains(function ($b) use ($start, $end) {
                $bs = Carbon::parse($b->res_start, $this->tz());
                $be = $b->res_end ? Carbon::parse($b->res_end, $this->tz()) : $bs->copy()->addMinutes(1);

                return $bs->lt($end) && $be->gt($start);
            });

            if (! $clash) {
                $slots[] = $start->format('H:i');
            }
        }

        return $slots;
    }

    /* ================= cache ================= */

    /**
     * AvailabilityService caches its bookable-dates list for 5 minutes. Editing
     * hours must show up now, not eventually, so the cache key carries a
     * per-branch version we bump on every save.
     */
    public function bumpScheduleVersion(int $branchId): void
    {
        Cache::forever("avail:ver:{$branchId}", (int) Cache::get("avail:ver:{$branchId}", 0) + 1);
    }

    public static function scheduleVersion(int $branchId): int
    {
        return (int) Cache::get("avail:ver:{$branchId}", 0);
    }
}
