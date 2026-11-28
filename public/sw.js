/*
 * Akuru service worker: cache the shell, stay offline-safe.
 *
 * - Pages are never cached: online they come from the server, offline the
 *   offline page stands in. A deploy therefore shows on the next open.
 * - /build/ files carry a hash in their name, so they are kept as they are;
 *   the ones a deploy has retired are cleared on the page's `prune` message
 *   (PW1, STATUS §5uz). The build's own manifest is never cached: the page
 *   reads it to see that a new version is out.
 * - Images and fonts are served from the cache and refreshed behind it, so a
 *   file replaced under the same name reaches an installed app.
 */
const CACHE = 'akuru-shell-v2';
const BUILD_MANIFEST = '/build/manifest.json';
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

/* Drop every cached /build/ file the current build no longer names. */
async function prune() {
    const response = await fetch(BUILD_MANIFEST, { cache: 'no-store' });
    if (!response.ok) {
        return;
    }
    const keep = new Set();
    for (const entry of Object.values(await response.json())) {
        for (const file of [entry.file, ...(entry.css || []), ...(entry.assets || [])]) {
            if (file) {
                keep.add(`/build/${file}`);
            }
        }
    }
    if (keep.size === 0) {
        return;
    }
    const cache = await caches.open(CACHE);
    for (const request of await cache.keys()) {
        const path = new URL(request.url).pathname;
        if (path.startsWith('/build/') && !keep.has(path)) {
            await cache.delete(request);
        }
    }
}

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'prune') {
        event.waitUntil(prune().catch(() => {}));
    }
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin || url.pathname === BUILD_MANIFEST) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match('/offline.html')),
        );
        return;
    }

    const hashed = url.pathname.startsWith('/build/');
    const refreshed = url.pathname.startsWith('/fonts/') || url.pathname.startsWith('/images/');

    event.respondWith(
        caches.match(event.request).then((cached) => {
            const fresh = () => fetch(event.request).then((response) => {
                if (response.ok && (hashed || refreshed)) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(event.request, copy));
                }

                return response;
            });

            if (cached) {
                if (refreshed) {
                    event.waitUntil(fresh().catch(() => {}));
                }

                return cached;
            }

            return fresh();
        }),
    );
});
