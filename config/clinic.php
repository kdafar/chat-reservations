<?php

return [
    'visit_financials_enabled' => env('CLINIC_VISIT_FINANCIALS_ENABLED', false),
    'follow_up_enabled' => true,
    'follow_up_auto_create_booking_default' => false,
    'follow_up_booking_status' => 'draft',
    'follow_up_only_on_completed' => false,
    'visit_status_auto_timestamps_enabled' => (bool) env('CLINIC_VISIT_STATUS_AUTO_TIMESTAMPS_ENABLED', false),
    'inventory_enabled' => (bool) env('CLINIC_INVENTORY_ENABLED', false),
    'inventory_enforce_on_finalize' => (bool) env('CLINIC_INVENTORY_ENFORCE_ON_FINALIZE', false),
    'low_stock_alerts_enabled' => (bool) env('CLINIC_LOW_STOCK_ALERTS_ENABLED', false),
    'checkin_window_before_minutes' => 360, // 6 hours
    'checkin_window_after_minutes' => 240,  // 4 hours
];
