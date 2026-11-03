<?php
/**
 * Admin Notification Management Center
 * N.A Fresh Fruits & Coconuts
 *
 * Implements the 4 distinct notification categories:
 *  1. Offers (Broadcast deals, combos, and discounts)
 *  2. Orders (Order status alerts and customer updates)
 *  3. Stock (Coconut water harvest / stock availability with duplicate protection)
 *  4. News (Store announcements, holiday schedules, service area updates)
 */
$pageTitle = 'Push Notifications - N.A Admin';
$activeAdminTab = 'notifications';

$pdo = Database::getConnection();
$stats = NotificationService::getStats();
$adminUser = AuthService::user();

// Fetch recent notifications history (last 50)
$stmtHistory = $pdo->query("
    SELECT h.*, a.name AS admin_name
    FROM `notification_history` h
    LEFT JOIN `admins` a ON a.id = h.sent_by_admin_id
    ORDER BY h.`id` DESC 
    LIMIT 50
");
$history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
$stmtHistory->closeCursor();

// Fetch customers for individual targeting if needed
$stmtCustomers = $pdo->query("SELECT `id`, `name`, `phone` FROM `customers` ORDER BY `name` ASC LIMIT 100");
$customers = $stmtCustomers->fetchAll(PDO::FETCH_ASSOC);
$stmtCustomers->closeCursor();

require __DIR__ . '/layout-header.php';
?>

<div class="space-y-6" x-data="{
    category: 'offers',
    title: '',
    message: '',
    imageUrl: '',
    clickUrl: '/shop',
    selectedCustId: '',
    isSending: false,
    testingAdmin: false,
    resultMsg: '',
    resultSuccess: true,

    onCategoryChange(newCat) {
        this.category = newCat;
        if (newCat === 'offers' || newCat === 'stock') {
            if (this.clickUrl === '/' || this.clickUrl === '/track') this.clickUrl = '/shop';
        } else if (newCat === 'orders') {
            if (this.clickUrl === '/' || this.clickUrl === '/shop') this.clickUrl = '/track';
        } else if (newCat === 'news') {
            if (this.clickUrl === '/shop' || this.clickUrl === '/track') this.clickUrl = '/';
        }
    },

    sendNotification() {
        if (!this.title.trim() || !this.message.trim()) {
            alert('Please enter both a title and a message body.');
            return;
        }

        const catNames = {
            offers: 'Special Offers & Deals',
            orders: 'Order Update',
            stock: 'Fresh Coconut Stock Announcement',
            news: 'Store News & Announcement'
        };

        if (!confirm('Are you sure you want to broadcast this ' + (catNames[this.category] || this.category) + ' push notification?')) {
            return;
        }

        this.isSending = true;
        this.resultMsg = '';

        const payload = {
            category: this.category,
            title: this.title,
            message: this.message,
            image_url: this.imageUrl,
            click_url: this.clickUrl,
            customer_id: this.selectedCustId ? parseInt(this.selectedCustId) : null,
            _csrf_token: '<?= csrf_token() ?>'
        };

        fetch('/admin/notifications/send', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(d => {
            this.isSending = false;
            if (d.success) {
                this.resultSuccess = true;
                this.resultMsg = '✓ Push broadcast complete: ' + (d.sent || 0) + ' sent, ' + (d.failed || 0) + ' failed (Target: ' + (d.total || 0) + ' subscribers).';
                this.title = '';
                this.message = '';
                setTimeout(() => window.location.reload(), 1500);
            } else {
                this.resultSuccess = false;
                this.resultMsg = '✕ ' + (d.error || 'Failed to dispatch notification');
            }
        })
        .catch(err => {
            this.isSending = false;
            this.resultSuccess = false;
            this.resultMsg = '✕ Network error: ' + err.message;
        });
    },

    testAdminDevice() {
        this.testingAdmin = true;
        fetch('/admin/notifications/test-admin', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            }
        })
        .then(r => r.json())
        .then(d => {
            this.testingAdmin = false;
            if (d.success) {
                alert('Test alert dispatched to ' + (d.sent || 0) + ' admin device(s)! Check your screen for the push notification.');
            } else {
                alert('Test alert failed: ' + (d.error || 'No active admin subscriptions found. Click Enrol Admin Device above first.'));
            }
        })
        .catch(e => {
            this.testingAdmin = false;
            alert('Error: ' + e.message);
        });
    }
}">

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-2">
                <svg class="w-7 h-7 text-[#235d3a]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                PWA Web Push Notification Center
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                Zero-cron browser Web Push engine (RFC 8291 / RFC 8292). Broadcast offers, stock updates, news, and automatic order status alerts.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button 
                type="button" 
                @click="testAdminDevice()" 
                :disabled="testingAdmin"
                class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold text-xs py-2.5 px-4 rounded-xl transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            >
                <span x-show="!testingAdmin">🔔 Test All Admin Devices</span>
                <span x-show="testingAdmin">Broadcasting alert...</span>
            </button>
        </div>
    </div>

    <!-- Active Subscriber Metrics -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Customer Devices</span>
            <div class="flex items-baseline gap-2 mt-1">
                <p class="text-2xl font-black text-gray-900"><?= (int)$stats['customer_devices'] ?></p>
                <span class="text-[11px] text-gray-400 font-medium">(<?= (int)$stats['total_customers'] ?> registered accounts)</span>
            </div>
            <div class="text-[11px] text-gray-400 mt-0.5">Active subscribers ready for broadcasts</div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Admin Alert Devices</span>
            <div class="flex items-baseline gap-2 mt-1">
                <p class="text-2xl font-black text-gray-900"><?= (int)$stats['admin_devices'] ?></p>
                <span class="text-[11px] text-emerald-600 font-medium">Isolated from customers</span>
            </div>
            <div class="text-[11px] text-gray-400 mt-0.5">Enrolled for new order push alerts</div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Pushes Delivered</span>
            <div class="flex items-baseline gap-2 mt-1">
                <p class="text-2xl font-black text-emerald-600"><?= (int)$stats['total_delivered'] ?></p>
                <span class="text-[11px] text-emerald-600 font-medium">✓ Verified</span>
            </div>
            <div class="text-[11px] text-gray-400 mt-0.5">Successful HTTP 201/200 push receipts</div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Deliveries Failed / Expired</span>
            <div class="flex items-baseline gap-2 mt-1">
                <p class="text-2xl font-black text-gray-500"><?= (int)$stats['total_failed'] ?></p>
                <span class="text-[11px] text-gray-400 font-medium">Auto-pruned</span>
            </div>
            <div class="text-[11px] text-gray-400 mt-0.5">410 Gone tokens cleaned automatically</div>
        </div>
    </div>

    <!-- Current Admin Device Enrollment & Real-Time Status Card -->
    <div class="bg-gradient-to-r from-emerald-900 to-[#143823] rounded-3xl p-5 sm:p-6 text-white shadow-md relative overflow-hidden">
        <div class="absolute right-0 top-0 translate-x-8 -translate-y-8 w-48 h-48 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5 relative z-10">
            <div class="space-y-1.5 max-w-xl">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="text-[11px] font-black uppercase tracking-wider text-emerald-300">This Admin Device</span>
                    <div id="admin-enroll-status-badge">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-white/10 text-emerald-100">Checking device subscription...</span>
                    </div>
                </div>
                <h3 class="text-lg font-black tracking-tight text-white">Instant Order Alerts on this Screen</h3>
                <p id="admin-enroll-status-text" class="text-xs text-emerald-100/90 leading-relaxed">
                    Enroll this device to receive instant push alerts whenever a customer places an order or completes payment.
                </p>
                <div id="admin-device-test-result" class="hidden text-xs py-1"></div>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button 
                    type="button" 
                    id="admin-enroll-btn"
                    onclick="window.NA_PWA.subscribeDevice('admin')"
                    class="bg-white hover:bg-emerald-50 text-[#143823] font-black text-xs py-2.5 px-5 rounded-xl shadow-sm transition flex items-center gap-2 cursor-pointer"
                >
                    <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    <span>Enrol Current Admin Device</span>
                </button>

                <button 
                    type="button" 
                    id="admin-device-test-btn"
                    style="display: none;"
                    onclick="window.NA_PWA.testCurrentDevice('admin')"
                    class="bg-emerald-600/80 hover:bg-emerald-500 text-white font-bold text-xs py-2.5 px-4 rounded-xl border border-emerald-400/40 transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>Test This Screen</span>
                </button>

                <button 
                    type="button" 
                    id="admin-repair-btn"
                    style="display: none;"
                    onclick="window.NA_PWA.repairSubscription('admin')"
                    title="Re-synchronize and generate fresh push token"
                    class="bg-white/10 hover:bg-white/20 text-emerald-100 font-semibold text-xs py-2.5 px-3.5 rounded-xl border border-white/20 transition cursor-pointer"
                >
                    Re-sync Device
                </button>

                <button 
                    type="button" 
                    id="admin-unenroll-btn"
                    style="display: none;"
                    onclick="if(confirm('Disconnect this device from admin push alerts?')) window.NA_PWA.unsubscribeDevice()"
                    class="bg-black/20 hover:bg-black/30 text-emerald-200 hover:text-white font-semibold text-xs py-2.5 px-3 rounded-xl transition cursor-pointer"
                >
                    Unenroll
                </button>
            </div>
        </div>
    </div>

    <!-- Main Two-Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left 2 Cols: Send Notification Form (Exactly the 4 Categories) -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-gray-100 shadow-xs space-y-5">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Broadcast Push Notification</h2>
                    <p class="text-xs text-gray-400">Select one of the 4 defined categories to dispatch to customer subscribers.</p>
                </div>
                <span class="text-[10px] font-bold px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full border border-emerald-100">
                    VAPID Active (RFC 8292)
                </span>
            </div>

            <!-- Category Selector (Exactly 4 Categories: Offers, Orders, Stock, News) -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">Select Category</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    
                    <!-- 1. OFFERS -->
                    <button 
                        type="button" 
                        @click="onCategoryChange('offers')" 
                        class="p-3 border rounded-2xl flex flex-col items-center justify-center text-center text-xs transition cursor-pointer" 
                        :class="category === 'offers' ? 'border-emerald-600 bg-emerald-50 text-emerald-900 font-bold ring-2 ring-emerald-500/20' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                    >
                        <span class="text-base mb-1">🎁</span>
                        <span class="font-bold">1. OFFERS</span>
                        <span class="text-[10px] text-gray-500 mt-0.5">Discounts & Combos</span>
                    </button>

                    <!-- 2. ORDERS -->
                    <button 
                        type="button" 
                        @click="onCategoryChange('orders')" 
                        class="p-3 border rounded-2xl flex flex-col items-center justify-center text-center text-xs transition cursor-pointer" 
                        :class="category === 'orders' ? 'border-indigo-600 bg-indigo-50 text-indigo-900 font-bold ring-2 ring-indigo-500/20' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                    >
                        <span class="text-base mb-1">📦</span>
                        <span class="font-bold">2. ORDERS</span>
                        <span class="text-[10px] text-gray-500 mt-0.5">Status Updates</span>
                    </button>

                    <!-- 3. STOCK -->
                    <button 
                        type="button" 
                        @click="onCategoryChange('stock')" 
                        class="p-3 border rounded-2xl flex flex-col items-center justify-center text-center text-xs transition cursor-pointer" 
                        :class="category === 'stock' ? 'border-amber-600 bg-amber-50 text-amber-900 font-bold ring-2 ring-amber-500/20' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                    >
                        <span class="text-base mb-1">🥥</span>
                        <span class="font-bold">3. STOCK</span>
                        <span class="text-[10px] text-gray-500 mt-0.5">Coconut Availability</span>
                    </button>

                    <!-- 4. NEWS -->
                    <button 
                        type="button" 
                        @click="onCategoryChange('news')" 
                        class="p-3 border rounded-2xl flex flex-col items-center justify-center text-center text-xs transition cursor-pointer" 
                        :class="category === 'news' ? 'border-sky-600 bg-sky-50 text-sky-900 font-bold ring-2 ring-sky-500/20' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                    >
                        <span class="text-base mb-1">📢</span>
                        <span class="font-bold">4. NEWS</span>
                        <span class="text-[10px] text-gray-500 mt-0.5">Store Announcements</span>
                    </button>
                </div>
            </div>

            <!-- Optional Target Customer (visible when orders category is selected) -->
            <div x-show="category === 'orders'" class="space-y-1 bg-indigo-50/50 p-3 rounded-2xl border border-indigo-100">
                <label class="block text-xs font-bold text-indigo-900 uppercase">Target Specific Customer (Optional)</label>
                <select x-model="selectedCustId" class="w-full bg-white border border-indigo-200 rounded-xl px-3 py-2 text-xs">
                    <option value="">-- All Active Customer Subscribers --</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['phone']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[11px] text-indigo-700 mt-1">Leave empty to test order notification broadcast, or pick a specific customer to isolate delivery.</p>
            </div>

            <!-- Title & Message Body -->
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Notification Title</label>
                    <input 
                        type="text" 
                        x-model="title" 
                        placeholder="e.g. Fresh Morning Coconuts Just Arrived! 🌴"
                        maxlength="100"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-[#235d3a] focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Message Body</label>
                    <textarea 
                        x-model="message" 
                        rows="3" 
                        placeholder="100% pure tender coconut water straight from the farm. Order fresh today for instant delivery!"
                        maxlength="240"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs leading-relaxed focus:ring-2 focus:ring-[#235d3a] focus:outline-none"
                    ></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Optional Image URL</label>
                        <input 
                            type="text" 
                            x-model="imageUrl" 
                            placeholder="/assets/images/hero-coconut.png"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Destination URL</label>
                        <input 
                            type="text" 
                            x-model="clickUrl" 
                            placeholder="/shop"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono"
                        >
                    </div>
                </div>
            </div>

            <!-- Submit & Status Feedback -->
            <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-gray-100">
                <span x-show="resultMsg" x-text="resultMsg" class="text-xs font-bold" :class="resultSuccess ? 'text-emerald-700' : 'text-red-600'"></span>
                <span x-show="!resultMsg"></span>

                <button 
                    type="button" 
                    @click="sendNotification()"
                    :disabled="isSending"
                    class="bg-[#235d3a] hover:bg-[#1b4a2e] text-white font-bold text-xs py-3 px-6 rounded-xl shadow-xs transition flex items-center gap-2 disabled:opacity-50 cursor-pointer ml-auto"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    <span x-show="!isSending">Send Push Notification Now</span>
                    <span x-show="isSending">Encrypting & Dispatching...</span>
                </button>
            </div>
        </div>

        <!-- Right Col: VAPID Key & System Information -->
        <div class="space-y-4">
            <div class="bg-white rounded-3xl p-5 border border-gray-100 shadow-xs space-y-3">
                <h3 class="text-xs font-bold uppercase text-gray-800 tracking-wider">VAPID Protocol & RFC 8292</h3>
                <p class="text-[11px] text-gray-500 leading-relaxed">
                    Uses native OpenSSL NIST P-256 (prime256v1) EC keys with RFC 8291 AES-128-GCM payload encryption. Private keys remain protected outside web root.
                </p>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-2.5">
                    <span class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Public VAPID Key</span>
                    <p class="font-mono text-[10px] text-gray-700 break-all select-all leading-tight">
                        <?= e($stats['vapid_public_key']) ?>
                    </p>
                </div>
            </div>

            <!-- Categories Guide Card -->
            <div class="bg-emerald-50/60 rounded-3xl p-5 border border-emerald-100 space-y-2.5 text-xs text-gray-700">
                <h4 class="font-bold text-emerald-950 flex items-center gap-1.5">
                    <span>📋</span> Category Specifications
                </h4>
                <ul class="space-y-2 text-[11px] text-gray-600">
                    <li><strong class="text-emerald-900">1. Offers:</strong> Broadcast to all active customer devices for flash sales and combos.</li>
                    <li><strong class="text-indigo-900">2. Orders:</strong> Auto-triggered on order lifecycle changes, isolated only to the customer who ordered.</li>
                    <li><strong class="text-amber-900">3. Stock:</strong> Coconut water availability alerts with 10-minute duplicate flood protection.</li>
                    <li><strong class="text-sky-900">4. News:</strong> General store updates, holiday hours, and delivery zone changes.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Notification History Table -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-xs space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
                <h3 class="text-base font-bold text-gray-900">Notification History</h3>
                <p class="text-xs text-gray-400">Log of recent broadcasts and direct order notifications.</p>
            </div>
            <span class="text-xs text-gray-500 font-medium">Showing last <?= count($history) ?> records</span>
        </div>

        <?php if (empty($history)): ?>
        <div class="text-center py-10 text-gray-400 text-xs">
            No push notifications dispatched yet. Use the form above to send your first announcement.
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-600">
                <thead class="bg-gray-50 text-[10px] font-bold uppercase text-gray-400 border-b border-gray-100">
                    <tr>
                        <th class="py-3 px-4">Category</th>
                        <th class="py-3 px-4">Title &amp; Message</th>
                        <th class="py-3 px-4">Destination</th>
                        <th class="py-3 px-4">Target Audience</th>
                        <th class="py-3 px-4 text-center">Delivered</th>
                        <th class="py-3 px-4 text-center">Failed</th>
                        <th class="py-3 px-4">Sent At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($history as $h): ?>
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="py-3 px-4">
                            <?php
                            $catClass = match($h['category']) {
                                'offers' => 'bg-emerald-50 text-emerald-800 border-emerald-100',
                                'orders' => 'bg-indigo-50 text-indigo-800 border-indigo-100',
                                'stock' => 'bg-amber-50 text-amber-800 border-amber-100',
                                default => 'bg-sky-50 text-sky-800 border-sky-100'
                            };
                            ?>
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $catClass ?> uppercase">
                                <?= e($h['category']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 max-w-xs">
                            <strong class="text-gray-900 block truncate"><?= e($h['title']) ?></strong>
                            <span class="text-[11px] text-gray-400 truncate block"><?= e($h['message']) ?></span>
                        </td>
                        <td class="py-3 px-4">
                            <a href="<?= e($h['click_url']) ?>" target="_blank" class="font-mono text-[11px] text-emerald-700 hover:underline">
                                <?= e($h['click_url']) ?>
                            </a>
                        </td>
                        <td class="py-3 px-4">
                            <span class="text-gray-700 font-medium">
                                <?= ($h['target_type'] === 'single_customer' && !empty($h['target_customer_id'])) ? 'Customer #' . (int)$h['target_customer_id'] : 'All Customers' ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-emerald-600">
                            <?= (int)$h['success_count'] ?>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-gray-400">
                            <?= (int)$h['failure_count'] ?>
                        </td>
                        <td class="py-3 px-4 text-gray-400 text-[11px] whitespace-nowrap">
                            <?= date('M d, Y h:i A', strtotime($h['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Blocked Permission Help Modal -->
<div id="pwa-permission-blocked-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
    <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl border border-gray-100 text-left">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center font-bold text-base">🔒</div>
            <div>
                <h3 class="text-sm font-bold text-gray-900">Notifications Blocked</h3>
                <p class="text-xs text-gray-400">Browser permission is set to Block</p>
            </div>
        </div>
        <div class="space-y-2 text-xs text-gray-600 bg-gray-50 p-3 rounded-xl border border-gray-100 leading-relaxed">
            <p>1. Click the <strong>padlock</strong> or <strong>site settings icon</strong> 🔒 next to the URL in your browser address bar.</p>
            <p>2. Find <strong>Notifications</strong> and change it from <em>Block</em> to <strong>Allow</strong>.</p>
            <p>3. Refresh the page and tap <strong>Enrol Device</strong> again.</p>
        </div>
        <button type="button" onclick="window.NA_PWA.hideBlockedPermissionModal()" class="mt-4 w-full bg-[#235d3a] hover:bg-[#1b4a2e] text-white py-2 rounded-xl text-xs font-bold transition">
            Close Guide
        </button>
    </div>
</div>

<?php require __DIR__ . '/layout-footer.php'; ?>
