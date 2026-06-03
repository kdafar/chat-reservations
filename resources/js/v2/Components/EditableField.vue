<script setup>
import { nextTick, ref, watch } from 'vue'
import Icon from './Icon.vue'

/**
 * Inline-edit field. Renders as plain text until clicked (or its edit
 * pencil is clicked), then becomes a textarea/input with save/cancel.
 *
 * Usage:
 *   <EditableField
 *       v-model="visit.diagnosis"
 *       :on-save="(v) => save('diagnosis', v)"
 *       multiline
 *       placeholder="No diagnosis recorded yet."
 *   />
 *
 * `on-save` should return a Promise; the component flips into a `loading`
 * state and only commits the value on resolve.
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    onSave: { type: Function, required: true },
    multiline: { type: Boolean, default: true },
    type: { type: String, default: 'text' }, // 'text' | 'number' | 'date'
    placeholder: { type: String, default: '' },
    placeholderClass: { type: String, default: '' },
    readOnly: { type: Boolean, default: false },
    /** Min textarea rows when multiline. */
    rows: { type: Number, default: 3 },
    saveLabel: { type: String, default: 'Save' },
    cancelLabel: { type: String, default: 'Cancel' },
    /** Max input length (passed to maxlength). */
    maxlength: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue'])

const editing = ref(false)
const draft = ref(props.modelValue ?? '')
const saving = ref(false)
const error = ref('')
const inputRef = ref(null)

watch(() => props.modelValue, (v) => {
    if (!editing.value) draft.value = v ?? ''
})

async function beginEdit() {
    if (props.readOnly || saving.value) return
    draft.value = props.modelValue ?? ''
    editing.value = true
    error.value = ''
    await nextTick()
    inputRef.value?.focus()
    if (inputRef.value?.select) inputRef.value.select()
}

function cancel() {
    if (saving.value) return
    editing.value = false
    draft.value = props.modelValue ?? ''
    error.value = ''
}

async function save() {
    if (saving.value) return
    const next = props.type === 'number' && draft.value !== '' && draft.value !== null
        ? Number(draft.value)
        : draft.value === '' ? null : draft.value

    // No-op if unchanged.
    if (next === props.modelValue) {
        editing.value = false
        return
    }

    saving.value = true
    error.value = ''
    try {
        await props.onSave(next)
        emit('update:modelValue', next)
        editing.value = false
    } catch (e) {
        error.value = e?.message || 'Save failed'
    } finally {
        saving.value = false
    }
}

function onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); cancel() }
    // Enter saves on single-line; Cmd/Ctrl+Enter saves on multiline.
    if (e.key === 'Enter' && (!props.multiline || e.metaKey || e.ctrlKey)) {
        e.preventDefault()
        save()
    }
}
</script>

<template>
    <div class="ef">
        <!-- DISPLAY -->
        <div
            v-if="!editing"
            class="ef-display"
            :class="{ 'is-empty': !modelValue, 'is-readonly': readOnly }"
            @click="beginEdit"
        >
            <div class="ef-text" :class="placeholderClass">
                <slot v-if="modelValue" name="display" :value="modelValue">{{ modelValue }}</slot>
                <span v-else style="color: var(--fg-subtle); font-style: italic;">{{ placeholder }}</span>
            </div>
            <button
                v-if="!readOnly"
                type="button"
                class="ef-edit-btn"
                :title="saveLabel"
                @click.stop="beginEdit"
            >
                <Icon name="pencil" :size="13" />
            </button>
        </div>

        <!-- EDIT -->
        <div v-else class="ef-edit">
            <textarea
                v-if="multiline"
                ref="inputRef"
                v-model="draft"
                :rows="rows"
                :maxlength="maxlength"
                :placeholder="placeholder"
                class="ef-input"
                :disabled="saving"
                @keydown="onKey"
            />
            <input
                v-else
                ref="inputRef"
                v-model="draft"
                :type="type"
                :maxlength="maxlength"
                :placeholder="placeholder"
                class="ef-input ef-input-single"
                :disabled="saving"
                @keydown="onKey"
            />

            <div class="ef-actions">
                <span v-if="error" class="ef-error">{{ error }}</span>
                <span v-else class="ef-hint mono">
                    {{ multiline ? '⌘↵' : '↵' }} save · Esc cancel
                </span>
                <span style="flex: 1;"></span>
                <button type="button" class="btn btn-ghost btn-sm" :disabled="saving" @click="cancel">{{ cancelLabel }}</button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="saving" @click="save">
                    <Icon v-if="saving" name="loader" :size="12" />
                    {{ saveLabel }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ef-display {
    position: relative;
    cursor: text;
    border-radius: 8px;
    padding: 6px 32px 6px 8px;
    margin: -6px -8px;
    transition: background 0.12s;
    min-height: 28px;
}
.ef-display:hover:not(.is-readonly) { background: var(--bg-hover); }
.ef-display:hover .ef-edit-btn { opacity: 1; }
.ef-display.is-readonly { cursor: default; padding-right: 8px; padding-left: 8px; }

.ef-text {
    font-size: 14px;
    line-height: 1.55;
    color: var(--fg);
    white-space: pre-wrap;
    word-wrap: break-word;
}

.ef-edit-btn {
    position: absolute;
    top: 4px;
    inset-inline-end: 4px;
    width: 24px; height: 24px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px;
    background: transparent;
    border: 1px solid transparent;
    color: var(--fg-subtle);
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.12s, background 0.12s, color 0.12s;
    font-family: inherit;
}
.ef-edit-btn:hover { background: var(--bg-elev); border-color: var(--line); color: var(--fg); }

.ef-edit { display: flex; flex-direction: column; gap: 8px; }
.ef-input {
    width: 100%;
    padding: 10px 12px;
    border-radius: var(--radius-input);
    border: 1px solid var(--line);
    background: var(--bg-elev);
    color: var(--fg);
    font-size: 14px;
    line-height: 1.55;
    font-family: inherit;
    resize: vertical;
    min-height: 60px;
    transition: border-color 0.12s, box-shadow 0.12s;
}
.ef-input-single { min-height: 36px; }
.ef-input:focus {
    outline: none;
    border-color: oklch(calc(var(--gold-l) + 0.02) var(--gold-c) var(--gold-h));
    box-shadow: 0 0 0 3px var(--ring);
}

.ef-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
}
.ef-hint { color: var(--fg-faint); }
.ef-error { color: var(--destructive); font-weight: 500; }
</style>
