<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { confirm } from '../../Composables/useConfirm.js'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    filters: Object,
    page: Object,
    branches: { type: Array, default: () => [] },
    counts: Object,
})

const pg = usePage()
const isRtl = computed(() => (pg.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    eyebrow: 'الفوترة', title: 'كوبونات الخصم', desc: 'أكواد خصم تُطبَّق على الزيارة عند الدفع.',
    searchPh: 'ابحث بالكود أو الاسم…', new: 'كوبون جديد', clear: 'مسح',
    all: 'الكل', active: 'فعّال', inactive: 'غير فعّال',
    col: { code: 'الكود', disc: 'الخصم', branch: 'الفرع', validity: 'الصلاحية', uses: 'الاستخدام', status: 'الحالة' },
    empty: 'لا توجد كوبونات',
    f: { code: 'الكود', name: 'الاسم (اختياري)', type: 'نوع الخصم', amount: 'مبلغ (د.ك)', percent: 'نسبة (%)', value: 'قيمة الخصم', min: 'أدنى مبلغ للزيارة', cap: 'حد أقصى للخصم (للنسبة)', branch: 'الفرع', allBranches: '— كل الفروع —', starts: 'يبدأ في', ends: 'ينتهي في', maxUses: 'أقصى عدد استخدامات', isActive: 'فعّال', stacks: 'يُجمع مع العروض الترويجية' },
    save: 'حفظ', cancel: 'إلغاء', editTitle: 'تحرير الكوبون', createTitle: 'كوبون جديد', del: 'حذف هذا الكوبون؟',
    unlimited: 'غير محدود',
    showing: 'عرض', of: 'من',
    stats: { total: 'الكل', active: 'فعّال' },
} : {
    eyebrow: 'Billing', title: 'Coupons', desc: 'Discount codes applied to a visit at checkout.',
    searchPh: 'Search code or name…', new: 'New coupon', clear: 'Clear',
    all: 'All', active: 'Active', inactive: 'Inactive',
    col: { code: 'Code', disc: 'Discount', branch: 'Branch', validity: 'Validity', uses: 'Uses', status: 'Status' },
    empty: 'No coupons',
    f: { code: 'Code', name: 'Name (optional)', type: 'Discount type', amount: 'Amount (KWD)', percent: 'Percent (%)', value: 'Discount value', min: 'Min visit subtotal', cap: 'Max discount (percent only)', branch: 'Branch', allBranches: '— All branches —', starts: 'Starts at', ends: 'Ends at', maxUses: 'Max uses', isActive: 'Active', stacks: 'Stacks with promotions' },
    save: 'Save', cancel: 'Cancel', editTitle: 'Edit coupon', createTitle: 'New coupon', del: 'Delete this coupon?',
    unlimited: 'Unlimited',
    showing: 'Showing', of: 'of',
    stats: { total: 'Total', active: 'Active' },
})

const typeItems = computed(() => [
    { value: 'amount', label: t.value.f.amount },
    { value: 'percent', label: t.value.f.percent },
])

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
function apply() {
    router.get(route('v2.coupons.index'), { q: f.q || undefined, status: f.status === 'all' ? undefined : f.status }, { preserveState: true, preserveScroll: true, replace: true })
}
function onSearch() { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) }

const modalOpen = ref(false)
const mode = ref('create')
const editing = ref(null)
const saving = ref(false)
const errors = ref({})
const blank = () => ({ code: '', name: '', discount_type: 'amount', discount_value: 0, min_subtotal: 0, max_discount: null, branch_id: null, starts_at: '', ends_at: '', max_uses: null, is_active: true, stacks_with_promotions: true })
const form = reactive(blank())

function openCreate() { mode.value = 'create'; editing.value = null; Object.assign(form, blank()); errors.value = {}; modalOpen.value = true }
function openEdit(row) {
    mode.value = 'edit'; editing.value = row
    Object.assign(form, {
        code: row.code, name: row.name || '', discount_type: row.discount_type, discount_value: row.discount_value,
        min_subtotal: row.min_subtotal, max_discount: row.max_discount, branch_id: row.branch_id,
        starts_at: row.starts_at || '', ends_at: row.ends_at || '', max_uses: row.max_uses, is_active: !!row.is_active,
        stacks_with_promotions: row.stacks_with_promotions !== false,
    })
    errors.value = {}; modalOpen.value = true
}
function submit() {
    saving.value = true; errors.value = {}
    const url = mode.value === 'create' ? route('v2.coupons.store') : route('v2.coupons.update', { clinicCoupon: editing.value.id })
    const method = mode.value === 'create' ? 'post' : 'put'
    router[method](url, { ...form }, { preserveScroll: true, onSuccess: () => { modalOpen.value = false; saving.value = false }, onError: (e) => { errors.value = e; saving.value = false } })
}
function destroy(row) { confirm({ body: t.value.del, onConfirm: () => router.delete(route('v2.coupons.destroy', { clinicCoupon: row.id }), { preserveScroll: true }) }) }

function discLabel(r) { return r.discount_type === 'percent' ? `${Number(r.discount_value)}%` : `${fmt(r.discount_value)} KWD` }
function validityLabel(r) {
    if (!r.starts_at && !r.ends_at) return '—'
    return `${r.starts_at || '…'} → ${r.ends_at || '…'}`
}
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="font-size:22px; font-weight:600; margin:2px 0 2px;">{{ t.title }}</h1>
                <div style="font-size:13px; color:var(--fg-muted);">{{ t.desc }}</div>
            </div>
            <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:200px;">
                <Icon name="search" :size="14" :style="{ position:'absolute', top:'50%', insetInlineStart:'10px', transform:'translateY(-50%)', color:'var(--fg-subtle)' }" />
                <input v-model="f.q" @input="onSearch" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <div class="seg seg-sm">
                <button :class="f.status==='all'?'is-active':''" @click="f.status='all'; apply()">{{ t.all }}</button>
                <button :class="f.status==='active'?'is-active':''" @click="f.status='active'; apply()">{{ t.active }}</button>
                <button :class="f.status==='inactive'?'is-active':''" @click="f.status='inactive'; apply()">{{ t.inactive }}</button>
            </div>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.code }}</th>
                        <th>{{ t.col.disc }}</th>
                        <th>{{ t.col.branch }}</th>
                        <th>{{ t.col.validity }}</th>
                        <th style="text-align:end;">{{ t.col.uses }}</th>
                        <th style="text-align:end;">{{ t.col.status }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in page.data" :key="row.id" style="cursor:pointer;" @click="openEdit(row)">
                        <td><span class="mono" style="font-weight:600;">{{ row.code }}</span><div v-if="row.name" style="font-size:11.5px; color:var(--fg-subtle);">{{ row.name }}</div></td>
                        <td>{{ discLabel(row) }}</td>
                        <td>{{ row.branch_name || '—' }}</td>
                        <td style="font-size:12px;">{{ validityLabel(row) }}</td>
                        <td class="tnum" style="text-align:end;">{{ row.uses_count }}<span style="color:var(--fg-faint);"> / {{ row.max_uses ?? '∞' }}</span></td>
                        <td style="text-align:end;">
                            <span :class="row.is_active ? 'badge badge-ok' : 'badge badge-muted'">{{ row.is_active ? t.active : t.inactive }}</span>
                            <button class="btn btn-ghost btn-sm btn-icon" style="color:var(--destructive); margin-inline-start:6px;" @click.stop="destroy(row)"><Icon name="trash-2" :size="14" /></button>
                        </td>
                    </tr>
                    <tr v-if="page.data.length === 0"><td colspan="6" style="text-align:center; padding:32px; color:var(--fg-subtle);">{{ t.empty }}</td></tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;">
                <component :is="link.url ? Link : 'span'" v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label" :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" preserve-scroll preserve-state prefetch="click" />
            </div>
        </div>
    </div>

    <Teleport to="body">
        <Transition name="fade">
            <div v-if="modalOpen" class="cd-overlay overlay-enter" @click.self="modalOpen = false">
                <div class="cd-panel" style="width:min(560px,94vw);">
                    <div style="padding:14px 18px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between;">
                        <div style="font-weight:600;">{{ mode === 'create' ? t.createTitle : t.editTitle }}</div>
                        <button class="btn btn-ghost btn-sm btn-icon" @click="modalOpen = false"><Icon name="x" :size="16" /></button>
                    </div>
                    <form @submit.prevent="submit" style="padding:16px 18px; display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:74vh; overflow-y:auto;">
                        <div>
                            <label class="label">{{ t.f.code }} <span class="req">*</span></label>
                            <input v-model="form.code" class="input mono" required maxlength="64" style="text-transform:uppercase;" />
                            <div v-if="errors.code" class="err">{{ errors.code }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.f.name }}</label>
                            <input v-model="form.name" class="input" maxlength="191" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.type }}</label>
                            <SearchableSelect v-model="form.discount_type" :items="typeItems" :nullable="false" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.value }} <span class="req">*</span></label>
                            <input v-model.number="form.discount_value" type="number" step="any" min="0" class="input" required />
                            <div v-if="errors.discount_value" class="err">{{ errors.discount_value }}</div>
                        </div>
                        <div>
                            <label class="label">{{ t.f.min }}</label>
                            <input v-model.number="form.min_subtotal" type="number" step="any" min="0" class="input" />
                        </div>
                        <div v-if="form.discount_type === 'percent'">
                            <label class="label">{{ t.f.cap }}</label>
                            <input v-model.number="form.max_discount" type="number" step="any" min="0" class="input" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.branch }}</label>
                            <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.f.allBranches" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.maxUses }}</label>
                            <input v-model.number="form.max_uses" type="number" step="1" min="1" class="input" :placeholder="t.unlimited" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.starts }}</label>
                            <input v-model="form.starts_at" type="date" class="input" />
                        </div>
                        <div>
                            <label class="label">{{ t.f.ends }}</label>
                            <input v-model="form.ends_at" type="date" class="input" />
                            <div v-if="errors.ends_at" class="err">{{ errors.ends_at }}</div>
                        </div>
                        <label class="role-check" style="grid-column:span 2;"><input type="checkbox" v-model="form.stacks_with_promotions" /><span>{{ t.f.stacks }}</span></label>
                        <label class="role-check" style="grid-column:span 2;"><input type="checkbox" v-model="form.is_active" /><span>{{ t.f.isActive }}</span></label>
                        <div style="grid-column:span 2; display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--line); padding-top:12px;">
                            <button type="button" class="btn btn-outline" @click="modalOpen = false">{{ t.cancel }}</button>
                            <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.save }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.table thead th {
    position: sticky;
    top: 0;
    background: var(--card, var(--bg));
    z-index: 1;
}
</style>
