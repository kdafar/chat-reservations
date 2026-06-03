<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\Accounting\Expense;

class ExpenseImport extends AbstractImport
{
    public function slug(): string { return 'expenses'; }
    public function title(): string { return 'Expenses'; }
    public function model(): string { return Expense::class; }

    public function permission(): string { return 'create_accounting_expenses'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('expense_date', 'Date')->required()->note('YYYY-MM-DD')->example('2026-05-01'),
            ImportColumn::make('amount', 'Amount')->required()->rules(['numeric', 'min:0.001'])->example('45.000'),
            ImportColumn::make('account', 'Expense account code')->required()->note('Chart-of-accounts code')->example('5100'),
            ImportColumn::make('vendor', 'Vendor')->note('Existing vendor name or code'),
            ImportColumn::make('branch', 'Branch')->note('Branch name; blank = all branches'),
            ImportColumn::make('payment_account', 'Paid-from account code')->note('Chart-of-accounts code'),
            ImportColumn::make('reference_no', 'Reference #')->rules(['string', 'max:191'])->note('Unique key when present'),
            ImportColumn::make('description', 'Description')->rules(['string', 'max:500']),
        ];
    }

    public function instructions(): array
    {
        return ['Imported expenses are created as DRAFT — nothing posts to the ledger until you review and post them in the app.'];
    }

    public function exampleRows(): array
    {
        return [['expense_date' => '2026-05-01', 'amount' => '45.000', 'account' => '5100', 'vendor' => 'Gulf Medical Supplies', 'reference_no' => 'INV-7781', 'description' => 'Gloves']];
    }

    public function mapRow(array $row): array
    {
        return [
            'expense_date' => $this->date($row['expense_date'] ?? null),
            'amount' => (float) $row['amount'],
            'account_id' => $this->resolveAccountId($row['account'] ?? null),
            'vendor_id' => $this->resolveVendorId($row['vendor'] ?? null, false),
            'branch_id' => $this->resolveBranchId($row['branch'] ?? null),
            'payment_account_id' => $this->resolveAccountId($row['payment_account'] ?? null, false),
            'reference_no' => $row['reference_no'] ?: null,
            'description' => $row['description'] ?: null,
            'status' => Expense::STATUS_DRAFT,
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        // Prefer the reference number as the key. Without one, fall back to a
        // content signature (date + amount + account + vendor) so re-importing
        // the same draft sheet updates rather than piling up duplicates.
        if (! empty($attrs['reference_no'])) {
            return ['reference_no' => $attrs['reference_no'], 'status' => Expense::STATUS_DRAFT];
        }

        return [
            'status' => Expense::STATUS_DRAFT,
            'expense_date' => $attrs['expense_date'],
            'amount' => $attrs['amount'],
            'account_id' => $attrs['account_id'],
            'vendor_id' => $attrs['vendor_id'],
        ];
    }
}
