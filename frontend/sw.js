 jconst CACHE_NAME = 'denis-store-v2'; // Bumped version to force update
const STATIC_ASSETS = [
    './assets/css/style.css',
    './assets/js/app.js',
    './assets/js/pos.js',
    './assets/vendor/bootstrap/bootstrap.min.css',
    './assets/vendor/bootstrap/bootstrap.bundle.min.js',
    './assets/vendor/fontawesome/css/all.min.css',
    './assets/vendor/sweetalert2/sweetalert2.all.min.js',
    './assets/vendor/chart.js/chart.umd.js',
    './manifest.json',
    './assets/img/icon-192.png',
    './assets/img/icon-512.png'
];

// Install: Cache only static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[Service Worker] Caching static files');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting()) // Activate immediately
    );
});

// Activate: Cleanup old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(keyList.map((key) => {
                if (key !== CACHE_NAME) {
                    console.log('[Service Worker] Removing old cache', key);
                    return caches.delete(key);
                }
            }));
        })
    );
    return self.clients.claim();
});

// Fetch Strategy
self.addEventListener('fetch', (event) => {
    // 1. Non-GET requests (POST) -> Network Only
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    // 2. HTML Pages (PHP) -> Network First, Fallback to Cache
    // This ensures we always get a fresh CSRF token if online
    if (event.request.headers.get('accept').includes('text/html') || url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(event.request)
                .then((networkResponse) => {
                    // Cache the fresh copy
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
                    return networkResponse;
                })
                .catch(() => {
                    // Fallback to cache if offline
                    return caches.match(event.request);
                })
        );
        return;
    }

    // 3. Static Assets -> Cache First, Fallback to Network
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }
            return fetch(event.request).then((networkResponse) => {
                // Optionally cache new static assets found
                return networkResponse;
            });
        })
    );
});
