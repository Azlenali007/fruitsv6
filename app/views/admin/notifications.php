<?php
/**
 * Admin Notification Management Center
 * N.A Fresh Fruits & Coconuts
 */
$pageTitle = 'Push Notifications - N.A Admin';
$activeAdminTab = 'notifications';

$pdo = Database::getConnection();
$stats = NotificationService::getStats();
$adminUser = AuthService::user();
$isSuperAdmin = ($adminUser['role'] ?? '') === 'superadmin';

// Fetch recent notifications history (last 50)
$stmtHistory = $pdo->query("
    SELECT n.*, a.name AS admin_name
    FROM `notifications` n
    LEFT JOIN `admins` a ON a.id = n.sent_by_admin_id
    ORDER BY n.`id` DESC 
    LIMIT 50
");
$history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
$stmtHistory->closeCursor();

// Fetch customers for selector
$stmtCustomers = $pdo->query("SELECT `id`, `name`, `phone` FROM `customers` ORDER BY `name` ASC LIMIT 100");
$customers = $stmtCustomers->fetchAll(PDO::FETCH_ASSOC);
$stmtCustomers->closeCursor();

require __DIR__ . '/layout-header.php';
?>

<div class="space-y-6" x-data="{
    audience: 'all_customers',
    notifType: 'promo',
    selectedCustId: '',
    title: '',
    body: '',
    image: '',
    clickUrl: '/',
    isSending: false,
    testingAdmin: false,
    resultMsg: '',
    resultSuccess: true,
    
    sendNotification() {
        if (!this.title.trim() || !this.body.trim()) {
            alert('Please enter a notification title and message.');
            return;
        }

        if (this.audience === 'single_customer' && !this.selectedCustId) {
            alert('Please select a customer to receive the notification.');
            return;
        }

        if (this.audience === 'all_customers' && this.notifType === 'promo' && !<?= $isSuperAdmin ? 'true' : 'false' ?>) {
            alert('Only Superadmins can broadcast bulk promotional notifications.');
            return;
        }

        if (!confirm('Are you sure you want to send this push notification to ' + this.audience.replace('_', ' ') + '?')) {
            return;
        }

        this.isSending = true;
        this.resultMsg = '';

        const payload = {
            title: this.title,
            body: this.body,
            image: this.image,
            url: this.clickUrl,
            type: this.notifType,
            audience: this.audience,
            customer_id: this.selectedCustId,
            _csrf_token: '<?= csrf_token() ?>'
        };

        fetch('/admin/notifications/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(d => {
            this.isSending = false;
            if (d.success) {
                this.resultSuccess = true;
                this.resultMsg = '✓ Push broadcast complete: ' + (d.sent || 0) + ' sent, ' + (d.failed || 0) + ' failed (Total target: ' + (d.total || 0) + ').';
                this.title = '';
                this.body = '';
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

    <!-- Page Header & Enrolment Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-2">
                <svg class="w-7 h-7 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                PWA Web Push Notification Center
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                Real-time instant push notifications for admin order alerts and customer order updates without any server cron dependency.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button 
                type="button" 
                onclick="window.NA_PWA.subscribeDevice('admin').then(r => { if(r.success) alert('This device is now subscribed for instant admin order alerts!'); })"
                class="bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs py-2.5 px-4 rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                <span>Enrol Current Admin Device</span>
            </button>

            <button 
                type="button" 
                @click="testAdminDevice()" 
                :disabled="testingAdmin"
                class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold text-xs py-2.5 px-4 rounded-xl transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            >
                <span x-show="!testingAdmin">🔔 Test Push on Admin Devices</span>
                <span x-show="testingAdmin">Sending alert...</span>
            </button>
        </div>
    </div>

    <!-- Subscriber Stats Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <div class="text-[10px] font-bold text-brand-600 uppercase tracking-wider">Admin Devices</div>
            <div class="text-2xl font-black text-gray-900 mt-1"><?= (int)$stats['admin_devices'] ?></div>
            <div class="text-[11px] text-gray-400 mt-0.5">Enrolled for new order push alerts</div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <div class="text-[10px] font-bold text-sky-600 uppercase tracking-wider">Customer Subscribers</div>
            <div class="text-2xl font-black text-gray-900 mt-1"><?= (int)$stats['customer_devices'] ?></div>
            <div class="text-[11px] text-gray-400 mt-0.5">Subscribed customer browsers</div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Total Delivered</div>
            <div class="text-2xl font-black text-emerald-800 mt-1"><?= (int)$stats['total_delivered'] ?></div>
            <div class="text-[11px] text-gray-400 mt-0.5">Verified HTTP 201/200 push acceptances</div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <div class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Delivery Failures</div>
            <div class="text-2xl font-black text-amber-800 mt-1"><?= (int)$stats['total_failed'] ?></div>
            <div class="text-[11px] text-gray-400 mt-0.5">Expired / 410 tokens cleaned up automatically</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left 2 Cols: Send Notification Form -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-gray-100 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Send Web Push Notification</h2>
                    <p class="text-xs text-gray-400">Broadcast store announcements, special coconut combos, or direct customer updates.</p>
                </div>
                <span class="text-[10px] font-bold px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full border border-emerald-100">
                    VAPID Active (RFC 8292)
                </span>
            </div>

            <!-- Audience Selector -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-gray-700 uppercase">Target Audience</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <label class="p-3 border rounded-xl flex flex-col items-center justify-center cursor-pointer text-center text-xs transition" :class="audience === 'all_customers' ? 'border-brand-600 bg-brand-50/50 text-brand-800 font-bold' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                        <input type="radio" name="audience" value="all_customers" x-model="audience" class="sr-only">
                        <span>All Customers</span>
                    </label>

                    <label class="p-3 border rounded-xl flex flex-col items-center justify-center cursor-pointer text-center text-xs transition" :class="audience === 'single_customer' ? 'border-brand-600 bg-brand-50/50 text-brand-800 font-bold' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                        <input type="radio" name="audience" value="single_customer" x-model="audience" class="sr-only">
                        <span>Specific Customer</span>
                    </label>

                    <label class="p-3 border rounded-xl flex flex-col items-center justify-center cursor-pointer text-center text-xs transition" :class="audience === 'admins_only' ? 'border-brand-600 bg-brand-50/50 text-brand-800 font-bold' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                        <input type="radio" name="audience" value="admins_only" x-model="audience" class="sr-only">
                        <span>Admin Devices</span>
                    </label>

                    <label class="p-3 border rounded-xl flex flex-col items-center justify-center cursor-pointer text-center text-xs transition" :class="audience === 'selected_customers' ? 'border-brand-600 bg-brand-50/50 text-brand-800 font-bold' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                        <input type="radio" name="audience" value="selected_customers" x-model="audience" class="sr-only">
                        <span>Selected VIPs</span>
                    </label>
                </div>
            </div>

            <!-- Customer Picker (visible when single_customer) -->
            <div x-show="audience === 'single_customer'" class="space-y-1">
                <label class="block text-xs font-bold text-gray-700 uppercase">Select Target Customer</label>
                <select x-model="selectedCustId" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs">
                    <option value="">-- Choose Customer --</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['phone']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Notification Type & Purpose -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Notification Category</label>
                    <select x-model="notifType" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-semibold">
                        <option value="promo">Special Offer / Combo Promo (Requires Customer Opt-in)</option>
                        <option value="announcement">Important Store Announcement / Harvest Alert</option>
                        <option value="order_status">Order Status Notification</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Click Destination URL</label>
                    <input 
                        type="text" 
                        x-model="clickUrl" 
                        placeholder="/shop or /combos"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs"
                    >
                </div>
            </div>

            <!-- Title & Message -->
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Notification Title</label>
                    <input 
                        type="text" 
                        x-model="title" 
                        placeholder="e.g. Fresh Morning Coconuts Just Arrived! 🌴"
                        maxlength="100"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Notification Body Text</label>
                    <textarea 
                        x-model="body" 
                        rows="3" 
                        placeholder="Sweet natural coconut water and thick malai coconuts harvested today. Order now for 30-min doorstep delivery!"
                        maxlength="240"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs leading-relaxed focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Optional Banner Image URL</label>
                    <input 
                        type="text" 
                        x-model="image" 
                        placeholder="/assets/images/hero-coconut.png"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs"
                    >
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
                    class="bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs py-3 px-6 rounded-xl shadow-xs transition flex items-center gap-2 disabled:opacity-50 cursor-pointer ml-auto"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    <span x-show="!isSending">Send Notification Now</span>
                    <span x-show="isSending">Encrypting & Dispatching...</span>
                </button>
            </div>
        </div>

        <!-- Right Col: VAPID Diagnostics & iOS Guide -->
        <div class="space-y-4">
            <div class="bg-white rounded-3xl p-5 border border-gray-100 shadow-xs space-y-3">
                <h3 class="text-xs font-bold uppercase text-gray-800 tracking-wider">VAPID Key Configuration</h3>
                <p class="text-[11px] text-gray-500 leading-relaxed">
                    Standard Web Push VAPID keypair securely generated on NIST P-256 (prime256v1). The private key is stored outside web root in <code class="text-brand-700 bg-brand-50 px-1 py-0.5 rounded">storage/vapid_keys.json</code> and never exposed to clients.
                </p>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-2.5">
                    <span class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Public VAPID Key</span>
                    <p class="font-mono text-[10px] text-gray-700 break-all select-all leading-tight">
                        <?= e($stats['vapid_public_key']) ?>
                    </p>
                </div>
            </div>

            <!-- iPhone / iOS 16.4+ Support Guide -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-3xl p-5 border border-slate-700 space-y-2.5 text-xs">
                <div class="flex items-center gap-2 font-bold text-amber-300">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.85c.7-.85 1.17-2.03 1.04-3.21-1.01.04-2.23.67-2.95 1.52-.63.73-1.18 1.92-1.03 3.07 1.12.09 2.24-.53 2.94-1.38z"/></svg>
                    <span>Official iOS 16.4+ Web Push</span>
                </div>
                <p class="text-[11px] text-slate-300 leading-relaxed">
                    To receive push notifications on iPhones and iPads:
                </p>
                <ol class="list-decimal list-inside text-[11px] text-slate-300 space-y-1 leading-relaxed">
                    <li>Open <strong class="text-white">Safari</strong> on iOS 16.4+.</li>
                    <li>Tap the <strong class="text-white">Share</strong> icon at bottom.</li>
                    <li>Tap <strong class="text-white">Add to Home Screen</strong>.</li>
                    <li>Launch the app from your Home Screen.</li>
                    <li>Tap <strong class="text-white">Enrol Admin Device</strong> to grant push permission.</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Notification History Table -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-900">Notification Dispatch History</h2>
            <span class="text-xs text-gray-400">Showing last <?= count($history) ?> records</span>
        </div>

        <?php if (empty($history)): ?>
        <div class="p-8 text-center text-xs text-gray-400">
            No push notifications dispatched yet. Use the form above to send your first announcement.
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50/75 text-gray-500 uppercase text-[10px] font-bold border-b border-gray-100">
                    <tr>
                        <th class="py-3 px-4">Timestamp</th>
                        <th class="py-3 px-4">Title &amp; Message</th>
                        <th class="py-3 px-4">Audience</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4 text-center">Recipients</th>
                        <th class="py-3 px-4 text-center">Delivered</th>
                        <th class="py-3 px-4">Sent By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <?php foreach ($history as $h): ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="py-3 px-4 whitespace-nowrap text-gray-400 text-[11px]">
                            <?= date('M d, H:i', strtotime($h['created_at'])) ?>
                        </td>
                        <td class="py-3 px-4">
                            <span class="font-bold text-gray-900 block"><?= e($h['title']) ?></span>
                            <span class="text-[11px] text-gray-500 truncate max-w-xs block"><?= e($h['body']) ?></span>
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $h['audience'] === 'admins_only' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                <?= str_replace('_', ' ', $h['audience']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap uppercase text-[10px] font-semibold text-gray-500">
                            <?= e($h['notification_type']) ?>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-gray-900">
                            <?= (int)$h['total_recipients'] ?>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-emerald-700">
                            <?= (int)$h['success_count'] ?>
                            <?php if ($h['failure_count'] > 0): ?>
                            <span class="text-red-500 text-[10px] font-normal block">(<?= (int)$h['failure_count'] ?> failed)</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-gray-500 text-[11px]">
                            <?= e($h['admin_name'] ?? 'System') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require __DIR__ . '/layout-footer.php'; ?>
