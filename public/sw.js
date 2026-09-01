const CACHE_NAME = 'playersaloons-68ee119f5151';
const STATIC_ASSETS = [
    '/playersaloons_logo.webp',
    '/icon-192.png',
    '/icon-512.png',
    '/manifest.json',
];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)));
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(cacheNames => Promise.all(
                cacheNames
                    .filter(cacheName => cacheName.startsWith('playersaloons-') && cacheName !== CACHE_NAME)
                    .map(cacheName => caches.delete(cacheName)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', event => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;

    const requestUrl = new URL(event.request.url);
    if (requestUrl.origin !== self.location.origin) return;
    if (requestUrl.pathname.startsWith('/livewire/') || requestUrl.pathname.startsWith('/api/')) return;

    // Never cache HTML so authentication state and server-rendered releases are fresh.
    if (event.request.mode === 'navigate' || event.request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(fetch(event.request));
        return;
    }

    const isCacheableStaticAsset = STATIC_ASSETS.includes(requestUrl.pathname)
        || requestUrl.pathname.startsWith('/build/')
        || requestUrl.pathname.startsWith('/storage/')
        || /\.(?:css|js|woff2?|png|jpg|jpeg|webp|svg|ico)$/.test(requestUrl.pathname);

    if (!isCacheableStaticAsset) return;

    event.respondWith(
        caches.match(event.request).then(response => response || fetch(event.request).then(networkResponse => {
            if (!networkResponse || networkResponse.status !== 200) return networkResponse;

            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseToCache));

            return networkResponse;
        })),
    );
});
