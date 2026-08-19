<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import ImportButton from '../../Components/ImportButton.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import { formatMoney as fmt } from '../../lib/money.js'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    branches: { type: Array, required: true },
    clinicItems: { type: Array, required: true },
    counts: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    title: 'باقات العيادة', eyebrow: 'الإعداد',
    desc: 'حزمة من الأصناف بسعر واحد يضيفها الطبيب للزيارة بنقرة. الفرع فارغ = متاحة لكل الفروع.',
    searchPh: 'ابحث باسم الباقة…', new: 'باقة جديدة',
    status: { all: 'الكل', active: 'فعّالة', inactive: 'غير فعّالة' }, allBranches: 'كل الفروع', global: 'كل الفروع',
    col: { name: 'الاسم', branch: 'الفرع', price: 'السعر الأساسي', offer: 'سعر العرض', items: 'الأصناف', status: 'الحالة' },
    empty: 'لا توجد باقات', emptyDesc: 'أنشئ أول باقة.', clear: 'مسح', showing: 'عرض', of: 'من',
    stats: { total: 'الكل', active: 'فعّالة', offers: 'عروض منشورة' },
    web: 'على الموقع', expired: 'انتهى العرض', scheduled: 'لم يبدأ بعد',
    modal: {
        createTitle: 'باقة جديدة', editTitle: 'تحرير الباقة',
        branch: 'الفرع', branchHelp: 'اتركه فارغًا لإتاحتها في كل الفروع.', global: '— كل الفروع —',
        nameEn: 'الاسم (إنجليزي)', nameAr: 'الاسم (عربي)', price: 'السعر الأساسي', active: 'فعّالة',
        priceHelp: 'السعر المعتاد للباقة قبل الخصم.',
        discount: 'سعر العرض', discountHelp: 'اتركه فارغًا إن لم يكن هناك عرض. يجب أن يكون أقل من السعر الأساسي.',
        saves: 'يوفّر المريض', pricing: 'التسعير', offerWindow: 'مدة العرض',
        startsAt: 'يبدأ في', endsAt: 'ينتهي في', windowHelp: 'اتركه فارغًا ليظل العرض ساريًا دائمًا.',
        website: 'الموقع الإلكتروني', isPublic: 'اعرض هذه الباقة كعرض على الموقع',
        publicHelp: 'ستظهر في صفحة العروض مع السعر قبل وبعد الخصم وقيمة التوفير.',
        descEn: 'الوصف (إنجليزي)', descAr: 'الوصف (عربي)', descPh: 'وصف قصير يظهر للمريض…',
        imageUrl: 'رابط الصورة', imageHelp: 'رابط صورة تظهر على بطاقة العرض (اختياري).',
        sortOrder: 'الترتيب', sortHelp: 'الأصغر يظهر أولًا.',
        items: 'أصناف الباقة', item: 'الصنف', qty: 'الكمية (أساس)', consumable: 'يُخصم من المخزون',
        addItem: 'إضافة صنف', selectItem: '— اختر صنفًا —', noItems: 'لا أصناف بعد.',
        save: 'حفظ', cancel: 'إلغاء', delete: 'حذف', deleteConfirm: 'حذف هذه الباقة نهائيًا؟',
    },
} : {
    title: 'Clinic Packages', eyebrow: 'Setup',
    desc: 'A bundle of items at one price a doctor can add to a visit in one tap. Empty branch = available everywhere.',
    searchPh: 'Search by package name…', new: 'New package',
    status: { all: 'All', active: 'Active', inactive: 'Inactive' }, allBranches: 'All branches', global: 'All branches',
    col: { name: 'Name', branch: 'Branch', price: 'Main price', offer: 'Offer price', items: 'Items', status: 'Status' },
    empty: 'No packages', emptyDesc: 'Create your first package.', clear: 'Clear', showing: 'Showing', of: 'of',
    stats: { total: 'Total', active: 'Active', offers: 'Live offers' },
    web: 'On website', expired: 'Offer ended', scheduled: 'Not started',
    modal: {
        createTitle: 'New package', editTitle: 'Edit package',
        branch: 'Branch', branchHelp: 'Leave empty to offer it at every branch.', global: '— All branches —',
        nameEn: 'Name (English)', nameAr: 'Name (Arabic)', price: 'Main price', active: 'Active',
        priceHelp: 'What the package normally costs, before any discount.',
        discount: 'Discount price', discountHelp: 'Leave empty if there is no offer. Must be lower than the main price.',
        saves: 'Patient saves', pricing: 'Pricing', offerWindow: 'Offer period',
        startsAt: 'Starts', endsAt: 'Ends', windowHelp: 'Leave empty to run the offer indefinitely.',
        website: 'Public website', isPublic: 'Show this package as an offer on the website',
        publicHelp: 'It appears on the Offers page with the before/after price and the amount saved.',
        descEn: 'Description (English)', descAr: 'Description (Arabic)', descPh: 'Short description patients will see…',
        imageUrl: 'Image URL', imageHelp: 'Photo shown on the offer card (optional).',
        sortOrder: 'Sort order', sortHelp: 'Lower numbers appear first.',
        items: 'Package items', item: 'Item', qty: 'Qty (base)', consumable: 'Deduct from stock',
        addItem: 'Add item', selectItem: '— Select an item —', noItems: 'No items yet.',
        save: 'Save', cancel: 'Cancel', delete: 'Delete', deleteConfirm: 'Permanently delete this package?',
    },
})

const f = reactive({ q: props.filters.q || '', branch_id: props.filters.branch_id || '', status: props.filters.status || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => [f.branch_id, f.status], () => apply())
function apply() {
    router.get(route('v2.clinic-packages.index'), {
        q: f.q || undefined, branch_id: f.branch_id || undefined, status: f.status === 'all' ? undefined : f.status,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.branch_id = ''; f.status = 'all'; apply() }

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const blank = () => ({
    branch_id: '', name_en: '', name_ar: '',
    description_en: '', description_ar: '', image_url: '',
    default_price: 0, discount_price: null,
    offer_starts_at: '', offer_ends_at: '',
    is_active: true, is_public: false, sort_order: 0,
    items: [],
})
const form = reactive(blank())
const errors = ref({})
const saving = ref(false)

// Live "patient saves" readout under the two price inputs — the same figure the
// patient sees on the website, so it can't be published wrong by accident.
const preview = computed(() => {
    const main = Number(form.default_price) || 0
    const off = form.discount_price === null || form.discount_price === '' ? null : Number(form.discount_price)
    if (off === null || !(off > 0) || !(off < main)) return null
    const amount = main - off
    return { amount, percent: Math.round((amount / main) * 100) }
})

function openCreate() {
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blank())
    if (props.branches.length === 1) form.branch_id = props.branches[0].id
    errors.value = {}; modalOpen.value = true
}
function openEdit(row) {
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        branch_id: row.branch_id || '', name_en: row.name_en || '', name_ar: row.name_ar || '',
        description_en: row.description_en || '', description_ar: row.description_ar || '',
        image_url: row.image_url || '',
        default_price: row.default_price ?? 0,
        discount_price: row.discount_price ?? null,
        offer_starts_at: row.offer_starts_at || '', offer_ends_at: row.offer_ends_at || '',
        is_active: !!row.is_active, is_public: !!row.is_public, sort_order: row.sort_order ?? 0,
        items: (row.items || []).map(it => ({ clinic_item_id: it.clinic_item_id, qty_base: it.qty_base, is_consumable: it.is_consumable })),
    })
    errors.value = {}; modalOpen.value = true
}
function closeModal() { modalOpen.value = false; saving.value = false }

function addItem() { form.items.push({ clinic_item_id: '', qty_base: 1, is_consumable: true }) }
function removeItem(i) { form.items.splice(i, 1) }

function submit() {
    saving.value = true; errors.value = {}
    const url = modalMode.value === 'create'
        ? route('v2.clinic-packages.store')
        : route('v2.clinic-packages.update', { clinicPackage: editing.value.id })
    const method = modalMode.value === 'create' ? 'post' : 'put'
    const payload = {
        ...form,
        branch_id: form.branch_id || null,
        // Empty inputs must reach the server as null, not '' — the nullable
        // numeric/date rules reject a blank string.
        discount_price: form.discount_price === '' || form.discount_price === null ? null : Number(form.discount_price),
        offer_starts_at: form.offer_starts_at || null,
        offer_ends_at: form.offer_ends_at || null,
        image_url: form.image_url || null,
        sort_order: Number(form.sort_order) || 0,
    }
    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; saving.value = false },
        onFinish: () => { saving.value = false },
    })
}
function destroy(row) {
    confirm({ body: t.value.modal.deleteConfirm, tone: 'destructive', onConfirm: () => {
        router.delete(route('v2.clinic-packages.destroy', { clinicPackage: row.id }), { preserveScroll: true })
    } })
}
</script>

<template>
    <Head :title="t.title" />

    <div style="padding:24px; max-width:1200px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <a class="btn btn-sm btn-outline" :href="route('v2.clinic-packages.export', { ...f })"><Icon name="download" :size="13" /><span>{{ isRtl ? 'تصدير Excel' : 'Export Excel' }}</span></a>
                <ImportButton v-if="can_manage" type="clinic-packages" />
                <button v-if="can_manage" class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></button>
            </div>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.active }}</span><span class="stat-chip-lbl">{{ t.stats.active }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--accent, #b45309);">{{ counts.offers }}</span><span class="stat-chip-lbl">{{ t.stats.offers }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:220px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <SearchableSelect v-model="f.branch_id" :items="branches" :null-label="t.allBranches" :width="200" />
            <div class="seg seg-sm">
                <button :class="f.status === 'all' ? 'is-active' : ''" @click="f.status = 'all'">{{ t.status.all }}</button>
                <button :class="f.status === 'active' ? 'is-active' : ''" @click="f.status = 'active'">{{ t.status.active }}</button>
                <button :class="f.status === 'inactive' ? 'is-active' : ''" @click="f.status = 'inactive'">{{ t.status.inactive }}</button>
            </div>
            <button v-if="f.q || f.branch_id || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.name }}</th>
                        <th>{{ t.col.branch }}</th>
                        <th style="text-align:end;">{{ t.col.price }}</th>
                        <th style="text-align:end;">{{ t.col.offer }}</th>
                        <th style="text-align:end;">{{ t.col.items }}</th>
                        <th>{{ t.col.status }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="page.data.length === 0">
                        <td colspan="7" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                            <Icon name="gift" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                            <div style="font-weight:600;">{{ t.empty }}</div>
                            <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                        </td>
                    </tr>
                    <tr v-for="row in page.data" :key="row.id" @click="can_manage && openEdit(row)" :style="can_manage ? 'cursor:pointer;' : ''">
                        <td style="font-weight:600;">
                            {{ row.name }}
                            <span v-if="row.is_public" class="badge-web" :title="t.modal.publicHelp">{{ t.web }}</span>
                        </td>
                        <td>{{ row.branch_name || t.global }}</td>
                        <td class="mono" style="text-align:end;" :style="row.has_discount ? 'text-decoration:line-through; color:var(--fg-faint);' : ''">{{ fmt(row.default_price) }}</td>
                        <td class="mono" style="text-align:end;">
                            <template v-if="row.has_discount">
                                <span style="font-weight:700;">{{ fmt(row.effective_price) }}</span>
                                <span class="badge-save">−{{ row.savings_percent }}%</span>
                            </template>
                            <span v-else-if="row.discount_price !== null" class="badge-muted">
                                {{ row.offer_window_open ? '—' : (row.offer_ends_at && !row.offer_window_open ? t.expired : t.scheduled) }}
                            </span>
                            <span v-else style="color:var(--fg-faint);">—</span>
                        </td>
                        <td class="mono" style="text-align:end;">{{ row.items_count }}</td>
                        <td><span :class="row.is_active ? 'badge-ok' : 'badge-muted'">{{ row.is_active ? t.status.active : t.status.inactive }}</span></td>
                        <td @click.stop>
                            <button v-if="can_manage" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.delete" @click.stop="destroy(row)"><Icon name="trash-2" :size="14" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;">
                <a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                   :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']" style="min-width:32px;" />
            </div>
        </div>
    </div>

    <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:720px; display:flex; flex-direction:column; max-height:88vh;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line); flex-shrink:0;">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
            </div>
            <form @submit.prevent="submit" style="padding:16px; overflow-y:auto;">
                <div class="rgrid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">{{ t.modal.nameEn }} <span class="req">*</span></label>
                        <input v-model="form.name_en" type="text" class="input" required maxlength="191" />
                        <div v-if="errors.name_en" class="err">{{ errors.name_en }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.nameAr }} <span class="req">*</span></label>
                        <input v-model="form.name_ar" type="text" class="input" required maxlength="191" dir="rtl" />
                        <div v-if="errors.name_ar" class="err">{{ errors.name_ar }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.branch }}</label>
                        <SearchableSelect v-model="form.branch_id" :items="branches" :null-label="t.modal.global" />
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.modal.branchHelp }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.sortOrder }}</label>
                        <input v-model.number="form.sort_order" type="number" step="1" min="0" class="input" />
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.modal.sortHelp }}</div>
                    </div>
                </div>

                <div class="section-head">{{ t.modal.pricing }}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">{{ t.modal.price }} <span class="req">*</span></label>
                        <input v-model.number="form.default_price" type="number" step="any" min="0" class="input" required />
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.modal.priceHelp }}</div>
                        <div v-if="errors.default_price" class="err">{{ errors.default_price }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.discount }}</label>
                        <input v-model="form.discount_price" type="number" step="any" min="0" class="input" placeholder="—" />
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.modal.discountHelp }}</div>
                        <div v-if="errors.discount_price" class="err">{{ errors.discount_price }}</div>
                    </div>
                </div>

                <div v-if="preview" class="savings-preview">
                    <Icon name="tag" :size="14" />
                    <span>{{ t.modal.saves }}</span>
                    <strong>{{ fmt(preview.amount) }}</strong>
                    <span class="badge-save">−{{ preview.percent }}%</span>
                </div>

                <div class="section-head">{{ t.modal.offerWindow }}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">{{ t.modal.startsAt }}</label>
                        <input v-model="form.offer_starts_at" type="date" class="input" />
                        <div v-if="errors.offer_starts_at" class="err">{{ errors.offer_starts_at }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.endsAt }}</label>
                        <input v-model="form.offer_ends_at" type="date" class="input" />
                        <div v-if="errors.offer_ends_at" class="err">{{ errors.offer_ends_at }}</div>
                    </div>
                </div>
                <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.modal.windowHelp }}</div>

                <div class="section-head">{{ t.modal.website }}</div>
                <label class="role-check" style="width:fit-content;"><input type="checkbox" v-model="form.is_public" /><span>{{ t.modal.isPublic }}</span></label>
                <div style="font-size:11px; color:var(--fg-faint); margin-top:6px;">{{ t.modal.publicHelp }}</div>

                <div v-if="form.is_public" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                    <div>
                        <label class="label">{{ t.modal.descEn }}</label>
                        <textarea v-model="form.description_en" class="input" rows="3" maxlength="1000" :placeholder="t.modal.descPh"></textarea>
                        <div v-if="errors.description_en" class="err">{{ errors.description_en }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.descAr }}</label>
                        <textarea v-model="form.description_ar" class="input" rows="3" maxlength="1000" dir="rtl" :placeholder="t.modal.descPh"></textarea>
                        <div v-if="errors.description_ar" class="err">{{ errors.description_ar }}</div>
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="label">{{ t.modal.imageUrl }}</label>
                        <input v-model="form.image_url" type="url" class="input" placeholder="https://…" dir="ltr" />
                        <div style="font-size:11px; color:var(--fg-faint); margin-top:4px;">{{ t.modal.imageHelp }}</div>
                        <div v-if="errors.image_url" class="err">{{ errors.image_url }}</div>
                    </div>
                </div>

                <label class="role-check" style="width:fit-content; margin-top:16px;"><input type="checkbox" v-model="form.is_active" /><span>{{ t.modal.active }}</span></label>

                <div style="display:flex; justify-content:space-between; align-items:center; margin:18px 0 8px;">
                    <label class="label" style="margin:0;">{{ t.modal.items }}</label>
                    <button type="button" class="btn btn-ghost btn-sm" @click="addItem"><Icon name="plus" :size="13" /><span>{{ t.modal.addItem }}</span></button>
                </div>

                <div v-if="!form.items.length" style="color:var(--fg-faint); font-size:12px; font-style:italic; padding:8px 0;">{{ t.modal.noItems }}</div>
                <div v-for="(it, i) in form.items" :key="i" class="item-row">
                    <SearchableSelect v-model="it.clinic_item_id" :items="clinicItems" :nullable="false" :placeholder="t.modal.selectItem" :width="'100%'" style="flex:1; min-width:0;" />
                    <input v-model.number="it.qty_base" type="number" step="any" min="0.0001" class="input" style="width:100px;" :placeholder="t.modal.qty" required />
                    <label class="role-check" :title="t.modal.consumable" style="padding:6px 8px;"><input type="checkbox" v-model="it.is_consumable" /><Icon name="package" :size="14" /></label>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeItem(i)"><Icon name="trash-2" :size="14" /></button>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px; padding-top:12px; border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? '…' : t.modal.save }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.stat-chip { display:inline-flex; flex-direction:column; align-items:flex-start; padding:8px 12px; border-radius:8px; background:var(--bg-elev, var(--bg-hover)); border:1px solid var(--line); min-width:80px; }
.stat-chip-num { font-size:18px; font-weight:700; color:var(--fg); line-height:1; }
.stat-chip-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-top:4px; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--card, var(--bg)); z-index:1; }
.table td { padding:10px 12px; border-bottom:1px solid var(--line); }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:var(--bg-hover); }
.badge-ok { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--ok); color:var(--ok); border-radius:999px; }
.badge-muted { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border:1px solid var(--fg-faint); color:var(--fg-faint); border-radius:999px; }
.badge-save { display:inline-block; margin-inline-start:6px; padding:2px 7px; font-size:10px; font-weight:700; border-radius:999px; background:#059669; color:#fff; letter-spacing:0.02em; }
.badge-web { display:inline-block; margin-inline-start:6px; padding:1px 7px; font-size:10px; font-weight:600; border-radius:999px; background:var(--bg-hover); border:1px solid var(--line); color:var(--fg-subtle); }
.section-head { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); margin:20px 0 10px; padding-top:12px; border-top:1px solid var(--line); }
.savings-preview { display:flex; align-items:center; gap:8px; margin-top:10px; padding:8px 12px; border-radius:8px; background:rgba(5,150,105,0.08); border:1px solid rgba(5,150,105,0.25); color:#047857; font-size:13px; font-weight:600; }
textarea.input { resize:vertical; font-family:inherit; line-height:1.5; }
.role-check { display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:6px 10px; border:1px solid var(--line); border-radius:6px; cursor:pointer; }
.role-check:hover { background:var(--bg-hover); }
.item-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
