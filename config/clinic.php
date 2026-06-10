<?php

return [
    // Public customer portal (front-office login/register + /my/* account area).
    // This is leftover from the original food-ordering skeleton; the clinic only
    // needs the staff admin login at /admin/login. Default OFF so /login,
    // /register and the customer account pages are not registered. Guests hitting
    // any admin/v2 page are redirected to the Filament admin login instead
    // (see bootstrap/app.php redirectGuestsTo). Flip on only if a real public
    // patient portal is reintroduced.
    'customer_portal_enabled' => (bool) env('CLINIC_CUSTOMER_PORTAL_ENABLED', false),

    // Operating hours, shown in the v2 topbar clock as an "open / closing soon /
    // closed" indicator so reception/cashiers don't forget the daily closing.
    // 24h HH:MM, clinic-local (config('app.timezone')). Override per deployment.
    'hours' => [
        'open' => env('CLINIC_OPEN', '09:00'),
        'close' => env('CLINIC_CLOSE', '21:00'),
    ],

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

    // Insurance auto-claim: when a visit transitions to 'completed' and the
    // patient has an active policy, the VisitObserver drafts an InsuranceClaim
    // (idempotent — won't duplicate if one already exists, won't fire if
    // reception explicitly skipped via insurance_claim_skipped_at). Disable
    // in tests or environments without a fully-seeded insurance module.
    'insurance_auto_claim_on_complete' => (bool) env('CLINIC_INSURANCE_AUTO_CLAIM_ON_COMPLETE', true),

    // WhatsApp OTP gate on the public booking endpoint. Disabled by default
    // because the template (clinic_booking_otp_v1) must be approved in Meta
    // Business Manager first. When enabled, /api/bookings rejects requests
    // without a verified OTP for the same msisdn. Bookings from source='wa'
    // skip the check — they're already verified by virtue of being on WA.
    // CLINIC_OTP_DEV_LOG=true makes OtpService log the plain code to
    // laravel.log so the flow can be tested without a real WA send.
    'booking_otp_enabled' => (bool) env('CLINIC_BOOKING_OTP_ENABLED', false),
    'booking_otp_template' => env('CLINIC_BOOKING_OTP_TEMPLATE', 'clinic_booking_otp_v1'),
    'booking_otp_template_lang' => env('CLINIC_BOOKING_OTP_TEMPLATE_LANG', 'en'),

    // Follow-up plans. When a doctor sets a follow-up date we auto-book the first
    // free slot that day (Phase 7). Status is 'pending' so the slot is actually
    // held — the availability grid only treats confirmed/pending as blocking, so
    // a 'draft' booking would NOT reserve the slot and could be double-booked.
    'follow_up_enabled' => true,
    'follow_up_auto_create_booking_default' => true,
    'follow_up_booking_status' => 'pending',
    'follow_up_only_on_completed' => false,

    // Check-in window relative to res_start.
    'checkin_window_before_minutes' => 360, // 6 hours
    'checkin_window_after_minutes' => 240,  // 4 hours

    // WhatsApp appointment reminders.
    //   enabled:   master switch — keep false in dev/staging to avoid Meta bills
    //   lead_hours: how far ahead of the appointment to send (24 = "tomorrow")
    //   template:  WhatsApp template name (must be approved in Business Manager)
    //   template_lang: language code matching the approved template
    //   dry_run:   true = log "would send" without calling the API
    'reminders' => [
        'enabled' => (bool) env('CLINIC_REMINDERS_ENABLED', false),
        'lead_hours' => (int) env('CLINIC_REMINDERS_LEAD_HOURS', 24),
        'template' => env('CLINIC_REMINDERS_TEMPLATE', 'appointment_reminder_v1'),
        'template_lang' => env('CLINIC_REMINDERS_TEMPLATE_LANG', 'en'),
        'dry_run' => (bool) env('CLINIC_REMINDERS_DRY_RUN', false),
    ],
];
