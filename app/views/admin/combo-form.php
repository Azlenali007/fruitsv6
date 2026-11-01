<?php
/**
 * Admin Combo Offer Add/Edit Form
 * N.A Fresh Fruits & Coconuts
 */
$isEdit = !empty($combo);
$adminTitle = ($isEdit ? 'Edit Combo Bundle' : 'Create New Combo Offer') . ' - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'combos';
require_once __DIR__ . '/layout-header.php';

$allProducts = Product::getAll(false);
$existingItems = $isEdit ? ($combo['items'] ?? []) : [];
$initialItems = [];
foreach ($existingItems as $it) {
    $initialItems[] = [
        'product_id' => (int)$it['product_id'],
        'quantity' => (int)$it['quantity'],
        'notes' => $it['notes'] ?? ''
    ];
}
if (empty($initialItems) && !empty($allProducts)) {
    $initialItems[] = ['product_id' => (int)$allProducts[0]['id'], 'quantity' => 1, 'notes' => ''];
}
?>

<div class="max-w-4xl mx-auto space-y-6" x-data="{
    allProducts: <?= htmlspecialchars(json_encode(array_map(function($p) {
        return ['id' => (int)$p['id'], 'name' => $p['name'], 'price' => (float)$p['price']];
    }, $allProducts)), ENT_QUOTES) ?>,
    items: <?= json_encode($initialItems) ?>,
    price: <?= $isEdit ? (float)$combo['price'] : 90 ?>,
    originalPrice: <?= $isEdit ? (float)$combo['original_price'] : 100 ?>,
    addItem() {
        if (this.allProducts.length > 0) {
            this.items.push({
                product_id: this.allProducts[0].id,
                quantity: 1,
                notes: ''
            });
            this.calculateOriginalSum();
        }
    },
    removeItem(idx) {
        if (this.items.length > 1) {
            this.items.splice(idx, 1);
            this.calculateOriginalSum();
        }
    },
    calculateOriginalSum() {
        let sum = 0;
        for (const item of this.items) {
            const p = this.allProducts.find(x => x.id === parseInt(item.product_id));
            if (p) {
                sum += p.price * (parseInt(item.quantity) || 1);
            }
        }
        if (sum > 0) {
            this.originalPrice = sum;
        }
    },
    get savings() {
        return Math.max(0, this.originalPrice - this.price);
    },
    get savingsPercent() {
        if (this.originalPrice <= 0) return 0;
        return Math.round((this.savings / this.originalPrice) * 100);
    }
}">

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/admin/combos" class="p-1.5 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-black text-gray-900"><?= $isEdit ? 'Edit Combo Bundle' : 'Create New Combo Offer' ?></h1>
                <p class="text-xs text-gray-500"><?= $isEdit ? 'Modify bundled items, special pricing and validity' : 'Select products and provide attractive bundle savings' ?></p>
            </div>
        </div>
    </div>

    <form action="<?= $isEdit ? '/admin/combos/update' : '/admin/combos/create' ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="combo_id" value="<?= (int)$combo['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="items_json" :value="JSON.stringify(items)">

        <!-- Basic Combo Info -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">1. Bundle Information</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Combo Title *</label>
                    <input 
                        type="text" 
                        name="name" 
                        value="<?= e($combo['name'] ?? '') ?>" 
                        required 
                        placeholder="e.g. Family Coconut Pack (2 Water + 1 Malai)"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Tagline</label>
                    <input 
                        type="text" 
                        name="tagline" 
                        value="<?= e($combo['tagline'] ?? '') ?>" 
                        placeholder="e.g. Save ₹25 with Pure Nature Refreshment"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div class="md:col-span-2">
                    <label class="block font-bold text-gray-700 uppercase mb-1">Description</label>
                    <textarea 
                        name="description" 
                        rows="2" 
                        placeholder="Brief overview of what's inside and why it's a great value..."
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    ><?= e($combo['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Products included in bundle -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">2. Included Products & Quantities</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Choose the products and exact quantities included in this bundle</p>
                </div>
                <button 
                    type="button" 
                    @click="addItem()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand-50 hover:bg-brand-100 text-brand-700 text-xs font-bold transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    <span>Add Item to Bundle</span>
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
                        <div class="flex-grow">
                            <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Product</label>
                            <select 
                                x-model="item.product_id" 
                                @change="calculateOriginalSum()"
                                class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                            >
                                <template x-for="p in allProducts" :key="p.id">
                                    <option :value="p.id" x-text="p.name + ' (₹' + p.price + ')'" :selected="p.id == item.product_id"></option>
                                </template>
                            </select>
                        </div>

                        <div class="w-24">
                            <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Quantity</label>
                            <input 
                                type="number" 
                                min="1" 
                                x-model.number="item.quantity" 
                                @input="calculateOriginalSum()"
                                class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                            >
                        </div>

                        <div class="pt-5">
                            <button 
                                type="button" 
                                @click="removeItem(idx)" 
                                :disabled="items.length <= 1"
                                class="p-2 text-gray-400 hover:text-red-600 transition disabled:opacity-30 disabled:cursor-not-allowed"
                                title="Remove Item"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Pricing, Savings & Inventory -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">3. Pricing, Savings & Stock</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Original Combined Price (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-gray-400 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="0.5" 
                            min="0" 
                            name="original_price" 
                            x-model.number="originalPrice" 
                            required 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Calculated from individual items</span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Discounted Combo Price (₹) *</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-brand-600 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="0.5" 
                            min="0" 
                            name="price" 
                            x-model.number="price" 
                            required 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-emerald-600 font-semibold mt-1 block">
                        Customer Saves: ₹<span x-text="savings.toFixed(2)"></span> (<span x-text="savingsPercent"></span>% OFF)
                    </span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Bundle Stock *</label>
                    <input 
                        type="number" 
                        min="0" 
                        name="stock_quantity" 
                        value="<?= $isEdit ? (int)$combo['stock_quantity'] : 50 ?>" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>
            </div>

            <!-- Image URL & Status -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs pt-2">
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Combo Image URL</label>
                    <input 
                        type="text" 
                        name="image_url" 
                        value="<?= e($combo['image_url'] ?? '/assets/images/hero-coconut.png') ?>" 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="is_available" 
                            value="1" 
                            <?= (!$isEdit || !empty($combo['is_available'])) ? 'checked' : '' ?>
                            class="w-4 h-4 text-brand-600 rounded-md border-gray-300 focus:ring-brand-500"
                        >
                        <span class="text-xs font-bold text-gray-800">Active & Available for Purchase</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/combos" class="px-5 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition">
                Cancel
            </a>
            <button 
                type="submit" 
                class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold transition shadow-xs"
            >
                <?= $isEdit ? 'Save Changes' : 'Create Combo Bundle' ?>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
