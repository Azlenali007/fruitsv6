<?php
/**
 * Admin Loyalty & Instagram Story Rewards Management View
 * N.A Fresh Fruits & Coconuts
 */
$pageTitle = 'Customer Loyalty & Story Rewards - Admin';
$activeAdminTab = 'loyalty';

$tab = $_GET['tab'] ?? 'submissions';
if (!in_array($tab, ['submissions', 'wallets', 'vouchers', 'settings'])) {
    $tab = 'submissions';
}

$pdo = Database::getConnection();

// Fetch summary metrics
$stmtMetrics = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM `loyalty_story_submissions` WHERE `status` = 'pending') AS pending_submissions,
        (SELECT COUNT(*) FROM `loyalty_story_submissions` WHERE `status` = 'approved') AS approved_submissions,
        (SELECT COUNT(*) FROM `loyalty_story_submissions` WHERE `status` = 'rejected') AS rejected_submissions,
        (SELECT COALESCE(SUM(`current_balance`), 0) FROM `loyalty_wallets`) AS total_coins_in_circulation,
        (SELECT COUNT(*) FROM `loyalty_vouchers` WHERE `is_used` = 0 AND `expires_at` > NOW()) AS active_vouchers
");
$metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC);

// Tab 1: Submissions Queue
$statusFilter = $_GET['status'] ?? 'pending';
$whereClause = "";
$params = [];
if ($statusFilter !== 'all') {
    $whereClause = "WHERE s.status = ?";
    $params[] = $statusFilter;
}

$stmtSubs = $pdo->prepare("
    SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, o.total_amount AS order_amount
    FROM `loyalty_story_submissions` s
    JOIN `customers` c ON s.customer_id = c.id
    JOIN `orders` o ON s.order_id = o.id
    {$whereClause}
    ORDER BY s.id DESC
");
$stmtSubs->execute($params);
$submissions = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

// Tab 2: Customer Wallets & Ledger
$searchQuery = trim($_GET['q'] ?? '');
$walletWhere = "";
$walletParams = [];
if (!empty($searchQuery)) {
    $walletWhere = "WHERE c.name LIKE ? OR c.phone LIKE ?";
    $walletParams = ["%{$searchQuery}%", "%{$searchQuery}%"];
}

$stmtWallets = $pdo->prepare("
    SELECT w.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email
    FROM `loyalty_wallets` w
    JOIN `customers` c ON w.customer_id = c.id
    {$walletWhere}
    ORDER BY w.current_balance DESC
    LIMIT 100
");
$stmtWallets->execute($walletParams);
$wallets = $stmtWallets->fetchAll(PDO::FETCH_ASSOC);

// Recent Ledger Entries
$stmtRecentTx = $pdo->query("
    SELECT t.*, c.name AS customer_name, c.phone AS customer_phone, u.name AS admin_name
    FROM `loyalty_transactions` t
    JOIN `customers` c ON t.customer_id = c.id
    LEFT JOIN `admins` u ON t.admin_id = u.id
    ORDER BY t.id DESC
    LIMIT 30
");
$recentLedger = $stmtRecentTx->fetchAll(PDO::FETCH_ASSOC);

// Tab 3: Generated Vouchers
$stmtVouchers = $pdo->query("
    SELECT v.*, c.name AS customer_name, c.phone AS customer_phone, o.order_number AS used_order_number
    FROM `loyalty_vouchers` v
    JOIN `customers` c ON v.customer_id = c.id
    LEFT JOIN `orders` o ON v.used_order_id = o.id
    ORDER BY v.id DESC
    LIMIT 100
");
$vouchers = $stmtVouchers->fetchAll(PDO::FETCH_ASSOC);

// Tab 4: Current Settings
$loyaltyEnabled = LoyaltyService::isEnabled();
$instagramHandle = Setting::get(LoyaltyService::SETTING_INSTAGRAM_HANDLE, 'nafreshfruitsjaora');
$instagramProfileUrl = Setting::get(LoyaltyService::SETTING_INSTAGRAM_URL, 'https://instagram.com/nafreshfruitsjaora');
$coinsPerStory = Setting::get(LoyaltyService::SETTING_COINS_PER_STORY, '100');
$redemptionThreshold = Setting::get(LoyaltyService::SETTING_REDEMPTION_THRESHOLD, '100');
$discountPercent = Setting::get(LoyaltyService::SETTING_DISCOUNT_PERCENT, '50.00');
$maxDiscount = Setting::get(LoyaltyService::SETTING_MAX_DISCOUNT, '150.00');
$voucherExpiryDays = Setting::get(LoyaltyService::SETTING_EXPIRY_DAYS, '30');

require_once __DIR__ . '/layout-header.php';
?>

<div class="space-y-6" x-data="adminLoyaltyManager()">
    <!-- Header with Breadcrumbs & Action -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                <a href="/admin" class="hover:text-gray-700">Admin</a>
                <span>/</span>
                <span class="text-gray-900 font-semibold">Loyalty & Story Rewards</span>
            </div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-2.5">
                <span>🪙</span>
                <span>Customer Loyalty & Instagram Rewards</span>
            </h1>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold <?= $loyaltyEnabled ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <span class="w-2 h-2 rounded-full <?= $loyaltyEnabled ? 'bg-emerald-500 animate-ping' : 'bg-red-500' ?>"></span>
                <span><?= $loyaltyEnabled ? 'Loyalty Active' : 'Loyalty Paused' ?></span>
            </span>
        </div>
    </div>

    <!-- Quick Metrics Strip -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Pending Review</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-amber-600"><?= $metrics['pending_submissions'] ?></span>
                <span class="text-[10px] text-gray-400">stories</span>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Approved Stories</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-emerald-600"><?= $metrics['approved_submissions'] ?></span>
                <span class="text-[10px] text-gray-400">verified</span>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Coins Circulating</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-yellow-600"><?= number_format($metrics['total_coins_in_circulation']) ?></span>
                <span class="text-[10px] text-gray-400">coins</span>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Active Vouchers</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-indigo-600"><?= $metrics['active_vouchers'] ?></span>
                <span class="text-[10px] text-gray-400">50% off</span>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Reward Ratio</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-lg font-black text-gray-800">100 = 50%</span>
                <span class="text-[10px] text-gray-400">OFF</span>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-1.5 flex flex-wrap gap-1 text-xs font-bold">
        <a href="/admin/loyalty?tab=submissions"
           class="px-4 py-2.5 rounded-xl transition flex items-center gap-2 <?= $tab === 'submissions' ? 'bg-[#235d3a] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-50' ?>">
            <span>📸 Story Review Queue</span>
            <?php if ($metrics['pending_submissions'] > 0): ?>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black <?= $tab === 'submissions' ? 'bg-amber-400 text-slate-950' : 'bg-amber-100 text-amber-800' ?>">
                    <?= $metrics['pending_submissions'] ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="/admin/loyalty?tab=wallets"
           class="px-4 py-2.5 rounded-xl transition flex items-center gap-2 <?= $tab === 'wallets' ? 'bg-[#235d3a] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-50' ?>">
            <span>👛 Customer Wallets & Ledger</span>
        </a>

        <a href="/admin/loyalty?tab=vouchers"
           class="px-4 py-2.5 rounded-xl transition flex items-center gap-2 <?= $tab === 'vouchers' ? 'bg-[#235d3a] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-50' ?>">
            <span>🎟️ 50% Discount Vouchers</span>
        </a>

        <a href="/admin/loyalty?tab=settings"
           class="px-4 py-2.5 rounded-xl transition flex items-center gap-2 <?= $tab === 'settings' ? 'bg-[#235d3a] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-50' ?>">
            <span>⚙️ Reward Settings</span>
        </a>
    </div>

    <!-- TAB 1: STORY REVIEW QUEUE -->
    <?php if ($tab === 'submissions'): ?>
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <!-- Filter Bar -->
            <div class="p-4 sm:p-5 border-b border-gray-100 flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-500 uppercase">Filter Status:</span>
                    <a href="/admin/loyalty?tab=submissions&status=pending"
                       class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $statusFilter === 'pending' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        Pending (<?= $metrics['pending_submissions'] ?>)
                    </a>
                    <a href="/admin/loyalty?tab=submissions&status=approved"
                       class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $statusFilter === 'approved' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        Approved (<?= $metrics['approved_submissions'] ?>)
                    </a>
                    <a href="/admin/loyalty?tab=submissions&status=rejected"
                       class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $statusFilter === 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        Rejected (<?= $metrics['rejected_submissions'] ?>)
                    </a>
                    <a href="/admin/loyalty?tab=submissions&status=all"
                       class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $statusFilter === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        All
                    </a>
                </div>
            </div>

            <?php if (empty($submissions)): ?>
                <div class="p-12 text-center text-gray-500 space-y-2">
                    <span class="text-3xl block">📭</span>
                    <h3 class="text-sm font-bold text-gray-900">No Story Submissions Found</h3>
                    <p class="text-xs text-gray-400">There are currently no submissions matching this filter.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-400 border-b border-gray-100">
                            <tr>
                                <th class="py-3 px-4">Proof Screenshot</th>
                                <th class="py-3 px-4">Customer Details</th>
                                <th class="py-3 px-4">Order #</th>
                                <th class="py-3 px-4">Instagram Handle</th>
                                <th class="py-3 px-4">Submitted At</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($submissions as $sub): ?>
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="py-3 px-4">
                                        <button type="button" @click="viewProofModal('<?= (int)$sub['id'] ?>', '<?= e($sub['order_number']) ?>', '<?= e($sub['instagram_username']) ?>')"
                                                class="w-14 h-14 rounded-xl border border-gray-200 overflow-hidden bg-gray-100 hover:opacity-80 transition relative group block">
                                            <img src="/admin/loyalty/proof-image?id=<?= $sub['id'] ?>"
                                                 alt="Proof"
                                                 class="w-full h-full object-cover">
                                            <span class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-[10px] font-bold transition">
                                                🔍
                                            </span>
                                        </button>
                                    </td>
                                    <td class="py-3 px-4">
                                        <strong class="text-gray-900 block"><?= e($sub['customer_name']) ?></strong>
                                        <span class="text-gray-500 text-[11px]"><?= e($sub['customer_phone']) ?></span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <a href="/admin/orders/view?id=<?= $sub['order_id'] ?>" class="font-bold text-[#235d3a] hover:underline">
                                            #<?= e($sub['order_number']) ?>
                                        </a>
                                        <span class="text-gray-400 block text-[10px]">₹<?= number_format((float)$sub['order_amount'], 2) ?></span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <a href="https://instagram.com/<?= urlencode($sub['instagram_username']) ?>" target="_blank"
                                           class="font-bold text-pink-600 hover:underline inline-flex items-center gap-1">
                                            <span>@<?= e($sub['instagram_username']) ?></span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                        </a>
                                    </td>
                                    <td class="py-3 px-4 text-gray-500 text-[11px]">
                                        <?= date('d M Y, h:i A', strtotime($sub['created_at'])) ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($sub['status'] === 'pending'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200">
                                                Pending Review
                                            </span>
                                        <?php elseif ($sub['status'] === 'approved'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                Approved (+<?= (int)$sub['coins_awarded'] ?> Coins)
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-red-50 text-red-800 border border-red-200">
                                                Rejected
                                            </span>
                                            <?php if (!empty($sub['rejection_reason'])): ?>
                                                <p class="text-[10px] text-red-600 mt-1 max-w-xs truncate" title="<?= e($sub['rejection_reason']) ?>">
                                                    Reason: <?= e($sub['rejection_reason']) ?>
                                                </p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <?php if ($sub['status'] === 'pending'): ?>
                                            <div class="flex items-center justify-end gap-1.5">
                                                <form action="/admin/loyalty/approve" method="POST" class="inline" onsubmit="return confirm('Approve this story and credit 100 coins to <?= e(addslashes($sub['customer_name'])) ?>?');">
                                                    <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                                    <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition">
                                                        ✓ Approve
                                                    </button>
                                                </form>

                                                <button type="button" @click="openRejectModal('<?= (int)$sub['id'] ?>', '<?= e($sub['customer_name']) ?>', '<?= e($sub['order_number']) ?>')"
                                                        class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 font-bold text-xs rounded-xl transition">
                                                    ✕ Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-[11px]">Reviewed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 2: CUSTOMER WALLETS & AUDIT LEDGER -->
    <?php if ($tab === 'wallets'): ?>
        <div class="space-y-6">
            <!-- Search & Wallets Table -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                    <h2 class="text-base font-extrabold text-gray-900">Customer Loyalty Wallets</h2>
                    <form action="/admin/loyalty" method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="wallets">
                        <input type="text"
                               name="q"
                               value="<?= e($searchQuery) ?>"
                               placeholder="Search customer name or phone..."
                               class="bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2 text-xs focus:ring-2 focus:ring-[#235d3a] focus:outline-none w-64">
                        <button type="submit" class="bg-[#235d3a] hover:bg-[#1b4a2e] text-white font-bold text-xs px-4 py-2 rounded-xl transition">
                            Search
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-400 border-b border-gray-100">
                            <tr>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-4">Contact</th>
                                <th class="py-3 px-4 text-right">Current Balance</th>
                                <th class="py-3 px-4 text-right">Total Earned</th>
                                <th class="py-3 px-4 text-right">Total Redeemed</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($wallets as $w): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="py-3 px-4 font-bold text-gray-900"><?= e($w['customer_name']) ?></td>
                                    <td class="py-3 px-4 text-gray-500"><?= e($w['customer_phone']) ?></td>
                                    <td class="py-3 px-4 text-right">
                                        <span class="font-black text-amber-600 text-sm"><?= (int)$w['current_balance'] ?></span>
                                        <span class="text-[10px] text-gray-400">coins</span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-bold text-emerald-600"><?= (int)$w['total_earned'] ?></td>
                                    <td class="py-3 px-4 text-right font-bold text-gray-500"><?= (int)$w['total_redeemed'] ?></td>
                                    <td class="py-3 px-4 text-right">
                                        <button type="button" @click="openAdjustModal('<?= (int)$w['customer_id'] ?>', '<?= e($w['customer_name']) ?>', '<?= (int)$w['current_balance'] ?>')"
                                                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-xl transition">
                                            ✏️ Adjust Balance
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Transaction Audit Ledger -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">System-Wide Transaction Audit Ledger</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-400 border-b border-gray-100">
                            <tr>
                                <th class="py-2.5 px-3">Timestamp</th>
                                <th class="py-2.5 px-3">Customer</th>
                                <th class="py-2.5 px-3">Transaction Type</th>
                                <th class="py-2.5 px-3 text-right">Coins</th>
                                <th class="py-2.5 px-3 text-right">Balance After</th>
                                <th class="py-2.5 px-3">Reason / Description</th>
                                <th class="py-2.5 px-3">Authorized By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-[11px]">
                            <?php foreach ($recentLedger as $tx): ?>
                                <tr class="hover:bg-gray-50/50">
                                    <td class="py-2 px-3 text-gray-500"><?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?></td>
                                    <td class="py-2 px-3 font-semibold text-gray-800"><?= e($tx['customer_name']) ?></td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-0.5 rounded-md font-bold uppercase text-[9px] <?= $tx['amount'] > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                                            <?= e(str_replace('_', ' ', $tx['type'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-right font-black <?= $tx['amount'] > 0 ? 'text-emerald-600' : 'text-amber-600' ?>">
                                        <?= $tx['amount'] > 0 ? '+' . (int)$tx['amount'] : (int)$tx['amount'] ?>
                                    </td>
                                    <td class="py-2 px-3 text-right font-bold text-gray-700"><?= (int)$tx['balance_after'] ?></td>
                                    <td class="py-2 px-3 text-gray-600 max-w-sm truncate"><?= e($tx['description']) ?></td>
                                    <td class="py-2 px-3 text-gray-400 text-[10px]"><?= e($tx['admin_name'] ?? 'System') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 3: 50% DISCOUNT VOUCHERS -->
    <?php if ($tab === 'vouchers'): ?>
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-gray-900">Generated 50% Discount Vouchers</h2>
                    <p class="text-xs text-gray-500">Unique single-use vouchers redeemed by customers using 100 loyalty coins</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-400 border-b border-gray-100">
                        <tr>
                            <th class="py-3 px-4">Voucher Code</th>
                            <th class="py-3 px-4">Customer</th>
                            <th class="py-3 px-4">Discount %</th>
                            <th class="py-3 px-4">Max Cap</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Used On Order</th>
                            <th class="py-3 px-4">Expires At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($vouchers as $v): ?>
                            <tr class="hover:bg-gray-50/60">
                                <td class="py-3 px-4 font-mono font-black text-gray-900"><?= e($v['code']) ?></td>
                                <td class="py-3 px-4">
                                    <strong class="text-gray-800 block"><?= e($v['customer_name']) ?></strong>
                                    <span class="text-gray-400 text-[11px]"><?= e($v['customer_phone']) ?></span>
                                </td>
                                <td class="py-3 px-4 font-bold text-emerald-600"><?= (float)$v['discount_percent'] ?>%</td>
                                <td class="py-3 px-4 text-gray-600">₹<?= number_format((float)$v['max_discount_amount'], 2) ?></td>
                                <td class="py-3 px-4">
                                    <?php if (!empty($v['is_used'])): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-gray-100 text-gray-600">USED</span>
                                    <?php elseif (strtotime($v['expires_at']) < time()): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-red-50 text-red-700 border border-red-200">EXPIRED</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">ACTIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if (!empty($v['used_order_number'])): ?>
                                        <span class="font-bold text-[#235d3a]">#<?= e($v['used_order_number']) ?></span>
                                        <span class="text-gray-400 text-[10px] block"><?= date('d M Y', strtotime($v['used_at'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-gray-500 text-[11px]">
                                    <?= date('d M Y', strtotime($v['expires_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 4: REWARD SETTINGS -->
    <?php if ($tab === 'settings'): ?>
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 max-w-2xl space-y-6">
            <div>
                <h2 class="text-lg font-black text-gray-900">Loyalty Rewards Program Configuration</h2>
                <p class="text-xs text-gray-500 mt-1">Configure coins reward ratios, official Instagram handle, and voucher discount caps</p>
            </div>

            <form action="/admin/loyalty/settings" method="POST" class="space-y-5 text-xs">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">

                <!-- Program Toggle -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-200">
                    <div>
                        <strong class="text-gray-900 block text-sm">Enable Loyalty & Story Rewards</strong>
                        <p class="text-gray-500 text-[11px] mt-0.5">Allow customers to submit stories and redeem 50% discount vouchers</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="loyalty_enabled" value="1" class="sr-only peer" <?= $loyaltyEnabled ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#235d3a]"></div>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Official Instagram Handle</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 font-bold pointer-events-none">@</span>
                            <input type="text"
                                   name="instagram_handle"
                                   value="<?= e($instagramHandle) ?>"
                                   required
                                   class="w-full pl-7 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#235d3a] focus:bg-white focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Instagram Profile URL</label>
                        <input type="url"
                               name="instagram_profile_url"
                               value="<?= e($instagramProfileUrl) ?>"
                               required
                               class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#235d3a] focus:bg-white focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Coins Awarded Per Approved Story</label>
                        <input type="number"
                               name="loyalty_coins_per_story"
                               value="<?= (int)$coinsPerStory ?>"
                               required
                               min="1"
                               class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#235d3a] focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Redemption Threshold (Coins)</label>
                        <input type="number"
                               name="loyalty_redemption_threshold"
                               value="<?= (int)$redemptionThreshold ?>"
                               required
                               min="10"
                               class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#235d3a] focus:bg-white focus:outline-none">
                        <span class="text-[10px] text-gray-400 mt-0.5 block">Coins deducted per 50% discount voucher (Default: 100)</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Max Voucher Discount Cap (₹)</label>
                        <input type="number"
                               name="loyalty_voucher_max_discount"
                               value="<?= (float)$maxDiscount ?>"
                               step="0.01"
                               required
                               class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#235d3a] focus:bg-white focus:outline-none">
                        <span class="text-[10px] text-gray-400 mt-0.5 block">Upper limit in ₹ for 50% off (Default: ₹150)</span>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Voucher Validity (Days)</label>
                        <input type="number"
                               name="loyalty_voucher_expiry_days"
                               value="<?= (int)$voucherExpiryDays ?>"
                               required
                               min="1"
                               class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#235d3a] focus:bg-white focus:outline-none">
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100 flex justify-end">
                    <button type="submit" class="bg-[#235d3a] hover:bg-[#1b4a2e] text-white font-bold text-xs py-2.5 px-6 rounded-xl shadow-xs transition">
                        Save Loyalty Configuration
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- MODAL: PROOF SCREENSHOT VIEWER -->
    <div x-show="showProofModal" x-cloak
         class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-lg w-full space-y-4 shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-sm font-extrabold text-gray-900" x-text="'Story Proof: Order #' + proofOrderNumber"></h3>
                    <p class="text-xs text-pink-600 font-bold" x-text="'@' + proofHandle"></p>
                </div>
                <button type="button" @click="showProofModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
            </div>
            <div class="max-h-[65vh] overflow-auto rounded-2xl bg-gray-950 flex items-center justify-center p-2">
                <img :src="'/admin/loyalty/proof-image?id=' + proofSubId" alt="Proof Full" class="max-h-[60vh] object-contain rounded-xl">
            </div>
            <div class="flex justify-end">
                <button type="button" @click="showProofModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-xl">
                    Close Preview
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: REJECT SUBMISSION -->
    <div x-show="showRejectModal" x-cloak
         class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-sm font-extrabold text-gray-900">Reject Story Submission</h3>
                <button type="button" @click="showRejectModal = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
            </div>
            <form action="/admin/loyalty/reject" method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                <input type="hidden" name="submission_id" :value="rejectSubId">

                <p class="text-xs text-gray-600">
                    Rejecting story for <strong x-text="rejectCustName"></strong> on Order <strong x-text="'#' + rejectOrderNum"></strong>.
                </p>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Mandatory Rejection Reason</label>
                    <textarea name="rejection_reason"
                              required
                              rows="3"
                              placeholder="e.g. Account tag @nafreshfruitsjaora is missing, story screenshot expired, or invalid photo."
                              class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-red-500 focus:outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showRejectModal = false" class="px-4 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-xl">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-xs">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: MANUAL BALANCE ADJUSTMENT -->
    <div x-show="showAdjustModal" x-cloak
         class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-sm font-extrabold text-gray-900">Manual Coin Balance Adjustment</h3>
                <button type="button" @click="showAdjustModal = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
            </div>
            <form action="/admin/loyalty/adjust" method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                <input type="hidden" name="customer_id" :value="adjustCustId">

                <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs">
                    Customer: <strong x-text="adjustCustName" class="text-gray-900"></strong><br>
                    Current Balance: <strong x-text="adjustCurrentBal + ' Coins'" class="text-amber-600"></strong>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Adjustment Amount (Positive to add, Negative to deduct)</label>
                    <input type="number"
                           name="amount"
                           required
                           placeholder="e.g. 50 or -50"
                           class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#235d3a] focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1">Mandatory Audit Reason</label>
                    <input type="text"
                           name="reason"
                           required
                           placeholder="e.g. Customer loyalty compensation, promotional campaign credit..."
                           class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#235d3a] focus:outline-none">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showAdjustModal = false" class="px-4 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-xl">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-[#235d3a] hover:bg-[#1b4a2e] text-white font-bold text-xs rounded-xl shadow-xs">
                        Save Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function adminLoyaltyManager() {
    return {
        showProofModal: false,
        proofSubId: null,
        proofOrderNumber: '',
        proofHandle: '',

        showRejectModal: false,
        rejectSubId: null,
        rejectCustName: '',
        rejectOrderNum: '',

        showAdjustModal: false,
        adjustCustId: null,
        adjustCustName: '',
        adjustCurrentBal: 0,

        viewProofModal(subId, orderNum, handle) {
            this.proofSubId = subId;
            this.proofOrderNumber = orderNum;
            this.proofHandle = handle;
            this.showProofModal = true;
        },

        openRejectModal(subId, name, orderNum) {
            this.rejectSubId = subId;
            this.rejectCustName = name;
            this.rejectOrderNum = orderNum;
            this.showRejectModal = true;
        },

        openAdjustModal(cid, name, bal) {
            this.adjustCustId = cid;
            this.adjustCustName = name;
            this.adjustCurrentBal = bal;
            this.showAdjustModal = true;
        }
    };
}
</script>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
