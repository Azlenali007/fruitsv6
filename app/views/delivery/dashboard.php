<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Delivery Partner Dashboard - N.A Fresh Coconuts</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link rel="manifest" href="/manifest-delboy.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NA Rider">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
    <meta name="theme-color" content="#064e3b">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full bg-slate-950 text-slate-100 font-sans antialiased pb-24"
      x-data="deliveryDashboard()">

    <!-- Top Bar with Online/Offline Switch -->
    <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-md border-b border-emerald-900/40 px-4 py-3 flex items-center justify-between shadow-lg">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-emerald-600 flex items-center justify-center font-black text-white shadow-md shadow-emerald-600/30">
                ⚡
            </div>
            <div>
                <h1 class="text-sm font-bold text-white tracking-tight leading-tight"><?= e($rider['name']) ?></h1>
                <p class="text-[11px] text-slate-400"><?= e($rider['vehicle_type']) ?> • <?= e($rider['mobile']) ?></p>
            </div>
        </div>

        <!-- Online / Offline Toggle Switch -->
        <div class="flex items-center gap-2">
            <span class="text-[11px] font-bold uppercase tracking-wider"
                  :class="isOnline ? 'text-emerald-400' : 'text-slate-500'"
                  x-text="isOnline ? 'ONLINE' : 'OFFLINE'"></span>
            <button type="button"
                    @click="toggleOnline()"
                    :disabled="togglingOnline"
                    class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                    :class="isOnline ? 'bg-emerald-500' : 'bg-slate-700'">
                <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                      :class="isOnline ? 'translate-x-5' : 'translate-x-0'"></span>
            </button>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-lg mx-auto px-4 py-4 space-y-4">

        <!-- PWA Install Banner (Android / Desktop Chrome) -->
        <div x-show="showInstallBanner" x-cloak
             class="bg-gradient-to-r from-emerald-900 to-slate-900 border border-emerald-500/50 rounded-2xl p-3.5 shadow-lg flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-emerald-500 text-slate-950 font-black flex items-center justify-center text-sm shadow-md">
                    📲
                </span>
                <div>
                    <h3 class="text-xs font-bold text-white">Install N.A Rider App</h3>
                    <p class="text-[10px] text-emerald-200">Get instant order alerts & quick full-screen access</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button type="button" @click="installPwa()"
                        class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs rounded-xl shadow-md transition">
                    Install
                </button>
                <button type="button" @click="showInstallBanner = false"
                        class="p-1.5 text-slate-400 hover:text-white rounded-lg transition text-xs">
                    ✕
                </button>
            </div>
        </div>

        <!-- iOS Safari Add to Home Screen Banner -->
        <div x-show="isIos && !isStandalone && showIosBanner" x-cloak
             class="bg-gradient-to-r from-indigo-950 to-slate-900 border border-indigo-500/40 rounded-2xl p-3.5 shadow-lg flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white font-black flex items-center justify-center text-sm shadow-md">
                    🍎
                </span>
                <div>
                    <h3 class="text-xs font-bold text-white">Install on iPhone</h3>
                    <p class="text-[10px] text-indigo-200">Add to Home Screen via Safari</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button type="button" @click="showIosModal = true"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md transition">
                    View Guide
                </button>
                <button type="button" @click="showIosBanner = false"
                        class="p-1.5 text-slate-400 hover:text-white rounded-lg transition text-xs">
                    ✕
                </button>
            </div>
        </div>

        <!-- iOS Installation Instructions Modal -->
        <div x-show="showIosModal" x-cloak
             class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 max-w-sm w-full space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                        <span>📲</span>
                        <span>Install NA Rider on iPhone</span>
                    </h3>
                    <button type="button" @click="showIosModal = false" class="text-slate-400 hover:text-white font-bold text-lg">✕</button>
                </div>
                <div class="space-y-3 text-xs text-slate-300">
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-slate-800 text-emerald-400 font-black flex items-center justify-center shrink-0">1</span>
                        <p>Open this page in <strong>Safari</strong> on your iPhone.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-slate-800 text-emerald-400 font-black flex items-center justify-center shrink-0">2</span>
                        <p>Tap the <strong>Share</strong> button at the bottom of the screen (square icon with an arrow pointing up).</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-slate-800 text-emerald-400 font-black flex items-center justify-center shrink-0">3</span>
                        <p>Scroll down and select <strong>"Add to Home Screen"</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-slate-800 text-emerald-400 font-black flex items-center justify-center shrink-0">4</span>
                        <p>Tap <strong>Add</strong> in the top-right corner. The NA Rider app icon will be ready on your home screen!</p>
                    </div>
                </div>
                <button type="button" @click="showIosModal = false"
                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition">
                    Got it
                </button>
            </div>
        </div>

        <!-- GPS Permission & Tracking Status Widget -->
        <div class="bg-slate-900 border rounded-2xl p-3.5 shadow-md"
             :class="gpsPermission === 'granted' ? 'border-emerald-500/40 bg-slate-900/90' : (gpsPermission === 'denied' ? 'border-red-500/50 bg-red-950/30' : 'border-amber-500/50 bg-amber-950/30')">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full"
                          :class="gpsPermission === 'granted' ? 'bg-emerald-400 animate-ping' : (gpsPermission === 'denied' ? 'bg-red-400' : 'bg-amber-400 animate-pulse')"></span>
                    <span class="text-xs font-bold"
                          :class="gpsPermission === 'granted' ? 'text-emerald-300' : (gpsPermission === 'denied' ? 'text-red-300' : 'text-amber-300')">
                        <span x-show="gpsPermission === 'granted'">GPS Active <span x-text="gpsAccuracy ? '(±' + gpsAccuracy + 'm)' : ''"></span></span>
                        <span x-show="gpsPermission === 'denied'">GPS Permission Denied</span>
                        <span x-show="gpsPermission === 'prompt'">GPS Permission Required</span>
                    </span>
                </div>
                <div>
                    <span x-show="gpsPermission === 'granted'" class="text-[10px] text-slate-400" x-text="lastPingText"></span>
                    <button x-show="gpsPermission !== 'granted'" type="button" @click="requestGpsPermission()"
                            class="px-2.5 py-1 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-[11px] rounded-lg shadow-sm transition">
                        Allow GPS
                    </button>
                </div>
            </div>

            <!-- Permission Explanation / Background Tracking Notice -->
            <div class="mt-2 text-[11px] text-slate-400 border-t border-slate-800/80 pt-2 space-y-1">
                <p x-show="gpsPermission !== 'granted'" class="text-amber-200/90 leading-tight">
                    📍 <strong>Why location is needed:</strong> Real-time coordinates are required for route guidance and accurate customer arrival estimates during active deliveries.
                </p>
                <p class="text-slate-500 leading-tight">
                    ℹ️ <em>Background GPS Note:</em> Mobile browsers suspend background timers when the screen locks or apps switch. Keep your phone unlocked during active transit or reopen the app to refresh your position instantly.
                </p>
            </div>
        </div>

        <!-- Flash messages -->
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="p-3 <?= ($flash['type'] ?? '') === 'error' ? 'bg-red-950/80 border-red-500/50 text-red-200' : 'bg-emerald-950/80 border-emerald-500/50 text-emerald-200' ?> border rounded-2xl text-xs">
                <?= e($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>

        <!-- Active Delivery Alert Card (if any) -->
        <?php if (!empty($activeOrder)): ?>
            <div class="bg-gradient-to-r from-emerald-900 to-green-950 border-2 border-emerald-400 rounded-3xl p-5 shadow-xl animate-pulse">
                <div class="flex items-center justify-between mb-2">
                    <span class="px-2.5 py-1 bg-emerald-500 text-slate-950 text-[10px] font-black uppercase tracking-wider rounded-lg">
                        DELIVERY IN PROGRESS
                    </span>
                    <span class="text-xs font-bold text-emerald-300">#<?= e($activeOrder['order_number']) ?></span>
                </div>
                <h2 class="text-base font-extrabold text-white leading-tight">
                    <?= e($activeOrder['customer_name']) ?>
                </h2>
                <p class="text-xs text-emerald-200/80 mt-1 line-clamp-1">
                    📍 <?= e($activeOrder['delivery_address']) ?>
                </p>
                <div class="mt-3 flex items-center justify-between pt-3 border-t border-emerald-700/50 text-xs">
                    <span class="text-emerald-300 font-semibold">
                        Status: <strong class="text-white uppercase"><?= str_replace('_', ' ', $activeOrder['delivery_workflow_status']) ?></strong>
                    </span>
                    <a href="/delivery/order?id=<?= $activeOrder['id'] ?>"
                       class="px-4 py-2 bg-white text-emerald-950 font-black rounded-xl hover:bg-emerald-100 transition shadow-md">
                        Open Delivery &rarr;
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Rider Quick Performance Widget -->
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5">
                <span class="text-[11px] font-medium text-slate-400 block">Delivered Today</span>
                <span class="text-2xl font-black text-emerald-400 mt-1 block"><?= $stats['total_delivered'] ?></span>
                <span class="text-[10px] text-slate-500">Orders completed</span>
            </div>
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5">
                <span class="text-[11px] font-medium text-slate-400 block">Pending Cash in Hand</span>
                <span class="text-2xl font-black text-amber-400 mt-1 block">₹<?= number_format($stats['pending_cash'], 2) ?></span>
                <span class="text-[10px] text-slate-500">COD to deposit with Admin</span>
            </div>
        </div>

        <!-- Incoming Available Orders Section -->
        <div>
            <div class="flex items-center justify-between mb-3 px-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    <h2 class="text-sm font-extrabold text-white tracking-tight">Available Delivery Orders</h2>
                </div>
                <span class="text-[11px] text-slate-400" x-text="'Auto-refresh: ' + pollCounter + 's'"></span>
            </div>

            <!-- Offline Banner if Rider is Offline -->
            <div x-show="!isOnline" x-cloak class="p-6 bg-slate-900 border border-slate-800 rounded-3xl text-center space-y-3">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-800 flex items-center justify-center text-xl text-slate-400">
                    💤
                </div>
                <h3 class="text-sm font-bold text-white">You are Currently Offline</h3>
                <p class="text-xs text-slate-400 max-w-xs mx-auto">
                    Toggle your status to ONLINE at the top to receive instant incoming order dispatch offers.
                </p>
                <button type="button"
                        @click="toggleOnline()"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 font-bold text-xs rounded-xl text-white shadow-lg shadow-emerald-600/30">
                    Go Online Now
                </button>
            </div>

            <!-- No Orders Available -->
            <div x-show="isOnline && availableOrders.length === 0" x-cloak class="p-8 bg-slate-900/70 border border-slate-800/80 rounded-3xl text-center space-y-3">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-950/60 border border-emerald-500/20 flex items-center justify-center text-2xl text-emerald-400">
                    🛵
                </div>
                <h3 class="text-sm font-bold text-white">Looking for Nearby Deliveries...</h3>
                <p class="text-xs text-slate-400 max-w-xs mx-auto">
                    Stay on this screen. New orders will appear here automatically with sound alert when placed by customers.
                </p>
            </div>

            <!-- Available Orders Cards List -->
            <div x-show="isOnline && availableOrders.length > 0" class="space-y-3">
                <template x-for="order in availableOrders" :key="order.id">
                    <div class="bg-slate-900 border border-emerald-500/40 rounded-3xl p-4 shadow-xl transition-all hover:border-emerald-400">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider"
                                      :class="order.payment_method === 'cod' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'"
                                      x-text="order.payment_method === 'cod' ? 'CASH ON DELIVERY' : 'PAID ONLINE'"></span>
                                <span class="text-xs font-extrabold text-white" x-text="'#' + order.order_number"></span>
                            </div>
                            <span class="text-xs font-black text-emerald-400 text-base" x-text="'₹' + parseFloat(order.total_amount).toFixed(2)"></span>
                        </div>

                        <!-- Customer & Items details -->
                        <div class="mt-3 space-y-1.5 text-xs">
                            <div class="flex items-start gap-2">
                                <span class="text-slate-500">📍</span>
                                <div class="flex-1">
                                    <strong class="text-white block" x-text="order.delivery_area_name || 'Jaora'"></strong>
                                    <span class="text-slate-400 text-[11px]" x-text="order.delivery_address"></span>
                                </div>
                            </div>
                            <div class="flex items-start gap-2 pt-1 text-slate-300 text-[11px]">
                                <span class="text-slate-500">🥥</span>
                                <span class="line-clamp-2" x-text="order.items_preview || (order.item_count + ' items')"></span>
                            </div>
                        </div>

                        <!-- Action Buttons: Accept / Reject -->
                        <div class="mt-4 grid grid-cols-2 gap-2 pt-3 border-t border-slate-800">
                            <button type="button"
                                    @click="rejectOrder(order.id)"
                                    class="py-2.5 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5">
                                <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                <span>Pass</span>
                            </button>

                            <button type="button"
                                    @click.prevent.stop="acceptOrder(order.id)"
                                    :disabled="acceptingOrderId !== null"
                                    class="py-2.5 px-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 active:scale-95 text-white font-black text-xs rounded-xl shadow-lg shadow-emerald-500/25 transition flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="acceptingOrderId !== order.id" class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Accept Delivery</span>
                                </span>
                                <span x-show="acceptingOrderId === order.id" class="flex items-center gap-1.5">
                                    <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>Locking...</span>
                                </span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </main>

    <!-- Bottom Mobile Nav Bar -->
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-slate-900/95 backdrop-blur-md border-t border-slate-800 px-6 py-2 flex items-center justify-around text-[10px] font-semibold text-slate-400">
        <a href="/delivery/dashboard" class="flex flex-col items-center gap-1 text-emerald-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span>Orders</span>
        </a>
        <?php if (!empty($activeOrder)): ?>
        <a href="/delivery/order?id=<?= $activeOrder['id'] ?>" class="flex flex-col items-center gap-1 text-amber-400 font-bold">
            <svg class="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            <span>Active</span>
        </a>
        <?php endif; ?>
        <a href="/delivery/history" class="flex flex-col items-center gap-1 hover:text-white transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>History</span>
        </a>
        <form action="/delivery/logout" method="POST" class="inline">
            <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
            <button type="submit" class="flex flex-col items-center gap-1 text-red-400 hover:text-red-300 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span>Logout</span>
            </button>
        </form>
    </nav>

    <script>
    function deliveryDashboard() {
        return {
            isOnline: <?= $rider['is_online'] ? 'true' : 'false' ?>,
            togglingOnline: false,
            acceptingOrderId: null,
            availableOrders: <?= json_encode($availableOrders) ?>,
            knownOrderIds: new Set(<?= json_encode(array_column($availableOrders, 'id')) ?>),
            pollTimer: null,
            pollCounter: 6,
            audioCtx: null,

            // PWA Install State
            deferredPrompt: null,
            showInstallBanner: false,
            showIosBanner: true,
            showIosModal: false,
            isIos: /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream,
            isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,

            // GPS Tracking State
            gpsPermission: 'prompt',
            gpsAccuracy: null,
            lastPingTime: null,
            lastPingText: '',

            init() {
                this.initPwa();
                this.checkGpsPermission();
                this.startPolling();
                this.setupVisibilityListeners();
                this.pingLocation();
            },

            initPwa() {
                // Register Service Worker
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('/sw-delboy.js', { scope: '/' })
                        .catch(err => console.warn('SW registration warning:', err));
                }

                // Android Chrome / Edge beforeinstallprompt
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    this.deferredPrompt = e;
                    if (!this.isStandalone) {
                        this.showInstallBanner = true;
                    }
                });

                window.addEventListener('appinstalled', () => {
                    this.deferredPrompt = null;
                    this.showInstallBanner = false;
                });
            },

            async installPwa() {
                if (!this.deferredPrompt) return;
                this.deferredPrompt.prompt();
                const choice = await this.deferredPrompt.userChoice;
                if (choice.outcome === 'accepted') {
                    this.showInstallBanner = false;
                }
                this.deferredPrompt = null;
            },

            checkGpsPermission() {
                if (!navigator.geolocation) {
                    this.gpsPermission = 'denied';
                    return;
                }
                if (navigator.permissions && navigator.permissions.query) {
                    navigator.permissions.query({ name: 'geolocation' }).then(result => {
                        this.gpsPermission = result.state;
                        result.onchange = () => {
                            this.gpsPermission = result.state;
                            if (result.state === 'granted') {
                                this.pingLocation();
                            }
                        };
                    }).catch(() => {});
                }
            },

            requestGpsPermission() {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by your device browser.');
                    return;
                }
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        this.gpsPermission = 'granted';
                        this.handleGpsPosition(pos);
                    },
                    err => {
                        this.gpsPermission = 'denied';
                        alert('Location permission denied. Please allow location access in your device settings.');
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
                );
            },

            setupVisibilityListeners() {
                // Resume immediate location ping when rider unlocks phone or returns to app
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        this.pingLocation();
                        if (this.isOnline) {
                            this.fetchAvailableOrders();
                        }
                    }
                });
                window.addEventListener('focus', () => this.pingLocation());
                window.addEventListener('pageshow', () => this.pingLocation());
            },

            handleGpsPosition(pos) {
                this.gpsAccuracy = Math.round(pos.coords.accuracy);
                this.lastPingTime = new Date();
                this.lastPingText = 'Just now';

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                fetch('/api/delivery/gps-ping', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        heading: pos.coords.heading,
                        speed: pos.coords.speed,
                        accuracy: pos.coords.accuracy
                    })
                }).catch(() => {});
            },

            playChime() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.15);
                    gain.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.4);
                } catch(e) {}
            },

            startPolling() {
                this.pollTimer = setInterval(() => {
                    this.pollCounter--;
                    if (this.lastPingTime) {
                        const elapsedSecs = Math.round((new Date() - this.lastPingTime) / 1000);
                        this.lastPingText = elapsedSecs < 10 ? 'Just now' : (elapsedSecs + 's ago');
                    }
                    if (this.pollCounter <= 0) {
                        this.pollCounter = 6;
                        if (this.isOnline) {
                            this.fetchAvailableOrders();
                            this.pingLocation();
                        }
                    }
                }, 1000);
            },

            async fetchAvailableOrders() {
                try {
                    const res = await fetch('/api/delivery/available-orders', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.success && Array.isArray(data.orders)) {
                        let hasNew = false;
                        data.orders.forEach(o => {
                            if (!this.knownOrderIds.has(o.id)) {
                                hasNew = true;
                                this.knownOrderIds.add(o.id);
                            }
                        });
                        if (hasNew) {
                            this.playChime();
                        }
                        this.availableOrders = data.orders;
                    }
                } catch (e) {
                    console.error('Polling error', e);
                }
            },

            async toggleOnline() {
                this.togglingOnline = true;
                const targetState = !this.isOnline;
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch('/api/delivery/toggle-online', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({ is_online: targetState ? 1 : 0 })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.isOnline = targetState;
                        if (this.isOnline) {
                            this.fetchAvailableOrders();
                            this.pingLocation();
                        } else {
                            this.availableOrders = [];
                        }
                    }
                } catch (e) {
                    alert('Network error updating online status.');
                } finally {
                    this.togglingOnline = false;
                }
            },

            async acceptOrder(orderId) {
                if (this.acceptingOrderId !== null) return;
                this.acceptingOrderId = orderId;
                this.pollCounter = 20;

                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch('/api/delivery/accept-order', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ order_id: orderId, csrf_token: csrf })
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.href = data.redirect_url || ('/delivery/order?id=' + orderId);
                    } else {
                        alert(data.error || 'This order could not be accepted or was already claimed.');
                        await this.fetchAvailableOrders();
                    }
                } catch (e) {
                    alert('Network error accepting order. Please check your internet connection.');
                } finally {
                    this.acceptingOrderId = null;
                }
            },

            async rejectOrder(orderId) {
                if (!confirm('Pass on this delivery? It will be offered to other partners.')) return;
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch('/api/delivery/reject-order', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({ order_id: orderId, reason: 'Rider busy or out of range' })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.availableOrders = this.availableOrders.filter(o => o.id !== orderId);
                    }
                } catch (e) {
                    alert('Network error.');
                }
            },

            pingLocation() {
                if (!navigator.geolocation) return;
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        this.gpsPermission = 'granted';
                        this.handleGpsPosition(pos);
                    },
                    err => {
                        if (err.code === err.PERMISSION_DENIED) {
                            this.gpsPermission = 'denied';
                        }
                    },
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 10000 }
                );
            }
        };
    }
    </script>
</body>
</html>
