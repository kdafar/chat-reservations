<script setup>
import { cn } from '../../../lib/utils.js'
defineProps({ class: { type: null, default: '' } })
</script>
<template>
    <div :class="cn('rounded-xl border bg-card text-card-foreground shadow-sm', $props.class)">
        <slot />
    </div>
</template>
