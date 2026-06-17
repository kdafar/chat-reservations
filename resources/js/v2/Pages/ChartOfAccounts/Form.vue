<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
    mode: { type: String, default: 'create' },
    account: { type: Object, default: null },
    types: { type: Array, default: () => [] },
    parents: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')
const isEdit = computed(() => props.mode === 'edit')
const isSystem = computed(() => isEdit.value && !!props.account?.is_system)

const t = computed(() => isRtl.value ? {
    eyebrow: 'المحاسبة', back: 'دليل الحسابات',
    createTitle: 'حساب جديد', editTitle: 'تحرير الحساب',
    desc: 'أضف حساباً إلى شجرة الحسابات المالية.',
    save: 'حفظ', cancel: 'إلغاء',
    fields: { code: 'الكود', name: 'الاسم', type: 'النوع', parent: 'الحساب الأب', branch: 'الفرع', currency: 'العملة', is_active: 'فعّال', description: 'الوصف', none: '— بدون —', sysNote: 'حساب نظام — الكود والنوع مقفلان.' },
} : {
    eyebrow: 'Accounting', back: 'Chart of Accounts',
    createTitle: 'New account', editTitle: 'Edit account',
    desc: 'Add an account to the financial account tree.',
    save: 'Save', cancel: 'Cancel',
    fields: { code: 'Code', name: 'Name', type: 'Type', parent: 'Parent account', branch: 'Branch', currency: 'Currency', is_active: 'Active', description: 'Description', none: '— None —', sysNote: 'System account — code & type are locked.' },
})

const typeLabel = (ty) => (ty || '').replace(/_/g, ' ')
const typeItems = computed(() => props.types.map((ty) => ({ value: ty, label: typeLabel(ty) })))
const parentItems = computed(() => props.parents.map((p) => ({ value: p.id, label: p.label })))

const form = reactive(props.account ? {
    code: props.account.code,
    name: props.account.name,
    type: props.account.type,
    parent_id: props.account.parent_id ?? null,
    branch_id: props.account.branch_id ?? null,
    currency: props.account.currency || 'KWD',
    is_active: !!props.account.is_active,
    description: props.account.description || '',
} : {
    code: '', name: '', type: 'asset', parent_id: null, branch_id: null, currency: 'KWD', is_active: true, description: '',
})

const errors = ref({})
const saving = ref(false)
const indexUrl = route('v2.accounting.accounts.index')

function submit() {
    saving.value = true; errors.value = {}
    const url = isEdit.value
        ? route('v2.accounting.accounts.update', { account: props.account.id })
        : route('v2.accounting.accounts.store')
    const method = isEdit.value ? 'put' : 'post'
    router[method](url, { ...form }, {
        // Server redirects to the index on success.
        onError: (e) => { errors.value = e; saving.value = false },
    })
}
</script>

<template>
    <Head :title="isEdit ? t.editTitle : t.createTitle" />
    <div style="padding:24px; max-width:880px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <Link :href="indexUrl" class="btn btn-ghost btn-sm" style="margin-bottom:8px;">
                <Icon name="arrow-left" :size="14" class="flip-rtl" /><span>{{ t.back }}</span>
            </Link>
            <div class="eyebrow">{{ t.eyebrow }}</div>
            <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ isEdit ? t.editTitle : t.createTitle }}</h1>
            <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle);">{{ t.desc }}</p>
        </div>

        <form @submit.prevent="submit" class="card" style="padding:20px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div v-if="isSystem" style="grid-column:span 2; font-size:12px; color:var(--warning, #d97706);"><Icon name="lock" :size="12" /> {{ t.fields.sysNote }}</div>
                <div>
                    <label class="label">{{ t.fields.code }} <span class="req">*</span></label>
                    <input v-model="form.code" class="input mono" required maxlength="16" :disabled="isSystem" />
                    <div v-if="errors.code" class="err">{{ errors.code }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.type }}</label>
                    <SearchableSelect v-model="form.type" :items="typeItems" :nullable="false" :disabled="isSystem" />
                    <div v-if="errors.type" class="err">{{ errors.type }}</div>
                </div>
                <div style="grid-column:span 2;">
                    <label class="label">{{ t.fields.name }} <span class="req">*</span></label>
                    <input v-model="form.name" class="input" required maxlength="191" :disabled="isSystem" />
                    <div v-if="errors.name" class="err">{{ errors.name }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.parent }}</label>
                    <SearchableSelect v-model="form.parent_id" :items="parentItems" :null-label="t.fields.none" :disabled="isSystem" />
                    <div v-if="errors.parent_id" class="err">{{ errors.parent_id }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.branch }}</label>
                    <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.fields.none" :disabled="isSystem" />
                    <div v-if="errors.branch_id" class="err">{{ errors.branch_id }}</div>
                </div>
                <div>
                    <label class="label">{{ t.fields.currency }} <span class="req">*</span></label>
                    <input v-model="form.currency" class="input mono" required maxlength="3" :disabled="isSystem" />
                    <div v-if="errors.currency" class="err">{{ errors.currency }}</div>
                </div>
                <div style="display:flex; align-items:flex-end; gap:8px;">
                    <input id="acc_active" v-model="form.is_active" type="checkbox" />
                    <label for="acc_active" style="font-size:13px;">{{ t.fields.is_active }}</label>
                </div>
                <div style="grid-column:span 2;">
                    <label class="label">{{ t.fields.description }}</label>
                    <textarea v-model="form.description" rows="2" class="input" maxlength="1000"></textarea>
                    <div v-if="errors.description" class="err">{{ errors.description }}</div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid var(--line);">
                <Link :href="indexUrl" class="btn btn-ghost">{{ t.cancel }}</Link>
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.save }}</button>
            </div>
        </form>
    </div>
</template>
