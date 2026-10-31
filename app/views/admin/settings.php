<?php
/**
 * Admin Delivery & Business Settings View
 * N.A Fresh Fruits & Coconuts
 */
$adminTitle = 'Delivery Settings - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'settings';
require_once __DIR__ . '/layout-header.php';

$settings = Setting::getAll();
?>

<div class="max-w-3xl mx-auto space-y-6">
    
    <div>
        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Delivery & Store Settings</h1>
        <p class="text-xs text-gray-500">Configure local delivery parameters, pickup options, delivery fees, and service areas</p>
    </div>

    <form action="/admin/settings/update" method="POST" class="space-y-6">
        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">

        <!-- Fulfilment Methods Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Fulfilment Method</h2>
            
            <div class="grid grid-cols-1 gap-4">
                <label class="flex items-start gap-3 p-4 bg-gray-50 border border-gray-200 rounded-2xl cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="local_delivery_enabled" 
                        value="1" 
                        <?= ($settings['local_delivery_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                        class="w-4 h-4 text-brand-600 rounded-sm focus:ring-brand-500 mt-0.5"
                    >
                    <div>
                        <span class="font-bold text-xs text-gray-900 block">Enable Home Doorstep Delivery</span>
                        <span class="text-[11px] text-gray-500">Allow customers to order for fresh tender coconut doorstep delivery.</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Charges & Minimum Order Thresholds -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Delivery Charges & Minimum Order</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Standard Delivery Fee (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-500 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="1" 
                            name="delivery_charge" 
                            value="<?= (int)($settings['delivery_charge'] ?? 40) ?>" 
                            required 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-7 pr-3 py-2.5 font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Default charge applied at checkout</span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Minimum Order Amount (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-500 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="1" 
                            name="min_order_amount" 
                            value="<?= (int)($settings['min_order_amount'] ?? 40) ?>" 
                            required 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-7 pr-3 py-2.5 font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Minimum subtotal required to place delivery</span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Free Delivery Above (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-500 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="1" 
                            name="free_delivery_above" 
                            value="<?= (int)($settings['free_delivery_above'] ?? 500) ?>" 
                            required 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-7 pr-3 py-2.5 font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Zero delivery fee when subtotal reaches this</span>
                </div>

            </div>

            <!-- Service Areas -->
            <div class="text-xs">
                <label class="block font-bold text-gray-700 uppercase mb-1">Supported Service Areas</label>
                <input 
                    type="text" 
                    name="service_areas" 
                    value="<?= e($settings['service_areas'] ?? 'Bhopal, MP Nagar, Arera Colony, Kolar Road, Green Park') ?>" 
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-medium focus:ring-2 focus:ring-brand-600 focus:outline-none"
                >
                <span class="text-[10px] text-gray-400 mt-1 block">Comma-separated list of localities served</span>
            </div>

        </div>

        <!-- Store Information -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Business & Contact Info</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Store Name</label>
                    <input 
                        type="text" 
                        name="store_name" 
                        value="<?= e($settings['store_name'] ?? 'N.A Fresh Fruits & Coconuts') ?>" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Contact Phone</label>
                    <input 
                        type="text" 
                        name="store_phone" 
                        value="<?= e($settings['store_phone'] ?? '+91 98765 43210') ?>" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label class="block font-bold text-gray-700 uppercase mb-1">Store Address</label>
                    <input 
                        type="text" 
                        name="store_address" 
                        value="<?= e($settings['store_address'] ?? '123, Green Park, Near Main Road, Bhopal, Madhya Pradesh - 462016') ?>" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-medium focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Daily Opening Time</label>
                    <input 
                        type="text" 
                        name="store_open_time" 
                        value="<?= e($settings['store_open_time'] ?? '07:00 AM') ?>" 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Daily Closing Time</label>
                    <input 
                        type="text" 
                        name="store_close_time" 
                        value="<?= e($settings['store_close_time'] ?? '09:00 PM') ?>" 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>
            </div>
        </div>

        <!-- WhatsApp Channel Popup Settings Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5" x-data="{
            previewOpen: false,
            heading: '<?= e($settings['whatsapp_popup_heading'] ?? 'Join Our WhatsApp Channel') ?>',
            message: '<?= e($settings['whatsapp_popup_message'] ?? 'Get real-time fresh coconut harvest alerts, special seasonal discounts, and quick delivery updates straight on WhatsApp!') ?>',
            btnText: '<?= e($settings['whatsapp_popup_button_text'] ?? 'Join WhatsApp Channel') ?>'
        }">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#25D366]"></span>
                        <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">WhatsApp Channel Popup Settings</h2>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-0.5">Control the promotional modal inviting storefront visitors to your official WhatsApp channel</p>
                </div>
                <button 
                    type="button" 
                    @click="previewOpen = true" 
                    class="inline-flex items-center gap-1.5 bg-[#25D366]/10 text-[#128C7E] hover:bg-[#25D366]/20 font-bold text-xs py-2 px-3.5 rounded-xl transition"
                >
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.173.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-10.416c-5.524 0-10 4.477-10 10 0 1.956.565 3.78 1.547 5.325l-1.642 5.999 6.179-1.62c1.493.864 3.228 1.365 5.078 1.365 5.523 0 10-4.477 10-10 0-5.522-4.477-10-10-10z"/></svg>
                    <span>Test / Preview Popup</span>
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                
                <!-- Toggle -->
                <div class="sm:col-span-2">
                    <label class="flex items-start gap-3 p-4 bg-emerald-50/50 border border-emerald-200/60 rounded-2xl cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="whatsapp_popup_enabled" 
                            value="1" 
                            <?= ($settings['whatsapp_popup_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                            class="w-4 h-4 text-[#25D366] rounded-sm focus:ring-[#25D366] mt-0.5"
                        >
                        <div>
                            <span class="font-bold text-xs text-gray-900 block">Enable WhatsApp Channel Popup</span>
                            <span class="text-[11px] text-gray-600">Displays a floating invitation modal for visitors to join your official WhatsApp channel.</span>
                        </div>
                    </label>
                </div>

                <!-- Channel URL -->
                <div class="sm:col-span-2">
                    <label class="block font-bold text-gray-700 uppercase mb-1">
                        Official WhatsApp Channel Link <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-400 font-mono text-[11px]">🔗</span>
                        <input 
                            type="url" 
                            name="whatsapp_channel_url" 
                            value="<?= e($settings['whatsapp_channel_url'] ?? 'https://whatsapp.com/channel/0029VaNAFreshFruits') ?>" 
                            placeholder="https://whatsapp.com/channel/..." 
                            required 
                            pattern="https://.*"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-3 py-2.5 text-xs font-mono font-medium focus:ring-2 focus:ring-[#25D366] focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Must be a secure HTTPS link provided by WhatsApp (e.g. https://whatsapp.com/channel/...)</span>
                </div>

                <!-- Popup Heading -->
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Popup Heading</label>
                    <input 
                        type="text" 
                        name="whatsapp_popup_heading" 
                        x-model="heading"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-[#25D366] focus:outline-none"
                    >
                </div>

                <!-- Button Text -->
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Join Button Label</label>
                    <input 
                        type="text" 
                        name="whatsapp_popup_button_text" 
                        x-model="btnText"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-[#25D366] focus:outline-none"
                    >
                </div>

                <!-- Message Body -->
                <div class="sm:col-span-2">
                    <label class="block font-bold text-gray-700 uppercase mb-1">Invitation Message</label>
                    <textarea 
                        name="whatsapp_popup_message" 
                        x-model="message"
                        rows="2" 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs leading-relaxed focus:ring-2 focus:ring-[#25D366] focus:outline-none"
                    ></textarea>
                </div>

                <!-- Frequency Dropdown -->
                <div class="sm:col-span-2">
                    <label class="block font-bold text-gray-700 uppercase mb-1">Display Frequency / Persistence</label>
                    <select 
                        name="whatsapp_popup_frequency" 
                        class="w-full sm:w-72 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-[#25D366] focus:outline-none"
                    >
                        <?php $currFreq = $settings['whatsapp_popup_frequency'] ?? 'session'; ?>
                        <option value="session" <?= $currFreq === 'session' ? 'selected' : '' ?>>Once per browser session (Recommended)</option>
                        <option value="daily" <?= $currFreq === 'daily' ? 'selected' : '' ?>>Once per day (24-hour cooldown)</option>
                        <option value="weekly" <?= $currFreq === 'weekly' ? 'selected' : '' ?>>Once per week (7-day cooldown)</option>
                        <option value="always" <?= $currFreq === 'always' ? 'selected' : '' ?>>Always on every visit</option>
                    </select>
                    <span class="text-[10px] text-gray-400 mt-1 block">Uses browser storage to prevent annoying returning customers on every page navigation</span>
                </div>

            </div>

            <!-- Interactive Live Modal Preview for Admin -->
            <div 
                x-show="previewOpen" 
                x-cloak 
                class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4"
                @click="previewOpen = false"
                @keydown.escape.window="previewOpen = false"
            >
                <div class="bg-white rounded-3xl max-w-sm w-full shadow-2xl border border-gray-100 overflow-hidden relative" @click.stop>
                    <button type="button" @click="previewOpen = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 p-1.5 rounded-full transition z-10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    <div class="bg-gradient-to-br from-[#25D366] to-[#128C7E] px-6 pt-7 pb-6 text-white text-center relative overflow-hidden">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-white text-[#25D366] flex items-center justify-center shadow-lg transform -rotate-3">
                            <svg class="w-10 h-10 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.173.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-10.416c-5.524 0-10 4.477-10 10 0 1.956.565 3.78 1.547 5.325l-1.642 5.999 6.179-1.62c1.493.864 3.228 1.365 5.078 1.365 5.523 0 10-4.477 10-10 0-5.522-4.477-10-10-10z"/></svg>
                        </div>
                        <div class="mt-3 inline-flex items-center gap-1 bg-white/20 px-2.5 py-0.5 rounded-full text-[11px] font-bold">
                            <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            <span>Official WhatsApp Channel</span>
                        </div>
                        <h3 class="text-lg font-black mt-2 tracking-tight" x-text="heading"></h3>
                    </div>
                    <div class="p-6 text-center space-y-4">
                        <p class="text-xs text-gray-600 leading-relaxed" x-text="message"></p>
                        <div class="grid grid-cols-2 gap-2 text-left bg-emerald-50/60 p-3 rounded-2xl border border-emerald-100 text-[11px]">
                            <div class="font-semibold text-emerald-900"><span class="text-emerald-600">✓</span> Fresh harvest alerts</div>
                            <div class="font-semibold text-emerald-900"><span class="text-emerald-600">✓</span> Exclusive coupons</div>
                            <div class="font-semibold text-emerald-900"><span class="text-emerald-600">✓</span> Zero spam</div>
                            <div class="font-semibold text-emerald-900"><span class="text-emerald-600">✓</span> 100% Free to join</div>
                        </div>
                        <button type="button" @click="previewOpen = false" class="w-full bg-[#25D366] text-white font-extrabold text-sm py-3 px-6 rounded-2xl flex items-center justify-center gap-2 shadow-md">
                            <span x-text="btnText"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Telegram Order Notifications Card (Decoupled & Background-Ready) -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5" x-data="{
            testing: false,
            testMsg: '',
            testSuccess: false,
            showToken: false,
            runTest() {
                const token = document.getElementById('tg_bot_token').value.trim();
                const chatId = document.getElementById('tg_chat_id').value.trim();
                
                this.testing = true;
                this.testMsg = '';
                const fd = new FormData();
                if (token) fd.append('token', token);
                if (chatId) fd.append('chat_id', chatId);
                
                fetch('/admin/settings/telegram/test', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(d => {
                    this.testing = false;
                    this.testSuccess = d.success;
                    this.testMsg = d.success 
                        ? '✓ ' + (d.message || 'Test notification sent successfully to Telegram!') 
                        : ('✗ Failed: ' + (d.error || 'Connection error'));
                })
                .catch(err => {
                    this.testing = false;
                    this.testSuccess = false;
                    this.testMsg = '✗ Network error testing Telegram connection.';
                });
            }
        }">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-sm">
                        ✈️
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Telegram Order Alerts</h2>
                        <p class="text-[11px] text-gray-400">Automatic order notifications sent directly to your Telegram group or channel</p>
                    </div>
                </div>
                <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full <?= ($settings['telegram_notifications_enabled'] ?? '0') === '1' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' ?>">
                    <?= ($settings['telegram_notifications_enabled'] ?? '0') === '1' ? 'Active' : 'Disabled' ?>
                </span>
            </div>

            <div class="space-y-4 text-xs">
                <label class="flex items-start gap-3 p-3.5 bg-sky-50/50 border border-sky-100 rounded-2xl cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="telegram_notifications_enabled" 
                        value="1" 
                        <?= ($settings['telegram_notifications_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                        class="w-4 h-4 text-sky-600 rounded-sm focus:ring-sky-500 mt-0.5"
                    >
                    <div>
                        <span class="font-bold text-gray-900 block">Enable Instant Telegram Alerts</span>
                        <span class="text-[11px] text-gray-500">Automatically send rich Telegram order alerts with customer details, delivery address, items, and totals.</span>
                    </div>
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Telegram Bot Token</label>
                        <div class="relative">
                            <input 
                                :type="showToken ? 'text' : 'password'" 
                                id="tg_bot_token"
                                name="telegram_bot_token" 
                                value="<?= e($settings['telegram_bot_token'] ?? '') ?>" 
                                placeholder="e.g. 123456789:ABCDefghIJKLmnOPQRstuvWXyz"
                                class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 pr-10 py-2.5 font-mono text-xs focus:ring-2 focus:ring-brand-600 focus:outline-none"
                            >
                            <button 
                                type="button" 
                                @click="showToken = !showToken" 
                                class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 cursor-pointer"
                                :title="showToken ? 'Hide token' : 'Show token'"
                            >
                                <span x-show="!showToken">👁️</span>
                                <span x-show="showToken">🙈</span>
                            </button>
                        </div>
                        <span class="text-[10px] text-gray-400 mt-1 block">Created via @BotFather. Saved securely on the server.</span>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Target Chat / Channel / Group ID</label>
                        <input 
                            type="text" 
                            id="tg_chat_id"
                            name="telegram_chat_id" 
                            value="<?= e($settings['telegram_chat_id'] ?? '') ?>" 
                            placeholder="e.g. -100123456789 or 98765432"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 font-mono text-xs focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                        <span class="text-[10px] text-gray-400 mt-1 block">Chat or group ID (e.g. group ID starting with minus sign like -100...).</span>
                    </div>
                </div>

                <!-- Event notification toggles -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <label class="flex items-center gap-2.5 p-3 bg-gray-50 border border-gray-200 rounded-2xl cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="telegram_send_on_cod" 
                            value="1" 
                            <?= ($settings['telegram_send_on_cod'] ?? '1') === '1' ? 'checked' : '' ?> 
                            class="w-4 h-4 text-brand-600 rounded"
                        >
                        <div>
                            <span class="font-bold text-gray-800 text-xs block">Cash on Delivery Orders</span>
                            <span class="text-[10px] text-gray-500">Alert immediately when a customer places a COD order.</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-2.5 p-3 bg-gray-50 border border-gray-200 rounded-2xl cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="telegram_send_on_online_paid" 
                            value="1" 
                            <?= ($settings['telegram_send_on_online_paid'] ?? '1') === '1' ? 'checked' : '' ?> 
                            class="w-4 h-4 text-brand-600 rounded"
                        >
                        <div>
                            <span class="font-bold text-gray-800 text-xs block">Verified Online Payments</span>
                            <span class="text-[10px] text-gray-500">Alert only after Razorpay signature verification succeeds.</span>
                        </div>
                    </label>
                </div>

                <div class="pt-2 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100">
                    <button 
                        type="button" 
                        @click="runTest()"
                        :disabled="testing"
                        class="bg-sky-50 hover:bg-sky-100 text-sky-800 border border-sky-200 font-bold px-4 py-2.5 rounded-xl transition flex items-center gap-1.5 text-xs shadow-2xs disabled:opacity-50 cursor-pointer"
                    >
                        <span x-show="!testing">⚡ Test Bot Notification</span>
                        <span x-show="testing">Connecting to Telegram...</span>
                    </button>
                    <span x-show="testMsg" x-text="testMsg" class="font-bold text-xs" :class="testSuccess ? 'text-emerald-700' : 'text-red-600'"></span>
                </div>
            </div>

            <?php $queueStats = TelegramQueueService::getQueueStats(); ?>
            <!-- Server-Side Cron Job & Queue Telemetry Card -->
            <div class="mt-4 pt-4 border-t border-gray-100" x-data="{
                runningWorker: false,
                retryingQueue: false,
                copiedCron: false,
                workerActionMsg: '',
                stats: <?= htmlspecialchars(json_encode($queueStats), ENT_QUOTES, 'UTF-8') ?>,
                refreshStats() {
                    fetch('/admin/telegram/queue/stats')
                        .then(r => r.json())
                        .then(d => { if (d.success && d.stats) this.stats = d.stats; });
                },
                runWorkerNow() {
                    this.runningWorker = true;
                    this.workerActionMsg = '';
                    fetch('/admin/telegram/worker/run', { method: 'POST' })
                        .then(r => r.json())
                        .then(d => {
                            this.runningWorker = false;
                            this.workerActionMsg = d.message || 'Queue batch processed.';
                            if (d.stats) this.stats = d.stats;
                        })
                        .catch(() => {
                            this.runningWorker = false;
                            this.workerActionMsg = 'Error executing queue processor.';
                        });
                },
                retryFailed() {
                    this.retryingQueue = true;
                    this.workerActionMsg = '';
                    fetch('/admin/telegram/queue/retry', { method: 'POST' })
                        .then(r => r.json())
                        .then(d => {
                            this.retryingQueue = false;
                            this.workerActionMsg = d.message || 'Failed notifications reset to pending.';
                            if (d.stats) this.stats = d.stats;
                        })
                        .catch(() => {
                            this.retryingQueue = false;
                            this.workerActionMsg = 'Error retrying queue items.';
                        });
                },
                copyCronCommands() {
                    const cmd = '* * * * * /usr/bin/php /app/applet/bin/cron_telegram_queue.php >> /app/applet/storage/logs/telegram_cron.log 2>&1\n* * * * * sleep 30 && /usr/bin/php /app/applet/bin/cron_telegram_queue.php >> /app/applet/storage/logs/telegram_cron.log 2>&1';
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(cmd).then(() => {
                            this.copiedCron = true;
                            setTimeout(() => { this.copiedCron = false; }, 2500);
                        });
                    }
                }
            }">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="relative flex h-2.5 w-2.5">
                                <span :class="stats.is_cron_active ? 'animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75' : ''"></span>
                                <span :class="stats.is_cron_active ? 'relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500' : (stats.cron_status === 'delayed' ? 'relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500' : 'relative inline-flex rounded-full h-2.5 w-2.5 bg-gray-400')"></span>
                            </span>
                            <span>Server-Side Cron Job (30-Second Schedule)</span>
                        </span>
                    </div>

                    <div class="flex items-center gap-2 text-[11px]">
                        <span class="text-gray-500 font-medium">Cron Status:</span>
                        <template x-if="stats.is_cron_active">
                            <span class="bg-emerald-100 text-emerald-800 font-bold px-2.5 py-0.5 rounded-full uppercase text-[10px] inline-flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                ACTIVE (Verified <span x-text="stats.seconds_since_cron"></span>s ago)
                            </span>
                        </template>
                        <template x-if="!stats.is_cron_active && stats.cron_status === 'delayed'">
                            <span class="bg-amber-100 text-amber-800 font-bold px-2.5 py-0.5 rounded-full uppercase text-[10px]">
                                DELAYED (<span x-text="stats.seconds_since_cron"></span>s ago)
                            </span>
                        </template>
                        <template x-if="!stats.is_cron_active && stats.cron_status !== 'delayed'">
                            <span class="bg-gray-100 text-gray-700 font-bold px-2.5 py-0.5 rounded-full uppercase text-[10px]">
                                INACTIVE / AWAITING CRON
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Cron & Queue metrics grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-3 text-center">
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-2.5">
                        <div class="text-[10px] uppercase font-bold text-amber-700">Pending Queue</div>
                        <div class="text-lg font-black text-amber-900" x-text="stats.pending">0</div>
                        <div class="text-[9px] text-gray-400">waiting for 30s cron</div>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-2.5">
                        <div class="text-[10px] uppercase font-bold text-sky-700">Last Cron Execution</div>
                        <div class="text-sm font-black text-sky-900 truncate" x-text="stats.cron_last_execution_at ? stats.cron_last_execution_at.split(' ')[1] : 'Never'">Never</div>
                        <div class="text-[9px] text-gray-500" x-text="stats.cron_last_execution_at ? (stats.seconds_since_cron + 's ago (' + stats.cron_total_runs + ' runs)') : 'Awaiting first run'"></div>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-2.5">
                        <div class="text-[10px] uppercase font-bold text-emerald-700">Last Telegram Delivery</div>
                        <div class="text-sm font-black text-emerald-900 truncate" x-text="stats.last_delivery_at ? stats.last_delivery_at.split(' ')[1] : 'None yet'">None yet</div>
                        <div class="text-[9px] text-gray-500" x-text="stats.sent + ' delivered total'">0 delivered total</div>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-2.5">
                        <div class="text-[10px] uppercase font-bold text-red-700">Failed / Retrying</div>
                        <div class="text-lg font-black text-red-900" x-text="stats.failed">0</div>
                        <div class="text-[9px] text-gray-400" x-text="stats.processing + ' in processing'">0 in processing</div>
                    </div>
                </div>

                <!-- Hosting Server Crontab Setup Box (cPanel / DirectAdmin / Linux) -->
                <div class="bg-slate-900 text-slate-100 rounded-2xl p-3.5 mb-3 border border-slate-800 text-xs">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <div class="flex items-center gap-1.5 font-bold text-slate-300 text-[11px]">
                            <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            <span>Server Crontab Setup (cPanel, DirectAdmin, or Linux Server)</span>
                        </div>
                        <button 
                            type="button" 
                            @click="copyCronCommands()"
                            class="bg-slate-800 hover:bg-slate-700 text-amber-300 border border-slate-700 font-bold px-2.5 py-1 rounded-lg text-[10px] transition cursor-pointer flex items-center gap-1"
                        >
                            <span x-show="!copiedCron">📋 Copy Cron Commands</span>
                            <span x-show="copiedCron" class="text-emerald-400">✓ Copied to Clipboard!</span>
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-400 mb-2">
                        Runs non-stop 24/7 in the background without opening the website or Admin Panel. The two scheduled executions per minute achieve exact 30-second processing:
                    </p>
                    <pre class="bg-black/50 p-2.5 rounded-xl text-[10px] font-mono text-emerald-400 overflow-x-auto whitespace-pre leading-relaxed select-all border border-slate-800">* * * * * /usr/bin/php /app/applet/bin/cron_telegram_queue.php >> /app/applet/storage/logs/telegram_cron.log 2>&1
* * * * * sleep 30 && /usr/bin/php /app/applet/bin/cron_telegram_queue.php >> /app/applet/storage/logs/telegram_cron.log 2>&1</pre>
                </div>

                <!-- Action buttons -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-xs">
                    <div class="flex flex-wrap items-center gap-2">
                        <button 
                            type="button" 
                            @click="runWorkerNow()"
                            :disabled="runningWorker"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-3 py-1.5 rounded-xl transition flex items-center gap-1 text-[11px] disabled:opacity-50 cursor-pointer"
                            title="Server Cron runs automatically. Use this button only if you want to manually test batch processing."
                        >
                            <span x-show="!runningWorker">⚡ Test Queue Processing (Manual Debug Only)</span>
                            <span x-show="runningWorker">Processing batch...</span>
                        </button>
                        <button 
                            type="button" 
                            @click="retryFailed()"
                            :disabled="retryingQueue || stats.failed === 0"
                            class="bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold px-3 py-1.5 rounded-xl transition flex items-center gap-1 text-[11px] disabled:opacity-50 cursor-pointer"
                        >
                            <span x-show="!retryingQueue">🔄 Retry Failed (<span x-text="stats.failed"></span>)</span>
                            <span x-show="retryingQueue">Resetting queue...</span>
                        </button>
                        <button 
                            type="button" 
                            @click="refreshStats()"
                            class="text-gray-400 hover:text-gray-600 p-1 text-[11px] cursor-pointer"
                            title="Refresh statistics"
                        >
                            🔄 Refresh
                        </button>
                    </div>

                    <span x-show="workerActionMsg" x-text="workerActionMsg" class="font-bold text-[11px] text-emerald-700"></span>
                </div>

                <!-- Recent notifications table snippet -->
                <template x-if="stats.recent_jobs && stats.recent_jobs.length > 0">
                    <div class="mt-3 overflow-x-auto border border-gray-100 rounded-xl">
                        <table class="w-full text-left text-[11px]">
                            <thead class="bg-gray-50 border-b border-gray-100 text-gray-500 font-bold uppercase text-[9px]">
                                <tr>
                                    <th class="py-2 px-3">Order</th>
                                    <th class="py-2 px-3">Event</th>
                                    <th class="py-2 px-3">Status</th>
                                    <th class="py-2 px-3">Attempts</th>
                                    <th class="py-2 px-3">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="j in stats.recent_jobs" :key="j.id">
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="py-1.5 px-3 font-mono font-bold text-gray-800" x-text="j.order_number || ('#' + j.order_id)"></td>
                                        <td class="py-1.5 px-3 capitalize text-gray-600" x-text="(j.event_type || '').replace('_', ' ')"></td>
                                        <td class="py-1.5 px-3">
                                            <span 
                                                class="px-2 py-0.5 rounded-full font-bold text-[9px] uppercase"
                                                :class="{
                                                    'bg-amber-100 text-amber-800': j.status === 'pending',
                                                    'bg-blue-100 text-blue-800': j.status === 'processing',
                                                    'bg-emerald-100 text-emerald-800': j.status === 'sent',
                                                    'bg-red-100 text-red-800': j.status === 'failed'
                                                }"
                                                x-text="j.status"
                                            ></span>
                                        </td>
                                        <td class="py-1.5 px-3 text-gray-500" x-text="j.attempts + ' / ' + j.max_attempts"></td>
                                        <td class="py-1.5 px-3 text-gray-400" x-text="j.created_at"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs py-3 px-6 rounded-xl shadow-xs transition">
                Save All Settings
            </button>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
