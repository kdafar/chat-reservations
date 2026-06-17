<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\StaffLeaveEntitlement;

/**
 * Bulk-seed annual leave entitlements per staff member per year — entitled days
 * plus any carried-over balance. "Used" days are never imported: they are
 * always computed live from approved leave by LeaveBalanceService. One row per
 * staff member per year, so re-importing updates that year's entitlement.
 */
class LeaveEntitlementImport extends AbstractImport
{
    public function slug(): string { return 'leave-entitlements'; }
    public function title(): string { return 'Leave Entitlements'; }
    public function model(): string { return StaffLeaveEntitlement::class; }

    /** Same write gate the leave-balances controller uses. */
    public function permission(): string { return 'update_staff_leave_entitlements'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('staff', 'Staff')->required()->note('Staff email (preferred) or exact name')->example('sara@clinic.com'),
            ImportColumn::make('year', 'Year')->required()->rules(['integer', 'min:2020', 'max:2100'])->example('2026'),
            ImportColumn::make('entitled_days', 'Entitled days')->required()->rules(['numeric', 'min:0', 'max:90'])->example('30'),
            ImportColumn::make('carried_over_days', 'Carried-over days')->rules(['numeric', 'min:0', 'max:90'])->note('Balance brought forward from last year — blank = 0'),
            ImportColumn::make('notes', 'Notes')->rules(['string', 'max:500']),
        ];
    }

    public function instructions(): array
    {
        return [
            'Annual leave only. One row per staff member per year — re-importing updates that year.',
            '"Used" days are never imported; they are computed live from approved leave requests.',
        ];
    }

    public function exampleRows(): array
    {
        return [['staff' => 'sara@clinic.com', 'year' => '2026', 'entitled_days' => '30', 'carried_over_days' => '5']];
    }

    public function mapRow(array $row): array
    {
        return [
            'user_id' => $this->resolveUserId($row['staff'] ?? null),
            'year' => (int) $row['year'],
            'leave_type' => 'annual',
            'entitled_days' => round((float) $row['entitled_days'], 2),
            'carried_over_days' => round((float) ($row['carried_over_days'] ?? 0), 2),
            'notes' => $row['notes'] ?: null,
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return [
            'user_id' => $attrs['user_id'],
            'year' => $attrs['year'],
            'leave_type' => 'annual',
        ];
    }
}
