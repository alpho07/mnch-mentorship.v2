// Admin panel service worker — installability only. No HTML or API caching.
// Scoped to /admin/ at registration time (see AdminPanelProvider render hook).

const CACHE_NAME = 'mnch-admin-static-v1';

const STATIC_ASSETS = [
    '/css/filament-admin-theme.css',
    '/manifest.webmanifest',
    '/app-icons/admin-icon-192.png',
    '/app-icons/admin-icon-512.png',
    '/app-icons/admin-icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// Cache-first for the known static assets only; everything else (pages, API calls)
// goes straight to the network — this worker never serves stale admin data.
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || !STATIC_ASSETS.includes(url.pathname)) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => cached || fetch(event.request))
    );
});
