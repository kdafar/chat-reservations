import { defineConfig } from 'vite'
import { resolve } from 'node:path'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  resolve: {
    alias: {
      // Ziggy ships its JS inside the composer package (not npm) — alias the
      // bare `ziggy-js` import to the vendored dist so Vite can resolve it.
      'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
    },
  },
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/filament-dashboard.css',
        'resources/js/clinic/landing.jsx',
        'resources/js/filament-dashboard.js',
        // v2 UI (Inertia + Vue) — parallel to existing Filament admin
        'resources/js/v2/app.js',
        'resources/css/v2.css',
      ],
      refresh: true,
    }),
    tailwindcss(),
    react(),
    // Skip .jsx so Vue plugin doesn't try to parse React files
    vue({ include: [/\.vue$/] }),

    // VitePWA({
    //   registerType: 'autoUpdate',
    //   devOptions: { enabled: false },
    //   manifest: {
    //     name: 'Zad Hub',
    //     short_name: 'Zad',
    //     start_url: '/',
    //     display: 'standalone',
    //     background_color: '#ffffff',
    //     theme_color: '#f97316',
    //     icons: [],
    //   },
    // }),
  ],
})
