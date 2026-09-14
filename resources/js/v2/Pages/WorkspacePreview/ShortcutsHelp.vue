<script setup>
/** The "?" panel: every keyboard shortcut on the page, in one place. */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'
import PreviewDialog from './PreviewDialog.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    tabs: { type: Array, default: () => [] },   // [{ key, label }] in number order
})
const emit = defineEmits(['close'])
const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

const groups = computed(() => {
    const ar = isRtl.value
    return [
        { title: ar ? 'الطابور' : 'Queue', keys: [
            [['↑', '↓'], ar ? 'التنقل بين المرضى' : 'Move between patients'],
            [['Enter'], ar ? 'الخطوة التالية للمريض المحدد' : 'Next step for the selected patient'],
            [['/'], ar ? 'البحث في الطابور' : 'Search the queue'],
            [['Esc'], ar ? 'إغلاق / إلغاء التحديد' : 'Close / clear selection'],
        ] },
        { title: ar ? 'تبويبات الزيارة' : 'Visit tabs', keys: props.tabs.map((x, i) => [[String((i + 1) % 10)], x.label]) },
        { title: ar ? 'أخرى' : 'Other', keys: [
            [['C'], ar ? 'نداء المريض المحدد' : 'Call the selected patient to the room'],
            [['?'], ar ? 'هذه القائمة' : 'This list'],
        ] },
    ]
})
</script>

<template>
    <PreviewDialog :open="open" :width="520" @close="emit('close')">
        <div class="sh">
            <div class="sh-head">
                <Icon name="keyboard" :size="18" />
                <span class="sh-title">{{ isRtl ? 'اختصارات لوحة المفاتيح' : 'Keyboard shortcuts' }}</span>
                <span style="flex: 1;"></span>
                <button type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="isRtl ? 'إغلاق' : 'Close'" @click="emit('close')"><Icon name="x" :size="15" /></button>
            </div>
            <div class="sh-grid">
                <section v-for="g in groups" :key="g.title">
                    <div class="sh-k">{{ g.title }}</div>
                    <div v-for="[keys, label] in g.keys" :key="label" class="sh-row">
                        <span class="sh-label">{{ label }}</span>
                        <span class="sh-keys"><kbd v-for="k in keys" :key="k">{{ k }}</kbd></span>
                    </div>
                </section>
            </div>
            <div class="sh-note">{{ isRtl ? 'لا تعمل الاختصارات أثناء الكتابة في حقل.' : 'Shortcuts are off while you are typing in a field.' }}</div>
        </div>
    </PreviewDialog>
</template>

<style scoped>
.sh { padding: 18px 20px; display: flex; flex-direction: column; gap: 12px; }
.sh-head { display: flex; align-items: center; gap: 8px; color: var(--fg-muted); }
.sh-title { font-size: 15px; font-weight: 600; color: var(--fg); }
.sh-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 22px; }
.sh-grid section:first-child, .sh-grid section:last-child { grid-column: auto; }
@media (max-width: 560px) { .sh-grid { grid-template-columns: 1fr; } }
.sh-k { font-size: 10.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--fg-faint); margin: 6px 0 4px; }
.sh-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 4px 0; font-size: 12.5px; border-bottom: 1px solid var(--line); }
.sh-label { color: var(--fg-muted); }
.sh-keys { display: inline-flex; gap: 3px; }
kbd { min-width: 22px; height: 22px; padding: 0 6px; display: inline-flex; align-items: center; justify-content: center; font-family: inherit; font-size: 11.5px; font-weight: 600;
    color: var(--fg); background: var(--bg-sunken); border: 1px solid var(--line-strong); border-bottom-width: 2px; border-radius: 5px; }
.sh-note { font-size: 11.5px; color: var(--fg-faint); }
</style>
