<?php

return [
    // Visit financials: enable the VisitCostingService snapshot machinery.
    // After audit fix #1+#2 (profit formula + no fees overwrite), this is safe
    // to default on. Reports and the discharge balance gate depend on it.
    'visit_financials_enabled' => (bool) env('CLINIC_VISIT_FINANCIALS_ENABLED', true),

    // Doctor compensation: write DoctorCompensationLedger rows on completed visits.
    // Defaults on so payouts accrue from the start of operation.
    'doctor_comp_enabled' => (bool) env('CLINIC_DOCTOR_COMP_ENABLED', true),
    'doctor_comp_only_on_completed' => (bool) env('CLINIC_DOCTOR_COMP_ONLY_ON_COMPLETED', true),

    // Inventory + stock requests + packages (all default on so the full flow works
    // out-of-the-box; individual features can still be disabled per env).
    'inventory_enabled' => (bool) env('CLINIC_INVENTORY_ENABLED', true),
    'inventory_enforce_on_finalize' => (bool) env('CLINIC_INVENTORY_ENFORCE_ON_FINALIZE', false),
    'low_stock_alerts_enabled' => (bool) env('CLINIC_LOW_STOCK_ALERTS_ENABLED', false),
    'stock_requests_enabled' => (bool) env('CLINIC_STOCK_REQUESTS_ENABLED', true),
    'packages_enabled' => (bool) env('CLINIC_PACKAGES_ENABLED', true),

    // Visit status auto-capture (service_started_at, completed_at) wired through
    // VisitResource form's afterStateUpdated. Note: direct $visit->update() calls
    // still bypass this; see audit finding #16.
    'visit_status_auto_timestamps_enabled' => (bool) env('CLINIC_VISIT_STATUS_AUTO_TIMESTAMPS_ENABLED', false),

    // Follow-up plans.
    'follow_up_enabled' => true,
    'follow_up_auto_create_booking_default' => false,
    'follow_up_booking_status' => 'draft',
    'follow_up_only_on_completed' => false,

    // Check-in window relative to res_start.
    'checkin_window_before_minutes' => 360, // 6 hours
    'checkin_window_after_minutes' => 240,  // 4 hours
];
