<?php

namespace App\Filament\Widgets\Clinic;

use App\Services\Clinic\ClinicReportCache;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ClinicProfitOverview extends StatsOverviewWidget
{
    public ?array $filters = [];

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        if (! config('clinic.visit_financials_enabled', true)) {
            return [
                Stat::make('Clinic financials', 'Disabled')
                    ->description('Enable clinic.visit_financials_enabled'),
            ];
        }

        [$from, $to, $branchId, $doctorId] = $this->resolvedFilters();

        // Enforce branch scope for non-admin users, even when filter is empty.
        $branchId = $this->effectiveBranchId($branchId);

        // Optional: enforce doctor scope for doctor users, even when filter is empty.
        $doctorId = $this->effectiveDoctorId($doctorId);

        // Prevent cross-branch/user cache leakage.
        $userId = Filament::auth()->id() ?? auth()->id();
        $cacheKey = 'profit_overview:'.md5(json_encode([$from, $to, $branchId, $doctorId, $userId]));

        $data = ClinicReportCache::remember($cacheKey, 60, function () use ($from, $to, $branchId, $doctorId) {
            $visitsQ = DB::table('visits')
                ->whereNotNull('computed_at')
                ->whereDate('computed_at', '>=', $from)
                ->whereDate('computed_at', '<=', $to);

            // If branchId is -1, return empty safely (user has no branch assigned).
            if ($branchId === -1) {
                $visitsQ->whereRaw('1=0');
            } else {
                $visitsQ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            }

            // If doctorId is -1 (doctor role but no doctor row), return empty.
            if ($doctorId === -1) {
                $visitsQ->whereRaw('1=0');
            } else {
                $visitsQ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId));
            }

            $visitsAgg = $visitsQ
                ->selectRaw('
                    COALESCE(SUM(fees_total),0) as fees_total,
                    COALESCE(SUM(discount_total),0) as discount_total,
                    COALESCE(SUM(items_cost_total),0) as items_cost_total,
                    COALESCE(SUM(profit_total),0) as profit_total,
                    COUNT(*) as visits_count
                ')
                ->first();

            $doctorCut = 0.0;

            if (config('clinic.doctor_cut_enabled', true)) {
                $ledgerQ = DB::table('doctor_compensation_ledgers')
                    ->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);

                if ($branchId === -1) {
                    $ledgerQ->whereRaw('1=0');
                } else {
                    $ledgerQ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
                }

                if ($doctorId === -1) {
                    $ledgerQ->whereRaw('1=0');
                } else {
                    $ledgerQ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId));
                }

                $doctorCut = (float) $ledgerQ->sum('doctor_cut_amount');
            }

            return [$visitsAgg, $doctorCut];
        });

        /** @var object|null $visitsAgg */
        [$visitsAgg, $doctorCut] = $data;

        return [
            Stat::make('Visits', number_format((int) ($visitsAgg->visits_count ?? 0)))
                ->description('Computed visits (snapshot)'),

            Stat::make('Fees', number_format((float) ($visitsAgg->fees_total ?? 0), 3))
                ->description('Sum fees_total'),

            Stat::make('Items Cost', number_format((float) ($visitsAgg->items_cost_total ?? 0), 3))
                ->description('Sum items_cost_total'),

            Stat::make('Profit', number_format((float) ($visitsAgg->profit_total ?? 0), 3))
                ->description('Sum profit_total'),

            Stat::make('Doctor Cut', number_format((float) $doctorCut, 3))
                ->description('From compensation ledger'),
        ];
    }

    protected function resolvedFilters(): array
    {
        $from = (string) ($this->filters['from'] ?? now()->startOfMonth()->toDateString());
        $to = (string) ($this->filters['to'] ?? now()->toDateString());

        $branchId = $this->filters['branch_id'] ?? null;
        $branchId = ($branchId !== '' && $branchId !== null) ? (int) $branchId : null;

        $doctorId = $this->filters['doctor_id'] ?? null;
        $doctorId = ($doctorId !== '' && $doctorId !== null) ? (int) $doctorId : null;

        return [$from, $to, $branchId, $doctorId];
    }

    /**
     * For non-admin users, always scope to their assigned branch (branch_user).
     * Returns:
     *  - null  => no branch constraint (admin/super_admin or no user)
     *  - int   => effective branch_id
     *  - -1    => user has no branch assigned; forces empty results safely
     */
    protected function effectiveBranchId(?int $requestedBranchId): ?int
    {
        $user = Filament::auth()->user() ?? auth()->user();

        if (! $user) {
            return $requestedBranchId;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole(['admin', 'super_admin'])) {
            return $requestedBranchId;
        }

        $branchId = (int) DB::table('branch_user')
            ->where('user_id', $user->id)
            ->value('branch_id');

        return $branchId > 0 ? $branchId : -1;
    }

    /**
     * If the current user is a doctor and no doctor filter is selected,
     * force scope to their doctor_id.
     */
    protected function effectiveDoctorId(?int $requestedDoctorId): ?int
    {
        if ($requestedDoctorId) {
            return $requestedDoctorId;
        }

        $user = Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return null;
        }

        if (! (method_exists($user, 'hasRole') && $user->hasRole('doctor'))) {
            return null;
        }

        $doctorId = (int) DB::table('doctors')
            ->where('user_id', $user->id)
            ->value('id');

        return $doctorId > 0 ? $doctorId : -1;
    }
}
