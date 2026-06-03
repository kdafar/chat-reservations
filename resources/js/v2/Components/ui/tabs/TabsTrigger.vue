<script setup>
import { TabsTrigger as RTrigger } from 'reka-ui'
import { cn } from '../../../lib/utils.js'

defineProps({
    value: { type: String, required: true },
    disabled: { type: Boolean, default: false },
    class: { type: null, default: '' },
})
</script>
<template>
    <RTrigger
        :value="value"
        :disabled="disabled"
        :class="cn(
            'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium ring-offset-background transition-all',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
            'disabled:pointer-events-none disabled:opacity-50',
            'data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow',
            $props.class
        )"
    >
        <slot />
    </RTrigger>
</template>
