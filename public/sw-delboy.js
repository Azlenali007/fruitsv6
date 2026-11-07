/**
 * Delivery Boy Service Worker (sw-delboy.js)
 * N.A Fresh Fruits & Coconuts
 *
 * Implements strict security discipline:
 * - Caches ONLY safe static assets and offline fallback screen.
 * - NEVER caches customer personal details, payment data, OTPs, or active orders.
 * - All dynamic and API requests are Network-Only.
 */

const CACHE_NAME = 'na-delboy-static-v1';
const STATIC_ASSETS = [
    '/delboy-offline.html',
    '/manifest-delboy.webmanifest',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
    '/assets/icons/icon-maskable-512.png'
];

// Install Event: Pre-cache safe static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Activate Event: Clean up stale caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event: Secure routing rules
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // 1. Non-GET requests (POST, PUT, DELETE) - Always Network Only
    if (request.method !== 'GET') {
        return;
    }

    // 2. Sensitive endpoints: NEVER cache customer orders, APIs, GPS pings, auth, or OTPs
    const isSensitive = 
        url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/admin/') ||
        url.pathname.includes('/order') ||
        url.pathname.includes('/accept') ||
        url.pathname.includes('/reject') ||
        url.pathname.includes('/workflow') ||
        url.pathname.includes('/otp') ||
        url.pathname.includes('/payment') ||
        url.pathname.includes('/qr') ||
        url.pathname.includes('/gps') ||
        url.pathname.includes('/login') ||
        url.pathname.includes('/logout') ||
        url.search.includes('token') ||
        url.search.includes('otp');

    if (isSensitive) {
        event.respondWith(
            fetch(request).catch(() => {
                // If it is a full page navigation that failed, show the offline screen
                if (request.mode === 'navigate') {
                    return caches.match('/delboy-offline.html');
                }
                return new Response(JSON.stringify({ error: 'Offline. Network connection required.' }), {
                    status: 503,
                    headers: { 'Content-Type': 'application/json' }
                });
            })
        );
        return;
    }

    // 3. Static safe assets (images, fonts, manifest) - Stale While Revalidate or Cache First
    const isStaticAsset = 
        url.pathname.startsWith('/assets/') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.ico') ||
        url.pathname.endsWith('.webmanifest');

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    // Fetch in background to revalidate
                    fetch(request).then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, networkResponse));
                        }
                    }).catch(() => {});
                    return cachedResponse;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // 4. Navigation requests (HTML pages) - Network First with fallback to offline page
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match('/delboy-offline.html');
            })
        );
        return;
    }

    // Default: Network fetch
    event.respondWith(fetch(request));
});
