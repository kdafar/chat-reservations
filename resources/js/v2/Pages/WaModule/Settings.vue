<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({ settings: Object, health: Object, configured: Boolean })
const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    title: 'الإعدادات', eyebrow: 'منصة واتساب', desc: 'رسائل البوت وسلوكه + حالة الرقم.', save: 'حفظ الإعدادات',
    health: 'حالة الرقم', notConfigured: 'واتساب غير مُهيّأ — أضف WHATSAPP_API_TOKEN.',
    sec: { entry: 'وضع الدخول', greet: 'رسائل الترحيب', replies: 'الردود الجاهزة', ops: 'التشغيل' },
    lbl: {
        entry_mode: 'وضع الدخول (flow/list)', banner_en: 'ترحيب (EN)', banner_ar: 'ترحيب (AR)',
        welcome_en: 'رسالة التدفق (EN)', welcome_ar: 'رسالة التدفق (AR)', fallback_en: 'رد افتراضي (EN)', fallback_ar: 'رد افتراضي (AR)',
        about_en: 'من نحن (EN)', about_ar: 'من نحن (AR)', pricing_en: 'الأسعار (EN)', pricing_ar: 'الأسعار (AR)',
        privacy_en: 'الخصوصية (EN)', privacy_ar: 'الخصوصية (AR)', whitelist: 'استثناء حد التكرار (أرقام مفصولة بفواصل)', restricted: 'تقييد البدء (1/0)',
        stopKeywords: 'كلمات الإيقاف (يتخطّى الرد التلقائي)', modeNote: 'الردود الجاهزة أدناه تعمل في وضع «الرد بالكلمات المفتاحية».',
    },
} : {
    title: 'Settings', eyebrow: 'WhatsApp Platform', desc: 'Bot messages & behavior + number health.', save: 'Save settings',
    health: 'Number health', notConfigured: 'WhatsApp not configured — add WHATSAPP_API_TOKEN.',
    sec: { entry: 'Entry mode', greet: 'Greetings', replies: 'Canned replies', ops: 'Operational' },
    lbl: {
        entry_mode: 'Entry mode (flow/list)', banner_en: 'Greeting (EN)', banner_ar: 'Greeting (AR)',
        welcome_en: 'Flow welcome (EN)', welcome_ar: 'Flow welcome (AR)', fallback_en: 'Fallback reply (EN)', fallback_ar: 'Fallback reply (AR)',
        about_en: 'About (EN)', about_ar: 'About (AR)', pricing_en: 'Pricing (EN)', pricing_ar: 'Pricing (AR)',
        privacy_en: 'Privacy (EN)', privacy_ar: 'Privacy (AR)', whitelist: 'Frequency-cap whitelist (comma phones)', restricted: 'Restrict initiation (1/0)',
        stopKeywords: 'Stop keywords (skip auto-reply)', modeNote: 'The canned replies below run in “Keyword auto-reply” mode.',
    },
})

const form = useForm({ settings: { ...props.settings } })
function save() { form.post(route('v2.wa-module.settings.update'), { preserveScroll: true }) }

const K = (k) => 'whatsapp.' + k

const entryItems = computed(() => isRtl.value ? [
    { value: 'flow', label: 'تدفّق (قائمة تفاعلية)' },
    { value: 'list', label: 'قائمة' },
    { value: 'keyword', label: 'رد بالكلمات المفتاحية' },
] : [
    { value: 'flow', label: 'Flow (interactive menu)' },
    { value: 'list', label: 'List' },
    { value: 'keyword', label: 'Keyword auto-reply' },
])
const isKeywordMode = computed(() => (form.settings[K('entry_mode')] || '') === 'keyword')

const q = computed(() => String(props.health?.quality_rating || '').toUpperCase())
const qualityLabel = computed(() => ({ GREEN: 'High quality', HIGH: 'High quality', YELLOW: 'Medium quality', MEDIUM: 'Medium quality', RED: 'Low quality', LOW: 'Low quality' }[q.value] || (props.health?.status === 'ok' ? 'Connected' : props.health?.status || 'Unknown')))
const qualityDot = computed(() => ({ GREEN: '#16a34a', HIGH: '#16a34a', YELLOW: '#d97706', MEDIUM: '#d97706', RED: '#dc2626', LOW: '#dc2626' }[q.value] || (props.health?.status === 'ok' ? '#16a34a' : '#94a3b8')))
const qualityStyle = computed(() => ({ background: qualityDot.value + '1a', color: qualityDot.value }))
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:900px; margin:0 auto;">
        <div style="margin-bottom:16px;"><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>

        <div class="card" style="padding:14px 16px; margin-bottom:14px;">
            <h3 style="margin:0 0 8px; font-size:13px; font-weight:600; color:var(--fg);">{{ t.health }}</h3>
            <div v-if="!configured" style="font-size:13px; color:#92400e;">{{ t.notConfigured }}</div>
            <div v-else>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
                    <span :style="{ display:'inline-flex', alignItems:'center', gap:'6px', padding:'4px 11px', borderRadius:'20px', fontSize:'12px', fontWeight:'600', ...qualityStyle }">
                        <span :style="{ height:'8px', width:'8px', borderRadius:'50%', background: qualityDot }"></span>{{ qualityLabel }}
                    </span>
                    <span v-if="health.display_phone_number" class="mono" style="font-size:13px; color:var(--fg);">{{ health.display_phone_number }}</span>
                    <span v-if="health.verified_name" style="font-size:13px; color:var(--fg-subtle);">· {{ health.verified_name }}</span>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:8px 16px; font-size:12px;">
                    <div><span style="color:var(--fg-faint);">Messaging tier:</span> <b>{{ health.effective_messaging_limit_tier || health.messaging_limit_tier || '—' }}</b></div>
                    <div><span style="color:var(--fg-faint);">Name status:</span> {{ health.name_status || '—' }}</div>
                    <div><span style="color:var(--fg-faint);">Throughput:</span> {{ health.throughput_level || 'Standard' }}</div>
                    <div><span style="color:var(--fg-faint);">Platform:</span> {{ health.platform_type || '—' }}</div>
                    <div><span style="color:var(--fg-faint);">Verification:</span> {{ health.code_verification_status || '—' }}</div>
                </div>
            </div>
        </div>

        <div class="card" style="padding:18px;">
            <div style="display:grid; gap:14px;">
                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                    <div style="flex:1; min-width:220px;">
                        <label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.entry_mode }}</label>
                        <SearchableSelect v-model="form.settings[K('entry_mode')]" :items="entryItems" :nullable="false" />
                    </div>
                    <div style="flex:2; min-width:240px;">
                        <label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.stopKeywords }}</label>
                        <input v-model="form.settings[K('stop_keywords')]" class="input" placeholder="agent, human, support" />
                    </div>
                </div>
                <div v-if="isKeywordMode" style="font-size:12px; color:#2563eb; background:#2563eb12; padding:8px 12px; border-radius:8px;">💡 {{ t.lbl.modeNote }}</div>
                <hr style="border:none; border-top:1px solid var(--border);" />
                <div style="font-size:12px; font-weight:600; color:var(--fg);">{{ t.sec.greet }}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.banner_en }}</label><textarea v-model="form.settings[K('banner_greeting_en')]" class="input" rows="3"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.banner_ar }}</label><textarea v-model="form.settings[K('banner_greeting_ar')]" class="input" rows="3" dir="rtl"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.welcome_en }}</label><textarea v-model="form.settings[K('flow_welcome_en')]" class="input" rows="3"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.welcome_ar }}</label><textarea v-model="form.settings[K('flow_welcome_ar')]" class="input" rows="3" dir="rtl"></textarea></div>
                </div>
                <hr style="border:none; border-top:1px solid var(--border);" />
                <div style="font-size:12px; font-weight:600; color:var(--fg);">{{ t.sec.replies }}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.fallback_en }}</label><textarea v-model="form.settings[K('fallback_reply_en')]" class="input" rows="2"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.fallback_ar }}</label><textarea v-model="form.settings[K('fallback_reply_ar')]" class="input" rows="2" dir="rtl"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.about_en }}</label><textarea v-model="form.settings[K('about_reply_en')]" class="input" rows="2"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.about_ar }}</label><textarea v-model="form.settings[K('about_reply_ar')]" class="input" rows="2" dir="rtl"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.pricing_en }}</label><textarea v-model="form.settings[K('pricing_reply_en')]" class="input" rows="2"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.pricing_ar }}</label><textarea v-model="form.settings[K('pricing_reply_ar')]" class="input" rows="2" dir="rtl"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.privacy_en }}</label><textarea v-model="form.settings[K('privacy_reply_en')]" class="input" rows="2"></textarea></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.privacy_ar }}</label><textarea v-model="form.settings[K('privacy_reply_ar')]" class="input" rows="2" dir="rtl"></textarea></div>
                </div>
                <hr style="border:none; border-top:1px solid var(--border);" />
                <div style="font-size:12px; font-weight:600; color:var(--fg);">{{ t.sec.ops }}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.whitelist }}</label><input v-model="form.settings[K('frequency_cap_whitelist')]" class="input" /></div>
                    <div><label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.restricted }}</label><input v-model="form.settings['wa_initiation_restricted']" class="input" /></div>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; margin-top:18px;"><button class="btn btn-primary" :disabled="form.processing" @click="save"><Icon name="save" :size="14" /> {{ t.save }}</button></div>
        </div>
    </div>
</template>
