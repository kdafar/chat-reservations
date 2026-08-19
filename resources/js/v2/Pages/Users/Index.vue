<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    roles: { type: Array, required: true },
    branches: { type: Array, default: () => [] },
    counts: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        title: 'المستخدمون', eyebrow: 'المنصة',
        desc: 'حسابات المستخدمين والصلاحيات. هذه الشاشة للمسؤولين فقط.',
        searchPh: 'ابحث بالاسم، البريد، أو الهاتف…',
        new: 'مستخدم جديد',
        status: { all: 'الكل', active: 'فعّال', inactive: 'غير فعّال' },
        col: { name: 'الاسم', email: 'البريد', phone: 'الهاتف', roles: 'الأدوار', branches: 'الفروع', status: 'الحالة' },
        empty: 'لا يوجد مستخدمون', emptyDesc: 'أنشئ حسابًا للموظفين.',
        clear: 'مسح', allRoles: 'كل الأدوار', allBranches: 'كل الفروع', previous: 'السابق', next: 'التالي', showing: 'عرض', of: 'من',
        modal: {
            createTitle: 'مستخدم جديد', editTitle: 'تحرير المستخدم',
            name: 'الاسم', email: 'البريد', phone: 'الهاتف', status: 'الحالة',
            password: 'كلمة المرور', passwordEdit: 'كلمة مرور جديدة (اتركها فارغة لتركها كما هي)',
            roles: 'الأدوار', branches: 'الفروع (نطاق الوصول)',
            branchesHint: 'يحدّد الفروع التي يرى المستخدم بياناتها. فرع الطبيب الأساسي محفوظ دائمًا.',
            noBranches: 'لا توجد فروع متاحة.',
            save: 'حفظ', cancel: 'إلغاء',
            deleteConfirm: 'سيتم تعطيل هذا الحساب. متابعة؟',
        },
        stats: { total: 'الكل', active: 'فعّال', inactive: 'غير فعّال', unassigned: 'بدون فرع' },
        bulk: {
            selected: 'محدد', clear: 'إلغاء التحديد',
            manageBranches: 'إدارة الفروع',
            title: 'إدارة فروع المستخدمين المحددين',
            mode: 'الإجراء',
            add: 'إضافة إلى الفروع', remove: 'إزالة من الفروع', replace: 'استبدال الفروع',
            addHint: 'تُضاف الفروع المحددة دون المساس بالفروع الحالية.',
            removeHint: 'تُزال الفروع المحددة فقط. فرع الطبيب الأساسي محفوظ.',
            replaceHint: 'يُستبدل نطاق الفروع بالكامل بما هو محدد. الاختيار الفارغ يزيل كل الفروع.',
            apply: 'تطبيق', pickBranch: 'اختر فرعًا واحدًا على الأقل.',
            confirmReplace: 'سيتم استبدال نطاق الفروع للمستخدمين المحددين. متابعة؟',
        },
    }
    : {
        title: 'Users', eyebrow: 'Platform',
        desc: "Staff user accounts and roles. This screen is admin-only.",
        searchPh: 'Search by name, email, or phone…',
        new: 'New user',
        status: { all: 'All', active: 'Active', inactive: 'Inactive' },
        col: { name: 'Name', email: 'Email', phone: 'Phone', roles: 'Roles', branches: 'Branches', status: 'Status' },
        empty: 'No users', emptyDesc: 'Create an account for your team.',
        clear: 'Clear', allRoles: 'All roles', allBranches: 'All branches', previous: 'Previous', next: 'Next', showing: 'Showing', of: 'of',
        modal: {
            createTitle: 'New user', editTitle: 'Edit user',
            name: 'Name', email: 'Email', phone: 'Phone', status: 'Status',
            password: 'Password', passwordEdit: 'New password (leave empty to keep current)',
            roles: 'Roles', branches: 'Branches (access scope)',
            branchesHint: "Controls which branches' data this user can see. A doctor's own branch is always kept.",
            noBranches: 'No branches available.',
            save: 'Save', cancel: 'Cancel',
            deleteConfirm: 'Deactivate this account?',
        },
        stats: { total: 'Total', active: 'Active', inactive: 'Inactive', unassigned: 'No branch' },
        bulk: {
            selected: 'selected', clear: 'Clear selection',
            manageBranches: 'Manage branches',
            title: 'Manage branches for selected users',
            mode: 'Action',
            add: 'Add to branches', remove: 'Remove from branches', replace: 'Replace branches',
            addHint: 'The selected branches are added; existing ones are left alone.',
            removeHint: "Only the selected branches are removed. A doctor's own branch is kept.",
            replaceHint: 'The whole branch scope is replaced by your selection. An empty selection clears all branches.',
            apply: 'Apply', pickBranch: 'Pick at least one branch.',
            confirmReplace: 'This replaces the branch scope for the selected users. Continue?',
        },
    })

const roleItems = computed(() => props.roles.map((r) => ({ value: r.name, label: r.name })))
const branchItems = computed(() => props.branches.map((b) => ({ value: b.id, label: b.name })))

const f = reactive({
    q: props.filters.q || '',
    role: props.filters.role || '',
    status: props.filters.status || 'all',
    branch: props.filters.branch || '',
})
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.role, f.status, f.branch], () => apply(), { deep: true })

function apply() {
    router.get(route('v2.users.index'), {
        q: f.q || undefined, role: f.role || undefined,
        status: f.status === 'all' ? undefined : f.status,
        branch: f.branch || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.role = ''; f.status = 'all'; f.branch = ''; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const form = reactive({
    name: '', email: '', phone: '', status: 'active', password: '', roles: [], branches: [],
})
const errors = ref({})
const saving = ref(false)

function openCreate() {
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, { name: '', email: '', phone: '', status: 'active', password: '', roles: [], branches: [] })
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        name: row.name, email: row.email, phone: row.phone || '',
        status: row.status || 'active', password: '',
        roles: (row.roles || []).map(r => r.name),
        branches: (row.branches || []).map(b => b.id),
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create'
        ? route('v2.users.store')
        : route('v2.users.update', { user: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    const payload = { ...form }
    if (modalMode.value === 'edit' && !payload.password) delete payload.password
    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}

function deactivate(row) {
    confirm({ body: t.value.modal.deleteConfirm, tone: 'destructive', onConfirm: () => {
        router.delete(route('v2.users.destroy', { user: row.id }), { preserveScroll: true })
    } })
}

function toggleRole(name) {
    const i = form.roles.indexOf(name)
    if (i === -1) form.roles.push(name); else form.roles.splice(i, 1)
}
function toggleBranch(id) {
    const i = form.branches.indexOf(id)
    if (i === -1) form.branches.push(id); else form.branches.splice(i, 1)
}

/* ---- bulk branch assignment ---------------------------------------- */

const selected = ref([])
// A page change swaps the rows underneath us; drop any id that's no longer here.
watch(() => props.page.data, (rows) => {
    const ids = new Set(rows.map(r => r.id))
    selected.value = selected.value.filter(id => ids.has(id))
})
const allChecked = computed(() => props.page.data.length > 0 && selected.value.length === props.page.data.length)
function toggleAll() {
    selected.value = allChecked.value ? [] : props.page.data.map(r => r.id)
}
function toggleRow(id) {
    const i = selected.value.indexOf(id)
    if (i === -1) selected.value.push(id); else selected.value.splice(i, 1)
}

const bulkOpen = ref(false)
const bulkSaving = ref(false)
const bulkError = ref('')
const bulk = reactive({ mode: 'add', branches: [] })

function openBulk() {
    Object.assign(bulk, { mode: 'add', branches: [] })
    bulkError.value = ''; bulkOpen.value = true
}
function closeBulk() { bulkOpen.value = false; bulkSaving.value = false }
function toggleBulkBranch(id) {
    const i = bulk.branches.indexOf(id)
    if (i === -1) bulk.branches.push(id); else bulk.branches.splice(i, 1)
}
const bulkHint = computed(() => t.value.bulk[`${bulk.mode}Hint`])

function submitBulk() {
    bulkError.value = ''
    // Only "replace" is meaningful with an empty selection (it clears the scope).
    if (bulk.branches.length === 0 && bulk.mode !== 'replace') {
        bulkError.value = t.value.bulk.pickBranch
        return
    }
    const send = () => {
        bulkSaving.value = true
        router.post(route('v2.users.bulk-branches'), {
            user_ids: selected.value, mode: bulk.mode, branches: bulk.branches,
        }, {
            preserveScroll: true,
            onSuccess: () => { closeBulk(); selected.value = [] },
            onError: (errs) => { bulkError.value = Object.values(errs)[0] || ''; bulkSaving.value = false },
            onFinish: () => { bulkSaving.value = false },
        })
    }
    if (bulk.mode === 'replace') {
        confirm({ body: t.value.bulk.confirmReplace, tone: 'destructive', onConfirm: send })
    } else {
        send()
    }
}
</script>

<template>
    <Head :title="t.title" />

        <div style="padding: 24px; max-width: 1280px; margin: 0 auto;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">{{ t.eyebrow }}</div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <ImportButton type="users" />
                    <a class="btn btn-sm btn-outline" :href="route('v2.users.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" style="color:var(--fg-faint);">{{ counts.inactive }}</span><span class="stat-chip-lbl">{{ t.stats.inactive }}</span></div>
                <div class="stat-chip"><span class="stat-chip-num" :style="counts.unassigned ? 'color:var(--warn, #d97706);' : ''">{{ counts.unassigned }}</span><span class="stat-chip-lbl">{{ t.stats.unassigned }}</span></div>
            </div>

            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
                </div>
                <SearchableSelect v-model="f.role" :items="roleItems" :null-label="t.allRoles" :width="200" />
                <SearchableSelect v-model="f.branch" :items="branchItems" :null-label="t.allBranches" :width="200" />
                <div class="seg seg-sm">
                    <button :class="f.status === 'all' ? 'is-active' : ''" @click="f.status = 'all'">{{ t.status.all }}</button>
                    <button :class="f.status === 'active' ? 'is-active' : ''" @click="f.status = 'active'">{{ t.status.active }}</button>
                    <button :class="f.status === 'inactive' ? 'is-active' : ''" @click="f.status = 'inactive'">{{ t.status.inactive }}</button>
                </div>
                <button v-if="f.q || f.role || f.branch || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
            </div>

            <div v-if="selected.length" class="card bulk-bar">
                <span style="font-size:13px; font-weight:600;">{{ selected.length }} {{ t.bulk.selected }}</span>
                <button class="btn btn-sm btn-primary" @click="openBulk"><Icon name="building" :size="13" /><span>{{ t.bulk.manageBranches }}</span></button>
                <button class="btn btn-sm btn-ghost" @click="selected = []">{{ t.bulk.clear }}</button>
            </div>

            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:36px;" @click.stop>
                                <input type="checkbox" :checked="allChecked" @change="toggleAll" />
                            </th>
                            <th>{{ t.col.name }}</th>
                            <th>{{ t.col.email }}</th>
                            <th>{{ t.col.phone }}</th>
                            <th>{{ t.col.roles }}</th>
                            <th>{{ t.col.branches }}</th>
                            <th>{{ t.col.status }}</th>
                            <th style="width:80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="8" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="users" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr v-for="row in page.data" :key="row.id" @click="openEdit(row)" style="cursor:pointer;">
                            <td @click.stop>
                                <input type="checkbox" :checked="selected.includes(row.id)" @change="toggleRow(row.id)" />
                            </td>
                            <td style="font-weight:600;">{{ row.name }}</td>
                            <td>{{ row.email }}</td>
                            <td class="mono" style="font-size:12px;">{{ row.phone || '—' }}</td>
                            <td>
                                <span v-for="r in (row.roles || [])" :key="r.id" class="badge-role">{{ r.name }}</span>
                                <span v-if="!(row.roles || []).length" style="color:var(--fg-faint);">—</span>
                            </td>
                            <td>
                                <span v-for="b in (row.branches || [])" :key="b.id" class="badge-role">{{ b.name }}</span>
                                <span v-if="!(row.branches || []).length" class="badge-warn">{{ t.stats.unassigned }}</span>
                            </td>
                            <td>
                                <span :class="(row.status === 'active') ? 'badge-ok' : 'badge-muted'">
                                    {{ row.status || '—' }}
                                </span>
                            </td>
                            <td @click.stop>
                                <button v-if="row.status === 'active'" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.deleteConfirm" @click="deactivate(row)">
                                    <Icon name="user-x" :size="14" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
                <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
                <div style="display:flex; gap:4px;">
                    <a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                       :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                       style="min-width:32px;" />
                </div>
            </div>
        </div>

        <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:75vh; overflow-y:auto;">
                    <div>
                        <label class="label">{{ t.modal.name }} <span class="req">*</span></label>
                        <input v-model="form.name" type="text" class="input" required maxlength="255" />
                        <div v-if="errors.name" class="err">{{ errors.name }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.email }} <span class="req">*</span></label>
                        <input v-model="form.email" type="email" class="input" required maxlength="191" />
                        <div v-if="errors.email" class="err">{{ errors.email }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.phone }}</label>
                        <input v-model="form.phone" type="text" class="input" maxlength="32" />
                    </div>
                    <div>
                        <label class="label">{{ t.modal.status }}</label>
                        <SearchableSelect v-model="form.status" :nullable="false" :items="[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }, { value: 'suspended', label: 'Suspended' }]" />
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ modalMode === 'edit' ? t.modal.passwordEdit : t.modal.password }} <span v-if="modalMode === 'create'" class="req">*</span></label>
                        <input v-model="form.password" type="password" class="input" :required="modalMode === 'create'" minlength="8" autocomplete="new-password" />
                        <div v-if="errors.password" class="err">{{ errors.password }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.roles }}</label>
                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                            <label v-for="r in roles" :key="r.id" class="role-check">
                                <input type="checkbox" :checked="form.roles.includes(r.name)" @change="toggleRole(r.name)" />
                                <span>{{ r.name }}</span>
                            </label>
                        </div>
                    </div>

                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.branches }}</label>
                        <div v-if="branches.length" style="display:flex; flex-wrap:wrap; gap:8px;">
                            <label v-for="b in branches" :key="b.id" class="role-check">
                                <input type="checkbox" :checked="form.branches.includes(b.id)" @change="toggleBranch(b.id)" />
                                <span>{{ b.name }}</span>
                            </label>
                        </div>
                        <div v-else style="font-size:12px; color:var(--fg-faint);">{{ t.modal.noBranches }}</div>
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:6px;">{{ t.modal.branchesHint }}</div>
                        <div v-if="errors.branches" class="err">{{ errors.branches }}</div>
                    </div>

                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="bulkOpen" class="modal-backdrop" @click.self="closeBulk">
            <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:560px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.bulk.title }}</h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeBulk"><Icon name="x" :size="14" /></button>
                </div>
                <form @submit.prevent="submitBulk" style="padding:16px; display:flex; flex-direction:column; gap:12px; max-height:75vh; overflow-y:auto;">
                    <div style="font-size:13px; color:var(--fg-subtle);">{{ selected.length }} {{ t.bulk.selected }}</div>
                    <div>
                        <label class="label">{{ t.bulk.mode }}</label>
                        <div class="seg seg-sm">
                            <button type="button" :class="bulk.mode === 'add' ? 'is-active' : ''" @click="bulk.mode = 'add'">{{ t.bulk.add }}</button>
                            <button type="button" :class="bulk.mode === 'remove' ? 'is-active' : ''" @click="bulk.mode = 'remove'">{{ t.bulk.remove }}</button>
                            <button type="button" :class="bulk.mode === 'replace' ? 'is-active' : ''" @click="bulk.mode = 'replace'">{{ t.bulk.replace }}</button>
                        </div>
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:6px;">{{ bulkHint }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.col.branches }}</label>
                        <div v-if="branches.length" style="display:flex; flex-wrap:wrap; gap:8px;">
                            <label v-for="b in branches" :key="b.id" class="role-check">
                                <input type="checkbox" :checked="bulk.branches.includes(b.id)" @change="toggleBulkBranch(b.id)" />
                                <span>{{ b.name }}</span>
                            </label>
                        </div>
                        <div v-else style="font-size:12px; color:var(--fg-faint);">{{ t.modal.noBranches }}</div>
                    </div>
                    <div v-if="bulkError" class="err">{{ bulkError }}</div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="closeBulk">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="bulkSaving">{{ bulkSaving ? '…' : t.bulk.apply }}</button>
                    </div>
                </form>
            </div>
        </div>
</template>

<style scoped>
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-role { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid var(--line); background:var(--bg-hover); color:var(--fg-subtle); border-radius:999px; margin-inline-end:4px; }
.badge-ok { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
.badge-muted { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; }
.badge-warn { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid var(--warn, #d97706); color:var(--warn, #d97706); border-radius:999px; }
.bulk-bar { display:flex; align-items:center; gap:10px; padding:10px 12px; margin-bottom:12px; }
.role-check { display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:6px 10px; border:1px solid var(--line); border-radius:6px; cursor:pointer; }
.role-check:hover { background:var(--bg-hover); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:640px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
