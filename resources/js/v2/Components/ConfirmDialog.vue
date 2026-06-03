<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Icon from './Icon.vue'

const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
    title: { type: String, default: 'Are you sure?' },
    body: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    /** 'destructive' uses rose red; 'primary' uses gold. */
    tone: { type: String, default: 'destructive' },
    icon: { type: String, default: '' },
    /** Optional input that we capture and pass back via @confirm. */
    inputPlaceholder: { type: String, default: '' },
    inputLabel: { type: String, default: '' },
    inputRequired: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
})
const emit = defineEmits(['confirm', 'cancel'])

const inputValue = ref('')

watch(open, (v) => {
    if (v) inputValue.value = ''
})

function onKey(e) {
    if (!open.value) return
    if (e.key === 'Escape') { e.preventDefault(); cancel() }
    if (e.key === 'Enter' && !props.inputRequired) { e.preventDefault(); confirm() }
}

function confirm() {
    if (props.inputRequired && inputValue.value.trim() === '') return
    emit('confirm', inputValue.value)
}
function cancel() {
    emit('cancel')
    open.value = false
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))

const iconColor = computed(() => props.tone === 'destructive' ? 'var(--destructive)' : 'var(--primary)')
const iconBg = computed(() => props.tone === 'destructive' ? 'var(--destructive-soft)' : 'var(--primary-soft)')
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="cd-overlay overlay-enter" @click.self="cancel">
                <div class="cd-panel" role="dialog" aria-modal="true">
                    <div style="display: flex; align-items: flex-start; gap: 14px; padding: 22px 22px 8px;">
                        <span
                            :style="{
                                width: '44px', height: '44px', borderRadius: '12px',
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                background: iconBg, color: iconColor,
                                flexShrink: 0,
                            }"
                        >
                            <Icon :name="icon || (tone === 'destructive' ? 'alert-triangle' : 'check-circle-2')" :size="20" />
                        </span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 500; font-size: 16px;">{{ title }}</div>
                            <div v-if="body" style="font-size: 13px; color: var(--fg-muted); margin-top: 4px; line-height: 1.55;">{{ body }}</div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" aria-label="Close" @click="cancel">
                            <Icon name="x" :size="14" />
                        </button>
                    </div>

                    <div v-if="inputPlaceholder || inputLabel" style="padding: 4px 22px 8px;">
                        <label v-if="inputLabel" style="font-size: 11px; color: var(--fg-subtle); display: block; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600;">
                            {{ inputLabel }}
                        </label>
                        <input
                            v-model="inputValue"
                            :placeholder="inputPlaceholder"
                            class="input"
                            @keydown.enter.prevent="confirm"
                        />
                    </div>

                    <div style="display: flex; gap: 8px; padding: 16px 22px 20px;">
                        <button type="button" class="btn btn-outline" style="flex: 1;" :disabled="loading" @click="cancel">{{ cancelLabel }}</button>
                        <button
                            type="button"
                            :class="['btn', tone === 'destructive' ? 'btn-destructive' : 'btn-primary']"
                            style="flex: 1;"
                            :disabled="loading || (inputRequired && inputValue.trim() === '')"
                            @click="confirm"
                        >
                            <Icon v-if="loading" name="loader" :size="13" />
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cd-overlay {
    position: fixed; inset: 0;
    background: oklch(0.18 0.02 260 / 0.4);
    z-index: 90;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
    -webkit-backdrop-filter: blur(2px);
    backdrop-filter: blur(2px);
}
.cd-panel {
    width: min(440px, 92vw);
    background: var(--bg-elev);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
