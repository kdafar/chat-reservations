<?php

namespace App\Filament\Widgets\Clinic;

use App\Models\VisitItem;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClinicTopItems extends TableWidget
{
    public ?array $filters = [];

    protected static ?string $heading = 'Top Items (Today)';

    protected static ?int $sort = 40;

    protected static ?string $pollingInterval = null;

    public function getTableRecordKey($record): string
    {
        return (string) ($record->clinic_item_id ?? '0');
    }

    protected function getTableQuery(): Builder
    {
        if (! config('clinic.visit_items_enabled', true)) {
            return VisitItem::query()->whereRaw('1=0');
        }

        // Force today (app timezone)
        $today = now()
            ->timezone(config('app.timezone', 'Asia/Kuwait'))
            ->toDateString();

        // Keep filters only for branch/doctor scope
        [, , $branchId, $doctorId] = $this->resolvedFilters();

        $branchId = $this->effectiveBranchId($branchId);
        $doctorId = $this->effectiveDoctorId($doctorId);

        $q = VisitItem::query()
            ->join('visits', 'visits.id', '=', 'visit_items.visit_id')
            ->join('clinic_items', 'clinic_items.id', '=', 'visit_items.clinic_item_id')
            ->whereNotNull('visits.computed_at')
            ->whereDate('visits.computed_at', '=', $today);

        if ($branchId === -1) {
            $q->whereRaw('1=0');
        } else {
            $q->when($branchId, fn ($qq) => $qq->where('visits.branch_id', $branchId));
        }

        if ($doctorId === -1) {
            $q->whereRaw('1=0');
        } else {
            $q->when($doctorId, fn ($qq) => $qq->where('visits.doctor_id', $doctorId));
        }

        return $q->groupBy('visit_items.clinic_item_id', 'clinic_items.name', 'clinic_items.type')
            ->selectRaw('
                visit_items.clinic_item_id,
                clinic_items.name as item_name,
                clinic_items.type as item_type,
                COALESCE(SUM(visit_items.qty),0) as qty_total,
                COALESCE(SUM(visit_items.qty * visit_items.unit_price_snapshot),0) as revenue_total,
                COALESCE(SUM(visit_items.qty * visit_items.unit_cost_snapshot),0) as cost_total,
                COALESCE(SUM(visit_items.qty * (visit_items.unit_price_snapshot - visit_items.unit_cost_snapshot)),0) as profit_total
            ')
            ->orderByDesc('profit_total');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('item_name')
                ->label('Item')
                ->limit(40),

            Tables\Columns\TextColumn::make('item_type')
                ->label('Type')
                ->badge(),

            Tables\Columns\TextColumn::make('qty_total')
                ->label('Qty')
                ->numeric(decimalPlaces: 3),

            Tables\Columns\TextColumn::make('revenue_total')
                ->label('Revenue')
                ->numeric(decimalPlaces: 3),

            Tables\Columns\TextColumn::make('cost_total')
                ->label('Cost')
                ->numeric(decimalPlaces: 3),

            Tables\Columns\TextColumn::make('profit_total')
                ->label('Profit')
                ->numeric(decimalPlaces: 3)
                ->sortable(),
        ];
    }

    protected function getTableDefaultPaginationPageOption(): int
    {
        return 10;
    }

    protected function resolvedFilters(): array
    {
        // Keep parsing so branch/doctor filters still work,
        // but from/to are not used by this widget anymore.
        $from = (string) ($this->filters['from'] ?? now()->startOfMonth()->toDateString());
        $to = (string) ($this->filters['to'] ?? now()->toDateString());

        $branchId = $this->filters['branch_id'] ?? null;
        $branchId = ($branchId !== '' && $branchId !== null) ? (int) $branchId : null;

        $doctorId = $this->filters['doctor_id'] ?? null;
        $doctorId = ($doctorId !== '' && $doctorId !== null) ? (int) $doctorId : null;

        return [$from, $to, $branchId, $doctorId];
    }

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
