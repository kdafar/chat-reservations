<script setup>
// Lightweight shimmer placeholder used inside <Deferred> fallbacks.
defineProps({
    height: { type: String, default: '14px' },
    width: { type: String, default: '100%' },
    radius: { type: String, default: '6px' },
})
</script>

<template>
    <span class="v2-skel" :style="{ height, width, borderRadius: radius }" />
</template>

<style scoped>
.v2-skel {
    display: block;
    background: linear-gradient(90deg, var(--bg-hover) 25%, var(--line) 37%, var(--bg-hover) 63%);
    background-size: 400% 100%;
    animation: v2-shimmer 1.2s ease-in-out infinite;
}
@keyframes v2-shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}
</style>
