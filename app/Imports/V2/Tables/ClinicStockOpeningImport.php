<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\ClinicItemStock;

class ClinicStockOpeningImport extends AbstractImport
{
    public function slug(): string { return 'clinic-stock'; }
    public function title(): string { return 'Opening Stock Balances'; }
    public function model(): string { return ClinicItemStock::class; }

    public function permission(): string { return 'create_clinic_item_stocks'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('item', 'Item (English name)')->required()->note('Must match an existing clinic item')->example('Paracetamol 500mg'),
            ImportColumn::make('branch', 'Branch')->required()->note('Branch name')->example('Main Clinic'),
            ImportColumn::make('qty_on_hand', 'Quantity on hand')->required()->rules(['numeric', 'min:0'])->note('In base (usage) units')->example('120'),
            ImportColumn::make('min_threshold', 'Min threshold')->rules(['numeric', 'min:0'])->note('Low-stock alert level'),
            ImportColumn::make('bin_location', 'Bin location')->rules(['string', 'max:64']),
        ];
    }

    public function instructions(): array
    {
        return ['Sets opening balances directly (a starting snapshot) — it does not create stock movements or accounting entries. Use the Receive Stock action for ongoing restocks.'];
    }

    public function exampleRows(): array
    {
        return [['item' => 'Paracetamol 500mg', 'branch' => 'Main Clinic', 'qty_on_hand' => '120', 'min_threshold' => '20', 'bin_location' => 'A-12']];
    }

    public function mapRow(array $row): array
    {
        $branchId = $this->resolveBranchId($row['branch'] ?? null);
        if (! $branchId) {
            $this->fail('Branch is required.');
        }

        return [
            'clinic_item_id' => $this->resolveClinicItemId($row['item'] ?? null, $branchId),
            'branch_id' => $branchId,
            'qty_on_hand_base' => (float) $row['qty_on_hand'],
            'min_qty_threshold_base' => $row['min_threshold'] !== null ? (float) $row['min_threshold'] : null,
            'bin_location' => $row['bin_location'] ?: null,
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        return ['clinic_item_id' => $attrs['clinic_item_id'], 'branch_id' => $attrs['branch_id']];
    }
}
