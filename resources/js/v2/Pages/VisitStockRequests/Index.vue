<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    filters: { type: Object, required: true },
    rows: { type: Array, required: true },
    canAct: { type: Boolean, default: false },
    counts: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'طلبات صرف المخزون', eyebrow: 'الصيدلية والمخزون',
    desc: 'طلبات الأصناف المرفوعة من شاشة الزيارة، مقارنةً بالمخزون الحالي للفرع.',
    status: { pending: 'معلّقة', fulfilled: 'تم الصرف', cancelled: 'ملغاة' },
    visit: 'الزيارة', branch: 'الفرع', by: 'بواسطة', items: 'الأصناف',
    available: 'متوفر', short: 'ناقص!', ok: 'متوفر',
    empty: 'لا توجد طلبات', emptyDesc: 'لا توجد طلبات بهذه الحالة.',
    fulfill: 'صرف', cancel: 'إلغاء', open: 'فتح الزيارة',
    fulfillModal: { title: 'صرف الطلب', notes: 'ملاحظات الصرف', resume: 'استئناف حالة الزيارة',
        awaiting: 'بانتظار الطبيب', inProgress: 'قيد التنفيذ', resumeHelp: 'إلى أي حالة تعود الزيارة بعد الصرف؟',
        confirm: 'تأكيد الصرف', cancelBtn: 'إلغاء' },
    cancelModal: { title: 'إلغاء الطلب', reason: 'السبب', confirm: 'تأكيد الإلغاء', cancelBtn: 'تراجع' },
} : {
    title: 'Visit Stock Requests', eyebrow: 'Pharmacy & Stock',
    desc: 'Item requests raised from the visit console, checked against live branch stock.',
    status: { pending: 'Pending', fulfilled: 'Fulfilled', cancelled: 'Cancelled' },
    visit: 'Visit', branch: 'Branch', by: 'by', items: 'Items',
    available: 'avail', short: 'short!', ok: 'ok',
    empty: 'No requests', emptyDesc: 'No requests with this status.',
    fulfill: 'Fulfil', cancel: 'Cancel', open: 'Open visit',
    fulfillModal: { title: 'Fulfil request', notes: 'Fulfilment notes', resume: 'Resume visit status',
        awaiting: 'Awaiting doctor', inProgress: 'In progress', resumeHelp: 'Which status should the visit return to after issuing stock?',
        confirm: 'Confirm fulfil', cancelBtn: 'Cancel' },
    cancelModal: { title: 'Cancel request', reason: 'Reason', confirm: 'Confirm cancel', cancelBtn: 'Back' },
})

const status = ref(props.filters.status || 'pending')
function setStatus(s) {
    status.value = s
    router.get(route('v2.visit-stock-requests.index'), { status: s }, { preserveState: true, preserveScroll: true, replace: true })
}

// Fulfil modal
const fulfilOpen = ref(false)
const acting = ref(null)
const fulfilForm = reactive({ notes: '', resume_status: 'awaiting_doctor' })
const busy = ref(false)
function openFulfil(row) { acting.value = row; Object.assign(fulfilForm, { notes: '', resume_status: 'awaiting_doctor' }); fulfilOpen.value = true }
function submitFulfil() {
    busy.value = true
    router.post(route('v2.visit-stock-requests.fulfill', { visitStockRequest: acting.value.id }), { ...fulfilForm }, {
        preserveScroll: true,
        onSuccess: () => { fulfilOpen.value = false },
        onFinish: () => { busy.value = false },
    })
}

// Cancel modal
const cancelOpen = ref(false)
const cancelForm = reactive({ reason: '' })
function openCancel(row) { acting.value = row; cancelForm.reason = ''; cancelOpen.value = true }
function submitCancel() {
    busy.value = true
    router.post(route('v2.visit-stock-requests.cancel', { visitStockRequest: acting.value.id }), { ...cancelForm }, {
        preserveScroll: true,
        onSuccess: () => { cancelOpen.value = false },
        onFinish: () => { busy.value = false },
    })
}

const resumeStatusItems = computed(() => [
    { value: 'awaiting_doctor', label: t.value.fulfillModal.awaiting },
    { value: 'in_progress', label: t.value.fulfillModal.inProgress },
])

const fmt = (n) => Number(n ?? 0).toLocaleString(undefined, { maximumFractionDigits: 4 })
</script>

<template>
    <Head :title="t.title" />

    <div style="padding:24px; max-width:1000px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
        </div>
            <a class="btn btn-sm btn-outline" :href="route('v2.visit-stock-requests.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
        </div>

        <div class="card" style="padding:12px; margin-bottom:16px; display:flex; gap:8px; align-items:center;">
            <div class="seg seg-sm">
                <button :class="status === 'pending' ? 'is-active' : ''" @click="setStatus('pending')">{{ t.status.pending }} · {{ counts.pending }}</button>
                <button :class="status === 'fulfilled' ? 'is-active' : ''" @click="setStatus('fulfilled')">{{ t.status.fulfilled }} · {{ counts.fulfilled }}</button>
                <button :class="status === 'cancelled' ? 'is-active' : ''" @click="setStatus('cancelled')">{{ t.status.cancelled }} · {{ counts.cancelled }}</button>
            </div>
        </div>

        <div v-if="!rows.length" class="card" style="padding:48px 12px; text-align:center; color:var(--fg-faint);">
            <Icon name="inbox" :size="32" style="margin-bottom:8px; opacity:0.4;" />
            <div style="font-weight:600;">{{ t.empty }}</div>
            <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
        </div>

        <div v-for="row in rows" :key="row.id" class="card" style="padding:14px 16px; margin-bottom:12px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:10px;">
                <div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <Link :href="route('v2.visits.show', { visit: row.visit_id })" class="badge-visit mono">{{ row.visit_code }}</Link>
                        <span :class="['badge-status', 'st-' + row.status]">{{ t.status[row.status] }}</span>
                        <span v-if="row.any_short && row.status === 'pending'" class="badge-short"><Icon name="alert-triangle" :size="11" style="vertical-align:-1px;" /> {{ t.short }}</span>
                    </div>
                    <div style="font-size:12px; color:var(--fg-faint); margin-top:4px;">
                        {{ row.branch }} · {{ row.created_at }}
                        <span v-if="row.requested_by"> · {{ t.by }} {{ row.requested_by }}</span>
                    </div>
                </div>
                <div v-if="canAct && row.status === 'pending'" style="display:flex; gap:6px; flex-shrink:0;">
                    <button class="btn btn-primary btn-sm" @click="openFulfil(row)"><Icon name="check-circle" :size="13" /><span>{{ t.fulfill }}</span></button>
                    <button class="btn btn-ghost btn-sm" style="color:var(--err, #dc2626);" @click="openCancel(row)"><Icon name="x-circle" :size="13" /></button>
                </div>
            </div>

            <div class="lines">
                <div v-for="(l, i) in row.lines" :key="i" class="line" :class="l.short ? 'is-short' : ''">
                    <span style="flex:1;">{{ l.name }}</span>
                    <span class="mono" style="font-weight:600;">{{ fmt(l.qty) }}</span>
                    <span v-if="l.available !== null" class="mono line-avail" :class="l.short ? 'short' : 'ok'">
                        / {{ fmt(l.available) }} {{ l.short ? t.short : t.ok }}
                    </span>
                </div>
                <div v-if="!row.lines.length" style="color:var(--fg-faint); font-size:12px; font-style:italic;">—</div>
            </div>
        </div>
    </div>

    <!-- Fulfil modal -->
    <div v-if="fulfilOpen" class="modal-backdrop" @click.self="fulfilOpen = false">
        <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.fulfillModal.title }} · {{ acting?.visit_code }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="fulfilOpen = false"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submitFulfil" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label class="label">{{ t.fulfillModal.resume }}</label>
                    <SearchableSelect v-model="fulfilForm.resume_status" :items="resumeStatusItems" :nullable="false" />
                    <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.fulfillModal.resumeHelp }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fulfillModal.notes }}</label>
                    <textarea v-model="fulfilForm.notes" class="input" rows="3" maxlength="2000"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="fulfilOpen = false">{{ t.fulfillModal.cancelBtn }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="busy">{{ busy ? '…' : t.fulfillModal.confirm }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel modal -->
    <div v-if="cancelOpen" class="modal-backdrop" @click.self="cancelOpen = false">
        <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line);">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ t.cancelModal.title }} · {{ acting?.visit_code }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="cancelOpen = false"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submitCancel" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label class="label">{{ t.cancelModal.reason }} <span class="req">*</span></label>
                    <textarea v-model="cancelForm.reason" class="input" rows="3" maxlength="2000" required></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="cancelOpen = false">{{ t.cancelModal.cancelBtn }}</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--err, #dc2626); border-color:var(--err, #dc2626);" :disabled="busy || !cancelForm.reason">{{ busy ? '…' : t.cancelModal.confirm }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.badge-visit { font-family:var(--mono, monospace); font-size:12px; font-weight:600; padding:2px 8px; border:1px solid var(--line); border-radius:6px; color:var(--accent, #2563eb); text-decoration:none; background:var(--bg-hover); }
.badge-visit:hover { border-color:var(--accent, #2563eb); }
.badge-status { font-size:10.5px; font-weight:600; padding:2px 8px; border-radius:999px; border:1px solid; }
.st-pending { color:var(--warn, #d97706); border-color:var(--warn, #d97706); }
.st-fulfilled { color:var(--ok); border-color:var(--ok); }
.st-cancelled { color:var(--err, #dc2626); border-color:var(--err, #dc2626); }
.badge-short { font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; color:var(--err, #dc2626); background:rgba(220,38,38,0.08); }
.lines { display:flex; flex-direction:column; gap:4px; border-top:1px solid var(--line); padding-top:10px; }
.line { display:flex; align-items:center; gap:8px; font-size:13px; padding:3px 0; }
.line.is-short { color:var(--err, #dc2626); }
.line-avail { font-size:11.5px; }
.line-avail.ok { color:var(--ok); }
.line-avail.short { color:var(--err, #dc2626); font-weight:700; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
