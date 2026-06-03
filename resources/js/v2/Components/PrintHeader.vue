<script setup>
/**
 * Print-only letterhead. Hidden on screen (.print-only), rendered at the top of
 * a report when the user prints. Pulls the clinic name/logo from Inertia's
 * shared `app` props and stamps the print time so the paper copy is self-dating.
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
})

const page = usePage()
const appName = computed(() => page.props.app?.name ?? 'Clinic')
const appLogo = computed(() => page.props.app?.logo_url ?? null)

const printedAt = new Date().toLocaleString(
    (page.props.locale ?? 'en') === 'ar' ? 'ar' : 'en-GB',
    { dateStyle: 'medium', timeStyle: 'short' },
)
</script>

<template>
    <div class="print-only print-letterhead">
        <div class="ph-brand">
            <img v-if="appLogo" :src="appLogo" :alt="appName" class="ph-logo" />
            <div class="ph-name">{{ appName }}</div>
        </div>
        <div class="ph-meta">
            <div v-if="title" class="ph-title">{{ title }}</div>
            <div v-if="subtitle" class="ph-sub">{{ subtitle }}</div>
            <div class="ph-date">{{ printedAt }}</div>
        </div>
    </div>
</template>
