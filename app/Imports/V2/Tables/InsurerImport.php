<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\Insurance\Insurer;

class InsurerImport extends AbstractImport
{
    public function slug(): string { return 'insurers'; }
    public function title(): string { return 'Insurers'; }
    public function model(): string { return Insurer::class; }

    public function permission(): string { return 'create_insurers'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('code', 'Code')->required()->rules(['string', 'max:32'])->note('Unique key')->example('GIG'),
            ImportColumn::make('name', 'Name')->required()->rules(['string', 'max:191'])->example('Gulf Insurance Group'),
            ImportColumn::make('name_ar', 'Name (Arabic)')->rules(['string', 'max:191']),
            ImportColumn::make('tax_id', 'Tax ID')->rules(['string', 'max:64']),
            ImportColumn::make('payment_terms_days', 'Payment terms (days)')->rules(['integer', 'min:0'])->example('30'),
            ImportColumn::make('is_active', 'Active')->allowed(['yes', 'no']),
        ];
    }

    public function exampleRows(): array
    {
        return [['code' => 'GIG', 'name' => 'Gulf Insurance Group', 'payment_terms_days' => '30', 'is_active' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        return [
            'code' => $row['code'],
            'name' => $row['name'],
            'name_ar' => $row['name_ar'] ?: null,
            'tax_id' => $row['tax_id'] ?: null,
            'payment_terms_days' => $row['payment_terms_days'] !== null ? (int) $row['payment_terms_days'] : null,
            'is_active' => $this->bool($row['is_active'] ?? null, true),
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return ['code' => $attrs['code']];
    }
}
