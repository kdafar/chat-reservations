<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return [
    /**
     * Model Class => permission resource key
     * Keys become: view_any_{key}, view_{key}, create_{key}, update_{key}, delete_{key}, ...
     */
    'mapping' => [
        // Clinic layer
        \App\Models\Doctor::class => 'doctors',
        \App\Models\Patient::class => 'patients',
        \App\Models\Visit::class => 'visits',
        \App\Models\VisitItem::class => 'visit_items',
        \App\Models\ClinicItem::class => 'clinic_items',
        \App\Models\DoctorCompensationProfile::class => 'doctor_compensation_profiles',
        \App\Models\DoctorCompensationLedger::class => 'doctor_compensation_ledgers',
        \App\Models\Booking::class => 'bookings',
        \App\Models\Branch::class => 'branch',
        \App\Models\FollowUpPlan::class => 'follow_up_plans',
        \App\Models\BranchAvailabilityRule::class => 'branch_availability_rule',
        \App\Models\RestaurantTable::class => 'restaurant_table',
        \App\Models\ClinicStockMovement::class => 'clinic_stock_movement',
        \App\Models\ClinicItemStock::class => 'clinic_item _stock',
        \App\Models\User::class => 'user',
        \App\Models\ReservationTerm::class => 'reservation_term',
        \App\Models\Partner::class => 'partner',
        \App\Models\BranchBlackout::class => 'branch_blackout',
        \App\Models\WhatsappSession::class => 'whatsapp_session',
        \App\Models\WhatsappMessage::class => 'whatsapp_message',
        \App\Models\WhatsappTrigger::class => 'whatsapp_trigger',
        \App\Models\WhatsappFlowState::class => 'whatsapp_flow_state',
        \App\Models\WhatsappContact::class => 'whatsapp_contact',
        \App\Models\WAMessageLog::class => 'WAMessage_log',
        \App\Models\WAMessage::class => 'WAMessage',
        \App\Models\WACommand::class => 'WACommand',
        \App\Models\MessageText::class => 'message_text',
        \App\Models\AudienceMetric::class => 'audience_metric',
        \App\Models\SystemSetting::class => 'system_setting',
        \App\Models\BulkInviteCampaign::class => 'bulk_invite_Campaign',
        Role::class => 'roles',
        Permission::class => 'permissions',
        \App\Models\VisitStockRequest::class => 'visit_stock_request',
        \App\Models\GatewayAccount::class => 'gateway_account',
        \App\Models\ClinicPackage::class => 'clinic_packages',
    ],

    /**
     * Filament custom pages (view only)
     * Permission: view_{page_key}
     */
    'pages' => [
        'clinic_reports',
        'clinic_reporting_dashboard', // if you treat it as a page
        'clinic-dashboard',
        'executive-dashboard',
        'clinic-dashboard',
        'clinic_closing_reports',
        'check-in-scanner',
        'doctor-schedule',
        'whats-app-campaign-settings',
        'whats-app-rate-limit-settings',
        'waiting_patients',
        'nurse_station',
    ],
];
