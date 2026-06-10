<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    branches: { type: Array, required: true },
    counts: { type: Object, required: true },
    can_edit: { type: Boolean, default: false },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        title: 'كتالوج الاختبارات', eyebrow: 'المختبر',
        desc: 'إدارة الاختبارات المتاحة في العيادة. الأطباء يطلبونها من شاشة الزيارة.',
        searchPh: 'ابحث بالكود، الاسم، أو نوع العينة…',
        new: 'اختبار جديد',
        active: { all: 'الكل', active: 'فعّال', inactive: 'مؤرشف' },
        branch: { all: 'كل الفروع', shared: 'مشترك' },
        clear: 'مسح',
        col: { code: 'الكود', name: 'الاسم', specimen: 'العينة', unit: 'الوحدة', range: 'القيم المرجعية', price: 'السعر', branch: 'الفرع' },
        empty: 'لا توجد اختبارات بعد',
        emptyDesc: 'أضف اختبارًا للبدء.',
        previous: 'السابق', next: 'التالي',
        showing: 'عرض', of: 'من',
        modal: {
            createTitle: 'اختبار جديد', editTitle: 'تحرير الاختبار',
            code: 'الكود', codeHint: 'مثال: CBC، GLU، HBA1C',
            name: 'الاسم', branch: 'الفرع (اتركه فارغًا = كل الفروع)',
            specimen: 'نوع العينة', unit: 'الوحدة', range: 'القيم المرجعية',
            price: 'السعر الافتراضي (د.ك)', active: 'فعّال',
            save: 'حفظ', cancel: 'إلغاء', archive: 'أرشفة', restore: 'استعادة',
            archiveConfirm: 'سيتم إخفاؤه من قوائم الطلب الجديدة. النتائج التاريخية ستظل ظاهرة.',
        },
        stats: { total: 'الكل', active: 'فعّال', inactive: 'مؤرشف' },
    }
    : {
        title: 'Lab Test Catalog', eyebrow: 'Laboratory',
        desc: 'Manage the tests the clinic offers. Doctors order them from the visit screen.',
        searchPh: 'Search by code, name, or specimen type…',
        new: 'New test',
        active: { all: 'All', active: 'Active', inactive: 'Archived' },
        branch: { all: 'All branches', shared: 'Shared' },
        clear: 'Clear',
        col: { code: 'Code', name: 'Name', specimen: 'Specimen', unit: 'Unit', range: 'Reference range', price: 'Price', branch: 'Branch' },
        empty: 'No lab tests yet',
        emptyDesc: 'Add a test to get started.',
        previous: 'Previous', next: 'Next',
        showing: 'Showing', of: 'of',
        modal: {
            createTitle: 'New lab test', editTitle: 'Edit lab test',
            code: 'Code', codeHint: 'Short code (e.g. CBC, GLU, HBA1C)',
            name: 'Name', branch: 'Branch (leave empty = all branches)',
            specimen: 'Specimen type', unit: 'Unit', range: 'Reference range',
            price: 'Default price (KWD)', active: 'Active',
            save: 'Save', cancel: 'Cancel', archive: 'Archive', restore: 'Restore',
            archiveConfirm: 'Test will be hidden from new orders. Historical results keep showing it.',
        },
        stats: { total: 'Total', active: 'Active', inactive: 'Archived' },
    })

// --- Filter state ---
const f = reactive({
    q: props.filters.q || '',
    branch_id: props.filters.branch_id || '',
    active: props.filters.active || 'all',
})

let qTimer = null
watch(() => f.q, () => {
    clearTimeout(qTimer)
    qTimer = setTimeout(() => applyFilters(), 250)
})
watch(() => [f.branch_id, f.active], () => applyFilters(), { deep: true })

function applyFilters() {
    router.get(route('v2.lab-tests.index'), {
        q: f.q || undefined,
        branch_id: f.branch_id || undefined,
        active: f.active === 'all' ? undefined : f.active,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() {
    f.q = ''; f.branch_id = ''; f.active = 'all'
    applyFilters()
}

// --- Create / Edit modal ---
const modalOpen = ref(false)
const modalMode = ref('create') // 'create' | 'edit'
const editing = ref(null)
const form = reactive({
    code: '', name: '', branch_id: '', specimen_type: '',
    unit: '', reference_range: '', default_price: 0, is_active: true,
})
const errors = ref({})
const saving = ref(false)

function openCreate() {
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, { code: '', name: '', branch_id: props.branches.length === 1 ? props.branches[0].id : '', specimen_type: '', unit: '', reference_range: '', default_price: 0, is_active: true })
    errors.value = {}
    modalOpen.value = true
}
function openEdit(row) {
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        code: row.code, name: row.name, branch_id: row.branch_id || '',
        specimen_type: row.specimen_type || '', unit: row.unit || '',
        reference_range: row.reference_range || '', default_price: Number(row.default_price ?? 0),
        is_active: !!row.is_active,
    })
    errors.value = {}
    modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function submit() {
    saving.value = true
    errors.value = {}
    const payload = { ...form, branch_id: form.branch_id || null }
    const url = modalMode.value === 'create'
        ? route('v2.lab-tests.store')
        : route('v2.lab-tests.update', { labTest: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}

function archive(row) {
    if (!window.confirm(t.value.modal.archiveConfirm)) return
    router.delete(route('v2.lab-tests.destroy', { labTest: row.id }), { preserveScroll: true })
}
function restore(row) {
    router.post(route('v2.lab-tests.restore', { labTest: row.id }), {}, { preserveScroll: true })
}

function fmtPrice(v) { return Number(v ?? 0).toFixed(3) }
function rowIsArchived(row) { return !!row.deleted_at || !row.is_active }
</script>

<template>
    <Head :title="t.title" />

        <div style="padding: 24px; max-width: 1280px; margin: 0 auto;">
            <!-- Header -->
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
                <div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint);">
                        {{ t.eyebrow }}
                    </div>
                    <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; letter-spacing:-0.01em; color:var(--fg);">
                        {{ t.title }}
                    </h1>
                    <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">
                        {{ t.desc }}
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <a class="btn btn-sm btn-outline" :href="route('v2.lab-tests.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                    <ImportButton v-if="can_edit" type="lab-tests" />
                    <button v-if="can_edit" class="btn btn-primary" @click="openCreate">
                        <Icon name="plus" :size="14" />
                        <span>{{ t.new }}</span>
                    </button>
                </div>
            </div>

            <!-- Stat chips -->
            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <div class="stat-chip">
                    <span class="stat-chip-num">{{ counts.total }}</span>
                    <span class="stat-chip-lbl">{{ t.stats.total }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span>
                    <span class="stat-chip-lbl">{{ t.stats.active }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip-num" style="color:var(--fg-faint);">{{ counts.inactive }}</span>
                    <span class="stat-chip-lbl">{{ t.stats.inactive }}</span>
                </div>
            </div>

            <!-- Filters bar -->
            <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <div style="position:relative; flex:1; min-width:240px;">
                    <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                    <input
                        v-model="f.q"
                        type="search"
                        :placeholder="t.searchPh"
                        class="input"
                        style="padding-inline-start:32px;"
                    />
                </div>

                <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.branch.all" :width="200" />

                <div class="seg seg-sm">
                    <button :class="f.active === 'all' ? 'is-active' : ''" @click="f.active = 'all'">{{ t.active.all }}</button>
                    <button :class="f.active === 'active' ? 'is-active' : ''" @click="f.active = 'active'">{{ t.active.active }}</button>
                    <button :class="f.active === 'inactive' ? 'is-active' : ''" @click="f.active = 'inactive'">{{ t.active.inactive }}</button>
                </div>

                <button v-if="f.q || f.branch_id || f.active !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">
                    {{ t.clear }}
                </button>
            </div>

            <!-- Table -->
            <div class="card" style="overflow:hidden;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t.col.code }}</th>
                            <th>{{ t.col.name }}</th>
                            <th>{{ t.col.specimen }}</th>
                            <th>{{ t.col.unit }}</th>
                            <th>{{ t.col.range }}</th>
                            <th style="text-align:end;">{{ t.col.price }}</th>
                            <th>{{ t.col.branch }}</th>
                            <th style="width:80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="page.data.length === 0">
                            <td colspan="8" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                                <Icon name="beaker" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                                <div style="font-weight:600;">{{ t.empty }}</div>
                                <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                            </td>
                        </tr>
                        <tr
                            v-for="row in page.data"
                            :key="row.id"
                            :class="rowIsArchived(row) ? 'is-archived' : ''"
                            @click="can_edit && openEdit(row)"
                            :style="can_edit ? 'cursor:pointer;' : ''"
                        >
                            <td class="mono" style="font-weight:600;">{{ row.code }}</td>
                            <td>{{ row.name }}</td>
                            <td style="color:var(--fg-subtle);">{{ row.specimen_type || '—' }}</td>
                            <td style="color:var(--fg-subtle);">{{ row.unit || '—' }}</td>
                            <td style="color:var(--fg-subtle); font-size:12px;">{{ row.reference_range || '—' }}</td>
                            <td class="mono" style="text-align:end;">{{ fmtPrice(row.default_price) }}</td>
                            <td style="color:var(--fg-subtle); font-size:12px;">
                                {{ row.branch?.name || t.branch.shared }}
                            </td>
                            <td @click.stop>
                                <template v-if="can_edit">
                                    <button
                                        v-if="!rowIsArchived(row)"
                                        class="btn btn-ghost btn-sm btn-icon"
                                        :title="t.modal.archive"
                                        @click="archive(row)"
                                    >
                                        <Icon name="archive" :size="14" />
                                    </button>
                                    <button
                                        v-else
                                        class="btn btn-ghost btn-sm btn-icon"
                                        :title="t.modal.restore"
                                        @click="restore(row)"
                                    >
                                        <Icon name="undo-2" :size="14" />
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
                <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
                <div style="display:flex; gap:4px;">
                    <a
                        v-for="link in page.links"
                        :key="link.label"
                        :href="link.url || undefined"
                        v-html="link.label"
                        :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                        style="min-width:32px;"
                    />
                </div>
            </div>
        </div>

        <!-- Create / Edit modal -->
        <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-panel" role="dialog" aria-modal="true">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                    <h3 style="margin:0; font-size:15px; font-weight:600;">
                        {{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}
                    </h3>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
                </div>

                <form @submit.prevent="submit" class="rgrid-2" style="padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">{{ t.modal.code }} <span class="req">*</span></label>
                        <input v-model="form.code" type="text" class="input" maxlength="32" required />
                        <div v-if="errors.code" class="err">{{ errors.code }}</div>
                        <div v-else class="hint">{{ t.modal.codeHint }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.name }} <span class="req">*</span></label>
                        <input v-model="form.name" type="text" class="input" maxlength="191" required />
                        <div v-if="errors.name" class="err">{{ errors.name }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label class="label">{{ t.modal.branch }}</label>
                        <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.branch.shared" />
                    </div>
                    <div>
                        <label class="label">{{ t.modal.specimen }}</label>
                        <input v-model="form.specimen_type" type="text" class="input" maxlength="64" placeholder="Blood, Urine…" />
                    </div>
                    <div>
                        <label class="label">{{ t.modal.unit }}</label>
                        <input v-model="form.unit" type="text" class="input" maxlength="32" placeholder="mg/dL" />
                    </div>
                    <div>
                        <label class="label">{{ t.modal.range }}</label>
                        <input v-model="form.reference_range" type="text" class="input" maxlength="191" placeholder="70–100 mg/dL" />
                    </div>
                    <div>
                        <label class="label">{{ t.modal.price }} <span class="req">*</span></label>
                        <input v-model.number="form.default_price" type="number" step="0.001" min="0" class="input" required />
                        <div v-if="errors.default_price" class="err">{{ errors.default_price }}</div>
                    </div>
                    <div style="grid-column:span 2; display:flex; align-items:center; gap:8px;">
                        <input id="lt_active" v-model="form.is_active" type="checkbox" />
                        <label for="lt_active" style="font-size:13px;">{{ t.modal.active }}</label>
                    </div>

                    <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; margin-top:8px; padding-top:12px; border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            <span v-if="saving">…</span>
                            <span v-else>{{ t.modal.save }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
</template>

<style scoped>
.stat-chip {
    display: inline-flex; flex-direction: column; align-items: flex-start;
    padding: 8px 12px; border-radius: 8px;
    background: var(--bg-elev, var(--bg-hover)); border: 1px solid var(--line);
    min-width: 80px;
}
.stat-chip-num { font-size: 18px; font-weight: 700; color: var(--fg); line-height: 1; }
.stat-chip-lbl { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--fg-faint); margin-top: 4px; }

.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th { text-align: start; padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--fg-faint); font-weight: 600; border-bottom: 1px solid var(--line); }
.table td { padding: 10px 12px; border-bottom: 1px solid var(--line); }
.table tr:last-child td { border-bottom: none; }
.table tr.is-archived { opacity: 0.55; background: repeating-linear-gradient(45deg, transparent, transparent 6px, var(--bg-hover) 6px, var(--bg-hover) 7px); }
.table tbody tr:hover { background: var(--bg-hover); }

.label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--fg-faint); margin-bottom: 4px; }
.hint { font-size: 11px; color: var(--fg-faint); margin-top: 4px; }
.err { font-size: 11px; color: var(--err, #dc2626); margin-top: 4px; font-weight: 500; }

.modal-backdrop {
    position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); z-index: 80;
    display: flex; align-items: center; justify-content: center; padding: 24px;
}
.modal-panel {
    width: 100%; max-width: 560px; background: var(--bg); border: 1px solid var(--line);
    border-radius: 12px; box-shadow: 0 24px 60px rgba(0,0,0,0.25); overflow: hidden;
}
</style>
