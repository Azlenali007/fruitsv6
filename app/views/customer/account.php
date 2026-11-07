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

// Loyalty & Rewards Data
$wallet = LoyaltyService::getWallet($customerId);
$vouchers = LoyaltyService::getCustomerVouchers($customerId);
$ledger = LoyaltyService::getLedger($customerId, 15);
$storySubmissions = LoyaltyService::getCustomerSubmissions($customerId);
$loyaltyThreshold = (int)Setting::get(LoyaltyService::SETTING_REDEMPTION_THRESHOLD, '100');
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
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Loyalty Coins</span>
            <p class="text-2xl sm:text-3xl font-black text-amber-500"><?= (int)$wallet['current_balance'] ?> 🪙</p>
        </div>
    </div>

    <!-- CUSTOMER LOYALTY WALLET & 50% VOUCHER REDEMPTION SECTION -->
    <div class="bg-gradient-to-br from-amber-500/10 via-white to-emerald-500/10 rounded-3xl p-6 sm:p-8 border border-amber-200 shadow-sm space-y-6"
         x-data="loyaltyWallet(<?= htmlspecialchars(json_encode([
             'balance' => (int)$wallet['current_balance'],
             'totalEarned' => (int)$wallet['total_earned'],
             'totalRedeemed' => (int)$wallet['total_redeemed'],
             'threshold' => $loyaltyThreshold
         ]), ENT_QUOTES, 'UTF-8') ?>)">
        
        <!-- Header & Balance -->
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 border-b border-amber-100 pb-5">
            <div class="flex items-center gap-3.5">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-400 to-yellow-500 text-slate-950 flex items-center justify-center text-2xl font-black shadow-md shadow-amber-400/30">
                    🪙
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 bg-amber-100 px-2 py-0.5 rounded-md">
                        N.A Fresh Rewards Wallet
                    </span>
                    <h2 class="text-xl sm:text-2xl font-black text-gray-900 mt-1">Loyalty Coin Balance</h2>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="text-right">
                    <span class="text-3xl sm:text-4xl font-black text-amber-600 block leading-tight" x-text="balance + ' Coins'">
                        <?= (int)$wallet['current_balance'] ?> Coins
                    </span>
                    <span class="text-[11px] text-gray-500">
                        Earned: <?= (int)$wallet['total_earned'] ?> • Redeemed: <?= (int)$wallet['total_redeemed'] ?>
                    </span>
                </div>

                <button type="button"
                        @click="redeemVoucher()"
                        :disabled="balance < threshold || redeeming"
                        class="px-5 py-3 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-600 hover:to-yellow-600 active:scale-95 text-slate-950 font-black text-xs rounded-2xl shadow-lg shadow-amber-500/30 transition disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                    <span x-show="!redeeming">🎟️ Redeem 100 Coins (50% OFF)</span>
                    <span x-show="redeeming" x-cloak class="flex items-center gap-1.5">
                        <svg class="animate-spin w-4 h-4 text-slate-950" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Generating Voucher...</span>
                    </span>
                </button>
            </div>
        </div>

        <!-- Available Vouchers List -->
        <div>
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Your 50% Discount Vouchers</h3>
            <?php if (empty($vouchers)): ?>
                <div class="p-4 bg-white/70 rounded-2xl border border-gray-100 text-center text-xs text-gray-500">
                    No vouchers generated yet. Share your delivered orders on Instagram to earn 100 coins and redeem a 50% discount voucher!
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($vouchers as $v): ?>
                        <div class="p-4 rounded-2xl border transition shadow-xs <?= empty($v['is_used']) ? 'bg-gradient-to-br from-emerald-50 to-white border-emerald-300' : 'bg-gray-50 border-gray-200 opacity-60' ?>">
                            <div class="flex items-center justify-between pb-2 border-b <?= empty($v['is_used']) ? 'border-emerald-100' : 'border-gray-200' ?>">
                                <span class="font-mono text-sm font-black text-gray-900 tracking-wider"><?= e($v['code']) ?></span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase <?= empty($v['is_used']) ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' ?>">
                                    <?= empty($v['is_used']) ? '50% OFF ACTIVE' : 'USED' ?>
                                </span>
                            </div>
                            <div class="mt-2 text-[11px] text-gray-600 flex items-center justify-between">
                                <span>Max discount: ₹<?= number_format((float)$v['max_discount_amount'], 2) ?></span>
                                <span>Expires: <?= date('d M Y', strtotime($v['expires_at'])) ?></span>
                            </div>
                            <?php if (empty($v['is_used'])): ?>
                                <div class="mt-3 pt-2 border-t border-emerald-100/60 flex items-center justify-between">
                                    <button type="button" @click="copyVoucher('<?= e($v['code']) ?>')"
                                            class="text-xs font-bold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                                        <span>📋 Copy Code</span>
                                    </button>
                                    <a href="/shop" class="text-xs font-extrabold text-brand-600 hover:underline">Apply in Cart &rarr;</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Auditable Transaction Ledger Table -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Coin Transaction Ledger</h3>
                <span class="text-[10px] text-gray-400">Date-wise audit trail</span>
            </div>
            <?php if (empty($ledger)): ?>
                <p class="text-xs text-gray-400">No transaction records yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto bg-white rounded-2xl border border-gray-100 shadow-xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-400 border-b border-gray-100">
                            <tr>
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Type</th>
                                <th class="py-2.5 px-3 text-right">Coins</th>
                                <th class="py-2.5 px-3 text-right">Balance</th>
                                <th class="py-2.5 px-3">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-[11px]">
                            <?php foreach ($ledger as $tx): ?>
                                <tr class="hover:bg-gray-50/50">
                                    <td class="py-2 px-3 text-gray-500"><?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?></td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-0.5 rounded-md font-bold uppercase text-[9px] <?= $tx['amount'] > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                                            <?= e(str_replace('_', ' ', $tx['type'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-right font-black <?= $tx['amount'] > 0 ? 'text-emerald-600' : 'text-amber-600' ?>">
                                        <?= $tx['amount'] > 0 ? '+' . (int)$tx['amount'] : (int)$tx['amount'] ?>
                                    </td>
                                    <td class="py-2 px-3 text-right font-bold text-gray-700"><?= (int)$tx['balance_after'] ?></td>
                                    <td class="py-2 px-3 text-gray-500 max-w-xs truncate"><?= e($tx['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
                        <span class="truncate pr-2">
                            <?php foreach ($order['items'] as $it): ?>
                                <?= e($it['product_name']) ?> &times; <?= (int)$it['quantity'] ?>
                            <?php endforeach; ?>
                        </span>
                        <div class="flex items-center gap-2 shrink-0">
                            <?php if ($order['order_status'] === 'delivered'): ?>
                            <a href="/account/orders/invoice/download?num=<?= urlencode($order['order_number']) ?>&h=<?= urlencode($order['tracking_hash']) ?>" class="text-emerald-700 hover:text-emerald-800 font-bold text-[11px] inline-flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Invoice</span>
                            </a>
                            <?php endif; ?>
                            <a href="/track?h=<?= urlencode($order['tracking_hash']) ?>" class="text-brand-600 hover:text-brand-700 font-bold text-[11px] inline-flex items-center gap-0.5">
                                <span><?= $order['order_status'] === 'delivered' ? 'Details' : 'Track' ?></span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
<script>
function loyaltyWallet(initial) {
    return {
        balance: initial.balance,
        totalEarned: initial.totalEarned,
        totalRedeemed: initial.totalRedeemed,
        threshold: initial.threshold,
        redeeming: false,

        async redeemVoucher() {
            if (this.balance < this.threshold) {
                alert(`You need at least ${this.threshold} coins to redeem a 50% discount voucher.`);
                return;
            }
            if (!confirm(`Redeem ${this.threshold} Loyalty Coins for a 50% Discount Voucher?`)) {
                return;
            }

            this.redeeming = true;
            try {
                const res = await fetch('/api/loyalty/redeem-voucher', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?= csrf_token() ?>',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ <?= CSRF_TOKEN_KEY ?>: '<?= csrf_token() ?>' })
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message || `Voucher ${data.voucher_code} generated successfully!`);
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to redeem voucher.');
                }
            } catch(e) {
                alert('Network error during voucher redemption.');
            } finally {
                this.redeeming = false;
            }
        },

        copyVoucher(code) {
            navigator.clipboard.writeText(code).then(() => {
                alert(`Voucher code "${code}" copied to clipboard! Paste it at checkout for 50% off.`);
            }).catch(() => {
                prompt('Copy your voucher code:', code);
            });
        }
    };
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
