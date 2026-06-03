<script setup>
import { TabsContent as RContent } from 'reka-ui'
import { cn } from '../../../lib/utils.js'

defineProps({
    value: { type: String, required: true },
    class: { type: null, default: '' },
})
</script>
<template>
    <RContent
        :value="value"
        :class="cn('mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2', $props.class)"
    >
        <slot />
    </RContent>
</template>
