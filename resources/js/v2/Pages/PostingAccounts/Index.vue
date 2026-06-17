<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'

const props = defineProps({
  rows: Array,
  groups: Object,
  accounts: Array,
  can_edit: Boolean,
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
  title: 'حسابات الترحيل التلقائي', eyebrow: 'المحاسبة',
  desc: 'حدّد الحساب الذي يستخدمه النظام تلقائيًا لكل عملية محاسبية. اترك «الافتراضي» لاستخدام إعداد إيفا الجاهز.',
  default: 'افتراضي', custom: 'مخصّص', useDefault: 'إرجاع للافتراضي',
  defaultAcc: 'الافتراضي', save: 'حفظ التغييرات', saving: 'جارٍ الحفظ…',
  readonly: 'عرض فقط — لا تملك صلاحية التعديل.', pick: 'اختر حسابًا…',
  note: 'يُفضَّل تغيير الربط في بداية الفترة المحاسبية. عند التغيير في منتصف الشهر يُقسَّم رصيد الحساب بين القديم والجديد — سجّل قيد إعادة تصنيف لنقله. لا يؤثر التغيير على القيود السابقة، والتقارير تحتسب الحسابين معًا.',
} : {
  title: 'Auto-Posting Accounts', eyebrow: 'Accounting',
  desc: 'Choose the account the system posts to automatically for each event. Leave “Default” to use the built-in EVA setup.',
  default: 'Default', custom: 'Custom', useDefault: 'Reset to default',
  defaultAcc: 'Default', save: 'Save changes', saving: 'Saving…',
  readonly: 'Read-only — you do not have edit permission.', pick: 'Choose an account…',
  note: 'Best changed at the start of an accounting period. If you switch mid-month, that account’s balance splits between the old and new account — post a reclass entry to move it. Past entries are never changed, and reports count both accounts.',
})

// role => selected account_id (null = use default)
const sel = reactive({})
props.rows.forEach(r => { sel[r.role] = r.account_id ?? null })

const saving = ref(false)
const dirty = computed(() => props.rows.some(r => (sel[r.role] ?? null) !== (r.account_id ?? null)))

const grouped = computed(() => {
  const out = []
  for (const [gid, gl] of Object.entries(props.groups)) {
    const items = props.rows.filter(r => r.group === gid)
    if (items.length) out.push({ id: gid, label: gl[locale.value] ?? gl.en, items })
  }
  return out
})

const lbl = (obj) => obj?.[locale.value] ?? obj?.en ?? ''

function resetRow(role) { if (props.can_edit) sel[role] = null }

function save() {
  if (!props.can_edit || saving.value || !dirty.value) return
  saving.value = true
  const map = props.rows.map(r => ({ role: r.role, account_id: sel[r.role] ?? null }))
  router.put(route('v2.accounting.posting.update'), { map }, {
    preserveScroll: true,
    onFinish: () => { saving.value = false },
  })
}
</script>

<template>
  <Head :title="t.title" />
  <div style="padding:24px; max-width:960px; margin:0 auto;" :dir="isRtl ? 'rtl' : 'ltr'">
    <!-- Header -->
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
      <div>
        <div class="eyebrow">{{ t.eyebrow }}</div>
        <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
        <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
      </div>
      <button
        v-if="can_edit"
        class="btn btn-primary"
        :class="{ 'is-disabled': !dirty || saving }"
        :disabled="!dirty || saving"
        @click="save"
      >
        <Icon name="check" :size="14" /><span>{{ saving ? t.saving : t.save }}</span>
      </button>
    </div>

    <!-- Guidance -->
    <div class="card" style="display:flex; gap:10px; padding:12px 14px; margin-bottom:16px; font-size:13px; color:var(--fg-subtle); background:var(--info-soft); border-color:var(--info);">
      <Icon name="info" :size="16" style="color:var(--info); flex-shrink:0; margin-top:1px;" />
      <span>{{ t.note }}</span>
    </div>

    <p v-if="!can_edit" class="card" style="padding:10px 14px; margin-bottom:16px; font-size:13px; color:var(--warning); background:var(--warning-soft); border-color:var(--warning);">
      {{ t.readonly }}
    </p>

    <!-- Groups -->
    <div v-for="g in grouped" :key="g.id" style="margin-bottom:22px;">
      <div class="eyebrow" style="margin-bottom:8px;">{{ g.label }}</div>
      <div class="card" style="overflow:hidden;">
        <div
          v-for="(r, i) in g.items"
          :key="r.role"
          style="display:flex; align-items:center; gap:16px; padding:14px 16px; flex-wrap:wrap;"
          :style="i > 0 ? 'border-top:1px solid var(--line);' : ''"
        >
          <!-- Label + help -->
          <div style="flex:1; min-width:240px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <span style="font-weight:600; color:var(--fg);">{{ lbl(r.label) }}</span>
              <span v-if="(sel[r.role] ?? null)" class="badge badge-info">{{ t.custom }}</span>
              <span v-else class="badge">{{ t.default }}</span>
            </div>
            <div style="font-size:13px; color:var(--fg-subtle); margin-top:3px;">{{ lbl(r.help) }}</div>
            <div style="font-size:12px; color:var(--fg-faint); margin-top:3px;">
              {{ t.defaultAcc }}: <span class="mono">{{ r.default_label }}</span>
            </div>
          </div>

          <!-- Picker -->
          <div style="width:320px; max-width:100%;">
            <SearchableSelect
              :model-value="sel[r.role]"
              @update:model-value="v => can_edit && (sel[r.role] = v)"
              :items="accounts"
              :placeholder="r.default_label + '  (' + t.default + ')'"
              :null-label="t.default"
              :search-placeholder="t.pick"
              :width="320"
            />
            <button
              v-if="can_edit && (sel[r.role] ?? null)"
              type="button"
              class="btn btn-ghost btn-sm"
              style="margin-top:6px;"
              @click="resetRow(r.role)"
            >
              <Icon name="rotate-ccw" :size="12" /><span>{{ t.useDefault }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
