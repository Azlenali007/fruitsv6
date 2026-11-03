/**
 * PWA Installation Helper
 * N.A Fresh Fruits & Coconuts
 * 
 * Handles Service Worker registration for offline functionality
 * and beforeinstallprompt / iOS Add-to-Home-Screen banners.
 * Pure PWA installation only - zero push notifications.
 */

(function () {
    'use strict';

    window.NA_PWA = {
        deferredPrompt: null,
        isIos: /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase()),
        isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,

        init: function () {
            // Register Service Worker for offline caching and PWA support
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js')
                    .then(function (reg) {
                        // Check for updates periodically
                        reg.addEventListener('updatefound', function () {
                            const newWorker = reg.installing;
                            if (newWorker) {
                                newWorker.addEventListener('statechange', function () {
                                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                        console.log('[PWA] New version ready.');
                                    }
                                });
                            }
                        });
                    })
                    .catch(function (err) {
                        console.warn('[PWA] Service Worker registration failed:', err);
                    });
            }

            // Capture beforeinstallprompt for Android / Chromium browsers
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                this.showInstallBanner();
            });

            window.addEventListener('appinstalled', () => {
                console.log('[PWA] App successfully installed.');
                this.hideInstallBanner();
            });
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
            } else if (this.isIos && !this.isStandalone) {
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
                alert("iPhone / iPad Installation Guide:\n\n1. Tap the Share button in Safari.\n2. Tap 'Add to Home Screen'.\n3. Launch N.A Fruits from your Home Screen for faster access!");
            }
        },

        hideIosInstallModal: function () {
            const modal = document.getElementById('ios-install-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.NA_PWA.init());
    } else {
        window.NA_PWA.init();
    }
})();
