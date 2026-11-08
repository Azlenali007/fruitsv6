<?php
/**
 * Customer Home Page - Premium Dual Store Experience (Coconut Store & Juice Store)
 * N.A Fresh Fruits & Coconuts
 * Mobile-First, Minimal Scrolling, Fast 1-Tap Ordering
 */

$currentStore = StoreService::getCurrentStore();
$storeMeta = StoreService::getStoreMeta($currentStore);
$isJuiceMode = ($currentStore === 'juice');
$storeSwitchEnabled = StoreService::isSwitchButtonEnabled();
$switchToStore = $isJuiceMode ? 'coconut' : 'juice';
$switchButtonText = $isJuiceMode 
    ? Setting::get('switch_to_coconut_text', 'Looking for Coconut? 🥥') 
    : Setting::get('switch_to_juice_text', 'Looking for Juice? 🍹');

$pageTitle = $isJuiceMode 
    ? '100% Pure Cold-Pressed Juices & Shakes - ' . $storeMeta['store_name']
    : 'Fresh Coconut Water - ' . $storeMeta['store_name'];

$activePage = 'home';
require_once __DIR__ . '/layouts/header.php';

// Prepare cart quantity map for instant in-card steppers
$cartData = CartService::getCart();
$cartItemMap = [];
foreach ($cartData['items'] as $item) {
    if (!empty($item['product_id'])) {
        $cartItemMap[(int)$item['product_id']] = (int)$item['quantity'];
    }
}

// Fetch categories and products for the current active store
$categories = StoreService::getStoreCategories($currentStore);
$products = StoreService::getStoreProducts($currentStore, 12);
$primaryProduct = !empty($products) ? $products[0] : Product::getPrimary();
?>

<!-- Minimal High-Impact Hero Section -->
<section class="relative overflow-hidden pt-3 pb-8 sm:pt-6 sm:pb-12 border-b border-gray-100/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-center">
            
            <!-- Left Hero Content -->
            <div class="lg:col-span-7 space-y-4 sm:space-y-5">
                
                <!-- Dual Store Mode Indicator & Switch Pill -->
                <div class="flex items-center gap-2.5 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold tracking-wide <?= $isJuiceMode ? 'bg-orange-100 text-orange-800' : 'bg-brand-100 text-brand-700' ?>">
                        <span><?= $isJuiceMode ? '🍹' : '🥥' ?></span>
                        <span><?= $isJuiceMode ? 'Juice Bar Active' : 'Coconut Store Active' ?></span>
                    </span>

                    <?php if ($storeSwitchEnabled): ?>
                    <button 
                        type="button" 
                        data-switch-store="<?= $switchToStore ?>" 
                        class="store-switch-btn inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition active:scale-95 shadow-2xs <?= $isJuiceMode ? 'bg-emerald-100 hover:bg-emerald-200 text-emerald-800' : 'bg-orange-100 hover:bg-orange-200 text-orange-800' ?>"
                    >
                        <span><?= $isJuiceMode ? '🥥 Switch to Coconut' : '🍹 Switch to Juice' ?></span>
                        <span class="text-xs">&rarr;</span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Main Hero Headline -->
                <div class="space-y-1.5">
                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-gray-950 leading-[1.08]">
                        <?php if ($isJuiceMode): ?>
                            Fresh Cold-Pressed<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-600 via-amber-600 to-orange-500">Fruit Juices & Shakes</span>
                        <?php else: ?>
                            Pure Tender<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-700 via-green-600 to-teal-700">Coconut Water</span>
                        <?php endif; ?>
                    </h1>
                    <p class="text-base sm:text-xl font-bold <?= $isJuiceMode ? 'text-orange-600' : 'text-brand-600' ?>">
                        <?= $isJuiceMode ? '100% Real Fruit • Cold Extracted • No Added Sugar' : 'Pure. Natural. Refreshing. Straight from Farm.' ?>
                    </p>
                </div>

                <!-- Description -->
                <p class="text-gray-600 text-xs sm:text-base max-w-xl leading-relaxed">
                    <?= $isJuiceMode 
                        ? 'Cold-pressed fresh on every single order in Jaora. Sweet Mosambi, Royal Anar, Valencia Orange, Pineapple, ABC Detox & creamy milkshakes. 100% hygienic, zero preservatives.' 
                        : 'Freshly harvested coastal tender coconuts delivered in Jaora within 20-30 minutes. Packed with natural electrolytes, potassium and vital enzymes.' ?>
                </p>

                <!-- Quick Action Buttons -->
                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <a href="#quick-menu" class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-extrabold text-xs sm:text-sm px-6 py-3 rounded-xl shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0">
                        <span>Order Now</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                    </a>

                    <a href="/shop" class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-800 font-bold text-xs sm:text-sm px-5 py-3 rounded-xl border border-gray-200 transition">
                        <span>Explore Full Catalog (<?= count($products) ?>)</span>
                    </a>
                </div>

                <!-- 4 Minimal Value Badges -->
                <div class="grid grid-cols-4 gap-2 pt-4 border-t border-gray-100">
                    <div class="text-center">
                        <span class="block text-base sm:text-lg"><?= $isJuiceMode ? '🍊' : '🌴' ?></span>
                        <span class="text-[10px] sm:text-xs font-bold text-gray-700 leading-tight block mt-0.5"><?= $isJuiceMode ? '100% Real Fruit' : '100% Natural' ?></span>
                    </div>
                    <div class="text-center">
                        <span class="block text-base sm:text-lg">🚫</span>
                        <span class="text-[10px] sm:text-xs font-bold text-gray-700 leading-tight block mt-0.5">No Added Sugar</span>
                    </div>
                    <div class="text-center">
                        <span class="block text-base sm:text-lg">✨</span>
                        <span class="text-[10px] sm:text-xs font-bold text-gray-700 leading-tight block mt-0.5">Hygienic Prep</span>
                    </div>
                    <div class="text-center">
                        <span class="block text-base sm:text-lg">⚡</span>
                        <span class="text-[10px] sm:text-xs font-bold text-gray-700 leading-tight block mt-0.5">20-30m Jaora</span>
                    </div>
                </div>

            </div>

            <!-- Right Hero Image -->
            <div class="lg:col-span-5 relative flex justify-center">
                <div class="relative w-full max-w-sm sm:max-w-md rounded-3xl overflow-hidden shadow-xl <?= $isJuiceMode ? 'bg-orange-50/50' : 'bg-emerald-50/50' ?> p-2 sm:p-3 border border-gray-100">
                    <img 
                        src="<?= $isJuiceMode ? '/assets/images/juice/hero-juice.png' : '/assets/images/hero-coconut.png' ?>" 
                        alt="<?= $isJuiceMode ? 'Fresh Cold-Pressed Juices' : 'Fresh Coconut Water' ?>" 
                        class="w-full h-auto object-cover rounded-2xl transform hover:scale-[1.02] transition duration-500 aspect-[4/3]"
                    >
                    <div class="absolute bottom-4 right-4 bg-white/95 backdrop-blur-md px-3.5 py-2 rounded-xl shadow-md border border-white/60 flex items-center gap-2.5">
                        <div class="w-2.5 h-2.5 rounded-full <?= $isJuiceMode ? 'bg-orange-500' : 'bg-emerald-500' ?> animate-pulse"></div>
                        <div>
                            <p class="text-[9px] uppercase font-bold text-gray-400">Live Stock</p>
                            <p class="text-xs font-extrabold text-gray-900"><?= $isJuiceMode ? 'Juice Bar Open Now' : (int)($primaryProduct['stock_quantity'] ?? 100) . ' Coconuts In Stock' ?></p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Section: Category Quick Filter & Fast 1-Tap Ordering -->
<section id="quick-menu" class="py-8 sm:py-12 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header & Category Filter Tabs -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider <?= $isJuiceMode ? 'text-orange-600' : 'text-brand-600' ?> block">
                    <?= $isJuiceMode ? 'Juice Bar Menu' : 'Farm Harvest Menu' ?>
                </span>
                <h2 class="text-2xl sm:text-3xl font-black text-gray-950 tracking-tight">
                    <?= $isJuiceMode ? 'Fresh Cold-Pressed Beverages' : 'Fresh Tender Coconuts & Malai' ?>
                </h2>
            </div>

            <!-- Category Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
                <a href="/shop" class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap bg-brand-600 text-white shadow-2xs">
                    All Items
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="/shop?cat=<?= urlencode($cat['slug']) ?>" class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                    <?= e($cat['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Fast 1-Tap Product Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
            <?php foreach ($products as $p): 
                $pid = (int)$p['id'];
                $currentQty = $cartItemMap[$pid] ?? 0;
            ?>
            <div 
                data-product-card 
                class="bg-white rounded-2xl border border-gray-200/90 shadow-2xs hover:shadow-md transition flex flex-col justify-between overflow-hidden group"
            >
                <div>
                    <!-- Product Image -->
                    <div class="relative aspect-square bg-gray-50 overflow-hidden flex items-center justify-center p-2.5">
                        <img 
                            src="<?= e($p['image_url']) ?>" 
                            alt="<?= e($p['name']) ?>" 
                            class="w-full h-full object-cover rounded-xl group-hover:scale-105 transition duration-300"
                            loading="lazy"
                        >
                        <?php if (!empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price']): ?>
                        <span class="absolute top-2 left-2 bg-red-600 text-white text-[9px] font-black px-1.5 py-0.5 rounded shadow-2xs">
                            ₹<?= number_format((float)$p['original_price'] - (float)$p['price']) ?> OFF
                        </span>
                        <?php endif; ?>

                        <span class="absolute bottom-2 right-2 text-[9px] font-bold px-1.5 py-0.5 rounded-md backdrop-blur-md <?= ($p['stock_quantity'] > 0) ? 'bg-emerald-500/90 text-white' : 'bg-red-500/90 text-white' ?>">
                            <?= ($p['stock_quantity'] > 0) ? 'In Stock' : 'Out' ?>
                        </span>
                    </div>

                    <!-- Details -->
                    <div class="p-3 space-y-1">
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">
                            <?= e($p['category_name'] ?? ($isJuiceMode ? 'Cold-Pressed' : 'Farm Harvest')) ?>
                        </span>
                        <h3 class="text-xs sm:text-sm font-black text-gray-900 leading-snug line-clamp-1">
                            <?= e($p['name']) ?>
                        </h3>
                        <p class="text-[11px] text-gray-500 line-clamp-1">
                            <?= e($p['tagline'] ?: $p['description']) ?>
                        </p>
                    </div>
                </div>

                <!-- Footer with Pricing & 1-Tap AJAX Stepper -->
                <div class="p-3 pt-0 space-y-2">
                    <div class="flex items-baseline justify-between">
                        <div class="flex items-baseline gap-1">
                            <span class="text-base sm:text-lg font-black text-gray-900">₹<?= number_format($p['price']) ?></span>
                            <?php if (!empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price']): ?>
                            <span class="line-through text-[10px] text-gray-400">₹<?= number_format($p['original_price']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="text-[10px] text-gray-400 font-medium">/pc</span>
                    </div>

                    <!-- 1-Tap Ordering Stepper Component -->
                    <div class="product-ordering-actions" data-product-id="<?= $pid ?>">
                        <button 
                            type="button" 
                            data-quick-add 
                            data-product-id="<?= $pid ?>" 
                            class="product-initial-add-btn w-full bg-brand-600 hover:bg-brand-700 text-white font-extrabold text-xs py-2 px-3 rounded-xl transition shadow-2xs flex items-center justify-center gap-1 active:scale-95 <?= ($currentQty > 0) ? 'hidden' : '' ?>"
                        >
                            <span>Add</span>
                            <span class="text-sm leading-none font-bold">+</span>
                        </button>

                        <div class="product-qty-stepper flex items-center justify-between bg-brand-50 border border-brand-200 rounded-xl p-0.5 <?= ($currentQty > 0) ? '' : 'hidden' ?>">
                            <button 
                                type="button" 
                                data-quick-step="-1" 
                                data-product-id="<?= $pid ?>" 
                                class="w-7 h-7 rounded-lg bg-white text-brand-700 hover:bg-brand-100 flex items-center justify-center font-black text-sm shadow-2xs active:scale-90"
                                aria-label="Decrease quantity"
                            >&minus;</button>
                            <span class="product-qty-value font-black text-xs text-brand-900 px-2"><?= $currentQty ?></span>
                            <button 
                                type="button" 
                                data-quick-step="1" 
                                data-product-id="<?= $pid ?>" 
                                class="w-7 h-7 rounded-lg bg-brand-600 text-white hover:bg-brand-700 flex items-center justify-center font-black text-sm shadow-2xs active:scale-90"
                                aria-label="Increase quantity"
                            >&plus;</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<!-- In-Page Dual Store Switching Callout Banner -->
<?php if ($storeSwitchEnabled): ?>
<section class="py-8 bg-[#f8faf8]">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
        <div class="rounded-3xl p-6 sm:p-8 shadow-sm border <?= $isJuiceMode ? 'bg-gradient-to-br from-emerald-900 to-green-950 text-white border-emerald-800' : 'bg-gradient-to-br from-orange-950 via-amber-950 to-orange-900 text-white border-orange-900' ?> flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4 text-center md:text-left">
                <span class="text-4xl sm:text-5xl p-3 rounded-2xl bg-white/10 backdrop-blur-md shadow-inner shrink-0">
                    <?= $isJuiceMode ? '🥥' : '🍹' ?>
                </span>
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-300 block">
                        <?= $isJuiceMode ? 'Switch to Coconut Store' : 'Switch to Juice Store' ?>
                    </span>
                    <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                        <?= $isJuiceMode ? 'Looking for Pure Tender Coconut Water?' : 'Thirsty for Cold-Pressed Juices & Shakes?' ?>
                    </h3>
                    <p class="text-xs text-white/80 max-w-md">
                        <?= $isJuiceMode 
                            ? 'Switch back to the Coconut Store for fresh coastal coconuts & tender malai.' 
                            : 'Explore our vibrant Juice Bar with 100% natural citrus juices, exotic blends & thick fruit shakes.' ?>
                    </p>
                </div>
            </div>

            <button 
                type="button" 
                data-switch-store="<?= $switchToStore ?>" 
                class="store-switch-btn shrink-0 inline-flex items-center gap-2 px-6 py-3.5 rounded-2xl font-black text-xs sm:text-sm shadow-xl transition transform hover:scale-105 active:scale-95 <?= $isJuiceMode ? 'bg-emerald-500 hover:bg-emerald-400 text-gray-950' : 'bg-orange-500 hover:bg-orange-400 text-gray-950' ?>"
            >
                <span><?= e($switchButtonText) ?></span>
                <span class="text-base">&rarr;</span>
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Minimal Quality Promise Banner -->
<section class="py-10 bg-white border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-1">
                <span class="text-2xl">⚡</span>
                <h4 class="font-extrabold text-sm text-gray-900">Direct Farm & Orchard Sourcing</h4>
                <p class="text-xs text-gray-500">Picked at peak freshness every morning</p>
            </div>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-1">
                <span class="text-2xl">🧼</span>
                <h4 class="font-extrabold text-sm text-gray-900">100% Sanitized & Hygienic</h4>
                <p class="text-xs text-gray-500">Food-grade cleaning & sealed delivery</p>
            </div>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-1">
                <span class="text-2xl">📍</span>
                <h4 class="font-extrabold text-sm text-gray-900">Jaora-Only Express Delivery</h4>
                <p class="text-xs text-gray-500">Live GPS tracking & OTP verified drop</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
