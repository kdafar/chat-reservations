<?php

namespace App\Filament\Widgets\Clinic;

use App\Models\DoctorCompensationLedger;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClinicTopDoctors extends TableWidget
{
    public ?array $filters = [];

    protected static ?string $heading = 'Top Doctors (Today, By Cut)';

    protected static ?int $sort = 45;

    protected static ?string $pollingInterval = null;

    public function getTableRecordKey($record): string
    {
        return (string) ($record->doctor_id ?? '0');
    }

    protected function getTableQuery(): Builder
    {
        // Force today only (app timezone)
        $today = now()
            ->timezone(config('app.timezone', 'Asia/Kuwait'))
            ->toDateString();

        // Keep filters only for branch/doctor scope
        [, , $branchId, $doctorId] = $this->resolvedFilters();

        $branchId = $this->effectiveBranchId($branchId);
        $doctorId = $this->effectiveDoctorId($doctorId);

        $q = DoctorCompensationLedger::query()
            // Correct join chain: ledger.doctor_id -> doctors.id -> users.id
            ->join('doctors', 'doctors.id', '=', 'doctor_compensation_ledgers.doctor_id')
            ->join('users', 'users.id', '=', 'doctors.user_id')
            ->whereDate('doctor_compensation_ledgers.created_at', '=', $today);

        // If branchId is -1, return empty safely (user has no branch assigned).
        if ($branchId === -1) {
            $q->whereRaw('1=0');
        } else {
            $q->when($branchId, fn ($qq) => $qq->where('doctor_compensation_ledgers.branch_id', $branchId));
        }

        // If doctorId is -1 (doctor role but no doctor row), return empty.
        if ($doctorId === -1) {
            $q->whereRaw('1=0');
        } else {
            $q->when($doctorId, fn ($qq) => $qq->where('doctor_compensation_ledgers.doctor_id', $doctorId));
        }

        return $q->groupBy('doctor_compensation_ledgers.doctor_id', 'users.name')
            ->selectRaw('
                doctor_compensation_ledgers.doctor_id,
                users.name as doctor_name,
                COALESCE(SUM(doctor_compensation_ledgers.doctor_cut_amount),0) as cut_total,
                COUNT(*) as visits_count
            ')
            ->orderByDesc('cut_total');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('doctor_name')->label('Doctor')->limit(40),
            Tables\Columns\TextColumn::make('visits_count')->label('Visits')->numeric(),
            Tables\Columns\TextColumn::make('cut_total')->label('Cut')->numeric(decimalPlaces: 3),
        ];
    }

    protected function getTableDefaultPaginationPageOption(): int
    {
        return 10;
    }

    protected function resolvedFilters(): array
    {
        // Keep parsing for branch/doctor UI filters, but from/to are not used here anymore.
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
