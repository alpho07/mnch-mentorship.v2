// Public site service worker (scope "/"): makes the site installable and shows a friendly offline page.
// It never caches pages or API responses, so signed-in users never see stale or someone else's data.
// The admin panel keeps its own worker (sw.js, scope /admin/), which wins for /admin/ pages.

const CACHE = 'mnch-site-v1';
const OFFLINE_URL = '/offline';
const PRECACHE = [OFFLINE_URL, '/app-icons/site-icon-192.png', '/images/site/moh-logo.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/livewire') || url.pathname.startsWith('/api')) return;

    // Pages: network first; when offline show the offline page.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Static, versioned-by-name assets: serve from cache and refresh in the background.
    if (/^\/(images\/site|app-icons|build\/assets)\//.test(url.pathname)) {
        event.respondWith(
            caches.open(CACHE).then((cache) =>
                cache.match(req).then((hit) => {
                    const fresh = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
                    return hit || fresh;
                })
            )
        );
    }
});
