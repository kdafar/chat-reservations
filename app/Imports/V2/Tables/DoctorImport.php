<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\Doctor;

class DoctorImport extends AbstractImport
{
    public function slug(): string { return 'doctors'; }
    public function title(): string { return 'Doctors'; }
    public function model(): string { return Doctor::class; }

    public function permission(): string { return 'create_doctors'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('name', 'Name')->required()->rules(['string', 'max:255'])->example('Dr. Sara Khalid'),
            ImportColumn::make('specialty', 'Specialty')->rules(['string', 'max:100'])->example('Cardiology'),
            ImportColumn::make('phone', 'Phone')->rules(['string', 'max:32']),
            ImportColumn::make('email', 'Email')->rules(['email', 'max:191']),
            ImportColumn::make('license_number', 'License #')->rules(['string', 'max:64'])->note('Unique key when present'),
            ImportColumn::make('consultation_fee', 'Consultation fee')->rules(['numeric', 'min:0'])->example('15'),
            ImportColumn::make('branch', 'Branch')->note('Branch name; leave blank for all branches'),
            ImportColumn::make('bio', 'Bio')->rules(['string', 'max:2000']),
            ImportColumn::make('is_active', 'Active')->allowed(['yes', 'no'])->example('yes'),
        ];
    }

    public function exampleRows(): array
    {
        return [['name' => 'Dr. Sara Khalid', 'specialty' => 'Cardiology', 'license_number' => 'KW-12345', 'consultation_fee' => '15', 'is_active' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        return [
            'name' => $row['name'],
            'specialty' => $row['specialty'] ?: null,
            'phone' => $row['phone'] ?: null,
            'email' => $row['email'] ?: null,
            'license_number' => $row['license_number'] ?: null,
            'consultation_fee' => $row['consultation_fee'] !== null ? (float) $row['consultation_fee'] : null,
            'branch_id' => $this->resolveBranchId($row['branch'] ?? null),
            'bio' => $row['bio'] ?: null,
            'is_active' => $this->bool($row['is_active'] ?? null, true),
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        if (! empty($attrs['license_number'])) {
            return ['license_number' => $attrs['license_number']];
        }

        return ['name' => $attrs['name'], 'branch_id' => $attrs['branch_id']];
    }
}
