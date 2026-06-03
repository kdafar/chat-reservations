<script setup>
import { AvatarImage as RImage } from 'reka-ui'
import { cn } from '../../../lib/utils.js'
defineProps({
    src: { type: String, default: '' },
    alt: { type: String, default: '' },
    class: { type: null, default: '' },
})
</script>
<template>
    <RImage :src="src" :alt="alt" :class="cn('aspect-square h-full w-full object-cover', $props.class)" />
</template>
