<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\Lab\LabTest;

class LabTestImport extends AbstractImport
{
    public function slug(): string { return 'lab-tests'; }
    public function title(): string { return 'Lab Tests'; }
    public function model(): string { return LabTest::class; }

    public function permission(): string { return 'create_lab_tests'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('code', 'Code')->required()->rules(['string', 'max:32'])->note('Unique key per branch')->example('CBC'),
            ImportColumn::make('name', 'Name')->required()->rules(['string', 'max:191'])->example('Complete Blood Count'),
            ImportColumn::make('branch', 'Branch')->note('Branch name; blank = all branches'),
            ImportColumn::make('specimen_type', 'Specimen type')->rules(['string', 'max:64'])->example('Blood'),
            ImportColumn::make('unit', 'Unit')->rules(['string', 'max:32']),
            ImportColumn::make('reference_range', 'Reference range')->rules(['string', 'max:191']),
            ImportColumn::make('default_price', 'Default price')->required()->rules(['numeric', 'min:0'])->example('5.000'),
            ImportColumn::make('is_active', 'Active')->allowed(['yes', 'no']),
        ];
    }

    public function exampleRows(): array
    {
        return [['code' => 'CBC', 'name' => 'Complete Blood Count', 'specimen_type' => 'Blood', 'default_price' => '5.000', 'is_active' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        return [
            'code' => $row['code'],
            'name' => $row['name'],
            'branch_id' => $this->resolveBranchId($row['branch'] ?? null),
            'specimen_type' => $row['specimen_type'] ?: null,
            'unit' => $row['unit'] ?: null,
            'reference_range' => $row['reference_range'] ?: null,
            'default_price' => (float) $row['default_price'],
            'is_active' => $this->bool($row['is_active'] ?? null, true),
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return ['code' => $attrs['code'], 'branch_id' => $attrs['branch_id']];
    }
}
