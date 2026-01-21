// --- Alerts / Toasts ---
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'
import 'toastify-js/src/toastify.css'
import Toastify from 'toastify-js'

// --- Alpine ---
import Alpine from 'alpinejs'
import focus from '@alpinejs/focus'
import collapse from '@alpinejs/collapse'
import intersect from '@alpinejs/intersect'
import persist from '@alpinejs/persist'

// --- Leaflet + GeoSearch ---
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'
import { OpenStreetMapProvider } from 'leaflet-geosearch'
// Fix Leaflet default marker icons with Vite
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
})

// --- Inputs / Widgets ---
import 'flatpickr/dist/flatpickr.css'
import flatpickr from 'flatpickr'
import IMask from 'imask'
import EmblaCarousel from 'embla-carousel'

// --- Sentry (frontend) ---
import * as Sentry from '@sentry/browser'
Sentry.init({
  dsn:
    import.meta.env.VITE_SENTRY_DSN ||
    document.querySelector('meta[name="sentry-dsn"]')?.content ||
    undefined,
  environment:
    import.meta.env.VITE_APP_ENV ||
    document.querySelector('meta[name="app-environment"]')?.content ||
    undefined,
  release:
    import.meta.env.VITE_APP_RELEASE ||
    document.querySelector('meta[name="app-release"]')?.content ||
    undefined,
  tracesSampleRate: 0.1, // adjust later
  // integrations: [Sentry.browserTracingIntegration()], // optional if you want routing spans
})

// --- Expose globals for Blade inline scripts (optional) ---
Object.assign(window, {
  Swal,
  Toastify,
  Alpine,
  L,
  OpenStreetMapProvider,
  flatpickr,
  IMask,
  EmblaCarousel,
  // tiny helper
  toast: (text, opts = {}) =>
    Toastify({ text, duration: 2500, gravity: 'bottom', position: 'center', ...opts }).showToast(),
})

// --- Alpine start ---
Alpine.plugin(collapse)
Alpine.plugin(intersect)
Alpine.plugin(persist)
Alpine.plugin(focus)
Alpine.start()
console.log('[app.js] bundle loaded') 

// --- Gentle, safe auto-inits (only if elements exist) ---
document.addEventListener('DOMContentLoaded', () => {
  // Flatpickr
  document.querySelectorAll('[data-flatpickr]').forEach((el) => {
    flatpickr(el, { allowInput: true })
  })
  // Kuwait phone mask example: <input id="phone" data-imask="kw-phone">
  document.querySelectorAll('[data-imask="kw-phone"]').forEach((el) => {
    IMask(el, { mask: '+{965} 000 00000' })
  })
  // Embla carousel: wrap with .js-embla and viewport .js-embla__viewport
  document.querySelectorAll('.js-embla').forEach((root) => {
    const viewport = root.querySelector('.js-embla__viewport')
    if (viewport) EmblaCarousel(viewport, { align: 'start', loop: false })
  })
})

// -----------------------------------------------------------------------------
// Executive Dashboard (Filament): Browser event listener for chart refresh
// Safe: no IIFE, no ASI pitfalls, idempotent.
// -----------------------------------------------------------------------------
if (!window.__execDashListenerRegistered) {
  window.__execDashListenerRegistered = true;

  window.addEventListener('dashboard-updated', (e) => {
    console.log('[dashboard-updated] fired', e.detail);
    window.__execDash = e.detail?.dashboardData ?? null;

    // allow echarts to resize after Livewire DOM changes
    window.dispatchEvent(new Event('resize'));
  });

  // Filament uses SPA-like navigation; keep visibility
  document.addEventListener('livewire:navigated', () => {
    console.log('[livewire:navigated] execdash listener still active');
  });
}
