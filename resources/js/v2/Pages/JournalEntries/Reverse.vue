<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import { formatMoney as fmt } from '../../lib/money.js'

const props = defineProps({
    entry: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'المحاسبة', back: 'القيود اليومية', title: 'عكس القيد',
    desc: 'سيُنشأ قيد عكسي مطابق ومقابل لهذا القيد المُرحّل. القيد الأصلي يبقى كما هو.',
    summary: 'القيد الأصلي', date: 'التاريخ', narration: 'البيان',
    account: 'الحساب', debit: 'مدين', credit: 'دائن',
    reason: 'سبب العكس', reverseDo: 'عكس القيد', cancel: 'إلغاء',
} : {
    eyebrow: 'Accounting', back: 'Journal Entries', title: 'Reverse entry',
    desc: 'A mirror-image reversing entry will be created to cancel this posted entry. The original stays untouched.',
    summary: 'Original entry', date: 'Date', narration: 'Narration',
    account: 'Account', debit: 'Debit', credit: 'Credit',
    reason: 'Reversal reason', reverseDo: 'Reverse entry', cancel: 'Cancel',
})

const form = reactive({ reason: '' })
const errors = ref({})
const saving = ref(false)
const indexUrl = route('v2.accounting.journal-entries.index')

function submit() {
    saving.value = true; errors.value = {}
    router.post(route('v2.accounting.journal-entries.reverse', { journalEntry: props.entry.id }), { ...form }, {
        onError: (e) => { errors.value = e; saving.value = false },
    })
}
</script>

<template>
    <Head :title="`${t.title} · ${entry.code}`" />
    <div style="padding:24px; max-width:760px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <Link :href="indexUrl" class="btn btn-ghost btn-sm" style="margin-bottom:8px;">
                <Icon name="arrow-left" :size="14" class="flip-rtl" /><span>{{ t.back }}</span>
            </Link>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }} · <span class="mono">{{ entry.code }}</span></h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p>
        </div>

        <div class="card" style="padding:18px; margin-bottom:16px;">
            <div class="eyebrow" style="margin-bottom:10px;">{{ t.summary }}</div>
            <div style="display:flex; gap:20px; font-size:13px; margin-bottom:12px; color:var(--fg-muted);">
                <span><strong style="color:var(--fg);">{{ t.date }}:</strong> {{ entry.entry_date }}</span>
                <span><strong style="color:var(--fg);">{{ t.narration }}:</strong> {{ entry.narration }}</span>
            </div>
            <table class="table">
                <thead>
                    <tr><th>{{ t.account }}</th><th style="text-align:end;">{{ t.debit }}</th><th style="text-align:end;">{{ t.credit }}</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(l, i) in entry.lines" :key="i">
                        <td>{{ l.account }}</td>
                        <td class="mono" style="text-align:end;">{{ l.debit ? fmt(l.debit) : '—' }}</td>
                        <td class="mono" style="text-align:end;">{{ l.credit ? fmt(l.credit) : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form @submit.prevent="submit" class="card" style="padding:18px;">
            <label class="label">{{ t.reason }} <span class="req">*</span></label>
            <textarea v-model="form.reason" rows="3" class="input" required maxlength="500"></textarea>
            <div v-if="errors.reason" class="err">{{ errors.reason }}</div>
            <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:14px; margin-top:14px; border-top:1px solid var(--line);">
                <Link :href="indexUrl" class="btn btn-ghost">{{ t.cancel }}</Link>
                <button type="submit" class="btn btn-destructive" :disabled="saving">{{ saving ? '…' : t.reverseDo }}</button>
            </div>
        </form>
    </div>
</template>
