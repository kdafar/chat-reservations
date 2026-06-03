<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    roles: { type: Array, required: true },
    permissionGroups: { type: Array, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        title: 'الأدوار والصلاحيات', eyebrow: 'التحكم بالوصول',
        desc: 'حدّد ما يمكن لكل دور رؤيته والقيام به. للمسؤولين فقط.',
        searchPh: 'ابحث عن دور…', new: 'دور جديد',
        col: { name: 'الدور', perms: 'الصلاحيات', users: 'المستخدمون' },
        empty: 'لا توجد أدوار', protected: 'محمي',
        clear: 'مسح',
        modal: {
            createTitle: 'دور جديد', editTitle: 'تحرير الدور',
            name: 'اسم الدور', permissions: 'الصلاحيات',
            save: 'حفظ', cancel: 'إلغاء', delete: 'حذف',
            searchPerms: 'ابحث في الصلاحيات…', selectedOf: 'محدد',
            all: 'الكل', none: 'لا شيء', expandAll: 'توسيع', collapseAll: 'طي',
            deleteConfirm: 'حذف هذا الدور؟ لا يمكن التراجع.',
            protectedHint: 'هذا دور أساسي — لا يمكن إعادة تسميته أو حذفه، لكن يمكن تعديل صلاحياته.',
        },
    }
    : {
        title: 'Roles & Permissions', eyebrow: 'Access Control',
        desc: 'Define what each role can see and do. Admin-only.',
        searchPh: 'Search roles…', new: 'New role',
        col: { name: 'Role', perms: 'Permissions', users: 'Users' },
        empty: 'No roles', protected: 'Protected',
        clear: 'Clear',
        modal: {
            createTitle: 'New role', editTitle: 'Edit role',
            name: 'Role name', permissions: 'Permissions',
            save: 'Save', cancel: 'Cancel', delete: 'Delete',
            searchPerms: 'Filter permissions…', selectedOf: 'selected',
            all: 'All', none: 'None', expandAll: 'Expand all', collapseAll: 'Collapse all',
            deleteConfirm: 'Delete this role? This cannot be undone.',
            protectedHint: 'This is a core role — it can\'t be renamed or deleted, but its permissions are editable.',
        },
    })

// ---- filters ----
const f = reactive({ q: props.filters.q || '' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
function apply() {
    router.get(route('v2.roles.index'), { q: f.q || undefined }, { preserveState: true, preserveScroll: true, replace: true })
}

// ---- modal / form ----
const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const form = reactive({ name: '', permissions: [] })
const errors = ref({})
const saving = ref(false)
const permFilter = ref('')
const collapsed = reactive({})

const selected = computed(() => new Set(form.permissions))

const visibleGroups = computed(() => {
    const needle = permFilter.value.trim().toLowerCase()
    if (!needle) return props.permissionGroups
    return props.permissionGroups
        .map(g => ({
            ...g,
            permissions: g.permissions.filter(p =>
                p.name.toLowerCase().includes(needle) ||
                p.action.toLowerCase().includes(needle) ||
                g.label.toLowerCase().includes(needle)),
        }))
        .filter(g => g.permissions.length)
})

function openCreate() {
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, { name: '', permissions: [] })
    errors.value = {}; permFilter.value = ''; modalOpen.value = true
}
function openEdit(role) {
    modalMode.value = 'edit'; editing.value = role
    Object.assign(form, { name: role.name, permissions: [...role.permissions] })
    errors.value = {}; permFilter.value = ''; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function togglePerm(name) {
    const i = form.permissions.indexOf(name)
    if (i === -1) form.permissions.push(name); else form.permissions.splice(i, 1)
}
function groupState(g) {
    const total = g.permissions.length
    const on = g.permissions.filter(p => selected.value.has(p.name)).length
    return { total, on, all: on === total && total > 0, some: on > 0 && on < total }
}
function toggleGroup(g, value) {
    const names = g.permissions.map(p => p.name)
    if (value) {
        const set = new Set(form.permissions)
        names.forEach(n => set.add(n))
        form.permissions = [...set]
    } else {
        form.permissions = form.permissions.filter(n => !names.includes(n))
    }
}
function expandAll(v) { props.permissionGroups.forEach(g => { collapsed[g.key] = v }) }

function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create'
        ? route('v2.roles.store')
        : route('v2.roles.update', { role: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}
function destroy(role) {
    if (!window.confirm(t.value.modal.deleteConfirm)) return
    router.delete(route('v2.roles.destroy', { role: role.id }), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t.title" />

    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
            </div>
            <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:240px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <button v-if="f.q" class="btn btn-ghost btn-sm" @click="f.q = ''">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.name }}</th>
                        <th style="width:140px;">{{ t.col.perms }}</th>
                        <th style="width:120px;">{{ t.col.users }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!roles.length">
                        <td colspan="4" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                            <Icon name="shield" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                            <div style="font-weight:600;">{{ t.empty }}</div>
                        </td>
                    </tr>
                    <tr v-for="role in roles" :key="role.id" @click="openEdit(role)" style="cursor:pointer;">
                        <td style="font-weight:600;">
                            {{ role.name }}
                            <span v-if="role.protected" class="badge-muted" style="margin-inline-start:8px;">{{ t.protected }}</span>
                        </td>
                        <td><span class="badge-info">{{ role.permissions_count }}</span></td>
                        <td class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ role.users_count }}</td>
                        <td @click.stop>
                            <button v-if="!role.protected && role.users_count === 0" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.delete" @click="destroy(role)">
                                <Icon name="trash-2" :size="14" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Editor modal -->
    <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:820px; display:flex; flex-direction:column; max-height:88vh;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line); flex-shrink:0;">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
            </div>

            <div style="padding:16px; overflow-y:auto;">
                <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                    <div style="flex:1; min-width:220px;">
                        <label class="label">{{ t.modal.name }} <span class="req">*</span></label>
                        <input v-model="form.name" type="text" class="input" :disabled="editing?.protected" required maxlength="191" />
                        <div v-if="errors.name" class="err">{{ errors.name }}</div>
                    </div>
                    <div style="font-size:12px; color:var(--fg-subtle); padding-bottom:9px;">
                        {{ form.permissions.length }} {{ t.modal.selectedOf }}
                    </div>
                </div>
                <div v-if="editing?.protected" style="margin-top:8px; font-size:12px; color:var(--fg-subtle); background:var(--bg-hover); border:1px solid var(--line); border-radius:8px; padding:8px 10px;">
                    <Icon name="info" :size="13" style="vertical-align:-2px; margin-inline-end:4px;" />{{ t.modal.protectedHint }}
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin:16px 0 8px; gap:8px; flex-wrap:wrap;">
                    <label class="label" style="margin:0;">{{ t.modal.permissions }}</label>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn btn-ghost btn-sm" @click="expandAll(false)">{{ t.modal.expandAll }}</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="expandAll(true)">{{ t.modal.collapseAll }}</button>
                    </div>
                </div>

                <div style="position:relative; margin-bottom:10px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input v-model="permFilter" type="search" :placeholder="t.modal.searchPerms" class="input" style="padding-inline-start:32px;" />
                </div>

                <div class="perm-groups">
                    <div v-for="g in visibleGroups" :key="g.key" class="perm-group">
                        <div class="perm-group-head">
                            <button type="button" class="perm-group-toggle" @click="collapsed[g.key] = !collapsed[g.key]">
                                <Icon :name="collapsed[g.key] ? 'chevron-right' : 'chevron-down'" :size="14" />
                                <span style="font-weight:600;">{{ g.label }}</span>
                                <span class="perm-count">{{ groupState(g).on }}/{{ groupState(g).total }}</span>
                            </button>
                            <div style="display:flex; gap:4px;">
                                <button type="button" class="btn btn-ghost btn-sm" :class="groupState(g).all ? 'is-active' : ''" @click="toggleGroup(g, true)">{{ t.modal.all }}</button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="toggleGroup(g, false)">{{ t.modal.none }}</button>
                            </div>
                        </div>
                        <div v-if="!collapsed[g.key]" class="perm-grid">
                            <label v-for="p in g.permissions" :key="p.name" class="perm-check" :class="selected.has(p.name) ? 'is-on' : ''">
                                <input type="checkbox" :checked="selected.has(p.name)" @change="togglePerm(p.name)" />
                                <span class="perm-action">{{ p.action }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; gap:8px; padding:12px 16px; border-top:1px solid var(--line); flex-shrink:0;">
                <button v-if="modalMode === 'edit' && !editing?.protected && editing?.users_count === 0" type="button" class="btn btn-ghost" style="color:var(--err, #dc2626);" @click="destroy(editing)">{{ t.modal.delete }}</button>
                <div style="flex:1;"></div>
                <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                <button type="button" class="btn btn-primary" :disabled="saving" @click="submit">{{ saving ? '…' : t.modal.save }}</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-info { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--accent, #2563eb); color:var(--accent, #2563eb); border-radius:999px; }
.badge-muted { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
.perm-groups { display:flex; flex-direction:column; gap:8px; }
.perm-group { border:1px solid var(--line); border-radius:8px; overflow:hidden; }
.perm-group-head { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:6px 8px 6px 4px; background:var(--bg-hover); }
.perm-group-toggle { display:inline-flex; align-items:center; gap:6px; background:none; border:none; cursor:pointer; color:var(--fg); font-size:13px; padding:4px 6px; }
.perm-count { font-size:11px; color:var(--fg-faint); font-family:var(--mono, monospace); }
.perm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:4px; padding:8px; }
.perm-check { display:inline-flex; align-items:center; gap:6px; font-size:12px; padding:5px 8px; border:1px solid var(--line); border-radius:6px; cursor:pointer; text-transform:capitalize; }
.perm-check:hover { background:var(--bg-hover); }
.perm-check.is-on { border-color:var(--accent, #2563eb); background:var(--accent-bg, rgba(37,99,235,0.08)); color:var(--accent, #2563eb); }
.perm-action { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
</style>
