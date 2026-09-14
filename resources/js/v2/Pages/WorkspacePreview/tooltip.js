/**
 * v-tip — the preview's hover label, in place of the browser's `title`.
 *
 * A native title tooltip is a yellow-ish OS box that appears after a second,
 * ignores the theme and the font, and never shows on keyboard focus. This one
 * uses the design tokens, follows dark mode, shows on focus as well as hover,
 * and flips below the element when there is no room above.
 *
 *   <button v-tip="t.reset" :aria-label="t.reset">…</button>
 *
 * One floating element is shared by every tip on the page. Pass a falsy value
 * to show nothing. Keep an aria-label on icon-only buttons — the tip is for
 * sighted pointer users; the label is what a screen reader reads.
 */

const DELAY_MS = 300
let el = null
let timer = null
let current = null

function ensureEl() {
    if (el) return el
    el = document.createElement('div')
    el.className = 'wsp-tip'
    el.setAttribute('role', 'tooltip')
    el.hidden = true
    document.body.appendChild(el)
    return el
}

function place(target) {
    const tip = ensureEl()
    const r = target.getBoundingClientRect()
    const t = tip.getBoundingClientRect()
    const gap = 6
    let top = r.top - t.height - gap
    let below = false
    if (top < 8) { top = r.bottom + gap; below = true }
    let left = r.left + r.width / 2 - t.width / 2
    left = Math.max(8, Math.min(left, window.innerWidth - t.width - 8))
    tip.style.top = `${Math.round(top)}px`
    tip.style.left = `${Math.round(left)}px`
    tip.dataset.side = below ? 'bottom' : 'top'
}

function show(target) {
    const text = target.__tip
    if (!text) return
    const tip = ensureEl()
    tip.textContent = text
    tip.dir = document.documentElement.dir || 'ltr'
    tip.hidden = false
    tip.classList.remove('is-in')
    current = target
    place(target)
    requestAnimationFrame(() => tip.classList.add('is-in'))
}

function hide() {
    clearTimeout(timer)
    timer = null
    current = null
    if (el) { el.hidden = true; el.classList.remove('is-in') }
}

function schedule(target) {
    clearTimeout(timer)
    // Moving between two tipped buttons should not wait again.
    const delay = el && !el.hidden ? 0 : DELAY_MS
    timer = setTimeout(() => show(target), delay)
}

function bind(node, value) {
    node.__tip = value || ''
    if (node.__tipBound) return
    node.__tipBound = true
    node.__tipOn = () => schedule(node)
    node.__tipOff = () => { if (current === node || timer) hide() }
    node.addEventListener('mouseenter', node.__tipOn)
    node.addEventListener('focusin', node.__tipOn)
    node.addEventListener('mouseleave', node.__tipOff)
    node.addEventListener('focusout', node.__tipOff)
    node.addEventListener('pointerdown', hide)
    node.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide() })
}

let globalsBound = false
function bindGlobals() {
    if (globalsBound) return
    globalsBound = true
    window.addEventListener('scroll', hide, true)
    window.addEventListener('resize', hide)
}

export const vTip = {
    mounted(node, binding) { bindGlobals(); bind(node, binding.value) },
    updated(node, binding) {
        node.__tip = binding.value || ''
        if (current === node) { if (node.__tip) { el.textContent = node.__tip; place(node) } else hide() }
    },
    beforeUnmount(node) {
        if (current === node) hide()
        node.removeEventListener('mouseenter', node.__tipOn)
        node.removeEventListener('focusin', node.__tipOn)
        node.removeEventListener('mouseleave', node.__tipOff)
        node.removeEventListener('focusout', node.__tipOff)
        node.removeEventListener('pointerdown', hide)
    },
}
