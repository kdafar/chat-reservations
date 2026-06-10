<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({ balance: Number, purchased: Number, used: Number, purchases: Array, usage: Array, can_edit: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'نقاط واتساب', eyebrow: 'منصة واتساب', desc: 'كل رسالة قالب تُخصم من رصيد النقاط. اشحن الرصيد لإرسال الحملات.',
    balance: 'الرصيد الحالي', purchased: 'إجمالي المشحون', used: 'إجمالي المستخدم', low: 'الرصيد منخفض — اشحن النقاط قبل الإرسال.', zero: 'الرصيد صفر — لن تُرسَل أي حملة حتى الشحن.',
    topup: 'شحن النقاط', points: 'النقاط', amount: 'المبلغ المدفوع', currency: 'العملة', note: 'ملاحظة', add: 'إضافة نقاط', quick: 'شحن سريع',
    purchases: 'عمليات الشحن', usage: 'سجل الاستخدام', empty: 'لا يوجد سجل بعد',
    col: { points: 'النقاط', amount: 'المبلغ', gateway: 'المصدر', status: 'الحالة', note: 'ملاحظة', date: 'التاريخ', event: 'الحدث', campaign: 'الحملة', to: 'إلى' },
} : {
    title: 'WhatsApp Points', eyebrow: 'WhatsApp Platform', desc: 'Every template message is metered against this points balance. Top up to send campaigns.',
    balance: 'Current balance', purchased: 'Total purchased', used: 'Total used', low: 'Balance is low — top up before sending.', zero: 'Balance is 0 — campaigns will not send until you top up.',
    topup: 'Top up points', points: 'Points', amount: 'Amount paid', currency: 'Currency', note: 'Note', add: 'Add points', quick: 'Quick add',
    purchases: 'Top-ups', usage: 'Usage log', empty: 'Nothing here yet',
    col: { points: 'Points', amount: 'Amount', gateway: 'Source', status: 'Status', note: 'Note', date: 'Date', event: 'Event', campaign: 'Campaign', to: 'To' },
})

const nf = (n) => (n ?? 0).toLocaleString()
const form = useForm({ points: 1000, amount_paid: null, currency: 'KWD', note: '' })
function addPoints() { form.post(route('v2.wa-module.points.topup'), { preserveScroll: true, onSuccess: () => { form.reset('note', 'amount_paid') } }) }
function quick(n) { form.points = n; addPoints() }
const low = computed(() => (props.balance ?? 0) > 0 && (props.balance ?? 0) < 100)
const zero = computed(() => (props.balance ?? 0) <= 0)
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p>
        </div>

        <div v-if="zero || low" :style="{ background: (zero ? '#dc2626' : '#d97706') + '14', color: zero ? '#dc2626' : '#b45309' }" style="display:flex; align-items:center; gap:8px; padding:10px 14px; border-radius:10px; margin-bottom:14px; font-size:13px;">
            <Icon name="alert-triangle" :size="16" /> {{ zero ? t.zero : t.low }}
        </div>

        <!-- stat cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:12px; margin-bottom:16px;">
            <div class="card" style="padding:16px; display:flex; gap:12px; align-items:center;">
                <div :style="{ height:'44px', width:'44px', borderRadius:'12px', background:'#16a34a1a', color:'#16a34a', display:'flex', alignItems:'center', justifyContent:'center' }"><Icon name="coins" :size="20" /></div>
                <div><div style="font-size:24px; font-weight:700; color:var(--fg); line-height:1;">{{ nf(balance) }}</div><div style="font-size:12px; color:var(--fg-subtle); margin-top:3px;">{{ t.balance }}</div></div>
            </div>
            <div class="card" style="padding:16px; display:flex; gap:12px; align-items:center;">
                <div :style="{ height:'44px', width:'44px', borderRadius:'12px', background:'#3b82f61a', color:'#3b82f6', display:'flex', alignItems:'center', justifyContent:'center' }"><Icon name="arrow-down-circle" :size="20" /></div>
                <div><div style="font-size:24px; font-weight:700; color:var(--fg); line-height:1;">{{ nf(purchased) }}</div><div style="font-size:12px; color:var(--fg-subtle); margin-top:3px;">{{ t.purchased }}</div></div>
            </div>
            <div class="card" style="padding:16px; display:flex; gap:12px; align-items:center;">
                <div :style="{ height:'44px', width:'44px', borderRadius:'12px', background:'#ef44441a', color:'#ef4444', display:'flex', alignItems:'center', justifyContent:'center' }"><Icon name="arrow-up-circle" :size="20" /></div>
                <div><div style="font-size:24px; font-weight:700; color:var(--fg); line-height:1;">{{ nf(used) }}</div><div style="font-size:12px; color:var(--fg-subtle); margin-top:3px;">{{ t.used }}</div></div>
            </div>
        </div>

        <!-- top up -->
        <div v-if="can_edit" class="card" style="padding:16px 18px; margin-bottom:16px;">
            <div style="font-size:14px; font-weight:700; color:var(--fg); margin-bottom:12px;">{{ t.topup }}</div>
            <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <div style="flex:0 0 140px;"><label class="lbl">{{ t.points }}</label><input v-model.number="form.points" type="number" min="1" class="input" /></div>
                <div style="flex:0 0 130px;"><label class="lbl">{{ t.amount }}</label><input v-model.number="form.amount_paid" type="number" min="0" step="0.001" class="input" placeholder="0" /></div>
                <div style="flex:0 0 90px;"><label class="lbl">{{ t.currency }}</label><input v-model="form.currency" class="input" maxlength="8" /></div>
                <div style="flex:1; min-width:160px;"><label class="lbl">{{ t.note }}</label><input v-model="form.note" class="input" maxlength="191" /></div>
                <button class="btn btn-primary" :disabled="form.processing || !form.points" @click="addPoints"><Icon name="plus" :size="14" /> {{ t.add }}</button>
            </div>
            <div style="display:flex; gap:6px; align-items:center; margin-top:12px;">
                <span style="font-size:11px; color:var(--fg-faint);">{{ t.quick }}:</span>
                <button v-for="n in [1000,5000,10000,50000]" :key="n" class="btn btn-ghost btn-sm" :disabled="form.processing" @click="quick(n)">+{{ nf(n) }}</button>
            </div>
            <div v-if="form.errors.points" style="font-size:11px; color:var(--destructive); margin-top:6px;">{{ form.errors.points }}</div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <!-- purchases -->
            <div class="card" style="overflow:hidden;">
                <div style="padding:12px 16px; border-bottom:1px solid var(--line); font-size:14px; font-weight:700; color:var(--fg);">{{ t.purchases }}</div>
                <table class="table">
                    <thead><tr><th>{{ t.col.points }}</th><th>{{ t.col.amount }}</th><th>{{ t.col.gateway }}</th><th>{{ t.col.date }}</th></tr></thead>
                    <tbody>
                        <tr v-if="!purchases.length"><td colspan="4" style="text-align:center; padding:28px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                        <tr v-for="p in purchases" :key="p.id">
                            <td style="font-size:12px; font-weight:700; color:#16a34a;">+{{ nf(p.points) }}</td>
                            <td style="font-size:12px;">{{ p.amount ? nf(p.amount) + ' ' + p.currency : '—' }}</td>
                            <td style="font-size:11px; color:var(--fg-faint);">{{ p.gateway }}<span v-if="p.note"> · {{ p.note }}</span></td>
                            <td style="font-size:11px; color:var(--fg-faint);">{{ p.at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- usage -->
            <div class="card" style="overflow:hidden;">
                <div style="padding:12px 16px; border-bottom:1px solid var(--line); font-size:14px; font-weight:700; color:var(--fg);">{{ t.usage }}</div>
                <table class="table">
                    <thead><tr><th>{{ t.col.points }}</th><th>{{ t.col.event }}</th><th>{{ t.col.to }}</th><th>{{ t.col.date }}</th></tr></thead>
                    <tbody>
                        <tr v-if="!usage.length"><td colspan="4" style="text-align:center; padding:28px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                        <tr v-for="u in usage" :key="u.id">
                            <td style="font-size:12px; font-weight:700; color:#ef4444;">−{{ nf(u.points) }}</td>
                            <td style="font-size:11px; color:var(--fg-subtle);">{{ u.event }}</td>
                            <td class="mono" style="font-size:11px; color:var(--fg-faint);">{{ u.to || (u.campaign ? '#'+u.campaign : '—') }}</td>
                            <td style="font-size:11px; color:var(--fg-faint);">{{ u.at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.lbl { display:block; font-size:12px; font-weight:600; color:var(--fg-subtle); margin-bottom:6px; }
</style>
