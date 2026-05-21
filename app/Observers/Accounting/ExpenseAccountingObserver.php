<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Expense;
use App\Services\Accounting\AccountingService;

/**
 * Auto-posts expenses to the GL when they're created with status='posted'.
 *
 * Note: status changes draft → posted are typically handled by an explicit
 * "Post" action that calls $expense->post(), which itself calls the service.
 * The void path is handled by $expense->void(), which reverses the JE.
 * So no `updated` hook is required.
 */
class ExpenseAccountingObserver
{
    public function __construct(protected AccountingService $accounting) {}

    public function created(Expense $expense): void
    {
        if ($expense->status === Expense::STATUS_POSTED && ! $expense->journal_entry_id) {
            $expense->post($expense->posted_by_user_id);
        }
    }
}
