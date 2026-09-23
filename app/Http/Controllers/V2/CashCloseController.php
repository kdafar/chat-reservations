<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashClose;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Services\Clinic\ClinicPaymentMethodResolver;
use App\Support\ResolvesAccessibleClinics;
use App\Support\VisitAuthorization;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily cash close for reception (visit workspace → "Daily cash close").
 *
 *   summary()  what the system recorded today for one branch: paid payments
 *              per method, voids, visits still owing, today's close (if any)
 *              and a suggested opening float (the last close's counted cash).
 *   store()    saves the close. Every expected total and difference is
 *              recomputed here from the database — client totals are ignored.
 *
 * "Today" is the business day in the app timezone. There is no session-level
 * "current branch" in this app, so the branch is the requested `branch_id`
 * (if the user may act in it), else the user's first accessible branch (hub
 * first for a global admin). The summary returns the branch options so the
 * UI can switch when there is more than one.
 */
class CashCloseController extends Controller
{
    use ResolvesAccessibleClinics;
    use VisitAuthorization;

    public function summary(Request $request): JsonResponse
    {
        if (! $this->mayClose()) {
            return response()->json(['ok' => false, 'error' => 'Not authorized to close the cash drawer.'], 403);
        }

        $branch = $this->resolveBranch($request);
        if (! $branch) {
            return response()->json(['ok' => false, 'error' => 'No branch available for this user.'], 422);
        }

        $date = $this->businessDate();
        $expected = $this->computeExpected($branch, $date);

        $close = CashClose::query()
            ->where('branch_id', $branch->id)
            ->whereDate('business_date', $date->toDateString())
            ->first();

        $last = CashClose::query()
            ->where('branch_id', $branch->id)
            ->whereDate('business_date', '<', $date->toDateString())
            ->orderByDesc('business_date')
            ->first();

        return response()->json([
            'ok' => true,
            'business_date' => $date->toDateString(),
            'branch' => ['id' => $branch->id, 'name' => $this->branchName($branch)],
            'branches' => $this->accessibleBranches()->map(fn ($b) => ['id' => $b['id'], 'name' => $b['name']])->values(),
            'methods' => $expected['methods'],
            'total' => $expected['total'],
            'count' => $expected['count'],
            'cash_total' => $expected['cash_total'],
            'knet_total' => $expected['knet_total'],
            'voided' => $expected['voided'],
            'owing' => $this->owingToday($branch, $date),
            'suggested_float' => $last ? round((float) $last->cash_counted, 3) : 0.0,
            'last_close_date' => $last?->business_date?->toDateString(),
            'close' => $close ? $this->present($close) : null,
            'can_reopen' => $this->isAdminUser(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->mayClose()) {
            return response()->json(['ok' => false, 'error' => 'Not authorized to close the cash drawer.'], 403);
        }

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'opening_float' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'denominations' => ['nullable', 'array', 'max:30'],
            'coins' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'knet_slip' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'reopen' => ['nullable', 'boolean'],
        ]);

        $branch = $this->resolveBranch($request);
        if (! $branch) {
            return response()->json(['ok' => false, 'error' => 'No branch available for this user.'], 422);
        }

        // Denomination keys are note values ("20", "0.5", ...). They are read
        // raw: a dotted key like "0.5" would be split into a path by the
        // validator's `denominations.*` rule. Checked by hand instead.
        $denoms = [];
        foreach ((array) $request->input('denominations', []) as $value => $count) {
            if (! is_numeric($value) || (float) $value <= 0) {
                return response()->json(['ok' => false, 'error' => "Invalid note value: {$value}"], 422);
            }
            if ($count === null || $count === '') {
                continue;
            }
            if (! is_numeric($count) || (float) $count < 0 || (float) $count > 100000 || floor((float) $count) != (float) $count) {
                return response()->json(['ok' => false, 'error' => "Invalid count for note {$value}."], 422);
            }
            $count = (int) $count;
            if ($count > 0) {
                $denoms[$this->r3s((float) $value)] = $count;
            }
        }
        $coins = round((float) ($data['coins'] ?? 0), 3);

        $counted = $coins;
        foreach ($denoms as $value => $count) {
            $counted += (float) $value * $count;
        }
        $counted = round($counted, 3);

        $date = $this->businessDate();
        $expected = $this->computeExpected($branch, $date);
        $float = round((float) $data['opening_float'], 3);
        $cashExpected = round($float + $expected['cash_total'], 3);
        $knetSlip = isset($data['knet_slip']) ? round((float) $data['knet_slip'], 3) : null;

        $payload = [
            'branch_id' => $branch->id,
            'business_date' => $date->toDateString(),
            'closed_by_user_id' => auth()->id(),
            'opening_float' => $float,
            'expected' => [
                'methods' => $expected['methods'],
                'total' => $expected['total'],
                'count' => $expected['count'],
                'voided' => $expected['voided'],
                'owing_total' => round(array_sum(array_column($this->owingToday($branch, $date), 'due')), 3),
            ],
            'cash_expected' => $cashExpected,
            'cash_counted' => $counted,
            'cash_diff' => round($counted - $cashExpected, 3),
            'knet_system' => $expected['knet_total'],
            'knet_slip' => $knetSlip,
            'knet_diff' => $knetSlip === null ? null : round($knetSlip - $expected['knet_total'], 3),
            'denominations' => ['notes' => (object) $denoms, 'coins' => $coins],
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'closed_at' => now(),
        ];

        $reopen = (bool) ($data['reopen'] ?? false);
        if ($reopen && ! $this->isAdminUser()) {
            return response()->json(['ok' => false, 'error' => 'Only an admin can reopen a closed day.'], 403);
        }

        try {
            $close = DB::transaction(function () use ($branch, $date, $payload, $reopen) {
                $existing = CashClose::query()
                    ->where('branch_id', $branch->id)
                    ->whereDate('business_date', $date->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($existing && ! $reopen) {
                    return null;
                }

                if ($existing) {
                    $existing->fill($payload)->save();

                    return $existing;
                }

                return CashClose::create($payload);
            });
        } catch (QueryException $e) {
            // Unique (branch_id, business_date) — a concurrent close won the race.
            $close = null;
        }

        if (! $close) {
            return response()->json([
                'ok' => false,
                'error' => 'The day is already closed for this branch.',
            ], 422);
        }

        return response()->json(['ok' => true, 'close' => $this->present($close->fresh())]);
    }

    /* ── helpers ─────────────────────────────────────────────────────── */

    protected function mayClose(): bool
    {
        return $this->isAdminUser() || $this->isReceptionUser();
    }

    protected function businessDate(): Carbon
    {
        return Carbon::today(config('app.timezone'));
    }

    protected function resolveBranch(Request $request): ?Branch
    {
        $ids = $this->accessibleBranchIds(); // null = global admin (all)
        $requested = (int) $request->input('branch_id', 0);

        $q = Branch::query()->withoutGlobalScopes();
        if ($requested > 0 && ($ids === null || in_array($requested, $ids, true))) {
            $b = (clone $q)->find($requested);
            if ($b) {
                return $b;
            }
        }

        if ($ids !== null) {
            return empty($ids) ? null : (clone $q)->whereIn('id', $ids)->orderByDesc('is_hub')->orderBy('id')->first();
        }

        return $q->orderByDesc('is_hub')->orderBy('id')->first();
    }

    protected function branchName(Branch $b): string
    {
        return method_exists($b, 'getTranslation')
            ? (string) $b->getTranslation('name', app()->getLocale(), true)
            : (string) $b->name;
    }

    /**
     * Today's payments for the branch, paid grouped by method (in the order
     * the branch's payment methods are configured) plus voids separately.
     * A payment counts on the day it was paid (paid_at; created_at if unset).
     */
    protected function computeExpected(Branch $branch, Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $rows = VisitPayment::query()
            ->join('visits', 'visits.id', '=', 'visit_payments.visit_id')
            ->where('visits.branch_id', $branch->id)
            ->whereIn('visit_payments.status', ['paid', 'void'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('visit_payments.paid_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('visit_payments.paid_at')
                            ->whereBetween('visit_payments.created_at', [$start, $end]);
                    });
            })
            ->groupBy('visit_payments.method', 'visit_payments.status')
            ->selectRaw('visit_payments.method as method, visit_payments.status as status, COUNT(*) as cnt, SUM(visit_payments.amount) as total')
            ->get();

        $methods = app(ClinicPaymentMethodResolver::class)
            ->forBranchOrDefault((int) $branch->id, $branch->partner_id ? (int) $branch->partner_id : null);

        $out = [];
        foreach ($methods as $m) {
            $out[$m['key']] = ['method' => $m['key'], 'label' => $m['label'], 'count' => 0, 'total' => 0.0];
        }

        $voided = ['count' => 0, 'total' => 0.0];
        foreach ($rows as $r) {
            if ($r->status === 'void') {
                $voided['count'] += (int) $r->cnt;
                $voided['total'] += (float) $r->total;

                continue;
            }
            $key = (string) ($r->method ?: 'other');
            // A method no longer configured still has to be counted.
            $out[$key] ??= ['method' => $key, 'label' => ucfirst($key), 'count' => 0, 'total' => 0.0];
            $out[$key]['count'] += (int) $r->cnt;
            $out[$key]['total'] += (float) $r->total;
        }

        $list = array_values(array_map(fn ($m) => [...$m, 'total' => round($m['total'], 3)], $out));
        $voided['total'] = round($voided['total'], 3);

        return [
            'methods' => $list,
            'total' => round(array_sum(array_column($list, 'total')), 3),
            'count' => array_sum(array_column($list, 'count')),
            'cash_total' => round((float) ($out['cash']['total'] ?? 0), 3),
            'knet_total' => round((float) ($out['knet']['total'] ?? 0), 3),
            'voided' => $voided,
        ];
    }

    /**
     * Visits checked in today at the branch that still owe money. Balance is
     * computed the way the Waiting Patients card does it: the visit's snapshot
     * totals minus every paid payment (one grouped query, no per-row costing).
     */
    protected function owingToday(Branch $branch, Carbon $date): array
    {
        $visits = Visit::query()
            ->with('patient:id,name')
            ->where('branch_id', $branch->id)
            ->whereBetween('checked_in_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->where('status', '!=', Visit::STATUS_CANCELLED)
            ->get(['id', 'patient_id', 'status', 'fees_total', 'packages_price_total', 'items_price_total', 'discount_total']);

        if ($visits->isEmpty()) {
            return [];
        }

        $paid = VisitPayment::query()
            ->whereIn('visit_id', $visits->pluck('id'))
            ->where('status', 'paid')
            ->groupBy('visit_id')
            ->selectRaw('visit_id, SUM(amount) as paid')
            ->pluck('paid', 'visit_id');

        return $visits->map(function (Visit $v) use ($paid) {
            $billed = (float) ($v->fees_total ?? 0)
                + (float) ($v->packages_price_total ?? 0)
                + (float) ($v->items_price_total ?? 0)
                - (float) ($v->discount_total ?? 0);
            $due = round(max(0, $billed - (float) ($paid[$v->id] ?? 0)), 3);

            return [
                'id' => $v->id,
                'patient_id' => $v->patient_id,
                'name' => $v->patient?->name,
                'status' => $v->status,
                'due' => $due,
            ];
        })->filter(fn ($r) => $r['due'] > 0)->values()->all();
    }

    protected function present(CashClose $c): array
    {
        $c->loadMissing('closedBy:id,name');

        return [
            'id' => $c->id,
            'branch_id' => $c->branch_id,
            'business_date' => $c->business_date?->toDateString(),
            'opening_float' => (float) $c->opening_float,
            'expected' => $c->expected,
            'cash_expected' => (float) $c->cash_expected,
            'cash_counted' => (float) $c->cash_counted,
            'cash_diff' => (float) $c->cash_diff,
            'knet_system' => (float) $c->knet_system,
            'knet_slip' => $c->knet_slip !== null ? (float) $c->knet_slip : null,
            'knet_diff' => $c->knet_diff !== null ? (float) $c->knet_diff : null,
            'denominations' => $c->denominations,
            'note' => $c->note,
            'closed_at' => optional($c->closed_at)->toIso8601String(),
            'closed_by' => $c->closedBy ? ['id' => $c->closedBy->id, 'name' => $c->closedBy->name] : null,
        ];
    }

    /** Normalised string key for a note value: "20", "0.5", "0.25". */
    protected function r3s(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }
}
