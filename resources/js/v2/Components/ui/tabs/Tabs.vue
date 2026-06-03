<script setup>
import { TabsRoot, useForwardPropsEmits } from 'reka-ui'

const props = defineProps({
    defaultValue: { type: String, default: undefined },
    modelValue: { type: String, default: undefined },
    orientation: { type: String, default: 'horizontal' },
    dir: { type: String, default: undefined },
    activationMode: { type: String, default: 'automatic' },
    class: { type: null, default: '' },
})
const emits = defineEmits(['update:modelValue'])
const forwarded = useForwardPropsEmits(props, emits)
</script>
<template>
    <TabsRoot v-bind="forwarded" :class="$props.class"><slot /></TabsRoot>
</template>
