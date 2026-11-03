<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Active Delivery #<?= e($order['order_number']) ?> - Partner Panel</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#064e3b">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <!-- Leaflet CSS & JS for Live Delivery Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full bg-slate-950 text-slate-100 font-sans antialiased pb-28"
      x-data="activeDeliveryController()">

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-md border-b border-emerald-900/40 px-4 py-3 flex items-center justify-between shadow-lg">
        <div class="flex items-center gap-3">
            <a href="/delivery/dashboard" class="p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div>
                <span class="text-[10px] uppercase tracking-wider font-extrabold text-emerald-400">Order Dispatch</span>
                <h1 class="text-base font-black text-white">#<?= e($order['order_number']) ?></h1>
            </div>
        </div>

        <!-- Live GPS Signal Indicator -->
        <div class="flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold border transition"
             :class="gpsActive ? 'bg-emerald-950/80 text-emerald-400 border-emerald-500/50' : 'bg-amber-950/80 text-amber-400 border-amber-500/50'">
            <span class="w-2 h-2 rounded-full" :class="gpsActive ? 'bg-emerald-400 animate-ping' : 'bg-amber-400'"></span>
            <span x-text="gpsStatusText"></span>
        </div>
    </header>

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

        <!-- Blinkit-Style Workflow Steps Progress Bar -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-4 shadow-xl">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Delivery Progress</h2>
            <div class="relative flex items-center justify-between text-center">
                <!-- Connecting lines -->
                <div class="absolute left-4 right-4 top-1/2 -translate-y-1/2 h-1 bg-slate-800 -z-0"></div>
                <div class="absolute left-4 top-1/2 -translate-y-1/2 h-1 bg-emerald-500 transition-all duration-500 -z-0"
                     :style="'width: ' + progressPercent + '%'"></div>

                <!-- Step 1: Accepted -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shadow-md"
                         :class="isStepComplete('accepted') ? 'bg-emerald-500 text-slate-950 ring-4 ring-emerald-500/20' : 'bg-slate-800 text-slate-400'">
                        ✓
                    </div>
                    <span class="text-[10px] font-bold mt-1.5" :class="currentStep === 'accepted' ? 'text-emerald-400' : 'text-slate-400'">Accepted</span>
                </div>

                <!-- Step 2: Reached Shop -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shadow-md"
                         :class="isStepComplete('reached_shop') ? 'bg-emerald-500 text-slate-950 ring-4 ring-emerald-500/20' : (currentStep === 'reached_shop' ? 'bg-emerald-600 text-white animate-pulse' : 'bg-slate-800 text-slate-400')">
                        2
                    </div>
                    <span class="text-[10px] font-bold mt-1.5" :class="currentStep === 'reached_shop' ? 'text-emerald-400' : 'text-slate-400'">At Store</span>
                </div>

                <!-- Step 3: Picked Up -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shadow-md"
                         :class="isStepComplete('picked_up') ? 'bg-emerald-500 text-slate-950 ring-4 ring-emerald-500/20' : (currentStep === 'picked_up' ? 'bg-emerald-600 text-white animate-pulse' : 'bg-slate-800 text-slate-400')">
                        3
                    </div>
                    <span class="text-[10px] font-bold mt-1.5" :class="currentStep === 'picked_up' ? 'text-emerald-400' : 'text-slate-400'">Picked Up</span>
                </div>

                <!-- Step 4: Out for Delivery -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shadow-md"
                         :class="isStepComplete('out_for_delivery') ? 'bg-emerald-500 text-slate-950 ring-4 ring-emerald-500/20' : (currentStep === 'out_for_delivery' ? 'bg-emerald-500 text-slate-950 ring-4 ring-emerald-500/30 animate-bounce' : 'bg-slate-800 text-slate-400')">
                        4
                    </div>
                    <span class="text-[10px] font-bold mt-1.5" :class="currentStep === 'out_for_delivery' ? 'text-emerald-400' : 'text-slate-400'">On The Way</span>
                </div>

                <!-- Step 5: Delivered -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shadow-md"
                         :class="currentStep === 'delivered' ? 'bg-emerald-400 text-slate-950 ring-4 ring-emerald-400/30' : 'bg-slate-800 text-slate-400'">
                        5
                    </div>
                    <span class="text-[10px] font-bold mt-1.5" :class="currentStep === 'delivered' ? 'text-emerald-400' : 'text-slate-400'">Delivered</span>
                </div>
            </div>
        </div>

        <!-- Interactive Live GPS Map -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-3 shadow-xl overflow-hidden">
            <div class="flex items-center justify-between mb-2 px-1">
                <span class="text-xs font-bold text-white flex items-center gap-1.5">
                    <span>🗺️</span>
                    <span>Live Route & GPS Radar</span>
                </span>
                <span class="text-[11px] font-bold text-emerald-400" x-text="etaText || 'Calculating ETA...'"></span>
            </div>
            <div id="deliveryMap" class="w-full h-48 sm:h-56 rounded-2xl border border-slate-800 bg-slate-950"></div>
            <div class="mt-2 text-[11px] text-slate-400 flex items-center justify-between px-1">
                <span>🟢 Store &nbsp; 🛵 Rider &nbsp; 📍 Customer</span>
                <button type="button" @click="recenterMap()" class="text-emerald-400 hover:underline">Re-center</button>
            </div>
        </div>

        <!-- Customer & Delivery Destination Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-4 shadow-xl space-y-3">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Customer Details</span>
                    <h3 class="text-base font-extrabold text-white mt-0.5"><?= e($order['customer_name']) ?></h3>
                </div>
                <!-- Call button -->
                <a href="tel:<?= e($order['customer_phone']) ?>"
                   class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl flex items-center gap-1.5 shadow-md shadow-emerald-600/30 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    <span>Call Customer</span>
                </a>
            </div>

            <div class="pt-2 border-t border-slate-800 space-y-1 text-xs">
                <div class="flex items-start gap-2">
                    <span class="text-slate-500">📍</span>
                    <div class="flex-1">
                        <strong class="text-white block"><?= e($order['delivery_area_name'] ?? 'Jaora Delivery Area') ?></strong>
                        <span class="text-slate-300"><?= nl2br(e($order['delivery_address'])) ?></span>
                    </div>
                </div>
                <?php if (!empty($order['notes'])): ?>
                    <div class="p-2 rounded-xl bg-slate-800/80 text-[11px] text-amber-300 flex items-start gap-1.5 mt-2">
                        <span>📝</span>
                        <span>Customer Note: <?= e($order['notes']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Items Preview Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-4 shadow-xl">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5">Items to Deliver</h3>
            <div class="divide-y divide-slate-800">
                <?php foreach ($items as $item): ?>
                    <div class="py-2 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-emerald-950/80 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-bold text-[11px]">
                                <?= (int)$item['quantity'] ?>x
                            </span>
                            <div>
                                <strong class="text-white block"><?= e($item['product_name']) ?></strong>
                                <?php if (!empty($item['cutting_option']) && $item['cutting_option'] !== 'whole'): ?>
                                    <span class="text-[10px] text-emerald-300">Option: <?= e(ucwords(str_replace('_', ' ', $item['cutting_option']))) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="font-bold text-slate-300">₹<?= number_format((float)$item['total_price'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Total Amount & Payment Status -->
            <div class="mt-3 pt-3 border-t border-slate-800 flex items-center justify-between">
                <div>
                    <span class="text-[11px] text-slate-400 block">Total Amount</span>
                    <strong class="text-lg font-black text-emerald-400">₹<?= number_format((float)$order['total_amount'], 2) ?></strong>
                </div>

                <div>
                    <?php if (in_array($order['payment_status'], ['paid', 'captured'], true)): ?>
                        <span class="px-3 py-1.5 bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 font-bold text-xs rounded-xl flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                            <span>Paid Online (Verified)</span>
                        </span>
                    <?php else: ?>
                        <span class="px-3 py-1.5 bg-amber-500/20 border border-amber-500/40 text-amber-300 font-bold text-xs rounded-xl flex items-center gap-1.5">
                            <span>💵</span>
                            <span>Cash on Delivery (Pending)</span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PAYMENT COLLECTION SECTION (When Out for Delivery & Payment Pending) -->
        <?php if (!in_array($order['payment_status'], ['paid', 'captured'], true)): ?>
            <div class="bg-gradient-to-br from-amber-950/40 via-slate-900 to-slate-900 border border-amber-500/40 rounded-3xl p-4 shadow-xl space-y-3"
                 x-show="currentStep === 'out_for_delivery'">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">💳</span>
                        <h3 class="text-sm font-bold text-white">Payment Required at Delivery</h3>
                    </div>
                    <span class="text-sm font-black text-amber-400">₹<?= number_format((float)$order['total_amount'], 2) ?></span>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-1">
                    <!-- Option A: Collect Cash (COD) -->
                    <button type="button"
                            @click="collectCash()"
                            :disabled="processingPayment"
                            class="py-3 px-3 bg-amber-600 hover:bg-amber-500 active:scale-95 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-amber-600/30 transition flex items-center justify-center gap-1.5">
                        <span class="text-base">💵</span>
                        <span>Cash Collected</span>
                    </button>

                    <!-- Option B: Generate Razorpay Dynamic UPI QR -->
                    <button type="button"
                            @click="generateDynamicQr()"
                            :disabled="processingPayment"
                            class="py-3 px-3 bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-1.5">
                        <span class="text-base">📱</span>
                        <span>Generate UPI QR</span>
                    </button>
                </div>

                <!-- Dynamic QR Display Container -->
                <div x-show="qrGenerated" x-cloak class="p-4 bg-slate-950 border border-indigo-500/40 rounded-2xl text-center space-y-3">
                    <h4 class="text-xs font-bold text-indigo-300 uppercase tracking-wider">Razorpay Dynamic UPI QR</h4>
                    <div class="w-48 h-48 mx-auto bg-white p-2 rounded-2xl shadow-lg flex items-center justify-center">
                        <img :src="qrImageUrl" alt="Razorpay QR" class="w-full h-full object-contain">
                    </div>
                    <p class="text-xs text-slate-300">
                        Ask customer to scan with any UPI app (GPay, PhonePe, Paytm).
                    </p>
                    <div class="flex items-center justify-center gap-2 text-xs text-emerald-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <span x-text="qrChecking ? 'Checking for payment...' : 'Ready for payment'"></span>
                    </div>
                </div>

                <!-- QR Error / Merchant Setup Notice -->
                <div x-show="qrError" x-cloak class="p-3 bg-slate-800 rounded-xl text-xs text-amber-200 border border-amber-500/30">
                    <p class="font-bold">Razorpay UPI QR Info:</p>
                    <p class="text-[11px] mt-1 text-slate-300" x-text="qrError"></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- WORKFLOW ACTION BUTTON CONTAINER -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-4 shadow-xl">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Action Required</h3>

            <!-- State 1: Accepted -> Reached Shop -->
            <div x-show="currentStep === 'accepted'">
                <p class="text-xs text-slate-300 mb-3">
                    Head to the store counter to pick up the customer's fresh harvest order.
                </p>
                <button type="button"
                        @click="advanceWorkflow('reached_shop')"
                        :disabled="actionLoading"
                        class="w-full py-4 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 active:scale-95 text-white font-black text-sm rounded-2xl shadow-lg shadow-emerald-500/30 transition flex items-center justify-center gap-2">
                    <span>🏬 I Have Reached Store</span>
                </button>
            </div>

            <!-- State 2: Reached Shop -> Picked Up -->
            <div x-show="currentStep === 'reached_shop'">
                <p class="text-xs text-slate-300 mb-3">
                    Check the items on counter and pack them securely on your bike.
                </p>
                <button type="button"
                        @click="advanceWorkflow('picked_up')"
                        :disabled="actionLoading"
                        class="w-full py-4 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 active:scale-95 text-white font-black text-sm rounded-2xl shadow-lg shadow-emerald-500/30 transition flex items-center justify-center gap-2">
                    <span>🥥 Order Picked Up from Store</span>
                </button>
            </div>

            <!-- State 3: Picked Up -> Out for Delivery -->
            <div x-show="currentStep === 'picked_up'">
                <p class="text-xs text-slate-300 mb-3">
                    Start driving towards the customer location. This will activate live customer tracking and generate the customer delivery OTP.
                </p>
                <button type="button"
                        @click="advanceWorkflow('out_for_delivery')"
                        :disabled="actionLoading"
                        class="w-full py-4 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 active:scale-95 text-white font-black text-sm rounded-2xl shadow-lg shadow-emerald-500/30 transition flex items-center justify-center gap-2">
                    <span>🛵 Start Delivery (Out for Delivery)</span>
                </button>
            </div>

            <!-- State 4: Out for Delivery -> Delivered (OTP Verification) -->
            <div x-show="currentStep === 'out_for_delivery'" class="space-y-3">
                <p class="text-xs text-slate-300">
                    Ask the customer for their <strong class="text-emerald-400">4-digit Delivery OTP</strong> displayed on their tracking screen.
                </p>

                <div class="flex items-center gap-2">
                    <input type="number"
                           x-model="enteredOtp"
                           placeholder="Enter 4-digit OTP"
                           maxlength="6"
                           class="flex-1 py-3 px-4 bg-slate-800 border border-slate-700 rounded-2xl text-white text-center text-lg font-black tracking-widest placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <button type="button"
                            @click="completeDeliveryWithOtp()"
                            :disabled="actionLoading || !enteredOtp"
                            class="py-3 px-6 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 active:scale-95 text-white font-black text-sm rounded-2xl shadow-lg shadow-emerald-500/30 transition flex items-center justify-center gap-1.5">
                        <span x-show="!actionLoading">Verify & Deliver</span>
                        <span x-show="actionLoading">Verifying...</span>
                    </button>
                </div>
            </div>

            <!-- State 5: Already Delivered -->
            <div x-show="currentStep === 'delivered'" class="text-center py-3 space-y-2">
                <div class="w-12 h-12 mx-auto rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl font-black">
                    ✓
                </div>
                <h4 class="text-sm font-black text-emerald-400">Order Completed & Delivered!</h4>
                <p class="text-xs text-slate-400">
                    Thank you for the quick and safe delivery.
                </p>
                <a href="/delivery/dashboard"
                   class="inline-block mt-2 px-5 py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-md">
                    Return to Dashboard &rarr;
                </a>
            </div>
        </div>

        <!-- Audit Timeline of Order -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-4 shadow-xl">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Delivery Timeline Audit</h3>
            <div class="space-y-3 relative border-l-2 border-slate-800 ml-2 pl-4 text-xs">
                <?php foreach ($timeline as $t): ?>
                    <div class="relative">
                        <span class="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <div class="flex items-center justify-between">
                            <strong class="text-white"><?= e($t['title']) ?></strong>
                            <span class="text-[10px] text-slate-500"><?= date('h:i A', strtotime($t['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($t['description'])): ?>
                            <p class="text-[11px] text-slate-400 mt-0.5"><?= e($t['description']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>

    <script>
    function activeDeliveryController() {
        return {
            orderId: <?= (int)$order['id'] ?>,
            currentStep: '<?= e($order['delivery_workflow_status']) ?>',
            actionLoading: false,
            enteredOtp: '',
            gpsActive: false,
            gpsStatusText: 'Connecting GPS...',
            currentLat: null,
            currentLng: null,
            storeLat: 23.8920,
            storeLng: 75.1320,
            destLat: <?= !empty($order['latitude']) ? (float)$order['latitude'] : 23.8940 ?>,
            destLng: <?= !empty($order['longitude']) ? (float)$order['longitude'] : 75.1350 ?>,
            etaText: '',
            map: null,
            riderMarker: null,
            storeMarker: null,
            destMarker: null,
            processingPayment: false,
            qrGenerated: false,
            qrImageUrl: '',
            qrId: '',
            qrChecking: false,
            qrError: '',

            get progressPercent() {
                const map = {
                    'unassigned': 0,
                    'accepted': 15,
                    'reached_shop': 40,
                    'picked_up': 65,
                    'out_for_delivery': 85,
                    'delivered': 100
                };
                return map[this.currentStep] || 15;
            },

            isStepComplete(step) {
                const order = ['accepted', 'reached_shop', 'picked_up', 'out_for_delivery', 'delivered'];
                return order.indexOf(this.currentStep) >= order.indexOf(step);
            },

            init() {
                this.initMap();
                this.startGpsTracking();
            },

            initMap() {
                const container = document.getElementById('deliveryMap');
                if (!container) return;

                this.map = L.map('deliveryMap', {
                    zoomControl: false,
                    attributionControl: false
                }).setView([this.storeLat, this.storeLng], 14);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19
                }).addTo(this.map);

                // Store Marker (Green Shop Pin)
                const storeIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: '<div style="background:#059669; width:28px; height:28px; border-radius:50%; border:2px solid #fff; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,0.5); font-size:14px;">🏬</div>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                });
                this.storeMarker = L.marker([this.storeLat, this.storeLng], { icon: storeIcon }).addTo(this.map);
                this.storeMarker.bindPopup('<strong>N.A Fresh Coconuts Store</strong>');

                // Customer Destination Marker (Red Pin)
                const destIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: '<div style="background:#dc2626; width:28px; height:28px; border-radius:50%; border:2px solid #fff; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,0.5); font-size:14px;">📍</div>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                });
                this.destMarker = L.marker([this.destLat, this.destLng], { icon: destIcon }).addTo(this.map);
                this.destMarker.bindPopup('<strong>Customer Destination</strong>');

                // Fit bounds between store and destination initially
                const group = L.featureGroup([this.storeMarker, this.destMarker]);
                this.map.fitBounds(group.getBounds().pad(0.3));
            },

            startGpsTracking() {
                if (!navigator.geolocation) {
                    this.gpsStatusText = 'GPS Not Supported';
                    return;
                }

                this.gpsStatusText = 'Acquiring GPS...';

                navigator.geolocation.watchPosition(
                    pos => {
                        this.gpsActive = true;
                        this.gpsStatusText = 'GPS Active (±' + Math.round(pos.coords.accuracy) + 'm)';
                        this.currentLat = pos.coords.latitude;
                        this.currentLng = pos.coords.longitude;

                        this.updateRiderMarker(this.currentLat, this.currentLng);
                        this.updateEta(this.currentLat, this.currentLng);
                        this.sendGpsPing(pos.coords);
                    },
                    err => {
                        this.gpsActive = false;
                        this.gpsStatusText = 'GPS Denied / Unavailable';
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
                );
            },

            updateRiderMarker(lat, lng) {
                if (!this.map) return;
                const riderIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: '<div style="background:#10b981; width:34px; height:34px; border-radius:50%; border:3px solid #fff; display:flex; align-items:center; justify-content:center; box-shadow:0 0 15px #10b981; font-size:18px;">🛵</div>',
                    iconSize: [34, 34],
                    iconAnchor: [17, 17]
                });

                if (this.riderMarker) {
                    this.riderMarker.setLatLng([lat, lng]);
                } else {
                    this.riderMarker = L.marker([lat, lng], { icon: riderIcon }).addTo(this.map);
                    this.riderMarker.bindPopup('<strong>Your Live Location</strong>');
                }
            },

            updateEta(riderLat, riderLng) {
                // Target is store if before pickup, destination if out for delivery
                const targetLat = (this.currentStep === 'out_for_delivery') ? this.destLat : this.storeLat;
                const targetLng = (this.currentStep === 'out_for_delivery') ? this.destLng : this.storeLng;

                // Haversine
                const R = 6371;
                const dLat = (targetLat - riderLat) * Math.PI / 180;
                const dLon = (targetLng - riderLng) * Math.PI / 180;
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                          Math.cos(riderLat * Math.PI / 180) * Math.cos(targetLat * Math.PI / 180) *
                          Math.sin(dLon/2) * Math.sin(dLon/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                const distKm = Math.round(R * c * 1.3 * 10) / 10;
                const minutes = Math.max(2, Math.ceil((distKm / 20) * 60) + 1);

                this.etaText = `ETA: ~${minutes} mins (${distKm} km)`;
            },

            recenterMap() {
                if (!this.map) return;
                const markers = [this.storeMarker, this.destMarker];
                if (this.riderMarker) markers.push(this.riderMarker);
                const group = L.featureGroup(markers);
                this.map.fitBounds(group.getBounds().pad(0.3));
            },

            sendGpsPing(coords) {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                fetch('/api/delivery/gps-ping', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({
                        order_id: this.orderId,
                        lat: coords.latitude,
                        lng: coords.longitude,
                        heading: coords.heading,
                        speed: coords.speed,
                        accuracy: coords.accuracy
                    })
                }).catch(() => {});
            },

            async advanceWorkflow(targetStatus) {
                this.actionLoading = true;
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch('/api/delivery/update-workflow', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            order_id: this.orderId,
                            target_status: targetStatus,
                            lat: this.currentLat,
                            lng: this.currentLng
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.currentStep = targetStatus;
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to update workflow.');
                    }
                } catch(e) {
                    alert('Network error updating delivery workflow.');
                } finally {
                    this.actionLoading = false;
                }
            },

            async completeDeliveryWithOtp() {
                if (!this.enteredOtp || this.enteredOtp.length < 4) {
                    alert('Please enter the 4-digit Delivery OTP.');
                    return;
                }
                this.actionLoading = true;
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch('/api/delivery/update-workflow', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            order_id: this.orderId,
                            target_status: 'delivered',
                            otp: this.enteredOtp,
                            lat: this.currentLat,
                            lng: this.currentLng
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.currentStep = 'delivered';
                        window.location.reload();
                    } else {
                        alert(data.error || 'Invalid OTP. Please verify with customer.');
                    }
                } catch(e) {
                    alert('Network error verifying delivery OTP.');
                } finally {
                    this.actionLoading = false;
                }
            },

            async collectCash() {
                if (!confirm('Confirm that you have collected exact cash from the customer?')) return;
                this.processingPayment = true;
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch('/api/delivery/record-cod', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            order_id: this.orderId
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        alert('Cash collection recorded successfully!');
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to record cash collection.');
                    }
                } catch(e) {
                    alert('Network error.');
                } finally {
                    this.processingPayment = false;
                }
            },

            async generateDynamicQr() {
                this.processingPayment = true;
                this.qrError = '';
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch('/api/delivery/generate-qr', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            order_id: this.orderId
                        })
                    });
                    const data = await res.json();
                    if (data.success && data.qr_image_url) {
                        this.qrGenerated = true;
                        this.qrImageUrl = data.qr_image_url;
                        this.qrId = data.qr_id;
                        this.startQrPolling();
                    } else {
                        this.qrError = data.error || 'Failed to generate Dynamic QR from Razorpay.';
                        if (data.help_text) {
                            this.qrError += ' ' + data.help_text;
                        }
                    }
                } catch(e) {
                    this.qrError = 'Network error contacting Razorpay gateway.';
                } finally {
                    this.processingPayment = false;
                }
            },

            startQrPolling() {
                if (!this.qrId) return;
                this.qrChecking = true;
                const timer = setInterval(async () => {
                    try {
                        const res = await fetch('/api/delivery/check-qr?qr_id=' + encodeURIComponent(this.qrId) + '&order_id=' + this.orderId);
                        const data = await res.json();
                        if (data.success && data.is_paid) {
                            clearInterval(timer);
                            alert('Payment received and verified via Razorpay UPI!');
                            window.location.reload();
                        }
                    } catch(e) {}
                }, 4000);
            }
        };
    }
    </script>
</body>
</html>
