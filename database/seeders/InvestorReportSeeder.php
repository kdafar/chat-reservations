<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\Doctor;
use App\Models\DoctorCompensationLedger;
use App\Models\DoctorShift;
use App\Models\FollowUpPlan;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitItem;
use App\Models\VisitPayment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvestorReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Purpose: Generate 1 month of "good" data to test investor/manager charts.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedData();
        });
    }

    private function seedData(): void
    {
        // Use app timezone (your app is Asia/Kuwait). Carbon will respect config('app.timezone').
        $startDate = now()->subDays(30)->startOfDay();
        $endDate = now()->endOfDay();

        // 1) Master Data (safe creates)
        $partner = Partner::firstOrCreate(
            ['slug' => 'hq-partner'],
            ['name' => ['en' => 'HQ Partner', 'ar' => 'الشريك الرئيسي']]
        );

        $branch = Branch::firstOrCreate(
            ['slug' => 'downtown-hq'],
            [
                'partner_id' => $partner->id,
                'name' => ['en' => 'Downtown HQ', 'ar' => 'الفرع الرئيسي'],
            ]
        );

        $doc1 = Doctor::firstOrCreate(
            ['name' => 'Dr. Ahmed (Derma)'],
            [
                'branch_id' => $branch->id,
                'partner_id' => $partner->id,
                'specialty' => 'Dermatology',
            ]
        );

        $doc2 = Doctor::firstOrCreate(
            ['name' => 'Dr. Sarah (Laser)'],
            [
                'branch_id' => $branch->id,
                'partner_id' => $partner->id,
                'specialty' => 'Laser Therapy',
            ]
        );

        $admin = User::first() ?? User::create([
            'name' => 'System Admin',
            'email' => 'admin@clinic.com',
            'password' => bcrypt('password'),
        ]);

        // Clinic items (services + product)
        $serviceBotox = ClinicItem::firstOrCreate(
            ['branch_id' => $branch->id, 'type' => 'service', 'default_price' => 120.000],
            [
                'name' => ['en' => 'Botox Full Face', 'ar' => 'بوتوكس كامل الوجه'],
                'default_cost' => 40.000,
                'is_active' => true,
            ]
        );

        $serviceLaser = ClinicItem::firstOrCreate(
            ['branch_id' => $branch->id, 'type' => 'service', 'default_price' => 50.000],
            [
                'name' => ['en' => 'Laser Hair Removal', 'ar' => 'ليزر إزالة الشعر'],
                'default_cost' => 10.000,
                'is_active' => true,
            ]
        );

        $productSerum = ClinicItem::firstOrCreate(
            ['branch_id' => $branch->id, 'type' => 'product', 'default_price' => 25.000],
            [
                'name' => ['en' => 'Vit C Serum', 'ar' => 'سيروم فيتامين سي'],
                'default_cost' => 12.000,
                'is_active' => true,
            ]
        );

        $this->command->info("Seeding data from {$startDate->toDateString()} to {$endDate->toDateString()}...");

        // 2) Loop days
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {

            // Skip Friday (Kuwait weekend)
            if ((int) $date->dayOfWeek === Carbon::FRIDAY) {
                continue;
            }

            foreach ([$doc1, $doc2] as $doc) {

                // A) Doctor shift (9:00-17:00) - matches your unique constraint
                DoctorShift::firstOrCreate(
                    [
                        'doctor_id' => $doc->id,
                        'branch_id' => $branch->id,
                        'shift_date' => $date->toDateString(),
                        'start_time' => '09:00:00',
                        'end_time' => '17:00:00',
                    ],
                    [
                        'break_minutes' => 60,
                        'is_cancelled' => false,
                    ]
                );

                // B) Create bookings (3-6)
                $appointments = rand(3, 6);

                for ($i = 0; $i < $appointments; $i++) {

                    $slotBase = Carbon::parse($date->toDateString().' 09:00:00');
                    $resStart = $slotBase->copy()->addMinutes(($i * 60) + rand(0, 20));
                    $resEnd = $resStart->copy()->addMinutes(45);

                    // Patient: keep stable unique phone per row to avoid duplicates
                    $phone = '9'.rand(10000000, 99999999);

                    $patient = Patient::firstOrCreate(
                        ['phone' => $phone, 'partner_id' => $partner->id],
                        ['name' => 'Patient '.rand(10000, 99999)]
                    );

                    // Booking creation
                    $booking = Booking::create([
                        'branch_id' => $branch->id,
                        'doctor_id' => $doc->id,
                        'patient_id' => $patient->id,

                        'msisdn' => $phone,
                        'party_size' => 1,
                        'booking_code' => strtoupper(Str::random(8)),

                        // funnel/source tracking
                        'source' => rand(1, 100) <= 65 ? 'whatsapp' : 'call',

                        'res_date' => $date->toDateString(),
                        'res_time' => $resStart->format('H:i:s'),
                        'res_start' => $resStart,
                        'res_end' => $resEnd,

                        'status' => 'pending',

                        'created_at' => $resStart->copy()->subDays(rand(1, 5)),
                        'updated_at' => $resStart->copy()->subDays(rand(0, 2)),
                    ]);

                    // Outcome distribution:
                    // 10% no-show, 10% cancelled, 80% completed
                    $outcome = rand(1, 100);

                    if ($outcome <= 10) {
                        // No-show: keep status enum safe; use no_show_at timestamp for reporting truth
                        $booking->update([
                            'status' => 'cancelled',
                            'no_show_at' => $resStart->copy()->addMinutes(15),
                        ]);

                        continue;
                    }

                    if ($outcome <= 20) {
                        // Cancelled
                        $booking->update([
                            'status' => 'cancelled',
                            'cancelled_at' => $resStart->copy()->subHours(rand(1, 8)),
                            'cancellation_reason_code' => rand(1, 100) <= 50 ? 'price_high' : 'rescheduled',
                            'cancellation_comment' => 'Seeded cancellation for reporting',
                            'cancelled_by_user_id' => $admin->id,
                        ]);

                        continue;
                    }

                    // Completed
                    $booking->update([
                        'status' => 'completed',
                        'checked_in_at' => $resStart->copy()->subMinutes(rand(3, 8)),
                    ]);

                    $this->createVisit(
                        booking: $booking,
                        doc: $doc,
                        branch: $branch,
                        botox: $serviceBotox,
                        laser: $serviceLaser,
                        serum: $productSerum,
                        admin: $admin
                    );
                }
            }
        }
    }

    private function createVisit(
        Booking $booking,
        Doctor $doc,
        Branch $branch,
        ClinicItem $botox,
        ClinicItem $laser,
        ClinicItem $serum,
        User $admin
    ): void {
        // Defensive: res_start may be string depending on model casts
        $resStart = Carbon::parse((string) $booking->res_start);

        $checkedIn = $booking->checked_in_at
            ? Carbon::parse((string) $booking->checked_in_at)
            : $resStart->copy()->subMinutes(5);

        $serviceStart = $resStart->copy()->addMinutes(rand(5, 15));
        $completedAt = $serviceStart->copy()->addMinutes(rand(25, 45));

        // Choose main service per doctor
        $mainItem = ($doc->name === 'Dr. Ahmed (Derma)') ? $botox : $laser;

        // Variation for realism
        $qty = 1;
        $basePrice = (float) $mainItem->default_price;
        $baseCost = (float) $mainItem->default_cost;

        // Sometimes apply a small discount (0–15%)
        $discountRate = (rand(1, 100) <= 25) ? (rand(5, 15) / 100) : 0;
        $discountTotal = round($basePrice * $discountRate, 3);

        // Create visit first (snapshots filled after items)
        $visit = Visit::create([
            'branch_id' => $branch->id,
            'doctor_id' => $doc->id,
            'patient_id' => $booking->patient_id,
            'booking_id' => $booking->id,

            'source' => $booking->source ?? null,
            'booking_code' => $booking->booking_code ?? null,

            'status' => 'completed',
            'checked_in_at' => $checkedIn,
            'service_started_at' => $serviceStart,
            'completed_at' => $completedAt,

            // will update totals after items
            'fees_total' => 0,
            'discount_total' => 0,
            'items_cost_total' => 0,
            'items_price_total' => 0,
            'profit_total' => 0,

            'computed_at' => $completedAt,
            'computed_version' => 'seed_v1',

            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        // Main service line
        $linePrice = round($basePrice * $qty, 3);
        $lineCost = round($baseCost * $qty, 3);

        VisitItem::create([
            'visit_id' => $visit->id,
            'clinic_item_id' => $mainItem->id,
            'branch_id' => $branch->id,
            'qty' => $qty,

            'unit_cost_snapshot' => $baseCost,
            'unit_price_snapshot' => $basePrice,

            'line_cost_total' => $lineCost,
            'line_price_total' => $linePrice,

            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        // Upsell product 30% chance
        $upsellPrice = 0.0;
        $upsellCost = 0.0;

        if (rand(1, 100) <= 30) {
            $upsellPrice = (float) $serum->default_price;
            $upsellCost = (float) $serum->default_cost;

            VisitItem::create([
                'visit_id' => $visit->id,
                'clinic_item_id' => $serum->id,
                'branch_id' => $branch->id,
                'qty' => 1,

                'unit_cost_snapshot' => $upsellCost,
                'unit_price_snapshot' => $upsellPrice,

                'line_cost_total' => round($upsellCost, 3),
                'line_price_total' => round($upsellPrice, 3),

                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
        }

        // Totals
        $grossRevenue = round($linePrice + $upsellPrice, 3);
        $totalCost = round($lineCost + $upsellCost, 3);

        $netFees = round($grossRevenue - $discountTotal, 3);

        // Doctor cut (commission model for seeded data)
        $rate = 0.30;
        $doctorCut = round($netFees * $rate, 3);

        // Profit snapshot: net - cost - doctor cut
        $profit = round($netFees - $totalCost - $doctorCut, 3);

        // Ledger (fill all snapshot fields defensively)
        DoctorCompensationLedger::updateOrCreate(
            ['visit_id' => $visit->id],
            [
                'doctor_id' => $doc->id,
                'branch_id' => $branch->id,

                'type_snapshot' => 'percentage',
                'basis_snapshot' => 'fees_only',
                'rate_snapshot' => $rate,

                'fees_snapshot' => $grossRevenue,
                'discount_snapshot' => $discountTotal,
                'cost_snapshot' => $totalCost,
                'profit_snapshot' => $profit,

                'doctor_cut_amount' => $doctorCut,

                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]
        );

        // Update visit financial snapshots (this is what your report uses)
        $visit->update([
            'fees_total' => $grossRevenue,
            'discount_total' => $discountTotal,
            'items_cost_total' => $totalCost,
            'items_price_total' => $grossRevenue,
            'profit_total' => $profit,
            'computed_at' => $completedAt,
            'computed_version' => 'seed_v1',
            'updated_at' => $completedAt,
        ]);

        // Payment methods mix (cash/knet/card/link/transfer)
        $methodRoll = rand(1, 100);
        $method = match (true) {
            $methodRoll <= 45 => 'knet',
            $methodRoll <= 75 => 'cash',
            $methodRoll <= 90 => 'card',
            $methodRoll <= 95 => 'link',
            default => 'transfer',
        };

        VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => $netFees, // collected amount should be net (after discount)
            'method' => $method,
            'reference_no' => in_array($method, ['knet', 'card', 'link'], true)
                ? strtoupper($method).'-'.rand(100000, 999999)
                : null,
            'status' => 'paid',
            'collected_by_user_id' => $admin->id,
            'paid_at' => $completedAt,
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        // Follow-up pipeline (20% of completed visits)
        if (rand(1, 100) <= 20) {
            $suggested = $completedAt->copy()->addDays(rand(7, 21));

            $plan = FollowUpPlan::create([
                'source_visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'doctor_id' => $visit->doctor_id,
                'branch_id' => $visit->branch_id,
                'suggested_at' => $suggested,
                'auto_create_booking' => (rand(1, 100) <= 50),
                'booking_id' => null,
                'status' => 'suggested',
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);

            // Optionally: auto create a future booking (only if you want funnel charts to show)
            if ($plan->auto_create_booking) {
                $futureStart = $suggested->copy()->setTime(10, 0)->addMinutes(rand(0, 120));
                $futureEnd = $futureStart->copy()->addMinutes(45);

                $followBooking = Booking::create([
                    'branch_id' => $branch->id,
                    'doctor_id' => $doc->id,
                    'patient_id' => $visit->patient_id,

                    'msisdn' => $booking->msisdn ?? ('9'.rand(10000000, 99999999)),
                    'party_size' => 1,
                    'booking_code' => strtoupper(Str::random(8)),

                    'source' => 'followup',
                    'res_date' => $futureStart->toDateString(),
                    'res_time' => $futureStart->format('H:i:s'),
                    'res_start' => $futureStart,
                    'res_end' => $futureEnd,
                    'status' => 'pending',
                    'created_at' => $completedAt,
                    'updated_at' => $completedAt,
                ]);

                $plan->update([
                    'booking_id' => $followBooking->id,
                    'status' => 'booked',
                    'updated_at' => $completedAt,
                ]);
            }
        }
    }
}
