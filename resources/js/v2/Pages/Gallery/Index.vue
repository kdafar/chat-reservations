<script setup>
/**
 * Results gallery — the before/after cases published on the public website.
 *
 * A case only goes live when it is BOTH published and has consent recorded;
 * the list makes that explicit rather than showing a single "active" flag,
 * because the two mean very different things legally.
 */
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import { confirm } from '../../Composables/useConfirm.js'

const props = defineProps({ filters: Object, page: Object, services: Array, branches: Array, doctors: Array, counts: Object })
const pageProps = usePage()
const locale = computed(() => pageProps.props.locale ?? 'en')
const isRtl = computed(() => locale.value === 'ar')

const t = computed(() => isRtl.value ? {
    eyebrow: 'الموقع العام', title: 'معرض النتائج', desc: 'حالات قبل وبعد تظهر على الموقع. تحتاج كل حالة إلى موافقة موثّقة قبل النشر.',
    add: 'حالة جديدة', search: 'بحث بالعنوان…',
    all: 'الكل', published: 'منشور', draft: 'مسودة',
    thTitle: 'الحالة', thService: 'العلاج', thDoctor: 'الطبيب', thStatus: 'الحالة على الموقع', thOrder: 'الترتيب', thActions: '',
    empty: 'لا توجد حالات بعد', live: 'ظاهر على الموقع', notLive: 'غير ظاهر', noConsent: 'بانتظار الموافقة', draftBadge: 'مسودة',
    totalT: 'إجمالي الحالات', liveT: 'ظاهرة على الموقع',
    modal: {
        createTitle: 'حالة جديدة', editTitle: 'تعديل الحالة',
        titleEn: 'العنوان (إنجليزي)', titleAr: 'العنوان (عربي)',
        summaryEn: 'الوصف (إنجليزي)', summaryAr: 'الوصف (عربي)',
        protocolEn: 'البروتوكول (إنجليزي)', protocolAr: 'البروتوكول (عربي)',
        protocolHelp: 'مثال: ٦ جلسات · ٢٤ أسبوعًا',
        beforeUrl: 'رابط صورة "قبل"', afterUrl: 'رابط صورة "بعد"',
        service: 'العلاج', branch: 'الفرع', doctor: 'الطبيب', none: '— بدون —',
        consent: 'موافقة المريضة موثّقة', consentHelp: 'إلزامية للنشر. لا تُعرض أي حالة على الموقع بدونها.',
        published: 'منشور', sort: 'الترتيب',
        save: 'حفظ', cancel: 'إلغاء', deleteConfirm: 'حذف هذه الحالة؟',
    },
} : {
    eyebrow: 'Public website', title: 'Results Gallery', desc: 'Before / after cases shown on the website. Each case needs recorded consent before it can be published.',
    add: 'New case', search: 'Search by title…',
    all: 'All', published: 'Published', draft: 'Draft',
    thTitle: 'Case', thService: 'Treatment', thDoctor: 'Doctor', thStatus: 'On the website', thOrder: 'Order', thActions: '',
    empty: 'No cases yet', live: 'Live', notLive: 'Not shown', noConsent: 'Consent missing', draftBadge: 'Draft',
    totalT: 'Total cases', liveT: 'Live on site',
    modal: {
        createTitle: 'New case', editTitle: 'Edit case',
        titleEn: 'Title (English)', titleAr: 'Title (Arabic)',
        summaryEn: 'Summary (English)', summaryAr: 'Summary (Arabic)',
        protocolEn: 'Protocol (English)', protocolAr: 'Protocol (Arabic)',
        protocolHelp: 'e.g. 6 sessions · 24 weeks',
        beforeUrl: '"Before" image URL', afterUrl: '"After" image URL',
        service: 'Treatment', branch: 'Branch', doctor: 'Doctor', none: '— none —',
        consent: 'Patient consent on file', consentHelp: 'Required to publish. No case reaches the website without it.',
        published: 'Published', sort: 'Sort order',
        save: 'Save', cancel: 'Cancel', deleteConfirm: 'Delete this case?',
    },
})

const f = reactive({ q: props.filters.q ?? '', status: props.filters.status ?? 'all' })
function apply() {
    router.get(route('v2.gallery.index'), { q: f.q, status: f.status }, { preserveState: true, preserveScroll: true, replace: true })
}

const modalOpen = ref(false)
const modalMode = ref('create')
const editing = ref(null)
const saving = ref(false)
const errors = ref({})

const blank = () => ({
    title_en: '', title_ar: '', summary_en: '', summary_ar: '', protocol_en: '', protocol_ar: '',
    before_image_url: '', after_image_url: '',
    service_id: '', branch_id: '', doctor_id: '',
    consent_on_file: false, is_published: false, sort_order: 0,
})
const form = reactive(blank())

function openCreate() {
    modalMode.value = 'create'; editing.value = null
    Object.assign(form, blank())
    errors.value = {}; modalOpen.value = true
}

function openEdit(row) {
    modalMode.value = 'edit'; editing.value = row
    Object.assign(form, {
        title_en: row.title?.en ?? '', title_ar: row.title?.ar ?? '',
        summary_en: row.summary?.en ?? '', summary_ar: row.summary?.ar ?? '',
        protocol_en: row.protocol?.en ?? '', protocol_ar: row.protocol?.ar ?? '',
        before_image_url: row.before_image_url ?? '', after_image_url: row.after_image_url ?? '',
        service_id: row.service_id ?? '', branch_id: row.branch_id ?? '', doctor_id: row.doctor_id ?? '',
        consent_on_file: !!row.consent_on_file, is_published: !!row.is_published, sort_order: row.sort_order ?? 0,
    })
    errors.value = {}; modalOpen.value = true
}

function closeModal() { modalOpen.value = false; saving.value = false }

function submit() {
    saving.value = true
    const payload = {
        ...form,
        service_id: form.service_id || null,
        branch_id: form.branch_id || null,
        doctor_id: form.doctor_id || null,
    }
    const url = modalMode.value === 'create'
        ? route('v2.gallery.store')
        : route('v2.gallery.update', editing.value.id)
    const method = modalMode.value === 'create' ? 'post' : 'put'

    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: closeModal,
        onError: (e) => { errors.value = e; saving.value = false },
    })
}

function destroy(row) {
    confirm({
        body: t.value.modal.deleteConfirm,
        tone: 'destructive',
        onConfirm: () => router.delete(route('v2.gallery.destroy', row.id), { preserveScroll: true }),
    })
}

// Doctors are branch-scoped, so narrow the picker once a branch is chosen.
const doctorOptions = computed(() => form.branch_id
    ? props.doctors.filter(d => String(d.branch_id) === String(form.branch_id))
    : props.doctors)
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px 28px; max-width:1180px; margin:0 auto;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div class="eyebrow">{{ t.eyebrow }}</div>
                <h1 style="margin:6px 0 4px; font-size:26px; font-weight:500; letter-spacing:-0.02em;">{{ t.title }}</h1>
                <p style="margin:0; font-size:13.5px; color:var(--fg-muted); max-width:640px;">{{ t.desc }}</p>
            </div>
            <button class="btn btn-primary" @click="openCreate"><Icon name="plus" :size="14" /><span>{{ t.add }}</span></button>
        </div>

        <div style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
            <div class="stat"><span class="stat-label">{{ t.totalT }}</span><span class="stat-val">{{ counts.total }}</span></div>
            <div class="stat"><span class="stat-label">{{ t.liveT }}</span><span class="stat-val">{{ counts.live }}</span></div>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
            <input v-model="f.q" class="input" :placeholder="t.search" style="max-width:280px;" @keyup.enter="apply" />
            <select v-model="f.status" class="input" style="max-width:170px;" @change="apply">
                <option value="all">{{ t.all }}</option>
                <option value="published">{{ t.published }}</option>
                <option value="draft">{{ t.draft }}</option>
            </select>
            <button class="btn btn-ghost" @click="apply"><Icon name="search" :size="14" /></button>
        </div>

        <div class="card" style="overflow:hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ t.thTitle }}</th>
                        <th>{{ t.thService }}</th>
                        <th>{{ t.thDoctor }}</th>
                        <th>{{ t.thStatus }}</th>
                        <th style="text-align:center;">{{ t.thOrder }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!page.data.length"><td colspan="6" style="text-align:center; padding:40px; color:var(--fg-faint);">{{ t.empty }}</td></tr>
                    <tr v-for="c in page.data" :key="c.id">
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="display:flex; gap:3px; flex-shrink:0;">
                                    <img :src="c.before_image_url" alt="" style="width:26px; height:34px; object-fit:cover; border-radius:4px; border:1px solid var(--line);" />
                                    <img :src="c.after_image_url" alt="" style="width:26px; height:34px; object-fit:cover; border-radius:4px; border:1px solid var(--line);" />
                                </div>
                                <div>
                                    <div style="font-weight:500;">{{ c.title_label }}</div>
                                    <div style="font-size:11px; color:var(--fg-faint);">{{ c.branch || '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:12.5px;">{{ c.service || '—' }}</td>
                        <td style="font-size:12.5px;">{{ c.doctor || '—' }}</td>
                        <td>
                            <span v-if="c.live" class="badge st-live">{{ t.live }}</span>
                            <span v-else-if="!c.consent_on_file" class="badge st-warn">{{ t.noConsent }}</span>
                            <span v-else class="badge">{{ t.draftBadge }}</span>
                        </td>
                        <td style="text-align:center;" class="mono">{{ c.sort_order }}</td>
                        <td style="text-align:end; white-space:nowrap;">
                            <button class="btn btn-ghost btn-sm btn-icon" @click="openEdit(c)"><Icon name="pencil" :size="13" /></button>
                            <button class="btn btn-ghost btn-sm btn-icon" @click="destroy(c)"><Icon name="trash-2" :size="13" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="page.links && page.links.length > 3" style="margin-top:14px; display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">
            <Link v-for="(l, i) in page.links" :key="i" :href="l.url || ''" v-html="l.label"
                  class="pager" :class="{ active: l.active, disabled: !l.url }" preserve-scroll />
        </div>
    </div>

    <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-panel" role="dialog" aria-modal="true" style="max-width:720px; display:flex; flex-direction:column; max-height:88vh;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line); flex-shrink:0;">
                <h3 style="margin:0; font-size:15px; font-weight:600;">{{ modalMode === 'create' ? t.modal.createTitle : t.modal.editTitle }}</h3>
                <button class="btn btn-ghost btn-sm btn-icon" @click="closeModal"><Icon name="x" :size="14" /></button>
            </div>

            <form @submit.prevent="submit" style="padding:16px; overflow-y:auto;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">{{ t.modal.titleEn }}</label>
                        <input v-model="form.title_en" class="input" dir="ltr" />
                        <div v-if="errors.title_en" class="err">{{ errors.title_en }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.titleAr }}</label>
                        <input v-model="form.title_ar" class="input" dir="rtl" />
                    </div>

                    <div>
                        <label class="label">{{ t.modal.summaryEn }}</label>
                        <textarea v-model="form.summary_en" class="input" rows="3" dir="ltr"></textarea>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.summaryAr }}</label>
                        <textarea v-model="form.summary_ar" class="input" rows="3" dir="rtl"></textarea>
                    </div>

                    <div>
                        <label class="label">{{ t.modal.protocolEn }}</label>
                        <input v-model="form.protocol_en" class="input" dir="ltr" :placeholder="t.modal.protocolHelp" />
                    </div>
                    <div>
                        <label class="label">{{ t.modal.protocolAr }}</label>
                        <input v-model="form.protocol_ar" class="input" dir="rtl" />
                    </div>

                    <div>
                        <label class="label">{{ t.modal.beforeUrl }}</label>
                        <input v-model="form.before_image_url" class="input" dir="ltr" placeholder="https://…" />
                        <div v-if="errors.before_image_url" class="err">{{ errors.before_image_url }}</div>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.afterUrl }}</label>
                        <input v-model="form.after_image_url" class="input" dir="ltr" placeholder="https://…" />
                        <div v-if="errors.after_image_url" class="err">{{ errors.after_image_url }}</div>
                    </div>

                    <div v-if="form.before_image_url || form.after_image_url" style="grid-column:1 / -1; display:flex; gap:8px;">
                        <img v-if="form.before_image_url" :src="form.before_image_url" alt="" style="width:96px; height:120px; object-fit:cover; border-radius:8px; border:1px solid var(--line);" />
                        <img v-if="form.after_image_url" :src="form.after_image_url" alt="" style="width:96px; height:120px; object-fit:cover; border-radius:8px; border:1px solid var(--line);" />
                    </div>

                    <div>
                        <label class="label">{{ t.modal.service }}</label>
                        <select v-model="form.service_id" class="input">
                            <option value="">{{ t.modal.none }}</option>
                            <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.branch }}</label>
                        <select v-model="form.branch_id" class="input">
                            <option value="">{{ t.modal.none }}</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.doctor }}</label>
                        <select v-model="form.doctor_id" class="input">
                            <option value="">{{ t.modal.none }}</option>
                            <option v-for="d in doctorOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">{{ t.modal.sort }}</label>
                        <input v-model.number="form.sort_order" type="number" step="any" class="input" />
                    </div>

                    <div style="grid-column:1 / -1; border-top:1px solid var(--line); padding-top:12px;">
                        <label style="display:flex; align-items:flex-start; gap:8px; font-size:13px;">
                            <input v-model="form.consent_on_file" type="checkbox" style="margin-top:3px;" />
                            <span>
                                {{ t.modal.consent }}
                                <span style="display:block; font-size:11px; color:var(--fg-faint);">{{ t.modal.consentHelp }}</span>
                            </span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; margin-top:10px;">
                            <input v-model="form.is_published" type="checkbox" />
                            <span>{{ t.modal.published }}</span>
                        </label>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;">
                    <button type="button" class="btn btn-ghost" @click="closeModal">{{ t.modal.cancel }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">{{ t.modal.save }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.eyebrow { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-faint); }
.stat { background:var(--bg-card); border:1px solid var(--line); border-radius:10px; padding:10px 16px; min-width:150px; }
.stat-label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); }
.stat-val { font-size:20px; font-weight:600; font-variant-numeric:tabular-nums; }
.table { width:100%; border-collapse:collapse; font-size:13px; }
.table th { text-align:start; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-faint); font-weight:600; border-bottom:1px solid var(--line); }
.table td { padding:9px 12px; border-bottom:1px solid var(--line); }
.table tbody tr:hover { background:var(--bg-hover); }
.badge { font-size:11px; font-weight:600; padding:2px 9px; border-radius:999px; border:1px solid var(--line); color:var(--fg-muted); }
.badge.st-live { color:var(--success); border-color:var(--success); }
.badge.st-warn { color:var(--destructive); border-color:var(--destructive); }
.err { font-size:11px; color:var(--destructive); margin-top:4px; }
.pager { padding:5px 11px; border:1px solid var(--line); border-radius:7px; font-size:13px; color:var(--fg); }
.pager.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.pager.disabled { color:var(--fg-faint); pointer-events:none; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:80; display:flex; align-items:center; justify-content:center; padding:24px; }
</style>
