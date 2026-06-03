<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\Insurance\PatientInsurancePolicy;

class PatientPolicyImport extends AbstractImport
{
    public function slug(): string { return 'patient-policies'; }
    public function title(): string { return 'Patient Insurance Policies'; }
    public function model(): string { return PatientInsurancePolicy::class; }

    public function permission(): string { return 'create_patient_insurance_policies'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('patient_civil_id', 'Patient civil ID')->required()->note('Must match an existing patient civil ID')->example('290010112345'),
            ImportColumn::make('insurer', 'Insurer code')->required()->note('Existing insurer code')->example('GIG'),
            ImportColumn::make('plan', 'Plan code')->note('Existing plan code (optional)')->example('GIG-GOLD'),
            ImportColumn::make('policy_number', 'Policy number')->required()->rules(['string', 'max:64'])->note('Unique key'),
            ImportColumn::make('member_id', 'Member ID')->rules(['string', 'max:64']),
            ImportColumn::make('card_number', 'Card number')->rules(['string', 'max:64']),
            ImportColumn::make('holder_relationship', 'Holder relationship')->required()->allowed(['self', 'spouse', 'child', 'parent', 'other']),
            ImportColumn::make('holder_name', 'Holder name')->rules(['string', 'max:191']),
            ImportColumn::make('status', 'Status')->required()->allowed(['active', 'expired', 'suspended', 'cancelled']),
            ImportColumn::make('is_primary', 'Primary')->allowed(['yes', 'no']),
            ImportColumn::make('priority', 'Priority')->rules(['integer', 'min:1']),
            ImportColumn::make('effective_from', 'Effective from')->note('YYYY-MM-DD'),
            ImportColumn::make('effective_until', 'Effective until')->note('YYYY-MM-DD'),
            ImportColumn::make('notes', 'Notes')->rules(['string', 'max:1000']),
        ];
    }

    public function exampleRows(): array
    {
        return [['patient_civil_id' => '290010112345', 'insurer' => 'GIG', 'plan' => 'GIG-GOLD', 'policy_number' => 'POL-001', 'holder_relationship' => 'self', 'status' => 'active', 'is_primary' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        return [
            'patient_id' => $this->resolvePatientId($row['patient_civil_id'] ?? null),
            'insurer_id' => $this->resolveInsurerId($row['insurer'] ?? null),
            'plan_id' => $this->resolvePlanId($row['plan'] ?? null, false),
            'policy_number' => $row['policy_number'],
            'member_id' => $row['member_id'] ?: null,
            'card_number' => $row['card_number'] ?: null,
            'holder_relationship' => $row['holder_relationship'],
            'holder_name' => $row['holder_name'] ?: null,
            'status' => $row['status'],
            'is_primary' => $this->bool($row['is_primary'] ?? null, false),
            'priority' => $row['priority'] !== null ? (int) $row['priority'] : null,
            'effective_from' => $this->date($row['effective_from'] ?? null),
            'effective_until' => $this->date($row['effective_until'] ?? null),
            'notes' => $row['notes'] ?: null,
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return ['policy_number' => $attrs['policy_number']];
    }
}
