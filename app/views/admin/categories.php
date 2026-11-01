<?php
/**
 * Admin Product Categories Management
 * N.A Fresh Fruits & Coconuts
 */
$adminTitle = 'Product Categories - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'categories';
require_once __DIR__ . '/layout-header.php';

$categories = Category::getAll(false);
?>

<div class="max-w-4xl mx-auto space-y-6" x-data="{
    showModal: false,
    editMode: false,
    catId: null,
    name: '',
    slug: '',
    description: '',
    sortOrder: 0,
    isActive: true,
    openAdd() {
        this.editMode = false;
        this.catId = null;
        this.name = '';
        this.slug = '';
        this.description = '';
        this.sortOrder = <?= count($categories) + 1 ?>;
        this.isActive = true;
        this.showModal = true;
    },
    openEdit(cat) {
        this.editMode = true;
        this.catId = cat.id;
        this.name = cat.name;
        this.slug = cat.slug;
        this.description = cat.description || '';
        this.sortOrder = cat.sort_order;
        this.isActive = cat.is_active == 1;
        this.showModal = true;
    }
}">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Product Categories</h1>
            <p class="text-xs text-gray-500">Organize your shop offerings into clear customer-friendly categories</p>
        </div>

        <button 
            type="button" 
            @click="openAdd()"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold shadow-xs transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
            <span>Add Category</span>
        </button>
    </div>

    <!-- Category Table -->
    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="bg-gray-50/70 border-b border-gray-200 text-gray-500 uppercase tracking-wider font-bold">
                    <th class="py-3 px-4">Category</th>
                    <th class="py-3 px-3">Slug</th>
                    <th class="py-3 px-3">Products</th>
                    <th class="py-3 px-3">Sort Order</th>
                    <th class="py-3 px-3">Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
                <?php foreach ($categories as $cat): ?>
                <tr class="hover:bg-gray-50/50 transition">
                    <td class="py-3 px-4 font-bold text-gray-900">
                        <?= e($cat['name']) ?>
                        <?php if (!empty($cat['description'])): ?>
                            <span class="block font-normal text-[11px] text-gray-400"><?= e($cat['description']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-3 font-mono text-gray-500 text-[11px]">
                        <?= e($cat['slug']) ?>
                    </td>
                    <td class="py-3 px-3 font-bold text-gray-800">
                        <?= (int)$cat['product_count'] ?> products
                    </td>
                    <td class="py-3 px-3 text-gray-500">
                        <?= (int)$cat['sort_order'] ?>
                    </td>
                    <td class="py-3 px-3">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $cat['is_active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600' ?>">
                            <?= $cat['is_active'] ? 'Active' : 'Hidden' ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <button 
                                type="button" 
                                @click="openEdit(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>)"
                                class="p-1.5 rounded-lg text-gray-500 hover:text-brand-600 hover:bg-gray-100 transition"
                                title="Edit Category"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>
                            <form action="/admin/categories/delete" method="POST" onsubmit="return confirm('Delete category \'<?= e($cat['name']) ?>\'? Associated products will become uncategorized.');" class="inline">
                                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <button 
                                    type="submit" 
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition"
                                    title="Delete Category"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Form -->
    <div 
        x-show="showModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-xs p-4"
    >
        <div 
            @click.away="showModal = false"
            class="bg-white rounded-3xl p-6 sm:p-7 max-w-md w-full border border-gray-100 shadow-xl space-y-4"
        >
            <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900" x-text="editMode ? 'Edit Category' : 'Add Category'"></h3>
                <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form action="/admin/categories/save" method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                <input type="hidden" name="category_id" :value="catId">

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Category Name *</label>
                    <input 
                        type="text" 
                        name="name" 
                        x-model="name" 
                        required 
                        placeholder="e.g. Fresh Fruit Juices"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Slug (Optional)</label>
                    <input 
                        type="text" 
                        name="slug" 
                        x-model="slug" 
                        placeholder="e.g. fresh-fruit-juices"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Short Description</label>
                    <input 
                        type="text" 
                        name="description" 
                        x-model="description" 
                        placeholder="e.g. 100% cold pressed and freshly squeezed"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Sort Order</label>
                        <input 
                            type="number" 
                            name="sort_order" 
                            x-model.number="sortOrder" 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>

                    <div class="flex items-center pt-5">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1" 
                                x-model="isActive" 
                                class="w-4 h-4 text-brand-600 rounded-md border-gray-300 focus:ring-brand-500"
                            >
                            <span class="text-xs font-bold text-gray-800">Active</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                    <button type="button" @click="showModal = false" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold transition">
                        Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
