<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\ClinicItem;

class ClinicItemImport extends AbstractImport
{
    public function slug(): string { return 'clinic-items'; }
    public function title(): string { return 'Clinic Items'; }
    public function model(): string { return ClinicItem::class; }

    public function permission(): string { return 'create_clinic_items'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('type', 'Type')->required()->allowed(['consumable', 'service', 'product']),
            ImportColumn::make('name_en', 'Name (English)')->required()->rules(['string', 'max:191'])->example('Paracetamol 500mg'),
            ImportColumn::make('name_ar', 'Name (Arabic)')->required()->rules(['string', 'max:191']),
            ImportColumn::make('branch', 'Branch')->note('Branch name; blank = all branches'),
            ImportColumn::make('default_cost', 'Default cost')->required()->rules(['numeric', 'min:0'])->example('0.250'),
            ImportColumn::make('default_price', 'Default price')->required()->rules(['numeric', 'min:0'])->example('0.500'),
            ImportColumn::make('is_billable', 'Billable')->allowed(['yes', 'no']),
            ImportColumn::make('is_stockable', 'Stockable')->allowed(['yes', 'no'])->note('Consumables only'),
            ImportColumn::make('stock_unit', 'Stock unit')->rules(['string', 'max:50'])->note('Required if stockable')->example('box'),
            ImportColumn::make('usage_unit', 'Usage unit')->rules(['string', 'max:50'])->note('Required if stockable')->example('tablet'),
            ImportColumn::make('conversion_factor', 'Conversion factor')->rules(['numeric', 'min:0.0001'])->note('Usage units per stock unit')->example('20'),
            ImportColumn::make('is_active', 'Active')->allowed(['yes', 'no']),
        ];
    }

    public function exampleRows(): array
    {
        return [['type' => 'consumable', 'name_en' => 'Paracetamol 500mg', 'name_ar' => 'باراسيتامول 500', 'default_cost' => '0.250', 'default_price' => '0.500', 'is_stockable' => 'yes', 'stock_unit' => 'box', 'usage_unit' => 'tablet', 'conversion_factor' => '20', 'is_active' => 'yes']];
    }

    public function mapRow(array $row): array
    {
        $isService = $row['type'] === 'service';
        $stockable = ! $isService && $this->bool($row['is_stockable'] ?? null, false);

        if ($stockable && (! $row['stock_unit'] || ! $row['usage_unit'] || ! $row['conversion_factor'])) {
            $this->fail('Stockable items need stock unit, usage unit and conversion factor.');
        }

        return [
            'type' => $row['type'],
            'name' => ['en' => $row['name_en'], 'ar' => $row['name_ar']],
            'branch_id' => $this->resolveBranchId($row['branch'] ?? null),
            'default_cost' => (float) $row['default_cost'],
            'default_price' => (float) $row['default_price'],
            'is_billable' => $isService ? true : $this->bool($row['is_billable'] ?? null, true),
            'is_stockable' => $stockable,
            'stock_unit' => $stockable ? $row['stock_unit'] : null,
            'usage_unit' => $stockable ? $row['usage_unit'] : null,
            'conversion_factor' => $stockable ? (float) $row['conversion_factor'] : null,
            'consume_step' => 1,
            'is_active' => $this->bool($row['is_active'] ?? null, true),
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return ['name->en' => $attrs['name']['en'], 'branch_id' => $attrs['branch_id']];
    }
}
