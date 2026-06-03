<script setup>
import { AvatarFallback as RFallback } from 'reka-ui'
import { cn } from '../../../lib/utils.js'
defineProps({ class: { type: null, default: '' } })
</script>
<template>
    <RFallback :class="cn('flex h-full w-full items-center justify-center rounded-full bg-muted font-semibold text-sm', $props.class)">
        <slot />
    </RFallback>
</template>
