import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ mode }) => ({
    plugins: [react()],

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
