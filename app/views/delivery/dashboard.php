<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Delivery Partner Dashboard - N.A Fresh Coconuts</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link rel="manifest" href="/manifest.webmanifest">
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

        <!-- Flash messages -->
        <?php if ($msg = get_flash('success')): ?>
            <div class="p-3 bg-emerald-950/80 border border-emerald-500/50 rounded-2xl text-emerald-200 text-xs">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($msg = get_flash('error')): ?>
            <div class="p-3 bg-red-950/80 border border-red-500/50 rounded-2xl text-red-200 text-xs">
                <?= e($msg) ?>
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
                                    @click="acceptOrder(order.id)"
                                    :disabled="order._accepting"
                                    class="py-2.5 px-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 active:scale-95 text-white font-black text-xs rounded-xl shadow-lg shadow-emerald-500/25 transition flex items-center justify-center gap-1.5">
                                <span x-show="!order._accepting" class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Accept Delivery</span>
                                </span>
                                <span x-show="order._accepting" class="flex items-center gap-1">
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
            availableOrders: <?= json_encode($availableOrders) ?>,
            knownOrderIds: new Set(<?= json_encode(array_column($availableOrders, 'id')) ?>),
            pollTimer: null,
            pollCounter: 6,
            audioCtx: null,

            init() {
                this.startPolling();
                // Send an initial GPS ping if geolocation permitted
                this.pingLocation();
            },

            playChime() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime); // A5 note
                    osc.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.15); // E6
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
                        // Check if new orders arrived
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
                const target = this.availableOrders.find(o => o.id === orderId);
                if (target) target._accepting = true;

                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch('/api/delivery/accept-order', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({ order_id: orderId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.href = '/delivery/order?id=' + orderId;
                    } else {
                        alert(data.error || 'Failed to accept order.');
                        this.fetchAvailableOrders();
                    }
                } catch (e) {
                    alert('Network error accepting order.');
                } finally {
                    if (target) target._accepting = false;
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
                    err => {},
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 10000 }
                );
            }
        };
    }
    </script>
</body>
</html>
