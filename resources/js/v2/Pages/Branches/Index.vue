<script setup>
import { computed, reactive, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({
    filters: { type: Object, required: true },
    page: { type: Object, required: true },
    counts: { type: Object, required: true },
})

const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value
    ? {
        title: 'الفروع', eyebrow: 'الإعدادات',
        desc: 'فروع العيادة ومواقعها وبيانات الاتصال وأفق الحجز. للمسؤولين فقط.',
        searchPh: 'ابحث بالاسم، الهاتف، أو رقم الترخيص…', new: 'فرع جديد',
        status: { all: 'الكل', available: 'متاح', unavailable: 'غير متاح' },
        col: { name: 'الفرع', clinic: 'العيادة', city: 'المدينة', phone: 'الهاتف', license: 'الترخيص', bookingDays: 'أيام الحجز', status: 'الحالة' },
        empty: 'لا توجد فروع', emptyDesc: 'أضف أول فرع للعيادة.',
        clear: 'مسح', previous: 'السابق', next: 'التالي', showing: 'عرض', of: 'من',
        stats: { total: 'الكل', available: 'متاح', unavailable: 'غير متاح' },
        modal: {
            createTitle: 'فرع جديد', editTitle: 'تحرير الفرع',
            clinic: 'العيادة (الجهة)', nameEn: 'الاسم (إنجليزي)', nameAr: 'الاسم (عربي)',
            slug: 'الاسم اللطيف (slug)', slugHelp: 'يُولّد تلقائيًا إن تُرك فارغًا.',
            phone: 'الهاتف', email: 'البريد', license: 'رقم الترخيص', address: 'العنوان', city: 'المدينة',
            bookingDays: 'أقصى أيام للحجز المسبق', available: 'متاح للحجز',
            account: 'الحساب المحاسبي (النقد/التشغيل)', accountHelp: 'الحساب الذي تُرحَّل إليه مقبوضات هذا الفرع النقدية. يُترك للنظام إن لم يُحدَّد.', accountNone: 'افتراضي النظام',
            save: 'حفظ', cancel: 'إلغاء',
            deleteConfirm: 'سيتم وضع علامة "غير متاح" على هذا الفرع. متابعة؟',
        },
    }
    : {
        title: 'Branches', eyebrow: 'Settings',
        desc: 'Clinic branches — location, contact details, and booking horizon. Admin-only.',
        searchPh: 'Search by name, phone, or license…', new: 'New branch',
        status: { all: 'All', available: 'Available', unavailable: 'Unavailable' },
        col: { name: 'Branch', clinic: 'Clinic', city: 'City', phone: 'Phone', license: 'License', bookingDays: 'Booking days', status: 'Status' },
        empty: 'No branches', emptyDesc: 'Add your clinic\'s first branch.',
        clear: 'Clear', previous: 'Previous', next: 'Next', showing: 'Showing', of: 'of',
        stats: { total: 'Total', available: 'Available', unavailable: 'Unavailable' },
        modal: {
            createTitle: 'New branch', editTitle: 'Edit branch',
            clinic: 'Clinic (owner)', nameEn: 'Name (English)', nameAr: 'Name (Arabic)',
            slug: 'Slug', slugHelp: 'Auto-generated if left empty.',
            phone: 'Phone', email: 'Email', license: 'License number', address: 'Address', city: 'City',
            bookingDays: 'Max advance booking days', available: 'Available for booking',
            account: 'Accounting account (cash / operating)', accountHelp: "The account this branch's cash receipts post to. Leave as system default if unset.", accountNone: 'System default',
            save: 'Save', cancel: 'Cancel',
            deleteConfirm: 'Mark this branch unavailable?',
        },
    })

const f = reactive({ q: props.filters.q || '', status: props.filters.status || 'all' })
let qTimer = null
watch(() => f.q, () => { clearTimeout(qTimer); qTimer = setTimeout(apply, 250) })
watch(() => f.status, () => apply())
function apply() {
    router.get(route('v2.branches.index'), {
        q: f.q || undefined,
        status: f.status === 'all' ? undefined : f.status,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() { f.q = ''; f.status = 'all'; apply() }

function openEdit(row) { router.visit(route('v2.branches.edit', { branch: row.id })) }
function deactivate(row) {
    confirm({ body: t.value.modal.deleteConfirm, tone: 'destructive', onConfirm: () => {
        router.delete(route('v2.branches.destroy', { branch: row.id }), { preserveScroll: true })
    } })
}
</script>

<template>
    <Head :title="t.title" />

    <div style="padding:24px; max-width:1280px; margin:0 auto;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:4px 0 0; font-size:22px; font-weight:700; color:var(--fg);">{{ t.title }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:var(--fg-subtle); max-width:640px;">{{ t.desc }}</p>
            </div>
            <Link class="btn btn-primary" :href="route('v2.branches.create')"><Icon name="plus" :size="14" /><span>{{ t.new }}</span></Link>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat-chip"><span class="stat-chip-num">{{ counts.total }}</span><span class="stat-chip-lbl">{{ t.stats.total }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--ok);">{{ counts.available }}</span><span class="stat-chip-lbl">{{ t.stats.available }}</span></div>
            <div class="stat-chip"><span class="stat-chip-num" style="color:var(--fg-faint);">{{ counts.unavailable }}</span><span class="stat-chip-lbl">{{ t.stats.unavailable }}</span></div>
        </div>

        <div class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative; flex:1; min-width:240px;">
                <Icon name="search" :size="14" style="position:absolute; inset-inline-start:10px; top:50%; transform:translateY(-50%); color:var(--fg-faint);" />
                <input v-model="f.q" type="search" :placeholder="t.searchPh" class="input" style="padding-inline-start:32px;" />
            </div>
            <div class="seg seg-sm">
                <button :class="f.status === 'all' ? 'is-active' : ''" @click="f.status = 'all'">{{ t.status.all }}</button>
                <button :class="f.status === 'available' ? 'is-active' : ''" @click="f.status = 'available'">{{ t.status.available }}</button>
                <button :class="f.status === 'unavailable' ? 'is-active' : ''" @click="f.status = 'unavailable'">{{ t.status.unavailable }}</button>
            </div>
            <button v-if="f.q || f.status !== 'all'" class="btn btn-ghost btn-sm" @click="clearFilters">{{ t.clear }}</button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.col.name }}</th>
                        <th>{{ t.col.clinic }}</th>
                        <th>{{ t.col.city }}</th>
                        <th>{{ t.col.phone }}</th>
                        <th>{{ t.col.license }}</th>
                        <th style="text-align:end;">{{ t.col.bookingDays }}</th>
                        <th>{{ t.col.status }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="page.data.length === 0">
                        <td colspan="8" style="text-align:center; padding:48px 12px; color:var(--fg-faint);">
                            <Icon name="map-pin" :size="32" style="margin-bottom:8px; opacity:0.4;" />
                            <div style="font-weight:600;">{{ t.empty }}</div>
                            <div style="font-size:12px; margin-top:4px;">{{ t.emptyDesc }}</div>
                        </td>
                    </tr>
                    <tr v-for="row in page.data" :key="row.id" @click="openEdit(row)" style="cursor:pointer;">
                        <td style="font-weight:600;">{{ row.name }}</td>
                        <td>{{ row.partner_name || '—' }}</td>
                        <td>{{ row.city_name || '—' }}</td>
                        <td class="mono" style="font-size:12px;">{{ row.phone || '—' }}</td>
                        <td class="mono" style="font-size:12px;">{{ row.license_number || '—' }}</td>
                        <td class="mono" style="text-align:end;">{{ row.max_booking_days }}</td>
                        <td>
                            <span :class="row.is_available ? 'badge-ok' : 'badge-muted'">
                                {{ row.is_available ? t.status.available : t.status.unavailable }}
                            </span>
                        </td>
                        <td @click.stop>
                            <button v-if="row.is_available" class="btn btn-ghost btn-sm btn-icon" :title="t.modal.deleteConfirm" @click="deactivate(row)">
                                <Icon name="eye-off" :size="14" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.last_page > 1" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding:0 4px; font-size:12px; color:var(--fg-subtle);">
            <span>{{ t.showing }} {{ page.from }}–{{ page.to }} {{ t.of }} {{ page.total }}</span>
            <div style="display:flex; gap:4px;">
                <a v-for="link in page.links" :key="link.label" :href="link.url || undefined" v-html="link.label"
                   :class="['btn', 'btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'is-disabled' : '']"
                   style="min-width:32px;" />
            </div>
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
.role-check { display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:6px 10px; border:1px solid var(--line); border-radius:6px; cursor:pointer; }
.role-check:hover { background:var(--bg-hover); }
.label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); margin-bottom:4px; }
.err { font-size:11px; color:var(--err, #dc2626); margin-top:4px; font-weight:500; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal-panel { width:100%; max-width:680px; background:var(--bg); border:1px solid var(--line); border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; }
</style>
