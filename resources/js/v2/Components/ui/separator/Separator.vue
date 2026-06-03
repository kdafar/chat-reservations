<script setup>
import { Separator as SeparatorRoot } from 'reka-ui'
import { cn } from '../../../lib/utils.js'

defineProps({
    orientation: { type: String, default: 'horizontal' },
    decorative: { type: Boolean, default: true },
    class: { type: null, default: '' },
})
</script>
<template>
    <SeparatorRoot
        :decorative="decorative"
        :orientation="orientation"
        :class="cn(
            'shrink-0 bg-border',
            orientation === 'horizontal' ? 'h-px w-full' : 'h-full w-px',
            $props.class
        )"
    />
</template>
