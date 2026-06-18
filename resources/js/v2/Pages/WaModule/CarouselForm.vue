<script setup>
import { computed } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../../Components/Icon.vue'
import SearchableSelect from '../../Components/SearchableSelect.vue'
import WaMediaInput from '../../Components/WaMediaInput.vue'

const pageProps = usePage()
const isRtl = computed(() => (pageProps.props.locale ?? 'en') === 'ar')
const t = computed(() => isRtl.value ? {
    crumbs: 'القوالب', title: 'قالب كاروسيل', bundle: 'رسالة التقديم', cards: 'البطاقات', addCard: 'إضافة بطاقة', img: 'رابط الصورة', cbody: 'نص البطاقة', addBtn: 'زر',
    name: 'الاسم', category: 'الفئة', lang: 'اللغة', cancel: 'إلغاء', save: 'حفظ كمسودة', saveSubmit: 'حفظ وإرسال',
} : {
    crumbs: 'Message Templates', title: 'Carousel Template', bundle: 'Intro message', cards: 'Cards', addCard: 'Add card', img: 'Image URL', cbody: 'Card text', addBtn: 'Button',
    name: 'Template Name (Slug)', category: 'Category', lang: 'Language', cancel: 'Cancel', save: 'Save draft', saveSubmit: 'Save & submit',
})
const langItems = [{ value: 'en', label: 'English' }, { value: 'ar', label: 'العربية' }]

const cForm = useForm({ name: '', category: 'UTILITY', language: 'en', body: '', cards: [newCard(), newCard()], publish: false })
function newCard() { return { image_path: '', image_url: '', body: '', buttons: [] } }
function addCard() { if (cForm.cards.length < 10) cForm.cards.push(newCard()) }
function removeCard(i) { if (cForm.cards.length > 2) cForm.cards.splice(i, 1) }
function addCardBtn(card) { if (card.buttons.length < 2) card.buttons.push({ type: 'QUICK_REPLY', text: '', url: '' }) }
function removeCardBtn(card, i) { card.buttons.splice(i, 1) }
function cancel() { router.get(route('v2.wa-module.templates')) }
function submit(publish) { cForm.publish = publish; cForm.post(route('v2.wa-module.templates.carousel')) }
</script>

<template>
    <Head :title="t.title" />
    <div style="padding:24px; max-width:1100px; margin:0 auto;">
        <div style="margin-bottom:16px;">
            <div style="font-size:12px; color:var(--fg-faint);"><a :href="route('v2.wa-module.templates')" style="color:var(--fg-subtle);">{{ t.crumbs }}</a> › {{ t.title }}</div>
            <h1 style="margin:6px 0 0; font-size:24px; font-weight:700; color:var(--fg); display:flex; align-items:center; gap:10px;"><Icon name="gallery-horizontal-end" :size="22" style="color:#8b5cf6;" /> {{ t.title }}</h1>
        </div>

        <div class="card" style="padding:22px; display:grid; gap:16px;">
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <div style="flex:2; min-width:240px;"><label class="wa-lbl">{{ t.name }}</label><input v-model="cForm.name" class="input" placeholder="summer_carousel_en" /><div v-if="cForm.errors.name" class="wa-err">{{ cForm.errors.name }}</div></div>
                <div style="flex:1; min-width:140px;"><label class="wa-lbl">{{ t.category }}</label><SearchableSelect v-model="cForm.category" :items="[{value:'MARKETING',label:'Marketing'},{value:'UTILITY',label:'Utility'}]" :nullable="false" /></div>
                <div style="flex:1; min-width:140px;"><label class="wa-lbl">{{ t.lang }}</label><SearchableSelect v-model="cForm.language" :items="langItems" :nullable="false" /></div>
            </div>
            <div><label class="wa-lbl">{{ t.bundle }}</label><textarea v-model="cForm.body" class="input" rows="2" maxlength="1024"></textarea><div v-if="cForm.errors.body" class="wa-err">{{ cForm.errors.body }}</div></div>
            <div style="display:flex; justify-content:space-between; align-items:center;"><label class="wa-lbl" style="margin:0;">{{ t.cards }} ({{ cForm.cards.length }}/10)</label><button type="button" class="btn btn-ghost btn-sm" :disabled="cForm.cards.length>=10" @click="addCard"><Icon name="plus" :size="12" /> {{ t.addCard }}</button></div>
            <div style="display:flex; gap:12px; overflow-x:auto; padding-bottom:6px;">
                <div v-for="(card,ci) in cForm.cards" :key="ci" class="card" style="min-width:260px; max-width:260px; padding:12px; flex:0 0 auto;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;"><span style="font-size:12px; font-weight:600; color:var(--fg);">#{{ ci+1 }}</span><button v-if="cForm.cards.length>2" type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeCard(ci)"><Icon name="x" :size="12" :style="{color:'var(--destructive)'}" /></button></div>
                    <div style="margin-bottom:8px;"><WaMediaInput v-model="card.image_path" :url="card.image_url" @update:url="v => card.image_url = v" kind="image" /></div>
                    <div v-if="cForm.errors['cards.'+ci+'.image_path']" class="wa-err" style="margin-bottom:6px;">{{ cForm.errors['cards.'+ci+'.image_path'] }}</div>
                    <textarea v-model="card.body" class="input" :placeholder="t.cbody" rows="2" maxlength="160" style="font-size:12px;"></textarea>
                    <div v-for="(b,bi) in card.buttons" :key="bi" style="display:flex; gap:4px; margin-top:6px; align-items:center;">
                        <SearchableSelect v-model="b.type" :items="[{ value: 'QUICK_REPLY', label: 'Reply' }, { value: 'URL', label: 'URL' }]" :nullable="false" :width="90" />
                        <input v-model="b.text" class="input" placeholder="Text" style="flex:1; font-size:11px;" maxlength="25" />
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="removeCardBtn(card,bi)"><Icon name="x" :size="11" /></button>
                    </div>
                    <input v-for="(b,bi) in card.buttons.filter(x=>x.type==='URL')" :key="'u'+bi" v-model="b.url" class="input" placeholder="https://…" style="font-size:11px; margin-top:4px;" />
                    <button v-if="card.buttons.length<2" type="button" class="btn btn-ghost btn-sm" style="margin-top:6px; width:100%;" @click="addCardBtn(card)"><Icon name="plus" :size="11" /> {{ t.addBtn }}</button>
                </div>
            </div>
            <div v-if="cForm.errors.cards" class="wa-err">{{ cForm.errors.cards }}</div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
            <button class="btn btn-ghost" @click="cancel">{{ t.cancel }}</button>
            <button class="btn btn-ghost" :disabled="cForm.processing" @click="submit(false)">{{ t.save }}</button>
            <button class="btn btn-primary" :disabled="cForm.processing" @click="submit(true)">{{ t.saveSubmit }}</button>
        </div>
    </div>
</template>

<style scoped>
.wa-lbl { display:block; font-size:12px; color:var(--fg-subtle); margin-bottom:4px; }
.wa-err { font-size:11px; color:var(--destructive); margin-top:3px; }
</style>
