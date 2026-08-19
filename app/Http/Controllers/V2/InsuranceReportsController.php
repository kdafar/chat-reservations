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
 * Insurance Performance Report — how well each insurer actually pays.
 *
 * The follow-up board is a chase worklist: it shows which claims are overdue
 * today. It cannot answer the questions that decide whether a contract is worth
 * keeping — what share this insurer approves, how long it takes to pay, how much
 * of what we bill we never collect, and which rejection reasons keep recurring.
 *
 * Value leakage is the spine of this report: charged → approved → paid, with the
 * gap at each step named rather than averaged away.
 */
class InsuranceReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_insurance_reports')) {
            abort(403, 'Not authorized to view insurance reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $filters = [
            'from' => $request->input('from') ?: Carbon::now($tz)->subMonths(6)->startOfMonth()->toDateString(),
            'to' => $request->input('to') ?: Carbon::now($tz)->toDateString(),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            'insurer_id' => $request->input('insurer_id', '') !== '' ? (int) $request->input('insurer_id') : null,
        ];
        $from = Carbon::parse($filters['from'], $tz)->startOfDay();
        $to = Carbon::parse($filters['to'], $tz)->endOfDay();
        $branchIds = $this->accessibleBranchIds();

        $memo = null;
        $get = function (string $key) use (&$memo, $filters, $from, $to, $branchIds) {
            $memo ??= $this->build($filters, $from, $to, $branchIds);

            return $memo[$key];
        };

        return Inertia::render('Reports/InsuranceReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'insurance'),
            'by_insurer' => Inertia::defer(fn () => $get('by_insurer'), 'insurance'),
            'status_mix' => Inertia::defer(fn () => $get('status_mix'), 'insurance'),
            'aging' => Inertia::defer(fn () => $get('aging'), 'insurance'),
            'rejection_reasons' => Inertia::defer(fn () => $get('rejection_reasons'), 'insurance'),
            'preauth' => Inertia::defer(fn () => $get('preauth'), 'insurance'),
            'trend' => Inertia::defer(fn () => $get('trend'), 'insurance'),
            'insurers' => DB::table('insurers')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($i) => ['id' => $i->id, 'name' => $this->name($i->name)])->all(),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
        ]);
    }

    protected function build(array $filters, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        $base = function () use ($filters, $from, $to, $branchIds) {
            $q = DB::table('insurance_claims as c')
                ->leftJoin('patient_insurance_policies as p', 'p.id', '=', 'c.patient_policy_id')
                ->leftJoin('insurers as i', 'i.id', '=', 'p.insurer_id')
                ->whereNull('c.deleted_at')
                ->whereBetween('c.created_at', [$from, $to]);

            if ($filters['branch_id']) {
                $q->where('c.branch_id', $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn('c.branch_id', $branchIds ?: [0]);
            }
            if ($filters['insurer_id']) {
                $q->where('p.insurer_id', $filters['insurer_id']);
            }

            return $q;
        };

        // ---- Value leakage: charged → approved → paid -----------------------
        $totals = $base()->selectRaw('
            COUNT(*) as claims,
            COALESCE(SUM(c.total_charged),0) as charged,
            COALESCE(SUM(c.insurer_payable),0) as payable,
            COALESCE(SUM(c.approved_amount),0) as approved,
            COALESCE(SUM(c.rejected_amount),0) as rejected,
            COALESCE(SUM(c.paid_amount),0) as paid,
            COALESCE(SUM(c.write_off_amount),0) as written_off,
            COALESCE(SUM(c.patient_copay),0) as copay
        ')->first();

        $charged = (float) $totals->charged;
        $payable = (float) $totals->payable;
        $approved = (float) $totals->approved;
        $paid = (float) $totals->paid;

        // Days to payment, over claims that actually settled.
        $settled = $base()->whereNotNull('c.paid_at')->whereNotNull('c.submitted_at')
            ->selectRaw('AVG(DATEDIFF(c.paid_at, c.submitted_at)) as d, COUNT(*) as n')->first();

        $decidedStatuses = ['approved', 'partially_approved', 'rejected', 'paid'];
        $decided = $base()->whereIn('c.status', $decidedStatuses)->count();
        $rejectedCount = $base()->where('c.status', 'rejected')->count();

        // Approval is measured against what has actually been ruled on. Including
        // claims still sitting in submitted/under-review would count "no answer
        // yet" as a refusal and permanently understate every insurer.
        $decidedPayable = (float) $base()->whereIn('c.status', $decidedStatuses)->sum('c.insurer_payable');

        $kpis = [
            'claims' => (int) $totals->claims,
            'charged' => round($charged, 3),
            'payable' => round($payable, 3),
            'approved' => round($approved, 3),
            'paid' => round($paid, 3),
            'rejected_value' => round((float) $totals->rejected, 3),
            'written_off' => round((float) $totals->written_off, 3),
            'copay' => round((float) $totals->copay, 3),
            'outstanding' => round($payable - $paid - (float) $totals->written_off - (float) $totals->rejected, 3),
            // Of everything an insurer has ruled on, how much did they agree to?
            'approval_rate' => $decidedPayable > 0 ? round(($approved / $decidedPayable) * 100, 1) : null,
            'pending_value' => round($payable - $decidedPayable, 3),
            // And of what they agreed, how much actually arrived?
            'collection_rate' => $approved > 0 ? round(($paid / $approved) * 100, 1) : null,
            'rejection_rate' => $decided > 0 ? round(($rejectedCount / $decided) * 100, 1) : null,
            'avg_days_to_pay' => $settled && $settled->n > 0 ? round((float) $settled->d, 1) : null,
            'settled_count' => (int) ($settled->n ?? 0),
        ];

        // ---- Per-insurer scorecard -------------------------------------------
        $byInsurer = $base()
            ->groupBy('i.id', 'i.name')
            ->selectRaw("COALESCE(i.name, '(no insurer)') as insurer, i.id as insurer_id,
                COUNT(*) as claims,
                COALESCE(SUM(c.insurer_payable),0) as payable,
                COALESCE(SUM(c.approved_amount),0) as approved,
                COALESCE(SUM(c.paid_amount),0) as paid,
                COALESCE(SUM(c.rejected_amount),0) as rejected,
                COALESCE(SUM(c.write_off_amount),0) as written_off,
                AVG(CASE WHEN c.paid_at IS NOT NULL AND c.submitted_at IS NOT NULL
                    THEN DATEDIFF(c.paid_at, c.submitted_at) END) as days_to_pay,
                SUM(CASE WHEN c.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                COALESCE(SUM(CASE WHEN c.status IN ('approved','partially_approved','rejected','paid')
                    THEN c.insurer_payable ELSE 0 END),0) as decided_payable")
            ->orderByDesc('payable')->get()
            ->map(function ($r) {
                $payable = (float) $r->payable;
                $approved = (float) $r->approved;
                $decidedPayable = (float) $r->decided_payable;

                return [
                    'insurer' => $this->name($r->insurer),
                    'claims' => (int) $r->claims,
                    'payable' => round($payable, 3),
                    'approved' => round($approved, 3),
                    'paid' => round((float) $r->paid, 3),
                    'rejected' => round((float) $r->rejected, 3),
                    'written_off' => round((float) $r->written_off, 3),
                    'outstanding' => round($payable - (float) $r->paid - (float) $r->written_off - (float) $r->rejected, 3),
                    'approval_rate' => $decidedPayable > 0 ? round(($approved / $decidedPayable) * 100, 1) : null,
                    'collection_rate' => $approved > 0 ? round(((float) $r->paid / $approved) * 100, 1) : null,
                    'days_to_pay' => $r->days_to_pay !== null ? round((float) $r->days_to_pay, 1) : null,
                    'rejected_count' => (int) $r->rejected_count,
                ];
            })->all();

        // ---- Where claims are sitting -----------------------------------------
        $statusMix = $base()->groupBy('c.status')
            ->selectRaw('c.status as status, COUNT(*) as c, COALESCE(SUM(c.insurer_payable),0) as value')
            ->orderByDesc('value')->get()
            ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->c, 'value' => round((float) $r->value, 3)])->all();

        // ---- Aging of what is still owed ----------------------------------------
        // Buckets run from submission, which is when the clock starts for an insurer.
        $agingRows = $base()
            ->whereNotIn('c.status', ['draft', 'void', 'rejected'])
            ->whereRaw('(c.insurer_payable - c.paid_amount - c.write_off_amount - c.rejected_amount) > 0.005')
            ->selectRaw('c.submitted_at, c.created_at,
                (c.insurer_payable - c.paid_amount - c.write_off_amount - c.rejected_amount) as balance')
            ->get();

        $buckets = ['0–30' => 0.0, '31–60' => 0.0, '61–90' => 0.0, '90+' => 0.0];
        $bucketCounts = ['0–30' => 0, '31–60' => 0, '61–90' => 0, '90+' => 0];
        foreach ($agingRows as $row) {
            $age = Carbon::parse($row->submitted_at ?? $row->created_at)->diffInDays(Carbon::now());
            $key = match (true) {
                $age <= 30 => '0–30',
                $age <= 60 => '31–60',
                $age <= 90 => '61–90',
                default => '90+',
            };
            $buckets[$key] += (float) $row->balance;
            $bucketCounts[$key]++;
        }
        $aging = array_map(
            fn ($label) => ['label' => $label, 'value' => round($buckets[$label], 3), 'count' => $bucketCounts[$label]],
            array_keys($buckets)
        );

        // ---- Why claims get refused ----------------------------------------------
        $rejectionReasons = $base()
            ->whereIn('c.status', ['rejected', 'partially_approved'])
            ->whereNotNull('c.decision_notes')
            ->selectRaw('c.decision_notes as reason, c.status, COUNT(*) as c, COALESCE(SUM(c.rejected_amount),0) as value')
            ->groupBy('c.decision_notes', 'c.status')
            ->orderByDesc('c')->limit(15)->get()
            ->map(fn ($r) => [
                'reason' => \Illuminate\Support\Str::limit((string) $r->reason, 90),
                'status' => (string) $r->status,
                'count' => (int) $r->c,
                'value' => round((float) $r->value, 3),
            ])->all();

        // ---- Pre-authorisation pipeline ---------------------------------------------
        $preauthQ = DB::table('insurance_preauthorizations as pa')->whereNull('pa.deleted_at')
            ->whereBetween('pa.requested_at', [$from, $to]);
        if ($filters['branch_id']) {
            $preauthQ->where('pa.branch_id', $filters['branch_id']);
        } elseif ($branchIds !== null) {
            $preauthQ->whereIn('pa.branch_id', $branchIds ?: [0]);
        }

        $preauthRows = (clone $preauthQ)->groupBy('pa.status')
            ->selectRaw('pa.status as status, COUNT(*) as c, COALESCE(SUM(pa.estimated_total),0) as estimated,
                COALESCE(SUM(pa.approved_amount),0) as approved')
            ->get();

        $preauthTotal = (int) $preauthRows->sum('c');
        $preauthApproved = (int) $preauthRows->whereIn('status', ['approved', 'partially_approved'])->sum('c');

        $preauth = [
            'rows' => $preauthRows->map(fn ($r) => [
                'status' => (string) $r->status,
                'count' => (int) $r->c,
                'estimated' => round((float) $r->estimated, 3),
                'approved' => round((float) $r->approved, 3),
            ])->all(),
            'total' => $preauthTotal,
            'approval_rate' => $preauthTotal > 0 ? round(($preauthApproved / $preauthTotal) * 100, 1) : null,
        ];

        // ---- Monthly trend --------------------------------------------------------------
        $trend = $base()
            ->groupBy(DB::raw("DATE_FORMAT(c.created_at, '%Y-%m')"))
            ->selectRaw("DATE_FORMAT(c.created_at, '%Y-%m') as m,
                COUNT(*) as claims,
                COALESCE(SUM(c.insurer_payable),0) as payable,
                COALESCE(SUM(c.paid_amount),0) as paid")
            ->orderBy('m')->get()
            ->map(fn ($r) => [
                'label' => Carbon::createFromFormat('Y-m', $r->m)->format('M Y'),
                'claims' => (int) $r->claims,
                'payable' => round((float) $r->payable, 3),
                'paid' => round((float) $r->paid, 3),
            ])->all();

        return [
            'kpis' => $kpis,
            'by_insurer' => $byInsurer,
            'status_mix' => $statusMix,
            'aging' => $aging,
            'rejection_reasons' => $rejectionReasons,
            'preauth' => $preauth,
            'trend' => $trend,
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
