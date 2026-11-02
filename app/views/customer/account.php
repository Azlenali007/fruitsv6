<?php
/**
 * Customer Account & Profile Dashboard
 * N.A Fresh Fruits & Coconuts
 */
$pageTitle = 'My Account - N.A Fresh Fruits & Coconuts';
$activePage = 'profile';
require_once __DIR__ . '/../layouts/header.php';

$customer = CustomerAuthService::user();
$customerId = (int)$customer['id'];
$orders = Order::getByCustomerId($customerId);
$totalOrders = count($orders);
$totalSpent = 0;
foreach ($orders as $o) {
    if ($o['order_status'] !== 'cancelled') {
        $totalSpent += (float)$o['total_amount'];
    }
}
$notifPrefs = NotificationService::getCustomerPreferences($customerId);
$orderUpdatesEnabled = !empty($notifPrefs['order_updates']);
$promotionsEnabled = !empty($notifPrefs['promotions']);
?>

<div class="max-w-4xl mx-auto px-4 py-8 sm:py-10 space-y-8">
    <!-- Top Welcome Card -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm flex flex-col sm:flex-row justify-between sm:items-center gap-6">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center font-bold text-2xl border border-brand-100/80 shrink-0">
                <?= strtoupper(substr($customer['name'], 0, 1)) ?>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-black text-gray-900"><?= e($customer['name']) ?></h1>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Active Account
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">
                    <?= e($customer['phone']) ?> <?= !empty($customer['email']) ? '• ' . e($customer['email']) : '' ?>
                </p>
                <span class="text-[11px] text-gray-400 block mt-1">Customer since <?= date('M Y', strtotime($customer['created_at'])) ?></span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="/shop" class="bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs py-2.5 px-4 rounded-xl transition inline-flex items-center gap-1.5 shadow-xs">
                <span>Order Coconut Water</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </a>
            <form action="/logout" method="POST" class="inline">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs py-2.5 px-4 rounded-xl transition">
                    Logout
                </button>
            </form>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100 shadow-xs">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Total Orders</span>
            <p class="text-2xl sm:text-3xl font-black text-gray-950"><?= $totalOrders ?></p>
        </div>
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100 shadow-xs">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Total Spent</span>
            <p class="text-2xl sm:text-3xl font-black text-brand-600">₹<?= number_format($totalSpent, 2) ?></p>
        </div>
        <div class="col-span-2 sm:col-span-1 bg-white rounded-2xl p-4 sm:p-5 border border-gray-100 shadow-xs">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Default Locality</span>
            <p class="text-sm font-bold text-gray-800 truncate"><?= e($customer['city'] ?? 'Bhopal') ?>, <?= e($customer['state'] ?? 'MP') ?></p>
        </div>
    </div>

    <!-- Grid: Profile Settings & Recent Orders -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Left: Editable Profile Info -->
        <div class="lg:col-span-5 bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex justify-between items-center">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Account Details</h2>
                <span class="text-[10px] text-gray-400">Update anytime</span>
            </div>

            <form action="/account/profile" method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Full Name</label>
                    <input 
                        type="text" 
                        name="name" 
                        required 
                        value="<?= e($customer['name']) ?>"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-brand-500 focus:bg-white focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Mobile Number (Login ID)</label>
                    <input 
                        type="text" 
                        disabled 
                        value="<?= e($customer['phone']) ?>"
                        class="w-full bg-gray-100 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-gray-500 cursor-not-allowed"
                    >
                    <span class="text-[10px] text-gray-400 mt-0.5 block">Mobile number is your primary account identifier</span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        value="<?= e($customer['email'] ?? '') ?>"
                        placeholder="your@email.com"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-brand-500 focus:bg-white focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Default Delivery Address</label>
                    <textarea 
                        name="address" 
                        rows="2"
                        placeholder="House/flat, building, street, locality..."
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-900 focus:ring-2 focus:ring-brand-500 focus:bg-white focus:outline-none"
                    ><?= e($customer['address'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">City</label>
                        <input 
                            type="text" 
                            name="city" 
                            value="<?= e($customer['city'] ?? 'Bhopal') ?>"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-900 focus:ring-2 focus:ring-brand-500 focus:outline-none"
                        >
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Pincode</label>
                        <input 
                            type="text" 
                            name="pincode" 
                            value="<?= e($customer['pincode'] ?? '462016') ?>"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-900 focus:ring-2 focus:ring-brand-500 focus:outline-none"
                        >
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 px-4 rounded-xl transition text-xs shadow-xs">
                        Save Changes
                    </button>
                </div>
            </form>

            <!-- Push Notifications & Preferences Card -->
            <div class="border-t border-gray-100 pt-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 text-brand-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        </div>
                        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Web Push Notifications</h3>
                    </div>
                    <span id="pwa-push-status" class="text-[10px] text-gray-400 font-medium">Checking...</span>
                </div>

                <p class="text-xs text-gray-500 leading-relaxed">
                    Receive instant real-time alerts directly on this phone or computer when your order is confirmed, prepared, out for delivery, and delivered.
                </p>

                <!-- Push Device Subscription Toggle Button -->
                <button 
                    type="button" 
                    id="pwa-push-toggle-btn"
                    onclick="if (window.NA_PWA && window.NA_PWA.isSubscribed) { window.NA_PWA.unsubscribeDevice(); } else if (window.NA_PWA) { window.NA_PWA.subscribeDevice('customer'); }"
                    class="w-full bg-emerald-50 hover:bg-emerald-100 text-[#235d3a] border border-emerald-200 font-bold py-2 px-3 rounded-xl transition text-xs flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span>Enable Instant Push Notifications</span>
                </button>

                <!-- Customer Topic Preferences Form -->
                <form action="/account/notifications/preferences" method="POST" class="space-y-3 pt-2 border-t border-gray-100">
                    <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                    
                    <div class="flex items-start gap-2.5">
                        <input 
                            type="checkbox" 
                            id="order_updates" 
                            name="order_updates" 
                            value="1" 
                            <?= $orderUpdatesEnabled ? 'checked' : '' ?>
                            class="mt-0.5 rounded text-brand-600 focus:ring-brand-500 h-4 w-4 border-gray-300"
                        >
                        <label for="order_updates" class="text-xs text-gray-700 cursor-pointer">
                            <strong class="font-semibold text-gray-900 block">Order Status Updates</strong>
                            Instant alerts when order is placed, dispatched and delivered.
                        </label>
                    </div>

                    <div class="flex items-start gap-2.5">
                        <input 
                            type="checkbox" 
                            id="promotions" 
                            name="promotions" 
                            value="1" 
                            <?= $promotionsEnabled ? 'checked' : '' ?>
                            class="mt-0.5 rounded text-brand-600 focus:ring-brand-500 h-4 w-4 border-gray-300"
                        >
                        <label for="promotions" class="text-xs text-gray-700 cursor-pointer">
                            <strong class="font-semibold text-gray-900 block">Fresh Harvest & Special Offers</strong>
                            Early access to fresh coconut batches and combo savings.
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-3 rounded-xl transition text-xs">
                        Save Preferences
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Recent Orders List -->
        <div class="lg:col-span-7 bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex justify-between items-center">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">My Orders (<?= $totalOrders ?>)</h2>
                <a href="/my-orders" class="text-xs font-bold text-brand-600 hover:underline">View All Orders &rarr;</a>
            </div>

            <?php if (empty($orders)): ?>
            <div class="py-12 text-center space-y-3">
                <div class="w-12 h-12 mx-auto rounded-full bg-emerald-50 text-brand-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <p class="text-sm font-bold text-gray-800">No Orders Placed Yet</p>
                <p class="text-xs text-gray-400 max-w-xs mx-auto">Get pure tender coconut water delivered to your doorstep today!</p>
                <div class="pt-2">
                    <a href="/shop" class="bg-brand-600 text-white text-xs font-bold py-2 px-5 rounded-xl inline-block hover:bg-brand-700 transition">
                        Shop Now
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                <div class="py-4 space-y-2.5">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="font-bold text-gray-900 text-sm">Order #<?= e($order['order_number']) ?></span>
                            <span class="text-[11px] text-gray-400 block"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="text-right">
                            <span class="font-black text-gray-950 text-sm">₹<?= number_format($order['total_amount'], 2) ?></span>
                            <div>
                                <?php
                                $statusColors = [
                                    'pending' => 'bg-amber-50 text-amber-800 border-amber-200',
                                    'confirmed' => 'bg-blue-50 text-blue-800 border-blue-200',
                                    'delivered' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    'cancelled' => 'bg-red-50 text-red-800 border-red-200'
                                ];
                                $badgeClass = $statusColors[$order['order_status']] ?? 'bg-gray-50 text-gray-700 border-gray-200';
                                ?>
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border <?= $badgeClass ?>">
                                    <?= ucfirst($order['order_status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Item summary -->
                    <div class="bg-gray-50 rounded-xl p-2.5 text-xs text-gray-600 flex justify-between items-center">
                        <span>
                            <?php foreach ($order['items'] as $it): ?>
                                <?= e($it['product_name']) ?> &times; <?= (int)$it['quantity'] ?>
                            <?php endforeach; ?>
                        </span>
                        <a href="/track?h=<?= urlencode($order['tracking_hash']) ?>" class="text-brand-600 hover:text-brand-700 font-bold text-[11px] inline-flex items-center gap-1">
                            <span>Track</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
