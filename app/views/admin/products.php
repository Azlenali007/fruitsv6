<?php
/**
 * Admin Product & Catalog Management
 * N.A Fresh Fruits & Coconuts
 */
$adminTitle = 'Product & Stock Management - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'products';
require_once __DIR__ . '/layout-header.php';

$storeFilter = isset($_GET['store']) && in_array($_GET['store'], ['coconut', 'juice']) ? $_GET['store'] : 'all';
$categoryId = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : null;
$search = trim($_GET['q'] ?? '');

$categories = Category::getAll(false, $storeFilter === 'all' ? null : $storeFilter);
$products = Product::getAll(false, $categoryId, $search, $storeFilter === 'all' ? null : $storeFilter);

$countAll = count(Product::getAll(false));
$countCoconut = count(Product::getAll(false, null, null, 'coconut'));
$countJuice = count(Product::getAll(false, null, null, 'juice'));
?>

<div class="max-w-6xl mx-auto space-y-6">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Products & Catalog</h1>
            <p class="text-xs text-gray-500">Manage all products, categories, selling prices, stock and cutting options across stores</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="/admin/categories" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-xs font-bold text-gray-700 shadow-2xs transition">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span>Categories</span>
            </a>
            <a href="/admin/combos" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-xs font-bold text-gray-700 shadow-2xs transition">
                <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                <span>Combos</span>
            </a>
            <a href="/admin/products/new" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold shadow-xs transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Product</span>
            </a>
        </div>
    </div>

    <!-- Store Selector Tabs -->
    <div class="flex items-center gap-2 border-b border-gray-200 pb-2">
        <a 
            href="/admin/products<?= !empty($search) ? '?q=' . urlencode($search) : '' ?>" 
            class="px-4 py-2 rounded-xl text-xs font-black transition <?= $storeFilter === 'all' ? 'bg-gray-900 text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' ?>"
        >
            All Stores (<?= $countAll ?>)
        </a>
        <a 
            href="/admin/products?store=coconut<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
            class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-1.5 <?= $storeFilter === 'coconut' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-white text-emerald-800 hover:bg-emerald-50 border border-emerald-200' ?>"
        >
            <span>🥥</span>
            <span>Coconut Store (<?= $countCoconut ?>)</span>
        </a>
        <a 
            href="/admin/products?store=juice<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
            class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-1.5 <?= $storeFilter === 'juice' ? 'bg-orange-600 text-white shadow-xs' : 'bg-white text-orange-800 hover:bg-orange-50 border border-orange-200' ?>"
        >
            <span>🍹</span>
            <span>Juice Store (<?= $countJuice ?>)</span>
        </a>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white rounded-2xl p-4 border border-gray-200/80 shadow-2xs flex flex-col sm:flex-row gap-3 items-center justify-between">
        <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
            <a 
                href="/admin/products<?= $storeFilter !== 'all' ? '?store=' . $storeFilter : '' ?>" 
                class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition <?= $categoryId === null ? 'bg-brand-600 text-white shadow-2xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"
            >
                All Categories
            </a>
            <?php foreach ($categories as $cat): ?>
            <a 
                href="/admin/products?<?= $storeFilter !== 'all' ? 'store=' . $storeFilter . '&' : '' ?>category=<?= $cat['id'] ?>" 
                class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition <?= $categoryId === (int)$cat['id'] ? 'bg-brand-600 text-white shadow-2xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"
            >
                <?= e($cat['name']) ?> (<?= (int)$cat['product_count'] ?>)
            </a>
            <?php endforeach; ?>
        </div>

        <form action="/admin/products" method="GET" class="w-full sm:w-64">
            <?php if ($categoryId): ?>
                <input type="hidden" name="category" value="<?= $categoryId ?>">
            <?php endif; ?>
            <div class="relative">
                <input 
                    type="text" 
                    name="q" 
                    value="<?= e($search) ?>" 
                    placeholder="Search catalog..." 
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-3 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                >
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-gray-50/70 border-b border-gray-200 text-gray-500 uppercase tracking-wider font-bold">
                        <th class="py-3 px-4">Product</th>
                        <th class="py-3 px-3">Category</th>
                        <th class="py-3 px-3">Selling Price</th>
                        <th class="py-3 px-3">Stock</th>
                        <th class="py-3 px-3">Cutting Options</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-gray-400">
                            No products found matching your search.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-gray-100 overflow-hidden shrink-0">
                                    <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" class="w-full h-full object-cover">
                                </div>
                                <div class="min-w-0">
                                    <span class="font-bold text-gray-900 block truncate"><?= e($p['name']) ?></span>
                                    <span class="text-[11px] text-gray-400 truncate block"><?= e($p['tagline'] ?: 'No tagline') ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-3">
                            <div class="space-y-1">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black <?= ($p['store_type'] ?? 'coconut') === 'juice' ? 'bg-orange-100 text-orange-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                    <?= ($p['store_type'] ?? 'coconut') === 'juice' ? '🍹 Juice' : '🥥 Coconut' ?>
                                </span>
                                <span class="px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 font-semibold text-[11px] block truncate">
                                    <?= e($p['category_name'] ?? 'Uncategorized') ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-3 px-3">
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-black text-gray-950 text-sm">₹<?= number_format($p['price'], 2) ?></span>
                                <?php if (!empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price']): ?>
                                <span class="line-through text-gray-400 text-[11px]">₹<?= number_format($p['original_price'], 2) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-3 px-3">
                            <span class="font-bold <?= (int)$p['stock_quantity'] > 10 ? 'text-gray-900' : ((int)$p['stock_quantity'] > 0 ? 'text-amber-600' : 'text-red-600') ?>">
                                <?= (int)$p['stock_quantity'] ?> units
                            </span>
                        </td>
                        <td class="py-3 px-3">
                            <?php if (!empty($p['has_cutting_options']) && !empty($p['cutting_options'])): ?>
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-700 bg-brand-50 px-2 py-0.5 rounded-md border border-brand-200/50">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z"></path></svg>
                                <span><?= count($p['cutting_options']) ?> options</span>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-400 text-[11px]">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= !empty($p['is_available']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                                <?= !empty($p['is_available']) ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a 
                                    href="/admin/products/edit?id=<?= $p['id'] ?>" 
                                    class="p-1.5 rounded-lg text-gray-500 hover:text-brand-600 hover:bg-gray-100 transition"
                                    title="Edit Product"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </a>
                                <form action="/admin/products/delete" method="POST" onsubmit="return confirm('Delete product \'<?= e($p['name']) ?>\'? This cannot be undone.');" class="inline">
                                    <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button 
                                        type="submit" 
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition"
                                        title="Delete Product"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
