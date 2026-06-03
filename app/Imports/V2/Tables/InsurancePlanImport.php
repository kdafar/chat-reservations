<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\Insurance\InsurancePlan;

class InsurancePlanImport extends AbstractImport
{
    public function slug(): string { return 'insurance-plans'; }
    public function title(): string { return 'Insurance Plans'; }
    public function model(): string { return InsurancePlan::class; }

    public function permission(): string { return 'create_insurance_plans'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('insurer', 'Insurer code')->required()->note('Must match an existing insurer code')->example('GIG'),
            ImportColumn::make('code', 'Plan code')->required()->rules(['string', 'max:32'])->note('Unique key')->example('GIG-GOLD'),
            ImportColumn::make('name', 'Plan name')->required()->rules(['string', 'max:191'])->example('Gold'),
            ImportColumn::make('name_ar', 'Plan name (Arabic)')->rules(['string', 'max:191']),
            ImportColumn::make('tier', 'Tier')->allowed(['platinum', 'gold', 'silver', 'bronze']),
            ImportColumn::make('effective_from', 'Effective from')->note('YYYY-MM-DD'),
            ImportColumn::make('effective_until', 'Effective until')->note('YYYY-MM-DD'),
            ImportColumn::make('is_active', 'Active')->allowed(['yes', 'no']),
            ImportColumn::make('notes', 'Notes')->rules(['string', 'max:1000']),
        ];
    }

    public function exampleRows(): array
    {
        return [['insurer' => 'GIG', 'code' => 'GIG-GOLD', 'name' => 'Gold', 'tier' => 'gold', 'is_active' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        return [
            'insurer_id' => $this->resolveInsurerId($row['insurer'] ?? null),
            'code' => $row['code'],
            'name' => $row['name'],
            'name_ar' => $row['name_ar'] ?: null,
            'tier' => $row['tier'] ?: null,
            'effective_from' => $this->date($row['effective_from'] ?? null),
            'effective_until' => $this->date($row['effective_until'] ?? null),
            'is_active' => $this->bool($row['is_active'] ?? null, true),
            'notes' => $row['notes'] ?: null,
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return ['code' => $attrs['code']];
    }
}
