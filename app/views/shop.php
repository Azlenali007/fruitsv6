<?php
/**
 * Multi-Product Shop View - Dual Store (Coconut Store & Juice Store)
 * N.A Fresh Fruits & Coconuts
 */
$currentStore = StoreService::getCurrentStore();
$storeMeta = StoreService::getStoreMeta($currentStore);
$isJuiceMode = ($currentStore === 'juice');

$pageTitle = $isJuiceMode 
    ? 'Juice Bar Menu - 100% Pure Cold-Pressed Juices & Shakes' 
    : 'Shop Fresh Coconuts & Farm Delights - N.A Fresh Fruits';

$activePage = 'shop';
require_once __DIR__ . '/layouts/header.php';

$cart = CartService::getCart();
$cartItemMap = [];
foreach ($cart['items'] as $item) {
    if (!empty($item['product_id'])) {
        $cartItemMap[(int)$item['product_id']] = (int)$item['quantity'];
    }
}
?>

<div class="max-w-6xl mx-auto px-4 py-4 sm:py-8 space-y-8" x-data="{
    activeModal: null,
    selectedProduct: null,
    selectedOption: null,
    selectedQty: 1,
    isSubmitting: false,
    openOptionModal(prod) {
        this.selectedProduct = prod;
        this.selectedQty = 1;
        if (prod.cutting_options && prod.cutting_options.length > 0) {
            this.selectedOption = prod.cutting_options[0];
        } else {
            this.selectedOption = null;
        }
        this.activeModal = 'options';
    },
    closeModal() {
        this.activeModal = null;
        this.selectedProduct = null;
    },
    get modalUnitPrice() {
        if (!this.selectedProduct) return 0;
        let base = parseFloat(this.selectedProduct.price) || 0;
        let extra = (this.selectedOption && this.selectedOption.price) ? parseFloat(this.selectedOption.price) : 0;
        return base + extra;
    },
    get modalTotalPrice() {
        return this.modalUnitPrice * this.selectedQty;
    }
}">

    <!-- Top Sub-Header & Search -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="/" class="p-1 -ml-1 text-gray-500 hover:text-brand-600 transition" aria-label="Back">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-950 tracking-tight">
                    <?= $isJuiceMode ? 'Fresh Cold-Pressed Juice Bar' : 'Our Farm Fresh Store' ?>
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-gray-500">
                <?= $isJuiceMode ? '100% natural cold-pressed juices, pure citrus coolers & creamy shakes in Jaora' : '100% natural coastal coconuts, pure scooped malai & farm-fresh delights' ?>
            </p>
        </div>

        <!-- Search Bar -->
        <form action="/shop" method="GET" class="w-full sm:w-72">
            <?php if (!empty($selectedCategory) && $selectedCategory !== 'all'): ?>
                <input type="hidden" name="cat" value="<?= e($selectedCategory) ?>">
            <?php endif; ?>
            <div class="relative">
                <input 
                    type="text" 
                    name="q" 
                    value="<?= e($search) ?>" 
                    placeholder="Search coconut, malai, juice..." 
                    class="w-full bg-white border border-gray-200 rounded-2xl pl-10 pr-4 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none shadow-2xs"
                >
                <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </form>
    </div>

    <!-- Category Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-gray-100 no-scrollbar">
        <a 
            href="/shop<?= !empty($search) ? '?q=' . urlencode($search) : '' ?>" 
            class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition <?= ($selectedCategory === 'all' || empty($selectedCategory)) ? 'bg-brand-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>"
        >
            All Products
        </a>
        <?php foreach ($categories as $cat): ?>
        <a 
            href="/shop?cat=<?= urlencode($cat['slug']) ?><?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
            class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition <?= ($selectedCategory === $cat['slug']) ? 'bg-brand-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>"
        >
            <?= e($cat['name']) ?>
        </a>
        <?php endforeach; ?>
        <a 
            href="/shop?cat=combos<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
            class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition <?= ($selectedCategory === 'combos') ? 'bg-brand-600 text-white shadow-xs' : 'bg-amber-100 text-amber-900 hover:bg-amber-200' ?>"
        >
            ✨ Combo Bundles
        </a>
    </div>

    <!-- Special Combos & Bundles Section (if available) -->
    <?php if (!empty($combos)): ?>
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-brand-700 block">Best Value Bundles</span>
                <h2 class="text-xl font-black text-gray-950">Combo Offers & Family Packs</h2>
            </div>
            <span class="text-xs text-gray-400 font-semibold hidden sm:inline">Save up to 25% on combined favorites</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($combos as $combo): ?>
            <div class="bg-white rounded-3xl border border-gray-200/80 shadow-xs overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                <div>
                    <!-- Combo Image & Savings Banner -->
                    <div class="relative aspect-[16/9] bg-emerald-50/60 overflow-hidden">
                        <img src="<?= e($combo['image_url']) ?>" alt="<?= e($combo['name']) ?>" class="w-full h-full object-cover">
                        <?php if ((float)$combo['savings'] > 0): ?>
                        <div class="absolute top-3 left-3 bg-red-600 text-white text-[10px] sm:text-xs font-black px-2.5 py-1 rounded-lg shadow-sm">
                            SAVE ₹<?= number_format($combo['savings']) ?> (<?= $combo['discount_percent'] ?>% OFF)
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Combo Details -->
                    <div class="p-5 space-y-3">
                        <div>
                            <h3 class="text-base font-extrabold text-gray-950 leading-snug"><?= e($combo['name']) ?></h3>
                            <?php if (!empty($combo['tagline'])): ?>
                            <p class="text-xs text-brand-700 font-semibold mt-0.5"><?= e($combo['tagline']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Included Items Pill List -->
                        <div class="bg-emerald-50/60 rounded-xl p-3 border border-emerald-100/60 text-xs space-y-1">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Bundle Contains:</span>
                            <?php foreach ($combo['items'] as $cItem): ?>
                            <div class="flex items-center justify-between text-gray-800 text-[11px]">
                                <span>• <?= (int)$cItem['quantity'] ?>&times; <?= e($cItem['product_name']) ?></span>
                                <span class="text-gray-400 text-[10px]">₹<?= number_format((float)$cItem['product_price'] * (int)$cItem['quantity']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($combo['description'])): ?>
                        <p class="text-xs text-gray-500 line-clamp-2"><?= e($combo['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer with Price & Add to Cart -->
                <div class="p-5 pt-0">
                    <div class="flex items-baseline justify-between mb-3">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl font-black text-gray-900">₹<?= number_format($combo['price']) ?></span>
                            <span class="line-through text-xs font-semibold text-gray-400">₹<?= number_format($combo['original_price']) ?></span>
                        </div>
                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">In Stock</span>
                    </div>

                    <form action="/cart/add" method="POST">
                        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                        <input type="hidden" name="type" value="combo">
                        <input type="hidden" name="combo_id" value="<?= (int)$combo['id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button 
                            type="submit" 
                            class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs py-3 rounded-xl transition shadow-xs flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            <span>Add Combo to Cart</span>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Individual Products Catalog -->
    <?php if ($selectedCategory !== 'combos'): ?>
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-brand-700 block">Fresh Harvest</span>
                <h2 class="text-xl font-black text-gray-950">
                    <?= !empty($selectedCategory) && $selectedCategory !== 'all' ? e($categories[array_search($selectedCategory, array_column($categories, 'slug'))]['name'] ?? 'Products') : 'All Fresh Items' ?>
                </h2>
            </div>
            <span class="text-xs text-gray-400 font-semibold"><?= count($products) ?> items available</span>
        </div>

        <?php if (empty($products)): ?>
        <div class="bg-white rounded-3xl p-12 text-center border border-gray-200/80 space-y-3">
            <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            </div>
            <h3 class="text-base font-bold text-gray-900">No items found</h3>
            <p class="text-xs text-gray-500">Try browsing another category or clear your search term.</p>
            <a href="/shop" class="inline-block mt-2 px-4 py-2 bg-brand-600 text-white rounded-xl text-xs font-bold">View All Products</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($products as $p): ?>
            <div class="bg-white rounded-3xl border border-gray-200/80 shadow-xs overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                <div>
                    <!-- Product Image -->
                    <div class="relative aspect-square sm:aspect-[4/3] bg-emerald-50/40 overflow-hidden border-b border-gray-100 flex items-center justify-center p-3">
                        <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" class="w-full h-full object-cover rounded-2xl">

                        <?php if (!empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price']): ?>
                        <div class="absolute top-3 left-3 bg-brand-600 text-white text-[10px] font-black px-2 py-0.5 rounded-lg shadow-2xs">
                            ₹<?= number_format((float)$p['original_price'] - (float)$p['price']) ?> OFF
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($p['has_cutting_options'])): ?>
                        <div class="absolute bottom-3 left-3 bg-white/90 backdrop-blur-xs text-gray-800 text-[10px] font-bold px-2.5 py-1 rounded-lg border border-gray-200/80 shadow-2xs flex items-center gap-1">
                            <svg class="w-3 h-3 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z"></path></svg>
                            <span>Cutting Options Available</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Details -->
                    <div class="p-5 space-y-2">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">
                            <?= e($p['category_name'] ?? 'Fresh Farm Produce') ?>
                        </span>
                        <h3 class="text-base font-extrabold text-gray-950 leading-snug"><?= e($p['name']) ?></h3>
                        <p class="text-xs text-gray-500 line-clamp-2"><?= e($p['tagline'] ?: $p['description']) ?></p>
                    </div>
                </div>

                <!-- Footer with Pricing & Action -->
                <div class="p-5 pt-0">
                    <div class="flex items-baseline justify-between mb-3">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl font-black text-gray-900">₹<?= number_format($p['price']) ?></span>
                            <?php if (!empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price']): ?>
                            <span class="line-through text-xs font-semibold text-gray-400">₹<?= number_format($p['original_price']) ?></span>
                            <?php endif; ?>
                            <span class="text-xs text-gray-400 font-medium">/pc</span>
                        </div>

                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">
                            <?= (int)$p['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                        </span>
                    </div>

                    <?php 
                    $pid = (int)$p['id'];
                    $currentQty = $cartItemMap[$pid] ?? 0;
                    if (!empty($p['has_cutting_options']) && !empty($p['cutting_options'])): 
                    ?>
                    <!-- Open Cutting & Preparation Options Modal -->
                    <button 
                        type="button" 
                        @click="openOptionModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"
                        class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs py-3 rounded-xl transition shadow-xs flex items-center justify-center gap-2 active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z"></path></svg>
                        <span>Select Options & Add</span>
                    </button>
                    <?php else: ?>
                    <!-- Fast 1-Tap Ordering Stepper Component -->
                    <div class="product-ordering-actions" data-product-id="<?= $pid ?>">
                        <button 
                            type="button" 
                            data-quick-add 
                            data-product-id="<?= $pid ?>" 
                            class="product-initial-add-btn w-full bg-brand-600 hover:bg-brand-700 text-white font-extrabold text-xs py-2.5 px-3 rounded-xl transition shadow-2xs flex items-center justify-center gap-1.5 active:scale-95 <?= ($currentQty > 0) ? 'hidden' : '' ?>"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            <span>Add to Cart</span>
                        </button>

                        <div class="product-qty-stepper flex items-center justify-between bg-brand-50 border border-brand-200 rounded-xl p-1 <?= ($currentQty > 0) ? '' : 'hidden' ?>">
                            <button 
                                type="button" 
                                data-quick-step="-1" 
                                data-product-id="<?= $pid ?>" 
                                class="w-8 h-8 rounded-lg bg-white text-brand-700 hover:bg-brand-100 flex items-center justify-center font-black text-sm shadow-2xs active:scale-90"
                                aria-label="Decrease quantity"
                            >&minus;</button>
                            <span class="product-qty-value font-black text-xs text-brand-900 px-3"><?= $currentQty ?></span>
                            <button 
                                type="button" 
                                data-quick-step="1" 
                                data-product-id="<?= $pid ?>" 
                                class="w-8 h-8 rounded-lg bg-brand-600 text-white hover:bg-brand-700 flex items-center justify-center font-black text-sm shadow-2xs active:scale-90"
                                aria-label="Increase quantity"
                            >&plus;</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Dual Store In-Page Switcher Banner -->
    <?php if (StoreService::isSwitchButtonEnabled()): ?>
    <div class="rounded-3xl p-6 sm:p-7 shadow-xs border <?= $isJuiceMode ? 'bg-gradient-to-r from-emerald-900 to-green-950 text-white border-emerald-800' : 'bg-gradient-to-r from-orange-950 to-amber-950 text-white border-orange-900' ?> flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3.5 text-center sm:text-left">
            <span class="text-3xl p-2 rounded-2xl bg-white/10 shrink-0"><?= $isJuiceMode ? '🥥' : '🍹' ?></span>
            <div>
                <span class="text-[10px] font-black uppercase tracking-widest text-amber-300 block"><?= $isJuiceMode ? 'Switch Store' : 'Juice Bar' ?></span>
                <h4 class="text-base sm:text-lg font-black text-white"><?= $isJuiceMode ? 'Craving Tender Coconut Water?' : 'Thirsty for Cold-Pressed Juices & Shakes?' ?></h4>
                <p class="text-xs text-white/80"><?= $isJuiceMode ? 'Switch to our Coconut Store for fresh coastal coconuts and scraped malai.' : 'Discover our complete Juice Bar with freshly pressed fruit extracts and thick shakes.' ?></p>
            </div>
        </div>
        <button 
            type="button" 
            data-switch-store="<?= $isJuiceMode ? 'coconut' : 'juice' ?>" 
            class="store-switch-btn shrink-0 px-5 py-2.5 rounded-xl text-xs font-black transition active:scale-95 shadow-md <?= $isJuiceMode ? 'bg-emerald-500 hover:bg-emerald-400 text-gray-950' : 'bg-orange-500 hover:bg-orange-400 text-gray-950' ?>"
        >
            <?= $isJuiceMode ? 'Looking for Coconut? 🥥' : 'Looking for Juice? 🍹' ?>
        </button>
    </div>
    <?php endif; ?>

    <!-- Interactive Coconut Cutting & Preparation Selection Modal (User Requirement C) -->
    <div 
        x-show="activeModal === 'options'" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-xs p-4"
    >
        <div 
            @click.away="closeModal()"
            class="bg-white rounded-3xl p-6 sm:p-7 max-w-md w-full border border-gray-100 shadow-xl space-y-5"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-brand-700 block">Choose Preparation</span>
                    <h3 class="text-base font-extrabold text-gray-900" x-text="selectedProduct?.name"></h3>
                </div>
                <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-700 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Form -->
            <form action="/cart/add" method="POST" class="space-y-4">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                <input type="hidden" name="product_id" :value="selectedProduct?.id">
                <input type="hidden" name="option_id" :value="selectedOption?.id">
                <input type="hidden" name="quantity" :value="selectedQty">

                <!-- Preparation Options Radio List -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase">How would you like your coconut prepared?</label>
                    <template x-for="(opt, idx) in selectedProduct?.cutting_options" :key="idx">
                        <label 
                            class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition"
                            :class="selectedOption?.id === opt.id ? 'border-brand-600 bg-brand-50/40 text-brand-900 ring-1 ring-brand-600' : 'border-gray-200 bg-gray-50/50 hover:bg-gray-50 text-gray-700'"
                        >
                            <div class="flex items-center gap-3">
                                <input 
                                    type="radio" 
                                    name="radio_opt" 
                                    :value="opt.id"
                                    :checked="selectedOption?.id === opt.id"
                                    @change="selectedOption = opt"
                                    class="w-4 h-4 text-brand-600 focus:ring-brand-500 border-gray-300"
                                >
                                <span class="text-xs font-bold" x-text="opt.name"></span>
                            </div>
                            <span 
                                class="text-xs font-black"
                                :class="parseFloat(opt.price) > 0 ? 'text-brand-700' : 'text-gray-400'"
                                x-text="parseFloat(opt.price) > 0 ? '+₹' + opt.price : 'Free'"
                            ></span>
                        </label>
                    </template>
                </div>

                <!-- Quantity Stepper -->
                <div class="pt-2 flex items-center justify-between bg-gray-50 p-3 rounded-2xl border border-gray-100">
                    <div>
                        <span class="text-xs font-bold text-gray-800 block">Quantity</span>
                        <span class="text-[11px] text-gray-400">Total units</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <button 
                            type="button" 
                            @click="if (selectedQty > 1) selectedQty--"
                            :disabled="selectedQty <= 1"
                            class="w-8 h-8 rounded-xl bg-white border border-gray-200 text-gray-800 font-bold flex items-center justify-center hover:bg-gray-100 disabled:opacity-40 transition"
                        >-</button>
                        <span class="text-sm font-black w-6 text-center" x-text="selectedQty"></span>
                        <button 
                            type="button" 
                            @click="if (selectedQty < (selectedProduct?.stock_quantity || 99)) selectedQty++"
                            class="w-8 h-8 rounded-xl bg-white border border-gray-200 text-gray-800 font-bold flex items-center justify-center hover:bg-gray-100 transition"
                        >+</button>
                    </div>
                </div>

                <!-- Modal Summary & Confirm Button -->
                <div class="pt-3 border-t border-gray-100 space-y-3">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs text-gray-500 font-semibold">Total Price:</span>
                        <span class="text-2xl font-black text-gray-950">₹<span x-text="modalTotalPrice.toFixed(2)"></span></span>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs py-3.5 rounded-xl transition shadow-xs flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <span>Add to Cart (₹<span x-text="modalTotalPrice.toFixed(2)"></span>)</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
