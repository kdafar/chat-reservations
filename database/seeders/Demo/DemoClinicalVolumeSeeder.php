<?php

namespace Database\Seeders\Demo;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\ClinicPackage;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\RestaurantTable;
use App\Models\Visit;
use App\Models\VisitCharge;
use App\Models\VisitPayment;
use App\Services\Clinic\VisitCostingService;
use App\Services\Clinic\VisitPackageService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds six months of clinical trading history.
 *
 * The estate had 287 visits in a single month at ~19 KWD each — about one
 * patient per branch per day, which is roughly 1/100th of what a 12-branch
 * group actually bills. Every trend chart drew one bar, and the moment a
 * realistic cost side went into the GL the P&L showed a catastrophic loss.
 *
 * This walks the same booking → check-in → treatment → payment → discharge path
 * the app itself uses (VisitCostingService drives the costing, the doctor
 * commission ledger and the revenue accrual through the existing observers), so
 * the ledger stays self-consistent. Carbon::setTestNow() backdates now() at
 * each step so timestamps land in the past rather than all reading "today".
 */
class DemoClinicalVolumeSeeder extends Seeder
{
    private const TZ = 'Asia/Kuwait';

    /** How far back to build. */
    private const DAYS_BACK = 180;

    /** Confirmed appointments to place on the books ahead of today. */
    private const DAYS_FORWARD = 21;

    /** Average appointments per branch per working day. */
    private const APPTS_PER_BRANCH_DAY = 3.4;

    private const P_NO_SHOW = 0.055;

    private const P_CANCELLED = 0.09;

    private const P_SPLIT_PAYMENT = 0.14;

    /** Bills left partly unpaid — the AR aging report needs something to age. */
    private const P_PART_PAID = 0.07;

    private const P_REFUND = 0.02;

    private const P_PACKAGE = 0.18;

    private const P_DISCOUNT = 0.12;

    private const METHODS = ['knet', 'knet', 'knet', 'visa', 'visa', 'cash'];

    private const SOURCES = ['reception', 'reception', 'whatsapp', 'whatsapp', 'website', 'phone', 'walk_in'];

    /** Cashier user per branch, resolved lazily — see cashierFor(). */
    private array $cashiers = [];

    /**
     * Who took the money. Payments must carry a collector: the daily
     * reconciliation groups by cashier, and voidPayment only lets the original
     * collector (or an admin) reverse a row — a NULL collector makes both
     * useless. Prefers the branch's reception user, then any of its staff.
     */
    protected function cashierFor(int $branchId): ?int
    {
        if (array_key_exists($branchId, $this->cashiers)) {
            return $this->cashiers[$branchId];
        }

        $pick = fn (array $roles) => DB::table('users as u')
            ->join('branch_user as bu', 'bu.user_id', '=', 'u.id')
            ->join('model_has_roles as mr', function ($j) {
                $j->on('mr.model_id', '=', 'u.id')->where('mr.model_type', '=', \App\Models\User::class);
            })
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('bu.branch_id', $branchId)
            ->whereIn('r.name', $roles)
            ->value('u.id');

        return $this->cashiers[$branchId] = (int) ($pick(['clinic_reception'])
            ?: $pick(['clinic_admin', 'clinic_nurse', 'accountant'])) ?: null;
    }

    /**
     * The treatment menu that carries the money. Aesthetic work is billed per
     * procedure, so a visit is a consultation plus one or two of these — which
     * is what lifts the average ticket from ~19 KWD to a realistic ~110 KWD.
     *
     * [label, price, weight]
     */
    private const TREATMENTS = [
        ['Botulinum toxin — one area', 65.000, 8],
        ['Botulinum toxin — three areas', 145.000, 6],
        ['Dermal filler — 1ml', 130.000, 6],
        ['Lip filler — 1ml', 120.000, 5],
        ['Laser hair removal — small area', 35.000, 10],
        ['Laser hair removal — full body', 180.000, 4],
        ['HydraFacial — signature', 55.000, 9],
        ['Chemical peel', 45.000, 7],
        ['Microneedling (Dermapen)', 60.000, 6],
        ['Mesotherapy — scalp', 75.000, 5],
        ['PRP — facial rejuvenation', 95.000, 4],
        ['PRP — hair restoration', 110.000, 4],
        ['Carbon laser peel', 50.000, 6],
        ['Skin lesion / mole removal', 40.000, 5],
        ['IV vitamin infusion', 45.000, 5],
        ['Fractional CO2 laser resurfacing', 150.000, 3],
        ['HIFU lifting — full face', 220.000, 2],
        ['Cryolipolysis — one cycle', 130.000, 3],
        ['Dermatology review & prescription', 12.000, 8],
    ];

    private const FIRST_NAMES = [
        'Fatima', 'Noura', 'Aisha', 'Maryam', 'Hessa', 'Dalal', 'Shaikha', 'Amal', 'Latifa', 'Munira',
        'Abdullah', 'Mohammed', 'Yousef', 'Khaled', 'Faisal', 'Bader', 'Salem', 'Nasser', 'Talal', 'Jaber',
        'Sara', 'Layla', 'Hanan', 'Reem', 'Ghadeer', 'Bashayer', 'Wafa', 'Nada', 'Asma', 'Rana',
    ];

    private const LAST_NAMES = [
        'Al-Sabah', 'Al-Mutairi', 'Al-Ajmi', 'Al-Rashidi', 'Al-Enezi', 'Al-Otaibi', 'Al-Shammari',
        'Al-Hajri', 'Al-Dosari', 'Al-Qallaf', 'Al-Failakawi', 'Al-Kandari', 'Al-Ansari', 'Al-Fadhli',
        'Al-Awadhi', 'Al-Sayegh', 'Al-Baghli', 'Al-Duaij', 'Al-Ghanim', 'Al-Marzouq',
    ];

    private const COMPLAINTS = [
        'Concerned about fine lines around the eyes',
        'Recurrent acne with post-inflammatory marks',
        'Unwanted facial and body hair',
        'Skin dullness and uneven tone',
        'Hair thinning at the crown over the last six months',
        'Wants volume restoration in the mid-face',
        'Pigmentation patches on the cheeks',
        'Stubborn fat on the flanks despite diet',
        'Enlarged pores and oily skin',
        'Follow-up after a previous treatment course',
    ];

    public function run(): void
    {
        $existing = Visit::query()->withoutGlobalScopes()->count();
        if ($existing > 2000) {
            $this->command?->warn("DemoClinicalVolumeSeeder: {$existing} visits already present — skipping.");

            return;
        }

        // Thousands of visits each fire a dozen queries through the observers.
        // Telescope and the query log hold every one of them in memory and will
        // exhaust the process long before the run finishes.
        DB::connection()->disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $doctorsByBranch = Doctor::query()->withoutGlobalScopes()
            ->where('is_active', true)->whereNotNull('branch_id')
            ->with('compensationProfile')->get()->groupBy('branch_id');
        $roomsByBranch = RestaurantTable::query()->get()->groupBy('branch_id');
        $branches = Branch::query()->orderBy('id')->get(['id', 'partner_id']);

        // The package catalogue is global (partner_id / branch_id are null), so a
        // package is offered at every branch unless it names one. Grouping by
        // partner would match nothing and silently sell no packages at all.
        $allPackages = ClinicPackage::query()->withoutGlobalScopes()->where('is_active', true)->get();
        $packagesFor = fn ($branch) => $allPackages->filter(
            fn ($p) => (! $p->partner_id || (int) $p->partner_id === (int) $branch->partner_id)
                && (! $p->branch_id || (int) $p->branch_id === (int) $branch->id)
        )->values();

        $patientsByPartner = $this->seedPatients($branches);

        $totals = ['visits' => 0, 'no_show' => 0, 'cancelled' => 0, 'future' => 0, 'failed' => 0];
        $realToday = Carbon::today(self::TZ);
        $start = $realToday->copy()->subDays(self::DAYS_BACK);
        $days = self::DAYS_BACK + self::DAYS_FORWARD;

        for ($d = 0; $d <= $days; $d++) {
            Carbon::setTestNow();
            $date = $start->copy()->addDays($d);
            $isFuture = $date->gt($realToday);
            $isToday = $date->isSameDay($realToday);

            // Fridays are quiet; the estate has also been growing, so older
            // months run lighter than recent ones.
            $growth = 0.62 + (0.38 * ($d / max(1, $days)));
            $density = ($date->isFriday() ? 0.45 : 1.0) * $growth;

            foreach ($branches as $branch) {
                $doctors = $doctorsByBranch->get($branch->id);
                $rooms = $roomsByBranch->get($branch->id);
                $patients = $patientsByPartner[$branch->partner_id] ?? [];
                if (! $doctors || $doctors->isEmpty() || ! $rooms || $rooms->isEmpty() || ! $patients) {
                    continue;
                }

                $count = (int) round(self::APPTS_PER_BRANCH_DAY * $density * (random_int(70, 130) / 100));
                if ($isFuture) {
                    // Forward book lighter — the diary fills up as the date nears.
                    $count = (int) round($count * 0.55);
                }

                for ($i = 0; $i < max(0, $count); $i++) {
                    $doctor = $doctors->random();
                    $room = $rooms->random();
                    $patientId = $patients[array_rand($patients)];
                    $slot = $date->copy()->setTime(random_int(9, 18), [0, 15, 30, 45][random_int(0, 3)], 0);

                    try {
                        $outcome = $this->buildAppointment(
                            $slot, (int) $branch->id, $doctor, $room, $patientId,
                            $packagesFor($branch),
                            $isFuture, $isToday,
                        );
                        $totals[$outcome]++;
                    } catch (\Throwable $e) {
                        $totals['failed']++;
                        if ($totals['failed'] <= 5) {
                            $this->command?->warn('  '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');
                        }
                    }
                }
            }

            if ($d % 20 === 19) {
                Carbon::setTestNow();
                $this->command?->info("  day {$d}/{$days} — {$totals['visits']} visits so far");
            }
        }

        Carbon::setTestNow();
        $this->command?->info(sprintf(
            'DemoClinicalVolumeSeeder: %d completed visits, %d no-shows, %d cancellations, %d future bookings (%d failed).',
            $totals['visits'], $totals['no_show'], $totals['cancelled'], $totals['future'], $totals['failed'],
        ));
    }

    /**
     * Extra patients registered across the whole window, so "new vs returning"
     * and patient-growth panels have a real intake curve.
     *
     * @return array<int, int[]> patient ids keyed by partner id
     */
    protected function seedPatients($branches): array
    {
        $byPartner = [];
        $partnerIds = $branches->pluck('partner_id')->unique()->values();

        foreach ($partnerIds as $partnerId) {
            $byPartner[$partnerId] = Patient::query()->where('partner_id', $partnerId)->pluck('id')->all();
        }

        if (Patient::query()->count() >= 900) {
            return $byPartner;
        }

        $created = 0;
        foreach ($partnerIds as $partnerId) {
            for ($n = 0; $n < 170; $n++) {
                $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
                $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
                // Registration dates spread across the window — weighted towards
                // recent months so the intake curve trends upward.
                $registered = Carbon::today(self::TZ)->subDays((int) round(self::DAYS_BACK * (1 - sqrt(random_int(0, 100) / 100))));

                $patient = Patient::create([
                    'partner_id' => $partnerId,
                    'name' => $first.' '.$last,
                    'phone' => '+9656'.random_int(1000000, 9999999),
                    'email' => Str::slug($first.'.'.$last).random_int(10, 9999).'@example.kw',
                    'dob' => Carbon::today()->subYears(random_int(19, 62))->subDays(random_int(0, 364))->toDateString(),
                    'gender' => random_int(1, 100) <= 72 ? 'female' : 'male',
                    'civil_id' => (string) random_int(200000000000, 312999999999),
                    'blood_group' => ['O+', 'A+', 'B+', 'AB+', 'O-', 'A-'][random_int(0, 5)],
                    'created_at' => $registered,
                    'updated_at' => $registered,
                ]);
                $byPartner[$partnerId][] = $patient->id;
                $created++;
            }
        }

        $this->command?->info("DemoClinicalVolumeSeeder: registered {$created} additional patients.");

        return $byPartner;
    }

    /** @return string one of visits|no_show|cancelled|future */
    protected function buildAppointment(
        Carbon $slot, int $branchId, Doctor $doctor, RestaurantTable $room, int $patientId,
        $packages, bool $isFuture, bool $isToday,
    ): string {
        $patient = Patient::find($patientId);
        if (! $patient) {
            return 'failed';
        }

        $end = $slot->copy()->addMinutes((int) ($doctor->default_slot_minutes ?: 30));
        $code = strtoupper(Str::random(6));
        $source = self::SOURCES[array_rand(self::SOURCES)];
        $bookedAt = $slot->copy()->subDays(random_int(1, 12))->setTime(random_int(9, 20), random_int(0, 59));

        if ($isFuture) {
            Carbon::setTestNow($bookedAt->isFuture() ? Carbon::now(self::TZ) : $bookedAt);
            Booking::create($this->bookingRow($slot, $end, $branchId, $doctor, $room, $patient, $code, $source, 'confirmed'));
            Carbon::setTestNow();

            return 'future';
        }

        $roll = mt_rand() / mt_getrandmax();

        if ($roll < self::P_NO_SHOW) {
            Carbon::setTestNow($bookedAt);
            $booking = Booking::create($this->bookingRow($slot, $end, $branchId, $doctor, $room, $patient, $code, $source, 'confirmed'));
            Carbon::setTestNow($slot->copy()->addMinutes(45));
            // no_show_at must be stamped, not just the status: the dashboard's
            // lost-revenue and no-show analytics key off the timestamp.
            $booking->update(['status' => 'no_show', 'no_show_at' => Carbon::now()]);
            Carbon::setTestNow();

            return 'no_show';
        }

        if ($roll < self::P_NO_SHOW + self::P_CANCELLED) {
            Carbon::setTestNow($bookedAt);
            $booking = Booking::create($this->bookingRow($slot, $end, $branchId, $doctor, $room, $patient, $code, $source, 'confirmed'));
            Carbon::setTestNow($slot->copy()->subHours(random_int(2, 60)));
            // The cancellation analysis on the executive dashboard filters on
            // cancelled_at and groups by cancellation_reason_code — a reason
            // parked only in meta is invisible to it.
            $reasons = ['patient_rescheduled', 'patient_emergency', 'no_answer', 'price_high', 'found_other_clinic'];
            $reason = $reasons[random_int(0, count($reasons) - 1)];
            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => Carbon::now(),
                'cancellation_reason_code' => $reason,
                'meta' => array_merge((array) $booking->meta, ['cancel_reason' => $reason]),
            ]);
            Carbon::setTestNow();

            return 'cancelled';
        }

        $this->runVisit($slot, $end, $branchId, $doctor, $room, $patient, $code, $source, $bookedAt, $packages, $isToday);
        Carbon::setTestNow();

        return 'visits';
    }

    protected function bookingRow(Carbon $slot, Carbon $end, int $branchId, Doctor $doctor, RestaurantTable $room, Patient $patient, string $code, string $source, string $status): array
    {
        return [
            'branch_id' => $branchId,
            'doctor_id' => $doctor->id,
            'msisdn' => $patient->phone,
            'patient_id' => $patient->id,
            'party_size' => 1,
            'res_date' => $slot->toDateString(),
            'res_time' => $slot->format('H:i:s'),
            'res_start' => $slot,
            'res_end' => $end,
            'status' => $status,
            'booking_code' => $code,
            'table_id' => $room->id,
            'source' => $source,
        ];
    }

    /** The full clinical + billing path for one attended appointment. */
    protected function runVisit(
        Carbon $slot, Carbon $end, int $branchId, Doctor $doctor, RestaurantTable $room,
        Patient $patient, string $code, string $source, Carbon $bookedAt, $packages, bool $isToday,
    ): void {
        $fee = (float) ($doctor->consultation_fee ?: 15);

        // 1. Booking taken.
        Carbon::setTestNow($bookedAt);
        $booking = Booking::create($this->bookingRow($slot, $end, $branchId, $doctor, $room, $patient, $code, $source, 'confirmed'));

        // 2. Visit opened at reception, consultation fee charged up front.
        Carbon::setTestNow($slot->copy()->subMinutes(random_int(4, 25)));
        $visit = Visit::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'branch_id' => $branchId,
                'restaurant_table_id' => $room->id,
                'source' => $source,
                'booking_code' => $code,
                'status' => 'created',
            ],
        );

        VisitCharge::create([
            'visit_id' => $visit->id,
            'branch_id' => $branchId,
            'label' => 'Consultation Fee',
            'qty' => 1,
            'unit_price_snapshot' => $fee,
            'line_total' => $fee,
            'added_by_user_id' => null,
        ]);

        // 3. Check in, then the doctor takes the patient through.
        Carbon::setTestNow($slot);
        $booking->update(['checked_in_at' => Carbon::now()]);
        $visit->update(['status' => 'awaiting_doctor', 'checked_in_at' => Carbon::now(), 'queued_at' => Carbon::now()]);

        Carbon::setTestNow($slot->copy()->addMinutes(random_int(3, 20)));
        $visit->update([
            'status' => 'in_progress',
            'accepted_at' => Carbon::now(),
            'accepted_by_user_id' => $doctor->user_id,
        ]);

        // 4. Treatments performed — this is where the money is.
        $this->addTreatments($visit, $branchId);

        // 5. Some visits sell a package on top.
        if ($packages->isNotEmpty() && mt_rand() / mt_getrandmax() < self::P_PACKAGE) {
            try {
                app(VisitPackageService::class)->applyPackagesOnly(
                    $visit, [['clinic_package_id' => $packages->random()->id, 'qty' => 1]], 0, 'demo seed',
                );
            } catch (\Throwable) {
                // package unavailable at this branch — carry on without it
            }
        }

        // 6. Clinical note, so the visit isn't an empty shell on screen.
        $visit->update([
            'chief_complaint' => self::COMPLAINTS[array_rand(self::COMPLAINTS)],
            'diagnosis' => ['Photoaging', 'Acne vulgaris', 'Melasma', 'Androgenetic alopecia', 'Hirsutism', 'Post-inflammatory hyperpigmentation', 'Skin laxity'][random_int(0, 6)],
            'patient_instructions' => 'Avoid direct sun for 48 hours, apply SPF 50 daily, and use the prescribed moisturiser twice a day.',
            'follow_up_date' => random_int(1, 100) <= 35 ? $slot->copy()->addDays([14, 21, 28, 42][random_int(0, 3)])->toDateString() : null,
        ]);

        // 7. Discount, on the visits where one was negotiated.
        if (mt_rand() / mt_getrandmax() < self::P_DISCOUNT) {
            $visit->update([
                'discount_total' => (float) [5.000, 10.000, 15.000, 20.000, 25.000][random_int(0, 4)],
                'discount_type' => 'amount',
            ]);
        }

        // 8. Treatment finishes; bill goes to reception.
        Carbon::setTestNow($slot->copy()->addMinutes(random_int(25, 75)));
        $visit->refresh();
        $visit->update(['status' => 'awaiting_payment', 'restaurant_table_id' => null]);

        // 9. Cost the visit, then take payment.
        Carbon::setTestNow($slot->copy()->addMinutes(random_int(75, 110)));
        app(VisitCostingService::class)->compute($visit, 0);
        $visit->refresh();

        $due = (float) $visit->fees_total + (float) $visit->packages_price_total
            + (float) $visit->items_price_total - (float) $visit->discount_total;
        $this->collect($visit, max(0, $due), $code);

        // 10. Today's diary keeps a spread of live stages for the queue screens.
        if ($isToday) {
            $stage = mt_rand() / mt_getrandmax();
            if ($stage < 0.22) {
                // Rewinding to the queue must also drop the acceptance stamps,
                // otherwise the visit looks claimed and the doctor can't start it.
                $visit->update([
                    'status' => 'awaiting_doctor',
                    'accepted_at' => null,
                    'accepted_by_user_id' => null,
                    'service_started_at' => null,
                ]);
                return;
            }
            if ($stage < 0.42) {
                $visit->update(['status' => 'in_progress']);
                return;
            }
            if ($stage < 0.60) {
                return; // stays in awaiting_payment
            }
        }

        // 11. Discharge — recompute so the doctor commission ledger writes.
        Carbon::setTestNow($slot->copy()->addMinutes(random_int(110, 150)));
        $booking->update([
            'status' => 'completed',
            'meta' => array_merge((array) $booking->meta, ['checked_out_at' => Carbon::now()->toDateTimeString()]),
        ]);
        $visit->update(['status' => 'completed']);
        app(VisitCostingService::class)->compute($visit->refresh(), 0);

        // 12. The occasional refund, days later.
        if (mt_rand() / mt_getrandmax() < self::P_REFUND) {
            Carbon::setTestNow($slot->copy()->addDays(random_int(1, 6)));
            $payment = $visit->payments()->where('status', 'paid')->latest('id')->first();
            if ($payment) {
                $payment->update(['status' => 'refunded']);
                app(VisitCostingService::class)->compute($visit->refresh(), 0);
            }
        }
    }

    /** One to three procedures per visit, weighted so common work dominates. */
    protected function addTreatments(Visit $visit, int $branchId): void
    {
        static $pool = null;
        if ($pool === null) {
            $pool = [];
            foreach (self::TREATMENTS as $i => [, , $weight]) {
                $pool = array_merge($pool, array_fill(0, $weight, $i));
            }
        }

        $count = random_int(1, 100) <= 62 ? 1 : (random_int(1, 100) <= 78 ? 2 : 3);
        $used = [];

        for ($n = 0; $n < $count; $n++) {
            $idx = $pool[array_rand($pool)];
            if (in_array($idx, $used, true)) {
                continue;
            }
            $used[] = $idx;
            [$label, $price] = self::TREATMENTS[$idx];
            $qty = ($price <= 60 && random_int(1, 100) <= 25) ? random_int(2, 3) : 1;

            VisitCharge::create([
                'visit_id' => $visit->id,
                'branch_id' => $branchId,
                'label' => $label,
                'qty' => $qty,
                'unit_price_snapshot' => $price,
                'line_total' => round($price * $qty, 3),
                'added_by_user_id' => null,
            ]);
        }
    }

    /** Settle the bill: usually in full, sometimes split, occasionally part-paid. */
    protected function collect(Visit $visit, float $due, string $code): void
    {
        $paid = (float) $visit->payments()->where('status', 'paid')->sum('amount');
        $remaining = round($due - $paid, 3);
        if ($remaining <= 0.005) {
            return;
        }

        $roll = mt_rand() / mt_getrandmax();
        $cashier = $this->cashierFor((int) $visit->branch_id);

        if ($roll < self::P_PART_PAID && $remaining > 20) {
            // Deposit taken, balance still owed — feeds the AR aging report.
            VisitPayment::create([
                'visit_id' => $visit->id,
                'amount' => round($remaining * (random_int(30, 60) / 100), 3),
                'method' => self::METHODS[array_rand(self::METHODS)],
                'status' => 'paid',
                'kind' => 'services',
                'reference_no' => 'DEP-'.$code,
                'paid_at' => Carbon::now(),
                'collected_by_user_id' => $cashier,
            ]);

            return;
        }

        if ($roll < self::P_PART_PAID + self::P_SPLIT_PAYMENT && $remaining > 2) {
            $half = round($remaining / 2, 3);
            VisitPayment::create([
                'visit_id' => $visit->id, 'amount' => $half,
                'method' => self::METHODS[array_rand(self::METHODS)],
                'status' => 'paid', 'kind' => 'services', 'paid_at' => Carbon::now(),
                'collected_by_user_id' => $cashier,
            ]);
            VisitPayment::create([
                'visit_id' => $visit->id, 'amount' => round($remaining - $half, 3),
                'method' => self::METHODS[array_rand(self::METHODS)],
                'status' => 'paid', 'kind' => 'services', 'paid_at' => Carbon::now()->addMinutes(3),
                'collected_by_user_id' => $cashier,
            ]);

            return;
        }

        VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => $remaining,
            'method' => self::METHODS[array_rand(self::METHODS)],
            'status' => 'paid',
            'kind' => 'services',
            'reference_no' => 'PAY-'.$code,
            'paid_at' => Carbon::now(),
            'collected_by_user_id' => $cashier,
        ]);
    }
}
