<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\ClinicPackage;

class ClinicPackageImport extends AbstractImport
{
    public function slug(): string { return 'clinic-packages'; }
    public function title(): string { return 'Clinic Packages'; }
    public function model(): string { return ClinicPackage::class; }

    public function permission(): string { return 'create_clinic_packages'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('name_en', 'Name (English)')->required()->rules(['string', 'max:191'])->example('Annual Check-up'),
            ImportColumn::make('name_ar', 'Name (Arabic)')->required()->rules(['string', 'max:191']),
            ImportColumn::make('branch', 'Branch')->note('Branch name; blank = all branches'),
            ImportColumn::make('default_price', 'Default price')->required()->rules(['numeric', 'min:0'])->example('25.000'),
            ImportColumn::make('is_active', 'Active')->allowed(['yes', 'no']),
        ];
    }

    public function instructions(): array
    {
        return ['This template creates the package header (name + price). Add the items inside each package from the Packages screen after importing.'];
    }

    public function exampleRows(): array
    {
        return [['name_en' => 'Annual Check-up', 'name_ar' => 'الفحص السنوي', 'default_price' => '25.000', 'is_active' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        return [
            'name' => ['en' => $row['name_en'], 'ar' => $row['name_ar']],
            'branch_id' => $this->resolveBranchId($row['branch'] ?? null),
            'default_price' => (float) $row['default_price'],
            'is_active' => $this->bool($row['is_active'] ?? null, true),
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return ['name->en' => $attrs['name']['en'], 'branch_id' => $attrs['branch_id']];
    }
}
