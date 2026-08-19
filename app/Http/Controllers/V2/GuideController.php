<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * System Guide — a presentation-style "what does each menu link do, and who is
 * allowed to use it" overview of the whole v2 admin.
 *
 * It is READ-ONLY and visible to every authenticated staff member. The page is
 * "personalised": ordinary staff only ever see the links they can actually open —
 * listing the whole system to a receptionist reads as a menu of things that are
 * broken or hidden from them. Admins / clinic managers keep the full catalogue
 * plus the per-role filters, because they are the ones who onboard and demo.
 *
 * The link list + access gates here MIRROR the sidebar in
 * resources/js/v2/Layouts/AppLayout.vue (navSections + navGates). When you add a
 * sidebar link, add it here too. Easy-language blurbs live in
 * resources/lang/{en,ar}/guide.php keyed by the nav-item id.
 */
class GuideController extends Controller
{
    /** Staff roles shown as columns/chips on the guide (admin = full access, noted separately). */
    private const DISPLAY_ROLES = [
        'clinic_admin'     => ['en' => 'Manager',    'ar' => 'المدير'],
        'clinic_doctor'    => ['en' => 'Doctor',     'ar' => 'الطبيب'],
        'clinic_reception' => ['en' => 'Reception',  'ar' => 'الاستقبال'],
        'clinic_nurse'     => ['en' => 'Nurse',      'ar' => 'الممرض/ة'],
        'accountant'       => ['en' => 'Accountant', 'ar' => 'المحاسب'],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Only these roles get the whole-system catalogue + the per-role filters.
        // Everyone else sees strictly their own surface.
        $canSeeAll = $user && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['admin', 'super_admin', 'clinic_admin']);

        // Pre-load each display role once so we don't hit the DB per link.
        $roles = [];
        foreach (array_keys(self::DISPLAY_ROLES) as $name) {
            $roles[$name] = Role::where('name', $name)->first();
        }

        // Easy-language blurbs, both locales, so the page can switch without a reload.
        $descEn = (array) trans('guide.items', [], 'en');
        $descAr = (array) trans('guide.items', [], 'ar');

        // "What it does" + "How to use it" reuse the existing help library content.
        $helpEn = (array) trans('help_v2.pages', [], 'en');
        $helpAr = (array) trans('help_v2.pages', [], 'ar');

        $sections = [];
        foreach ($this->structure() as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                $gate = $item['gate'] ?? [];
                $mine = $this->userPasses($gate, $user);

                // Ordinary staff: drop anything they cannot open, so the guide is a
                // list of what they CAN do rather than a wall of locked doors.
                if (! $canSeeAll && ! $mine) {
                    continue;
                }

                $items[] = [
                    'id'        => $item['id'],
                    'icon'      => $item['icon'],
                    'label_en'  => $item['label_en'],
                    'label_ar'  => $item['label_ar'],
                    'href'      => $item['href'] ?? null,
                    'desc_en'   => $descEn[$item['id']] ?? '',
                    'desc_ar'   => $descAr[$item['id']] ?? '',
                    // "What it does" body + "How to use it" steps, both locales.
                    'what_en'   => $helpEn[$item['id']]['what']['body'] ?? '',
                    'what_ar'   => $helpAr[$item['id']]['what']['body'] ?? '',
                    'how_en'    => array_values($helpEn[$item['id']]['how']['items'] ?? []),
                    'how_ar'    => array_values($helpAr[$item['id']]['how']['items'] ?? []),
                    // Real product screenshots per locale; null falls back to an icon tile.
                    'shot_en'   => is_file(public_path("guide-shots/en/{$item['id']}.jpg")) ? "/guide-shots/en/{$item['id']}.jpg" : null,
                    'shot_ar'   => is_file(public_path("guide-shots/ar/{$item['id']}.jpg")) ? "/guide-shots/ar/{$item['id']}.jpg" : null,
                    // Roles (by key) that can open this link.
                    'roles'     => $this->rolesForGate($gate, $roles),
                    // Does the CURRENT user have access? (personalisation)
                    'mine'      => $mine,
                ];
            }

            // A section with nothing in it for this user is not worth a heading.
            if (! $items) {
                continue;
            }

            $sections[] = [
                'id'       => $section['id'],
                'icon'     => $section['icon'],
                'label_en' => $section['label_en'],
                'label_ar' => $section['label_ar'],
                'items'    => $items,
            ];
        }

        $roleMeta = [];
        foreach (self::DISPLAY_ROLES as $key => $names) {
            $roleMeta[] = ['key' => $key, 'en' => $names['en'], 'ar' => $names['ar']];
        }

        return Inertia::render('Guide/Index', [
            'sections'    => $sections,
            'roles'       => $canSeeAll ? $roleMeta : [],
            'my_roles'    => $user && method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [],
            'can_see_all' => $canSeeAll,
        ]);
    }

    /** Which display roles satisfy a gate. */
    private function rolesForGate(array $gate, array $roles): array
    {
        $out = [];
        foreach ($roles as $name => $role) {
            if ($role && $this->roledPasses($gate, $name, $role)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /** Evaluate a sidebar gate for a specific role (mirrors AppLayout.itemVisible). */
    private function roledPasses(array $gate, string $roleName, Role $role): bool
    {
        if (! empty($gate['perm'])) {
            try {
                if ($role->hasPermissionTo($gate['perm'])) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Permission may not exist in this install — treat as no-access.
            }
        }

        if (! empty($gate['roles']) && in_array($roleName, $gate['roles'], true)) {
            return true;
        }

        if (! empty($gate['flags'])) {
            foreach ($gate['flags'] as $flag) {
                if ($this->roleHasFlag($roleName, $flag)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Map the identity flags used by the sidebar onto roles (see HandleInertiaRequests). */
    private function roleHasFlag(string $roleName, string $flag): bool
    {
        return match ($flag) {
            'is_admin'     => in_array($roleName, ['clinic_admin'], true),
            'is_reception' => in_array($roleName, ['clinic_reception', 'clinic_admin'], true),
            'is_doctor'    => $roleName === 'clinic_doctor',
            'is_nurse'     => $roleName === 'clinic_nurse',
            default        => false,
        };
    }

    /** Evaluate a gate for the live user (admin/super_admin always pass). */
    private function userPasses(array $gate, $user): bool
    {
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            return true;
        }
        if (! empty($gate['perm']) && $user->can($gate['perm'])) {
            return true;
        }
        if (! empty($gate['roles']) && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($gate['roles'])) {
            return true;
        }
        if (! empty($gate['flags'])) {
            foreach ($gate['flags'] as $flag) {
                if ($this->userHasFlag($flag, $user)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function userHasFlag(string $flag, $user): bool
    {
        return match ($flag) {
            'is_admin'     => $user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('clinic_admin'),
            'is_reception' => $user->hasRole('clinic_reception') || $user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('clinic_admin'),
            'is_doctor'    => \App\Models\Doctor::where('user_id', $user->id)->exists(),
            'is_nurse'     => $user->hasRole('clinic_nurse'),
            default        => false,
        };
    }

    /**
     * Canonical sidebar structure mirroring AppLayout.vue navSections + navGates.
     * Keep in sync when sidebar links change.
     */
    private function structure(): array
    {
        return [
            ['id' => 'operations', 'icon' => 'gauge', 'label_en' => 'Operations', 'label_ar' => 'العمليات', 'items' => [
                ['id' => 'dashboard',       'icon' => 'gauge',          'label_en' => 'Dashboard',       'label_ar' => 'لوحة التحكم',   'href' => '/admin/v2/dashboard',          'gate' => ['perm' => 'view_clinic_reports']],
                ['id' => 'waiting',         'icon' => 'users-round',    'label_en' => 'Waiting',         'label_ar' => 'قائمة الانتظار', 'href' => '/admin/v2/waiting-patients',   'gate' => ['flags' => ['is_admin', 'is_reception', 'is_doctor', 'is_nurse']]],
                ['id' => 'checkin',         'icon' => 'log-in',         'label_en' => 'Check-in',        'label_ar' => 'تسجيل الدخول',  'href' => '/admin/v2/checkin',            'gate' => ['flags' => ['is_admin', 'is_reception']]],
                ['id' => 'bookings',        'icon' => 'calendar-days',  'label_en' => 'Bookings',        'label_ar' => 'الحجوزات',      'href' => '/admin/v2/bookings',           'gate' => ['flags' => ['is_admin', 'is_reception']]],
                ['id' => 'visits',          'icon' => 'clipboard-list', 'label_en' => 'Visits',          'label_ar' => 'الزيارات',      'href' => '/admin/v2/visits-list',        'gate' => ['perm' => 'view_any_visits']],
                ['id' => 'doctor-schedule', 'icon' => 'calendar-clock', 'label_en' => 'Doctor Schedule', 'label_ar' => 'جدول الأطباء',  'href' => '/admin/v2/doctor-schedule',    'gate' => ['perm' => 'view_doctor_schedule', 'flags' => ['is_doctor']]],
                ['id' => 'my-earnings',     'icon' => 'coins',          'label_en' => 'My Earnings',     'label_ar' => 'أرباحي اليومية', 'href' => '/admin/v2/my-earnings',        'gate' => ['flags' => ['is_doctor']]],
            ]],
            ['id' => 'patients', 'icon' => 'user-round', 'label_en' => 'Patients', 'label_ar' => 'المرضى', 'items' => [
                ['id' => 'patients',        'icon' => 'user-round', 'label_en' => 'Patients',        'label_ar' => 'المرضى',       'href' => '/admin/v2/patients',         'gate' => ['perm' => 'view_any_patients']],
                ['id' => 'patient-files',   'icon' => 'folder',     'label_en' => 'Patient files',   'label_ar' => 'ملفات المرضى',  'href' => '/admin/v2/patient-files',    'gate' => ['perm' => 'patient_files_view']],
                ['id' => 'follow-up-plans', 'icon' => 'rotate-ccw', 'label_en' => 'Follow-up Plans', 'label_ar' => 'خطط المتابعة',  'href' => '/admin/v2/follow-up-plans',  'gate' => ['perm' => 'view_any_follow_up_plans']],
            ]],
            ['id' => 'inpatient', 'icon' => 'bed-double', 'label_en' => 'Inpatient', 'label_ar' => 'القسم الداخلي', 'items' => [
                ['id' => 'inpatient-board',      'icon' => 'bed-double',  'label_en' => 'Bed Board',         'label_ar' => 'لوحة الأسرّة',  'href' => '/admin/v2/inpatient/board',      'gate' => ['flags' => ['is_admin', 'is_reception', 'is_doctor', 'is_nurse']]],
                ['id' => 'inpatient-admissions', 'icon' => 'list-checks', 'label_en' => 'Admissions',        'label_ar' => 'الإدخالات',     'href' => '/admin/v2/inpatient/admissions', 'gate' => ['flags' => ['is_admin', 'is_reception', 'is_doctor', 'is_nurse']]],
                ['id' => 'inpatient-wards',      'icon' => 'door-open',   'label_en' => 'Wards',             'label_ar' => 'الأقسام',       'href' => '/admin/v2/inpatient/wards',      'gate' => ['perm' => 'view_any_wards']],
                ['id' => 'inpatient-beds',       'icon' => 'bed',         'label_en' => 'Beds',              'label_ar' => 'الأسرّة',       'href' => '/admin/v2/inpatient/beds',       'gate' => ['perm' => 'view_any_beds']],
                ['id' => 'inpatient-reports',    'icon' => 'bar-chart-3', 'label_en' => 'Inpatient Reports', 'label_ar' => 'تقارير القسم',  'href' => '/admin/v2/inpatient/reports',    'gate' => ['roles' => ['admin', 'super_admin', 'clinic_admin', 'accountant'], 'perm' => 'view_any_admissions']],
            ]],
            ['id' => 'insurance', 'icon' => 'shield', 'label_en' => 'Insurance', 'label_ar' => 'التأمين', 'items' => [
                ['id' => 'insurance-insurers', 'icon' => 'shield',         'label_en' => 'Insurers',          'label_ar' => 'شركات التأمين',      'href' => '/admin/v2/insurance/insurers',          'gate' => ['perm' => 'view_any_insurers']],
                ['id' => 'insurance-plans',    'icon' => 'list',           'label_en' => 'Plans',             'label_ar' => 'الخطط',              'href' => '/admin/v2/insurance/plans',             'gate' => ['perm' => 'view_any_insurance_plans']],
                ['id' => 'insurance-policies', 'icon' => 'badge-check',    'label_en' => 'Policies',          'label_ar' => 'البوالص',            'href' => '/admin/v2/insurance/policies',          'gate' => ['perm' => 'view_any_patient_insurance_policies']],
                ['id' => 'insurance-preauth',  'icon' => 'document-check', 'label_en' => 'Pre-authorizations','label_ar' => 'الموافقات المسبقة',  'href' => '/admin/v2/insurance/preauthorizations', 'gate' => ['perm' => 'view_any_insurance_preauthorizations']],
                ['id' => 'insurance-claims',   'icon' => 'file-text',      'label_en' => 'Claims',            'label_ar' => 'المطالبات',          'href' => '/admin/v2/insurance/claims',            'gate' => ['perm' => 'view_any_insurance_claims']],
                ['id' => 'insurance-followup', 'icon' => 'bell',           'label_en' => 'Follow-up',         'label_ar' => 'متابعة التحصيل',     'href' => '/admin/v2/insurance/follow-up',         'gate' => ['perm' => 'view_any_insurance_claims']],
            ]],
            ['id' => 'lab', 'icon' => 'beaker', 'label_en' => 'Laboratory', 'label_ar' => 'المختبر', 'items' => [
                ['id' => 'lab-orders', 'icon' => 'flask-conical', 'label_en' => 'Lab Worklist', 'label_ar' => 'قائمة عمل المختبر', 'href' => '/admin/v2/lab-orders', 'gate' => ['perm' => 'view_any_lab_orders']],
                ['id' => 'lab-tests', 'icon' => 'beaker', 'label_en' => 'Lab Tests', 'label_ar' => 'كتالوج الاختبارات', 'href' => '/admin/v2/lab-tests', 'gate' => ['perm' => 'view_any_lab_tests']],
            ]],
            ['id' => 'pharmacy', 'icon' => 'pill', 'label_en' => 'Pharmacy & Stock', 'label_ar' => 'الصيدلية والمخزون', 'items' => [
                ['id' => 'clinic-items',    'icon' => 'pill',             'label_en' => 'Items',           'label_ar' => 'الأصناف',       'href' => '/admin/v2/clinic-items',         'gate' => ['perm' => 'view_any_clinic_items']],
                ['id' => 'clinic-stock',    'icon' => 'package',          'label_en' => 'Stock',           'label_ar' => 'المخزون',       'href' => '/admin/v2/clinic-stock',         'gate' => ['perm' => 'view_any_clinic_item_stocks']],
                ['id' => 'stock-movements', 'icon' => 'truck',            'label_en' => 'Movements',       'label_ar' => 'حركة المخزون',  'href' => '/admin/v2/stock-movements',      'gate' => ['perm' => 'view_any_clinic_stock_movement']],
                ['id' => 'stock-requests',  'icon' => 'inbox',            'label_en' => 'Stock Requests',  'label_ar' => 'طلبات الصرف',   'href' => '/admin/v2/visit-stock-requests', 'gate' => ['perm' => 'view_any_visit_stock_request']],
                ['id' => 'stock-transfers', 'icon' => 'arrow-left-right', 'label_en' => 'Stock Transfers', 'label_ar' => 'تحويلات المخزون','href' => '/admin/v2/stock-transfers',      'gate' => ['perm' => 'view_any_stock_transfers']],
                ['id' => 'purchase-orders', 'icon' => 'shopping-cart',    'label_en' => 'Purchase Orders', 'label_ar' => 'أوامر الشراء',  'href' => '/admin/v2/purchase-orders',      'gate' => ['perm' => 'view_any_purchase_orders']],
                ['id' => 'clinic-packages', 'icon' => 'gift',             'label_en' => 'Packages',        'label_ar' => 'الباقات',       'href' => '/admin/v2/clinic-packages',      'gate' => ['perm' => 'view_any_clinic_packages']],
            ]],
            ['id' => 'discounts', 'icon' => 'badge-percent', 'label_en' => 'Discounts & Promotions', 'label_ar' => 'الخصومات والعروض', 'items' => [
                ['id' => 'coupons',    'icon' => 'ticket',        'label_en' => 'Coupons',    'label_ar' => 'كوبونات الخصم',   'href' => '/admin/v2/coupons',    'gate' => ['perm' => 'view_any_coupons']],
                ['id' => 'promotions', 'icon' => 'badge-percent', 'label_en' => 'Promotions', 'label_ar' => 'العروض الترويجية', 'href' => '/admin/v2/promotions', 'gate' => ['perm' => 'view_any_promotions']],
            ]],
            ['id' => 'hr', 'icon' => 'users', 'label_en' => 'HR', 'label_ar' => 'الموارد البشرية', 'items' => [
                ['id' => 'leaves',          'icon' => 'calendar-x',  'label_en' => 'Leaves',         'label_ar' => 'الإجازات',        'href' => '/admin/v2/staff-leaves',                   'gate' => ['perm' => 'view_any_staff_leaves']],
                ['id' => 'attendance',      'icon' => 'clock',       'label_en' => 'Attendance',     'label_ar' => 'الحضور',          'href' => '/admin/v2/staff-attendances',              'gate' => ['perm' => 'view_any_staff_attendances']],
                ['id' => 'doctors',         'icon' => 'stethoscope', 'label_en' => 'Doctors',        'label_ar' => 'الأطباء',         'href' => '/admin/v2/doctors',                        'gate' => ['perm' => 'view_any_doctors']],
                ['id' => 'users',           'icon' => 'users',       'label_en' => 'Users',          'label_ar' => 'المستخدمون',      'href' => '/admin/v2/users',                          'gate' => ['perm' => 'view_any_user']],
                ['id' => 'doctor-comp',     'icon' => 'wallet',      'label_en' => 'Comp. Profiles', 'label_ar' => 'إعدادات العمولات', 'href' => '/admin/v2/doctor-compensation-profiles',  'gate' => ['perm' => 'view_any_doctor_compensation_profiles']],
                ['id' => 'doctor-earnings', 'icon' => 'coins',       'label_en' => 'Doctor Earnings','label_ar' => 'أرباح الأطباء',   'href' => '/admin/v2/doctor-compensation',            'gate' => ['perm' => 'view_any_doctor_compensation_ledgers']],
            ]],
            ['id' => 'payroll', 'icon' => 'banknote', 'label_en' => 'Payroll', 'label_ar' => 'الرواتب', 'items' => [
                ['id' => 'payroll-runs',    'icon' => 'calendar-check', 'label_en' => 'Payroll Runs',    'label_ar' => 'مسيّر الرواتب',        'href' => '/admin/v2/payroll-runs',                  'gate' => ['perm' => 'view_any_payroll_runs']],
                ['id' => 'salary-profiles', 'icon' => 'wallet',         'label_en' => 'Salary Profiles', 'label_ar' => 'هياكل الرواتب',        'href' => '/admin/v2/staff-compensation-profiles',   'gate' => ['perm' => 'view_any_staff_compensation_profiles']],
                ['id' => 'staff-loans',     'icon' => 'hand-coins',     'label_en' => 'Loans & Advances','label_ar' => 'السلف والقروض',        'href' => '/admin/v2/staff-loans',                   'gate' => ['perm' => 'view_any_staff_loans']],
                ['id' => 'leave-balances',  'icon' => 'calendar-clock', 'label_en' => 'Leave Balances',  'label_ar' => 'أرصدة الإجازات',       'href' => '/admin/v2/leave-balances',                'gate' => ['roles' => ['admin', 'super_admin', 'clinic_admin', 'accountant'], 'perm' => 'view_any_staff_leave_entitlements']],
                ['id' => 'settlements',     'icon' => 'log-out',        'label_en' => 'End of Service',  'label_ar' => 'مكافأة نهاية الخدمة',  'href' => '/admin/v2/staff-settlements',             'gate' => ['perm' => 'view_any_staff_settlements']],
            ]],
            ['id' => 'accounting', 'icon' => 'book', 'label_en' => 'Accounting', 'label_ar' => 'المحاسبة', 'items' => [
                ['id' => 'accounts',          'icon' => 'book',         'label_en' => 'Chart of Accounts',     'label_ar' => 'دليل الحسابات',          'href' => '/admin/v2/accounting/chart-of-accounts',          'gate' => ['perm' => 'view_any_accounting_accounts']],
                ['id' => 'posting-accounts',  'icon' => 'settings',     'label_en' => 'Auto-Posting Accounts', 'label_ar' => 'حسابات الترحيل التلقائي', 'href' => '/admin/v2/accounting/posting-accounts',           'gate' => ['perm' => 'view_any_accounting_accounts']],
                ['id' => 'fixed-assets',      'icon' => 'package',      'label_en' => 'Fixed Assets',          'label_ar' => 'الأصول الثابتة',         'href' => '/admin/v2/accounting/fixed-assets',               'gate' => ['perm' => 'view_any_accounting_accounts']],
                ['id' => 'prepaid-schedules', 'icon' => 'calendar',     'label_en' => 'Prepaid Expenses',      'label_ar' => 'المصاريف المقدمة',       'href' => '/admin/v2/accounting/prepaid-schedules',          'gate' => ['perm' => 'view_any_accounting_accounts']],
                ['id' => 'general-ledger',    'icon' => 'book-open',    'label_en' => 'General Ledger',        'label_ar' => 'دفتر الأستاذ',           'href' => '/admin/v2/reports/accounting/general-ledger',     'gate' => ['perm' => 'view_accounting_general_ledger']],
                ['id' => 'journal-entries',   'icon' => 'book-open',    'label_en' => 'Journal Entries',       'label_ar' => 'القيود اليومية',         'href' => '/admin/v2/accounting/journal-entries',            'gate' => ['perm' => 'view_any_accounting_journal_entries']],
                ['id' => 'expenses',          'icon' => 'minus-circle', 'label_en' => 'Expenses',              'label_ar' => 'المصروفات',              'href' => '/admin/v2/accounting/expenses',                   'gate' => ['perm' => 'view_any_accounting_expenses']],
                ['id' => 'vendors',           'icon' => 'building-2',    'label_en' => 'Vendors',               'label_ar' => 'الموردون',               'href' => '/admin/v2/accounting/vendors',                    'gate' => ['perm' => 'view_any_accounting_vendors']],
                ['id' => 'reconciliation',    'icon' => 'check-circle', 'label_en' => 'Bank Reconciliation',   'label_ar' => 'التسوية المصرفية',       'href' => '/admin/v2/accounting/bank-reconciliations',       'gate' => ['perm' => 'view_any_accounting_bank_reconciliations']],
                ['id' => 'periods',           'icon' => 'lock',         'label_en' => 'Periods',               'label_ar' => 'الفترات المحاسبية',      'href' => '/admin/v2/accounting/periods',                    'gate' => ['perm' => 'view_any_accounting_periods']],
                ['id' => 'trial-balance',     'icon' => 'scale',        'label_en' => 'Trial Balance',         'label_ar' => 'ميزان المراجعة',         'href' => '/admin/v2/reports/accounting/trial-balance',      'gate' => ['perm' => 'view_accounting_trial_balance']],
                ['id' => 'profit-loss',       'icon' => 'trending-up',  'label_en' => 'Profit & Loss',         'label_ar' => 'قائمة الدخل',            'href' => '/admin/v2/reports/accounting/profit-loss',        'gate' => ['perm' => 'view_accounting_profit_and_loss']],
                ['id' => 'balance-sheet',     'icon' => 'scale',        'label_en' => 'Balance Sheet',         'label_ar' => 'الميزانية العمومية',     'href' => '/admin/v2/reports/accounting/balance-sheet',      'gate' => ['perm' => 'view_accounting_balance_sheet']],
                ['id' => 'cash-flow',         'icon' => 'banknote',     'label_en' => 'Cash Flow',             'label_ar' => 'التدفقات النقدية',       'href' => '/admin/v2/reports/accounting/cash-flow',          'gate' => ['perm' => 'view_accounting_cash_flow']],
                ['id' => 'aging',             'icon' => 'clock',        'label_en' => 'AR / AP Aging',         'label_ar' => 'أعمار الذمم',            'href' => '/admin/v2/reports/accounting/aging',              'gate' => ['perm' => 'view_accounting_general_ledger']],
            ]],
            ['id' => 'reports', 'icon' => 'bar-chart-3', 'label_en' => 'Reports', 'label_ar' => 'التقارير', 'items' => [
                ['id' => 'reports',               'icon' => 'bar-chart-3', 'label_en' => 'Clinic Reports',        'label_ar' => 'تقارير العيادة',  'href' => '/admin/v2/reports',                      'gate' => ['perm' => 'view_clinic_reports']],
                ['id' => 'daily-closing',         'icon' => 'file-check',  'label_en' => 'Daily Closing',         'label_ar' => 'الإقفال اليومي',  'href' => '/admin/v2/reports/daily-closing',        'gate' => ['perm' => 'view_clinic_closing_reports']],
                ['id' => 'daily-reconciliation',  'icon' => 'banknote',    'label_en' => 'Daily Reconciliation',  'label_ar' => 'التسوية اليومية', 'href' => '/admin/v2/reports/daily-reconciliation', 'gate' => ['perm' => 'view_daily_reconciliation']],
                ['id' => 'executive',             'icon' => 'trending-up', 'label_en' => 'Executive',             'label_ar' => 'لوحة المدير',     'href' => '/admin/v2/reports/executive',            'gate' => ['perm' => 'view_executive-dashboard']],
            ]],
            ['id' => 'platform', 'icon' => 'settings', 'label_en' => 'Platform', 'label_ar' => 'المنصة', 'items' => [
                ['id' => 'clinics',  'icon' => 'building-2',  'label_en' => 'Clinics',             'label_ar' => 'العيادات',         'href' => '/admin/v2/partners',         'gate' => ['perm' => 'view_any_partner']],
                ['id' => 'branches', 'icon' => 'map-pin',     'label_en' => 'Branches',            'label_ar' => 'الفروع',           'href' => '/admin/v2/branches',         'gate' => ['perm' => 'view_any_branch']],
                ['id' => 'gateways', 'icon' => 'credit-card', 'label_en' => 'Gateway Accounts',    'label_ar' => 'حسابات الدفع',     'href' => '/admin/v2/gateway-accounts', 'gate' => ['perm' => 'view_any_gateway_account']],
                ['id' => 'roles',    'icon' => 'shield',      'label_en' => 'Roles & Permissions', 'label_ar' => 'الأدوار والصلاحيات', 'href' => '/admin/v2/roles',            'gate' => ['perm' => 'roles.view-any']],
                ['id' => 'settings', 'icon' => 'settings',    'label_en' => 'System Settings',     'label_ar' => 'إعدادات النظام',   'href' => '/admin/v2/settings',         'gate' => ['perm' => 'view_any_system_setting']],
                ['id' => 'activity', 'icon' => 'history',     'label_en' => 'Activity Log',        'label_ar' => 'سجل النشاط',       'href' => '/admin/v2/activity-log',     'gate' => ['perm' => 'view_any_activity_log']],
            ]],
            ['id' => 'whatsapp', 'icon' => 'message-circle', 'label_en' => 'WhatsApp', 'label_ar' => 'واتساب', 'items' => [
                ['id' => 'wa-triggers',  'icon' => 'zap',            'label_en' => 'Triggers',        'label_ar' => 'المحفّزات',       'href' => '/admin/v2/whatsapp/triggers',          'gate' => ['perm' => 'view_any_whatsapp_trigger']],
                ['id' => 'wa-campaigns', 'icon' => 'send',           'label_en' => 'Campaigns',       'label_ar' => 'الحملات',         'href' => '/admin/v2/campaigns',                  'gate' => ['perm' => 'view_any_bulk_invite_campaigns']],
                ['id' => 'wa-commands',  'icon' => 'terminal',       'label_en' => 'Commands',        'label_ar' => 'الأوامر',         'href' => '/admin/v2/whatsapp/commands',          'gate' => ['perm' => 'view_any_wa_commands']],
                ['id' => 'wa-messages',  'icon' => 'message-square', 'label_en' => 'Templates',       'label_ar' => 'القوالب',         'href' => '/admin/v2/whatsapp/messages',          'gate' => ['perm' => 'view_any_whatsapp_message']],
                ['id' => 'wa-texts',     'icon' => 'book-open',      'label_en' => 'Message Catalog', 'label_ar' => 'كتالوج الرسائل',  'href' => '/admin/v2/whatsapp/message-texts',     'gate' => ['perm' => 'view_any_message_text']],
                ['id' => 'wa-logs',      'icon' => 'inbox',          'label_en' => 'Logs',            'label_ar' => 'السجل',           'href' => '/admin/v2/whatsapp/logs',              'gate' => ['perm' => 'view_any_wa_message_logs']],
                ['id' => 'wa-sessions',  'icon' => 'message-circle', 'label_en' => 'Sessions',        'label_ar' => 'الجلسات',         'href' => '/admin/v2/whatsapp/sessions',          'gate' => ['perm' => 'view_any_whatsapp_session']],
                ['id' => 'wa-audience',  'icon' => 'users-round',    'label_en' => 'Audience',        'label_ar' => 'مقاييس الجمهور',  'href' => '/admin/v2/whatsapp/audience-metrics',  'gate' => ['perm' => 'view_any_audience_metric']],
            ]],
            ['id' => 'wa-platform', 'icon' => 'message-circle', 'label_en' => 'WhatsApp Platform', 'label_ar' => 'منصة واتساب', 'items' => [
                ['id' => 'wap-dashboard', 'icon' => 'layout-dashboard', 'label_en' => 'Dashboard', 'label_ar' => 'اللوحة',        'href' => '/admin/v2/wa-module',           'gate' => ['perm' => 'view_wa_module']],
                ['id' => 'wap-inbox',     'icon' => 'inbox',            'label_en' => 'Inbox',     'label_ar' => 'صندوق الوارد',  'href' => '/admin/v2/wa-module/inbox',     'gate' => ['perm' => 'view_wa_module']],
                ['id' => 'wap-templates', 'icon' => 'message-square',   'label_en' => 'Templates', 'label_ar' => 'القوالب',       'href' => '/admin/v2/wa-module/templates', 'gate' => ['perm' => 'view_wa_module']],
                ['id' => 'wap-media',     'icon' => 'image',            'label_en' => 'Media',     'label_ar' => 'الوسائط',       'href' => '/admin/v2/wa-module/media',     'gate' => ['perm' => 'view_wa_module']],
                ['id' => 'wap-contacts',  'icon' => 'users-round',      'label_en' => 'Contacts',  'label_ar' => 'جهات الاتصال',  'href' => '/admin/v2/wa-module/contacts',  'gate' => ['perm' => 'view_wa_module']],
                ['id' => 'wap-campaigns', 'icon' => 'send',             'label_en' => 'Campaigns', 'label_ar' => 'الحملات',       'href' => '/admin/v2/wa-module/campaigns', 'gate' => ['perm' => 'view_wa_module']],
                ['id' => 'wap-points',    'icon' => 'coins',            'label_en' => 'Points',    'label_ar' => 'النقاط',        'href' => '/admin/v2/wa-module/points',    'gate' => ['perm' => 'view_wa_module']],
                ['id' => 'wap-logs',      'icon' => 'scroll-text',      'label_en' => 'Message Logs','label_ar' => 'سجل الرسائل',  'href' => '/admin/v2/wa-module/logs',      'gate' => ['perm' => 'view_wa_module']],
                ['id' => 'wap-sessions',  'icon' => 'message-circle',   'label_en' => 'Sessions',  'label_ar' => 'الجلسات',       'href' => '/admin/v2/wa-module/sessions',  'gate' => ['perm' => 'view_wa_module']],
                ['id' => 'wap-settings',  'icon' => 'settings',         'label_en' => 'Settings',  'label_ar' => 'الإعدادات',     'href' => '/admin/v2/wa-module/settings',  'gate' => ['perm' => 'view_wa_module']],
            ]],
        ];
    }
}
