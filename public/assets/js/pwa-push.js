/**
 * Progressive Web App & Web Push Notification Client Manager
 * N.A Fresh Fruits & Coconuts
 * 
 * Supports:
 * - Android & Desktop Chrome / Edge / Firefox
 * - iOS / iPadOS 16.4+ Safari (with Home Screen installation detection and guidance)
 * - Safe VAPID subscription and server registration
 */

(function () {
    'use strict';

    // Utility: Convert base64url string to Uint8Array for PushManager
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Platform detection helpers
    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;

    window.NA_PWA = {
        swRegistration: null,
        deferredPrompt: null,
        isIos: isIos,
        isStandalone: isStandalone,
        vapidPublicKey: null,
        isSubscribed: false,

        init: async function () {
            if (!('serviceWorker' in navigator)) {
                console.log('[PWA] Service Workers not supported in this browser.');
                return;
            }

            try {
                // Register Service Worker
                const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                this.swRegistration = reg;
                console.log('[PWA] Service Worker registered with scope:', reg.scope);

                // Listen for updates
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('[PWA] New update available. Reloading soon.');
                        }
                    });
                });

                // Check active push subscription
                if ('pushManager' in reg) {
                    const sub = await reg.pushManager.getSubscription();
                    this.isSubscribed = !!sub;
                    this.updateUIState();
                }

                // Intercept Android beforeinstallprompt
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    this.deferredPrompt = e;
                    this.showInstallBanner();
                });

                // Detect appinstalled
                window.addEventListener('appinstalled', () => {
                    console.log('[PWA] Application successfully installed.');
                    this.hideInstallBanner();
                });

            } catch (err) {
                console.error('[PWA] Service Worker registration failed:', err);
            }
        },

        // Request Web Push Notification permission & subscribe device
        subscribeDevice: async function (userType = 'customer') {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                alert('Web Push Notifications are not supported by your current browser.');
                return { success: false, error: 'Push not supported' };
            }

            // iOS 16.4+ Requirement: App MUST be added to Home Screen first
            if (this.isIos && !this.isStandalone) {
                this.showIosInstallModal();
                return { success: false, requires_ios_install: true };
            }

            try {
                // Request user permission
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    alert('Notification permission was not granted. Please allow notifications in your browser or device settings.');
                    this.updateUIState();
                    return { success: false, error: 'Permission denied' };
                }

                // Fetch VAPID Public Key from server
                if (!this.vapidPublicKey) {
                    const keyRes = await fetch('/api/push/vapid-public-key');
                    const keyData = await keyRes.json();
                    if (!keyData.success || !keyData.publicKey) {
                        throw new Error(keyData.error || 'Failed to retrieve VAPID key');
                    }
                    this.vapidPublicKey = keyData.publicKey;
                }

                const appServerKey = urlBase64ToUint8Array(this.vapidPublicKey);
                const reg = this.swRegistration || await navigator.serviceWorker.ready;

                let subscription = await reg.pushManager.getSubscription();
                if (!subscription) {
                    subscription = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: appServerKey
                    });
                }

                const subJson = subscription.toJSON();
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                // Register subscription on backend
                const response = await fetch('/api/push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        endpoint: subJson.endpoint,
                        p256dh: subJson.keys?.p256dh,
                        auth: subJson.keys?.auth,
                        user_type: userType
                    })
                });

                const resData = await response.json();
                if (resData.success) {
                    this.isSubscribed = true;
                    this.updateUIState();
                    return { success: true };
                } else {
                    throw new Error(resData.error || 'Backend failed to save subscription');
                }
            } catch (err) {
                console.error('[PWA] Subscription error:', err);
                alert('Error subscribing to notifications: ' + err.message);
                return { success: false, error: err.message };
            }
        },

        // Unsubscribe device
        unsubscribeDevice: async function () {
            try {
                const reg = this.swRegistration || await navigator.serviceWorker.ready;
                const subscription = await reg.pushManager.getSubscription();
                if (subscription) {
                    const endpoint = subscription.endpoint;
                    await subscription.unsubscribe();
                    
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    await fetch('/api/push/unsubscribe', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ endpoint: endpoint })
                    });
                }
                this.isSubscribed = false;
                this.updateUIState();
                return { success: true };
            } catch (err) {
                console.error('[PWA] Unsubscribe error:', err);
                return { success: false, error: err.message };
            }
        },

        // Prompt user to install native PWA on Android/Chrome
        promptInstall: async function () {
            if (this.deferredPrompt) {
                this.deferredPrompt.prompt();
                const choice = await this.deferredPrompt.userChoice;
                if (choice.outcome === 'accepted') {
                    console.log('[PWA] User accepted installation prompt');
                }
                this.deferredPrompt = null;
                this.hideInstallBanner();
            } else if (this.isIos) {
                this.showIosInstallModal();
            } else {
                alert('To install the app, tap your browser menu (⋮) and select "Add to Home screen" or "Install App".');
            }
        },

        showInstallBanner: function () {
            const banner = document.getElementById('pwa-install-banner');
            if (banner && !this.isStandalone) {
                banner.classList.remove('hidden');
            }
        },

        hideInstallBanner: function () {
            const banner = document.getElementById('pwa-install-banner');
            if (banner) {
                banner.classList.add('hidden');
            }
        },

        showIosInstallModal: function () {
            const modal = document.getElementById('ios-install-modal');
            if (modal) {
                modal.classList.remove('hidden');
            } else {
                alert("iPhone / iPad Installation Guide:\n\n1. Tap the Share button (box with upward arrow) at the bottom of Safari.\n2. Scroll down and tap 'Add to Home Screen'.\n3. Open the N.A Fruits app from your Home Screen to enable instant order notifications!");
            }
        },

        hideIosInstallModal: function () {
            const modal = document.getElementById('ios-install-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        },

        updateUIState: function () {
            const statusEl = document.getElementById('pwa-push-status');
            const subBtn = document.getElementById('pwa-push-toggle-btn');
            if (statusEl) {
                statusEl.textContent = this.isSubscribed ? 'Subscribed & Active' : 'Not Subscribed';
                statusEl.className = this.isSubscribed ? 'text-emerald-700 font-bold' : 'text-gray-500';
            }
            if (subBtn) {
                subBtn.textContent = this.isSubscribed ? 'Disable Push on this Device' : 'Enable Instant Push Notifications';
            }
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.NA_PWA.init());
    } else {
        window.NA_PWA.init();
    }
})();
