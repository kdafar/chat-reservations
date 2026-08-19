<script setup>
/**
 * In-app error panel.
 *
 * Rendered inside the live v2 shell so an error is never a dead end: the
 * sidebar and topbar stay put and the user can just navigate somewhere they
 * do have access to. Wording comes from App\Support\ErrorCopy so this and the
 * standalone Blade pages can never drift apart.
 */
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import Icon from '../Components/Icon.vue'

const props = defineProps({
    status: { type: Number, required: true },
    headline: { type: String, required: true },
    message: { type: String, required: true },
    labels: { type: Object, default: () => ({}) },
    action: { type: String, default: 'home' },
})

const page = usePage()
const isRtl = computed(() => (page.props.locale ?? 'en') === 'ar')

// One glyph per family of problem — permission, missing, conflict, server.
const glyph = computed(() => {
    if (props.status === 403 || props.status === 401) return 'shield'
    if (props.status === 404 || props.status === 410) return 'search'
    if (props.status >= 500) return 'alert-triangle'
    return 'info'
})

function goBack() {
    if (window.history.length > 1) window.history.back()
}
</script>

<template>
    <Head :title="headline" />

    <div class="err-wrap">
        <div class="err-card">
            <div class="err-medallion" aria-hidden="true">
                <Icon :name="glyph" :size="26" />
            </div>

            <h1 class="err-headline" :class="{ 'is-ar': isRtl }">{{ headline }}</h1>
            <hr class="err-rule">
            <p class="err-message">{{ message }}</p>

            <div class="err-actions">
                <Link :href="route('v2.dashboard')" class="btn btn-primary">
                    {{ labels.dashboard || 'Go to dashboard' }}
                </Link>
                <button type="button" class="btn btn-ghost" @click="goBack">
                    {{ labels.back || 'Go back' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.err-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    padding: 2rem 1rem;
}

.err-card {
    position: relative;
    width: 100%;
    max-width: 30rem;
    padding: 3rem 2.5rem 2.5rem;
    text-align: center;
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: var(--radius-card);
    box-shadow: var(--shadow-md);
}

/* Same soft gold wash as the standalone page, scoped to the card. */
.err-card::before {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: radial-gradient(24rem 12rem at 50% 0%, oklch(0.71 0.085 82 / 0.08), transparent 70%);
    pointer-events: none;
}

.err-medallion {
    position: relative;
    width: 4rem;
    height: 4rem;
    margin: 0 auto 1.75rem;
    border-radius: var(--radius-pill);
    background: var(--primary-soft);
    border: 1px solid var(--primary-soft-2);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
}

.err-headline {
    position: relative;
    margin: 0;
    font-family: "Cormorant Garamond", ui-serif, Georgia, serif;
    font-size: 2rem;
    font-weight: 600;
    line-height: 1.15;
    letter-spacing: -0.01em;
    color: var(--fg);
    text-wrap: balance;
}

/* Cormorant carries no Arabic glyphs — fall back to the v2 Arabic face. */
.err-headline.is-ar {
    font-family: var(--font-arabic);
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.45;
}

.err-rule {
    position: relative;
    width: 2.5rem;
    height: 1px;
    margin: 1.25rem auto;
    border: 0;
    background: linear-gradient(90deg, transparent, var(--primary-soft-2), transparent);
}

.err-message {
    position: relative;
    margin: 0 auto 2rem;
    max-width: 24rem;
    color: var(--fg-muted);
    font-size: 0.9375rem;
    text-wrap: pretty;
}

.err-actions {
    position: relative;
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

@media (max-width: 30rem) {
    .err-card { padding: 2.25rem 1.25rem 2rem; }
    .err-headline { font-size: 1.625rem; }
}
</style>
