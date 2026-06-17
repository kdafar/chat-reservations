<?php

namespace App\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\ClinicStockMovement;
use App\Models\DoctorCompensationLedger;
use App\Models\Visit;
use App\Models\VisitPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Maps clinic-domain events to double-entry journal entries.
 *
 * All `record*` methods are idempotent: re-running for the same source
 * returns the existing entry without creating a duplicate.
 *
 * Errors are logged + swallowed — accounting should never break the
 * operational flow. Missing accounts get logged and the call no-ops.
 */
class AccountingService
{
    public function __construct(protected ChartOfAccounts $coa) {}

    // -------------------------------------------------------------------------
    // PUBLIC API — one method per clinic-event type
    // -------------------------------------------------------------------------

    /**
     * Cash receipt: Dr Cash (or Bank), Cr Service Revenue (kind-specific).
     * Refunds (status='refunded') reverse the original payment's entry.
     */
    public function recordVisitPayment(VisitPayment $payment): ?JournalEntry
    {
        try {
            // Handle refund: find the original payment entry and reverse it
            if (in_array($payment->status, ['refunded', 'void'], true)) {
                return $this->reverseSourceEntry($payment, "Payment {$payment->status}");
            }

            if ($payment->status !== 'paid') {
                return null; // pending — no entry yet
            }

            // Idempotency
            if ($existing = $this->existingFor($payment)) {
                return $existing;
            }

            $visit = $payment->visit;
            if (! $visit) {
                return null;
            }

            $amount = (float) $payment->amount;
            if ($amount <= 0) {
                return null;
            }

            $cashAccount = $this->coa->cashAccountFor($payment->method, (int) $visit->branch_id);
            $revenueAccount = $this->coa->revenueAccountFor((string) ($payment->kind ?? 'consultation'));

            if (! $cashAccount || ! $revenueAccount) {
                Log::warning('[AccountingService] missing accounts for VisitPayment', [
                    'payment_id' => $payment->id,
                    'method' => $payment->method,
                    'kind' => $payment->kind,
                ]);

                return null;
            }

            return $this->postBalancedEntry(
                date: $payment->paid_at ?? $payment->created_at ?? now(),
                narration: "Payment #{$payment->id} ({$payment->method}) for visit #{$visit->id}",
                source: $payment,
                branchId: (int) $visit->branch_id,
                lines: [
                    [
                        'account_id' => $cashAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Cash in ({$payment->method})",
                        'branch_id' => $visit->branch_id,
                        'doctor_id' => $visit->doctor_id,
                        'patient_id' => $visit->patient_id,
                    ],
                    [
                        'account_id' => $revenueAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => 'Service revenue ('.($payment->kind ?? 'consultation').')',
                        'branch_id' => $visit->branch_id,
                        'doctor_id' => $visit->doctor_id,
                        'patient_id' => $visit->patient_id,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordVisitPayment] error', [
                'payment_id' => $payment->id,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cost of Goods Sold: Dr COGS, Cr Inventory.
     * Posted when stock is consumed for a visit (qty_change_base is negative).
     */
    public function recordStockConsume(ClinicStockMovement $movement): ?JournalEntry
    {
        try {
            if ($movement->type !== 'consume') {
                return null;
            }
            if ($existing = $this->existingFor($movement)) {
                return $existing;
            }

            $qty = abs((float) $movement->qty_change_base);
            $item = $movement->clinicItem;
            if (! $item || $qty <= 0) {
                return null;
            }

            $unitCost = (float) ($item->default_cost ?? 0);
            $amount = round($qty * $unitCost, 3);
            if ($amount <= 0) {
                return null; // free items / services don't move the GL
            }

            $cogsAccount = $this->coa->resolve('5120'); // Cost of Items Sold
            $inventoryAccount = $this->coa->resolve('1150'); // Inventory

            if (! $cogsAccount || ! $inventoryAccount) {
                return null;
            }

            return $this->postBalancedEntry(
                date: $movement->created_at ?? now(),
                narration: "Inventory consume: {$item->localized_name} qty={$qty}",
                source: $movement,
                branchId: (int) $movement->branch_id,
                lines: [
                    [
                        'account_id' => $cogsAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => 'COGS: '.$item->localized_name,
                        'branch_id' => $movement->branch_id,
                    ],
                    [
                        'account_id' => $inventoryAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => 'Inventory out: '.$item->localized_name,
                        'branch_id' => $movement->branch_id,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordStockConsume] error', [
                'movement_id' => $movement->id,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Inventory receipt: Dr Inventory, Cr Cash (or AP if a bill).
     * For restock movements only.
     */
    public function recordStockRestock(ClinicStockMovement $movement): ?JournalEntry
    {
        try {
            if ($movement->type !== 'restock') {
                return null;
            }
            if ($existing = $this->existingFor($movement)) {
                return $existing;
            }

            $qty = (float) $movement->qty_change_base;
            $item = $movement->clinicItem;
            if (! $item || $qty <= 0) {
                return null;
            }

            $unitCost = (float) ($item->default_cost ?? 0);
            $amount = round($qty * $unitCost, 3);
            if ($amount <= 0) {
                return null;
            }

            $inventoryAccount = $this->coa->resolve('1150');
            // Assume paid in cash from main till by default. Real bills will use AP.
            $cashAccount = $this->coa->resolve('1110');

            if (! $inventoryAccount || ! $cashAccount) {
                return null;
            }

            return $this->postBalancedEntry(
                date: $movement->created_at ?? now(),
                narration: "Inventory restock: {$item->localized_name} qty={$qty}",
                source: $movement,
                branchId: (int) $movement->branch_id,
                lines: [
                    [
                        'account_id' => $inventoryAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => 'Inventory in: '.$item->localized_name,
                        'branch_id' => $movement->branch_id,
                    ],
                    [
                        'account_id' => $cashAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => 'Cash out: restock '.$item->localized_name,
                        'branch_id' => $movement->branch_id,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordStockRestock] error', [
                'movement_id' => $movement->id,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Doctor compensation accrual: Dr Doctor Comp Expense, Cr Doctor Payable.
     * Re-posts on every change to the ledger row (the source-link is stable).
     */
    public function recordDoctorCompensation(DoctorCompensationLedger $ledger): ?JournalEntry
    {
        try {
            $cut = (float) ($ledger->doctor_cut_amount ?? 0);
            if ($cut <= 0) {
                // Reverse any previously-posted entry for this ledger if the cut became zero.
                if ($existing = $this->existingFor($ledger)) {
                    return $this->reverseSourceEntry($ledger, 'Doctor cut recomputed to zero');
                }

                return null;
            }

            // If a previous entry exists with a different amount, reverse and re-post.
            $existing = $this->existingFor($ledger);
            if ($existing && abs((float) $existing->lines->sum('debit') - $cut) > 0.005) {
                $this->reverseSourceEntry($ledger, 'Doctor cut re-accrued');
                $existing = null;
            }
            if ($existing) {
                return $existing;
            }

            $expenseAccount = $this->coa->resolve('5130'); // Doctor Compensation Expense
            $payableAccount = $this->coa->resolve('2130'); // Doctor Payable

            if (! $expenseAccount || ! $payableAccount) {
                return null;
            }

            return $this->postBalancedEntry(
                date: now(),
                narration: "Doctor cut accrual: visit #{$ledger->visit_id}",
                source: $ledger,
                branchId: (int) $ledger->branch_id,
                lines: [
                    [
                        'account_id' => $expenseAccount->id,
                        'debit' => $cut,
                        'credit' => 0,
                        'description' => 'Doctor compensation expense',
                        'branch_id' => $ledger->branch_id,
                        'doctor_id' => $ledger->doctor_id,
                    ],
                    [
                        'account_id' => $payableAccount->id,
                        'debit' => 0,
                        'credit' => $cut,
                        'description' => 'Doctor payable accrual',
                        'branch_id' => $ledger->branch_id,
                        'doctor_id' => $ledger->doctor_id,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordDoctorCompensation] error', [
                'ledger_id' => $ledger->id,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Operational expense: Dr Expense (rent/utilities/etc), Cr Cash/Bank OR Accounts Payable.
     * If payment_account_id is set the expense is paid immediately; otherwise it accrues to AP (2010).
     *
     * Idempotent: returns the existing journal entry if this expense has already been posted.
     * On success, fills journal_entry_id + status='posted' + posted_at + posted_by_user_id.
     */
    public function recordExpense(\App\Models\Accounting\Expense $expense, ?int $userId = null): ?\App\Models\Accounting\JournalEntry
    {
        try {
            if ($expense->journal_entry_id) {
                return $expense->journalEntry;
            }
            if ((float) $expense->amount <= 0 || ! $expense->account_id) {
                return null;
            }

            $expenseAccount = $expense->account;
            // If payment_account_id is set the expense is paid (Dr Expense / Cr Cash or Bank).
            // If null it accrues to Accounts Payable 2010 (Dr Expense / Cr AP).
            $creditAccount = $expense->paymentAccount
                ?? $this->coa->resolve('2110');

            if (! $expenseAccount || ! $creditAccount) {
                Log::warning('[AccountingService] missing accounts for Expense', [
                    'expense_id' => $expense->id,
                    'account_id' => $expense->account_id,
                    'payment_account_id' => $expense->payment_account_id,
                ]);

                return null;
            }

            $amount = (float) $expense->amount;

            $entry = $this->postBalancedEntry(
                date: $expense->expense_date,
                narration: "Expense {$expense->code}: ".($expense->description ?? ''),
                source: $expense,
                branchId: $expense->branch_id,
                lines: [
                    [
                        'account_id' => $expenseAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => $expense->description,
                        'branch_id' => $expense->branch_id,
                    ],
                    [
                        'account_id' => $creditAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => $expense->vendor?->name ?? 'Vendor payment',
                        'branch_id' => $expense->branch_id,
                    ],
                ],
                userId: $userId,
            );

            $expense->forceFill([
                'status' => \App\Models\Accounting\Expense::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by_user_id' => $userId,
                'journal_entry_id' => $entry->id,
            ])->save();

            return $entry;
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordExpense] error', [
                'expense_id' => $expense->id,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cash receipt from an insurer paying down a claim AR balance.
     *
     *   Dr  Cash / Bank  (deposited_to_account_id, else branch cash via 'transfer')
     *   Cr  AR - Insurance (1110)
     *
     * Idempotent: keyed by (source_type=InsuranceClaimPayment::class, source_id=$payment->id, status=posted)
     * Errors are logged + swallowed — accounting should never break the payment flow.
     */
    public function recordInsurerPayment(\App\Models\Insurance\InsuranceClaimPayment $payment): ?JournalEntry
    {
        try {
            // Idempotency
            if ($existing = $this->existingFor($payment)) {
                return $existing;
            }

            $claim = $payment->claim;
            if (! $claim) {
                return null;
            }

            $amount = (float) $payment->amount;
            if ($amount <= 0) {
                return null;
            }

            // Debit side: explicit deposit account on the payment, else fall back to
            // a branch-scoped bank account via 'transfer' method (most insurer payouts
            // arrive by bank wire).
            $debitAccount = null;
            if ($payment->deposited_to_account_id) {
                $debitAccount = \App\Models\Accounting\Account::find($payment->deposited_to_account_id);
            }
            if (! $debitAccount) {
                $debitAccount = $this->coa->cashAccountFor('transfer', (int) $claim->branch_id);
            }

            $arAccount = $this->coa->resolve('1140'); // AR - Patients / Insurance

            if (! $debitAccount || ! $arAccount) {
                Log::warning('[AccountingService] missing accounts for InsuranceClaimPayment', [
                    'payment_id' => $payment->id,
                    'claim_id' => $payment->claim_id,
                    'deposited_to_account_id' => $payment->deposited_to_account_id,
                ]);

                return null;
            }

            return $this->postBalancedEntry(
                date: $payment->paid_at ?? $payment->created_at ?? now(),
                narration: "Insurer payment for claim {$claim->claim_number}".
                    ($payment->reference_no ? ", ref {$payment->reference_no}" : ''),
                source: $payment,
                branchId: (int) $claim->branch_id,
                lines: [
                    [
                        'account_id' => $debitAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Insurer payment in ({$payment->method})",
                        'branch_id' => $claim->branch_id,
                    ],
                    [
                        'account_id' => $arAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => "AR-Insurance settled: claim {$claim->claim_number}",
                        'branch_id' => $claim->branch_id,
                    ],
                ],
                userId: $payment->received_by_user_id,
            );
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordInsurerPayment] error', [
                'payment_id' => $payment->id,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Reverse the journal entry tied to an insurer payment (e.g. on soft-delete / void).
     * Public wrapper around reverseSourceEntry() so observers can call it without
     * needing visibility into the service internals.
     */
    public function recordInsurerPaymentReversal(\App\Models\Insurance\InsuranceClaimPayment $payment, ?string $reason = null): ?JournalEntry
    {
        try {
            return $this->reverseSourceEntry($payment, $reason ?? 'Insurer payment reversed');
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordInsurerPaymentReversal] error', [
                'payment_id' => $payment->id,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Write off an uncollectible insurance balance against bad-debt expense.
     *
     *   Dr  Bad Debt Expense (6020)
     *   Cr  AR - Insurance   (1110)
     *
     * Source link: the journal entry is posted with NO source link (source_type/source_id NULL),
     * because the unique (source_type, source_id, status) index would block a second posted
     * write-off against the same claim — and a claim may legitimately need multiple partial
     * write-offs over its lifetime (e.g. successive insurer rejections). The claim is captured
     * in narration + meta for traceability instead.
     */
    public function recordClaimWriteOff(\App\Models\Insurance\InsuranceClaim $claim, float $amount, string $reason, \App\Models\User $user): ?JournalEntry
    {
        try {
            if ($amount <= 0) {
                return null;
            }

            $expenseAccount = $this->coa->resolve('6530'); // Misc Expenses (bad debt / write-off)
            $arAccount = $this->coa->resolve('1140');      // AR - Patients / Insurance

            if (! $expenseAccount || ! $arAccount) {
                Log::warning('[AccountingService] missing accounts for claim write-off', [
                    'claim_id' => $claim->id,
                ]);

                return null;
            }

            // We post without a source-link to dodge the (source_type, source_id, status)
            // unique index — multiple write-offs per claim must be allowed. Traceability
            // lives in meta + narration.
            $entryDate = now();

            return DB::transaction(function () use ($entryDate, $claim, $amount, $reason, $user, $expenseAccount, $arAccount) {
                $entry = JournalEntry::create([
                    'entry_date' => $entryDate->toDateString(),
                    'narration' => "Write-off claim {$claim->claim_number}: {$reason}",
                    'status' => JournalEntry::STATUS_DRAFT,
                    'source_type' => null,
                    'source_id' => null,
                    'branch_id' => $claim->branch_id,
                    'currency' => 'KWD',
                    'meta' => [
                        'writeoff_claim_id' => $claim->id,
                        'writeoff_claim_number' => $claim->claim_number,
                        'writeoff_amount' => $amount,
                        'writeoff_reason' => $reason,
                        'writeoff_user_id' => $user->id,
                        'writeoff_at' => $entryDate->toIso8601String(),
                    ],
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $expenseAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "Bad debt: claim {$claim->claim_number}",
                    'branch_id' => $claim->branch_id,
                    'currency' => 'KWD',
                ]);
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $arAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "AR-Insurance written off: claim {$claim->claim_number}",
                    'branch_id' => $claim->branch_id,
                    'currency' => 'KWD',
                ]);

                $entry->post($user->id);

                return $entry->refresh();
            });
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordClaimWriteOff] error', [
                'claim_id' => $claim->id,
                'amount' => $amount,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // -------------------------------------------------------------------------
    // INTERNAL HELPERS
    // -------------------------------------------------------------------------

    /**
     * Goods received against a purchase order: Dr Inventory (1200) /
     * Cr Accounts Payable (2010), at the PO unit cost. The stock movements
     * themselves use a 'purchase_in' type the stock observer ignores, so this
     * is the single authoritative GL entry for the receipt. Idempotent.
     */
    public function recordPurchaseReceipt(\App\Models\Purchasing\PurchaseReceipt $receipt, ?int $userId = null): ?JournalEntry
    {
        try {
            if ($receipt->journal_entry_id) {
                return $receipt->journalEntry;
            }
            if ($existing = $this->existingFor($receipt)) {
                return $existing;
            }

            // Goods value (KWD → vendor Accounts Payable) and allocated landed
            // costs (KWD → Import Costs Payable). Inventory is debited the sum.
            $goods = round((float) $receipt->total_amount, 3);
            $landed = round((float) $receipt->landed_amount, 3);
            $inventoryValue = round($goods + $landed, 3);
            if ($inventoryValue <= 0) {
                return null;
            }

            $inventory = $this->coa->resolve('1150');
            $payable = $this->coa->resolve('2110');
            $importPayable = $landed > 0 ? $this->coa->resolve('2190') : null;
            if (! $inventory || ! $payable || ($landed > 0 && ! $importPayable)) {
                Log::warning('[AccountingService] missing accounts for PurchaseReceipt', [
                    'receipt_id' => $receipt->id,
                ]);

                return null;
            }

            $vendorName = $receipt->purchaseOrder?->vendor?->name ?? 'Vendor';

            $lines = [
                [
                    'account_id' => $inventory->id,
                    'debit' => $inventoryValue,
                    'credit' => 0,
                    'description' => 'Inventory in: '.$receipt->code,
                    'branch_id' => $receipt->branch_id,
                ],
                [
                    'account_id' => $payable->id,
                    'debit' => 0,
                    'credit' => $goods,
                    'description' => 'Payable to '.$vendorName,
                    'branch_id' => $receipt->branch_id,
                ],
            ];
            if ($landed > 0 && $importPayable) {
                $lines[] = [
                    'account_id' => $importPayable->id,
                    'debit' => 0,
                    'credit' => $landed,
                    'description' => 'Landed/import costs: '.$receipt->code,
                    'branch_id' => $receipt->branch_id,
                ];
            }

            $entry = $this->postBalancedEntry(
                date: $receipt->received_at ?? now(),
                narration: "Goods received {$receipt->code} ({$vendorName})",
                source: $receipt,
                branchId: (int) $receipt->branch_id,
                lines: $lines,
                userId: $userId,
            );

            $receipt->forceFill(['journal_entry_id' => $entry->id])->save();

            return $entry;
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordPurchaseReceipt] error', [
                'receipt_id' => $receipt->id,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Payment to a vendor against a purchase order: Dr Accounts Payable (2010) /
     * Cr Cash or Bank (resolved from the payment method). Idempotent.
     */
    public function recordPurchasePayment(\App\Models\Purchasing\PurchasePayment $payment, ?int $userId = null): ?JournalEntry
    {
        try {
            if ($payment->journal_entry_id) {
                return $payment->journalEntry;
            }
            if ($existing = $this->existingFor($payment)) {
                return $existing;
            }

            $amount = round((float) $payment->amount, 3);
            if ($amount <= 0) {
                return null;
            }

            $payable = $this->coa->resolve('2110');
            $credit = $payment->paymentAccount
                ?? $this->coa->cashAccountFor($payment->method, (int) $payment->branch_id);
            if (! $payable || ! $credit) {
                Log::warning('[AccountingService] missing accounts for PurchasePayment', [
                    'payment_id' => $payment->id,
                ]);

                return null;
            }

            $vendorName = $payment->vendor?->name ?? 'Vendor';

            $entry = $this->postBalancedEntry(
                date: $payment->payment_date ?? now(),
                narration: "Vendor payment {$payment->code} ({$vendorName})",
                source: $payment,
                branchId: (int) $payment->branch_id,
                lines: [
                    [
                        'account_id' => $payable->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => 'Settle payable: '.$vendorName,
                        'branch_id' => $payment->branch_id,
                    ],
                    [
                        'account_id' => $credit->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => 'Paid '.$vendorName.' ('.$payment->method.')',
                        'branch_id' => $payment->branch_id,
                    ],
                ],
                userId: $userId,
            );

            $payment->forceFill(['journal_entry_id' => $entry->id])->save();

            return $entry;
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordPurchasePayment] error', [
                'payment_id' => $payment->id,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Reverse the GL entry tied to a voided vendor payment.
     */
    public function recordPurchasePaymentReversal(\App\Models\Purchasing\PurchasePayment $payment, ?string $reason = null): ?JournalEntry
    {
        return $this->reverseSourceEntry($payment, $reason ?? 'Vendor payment voided');
    }

    /**
     * Reverse the GL entry tied to a reversed goods receipt (Dr AP + Import
     * Costs Payable / Cr Inventory — the mirror of the original posting).
     */
    public function recordPurchaseReceiptReversal(\App\Models\Purchasing\PurchaseReceipt $receipt, ?string $reason = null): ?JournalEntry
    {
        return $this->reverseSourceEntry($receipt, $reason ?? 'Goods receipt reversed');
    }

    // -------------------------------------------------------------------------
    // PAYROLL / HR
    //
    // A payroll run posts TWO entries (accrual + payment) against the same
    // source, which would collide on the (source_type, source_id, status)
    // unique index. So payroll/settlement entries are posted UNLINKED
    // (source = null) with the run/settlement captured in meta; idempotency is
    // guarded on the run's own *_journal_entry_id columns instead.
    // -------------------------------------------------------------------------

    /**
     * Salary accrual for an approved payroll run:
     *   Dr 6015 Staff Salaries (total_salary)  /  Cr 2030 Staff Salaries Payable.
     *
     * Doctor commission is NOT accrued here — it was already expensed per-visit
     * via the compensation ledger (Dr 6010 / Cr 2020). Idempotent on the run.
     */
    public function recordPayrollAccrual(\App\Models\PayrollRun $run, ?int $userId = null): ?JournalEntry
    {
        try {
            if ($run->accrual_journal_entry_id) {
                return JournalEntry::find($run->accrual_journal_entry_id);
            }
            $salary = round((float) $run->total_salary, 3);
            if ($salary <= 0) {
                return null; // pure-commission run — nothing new to accrue
            }

            $expense = $this->coa->resolve('6110'); // Staff Salaries
            $payable = $this->coa->resolve('2130'); // Staff Salaries Payable
            if (! $expense || ! $payable) {
                Log::warning('[AccountingService] missing accounts for payroll accrual', ['run_id' => $run->id]);

                return null;
            }

            $entry = $this->postBalancedEntry(
                date: $run->pay_date ?? now(),
                narration: "Payroll accrual {$run->periodLabel()}",
                source: null,
                branchId: $run->branch_id,
                lines: [
                    ['account_id' => $expense->id, 'debit' => $salary, 'credit' => 0, 'description' => 'Staff salaries '.$run->periodLabel(), 'branch_id' => $run->branch_id],
                    ['account_id' => $payable->id, 'debit' => 0, 'credit' => $salary, 'description' => 'Salaries payable '.$run->periodLabel(), 'branch_id' => $run->branch_id],
                ],
                userId: $userId,
            );
            $this->tagSource($entry, ['payroll_run_id' => $run->id, 'kind' => 'payroll_accrual']);
            $run->forceFill(['accrual_journal_entry_id' => $entry->id])->save();

            return $entry;
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordPayrollAccrual] error', ['run_id' => $run->id, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Disbursement for a paid payroll run:
     *   Dr 2030 Salaries Payable (total_salary)
     *   Dr 2020 Doctor Payable   (total_commission — settles standing accrual)
     *     Cr 1130 Staff Loans Receivable (total_loan_repaid — withheld installments)
     *     Cr Cash/Bank                   (net cash actually paid out)
     *
     * Idempotent on the run. Cash account: the run's payment_account_id, else
     * the branch cash account.
     */
    public function recordPayrollPayment(\App\Models\PayrollRun $run, ?int $userId = null): ?JournalEntry
    {
        try {
            if ($run->payment_journal_entry_id) {
                return JournalEntry::find($run->payment_journal_entry_id);
            }

            $salary = round((float) $run->total_salary, 3);
            $commission = round((float) $run->total_commission, 3);
            $loan = round((float) $run->total_loan_repaid, 3);
            $cash = round($salary + $commission - $loan, 3);
            if ($salary + $commission <= 0) {
                return null;
            }

            $cashAccount = $run->payment_account_id
                ? \App\Models\Accounting\Account::find($run->payment_account_id)
                : $this->coa->cashAccountFor('cash', (int) ($run->branch_id ?? 0));
            $salaryPayable = $this->coa->resolve('2130');
            $doctorPayable = $this->coa->resolve('2130');
            $loanRecv = $this->coa->resolve('1180');
            if (! $cashAccount || ! $salaryPayable || ! $doctorPayable || ! $loanRecv) {
                Log::warning('[AccountingService] missing accounts for payroll payment', ['run_id' => $run->id]);

                return null;
            }

            $lines = [];
            if ($salary > 0) {
                $lines[] = ['account_id' => $salaryPayable->id, 'debit' => $salary, 'credit' => 0, 'description' => 'Settle salaries '.$run->periodLabel(), 'branch_id' => $run->branch_id];
            }
            if ($commission > 0) {
                $lines[] = ['account_id' => $doctorPayable->id, 'debit' => $commission, 'credit' => 0, 'description' => 'Settle doctor commission '.$run->periodLabel(), 'branch_id' => $run->branch_id];
            }
            if ($loan > 0) {
                $lines[] = ['account_id' => $loanRecv->id, 'debit' => 0, 'credit' => $loan, 'description' => 'Loan repayments withheld '.$run->periodLabel(), 'branch_id' => $run->branch_id];
            }
            $lines[] = ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $cash, 'description' => 'Net payroll paid '.$run->periodLabel(), 'branch_id' => $run->branch_id];

            $entry = $this->postBalancedEntry(
                date: $run->paid_at ?? $run->pay_date ?? now(),
                narration: "Payroll paid {$run->periodLabel()}",
                source: null,
                branchId: $run->branch_id,
                lines: $lines,
                userId: $userId,
            );
            $this->tagSource($entry, ['payroll_run_id' => $run->id, 'kind' => 'payroll_payment']);
            $run->forceFill(['payment_journal_entry_id' => $entry->id])->save();

            return $entry;
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordPayrollPayment] error', ['run_id' => $run->id, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Loan/advance disbursement: Dr 1130 Staff Loans Receivable / Cr Cash/Bank.
     * Source-linked + idempotent on the loan's journal_entry_id.
     */
    public function recordLoanDisbursement(\App\Models\StaffLoan $loan, ?int $userId = null): ?JournalEntry
    {
        try {
            if ($loan->journal_entry_id) {
                return $loan->journalEntry;
            }
            $amount = round((float) $loan->principal_amount, 3);
            if ($amount <= 0) {
                return null;
            }

            $loanRecv = $this->coa->resolve('1180');
            $cashAccount = $loan->payment_account_id
                ? \App\Models\Accounting\Account::find($loan->payment_account_id)
                : $this->coa->cashAccountFor('cash', (int) ($loan->branch_id ?? 0));
            if (! $loanRecv || ! $cashAccount) {
                Log::warning('[AccountingService] missing accounts for loan disbursement', ['loan_id' => $loan->id]);

                return null;
            }

            $entry = $this->postBalancedEntry(
                date: $loan->issued_on ?? now(),
                narration: ucfirst($loan->type)." to {$loan->user?->name} (#{$loan->id})",
                source: $loan,
                branchId: $loan->branch_id,
                lines: [
                    ['account_id' => $loanRecv->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Staff '.$loan->type.' receivable', 'branch_id' => $loan->branch_id],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Cash out: '.$loan->type, 'branch_id' => $loan->branch_id],
                ],
                userId: $userId,
            );
            $loan->forceFill(['journal_entry_id' => $entry->id])->save();

            return $entry;
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordLoanDisbursement] error', ['loan_id' => $loan->id, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * End-of-service settlement accrual:
     *   Dr 6016 End-of-Service Expense (gratuity)
     *   Dr 6015 Staff Salaries        (leave encashment + other additions)
     *     Cr 1130 Loans Receivable    (loan clawback)
     *     Cr 4040 Other Income        (other deductions recovered)
     *     Cr 2030 Salaries Payable    (net settlement owed)
     * Idempotent on the settlement.
     */
    public function recordSettlementAccrual(\App\Models\StaffSettlement $s, ?int $userId = null): ?JournalEntry
    {
        try {
            if ($s->accrual_journal_entry_id) {
                return JournalEntry::find($s->accrual_journal_entry_id);
            }
            $gratuity = round((float) $s->gratuity_amount, 3);
            $extras = round((float) $s->leave_encashment + (float) $s->other_additions, 3);
            $clawback = round((float) $s->loan_clawback, 3);
            $otherDed = round((float) $s->other_deductions, 3);
            $net = round((float) $s->net_settlement, 3);
            if ($gratuity + $extras <= 0) {
                return null;
            }

            $eosExpense = $this->coa->resolve('6120');
            $salaryExpense = $this->coa->resolve('6110');
            $loanRecv = $this->coa->resolve('1180');
            $otherIncome = $this->coa->resolve('4290');
            $payable = $this->coa->resolve('2130');
            if (! $eosExpense || ! $salaryExpense || ! $loanRecv || ! $otherIncome || ! $payable) {
                Log::warning('[AccountingService] missing accounts for settlement accrual', ['settlement_id' => $s->id]);

                return null;
            }

            $lines = [];
            if ($gratuity > 0) {
                $lines[] = ['account_id' => $eosExpense->id, 'debit' => $gratuity, 'credit' => 0, 'description' => 'End-of-service gratuity', 'branch_id' => $s->branch_id];
            }
            if ($extras > 0) {
                $lines[] = ['account_id' => $salaryExpense->id, 'debit' => $extras, 'credit' => 0, 'description' => 'Leave encashment / additions', 'branch_id' => $s->branch_id];
            }
            if ($clawback > 0) {
                $lines[] = ['account_id' => $loanRecv->id, 'debit' => 0, 'credit' => $clawback, 'description' => 'Outstanding loan netted off', 'branch_id' => $s->branch_id];
            }
            if ($otherDed > 0) {
                $lines[] = ['account_id' => $otherIncome->id, 'debit' => 0, 'credit' => $otherDed, 'description' => 'Settlement deductions', 'branch_id' => $s->branch_id];
            }
            $lines[] = ['account_id' => $payable->id, 'debit' => 0, 'credit' => $net, 'description' => 'Final settlement payable', 'branch_id' => $s->branch_id];

            $entry = $this->postBalancedEntry(
                date: $s->last_working_day ?? now(),
                narration: "End-of-service settlement: {$s->user?->name} (#{$s->id})",
                source: null,
                branchId: $s->branch_id,
                lines: $lines,
                userId: $userId,
            );
            $this->tagSource($entry, ['settlement_id' => $s->id, 'kind' => 'settlement_accrual']);
            $s->forceFill(['accrual_journal_entry_id' => $entry->id])->save();

            return $entry;
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordSettlementAccrual] error', ['settlement_id' => $s->id, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * End-of-service settlement payment: Dr 2030 Salaries Payable / Cr Cash.
     * Idempotent on the settlement.
     */
    public function recordSettlementPayment(\App\Models\StaffSettlement $s, ?int $userId = null): ?JournalEntry
    {
        try {
            if ($s->payment_journal_entry_id) {
                return JournalEntry::find($s->payment_journal_entry_id);
            }
            $net = round((float) $s->net_settlement, 3);
            if ($net <= 0) {
                return null;
            }

            $payable = $this->coa->resolve('2130');
            $cashAccount = $s->payment_account_id
                ? \App\Models\Accounting\Account::find($s->payment_account_id)
                : $this->coa->cashAccountFor('cash', (int) ($s->branch_id ?? 0));
            if (! $payable || ! $cashAccount) {
                Log::warning('[AccountingService] missing accounts for settlement payment', ['settlement_id' => $s->id]);

                return null;
            }

            $entry = $this->postBalancedEntry(
                date: $s->paid_at ?? now(),
                narration: "End-of-service paid: {$s->user?->name} (#{$s->id})",
                source: null,
                branchId: $s->branch_id,
                lines: [
                    ['account_id' => $payable->id, 'debit' => $net, 'credit' => 0, 'description' => 'Settle final settlement', 'branch_id' => $s->branch_id],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $net, 'description' => 'Final settlement paid', 'branch_id' => $s->branch_id],
                ],
                userId: $userId,
            );
            $this->tagSource($entry, ['settlement_id' => $s->id, 'kind' => 'settlement_payment']);
            $s->forceFill(['payment_journal_entry_id' => $entry->id])->save();

            return $entry;
        } catch (\Throwable $e) {
            Log::error('[AccountingService::recordSettlementPayment] error', ['settlement_id' => $s->id, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /** Persist traceability meta on an unlinked (source=null) entry. */
    protected function tagSource(JournalEntry $entry, array $meta): void
    {
        $entry->forceFill(['meta' => array_merge((array) $entry->meta, $meta)])->save();
    }

    /**
     * Post a balanced entry in one shot, validating debits == credits.
     *
     * Race protection: if a parallel call (observer + manual call, double-fire,
     * etc.) wins the post() race, the unique (source_type, source_id, status)
     * index from 2026_05_20_145734 turns the duplicate post into SQLSTATE
     * 23000. We catch it and return the entry the other thread already posted.
     */
    protected function postBalancedEntry(
        Carbon|string $date,
        string $narration,
        ?Model $source,
        ?int $branchId,
        array $lines,
        ?int $userId = null,
    ): JournalEntry {
        $entryDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        try {
            return DB::transaction(function () use ($entryDate, $narration, $source, $branchId, $lines, $userId) {
                $entry = JournalEntry::create([
                    'entry_date' => $entryDate->toDateString(),
                    'narration' => $narration,
                    'status' => JournalEntry::STATUS_DRAFT,
                    'source_type' => $source ? get_class($source) : null,
                    'source_id' => $source?->getKey(),
                    'branch_id' => $branchId,
                    'currency' => 'KWD',
                ]);

                foreach ($lines as $line) {
                    JournalEntryLine::create(array_merge($line, [
                        'journal_entry_id' => $entry->id,
                        'currency' => $line['currency'] ?? 'KWD',
                    ]));
                }

                $entry->post($userId);

                return $entry->refresh();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // SQLSTATE 23000 = integrity constraint violation = race lost.
            // The parallel call already inserted the posted entry — return it.
            if (($e->errorInfo[0] ?? null) === '23000' && $source) {
                $existing = $this->existingFor($source);
                if ($existing) {
                    return $existing;
                }
            }
            throw $e;
        }
    }

    /**
     * Find an existing POSTED entry for a given source (idempotency).
     * Reversed and draft entries are ignored.
     */
    protected function existingFor(Model $source): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('source_type', get_class($source))
            ->where('source_id', $source->getKey())
            ->where('status', JournalEntry::STATUS_POSTED)
            ->with('lines')
            ->latest('id')
            ->first();
    }

    /**
     * Reverse the ORIGINAL posted entry for a given source.
     *
     * Idempotent. If the original is already reversed (or already had its
     * reversal posted), this is a no-op returning null — so refunding a
     * payment twice, or hitting the observer + a manual recordVisitPayment()
     * call with the refunded status both result in exactly ONE reversal.
     */
    protected function reverseSourceEntry(Model $source, ?string $reason = null): ?JournalEntry
    {
        // Find the FIRST entry posted for this source (the real original,
        // not a reversal entry that's also tagged with the same source).
        $original = JournalEntry::query()
            ->where('source_type', get_class($source))
            ->where('source_id', $source->getKey())
            ->oldest('id')
            ->first();

        if (! $original) {
            return null;
        }

        // Already reversed? Nothing to do.
        if ($original->status === JournalEntry::STATUS_REVERSED || $original->reversed_by_id) {
            return null;
        }

        // Must be posted to be reversible (drafts shouldn't reach this path).
        if ($original->status !== JournalEntry::STATUS_POSTED) {
            return null;
        }

        return $original->reverse(null, $reason);
    }
}
