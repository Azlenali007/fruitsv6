<?php
/**
 * Admin Product Add/Edit Form
 * N.A Fresh Fruits & Coconuts
 */
$isEdit = !empty($product);
$adminTitle = ($isEdit ? 'Edit Product' : 'Add New Product') . ' - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'products';
require_once __DIR__ . '/layout-header.php';

$categories = Category::getAll(false);
$cuttingOptions = $isEdit ? ($product['cutting_options'] ?? []) : [
    ['id' => 'whole', 'name' => 'Without Cutting — Whole Coconut', 'price' => 0],
    ['id' => 'cut', 'name' => 'With Cutting — Ready to Drink', 'price' => 0]
];
$gallery = $isEdit ? ($product['gallery'] ?? []) : [];
?>

<div class="max-w-4xl mx-auto space-y-6" x-data="{
    hasCutting: <?= ($isEdit && !empty($product['has_cutting_options'])) ? 'true' : 'false' ?>,
    options: <?= json_encode($cuttingOptions) ?>,
    addOption() {
        this.options.push({
            id: 'opt_' + Date.now(),
            name: '',
            price: 0
        });
    },
    removeOption(index) {
        this.options.splice(index, 1);
    }
}">

    <!-- Breadcrumb & Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/admin/products" class="p-1.5 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-black text-gray-900"><?= $isEdit ? 'Edit Product' : 'Add New Product' ?></h1>
                <p class="text-xs text-gray-500"><?= $isEdit ? 'Update product information, pricing, images, and preparation choices' : 'Create a fresh catalog item for your customers' ?></p>
            </div>
        </div>
    </div>

    <!-- Main Form Card -->
    <form action="<?= $isEdit ? '/admin/products/update' : '/admin/products/create' ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="cutting_options_json" :value="JSON.stringify(options)">

        <!-- Basic Information -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">1. Basic Information</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Product Title *</label>
                    <input 
                        type="text" 
                        name="name" 
                        value="<?= e($product['name'] ?? '') ?>" 
                        required 
                        placeholder="e.g. Tender Coconut"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Category *</label>
                    <select 
                        name="category_id" 
                        required
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                        <option value="">Select a Category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($isEdit && (int)($product['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">URL Slug (Optional)</label>
                    <input 
                        type="text" 
                        name="slug" 
                        value="<?= e($product['slug'] ?? '') ?>" 
                        placeholder="auto-generated-from-title"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Tagline</label>
                    <input 
                        type="text" 
                        name="tagline" 
                        value="<?= e($product['tagline'] ?? '') ?>" 
                        placeholder="e.g. Sweet Water & Soft Creamy Flesh"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div class="md:col-span-2">
                    <label class="block font-bold text-gray-700 uppercase mb-1">Detailed Description</label>
                    <textarea 
                        name="description" 
                        rows="3" 
                        placeholder="Detailed taste, origin, and nutritional benefits..."
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    ><?= e($product['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Pricing & Stock -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">2. Pricing & Inventory</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Selling Price (₹) *</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-gray-500 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="0.5" 
                            min="0" 
                            name="price" 
                            value="<?= $isEdit ? (float)$product['price'] : '' ?>" 
                            required 
                            placeholder="40.00"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Final price charged to customer</span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Original Price / MRP (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-gray-400 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="0.5" 
                            min="0" 
                            name="original_price" 
                            value="<?= ($isEdit && !empty($product['original_price'])) ? (float)$product['original_price'] : '' ?>" 
                            placeholder="50.00"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Displayed with strikethrough savings</span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Available Stock (Units) *</label>
                    <input 
                        type="number" 
                        min="0" 
                        name="stock_quantity" 
                        value="<?= $isEdit ? (int)$product['stock_quantity'] : 100 ?>" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                    <span class="text-[10px] text-gray-400 mt-1 block">Decremented on checkout</span>
                </div>
            </div>

            <div class="pt-2 flex items-center gap-6">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="is_available" 
                        value="1" 
                        <?= (!$isEdit || !empty($product['is_available'])) ? 'checked' : '' ?>
                        class="w-4 h-4 text-brand-600 rounded-md border-gray-300 focus:ring-brand-500"
                    >
                    <span class="text-xs font-bold text-gray-800">Active & Visible in Shop</span>
                </label>

                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="is_featured" 
                        value="1" 
                        <?= ($isEdit && !empty($product['is_featured'])) ? 'checked' : '' ?>
                        class="w-4 h-4 text-brand-600 rounded-md border-gray-300 focus:ring-brand-500"
                    >
                    <span class="text-xs font-bold text-gray-800">Featured on Home Page</span>
                </label>
            </div>
        </div>

        <!-- Coconut Cutting & Preparation Options (User Requirement C) -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">3. Coconut Cutting & Preparation Options</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Allow customers to choose how they want their coconut prepared before adding to cart</p>
                </div>
                <label class="flex items-center gap-2 cursor-pointer bg-gray-50 px-3 py-1.5 rounded-xl border border-gray-200">
                    <input 
                        type="checkbox" 
                        name="has_cutting_options" 
                        value="1" 
                        x-model="hasCutting"
                        class="w-4 h-4 text-brand-600 rounded-md border-gray-300 focus:ring-brand-500"
                    >
                    <span class="text-xs font-bold text-gray-800">Enable for this Product</span>
                </label>
            </div>

            <div x-show="hasCutting" x-transition class="space-y-3 pt-2">
                <p class="text-[11px] text-gray-600 bg-amber-50 p-2.5 rounded-xl border border-amber-200/70">
                    💡 <strong>Tip:</strong> Set extra charge to ₹0 for complimentary cutting (e.g. "With Cutting — Ready to Drink"), or add a surcharge if special cutting involves extra malai scoops or vacuum sealing.
                </p>

                <div class="space-y-2">
                    <template x-for="(opt, index) in options" :key="index">
                        <div class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-xl border border-gray-200">
                            <div class="flex-grow">
                                <input 
                                    type="text" 
                                    x-model="opt.name" 
                                    placeholder="Option label (e.g. Without Cutting — Whole Coconut)"
                                    class="w-full bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                                >
                            </div>
                            <div class="w-32 relative">
                                <span class="absolute left-2.5 top-1.5 text-gray-400 font-bold text-xs">+₹</span>
                                <input 
                                    type="number" 
                                    min="0" 
                                    step="1" 
                                    x-model.number="opt.price" 
                                    placeholder="0"
                                    class="w-full bg-white border border-gray-200 rounded-lg pl-8 pr-2 py-1.5 text-xs font-bold text-right focus:ring-2 focus:ring-brand-600 focus:outline-none"
                                >
                            </div>
                            <button 
                                type="button" 
                                @click="removeOption(index)" 
                                class="p-2 text-gray-400 hover:text-red-600 transition"
                                title="Delete Option"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </template>
                </div>

                <button 
                    type="button" 
                    @click="addOption()" 
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-700 hover:text-brand-800 bg-brand-50 hover:bg-brand-100 px-3 py-2 rounded-xl transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    <span>Add Another Option</span>
                </button>
            </div>
        </div>

        <!-- Images -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">4. Product Images</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Primary Image URL</label>
                    <input 
                        type="text" 
                        name="image_url" 
                        value="<?= e($product['image_url'] ?? '/assets/images/product-main.png') ?>" 
                        placeholder="/assets/images/product-main.png"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                    <span class="text-[10px] text-gray-400 mt-1 block">Relative or full image URL</span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Or Upload New Image</label>
                    <input 
                        type="file" 
                        name="product_image" 
                        accept="image/png,image/jpeg,image/webp"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs focus:outline-none"
                    >
                    <span class="text-[10px] text-gray-400 mt-1 block">Supported: PNG, JPG, WEBP (Max 5MB)</span>
                </div>
            </div>

            <?php if ($isEdit && !empty($product['image_url'])): ?>
            <div class="pt-2">
                <span class="text-[11px] font-bold text-gray-500 uppercase block mb-1.5">Current Preview</span>
                <div class="w-24 h-24 rounded-xl border border-gray-200 overflow-hidden bg-gray-50 p-1">
                    <img src="<?= e($product['image_url']) ?>" alt="Preview" class="w-full h-full object-cover rounded-lg">
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Submit & Actions -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/products" class="px-5 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition">
                Cancel
            </a>
            <button 
                type="submit" 
                class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold transition shadow-xs"
            >
                <?= $isEdit ? 'Save Changes' : 'Create Product' ?>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
