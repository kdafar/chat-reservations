<script setup>
/**
 * Insurance for one visit, on the workspace's Payments tab — the same
 * decisions v2's VisitSheet / Visit Console let reception make, against the
 * same endpoints (VisitConsoleController), so the server's rules still win:
 *
 *   no policy → attach one (insurer + plan + policy number), or skip
 *   policy    → estimate per kind → apply insurer share → create claim, or skip
 *
 * Discharge is refused with `insurance_decision_pending` while the patient
 * has an active policy and the visit has neither a (non-void) claim nor a
 * skip stamp — `insurance.requires_decision` mirrors that gate exactly.
 *
 * Loads its own state from GET /api/visits/{id}; emits 'changed' after every
 * successful write so the parent reloads the visit.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { call } from './live.js'
import { pushToast } from '../../Composables/useNotificationState.js'
import { formatMoney } from '../../lib/money.js'

const props = defineProps({
    row: { type: Object, required: true },
})
const emit = defineEmits(['changed'])

const API = '/admin/v2/api/visits'
const page = usePage()
const isRtl = computed(() => (page.props?.locale ?? 'en') === 'ar')

const STRINGS = {
    en: {
        title: 'Insurance',
        loading: 'Loading insurance…',
        loadFailed: 'Could not load insurance',
        retry: 'Retry',
        none: 'No insurance on file',
        attach: 'Attach policy',
        insurer: 'Insurer',
        plan: 'Plan',
        pick: 'Choose…',
        policyNo: 'Policy number',
        memberId: 'Member ID (optional)',
        civilId: 'Civil ID (optional)',
        save: 'Save policy',
        cancel: 'Cancel',
        noInsurers: 'No active insurers are set up.',
        attached: 'Insurance added',
        attachFailed: 'Could not add insurance',
        primary: 'Primary',
        estimate: 'Estimate',
        kind: 'Covers',
        insurerAmt: 'Insurer pays',
        status: 'Status',
        applied: 'Applied',
        pending: 'Not applied',
        noCover: 'Nothing on this visit is covered by the plan yet.',
        estimateFailed: 'Could not load the estimate',
        totals: 'Visit total',
        patientPays: 'Patient pays',
        apply: 'Apply insurer share',
        pickKind: 'Pick at least one item to apply',
        appliedOk: 'Insurer share applied',
        applyFailed: 'Could not apply insurance',
        claim: 'Claim',
        createClaim: 'Create claim',
        claimOk: 'Claim created',
        claimFailed: 'Could not create claim',
        claimHint: 'Filing the claim settles the insurance decision for discharge.',
        skipTitle: 'Patient pays themselves (skip insurance)',
        skipReason: 'Reason (optional)',
        skipConfirm: 'Skip insurance',
        skipOk: 'Insurance skipped',
        skipFailed: 'Could not skip',
        skipped: 'Insurance skipped — patient is paying',
        decision: 'Discharge needs an insurance decision: apply the share and create the claim, or skip.',
        readOnly: 'You can view insurance but not change it on this visit.',
        kinds: { consultation: 'Consultation', services: 'Services', medicines: 'Medicines', other: 'Other' },
        claimStatus: {},
    },
    ar: {
        title: 'التأمين',
        loading: 'جارٍ تحميل التأمين…',
        loadFailed: 'تعذّر تحميل التأمين',
        retry: 'إعادة المحاولة',
        none: 'لا يوجد تأمين مسجّل',
        attach: 'إضافة وثيقة تأمين',
        insurer: 'شركة التأمين',
        plan: 'الخطة',
        pick: 'اختر…',
        policyNo: 'رقم الوثيقة',
        memberId: 'رقم العضوية (اختياري)',
        civilId: 'الرقم المدني (اختياري)',
        save: 'حفظ الوثيقة',
        cancel: 'إلغاء',
        noInsurers: 'لا توجد شركات تأمين مفعّلة.',
        attached: 'تمت إضافة التأمين',
        attachFailed: 'تعذّر إضافة التأمين',
        primary: 'أساسية',
        estimate: 'التقدير',
        kind: 'البند',
        insurerAmt: 'يدفع التأمين',
        status: 'الحالة',
        applied: 'مطبّق',
        pending: 'غير مطبّق',
        noCover: 'لا يوجد في هذه الزيارة ما تغطيه الخطة بعد.',
        estimateFailed: 'تعذّر تحميل التقدير',
        totals: 'إجمالي الزيارة',
        patientPays: 'يدفع المريض',
        apply: 'تطبيق حصة التأمين',
        pickKind: 'اختر بندًا واحدًا على الأقل',
        appliedOk: 'تم تطبيق حصة التأمين',
        applyFailed: 'تعذّر تطبيق التأمين',
        claim: 'المطالبة',
        createClaim: 'إنشاء مطالبة',
        claimOk: 'تم إنشاء المطالبة',
        claimFailed: 'تعذّر إنشاء المطالبة',
        claimHint: 'إنشاء المطالبة يحسم قرار التأمين المطلوب للخروج.',
        skipTitle: 'المريض يدفع بنفسه (تخطي التأمين)',
        skipReason: 'السبب (اختياري)',
        skipConfirm: 'تخطي التأمين',
        skipOk: 'تم تخطي التأمين',
        skipFailed: 'تعذّر التخطي',
        skipped: 'تم تخطي التأمين — المريض يدفع',
        decision: 'الخروج يحتاج قرار تأمين: طبّق الحصة وأنشئ المطالبة، أو تخطَّ التأمين.',
        readOnly: 'يمكنك عرض التأمين دون تعديله في هذه الزيارة.',
        kinds: { consultation: 'الكشفية', services: 'الخدمات', medicines: 'الأدوية', other: 'أخرى' },
        claimStatus: {
            draft: 'مسودة', submitted: 'مُرسلة', approved: 'مقبولة', partially_approved: 'مقبولة جزئيًا',
            rejected: 'مرفوضة', paid: 'مدفوعة', void: 'ملغاة',
        },
    },
}
const t = computed(() => STRINGS[isRtl.value ? 'ar' : 'en'])

const visitId = computed(() => props.row?.id ?? null)

/* ── state ─────────────────────────────────────────────────────────────── */
const loading = ref(false)
const loadError = ref('')
const visit = ref(null)
const estimate = ref(null)
const estimateError = ref('')
const busy = ref('') // 'attach' | 'apply' | 'claim' | 'skip' | ''

const ins = computed(() => visit.value?.insurance ?? {})
const policies = computed(() => (Array.isArray(ins.value.active_policies) ? ins.value.active_policies : []))
const policy = computed(() => policies.value.find((p) => p?.is_primary) ?? policies.value[0] ?? null)
const claim = computed(() => ins.value.claim ?? null)
const skippedAt = computed(() => ins.value.skipped_at ?? null)
const requiresDecision = computed(() => !!ins.value.requires_decision)
const canWrite = computed(() => !!visit.value?.permissions?.can_record_payment)

const kinds = computed(() => (Array.isArray(estimate.value?.kinds) ? estimate.value.kinds : []))
const openKinds = computed(() => kinds.value.filter((k) => !k.already_applied))
const totals = computed(() => estimate.value?.totals ?? null)
const selectedKinds = ref([])

function kindLabel(k) { return t.value.kinds[k] ?? k }
function claimStatusLabel(s) { return t.value.claimStatus[s] ?? String(s ?? '').replace(/_/g, ' ') }
function money(v) { return formatMoney(Number(v) || 0) }
/** Plan/insurer names may arrive as translatable {en, ar} objects. */
function nameOf(v) {
    if (v && typeof v === 'object') return v[isRtl.value ? 'ar' : 'en'] ?? v.en ?? Object.values(v)[0] ?? ''
    return v ?? ''
}

/* ── loading ───────────────────────────────────────────────────────────── */
async function load() {
    if (!visitId.value) return
    loading.value = true
    loadError.value = ''
    try {
        const data = await call('GET', `${API}/${visitId.value}`)
        visit.value = data.visit ?? null
    } catch (e) {
        loadError.value = e.message || t.value.loadFailed
        visit.value = null
    } finally {
        loading.value = false
    }
    await loadEstimate()
}

async function loadEstimate() {
    estimate.value = null
    estimateError.value = ''
    // The estimate endpoint is reception/admin only, and only meaningful with a policy.
    if (!visitId.value || !policy.value || !canWrite.value) return
    try {
        estimate.value = await call('GET', `${API}/${visitId.value}/insurance/estimate`)
    } catch (e) {
        estimateError.value = e.message || t.value.estimateFailed
    }
}

// Default the checkboxes to every kind not yet applied.
watch(openKinds, (list) => { selectedKinds.value = list.map((k) => k.kind) }, { immediate: true })

onMounted(load)
watch(visitId, (id, old) => {
    if (id === old) return
    resetForms()
    load()
})

async function afterWrite() {
    emit('changed')
    await load()
}

/* ── attach policy ─────────────────────────────────────────────────────── */
const attachOpen = ref(false)
const insurers = ref([])
const optionsLoading = ref(false)
const form = ref(blankForm())
function blankForm() {
    return { civil_id: props.row?.patient?.civil_id ?? '', insurer_id: '', plan_id: '', policy_number: '', member_id: '' }
}
const plansForInsurer = computed(() =>
    insurers.value.find((i) => String(i.id) === String(form.value.insurer_id))?.plans ?? [])
watch(() => form.value.insurer_id, () => { form.value.plan_id = '' })
const canSubmitAttach = computed(() =>
    !!form.value.insurer_id && !!form.value.plan_id && !!String(form.value.policy_number ?? '').trim())

async function openAttach() {
    form.value = blankForm()
    attachOpen.value = true
    skipOpen.value = false
    optionsLoading.value = true
    try {
        const data = await call('GET', `${API}/${visitId.value}/insurance/options`)
        insurers.value = Array.isArray(data.insurers) ? data.insurers : []
        if (data.civil_id && !form.value.civil_id) form.value.civil_id = data.civil_id
    } catch (e) {
        insurers.value = []
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.attachFailed, desc: e.message })
    } finally {
        optionsLoading.value = false
    }
}

async function submitAttach() {
    if (busy.value || !canSubmitAttach.value) return
    busy.value = 'attach'
    try {
        await call('POST', `${API}/${visitId.value}/insurance/attach`, {
            civil_id: String(form.value.civil_id ?? '').trim() || null,
            insurer_id: Number(form.value.insurer_id),
            plan_id: Number(form.value.plan_id),
            policy_number: String(form.value.policy_number).trim(),
            member_id: String(form.value.member_id ?? '').trim() || null,
        })
        pushToast({ kind: 'success', icon: 'check', title: t.value.attached })
        attachOpen.value = false
        await afterWrite()
    } catch (e) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.attachFailed, desc: e.message })
    } finally {
        busy.value = ''
    }
}

/* ── apply insurer share ───────────────────────────────────────────────── */
async function submitApply() {
    if (busy.value) return
    const picked = selectedKinds.value.filter((k) => openKinds.value.some((o) => o.kind === k))
    if (!picked.length) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.pickKind })
        return
    }
    busy.value = 'apply'
    try {
        const data = await call('POST', `${API}/${visitId.value}/insurance/apply`, { kinds: picked, note: null })
        pushToast({ kind: 'success', icon: 'check', title: `${t.value.appliedOk} (${Number(data.created) || 0})` })
        await afterWrite()
    } catch (e) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.applyFailed, desc: e.message })
    } finally {
        busy.value = ''
    }
}

/* ── create claim ──────────────────────────────────────────────────────── */
async function submitClaim() {
    if (busy.value) return
    busy.value = 'claim'
    try {
        const body = policy.value?.id ? { policy_id: policy.value.id } : {}
        const data = await call('POST', `${API}/${visitId.value}/insurance/create-claim`, body)
        pushToast({ kind: 'success', icon: 'check', title: t.value.claimOk, desc: data.claim?.claim_number })
        await afterWrite()
    } catch (e) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.claimFailed, desc: e.message })
    } finally {
        busy.value = ''
    }
}

/* ── skip ──────────────────────────────────────────────────────────────── */
const skipOpen = ref(false)
const skipReason = ref('')
function openSkip() {
    skipOpen.value = !skipOpen.value
    attachOpen.value = false
}
async function submitSkip() {
    if (busy.value) return
    busy.value = 'skip'
    try {
        await call('POST', `${API}/${visitId.value}/insurance/skip`, { reason: String(skipReason.value ?? '').trim() || null })
        pushToast({ kind: 'success', icon: 'check', title: t.value.skipOk })
        skipOpen.value = false
        skipReason.value = ''
        await afterWrite()
    } catch (e) {
        pushToast({ kind: 'warning', icon: 'alert-triangle', title: t.value.skipFailed, desc: e.message })
    } finally {
        busy.value = ''
    }
}

function resetForms() {
    attachOpen.value = false
    skipOpen.value = false
    skipReason.value = ''
    estimate.value = null
}
</script>

<template>
    <section class="ins" :dir="isRtl ? 'rtl' : 'ltr'">
        <div class="ins-label">{{ t.title }}</div>

        <div v-if="loading && !visit" class="ins-muted">{{ t.loading }}</div>

        <div v-else-if="loadError && !visit" class="ins-row">
            <span class="ins-err">{{ t.loadFailed }}: {{ loadError }}</span>
            <button type="button" class="btn btn-sm btn-ghost" @click="load">{{ t.retry }}</button>
        </div>

        <template v-else-if="visit">
            <div v-if="requiresDecision" class="ins-amber">{{ t.decision }}</div>
            <div v-if="skippedAt" class="ins-info">
                {{ t.skipped }}<span v-if="ins.skip_reason" class="ins-muted"> — {{ ins.skip_reason }}</span>
            </div>
            <div v-if="!canWrite" class="ins-muted ins-small">{{ t.readOnly }}</div>

            <!-- ── No policy on file ─────────────────────────────────── -->
            <template v-if="!policy">
                <div class="ins-row">
                    <span class="ins-strong">{{ t.none }}</span>
                    <button v-if="canWrite && !attachOpen" type="button" class="btn btn-sm btn-outline ins-push" @click="openAttach">
                        {{ t.attach }}
                    </button>
                </div>

                <form v-if="attachOpen && canWrite" class="ins-form" @submit.prevent="submitAttach">
                    <div v-if="optionsLoading" class="ins-muted ins-small">{{ t.loading }}</div>
                    <div v-else-if="!insurers.length" class="ins-muted ins-small">{{ t.noInsurers }}</div>
                    <label class="ins-field">
                        <span>{{ t.insurer }}</span>
                        <select v-model="form.insurer_id" class="input" :disabled="optionsLoading || !insurers.length">
                            <option value="">{{ t.pick }}</option>
                            <option v-for="i in insurers" :key="i.id" :value="String(i.id)">{{ nameOf(i.name) }}</option>
                        </select>
                    </label>
                    <label class="ins-field">
                        <span>{{ t.plan }}</span>
                        <select v-model="form.plan_id" class="input" :disabled="!form.insurer_id">
                            <option value="">{{ t.pick }}</option>
                            <option v-for="p in plansForInsurer" :key="p.id" :value="String(p.id)">{{ nameOf(p.name) }}</option>
                        </select>
                    </label>
                    <label class="ins-field">
                        <span>{{ t.policyNo }}</span>
                        <input v-model="form.policy_number" class="input" type="text" maxlength="100" />
                    </label>
                    <label class="ins-field">
                        <span>{{ t.memberId }}</span>
                        <input v-model="form.member_id" class="input" type="text" maxlength="100" />
                    </label>
                    <label class="ins-field">
                        <span>{{ t.civilId }}</span>
                        <input v-model="form.civil_id" class="input" type="text" maxlength="32" />
                    </label>
                    <div class="ins-row">
                        <button type="submit" class="btn btn-sm btn-primary" :disabled="!canSubmitAttach || !!busy">{{ t.save }}</button>
                        <button type="button" class="btn btn-sm btn-ghost" @click="attachOpen = false">{{ t.cancel }}</button>
                    </div>
                </form>
            </template>

            <!-- ── Policy on file ────────────────────────────────────── -->
            <template v-else>
                <div class="ins-policy">
                    <div class="ins-strong">
                        {{ nameOf(policy.insurer_name) || '—' }}
                        <span v-if="policy.plan_name" class="ins-muted"> · {{ nameOf(policy.plan_name) }}</span>
                    </div>
                    <div class="ins-muted ins-small">
                        #{{ policy.policy_number || '—' }}
                        <span v-if="policy.is_primary" class="ins-chip">{{ t.primary }}</span>
                    </div>
                </div>

                <!-- Estimate + apply -->
                <template v-if="canWrite">
                    <div class="ins-label">{{ t.estimate }}</div>
                    <div v-if="estimateError" class="ins-row">
                        <span class="ins-err">{{ estimateError }}</span>
                        <button type="button" class="btn btn-sm btn-ghost" @click="loadEstimate">{{ t.retry }}</button>
                    </div>
                    <div v-else-if="!estimate" class="ins-muted ins-small">{{ t.loading }}</div>
                    <div v-else-if="!kinds.length" class="ins-muted ins-small">{{ t.noCover }}</div>
                    <template v-else>
                        <table class="ins-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>{{ t.kind }}</th>
                                    <th class="ins-num">{{ t.insurerAmt }}</th>
                                    <th>{{ t.status }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="k in kinds" :key="k.kind" :class="{ 'is-done': k.already_applied }">
                                    <td>
                                        <input
                                            type="checkbox"
                                            :value="k.kind"
                                            v-model="selectedKinds"
                                            :disabled="k.already_applied || !!busy"
                                        />
                                    </td>
                                    <td>{{ kindLabel(k.kind) }}</td>
                                    <td class="ins-num">{{ money(k.insurer_amount) }}</td>
                                    <td>
                                        <span :class="k.already_applied ? 'ins-ok' : 'ins-muted'">
                                            {{ k.already_applied ? t.applied : t.pending }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="totals" class="ins-muted ins-small">
                            {{ t.totals }} {{ money(totals.gross) }} ·
                            {{ t.insurerAmt }} {{ money(totals.insurer_total) }} ·
                            {{ t.patientPays }} {{ money(totals.patient_total) }}
                        </div>
                        <div v-if="openKinds.length" class="ins-row">
                            <button
                                type="button"
                                class="btn btn-sm btn-primary"
                                :disabled="!selectedKinds.length || !!busy"
                                @click="submitApply"
                            >{{ t.apply }}</button>
                        </div>
                    </template>
                </template>

                <!-- Claim -->
                <div class="ins-label">{{ t.claim }}</div>
                <div v-if="claim" class="ins-row">
                    <span class="ins-strong">{{ claim.claim_number || ('#' + claim.id) }}</span>
                    <span class="ins-chip">{{ claimStatusLabel(claim.status) }}</span>
                    <span v-if="claim.insurer_payable" class="ins-muted ins-small ins-push">
                        {{ t.insurerAmt }} {{ money(claim.insurer_payable) }}
                    </span>
                </div>
                <template v-else-if="canWrite">
                    <div class="ins-row">
                        <button type="button" class="btn btn-sm btn-outline" :disabled="!!busy" @click="submitClaim">
                            {{ t.createClaim }}
                        </button>
                    </div>
                    <div class="ins-muted ins-small">{{ t.claimHint }}</div>
                </template>
                <div v-else class="ins-muted ins-small">—</div>
            </template>

            <!-- ── Skip (either case) ────────────────────────────────── -->
            <template v-if="canWrite && !skippedAt && !claim">
                <button type="button" class="btn btn-sm btn-ghost ins-skip" :disabled="!!busy" @click="openSkip">
                    {{ t.skipTitle }}
                </button>
                <div v-if="skipOpen" class="ins-form">
                    <label class="ins-field">
                        <span>{{ t.skipReason }}</span>
                        <input v-model="skipReason" class="input" type="text" maxlength="500" />
                    </label>
                    <div class="ins-row">
                        <button type="button" class="btn btn-sm btn-primary" :disabled="!!busy" @click="submitSkip">{{ t.skipConfirm }}</button>
                        <button type="button" class="btn btn-sm btn-ghost" @click="skipOpen = false">{{ t.cancel }}</button>
                    </div>
                </div>
            </template>
        </template>
    </section>
</template>

<style scoped>
.ins { display: flex; flex-direction: column; gap: 8px; min-width: 0; border: 1px solid var(--line); border-radius: var(--radius-input); padding: 10px 12px; background: var(--bg-elev); }
.ins-label { display: flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); }
.ins-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ins-push { margin-inline-start: auto; }
.ins-muted { color: var(--fg-subtle); }
.ins-small { font-size: 12px; }
.ins-strong { font-size: 13px; font-weight: 600; color: var(--fg); }
.ins-err { font-size: 12px; color: var(--destructive); }
.ins-ok { color: var(--success); font-weight: 500; }
.ins-amber { font-size: 12.5px; font-weight: 500; color: var(--warning-text, var(--warning, #b45309)); background: var(--warning-soft, rgba(245, 158, 11, .12)); border: 1px solid var(--warning, #f59e0b); border-radius: var(--radius-input); padding: 7px 10px; }
.ins-info { font-size: 12.5px; color: var(--fg); background: var(--info-soft); border: 1px solid var(--info); border-radius: var(--radius-input); padding: 7px 10px; }
.ins-policy { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: var(--radius-input); background: var(--info-soft); border: 1px solid var(--info); }
.ins-chip { display: inline-block; margin-inline-start: 6px; padding: 1px 7px; border-radius: 999px; font-size: 11px; font-weight: 500; background: var(--bg-sunken); border: 1px solid var(--line); color: var(--fg-muted); text-transform: capitalize; }
.ins-form { display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px dashed var(--line-strong, var(--line)); border-radius: var(--radius-input); background: var(--bg-sunken); }
.ins-field { display: flex; flex-direction: column; gap: 3px; font-size: 12px; color: var(--fg-muted); }
.ins-field .input { height: 30px; font-size: 13px; }
.ins-table { width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid var(--line); border-radius: var(--radius-input); overflow: hidden; }
.ins-table th { font-size: 11px; font-weight: 600; color: var(--fg-subtle); text-align: start; padding: 5px 8px; background: var(--bg-sunken); border-bottom: 1px solid var(--line); }
.ins-table td { padding: 6px 8px; border-bottom: 1px solid var(--line); color: var(--fg); }
.ins-table tr:last-child td { border-bottom: 0; }
.ins-table tr.is-done td { color: var(--fg-muted); }
.ins-table th:first-child, .ins-table td:first-child { width: 28px; }
.ins-num { text-align: end !important; font-variant-numeric: tabular-nums; }
.ins-skip { align-self: flex-start; }
</style>
