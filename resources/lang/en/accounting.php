<?php

return [

    'journal_entry' => [
        // Sections
        'section_entry' => 'Entry',
        'section_lines' => 'Lines',

        // Fields
        'date' => 'Date',
        'branch' => 'Branch',
        'currency' => 'Currency',
        'narration' => 'Narration',
        'account' => 'Account',
        'debit' => 'Debit',
        'credit' => 'Credit',
        'description' => 'Description',
        'balance' => 'Balance',
        'code' => 'Code',
        'source' => 'Source',
        'posted_by' => 'Posted By',
        'status' => 'Status',

        // Repeater
        'add_line' => 'Add line',

        // Balance placeholder texts
        'balance_balanced' => 'Debits: :debit | Credits: :credit — ✓ Balanced',
        'balance_off' => 'Debits: :debit | Credits: :credit — ⚠ Off by :diff',

        // Filters
        'filter_from' => 'From',
        'filter_to' => 'To',

        // Actions
        'reverse' => 'Reverse',
        'reverse_modal_description' => 'Creates a new offsetting entry. The original is marked Reversed. This cannot be undone.',
        'reverse_reason' => 'Reason',
        'post_draft' => 'Post Draft',
        'post_modal_description' => 'Posting will validate the entry balances and freeze it. This cannot be undone.',

        // Notifications
        'entry_reversed' => 'Entry reversed',
        'reversal_body' => 'Reversal: :code',
        'entry_posted' => 'Entry posted',
        'failed' => 'Failed',
        'cannot_post' => 'Cannot post',

        // Placeholders
        'placeholder_system' => 'System',
        'placeholder_dash' => '—',
    ],

    'chart_of_account' => [
        // Sections
        'section_account' => 'Account',

        // Fields
        'code' => 'Code',
        'code_helper' => 'Numeric code, e.g. 1010, 4020.',
        'name' => 'Name',
        'type' => 'Type',
        'parent_account' => 'Parent Account',
        'branch_optional' => 'Branch (optional)',
        'branch_helper' => 'Scope this account to a specific branch (e.g. "Cash - Branch 4").',
        'currency' => 'Currency',
        'is_active' => 'Active',
        'description' => 'Description',
        'branch' => 'Branch',
        'balance_kwd' => 'Balance (KWD)',
        'system' => 'System',

        // Account types
        'type_asset' => 'Asset',
        'type_liability' => 'Liability',
        'type_equity' => 'Equity',
        'type_revenue' => 'Revenue',
        'type_cogs' => 'Cost of Sales',
        'type_expense' => 'Expense',
        'type_contra_asset' => 'Contra-Asset',
        'type_contra_liability' => 'Contra-Liability',
        'type_contra_revenue' => 'Contra-Revenue',

        // Tooltips
        'system_tooltip' => 'Used by auto-posting; cannot delete',
        'user_managed_tooltip' => 'User-managed',

        // Placeholders
        'placeholder_dash' => '—',
    ],

    'accounting_period' => [
        // Sections
        'section_period' => 'Period',

        // Fields
        'code' => 'Code',
        'status' => 'Status',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'closed_at' => 'Closed At',
        'closed_by' => 'Closed By',
        'closing_je' => 'Closing JE',
        'notes' => 'Notes',
        'year' => 'Year',

        // Actions
        'close_period' => 'Close Period',
        'close_modal_description' => 'Closing will create a journal entry that zeros all revenue, COGS, and expense accounts into Retained Earnings. Drafts in this period must be posted or deleted first.',
        'reopen_period' => 'Reopen Period',
        'reopen_modal_description' => 'Reopening will reverse the closing journal entry. Any entries dated in this period can be posted/edited again.',
        'view_closing_je' => 'View Closing JE',

        // Notifications
        'period_closed_title' => 'Period :code closed',
        'closing_je_body' => 'Closing JE: :code',
        'cannot_close_period' => 'Cannot close period',
        'period_reopened_title' => 'Period :code reopened',
        'cannot_reopen_period' => 'Cannot reopen period',

        // Placeholders
        'placeholder_dash' => '—',
    ],

    'bank_reconciliation' => [
        // Sections
        'section_reconciliation' => 'Reconciliation',

        // Fields
        'bank_cash_account' => 'Bank / Cash Account',
        'bank_cash_helper' => 'Cash on Hand (1010, 1010-{branch}) or Bank Accounts (1020, 1021, 1022).',
        'period_start' => 'Period Start',
        'period_end' => 'Period End',
        'opening_balance_statement' => 'Opening Balance (per statement)',
        'closing_balance_statement' => 'Closing Balance (per statement)',
        'notes' => 'Notes',
        'code' => 'Code',
        'account' => 'Account',
        'period' => 'Period',
        'opening' => 'Opening',
        'closing' => 'Closing',
        'book_opening' => 'Book Opening',
        'book_closing' => 'Book Closing',
        'diff' => 'Diff',
        'matched_total' => 'Matched/Total',

        // Status options
        'status_in_progress' => 'In Progress',
        'status_completed' => 'Completed',

        // Filters
        'filter_from' => 'From',
        'filter_to' => 'To',
        'filter_period_start' => 'Period Start',

        // Actions
        'recompute' => 'Recompute Book Balances',
        'auto_match' => 'Auto-match',
        'auto_match_modal_description' => 'Auto-pair unmatched statement lines with unmatched journal entry lines where amounts and dates (±2 days) align.',
        'mark_complete' => 'Mark Complete',
        'mark_complete_modal_description' => 'Freeze this reconciliation. Statement lines will no longer be editable. Admins can re-open it later.',
        'reopen' => 'Reopen',
        'reopen_modal_description' => 'Re-open this completed reconciliation. Lines will become editable again.',

        // Notifications
        'book_balances_refreshed' => 'Book balances refreshed',
        'closing_amount_body' => 'Closing: :amount KWD',
        'failed' => 'Failed',
        'auto_matched_title' => 'Auto-matched :count line(s)',
        'reconciliation_completed' => 'Reconciliation completed',
        'reconciliation_reopened' => 'Reconciliation re-opened',
    ],

    'expense' => [
        // Sections
        'section_expense' => 'Expense',
        'section_payment' => 'Payment',

        // Fields
        'code' => 'Code',
        'code_placeholder' => 'Auto-generated on save',
        'date' => 'Date',
        'vendor' => 'Vendor',
        'branch' => 'Branch',
        'expense_account' => 'Expense Account',
        'expense_account_helper' => 'The expense category being debited (e.g. 6030 Rent).',
        'amount_kwd' => 'Amount (KWD)',
        'description' => 'Description',
        'paid_from' => 'Paid From',
        'paid_from_helper' => 'Leave empty if billed to Accounts Payable.',
        'reference_invoice' => 'Reference / Invoice No.',
        'reference' => 'Reference',
        'receipt' => 'Receipt',
        'account' => 'Account',
        'status' => 'Status',
        'on_account' => 'On Account',

        // Status options
        'status_draft' => 'Draft',
        'status_posted' => 'Posted',
        'status_void' => 'Void',

        // Filters
        'filter_from' => 'From',
        'filter_to' => 'To',

        // Actions
        'post' => 'Post',
        'post_modal_description' => 'Posts this expense to the General Ledger. This cannot be edited afterwards.',
        'void' => 'Void',
        'void_modal_description' => 'Reverses the journal entry and marks this expense as void. The audit trail is preserved.',

        // Notifications
        'expense_posted' => 'Expense posted',
        'je_body' => 'JE: :code',
        'posting_failed' => 'Posting failed',
        'posting_failed_body' => 'Check that all required accounts are configured.',
        'failed' => 'Failed',
        'expense_voided' => 'Expense voided',

        // Placeholders
        'placeholder_dash' => '—',
    ],

    'vendor' => [
        // Sections
        'section_vendor' => 'Vendor',
        'section_defaults' => 'Defaults',
        'section_defaults_description' => 'Suggested accounts when creating an expense for this vendor.',
        'section_other' => 'Other',

        // Fields
        'name' => 'Name',
        'code' => 'Code',
        'code_helper' => 'Optional short reference (e.g. LANDLORD-A).',
        'contact_name' => 'Contact Name',
        'contact' => 'Contact',
        'phone' => 'Phone',
        'email' => 'Email',
        'tax_number' => 'Tax / Commercial Reg. No.',
        'address' => 'Address',
        'default_expense_account' => 'Default Expense Account',
        'default_payable_account' => 'Default Payable Account',
        'default_payable_account_helper' => 'Used when billing this vendor on account (typically 2010 Accounts Payable).',
        'default_account' => 'Default Account',
        'is_active' => 'Active',
        'notes' => 'Notes',

        // Filters
        'filter_active' => 'Active',

        // Placeholders
        'placeholder_dash' => '—',
    ],

];
