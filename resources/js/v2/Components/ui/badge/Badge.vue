<script setup>
import { cn } from '../../../lib/utils.js'
import { cva } from 'class-variance-authority'

const badgeVariants = cva(
    'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold transition-colors',
    {
        variants: {
            variant: {
                default:   'border-transparent bg-primary text-primary-foreground shadow',
                secondary: 'border-transparent bg-secondary text-secondary-foreground',
                outline:   'text-foreground',
                destructive: 'border-transparent bg-destructive text-destructive-foreground',
                success:   'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
                warning:   'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
                info:      'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
                violet:    'border-transparent bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
            },
        },
        defaultVariants: { variant: 'default' },
    },
)

defineProps({
    variant: { type: String, default: 'default' },
    class: { type: null, default: '' },
})
</script>
<template>
    <div :class="cn(badgeVariants({ variant }), $props.class)"><slot /></div>
</template>
