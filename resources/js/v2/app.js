import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'

// Inertia's router (router.post/put/delete, e.g. logout) uses axios, which by
// default auto-sends the X-XSRF-TOKEN header read from a cookie literally named
// "XSRF-TOKEN". We renamed that cookie to "clinic_xsrf_token" so it can't
// collide with the fleet app on the shared *.majestic-kw.com parent domain, so
// axios must be told the new name — otherwise it sends the stale/foreign
// XSRF-TOKEN cookie and the server rejects it with a 419.
axios.defaults.xsrfCookieName = 'clinic_xsrf_token'
// Belt-and-suspenders: also send the current session token via X-CSRF-TOKEN
// (which Laravel reads BEFORE the cookie-based X-XSRF-TOKEN), matching how the
// v2 fetch() calls already authenticate. Cookie-independent, so it can't be
// thrown off by leftover parent-domain cookies.
const csrfMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
if (csrfMeta) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta
}

// The whole v2 app calls a bare, un-imported `route('v2.x')` helper — in page
// <script setup> handlers (module scope) AND in a few template bindings. Ziggy
// provides the resolver; `@routes` in the blade head declares the global
// `Ziggy` config object, which Ziggy's own route() picks up automatically when
// no config is passed. We expose `route()` two ways so both scopes resolve:
//   • window.route        → bare route() in <script setup>
//   • globalProperties    → route() inside <template>
const routeFn = (name, params, absolute) => route(name, params, absolute)
window.route = routeFn

createInertiaApp({
    title: (t) => (t ? `${t} · Clinic` : 'Clinic'),

    // Lazy page resolution: each page compiles to its OWN chunk, fetched on
    // navigation, instead of being eager-bundled into one ~3MB entry. Inertia
    // awaits this promise — including for the initial page — so the boot splash
    // (removed in setup() below) stays up until the first page chunk lands.
    // Navigation chunk fetches are covered by the NProgress bar + Links'
    // prefetch="click", so there's no blank flash.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue')
        const importer = pages[`./Pages/${name}.vue`]
        if (!importer) throw new Error(`[v2] Inertia page not found: ${name}`)
        return importer().then((module) => module.default)
    },

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
        app.use(plugin)
        app.config.globalProperties.route = routeFn // route() usable in templates
        app.mount(el)

        // Dismiss the branded boot splash (resources/views/inertia/app.blade.php)
        // once the app has painted. requestAnimationFrame waits for the first
        // real frame so we never flip from splash → blank → content.
        requestAnimationFrame(() => {
            const splash = document.getElementById('v2-splash')
            if (!splash) return
            splash.classList.add('is-done')
            splash.addEventListener('transitionend', () => splash.remove(), { once: true })
            setTimeout(() => splash.remove(), 800) // fallback if transitionend is missed
        })
    },

    progress: {
        color: '#b19860',
        showSpinner: true,
    },
})
