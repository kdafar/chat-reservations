<?php

return [
    'sections' => [
        'visit_context' => 'Visit Context',
        'follow_up' => 'Follow-up',
        'financial_snapshot' => 'Financial Snapshot',
    ],

    'fields' => [
        'appointment' => [
            'label' => 'Appointment',
            'helper' => 'Usually created automatically on appointment check-in.',
        ],
        'room' => [
            'label' => 'Room',
        ],
        'branch' => [
            'label' => 'Branch',
        ],
        'patient' => [
            'label' => 'Patient',
        ],
        'doctor' => [
            'label' => 'Doctor',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'source' => [
            'label' => 'Source',
            'placeholder' => 'web / whatsapp / call / walk_in / reception',
            'helper' => 'Attribution only.',
        ],
        'booking_code' => [
            'label' => 'Booking Code',
            'helper' => 'Snapshot from appointment (optional).',
        ],
        'checked_in_at' => [
            'label' => 'Checked In At',
        ],
        'queued_at' => [
            'label' => 'Queued At',
        ],
        'accepted_at' => [
            'label' => 'Accepted At',
        ],
        'accepted_by' => [
            'label' => 'Accepted By',
        ],
        'service_started_at' => [
            'label' => 'Service Started At',
        ],
        'completed_at' => [
            'label' => 'Completed At',
        ],
        'follow_up_date' => [
            'label' => 'Follow-up Date',
        ],
        'auto_create_follow_up_booking' => [
            'label' => 'Auto-create follow-up booking',
            'helper' => 'If enabled, system creates a pending booking for the follow-up date/time.',
        ],
        'notes' => [
            'label' => 'Internal Notes',
        ],
        'fees_total' => [
            'label' => 'Fees Total',
            'helper' => 'Auto-computed as SUM(visit_charges.line_total). Override has no effect — VisitCostingService::compute() will overwrite it.',
        ],
        'discount_total' => [
            'label' => 'Discount Total',
            'helper' => 'Visit-level discount applied to the bill. Subtracted from profit and remaining balance.',
        ],
        'items_cost_total' => [
            'label' => 'Items Cost Total',
        ],
        'items_price_total' => [
            'label' => 'Items Price Total',
        ],
        'packages_price_total' => [
            'label' => 'Packages Price Total',
        ],
        'profit_total' => [
            'label' => 'Profit Total',
        ],
        'computed_at' => [
            'label' => 'Computed At',
        ],
        'computed_version' => [
            'label' => 'Computed Version',
        ],
        'financial_helper_content' => 'Computed by VisitCostingService (audit snapshot).',
    ],

    'columns' => [
        'id' => '#',
        'checked_in' => 'Checked-in',
        'service_started' => 'Service Started',
        'patient' => 'Patient',
        'doctor' => 'Doctor',
        'branch' => 'Branch',
        'room' => 'Room',
        'queued' => 'Queued',
        'accepted' => 'Accepted',
        'accepted_by' => 'Accepted By',
        'code' => 'Code',
        'source' => 'Source',
        'fees' => 'Fees',
        'profit' => 'Profit',
        'computed' => 'Computed',
        'version' => 'Ver',
    ],

    'filters' => [
        'doctor' => 'Doctor',
        'clinic_branch' => 'Clinic Branch',
        'accepted_question' => 'Accepted?',
    ],

    'actions' => [
        'mark_service_started' => 'Mark Service Started',
        'sync_follow_up_plan' => 'Sync Follow-up Plan',
        'recompute_financials' => 'Recompute Financials',
        'open_visit' => 'Open Visit',
    ],

    'notifications' => [
        'service_start_captured' => 'Service start time captured',
        'completion_time_captured' => 'Completion time captured',
        'auto_capture_failed' => 'Failed to auto-capture visit timestamps',
        'financial_snapshot_computed' => 'Financial snapshot computed',
        'financial_snapshot_failed' => 'Failed to compute financial snapshot',
        'not_allowed' => 'Not allowed',
        'service_marked_started' => 'Service marked as started',
        'follow_up_synced' => 'Follow-up plan synced',
        'follow_up_sync_failed' => 'Failed to sync follow-up plan',
        'snapshot_recomputed' => 'Financial snapshot recomputed',
        'snapshot_recompute_failed' => 'Failed to recompute snapshot',
        'check_logs' => 'Please check logs and try again.',
    ],

    'options' => [
        // Visit status options used in form Select and Filter
        // Keep keys as DB enums; only values translated
        'status' => [
            'created' => 'Created',
            'awaiting_doctor' => 'Awaiting Doctor',
            'awaiting_stock' => 'Awaiting Stock',
            'in_progress' => 'In Progress',
            'awaiting_payment' => 'Awaiting Payment',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No-show',
        ],
    ],
];
