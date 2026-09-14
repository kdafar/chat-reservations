import { onMounted, onUnmounted } from 'vue'

/**
 * Keyboard control for the queue — DESIGN PREVIEW, sealed like the page it
 * serves. No network, no router, no imports beyond Vue.
 *
 * A receptionist works this screen with a phone against one ear and a queue in
 * front of the desk. Arrow through the list, Enter to do the obvious thing,
 * "/" back to search, Escape to get out — no mouse in the loop.
 *
 *   ArrowDown / ArrowUp   move through the VISIBLE rows, wrapping at the ends
 *   Enter                 run the selected row's primary action
 *   Escape                blur the focused field, else clear the selection
 *   "/"                   jump to the queue search box
 *
 * Direction is deliberately NOT mirrored in Arabic: down is down on a keyboard
 * in every language, and the queue is a vertical list. Only horizontal keys
 * would need RTL handling, and there are none here.
 *
 *   useQueueKeys({ items: filtered, selectedId, onPrimary, onEscape, searchEl })
 *
 * @param {object}   opts
 * @param {import('vue').Ref<Array>} opts.items       visible rows, in display order
 * @param {import('vue').Ref}        opts.selectedId  id of the selected row (string or number)
 * @param {Function}                 opts.onPrimary   run the selected row's primary action
 * @param {Function}                 opts.onEscape    clear the selection
 * @param {import('vue').Ref<HTMLInputElement|null>} opts.searchEl  the queue search input
 * @returns {void}
 */
export function useQueueKeys(opts) {
    const { items, selectedId, onPrimary, onEscape, searchEl } = opts ?? {}

    /**
     * Is the user typing? Arrow keys, Enter and "/" all belong to a field while
     * it has focus — stealing them there would make the notes textarea unusable
     * and turn every "/" into a lost keystroke.
     *
     * Escape is the exception and is handled by the caller of this helper: it
     * means "let me out", which is exactly what a focused field needs too.
     */
    function isTypingIn(el) {
        if (!el) return false
        if (el.isContentEditable) return true
        const tag = (el.tagName ?? '').toLowerCase()
        return tag === 'input' || tag === 'textarea' || tag === 'select'
    }

    function visibleRows() {
        const list = items?.value
        return Array.isArray(list) ? list : []
    }

    /**
     * Move the selection by one row, wrapping.
     *
     * With nothing selected, ArrowDown lands on the first row and ArrowUp on
     * the last — pressing a direction should always produce a selection rather
     * than nothing at all.
     */
    function move(step) {
        const list = visibleRows()
        if (list.length === 0) return

        const at = list.findIndex((r) => r.id === selectedId?.value)
        if (at === -1) {
            selectedId.value = step > 0 ? list[0].id : list[list.length - 1].id
            return
        }
        // Modulo twice so a negative step wraps to the end rather than to -1.
        const next = ((at + step) % list.length + list.length) % list.length
        selectedId.value = list[next].id
    }

    function onKeydown(e) {
        // A dialog got this key first. ConfirmDialog listens on document, which
        // hears the event before this window listener, and it preventDefault()s
        // Enter and Escape. Without this check one Enter confirms the dialog AND
        // runs the queue's primary action for the patient's next status, and one
        // Escape closes the dialog AND drops the selection.
        if (e.defaultPrevented) return

        // A shortcut the browser or OS owns (Ctrl/Cmd/Alt) is not ours to take.
        if (e.ctrlKey || e.metaKey || e.altKey) return

        const typing = isTypingIn(e.target)

        if (e.key === 'Escape') {
            // Inside a field, Escape means "give me the keyboard back"; the
            // selection survives so the row is still there to act on.
            if (typing && typeof e.target.blur === 'function') {
                e.target.blur()
                return
            }
            onEscape?.()
            return
        }

        if (typing) return

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault() // otherwise the page scrolls under the queue
                move(1)
                break
            case 'ArrowUp':
                e.preventDefault()
                move(-1)
                break
            case 'Enter':
                // No guard on "is there a selection" — the primary action is
                // the page's business, and it already knows when it is a no-op.
                e.preventDefault()
                onPrimary?.()
                break
            case '/':
                // Focus the field, and swallow the key so the box does not open
                // with a stray "/" already typed into it.
                if (searchEl?.value) {
                    e.preventDefault()
                    searchEl.value.focus()
                    searchEl.value.select?.()
                }
                break
        }
    }

    onMounted(() => window.addEventListener('keydown', onKeydown))
    onUnmounted(() => window.removeEventListener('keydown', onKeydown))
}
