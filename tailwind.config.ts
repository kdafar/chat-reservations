import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/css/app.css','resources/js/app.js','resources/js/filament-dashboard.js', ], refresh: true }),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      manifest: {
        name: 'Zad Hub',
        short_name: 'Zad',
        start_url: '/',
        display: 'standalone',
        background_color: '#ffffff',
        theme_color: '#f97316',
        icons: [
          // add your /public/icons set later
        ],
      },
    }),
  ],
})
