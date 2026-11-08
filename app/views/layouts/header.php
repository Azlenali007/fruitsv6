<?php
/**
 * Header Layout Component
 * N.A Fresh Fruits & Coconuts - Dual Store Architecture (Coconut & Juice)
 */
$cartData = CartService::getCart();
$cartCount = $cartData['count'];
$activePage = $activePage ?? 'home';

// Resolve Active Store (Coconut vs Juice)
$currentStore = StoreService::getCurrentStore();
$storeMeta = StoreService::getStoreMeta($currentStore);
$isJuiceMode = ($currentStore === 'juice');
$storeSwitchEnabled = StoreService::isSwitchButtonEnabled();
$switchToStore = $isJuiceMode ? 'coconut' : 'juice';
$switchButtonText = $isJuiceMode 
    ? Setting::get('switch_to_coconut_text', 'Looking for Coconut? 🥥') 
    : Setting::get('switch_to_juice_text', 'Looking for Juice? 🍹');
$storeName = $storeMeta['store_name'];
$pageTitle = $pageTitle ?? ($isJuiceMode 
    ? '100% Pure Cold-Pressed Juices & Shakes - ' . $storeName 
    : 'Fresh Coconut Water - ' . $storeName);
?>
<!DOCTYPE html>
<html lang="en" class="h-full <?= $isJuiceMode ? 'bg-[#fffaf5]' : 'bg-[#f8faf8]' ?>" data-store="<?= $currentStore ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($storeMeta['tagline']) ?>. 100% pure, natural and hygienic doorstep delivery in Jaora.">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($storeMeta['tagline']) ?>">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/assets/images/icons/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/assets/images/icons/apple-touch-icon.png">

    <!-- Web App Manifest & PWA Configuration -->
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="<?= $storeMeta['theme_color'] ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= $isJuiceMode ? 'NA Juices' : 'NA Fruits' ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <!-- PWA & Scripts -->
    <script src="/assets/js/pwa-install.js" defer></script>
    <script src="/assets/js/store-switcher.js" defer></script>
    <script src="/assets/js/quick-ordering.js" defer></script>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: <?= $isJuiceMode ? json_encode([
                            '50' => '#fff7ed',
                            '100' => '#ffedd5',
                            '200' => '#fed7aa',
                            '300' => '#fdba74',
                            '400' => '#fb923c',
                            '500' => '#f97316',
                            '600' => '#ea580c', // Tangerine primary
                            '700' => '#c2410c',
                            '800' => '#9a3412',
                            '900' => '#7c2d12',
                        ]) : json_encode([
                            '50' => '#f0fdf4',
                            '100' => '#ebf5ed',
                            '200' => '#d1ead6',
                            '300' => '#a3d5ad',
                            '400' => '#529762',
                            '500' => '#2e7d32',
                            '600' => '#235d3a', // Emerald primary
                            '700' => '#1b4a2e',
                            '800' => '#173c26',
                            '900' => '#143220',
                        ]) ?>,
                        bgsoft: '<?= $isJuiceMode ? '#fffaf5' : '#f8faf8' ?>',
                    },
                    fontFamily: {
                        serif: ['Playfair Display', 'Georgia', 'serif'],
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        /* Safe area support for mobile devices */
        .pb-safe {
            padding-bottom: env(safe-area-inset-bottom, 1rem);
        }
        .bottom-nav-spacer {
            height: 4.5rem;
        }
        @media (min-width: 768px) {
            .bottom-nav-spacer {
                display: none;
            }
        }
        /* Smooth Page Transition Overlay */
        #page-loader {
            transition: opacity 0.25s ease-in-out, visibility 0.25s ease-in-out;
        }
        @media (prefers-reduced-motion: reduce) {
            #page-loader, .page-loader-spinner {
                animation: none !important;
                transition: none !important;
                display: none !important;
            }
        }
    </style>
</head>
<body class="min-h-full flex flex-col text-gray-800 antialiased bg-[#f8faf8] selection:bg-brand-100 selection:text-brand-700" x-data="{ mobileMenuOpen: false, searchOpen: false }">

<!-- Page Transition Loading Animation Overlay -->
<div id="page-loader" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-white/80 backdrop-blur-xs opacity-0 invisible pointer-events-none" aria-hidden="true">
    <div class="flex flex-col items-center p-6 rounded-3xl bg-white/95 shadow-xl border border-gray-100 max-w-xs text-center transform scale-95 transition-transform duration-200">
        <!-- Modern animated spinner -->
        <div class="relative w-12 h-12 flex items-center justify-center">
            <div class="w-12 h-12 rounded-full border-3 <?= $isJuiceMode ? 'border-orange-100 border-t-[#ea580c]' : 'border-emerald-100 border-t-[#235d3a]' ?> animate-spin"></div>
            <div class="absolute w-5 h-5 rounded-full <?= $isJuiceMode ? 'bg-orange-500/20' : 'bg-emerald-500/20' ?> flex items-center justify-center animate-pulse">
                <span class="w-2 h-2 rounded-full <?= $isJuiceMode ? 'bg-[#ea580c]' : 'bg-[#235d3a]' ?>"></span>
            </div>
        </div>
        <p class="text-xs font-bold text-gray-800 mt-3.5 tracking-wide"><?= e($storeName) ?></p>
        <p class="text-[10px] text-gray-400 mt-0.5"><?= $isJuiceMode ? 'Blending fresh citrus & juices...' : 'Harvesting freshness...' ?></p>
    </div>
</div>

<!-- Full-Screen Dual Store Coordinated Transition Overlay -->
<div id="store-transition-overlay" class="fixed inset-0 z-[100] pointer-events-none opacity-0 transition-opacity duration-300 flex flex-col items-center justify-center" aria-hidden="true">
    <div id="store-transition-backdrop" class="absolute inset-0 transition-all duration-500 ease-out"></div>
    <div id="store-transition-waves" class="absolute inset-0 overflow-hidden pointer-events-none">
        <svg class="absolute -bottom-10 left-0 right-0 w-[200%] h-48 opacity-25 animate-wave-slow" viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,0 C150,90 350,-40 500,60 C650,160 900,10 1200,40 L1200,120 L0,120 Z" fill="currentColor"></path></svg>
    </div>
    <div id="store-transition-card" class="relative z-10 flex flex-col items-center text-center p-8 max-w-sm mx-4 transform scale-90 opacity-0 transition-all duration-400 ease-out">
        <div class="relative mb-5">
            <div id="store-transition-halo" class="absolute -inset-4 rounded-full blur-xl opacity-70 transition-all duration-500"></div>
            <div id="store-transition-badge" class="relative w-20 h-20 rounded-3xl flex items-center justify-center text-4xl shadow-2xl transform transition-transform duration-500">
                <span id="store-transition-icon">🍹</span>
            </div>
        </div>
        <h3 id="store-transition-title" class="text-2xl font-black text-white tracking-tight drop-shadow-md">Switching Store...</h3>
        <p id="store-transition-tagline" class="text-xs font-semibold text-white/90 mt-1.5 max-w-xs drop-shadow-sm">Preparing fresh experience...</p>
        <div class="mt-6 flex items-center gap-2 bg-black/20 backdrop-blur-sm px-4 py-1.5 rounded-full">
            <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
            <span class="text-[11px] font-bold text-white/90 uppercase tracking-widest">Instant 1-Tap Ordering</span>
        </div>
    </div>
</div>

<script>
(function() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    var loader = document.getElementById('page-loader');
    if (!loader) return;

    var hideTimer = null;

    function showLoader() {
        if (!loader) return;
        clearTimeout(hideTimer);
        loader.classList.remove('opacity-0', 'invisible', 'pointer-events-none');
        loader.classList.add('opacity-100', 'visible');
        // Safety timeout: auto-hide after 6 seconds to prevent stuck state
        hideTimer = setTimeout(hideLoader, 6000);
    }

    function hideLoader() {
        if (!loader) return;
        clearTimeout(hideTimer);
        loader.classList.remove('opacity-100', 'visible');
        loader.classList.add('opacity-0', 'invisible', 'pointer-events-none');
        // Reset navigation lock on all links
        var navigatingLinks = document.querySelectorAll('a[data-navigating="true"]');
        for (var i = 0; i < navigatingLinks.length; i++) {
            delete navigatingLinks[i].dataset.navigating;
        }
    }

    // Hide loader immediately on DOM load and when restored from BFCache (Back/Forward navigation)
    window.addEventListener('pageshow', function(e) {
        hideLoader();
    });
    window.addEventListener('load', hideLoader);
    document.addEventListener('DOMContentLoaded', hideLoader);

    // Click handler for smooth transitions
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a');
        if (!link) return;

        var href = link.getAttribute('href');
        if (!href) return;

        // Skip anchors, javascript, empty
        if (href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href === '') return;

        // Skip new tab / blank
        if (link.target === '_blank') return;

        // Skip downloads
        if (link.hasAttribute('download')) return;

        // Skip special protocols
        if (/^(mailto|tel|sms|whatsapp|blob):/i.test(href)) return;

        // Check if internal domain
        try {
            var url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin) return;
            // Skip hash navigation on same page
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
        } catch (_) {
            return;
        }

        // Prevent double click navigation
        if (link.dataset.navigating === 'true') {
            e.preventDefault();
            return;
        }
        link.dataset.navigating = 'true';

        // Trigger smooth transition
        showLoader();
    }, true);

    // Form submission button loading state & double-submission prevention
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (form.hasAttribute('data-no-loader')) return;
        var btn = form.querySelector('button[type="submit"]:not([data-no-loader]), input[type="submit"]:not([data-no-loader])');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            var originalHtml = btn.innerHTML;
            btn.dataset.originalHtml = originalHtml;
            btn.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Processing...</span></span>';
            // Safety release after 8 seconds
            setTimeout(function() {
                if (btn && btn.disabled) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-75', 'cursor-not-allowed');
                    if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
                }
            }, 8000);
        }
    }, true);
})();

// PWA Install helper forwarder
function handlePwaInstall() {
    if (window.NA_PWA) {
        window.NA_PWA.promptInstall();
    }
}
</script>


<!-- Top Navigation Bar -->
<header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-gray-100 shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 sm:h-20">
            
            <!-- Logo Section -->
            <a href="/" class="flex items-center gap-2.5 group">
                <?php if ($isJuiceMode): ?>
                <!-- Fresh Juice Glass SVG Icon -->
                <div class="w-9 h-9 sm:w-10 sm:h-10 text-orange-600 flex items-center justify-center transition-transform group-hover:scale-105">
                    <svg viewBox="0 0 48 48" fill="none" class="w-full h-full" stroke="currentColor">
                        <path d="M14 12L17 40C17.2 41.8 18.8 43 20.6 43H27.4C29.2 43 30.8 41.8 31 40L34 12" stroke="#ea580c" stroke-width="3" stroke-linecap="round" fill="#ffedd5" fill-opacity="0.5"/>
                        <path d="M16 20C20 22 28 18 32 20L30.5 39C30.4 39.8 29.5 40.5 28.5 40.5H19.5C18.5 40.5 17.6 39.8 17.5 39L16 20Z" fill="#ea580c" fill-opacity="0.35"/>
                        <path d="M22 6L28 26" stroke="#f97316" stroke-width="3" stroke-linecap="round"/>
                        <path d="M30 10C33.5 10 36.5 7.5 37.5 4C34 3.5 30.5 5.5 30 10Z" fill="#fb923c" stroke="#ea580c" stroke-width="1.5"/>
                        <circle cx="33" cy="7" r="1" fill="#ffffff"/>
                    </svg>
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-xl sm:text-2xl tracking-tight text-gray-900 leading-none">N.A</span>
                    <span class="text-[10px] sm:text-xs font-semibold text-orange-600 tracking-wide">Fresh Fruit Juices & Shakes</span>
                </div>
                <?php else: ?>
                <!-- Palm Tree SVG Icon -->
                <div class="w-9 h-9 sm:w-10 sm:h-10 text-brand-600 flex items-center justify-center transition-transform group-hover:scale-105">
                    <svg viewBox="0 0 48 48" fill="none" class="w-full h-full" stroke="currentColor">
                        <path d="M24 44V26" stroke="#235d3a" stroke-width="3.5" stroke-linecap="round"/>
                        <path d="M24 26C16 26 8 20 8 10C16 10 22 17 24 26Z" fill="#235d3a" fill-opacity="0.2" stroke="#235d3a" stroke-width="2.5" stroke-linejoin="round"/>
                        <path d="M24 26C32 26 40 20 40 10C32 10 26 17 24 26Z" fill="#235d3a" fill-opacity="0.2" stroke="#235d3a" stroke-width="2.5" stroke-linejoin="round"/>
                        <path d="M24 26C24 16 19 8 10 6C15 15 20 22 24 26Z" stroke="#235d3a" stroke-width="2.5" stroke-linecap="round"/>
                        <path d="M24 26C24 16 29 8 38 6C33 15 28 22 24 26Z" stroke="#235d3a" stroke-width="2.5" stroke-linecap="round"/>
                        <circle cx="24" cy="24" r="2.5" fill="#235d3a"/>
                        <circle cx="21" cy="27" r="2.5" fill="#235d3a"/>
                        <circle cx="27" cy="27" r="2.5" fill="#235d3a"/>
                    </svg>
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-xl sm:text-2xl tracking-tight text-gray-900 leading-none">N.A</span>
                    <span class="text-[10px] sm:text-xs font-medium text-gray-500 tracking-wide">Fresh Fruits & Coconuts</span>
                </div>
                <?php endif; ?>
            </a>

            <?php 
            $isCustomerLoggedIn = CustomerAuthService::check(); 
            $currentCustomer = $isCustomerLoggedIn ? CustomerAuthService::user() : null;
            ?>

            <!-- Desktop Navigation Links -->
            <nav class="hidden md:flex items-center space-x-7 text-sm font-medium">
                <a href="/" class="<?= $activePage === 'home' ? 'text-brand-600 font-semibold border-b-2 border-brand-600 pb-1' : 'text-gray-600 hover:text-brand-600 transition' ?>">
                    Home
                </a>
                <a href="/shop" class="<?= $activePage === 'shop' ? 'text-brand-600 font-semibold border-b-2 border-brand-600 pb-1' : 'text-gray-600 hover:text-brand-600 transition' ?>">
                    <?= $isJuiceMode ? 'Juice Menu' : 'Shop' ?>
                </a>
                <?php if ($isCustomerLoggedIn): ?>
                <a href="/my-orders" class="<?= $activePage === 'track' ? 'text-brand-600 font-semibold border-b-2 border-brand-600 pb-1' : 'text-gray-600 hover:text-brand-600 transition' ?>">
                    My Orders
                </a>
                <?php else: ?>
                <a href="/track" class="<?= $activePage === 'track' ? 'text-brand-600 font-semibold border-b-2 border-brand-600 pb-1' : 'text-gray-600 hover:text-brand-600 transition' ?>">
                    Track Order
                </a>
                <?php endif; ?>
                <a href="/#why-choose-us" class="text-gray-600 hover:text-brand-600 transition">
                    About
                </a>
                <a href="/#contact" class="text-gray-600 hover:text-brand-600 transition">
                    Contact
                </a>
            </nav>

            <!-- Right Icons (Store Switcher, PWA Install, Search, Cart, Profile) -->
            <div class="flex items-center space-x-2 sm:space-x-3">
                <?php if ($storeSwitchEnabled): ?>
                <!-- Prominent Dual Store Switching Button (Desktop) -->
                <button 
                    type="button" 
                    data-switch-store="<?= $switchToStore ?>" 
                    class="store-switch-btn group relative hidden md:inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-black transition-all duration-300 transform hover:scale-105 active:scale-95 shadow-xs <?= $isJuiceMode ? 'bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white ring-2 ring-emerald-300/40 shadow-emerald-600/20' : 'bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-600 text-white ring-2 ring-amber-300/50 shadow-orange-500/20' ?>"
                    title="Switch to <?= $switchToStore === 'juice' ? 'Juice Store' : 'Coconut Store' ?>"
                >
                    <span class="text-sm transition-transform group-hover:rotate-12"><?= $isJuiceMode ? '🥥' : '🍹' ?></span>
                    <span class="tracking-tight"><?= e($switchButtonText) ?></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                </button>

                <!-- Prominent Store Switch Button (Mobile Header) -->
                <button 
                    type="button" 
                    data-switch-store="<?= $switchToStore ?>" 
                    class="store-switch-btn md:hidden inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold text-white transition active:scale-95 shadow-2xs <?= $isJuiceMode ? 'bg-gradient-to-r from-emerald-600 to-green-600' : 'bg-gradient-to-r from-orange-500 to-amber-500' ?>"
                    title="Switch Store"
                >
                    <span><?= $isJuiceMode ? '🥥' : '🍹' ?></span>
                    <span class="truncate max-w-[80px]"><?= $isJuiceMode ? 'Coconut' : 'Juice' ?></span>
                </button>
                <?php endif; ?>

                <!-- PWA Install Button (Auto-appears when installable or clicked) -->
                <button 
                    type="button" 
                    id="pwaInstallHeaderBtn" 
                    onclick="handlePwaInstall()" 
                    class="hidden sm:inline-flex items-center gap-1.5 text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-full border border-emerald-200/80 transition active:scale-95 shadow-2xs"
                    title="Install N.A Coconuts Web App"
                >
                    <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <span>Install App</span>
                </button>

                <!-- Search Button -->
                <button type="button" @click="searchOpen = !searchOpen" class="text-gray-600 hover:text-brand-600 p-1.5 transition" aria-label="Search">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3"></path>
                    </svg>
                </button>

                <!-- Cart Button with Counter Badge -->
                <a href="/cart" class="relative text-gray-600 hover:text-brand-600 p-1.5 transition" aria-label="Shopping Cart">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    <span class="cart-count-badge <?= ($cartCount > 0) ? '' : 'hidden' ?> absolute -top-1 -right-1 bg-brand-600 text-white text-[10px] sm:text-xs font-bold rounded-full h-4 w-4 sm:h-5 sm:w-5 flex items-center justify-center shadow-xs">
                        <?= $cartCount ?>
                    </span>
                </a>

                <!-- Customer Profile / Login Button -->
                <?php if ($isCustomerLoggedIn): ?>
                <a href="/account" class="hidden sm:inline-flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100/80 text-brand-700 px-3 py-1.5 rounded-full border border-emerald-200/60 transition text-xs font-bold" title="My Account">
                    <span class="w-5 h-5 rounded-full bg-brand-600 text-white flex items-center justify-center text-[10px]">
                        <?= strtoupper(substr($currentCustomer['name'] ?? 'U', 0, 1)) ?>
                    </span>
                    <span class="truncate max-w-[100px]"><?= e(explode(' ', $currentCustomer['name'] ?? 'Account')[0]) ?></span>
                </a>
                <?php else: ?>
                <a href="/login" class="hidden sm:inline-flex items-center gap-1.5 text-xs font-bold text-brand-600 hover:text-brand-700 bg-brand-50 hover:bg-brand-100/70 px-3 py-1.5 rounded-full border border-brand-200 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span>Log In</span>
                </a>
                <?php endif; ?>

                <!-- Mobile Hamburger Menu Button -->
                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden text-gray-700 hover:text-brand-600 p-1.5" aria-label="Toggle menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Inline Search Drawer -->
        <div x-show="searchOpen" x-cloak class="py-3 border-t border-gray-100">
            <form action="/shop" method="GET" class="flex gap-2">
                <input type="text" name="q" placeholder="Search fresh tender coconut water..." class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <button type="submit" class="bg-brand-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-700">Search</button>
            </form>
        </div>
    </div>

    <!-- Mobile Slideover Drawer -->
    <div x-show="mobileMenuOpen" x-cloak class="md:hidden border-t border-gray-100 bg-white px-4 pt-3 pb-6 space-y-3 shadow-lg">
        <div class="flex justify-between items-center pb-2 border-b border-gray-100">
            <span class="text-xs uppercase font-semibold text-gray-400">Navigation</span>
            <button @click="mobileMenuOpen = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <?php if ($storeSwitchEnabled): ?>
        <!-- Mobile Drawer Store Switcher Banner -->
        <div class="p-3.5 rounded-2xl border <?= $isJuiceMode ? 'bg-gradient-to-r from-emerald-50 to-teal-50 border-emerald-200' : 'bg-gradient-to-r from-orange-50 to-amber-50 border-orange-200' ?>">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl p-1.5 rounded-xl bg-white shadow-2xs"><?= $isJuiceMode ? '🥥' : '🍹' ?></span>
                    <div>
                        <span class="text-xs font-black <?= $isJuiceMode ? 'text-emerald-950' : 'text-orange-950' ?> block"><?= e($switchButtonText) ?></span>
                        <span class="text-[10px] <?= $isJuiceMode ? 'text-emerald-700' : 'text-orange-700' ?>"><?= $isJuiceMode ? 'Tender coconuts & fresh malai' : 'Cold-pressed pure juices & shakes' ?></span>
                    </div>
                </div>
                <button 
                    type="button" 
                    data-switch-store="<?= $switchToStore ?>" 
                    class="store-switch-btn shrink-0 px-3 py-1.5 rounded-xl text-xs font-bold text-white shadow-xs <?= $isJuiceMode ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-orange-600 hover:bg-orange-700' ?>"
                >
                    Switch &rarr;
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Customer Status in Mobile Drawer -->
        <?php if ($isCustomerLoggedIn): ?>
        <div class="bg-emerald-50 rounded-2xl p-3 flex items-center justify-between text-xs">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-brand-600 text-white font-bold flex items-center justify-center text-xs">
                    <?= strtoupper(substr($currentCustomer['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div>
                    <span class="font-bold text-gray-900 block"><?= e($currentCustomer['name']) ?></span>
                    <span class="text-[10px] text-gray-500 font-mono"><?= e($currentCustomer['phone']) ?></span>
                </div>
            </div>
            <a href="/account" class="text-brand-700 font-bold text-xs underline">Manage</a>
        </div>
        <?php else: ?>
        <div class="bg-gray-50 rounded-2xl p-3 flex items-center justify-between gap-2">
            <div class="text-xs text-gray-600">
                <span class="font-bold text-gray-900 block">Welcome, Guest!</span>
                <span class="text-[10px] text-gray-500">Log in for fast checkout & tracking</span>
            </div>
            <div class="flex gap-1.5">
                <a href="/login" class="bg-brand-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-xs">Log In</a>
                <a href="/register" class="bg-white border border-gray-200 text-gray-700 text-xs font-semibold px-2.5 py-1.5 rounded-lg">Sign Up</a>
            </div>
        </div>
        <?php endif; ?>

        <a href="/" class="block text-base font-medium <?= $activePage === 'home' ? 'text-brand-600' : 'text-gray-700' ?>">Home</a>
        <a href="/shop" class="block text-base font-medium <?= $activePage === 'shop' ? 'text-brand-600' : 'text-gray-700' ?>"><?= $isJuiceMode ? 'Juice Menu' : 'Shop Tender Coconut' ?></a>
        <a href="/cart" class="block text-base font-medium <?= $activePage === 'cart' ? 'text-brand-600' : 'text-gray-700' ?>">Cart (<span class="cart-count-badge"><?= $cartCount ?></span>)</a>

        <?php if ($isCustomerLoggedIn): ?>
        <a href="/account" class="block text-base font-medium <?= $activePage === 'profile' ? 'text-brand-600' : 'text-gray-700' ?>">My Account</a>
        <a href="/my-orders" class="block text-base font-medium <?= $activePage === 'track' ? 'text-brand-600' : 'text-gray-700' ?>">My Order History</a>
        <?php else: ?>
        <a href="/track" class="block text-base font-medium <?= $activePage === 'track' ? 'text-brand-600' : 'text-gray-700' ?>">Track Any Order</a>
        <?php endif; ?>

        <a href="/#why-choose-us" @click="mobileMenuOpen = false" class="block text-base font-medium text-gray-700">Why Choose Us</a>
        <a href="/#contact" @click="mobileMenuOpen = false" class="block text-base font-medium text-gray-700">Store Hours & Contact</a>

        <div class="pt-2">
            <button 
                type="button" 
                onclick="handlePwaInstall(); mobileMenuOpen = false;" 
                class="w-full text-left flex items-center justify-between text-base font-medium text-emerald-800 bg-emerald-50/80 hover:bg-emerald-100 p-3 rounded-2xl border border-emerald-200/80 transition"
            >
                <span class="flex items-center gap-2 text-sm font-bold">
                    <span>📲</span>
                    <span>Install App on Phone</span>
                </span>
                <span class="text-[10px] bg-brand-600 text-white font-bold px-2 py-0.5 rounded-full">Fast & Offline</span>
            </button>
        </div>

        <div class="pt-3 border-t border-gray-100 flex flex-col gap-2">
            <?php if ($isCustomerLoggedIn): ?>
            <form action="/logout" method="POST">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                <button type="submit" class="w-full text-left text-xs font-semibold text-red-600 py-1.5 hover:underline">
                    &larr; Log out of customer account
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Discreet PWA Install Banner -->
<div id="pwaInstallBanner" class="hidden bg-[#235d3a] text-white px-4 py-2.5 text-xs shadow-md">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <img src="/assets/icons/icon-192.png" alt="App Icon" class="w-7 h-7 rounded-lg shadow-xs">
            <div>
                <span class="font-bold">Install N.A Coconuts App</span>
                <span class="text-emerald-200 hidden sm:inline"> — Instant home screen access & offline order tracking!</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="handlePwaInstall()" class="bg-white text-emerald-900 font-bold px-3 py-1 rounded-lg text-xs hover:bg-emerald-50 transition shadow-xs">Install</button>
            <button onclick="document.getElementById('pwaInstallBanner').classList.add('hidden'); sessionStorage.setItem('pwa_banner_closed', '1');" class="text-emerald-300 hover:text-white p-1 text-sm font-bold leading-none">&times;</button>
        </div>
    </div>
</div>

<!-- iOS Safari Add to Home Screen Instructions Modal -->
<div id="iosInstallModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4" onclick="if (event.target === this) this.classList.add('hidden')">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 text-center space-y-4 shadow-2xl border border-gray-100">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center p-2">
            <img src="/assets/icons/icon-192.png" alt="App Icon" class="w-full h-full object-contain">
        </div>
        <div>
            <h3 class="text-base font-extrabold text-gray-900">Install N.A Coconuts on iPhone / iPad</h3>
            <p class="text-xs text-gray-500 mt-1">Add this web app to your Home Screen for full screen experience</p>
        </div>
        <div class="bg-gray-50 rounded-2xl p-4 text-left space-y-3 text-xs text-gray-700">
            <div class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-brand-600 text-white font-bold flex items-center justify-center shrink-0 text-[10px]">1</span>
                <span>Tap the <strong>Share button</strong> <span class="text-base">⎋</span> at the bottom of Safari.</span>
            </div>
            <div class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-brand-600 text-white font-bold flex items-center justify-center shrink-0 text-[10px]">2</span>
                <span>Scroll down and select <strong>"Add to Home Screen"</strong> <span class="text-base">⊞</span>.</span>
            </div>
            <div class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-brand-600 text-white font-bold flex items-center justify-center shrink-0 text-[10px]">3</span>
                <span>Tap <strong>Add</strong> in the top-right corner to finish.</span>
            </div>
        </div>
        <button type="button" onclick="document.getElementById('iosInstallModal').classList.add('hidden')" class="w-full bg-brand-600 text-white font-bold text-xs py-2.5 px-4 rounded-xl hover:bg-brand-700 transition">
            Got it!
        </button>
    </div>
</div>

<!-- Flash notification banner -->
<?php $flash = get_flash(); if ($flash): ?>
<div class="max-w-4xl mx-auto px-4 mt-3">
    <div class="p-3.5 rounded-xl text-sm font-medium flex items-center gap-2.5 <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' ?>">
        <?php if ($flash['type'] === 'error'): ?>
            <svg class="w-5 h-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <?php else: ?>
            <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <?php endif; ?>
        <span><?= e($flash['message']) ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Main Content Area -->
<main class="flex-grow">
