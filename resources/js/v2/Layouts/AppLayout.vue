<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Icon from '../Components/Icon.vue'
import FlashToasts from '../Components/FlashToasts.vue'
import NotificationPoller from '../Components/NotificationPoller.vue'
import NotificationsPopover from '../Components/NotificationsPopover.vue'
import UserMenu from '../Components/UserMenu.vue'
import BranchBadge from '../Components/BranchBadge.vue'
import QuickCreate from '../Components/QuickCreate.vue'
import SnapshotChips from '../Components/SnapshotChips.vue'
import ClinicClock from '../Components/ClinicClock.vue'
import CommandPalette from '../Components/CommandPalette.vue'
import ConfirmDialog from '../Components/ConfirmDialog.vue'
import HelpDrawer from '../Components/HelpDrawer.vue'
import { hasHelp } from '../helpMap.js'
import { unreadCount } from '../Composables/useNotificationState.js'
import { confirmState, resolveConfirm, cancelConfirm } from '../Composables/useConfirm.js'

// `active` is an optional override; normally the active nav item is derived
// from the current URL (see resolvedActive) so this layout can be used as a
// PERSISTENT Inertia layout — it never re-mounts between page visits.
const props = defineProps({
    active: { type: String, default: null },
})

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const locale = computed(() => page.props.locale ?? 'en')
const appName = computed(() => page.props.app?.name ?? 'Clinic')
const appLogo = computed(() => page.props.app?.logo_url ?? '/favicon.svg')

// Server is expected to send `auth.user.roles` (array of role names) +
// `auth.user.is_doctor` (bool) via HandleInertiaRequests. Used to gate
// sidebar sections so a receptionist doesn't see Accounting / Activity Log.
const userRoles = computed(() => {
    const r = page.props.auth?.user?.roles
    if (Array.isArray(r)) return r
    if (typeof r === 'string') return [r]
    return []
})
function hasAnyRole(roles) {
    return roles.some(r => userRoles.value.includes(r))
}
// Used only for the "Leaves" vs "My Leaves" label wording below.
const isClinicAdmin = computed(() => hasAnyRole(['admin', 'super_admin', 'clinic_admin', 'branch_manager']))

// -----------------------------------------------------------------------------
// Access gating for the sidebar. Each nav item is hidden unless the user can
// actually open it — mirroring exactly what the v2 controller enforces, so we
// never show a link that 403s. Items NOT listed here are open to any admin-panel
// user (dashboard, waiting, patients, doctor-schedule).
//   { perm }  -> requires this Spatie permission   (controller: ->can(perm))
//   { roles } -> requires any of these roles        (controller: hasRole([...]))
//   { flags } -> requires any of these user flags   (is_admin/is_reception/is_doctor)
// `perm`, `roles` and `flags` on one entry are OR'd together.
// -----------------------------------------------------------------------------
const userPermissions = computed(() => {
    const p = page.props.auth?.user?.permissions
    return Array.isArray(p) ? p : []
})
// admin holds every permission (seeded); super_admin is all-access via Gate::before.
const allAccess = computed(() => hasAnyRole(['admin', 'super_admin']))
// A non-admin user with no branch/clinic membership sees empty lists everywhere
// (patients, branches, queue all scope to zero) — surface a clear notice rather
// than letting it look broken.
const noClinic = computed(() => !allAccess.value && (page.props.auth?.user?.branches?.length ?? 0) === 0)
const noClinicMsg = computed(() => (locale.value === 'ar'
    ? 'حسابك غير مرتبط بأي فرع أو عيادة بعد، لذلك لن تظهر بيانات. يرجى التواصل مع المسؤول.'
    : "Your account isn't assigned to a clinic/branch yet, so data won't show. Please ask an admin to assign you."))
const userFlags = computed(() => ({
    is_admin: !!page.props.auth?.user?.is_admin,
    is_reception: !!page.props.auth?.user?.is_reception,
    is_doctor: !!page.props.auth?.user?.is_doctor,
    is_nurse: !!page.props.auth?.user?.is_nurse,
}))
function userCan(perm) {
    return allAccess.value || userPermissions.value.includes(perm)
}
const navGates = {
    dashboard:              { perm: 'view_clinic_reports' },
    'doctor-schedule':      { roles: ['admin', 'super_admin', 'clinic_admin', 'branch_manager'], flags: ['is_doctor'] },
    'my-earnings':          { flags: ['is_doctor'] },
    visits:                 { perm: 'view_any_visits' },
    patients:               { perm: 'view_any_patients' },
    checkin:                { flags: ['is_admin', 'is_reception'] },
    bookings:               { flags: ['is_admin', 'is_reception'] },
    'patient-files':        { perm: 'patient_files_view' },
    'follow-up-plans':      { perm: 'view_any_follow_up_plans' },
    'inpatient-board':      { flags: ['is_admin', 'is_reception', 'is_doctor', 'is_nurse'] },
    'inpatient-admissions': { flags: ['is_admin', 'is_reception', 'is_doctor', 'is_nurse'] },
    'inpatient-wards':      { perm: 'view_any_wards' },
    'inpatient-beds':       { perm: 'view_any_beds' },
    'inpatient-reports':    { roles: ['admin', 'super_admin', 'clinic_admin'], perm: 'view_any_admissions' },
    'insurance-insurers':   { perm: 'view_any_insurers' },
    'insurance-plans':      { perm: 'view_any_insurance_plans' },
    'insurance-policies':   { perm: 'view_any_patient_insurance_policies' },
    'insurance-preauth':    { perm: 'view_any_insurance_preauthorizations' },
    'insurance-claims':     { perm: 'view_any_insurance_claims' },
    'lab-tests':            { perm: 'view_any_lab_tests' },
    'clinic-items':         { perm: 'view_any_clinic_items' },
    'clinic-stock':         { perm: 'view_any_clinic_item_stocks' },
    'stock-movements':      { perm: 'view_any_clinic_stock_movement' },
    'stock-transfers':      { perm: 'view_any_stock_transfers' },
    'stock-requests':       { perm: 'view_any_visit_stock_request' },
    'clinic-packages':      { perm: 'view_any_clinic_packages' },
    leaves:                 { perm: 'view_any_staff_leaves' },
    attendance:             { perm: 'view_any_staff_attendances' },
    doctors:                { perm: 'view_any_doctors' },
    users:                  { roles: ['admin', 'super_admin'] },
    'doctor-comp':          { perm: 'view_any_doctor_compensation_profiles' },
    'doctor-earnings':      { perm: 'view_any_doctor_compensation_ledgers' },
    accounts:               { perm: 'view_any_accounting_accounts' },
    'journal-entries':      { perm: 'view_any_accounting_journal_entries' },
    expenses:               { perm: 'view_any_accounting_expenses' },
    vendors:                { perm: 'view_any_accounting_vendors' },
    reconciliation:         { perm: 'view_any_accounting_bank_reconciliations' },
    periods:                { perm: 'view_any_accounting_periods' },
    'trial-balance':        { perm: 'view_accounting_trial_balance' },
    'general-ledger':       { perm: 'view_accounting_general_ledger' },
    'profit-loss':          { perm: 'view_accounting_profit_and_loss' },
    'balance-sheet':        { perm: 'view_accounting_balance_sheet' },
    'cash-flow':            { perm: 'view_accounting_cash_flow' },
    reports:                { perm: 'view_clinic_reports' },
    'daily-closing':        { perm: 'view_clinic_closing_reports' },
    'daily-reconciliation': { roles: ['admin', 'super_admin', 'clinic_admin', 'branch_manager', 'accountant'] },
    executive:              { perm: 'view_executive-dashboard' },
    clinics:                { roles: ['admin', 'super_admin'] },
    branches:               { roles: ['admin', 'super_admin'] },
    gateways:               { roles: ['admin', 'super_admin'] },
    roles:                  { roles: ['admin', 'super_admin'] },
    settings:               { perm: 'view_any_system_setting' },
    activity:               { roles: ['admin', 'super_admin'] },
    coupons:                { roles: ['admin', 'super_admin', 'clinic_admin'] },
    promotions:             { roles: ['admin', 'super_admin', 'clinic_admin'] },
    'wa-triggers':          { roles: ['admin', 'super_admin'] },
    'wa-campaigns':         { roles: ['admin', 'super_admin'] },
    'wa-commands':          { roles: ['admin', 'super_admin'] },
    'wa-messages':          { roles: ['admin', 'super_admin'] },
    'wa-texts':             { roles: ['admin', 'super_admin'] },
    'wa-logs':              { roles: ['admin', 'super_admin'] },
    'wa-sessions':          { roles: ['admin', 'super_admin'] },
    'wa-audience':          { roles: ['admin', 'super_admin'] },
    'wap-dashboard':        { roles: ['admin', 'super_admin', 'clinic_admin'] },
    'wap-inbox':            { roles: ['admin', 'super_admin', 'clinic_admin'] },
    'wap-templates':        { roles: ['admin', 'super_admin', 'clinic_admin'] },
    'wap-contacts':         { roles: ['admin', 'super_admin', 'clinic_admin'] },
    'wap-campaigns':        { roles: ['admin', 'super_admin', 'clinic_admin'] },
    'wap-logs':             { roles: ['admin', 'super_admin', 'clinic_admin'] },
    'wap-sessions':         { roles: ['admin', 'super_admin', 'clinic_admin'] },
    'wap-settings':         { roles: ['admin', 'super_admin', 'clinic_admin'] },
}
function itemVisible(it) {
    const g = navGates[it.id]
    if (!g) return true
    if (g.perm && userCan(g.perm)) return true
    if (g.roles && hasAnyRole(g.roles)) return true
    if (g.flags && g.flags.some(f => userFlags.value[f])) return true
    return false
}

// Seed unread badge from server prop on first paint.
onMounted(() => {
    const initial = Number(page.props.unread_count ?? 0)
    if (Number.isFinite(initial)) unreadCount.value = initial
})

// Dark mode — persists.
const DARK_KEY = 'v2.dark'
const dark = ref(false)
onMounted(() => {
    const saved = localStorage.getItem(DARK_KEY)
    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches
    dark.value = saved === null ? !!prefersDark : saved === '1'
    document.documentElement.classList.toggle('dark', dark.value)
})
watch(dark, (v) => {
    document.documentElement.classList.toggle('dark', v)
    localStorage.setItem(DARK_KEY, v ? '1' : '0')
})
function toggleDark() { dark.value = !dark.value }

// Sidebar collapsed state — persists. Collapsed = icon-only rail, expanded = labelled.
const COLLAPSE_KEY = 'v2.sidebar.collapsed'
const collapsed = ref(false)
onMounted(() => {
    collapsed.value = localStorage.getItem(COLLAPSE_KEY) === '1'
})
watch(collapsed, (v) => { localStorage.setItem(COLLAPSE_KEY, v ? '1' : '0') })
function toggleCollapse() { collapsed.value = !collapsed.value }

// -----------------------------------------------------------------------------
// Responsive shell. Below `lg` (1024px) a 240px fixed sidebar would eat the
// screen, so the sidebar becomes an off-canvas drawer toggled by the topbar
// hamburger. We track the viewport with matchMedia (not CSS alone) because the
// sidebar must flip between `position: sticky` (desktop) and a fixed,
// translate-in drawer (mobile), and because the collapsed icon-rail is a
// desktop-only affordance that we never want on a phone.
// -----------------------------------------------------------------------------
const isMobile = ref(false)
const drawerOpen = ref(false)
onMounted(() => {
    const mq = window.matchMedia('(max-width: 1023px)')
    const apply = () => {
        isMobile.value = mq.matches
        if (!mq.matches) drawerOpen.value = false   // leaving mobile always closes the drawer
    }
    apply()
    mq.addEventListener?.('change', apply)
})
function toggleDrawer() { drawerOpen.value = !drawerOpen.value }
function closeDrawer() { drawerOpen.value = false }
// Primary topbar toggle: open/close the drawer on mobile, collapse the icon-rail
// on desktop. One button, context-aware, so the topbar stays uncluttered.
function onMenuToggle() { isMobile.value ? toggleDrawer() : toggleCollapse() }
// Lock body scroll behind the open drawer so the page can't scroll underneath.
watch(drawerOpen, (open) => { document.body.style.overflow = open ? 'hidden' : '' })
onMounted(() => {
    const onKey = (e) => { if (e.key === 'Escape') closeDrawer() }
    window.addEventListener('keydown', onKey)
})
// Desktop: sticky rail/column. Mobile: fixed off-canvas drawer that slides in
// from the start edge (the right edge in RTL) and sits over the backdrop. The
// `transform` is physical (not logical), so we pick the off-screen direction by
// reading-direction ourselves.
const sidebarStyle = computed(() => {
    if (isMobile.value) {
        const hidden = !drawerOpen.value
        const offSign = isRtl.value ? 1 : -1   // push off-screen toward the start edge
        return {
            position: 'fixed',
            top: '0',
            bottom: '0',
            insetInlineStart: '0',
            width: 'min(284px, 82vw)',
            maxWidth: '82vw',
            borderInlineEnd: '1px solid var(--line)',
            background: 'var(--bg)',
            overflowY: 'auto',
            zIndex: 60,
            transform: hidden ? `translateX(${offSign * 100}%)` : 'translateX(0)',
            transition: 'transform 0.24s cubic-bezier(.2,.7,.2,1)',
            boxShadow: hidden ? 'none' : 'var(--shadow-lg)',
        }
    }
    return {
        position: 'sticky',
        top: 'var(--topbar-h)',
        height: 'calc(100vh - var(--topbar-h))',
        width: collapsed.value ? '56px' : '240px',
        minWidth: collapsed.value ? '56px' : '240px',
        borderInlineEnd: '1px solid var(--line)',
        background: 'var(--bg)',
        overflowY: 'auto',
        zIndex: 30,
        transition: 'width 0.18s ease, min-width 0.18s ease',
    }
})

// Sidebar scroll position — the layout re-mounts on each Inertia visit, so we
// persist the sidebar's scrollTop and restore it on mount. Keeps the user's
// place instead of snapping back to the top after every navigation.
const sidebarEl = ref(null)
const SIDEBAR_SCROLL_KEY = 'v2.sidebar.scrollTop'
let sidebarScrollTimer = null
function onSidebarScroll() {
    clearTimeout(sidebarScrollTimer)
    sidebarScrollTimer = setTimeout(() => {
        try { sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(sidebarEl.value?.scrollTop ?? 0)) } catch (e) { /* ignore */ }
    }, 100)
}
onMounted(() => {
    const saved = Number(sessionStorage.getItem(SIDEBAR_SCROLL_KEY) || 0)
    if (sidebarEl.value && Number.isFinite(saved) && saved > 0) sidebarEl.value.scrollTop = saved
})

function setLang(target) {
    if (target === locale.value) return
    window.location.href = `/language/${target}`
}

// Cmd+K palette
const cmdOpen = ref(false)
function openCmd() { cmdOpen.value = true }
// Show ⌘K on Mac, Ctrl K elsewhere, in the search field's shortcut hint.
const isMac = ref(false)
onMounted(() => {
    isMac.value = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '')
})
onMounted(() => {
    const onKey = (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault()
            cmdOpen.value = true
        }
    }
    window.addEventListener('keydown', onKey)
})

// -----------------------------------------------------------------------------
// Sidebar nav: grouped by section so the customer sees a real structure, not
// a flat icon strip. Each item links to a real destination — v2 page where it
// exists, otherwise the matching Filament admin URL so nothing is a dead "#".
// -----------------------------------------------------------------------------
const navSections = computed(() => ([
    {
        id: 'operations', icon: 'gauge',
        label: locale.value === 'ar' ? 'العمليات' : 'Operations',
        items: [
            { id: 'dashboard', icon: 'gauge',         label: locale.value === 'ar' ? 'لوحة التحكم' : 'Dashboard', href: '/admin/v2/dashboard',         v2: true },
            { id: 'waiting',   icon: 'users-round',   label: locale.value === 'ar' ? 'قائمة الانتظار' : 'Waiting',  href: '/admin/v2/waiting-patients', v2: true },
            { id: 'checkin',   icon: 'log-in',        label: locale.value === 'ar' ? 'تسجيل الدخول' : 'Check-in', href: '/admin/v2/checkin',          v2: true },
            { id: 'bookings',  icon: 'calendar-days', label: locale.value === 'ar' ? 'الحجوزات' : 'Bookings', href: '/admin/v2/bookings',         v2: true },
            { id: 'visits',    icon: 'clipboard-list',label: locale.value === 'ar' ? 'الزيارات' : 'Visits',   href: '/admin/v2/visits-list', v2: true },
            { id: 'doctor-schedule', icon: 'calendar-clock', label: locale.value === 'ar' ? 'جدول الأطباء' : 'Doctor Schedule', href: '/admin/v2/doctor-schedule', v2: true },
            { id: 'my-earnings', icon: 'coins', label: locale.value === 'ar' ? 'أرباحي اليومية' : 'My Earnings', href: '/admin/v2/my-earnings', v2: true },
        ],
    },
    {
        id: 'patients', icon: 'user-round',
        label: locale.value === 'ar' ? 'المرضى' : 'Patients',
        items: [
            { id: 'patients',      icon: 'user-round',   label: locale.value === 'ar' ? 'المرضى' : 'Patients', href: '/admin/v2/patients', v2: true },
            { id: 'patient-files', icon: 'folder',       label: locale.value === 'ar' ? 'ملفات المرضى' : 'Patient files', href: '/admin/v2/patient-files', v2: true },
            { id: 'follow-up-plans', icon: 'rotate-ccw', label: locale.value === 'ar' ? 'خطط المتابعة' : 'Follow-up Plans', href: '/admin/v2/follow-up-plans', v2: true },
        ],
    },
    {
        id: 'inpatient', icon: 'bed-double',
        label: locale.value === 'ar' ? 'القسم الداخلي' : 'Inpatient',
        items: [
            { id: 'inpatient-board',      icon: 'bed-double',     label: locale.value === 'ar' ? 'لوحة الأسرّة' : 'Bed Board',  href: '/admin/v2/inpatient/board',      v2: true },
            { id: 'inpatient-admissions', icon: 'list-checks',    label: locale.value === 'ar' ? 'الإدخالات' : 'Admissions',    href: '/admin/v2/inpatient/admissions', v2: true },
            { id: 'inpatient-wards',      icon: 'door-open',      label: locale.value === 'ar' ? 'الأقسام' : 'Wards',           href: '/admin/v2/inpatient/wards', v2: true },
            { id: 'inpatient-beds',       icon: 'bed',            label: locale.value === 'ar' ? 'الأسرّة' : 'Beds',             href: '/admin/v2/inpatient/beds', v2: true },
            { id: 'inpatient-reports',    icon: 'bar-chart-3',    label: locale.value === 'ar' ? 'تقارير القسم' : 'Inpatient Reports', href: '/admin/v2/inpatient/reports', v2: true },
        ],
    },
    {
        id: 'insurance', icon: 'shield',
        label: locale.value === 'ar' ? 'التأمين' : 'Insurance',
        items: [
            { id: 'insurance-insurers',  icon: 'shield',         label: locale.value === 'ar' ? 'شركات التأمين' : 'Insurers',  href: '/admin/v2/insurance/insurers', v2: true },
            { id: 'insurance-plans',     icon: 'list',           label: locale.value === 'ar' ? 'الخطط' : 'Plans',              href: '/admin/v2/insurance/plans', v2: true },
            { id: 'insurance-policies',  icon: 'badge-check',    label: locale.value === 'ar' ? 'البوالص' : 'Policies',         href: '/admin/v2/insurance/policies', v2: true },
            { id: 'insurance-preauth',   icon: 'document-check', label: locale.value === 'ar' ? 'الموافقات المسبقة' : 'Pre-authorizations', href: '/admin/v2/insurance/preauthorizations', v2: true },
            { id: 'insurance-claims',    icon: 'file-text',      label: locale.value === 'ar' ? 'المطالبات' : 'Claims',         href: '/admin/v2/insurance/claims', v2: true },
        ],
    },
    {
        id: 'lab', icon: 'beaker',
        label: locale.value === 'ar' ? 'المختبر' : 'Laboratory',
        items: [
            { id: 'lab-tests', icon: 'beaker',  label: locale.value === 'ar' ? 'كتالوج الاختبارات' : 'Lab Tests', href: '/admin/v2/lab-tests', v2: true },
        ],
    },
    {
        id: 'pharmacy', icon: 'pill',
        label: locale.value === 'ar' ? 'الصيدلية والمخزون' : 'Pharmacy & Stock',
        items: [
            { id: 'clinic-items',    icon: 'pill',         label: locale.value === 'ar' ? 'الأصناف' : 'Items',     href: '/admin/v2/clinic-items', v2: true },
            { id: 'clinic-stock',    icon: 'package',      label: locale.value === 'ar' ? 'المخزون' : 'Stock',     href: '/admin/v2/clinic-stock', v2: true },
            { id: 'stock-movements', icon: 'truck',        label: locale.value === 'ar' ? 'حركة المخزون' : 'Movements', href: '/admin/v2/stock-movements', v2: true },
            { id: 'stock-requests',  icon: 'inbox',        label: locale.value === 'ar' ? 'طلبات الصرف' : 'Stock Requests', href: '/admin/v2/visit-stock-requests', v2: true },
            { id: 'stock-transfers', icon: 'arrow-left-right', label: locale.value === 'ar' ? 'تحويلات المخزون' : 'Stock Transfers', href: '/admin/v2/stock-transfers', v2: true },
            { id: 'clinic-packages', icon: 'gift',         label: locale.value === 'ar' ? 'الباقات' : 'Packages', href: '/admin/v2/clinic-packages', v2: true },
        ],
    },
    {
        id: 'discounts', icon: 'badge-percent',
        label: locale.value === 'ar' ? 'الخصومات والعروض' : 'Discounts & Promotions',
        items: [
            { id: 'coupons',    icon: 'ticket',        label: locale.value === 'ar' ? 'كوبونات الخصم' : 'Coupons',    href: '/admin/v2/coupons', v2: true },
            { id: 'promotions', icon: 'badge-percent', label: locale.value === 'ar' ? 'العروض الترويجية' : 'Promotions', href: '/admin/v2/promotions', v2: true },
        ],
    },
    {
        id: 'hr', icon: 'users',
        label: locale.value === 'ar' ? 'الموارد البشرية' : 'HR',
        items: [
            // My Leaves + My Attendance are always visible — every authenticated
            // user can view + manage their own. The resource enforces scoping.
            { id: 'leaves',      icon: 'calendar-x',  label: locale.value === 'ar' ? (isClinicAdmin.value ? 'الإجازات' : 'إجازاتي') : (isClinicAdmin.value ? 'Leaves' : 'My Leaves'),      href: '/admin/v2/staff-leaves', v2: true },
            { id: 'attendance',  icon: 'clock',       label: locale.value === 'ar' ? (isClinicAdmin.value ? 'الحضور' : 'حضوري') : (isClinicAdmin.value ? 'Attendance' : 'My Attendance'), href: '/admin/v2/staff-attendances', v2: true },
            // The rest are admin-only.
            { id: 'doctors',     icon: 'stethoscope', label: locale.value === 'ar' ? 'الأطباء' : 'Doctors',     href: '/admin/v2/doctors', v2: true },
            { id: 'users',       icon: 'users',       label: locale.value === 'ar' ? 'المستخدمون' : 'Users',    href: '/admin/v2/users', v2: true },
            { id: 'doctor-comp',     icon: 'wallet',  label: locale.value === 'ar' ? 'إعدادات العمولات' : 'Comp. Profiles', href: '/admin/v2/doctor-compensation-profiles', v2: true },
            { id: 'doctor-earnings', icon: 'coins',   label: locale.value === 'ar' ? 'أرباح الأطباء' : 'Doctor Earnings', href: '/admin/v2/doctor-compensation', v2: true },
        ],
    },
    {
        id: 'accounting', icon: 'book',
        label: locale.value === 'ar' ? 'المحاسبة' : 'Accounting',
        items: [
            { id: 'accounts',         icon: 'book',          label: locale.value === 'ar' ? 'دليل الحسابات' : 'Chart of Accounts', href: '/admin/v2/accounting/chart-of-accounts', v2: true },
            { id: 'journal-entries',  icon: 'book-open',     label: locale.value === 'ar' ? 'القيود اليومية' : 'Journal Entries', href: '/admin/v2/accounting/journal-entries', v2: true },
            { id: 'expenses',         icon: 'minus-circle',  label: locale.value === 'ar' ? 'المصروفات' : 'Expenses',             href: '/admin/v2/accounting/expenses', v2: true },
            { id: 'vendors',          icon: 'building-2',    label: locale.value === 'ar' ? 'الموردون' : 'Vendors',               href: '/admin/v2/accounting/vendors', v2: true },
            { id: 'reconciliation',   icon: 'check-circle',  label: locale.value === 'ar' ? 'التسوية المصرفية' : 'Bank Reconciliation', href: '/admin/v2/accounting/bank-reconciliations', v2: true },
            { id: 'periods',          icon: 'lock',          label: locale.value === 'ar' ? 'الفترات المحاسبية' : 'Periods', href: '/admin/v2/accounting/periods', v2: true },
            { id: 'trial-balance',    icon: 'scale',         label: locale.value === 'ar' ? 'ميزان المراجعة' : 'Trial Balance', href: '/admin/v2/reports/accounting/trial-balance', v2: true },
            { id: 'general-ledger',   icon: 'book-open',     label: locale.value === 'ar' ? 'دفتر الأستاذ' : 'General Ledger', href: '/admin/v2/reports/accounting/general-ledger', v2: true },
            { id: 'profit-loss',      icon: 'trending-up',   label: locale.value === 'ar' ? 'قائمة الدخل' : 'Profit & Loss', href: '/admin/v2/reports/accounting/profit-loss', v2: true },
            { id: 'balance-sheet',    icon: 'scale',         label: locale.value === 'ar' ? 'الميزانية العمومية' : 'Balance Sheet', href: '/admin/v2/reports/accounting/balance-sheet', v2: true },
            { id: 'cash-flow',        icon: 'banknote',      label: locale.value === 'ar' ? 'التدفقات النقدية' : 'Cash Flow', href: '/admin/v2/reports/accounting/cash-flow', v2: true },
        ],
    },
    {
        id: 'reports', icon: 'bar-chart-3',
        label: locale.value === 'ar' ? 'التقارير' : 'Reports',
        items: [
            { id: 'reports',        icon: 'bar-chart-3',  label: locale.value === 'ar' ? 'تقارير العيادة' : 'Clinic Reports', href: '/admin/v2/reports', v2: true },
            { id: 'daily-closing',  icon: 'file-check',   label: locale.value === 'ar' ? 'الإقفال اليومي' : 'Daily Closing', href: '/admin/v2/reports/daily-closing', v2: true },
            { id: 'daily-reconciliation', icon: 'banknote', label: locale.value === 'ar' ? 'التسوية اليومية' : 'Daily Reconciliation', href: '/admin/v2/reports/daily-reconciliation', v2: true },
            { id: 'executive',      icon: 'trending-up',  label: locale.value === 'ar' ? 'لوحة المدير' : 'Executive', href: '/admin/v2/reports/executive', v2: true },
        ],
    },
    {
        id: 'platform', icon: 'settings',
        label: locale.value === 'ar' ? 'المنصة' : 'Platform',
        sep: true,
        items: [
            { id: 'clinics',    icon: 'building-2', label: locale.value === 'ar' ? 'العيادات' : 'Clinics', href: '/admin/v2/partners', v2: true },
            { id: 'branches',   icon: 'map-pin',  label: locale.value === 'ar' ? 'الفروع' : 'Branches', href: '/admin/v2/branches', v2: true },
            { id: 'gateways',   icon: 'credit-card', label: locale.value === 'ar' ? 'حسابات الدفع' : 'Gateway Accounts', href: '/admin/v2/gateway-accounts', v2: true },
            { id: 'roles',      icon: 'shield',   label: locale.value === 'ar' ? 'الأدوار والصلاحيات' : 'Roles & Permissions', href: '/admin/v2/roles', v2: true },
            { id: 'settings',   icon: 'settings', label: locale.value === 'ar' ? 'إعدادات النظام' : 'System Settings', href: '/admin/v2/settings', v2: true },
            { id: 'activity',   icon: 'history',  label: locale.value === 'ar' ? 'سجل النشاط' : 'Activity Log', href: '/admin/v2/activity-log', v2: true },
        ],
    },
    {
        id: 'whatsapp', icon: 'message-circle',
        label: locale.value === 'ar' ? 'واتساب' : 'WhatsApp',
        items: [
            { id: 'wa-triggers',  icon: 'zap',             label: locale.value === 'ar' ? 'المحفّزات' : 'Triggers', href: '/admin/v2/whatsapp/triggers', v2: true },
            { id: 'wa-campaigns', icon: 'send',            label: locale.value === 'ar' ? 'الحملات' : 'Campaigns', href: '/admin/v2/campaigns', v2: true },
            { id: 'wa-commands',  icon: 'terminal',       label: locale.value === 'ar' ? 'الأوامر' : 'Commands', href: '/admin/v2/whatsapp/commands', v2: true },
            { id: 'wa-messages',  icon: 'message-square',  label: locale.value === 'ar' ? 'القوالب' : 'Templates', href: '/admin/v2/whatsapp/messages', v2: true },
            { id: 'wa-texts',     icon: 'book-open',       label: locale.value === 'ar' ? 'كتالوج الرسائل' : 'Message Catalog', href: '/admin/v2/whatsapp/message-texts', v2: true },
            { id: 'wa-logs',      icon: 'inbox',           label: locale.value === 'ar' ? 'السجل' : 'Logs', href: '/admin/v2/whatsapp/logs', v2: true },
            { id: 'wa-sessions',  icon: 'message-circle',  label: locale.value === 'ar' ? 'الجلسات' : 'Sessions', href: '/admin/v2/whatsapp/sessions', v2: true },
            { id: 'wa-audience',  icon: 'users-round',     label: locale.value === 'ar' ? 'مقاييس الجمهور' : 'Audience', href: '/admin/v2/whatsapp/audience-metrics', v2: true },
        ],
    },
    {
        id: 'wa-platform', icon: 'message-circle',
        label: locale.value === 'ar' ? 'منصة واتساب' : 'WhatsApp Platform',
        items: [
            { id: 'wap-dashboard',     icon: 'layout-dashboard', label: locale.value === 'ar' ? 'اللوحة' : 'Dashboard', href: '/admin/v2/wa-module', v2: true },
            { id: 'wap-inbox',         icon: 'inbox',            label: locale.value === 'ar' ? 'صندوق الوارد' : 'Inbox', href: '/admin/v2/wa-module/inbox', v2: true },
            { id: 'wap-templates',     icon: 'message-square',   label: locale.value === 'ar' ? 'القوالب' : 'Templates', href: '/admin/v2/wa-module/templates', v2: true },
            { id: 'wap-media',         icon: 'image',            label: locale.value === 'ar' ? 'الوسائط' : 'Media', href: '/admin/v2/wa-module/media', v2: true },
            { id: 'wap-contacts',      icon: 'users-round',      label: locale.value === 'ar' ? 'جهات الاتصال' : 'Contacts', href: '/admin/v2/wa-module/contacts', v2: true },
            { id: 'wap-campaigns',     icon: 'send',             label: locale.value === 'ar' ? 'الحملات' : 'Campaigns', href: '/admin/v2/wa-module/campaigns', v2: true },
            { id: 'wap-points',        icon: 'coins',            label: locale.value === 'ar' ? 'النقاط' : 'Points', href: '/admin/v2/wa-module/points', v2: true },
            { id: 'wap-logs',          icon: 'scroll-text',      label: locale.value === 'ar' ? 'سجل الرسائل' : 'Message Logs', href: '/admin/v2/wa-module/logs', v2: true },
            { id: 'wap-sessions',      icon: 'message-circle',   label: locale.value === 'ar' ? 'الجلسات' : 'Sessions', href: '/admin/v2/wa-module/sessions', v2: true },
            { id: 'wap-settings',      icon: 'settings',         label: locale.value === 'ar' ? 'الإعدادات' : 'Settings', href: '/admin/v2/wa-module/settings', v2: true },
        ],
    },
].map(section => ({
    ...section,
    items: section.items.filter(itemVisible),
})).filter(section => section.items.length > 0)))

// Active nav item: explicit `active` prop wins; otherwise derive from the URL by
// longest matching item href. Lets AppLayout work as a persistent layout where
// pages no longer pass `active`.
const currentPath = computed(() => (page.url || '/').split('?')[0])
// Navigating from inside the mobile drawer should close it (Inertia keeps this
// layout mounted, so it won't close on its own).
watch(currentPath, closeDrawer)
const resolvedActive = computed(() => {
    if (props.active) return props.active
    const path = currentPath.value
    // Visit console (/admin/v2/visits/123) maps to the Visits list item.
    if (/^\/admin\/v2\/visits\/\d+/.test(path)) return 'visits'
    let best = null, bestLen = -1
    for (const section of navSections.value) {
        for (const it of section.items) {
            const href = it.href || ''
            if ((path === href || path.startsWith(href + '/')) && href.length > bestLen) {
                best = it.id; bestLen = href.length
            }
        }
    }
    return best
})
const isRtl = computed(() => locale.value === 'ar')

// -----------------------------------------------------------------------------
// "How to use this page" help drawer — one topbar button, driven by the current
// nav item, so every page with help content gets it with zero per-page wiring.
// The button only shows for pages listed in helpMap.js (mirrors HelpController).
// -----------------------------------------------------------------------------
const helpOpen = ref(false)
// The visit console (/admin/v2/visits/123) highlights the "Visits" nav item, but
// it is a distinct workflow screen with its own help — so the help key diverges
// from resolvedActive there.
const isVisitConsole = computed(() => /^\/admin\/v2\/visits\/\d+/.test(currentPath.value))
const helpKey = computed(() => (isVisitConsole.value ? 'visit-console' : resolvedActive.value))
const helpAvailable = computed(() => hasHelp(helpKey.value))
const activeItemLabel = computed(() => {
    if (isVisitConsole.value) return isRtl.value ? 'وحدة الزيارة' : 'Visit console'
    for (const s of navSections.value) {
        for (const it of s.items) {
            if (it.id === resolvedActive.value) return it.label
        }
    }
    return ''
})
function openHelp() { helpOpen.value = true }

// Breadcrumb for the sub-bar ("where am I"). Derived from the nav structure so
// it works on every page with zero per-page wiring: [section, item]. Deep pages
// the nav can't name (e.g. the visit console) get a sensible extra crumb.
const breadcrumb = computed(() => {
    const path = currentPath.value
    let section = null
    let item = null
    for (const s of navSections.value) {
        for (const it of s.items) {
            if (it.id === resolvedActive.value) { section = s; item = it }
        }
    }

    const crumbs = []
    if (section) crumbs.push(section.label)
    if (item) crumbs.push(item.label)

    // Visit console: /admin/v2/visits/123 → … / Visits / Visit #123
    const vm = path.match(/^\/admin\/v2\/visits\/(\d+)/)
    if (vm) crumbs.push((isRtl.value ? 'زيارة #' : 'Visit #') + vm[1])

    if (crumbs.length === 0) {
        // Unknown page — humanise the last URL segment so it's never blank.
        const seg = path.split('/').filter(Boolean).pop() || 'dashboard'
        crumbs.push(seg.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()))
    }
    return crumbs
})

// Richer hover descriptions per nav item (keyed by item id), shown in a styled
// tooltip card. EN strings use double quotes so apostrophes are safe.
const navDescriptions = {
    // Operations
    dashboard:        { en: "Your clinic at a glance — today's bookings, who's in the queue, revenue and key activity in one place.", ar: 'عيادتك في لمحة — حجوزات اليوم، من في قائمة الانتظار، الإيرادات وأهم الأنشطة في مكان واحد.' },
    waiting:          { en: "The live waiting room: everyone who has checked in and is waiting for a doctor, in arrival order.", ar: 'غرفة الانتظار المباشرة: كل من سجّل وصوله وينتظر الطبيب، حسب ترتيب الوصول.' },
    checkin:          { en: "Check arriving patients in against their booking, collect any consultation fee, and send them to the queue.", ar: 'سجّل وصول المرضى مقابل حجزهم، حصّل رسوم الاستشارة، وأرسلهم إلى قائمة الانتظار.' },
    bookings:         { en: "Create, reschedule and track appointments across doctors and branches, from pending through to completed.", ar: 'أنشئ وأعد جدولة وتابع المواعيد عبر الأطباء والفروع، من قيد الانتظار حتى الاكتمال.' },
    visits:           { en: "Every patient visit — open the consultation console to add clinical notes, services, items and payments.", ar: 'كل زيارة مريض — افتح وحدة الكشف لإضافة الملاحظات السريرية والخدمات والأصناف والمدفوعات.' },
    'doctor-schedule':{ en: "View and manage each doctor's working hours, shifts and availability for bookings.", ar: 'اعرض وأدر ساعات عمل كل طبيب وورديّاته وتوفّره للحجوزات.' },
    'my-earnings':    { en: "Your own earnings from today's completed visits — a quick per-visit breakdown to reconcile at day close.", ar: 'أرباحك من زيارات اليوم المكتملة — تفصيل سريع لكل زيارة للمطابقة عند إقفال اليوم.' },
    // Patients
    patients:         { en: "The full patient directory — search records, review history and open any patient's profile.", ar: 'دليل المرضى الكامل — ابحث في السجلات، راجع التاريخ، وافتح ملف أي مريض.' },
    'patient-files':  { en: "Documents and medical files uploaded for patients — reports, scans, IDs and other attachments.", ar: 'المستندات والملفات الطبية المرفوعة للمرضى — التقارير والأشعة والهويات والمرفقات الأخرى.' },
    'follow-up-plans':{ en: "Scheduled follow-ups so patients are reminded and brought back for the next step of their care.", ar: 'متابعات مجدولة لتذكير المرضى وإعادتهم للخطوة التالية من رعايتهم.' },
    // Inpatient
    'inpatient-board':      { en: "A live map of every bed — occupied, free or being cleaned — across all wards.", ar: 'خريطة مباشرة لكل سرير — مشغول أو فارغ أو قيد التنظيف — عبر كل الأقسام.' },
    'inpatient-admissions': { en: "Admit patients to a bed, transfer them between wards, and discharge them when treatment is done.", ar: 'أدخل المرضى إلى سرير، حوّلهم بين الأقسام، وأخرجهم عند انتهاء العلاج.' },
    'inpatient-wards':      { en: "Set up the wards and departments that hold beds and group admitted patients.", ar: 'أنشئ الأقسام والأجنحة التي تضم الأسرّة وتجمع المرضى المنوّمين.' },
    'inpatient-beds':       { en: "Manage individual beds — their ward, daily rate and current availability.", ar: 'أدر الأسرّة الفردية — قسمها وسعرها اليومي وتوفّرها الحالي.' },
    'inpatient-reports':    { en: "Occupancy, length-of-stay and revenue reports for the inpatient department.", ar: 'تقارير الإشغال ومدة الإقامة والإيرادات لقسم التنويم.' },
    // Insurance
    'insurance-insurers':   { en: "The insurance companies you work with, along with their contact and billing details.", ar: 'شركات التأمين التي تتعامل معها، مع بيانات التواصل والفوترة الخاصة بها.' },
    'insurance-plans':      { en: "The coverage plans each insurer offers, with their tiers and rules.", ar: 'خطط التغطية التي تقدمها كل شركة تأمين، بفئاتها وقواعدها.' },
    'insurance-policies':   { en: "Which patients are covered by which plan, including policy and member numbers.", ar: 'أي المرضى مغطّى بأي خطة، بما في ذلك أرقام البوليصة والعضوية.' },
    'insurance-preauth':    { en: "Request and track insurer approval for treatments before they are carried out.", ar: 'اطلب وتابع موافقة شركة التأمين على العلاجات قبل تنفيذها.' },
    'insurance-claims':     { en: "Build, submit and follow up insurance claims and their settlement status.", ar: 'أنشئ وقدّم وتابع مطالبات التأمين وحالة تسويتها.' },
    // Lab
    'lab-tests':      { en: "The catalogue of laboratory tests you offer, with their pricing and specimen details.", ar: 'كتالوج اختبارات المختبر التي تقدمها، بأسعارها وتفاصيل العينات.' },
    // Pharmacy & stock
    'clinic-items':   { en: "Your catalogue of medicines, consumables and billable services used during visits.", ar: 'كتالوج الأدوية والمستهلكات والخدمات القابلة للفوترة المستخدمة خلال الزيارات.' },
    'clinic-stock':   { en: "On-hand quantities per branch, with reorder thresholds that trigger low-stock alerts.", ar: 'الكميات المتوفرة لكل فرع، مع حدود إعادة الطلب التي تطلق تنبيهات نقص المخزون.' },
    'stock-movements':{ en: "A full audit of stock in and out — purchases, dispensing, adjustments and transfers.", ar: 'تدقيق كامل لدخول وخروج المخزون — المشتريات والصرف والتسويات والتحويلات.' },
    'stock-requests': { en: "Requests to dispense items for a visit, waiting for the pharmacy to fulfil.", ar: 'طلبات صرف الأصناف لزيارة ما، بانتظار تنفيذ الصيدلية.' },
    'stock-transfers':{ en: "Move stock between your clinic's branches — the hub dispatches items to a branch that's short.", ar: 'نقل المخزون بين فروع العيادة — يرسل المركز الرئيسي الأصناف إلى الفرع الذي ينقصه.' },
    'clinic-packages':{ en: "Pre-priced bundles of services and items sold together as a single package.", ar: 'حزم مسبقة التسعير من الخدمات والأصناف تُباع معًا كباقة واحدة.' },
    // HR
    leaves:           { en: "Request, review and track staff leave, with the remaining balance for each person.", ar: 'اطلب وراجع وتابع إجازات الموظفين، مع الرصيد المتبقي لكل شخص.' },
    attendance:       { en: "Daily attendance and clock in / clock out records for staff.", ar: 'سجلات الحضور اليومية وتسجيل الدخول والخروج للموظفين.' },
    doctors:          { en: "The doctor directory — specialties, consultation fees, license details and active status.", ar: 'دليل الأطباء — التخصصات ورسوم الاستشارة وبيانات الترخيص وحالة النشاط.' },
    users:            { en: "The people who can log in, and the roles that control what each of them can see and do.", ar: 'الأشخاص الذين يمكنهم تسجيل الدخول، والأدوار التي تحدد ما يراه ويفعله كل منهم.' },
    'doctor-comp':    { en: "The rules that decide how each doctor is paid — salary, per-visit or commission.", ar: 'القواعد التي تحدد كيفية دفع أجر كل طبيب — راتب، أو لكل زيارة، أو عمولة.' },
    'doctor-earnings':{ en: "What each doctor has earned and is owed, based on their compensation rules.", ar: 'ما كسبه كل طبيب وما له من مستحقات، بناءً على قواعد تعويضه.' },
    // Accounting
    accounts:         { en: "The chart of accounts — the backbone of the books grouping all assets, liabilities, income and expenses.", ar: 'دليل الحسابات — العمود الفقري للدفاتر الذي يجمع كل الأصول والخصوم والإيرادات والمصروفات.' },
    'journal-entries':{ en: "Post manual double-entry transactions directly into the ledger when needed.", ar: 'سجّل قيود اليومية المزدوجة يدويًا مباشرة في الدفتر عند الحاجة.' },
    expenses:         { en: "Record what the clinic spends, categorise it, and link it to the right vendor.", ar: 'سجّل ما تنفقه العيادة، صنّفه، واربطه بالمورد الصحيح.' },
    vendors:          { en: "The suppliers and payees you buy from or pay, along with their balances.", ar: 'الموردون والمستفيدون الذين تشتري منهم أو تدفع لهم، مع أرصدتهم.' },
    reconciliation:   { en: "Match your bank statement line by line against the books to catch any difference.", ar: 'طابق كشف حسابك البنكي سطرًا بسطر مع الدفاتر لاكتشاف أي فرق.' },
    periods:          { en: "Open and lock accounting periods so finalised months can no longer be changed.", ar: 'افتح وأغلق الفترات المحاسبية حتى لا يمكن تغيير الأشهر المعتمدة.' },
    'trial-balance':  { en: "A snapshot of every account's debit and credit balance to confirm the books balance.", ar: 'لقطة لأرصدة المدين والدائن لكل حساب للتأكد من توازن الدفاتر.' },
    'general-ledger': { en: "Drill into every transaction posted to any account over a chosen period.", ar: 'تعمّق في كل حركة مسجلة على أي حساب خلال فترة محددة.' },
    'profit-loss':    { en: "Income statement showing revenue, costs and profit over a chosen period.", ar: 'قائمة الدخل التي تعرض الإيرادات والتكاليف والربح خلال فترة محددة.' },
    'balance-sheet':  { en: "A point-in-time view of what the clinic owns, owes and is worth.", ar: 'عرض في لحظة محددة لما تملكه العيادة وما عليها وصافي قيمتها.' },
    'cash-flow':      { en: "Where cash came from and where it went over a period.", ar: 'من أين جاء النقد وإلى أين ذهب خلال فترة.' },
    // Reports
    reports:          { en: "Operational reports on visits, revenue, doctors and items to see how the clinic is performing.", ar: 'تقارير تشغيلية عن الزيارات والإيرادات والأطباء والأصناف لمعرفة أداء العيادة.' },
    'daily-closing':  { en: "Close out the day — reconcile cash, payments and revenue before handover.", ar: 'أقفل اليوم — طابق النقد والمدفوعات والإيرادات قبل التسليم.' },
    'daily-reconciliation': { en: "Match the day's collected payments against the expected totals by method.", ar: 'طابق مدفوعات اليوم المحصّلة مع الإجماليات المتوقعة حسب طريقة الدفع.' },
    executive:        { en: "A high-level dashboard of the KPIs management cares about most.", ar: 'لوحة عالية المستوى لمؤشرات الأداء التي تهم الإدارة أكثر.' },
    // Platform
    clinics:          { en: "The clinics or partner organisations operating on the platform.", ar: 'العيادات أو المؤسسات الشريكة العاملة على المنصة.' },
    branches:         { en: "The physical branches and locations each clinic operates from.", ar: 'الفروع والمواقع الفعلية التي تعمل منها كل عيادة.' },
    gateways:         { en: "Payment gateway accounts used to collect online and card payments.", ar: 'حسابات بوابات الدفع المستخدمة لتحصيل المدفوعات الإلكترونية والبطاقات.' },
    roles:            { en: "Define roles and fine-tune exactly what each role is permitted to do.", ar: 'عرّف الأدوار واضبط بدقة ما يُسمح لكل دور بفعله.' },
    settings:         { en: "System-wide configuration that controls how the whole app behaves.", ar: 'إعدادات على مستوى النظام تتحكم في سلوك التطبيق بالكامل.' },
    activity:         { en: "An immutable audit trail of who changed what, and when, across the system.", ar: 'سجل تدقيق غير قابل للتعديل لمن غيّر ماذا ومتى عبر النظام.' },
    // WhatsApp
    'wa-triggers':    { en: "Automations that send a WhatsApp message when something happens, like a new booking or a reminder.", ar: 'أتمتة ترسل رسالة واتساب عند حدوث شيء، مثل حجز جديد أو تذكير.' },
    'wa-campaigns':   { en: "Send WhatsApp messages in bulk to a chosen audience and track their delivery.", ar: 'أرسل رسائل واتساب بالجملة لجمهور محدد وتابع تسليمها.' },
    'wa-commands':    { en: "The keywords patients can text, and how the chatbot replies to each one.", ar: 'الكلمات المفتاحية التي يمكن للمرضى إرسالها، وكيف يرد روبوت المحادثة على كل منها.' },
    'wa-messages':    { en: "The approved WhatsApp message templates used for notifications and replies.", ar: 'قوالب رسائل واتساب المعتمدة المستخدمة للإشعارات والردود.' },
    'wa-texts':       { en: "A catalogue of reusable message wording you can reference across the system.", ar: 'كتالوج من صياغات الرسائل القابلة لإعادة الاستخدام يمكن الرجوع إليها عبر النظام.' },
    'wa-logs':        { en: "Every WhatsApp message sent and received, with its delivery status.", ar: 'كل رسالة واتساب مرسلة ومستلمة، مع حالة تسليمها.' },
    'wa-sessions':    { en: "Open WhatsApp conversations currently in progress with patients.", ar: 'محادثات واتساب المفتوحة الجارية حاليًا مع المرضى.' },
    'wa-audience':    { en: "Metrics on how many people you reach and how they engage on WhatsApp.", ar: 'مقاييس عن عدد من تصل إليهم وكيفية تفاعلهم على واتساب.' },
}
function itemDesc(it) {
    const d = navDescriptions[it.id]
    return d ? (isRtl.value ? d.ar : d.en) : it.label
}

// Styled hover tooltip card for nav links (replaces the plain native title).
// Teleported + fixed-positioned so it's never clipped by the sidebar overflow,
// shown after a short delay, and flipped/clamped to stay on screen. RTL-aware.
const TIP_W = 264
const tip = ref({ open: false, x: 0, y: 0, label: '', body: '' })
let tipTimer = null
function showTip(it, e) {
    clearTimeout(tipTimer)
    const el = e.currentTarget
    tipTimer = setTimeout(() => {
        const r = el.getBoundingClientRect()
        const gap = 10
        let x = isRtl.value ? r.left - gap - TIP_W : r.right + gap
        if (!isRtl.value && x + TIP_W > window.innerWidth - 8) x = r.left - gap - TIP_W
        if (isRtl.value && x < 8) x = r.right + gap
        x = Math.max(8, Math.min(x, window.innerWidth - TIP_W - 8))
        const y = Math.max(8, Math.min(r.top - 2, window.innerHeight - 150))
        tip.value = { open: true, x, y, label: it.label, body: itemDesc(it) }
    }, 280)
}
function hideTip() { clearTimeout(tipTimer); tip.value.open = false }
watch(currentPath, hideTip)
watch(collapsed, hideTip)

// The section that owns the active route — used to auto-open its accordion group
// and to highlight the section icon on the collapsed rail.
const activeSectionId = computed(() => {
    for (const s of navSections.value) {
        if (s.items.some(it => it.id === resolvedActive.value)) return s.id
    }
    return null
})

// -----------------------------------------------------------------------------
// Accordion: each section is a collapsible group so the sidebar isn't one long
// scroll. State persists; a section with no recorded preference defaults to
// "open only if it holds the active route".
// -----------------------------------------------------------------------------
const EXPANDED_KEY = 'v2.sidebar.expanded'
const expanded = ref({})
onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(EXPANDED_KEY) || 'null')
        if (saved && typeof saved === 'object') expanded.value = saved
    } catch (e) { /* ignore */ }
})
watch(expanded, (v) => {
    try { localStorage.setItem(EXPANDED_KEY, JSON.stringify(v)) } catch (e) { /* ignore */ }
}, { deep: true })
function isExpanded(id) {
    return id in expanded.value ? !!expanded.value[id] : (id === activeSectionId.value)
}
function toggleSection(id) {
    expanded.value = { ...expanded.value, [id]: !isExpanded(id) }
}
// Keep the active section open as the user navigates between pages.
watch(activeSectionId, (id) => {
    if (id && !isExpanded(id)) expanded.value = { ...expanded.value, [id]: true }
})

// -----------------------------------------------------------------------------
// Collapsed rail flyout: hovering / clicking a section icon shows its items in a
// teleported, fixed-position panel beside the rail (so it's never clipped by the
// sidebar's overflow). Closes on mouse-out, outside click, or navigation.
// -----------------------------------------------------------------------------
const FLYOUT_W = 220
const flyout = ref({ open: false, id: null, top: 0, left: 0 })
let flyoutTimer = null
const flyoutSection = computed(() => navSections.value.find(s => s.id === flyout.value.id) || null)

function placeFlyout(section, el) {
    const r = el.getBoundingClientRect()
    const left = isRtl.value ? Math.max(8, r.left - 6 - FLYOUT_W) : Math.min(r.right + 6, window.innerWidth - FLYOUT_W - 8)
    const estH = Math.min(section.items.length * 34 + 40, window.innerHeight - 24)
    const top = Math.max(8, Math.min(r.top, window.innerHeight - estH - 8))
    flyout.value = { open: true, id: section.id, top, left }
}
function showFlyout(section, e) {
    clearTimeout(flyoutTimer)
    placeFlyout(section, e.currentTarget)
}
function onRailClick(section, e) {
    if (flyout.value.open && flyout.value.id === section.id) flyout.value.open = false
    else showFlyout(section, e)
}
function scheduleFlyoutClose() { flyoutTimer = setTimeout(() => { flyout.value.open = false }, 140) }
function keepFlyout() { clearTimeout(flyoutTimer) }
function closeFlyout() { flyout.value.open = false }

watch(currentPath, closeFlyout)
watch(collapsed, closeFlyout)
onMounted(() => {
    const onDocDown = (e) => {
        if (!flyout.value.open) return
        if (!e.target.closest?.('.nav-flyout, .nav-rail-btn')) closeFlyout()
    }
    document.addEventListener('mousedown', onDocDown)
})
</script>

<template>
    <div class="app-shell" style="display: flex; flex-direction: column; min-height: 100vh; background: var(--bg);">
        <!-- Topbar -->
        <div class="glass-strip" style="position: sticky; top: 0; z-index: 40; border-bottom: 1px solid var(--line);">
            <div style="height: 56px; padding: 0 20px; display: flex; align-items: center; gap: 16px; max-width: 100%;">
                <Link href="/admin/v2/dashboard" class="app-brand" :aria-label="appName">
                    <img :src="appLogo" :alt="appName" class="app-brand-logo" />
                    <span class="app-brand-name">{{ appName }}</span>
                </Link>

                <button
                    type="button"
                    class="btn btn-ghost btn-sm btn-icon"
                    :aria-label="isMobile ? (drawerOpen ? 'Close menu' : 'Open menu') : (collapsed ? 'Expand sidebar' : 'Collapse sidebar')"
                    @click="onMenuToggle"
                    :title="isMobile ? 'Menu' : 'Toggle sidebar (collapsed shows icons only)'"
                >
                    <Icon :name="isMobile ? 'menu' : 'panel-left'" :size="16" />
                </button>

                <!-- Search grows to fill the gap between the brand and the action
                     cluster instead of sitting in a fixed 240px box on the right. -->
                <div class="topbar-search-wrap">
                    <button
                        type="button"
                        class="topbar-search"
                        :aria-label="locale === 'ar' ? 'بحث' : 'Search'"
                        @click="openCmd"
                    >
                        <Icon name="search" :size="15" class="topbar-search-icon" />
                        <span class="topbar-search-text">
                            {{ locale === 'ar' ? 'ابحث عن مريض، حجز، طبيب…' : 'Search patients, bookings, doctors…' }}
                        </span>
                        <span class="topbar-search-kbd mono">{{ isMac ? '⌘K' : 'Ctrl K' }}</span>
                    </button>
                </div>

                <div class="topbar-actions" style="display: inline-flex; gap: 4px; align-items: center;">
                    <!-- High-frequency reception actions, reachable from anywhere. -->
                    <QuickCreate v-if="userFlags.is_reception" />

                    <span class="topbar-branch" style="display:inline-flex; align-items:center;"><BranchBadge /></span>

                    <div class="topbar-divider"></div>

                    <button type="button" class="btn btn-ghost btn-sm btn-icon topbar-dark" :aria-label="dark ? 'Light mode' : 'Dark mode'" @click="toggleDark">
                        <Icon :name="dark ? 'sun' : 'moon'" :size="15" />
                    </button>

                    <div class="seg seg-sm topbar-lang" style="height: 30px;">
                        <button type="button" :class="locale === 'en' ? 'is-active' : ''" @click="setLang('en')">EN</button>
                        <button type="button" :class="locale === 'ar' ? 'is-active' : ''" @click="setLang('ar')">ع</button>
                    </div>

                    <NotificationsPopover />

                    <div class="topbar-divider"></div>

                    <UserMenu />
                </div>
            </div>

            <!-- Sub-bar: page context on the start side, live status on the end.
                 Splitting this off row 1 keeps each row uncramped. -->
            <div class="subbar">
                <nav class="subbar-crumbs" aria-label="Breadcrumb">
                    <Icon name="map-pin" :size="13" class="subbar-crumb-icon" />
                    <template v-for="(c, i) in breadcrumb" :key="i">
                        <Icon v-if="i > 0" name="chevron-right" :size="12" class="subbar-crumb-sep" />
                        <span class="subbar-crumb" :class="{ 'is-last': i === breadcrumb.length - 1 }">{{ c }}</span>
                    </template>

                    <!-- Page help — sits right after the breadcrumb so it reads as
                         "you are on X · how to use X". Only shown where help exists. -->
                    <button
                        v-if="helpAvailable"
                        type="button"
                        class="subbar-help"
                        :aria-label="locale === 'ar' ? 'كيفية استخدام هذه الصفحة' : 'How to use this page'"
                        @click="openHelp"
                    >
                        <Icon name="help-circle" :size="14" />
                        <span class="subbar-help-label">{{ locale === 'ar' ? 'كيفية الاستخدام' : 'How to use' }}</span>
                    </button>
                </nav>

                <div class="subbar-status">
                    <SnapshotChips />
                    <ClinicClock />
                </div>
            </div>
        </div>

        <div style="display: flex; flex: 1;">
            <!-- Mobile drawer backdrop: tap to dismiss. -->
            <Transition name="backdrop-fade">
                <div v-if="isMobile && drawerOpen" class="drawer-backdrop" @click="closeDrawer"></div>
            </Transition>

            <!-- Sidebar — sticky column on desktop, off-canvas drawer on mobile. -->
            <aside
                ref="sidebarEl"
                class="sidebar"
                :class="{ 'is-collapsed': collapsed && !isMobile, 'is-drawer': isMobile }"
                @scroll="onSidebarScroll"
                :style="sidebarStyle"
            >
                <!-- Drawer header (mobile only): brand + an obvious close control,
                     since the full-height drawer sits over the topbar toggle. -->
                <div v-if="isMobile" class="drawer-head">
                    <Link href="/admin/v2/dashboard" class="app-brand" :aria-label="appName" @click="closeDrawer">
                        <img :src="appLogo" :alt="appName" class="app-brand-logo" />
                        <span class="app-brand-name" style="display:inline">{{ appName }}</span>
                    </Link>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon" aria-label="Close menu" @click="closeDrawer">
                        <Icon name="x" :size="18" />
                    </button>
                </div>

                <nav class="nav-root" :class="{ 'is-collapsed': collapsed && !isMobile }">
                    <template v-for="section in navSections" :key="section.id">
                        <div v-if="section.sep" class="nav-sep" aria-hidden="true"></div>

                        <!-- Collapsed rail: one icon per section; hover/click shows a flyout. -->
                        <button
                            v-if="collapsed && !isMobile"
                            type="button"
                            class="nav-rail-btn"
                            :class="{ 'is-active': activeSectionId === section.id }"
                            :title="section.label"
                            :aria-label="section.label"
                            @mouseenter="showFlyout(section, $event)"
                            @mouseleave="scheduleFlyoutClose"
                            @click="onRailClick(section, $event)"
                        >
                            <Icon :name="section.icon" :size="18" />
                        </button>

                        <!-- Expanded: collapsible accordion group. -->
                        <div v-else class="nav-section">
                            <button
                                type="button"
                                class="nav-group-header"
                                :class="{ 'has-active': activeSectionId === section.id }"
                                :aria-expanded="isExpanded(section.id)"
                                @click="toggleSection(section.id)"
                            >
                                <Icon :name="section.icon" :size="15" class="nav-group-icon" />
                                <span class="nav-group-label">{{ section.label }}</span>
                                <Icon name="chevron-down" :size="13" class="nav-group-chev" :class="{ 'is-open': isExpanded(section.id) }" />
                            </button>

                            <div v-show="isExpanded(section.id)" class="nav-group-items">
                                <Link
                                    v-for="it in section.items"
                                    :key="it.id"
                                    :href="it.href"
                                    :class="['nav-item', resolvedActive === it.id ? 'is-active' : '']"
                                    :aria-label="it.label"
                                    preserve-scroll
                                    prefetch="click"
                                    @mouseenter="showTip(it, $event)"
                                    @mouseleave="hideTip"
                                >
                                    <Icon :name="it.icon" :size="16" />
                                    <span class="nav-item-label">{{ it.label }}</span>
                                </Link>
                            </div>
                        </div>
                    </template>
                </nav>
            </aside>

            <!-- Collapsed-rail flyout submenu (teleported so the sidebar overflow can't clip it). -->
            <Teleport to="body">
                <div
                    v-if="collapsed && flyout.open && flyoutSection"
                    class="nav-flyout"
                    :style="{ top: flyout.top + 'px', left: flyout.left + 'px', width: FLYOUT_W + 'px' }"
                    @mouseenter="keepFlyout"
                    @mouseleave="scheduleFlyoutClose"
                >
                    <div class="nav-flyout-label">{{ flyoutSection.label }}</div>
                    <Link
                        v-for="it in flyoutSection.items"
                        :key="it.id"
                        :href="it.href"
                        :class="['nav-item', resolvedActive === it.id ? 'is-active' : '']"
                        :aria-label="it.label"
                        preserve-scroll
                        prefetch="click"
                        @click="closeFlyout"
                        @mouseenter="showTip(it, $event)"
                        @mouseleave="hideTip"
                    >
                        <Icon :name="it.icon" :size="16" />
                        <span class="nav-item-label">{{ it.label }}</span>
                    </Link>
                </div>
            </Teleport>

            <!-- Styled hover tooltip for nav links. -->
            <Teleport to="body">
                <div
                    v-if="tip.open"
                    class="nav-tip"
                    :style="{ top: tip.y + 'px', left: tip.x + 'px', width: TIP_W + 'px' }"
                >
                    <div class="nav-tip-title">{{ tip.label }}</div>
                    <div class="nav-tip-body">{{ tip.body }}</div>
                </div>
            </Teleport>

            <main class="app-main" style="flex: 1; min-width: 0;">
                <div v-if="noClinic" style="margin: 16px; padding: 12px 16px; display: flex; align-items: center; gap: 10px; background: var(--warning-soft, oklch(0.96 0.06 80)); border: 1px solid var(--warning, oklch(0.8 0.12 80)); border-radius: 10px; color: var(--fg);">
                    <Icon name="alert-triangle" :size="16" />
                    <span style="font-size: 13px;">{{ noClinicMsg }}</span>
                </div>
                <slot />
            </main>
        </div>

        <FlashToasts />
        <NotificationPoller />
        <CommandPalette v-model:open="cmdOpen" />

        <!-- "How to use this page" — dedicated v2 help slide-over. -->
        <HelpDrawer
            v-model:open="helpOpen"
            :page-key="helpKey"
            :page-title="activeItemLabel"
        />

        <!-- Global confirm dialog — driven by the useConfirm() composable. -->
        <ConfirmDialog
            v-model:open="confirmState.open"
            :title="confirmState.opts.title"
            :body="confirmState.opts.body"
            :confirm-label="confirmState.opts.confirmLabel"
            :cancel-label="confirmState.opts.cancelLabel"
            :tone="confirmState.opts.tone"
            :icon="confirmState.opts.icon"
            @confirm="resolveConfirm"
            @cancel="cancelConfirm"
        />
    </div>
</template>

<style scoped>
.app-brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: inherit;
    min-width: 0;
    padding: 4px 8px;
    margin-inline-start: -8px;
    border-radius: 8px;
    transition: background 0.12s;
}
.app-brand:hover { background: var(--bg-hover); }
.app-brand-logo {
    height: 28px;
    width: auto;
    max-width: 120px;
    object-fit: contain;
    flex-shrink: 0;
}
.app-brand-name {
    font-size: 14px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--fg);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (max-width: 720px) {
    .app-brand-name { display: none; }
}

/* Topbar search — grows to fill the space between brand and actions, capped so
   it stays a comfortable reading width on ultra-wide screens and centred in the
   remaining room. */
.topbar-search-wrap {
    flex: 1;
    display: flex;
    justify-content: center;
    min-width: 0;
}
.topbar-search {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    max-width: 560px;
    height: 38px;
    padding: 0 12px;
    border-radius: 10px;
    border: 1px solid var(--line);
    background: var(--bg-sunken);
    color: var(--fg-subtle);
    font-family: inherit;
    font-size: 13px;
    cursor: text;
    transition: background 0.12s, border-color 0.12s, box-shadow 0.12s;
}
.topbar-search:hover {
    background: var(--bg-elev);
    border-color: var(--line-strong);
}
.topbar-search:focus-visible {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
}
.topbar-search-icon { flex-shrink: 0; opacity: 0.75; }
.topbar-search-text {
    flex: 1;
    text-align: start;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.topbar-search-kbd {
    flex-shrink: 0;
    font-size: 11px;
    padding: 2px 6px;
    border: 1px solid var(--line);
    border-radius: 5px;
    background: var(--bg-elev);
    color: var(--fg-faint);
    line-height: 1.2;
}
@media (max-width: 640px) {
    .topbar-search { max-width: none; height: 34px; }
    /* Keep the placeholder text — there's room for a real search bar now that
       branch/theme/language no longer sit in the header. Only the ⌘K hint is
       dropped (no hardware keyboard on a phone). */
    .topbar-search-kbd { display: none; }
}

/* Thin vertical rule used to separate clusters in the action area. */
.topbar-divider {
    width: 1px;
    height: 24px;
    background: var(--line);
    margin-inline-start: 4px;
    margin-inline-end: 4px;
}

/* Combined sticky topbar height (row 1 = 56px + sub-bar = 40px). The sidebar
   reads this so it sticks right below the whole header block. */
.app-shell { --topbar-h: 96px; }

/* Sub-bar (row 2): breadcrumb start-side, live status end-side. Slightly sunken
   so it reads as secondary context under the main topbar. */
.subbar {
    height: 40px;
    padding: 0 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-top: 1px solid var(--line);
    background: var(--bg-sunken);
}
.subbar-crumbs {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    overflow: hidden;
}
.subbar-crumb-icon { flex-shrink: 0; color: var(--fg-faint); }
.subbar-crumb-sep { flex-shrink: 0; color: var(--fg-faint); opacity: 0.7; }
.subbar-crumb {
    font-size: 12.5px;
    color: var(--fg-subtle);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.subbar-crumb.is-last { color: var(--fg); font-weight: 600; }
.subbar-help {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-inline-start: 8px;
    padding: 3px 10px 3px 8px;
    height: 24px;
    border: 1px solid var(--line);
    border-radius: 9999px;
    background: var(--bg);
    color: var(--fg-subtle);
    font-family: inherit;
    font-size: 11.5px;
    font-weight: 500;
    line-height: 1;
    cursor: pointer;
    transition: color 0.12s, border-color 0.12s, background 0.12s;
}
.subbar-help:hover {
    color: var(--primary);
    border-color: var(--primary);
    background: var(--primary-soft);
}
.subbar-help:focus-visible {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
}
@media (max-width: 640px) {
    .subbar-help-label { display: none; }
    .subbar-help { padding: 3px; width: 24px; justify-content: center; }
}
.subbar-status {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

/* Nav container */
.nav-root {
    padding: 10px 8px;
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.nav-root.is-collapsed {
    padding: 10px 0;
    align-items: center;
    gap: 4px;
}
.nav-sep {
    height: 1px;
    background: var(--line);
    margin: 8px 10px;
}
.nav-root.is-collapsed .nav-sep {
    width: 22px;
    margin: 6px auto;
}

/* Accordion group header (expanded sidebar) */
.nav-group-header {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 7px 12px;
    border: 0;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    color: var(--fg-faint);
    font-size: 10.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    transition: background 0.12s, color 0.12s;
}
.nav-group-header:hover { background: var(--bg-hover); color: var(--fg-subtle); }
.nav-group-header.has-active { color: var(--accent); }
.nav-group-icon { flex-shrink: 0; opacity: 0.85; }
.nav-group-label { flex: 1; text-align: start; }
.nav-group-chev { flex-shrink: 0; opacity: 0.6; transition: transform 0.16s ease; }
.nav-group-chev.is-open { transform: rotate(180deg); }
.nav-group-items {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 2px 0 6px;
}

/* Collapsed rail: one button per section */
.nav-rail-btn {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    background: transparent;
    border-radius: 10px;
    color: var(--fg-subtle);
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
}
.nav-rail-btn:hover { background: var(--bg-hover); color: var(--fg); }
.nav-rail-btn.is-active { background: var(--accent-bg); color: var(--accent); }

/* Nav item (used by accordion items AND the collapsed flyout) */
.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    color: var(--fg-subtle);
    text-decoration: none;
    font-size: 13px;
    line-height: 1;
    transition: background 0.12s, color 0.12s;
}
.nav-item-label {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.nav-item:hover {
    background: var(--bg-hover);
    color: var(--fg);
}
.nav-item.is-active {
    background: var(--accent-bg);
    color: var(--accent);
    font-weight: 600;
}

/* Collapsed-rail flyout submenu (teleported to body) */
.nav-flyout {
    position: fixed;
    z-index: 60;
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    padding: 6px;
    max-height: calc(100vh - 24px);
    overflow-y: auto;
}
.nav-flyout-label {
    padding: 6px 10px 5px;
    font-size: 10.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--fg-faint);
}

/* Styled hover tooltip card for nav links */
.nav-tip {
    position: fixed;
    z-index: 80;
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 10px;
    box-shadow: var(--shadow-lg);
    padding: 10px 12px;
    pointer-events: none;
    animation: nav-tip-in 0.13s ease-out;
}
.nav-tip-title {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--fg);
    letter-spacing: -0.01em;
    margin-bottom: 4px;
}
.nav-tip-body {
    font-size: 12px;
    line-height: 1.55;
    color: var(--fg-subtle);
}
@keyframes nav-tip-in {
    from { opacity: 0; transform: translateY(3px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ─── Mobile off-canvas drawer ─────────────────────────────────────────────
   Below lg the sidebar (see sidebarStyle) slides in over this backdrop. */
.drawer-backdrop {
    position: fixed;
    inset: 0;
    background: oklch(0.18 0.02 260 / 0.42);
    -webkit-backdrop-filter: blur(2px);
    backdrop-filter: blur(2px);
    z-index: 55;
}
.backdrop-fade-enter-active,
.backdrop-fade-leave-active { transition: opacity 0.2s ease; }
.backdrop-fade-enter-from,
.backdrop-fade-leave-to { opacity: 0; }

/* Drawer header: brand on the start side, close on the end. */
.drawer-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    height: 56px;
    padding: 0 10px 0 12px;
    border-bottom: 1px solid var(--line);
    position: sticky;
    top: 0;
    background: var(--bg);
    z-index: 1;
}

/* Tighten the header on small screens so the action cluster never overflows
   and we don't waste horizontal room on a phone. */
@media (max-width: 1023px) {
    .glass-strip > div:first-child { padding: 0 12px !important; gap: 10px !important; }
    .subbar { padding: 0 12px; }
}
/* The branch is already shown inside the account menu, so the standalone branch
   pill is redundant on tablets/phones — drop it to free the most space. */
@media (max-width: 768px) {
    .topbar-branch { display: none !important; }
}
@media (max-width: 640px) {
    .topbar-actions { gap: 4px; }
    .topbar-actions .topbar-divider { display: none; }
    /* Drop the live status (snapshot chips + clock) on phones; the breadcrumb
       stays so the user still knows where they are. */
    .subbar-status { display: none; }
    /* Search fills the space freed by moving branch/theme/language out, so the
       header is a balanced row: brand + menu, a full-width search bar, then the
       action cluster — no dead space, no cramped icon. */
    .topbar-search-wrap { justify-content: stretch; }
    .topbar-search { width: 100%; max-width: none; }
    /* Theme + language move into the account menu on phones (see UserMenu's
       mobile preferences section) so the header keeps only its essentials. */
    .topbar-dark { display: none !important; }
    .topbar-lang { display: none !important; }
}
</style>
