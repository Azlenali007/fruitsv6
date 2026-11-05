<?php
$activeAdminTab = 'delivery';
$pageTitle = 'Delivery Boy Fleet & Live Dispatch';
require __DIR__ . '/layout-header.php';
?>

<!-- Google Maps Platform JavaScript API with Geometry Library -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyABvsHD8lil-4uRWC5lUT_qIQgGMpV0wGk&libraries=geometry"></script>

<div class="space-y-6" x-data="adminDeliveryApp()">

    <!-- Top Hero Banner & Quick Actions -->
    <div class="bg-gradient-to-r from-emerald-800 via-brand-700 to-emerald-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-white/20 text-emerald-100 border border-white/20">
                        ⚡ Blinkit-Style Fleet Engine
                    </span>
                    <span class="flex items-center gap-1.5 text-xs text-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <span>Auto-Escalation & Live GPS Active</span>
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black mt-2 tracking-tight">Delivery Operations & Dispatch</h1>
                <p class="text-sm text-emerald-100/90 max-w-xl mt-1">
                    Manage delivery partners, monitor real-time GPS locations, oversee atomic order assignments, and reconcile Cash-on-Delivery collections.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button"
                        @click="openAddRiderModal = true"
                        class="px-4 py-2.5 bg-white text-brand-800 hover:bg-emerald-50 active:scale-95 font-black text-xs rounded-xl shadow-lg transition flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    <span>Add Delivery Partner</span>
                </button>
                <a href="/delboy" target="_blank"
                   class="px-4 py-2.5 bg-emerald-950/60 hover:bg-emerald-950 text-white font-bold text-xs rounded-xl border border-emerald-400/30 transition flex items-center gap-1.5">
                    <span>Open Partner App (/delboy) &rarr;</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Operations Metrics Row -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5 sm:gap-4">
        <!-- Metric 1: Online Riders -->
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-xs">
            <span class="text-xs font-semibold text-gray-500 block">Active / Online Fleet</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-emerald-600"><?= $onlineRidersCount ?></span>
                <span class="text-xs text-gray-400 font-semibold">/ <?= count($allRiders) ?> total</span>
            </div>
            <span class="text-[11px] text-emerald-600 font-bold block mt-1">Ready for dispatch</span>
        </div>

        <!-- Metric 2: Active In-Transit -->
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-xs">
            <span class="text-xs font-semibold text-gray-500 block">Deliveries In-Progress</span>
            <span class="text-2xl font-black text-brand-700 mt-1 block"><?= count($activeOrders) ?></span>
            <span class="text-[11px] text-slate-500 block mt-1">Live tracking on road</span>
        </div>

        <!-- Metric 3: Unassigned Orders -->
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-xs">
            <span class="text-xs font-semibold text-gray-500 block">Unassigned Pool</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block"><?= count($unassignedOrders) ?></span>
            <span class="text-[11px] text-amber-600 font-bold block mt-1">Offered to nearby fleet</span>
        </div>

        <!-- Metric 4: Escalated Orders -->
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-xs">
            <span class="text-xs font-semibold text-gray-500 block">Escalated Orders</span>
            <span class="text-2xl font-black text-red-600 mt-1 block"><?= count($escalatedOrders) ?></span>
            <span class="text-[11px] text-red-500 font-bold block mt-1">Requires manual assign</span>
        </div>

        <!-- Metric 5: Pending COD Collections -->
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-xs col-span-2 lg:col-span-1">
            <span class="text-xs font-semibold text-gray-500 block">Unsettled COD Cash</span>
            <span class="text-2xl font-black text-purple-700 mt-1 block">₹<?= number_format($totalPendingCod, 2) ?></span>
            <span class="text-[11px] text-purple-600 font-bold block mt-1">Pending reconciliation</span>
        </div>
    </div>

    <!-- Navigation Tabs Navigation -->
    <div class="flex border-b border-gray-200 overflow-x-auto space-x-1 sm:space-x-4 text-xs font-extrabold scrollbar-none">
        <button type="button"
                @click="activeSubTab = 'live_map'"
                class="py-3 px-3.5 border-b-2 whitespace-nowrap transition flex items-center gap-1.5"
                :class="activeSubTab === 'live_map' ? 'border-brand-600 text-brand-700 font-black' : 'border-transparent text-gray-500 hover:text-gray-800'">
            <span>🗺️ Live Fleet Radar Map</span>
        </button>
        <button type="button"
                @click="activeSubTab = 'dispatch_queue'"
                class="py-3 px-3.5 border-b-2 whitespace-nowrap transition flex items-center gap-1.5"
                :class="activeSubTab === 'dispatch_queue' ? 'border-brand-600 text-brand-700 font-black' : 'border-transparent text-gray-500 hover:text-gray-800'">
            <span>📦 Order Dispatch Queue (<?= count($unassignedOrders) + count($activeOrders) ?>)</span>
        </button>
        <button type="button"
                @click="activeSubTab = 'riders'"
                class="py-3 px-3.5 border-b-2 whitespace-nowrap transition flex items-center gap-1.5"
                :class="activeSubTab === 'riders' ? 'border-brand-600 text-brand-700 font-black' : 'border-transparent text-gray-500 hover:text-gray-800'">
            <span>🛵 Delivery Partners (<?= count($allRiders) ?>)</span>
        </button>
        <button type="button"
                @click="activeSubTab = 'cod_reconciliation'"
                class="py-3 px-3.5 border-b-2 whitespace-nowrap transition flex items-center gap-1.5"
                :class="activeSubTab === 'cod_reconciliation' ? 'border-brand-600 text-brand-700 font-black' : 'border-transparent text-gray-500 hover:text-gray-800'">
            <span>💵 COD Cash Reconciliation (<?= count($pendingCodCollections) ?>)</span>
        </button>
    </div>

    <!-- TAB 1: LIVE FLEET RADAR MAP -->
    <div x-show="activeSubTab === 'live_map'" x-cloak class="space-y-4">
        <div class="bg-white rounded-3xl border border-gray-200/80 p-4 sm:p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-extrabold text-gray-900">Live Delivery Radar & Fleet Positioning</h2>
                    <p class="text-xs text-gray-500">Real-time GPS signals from active delivery partners and current delivery routes.</p>
                </div>
                <button type="button" @click="refreshMapMarkers()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-xl transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span>Refresh Radar</span>
                </button>
            </div>

            <div id="adminFleetMap" class="w-full h-96 sm:h-[480px] rounded-2xl border border-gray-200 bg-gray-50 shadow-inner"></div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 text-xs text-gray-600 px-1 border-t border-gray-100">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-emerald-600 inline-block"></span>
                        <strong>Main Store (Jaora)</strong>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span>
                        <span>Online Rider (Active GPS)</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span>
                        <span>Customer Delivery Point</span>
                    </span>
                </div>
                <span class="text-gray-400">Map updates automatically during active deliveries.</span>
            </div>
        </div>
    </div>

    <!-- TAB 2: ORDER DISPATCH QUEUE -->
    <div x-show="activeSubTab === 'dispatch_queue'" x-cloak class="space-y-6">

        <!-- Section A: Escalated Orders (Urgent) -->
        <?php if (!empty($escalatedOrders)): ?>
            <div class="bg-red-50/70 border border-red-300 rounded-3xl p-5 shadow-xs space-y-3">
                <div class="flex items-center gap-2 text-red-800">
                    <svg class="w-5 h-5 text-red-600 animate-pulse" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <h3 class="text-sm font-extrabold">Escalated Orders — No Partner Accepted within 5 Minutes</h3>
                </div>
                <div class="divide-y divide-red-200">
                    <?php foreach ($escalatedOrders as $esc): ?>
                        <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div>
                                <span class="font-black text-gray-900 text-sm">#<?= e($esc['order_number']) ?></span>
                                <span class="text-red-700 font-bold ml-2">Waiting <?= floor($esc['seconds_elapsed'] / 60) ?>m <?= $esc['seconds_elapsed'] % 60 ?>s</span>
                                <p class="text-gray-700 mt-0.5">👤 <?= e($esc['customer_name']) ?> (<?= e($esc['customer_phone']) ?>) • 📍 <?= e($esc['delivery_area_name']) ?></p>
                                <span class="text-[11px] text-gray-500">Amount: ₹<?= number_format((float)$esc['total_amount'], 2) ?> • <?= strtoupper($esc['payment_method']) ?></span>
                            </div>
                            <button type="button"
                                    @click="openAssignModal(<?= (int)$esc['id'] ?>, '<?= e($esc['order_number']) ?>')"
                                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl shadow-xs transition">
                                Manually Assign Rider &rarr;
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section B: Unassigned Orders Ready for Dispatch -->
        <div class="bg-white rounded-3xl border border-gray-200/80 overflow-hidden shadow-xs">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-extrabold text-gray-900">Unassigned Incoming Orders</h3>
                    <p class="text-xs text-gray-500">Available to online delivery partners. Auto-escalates after 5 minutes.</p>
                </div>
                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 font-extrabold text-xs rounded-full">
                    <?= count($unassignedOrders) ?> unassigned
                </span>
            </div>

            <?php if (empty($unassignedOrders)): ?>
                <div class="p-8 text-center text-xs text-gray-400">
                    No unassigned delivery orders at this moment. All placed orders are assigned or completed.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase tracking-wider font-bold">
                            <tr>
                                <th class="py-3 px-4">Order #</th>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-4">Area & Address</th>
                                <th class="py-3 px-4">Amount</th>
                                <th class="py-3 px-4">Payment</th>
                                <th class="py-3 px-4">Waiting Time</th>
                                <th class="py-3 px-4 text-right">Dispatch Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php foreach ($unassignedOrders as $ord): ?>
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="py-3 px-4 font-black text-gray-900">
                                        <a href="/admin/orders/view?id=<?= $ord['id'] ?>" class="text-brand-700 hover:underline">
                                            #<?= e($ord['order_number']) ?>
                                        </a>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-gray-900">
                                        <?= e($ord['customer_name']) ?><br>
                                        <span class="text-gray-400 text-[11px]"><?= e($ord['customer_phone']) ?></span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <strong class="text-gray-800"><?= e($ord['delivery_area_name'] ?? 'Jaora') ?></strong>
                                        <p class="text-gray-500 text-[11px] line-clamp-1"><?= e($ord['delivery_address']) ?></p>
                                    </td>
                                    <td class="py-3 px-4 font-extrabold text-gray-900">₹<?= number_format((float)$ord['total_amount'], 2) ?></td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-md font-bold text-[10px] <?= $ord['payment_method'] === 'cod' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                            <?= strtoupper($ord['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-gray-500"><?= floor($ord['seconds_elapsed'] / 60) ?>m <?= $ord['seconds_elapsed'] % 60 ?>s</td>
                                    <td class="py-3 px-4 text-right">
                                        <button type="button"
                                                @click="openAssignModal(<?= (int)$ord['id'] ?>, '<?= e($ord['order_number']) ?>')"
                                                class="px-3 py-1.5 bg-brand-700 hover:bg-brand-800 text-white font-bold text-xs rounded-xl shadow-xs transition">
                                            Assign Partner &rarr;
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section C: Active In-Transit Orders -->
        <div class="bg-white rounded-3xl border border-gray-200/80 overflow-hidden shadow-xs">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-extrabold text-gray-900">Active In-Transit Deliveries</h3>
                    <p class="text-xs text-gray-500">Orders currently accepted, picked up, or out for delivery.</p>
                </div>
                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 font-extrabold text-xs rounded-full">
                    <?= count($activeOrders) ?> active
                </span>
            </div>

            <?php if (empty($activeOrders)): ?>
                <div class="p-8 text-center text-xs text-gray-400">
                    No active deliveries in transit right now.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase tracking-wider font-bold">
                            <tr>
                                <th class="py-3 px-4">Order #</th>
                                <th class="py-3 px-4">Assigned Partner</th>
                                <th class="py-3 px-4">Current Workflow Stage</th>
                                <th class="py-3 px-4">Customer Destination</th>
                                <th class="py-3 px-4">Amount</th>
                                <th class="py-3 px-4 text-right">Emergency / Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php foreach ($activeOrders as $act): ?>
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="py-3 px-4 font-black text-gray-900">
                                        <a href="/admin/orders/view?id=<?= $act['id'] ?>" class="text-brand-700 hover:underline">
                                            #<?= e($act['order_number']) ?>
                                        </a>
                                    </td>
                                    <td class="py-3 px-4">
                                        <strong class="text-gray-900"><?= e($act['rider_name']) ?></strong><br>
                                        <span class="text-gray-400 text-[11px]"><?= e($act['rider_mobile']) ?></span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                            <?= str_replace('_', ' ', $act['delivery_workflow_status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <strong class="text-gray-900"><?= e($act['customer_name']) ?></strong>
                                        <p class="text-gray-500 text-[11px]"><?= e($act['delivery_area_name']) ?></p>
                                    </td>
                                    <td class="py-3 px-4 font-extrabold text-gray-900">₹<?= number_format((float)$act['total_amount'], 2) ?></td>
                                    <td class="py-3 px-4 text-right space-x-1">
                                        <!-- Reassign button -->
                                        <button type="button"
                                                @click="openAssignModal(<?= (int)$act['id'] ?>, '<?= e($act['order_number']) ?>')"
                                                class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-[11px]">
                                            Reassign
                                        </button>
                                        <!-- Emergency OTP override -->
                                        <button type="button"
                                                @click="openOverrideModal(<?= (int)$act['id'] ?>, '<?= e($act['order_number']) ?>')"
                                                class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 font-bold rounded-lg text-[11px]">
                                            OTP Override
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- TAB 3: DELIVERY BOYS (FLEET MANAGEMENT) -->
    <div x-show="activeSubTab === 'riders'" x-cloak class="space-y-4">
        <div class="bg-white rounded-3xl border border-gray-200/80 overflow-hidden shadow-xs">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-extrabold text-gray-900">Delivery Partners Registry</h3>
                    <p class="text-xs text-gray-500">Configure login accounts, vehicle information, and activation status.</p>
                </div>
                <button type="button"
                        @click="openAddRiderModal = true"
                        class="px-3.5 py-2 bg-brand-700 hover:bg-brand-800 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    <span>New Partner Account</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-600 uppercase tracking-wider font-bold">
                        <tr>
                            <th class="py-3 px-4">Partner Name</th>
                            <th class="py-3 px-4">Login ID / Mobile</th>
                            <th class="py-3 px-4">Vehicle</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Live State</th>
                            <th class="py-3 px-4">Last GPS Ping</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php foreach ($allRiders as $r): ?>
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-3 px-4 font-black text-gray-900">
                                    <?= e($r['name']) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <strong class="text-gray-800"><?= e($r['login_id']) ?></strong><br>
                                    <span class="text-gray-400 text-[11px]"><?= e($r['mobile']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-gray-800"><?= e($r['vehicle_type']) ?></span>
                                    <?php if (!empty($r['vehicle_number'])): ?>
                                        <span class="text-gray-400 text-[11px] block"><?= e($r['vehicle_number']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $r['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= strtoupper($r['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="flex items-center gap-1.5 font-bold <?= $r['is_online'] ? 'text-emerald-600' : 'text-gray-400' ?>">
                                        <span class="w-2 h-2 rounded-full <?= $r['is_online'] ? 'bg-emerald-500 animate-ping' : 'bg-gray-400' ?>"></span>
                                        <span><?= $r['is_online'] ? 'ONLINE' : 'OFFLINE' ?></span>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-gray-500">
                                    <?= !empty($r['last_location_updated_at']) ? date('h:i A', strtotime($r['last_location_updated_at'])) : 'Never' ?>
                                </td>
                                <td class="py-3 px-4 text-right space-x-1">
                                    <!-- Toggle Active/Inactive Form -->
                                    <form action="/admin/delivery/toggle-rider-status" method="POST" class="inline">
                                        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="rider_id" value="<?= $r['id'] ?>">
                                        <button type="submit"
                                                class="px-2.5 py-1 text-[11px] font-bold rounded-lg transition <?= $r['status'] === 'active' ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                                            <?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <!-- Edit Button -->
                                    <button type="button"
                                            @click="openEditModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-[11px] rounded-lg transition">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: COD CASH RECONCILIATION -->
    <div x-show="activeSubTab === 'cod_reconciliation'" x-cloak class="space-y-4">
        <div class="bg-white rounded-3xl border border-gray-200/80 overflow-hidden shadow-xs">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-extrabold text-gray-900">Cash on Delivery (COD) Collections & Store Register Settlement</h3>
                    <p class="text-xs text-gray-500">Verify cash handed over by riders at the end of shifts.</p>
                </div>
                <div class="text-right">
                    <span class="text-xs font-semibold text-gray-500 block">Total Pending Fleet Cash</span>
                    <strong class="text-base font-black text-amber-600">₹<?= number_format($totalPendingCod, 2) ?></strong>
                </div>
            </div>

            <?php if (empty($codCollections)): ?>
                <div class="p-8 text-center text-xs text-gray-400">
                    No COD cash collections recorded yet.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase tracking-wider font-bold">
                            <tr>
                                <th class="py-3 px-4">Order #</th>
                                <th class="py-3 px-4">Partner</th>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-4">Amount Collected</th>
                                <th class="py-3 px-4">Collected Timestamp</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Reconcile Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php foreach ($codCollections as $c): ?>
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="py-3 px-4 font-black text-gray-900">
                                        #<?= e($c['order_number']) ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <strong class="text-gray-900"><?= e($c['rider_name']) ?></strong><br>
                                        <span class="text-gray-400 text-[11px]"><?= e($c['rider_mobile']) ?></span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?= e($c['customer_name']) ?>
                                    </td>
                                    <td class="py-3 px-4 font-black text-emerald-700 text-sm">
                                        ₹<?= number_format((float)$c['amount'], 2) ?>
                                    </td>
                                    <td class="py-3 px-4 text-gray-500">
                                        <?= date('d M Y, h:i A', strtotime($c['collected_at'])) ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $c['status'] === 'reconciled_with_admin' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                            <?= $c['status'] === 'reconciled_with_admin' ? 'RECONCILED' : 'CASH IN HAND' ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <?php if ($c['status'] !== 'reconciled_with_admin'): ?>
                                            <form action="/admin/delivery/reconcile-cod" method="POST" class="inline">
                                                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="collection_id" value="<?= $c['id'] ?>">
                                                <button type="submit"
                                                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition">
                                                    Deposit in Register ✓
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-[11px] font-semibold">Settled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL 1: ADD DELIVERY BOY -->
    <div x-show="openAddRiderModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl border border-gray-200 max-w-md w-full p-6 shadow-2xl space-y-4" @click.away="openAddRiderModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-base font-black text-gray-900">Add New Delivery Partner</h3>
                <button type="button" @click="openAddRiderModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <form action="/admin/delivery/create-rider" method="POST" class="space-y-3.5 text-xs">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Partner Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. Rahul Sharma" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Mobile Number</label>
                        <input type="text" name="mobile" required placeholder="9876543210" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Login ID (Unique)</label>
                        <input type="text" name="login_id" required placeholder="rider_rahul" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Login Password</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Account Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Vehicle Type</label>
                        <select name="vehicle_type" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                            <option value="Bike / Scooter">Bike / Scooter</option>
                            <option value="Electric Scooter (EV)">Electric Scooter (EV)</option>
                            <option value="Bicycle">Bicycle</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Vehicle Reg Number</label>
                    <input type="text" name="vehicle_number" placeholder="MP 43 AB 1234" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                </div>

                <div class="pt-3 flex items-center justify-end gap-2 border-t border-gray-100">
                    <button type="button" @click="openAddRiderModal = false" class="px-4 py-2 bg-gray-100 text-gray-700 font-bold rounded-xl">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-brand-700 text-white font-bold rounded-xl shadow-xs">Save Partner</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 1B: EDIT DELIVERY BOY -->
    <div x-show="openEditRiderModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl border border-gray-200 max-w-md w-full p-6 shadow-2xl space-y-4" @click.away="openEditRiderModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-base font-black text-gray-900">Edit Delivery Partner</h3>
                <button type="button" @click="openEditRiderModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <form action="/admin/delivery/edit-rider" method="POST" class="space-y-3.5 text-xs">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                <input type="hidden" name="rider_id" :value="editRiderData.id">

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Partner Full Name</label>
                    <input type="text" name="name" x-model="editRiderData.name" required placeholder="e.g. Rahul Sharma" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Mobile Number</label>
                        <input type="text" name="mobile" x-model="editRiderData.mobile" required placeholder="9876543210" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Login ID (Unique)</label>
                        <input type="text" name="login_id" x-model="editRiderData.login_id" required placeholder="rider_rahul" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Reset Password <span class="text-gray-400 font-normal">(Leave blank to keep unchanged)</span></label>
                    <input type="password" name="password" placeholder="New password (optional)" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Account Status</label>
                        <select name="status" x-model="editRiderData.status" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Vehicle Type</label>
                        <select name="vehicle_type" x-model="editRiderData.vehicle_type" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                            <option value="Bike / Scooter">Bike / Scooter</option>
                            <option value="Electric Scooter (EV)">Electric Scooter (EV)</option>
                            <option value="Bicycle">Bicycle</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Vehicle Reg Number</label>
                    <input type="text" name="vehicle_number" x-model="editRiderData.vehicle_number" placeholder="MP 43 AB 1234" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                </div>

                <div class="pt-3 flex items-center justify-end gap-2 border-t border-gray-100">
                    <button type="button" @click="openEditRiderModal = false" class="px-4 py-2 bg-gray-100 text-gray-700 font-bold rounded-xl">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-brand-700 text-white font-bold rounded-xl shadow-xs">Update Partner</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: MANUAL ASSIGN / REASSIGN -->
    <div x-show="assignModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl border border-gray-200 max-w-sm w-full p-6 shadow-2xl space-y-4" @click.away="assignModalOpen = false">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-sm font-black text-gray-900">Assign Order <span x-text="'#' + assignOrderNum"></span></h3>
                <button type="button" @click="assignModalOpen = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <form action="/admin/delivery/assign-order" method="POST" class="space-y-3.5 text-xs">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                <input type="hidden" name="order_id" :value="assignOrderId">

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Select Delivery Partner</label>
                    <select name="rider_id" required class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                        <?php foreach ($allRiders as $r): ?>
                            <?php if ($r['status'] === 'active'): ?>
                                <option value="<?= $r['id'] ?>">
                                    <?= e($r['name']) ?> (<?= e($r['mobile']) ?>) — <?= $r['is_online'] ? 'ONLINE' : 'Offline' ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Admin Dispatch Notes (Optional)</label>
                    <input type="text" name="notes" placeholder="Priority express delivery" class="w-full px-3 py-2.5 rounded-xl border border-gray-300 text-xs">
                </div>

                <div class="pt-3 flex items-center justify-end gap-2 border-t border-gray-100">
                    <button type="button" @click="assignModalOpen = false" class="px-4 py-2 bg-gray-100 text-gray-700 font-bold rounded-xl">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-brand-700 text-white font-bold rounded-xl shadow-xs">Dispatch Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: EMERGENCY OTP OVERRIDE -->
    <div x-show="overrideModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl border border-gray-200 max-w-sm w-full p-6 shadow-2xl space-y-4" @click.away="overrideModalOpen = false">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-sm font-black text-gray-900">Emergency OTP Override <span x-text="'#' + overrideOrderNum"></span></h3>
                <button type="button" @click="overrideModalOpen = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <form action="/admin/delivery/override-otp" method="POST" class="space-y-3.5 text-xs">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                <input type="hidden" name="order_id" :value="overrideOrderId">

                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px]">
                    <strong>Audit Notice:</strong> This action bypasses customer OTP verification. Use only when customer phone is dead/unreachable and delivery is physically verified.
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Mandatory Override Reason</label>
                    <textarea name="reason" required rows="3" placeholder="e.g. Verified with customer directly at doorstep; phone battery dead." class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs"></textarea>
                </div>

                <div class="pt-3 flex items-center justify-end gap-2 border-t border-gray-100">
                    <button type="button" @click="overrideModalOpen = false" class="px-4 py-2 bg-gray-100 text-gray-700 font-bold rounded-xl">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white font-bold rounded-xl shadow-xs">Authorize Override</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function adminDeliveryApp() {
    return {
        activeSubTab: 'live_map',
        openAddRiderModal: false,
        openEditRiderModal: false,
        editRiderData: {
            id: null,
            name: '',
            mobile: '',
            login_id: '',
            status: 'active',
            vehicle_type: 'Bike / Scooter',
            vehicle_number: ''
        },
        assignModalOpen: false,
        assignOrderId: null,
        assignOrderNum: '',
        overrideModalOpen: false,
        overrideOrderId: null,
        overrideOrderNum: '',
        map: null,
        markers: [],
        infoWindow: null,
        storeLat: 23.635784,
        storeLng: 75.124683,

        init() {
            this.$nextTick(() => {
                this.initFleetMap();
            });
        },

        initFleetMap() {
            const container = document.getElementById('adminFleetMap');
            if (!container || typeof google === 'undefined' || !google.maps) return;

            this.map = new google.maps.Map(container, {
                center: { lat: this.storeLat, lng: this.storeLng },
                zoom: 14,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true
            });

            this.infoWindow = new google.maps.InfoWindow();

            // Draw Jaora delivery boundary polygon
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
                strokeOpacity: 0.85,
                strokeWeight: 2,
                fillColor: '#10b981',
                fillOpacity: 0.1,
                map: this.map
            });

            this.refreshMapMarkers();
        },

        refreshMapMarkers() {
            if (!this.map || typeof google === 'undefined' || !google.maps) return;

            // Clear previous markers
            this.markers.forEach(m => m.setMap(null));
            this.markers = [];

            const bounds = new google.maps.LatLngBounds();

            // 1. Store HQ Pin (Fixed location: 23.635784, 75.124683)
            const storePos = { lat: this.storeLat, lng: this.storeLng };
            const storeMarker = new google.maps.Marker({
                position: storePos,
                map: this.map,
                title: 'N.A Fresh Fruits & Coconuts (Store HQ)',
                label: { text: '🏬', fontSize: '18px' }
            });
            storeMarker.addListener('click', () => {
                this.infoWindow.setContent('<div class="p-2"><strong>N.A Fresh Fruits & Coconuts (Store HQ)</strong><br><span class="text-xs text-gray-600">Station Road, Jaora, MP - 457226</span></div>');
                this.infoWindow.open(this.map, storeMarker);
            });
            this.markers.push(storeMarker);
            bounds.extend(storePos);

            // 2. Delivery Riders
            const riders = <?= json_encode($allRiders) ?>;
            riders.forEach(r => {
                if (r.current_lat && r.current_lng) {
                    const rPos = { lat: parseFloat(r.current_lat), lng: parseFloat(r.current_lng) };
                    const riderMarker = new google.maps.Marker({
                        position: rPos,
                        map: this.map,
                        title: `Partner: ${r.name}`,
                        label: { text: '🛵', fontSize: '16px' }
                    });
                    riderMarker.addListener('click', () => {
                        this.infoWindow.setContent(`<div class="p-2"><strong>Partner: ${r.name}</strong><br><span class="text-xs">Phone: ${r.mobile}</span><br><span class="text-xs font-bold ${r.is_online ? 'text-emerald-600' : 'text-gray-500'}">Status: ${r.is_online ? 'ONLINE' : 'OFFLINE'}</span></div>`);
                        this.infoWindow.open(this.map, riderMarker);
                    });
                    this.markers.push(riderMarker);
                    bounds.extend(rPos);
                }
            });

            // 3. Active Order Destinations
            const activeOrders = <?= json_encode($activeOrders) ?>;
            activeOrders.forEach(o => {
                if (o.latitude && o.longitude) {
                    const dPos = { lat: parseFloat(o.latitude), lng: parseFloat(o.longitude) };
                    const destMarker = new google.maps.Marker({
                        position: dPos,
                        map: this.map,
                        title: `Order #${o.order_number}`,
                        label: { text: '📍', fontSize: '15px' }
                    });
                    destMarker.addListener('click', () => {
                        this.infoWindow.setContent(`<div class="p-2"><strong>Order #${o.order_number}</strong><br><span class="text-xs">Customer: ${o.customer_name}</span><br><span class="text-xs">Stage: ${o.delivery_workflow_status}</span></div>`);
                        this.infoWindow.open(this.map, destMarker);
                    });
                    this.markers.push(destMarker);
                    bounds.extend(dPos);
                }
            });

            if (this.markers.length > 1) {
                this.map.fitBounds(bounds);
            } else {
                this.map.setCenter(storePos);
                this.map.setZoom(14);
            }
        },

        openAssignModal(orderId, orderNum) {
            this.assignOrderId = orderId;
            this.assignOrderNum = orderNum;
            this.assignModalOpen = true;
        },

        openOverrideModal(orderId, orderNum) {
            this.overrideOrderId = orderId;
            this.overrideOrderNum = orderNum;
            this.overrideModalOpen = true;
        },

        openEditModal(rider) {
            this.editRiderData = {
                id: rider.id,
                name: rider.name || '',
                mobile: rider.mobile || '',
                login_id: rider.login_id || '',
                status: rider.status || 'active',
                vehicle_type: rider.vehicle_type || 'Bike / Scooter',
                vehicle_number: rider.vehicle_number || ''
            };
            this.openEditRiderModal = true;
        }
    };
}
</script>

<?php require __DIR__ . '/layout-footer.php'; ?>
