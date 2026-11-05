<?php
/**
 * Track Order View
 * N.A Fresh Fruits & Coconuts
 * Blinkit-Style Live GPS & OTP Order Tracking
 */
$pageTitle = 'Track Order - N.A Fresh Fruits & Coconuts';
$activePage = 'track';
require_once __DIR__ . '/layouts/header.php';

$searched = !empty($_GET['num']) || !empty($_GET['phone']) || !empty($_GET['h']);
$order = $foundOrder ?? null;

$rider = null;
$otpInfo = null;
$timeline = [];
if ($order) {
    if (!empty($order['delivery_boy_id'])) {
        $rider = DeliveryBoy::getById((int)$order['delivery_boy_id']);
    }
    $otpInfo = DeliveryDispatchService::getOtpInfo((int)$order['id']);
    $timeline = DeliveryDispatchService::getTimeline((int)$order['id']);
}
?>

<!-- Google Maps Platform JavaScript API with Geometry Library -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyABvsHD8lil-4uRWC5lUT_qIQgGMpV0wGk&libraries=geometry"></script>

<div class="max-w-xl mx-auto px-4 py-6 sm:py-10" x-data="customerTracker(<?= htmlspecialchars(json_encode([
    'orderId' => $order ? (int)$order['id'] : null,
    'trackingHash' => $order['tracking_hash'] ?? '',
    'workflowStatus' => $order['delivery_workflow_status'] ?? 'unassigned',
    'fulfilmentMethod' => $order['fulfilment_method'] ?? 'delivery',
    'customerLat' => !empty($order['latitude']) ? (float)$order['latitude'] : 23.635784,
    'customerLng' => !empty($order['longitude']) ? (float)$order['longitude'] : 75.124683,
    'riderLat' => $rider && $rider['current_lat'] ? (float)$rider['current_lat'] : null,
    'riderLng' => $rider && $rider['current_lng'] ? (float)$rider['current_lng'] : null,
    'plainOtp' => $otpInfo['plain_otp_for_dev'] ?? null,
    'isOtpVerified' => !empty($otpInfo['verified_at'])
]), ENT_QUOTES, 'UTF-8') ?>)">
    
    <div class="text-center mb-6">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200 mb-2">
            ⚡ 10-25 Min Fresh Harvest Delivery
        </span>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
            Live Order Tracking
        </h1>
        <p class="text-xs sm:text-sm text-gray-500 mt-1 max-w-sm mx-auto">
            Real-time GPS tracking and live harvest status from N.A store counter to your doorstep.
        </p>
    </div>

    <!-- Search Form (if not searching by direct link) -->
    <?php if (!$searched || !$order): ?>
    <div class="bg-white rounded-3xl p-5 border border-gray-100 shadow-sm mb-6" x-data="{ tab: 'number' }">
        <div class="flex border-b border-gray-100 pb-3 mb-4 gap-4 text-xs font-bold">
            <button type="button" @click="tab = 'number'" :class="tab === 'number' ? 'text-brand-600 border-b-2 border-brand-600 pb-1' : 'text-gray-400 hover:text-gray-600'">
                By Order Number
            </button>
            <button type="button" @click="tab = 'phone'" :class="tab === 'phone' ? 'text-brand-600 border-b-2 border-brand-600 pb-1' : 'text-gray-400 hover:text-gray-600'">
                By Phone Number
            </button>
        </div>

        <form action="/track" method="GET" class="space-y-4">
            <div x-show="tab === 'number'">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Order Number (e.g. NA1024)</label>
                <input 
                    type="text" 
                    name="num" 
                    value="<?= e($_GET['num'] ?? '') ?>" 
                    placeholder="Enter order number (e.g. NA1024)" 
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"
                >
            </div>

            <div x-show="tab === 'phone'" x-cloak>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Your Mobile Number</label>
                <input 
                    type="tel" 
                    name="phone" 
                    value="<?= e($_GET['phone'] ?? '') ?>" 
                    placeholder="Enter 10-digit mobile number" 
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"
                >
            </div>

            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm py-3 px-4 rounded-xl shadow-xs transition">
                Find My Order &rarr;
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Order Result Display -->
    <?php if ($searched && $order): ?>
    <div class="space-y-5">
        
        <!-- Order Header Card -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm flex flex-col sm:flex-row justify-between sm:items-center gap-3">
            <div>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Order Reference</span>
                <h2 class="text-xl sm:text-2xl font-black text-gray-900">#<?= e($order['order_number']) ?></h2>
                <span class="text-xs text-gray-500 block mt-0.5">Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider border shadow-xs"
                      :class="statusBadgeClass"
                      x-text="statusBadgeText">
                    <?= e(strtoupper(str_replace('_', ' ', $order['delivery_workflow_status'] !== 'unassigned' ? $order['delivery_workflow_status'] : $order['order_status']))) ?>
                </span>
            </div>
        </div>

        <!-- ORDER COMPLETED & DELIVERED BANNER -->
        <template x-if="workflowStatus === 'delivered'">
            <div class="bg-gradient-to-r from-emerald-600 via-green-600 to-teal-700 text-white rounded-3xl p-6 sm:p-7 shadow-xl space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-2xl font-black">
                        ✅
                    </div>
                    <div>
                        <span class="px-2.5 py-0.5 rounded-full bg-white/20 text-[10px] font-black uppercase tracking-wider border border-white/30">
                            Order Completed & Delivered
                        </span>
                        <h3 class="text-lg sm:text-xl font-black mt-1">Delivery Successfully Completed</h3>
                        <p class="text-xs text-emerald-100">Your fresh harvest was handed over and verified via OTP. Thank you for choosing N.A Fresh Fruits & Coconuts!</p>
                    </div>
                </div>
                <div class="pt-3 border-t border-white/20 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <span class="text-xs text-emerald-100">Order workflow closed • Tax invoice ready</span>
                    <a href="/account/orders/invoice/download?num=<?= urlencode($order['order_number']) ?>&h=<?= urlencode($order['tracking_hash']) ?>"
                       class="w-full sm:w-auto px-6 py-3 bg-white text-emerald-950 font-black text-xs rounded-2xl shadow-lg hover:bg-emerald-50 active:scale-95 transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Download Tax Invoice (PDF)</span>
                    </a>
                </div>
            </div>
        </template>

        <!-- BLINKIT-STYLE CUSTOMER DELIVERY OTP BANNER (Only when out for delivery / picked up) -->
        <template x-if="workflowStatus === 'out_for_delivery' || workflowStatus === 'picked_up'">
            <div class="bg-gradient-to-r from-emerald-600 to-green-600 text-white rounded-3xl p-5 shadow-lg relative overflow-hidden">
                <div class="relative z-10 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
                    <div>
                        <span class="px-2.5 py-0.5 rounded-md bg-white/20 text-[10px] font-black uppercase tracking-wider border border-white/30">
                            Delivery Confirmation Code
                        </span>
                        <h3 class="text-sm font-bold mt-1 text-emerald-100">Share this OTP with your delivery partner:</h3>
                    </div>
                    <div class="bg-white text-emerald-950 px-5 py-2.5 rounded-2xl shadow-md">
                        <span class="font-mono text-2xl font-black tracking-widest" x-text="plainOtp || '••••'"></span>
                    </div>
                </div>
            </div>
        </template>

        <!-- LIVE GPS DELIVERY MAP (For Home Deliveries when order is active) -->
        <?php if ($order['fulfilment_method'] === 'delivery'): ?>
        <div x-show="workflowStatus !== 'delivered' && workflowStatus !== 'cancelled'" class="bg-white rounded-3xl p-4 sm:p-5 border border-gray-100 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-extrabold text-gray-900 flex items-center gap-1.5">
                        <span>🛵</span>
                        <span>Live Delivery Partner Radar</span>
                    </h3>
                    <p class="text-xs text-gray-500" x-text="liveMessage"></p>
                </div>
                <div class="text-right">
                    <span class="text-[11px] font-bold text-brand-700 block" x-text="etaText || 'Calculating ETA...'"></span>
                </div>
            </div>

            <!-- Leaflet/Google Map Container -->
            <div id="customerLiveMap" class="w-full h-56 sm:h-64 rounded-2xl border border-gray-200 bg-gray-50 shadow-inner"></div>

            <div class="flex items-center justify-between text-[11px] text-gray-500 px-1 pt-1">
                <span>🟢 Store &nbsp; 🛵 Delivery Partner &nbsp; 📍 Your Address</span>
                <span class="text-emerald-600 font-bold flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>Live GPS Active</span>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ASSIGNED RIDER INFO CARD (If Assigned) -->
        <template x-if="rider">
            <div class="bg-white rounded-3xl p-4 sm:p-5 border border-gray-100 shadow-sm flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 border border-emerald-200 text-emerald-800 flex items-center justify-center text-xl font-bold shadow-xs">
                        🛵
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Delivery Partner</span>
                        <h4 class="text-sm font-extrabold text-gray-900" x-text="rider.name"></h4>
                        <p class="text-xs text-gray-500" x-text="rider.vehicle_type || 'Blinkit Express Rider'"></p>
                    </div>
                </div>

                <a :href="'tel:' + rider.mobile"
                   class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    <span>Call</span>
                </a>
            </div>
        </template>

        <!-- ORDER ITEMS & SUMMARY -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Order Items</h3>
            <div class="divide-y divide-gray-100 text-xs">
                <?php foreach ($order['items'] as $item): ?>
                    <div class="py-2.5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-gray-100 text-gray-700 flex items-center justify-center font-bold text-[11px]">
                                <?= (int)$item['quantity'] ?>x
                            </span>
                            <div>
                                <strong class="text-gray-900 block"><?= e($item['product_name']) ?></strong>
                                <?php if (!empty($item['cutting_option']) && $item['cutting_option'] !== 'whole'): ?>
                                    <span class="text-[10px] text-emerald-600">Option: <?= e(ucwords(str_replace('_', ' ', $item['cutting_option']))) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="font-bold text-gray-800">₹<?= number_format((float)$item['total_price'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <span class="text-xs text-gray-400 font-semibold block">Total Bill</span>
                    <strong class="text-lg font-black text-brand-700">₹<?= number_format((float)$order['total_amount'], 2) ?></strong>
                </div>
                <div>
                    <span class="px-3 py-1 rounded-xl font-bold text-xs <?= in_array($order['payment_status'], ['paid', 'captured'], true) ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' ?>">
                        <?= in_array($order['payment_status'], ['paid', 'captured'], true) ? 'Paid Online' : 'Cash on Delivery (Pending)' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- FULL TIMELINE AUDIT -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm space-y-3">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Live Delivery Timeline</h3>
            <div class="space-y-3 relative border-l-2 border-emerald-200 ml-2 pl-4 text-xs">
                <?php foreach ($timeline as $t): ?>
                    <div class="relative">
                        <span class="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                        <div class="flex items-center justify-between">
                            <strong class="text-gray-900"><?= e($t['title']) ?></strong>
                            <span class="text-[10px] text-gray-400"><?= date('h:i A', strtotime($t['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($t['description'])): ?>
                            <p class="text-[11px] text-gray-500 mt-0.5"><?= e($t['description']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
    <?php endif; ?>
</div>

<script>
function customerTracker(initialData) {
    return {
        orderId: initialData.orderId,
        trackingHash: initialData.trackingHash,
        workflowStatus: initialData.workflowStatus,
        fulfilmentMethod: initialData.fulfilmentMethod,
        customerLat: initialData.customerLat,
        customerLng: initialData.customerLng,
        riderLat: initialData.riderLat,
        riderLng: initialData.riderLng,
        plainOtp: initialData.plainOtp,
        isOtpVerified: initialData.isOtpVerified,
        rider: <?= json_encode($rider) ?>,
        map: null,
        riderMarker: null,
        storeMarker: null,
        destMarker: null,
        storeLat: 23.635784,
        storeLng: 75.124683,
        etaText: '',
        pollTimer: null,

        get statusBadgeText() {
            const map = {
                'unassigned': 'ORDER CONFIRMED',
                'accepted': 'PARTNER ASSIGNED',
                'reached_shop': 'AT STORE FOR PICKUP',
                'picked_up': 'PACKED & PICKED UP',
                'out_for_delivery': 'OUT FOR DELIVERY',
                'delivered': 'DELIVERED ✓',
                'cancelled': 'CANCELLED'
            };
            return map[this.workflowStatus] || this.workflowStatus.toUpperCase();
        },

        get statusBadgeClass() {
            if (this.workflowStatus === 'delivered') return 'bg-emerald-100 text-emerald-800 border-emerald-300';
            if (this.workflowStatus === 'out_for_delivery') return 'bg-amber-100 text-amber-800 border-amber-300 animate-pulse';
            if (this.workflowStatus === 'cancelled') return 'bg-red-100 text-red-800 border-red-300';
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        },

        get liveMessage() {
            if (this.workflowStatus === 'delivered') return 'Order successfully delivered to your address.';
            if (this.workflowStatus === 'out_for_delivery') return 'Delivery partner is on the road heading to you.';
            if (this.workflowStatus === 'picked_up') return 'Fresh coconuts picked up from store counter.';
            if (this.workflowStatus === 'reached_shop') return 'Partner has arrived at store to pick up items.';
            if (this.workflowStatus === 'accepted') return 'Partner assigned and driving to store.';
            return 'Fresh harvest being prepared at store.';
        },

        init() {
            if (this.fulfilmentMethod === 'delivery' && this.orderId) {
                this.$nextTick(() => {
                    this.initCustomerMap();
                    this.startLivePolling();
                });
            }
        },

        initCustomerMap() {
            const container = document.getElementById('customerLiveMap');
            if (!container || typeof google === 'undefined' || !google.maps) return;

            const centerCoords = { lat: this.storeLat, lng: this.storeLng };

            this.map = new google.maps.Map(container, {
                center: centerCoords,
                zoom: 14,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                zoomControl: false
            });

            // Draw Jaora boundary
            const jaoraCoords = [
                { lat: 23.6300, lng: 75.1050 },
                { lat: 23.6500, lng: 75.1100 },
                { lat: 23.6580, lng: 75.1250 },
                { lat: 23.6550, lng: 75.1400 },
                { lat: 23.6420, lng: 75.1480 },
                { lat: 23.6220, lng: 75.1450 },
                { lat: 23.6120, lng: 75.1320 },
                { lat: 23.6150, lng: 75.1150 }
            ];
            new google.maps.Polygon({
                paths: jaoraCoords,
                strokeColor: '#059669',
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: '#10b981',
                fillOpacity: 0.08,
                map: this.map
            });

            // Store HQ Pin (Fixed location: 23.635784, 75.124683)
            this.storeMarker = new google.maps.Marker({
                position: { lat: this.storeLat, lng: this.storeLng },
                map: this.map,
                title: 'N.A Fresh Fruits & Coconuts (Store HQ)',
                label: { text: '🏬', fontSize: '18px' }
            });

            // Customer Destination Pin
            this.destMarker = new google.maps.Marker({
                position: { lat: this.customerLat, lng: this.customerLng },
                map: this.map,
                title: 'Your Delivery Address',
                label: { text: '📍', fontSize: '18px' }
            });

            // Initial rider marker if available
            if (this.riderLat && this.riderLng) {
                this.updateRiderMarker(this.riderLat, this.riderLng);
            }

            this.recenter();
        },

        updateRiderMarker(lat, lng) {
            if (!this.map || typeof google === 'undefined' || !google.maps) return;
            const rPos = { lat: lat, lng: lng };

            if (this.riderMarker) {
                this.riderMarker.setPosition(rPos);
            } else {
                this.riderMarker = new google.maps.Marker({
                    position: rPos,
                    map: this.map,
                    title: 'Delivery Partner',
                    label: { text: '🛵', fontSize: '20px' }
                });
            }

            // Calculate ETA
            const R = 6371;
            const dLat = (this.customerLat - lat) * Math.PI / 180;
            const dLon = (this.customerLng - lng) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat * Math.PI / 180) * Math.cos(this.customerLat * Math.PI / 180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            const distKm = Math.round(R * c * 1.3 * 10) / 10;
            const minutes = Math.max(2, Math.ceil((distKm / 20) * 60) + 1);

            this.etaText = `Partner arriving in ~${minutes} mins (${distKm} km)`;
        },

        recenter() {
            if (!this.map || typeof google === 'undefined' || !google.maps) return;
            const bounds = new google.maps.LatLngBounds();
            bounds.extend({ lat: this.storeLat, lng: this.storeLng });
            bounds.extend({ lat: this.customerLat, lng: this.customerLng });
            if (this.riderMarker && this.riderMarker.getPosition()) {
                bounds.extend(this.riderMarker.getPosition());
            }
            this.map.fitBounds(bounds);
        },

        startLivePolling() {
            if (this.workflowStatus === 'delivered' || this.workflowStatus === 'cancelled') return;

            this.pollTimer = setInterval(async () => {
                try {
                    const res = await fetch('/api/customer/order-tracking?h=' + encodeURIComponent(this.trackingHash));
                    const data = await res.json();
                    if (data.success) {
                        this.workflowStatus = data.delivery_workflow_status;
                        if (data.plain_otp) this.plainOtp = data.plain_otp;
                        if (data.rider) this.rider = data.rider;
                        if (data.rider && data.rider.current_lat && data.rider.current_lng) {
                            this.updateRiderMarker(parseFloat(data.rider.current_lat), parseFloat(data.rider.current_lng));
                        }
                        if (this.workflowStatus === 'delivered') {
                            clearInterval(this.pollTimer);
                            window.location.reload();
                        }
                    }
                } catch(e) {}
            }, 7000);
        }
    };
}
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
