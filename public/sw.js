const CACHE_NAME = 'playersaloons-v3';
const STATIC_ASSETS = [
  '/playersaloons_logo.webp',
  '/icon-192.png',
  '/icon-512.png',
  '/manifest.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(cacheNames => Promise.all(
        cacheNames
          .filter(cacheName => cacheName !== CACHE_NAME)
          .map(cacheName => caches.delete(cacheName))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const requestUrl = new URL(event.request.url);
  if (requestUrl.origin !== self.location.origin) return;
  if (requestUrl.pathname.startsWith('/livewire/') || requestUrl.pathname.startsWith('/api/')) return;

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
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }

        return fetch(event.request).then(networkResponse => {
          if (!networkResponse || networkResponse.status !== 200) {
            return networkResponse;
          }

          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseToCache));

          return networkResponse;
        });
      })
  );
});
