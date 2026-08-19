<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\ResolvesAccessibleClinics;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payroll & HR Report — what people cost, and what the clinic owes them.
 *
 * The payroll module can run a month and post it, but nothing ever reported on
 * it: there was no register, no labour-cost ratio, and — the two that matter for
 * a Kuwait audit — no accrued leave liability and no end-of-service provision.
 * Both are real obligations that sit outside the payroll run, so they are
 * invisible until someone computes them.
 */
class PayrollReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    /** Kuwait indemnity: 15 days' pay per year for the first 5 years, then a month. */
    private const GRATUITY_DAYS_EARLY = 15;

    private const GRATUITY_DAYS_LATER = 26;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_payroll_reports')) {
            abort(403, 'Not authorized to view payroll reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $filters = [
            'year' => (int) ($request->input('year') ?: Carbon::now($tz)->year),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
        ];

        $branchIds = $this->accessibleBranchIds();

        $memo = null;
        $get = function (string $key) use (&$memo, $filters, $branchIds) {
            $memo ??= $this->build($filters, $branchIds);

            return $memo[$key];
        };

        return Inertia::render('Reports/PayrollReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'payroll'),
            'runs' => Inertia::defer(fn () => $get('runs'), 'payroll'),
            'cost_by_branch' => Inertia::defer(fn () => $get('cost_by_branch'), 'payroll'),
            'cost_by_role' => Inertia::defer(fn () => $get('cost_by_role'), 'payroll'),
            'leave_liability' => Inertia::defer(fn () => $get('leave_liability'), 'payroll'),
            'gratuity_provision' => Inertia::defer(fn () => $get('gratuity_provision'), 'payroll'),
            'loans' => Inertia::defer(fn () => $get('loans'), 'payroll'),
            'attendance' => Inertia::defer(fn () => $get('attendance'), 'payroll'),
            'years' => DB::table('payroll_runs')->distinct()->orderByDesc('period_year')->pluck('period_year')->all()
                ?: [Carbon::now($tz)->year],
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
        ]);
    }

    protected function build(array $filters, ?array $branchIds): array
    {
        $year = $filters['year'];
        $branchFilter = function ($q, string $column) use ($filters, $branchIds) {
            if ($filters['branch_id']) {
                $q->where($column, $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn($column, $branchIds ?: [0]);
            }

            return $q;
        };

        // ---- Payroll runs for the year --------------------------------------
        $runs = DB::table('payroll_runs')
            ->whereNull('deleted_at')
            ->where('period_year', $year)
            ->when($filters['branch_id'], fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->orderBy('period_month')
            ->get(['id', 'period_year', 'period_month', 'status', 'total_earnings', 'total_deductions',
                'total_net', 'total_salary', 'total_commission', 'total_loan_repaid', 'pay_date', 'paid_at'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'period' => Carbon::create($r->period_year, $r->period_month, 1)->format('M Y'),
                'month' => (int) $r->period_month,
                'status' => (string) $r->status,
                'earnings' => round((float) $r->total_earnings, 3),
                'deductions' => round((float) $r->total_deductions, 3),
                'net' => round((float) $r->total_net, 3),
                'salary' => round((float) $r->total_salary, 3),
                'commission' => round((float) $r->total_commission, 3),
                'loan_repaid' => round((float) $r->total_loan_repaid, 3),
                'pay_date' => $r->pay_date,
                'headcount' => (int) DB::table('payslips')->where('payroll_run_id', $r->id)->count(),
            ])->all();

        $runIds = array_column($runs, 'id');

        // ---- Labour cost against turnover -----------------------------------
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $revenue = (float) $branchFilter(
            DB::table('visits')->where('status', 'completed')->whereBetween('computed_at', [$yearStart, $yearEnd]),
            'branch_id'
        )->sum(DB::raw('fees_total + packages_price_total + items_price_total - discount_total'));

        $payrollCost = array_sum(array_column($runs, 'earnings'));

        // Doctor commission that a payroll run has already swept up is inside
        // $payrollCost — a generated run carries it in total_commission. Only the
        // unsettled remainder is additional cost, otherwise every doctor is
        // counted twice and the labour ratio reads far worse than reality.
        $doctorCost = (float) $branchFilter(
            DB::table('doctor_compensation_ledgers')
                ->whereBetween('created_at', [$yearStart, $yearEnd])
                ->whereNull('settled_payroll_run_id'),
            'branch_id'
        )->sum('doctor_cut_amount');

        $activeStaff = (int) $branchFilter(
            DB::table('staff_compensation_profiles')->whereNull('deleted_at')->where('is_active', true),
            'branch_id'
        )->count();

        // ---- Accrued leave liability -----------------------------------------
        // Entitlement earned but not taken, valued at the staff member's daily
        // rate. This is money owed whether or not anyone has asked for it.
        $leaveRows = $branchFilter(
            DB::table('staff_compensation_profiles as p')
                ->join('users as u', 'u.id', '=', 'p.user_id')
                ->leftJoin('staff_leave_entitlements as e', function ($j) use ($year) {
                    $j->on('e.user_id', '=', 'p.user_id')->where('e.year', '=', $year)->where('e.leave_type', '=', 'annual');
                })
                ->whereNull('p.deleted_at')->where('p.is_active', true),
            'p.branch_id'
        )
            ->selectRaw('p.user_id, u.name as staff, p.basic_salary,
                COALESCE(e.entitled_days, p.annual_leave_days, 30) as entitled,
                COALESCE(e.carried_over_days, 0) as carried')
            ->get();

        $takenByUser = DB::table('staff_leaves')
            ->whereNull('deleted_at')->where('status', 'approved')->where('type', 'annual')
            ->whereYear('starts_on', $year)
            ->groupBy('user_id')->selectRaw('user_id, SUM(days_count) as d')
            ->pluck('d', 'user_id')->all();

        $leaveTotal = 0.0;
        $leaveDetail = [];
        foreach ($leaveRows as $row) {
            $entitled = (float) $row->entitled + (float) $row->carried;
            $taken = (float) ($takenByUser[$row->user_id] ?? 0);
            $balance = round($entitled - $taken, 2);
            if ($balance <= 0) {
                continue;
            }
            $daily = (float) $row->basic_salary / 30;
            $value = round($balance * $daily, 3);
            $leaveTotal += $value;
            $leaveDetail[] = [
                'staff' => $this->name($row->staff),
                'entitled' => round($entitled, 1),
                'taken' => round($taken, 1),
                'balance' => $balance,
                'value' => $value,
            ];
        }
        usort($leaveDetail, fn ($a, $b) => $b['value'] <=> $a['value']);

        // ---- End-of-service provision -----------------------------------------
        // What the clinic would owe if every current employee left today.
        $gratuityRows = $branchFilter(
            DB::table('staff_compensation_profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')
                ->whereNull('p.deleted_at')->where('p.is_active', true)->whereNotNull('p.hire_date'),
            'p.branch_id'
        )->selectRaw('u.name as staff, p.basic_salary, p.hire_date')->get();

        $gratuityTotal = 0.0;
        $gratuityDetail = [];
        foreach ($gratuityRows as $row) {
            $years = Carbon::parse($row->hire_date)->floatDiffInYears(Carbon::now());
            $basic = (float) $row->basic_salary;
            $daily = $basic / 30;

            $early = min($years, 5) * self::GRATUITY_DAYS_EARLY;
            $later = max(0, $years - 5) * self::GRATUITY_DAYS_LATER;
            $value = round(($early + $later) * $daily, 3);

            $gratuityTotal += $value;
            $gratuityDetail[] = [
                'staff' => $this->name($row->staff),
                'years' => round($years, 1),
                'basic' => round($basic, 3),
                'value' => $value,
            ];
        }
        usort($gratuityDetail, fn ($a, $b) => $b['value'] <=> $a['value']);

        // What is already carried in the provision account (2220).
        $gratuityBooked = round((float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->where('chart_of_accounts.code', '2220')
            ->sum(DB::raw('journal_entry_lines.credit - journal_entry_lines.debit')), 3);

        // ---- Staff loans --------------------------------------------------------
        $loanRows = $branchFilter(
            DB::table('staff_loans as l')->join('users as u', 'u.id', '=', 'l.user_id')->whereNull('l.deleted_at'),
            'l.branch_id'
        )
            ->selectRaw('u.name as staff, l.type, l.principal_amount, l.outstanding_amount, l.installment_amount, l.status, l.issued_on')
            ->orderByDesc('l.outstanding_amount')->get()
            ->map(fn ($r) => [
                'staff' => $this->name($r->staff),
                'type' => (string) $r->type,
                'principal' => round((float) $r->principal_amount, 3),
                'outstanding' => round((float) $r->outstanding_amount, 3),
                'installment' => round((float) $r->installment_amount, 3),
                'status' => (string) $r->status,
                'issued_on' => $r->issued_on,
            ])->all();

        // ---- Cost splits ---------------------------------------------------------
        $costByBranch = empty($runIds) ? [] : DB::table('payslips')
            ->join('branches', 'branches.id', '=', 'payslips.branch_id')
            ->whereIn('payslips.payroll_run_id', $runIds)
            ->groupBy('branches.id', 'branches.name')
            ->selectRaw('branches.name as branch, SUM(payslips.gross_pay) as gross, COUNT(*) as headcount')
            ->orderByDesc('gross')->get()
            ->map(fn ($r) => ['branch' => $this->name($r->branch), 'gross' => round((float) $r->gross, 3), 'headcount' => (int) $r->headcount])->all();

        $costByRole = empty($runIds) ? [] : DB::table('payslips')
            ->join('model_has_roles as mr', function ($j) {
                $j->on('mr.model_id', '=', 'payslips.user_id')->where('mr.model_type', '=', \App\Models\User::class);
            })
            ->join('roles', 'roles.id', '=', 'mr.role_id')
            ->whereIn('payslips.payroll_run_id', $runIds)
            ->groupBy('roles.name')
            ->selectRaw('roles.name as role, SUM(payslips.gross_pay) as gross, COUNT(DISTINCT payslips.user_id) as headcount')
            ->orderByDesc('gross')->get()
            ->map(fn ($r) => ['role' => (string) $r->role, 'gross' => round((float) $r->gross, 3), 'headcount' => (int) $r->headcount])->all();

        // ---- Attendance ------------------------------------------------------------
        $attFrom = Carbon::now()->subDays(29)->startOfDay();
        $attendance = $branchFilter(
            DB::table('staff_attendances')->whereNull('deleted_at')->where('work_date', '>=', $attFrom->toDateString()),
            'branch_id'
        )
            ->selectRaw('COUNT(*) as records, COUNT(DISTINCT user_id) as staff, AVG(hours_worked) as avg_hours,
                SUM(CASE WHEN HOUR(clock_in_at) > 9 OR (HOUR(clock_in_at) = 9 AND MINUTE(clock_in_at) > 20) THEN 1 ELSE 0 END) as late')
            ->first();

        $kpis = [
            'payroll_cost' => round($payrollCost, 3),
            'doctor_cost' => round($doctorCost, 3),
            'total_labour' => round($payrollCost + $doctorCost, 3),
            'revenue' => round($revenue, 3),
            'labour_ratio' => $revenue > 0 ? round((($payrollCost + $doctorCost) / $revenue) * 100, 1) : null,
            'headcount' => $activeStaff,
            'avg_cost_per_head' => $activeStaff > 0 ? round($payrollCost / max(1, count($runs)) / $activeStaff, 3) : 0,
            'runs_count' => count($runs),
            'leave_liability' => round($leaveTotal, 3),
            'gratuity_provision' => round($gratuityTotal, 3),
            'gratuity_booked' => $gratuityBooked,
            'gratuity_gap' => round($gratuityTotal - $gratuityBooked, 3),
            'loans_outstanding' => round(array_sum(array_column($loanRows, 'outstanding')), 3),
        ];

        return [
            'kpis' => $kpis,
            'runs' => $runs,
            'cost_by_branch' => $costByBranch,
            'cost_by_role' => $costByRole,
            'leave_liability' => ['total' => round($leaveTotal, 3), 'rows' => array_slice($leaveDetail, 0, 25)],
            'gratuity_provision' => ['total' => round($gratuityTotal, 3), 'booked' => $gratuityBooked, 'rows' => array_slice($gratuityDetail, 0, 25)],
            'loans' => $loanRows,
            'attendance' => [
                'records' => (int) ($attendance->records ?? 0),
                'staff' => (int) ($attendance->staff ?? 0),
                'avg_hours' => round((float) ($attendance->avg_hours ?? 0), 2),
                'late' => (int) ($attendance->late ?? 0),
                'late_pct' => ($attendance->records ?? 0) > 0 ? round(($attendance->late / $attendance->records) * 100, 1) : 0,
            ],
        ];
    }

    protected function name($value): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $d = json_decode($value, true);
            if (is_array($d)) {
                return $d[app()->getLocale()] ?? $d['en'] ?? (array_values($d)[0] ?? '—');
            }
        }

        return (string) ($value ?? '—');
    }
}
