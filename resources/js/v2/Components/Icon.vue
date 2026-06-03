<script setup>
import { computed } from 'vue'
import * as L from 'lucide-vue-next'

/**
 * Tiny wrapper so we can write <Icon name="bell" size="14" /> in templates
 * and match the design's <Icon name="..." /> API exactly.
 * lucide-vue-next exports PascalCase components ("ArrowRight", "X", "Bell"…),
 * so we map kebab/dashed names to PascalCase here.
 */
const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 16 },
    strokeWidth: { type: [Number, String], default: 1.75 },
})

function pascal(name) {
    return name
        .split('-')
        .filter(Boolean)
        .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
        .join('')
}

const component = computed(() => L[pascal(props.name)] || L.HelpCircle)
</script>

<template>
    <component
        :is="component"
        :size="Number(size)"
        :stroke-width="Number(strokeWidth)"
        aria-hidden="true"
        style="display: inline-flex; line-height: 0; flex-shrink: 0;"
    />
</template>
