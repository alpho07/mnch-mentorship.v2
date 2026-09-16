import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig(({ mode }) => ({
    plugins: [
        react(),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['icon-192.png', 'icon-512.png', 'apple-touch-icon.png'],
            manifest: {
                name: 'MNCH Assessments',
                short_name: 'MNCH',
                description: 'MNCH Kenya facility assessments and mentorship tracking',
                theme_color: '#0097A7',
                background_color: '#ffffff',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/',
                scope: '/',
                icons: [
                    {
                        src: 'icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: 'icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable',
                    },
                ],
            },
            workbox: {
                // Don't cache API calls — the app's own offline layer handles that
                navigateFallback: 'index.html',
                runtimeCaching: [],
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
            },
        }),
    ],

    server: {
        // Dev proxy: only active during `npm run dev` in a browser.
        // Has no effect on Capacitor builds — those use the baked-in BASE_URL.
        proxy: {
            '/api': {
                target: 'https://mnchkenyamentorship.org',
                changeOrigin: true,
                secure: true,
            },
        },
    },
}))
