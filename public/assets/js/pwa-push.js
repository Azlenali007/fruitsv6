/**
 * Progressive Web App & Centralized Web Push Notification Client Manager
 * N.A Fresh Fruits & Coconuts
 * 
 * Implements:
 * - Deterministic 10-state finite state machine (FSM)
 * - Strict separation of Permission vs Browser Subscription vs Server Enrollment
 * - Sequential, self-healing subscription flow (RFC 8291 / RFC 8292)
 * - Safe VAPID key decoding and CSRF-protected backend registration
 * - Live diagnostics panel reporting for admin portal
 * - Post-login & post-registration customer prompt handling
 * - Clean device re-sync & repair capabilities
 */

(function () {
    'use strict';

    // Explicit Push States
    const PushState = {
        UNSUPPORTED: 'UNSUPPORTED',
        PERMISSION_NOT_REQUESTED: 'PERMISSION_NOT_REQUESTED',
        PERMISSION_DENIED: 'PERMISSION_DENIED',
        PERMISSION_GRANTED: 'PERMISSION_GRANTED',
        DEVICE_NOT_SUBSCRIBED: 'DEVICE_NOT_SUBSCRIBED',
        SUBSCRIPTION_PENDING: 'SUBSCRIPTION_PENDING',
        SUBSCRIPTION_SAVED: 'SUBSCRIPTION_SAVED',
        DEVICE_ENROLLED: 'DEVICE_ENROLLED',
        SUBSCRIPTION_EXPIRED: 'SUBSCRIPTION_EXPIRED',
        ENROLLMENT_FAILED: 'ENROLLMENT_FAILED'
    };

    // Utility: Convert base64url string to Uint8Array for PushManager
    function urlBase64ToUint8Array(base64String) {
        if (!base64String) return new Uint8Array(0);
        const cleanStr = String(base64String).trim();
        const padding = '='.repeat((4 - (cleanStr.length % 4)) % 4);
        const base64 = (cleanStr + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Utility: Convert ArrayBuffer to base64url
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

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;

    window.NA_PWA = {
        PushState: PushState,
        currentState: PushState.PERMISSION_NOT_REQUESTED,
        lastError: null,
        swRegistration: null,
        deferredPrompt: null,
        isIos: isIos,
        isStandalone: isStandalone,
        vapidPublicKey: null,
        isSubscribed: false,
        isServerVerified: false,
        deviceRole: null, // 'admin' | 'customer' | 'guest'
        currentEndpoint: null,
        subscriptionId: null,
        lastPushResult: null,

        getCsrfToken: function () {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="_csrf_token"]')?.value
                || '';
        },

        isSupported: function () {
            return ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
        },

        // Determine current true state based on browser permission, pushManager subscription, and server verification
        computeCurrentState: function () {
            if (!this.isSupported()) {
                return PushState.UNSUPPORTED;
            }

            const perm = Notification.permission;
            if (perm === 'denied') {
                return PushState.PERMISSION_DENIED;
            }

            if (perm === 'default') {
                return PushState.PERMISSION_NOT_REQUESTED;
            }

            // perm is 'granted'
            if (!this.currentEndpoint) {
                return PushState.PERMISSION_GRANTED;
            }

            if (!this.isServerVerified) {
                return PushState.DEVICE_NOT_SUBSCRIBED;
            }

            return PushState.DEVICE_ENROLLED;
        },

        setState: function (newState, errorDetail = null) {
            this.currentState = newState;
            if (errorDetail) {
                this.lastError = errorDetail;
            }
            console.log('[PWA Push FSM] State changed ->', newState, errorDetail ? `(${errorDetail})` : '');
            this.updateUIState();
        },

        init: async function () {
            if (!this.isSupported()) {
                console.log('[PWA] Service Worker or Push Notifications not supported in this browser.');
                this.setState(PushState.UNSUPPORTED);
                return;
            }

            try {
                // Ensure service worker is registered
                const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                this.swRegistration = reg;
                console.log('[PWA] Service Worker registered with scope:', reg.scope);

                // Listen for SW updates
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

                // Sync device status against active SW and database
                await this.syncDeviceStatus();

                // Android beforeinstallprompt
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    this.deferredPrompt = e;
                    this.showInstallBanner();
                });

                window.addEventListener('appinstalled', () => {
                    console.log('[PWA] App successfully installed.');
                    this.hideInstallBanner();
                });

                // Post auth prompt check
                this.checkPostAuthPrompt();

            } catch (err) {
                console.error('[PWA] Initialization error:', err);
                this.setState(this.computeCurrentState(), err.message);
            }
        },

        // Fetch and cache VAPID Public Key from server
        getVapidKey: async function () {
            if (this.vapidPublicKey) {
                return this.vapidPublicKey;
            }
            const res = await fetch('/api/push/vapid-public-key');
            if (!res.ok) {
                throw new Error(`Failed to fetch VAPID key (HTTP ${res.status})`);
            }
            const data = await res.json();
            if (!data.success || !data.publicKey) {
                throw new Error(data.error || 'Server did not return a valid VAPID public key');
            }
            this.vapidPublicKey = data.publicKey.trim();
            return this.vapidPublicKey;
        },

        // Sync local browser subscription with backend database
        syncDeviceStatus: async function () {
            if (!this.isSupported()) {
                this.setState(PushState.UNSUPPORTED);
                return;
            }

            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();

                if (!sub) {
                    this.currentEndpoint = null;
                    this.isSubscribed = false;
                    this.isServerVerified = false;
                    this.deviceRole = null;
                    this.subscriptionId = null;
                    this.setState(this.computeCurrentState());
                    return;
                }

                this.currentEndpoint = sub.endpoint;
                this.isSubscribed = true;

                // Query server for verification
                const res = await fetch('/api/push/device-status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ endpoint: sub.endpoint })
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.success && data.enrolled) {
                        this.isServerVerified = true;
                        this.deviceRole = data.user_type;
                        this.subscriptionId = data.subscription_id;
                        this.setState(PushState.DEVICE_ENROLLED);
                        return;
                    }
                }

                // Browser has a subscription token, but not yet verified in backend DB
                this.isServerVerified = false;
                this.setState(PushState.DEVICE_NOT_SUBSCRIBED);

            } catch (err) {
                console.warn('[PWA] syncDeviceStatus error:', err);
                this.setState(this.computeCurrentState(), err.message);
            }
        },

        // Complete sequential device subscription flow
        subscribeDevice: async function (userType = 'customer', options = {}) {
            if (!this.isSupported()) {
                alert('Web Push Notifications are not supported by this browser.');
                return { success: false, error: 'Push not supported' };
            }

            const isLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
            if (!window.isSecureContext && !isLocal) {
                alert('Push Notifications require a secure HTTPS connection.');
                return { success: false, error: 'HTTPS required' };
            }

            if (this.isIos && !this.isStandalone) {
                this.showIosInstallModal();
                return { success: false, requires_ios_install: true };
            }

            if (Notification.permission === 'denied') {
                this.setState(PushState.PERMISSION_DENIED);
                this.showBlockedPermissionModal();
                return { success: false, error: 'Permission denied in browser settings' };
            }

            this.setState(PushState.SUBSCRIPTION_PENDING);

            try {
                // Step 1: Request Notification permission
                let perm = Notification.permission;
                if (perm === 'default') {
                    perm = await Notification.requestPermission();
                }

                if (perm !== 'granted') {
                    this.setState(PushState.PERMISSION_DENIED);
                    this.showBlockedPermissionModal();
                    return { success: false, error: 'Notification permission was not granted by user.' };
                }

                // Step 2: Ensure Service Worker is active and ready
                const reg = await navigator.serviceWorker.ready;
                if (!reg) {
                    throw new Error('Service worker failed to become ready.');
                }

                // Step 3: Fetch VAPID key
                const vapidKey = await this.getVapidKey();
                const appServerKey = urlBase64ToUint8Array(vapidKey);

                // Step 4: Handle existing subscription cleanly
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
                                console.log('[PWA] Unsubscribing mismatched VAPID key before renewal');
                                await subscription.unsubscribe();
                                subscription = null;
                            }
                        }
                    } catch (e) {
                        // ignore and proceed
                    }
                }

                // Step 5: Subscribe with PushManager
                if (!subscription) {
                    subscription = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: appServerKey
                    });
                }

                this.currentEndpoint = subscription.endpoint;
                this.isSubscribed = true;

                // Step 6: Extract encryption keys
                const subJson = subscription.toJSON();
                let p256dh = subJson.keys?.p256dh;
                let auth = subJson.keys?.auth;

                if (!p256dh && subscription.getKey) {
                    const k = subscription.getKey('p256dh');
                    if (k) p256dh = arrayBufferToBase64Url(k);
                }
                if (!auth && subscription.getKey) {
                    const a = subscription.getKey('auth');
                    if (a) auth = arrayBufferToBase64Url(a);
                }

                if (!p256dh || !auth) {
                    throw new Error('Browser PushManager did not provide required p256dh/auth encryption keys.');
                }

                // Step 7: Transition to SUBSCRIPTION_SAVED and send to backend API
                this.setState(PushState.SUBSCRIPTION_SAVED);

                const csrfToken = this.getCsrfToken();
                const response = await fetch('/api/push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        p256dh: p256dh,
                        auth: auth,
                        user_type: userType,
                        csrf_token: csrfToken
                    })
                });

                if (!response.ok) {
                    const errData = await response.json().catch(() => ({}));
                    throw new Error(errData.error || `Server responded with HTTP ${response.status}`);
                }

                const resData = await response.json();

                // Step 8: Verify database confirmation
                if (resData.success && resData.verified) {
                    this.isServerVerified = true;
                    this.deviceRole = userType;
                    this.subscriptionId = resData.subscription_id;
                    this.lastError = null;
                    this.setState(PushState.DEVICE_ENROLLED);

                    localStorage.setItem('na_push_enrolled', '1');
                    sessionStorage.setItem('na_push_prompt_dismissed', '1');

                    return { 
                        success: true, 
                        subscription_id: resData.subscription_id, 
                        role: userType 
                    };
                } else {
                    throw new Error(resData.error || 'Server could not verify subscription in database.');
                }

            } catch (err) {
                console.error('[PWA] Enrollment failed:', err);
                this.isServerVerified = false;
                this.setState(PushState.ENROLLMENT_FAILED, err.message);
                return { success: false, error: err.message };
            }
        },

        // Repair / Re-sync Subscription: cleanly removes old subscription and creates fresh one
        repairSubscription: async function (userType = 'admin') {
            try {
                this.setState(PushState.SUBSCRIPTION_PENDING);
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    await sub.unsubscribe().catch(() => {});
                }
                this.currentEndpoint = null;
                this.isSubscribed = false;
                this.isServerVerified = false;

                const result = await this.subscribeDevice(userType);
                if (result.success) {
                    alert('✓ Subscription repaired and verified with database!');
                } else {
                    alert('✕ Subscription repair failed: ' + (result.error || 'Unknown error'));
                }
                return result;
            } catch (e) {
                alert('Error repairing subscription: ' + e.message);
                this.setState(PushState.ENROLLMENT_FAILED, e.message);
                return { success: false, error: e.message };
            }
        },

        // Unsubscribe device
        unsubscribeDevice: async function () {
            try {
                const reg = await navigator.serviceWorker.ready;
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
                this.isServerVerified = false;
                this.deviceRole = null;
                this.currentEndpoint = null;
                this.subscriptionId = null;
                this.setState(PushState.PERMISSION_GRANTED);
                localStorage.removeItem('na_push_enrolled');
                return { success: true };
            } catch (err) {
                console.error('[PWA] Unsubscribe error:', err);
                return { success: false, error: err.message };
            }
        },

        // Test alert on current device with detailed feedback
        testCurrentDevice: async function (role = 'admin') {
            if (!this.currentEndpoint) {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg?.pushManager.getSubscription();
                if (sub) {
                    this.currentEndpoint = sub.endpoint;
                }
            }

            if (!this.currentEndpoint) {
                alert('No push subscription found on this device. Please enroll first.');
                return { success: false, error: 'No subscription' };
            }

            const feedbackEl = document.getElementById('admin-device-test-result');
            if (feedbackEl) {
                feedbackEl.innerHTML = '<span class="text-amber-600 animate-pulse">Dispatching encrypted test alert via push gateway...</span>';
                feedbackEl.classList.remove('hidden');
            }

            try {
                const startTime = performance.now();
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

                const latencyMs = Math.round(performance.now() - startTime);
                const data = await res.json();

                this.lastPushResult = {
                    http_code: data.http_code || res.status,
                    latency_ms: data.latency_ms || latencyMs,
                    success: !!data.success,
                    timestamp: new Date().toLocaleTimeString(),
                    error: data.error || null,
                    endpoint_host: data.endpoint_host || (this.currentEndpoint ? new URL(this.currentEndpoint).hostname : '')
                };

                if (data.success) {
                    const msg = `✓ Success: Push alert received (HTTP ${data.http_code || 201} via ${data.endpoint_host || 'gateway'}, ${data.latency_ms || latencyMs}ms). Check your screen!`;
                    if (feedbackEl) {
                        feedbackEl.innerHTML = `<span class="text-emerald-700 font-bold">${escapeHtml(msg)}</span>`;
                    }
                    this.updateUIState();
                    alert(msg);
                    return { success: true, ...data };
                } else {
                    if (data.expired) {
                        this.setState(PushState.SUBSCRIPTION_EXPIRED);
                    }
                    const errMsg = `✕ Delivery failed (HTTP ${data.http_code || 0}): ${data.error || 'Unknown gateway rejection'}`;
                    if (feedbackEl) {
                        feedbackEl.innerHTML = `<span class="text-red-600 font-bold">${escapeHtml(errMsg)}</span>`;
                    }
                    this.updateUIState();
                    alert(errMsg);
                    return { success: false, ...data };
                }
            } catch (e) {
                const errMsg = '✕ Error: ' + e.message;
                if (feedbackEl) {
                    feedbackEl.innerHTML = `<span class="text-red-600 font-bold">${escapeHtml(errMsg)}</span>`;
                }
                alert(errMsg);
                return { success: false, error: e.message };
            }
        },

        sendTestToCurrentDevice: function (role = 'customer') {
            return this.testCurrentDevice(role);
        },

        // Collect full live diagnostics
        getDiagnostics: function () {
            const perm = ('Notification' in window) ? Notification.permission : 'unsupported';
            const swActive = navigator.serviceWorker?.controller != null;
            const swRegistered = this.swRegistration != null;
            const pushMgr = ('PushManager' in window);

            let host = 'None';
            if (this.currentEndpoint) {
                try { host = new URL(this.currentEndpoint).hostname; } catch (e) { host = 'Invalid URL'; }
            }

            return {
                permission: perm,
                serviceWorker: swActive ? 'Active (Controlling Page)' : (swRegistered ? 'Registered (Waiting/Installing)' : 'Inactive / Ready'),
                pushManager: pushMgr ? 'Available' : 'Unavailable',
                browserSubscription: this.currentEndpoint ? `Active (${host})` : 'None',
                serverEnrollment: this.isServerVerified ? `Enrolled (ID: #${this.subscriptionId || 'Active'})` : (this.currentEndpoint ? 'Token exists, not saved in DB' : 'Not Enrolled'),
                fsmState: this.currentState,
                deviceRole: this.deviceRole || 'None',
                vapidStatus: this.vapidPublicKey ? 'Configured (NIST P-256)' : 'Ready to load',
                lastPush: this.lastPushResult
            };
        },

        // Update Admin & General UI Elements based on finite state machine
        updateUIState: function () {
            const state = this.currentState;
            const perm = ('Notification' in window) ? Notification.permission : 'unsupported';
            const isEnrolledAdmin = (state === PushState.DEVICE_ENROLLED && this.deviceRole === 'admin');

            // Admin Status Badge & Text
            const adminStatusBadge = document.getElementById('admin-enroll-status-badge');
            const adminStatusText = document.getElementById('admin-enroll-status-text');
            const adminEnrollBtn = document.getElementById('admin-enroll-btn');
            const adminTestBtn = document.getElementById('admin-device-test-btn');
            const adminUnenrollBtn = document.getElementById('admin-unenroll-btn');
            const adminRepairBtn = document.getElementById('admin-repair-btn');

            if (adminStatusBadge) {
                switch (state) {
                    case PushState.DEVICE_ENROLLED:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 flex items-center gap-1.5 shadow-xs"><span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span> Enrolled &amp; Active (Admin ID: #${this.subscriptionId || 'Verified'})</span>`;
                        break;
                    case PushState.PERMISSION_GRANTED:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-sky-100 text-sky-800 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-sky-600"></span> Permission Granted (Device Not Subscribed)</span>`;
                        break;
                    case PushState.DEVICE_NOT_SUBSCRIBED:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-600"></span> Device Token Found (Pending Server Verification)</span>`;
                        break;
                    case PushState.SUBSCRIPTION_PENDING:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 flex items-center gap-1.5 animate-pulse"><svg class="animate-spin h-3.5 w-3.5 text-amber-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Enrolling Device...</span>`;
                        break;
                    case PushState.SUBSCRIPTION_SAVED:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800">Subscription Saved (Verifying in DB...)</span>`;
                        break;
                    case PushState.SUBSCRIPTION_EXPIRED:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Subscription Expired (Gateway 410)</span>`;
                        break;
                    case PushState.ENROLLMENT_FAILED:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">Enrollment Failed (${escapeHtml(this.lastError || 'Server error')})</span>`;
                        break;
                    case PushState.PERMISSION_DENIED:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">Permission Blocked in Browser</span>`;
                        break;
                    case PushState.UNSUPPORTED:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700">Push Unsupported</span>`;
                        break;
                    default:
                        adminStatusBadge.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-white/20 text-emerald-100">Permission Not Requested</span>`;
                        break;
                }
            }

            if (adminStatusText) {
                switch (state) {
                    case PushState.DEVICE_ENROLLED:
                        adminStatusText.textContent = 'This browser is enrolled and verified in the database. You will receive real-time order alerts directly on this screen.';
                        break;
                    case PushState.PERMISSION_GRANTED:
                        adminStatusText.textContent = 'Notification permission is already granted in your browser, but this device is not yet subscribed to push service. Click "Activate Admin Push Subscription" to connect.';
                        break;
                    case PushState.DEVICE_NOT_SUBSCRIBED:
                        adminStatusText.textContent = 'A push subscription exists in this browser, but is not verified as Admin in the database. Click "Activate Admin Subscription" to save it.';
                        break;
                    case PushState.SUBSCRIPTION_PENDING:
                        adminStatusText.textContent = 'Requesting browser PushManager subscription and synchronizing with server database...';
                        break;
                    case PushState.SUBSCRIPTION_EXPIRED:
                        adminStatusText.textContent = 'The push gateway reported this subscription has expired or was revoked. Click "Renew Subscription" to get a fresh token.';
                        break;
                    case PushState.ENROLLMENT_FAILED:
                        adminStatusText.textContent = `Server enrollment failed: ${this.lastError || 'Unable to register device'}. Click "Retry Enrollment" to try again.`;
                        break;
                    case PushState.PERMISSION_DENIED:
                        adminStatusText.textContent = 'Notifications are blocked in your browser site settings. Click "Unblock Guide" to enable notifications in your browser address bar.';
                        break;
                    default:
                        adminStatusText.textContent = 'Enroll this device to receive instant push alerts whenever a customer places an order or completes payment.';
                        break;
                }
            }

            if (adminEnrollBtn) {
                if (isEnrolledAdmin) {
                    adminEnrollBtn.style.display = 'none';
                } else {
                    adminEnrollBtn.style.display = 'inline-flex';
                    adminEnrollBtn.disabled = (state === PushState.SUBSCRIPTION_PENDING);

                    if (state === PushState.PERMISSION_DENIED) {
                        adminEnrollBtn.textContent = 'Unblock Guide';
                        adminEnrollBtn.onclick = () => this.showBlockedPermissionModal();
                    } else if (state === PushState.PERMISSION_GRANTED || state === PushState.DEVICE_NOT_SUBSCRIBED) {
                        adminEnrollBtn.textContent = 'Activate Admin Subscription';
                        adminEnrollBtn.onclick = () => this.subscribeDevice('admin');
                    } else if (state === PushState.ENROLLMENT_FAILED) {
                        adminEnrollBtn.textContent = 'Retry Admin Enrollment';
                        adminEnrollBtn.onclick = () => this.subscribeDevice('admin');
                    } else if (state === PushState.SUBSCRIPTION_EXPIRED) {
                        adminEnrollBtn.textContent = 'Renew Subscription';
                        adminEnrollBtn.onclick = () => this.subscribeDevice('admin');
                    } else if (state === PushState.SUBSCRIPTION_PENDING) {
                        adminEnrollBtn.textContent = 'Enrolling Device...';
                    } else {
                        adminEnrollBtn.textContent = 'Enrol Current Admin Device';
                        adminEnrollBtn.onclick = () => this.subscribeDevice('admin');
                    }
                }
            }

            if (adminTestBtn) {
                adminTestBtn.style.display = isEnrolledAdmin ? 'inline-flex' : 'none';
            }

            if (adminUnenrollBtn) {
                adminUnenrollBtn.style.display = (this.isSubscribed || isEnrolledAdmin) ? 'inline-flex' : 'none';
            }

            if (adminRepairBtn) {
                adminRepairBtn.style.display = (perm === 'granted' || this.isSubscribed) ? 'inline-flex' : 'none';
            }

            // Live Diagnostics Card Updating in Admin Notifications
            this.updateDiagnosticsUI();

            // Centralized Modal & Auth prompt updates
            this.updateCenterModalState();
        },

        updateDiagnosticsUI: function () {
            const diag = this.getDiagnostics();

            const permEl = document.getElementById('diag-val-permission');
            const swEl = document.getElementById('diag-val-sw');
            const pushEl = document.getElementById('diag-val-push');
            const subEl = document.getElementById('diag-val-sub');
            const dbEl = document.getElementById('diag-val-db');
            const fsmEl = document.getElementById('diag-val-fsm');
            const lastPushEl = document.getElementById('diag-val-last-push');

            if (permEl) {
                permEl.textContent = diag.permission;
                permEl.className = 'font-mono text-xs ' + (diag.permission === 'granted' ? 'text-emerald-600 font-bold' : (diag.permission === 'denied' ? 'text-red-600 font-bold' : 'text-amber-600'));
            }
            if (swEl) swEl.textContent = diag.serviceWorker;
            if (pushEl) pushEl.textContent = diag.pushManager;
            if (subEl) subEl.textContent = diag.browserSubscription;
            if (dbEl) {
                dbEl.textContent = diag.serverEnrollment;
                dbEl.className = 'font-mono text-xs ' + (this.isServerVerified ? 'text-emerald-600 font-bold' : 'text-amber-600');
            }
            if (fsmEl) fsmEl.textContent = diag.fsmState;
            if (lastPushEl) {
                if (diag.lastPush) {
                    lastPushEl.textContent = `HTTP ${diag.lastPush.http_code} (${diag.lastPush.latency_ms}ms) at ${diag.lastPush.timestamp}`;
                    lastPushEl.className = 'font-mono text-xs ' + (diag.lastPush.success ? 'text-emerald-600 font-bold' : 'text-red-600 font-bold');
                } else {
                    lastPushEl.textContent = 'None in this session';
                }
            }
        },

        // Post-auth prompt check
        checkPostAuthPrompt: function () {
            const modal = document.getElementById('pwa-auth-notif-modal');
            if (!modal) return;

            const isPostAuth = modal.dataset.postAuth === 'true';
            if (!isPostAuth) return;

            if (sessionStorage.getItem('na_push_prompt_dismissed') === '1') {
                return;
            }

            if (Notification.permission === 'granted' && this.isSubscribed && this.isServerVerified) {
                return;
            }

            if (Notification.permission === 'denied') {
                return;
            }

            setTimeout(() => {
                modal.classList.remove('hidden');
            }, 600);
        },

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
                if (txt) txt.textContent = 'Retry Subscription';
                if (fb) {
                    fb.textContent = '✕ ' + (result.error || 'Failed to enable notifications. Please try again.');
                    fb.className = 'my-3 p-2.5 rounded-xl text-xs bg-red-50 text-red-800 border border-red-200 block';
                    fb.classList.remove('hidden');
                }
            }
        },

        handleAuthPromptDismiss: function () {
            const modal = document.getElementById('pwa-auth-notif-modal');
            if (modal) modal.classList.add('hidden');
            sessionStorage.setItem('na_push_prompt_dismissed', '1');
        },

        openNotificationCenter: function (role = 'customer') {
            const modal = document.getElementById('pwa-notification-center-modal') || document.getElementById('pwa-center-modal');
            if (modal) {
                this.updateCenterModalState();
                modal.classList.remove('hidden');
            } else {
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
            const state = this.currentState;

            if (statusBadge) {
                if (!this.isSupported()) {
                    statusBadge.textContent = 'Unsupported in this browser';
                    statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 inline-block';
                } else if (perm === 'denied') {
                    statusBadge.textContent = 'Blocked in Browser Settings';
                    statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 inline-block';
                } else if (state === PushState.DEVICE_ENROLLED) {
                    statusBadge.textContent = 'Active on this Device (' + (this.deviceRole || 'customer') + ')';
                    statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 inline-block';
                } else if (perm === 'granted') {
                    statusBadge.textContent = 'Permission Granted (Click to Connect)';
                    statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-800 inline-block';
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
                } else if (state === PushState.DEVICE_ENROLLED) {
                    toggleBtn.textContent = 'Disable on this Device';
                    toggleBtn.className = 'w-full sm:w-auto bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs transition cursor-pointer';
                    toggleBtn.onclick = () => this.unsubscribeDevice();
                } else {
                    toggleBtn.textContent = (perm === 'granted') ? 'Connect Device to Push' : 'Enable Notifications';
                    toggleBtn.className = 'w-full sm:w-auto bg-[#235d3a] hover:bg-[#1b4a2e] text-white font-bold py-2 px-3 rounded-xl text-xs transition cursor-pointer';
                    toggleBtn.onclick = () => this.subscribeDevice('customer');
                }
            }

            if (testBtn) {
                testBtn.style.display = (state === PushState.DEVICE_ENROLLED) ? 'inline-flex' : 'none';
            }

            if (helpBox) {
                helpBox.style.display = (perm === 'denied') ? 'block' : 'none';
            }
        },

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
