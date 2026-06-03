<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\DoctorCompensationProfile;

/**
 * Import doctor compensation profiles (one per doctor — re-importing updates the
 * doctor's profile). Doctors are referenced by license number or exact name.
 */
class DoctorCompProfileImport extends AbstractImport
{
    public function slug(): string { return 'doctor-comp-profiles'; }
    public function title(): string { return 'Doctor Compensation Profiles'; }
    public function model(): string { return DoctorCompensationProfile::class; }

    public function permission(): string { return 'create_doctor_compensation_profiles'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('doctor', 'Doctor')->required()->note('Doctor license # or exact name')->example('KW-12345'),
            ImportColumn::make('type', 'Type')->required()->allowed(['salary', 'percentage']),
            ImportColumn::make('basis', 'Basis')->required()->allowed(['fees_only', 'net_profit']),
            ImportColumn::make('percentage_rate', 'Percentage rate')->rules(['numeric', 'min:0', 'max:100'])->note('Required when type = percentage')->example('40'),
            ImportColumn::make('is_active', 'Active')->allowed(['yes', 'no']),
        ];
    }

    public function instructions(): array
    {
        return ['Each doctor has a single compensation profile — importing again updates it.'];
    }

    public function exampleRows(): array
    {
        return [['doctor' => 'KW-12345', 'type' => 'percentage', 'basis' => 'net_profit', 'percentage_rate' => '40', 'is_active' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        $type = $row['type'];
        if ($type === 'percentage' && ($row['percentage_rate'] === null || $row['percentage_rate'] === '')) {
            $this->fail('Percentage rate is required when type is "percentage".');
        }

        return [
            'doctor_id' => $this->resolveDoctorId($row['doctor'] ?? null),
            'type' => $type,
            'basis' => $row['basis'],
            'percentage_rate' => $type === 'percentage' ? (float) $row['percentage_rate'] : null,
            'is_active' => $this->bool($row['is_active'] ?? null, true),
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return ['doctor_id' => $attrs['doctor_id']];
    }
}
