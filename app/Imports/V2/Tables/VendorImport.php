<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\Accounting\Vendor;

class VendorImport extends AbstractImport
{
    public function slug(): string { return 'vendors'; }
    public function title(): string { return 'Vendors'; }
    public function model(): string { return Vendor::class; }

    public function permission(): string { return 'create_accounting_vendors'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('name', 'Name')->required()->rules(['string', 'max:191'])->example('Gulf Medical Supplies'),
            ImportColumn::make('code', 'Code')->rules(['string', 'max:32'])->note('Unique key when present'),
            ImportColumn::make('contact_name', 'Contact name')->rules(['string', 'max:191']),
            ImportColumn::make('phone', 'Phone')->rules(['string', 'max:64']),
            ImportColumn::make('email', 'Email')->rules(['email', 'max:191']),
            ImportColumn::make('tax_number', 'Tax number')->rules(['string', 'max:64']),
            ImportColumn::make('address', 'Address')->rules(['string', 'max:1000']),
            ImportColumn::make('notes', 'Notes')->rules(['string', 'max:2000']),
            ImportColumn::make('is_active', 'Active')->allowed(['yes', 'no']),
        ];
    }

    public function exampleRows(): array
    {
        return [['name' => 'Gulf Medical Supplies', 'code' => 'GMS', 'phone' => '+96522334455', 'is_active' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        return [
            'name' => $row['name'],
            'code' => $row['code'] ?: null,
            'contact_name' => $row['contact_name'] ?: null,
            'phone' => $row['phone'] ?: null,
            'email' => $row['email'] ?: null,
            'tax_number' => $row['tax_number'] ?: null,
            'address' => $row['address'] ?: null,
            'notes' => $row['notes'] ?: null,
            'is_active' => $this->bool($row['is_active'] ?? null, true),
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        if (! empty($attrs['code'])) {
            return ['code' => $attrs['code']];
        }

        return ['name' => $attrs['name']];
    }
}
