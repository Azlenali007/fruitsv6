<?php
/**
 * Admin Combo Offers & Product Bundles Management
 * N.A Fresh Fruits & Coconuts
 */
$adminTitle = 'Combo Offers & Bundles - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'combos';
require_once __DIR__ . '/layout-header.php';

$combos = Combo::getAll(false);
?>

<div class="max-w-6xl mx-auto space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Combo Offers & Product Bundles</h1>
            <p class="text-xs text-gray-500">Create multi-product bundles with special bundle pricing and savings</p>
        </div>

        <a href="/admin/combos/new" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold shadow-xs transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
            <span>Create New Combo</span>
        </a>
    </div>

    <!-- Combos Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php if (empty($combos)): ?>
        <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-gray-200/80 space-y-3">
            <div class="w-12 h-12 mx-auto rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            </div>
            <h3 class="text-base font-bold text-gray-900">No combo offers yet</h3>
            <p class="text-xs text-gray-500 max-w-sm mx-auto">Create your first bundle offer combining multiple products for your customers with special discounts.</p>
            <a href="/admin/combos/new" class="inline-flex items-center gap-1 px-4 py-2 bg-brand-600 text-white rounded-xl text-xs font-bold">Create Combo</a>
        </div>
        <?php else: ?>
        <?php foreach ($combos as $combo): ?>
        <div class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden flex flex-col justify-between hover:border-brand-200 transition">
            <div>
                <!-- Image & Savings Header -->
                <div class="relative aspect-[16/9] bg-emerald-50/50 overflow-hidden border-b border-gray-100">
                    <img src="<?= e($combo['image_url']) ?>" alt="<?= e($combo['name']) ?>" class="w-full h-full object-cover">
                    
                    <?php if ((float)$combo['savings'] > 0): ?>
                    <div class="absolute top-3 left-3 bg-red-600 text-white text-[11px] font-black px-2.5 py-1 rounded-lg shadow-sm">
                        SAVE ₹<?= number_format($combo['savings']) ?> (<?= $combo['discount_percent'] ?>% OFF)
                    </div>
                    <?php endif; ?>

                    <div class="absolute top-3 right-3">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $combo['is_available'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600 border border-gray-200' ?>">
                            <?= $combo['is_available'] ? 'Active' : 'Disabled' ?>
                        </span>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-5 space-y-3">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 leading-snug"><?= e($combo['name']) ?></h3>
                        <?php if (!empty($combo['tagline'])): ?>
                            <p class="text-xs text-gray-400 font-semibold mt-0.5"><?= e($combo['tagline']) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Included items list -->
                    <div class="bg-gray-50/80 rounded-xl p-3 border border-gray-100 text-xs space-y-1">
                        <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider block">Bundle Contains:</span>
                        <?php foreach ($combo['items'] as $item): ?>
                        <div class="flex items-center justify-between text-gray-700 font-medium text-[11px]">
                            <span>• <?= (int)$item['quantity'] ?>&times; <?= e($item['product_name']) ?></span>
                            <span class="text-gray-400 text-[10px]">₹<?= number_format((float)$item['product_price'] * (int)$item['quantity']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Price row -->
                    <div class="flex items-baseline justify-between pt-1">
                        <div>
                            <span class="text-xs text-gray-400 font-bold block">Combo Price</span>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-xl font-black text-brand-700">₹<?= number_format($combo['price'], 2) ?></span>
                                <span class="line-through text-gray-400 text-xs">₹<?= number_format($combo['original_price'], 2) ?></span>
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="text-xs text-gray-400 font-bold block">Stock</span>
                            <span class="text-xs font-bold text-gray-800"><?= (int)$combo['stock_quantity'] ?> units</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Actions -->
            <div class="p-4 bg-gray-50/70 border-t border-gray-100 flex items-center justify-between text-xs">
                <a 
                    href="/admin/combos/edit?id=<?= $combo['id'] ?>" 
                    class="font-bold text-brand-700 hover:text-brand-800 inline-flex items-center gap-1"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    <span>Edit Bundle</span>
                </a>

                <form action="/admin/combos/delete" method="POST" onsubmit="return confirm('Delete combo offer \'<?= e($combo['name']) ?>\'?');">
                    <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                    <input type="hidden" name="combo_id" value="<?= $combo['id'] ?>">
                    <button type="submit" class="text-gray-400 hover:text-red-600 transition p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
