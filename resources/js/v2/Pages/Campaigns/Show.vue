<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import DateTimePicker from '../../Components/DateTimePicker.vue'

const props = defineProps({ campaign: Object, templateDef: Object, templates: Array, recipients: Array, regions: Array, statusCounts: Object })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'واتساب · الحملات', back: 'كل الحملات', save: 'حفظ التغييرات', deleteCampaign: 'حذف الحملة', delConfirm: 'حذف هذه الحملة وكل مستلميها؟',
    config: 'الإعداد', name: 'الاسم', template: 'القالب', rate: 'أقصى إرسال/دقيقة', schedule: 'الجدولة', variables: 'متغيرات القالب', bodyPreview: 'معاينة النص', headerImg: 'تتطلب صورة ترويسة', headerImgPath: 'مسار صورة الترويسة',
    recipients: 'المستلمون', addTitle: 'إضافة مستلمين', numbers: 'الأرقام (سطر لكل رقم)', region: 'المنطقة المفضّلة', nameOpt: 'الاسم (اختياري)', localeOpt: 'اللغة', add: 'إضافة', noRecipients: 'لا يوجد مستلمون بعد.',
    col: { phone: 'الهاتف', name: 'الاسم', locale: 'اللغة', source: 'المصدر', status: 'الحالة', error: 'الخطأ' },
    actions: 'الإجراءات', test: 'إرسال تجريبي', testPhone: 'هاتف الاختبار', queue: 'تحقّق وأرسل', sendTest: 'إرسال',
    statusLabel: 'الحالة',
} : {
    eyebrow: 'WhatsApp · Campaigns', back: 'All campaigns', save: 'Save changes', deleteCampaign: 'Delete campaign', delConfirm: 'Delete this campaign and all its recipients?',
    config: 'Configuration', name: 'Name', template: 'Template', rate: 'Max sends / minute', schedule: 'Schedule at', variables: 'Template variables', bodyPreview: 'Body preview', headerImg: 'Requires header image', headerImgPath: 'Header image path',
    recipients: 'Recipients', addTitle: 'Add recipients', numbers: 'Numbers (one per line)', region: 'Preferred region', nameOpt: 'Name (optional)', localeOpt: 'Locale', add: 'Add', noRecipients: 'No recipients yet.',
    col: { phone: 'Phone', name: 'Name', locale: 'Locale', source: 'Source', status: 'Status', error: 'Error' },
    actions: 'Actions', test: 'Send test', testPhone: 'Test phone', queue: 'Validate & queue', sendTest: 'Send',
    statusLabel: 'Status',
})

const statusColor = (s) => ({ draft: 'var(--fg-faint)', scheduled: 'var(--accent, #2563eb)', running: 'var(--warn, #d97706)', completed: 'var(--ok)', failed: 'var(--err, #dc2626)', paused: 'var(--fg-subtle)', pending: 'var(--fg-subtle)', sent: 'var(--ok)' }[s] || 'var(--fg-subtle)')

// config form
const cfg = reactive({
    name: props.campaign.name,
    template_name: props.campaign.template_name || '',
    send_rate_per_min: props.campaign.send_rate_per_min || 600,
    scheduled_at: props.campaign.scheduled_at || '',
    header_image_path: props.campaign.header_image_path || '',
    template_variables: { ...(props.campaign.template_variables || {}) },
})
const errors = ref({}), saving = ref(false)
const varIndexes = computed(() => props.templateDef?.var_indexes || [])
const needsHeaderImage = computed(() => props.templateDef?.header_format === 'IMAGE')
const templateItems = computed(() => props.templates.map((tpl) => ({ value: tpl.name, label: tpl.label })))
const varToken = (i) => '{' + '{' + i + '}' + '}'

function saveConfig() {
    saving.value = true; errors.value = {}
    router.put(route('v2.campaigns.update', { campaign: props.campaign.id }), { ...cfg }, {
        preserveScroll: true, onError: e => { errors.value = e }, onFinish: () => { saving.value = false },
    })
}
function destroy() { if (window.confirm(t.value.delConfirm)) router.delete(route('v2.campaigns.destroy', { campaign: props.campaign.id })) }

// recipients add
const add = reactive({ numbers: '', preferred_region: 'KW', name: '', locale: '' })
const adding = ref(false)
function addRecipients() {
    adding.value = true
    router.post(route('v2.campaigns.recipients.add', { campaign: props.campaign.id }), { ...add }, {
        preserveScroll: true, onSuccess: () => { add.numbers = '' }, onFinish: () => { adding.value = false },
    })
}
function delRecipient(r) { router.delete(route('v2.campaigns.recipients.delete', { campaign: props.campaign.id, recipient: r.id }), { preserveScroll: true }) }

// test + queue
const test = reactive({ test_msisdn: '', preferred_region: 'KW' })
const testing = ref(false)
function sendTest() {
    testing.value = true
    router.post(route('v2.campaigns.test', { campaign: props.campaign.id }), { ...test }, {
        preserveScroll: true, onSuccess: () => { test.test_msisdn = '' }, onFinish: () => { testing.value = false },
    })
}
const queueing = ref(false)
function queue() {
    queueing.value = true
    router.post(route('v2.campaigns.queue', { campaign: props.campaign.id }), {}, { preserveScroll: true, onFinish: () => { queueing.value = false } })
}
</script>

<template>
    <Head :title="campaign.name" />
    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <Link href="/admin/v2/campaigns" class="btn btn-ghost btn-sm" style="margin-bottom:12px;"><Icon name="arrow-left" :size="14" /><span>{{ t.back }}</span></Link>

        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ campaign.name }}</h1>
                <div style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span class="badge-status" :style="{ color: statusColor(campaign.status), borderColor: statusColor(campaign.status) }">{{ campaign.status }}</span>
                    <span class="mono" style="font-size:12px; color:var(--fg-subtle);">{{ campaign.template_name }}</span>
                    <span style="font-size:12px; color:var(--fg-faint);">· {{ campaign.total_recipients }} recipients · {{ campaign.sent_count }} sent · {{ campaign.failed_count }} failed</span>
                </div>
            </div>
            <button class="btn btn-ghost" style="color:var(--err, #dc2626);" @click="destroy"><Icon name="trash-2" :size="14" /><span>{{ t.deleteCampaign }}</span></button>
        </div>

        <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">
            <!-- Config -->
            <div class="card" style="padding:16px;">
                <h3 class="sec-h">{{ t.config }}</h3>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div><label class="label">{{ t.name }}</label><input v-model="cfg.name" class="input" /><div v-if="errors.name" class="err">{{ errors.name }}</div></div>
                    <div>
                        <label class="label">{{ t.template }}</label>
                        <SearchableSelect v-if="templates.length" v-model="cfg.template_name" :items="templateItems" :nullable="false" />
                        <input v-else v-model="cfg.template_name" class="input" />
                    </div>
                    <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div><label class="label">{{ t.rate }}</label><input v-model.number="cfg.send_rate_per_min" type="number" min="60" class="input" /></div>
                        <div><label class="label">{{ t.schedule }}</label><DateTimePicker v-model="cfg.scheduled_at" :locale="locale" :width="'100%'" :min-date="new Date().toISOString().slice(0, 10)" /></div>
                    </div>

                    <div v-if="templateDef?.body_text">
                        <label class="label">{{ t.bodyPreview }}</label>
                        <div style="font-size:13px; color:var(--fg-subtle); background:var(--bg-hover); border:1px solid var(--line); border-radius:8px; padding:10px; white-space:pre-wrap;">{{ templateDef.body_text }}</div>
                    </div>

                    <div v-if="varIndexes.length">
                        <label class="label">{{ t.variables }}</label>
                        <div v-for="i in varIndexes" :key="i" style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                            <span class="mono" style="font-size:12px; color:var(--fg-faint); width:36px;">{{ varToken(i) }}</span>
                            <input v-model="cfg.template_variables[i]" class="input" style="flex:1;" />
                        </div>
                    </div>

                    <div v-if="needsHeaderImage">
                        <label class="label">{{ t.headerImgPath }}</label>
                        <input v-model="cfg.header_image_path" class="input" placeholder="whatsapp/campaigns/headers/…" />
                        <div style="font-size:11px; color:var(--warn, #d97706); margin-top:4px;">{{ t.headerImg }}</div>
                    </div>

                    <button class="btn btn-primary" :disabled="saving" @click="saveConfig">{{ saving ? '…' : t.save }}</button>
                </div>
            </div>

            <!-- Actions -->
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div class="card" style="padding:16px;">
                    <h3 class="sec-h">{{ t.actions }}</h3>
                    <label class="label">{{ t.test }} <span class="req">*</span></label>
                    <div style="display:flex; gap:8px; align-items:flex-end;">
                        <input v-model="test.test_msisdn" class="input" :placeholder="t.testPhone" style="flex:1;" />
                        <SearchableSelect v-model="test.preferred_region" :items="regions" :nullable="false" :width="120" />
                        <button class="btn btn-outline" :disabled="testing || !test.test_msisdn" @click="sendTest"><Icon name="send" :size="13" /><span>{{ t.sendTest }}</span></button>
                    </div>
                    <button class="btn btn-primary" style="margin-top:14px; width:100%;" :disabled="queueing" @click="queue"><Icon name="check-circle" :size="14" /><span>{{ t.queue }}</span></button>
                </div>

                <div class="card" style="padding:16px;">
                    <h3 class="sec-h">{{ t.addTitle }}</h3>
                    <textarea v-model="add.numbers" class="input" rows="4" :placeholder="t.numbers"></textarea>
                    <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                        <div><label class="label">{{ t.region }}</label><SearchableSelect v-model="add.preferred_region" :items="regions" :nullable="false" /></div>
                        <div><label class="label">{{ t.localeOpt }}</label><SearchableSelect v-model="add.locale" :items="[{ value: 'en', label: 'EN' }, { value: 'ar', label: 'AR' }]" null-label="—" /></div>
                        <div style="grid-column:span 2;"><label class="label">{{ t.nameOpt }}</label><input v-model="add.name" class="input" /></div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:10px; width:100%;" :disabled="adding || !add.numbers.trim()" @click="addRecipients"><Icon name="plus" :size="13" /><span>{{ t.add }}</span></button>
                </div>
            </div>
        </div>

        <!-- Recipients table -->
        <div class="card" style="overflow:hidden; margin-top:16px;">
            <div style="padding:12px 16px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center;">
                <h3 class="sec-h" style="margin:0;">{{ t.recipients }} · {{ recipients.length }}</h3>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <span v-for="(c, s) in statusCounts" :key="s" class="badge-status" :style="{ color: statusColor(s), borderColor: statusColor(s) }">{{ s }}: {{ c }}</span>
                </div>
            </div>
            <table class="table">
                <thead><tr><th>{{ t.col.phone }}</th><th>{{ t.col.name }}</th><th>{{ t.col.locale }}</th><th>{{ t.col.source }}</th><th>{{ t.col.status }}</th><th>{{ t.col.error }}</th><th style="width:50px;"></th></tr></thead>
                <tbody>
                    <tr v-if="!recipients.length"><td colspan="7" style="text-align:center; padding:32px; color:var(--fg-faint);">{{ t.noRecipients }}</td></tr>
                    <tr v-for="r in recipients" :key="r.id">
                        <td class="mono" style="font-size:12px; font-weight:600;">{{ r.msisdn }}</td>
                        <td>{{ r.name || '—' }}</td>
                        <td><span class="badge-muted">{{ r.locale || '—' }}</span></td>
                        <td style="font-size:12px; color:var(--fg-subtle);">{{ r.source || '—' }}</td>
                        <td><span class="badge-status" :style="{ color: statusColor(r.status), borderColor: statusColor(r.status) }">{{ r.status }}</span></td>
                        <td style="font-size:11px; color:var(--err, #dc2626); max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ r.error_message || '' }}</td>
                        <td><button class="btn btn-ghost btn-sm btn-icon" @click="delRecipient(r)"><Icon name="trash-2" :size="14" /></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.sec-h { margin:0 0 12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:9px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-muted { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; text-transform:uppercase; }
.badge-status { display:inline-block; padding:2px 8px; font-size:10.5px; font-weight:600; border:1px solid; border-radius:999px; text-transform:capitalize; }
</style>
