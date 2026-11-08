<?php
/**
 * Footer Layout Component with Fixed Mobile Bottom Navigation & Sticky Quick Ordering Bar
 * N.A Fresh Fruits & Coconuts - Dual Store
 */
$cartData = CartService::getCart();
$cartCount = $cartData['count'];
$activePage = $activePage ?? 'home';

$currentStore = StoreService::getCurrentStore();
$storeMeta = StoreService::getStoreMeta($currentStore);
$isJuiceMode = ($currentStore === 'juice');
$storeName = $storeMeta['store_name'];
$storePhone = Setting::get('store_phone', '+91 98765 43210');
$storeEmail = Setting::get('store_email', 'contact@nafruitsandcoconuts.com');
$storeAddress = Setting::get('store_address', 'Station Road, Opp. Bus Stand, Jaora, Ratlam, Madhya Pradesh - 457226');
?>
</main>

<!-- Desktop Standard Footer -->
<footer class="bg-white border-t border-gray-100 mt-16 pb-20 md:pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Brand Column -->
            <div class="md:col-span-2 space-y-4">
                <div class="flex items-center gap-2.5">
                    <?php if ($isJuiceMode): ?>
                    <div class="w-8 h-8 text-orange-600">
                        <svg viewBox="0 0 48 48" fill="none" class="w-full h-full" stroke="currentColor">
                            <path d="M14 12L17 40C17.2 41.8 18.8 43 20.6 43H27.4C29.2 43 30.8 41.8 31 40L34 12" stroke="#ea580c" stroke-width="3.5" stroke-linecap="round" fill="#ffedd5"/>
                            <path d="M22 6L28 26" stroke="#f97316" stroke-width="3" stroke-linecap="round"/>
                            <circle cx="33" cy="8" r="4" fill="#fb923c"/>
                        </svg>
                    </div>
                    <?php else: ?>
                    <div class="w-8 h-8 text-brand-600">
                        <svg viewBox="0 0 48 48" fill="none" class="w-full h-full" stroke="currentColor">
                            <path d="M24 44V26" stroke="#235d3a" stroke-width="3.5" stroke-linecap="round"/>
                            <path d="M24 26C16 26 8 20 8 10C16 10 22 17 24 26Z" fill="#235d3a" fill-opacity="0.2" stroke="#235d3a" stroke-width="2.5" stroke-linejoin="round"/>
                            <path d="M24 26C32 26 40 20 40 10C32 10 26 17 24 26Z" fill="#235d3a" fill-opacity="0.2" stroke="#235d3a" stroke-width="2.5" stroke-linejoin="round"/>
                            <circle cx="24" cy="24" r="2.5" fill="#235d3a"/>
                        </svg>
                    </div>
                    <?php endif; ?>
                    <span class="font-bold text-xl text-gray-900"><?= e($storeName) ?></span>
                </div>
                <p class="text-sm text-gray-500 max-w-md leading-relaxed">
                    <?= $isJuiceMode ? '100% cold-pressed natural fruit juices, detox blends & rich seasonal milkshakes. No added sugar, no chemicals. Pure liquid goodness in Jaora.' : 'Direct from nature, our fresh tender coconut water keeps you hydrated, healthy and energized. 100% natural, no added sugar, no chemicals. Just pure goodness.' ?>
                </p>
                <div class="flex items-center gap-4 text-xs text-gray-500 pt-2">
                    <span class="inline-flex items-center gap-1.5 <?= $isJuiceMode ? 'text-orange-700 bg-orange-50' : 'text-brand-700 bg-brand-50' ?> px-2.5 py-1 rounded-full font-medium">
                        <span class="w-2 h-2 rounded-full <?= $isJuiceMode ? 'bg-orange-500' : 'bg-emerald-500' ?>"></span> Open Daily: <?= e(Setting::get('store_open_time', '07:00 AM')) ?> - <?= e(Setting::get('store_close_time', '09:00 PM')) ?>
                    </span>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4">Quick Links</h4>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="/" class="text-gray-600 hover:text-brand-600 transition">Home</a></li>
                    <li><a href="/shop" class="text-gray-600 hover:text-brand-600 transition"><?= $isJuiceMode ? 'Shop Fresh Juices' : 'Shop Coconut Water' ?></a></li>
                    <li><a href="/cart" class="text-gray-600 hover:text-brand-600 transition">Your Cart (<span class="cart-count-badge"><?= $cartCount ?></span>)</a></li>
                    <li><a href="/track" class="text-gray-600 hover:text-brand-600 transition">Track Your Order</a></li>
                </ul>
            </div>

            <!-- Contact & Delivery -->
            <div id="contact">
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4">Visit / Contact Us</h4>
                <ul class="space-y-2.5 text-sm text-gray-600">
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-brand-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <span><?= e($storeAddress) ?></span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        <a href="tel:<?= e($storePhone) ?>" class="hover:text-brand-600"><?= e($storePhone) ?></a>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <span><?= e($storeEmail) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="border-t border-gray-100 mt-8 pt-6 flex flex-col sm:flex-row justify-between items-center text-xs text-gray-400 gap-2">
            <p>&copy; <?= date('Y') ?> <?= e($storeName) ?>. All rights reserved.</p>
            <p><?= $isJuiceMode ? '100% Pure Cold-Pressed Juices & Shakes' : '100% Pure & Hygienic Tender Coconut Water' ?></p>
        </div>
    </div>
</footer>

<!-- Spacer to prevent content from hiding behind fixed bottom nav on mobile screens -->
<div class="bottom-nav-spacer"></div>

<!-- STICKY QUICK ORDERING BAR -->
<div 
    id="sticky-ordering-bar" 
    class="fixed z-40 transition-all duration-300 transform <?= ($cartCount > 0 && !in_array($activePage, ['cart', 'checkout'])) ? 'translate-y-0 opacity-100' : 'translate-y-full opacity-0 pointer-events-none' ?> bottom-16 md:bottom-6 left-0 right-0 md:left-auto md:right-8 md:max-w-md px-3 md:px-0"
>
    <div class="bg-gray-900/95 backdrop-blur-md text-white rounded-2xl p-3 shadow-2xl border border-white/10 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-10 h-10 rounded-xl <?= $isJuiceMode ? 'bg-orange-500' : 'bg-emerald-600' ?> flex items-center justify-center text-white shrink-0 shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            </div>
            <div class="truncate">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-white sticky-cart-items-count"><?= $cartCount ?> <?= ($cartCount === 1) ? 'Item' : 'Items' ?></span>
                    <span class="text-[10px] text-gray-400">&bull;</span>
                    <span class="text-xs font-extrabold text-amber-400 sticky-cart-subtotal">₹<?= number_format($cartData['subtotal']) ?></span>
                </div>
                <p class="text-[10px] text-gray-300 truncate">100% Fresh Doorstep Delivery in Jaora</p>
            </div>
        </div>

        <a 
            href="/cart" 
            class="shrink-0 <?= $isJuiceMode ? 'bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600' : 'bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700' ?> text-white text-xs font-black px-4 py-2.5 rounded-xl transition flex items-center gap-1.5 shadow-md active:scale-95"
        >
            <span>View Cart</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
        </a>
    </div>
</div>

<!-- SECTION 5: FIXED MOBILE BOTTOM NAVIGATION -->
<!-- Visible only on mobile/tablet viewports (< md), fixed to viewport bottom with safe area insets -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md border-t border-gray-200/80 shadow-[0_-4px_12px_rgba(0,0,0,0.04)] pb-safe">
    <div class="grid grid-cols-4 h-16 max-w-md mx-auto items-center">
        
        <!-- 1. Home Tab -->
        <a href="/" class="flex flex-col items-center justify-center py-1 transition group <?= $activePage === 'home' ? 'text-brand-600 font-semibold' : 'text-gray-500 hover:text-gray-800' ?>">
            <div class="relative">
                <svg class="w-5 h-5 <?= $activePage === 'home' ? 'text-brand-600 stroke-[2.2]' : 'text-gray-500 stroke-[1.8]' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
            </div>
            <span class="text-[11px] mt-1 <?= $activePage === 'home' ? 'text-brand-600 font-semibold' : 'text-gray-600 font-normal' ?>">Home</span>
        </a>

        <!-- 2. Shop Tab -->
        <a href="/shop" class="flex flex-col items-center justify-center py-1 transition group <?= $activePage === 'shop' ? 'text-brand-600 font-semibold' : 'text-gray-500 hover:text-gray-800' ?>">
            <div class="relative">
                <svg class="w-5 h-5 <?= $activePage === 'shop' ? 'text-brand-600 stroke-[2.2]' : 'text-gray-500 stroke-[1.8]' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <span class="text-[11px] mt-1 <?= $activePage === 'shop' ? 'text-brand-600 font-semibold' : 'text-gray-600 font-normal' ?>">Shop</span>
        </a>

        <!-- 3. Cart Tab with Badge -->
        <a href="/cart" class="flex flex-col items-center justify-center py-1 transition group <?= $activePage === 'cart' ? 'text-brand-600 font-semibold' : 'text-gray-500 hover:text-gray-800' ?>">
            <div class="relative">
                <svg class="w-5 h-5 <?= $activePage === 'cart' ? 'text-brand-600 stroke-[2.2]' : 'text-gray-500 stroke-[1.8]' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                <?php if ($cartCount > 0): ?>
                <span class="absolute -top-1.5 -right-2 bg-brand-600 text-white text-[9px] font-bold rounded-full h-4 w-4 flex items-center justify-center shadow-xs">
                    <?= $cartCount ?>
                </span>
                <?php endif; ?>
            </div>
            <span class="text-[11px] mt-1 <?= $activePage === 'cart' ? 'text-brand-600 font-semibold' : 'text-gray-600 font-normal' ?>">Cart</span>
        </a>

        <!-- 4. Profile / My Account Tab -->
        <?php 
        $isCustLoggedIn = CustomerAuthService::check(); 
        $profileTarget = $isCustLoggedIn ? '/account' : '/login';
        $isProfileActive = in_array($activePage, ['profile', 'account']);
        ?>
        <a href="<?= $profileTarget ?>" class="flex flex-col items-center justify-center py-1 transition group <?= $isProfileActive ? 'text-brand-600 font-semibold' : 'text-gray-500 hover:text-gray-800' ?>">
            <div class="relative">
                <svg class="w-5 h-5 <?= $isProfileActive ? 'text-brand-600 stroke-[2.2]' : 'text-gray-500 stroke-[1.8]' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <span class="text-[11px] mt-1 <?= $isProfileActive ? 'text-brand-600 font-semibold' : 'text-gray-600 font-normal' ?>">
                <?= $isCustLoggedIn ? 'Account' : 'Profile' ?>
            </span>
        </a>

    </div>
</nav>

<!-- WhatsApp Channel Modal Popup -->
<?php require_once __DIR__ . '/../components/whatsapp-popup.php'; ?>

<!-- First-Party Privacy-Preserving Traffic Telemetry -->
<script>
(function() {
    try {
        const path = window.location.pathname;
        if (path.startsWith('/admin') || path.startsWith('/api')) return;

        // 1. Resolve Persistent Visitor ID
        let vid = localStorage.getItem('na_vid');
        if (!vid) {
            vid = 'vid_' + Math.random().toString(36).substring(2, 12) + Date.now().toString(36);
            localStorage.setItem('na_vid', vid);
        }

        // 2. Resolve Active Session Token
        let sid = sessionStorage.getItem('na_sid');
        const searchParams = new URLSearchParams(window.location.search);
        const queryObj = {};
        for (const [k, v] of searchParams.entries()) {
            if (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'ref', 'source', 'gclid', 'fbclid', 'qr'].includes(k.toLowerCase())) {
                queryObj[k] = v;
            }
        }

        // 3. Dispatch Page Hit Telemetry (Non-blocking)
        const hitData = {
            visitor_id: vid,
            session_token: sid || '',
            path: path,
            title: document.title || '',
            referrer: document.referrer || '',
            query: queryObj
        };

        fetch('/api/analytics/track', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(hitData),
            keepalive: true
        })
        .then(r => r.json())
        .then(d => {
            if (d && d.session_token) {
                sessionStorage.setItem('na_sid', d.session_token);
                sid = d.session_token;
            }
        })
        .catch(() => {});

        // 4. Heartbeat while page is open (every 30s)
        setInterval(function() {
            if (document.visibilityState === 'visible' && sid) {
                fetch('/api/analytics/heartbeat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_token: sid, path: window.location.pathname }),
                    keepalive: true
                }).catch(() => {});
            }
        }, 30000);
    } catch (e) {}
})();
</script>

<!-- PWA Mobile Install Floating Prompt Banner -->
<div id="pwa-install-banner" class="hidden fixed bottom-20 left-4 right-4 md:left-auto md:right-6 md:bottom-6 max-w-md bg-white border border-emerald-100 rounded-2xl shadow-2xl p-4 z-50 flex items-center gap-3.5 animate-bounce-subtle">
    <img src="/assets/images/icons/icon-192.png" alt="App Icon" class="w-12 h-12 rounded-xl object-cover shrink-0 shadow-xs">
    <div class="flex-1 min-w-0">
        <h4 class="text-sm font-bold text-gray-900 leading-tight">Install N.A Fruits App</h4>
        <p class="text-xs text-gray-500 truncate mt-0.5">Instant order tracking & push notifications</p>
    </div>
    <div class="flex items-center gap-1.5 shrink-0">
        <button type="button" onclick="window.NA_PWA.promptInstall()" class="bg-[#235d3a] hover:bg-[#1b4a2e] text-white px-3 py-1.5 rounded-lg text-xs font-semibold shadow-xs transition">
            Install
        </button>
        <button type="button" onclick="window.NA_PWA.hideInstallBanner()" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg" title="Dismiss">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div>

<!-- iOS 16.4+ Safari Add to Home Screen Instructions Modal -->
<div id="ios-install-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl border border-gray-100 text-left">
        <div class="flex items-center gap-3 mb-4">
            <img src="/assets/images/icons/icon-192.png" alt="Logo" class="w-12 h-12 rounded-xl shadow-xs">
            <div>
                <h3 class="text-base font-bold text-gray-900">Install on iPhone / iPad</h3>
                <p class="text-xs text-gray-500">Enable real-time push notifications</p>
            </div>
        </div>
        <div class="space-y-3 text-xs text-gray-600 bg-gray-50 p-3.5 rounded-xl border border-gray-100">
            <div class="flex items-start gap-2.5">
                <span class="flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-[#235d3a] font-bold text-[11px] shrink-0 mt-0.5">1</span>
                <span>Tap the <strong class="text-gray-800">Share</strong> icon <svg class="inline w-4 h-4 text-blue-600 mx-0.5 align-text-bottom" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"></path></svg> in Safari's bottom toolbar.</span>
            </div>
            <div class="flex items-start gap-2.5">
                <span class="flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-[#235d3a] font-bold text-[11px] shrink-0 mt-0.5">2</span>
                <span>Scroll down and tap <strong class="text-gray-800">Add to Home Screen</strong> <svg class="inline w-4 h-4 text-gray-700 mx-0.5 align-text-bottom" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>.</span>
            </div>
            <div class="flex items-start gap-2.5">
                <span class="flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-[#235d3a] font-bold text-[11px] shrink-0 mt-0.5">3</span>
                <span>Launch the <strong class="text-gray-800">N.A Fruits</strong> app from your Home Screen to receive instant order alerts!</span>
            </div>
        </div>
        <button type="button" onclick="window.NA_PWA.hideIosInstallModal()" class="mt-4 w-full rounded-xl bg-[#235d3a] hover:bg-[#1b4a2e] py-2.5 text-xs font-bold text-white shadow-xs transition">
            Got It
        </button>
    </div>
</div>


</body>
</html>
