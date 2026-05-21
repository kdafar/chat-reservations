<?php

return [
    'user_badge' => [
        'all_branches' => 'All branches',
        'no_branch' => 'No branch assigned',
        'multiple_branches' => ':count branches',
    ],

    'nav' => [
        'clinic_operations' => 'Clinic — Operations',
        'clinic_scheduling' => 'Clinic — Scheduling',
        'clinic_inventory' => 'Clinic — Inventory',
        'clinic_finance' => 'Clinic — Finance',
        'clinic_reports' => 'Clinic — Reports',
        'clinic_setup' => 'Clinic — Setup',
        'clinic_tools' => 'Clinic — Tools',
        'clinic_compliance' => 'Clinic — Compliance',
        'accounting' => 'Accounting',
    ],

    'actions' => [
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'view' => 'View',
        'refresh' => 'Refresh',
        'export' => 'Export',
        'import' => 'Import',
        'print' => 'Print',
        'close' => 'Close',
        'submit' => 'Submit',
        'back' => 'Back',
        'next' => 'Next',
        'previous' => 'Previous',
        'search' => 'Search',
        'filter' => 'Filter',
        'reset' => 'Reset',
        'apply' => 'Apply',
        'confirm' => 'Confirm',
        'yes' => 'Yes',
        'no' => 'No',
        'help' => 'Help',
        'language' => 'Language',
    ],

    // Visit lifecycle statuses (see App\Models\Visit::STATUS_*)
    'visit_status' => [
        'draft' => 'Draft',
        'awaiting_doctor' => 'Awaiting Doctor',
        'in_progress' => 'In Progress',
        'awaiting_stock' => 'Awaiting Stock',
        'awaiting_payment' => 'Awaiting Payment',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-Show',
    ],

    // Booking lifecycle statuses
    'booking_status' => [
        'draft' => 'Draft',
        'hold' => 'On Hold',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked In',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-Show',
    ],

    // Payment methods
    'payment_method' => [
        'cash' => 'Cash',
        'knet' => 'K-Net',
        'card' => 'Credit Card',
        'link' => 'Payment Link',
        'transfer' => 'Bank Transfer',
        'wallet' => 'Wallet',
        'other' => 'Other',
    ],

    // Payment statuses
    'payment_status' => [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],

    // Journal entry statuses
    'je_status' => [
        'draft' => 'Draft',
        'posted' => 'Posted',
        'reversed' => 'Reversed',
    ],

    // Accounting period statuses
    'period_status' => [
        'open' => 'Open',
        'closed' => 'Closed',
    ],

    // Stock movement types
    'stock_movement' => [
        'restock' => 'Restock',
        'consume' => 'Consume',
        'adjust' => 'Adjust',
        'transfer' => 'Transfer',
    ],

    'fields' => [
        'name' => 'Name',
        'name_en' => 'Name (English)',
        'name_ar' => 'Name (Arabic)',
        'code' => 'Code',
        'description' => 'Description',
        'status' => 'Status',
        'branch' => 'Branch',
        'doctor' => 'Doctor',
        'patient' => 'Patient',
        'phone' => 'Phone',
        'amount' => 'Amount',
        'date' => 'Date',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'notes' => 'Notes',
        'is_active' => 'Active',
    ],

    'misc' => [
        'all' => 'All',
        'none' => 'None',
        'unknown' => 'Unknown',
        'optional' => 'Optional',
        'required' => 'Required',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'system' => 'System',
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'currency' => 'KWD',
    ],

    'locale' => [
        'switch_to_arabic' => 'Switch to Arabic',
        'switch_to_english' => 'Switch to English',
        'arabic' => 'العربية',
        'english' => 'English',
    ],
];
