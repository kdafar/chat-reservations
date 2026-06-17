<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'

const props = defineProps({
    fields: Array,
    can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'الإعدادات', eyebrow: 'المنصة',
    desc: 'إعدادات النظام وتكامل واتساب. القيم السرّية لا تُعرض — اتركها فارغة للإبقاء عليها.',
    save: 'حفظ الإعدادات', saved: 'تم الحفظ', secretSet: 'محفوظ — اتركه فارغاً للإبقاء', secretEmpty: 'غير مضبوط',
} : {
    title: 'Settings', eyebrow: 'Platform',
    desc: 'System configuration and WhatsApp integration. Secret values are never shown — leave blank to keep the current value.',
    save: 'Save settings', saved: 'Saved', secretSet: 'Set — leave blank to keep', secretEmpty: 'Not set',
})

const groups = computed(() => {
    const out = {}
    for (const fld of props.fields) (out[fld.group] ??= []).push(fld)
    return out
})

const form = reactive({})
for (const fld of props.fields) form[fld.key] = fld.type === 'bool' ? !!fld.value : (fld.value ?? '')

const saving = ref(false)
function submit() {
    saving.value = true
    router.put(route('v2.settings.update'), { values: { ...form } }, {
        preserveScroll: true, onFinish: () => { saving.value = false },
    })
}

function uploadLogo(e) {
    const file = e.target.files?.[0]
    if (!file) return
    const fd = new FormData()
    fd.append('logo', file)
    router.post(route('v2.settings.logo.upload'), fd, { preserveScroll: true, forceFormData: true })
    e.target.value = ''
}
function removeLogo() {
    router.delete(route('v2.settings.logo.remove'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t.title" />
        <div style="padding:24px; max-width:860px; margin:0 auto;">
            <div style="margin-bottom:20px;">
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
            </div>

            <form @submit.prevent="submit">
                <div v-for="(items, group) in groups" :key="group" class="card" style="padding:16px; margin-bottom:14px;">
                    <h3 style="margin:0 0 12px; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-subtle);">{{ group }}</h3>
                    <div style="display:flex; flex-direction:column; gap:14px;">
                        <div v-for="fld in items" :key="fld.key" class="rgrid-split" style="display:grid; grid-template-columns:1fr 1.4fr; gap:16px; align-items:center;">
                            <label :for="'set_' + fld.key" style="font-size:13px; font-weight:500; color:var(--fg);">
                                {{ fld.label }}
                                <span class="mono" style="display:block; font-size:11px; color:var(--fg-faint);">{{ fld.key }}</span>
                            </label>
                            <div>
                                <!-- bool -->
                                <label v-if="fld.type === 'bool'" style="display:inline-flex; align-items:center; gap:8px;">
                                    <input :id="'set_' + fld.key" v-model="form[fld.key]" type="checkbox" :disabled="!can_edit" />
                                </label>
                                <!-- int -->
                                <input v-else-if="fld.type === 'int'" :id="'set_' + fld.key" v-model="form[fld.key]" type="number" class="input" :placeholder="fld.placeholder || ''" :disabled="!can_edit" />
                                <!-- image (logo upload) -->
                                <div v-else-if="fld.type === 'image'" style="display:flex; align-items:center; gap:12px;">
                                    <img v-if="fld.value" :src="fld.value" alt="logo" style="width:48px; height:48px; border-radius:8px; object-fit:cover; border:1px solid var(--line); background:#fff;" />
                                    <div v-else style="width:48px; height:48px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:var(--bg-hover); color:var(--fg-faint);"><Icon name="image" :size="20" /></div>
                                    <label v-if="can_edit" class="btn btn-ghost btn-sm" style="cursor:pointer; margin:0;">
                                        <Icon name="upload" :size="14" /><span>{{ fld.value ? (isRtl ? 'تغيير' : 'Change') : (isRtl ? 'رفع' : 'Upload') }}</span>
                                        <input type="file" accept="image/png,image/jpeg,image/webp" style="display:none" @change="uploadLogo($event)" />
                                    </label>
                                    <button v-if="can_edit && fld.value" type="button" class="btn btn-ghost btn-sm" @click="removeLogo">
                                        <Icon name="trash-2" :size="14" /><span>{{ isRtl ? 'إزالة' : 'Remove' }}</span>
                                    </button>
                                </div>
                                <!-- secret -->
                                <template v-else-if="fld.type === 'secret'">
                                    <input :id="'set_' + fld.key" v-model="form[fld.key]" type="password" class="input" autocomplete="new-password"
                                        :placeholder="fld.is_set ? t.secretSet : t.secretEmpty" :disabled="!can_edit" />
                                </template>
                                <!-- text -->
                                <input v-else :id="'set_' + fld.key" v-model="form[fld.key]" type="text" class="input" :placeholder="fld.placeholder || ''" :disabled="!can_edit" />
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="can_edit" style="display:flex; justify-content:flex-end; margin-top:16px;">
                    <button type="submit" class="btn btn-primary" :disabled="saving"><Icon name="check" :size="14" /><span>{{ saving ? '…' : t.save }}</span></button>
                </div>
            </form>
        </div>
</template>
