<script setup>
import { computed, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    filters: { type: Object, required: true },
    periods: { type: Array, required: true },
    years: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    counts: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'الفترات المحاسبية', eyebrow: 'المحاسبة',
    desc: 'فترات شهرية تُنشأ تلقائيًا وترتبط بها كل القيود. أغلق الفترة لترحيل قيد الإقفال.',
    status: { all: 'الكل', open: 'مفتوحة', closed: 'مغلقة' }, allYears: 'كل السنوات',
    col: { code: 'الرمز', period: 'الفترة', status: 'الحالة', closingJe: 'قيد الإقفال', closedAt: 'أُغلقت في', closedBy: 'بواسطة' },
    close: 'إغلاق', reopen: 'إعادة فتح', empty: 'لا توجد فترات',
    stats: { open: 'مفتوحة', closed: 'مغلقة' },
    confirmClose: { title: 'إغلاق الفترة؟', body: 'سيتم ترحيل قيد الإقفال ومنع أي قيود جديدة في هذه الفترة.', ok: 'إغلاق الفترة' },
    confirmReopen: { title: 'إعادة فتح الفترة؟', body: 'سيتم عكس قيد الإقفال والسماح بقيود جديدة مجددًا.', ok: 'إعادة الفتح' },
} : {
    title: 'Accounting Periods', eyebrow: 'Accounting',
    desc: 'Auto-created monthly periods that every journal entry attaches to. Close a period to post its closing entry.',
    status: { all: 'All', open: 'Open', closed: 'Closed' }, allYears: 'All years',
    col: { code: 'Code', period: 'Period', status: 'Status', closingJe: 'Closing entry', closedAt: 'Closed at', closedBy: 'By' },
    close: 'Close', reopen: 'Reopen', empty: 'No periods',
    stats: { open: 'Open', closed: 'Closed' },
    confirmClose: { title: 'Close this period?', body: 'The closing journal entry will be posted and no new entries can be made in this period.', ok: 'Close period' },
    confirmReopen: { title: 'Reopen this period?', body: 'The closing entry will be reversed and the period will accept entries again.', ok: 'Reopen' },
})

const f = reactive({ status: props.filters.status || 'all', year: props.filters.year || '' })
watch(() => [f.status, f.year], () => apply())
function apply() {
    router.get(route('v2.accounting.periods.index'), {
        status: f.status === 'all' ? undefined : f.status,
        year: f.year || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

async function doClose(p) {
    if (!await confirm({ title: t.value.confirmClose.title, body: t.value.confirmClose.body, confirmLabel: t.value.confirmClose.ok, tone: 'destructive', icon: 'lock' })) return
    router.post(route('v2.accounting.periods.close', { period: p.id }), {}, { preserveScroll: true })
}
async function doReopen(p) {
    if (!await confirm({ title: t.value.confirmReopen.title, body: t.value.confirmReopen.body, confirmLabel: t.value.confirmReopen.ok, tone: 'primary', icon: 'lock-open' })) return
    router.post(route('v2.accounting.periods.reopen', { period: p.id }), {}, { preserveScroll: true })
}
</script>

<template>
    <Head :title="t.title" />

    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.open }}</span><span class="stat-chip-lbl">{{ t.stats.open }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--fg-faint);">{{ counts.closed }}</span><span class="stat-chip-lbl">{{ t.stats.closed }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div class="seg seg-sm">
                <button :class="f.status === 'all' ? 'is-active' : ''" @click="f.status = 'all'">{{ t.status.all }}</button>
                <button :class="f.status === 'open' ? 'is-active' : ''" @click="f.status = 'open'">{{ t.status.open }}</button>
                <button :class="f.status === 'closed' ? 'is-active' : ''" @click="f.status = 'closed'">{{ t.status.closed }}</button>
            </div>
            <SearchableSelect v-model="f.year" :items="years" :null-label="t.allYears" :width="200" />
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.code }}</th>
                        <th>{{ t.col.period }}</th>
                        <th>{{ t.col.status }}</th>
                        <th>{{ t.col.closingJe }}</th>
                        <th>{{ t.col.closedBy }}</th>
                        <th v-if="canManage" style="width:120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!periods.length"><td :colspan="canManage ? 6 : 5" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="p in periods" :key="p.id">
                        <td class="mono" style="font-weight:700;">{{ p.code }}</td>
                        <td class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ p.start_date }} → {{ p.end_date }}</td>
                        <td><span :class="p.status === 'open' ? 'badge-ok' : 'badge-muted'">{{ t.status[p.status] }}</span></td>
                        <td class="mono" style="font-size:12px;">{{ p.closing_je || '—' }}</td>
                        <td style="font-size:12px; color:var(--fg-subtle);">
                            <span v-if="p.closed_by">{{ p.closed_by }}</span>
                            <span v-else>—</span>
                            <div v-if="p.closed_at" class="mono" style="font-size:11px; color:var(--fg-faint);">{{ p.closed_at }}</div>
                        </td>
                        <td v-if="canManage">
                            <button v-if="p.status === 'open'" class="btn btn-ghost btn-sm" style="color:var(--err, #dc2626);" @click="doClose(p)">
                                <Icon name="lock" :size="13" /><span>{{ t.close }}</span>
                            </button>
                            <button v-else class="btn btn-ghost btn-sm" style="color:var(--warn, #d97706);" @click="doReopen(p)">
                                <Icon name="lock-open" :size="13" /><span>{{ t.reopen }}</span>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-ok { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
.badge-muted { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; }
</style>
