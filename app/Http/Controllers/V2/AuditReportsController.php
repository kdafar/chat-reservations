<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\ResolvesAccessibleClinics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit & Compliance Report — who did what, and which of it deserves a second look.
 *
 * The activity log holds everything but answers nothing: it is a flat feed with no
 * notion of which entries matter. This separates the money-and-privacy actions
 * (reversals, voids, deletions, discount overrides, write-offs, refunds) from the
 * ordinary traffic, attributes them to a person, and cross-checks the general feed
 * against the sources that carry their own audit trail — journal entry reversals
 * and patient-file access logs — so nothing sensitive depends on a single table.
 */
class AuditReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    /** Working hours in clinic-local time; anything outside is flagged. */
    private const DAY_START = 8;

    private const DAY_END = 21;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_audit_reports')) {
            abort(403, 'Not authorized to view audit reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $filters = [
            'from' => $request->input('from') ?: Carbon::now($tz)->subDays(29)->toDateString(),
            'to' => $request->input('to') ?: Carbon::now($tz)->toDateString(),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            'user_id' => $request->input('user_id', '') !== '' ? (int) $request->input('user_id') : null,
        ];
        $from = Carbon::parse($filters['from'], $tz)->startOfDay();
        $to = Carbon::parse($filters['to'], $tz)->endOfDay();

        $branchIds = $this->accessibleBranchIds();

        $payload = fn () => $this->build($filters, $from, $to, $branchIds);
        $memo = null;
        $get = function (string $key) use (&$memo, $payload) {
            $memo ??= $payload();

            return $memo[$key];
        };

        return Inertia::render('Reports/AuditReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'audit'),
            'sensitive' => Inertia::defer(fn () => $get('sensitive'), 'audit'),
            'by_user' => Inertia::defer(fn () => $get('by_user'), 'audit'),
            'by_subject' => Inertia::defer(fn () => $get('by_subject'), 'audit'),
            'after_hours' => Inertia::defer(fn () => $get('after_hours'), 'audit'),
            'action_trend' => Inertia::defer(fn () => $get('action_trend'), 'audit'),
            'phi_by_user' => Inertia::defer(fn () => $get('phi_by_user'), 'audit'),
            'phi_recent' => Inertia::defer(fn () => $get('phi_recent'), 'audit'),
            'je_integrity' => Inertia::defer(fn () => $get('je_integrity'), 'audit'),
            'je_reversals' => Inertia::defer(fn () => $get('je_reversals'), 'audit'),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
            'users' => $this->staffOptions($branchIds),
        ]);
    }

    protected function build(array $filters, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        // null = unrestricted; otherwise the branch ids every branch-aware query is confined to.
        $branchFilter = $filters['branch_id']
            ? [$filters['branch_id']]
            : ($branchIds !== null ? ($branchIds ?: [0]) : null);

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        // activity_log carries no branch_id, so a branch filter can only be honoured
        // through the acting user's branch membership. Rows with no causer (background
        // jobs, seeded history) therefore drop out of a branch-filtered view — the UI
        // says so rather than pretending the filter was free.
        $causerIds = null;
        if ($branchFilter !== null) {
            $staff = DB::table('branch_user')->whereIn('branch_id', $branchFilter)->pluck('user_id');
            $docs = DB::table('doctors')->whereIn('branch_id', $branchFilter)->whereNotNull('user_id')->pluck('user_id');
            $causerIds = $staff->merge($docs)->map(fn ($x) => (int) $x)->unique()->values()->all() ?: [0];
        }
        if ($filters['user_id']) {
            $causerIds = [$filters['user_id']];
        }

        $log = function () use ($from, $to, $causerIds) {
            $q = DB::table('activity_log')->whereBetween('activity_log.created_at', [$from, $to]);
            if ($causerIds !== null) {
                $q->whereIn('activity_log.causer_id', $causerIds);
            }

            return $q;
        };

        $afterHours = fn ($q) => $q->whereRaw('(HOUR(activity_log.created_at) < ? OR HOUR(activity_log.created_at) >= ?)', [self::DAY_START, self::DAY_END]);

        // ---- PHI access (its own trail, branch-scoped through the file) --------
        $phiBase = function () use ($from, $to, $branchFilter, $filters) {
            $q = DB::table('patient_file_access_logs as pal')
                ->leftJoin('patient_files as pf', 'pf.id', '=', 'pal.patient_file_id')
                ->whereBetween('pal.accessed_at', [$from, $to]);
            if ($branchFilter !== null) {
                $q->whereIn('pf.branch_id', $branchFilter);
            }
            if ($filters['user_id']) {
                $q->where('pal.accessed_by_user_id', $filters['user_id']);
            }

            return $q;
        };

        // ---- Sensitive-action sources -----------------------------------------
        $sources = $this->sensitiveSources($from, $to, $fromDate, $toDate, $branchFilter, $filters, $causerIds);

        $sensitiveCount = 0;
        $sensitive = [];
        foreach ($sources as $src) {
            $sensitiveCount += (int) (clone $src['query'])->count();
            foreach ((clone $src['query'])->orderByDesc($src['order'])->limit($src['take'])->get() as $row) {
                $sensitive[] = ($src['map'])($row);
            }
        }
        usort($sensitive, fn ($a, $b) => strcmp((string) $b['ts'], (string) $a['ts']));
        $sensitive = array_slice($sensitive, 0, 60);

        // ---- KPIs ---------------------------------------------------------------
        $total = (int) $log()->count();
        $kpis = [
            'total_actions' => $total,
            'unattributed' => (int) $log()->whereNull('activity_log.causer_id')->count(),
            'active_users' => (int) $log()->whereNotNull('activity_log.causer_id')->distinct()->count('activity_log.causer_id'),
            'sensitive' => $sensitiveCount,
            'after_hours' => (int) $afterHours($log())->count(),
            'after_hours_unattributed' => (int) $afterHours($log())->whereNull('activity_log.causer_id')->count(),
            'deletions' => (int) $log()->where('activity_log.event', 'deleted')->count(),
            'phi_access' => (int) $phiBase()->count(),
            'branch_scoped_log' => $causerIds !== null,
        ];

        $roles = $this->rolesByUser();

        // ---- Activity by user ----------------------------------------------------
        $sensitiveByUser = [];
        foreach ($sensitive as $s) {
            if ($s['user_id']) {
                $sensitiveByUser[$s['user_id']] = ($sensitiveByUser[$s['user_id']] ?? 0) + 1;
            }
        }

        $byUser = $log()
            ->leftJoin('users', 'users.id', '=', 'activity_log.causer_id')
            ->whereNotNull('activity_log.causer_id')
            ->groupBy('activity_log.causer_id', 'users.name')
            ->selectRaw('activity_log.causer_id as uid, users.name as uname, COUNT(*) as total,
                SUM(CASE WHEN activity_log.event = ? THEN 1 ELSE 0 END) as deleted_c,
                SUM(CASE WHEN HOUR(activity_log.created_at) < ? OR HOUR(activity_log.created_at) >= ? THEN 1 ELSE 0 END) as odd_c,
                MAX(activity_log.created_at) as last_at', ['deleted', self::DAY_START, self::DAY_END])
            ->orderByDesc('total')->limit(20)->get()
            ->map(fn ($r) => [
                'user' => $this->userLabel($r->uname, $r->uid),
                'role' => $roles[(int) $r->uid] ?? '—',
                'total' => (int) $r->total,
                'sensitive' => (int) ($sensitiveByUser[(int) $r->uid] ?? 0),
                'deletions' => (int) $r->deleted_c,
                'after_hours' => (int) $r->odd_c,
                'last_at' => $r->last_at ? Carbon::parse($r->last_at)->format('d M Y H:i') : '—',
            ])->all();

        // ---- What gets touched ----------------------------------------------------
        $bySubject = $log()
            ->whereNotNull('activity_log.subject_type')
            ->groupBy('activity_log.subject_type')
            ->selectRaw('activity_log.subject_type as st, COUNT(*) as total,
                SUM(CASE WHEN activity_log.event = ? THEN 1 ELSE 0 END) as created_c,
                SUM(CASE WHEN activity_log.event = ? THEN 1 ELSE 0 END) as updated_c,
                SUM(CASE WHEN activity_log.event = ? THEN 1 ELSE 0 END) as deleted_c', ['created', 'updated', 'deleted'])
            ->orderByDesc('total')->limit(15)->get()
            ->map(fn ($r) => [
                'subject' => $this->shortModel($r->st),
                'total' => (int) $r->total,
                'created' => (int) $r->created_c,
                'updated' => (int) $r->updated_c,
                'deleted' => (int) $r->deleted_c,
                'share' => $total > 0 ? round(((int) $r->total / $total) * 100, 1) : 0,
            ])->all();

        // ---- After hours, by person -------------------------------------------------
        $afterHoursByUser = $afterHours($log())
            ->leftJoin('users', 'users.id', '=', 'activity_log.causer_id')
            ->whereNotNull('activity_log.causer_id')
            ->groupBy('activity_log.causer_id', 'users.name')
            ->selectRaw('activity_log.causer_id as uid, users.name as uname, COUNT(*) as total,
                MIN(HOUR(activity_log.created_at)) as min_h, MAX(HOUR(activity_log.created_at)) as max_h,
                MAX(activity_log.created_at) as last_at')
            ->orderByDesc('total')->limit(15)->get()
            ->map(fn ($r) => [
                'user' => $this->userLabel($r->uname, $r->uid),
                'role' => $roles[(int) $r->uid] ?? '—',
                'total' => (int) $r->total,
                'window' => sprintf('%02d:00 – %02d:59', (int) $r->min_h, (int) $r->max_h),
                'last_at' => $r->last_at ? Carbon::parse($r->last_at)->format('d M Y H:i') : '—',
            ])->all();

        // ---- Daily action mix -----------------------------------------------------
        $trend = $log()
            ->groupBy(DB::raw('DATE(activity_log.created_at)'))
            ->selectRaw('DATE(activity_log.created_at) as d,
                SUM(CASE WHEN activity_log.event = ? THEN 1 ELSE 0 END) as created_c,
                SUM(CASE WHEN activity_log.event = ? THEN 1 ELSE 0 END) as updated_c,
                SUM(CASE WHEN activity_log.event = ? THEN 1 ELSE 0 END) as deleted_c', ['created', 'updated', 'deleted'])
            ->orderBy('d')->get()
            ->map(fn ($r) => [
                'date' => Carbon::parse($r->d)->format('d M'),
                'created' => (int) $r->created_c,
                'updated' => (int) $r->updated_c,
                'deleted' => (int) $r->deleted_c,
            ])->all();

        // ---- PHI trail ------------------------------------------------------------
        $phiByUser = $phiBase()
            ->leftJoin('users', 'users.id', '=', 'pal.accessed_by_user_id')
            ->groupBy('pal.accessed_by_user_id', 'users.name')
            ->selectRaw('pal.accessed_by_user_id as uid, users.name as uname, COUNT(*) as total,
                SUM(CASE WHEN pal.action = ? THEN 1 ELSE 0 END) as view_c,
                SUM(CASE WHEN pal.action = ? THEN 1 ELSE 0 END) as download_c,
                SUM(CASE WHEN pal.action = ? THEN 1 ELSE 0 END) as delete_c,
                COUNT(DISTINCT pf.patient_id) as patients_c,
                MAX(pal.accessed_at) as last_at', ['view', 'download', 'delete'])
            ->orderByDesc('total')->limit(15)->get()
            ->map(fn ($r) => [
                'user' => $this->userLabel($r->uname, $r->uid),
                'role' => $roles[(int) $r->uid] ?? '—',
                'total' => (int) $r->total,
                'views' => (int) $r->view_c,
                'downloads' => (int) $r->download_c,
                'deletes' => (int) $r->delete_c,
                'patients' => (int) $r->patients_c,
                'last_at' => $r->last_at ? Carbon::parse($r->last_at)->format('d M Y H:i') : '—',
            ])->all();

        $phiRecent = $phiBase()
            ->leftJoin('users', 'users.id', '=', 'pal.accessed_by_user_id')
            ->orderByDesc('pal.accessed_at')->limit(40)
            ->get([
                'pal.accessed_at', 'pal.action', 'pal.ip_address', 'users.name as uname',
                'pf.patient_id', 'pf.category', 'pf.original_filename',
            ])
            ->map(fn ($r) => [
                'at' => $r->accessed_at ? Carbon::parse($r->accessed_at)->format('d M Y H:i') : '—',
                'user' => $this->name($r->uname),
                'action' => (string) ($r->action ?: '—'),
                'patient' => $r->patient_id ? '#'.$r->patient_id : '—',
                'category' => (string) ($r->category ?: '—'),
                'file' => (string) ($r->original_filename ?: '—'),
                'ip' => (string) ($r->ip_address ?: '—'),
            ])->all();

        // ---- Journal entry integrity ------------------------------------------------
        $jeBase = function () use ($fromDate, $toDate, $branchFilter) {
            $q = DB::table('journal_entries')->whereBetween('journal_entries.entry_date', [$fromDate, $toDate]);
            if ($branchFilter !== null) {
                $q->whereIn('journal_entries.branch_id', $branchFilter);
            }

            return $q;
        };

        $statusCounts = $jeBase()->groupBy('journal_entries.status')
            ->selectRaw('journal_entries.status as st, COUNT(*) as total')->pluck('total', 'st');

        $jeIntegrity = [
            'posted' => (int) ($statusCounts['posted'] ?? 0),
            'reversed' => (int) ($statusCounts['reversed'] ?? 0),
            'draft' => (int) ($statusCounts['draft'] ?? 0),
            'reversal_entries' => (int) (clone $jeBase())->whereNotNull('journal_entries.reversal_of_id')->count(),
        ];
        $jeIntegrity['reversal_rate'] = $jeIntegrity['posted'] + $jeIntegrity['reversed'] > 0
            ? round(($jeIntegrity['reversed'] / ($jeIntegrity['posted'] + $jeIntegrity['reversed'])) * 100, 1)
            : 0;

        $jeReversals = $jeBase()
            ->leftJoin('journal_entries as orig', 'orig.id', '=', 'journal_entries.reversal_of_id')
            ->leftJoin('users', 'users.id', '=', 'journal_entries.posted_by_user_id')
            ->leftJoin('branches', 'branches.id', '=', 'journal_entries.branch_id')
            ->whereNotNull('journal_entries.reversal_of_id')
            ->orderByDesc('journal_entries.entry_date')->orderByDesc('journal_entries.id')->limit(40)
            ->get([
                'journal_entries.code', 'journal_entries.entry_date', 'journal_entries.narration',
                'orig.code as orig_code', 'orig.entry_date as orig_date',
                'users.name as uname', 'branches.name as bname',
            ])
            ->map(fn ($r) => [
                'code' => (string) ($r->code ?: '—'),
                'date' => $r->entry_date ? Carbon::parse($r->entry_date)->format('d M Y') : '—',
                'original' => (string) ($r->orig_code ?: '—'),
                'original_date' => $r->orig_date ? Carbon::parse($r->orig_date)->format('d M Y') : '—',
                'reason' => $this->reason($r->narration),
                'user' => $this->name($r->uname),
                'branch' => $this->name($r->bname),
            ])->all();

        return [
            'kpis' => $kpis,
            'sensitive' => $sensitive,
            'by_user' => $byUser,
            'by_subject' => $bySubject,
            'after_hours' => $afterHoursByUser,
            'action_trend' => $trend,
            'phi_by_user' => $phiByUser,
            'phi_recent' => $phiRecent,
            'je_integrity' => $jeIntegrity,
            'je_reversals' => $jeReversals,
        ];
    }

    /**
     * The actions worth a second look, each drawn from the table that actually
     * records it rather than from the generic activity feed — a reversal or a
     * refund is a state on its own row, and reading it there survives a gap in
     * the log.
     *
     * @return array<int, array{query: \Illuminate\Database\Query\Builder, order: string, take: int, map: callable}>
     */
    protected function sensitiveSources(Carbon $from, Carbon $to, string $fromDate, string $toDate, ?array $branchFilter, array $filters, ?array $causerIds): array
    {
        $inBranch = function ($q, string $col) use ($branchFilter) {
            if ($branchFilter !== null) {
                $q->whereIn($col, $branchFilter);
            }

            return $q;
        };
        $byUser = function ($q, string $col) use ($filters) {
            if ($filters['user_id']) {
                $q->where($col, $filters['user_id']);
            }

            return $q;
        };

        $sources = [];

        // Reversed / reversing journal entries.
        $sources[] = [
            'query' => $byUser($inBranch(
                DB::table('journal_entries')
                    ->leftJoin('journal_entries as orig', 'orig.id', '=', 'journal_entries.reversal_of_id')
                    ->leftJoin('users', 'users.id', '=', 'journal_entries.posted_by_user_id')
                    ->whereNotNull('journal_entries.reversal_of_id')
                    ->whereBetween('journal_entries.entry_date', [$fromDate, $toDate]),
                'journal_entries.branch_id'
            ), 'journal_entries.posted_by_user_id'),
            'order' => 'journal_entries.entry_date',
            'take' => 20,
            'map' => fn ($r) => [
                'ts' => (string) ($r->posted_at ?: $r->entry_date),
                'when' => $r->entry_date ? Carbon::parse($r->entry_date)->format('d M Y') : '—',
                'user_id' => (int) ($r->posted_by_user_id ?: 0),
                'user' => $this->name($r->uname),
                'action' => 'je_reversal',
                'subject' => (string) ($r->code ?: '—'),
                'detail' => trim(($r->orig_code ? 'Reverses '.$r->orig_code.' — ' : '').$this->reason($r->narration)),
                'amount' => null,
                'severity' => 'high',
            ],
            'select' => [
                'journal_entries.code', 'journal_entries.entry_date', 'journal_entries.posted_at',
                'journal_entries.narration', 'journal_entries.posted_by_user_id',
                'orig.code as orig_code', 'users.name as uname',
            ],
        ];

        // Voided expenses.
        $sources[] = [
            'query' => $byUser($inBranch(
                DB::table('expenses')
                    ->leftJoin('users', 'users.id', '=', 'expenses.posted_by_user_id')
                    ->where('expenses.status', 'void')
                    ->whereBetween('expenses.updated_at', [$from, $to]),
                'expenses.branch_id'
            ), 'expenses.posted_by_user_id'),
            'order' => 'expenses.updated_at',
            'take' => 20,
            'map' => fn ($r) => [
                'ts' => (string) $r->updated_at,
                'when' => $r->updated_at ? Carbon::parse($r->updated_at)->format('d M Y H:i') : '—',
                'user_id' => (int) ($r->posted_by_user_id ?: 0),
                'user' => $this->name($r->uname),
                'action' => 'expense_void',
                'subject' => (string) ($r->code ?: '—'),
                'detail' => (string) ($r->description ?: '—'),
                'amount' => round((float) $r->amount, 3),
                'severity' => 'high',
            ],
            'select' => [
                'expenses.code', 'expenses.updated_at', 'expenses.amount', 'expenses.description',
                'expenses.posted_by_user_id', 'users.name as uname',
            ],
        ];

        // Hard deletions of clinical / patient records.
        $deleted = DB::table('activity_log')
            ->leftJoin('users', 'users.id', '=', 'activity_log.causer_id')
            ->where('activity_log.event', 'deleted')
            ->whereIn('activity_log.subject_type', [
                'App\\Models\\Visit', 'App\\Models\\Booking', 'App\\Models\\Patient',
                'App\\Models\\VisitCharge', 'App\\Models\\VisitPayment', 'App\\Models\\PatientFile',
            ])
            ->whereBetween('activity_log.created_at', [$from, $to]);
        if ($causerIds !== null) {
            $deleted->whereIn('activity_log.causer_id', $causerIds);
        }
        $sources[] = [
            'query' => $deleted,
            'order' => 'activity_log.created_at',
            'take' => 25,
            'map' => fn ($r) => [
                'ts' => (string) $r->created_at,
                'when' => Carbon::parse($r->created_at)->format('d M Y H:i'),
                'user_id' => (int) ($r->causer_id ?: 0),
                'user' => $this->name($r->uname),
                'action' => 'deleted',
                'subject' => $this->shortModel($r->subject_type).' #'.$r->subject_id,
                'detail' => $this->propertyHint($r->properties),
                'amount' => null,
                'severity' => 'high',
            ],
            'select' => [
                'activity_log.created_at', 'activity_log.causer_id', 'activity_log.subject_type',
                'activity_log.subject_id', 'activity_log.properties', 'users.name as uname',
            ],
        ];

        // Manual discount overrides — a coupon is a policy, a typed-in discount is a decision.
        $sources[] = [
            'query' => $byUser($inBranch(
                DB::table('visits')
                    ->leftJoin('users', 'users.id', '=', 'visits.updated_by_user_id')
                    ->where('visits.discount_total', '>', 0)
                    ->whereNull('visits.coupon_id')
                    ->whereBetween('visits.created_at', [$from, $to]),
                'visits.branch_id'
            ), 'visits.updated_by_user_id'),
            'order' => 'visits.created_at',
            'take' => 15,
            'map' => fn ($r) => [
                'ts' => (string) $r->created_at,
                'when' => Carbon::parse($r->created_at)->format('d M Y H:i'),
                'user_id' => (int) ($r->updated_by_user_id ?: 0),
                'user' => $this->name($r->uname),
                'action' => 'discount_override',
                'subject' => 'Visit #'.$r->id,
                'detail' => 'off '.number_format((float) $r->fees_total, 3).' fees'
                    .($r->discount_type ? ' · '.$r->discount_type.' '.rtrim(rtrim(number_format((float) $r->discount_value, 3), '0'), '.') : ''),
                'amount' => round((float) $r->discount_total, 3),
                'severity' => 'medium',
            ],
            'select' => [
                'visits.id', 'visits.created_at', 'visits.discount_total', 'visits.discount_type',
                'visits.discount_value', 'visits.fees_total', 'visits.updated_by_user_id', 'users.name as uname',
            ],
        ];

        // Claim write-offs and rejections — revenue given up.
        $sources[] = [
            'query' => $byUser($inBranch(
                DB::table('insurance_claim_state_logs as csl')
                    ->join('insurance_claims as ic', 'ic.id', '=', 'csl.claim_id')
                    ->leftJoin('users', 'users.id', '=', 'csl.changed_by_user_id')
                    ->where(fn ($q) => $q->whereIn('csl.to_status', ['rejected', 'void'])->orWhere('ic.write_off_amount', '>', 0))
                    ->whereBetween('csl.changed_at', [$from, $to]),
                'ic.branch_id'
            ), 'csl.changed_by_user_id'),
            'order' => 'csl.changed_at',
            'take' => 20,
            'map' => fn ($r) => [
                'ts' => (string) $r->changed_at,
                'when' => $r->changed_at ? Carbon::parse($r->changed_at)->format('d M Y H:i') : '—',
                'user_id' => (int) ($r->changed_by_user_id ?: 0),
                'user' => $this->name($r->uname),
                'action' => 'claim_writeoff',
                'subject' => (string) ($r->claim_number ?: 'Claim #'.$r->claim_id),
                'detail' => trim(($r->from_status ? $r->from_status.' → ' : '').$r->to_status
                    .($r->notes ? ' · '.$r->notes : '')),
                'amount' => (float) $r->write_off_amount > 0 ? round((float) $r->write_off_amount, 3) : null,
                'severity' => 'medium',
            ],
            'select' => [
                'csl.changed_at', 'csl.from_status', 'csl.to_status', 'csl.notes', 'csl.claim_id',
                'csl.changed_by_user_id', 'ic.claim_number', 'ic.write_off_amount', 'users.name as uname',
            ],
        ];

        // Refunded payments.
        $sources[] = [
            'query' => $byUser($inBranch(
                DB::table('visit_payments as vp')
                    ->join('visits as v', 'v.id', '=', 'vp.visit_id')
                    ->leftJoin('users', 'users.id', '=', 'vp.collected_by_user_id')
                    ->where('vp.status', 'refunded')
                    ->whereBetween('vp.paid_at', [$from, $to]),
                'v.branch_id'
            ), 'vp.collected_by_user_id'),
            'order' => 'vp.paid_at',
            'take' => 20,
            'map' => fn ($r) => [
                'ts' => (string) $r->paid_at,
                'when' => $r->paid_at ? Carbon::parse($r->paid_at)->format('d M Y H:i') : '—',
                'user_id' => (int) ($r->collected_by_user_id ?: 0),
                'user' => $this->name($r->uname),
                'action' => 'payment_refund',
                'subject' => 'Visit #'.$r->visit_id,
                'detail' => ($r->method ?: '—').($r->reference_no ? ' · '.$r->reference_no : ''),
                'amount' => round((float) $r->amount, 3),
                'severity' => 'high',
            ],
            'select' => [
                'vp.paid_at', 'vp.amount', 'vp.method', 'vp.reference_no', 'vp.visit_id',
                'vp.collected_by_user_id', 'users.name as uname',
            ],
        ];

        foreach ($sources as $i => $s) {
            $sources[$i]['query'] = $s['query']->select($s['select']);
        }

        return $sources;
    }

    /** Every user's role names, keyed by user id. */
    protected function rolesByUser(): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->select('model_has_roles.model_id as uid', 'roles.name as rname')
            ->get()
            ->groupBy('uid')
            ->map(fn ($g) => $g->pluck('rname')->unique()->implode(', '))
            ->all();
    }

    /** Staff who could plausibly appear as a causer, for the user filter. */
    protected function staffOptions(?array $branchIds): array
    {
        return DB::table('users')
            ->join('model_has_roles', function ($j) {
                $j->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', \App\Models\User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereNotIn('roles.name', ['customer'])
            ->when($branchIds !== null, fn ($q) => $q->whereIn('users.id', function ($sub) use ($branchIds) {
                $sub->from('branch_user')->select('user_id')->whereIn('branch_id', $branchIds ?: [0]);
            }))
            ->distinct()
            ->orderBy('users.name')
            ->limit(300)
            ->get(['users.id', 'users.name'])
            ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $this->name($u->name)])
            ->all();
    }

    /** "Reversal of JE-x — Payment refunded" carries the reason after the dash. */
    protected function reason(?string $narration): string
    {
        $n = trim((string) $narration);
        if ($n === '') {
            return '—';
        }
        foreach ([' — ', ' - ', ' – '] as $sep) {
            $pos = mb_strrpos($n, $sep);
            if ($pos !== false) {
                return trim(mb_substr($n, $pos + mb_strlen($sep)));
            }
        }

        return $n;
    }

    /** A recognisable handle for a deleted row, pulled from the logged snapshot. */
    protected function propertyHint($properties): string
    {
        $d = is_string($properties) ? json_decode($properties, true) : (is_array($properties) ? $properties : null);
        if (! is_array($d)) {
            return '—';
        }
        $old = $d['old'] ?? $d['attributes'] ?? [];
        if (! is_array($old)) {
            return '—';
        }
        $bits = [];
        foreach (['booking_code', 'claim_number', 'code', 'name', 'label', 'status', 'amount', 'original_filename'] as $k) {
            if (isset($old[$k]) && is_scalar($old[$k]) && $old[$k] !== '') {
                $bits[] = $k.': '.$old[$k];
            }
        }

        return $bits ? implode(' · ', array_slice($bits, 0, 3)) : '—';
    }

    protected function shortModel(?string $fqcn): string
    {
        $fqcn = (string) $fqcn;

        return $fqcn === '' ? '—' : (string) (last(explode('\\', $fqcn)) ?: $fqcn);
    }

    /** A causer whose user row is gone still has to be named — the id is the audit trail. */
    protected function userLabel($name, $id): string
    {
        $n = $this->name($name);

        return $n !== '—' ? $n : ($id ? 'User #'.((int) $id).' (removed)' : '—');
    }

    /** Some names are stored as {en,ar} JSON blobs. */
    protected function name($value): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $d = json_decode($value, true);
            if (is_array($d)) {
                return $d[app()->getLocale()] ?? $d['en'] ?? (array_values($d)[0] ?? '—');
            }
        }
        $s = trim((string) ($value ?? ''));

        return $s === '' ? '—' : $s;
    }
}
