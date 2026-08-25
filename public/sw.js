/* Akuru 1B.6 service worker — cache the shell, stay offline-safe. */
const CACHE = 'akuru-shell-v1';
const PRECACHE = [
    '/offline.html',
    '/manifest.webmanifest',
    '/images/pwa-192.png',
    '/images/pwa-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)),
        )).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match('/offline.html')),
        );
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) {
                return cached;
            }

            return fetch(event.request).then((response) => {
                if (response.ok && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/fonts/') || url.pathname.startsWith('/images/'))) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(event.request, copy));
                }

                return response;
            });
        }),
    );
});
