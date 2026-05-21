<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Models\ClinicPackage;
use App\Models\Doctor;
use App\Models\DoctorCompensationProfile;
use App\Models\Patient;
use App\Models\RestaurantTable;
use App\Models\Visit;
use App\Models\VisitCharge;
use App\Models\VisitPayment;
use App\Services\Clinic\VisitChargeService;
use App\Services\Clinic\VisitCostingService;
use App\Services\Clinic\VisitPackageService;
use App\Services\Clinic\VisitStockRequestService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Wipes clinic operational data + patients, then generates 40 days of realistic
 * activity (30 days past + 10 future) covering every scenario the audit fixes
 * touched: split payments, refunds, packages, stock-outs, discounts, no-shows,
 * cancellations.
 *
 * Run with:
 *   php artisan db:seed --class=ClinicFreshMonthSeeder
 */
class ClinicFreshMonthSeeder extends Seeder
{
    private const TZ = 'Asia/Kuwait';

    private const DAYS_BACK = 30;

    private const DAYS_FORWARD = 10;

    private const AVG_VISITS_PER_DAY = 12;

    /** Probabilities, sum may exceed 1 (independent rolls) */
    private const P_NO_SHOW = 0.05;

    private const P_CANCELLED = 0.10;

    private const P_SPLIT_PAYMENT = 0.15;

    private const P_REFUND = 0.03;

    private const P_PACKAGE = 0.30;

    private const P_STOCK_OUT = 0.05;

    private const P_DISCOUNT = 0.08;

    /** First names for Kuwait market */
    private const FIRST_NAMES = [
        'Ahmed', 'Mohammed', 'Abdullah', 'Khalid', 'Yousef', 'Saud', 'Fahad', 'Bader', 'Hamad', 'Ali',
        'Fatima', 'Aisha', 'Maryam', 'Nour', 'Sara', 'Hala', 'Dana', 'Reem', 'Salma', 'Layla',
    ];

    private const LAST_NAMES = [
        'Al-Sabah', 'Al-Mutairi', 'Al-Otaibi', 'Al-Rashidi', 'Al-Ajmi', 'Al-Enezi', 'Al-Awadi',
        'Al-Khaldi', 'Al-Salem', 'Al-Saleh', 'Al-Bader', 'Al-Khaled', 'Al-Sayed',
    ];

    private const PAYMENT_METHODS_MANUAL = ['cash', 'knet', 'visa'];

    public function run(): void
    {
        $this->command->info('=== ClinicFreshMonthSeeder ===');

        $this->wipeClinicOperationalData();
        $this->ensureDoctorCompProfiles();
        $this->refillStocks();
        $patientIds = $this->seedPatients(20);
        $this->seedMonthlyActivity($patientIds);

        Carbon::setTestNow(); // reset
        $this->command->info('Done.');
    }

    // -------------------------------------------------------------------------
    // 1) Wipe
    // -------------------------------------------------------------------------
    private function wipeClinicOperationalData(): void
    {
        $this->command->info('Wiping clinic operational data...');

        $tables = [
            // children first
            'visit_payments',
            'visit_stock_request_lines',
            'visit_stock_requests',
            'visit_packages',
            'visit_charges',
            'visit_items',
            'follow_up_plans',
            'doctor_compensation_ledgers',
            'clinic_stock_movements',
            'clinic_item_stocks',
            // operational core
            'visits',
            'bookings',
            'booking_holds',
            'doctor_shifts',
            'patients',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $t) {
            DB::table($t)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('Wiped: '.implode(', ', $tables));
    }

    // -------------------------------------------------------------------------
    // 2) Ensure every doctor has a compensation profile
    // -------------------------------------------------------------------------
    private function ensureDoctorCompProfiles(): void
    {
        $defaults = [
            // doctor_id => [type, basis, percentage_rate]
            25 => ['percentage', 'fees_only', 40.0],
            26 => ['percentage', 'net_profit', 25.0],
            27 => ['salary', 'fees_only', null],
            28 => ['percentage', 'fees_only', 30.0],
        ];

        foreach (Doctor::query()->get() as $doc) {
            $exists = DoctorCompensationProfile::query()
                ->where('doctor_id', $doc->id)
                ->where('is_active', 1)
                ->exists();
            if ($exists) {
                continue;
            }

            [$type, $basis, $rate] = $defaults[$doc->id] ?? ['percentage', 'fees_only', 30.0];

            DoctorCompensationProfile::create([
                'doctor_id' => $doc->id,
                'type' => $type,
                'basis' => $basis,
                'percentage_rate' => $rate,
                'is_active' => 1,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // 3) Refill stocks generously for every stockable item per branch
    // -------------------------------------------------------------------------
    private function refillStocks(): void
    {
        $this->command->info('Refilling stocks...');

        $branchIds = Branch::pluck('id')->all();
        $stockables = ClinicItem::query()->where('is_stockable', true)->where('is_active', true)->get();

        foreach ($branchIds as $branchId) {
            foreach ($stockables as $item) {
                // 1000 base units per item per branch — plenty for a month
                ClinicItemStock::query()->updateOrCreate(
                    ['branch_id' => $branchId, 'clinic_item_id' => $item->id],
                    ['qty_on_hand_base' => 1000.0]
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // 4) Seed patients with Kuwait-style phones
    // -------------------------------------------------------------------------
    private function seedPatients(int $n): array
    {
        $this->command->info("Seeding {$n} patients...");

        $partnerIds = DB::table('partners')->pluck('id')->all();
        $ids = [];

        for ($i = 0; $i < $n; $i++) {
            $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
            $name = "{$first} {$last}";

            $phone = '+9655'.random_int(0, 9).random_int(1000000, 9999999);

            $p = Patient::create([
                'partner_id' => $partnerIds[array_rand($partnerIds)],
                'name' => $name,
                'phone' => $phone,
                'gender' => ['male', 'female'][random_int(0, 1)],
                'dob' => Carbon::now()->subYears(random_int(18, 70))->subDays(random_int(0, 364))->toDateString(),
            ]);

            $ids[] = $p->id;
        }

        return $ids;
    }

    // -------------------------------------------------------------------------
    // 5) Generate 40 days of activity
    // -------------------------------------------------------------------------
    private function seedMonthlyActivity(array $patientIds): void
    {
        $this->command->info('Seeding 40 days of activity...');

        $totalDays = self::DAYS_BACK + self::DAYS_FORWARD;
        $start = Carbon::today(self::TZ)->subDays(self::DAYS_BACK);

        // Pre-load FK data
        $doctors = Doctor::with('compensationProfile')->get();
        $branchById = Branch::pluck('id', 'id')->all();
        $roomsByBranch = RestaurantTable::query()->get()->groupBy('branch_id');
        $packagesByBranch = ClinicPackage::query()->where('is_active', true)->get()->groupBy('branch_id');
        $billableItemsByBranch = ClinicItem::query()
            ->where('is_active', true)->where('is_billable', true)
            ->get()->groupBy('branch_id');

        $totalGenerated = 0;
        // Capture the real "today" before any setTestNow() calls inside the inner loop pollute it.
        $realToday = Carbon::today(self::TZ);

        for ($d = 0; $d < $totalDays; $d++) {
            // Reset any prior setTestNow leak before we classify this date.
            Carbon::setTestNow();

            $date = $start->copy()->addDays($d);
            $isPast = $date->lt($realToday);
            $isToday = $date->isSameDay($realToday);
            $isFuture = $date->gt($realToday);

            // Quieter Fridays (weekend in Kuwait is Fri-Sat)
            $density = $date->isFriday() ? 0.5 : 1.0;
            $count = max(1, (int) round(self::AVG_VISITS_PER_DAY * $density * (random_int(80, 120) / 100)));

            for ($i = 0; $i < $count; $i++) {
                $doctor = $doctors->random();
                $branchId = (int) $doctor->branch_id;
                $rooms = $roomsByBranch->get($branchId) ?? collect();
                if ($rooms->isEmpty()) {
                    continue;
                }
                $room = $rooms->random();
                $patientId = $patientIds[array_rand($patientIds)];

                // Pick an hour 9..16, minute 00/15/30/45
                $hour = random_int(9, 16);
                $minute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
                $apptStart = $date->copy()->setTime($hour, $minute, 0);
                $apptEnd = $apptStart->copy()->addMinutes(30);

                try {
                    $this->generateBookingFlow(
                        apptStart: $apptStart,
                        apptEnd: $apptEnd,
                        branchId: $branchId,
                        doctor: $doctor,
                        room: $room,
                        patientId: $patientId,
                        packagesForBranch: $packagesByBranch->get($branchId) ?? collect(),
                        billableItemsForBranch: $billableItemsByBranch->get($branchId) ?? collect(),
                        isPast: $isPast,
                        isToday: $isToday,
                        isFuture: $isFuture,
                    );
                    $totalGenerated++;
                } catch (\Throwable $e) {
                    $this->command->warn("  day {$d} visit #{$i} failed: ".$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');
                }
            }

            if ($d % 5 === 4) {
                $this->command->info("  ...day {$d}/{$totalDays} processed ({$totalGenerated} bookings so far)");
            }
        }

        $this->command->info("Total bookings generated: {$totalGenerated}");
    }

    /**
     * Build a single booking with its downstream visit/payments/packages depending on scenario.
     * Uses Carbon::setTestNow() to backdate now() so model events and service calls
     * stamp the right historical timestamps.
     */
    private function generateBookingFlow(
        Carbon $apptStart,
        Carbon $apptEnd,
        int $branchId,
        Doctor $doctor,
        RestaurantTable $room,
        int $patientId,
        $packagesForBranch,
        $billableItemsForBranch,
        bool $isPast,
        bool $isToday,
        bool $isFuture,
    ): void {
        // ------ Decide scenario ------
        $roll = mt_rand() / mt_getrandmax();

        if ($isFuture) {
            // Future bookings: just create the booking row, status='confirmed', no visit yet.
            $this->createFutureBooking($apptStart, $apptEnd, $branchId, $doctor, $room, $patientId);

            return;
        }

        if ($isPast && $roll < self::P_NO_SHOW) {
            $this->createNoShowBooking($apptStart, $apptEnd, $branchId, $doctor, $room, $patientId);

            return;
        }
        if ($isPast && $roll < self::P_NO_SHOW + self::P_CANCELLED) {
            $this->createCancelledBooking($apptStart, $apptEnd, $branchId, $doctor, $room, $patientId);

            return;
        }

        // Confirmed → full visit flow
        $this->createConfirmedFlow(
            $apptStart, $apptEnd, $branchId, $doctor, $room, $patientId,
            $packagesForBranch, $billableItemsForBranch, $isToday
        );
    }

    private function createFutureBooking(Carbon $apptStart, Carbon $apptEnd, int $branchId, Doctor $doctor, RestaurantTable $room, int $patientId): void
    {
        Carbon::setTestNow(Carbon::now(self::TZ));
        $patient = Patient::find($patientId);

        Booking::create([
            'branch_id' => $branchId,
            'doctor_id' => $doctor->id,
            'msisdn' => $patient->phone,
            'patient_id' => $patientId,
            'party_size' => 1,
            'res_date' => $apptStart->toDateString(),
            'res_time' => $apptStart->format('H:i:s'),
            'res_start' => $apptStart,
            'res_end' => $apptEnd,
            'status' => 'confirmed',
            'booking_code' => strtoupper(Str::random(6)),
            'table_id' => $room->id,
            'source' => 'reception',
        ]);
    }

    private function createNoShowBooking(Carbon $apptStart, Carbon $apptEnd, int $branchId, Doctor $doctor, RestaurantTable $room, int $patientId): void
    {
        // Use end-of-appointment as the "now" for no_show_at
        Carbon::setTestNow($apptEnd->copy()->addMinutes(30));
        $patient = Patient::find($patientId);

        Booking::create([
            'branch_id' => $branchId,
            'doctor_id' => $doctor->id,
            'msisdn' => $patient->phone,
            'patient_id' => $patientId,
            'party_size' => 1,
            'res_date' => $apptStart->toDateString(),
            'res_time' => $apptStart->format('H:i:s'),
            'res_start' => $apptStart,
            'res_end' => $apptEnd,
            'status' => 'no_show',
            'no_show_at' => Carbon::now(),
            'booking_code' => strtoupper(Str::random(6)),
            'table_id' => $room->id,
            'source' => 'reception',
        ]);
    }

    private function createCancelledBooking(Carbon $apptStart, Carbon $apptEnd, int $branchId, Doctor $doctor, RestaurantTable $room, int $patientId): void
    {
        // Cancellation usually happens before the appointment
        Carbon::setTestNow($apptStart->copy()->subHours(random_int(1, 24)));
        $patient = Patient::find($patientId);

        Booking::create([
            'branch_id' => $branchId,
            'doctor_id' => $doctor->id,
            'msisdn' => $patient->phone,
            'patient_id' => $patientId,
            'party_size' => 1,
            'res_date' => $apptStart->toDateString(),
            'res_time' => $apptStart->format('H:i:s'),
            'res_start' => $apptStart,
            'res_end' => $apptEnd,
            'status' => 'cancelled',
            'cancelled_at' => Carbon::now(),
            'cancellation_reason_code' => ['price_high', 'patient_emergency', 'no_answer', 'found_other_clinic', 'rescheduled'][random_int(0, 4)],
            'booking_code' => strtoupper(Str::random(6)),
            'table_id' => $room->id,
            'source' => 'reception',
        ]);
    }

    private function createConfirmedFlow(
        Carbon $apptStart, Carbon $apptEnd, int $branchId, Doctor $doctor, RestaurantTable $room, int $patientId,
        $packagesForBranch, $billableItemsForBranch, bool $isToday,
    ): void {
        $consultationFee = (float) ($doctor->consultation_fee ?? 0);
        if ($consultationFee <= 0) {
            return; // can't run the flow without a fee
        }

        $patient = Patient::find($patientId);
        $bookingCode = strtoupper(Str::random(6));

        // ------- 1. Create booking (just before the appointment) -------
        Carbon::setTestNow($apptStart->copy()->subDays(random_int(1, 7))->setTime(random_int(9, 17), 0, 0));

        $booking = Booking::create([
            'branch_id' => $branchId,
            'doctor_id' => $doctor->id,
            'msisdn' => $patient->phone,
            'patient_id' => $patientId,
            'party_size' => 1,
            'res_date' => $apptStart->toDateString(),
            'res_time' => $apptStart->format('H:i:s'),
            'res_start' => $apptStart,
            'res_end' => $apptEnd,
            'status' => 'confirmed',
            'booking_code' => $bookingCode,
            'table_id' => $room->id,
            'source' => 'reception',
        ]);

        // ------- 2. Consultation collected just before appointment -------
        Carbon::setTestNow($apptStart->copy()->subMinutes(random_int(5, 30)));

        $visit = Visit::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'patient_id' => $patientId,
                'doctor_id' => $doctor->id,
                'branch_id' => $branchId,
                'restaurant_table_id' => $room->id,
                'source' => 'reception',
                'booking_code' => $bookingCode,
                'status' => 'created',
            ]
        );

        VisitCharge::create([
            'visit_id' => $visit->id,
            'branch_id' => $branchId,
            'label' => 'Consultation Fee',
            'qty' => 1,
            'unit_price_snapshot' => $consultationFee,
            'line_total' => $consultationFee,
            'added_by_user_id' => null,
        ]);

        VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => $consultationFee,
            'method' => self::PAYMENT_METHODS_MANUAL[array_rand(self::PAYMENT_METHODS_MANUAL)],
            'status' => 'paid',
            'kind' => 'consultation',
            'reference_no' => 'CONS-'.$bookingCode,
            'paid_at' => Carbon::now(),
        ]);

        // ------- 3. Check in at appointment time -------
        Carbon::setTestNow($apptStart);

        $booking->update(['checked_in_at' => Carbon::now()]);
        $visit->update([
            'status' => 'awaiting_doctor',
            'checked_in_at' => Carbon::now(),
            'queued_at' => Carbon::now(),
        ]);
        RestaurantTable::where('id', $room->id)->update(['status' => 'occupied']);

        // ------- 4. Doctor accepts a few minutes later -------
        Carbon::setTestNow($apptStart->copy()->addMinutes(random_int(2, 15)));

        $visit->update([
            'status' => 'in_progress', // booted() hook sets service_started_at
            'accepted_at' => Carbon::now(),
        ]);

        // ------- 5. Apply package (~30%) -------
        $stockOutHappened = false;
        if ($packagesForBranch->isNotEmpty() && mt_rand() / mt_getrandmax() < self::P_PACKAGE) {
            $pkg = $packagesForBranch->random();
            $qty = random_int(1, 2);

            try {
                app(VisitPackageService::class)->applyPackagesOnly(
                    $visit,
                    [['clinic_package_id' => $pkg->id, 'qty' => $qty]],
                    0,
                    'demo seed',
                );

                // Build requirements from package items
                $requirements = $pkg->items()->get()->map(fn ($it) => [
                    'clinic_item_id' => $it->clinic_item_id,
                    'qty_base' => (float) $it->qty_base * $qty,
                ])->all();

                $stockOutRoll = mt_rand() / mt_getrandmax();
                if ($stockOutRoll < self::P_STOCK_OUT) {
                    // Force stock-out by zeroing one item temporarily
                    $stockOutHappened = true;
                    if (! empty($requirements)) {
                        $first = $requirements[0]['clinic_item_id'];
                        ClinicItemStock::where('branch_id', $branchId)
                            ->where('clinic_item_id', $first)
                            ->update(['qty_on_hand_base' => 0]);
                    }
                    app(VisitStockRequestService::class)
                        ->createForVisit($visit, $requirements, 0, 'stock-out scenario', true);
                } else {
                    app(VisitStockRequestService::class)
                        ->issueOrRequestForVisit($visit, $requirements, 0, 'demo seed');
                }
            } catch (\Throwable $e) {
                // Insufficient stock from a previous deduction is acceptable — leave as-is
            }
        }

        // ------- 6. Maybe extra charges -------
        if (mt_rand() / mt_getrandmax() < 0.20) {
            try {
                app(VisitChargeService::class)->addCharge(
                    $visit,
                    ['Procedure Fee', 'Lab Test', 'Imaging', 'Extra Service'][random_int(0, 3)],
                    1,
                    (float) [5.000, 10.000, 15.000, 25.000][random_int(0, 3)],
                    0,
                );
            } catch (\Throwable) {
                // skip
            }
        }

        // ------- 7. Maybe discount -------
        if (mt_rand() / mt_getrandmax() < self::P_DISCOUNT) {
            $discount = (float) [2.000, 5.000, 10.000][random_int(0, 2)];
            $visit->update(['discount_total' => $discount]);
        }

        // ------- 8. If stock-out happened, stop here (visit stays in awaiting_stock) -------
        if ($stockOutHappened) {
            return;
        }

        // ------- 9. Finish treatment 15-45 min after accept -------
        Carbon::setTestNow($apptStart->copy()->addMinutes(random_int(15, 45)));

        $visit->refresh();
        if (in_array($visit->status, ['in_progress', 'awaiting_doctor'], true)) {
            $visit->update(['status' => 'awaiting_payment', 'restaurant_table_id' => null]);
            RestaurantTable::where('id', $room->id)->update(['status' => 'available']);
        }

        // ------- 10. Recompute + collect remaining balance -------
        Carbon::setTestNow($apptStart->copy()->addMinutes(random_int(45, 90)));

        app(VisitCostingService::class)->compute($visit, 0);
        $visit->refresh();

        $totalDue = (float) $visit->fees_total
            + (float) $visit->packages_price_total
            + (float) $visit->items_price_total
            - (float) $visit->discount_total;

        $alreadyPaid = (float) $visit->payments()->where('status', 'paid')->sum('amount');
        $remaining = max(0, $totalDue - $alreadyPaid);

        if ($remaining > 0.005) {
            $split = mt_rand() / mt_getrandmax() < self::P_SPLIT_PAYMENT;

            if ($split && $remaining > 1.0) {
                // Pay in 2 installments
                $half = round($remaining / 2, 3);
                VisitPayment::create([
                    'visit_id' => $visit->id,
                    'amount' => $half,
                    'method' => self::PAYMENT_METHODS_MANUAL[array_rand(self::PAYMENT_METHODS_MANUAL)],
                    'status' => 'paid',
                    'kind' => 'services',
                    'paid_at' => Carbon::now(),
                ]);
                VisitPayment::create([
                    'visit_id' => $visit->id,
                    'amount' => round($remaining - $half, 3),
                    'method' => self::PAYMENT_METHODS_MANUAL[array_rand(self::PAYMENT_METHODS_MANUAL)],
                    'status' => 'paid',
                    'kind' => 'services',
                    'paid_at' => Carbon::now()->addMinutes(2),
                ]);
            } else {
                VisitPayment::create([
                    'visit_id' => $visit->id,
                    'amount' => $remaining,
                    'method' => self::PAYMENT_METHODS_MANUAL[array_rand(self::PAYMENT_METHODS_MANUAL)],
                    'status' => 'paid',
                    'kind' => 'services',
                    'paid_at' => Carbon::now(),
                ]);
            }
        }

        // ------- 11. If today, leave some visits at different stages -------
        if ($isToday) {
            $stageRoll = mt_rand() / mt_getrandmax();
            if ($stageRoll < 0.20) {
                // Leave a fraction in awaiting_doctor (skip discharge)
                $visit->update(['status' => 'awaiting_doctor']);

                return;
            }
            if ($stageRoll < 0.40) {
                // Leave in_progress
                $visit->update(['status' => 'in_progress']);

                return;
            }
            if ($stageRoll < 0.60) {
                // Leave in awaiting_payment
                $visit->update(['status' => 'awaiting_payment']);

                return;
            }
            // Otherwise fall through to discharge
        }

        // ------- 12. Discharge -------
        Carbon::setTestNow($apptStart->copy()->addMinutes(random_int(90, 120)));

        $booking->update([
            'status' => 'completed',
            'meta' => array_merge((array) $booking->meta, ['checked_out_at' => Carbon::now()->toDateTimeString()]),
        ]);
        $visit->update(['status' => 'completed']); // booted() sets completed_at

        // Re-run compute() now that status='completed' so DoctorCompensationService::sync()
        // actually writes the ledger row (it bails out on non-completed visits).
        app(VisitCostingService::class)->compute($visit->refresh(), 0);

        // ------- 13. Maybe refund a previous payment (long after the visit) -------
        if (mt_rand() / mt_getrandmax() < self::P_REFUND) {
            Carbon::setTestNow($apptStart->copy()->addDays(random_int(1, 5)));

            $payment = $visit->payments()->where('status', 'paid')->latest('id')->first();
            if ($payment) {
                $payment->update(['status' => 'refunded']);
                app(VisitCostingService::class)->compute($visit->refresh(), 0);
            }
        }
    }
}
