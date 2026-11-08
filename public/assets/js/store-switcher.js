/**
 * N.A Fresh Fruits & Coconuts - Dual Store Transition Engine
 * High-performance, 60 FPS coordinated full-screen store switching
 * Coconut Store 🥥 <===> Juice Store 🍹
 */

(function() {
    'use strict';

    window.isStoreTransitioning = false;

    // Read active store from cookie, localStorage, or HTML data-attribute
    function getActiveStore() {
        var htmlStore = document.documentElement.getAttribute('data-store');
        if (htmlStore === 'juice' || htmlStore === 'coconut') return htmlStore;
        var match = document.cookie.match(/(?:^|; )na_store_mode=([^;]*)/);
        if (match && (match[1] === 'juice' || match[1] === 'coconut')) return match[1];
        var local = localStorage.getItem('na_store_mode');
        if (local === 'juice' || local === 'coconut') return local;
        return 'coconut';
    }

    // Ensure transition overlay exists in DOM
    function getOrCreateOverlay() {
        var el = document.getElementById('store-transition-overlay');
        if (el) return el;

        el = document.createElement('div');
        el.id = 'store-transition-overlay';
        el.setAttribute('aria-hidden', 'true');
        el.className = 'fixed inset-0 z-[100] pointer-events-none opacity-0 transition-opacity duration-300 flex flex-col items-center justify-center';
        el.innerHTML = [
            '<div id="store-transition-backdrop" class="absolute inset-0 transition-all duration-500 ease-out"></div>',
            '<div id="store-transition-waves" class="absolute inset-0 overflow-hidden pointer-events-none">',
            '  <svg class="absolute -bottom-10 left-0 right-0 w-[200%] h-48 opacity-25 animate-wave-slow" viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,0 C150,90 350,-40 500,60 C650,160 900,10 1200,40 L1200,120 L0,120 Z" fill="currentColor"></path></svg>',
            '</div>',
            '<div id="store-transition-card" class="relative z-10 flex flex-col items-center text-center p-8 max-w-sm mx-4 transform scale-90 opacity-0 transition-all duration-400 ease-out">',
            '  <div class="relative mb-5">',
            '    <div id="store-transition-halo" class="absolute -inset-4 rounded-full blur-xl opacity-70 transition-all duration-500"></div>',
            '    <div id="store-transition-badge" class="relative w-20 h-20 rounded-3xl flex items-center justify-center text-4xl shadow-2xl transform transition-transform duration-500">',
            '      <span id="store-transition-icon">🍹</span>',
            '    </div>',
            '  </div>',
            '  <h3 id="store-transition-title" class="text-2xl font-black text-white tracking-tight drop-shadow-md">Switching Store...</h3>',
            '  <p id="store-transition-tagline" class="text-xs font-semibold text-white/90 mt-1.5 max-w-xs drop-shadow-sm">Preparing fresh harvest...</p>',
            '  <div class="mt-6 flex items-center gap-1.5">',
            '    <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>',
            '    <span class="text-[11px] font-bold text-white/80 uppercase tracking-widest">Loading Experience</span>',
            '  </div>',
            '</div>'
        ].join('\n');

        document.body.appendChild(el);
        return el;
    }

    // Switch store handler
    window.switchStore = function(targetStore, redirectPath) {
        if (window.isStoreTransitioning) return;
        targetStore = (targetStore === 'juice') ? 'juice' : 'coconut';

        var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.isStoreTransitioning = true;

        // Persist selection across Session, Cookie and localStorage
        try {
            localStorage.setItem('na_store_mode', targetStore);
            document.cookie = 'na_store_mode=' + encodeURIComponent(targetStore) + '; path=/; max-age=' + (365 * 86400) + '; SameSite=Lax';
        } catch (_) {}

        var overlay = getOrCreateOverlay();
        var backdrop = document.getElementById('store-transition-backdrop');
        var card = document.getElementById('store-transition-card');
        var halo = document.getElementById('store-transition-halo');
        var badge = document.getElementById('store-transition-badge');
        var icon = document.getElementById('store-transition-icon');
        var title = document.getElementById('store-transition-title');
        var tagline = document.getElementById('store-transition-tagline');

        // Apply theme styling according to target
        if (targetStore === 'juice') {
            backdrop.className = 'absolute inset-0 bg-gradient-to-br from-orange-600 via-amber-600 to-orange-700 transition-all duration-500';
            halo.className = 'absolute -inset-4 rounded-full blur-2xl bg-amber-300/60 animate-pulse';
            badge.className = 'relative w-20 h-20 rounded-3xl bg-white/20 backdrop-blur-md border border-white/40 flex items-center justify-center text-4xl shadow-2xl';
            icon.textContent = '🍹';
            title.textContent = 'Opening Juice Store';
            tagline.textContent = 'Fresh cold-pressed juices, citrus coolers & fruit shakes';
        } else {
            backdrop.className = 'absolute inset-0 bg-gradient-to-br from-emerald-800 via-green-800 to-teal-900 transition-all duration-500';
            halo.className = 'absolute -inset-4 rounded-full blur-2xl bg-emerald-300/60 animate-pulse';
            badge.className = 'relative w-20 h-20 rounded-3xl bg-white/20 backdrop-blur-md border border-white/40 flex items-center justify-center text-4xl shadow-2xl';
            icon.textContent = '🥥';
            title.textContent = 'Opening Coconut Store';
            tagline.textContent = '100% pure tender coconuts & fresh coastal malai';
        }

        // Show overlay
        overlay.classList.remove('pointer-events-none', 'opacity-0');
        overlay.classList.add('pointer-events-auto', 'opacity-100');

        // Animate card scale
        requestAnimationFrame(function() {
            if (card) {
                card.classList.remove('scale-90', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            }
        });

        // Determine target URL: preserve current path if valid, or default to home
        var currentUrl = new URL(window.location.href);
        var destUrl = redirectPath ? new URL(redirectPath, window.location.origin) : currentUrl;
        destUrl.searchParams.set('store', targetStore);
        // Clear category filter when switching stores to avoid mismatched categories
        if (destUrl.pathname === '/shop' || destUrl.pathname === '/') {
            destUrl.searchParams.delete('cat');
        }

        // Send non-blocking backend session sync
        fetch('/api/store/switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ store: targetStore })
        }).catch(function() {});

        // Smooth navigation after short transition
        var delay = prefersReduced ? 150 : 500;
        setTimeout(function() {
            window.location.href = destUrl.toString();
        }, delay);
    };

    // Attach click listener for all store switch buttons
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.store-switch-btn, [data-switch-store]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var target = btn.getAttribute('data-switch-store');
        if (!target) {
            var current = getActiveStore();
            target = (current === 'juice') ? 'coconut' : 'juice';
        }
        window.switchStore(target);
    });

    // Dismiss transition overlay when page loads or resumes from BFCache
    function dismissOverlay() {
        var overlay = document.getElementById('store-transition-overlay');
        if (overlay) {
            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }
        window.isStoreTransitioning = false;
    }

    window.addEventListener('pageshow', dismissOverlay);
    document.addEventListener('DOMContentLoaded', dismissOverlay);

})();
