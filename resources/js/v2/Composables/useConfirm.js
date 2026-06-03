import { reactive } from 'vue'

// Global confirm dialog state — one <ConfirmDialog> is mounted in AppLayout and
// bound to this. Call confirm({ body, onConfirm }) from anywhere instead of the
// native window.confirm(); the callback runs only if the user confirms.
export const confirmState = reactive({ open: false, opts: {}, _cb: null })

/**
 * @param {string|object} opts  A message string, or { title, body, confirmLabel,
 *                              cancelLabel, tone: 'destructive'|'primary', icon, onConfirm }.
 */
export function confirm(opts) {
    const o = typeof opts === 'string' ? { body: opts } : (opts || {})
    confirmState.opts = {
        title: o.title || 'Are you sure?',
        body: o.body || '',
        confirmLabel: o.confirmLabel || 'Confirm',
        cancelLabel: o.cancelLabel || 'Cancel',
        tone: o.tone || 'destructive',
        icon: o.icon || (o.tone === 'primary' ? 'check' : 'alert-triangle'),
    }
    confirmState._cb = typeof o.onConfirm === 'function' ? o.onConfirm : null
    confirmState.open = true
}

export function resolveConfirm() {
    const cb = confirmState._cb
    confirmState.open = false
    confirmState._cb = null
    if (cb) cb()
}

export function cancelConfirm() {
    confirmState.open = false
    confirmState._cb = null
}
