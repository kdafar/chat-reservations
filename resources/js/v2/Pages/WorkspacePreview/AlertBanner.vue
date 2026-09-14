<script setup>
/**
 * Allergies and alerts, under the patient's name on every tab.
 *
 * Three states that must never look alike:
 *   - allergies recorded      red/amber chips, worst first
 *   - "No known allergies"    someone asked and the answer was none
 *   - not recorded            nobody asked — shown as a prompt, because an
 *                             empty list here is exactly how an allergy gets
 *                             missed
 *
 * Adding an allergy or alert is local, like everything on this page.
 */
import { computed, ref, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import { logEvent } from './clinical.js'
import { vTip } from './tooltip.js'

const props = defineProps({
    row: { type: Object, required: true },
    readonly: { type: Boolean, default: false },
})

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')
const v = props.row

const SEV_RANK = { severe: 0, moderate: 1, mild: 2 }
const allergies = computed(() => [...(v.allergies ?? [])].sort((a, b) => (SEV_RANK[a.severity] ?? 3) - (SEV_RANK[b.severity] ?? 3)))

const adding = ref(null)   // null | 'allergy' | 'alert'
const form = ref({ name: '', reaction: '', severity: 'moderate', text: '' })
const nameEl = ref(null)

function startAdd(kind) {
    adding.value = kind
    form.value = { name: '', reaction: '', severity: 'moderate', text: '' }
    nextTick(() => nameEl.value?.focus())
}
function saveAllergy() {
    const name = form.value.name.trim()
    if (!name) return
    v.allergies = [...(v.allergies ?? []), { name, class: guessClass(name), reaction: form.value.reaction.trim() || null, severity: form.value.severity }]
    v.allergies_recorded = true
    logEvent(v, 'allergy', isRtl.value ? `سُجّلت حساسية: ${name}` : `Allergy recorded: ${name}`)
    adding.value = null
}
function saveAlert() {
    const text = form.value.text.trim()
    if (!text) return
    v.alerts = [...(v.alerts ?? []), { kind: /warfarin|apixaban|rivaroxaban|مميع/i.test(text) ? 'anticoagulant' : /pregnan|حامل/i.test(text) ? 'pregnancy' : 'note', text }]
    logEvent(v, 'allergy', isRtl.value ? `تنبيه: ${text}` : `Alert added: ${text}`)
    adding.value = null
}
function markNone() {
    v.allergies = []
    v.allergies_recorded = true
    logEvent(v, 'allergy', isRtl.value ? 'لا توجد حساسية معروفة' : 'No known allergies confirmed')
}
function removeAllergy(a) { v.allergies = v.allergies.filter((x) => x !== a) }
function removeAlert(a) { v.alerts = v.alerts.filter((x) => x !== a) }

/* A typed allergy should still catch the drugs of its class. */
function guessClass(name) {
    const n = name.toLowerCase()
    if (/penicillin|amoxi|augmentin|بنسلين/.test(n)) return 'penicillin'
    if (/sulfa|سلفا/.test(n)) return 'sulfonamide'
    if (/nsaid|ibuprofen|aspirin|diclofenac|بروفين|أسبرين/.test(n)) return 'nsaid'
    if (/macrolide|azithro|erythro/.test(n)) return 'macrolide'
    return null
}

const t = computed(() => isRtl.value ? {
    allergy: 'حساسية', nka: 'لا توجد حساسية معروفة', notRecorded: 'الحساسية غير مسجلة', ask: 'اسأل المريض',
    none: 'لا يوجد', add: 'إضافة', addAllergy: 'حساسية', addAlert: 'تنبيه', name: 'مسبب الحساسية', reaction: 'رد الفعل',
    alertText: 'التنبيه (مثال: يتناول وارفارين)', save: 'حفظ', cancel: 'إلغاء',
    sev: { severe: 'شديدة', moderate: 'متوسطة', mild: 'خفيفة' }, remove: 'إزالة', conditions: 'حالات مزمنة',
} : {
    allergy: 'Allergy', nka: 'No known allergies', notRecorded: 'Allergies not recorded', ask: 'Ask the patient',
    none: 'None', add: 'Add', addAllergy: 'Allergy', addAlert: 'Alert', name: 'Allergic to…', reaction: 'Reaction',
    alertText: 'Alert (e.g. on warfarin)', save: 'Save', cancel: 'Cancel',
    sev: { severe: 'Severe', moderate: 'Moderate', mild: 'Mild' }, remove: 'Remove', conditions: 'Conditions',
})
</script>

<template>
    <div class="ab" :class="{ 'is-danger': allergies.some((a) => a.severity === 'severe'), 'is-unknown': !row.allergies_recorded }">
        <div class="ab-line">
            <!-- Allergies -->
            <template v-if="allergies.length">
                <span
                    v-for="a in allergies" :key="a.name"
                    class="ab-chip" :class="`is-${a.severity}`"
                    v-tip="`${t.sev[a.severity] ?? ''}${a.reaction ? ' · ' + a.reaction : ''}`"
                >
                    <Icon name="shield-alert" :size="12" />
                    <strong>{{ a.name }}</strong><span v-if="a.reaction" class="ab-sub">{{ a.reaction }}</span>
                    <button v-if="!readonly" type="button" class="ab-x" :aria-label="t.remove" @click="removeAllergy(a)"><Icon name="x" :size="10" /></button>
                </span>
            </template>
            <span v-else-if="row.allergies_recorded" class="ab-ok"><Icon name="shield-check" :size="12" />{{ t.nka }}</span>
            <span v-else class="ab-unknown">
                <Icon name="shield-question" :size="12" />{{ t.notRecorded }}
                <template v-if="!readonly">
                    <span class="ab-dot">·</span>{{ t.ask }}:
                    <button type="button" class="ab-link" @click="markNone">{{ t.none }}</button>
                    <button type="button" class="ab-link" @click="startAdd('allergy')">{{ t.addAllergy }}</button>
                </template>
            </span>

            <!-- Alerts: pregnancy, anticoagulant, anything that changes what is safe -->
            <span v-for="a in row.alerts ?? []" :key="a.text" class="ab-alert">
                <Icon :name="a.kind === 'pregnancy' ? 'baby' : a.kind === 'anticoagulant' ? 'droplet' : 'alert-triangle'" :size="12" />{{ a.text }}
                <button v-if="!readonly" type="button" class="ab-x" :aria-label="t.remove" @click="removeAlert(a)"><Icon name="x" :size="10" /></button>
            </span>

            <!-- Conditions: context, not an alarm -->
            <span v-if="row.conditions?.length" class="ab-cond"><Icon name="heart-pulse" :size="12" />{{ row.conditions.join(' · ') }}</span>

            <span v-if="!readonly && !adding" class="ab-adds">
                <button v-if="row.allergies_recorded" type="button" class="ab-link" @click="startAdd('allergy')"><Icon name="plus" :size="11" />{{ t.addAllergy }}</button>
                <button type="button" class="ab-link" @click="startAdd('alert')"><Icon name="plus" :size="11" />{{ t.addAlert }}</button>
            </span>
        </div>

        <form v-if="adding === 'allergy'" class="ab-form" @submit.prevent="saveAllergy" @keydown.esc.stop="adding = null">
            <input ref="nameEl" v-model="form.name" class="input" :placeholder="t.name" />
            <input v-model="form.reaction" class="input" :placeholder="t.reaction" />
            <span class="ab-sev" role="group">
                <button v-for="s in ['mild', 'moderate', 'severe']" :key="s" type="button" :class="{ 'is-active': form.severity === s, [`is-${s}`]: true }" :aria-pressed="form.severity === s" @click="form.severity = s">{{ t.sev[s] }}</button>
            </span>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="!form.name.trim()">{{ t.save }}</button>
            <button type="button" class="btn btn-ghost btn-sm" @click="adding = null">{{ t.cancel }}</button>
        </form>
        <form v-else-if="adding === 'alert'" class="ab-form" @submit.prevent="saveAlert" @keydown.esc.stop="adding = null">
            <input ref="nameEl" v-model="form.text" class="input" style="flex: 2;" :placeholder="t.alertText" />
            <button type="submit" class="btn btn-primary btn-sm" :disabled="!form.text.trim()">{{ t.save }}</button>
            <button type="button" class="btn btn-ghost btn-sm" @click="adding = null">{{ t.cancel }}</button>
        </form>
    </div>
</template>

<style scoped>
.ab { padding: 6px 14px; border-bottom: 1px solid var(--line); background: var(--bg-elev); display: flex; flex-direction: column; gap: 6px; }
.ab.is-danger { background: color-mix(in oklch, var(--destructive) 7%, var(--bg-elev)); border-bottom-color: color-mix(in oklch, var(--destructive) 30%, var(--line)); }
.ab.is-unknown { background: color-mix(in oklch, var(--warning) 7%, var(--bg-elev)); }
.ab-line { display: flex; align-items: center; gap: 6px 12px; flex-wrap: wrap; font-size: 12px; min-height: 22px; }
.ab-chip { display: inline-flex; align-items: center; gap: 5px; height: 22px; padding: 0 8px; border-radius: 5px; font-size: 12px; }
.ab-chip strong { font-weight: 600; }
.ab-chip.is-severe { background: var(--destructive); color: #fff; }
.ab-chip.is-moderate { background: color-mix(in oklch, var(--destructive) 15%, transparent); color: var(--wsp-bad-text); }
.ab-chip.is-mild { background: color-mix(in oklch, var(--warning) 16%, transparent); color: var(--wsp-warn-text); }
.ab-sub { opacity: .85; }
.ab-sub::before { content: '· '; }
.ab-x { display: inline-flex; background: none; border: 0; padding: 0; margin-inline-start: 2px; color: inherit; opacity: .6; cursor: pointer; }
.ab-x:hover { opacity: 1; }
.ab-ok { display: inline-flex; align-items: center; gap: 5px; color: var(--wsp-ok-text); font-weight: 500; }
.ab-unknown { display: inline-flex; align-items: center; gap: 5px; color: var(--wsp-warn-text); font-weight: 600; flex-wrap: wrap; }
.ab-dot { color: var(--fg-faint); font-weight: 400; }
.ab-alert { display: inline-flex; align-items: center; gap: 5px; height: 22px; padding: 0 8px; border-radius: 5px; font-weight: 500;
    background: color-mix(in oklch, var(--warning) 16%, transparent); color: var(--wsp-warn-text); }
.ab-cond { display: inline-flex; align-items: center; gap: 5px; color: var(--fg-muted); }
.ab-cond :deep(svg) { color: var(--fg-faint); }
.ab-adds { display: inline-flex; gap: 10px; margin-inline-start: auto; }
.ab-link { display: inline-flex; align-items: center; gap: 3px; background: none; border: 0; padding: 0; font: inherit; font-size: 12px; font-weight: 600; color: var(--accent); cursor: pointer; }
.ab-link:hover { text-decoration: underline; }
.ab-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.ab-form .input { height: 30px; font-size: 12.5px; flex: 1; min-width: 140px; }
.ab-sev { display: inline-flex; gap: 3px; }
.ab-sev button { height: 30px; padding: 0 9px; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--bg-elev); color: var(--fg-muted); font: inherit; font-size: 12px; cursor: pointer; }
.ab-sev button.is-active { background: var(--accent-bg); border-color: var(--primary); color: var(--fg); font-weight: 600; }
.ab-sev button.is-active.is-severe { background: color-mix(in oklch, var(--destructive) 15%, transparent); border-color: var(--destructive); color: var(--wsp-bad-text); }
</style>
