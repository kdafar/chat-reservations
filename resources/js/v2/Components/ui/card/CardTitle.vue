<script setup>
import { cn } from '../../../lib/utils.js'
defineProps({ class: { type: null, default: '' } })
</script>
<template>
    <h3 :class="cn('font-semibold leading-none tracking-tight', $props.class)"><slot /></h3>
</template>
