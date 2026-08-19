<?php

namespace Database\Seeders\Demo;

use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\StaffAttendance;
use App\Models\StaffCompensationProfile;
use App\Models\StaffLeave;
use App\Models\StaffLeaveEntitlement;
use App\Models\StaffLoan;
use App\Models\StaffSettlement;
use App\Models\User;
use App\Services\Clinic\GratuityService;
use App\Services\Clinic\LoanService;
use App\Services\Clinic\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Populates the HR side of the payroll module — salary profiles, leave
 * entitlements and requests, staff loans, attendance and end-of-service
 * settlements — then runs several months of payroll so the GL carries a real
 * salary cost and the HR screens have history.
 *
 * Doctors deliberately get no salary profile: they are paid from the
 * commission ledger, and PayrollService already sweeps them into a run. Giving
 * them a basic salary as well would double-expense them.
 */
class DemoHrPayrollSeeder extends Seeder
{
    /**
     * Monthly basic salary by role, in KWD. Pitched at the Kuwait private-clinic
     * market and sized so total payroll lands near 18% of group turnover —
     * allowances add another ~30% on top of these figures, and the doctors are
     * paid separately from the commission ledger.
     */
    protected array $salaryByRole = [
        'clinic_admin' => 680.000,
        'accountant' => 560.000,
        'clinic_lab' => 380.000,
        'clinic_nurse' => 340.000,
        'clinic_reception' => 300.000,
    ];

    protected array $leaveReasons = [
        'annual' => ['Family holiday abroad', 'Annual leave — Eid break', 'Rest days after a long rota', 'Travel with family'],
        'sick' => ['Influenza with fever', 'Back strain, physiotherapy advised', 'Migraine — medical certificate attached', 'Stomach infection'],
        'maternity' => ['Statutory maternity leave'],
        'emergency' => ['Family bereavement', 'Urgent family matter', 'Child hospitalised'],
        'unpaid' => ['Extended personal travel', 'Unpaid leave — family visit abroad'],
        'other' => ['Study leave for licensing exam', 'Hajj leave'],
    ];

    public function run(): void
    {
        $staff = $this->staff();
        if ($staff->isEmpty()) {
            $this->command?->warn('DemoHrPayrollSeeder: no non-doctor staff found.');

            return;
        }

        $approver = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin', 'clinic_admin']))->first()
            ?? User::query()->first();

        $this->seedProfiles($staff);
        $this->seedEntitlements($staff);
        $this->seedLeaves($staff, $approver);
        $this->seedAttendance($staff);
        $this->seedLoans($staff, $approver);
        $this->seedSettlements($approver);
        $this->runPayroll($approver);
    }

    /** Salaried staff = everyone with a clinic role that isn't a doctor or a patient. */
    protected function staff()
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', array_keys($this->salaryByRole)))
            ->with('roles:id,name')
            ->orderBy('id')
            ->get();
    }

    protected function roleOf(User $user): string
    {
        foreach ($user->roles as $role) {
            if (isset($this->salaryByRole[$role->name])) {
                return $role->name;
            }
        }

        return 'clinic_reception';
    }

    protected function branchOf(User $user): ?int
    {
        return (int) (\Illuminate\Support\Facades\DB::table('branch_user')->where('user_id', $user->id)->value('branch_id')
            ?: Branch::query()->value('id')) ?: null;
    }

    protected function seedProfiles($staff): void
    {
        if (StaffCompensationProfile::query()->withoutGlobalScopes()->exists()) {
            $this->command?->warn('DemoHrPayrollSeeder: salary profiles already exist — skipping.');

            return;
        }

        $created = 0;
        foreach ($staff as $i => $user) {
            $role = $this->roleOf($user);
            // ±12% band so the payroll register isn't a wall of identical numbers.
            $basic = round($this->salaryByRole[$role] * (1 + ((($i % 7) - 3) * 0.04)), 3);

            StaffCompensationProfile::create([
                'user_id' => $user->id,
                'branch_id' => $this->branchOf($user),
                'basic_salary' => $basic,
                'allowances' => [
                    ['label' => 'Housing allowance', 'amount' => round($basic * 0.25, 3)],
                    ['label' => 'Transport allowance', 'amount' => 50.000],
                    ['label' => 'Mobile allowance', 'amount' => 10.000],
                ],
                'deductions' => $i % 3 === 0
                    ? [['label' => 'Social security contribution', 'amount' => round($basic * 0.075, 3)]]
                    : [],
                'pay_currency' => 'KWD',
                'annual_leave_days' => 30,
                'hire_date' => Carbon::today()->subMonths(random_int(7, 54))->toDateString(),
                'is_active' => true,
            ]);
            $created++;
        }

        $this->command?->info("DemoHrPayrollSeeder: created {$created} salary profiles.");
    }

    protected function seedEntitlements($staff): void
    {
        if (StaffLeaveEntitlement::query()->exists()) {
            return;
        }

        $year = (int) Carbon::today()->year;
        foreach ($staff as $i => $user) {
            foreach ([$year - 1, $year] as $y) {
                StaffLeaveEntitlement::create([
                    'user_id' => $user->id,
                    'year' => $y,
                    'leave_type' => 'annual',
                    'entitled_days' => 30,
                    // Carry-over only on the current year, and only for some staff.
                    'carried_over_days' => ($y === $year && $i % 3 === 0) ? random_int(2, 9) : 0,
                ]);
            }
            StaffLeaveEntitlement::create([
                'user_id' => $user->id,
                'year' => $year,
                'leave_type' => 'sick',
                'entitled_days' => 15,
                'carried_over_days' => 0,
            ]);
        }

        $this->command?->info('DemoHrPayrollSeeder: created leave entitlements for '.$staff->count().' staff.');
    }

    protected function seedLeaves($staff, ?User $approver): void
    {
        if (StaffLeave::query()->withoutGlobalScopes()->exists()) {
            $this->command?->warn('DemoHrPayrollSeeder: leave requests already exist — skipping.');

            return;
        }

        $types = ['annual', 'annual', 'annual', 'sick', 'sick', 'emergency', 'unpaid', 'other', 'maternity'];
        $created = 0;

        foreach ($staff as $i => $user) {
            // 3–5 requests each across the last year, plus the odd future one.
            $count = random_int(3, 5);
            for ($n = 0; $n < $count; $n++) {
                $type = $types[($i + $n) % count($types)];
                $days = match ($type) {
                    'annual' => random_int(3, 14),
                    'maternity' => 70,
                    'unpaid' => random_int(2, 8),
                    default => random_int(1, 3),
                };

                // Most sit in the past; roughly one in six is upcoming so the
                // leave calendar has something ahead of today.
                $start = ($n === $count - 1 && $i % 6 === 0)
                    ? Carbon::today()->addDays(random_int(4, 40))
                    : Carbon::today()->subDays(random_int(20, 330));
                $end = $start->copy()->addDays($days - 1);

                $status = match (true) {
                    $start->isFuture() && $i % 3 === 0 => 'pending',
                    $i % 11 === 0 && $n === 1 => 'rejected',
                    $i % 17 === 0 && $n === 2 => 'cancelled',
                    default => 'approved',
                };

                StaffLeave::create([
                    'user_id' => $user->id,
                    'branch_id' => $this->branchOf($user),
                    'type' => $type,
                    'starts_on' => $start->toDateString(),
                    'ends_on' => $end->toDateString(),
                    'days_count' => $days,
                    'reason' => $this->leaveReasons[$type][($i + $n) % count($this->leaveReasons[$type])],
                    'status' => $status,
                    'decision_notes' => $status === 'rejected' ? 'Clashes with another approved leave in the same branch — please re-submit for a later window.' : null,
                    'decided_at' => in_array($status, ['approved', 'rejected'], true) ? $start->copy()->subDays(random_int(3, 12))->setTime(10, 0) : null,
                    'decided_by_user_id' => in_array($status, ['approved', 'rejected'], true) ? $approver?->id : null,
                    'requested_by_user_id' => $user->id,
                    'created_at' => $start->copy()->subDays(random_int(5, 20)),
                    'updated_at' => $start->copy()->subDays(random_int(1, 4)),
                ]);
                $created++;
            }
        }

        $this->command?->info("DemoHrPayrollSeeder: created {$created} leave requests.");
    }

    protected function seedAttendance($staff): void
    {
        if (StaffAttendance::query()->withoutGlobalScopes()->count() > 50) {
            $this->command?->warn('DemoHrPayrollSeeder: attendance already seeded — skipping.');

            return;
        }

        $created = 0;
        foreach ($staff as $i => $user) {
            $branchId = $this->branchOf($user);

            for ($d = 60; $d >= 0; $d--) {
                $date = Carbon::today()->subDays($d);
                if ($date->isFriday()) {
                    continue; // clinic weekend
                }
                // ~4% absence so the attendance report shows gaps, not a perfect grid.
                if (random_int(1, 100) <= 4) {
                    continue;
                }

                $in = $date->copy()->setTime(9, 0)->addMinutes(random_int(-12, 35));
                $out = $date->copy()->setTime(18, 0)->addMinutes(random_int(-25, 70));

                StaffAttendance::create([
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                    'work_date' => $date->toDateString(),
                    'clock_in_at' => $in,
                    'clock_out_at' => $out,
                    'hours_worked' => round($in->diffInMinutes($out) / 60, 2),
                    'notes' => $in->hour >= 9 && $in->minute > 20 ? 'Late arrival — traffic on the ring road.' : null,
                    'created_at' => $in,
                    'updated_at' => $out,
                ]);
                $created++;
            }
            unset($i);
        }

        $this->command?->info("DemoHrPayrollSeeder: created {$created} attendance records.");
    }

    protected function seedLoans($staff, ?User $approver): void
    {
        if (StaffLoan::query()->withoutGlobalScopes()->exists()) {
            $this->command?->warn('DemoHrPayrollSeeder: staff loans already exist — skipping.');

            return;
        }

        $loans = app(LoanService::class);
        $cash = Account::query()->where('code', '1110')->value('id');
        $created = 0;

        foreach ($staff as $i => $user) {
            // Roughly a third of staff carry a loan or an advance.
            if ($i % 3 !== 0) {
                continue;
            }

            $isAdvance = $i % 6 === 0;
            $principal = $isAdvance ? round(random_int(100, 300), 3) : round(random_int(600, 2500) / 10, 3) * 10;
            $installment = $isAdvance ? $principal : round($principal / random_int(6, 18), 3);
            $issued = Carbon::today()->subMonths(random_int(1, 10))->startOfMonth()->addDays(random_int(0, 20));

            $loan = StaffLoan::create([
                'user_id' => $user->id,
                'branch_id' => $this->branchOf($user),
                'type' => $isAdvance ? 'advance' : 'loan',
                'principal_amount' => $principal,
                'outstanding_amount' => 0,
                'installment_amount' => $installment,
                'reason' => $isAdvance ? 'Salary advance requested ahead of Eid.' : 'Personal loan — school fees, repaid over monthly installments.',
                'issued_on' => $issued->toDateString(),
                'status' => StaffLoan::STATUS_PENDING,
                'payment_account_id' => $cash,
                'created_at' => $issued,
                'updated_at' => $issued,
            ]);

            // Leave a couple pending so the approvals queue isn't empty.
            if ($i % 9 !== 0) {
                $loans->approve($loan, $approver);
                // Back-date the approval; the service stamps now().
                $loan->fresh()->forceFill(['approved_at' => $issued->copy()->addDay()->setTime(12, 0)])->save();
            }
            $created++;
        }

        $this->command?->info("DemoHrPayrollSeeder: created {$created} staff loans/advances.");
    }

    /** A few leavers so the end-of-service (Kuwait gratuity) screen has history. */
    protected function seedSettlements(?User $approver): void
    {
        if (StaffSettlement::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        $gratuity = app(GratuityService::class);
        $profiles = StaffCompensationProfile::query()->withoutGlobalScopes()->inRandomOrder()->limit(4)->get();
        if ($profiles->isEmpty()) {
            return;
        }

        foreach ($profiles as $i => $profile) {
            $hire = Carbon::parse($profile->hire_date ?? Carbon::today()->subYears(3));
            $lastDay = Carbon::today()->subDays(random_int(15, 200));
            if ($lastDay->lte($hire)) {
                continue;
            }

            $years = $gratuity->yearsOfService($hire, $lastDay);
            $basic = (float) $profile->basic_salary;
            $amount = $gratuity->gratuity($basic, $years, $i % 2 === 0 ? 'termination' : 'resignation');
            $encash = round(($basic / 30) * random_int(4, 18), 3);
            $status = ['paid', 'paid', 'approved', 'draft'][$i % 4];

            StaffSettlement::create([
                'user_id' => $profile->user_id,
                'branch_id' => $profile->branch_id,
                'hire_date' => $hire->toDateString(),
                'last_working_day' => $lastDay->toDateString(),
                'years_of_service' => round($years, 3),
                'basic_salary_snapshot' => $basic,
                'gratuity_amount' => $amount,
                'leave_encashment' => $encash,
                'other_additions' => 0,
                'loan_clawback' => 0,
                'other_deductions' => 0,
                'net_settlement' => round($amount + $encash, 3),
                'status' => $status,
                'notes' => $i % 2 === 0 ? 'Contract terminated by the clinic — full gratuity entitlement.' : 'Resignation — gratuity reduced per the Kuwait Labour Law scale.',
                'prepared_by_user_id' => $approver?->id,
                'approved_by_user_id' => $status === 'draft' ? null : $approver?->id,
                'approved_at' => $status === 'draft' ? null : $lastDay->copy()->addDays(5)->setTime(11, 0),
                'paid_at' => $status === 'paid' ? $lastDay->copy()->addDays(12)->setTime(13, 0) : null,
                'created_at' => $lastDay->copy()->addDays(2),
                'updated_at' => $lastDay->copy()->addDays(6),
            ]);
        }

        $this->command?->info('DemoHrPayrollSeeder: created end-of-service settlements.');
    }

    /**
     * Generate → approve → pay a run for each of the last few months so the
     * salary expense, salaries-payable and cash movements all exist in the GL.
     */
    protected function runPayroll(?User $approver): void
    {
        if (! $approver) {
            return;
        }
        if (PayrollRun::query()->where('status', PayrollRun::STATUS_PAID)->exists()) {
            $this->command?->warn('DemoHrPayrollSeeder: paid payroll runs already exist — skipping.');

            return;
        }

        $payroll = app(PayrollService::class);
        $cash = Account::query()->where('code', '1120')->value('id')
            ?? Account::query()->where('code', '1110')->value('id');
        $done = 0;

        // Last full month back four months. The most recent month stays unpaid
        // (approved) so the payroll screen has an open run to act on.
        for ($m = 4; $m >= 1; $m--) {
            $period = Carbon::today()->startOfMonth()->subMonths($m);
            $payDate = $period->copy()->endOfMonth();

            $existing = PayrollRun::query()
                ->where('period_year', $period->year)->where('period_month', $period->month)
                ->whereNull('branch_id')->first();
            if ($existing && $existing->status !== PayrollRun::STATUS_DRAFT) {
                continue;
            }

            $run = $existing ?: PayrollRun::create([
                'branch_id' => null,
                'period_year' => $period->year,
                'period_month' => $period->month,
                'status' => PayrollRun::STATUS_DRAFT,
                'pay_date' => $payDate->toDateString(),
                'prepared_by_user_id' => $approver->id,
                'notes' => 'Monthly payroll — all branches.',
                'created_at' => $payDate->copy()->subDays(3),
                'updated_at' => $payDate->copy()->subDays(3),
            ]);

            try {
                $payroll->generate($run);
                $payroll->approve($run->fresh(), $approver);
                // The service stamps now(); realign to the actual period.
                $run->fresh()->forceFill(['approved_at' => $payDate->copy()->subDay()->setTime(15, 0)])->save();

                if ($m > 1) {
                    $payroll->markPaid($run->fresh(), $approver, $cash);
                    $run->fresh()->forceFill(['paid_at' => $payDate->copy()->setTime(12, 0)])->save();
                }
                $done++;
            } catch (\Throwable $e) {
                $this->command?->warn("  payroll {$period->format('Y-m')} failed: {$e->getMessage()}");
            }
        }

        $this->command?->info("DemoHrPayrollSeeder: processed {$done} payroll runs.");
    }
}
