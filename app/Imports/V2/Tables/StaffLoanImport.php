<?php

namespace App\Imports\V2\Tables;

use App\Imports\V2\AbstractImport;
use App\Imports\V2\ImportColumn;
use App\Models\StaffLoan;

/**
 * Bulk-load existing staff loans & advances when migrating from another system
 * — their current outstanding balance feeds payroll withholding immediately.
 *
 * IMPORTANT: imported loans do NOT post a disbursement journal entry. They are
 * opening balances — the loans-receivable balance is carried in the opening
 * trial balance, not re-posted per loan. New loans raised in-app still go
 * through approve() (which posts Dr Loans Receivable / Cr Cash). Re-importing
 * the same row (same staff + issue date + principal) updates it instead of
 * duplicating.
 */
class StaffLoanImport extends AbstractImport
{
    public function slug(): string { return 'staff-loans'; }
    public function title(): string { return 'Staff Loans'; }
    public function model(): string { return StaffLoan::class; }

    /** Same write gate the controller's store() uses. */
    public function permission(): string { return 'update_staff_loans'; }

    public function columns(): array
    {
        return [
            ImportColumn::make('staff', 'Staff')->required()->note('Staff email (preferred) or exact name')->example('sara@clinic.com'),
            ImportColumn::make('type', 'Type')->required()->allowed(['loan', 'advance']),
            ImportColumn::make('principal_amount', 'Principal amount')->required()->rules(['numeric', 'min:0.001'])->example('600'),
            ImportColumn::make('installment_amount', 'Installment amount')->required()->rules(['numeric', 'min:0'])->note('Withheld each payroll run — must be ≤ principal')->example('50'),
            ImportColumn::make('outstanding_amount', 'Outstanding amount')->rules(['numeric', 'min:0'])->note('Current balance still owed — blank uses the full principal'),
            ImportColumn::make('issued_on', 'Issued on')->required()->note('YYYY-MM-DD')->example('2025-03-01'),
            ImportColumn::make('status', 'Status')->allowed(['pending', 'active', 'settled'])->note('Blank = active (an outstanding opening balance)'),
            ImportColumn::make('branch', 'Branch')->note('Branch name, slug or id — blank applies to all branches'),
            ImportColumn::make('reason', 'Reason')->rules(['string', 'max:500']),
        ];
    }

    public function instructions(): array
    {
        return [
            'For migrating EXISTING loans — no disbursement entry is posted (opening balances). Raise brand-new loans in the app so they post to accounting.',
            'Outstanding amount is the balance still owed today; leave it blank to default to the full principal.',
            'Re-importing a row with the same staff + issue date + principal updates that loan instead of creating a duplicate.',
        ];
    }

    public function exampleRows(): array
    {
        return [['staff' => 'sara@clinic.com', 'type' => 'loan', 'principal_amount' => '600', 'installment_amount' => '50', 'outstanding_amount' => '450', 'issued_on' => '2025-03-01', 'status' => 'active']];
    }

    public function mapRow(array $row): array
    {
        $principal = round((float) $row['principal_amount'], 3);
        $installment = round((float) $row['installment_amount'], 3);
        if ($installment > $principal) {
            $this->fail('Installment amount must be less than or equal to the principal.');
        }

        $outstanding = ($row['outstanding_amount'] ?? null) !== null && $row['outstanding_amount'] !== ''
            ? round((float) $row['outstanding_amount'], 3)
            : $principal;
        if ($outstanding > $principal) {
            $this->fail('Outstanding amount cannot exceed the principal.');
        }

        return [
            'user_id' => $this->resolveUserId($row['staff'] ?? null),
            'branch_id' => $this->resolveBranchId($row['branch'] ?? null),
            'type' => $row['type'],
            'principal_amount' => $principal,
            'installment_amount' => $installment,
            'outstanding_amount' => $outstanding,
            'issued_on' => $this->date($row['issued_on'] ?? null),
            'status' => $row['status'] ?: StaffLoan::STATUS_ACTIVE,
            'reason' => $row['reason'] ?: null,
        ];
    }

    public function matchAttributes(array $attrs): array
    {
        // No natural unique key — dedupe on the loan's identity (who / when / how much).
        return [
            'user_id' => $attrs['user_id'],
            'issued_on' => $attrs['issued_on'],
            'principal_amount' => $attrs['principal_amount'],
        ];
    }
}
