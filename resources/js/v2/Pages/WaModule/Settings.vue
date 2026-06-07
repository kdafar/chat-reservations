<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

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
    },
})

const form = useForm({ settings: { ...props.settings } })
function save() { form.post(route('v2.wa-module.settings.update'), { preserveScroll: true }) }

const K = (k) => 'whatsapp.' + k
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:900px; margin:0 auto;">
        <div style="margin-bottom:16px;"><div class="eyebrow">{{ t.eyebrow }}</div><h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1><p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p></div>

        <div class="card" style="padding:14px 16px; margin-bottom:14px;">
            <h3 style="margin:0 0 8px; font-size:13px; font-weight:600; color:var(--fg);">{{ t.health }}</h3>
            <div v-if="!configured" style="font-size:13px; color:#92400e;">{{ t.notConfigured }}</div>
            <div v-else style="display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:8px; font-size:12px;">
                <div><span style="color:var(--fg-faint);">status:</span> <b>{{ health.status }}</b></div>
                <div v-if="health.display_phone_number"><span style="color:var(--fg-faint);">phone:</span> {{ health.display_phone_number }}</div>
                <div v-if="health.verified_name"><span style="color:var(--fg-faint);">name:</span> {{ health.verified_name }}</div>
                <div v-if="health.quality_rating"><span style="color:var(--fg-faint);">quality:</span> {{ health.quality_rating }}</div>
                <div v-if="health.messaging_limit_tier"><span style="color:var(--fg-faint);">tier:</span> {{ health.messaging_limit_tier }}</div>
            </div>
        </div>

        <div class="card" style="padding:18px;">
            <div style="display:grid; gap:14px;">
                <div>
                    <label style="font-size:12px; color:var(--fg-subtle);">{{ t.lbl.entry_mode }}</label>
                    <input v-model="form.settings[K('entry_mode')]" class="input" placeholder="flow" />
                </div>
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
