// Nav-item ids that have "How to use this page" help content.
//
// This MUST stay in sync with HelpController::MAP on the server — it only lists
// the ids, so AppLayout can decide whether to show the Help button before
// firing a request. The actual bilingual content is fetched lazily from
// GET /admin/v2/api/help/{id} when the drawer opens.
export const HELP_PAGES = new Set([
    // Operations
    'dashboard', 'waiting', 'checkin', 'bookings', 'visits', 'visit-console', 'doctor-schedule',
    // Patients
    'patients', 'patient-files', 'follow-up-plans',
    // Inpatient
    'inpatient-board', 'inpatient-admissions', 'inpatient-wards', 'inpatient-beds', 'inpatient-reports',
    // Insurance
    'insurance-insurers', 'insurance-plans', 'insurance-policies', 'insurance-preauth', 'insurance-claims',
    // Laboratory
    'lab-tests',
    // Pharmacy & stock
    'clinic-items', 'clinic-stock', 'stock-movements', 'stock-requests', 'purchase-orders', 'clinic-packages',
    // HR
    'leaves', 'attendance', 'doctors', 'users', 'doctor-comp', 'doctor-earnings',
    // Accounting
    'accounts', 'journal-entries', 'expenses', 'vendors', 'reconciliation', 'periods',
    'trial-balance', 'general-ledger', 'profit-loss', 'balance-sheet', 'cash-flow',
    // Reports
    'reports', 'daily-closing', 'daily-reconciliation', 'executive',
    // Platform
    'clinics', 'branches', 'gateways', 'roles', 'settings', 'activity',
    // WhatsApp
    'wa-triggers', 'wa-campaigns', 'wa-commands', 'wa-messages', 'wa-texts', 'wa-logs', 'wa-sessions', 'wa-audience',
])

export function hasHelp(navId) {
    return HELP_PAGES.has(navId)
}
