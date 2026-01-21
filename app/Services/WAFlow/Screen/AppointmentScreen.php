<?php

namespace App\Services\WAFlow\Screen;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\WhatsappContact;
use App\Models\WhatsappSession;
use App\Services\AvailabilityService;
use App\Services\HoldService;
use App\Services\WAFlow\FlowAssets;
use App\Services\WAFlow\FlowCtx;
use App\Services\WAFlow\FlowLocalization;
use App\Services\WAFlow\FlowRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AppointmentScreen
{
    public function __construct(
        private AvailabilityService $availability,
        private FlowAssets $assets,
        private FlowLocalization $i18n,
        private FlowCtx $ctx,
        private HoldService $holds,
    ) {}

    private function defaultBranchId(): int
    {
        $cfg = (int) (config('booking.default_branch_id') ?: 0);
        if ($cfg > 0) {
            return $cfg;
        }

        $single = Branch::where('is_available', true)->orderBy('id')->value('id');

        return (int) ($single ?: 1);
    }

    public function exchange(FlowRequest $req, ?WhatsappSession $session, string $locale): array
    {
        Log::info('Flow: APPOINTMENT exchange in', ['data' => $req->data]);

        $flowToken = $req->flowToken;
        $sesCtx = (array) ($session?->context ?? []);
        $c = array_replace($sesCtx, (array) ($this->ctx->all($flowToken) ?? []));

        // Ensure branch
        $c['branch_id'] = $c['branch_id'] ?? $this->defaultBranchId();

        // Party
        if (isset($req->data['party_size']) && $req->data['party_size'] !== '') {
            $c['party_size'] = (int) $req->data['party_size'];
        }

        // Date (if changed → release old holds)
        if (! empty($req->data['date'])) {
            $newDate = (string) $req->data['date'];
            if (($c['res_date'] ?? null) !== $newDate) {
                if (! empty($c['slot_key'])) {
                    $this->holds->release($c['slot_key'], $session?->phone);
                    Log::info('Flow: APPOINTMENT hold released (date changed)', [
                        'slot_key' => $c['slot_key'], 'msisdn_tail' => substr((string) ($session->phone ?? ''), -4),
                    ]);
                }
                if ($session?->phone) {
                    $released = $this->holds->releaseByPhone($session->phone);
                    Log::info('Flow: APPOINTMENT released all holds for phone (date changed)', [
                        'released' => $released, 'msisdn_tail' => substr($session->phone, -4),
                    ]);
                }
                $c['res_date'] = $newDate;
                unset($c['res_time'], $c['slot_key']);
            }
        }

        // Time (if changed → release, then fresh hold)
        if (! empty($req->data['time'])) {
            $newTime = (string) $req->data['time'];
            if (($c['res_time'] ?? null) !== $newTime) {
                if (! empty($c['slot_key'])) {
                    $this->holds->release($c['slot_key'], $session?->phone);
                    Log::info('Flow: APPOINTMENT hold released (time changed)', [
                        'slot_key' => $c['slot_key'], 'msisdn_tail' => substr((string) ($session->phone ?? ''), -4),
                    ]);
                }
                if ($session?->phone) {
                    $released = $this->holds->releaseByPhone($session->phone);
                    Log::info('Flow: APPOINTMENT released all holds for phone (time changed)', [
                        'released' => $released, 'msisdn_tail' => substr($session->phone, -4),
                    ]);
                }
                $c['res_time'] = $newTime;

                // If editing and unchanged from original, don't hold
                $shouldHold = true;
                if (! empty($c['edit_booking_id'])) {
                    if ($b = Booking::find((int) $c['edit_booking_id'])) {
                        if ($b->res_date === ($c['res_date'] ?? '') &&
                            $b->res_time === $newTime &&
                            $b->party_size == ($c['party_size'] ?? 0)) {
                            $shouldHold = false;
                        }
                    }
                }

                if ($shouldHold) {
                    $key = $this->holds->hold(
                        (int) $c['branch_id'],
                        (string) $c['res_date'],
                        (string) $c['res_time'],
                        (int) ($c['party_size'] ?? 0),
                        (string) ($session->phone ?? '')
                    );

                    $c['slot_key'] = $key ?: null;
                    Log::info('Flow: APPOINTMENT new hold', [
                        'slot_key' => $c['slot_key'],
                        'msisdn_tail' => substr((string) ($session->phone ?? ''), -4),
                    ]);
                } else {
                    unset($c['slot_key']);
                    Log::info('Flow: APPOINTMENT skip hold (editing unchanged)');
                }
            }
        }

        // Persist ctx (DB + legacy session)
        $this->ctx->put($flowToken, $c);
        if ($session) {
            $session->update(['context' => $c, 'last_interacted_at' => now()]);
        }

        Log::info('Flow: APPOINTMENT context saved', [
            'branch_id' => $c['branch_id'] ?? null,
            'party' => $c['party_size'] ?? null,
            'res_date' => $c['res_date'] ?? null,
            'res_time' => $c['res_time'] ?? null,
            'slot_key' => $c['slot_key'] ?? null,
            'edit_id' => $c['edit_booking_id'] ?? null,
        ]);

        // Build payload from DB ctx
        $payload = $this->build($session, $locale, $flowToken);

        // Navigation to DETAILS?
        $trigger = (string) ($req->data['trigger'] ?? '');
        if ($trigger === 'go_details') {
            $branchId = (int) ($c['branch_id'] ?? 0);
            $party = (int) ($c['party_size'] ?? 0);
            $date = (string) ($c['res_date'] ?? '');
            $time = (string) ($c['res_time'] ?? '');
            $slotKey = (string) ($c['slot_key'] ?? '');

            if (! $branchId || ! $party || $date === '' || $time === '') {
                $payload['confirmation_message'] = app(FlowLocalization::class)->tr('pick_again', $locale);
                Log::warning('Flow: APPOINTMENT → DETAILS blocked (missing picks)', compact('branchId', 'party', 'date', 'time'));

                return $payload; // stay
            }

            // If not editing or changed: require a valid hold or live availability
            $skipChecks = false;
            if (! empty($c['edit_booking_id'])) {
                if ($b = Booking::find((int) $c['edit_booking_id'])) {
                    if ($b->branch_id == $branchId && $b->party_size == $party &&
                        $b->res_date === $date && $b->res_time === $time) {
                        $skipChecks = true;
                    }
                }
            }

            $holdValid = $slotKey ? $this->holds->isValid($slotKey) : false;
            Log::info('Flow: APPOINTMENT → DETAILS check', [
                'slot_key' => $slotKey, 'hold_valid' => $holdValid, 'skipChecks' => $skipChecks,
            ]);

            if (! $skipChecks) {
                if (! $holdValid) {
                    $slots = $this->availability->timeslots($branchId, $date, $party);
                    $inList = in_array($time, $slots, true);
                    Log::info('Flow: APPOINTMENT live availability check', [
                        'selected' => $time, 'inList' => $inList, 'slots' => $slots,
                    ]);
                    if (! $inList) {
                        $payload['confirmation_message'] = app(FlowLocalization::class)->tr('slot_taken', $locale);
                        $rule = $this->resolveRule($branchId, $date);

                        return $this->rebuildTimes($payload, $rule, $branchId, $date, $party, $locale, $time, (string) ($session->phone ?? '')); // stay
                    }
                }
            }

            // Prefill DETAILS
            $contact = $session && $session->phone
                ? WhatsappContact::where('msisdn', $session->phone)->first()
                : null;

            $prefill = [
                'branch' => (string) $branchId,
                'party_size' => (string) $party,
                'date' => $date,
                'time' => $time,
                'name' => (string) ($contact->name ?? ($c['name'] ?? '')),
                'phone' => (string) ($session->phone ?? ($c['phone'] ?? '')),
                'email' => (string) ($contact->email ?? ($c['email'] ?? '')),
                'notes' => (string) ($c['notes'] ?? ''),
            ];

            Log::info('Flow: APPOINTMENT → DETAILS attempt', compact('branchId', 'party', 'date', 'time'));

            return [
                '__nav' => 'DETAILS',
                '__data' => $prefill,
            ];
        }

        // Remain on APPOINTMENT
        return $payload;
    }

    private function resolveRule(int $branchId, string $dateStr): ?BranchAvailabilityRule
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $dowIso = Carbon::parse($dateStr, $tz)->dayOfWeekIso; // 1..7
        $dow0 = $dowIso === 7 ? 0 : $dowIso;                // 0..6 Sun..Sat

        return BranchAvailabilityRule::where('branch_id', $branchId)->where('day_of_week', $dow0)->first()
            ?: BranchAvailabilityRule::where('branch_id', $branchId)->where('is_open', 1)->first();
    }

    // 2) CHANGE partyList signature so we can handle null $rule cleanly
    private function partyList(int $branchId, ?\App\Models\BranchAvailabilityRule $rule, string $locale): array
    {
        $options = $locale === 'ar'
            ? [['id' => '2', 'title' => 'شخصان'], ['id' => '4', 'title' => '٤ أشخاص'], ['id' => '6', 'title' => '٦ أشخاص']]
            : [['id' => '2', 'title' => '2 people'], ['id' => '4', 'title' => '4 people'], ['id' => '6', 'title' => '6 people']];

        $maxAllowed = $rule
            ? app(\App\Services\AvailabilityService::class)->maxAllowedSize($rule->branch_id, $rule)
            : app(\App\Services\AvailabilityService::class)->branchWideMaxAllowedSize($branchId);

        if ($maxAllowed) {
            $options = array_values(array_filter($options, fn ($o) => (int) $o['id'] <= $maxAllowed));
        }

        $imgMap = (array) ($rule?->ui_party_images ?? []);

        $mapped = array_map(function ($opt) use ($imgMap) {
            $size = (string) ((int) $opt['id']);
            $opt['image'] = app(\App\Services\WAFlow\FlowAssets::class)->imageStrForPicker($imgMap[$size] ?? null);

            return $opt;
        }, $options);

        // Ensure numeric keys
        return array_values($mapped);
    }

    public function build(?WhatsappSession $session, string $locale, ?string $flowToken = null): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        // Merge DB flow ctx over legacy session ctx
        $cDb = $flowToken ? (array) ($this->ctx->all($flowToken) ?? []) : [];
        $cSes = (array) ($session?->context ?? []);
        $c = array_replace($cSes, $cDb);

        // Ensure branch
        if (empty($c['branch_id'])) {
            $c['branch_id'] = $this->defaultBranchId();
            $flowToken && $this->ctx->put($flowToken, ['branch_id' => $c['branch_id']]);
            $session?->update(['context' => array_replace($cSes, ['branch_id' => $c['branch_id']])]);
        }

        // If editing, hydrate once from booking
        if (! empty($c['edit_booking_id']) && empty($c['__edit_prefill_done'])) {
            if ($b = Booking::find((int) $c['edit_booking_id'])) {
                $patch = [
                    'branch_id' => $b->branch_id,
                    'party_size' => $b->party_size,
                    'res_date' => $b->res_date,
                    'res_time' => $b->res_time,
                    '__edit_prefill_done' => true,
                ];
                $c = array_replace($c, $patch);
                $flowToken && $this->ctx->put($flowToken, $patch);
                $session?->update(['context' => array_replace($cSes, $patch)]);
            }
        }

        $branchId = (int) $c['branch_id'];
        $dateCtx = (string) ($c['res_date'] ?? Carbon::now($tz)->toDateString());
        $rule = $this->resolveRule($branchId, $dateCtx);
        $partyList = $this->partyList($branchId, $rule, $locale);

        // --- IMPORTANT DEFAULTS ---
        // Time should be hidden/disabled until BOTH party & date are selected.
        $payload = [
            'party_size' => array_values($partyList), // already includes image
            'party_size_prefill' => (string) ($c['party_size'] ?? ''),
            'is_date_enabled' => true,
            'min_date' => Carbon::now($tz)->toDateString(),
            'max_date' => Carbon::now($tz)->addDays(60)->toDateString(),
            'unavailable_dates' => [],
            'include_days' => $this->includeDaysFor($branchId),

            // Time block defaults (hidden & disabled until ready)
            'time' => [],
            'is_time_enabled' => false,
            'show_time' => false,

            'confirmation_message' => '',
        ];

        $party = (int) ($c['party_size'] ?? 0);
        $date = (string) ($c['res_date'] ?? '');

        // Only build times when both party and date exist
        if ($branchId && $party > 0 && $date !== '') {
            $payload = $this->rebuildTimes(
                payload: $payload,
                rule: $rule,
                branchId: $branchId,
                date: $date,
                party: $party,
                locale: $locale,
                selectedTime: (string) ($c['res_time'] ?? ''),
                slotKey: (string) ($c['slot_key'] ?? '')
            );
        }

        // Safety: ensure numeric keys (validator likes lists)
        $payload['party_size'] = array_values($payload['party_size']);
        $payload['time'] = array_values($payload['time']);

        return $payload;
    }

    private function rebuildTimes(
        array $payload,
        ?BranchAvailabilityRule $rule,
        int $branchId,
        string $date,
        int $party,
        string $locale,
        string $selectedTime = '',
        string $slotKey = ''
    ): array {
        $slots = $this->availability->getSlots($branchId, $date, $party, 10);
        $slots = array_values(array_map('strval', $slots)); // numeric keys

        Log::info('Flow: APPOINTMENT slots (raw)', [
            'branch' => $branchId, 'date' => $date, 'party' => $party,
            'count' => count($slots), 'selected' => $selectedTime, 'slot_key' => $slotKey,
            'slots' => $slots,
        ]);

        if ($selectedTime && ! in_array($selectedTime, $slots, true)) {
            $slots[] = $selectedTime;
            $slots = array_values(array_unique($slots));
            Log::info('Flow: APPOINTMENT slots (inject_selected)', [
                'selected' => $selectedTime, 'count' => count($slots),
            ]);
        }

        // ⚠️ Schema-perfect items: id, title, enabled, image
        $placeholderImg = $this->assets->imageStrForPicker(null);
        $opts = array_map(function (string $t) use ($locale, $placeholderImg) {
            return [
                'id' => $t,
                'title' => $this->i18n->humanTime($t, $locale),
                'enabled' => true,
                'image' => $placeholderImg, // ensure 'image' is always present
            ];
        }, $slots);

        $payload['time'] = array_values($opts);
        $payload['is_time_enabled'] = ! empty($opts);  // enabled only when we have data
        $payload['show_time'] = ! empty($opts);  // visible only when we have data

        Log::info('Flow: APPOINTMENT slots (final)', [
            'count' => count($payload['time']),
            'has_selected' => $selectedTime
                ? in_array($selectedTime, array_column($payload['time'], 'id'), true)
                : null,
        ]);

        return $payload;
    }

    private function includeDaysFor(int $branchId): array
    {
        // Map 0..6 → Sun..Sat exactly as your rules use them
        $abbr = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $days = \App\Models\BranchAvailabilityRule::query()
            ->where('branch_id', $branchId)
            ->where('is_open', 1)
            ->pluck('day_of_week')
            ->unique()
            ->map(fn ($d) => $abbr[(int) $d] ?? null)
            ->filter()
            ->values()
            ->all();

        return $days ?: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    }
}
