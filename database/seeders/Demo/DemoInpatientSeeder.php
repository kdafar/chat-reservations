<?php

namespace Database\Seeders\Demo;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Inpatient\Admission;
use App\Models\Inpatient\AdmissionBedStay;
use App\Models\Inpatient\AdmissionCharge;
use App\Models\Inpatient\AdmissionRound;
use App\Models\Inpatient\Bed;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills the inpatient module so /admin/v2/inpatient/reports has something to
 * report on: occupancy trend, length-of-stay distribution, discharge outcomes,
 * readmission rate, admissions per ward and revenue per ward.
 *
 * Dates are written directly rather than going through AdmissionService, which
 * stamps now() — the reports need ~4 months of history to draw a trend.
 */
class DemoInpatientSeeder extends Seeder
{
    /** Reasons paired with the diagnosis the reports group by. */
    protected array $cases = [
        ['Post-operative observation after abdominoplasty', 'Post-op recovery — abdominoplasty'],
        ['Severe dehydration and electrolyte imbalance', 'Acute gastroenteritis'],
        ['Cellulitis of the lower limb requiring IV antibiotics', 'Lower-limb cellulitis'],
        ['Observation after rhinoplasty under general anaesthetic', 'Post-op recovery — rhinoplasty'],
        ['Uncontrolled hypertension with chest discomfort', 'Hypertensive urgency'],
        ['Post-operative care following liposuction', 'Post-op recovery — liposuction'],
        ['Community-acquired pneumonia', 'Pneumonia'],
        ['Acute allergic reaction after dermal filler', 'Anaphylactoid reaction — filler'],
        ['Diabetic foot ulcer, IV antibiotics and wound care', 'Diabetic foot infection'],
        ['Observation after breast augmentation', 'Post-op recovery — augmentation mammoplasty'],
        ['Renal colic with intractable pain', 'Ureteric calculus'],
        ['Post-operative monitoring after blepharoplasty', 'Post-op recovery — blepharoplasty'],
    ];

    protected array $ancillary = [
        ['Nursing care & monitoring', 18.0],
        ['IV antibiotics course', 26.5],
        ['Analgesia & antiemetics', 9.75],
        ['Wound dressing set', 12.0],
        ['Physiotherapy session', 22.0],
        ['Blood workup (CBC + CRP)', 15.5],
    ];

    public function run(): void
    {
        if (Admission::query()->withoutGlobalScopes()->exists()) {
            $this->command?->warn('DemoInpatientSeeder: admissions already exist — skipping.');

            return;
        }

        $withBeds = Bed::query()->distinct()->pluck('branch_id')->filter()->all();
        $branches = Branch::query()->whereIn('id', $withBeds)->orderBy('id')->get(['id', 'partner_id']);
        if ($branches->isEmpty()) {
            $this->command?->warn('DemoInpatientSeeder: no branch has beds — run DemoRepairSeeder first.');

            return;
        }

        $staff = User::query()->pluck('id')->all();
        $today = Carbon::today(config('app.timezone', 'Asia/Kuwait'));
        $seq = 0;
        $created = 0;

        foreach ($branches as $branch) {
            $beds = Bed::query()->where('branch_id', $branch->id)->where('is_active', true)
                ->with('ward')->orderBy('id')->get();
            if ($beds->isEmpty()) {
                continue;
            }

            $doctors = Doctor::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->pluck('id')->all();
            if (! $doctors) {
                $doctors = Doctor::query()->withoutGlobalScopes()->limit(5)->pluck('id')->all();
            }

            $patients = Patient::query()->where('partner_id', $branch->partner_id)->inRandomOrder()->limit(40)->pluck('id')->all();
            if (! $patients) {
                $patients = Patient::query()->inRandomOrder()->limit(40)->pluck('id')->all();
            }
            if (! $patients || ! $doctors) {
                continue;
            }

            // ~11 admissions per branch spread over the last 120 days, with the
            // tail reaching today so a few are still active (live occupancy).
            for ($n = 0; $n < 11; $n++) {
                $daysAgo = (int) round(118 - ($n * 10.5)) + random_int(-2, 2);
                $daysAgo = max(0, $daysAgo);
                $los = $this->sampleLos();

                // A repeat patient every 4th admission on a branch drives the
                // readmission-rate panel.
                $patientId = ($n > 0 && $n % 4 === 0)
                    ? $patients[($n - 1) % count($patients)]
                    : $patients[$n % count($patients)];

                $seq++;
                $created += $this->makeAdmission(
                    $branch,
                    $patientId,
                    $doctors[$n % count($doctors)],
                    $beds,
                    $staff,
                    $today->copy()->subDays($daysAgo),
                    $los,
                    $seq,
                ) ? 1 : 0;
            }
        }

        $this->command?->info("DemoInpatientSeeder: created {$created} admissions with bed stays, rounds and charges.");
    }

    /** Length of stay skewed short, with a long tail — matches real ward data. */
    protected function sampleLos(): int
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 35 => random_int(1, 2),
            $roll <= 70 => random_int(3, 4),
            $roll <= 90 => random_int(5, 8),
            default => random_int(9, 16),
        };
    }

    protected function makeAdmission(Branch $branch, int $patientId, int $doctorId, $beds, array $staff, Carbon $admittedOn, int $los, int $seq): bool
    {
        $admittedAt = $admittedOn->copy()->setTime(random_int(8, 21), [0, 15, 30, 45][random_int(0, 3)]);
        $dischargeAt = $admittedAt->copy()->addDays($los)->setTime(random_int(9, 16), 0);
        $stillIn = $dischargeAt->isFuture();

        // An active admission must hold a bed that nothing else holds.
        $bed = $stillIn
            ? $beds->firstWhere('status', Bed::STATUS_AVAILABLE)
            : $beds[$seq % $beds->count()];
        if (! $bed) {
            return false;
        }

        [$reason, $diagnosis] = $this->cases[($seq - 1) % count($this->cases)];
        $staffId = $staff ? $staff[$seq % count($staff)] : null;

        return DB::transaction(function () use ($branch, $patientId, $doctorId, $bed, $beds, $staffId, $admittedAt, $dischargeAt, $stillIn, $los, $seq, $reason, $diagnosis) {
            $admission = Admission::create([
                'partner_id' => $branch->partner_id,
                'branch_id' => $branch->id,
                'patient_id' => $patientId,
                'admitting_doctor_id' => $doctorId,
                'admission_code' => 'ADM-'.$admittedAt->format('Ym').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'admitted_at' => $admittedAt,
                'expected_discharge_at' => $admittedAt->copy()->addDays($los),
                'admission_reason' => $reason,
                'diagnosis' => $diagnosis,
                'status' => $stillIn ? Admission::STATUS_ACTIVE : $this->outcome($seq),
                'discharged_at' => $stillIn ? null : $dischargeAt,
                'discharged_by_user_id' => $stillIn ? null : $staffId,
                'discharge_summary' => $stillIn ? null : "Patient recovered well. {$diagnosis} resolved; discharged on oral medication with a follow-up in one week.",
                'created_at' => $admittedAt,
                'updated_at' => $stillIn ? $admittedAt : $dischargeAt,
            ]);

            // Roughly one in five admissions moves bed mid-stay (ward upgrade,
            // isolation) — the bed-stay table needs more than one row per case.
            $transfers = ($los >= 5 && $seq % 5 === 0) ? 1 : 0;
            $stays = $this->makeBedStays($admission, $bed, $beds, $staffId, $admittedAt, $dischargeAt, $stillIn, $transfers);

            $this->makeCharges($admission, $stays, $staffId, $admittedAt, $stillIn ? Carbon::now() : $dischargeAt);
            $this->makeRounds($admission, $doctorId, $staffId, $admittedAt, $stillIn ? Carbon::now() : $dischargeAt);

            return true;
        });
    }

    /** Non-discharge outcomes, kept rare so the outcome chart stays realistic. */
    protected function outcome(int $seq): string
    {
        return match (true) {
            $seq % 29 === 0 => Admission::STATUS_LAMA,
            $seq % 43 === 0 => Admission::STATUS_TRANSFERRED_OUT,
            $seq % 61 === 0 => Admission::STATUS_EXPIRED,
            default => Admission::STATUS_DISCHARGED,
        };
    }

    /** @return AdmissionBedStay[] */
    protected function makeBedStays(Admission $admission, Bed $bed, $beds, ?int $staffId, Carbon $from, Carbon $to, bool $stillIn, int $transfers): array
    {
        $stays = [];
        $rate = (float) ($bed->daily_rate_override ?: $bed->ward?->daily_rate ?: 45);
        $split = $transfers > 0 ? $from->copy()->addDays((int) floor($from->diffInDays($to) / 2)) : null;

        $stays[] = AdmissionBedStay::create([
            'admission_id' => $admission->id,
            'bed_id' => $bed->id,
            'ward_id' => $bed->ward_id,
            'assigned_at' => $from,
            'released_at' => $split ?: ($stillIn ? null : $to),
            'daily_rate' => $rate,
            'assigned_by_user_id' => $staffId,
            'released_by_user_id' => ($split || ! $stillIn) ? $staffId : null,
            'reason_for_change' => 'Initial admission',
            'created_at' => $from,
            'updated_at' => $split ?: $to,
        ]);

        if ($split) {
            $second = $beds->where('id', '!=', $bed->id)->values()[$admission->id % max(1, $beds->count() - 1)] ?? null;
            if ($second) {
                $rate2 = (float) ($second->daily_rate_override ?: $second->ward?->daily_rate ?: $rate);
                $stays[] = AdmissionBedStay::create([
                    'admission_id' => $admission->id,
                    'bed_id' => $second->id,
                    'ward_id' => $second->ward_id,
                    'assigned_at' => $split,
                    'released_at' => $stillIn ? null : $to,
                    'daily_rate' => $rate2,
                    'assigned_by_user_id' => $staffId,
                    'released_by_user_id' => $stillIn ? null : $staffId,
                    'reason_for_change' => 'Moved to a private room at the patient’s request',
                    'created_at' => $split,
                    'updated_at' => $to,
                ]);
                $bed = $second;
            }
        }

        // Only a currently-open stay may hold its bed.
        if ($stillIn) {
            $bed->update(['status' => Bed::STATUS_OCCUPIED]);
        }

        return $stays;
    }

    /**
     * One bed-day charge per night, plus a few ancillary lines.
     *
     * admission_charges carries a unique key on (admission_id, charge_date,
     * source), so a mid-stay bed transfer must not bill the changeover day
     * twice and two ancillary items cannot share a day — collect by key first.
     */
    protected function makeCharges(Admission $admission, array $stays, ?int $staffId, Carbon $from, Carbon $to): void
    {
        $rows = [];

        foreach ($stays as $stay) {
            $cursor = Carbon::parse($stay->assigned_at)->startOfDay();
            $end = Carbon::parse($stay->released_at ?? $to)->startOfDay();
            $rate = (float) $stay->daily_rate;

            while ($cursor->lte($end)) {
                $key = $cursor->toDateString().'|bed_day';
                if (! isset($rows[$key])) {
                    $rows[$key] = [
                        'admission_id' => $admission->id,
                        'bed_stay_id' => $stay->id,
                        'charge_date' => $cursor->toDateString(),
                        'amount' => $rate,
                        'description' => 'Bed day — '.$cursor->format('d M Y'),
                        'source' => 'bed_day',
                        'created_by_user_id' => $staffId,
                        'created_at' => $cursor->copy()->setTime(23, 0),
                        'updated_at' => $cursor->copy()->setTime(23, 0),
                    ];
                }
                $cursor->addDay();
            }
        }

        $span = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $extras = min(random_int(1, 3), $span);
        for ($i = 0; $i < $extras; $i++) {
            [$label, $amount] = $this->ancillary[($admission->id + $i) % count($this->ancillary)];
            $on = $from->copy()->startOfDay()->addDays($i);
            $key = $on->toDateString().'|ancillary';
            if (isset($rows[$key])) {
                continue;
            }
            $rows[$key] = [
                'admission_id' => $admission->id,
                'bed_stay_id' => $stays[0]->id ?? null,
                'charge_date' => $on->toDateString(),
                'amount' => $amount,
                'description' => $label,
                'source' => 'ancillary',
                'created_by_user_id' => $staffId,
                'created_at' => $on->copy()->setTime(12, 0),
                'updated_at' => $on->copy()->setTime(12, 0),
            ];
        }

        foreach ($rows as $row) {
            AdmissionCharge::create($row);
        }
    }

    protected function makeRounds(Admission $admission, int $doctorId, ?int $staffId, Carbon $from, Carbon $to): void
    {
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $day = 1;

        while ($cursor->lte($end) && $day <= 20) {
            AdmissionRound::create([
                'admission_id' => $admission->id,
                'doctor_id' => $doctorId,
                'round_date' => $cursor->toDateString(),
                'vitals' => [
                    'temp_c' => round(36.4 + (random_int(0, 18) / 10), 1),
                    'pulse' => random_int(62, 104),
                    'bp' => random_int(105, 142).'/'.random_int(66, 92),
                    'spo2' => random_int(94, 100),
                    'resp' => random_int(12, 22),
                ],
                'progress_notes' => $day === 1
                    ? 'Admitted and stabilised. Baseline observations recorded, IV access established.'
                    : 'Observations stable overnight. Patient comfortable, tolerating oral intake.',
                'med_changes' => $day === 2 ? 'IV antibiotics stepped down to oral.' : null,
                'next_steps' => 'Continue current plan; review in the morning round.',
                'created_by_user_id' => $staffId,
                'created_at' => $cursor->copy()->setTime(9, 30),
                'updated_at' => $cursor->copy()->setTime(9, 30),
            ]);
            $cursor->addDay();
            $day++;
        }
    }
}
