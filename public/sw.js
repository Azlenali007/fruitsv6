/**
 * Progressive Web App Service Worker
 * N.A Fresh Fruits & Coconuts
 * 
 * Implements:
 * 1. Cache-first for static assets (icons, images, local fonts)
 * 2. Network-first for storefront navigation with offline fallback
 * 3. Strict bypass for checkout, account, admin, and authentication pages (zero sensitive data caching)
 * 4. Web Push Notifications handler with rich media and click routing
 * 5. Safe service worker update lifecycle
 */

const CACHE_NAME = 'na-fruits-v1.3';
const OFFLINE_FALLBACK_URL = '/offline.html';

// Assets to precache immediately on install
const PRECACHE_ASSETS = [
    '/',
    '/offline.html',
    '/manifest.webmanifest',
    '/assets/images/icons/icon-192.png',
    '/assets/images/icons/icon-512.png',
    '/assets/images/icons/badge-72.png',
    '/assets/images/icons/apple-touch-icon.png',
    '/assets/images/hero-coconut.png',
    '/assets/images/product-main.png'
];

// Sensitive routes that must NEVER be cached by the service worker
const SENSITIVE_PREFIXES = [
    '/checkout',
    '/admin',
    '/account',
    '/login',
    '/register',
    '/api',
    '/webhooks',
    '/install',
    '/confirmation'
];
const EXCLUDED_PATHS = SENSITIVE_PREFIXES;

// 1. Install Event: Precache essential shell & offline fallback
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[SW] Precache non-critical warning:', err);
            });
        })
    );
    self.skipWaiting();
});

// 2. Activate Event: Clean up old versioned caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// 3. Fetch Event: Routing & Cache Strategy
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle GET requests originating from same origin
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Never cache sensitive dynamic paths
    const isSensitive = SENSITIVE_PREFIXES.some(prefix => 
        url.pathname === prefix || url.pathname.startsWith(prefix + '/')
    );
    if (isSensitive) {
        return; // Let browser handle network request natively
    }

    // A. Navigation / Page Requests: Network-first with offline fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .catch(() => {
                    return caches.match(request).then((cachedResponse) => {
                        return cachedResponse || caches.match(OFFLINE_FALLBACK_URL);
                    });
                })
        );
        return;
    }

    // B. Static Images and Assets: Cache-first, network fallback
    if (url.pathname.startsWith('/assets/') || url.pathname.endsWith('.png') || url.pathname.endsWith('.jpg') || url.pathname.endsWith('.svg') || url.pathname.endsWith('.webmanifest')) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const copy = networkResponse.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
                    }
                    return networkResponse;
                }).catch(() => {
                    // Fallback transparent 1px if image unavailable offline
                    return new Response('', { status: 408 });
                });
            })
        );
        return;
    }

    // Default: Network with cache fallback
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});

// 4. Web Push Notification Event: Receive and display push
self.addEventListener('push', (event) => {
    let data = {
        title: 'N.A Fresh Fruits & Coconuts',
        body: 'Fresh farm updates available!',
        icon: '/assets/images/icons/icon-192.png',
        badge: '/assets/images/icons/badge-72.png',
        data: { url: '/' }
    };

    if (event.data) {
        try {
            const parsed = event.data.json();
            data = Object.assign(data, parsed);
        } catch (e) {
            data.body = event.data.text() || data.body;
        }
    }

    const options = {
        body: data.body,
        icon: data.icon || '/assets/images/icons/icon-192.png',
        badge: data.badge || '/assets/images/icons/badge-72.png',
        image: data.image || null,
        tag: data.tag || 'na-fruits-notice',
        renotify: true,
        requireInteraction: false,
        vibrate: [100, 50, 100],
        data: data.data || { url: '/' },
        actions: [
            { action: 'open', title: 'View Details' },
            { action: 'close', title: 'Dismiss' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// 5. Notification Click Event: Navigate to destination
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'close') {
        return;
    }

    const targetUrl = (event.notification.data && event.notification.data.url) ? event.notification.data.url : '/';
    const absoluteUrl = new URL(targetUrl, self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // If already open in a tab, focus and navigate
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if ('focus' in client) {
                    client.focus();
                    if ('navigate' in client && client.url !== absoluteUrl) {
                        return client.navigate(absoluteUrl);
                    }
                    return client;
                }
            }
            // Otherwise open a new window
            if (clients.openWindow) {
                return clients.openWindow(absoluteUrl);
            }
        })
    );
});

// 6. Listen for immediate activation message
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
