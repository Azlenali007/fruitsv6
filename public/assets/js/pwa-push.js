/**
 * Progressive Web App & Centralized Web Push Notification Client Manager
 * N.A Fresh Fruits & Coconuts
 * 
 * Supports:
 * - Real Admin device enrollment with live server verification
 * - Customer post-login / post-registration "Stay Updated!" prompt
 * - Detection and handling of already enrolled devices (updates subscription without duplicates)
 * - Single centralized permission management system
 * - iOS 16.4+ Safari Add to Home Screen detection and guidance
 * - Safe VAPID key subscription and CSRF-protected backend registration
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

    // Helper: Convert ArrayBuffer to base64url
    function arrayBufferToBase64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/, '');
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
        deviceRole: null, // 'admin' | 'customer' | 'guest'
        currentEndpoint: null,

        getCsrfToken: function () {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },

        isSupported: function () {
            return ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
        },

        init: async function () {
            if (!this.isSupported()) {
                console.log('[PWA] Service Worker or Push Notifications not supported in this browser.');
                this.updateUIState();
                return;
            }

            try {
                // Register Service Worker
                const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                this.swRegistration = reg;
                console.log('[PWA] Service Worker registered:', reg.scope);

                // Listen for service worker updates
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    if (newWorker) {
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                console.log('[PWA] New version ready.');
                            }
                        });
                    }
                });

                // Check existing subscription and sync with server
                await this.syncDeviceStatus();

                // Intercept Android beforeinstallprompt
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    this.deferredPrompt = e;
                    this.showInstallBanner();
                });

                // Detect appinstalled
                window.addEventListener('appinstalled', () => {
                    console.log('[PWA] App successfully installed.');
                    this.hideInstallBanner();
                });

                // Check if post-login / post-registration notification prompt should be displayed
                this.checkPostAuthPrompt();

            } catch (err) {
                console.error('[PWA] Init error:', err);
                this.updateUIState();
            }
        },

        // Fetch VAPID Public Key from server (cached)
        getVapidKey: async function () {
            if (this.vapidPublicKey) {
                return this.vapidPublicKey;
            }
            const res = await fetch('/api/push/vapid-public-key');
            const data = await res.json();
            if (!data.success || !data.publicKey) {
                throw new Error(data.error || 'Failed to retrieve VAPID public key from server');
            }
            this.vapidPublicKey = data.publicKey;
            return this.vapidPublicKey;
        },

        // Query server to verify if current device is registered and what role it holds
        syncDeviceStatus: async function () {
            if (!this.isSupported()) {
                this.updateUIState();
                return;
            }

            try {
                const reg = this.swRegistration || await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (!sub) {
                    this.isSubscribed = false;
                    this.deviceRole = null;
                    this.currentEndpoint = null;
                    this.updateUIState();
                    return;
                }

                this.currentEndpoint = sub.endpoint;

                // Verify with server database
                const res = await fetch('/api/push/device-status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ endpoint: sub.endpoint })
                });
                const data = await res.json();

                if (data.success && data.enrolled) {
                    this.isSubscribed = true;
                    this.deviceRole = data.user_type;
                } else {
                    // Subscription exists in browser but not registered on server
                    this.isSubscribed = false;
                    this.deviceRole = null;
                }

                this.updateUIState();
            } catch (err) {
                console.warn('[PWA] syncDeviceStatus error:', err);
                this.updateUIState();
            }
        },

        // Subscribe device (Customer or Admin) with real server verification
        subscribeDevice: async function (userType = 'customer') {
            if (!this.isSupported()) {
                alert('Web Push Notifications are not supported by your current browser.');
                return { success: false, error: 'Push not supported' };
            }

            // Check HTTPS / Secure Context (Allow localhost / 127.0.0.1 for local dev)
            const isLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
            if (!window.isSecureContext && !isLocal) {
                alert('Push Notifications require a secure HTTPS connection.');
                return { success: false, error: 'HTTPS required' };
            }

            // iOS 16.4+ Requirement: App MUST be added to Home Screen first
            if (this.isIos && !this.isStandalone) {
                this.showIosInstallModal();
                return { success: false, requires_ios_install: true };
            }

            // If permission was already denied in browser, don't repeatedly prompt
            if (Notification.permission === 'denied') {
                this.showBlockedPermissionModal();
                return { success: false, error: 'Permission denied in browser settings' };
            }

            try {
                // Request user permission explicitly
                let perm = Notification.permission;
                if (perm === 'default') {
                    perm = await Notification.requestPermission();
                }

                if (perm !== 'granted') {
                    this.updateUIState();
                    this.showBlockedPermissionModal();
                    return { success: false, error: 'Permission not granted' };
                }

                // Retrieve VAPID key and convert
                const vapidKey = await this.getVapidKey();
                const appServerKey = urlBase64ToUint8Array(vapidKey);

                const reg = this.swRegistration || await navigator.serviceWorker.ready;

                // Handle existing subscription cleanly (avoid InvalidStateError if key changed)
                let subscription = await reg.pushManager.getSubscription();
                if (subscription) {
                    try {
                        const rawKey = subscription.options?.applicationServerKey;
                        if (rawKey) {
                            const curBytes = new Uint8Array(rawKey);
                            let match = (curBytes.length === appServerKey.length);
                            if (match) {
                                for (let i = 0; i < curBytes.length; i++) {
                                    if (curBytes[i] !== appServerKey[i]) { match = false; break; }
                                }
                            }
                            if (!match) {
                                console.log('[PWA] Unsubscribing old VAPID subscription before renewal');
                                await subscription.unsubscribe();
                                subscription = null;
                            }
                        }
                    } catch (e) {
                        // ignore and subscribe
                    }
                }

                if (!subscription) {
                    subscription = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: appServerKey
                    });
                }

                const subJson = subscription.toJSON();
                let p256dh = subJson.keys?.p256dh;
                let auth = subJson.keys?.auth;

                // Fallback extraction if toJSON().keys is missing
                if (!p256dh && subscription.getKey) {
                    const k = subscription.getKey('p256dh');
                    if (k) p256dh = arrayBufferToBase64Url(k);
                }
                if (!auth && subscription.getKey) {
                    const a = subscription.getKey('auth');
                    if (a) auth = arrayBufferToBase64Url(a);
                }

                if (!p256dh || !auth) {
                    throw new Error('Browser did not provide valid encryption keys.');
                }

                // Send subscription to PHP backend
                const response = await fetch('/api/push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.getCsrfToken()
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        p256dh: p256dh,
                        auth: auth,
                        user_type: userType,
                        csrf_token: this.getCsrfToken()
                    })
                });

                const resData = await response.json();

                if (resData.success && resData.verified) {
                    this.isSubscribed = true;
                    this.deviceRole = userType;
                    this.currentEndpoint = subscription.endpoint;
                    this.updateUIState();
                    return { success: true, subscription_id: resData.subscription_id };
                } else {
                    throw new Error(resData.error || 'Server could not verify device subscription in database');
                }
            } catch (err) {
                console.error('[PWA] Enrollment error:', err);
                this.updateUIState();
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

                    await fetch('/api/push/unsubscribe', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': this.getCsrfToken()
                        },
                        body: JSON.stringify({
                            endpoint: endpoint,
                            csrf_token: this.getCsrfToken()
                        })
                    });
                }
                this.isSubscribed = false;
                this.deviceRole = null;
                this.currentEndpoint = null;
                this.updateUIState();
                return { success: true };
            } catch (err) {
                console.error('[PWA] Unsubscribe error:', err);
                return { success: false, error: err.message };
            }
        },

        // Test alert on current device
        testCurrentDevice: async function (role = 'admin') {
            if (!this.currentEndpoint) {
                const reg = this.swRegistration || await navigator.serviceWorker.ready;
                const sub = await reg?.pushManager.getSubscription();
                if (sub) {
                    this.currentEndpoint = sub.endpoint;
                }
            }

            if (!this.currentEndpoint) {
                alert('No push subscription found on this device. Please enroll first.');
                return { success: false };
            }

            try {
                const res = await fetch('/api/push/test-device', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.getCsrfToken()
                    },
                    body: JSON.stringify({
                        endpoint: this.currentEndpoint,
                        role: role,
                        csrf_token: this.getCsrfToken()
                    })
                });
                const data = await res.json();
                if (data.success) {
                    alert('✓ ' + (data.message || 'Test push alert sent to this device!'));
                    return { success: true };
                } else {
                    alert('✕ ' + (data.error || 'Test notification failed'));
                    return { success: false, error: data.error };
                }
            } catch (e) {
                alert('Error: ' + e.message);
                return { success: false, error: e.message };
            }
        },

        // Alias for testCurrentDevice
        sendTestToCurrentDevice: function (role = 'customer') {
            return this.testCurrentDevice(role);
        },

        // Check if post-auth (login/register) custom popup should appear
        checkPostAuthPrompt: function () {
            const modal = document.getElementById('pwa-auth-notif-modal');
            if (!modal) return;

            const isPostAuth = modal.dataset.postAuth === 'true';
            if (!isPostAuth) return;

            // If already dismissed in this session, do not show repeatedly
            if (sessionStorage.getItem('na_push_prompt_dismissed') === '1') {
                return;
            }

            // If user already granted permission AND has active subscription, do not show
            if (Notification.permission === 'granted' && this.isSubscribed) {
                return;
            }

            // If permission was already permanently denied in browser, do not show native prompt repeatedly
            if (Notification.permission === 'denied') {
                return;
            }

            // Show custom popup modal
            setTimeout(() => {
                modal.classList.remove('hidden');
            }, 600);
        },

        // Post-auth "Allow Notifications" handler
        handleAuthPromptAllow: async function () {
            const btn = document.getElementById('pwa-auth-allow-btn');
            const txt = document.getElementById('pwa-auth-allow-text');
            const spin = document.getElementById('pwa-auth-allow-spinner');
            const fb = document.getElementById('pwa-auth-prompt-feedback');
            const modal = document.getElementById('pwa-auth-notif-modal');

            if (txt) txt.textContent = 'Enrolling...';
            if (spin) spin.classList.remove('hidden');
            if (btn) btn.disabled = true;
            if (fb) fb.classList.add('hidden');

            const result = await this.subscribeDevice('customer');

            if (spin) spin.classList.add('hidden');
            if (btn) btn.disabled = false;

            if (result.success) {
                if (txt) txt.textContent = '✓ Subscribed!';
                if (fb) {
                    fb.textContent = '✓ Notifications enabled! You will receive instant order updates.';
                    fb.className = 'my-3 p-2.5 rounded-xl text-xs bg-emerald-50 text-emerald-800 border border-emerald-200 block';
                    fb.classList.remove('hidden');
                }
                setTimeout(() => {
                    if (modal) modal.classList.add('hidden');
                }, 1400);
            } else {
                if (txt) txt.textContent = 'Retry Permission';
                if (fb) {
                    fb.textContent = '✕ ' + (result.error || 'Failed to enable notifications. Please try again.');
                    fb.className = 'my-3 p-2.5 rounded-xl text-xs bg-red-50 text-red-800 border border-red-200 block';
                    fb.classList.remove('hidden');
                }
            }
        },

        // Post-auth "Not Now" handler
        handleAuthPromptDismiss: function () {
            const modal = document.getElementById('pwa-auth-notif-modal');
            if (modal) modal.classList.add('hidden');
            // Dismiss for this session only; can be offered again on later login
            sessionStorage.setItem('na_push_prompt_dismissed', '1');
        },

        // Open centralized notification center modal (for customer or admin)
        openNotificationCenter: function (role = 'customer') {
            const modal = document.getElementById('pwa-notification-center-modal') || document.getElementById('pwa-center-modal');
            if (modal) {
                this.updateCenterModalState();
                modal.classList.remove('hidden');
            } else {
                // If modal not on page, perform direct subscription
                this.subscribeDevice(role);
            }
        },

        closeNotificationCenter: function () {
            const modal = document.getElementById('pwa-notification-center-modal') || document.getElementById('pwa-center-modal');
            if (modal) modal.classList.add('hidden');
        },

        updateCenterModalState: function () {
            const statusBadge = document.getElementById('pwa-center-status-badge');
            const toggleBtn = document.getElementById('pwa-center-toggle-btn') || document.getElementById('pwa-center-enrol-btn');
            const testBtn = document.getElementById('pwa-center-test-btn');
            const helpBox = document.getElementById('pwa-center-blocked-help') || document.getElementById('pwa-auth-blocked-help');

            const perm = ('Notification' in window) ? Notification.permission : 'unsupported';

            if (statusBadge) {
                if (!this.isSupported()) {
                    statusBadge.textContent = 'Unsupported in this browser';
                    statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 inline-block';
                } else if (perm === 'denied') {
                    statusBadge.textContent = 'Blocked in Browser Settings';
                    statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 inline-block';
                } else if (this.isSubscribed) {
                    statusBadge.textContent = 'Active on this Device (' + (this.deviceRole || 'customer') + ')';
                    statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 inline-block';
                } else {
                    statusBadge.textContent = 'Not Enabled on this Device';
                    statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 inline-block';
                }
            }

            if (toggleBtn) {
                if (perm === 'denied') {
                    toggleBtn.textContent = 'Permissions Blocked — View Guide';
                    toggleBtn.className = 'w-full sm:w-auto bg-red-50 hover:bg-red-100 text-red-800 border border-red-200 font-bold py-2 px-3 rounded-xl text-xs transition cursor-pointer';
                    toggleBtn.onclick = () => this.showBlockedPermissionModal();
                } else if (this.isSubscribed) {
                    toggleBtn.textContent = 'Disable on this Device';
                    toggleBtn.className = 'w-full sm:w-auto bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs transition cursor-pointer';
                    toggleBtn.onclick = () => this.unsubscribeDevice();
                } else {
                    toggleBtn.textContent = 'Enable Notifications';
                    toggleBtn.className = 'w-full sm:w-auto bg-[#235d3a] hover:bg-[#1b4a2e] text-white font-bold py-2 px-3 rounded-xl text-xs transition cursor-pointer';
                    toggleBtn.onclick = () => this.subscribeDevice('customer');
                }
            }

            if (testBtn) {
                testBtn.style.display = this.isSubscribed ? 'inline-flex' : 'none';
            }

            if (helpBox) {
                helpBox.style.display = (perm === 'denied') ? 'block' : 'none';
            }
        },

        // Update Admin & General UI Elements
        updateUIState: function () {
            // Admin Device Enrollment Elements in /admin/notifications
            const adminStatusBadge = document.getElementById('admin-enroll-status-badge');
            const adminStatusText = document.getElementById('admin-enroll-status-text');
            const adminEnrollBtn = document.getElementById('admin-enroll-btn');
            const adminTestBtn = document.getElementById('admin-device-test-btn');
            const adminUnenrollBtn = document.getElementById('admin-unenroll-btn');

            const perm = ('Notification' in window) ? Notification.permission : 'unsupported';
            const isEnrolledAdmin = this.isSubscribed && this.deviceRole === 'admin';

            if (adminStatusBadge) {
                if (!this.isSupported()) {
                    adminStatusBadge.innerHTML = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700">Push Unsupported</span>';
                } else if (perm === 'denied') {
                    adminStatusBadge.innerHTML = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">Permission Blocked in Browser</span>';
                } else if (isEnrolledAdmin) {
                    adminStatusBadge.innerHTML = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span> Enrolled & Verified Active</span>';
                } else if (this.isSubscribed) {
                    adminStatusBadge.innerHTML = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-sky-100 text-sky-800">Subscribed (as ' + (this.deviceRole || 'customer') + ')</span>';
                } else {
                    adminStatusBadge.innerHTML = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Not Enrolled</span>';
                }
            }

            if (adminStatusText) {
                if (perm === 'denied') {
                    adminStatusText.textContent = 'Notifications are blocked in your browser site settings. Click "Unblock Guide" to enable.';
                } else if (isEnrolledAdmin) {
                    adminStatusText.textContent = 'This browser is enrolled and verified in the database. You will receive real-time order alerts directly on this screen.';
                } else {
                    adminStatusText.textContent = 'This device is not enrolled for admin alerts. Click "Enrol Current Device" to receive order notifications.';
                }
            }

            if (adminEnrollBtn) {
                if (isEnrolledAdmin) {
                    adminEnrollBtn.style.display = 'none';
                } else {
                    adminEnrollBtn.style.display = 'inline-flex';
                    adminEnrollBtn.textContent = (perm === 'denied') ? 'Unblock & Enrol' : 'Enrol Current Admin Device';
                }
            }

            if (adminTestBtn) {
                adminTestBtn.style.display = isEnrolledAdmin ? 'inline-flex' : 'none';
            }

            if (adminUnenrollBtn) {
                adminUnenrollBtn.style.display = isEnrolledAdmin ? 'inline-flex' : 'none';
            }

            // Update Center modal if open
            this.updateCenterModalState();
        },

        // Helper: Show blocked permission modal with instructions
        showBlockedPermissionModal: function () {
            const modal = document.getElementById('pwa-permission-blocked-modal');
            if (modal) {
                modal.classList.remove('hidden');
            } else {
                alert("Notifications are Blocked in Browser Settings:\n\n1. Click the padlock icon 🔒 in your browser address bar.\n2. Locate 'Notifications' and set to 'Allow'.\n3. Reload the page and click 'Enrol Device' again.");
            }
        },

        hideBlockedPermissionModal: function () {
            const modal = document.getElementById('pwa-permission-blocked-modal');
            if (modal) modal.classList.add('hidden');
        },

        // Prompt user to install native PWA on Android/Chrome
        promptInstall: async function () {
            if (this.deferredPrompt) {
                this.deferredPrompt.prompt();
                const choice = await this.deferredPrompt.userChoice;
                if (choice.outcome === 'accepted') {
                    console.log('[PWA] User accepted install prompt');
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
                alert("iPhone / iPad Installation Guide:\n\n1. Tap the Share button in Safari.\n2. Tap 'Add to Home Screen'.\n3. Launch N.A Fruits from your Home Screen to enable order notifications!");
            }
        },

        hideIosInstallModal: function () {
            const modal = document.getElementById('ios-install-modal');
            if (modal) modal.classList.add('hidden');
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.NA_PWA.init());
    } else {
        window.NA_PWA.init();
    }
})();
