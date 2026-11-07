<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Delivery History - Partner Panel</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link rel="manifest" href="/manifest-delboy.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NA Rider">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
    <meta name="theme-color" content="#064e3b">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw-delboy.js', { scope: '/' }).catch(() => {});
        });
    }
    </script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full bg-slate-950 text-slate-100 font-sans antialiased pb-24">

    <!-- Top Navigation -->
    <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-md border-b border-emerald-900/40 px-4 py-3 flex items-center justify-between shadow-lg">
        <div class="flex items-center gap-3">
            <a href="/delivery/dashboard" class="p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div>
                <h1 class="text-base font-black text-white">Delivery History</h1>
                <p class="text-[11px] text-slate-400">Completed Fulfillment Records</p>
            </div>
        </div>
        <span class="text-xs font-black text-emerald-400"><?= count($history) ?> Delivered</span>
    </header>

    <main class="max-w-lg mx-auto px-4 py-4 space-y-3">
        <?php if (empty($history)): ?>
            <div class="p-8 bg-slate-900/70 border border-slate-800 rounded-3xl text-center space-y-3">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-950/60 border border-emerald-500/20 flex items-center justify-center text-2xl text-emerald-400">
                    📜
                </div>
                <h3 class="text-sm font-bold text-white">No Completed Deliveries Yet</h3>
                <p class="text-xs text-slate-400">
                    Your completed orders and customer delivery confirmations will show up here.
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $h): ?>
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-4 shadow-xl">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                DELIVERED
                            </span>
                            <span class="text-xs font-bold text-white">#<?= e($h['order_number']) ?></span>
                        </div>
                        <span class="text-sm font-black text-emerald-400">₹<?= number_format((float)$h['total_amount'], 2) ?></span>
                    </div>

                    <div class="mt-3 space-y-1 text-xs">
                        <div class="flex items-center justify-between">
                            <strong class="text-white"><?= e($h['customer_name']) ?></strong>
                            <span class="text-[11px] text-slate-400"><?= date('d M Y, h:i A', strtotime($h['delivery_completed_at'])) ?></span>
                        </div>
                        <p class="text-slate-400 text-[11px]">📍 <?= e($h['delivery_address']) ?></p>
                        <div class="pt-2 flex items-center justify-between text-[11px]">
                            <span class="text-slate-400">
                                Mode: <strong class="text-white uppercase"><?= e($h['payment_method']) ?></strong>
                            </span>
                            <?php if ($h['payment_method'] === 'cod'): ?>
                                <span class="px-2 py-0.5 rounded-md font-bold <?= ($h['cod_reconciliation_status'] === 'reconciled_with_admin') ? 'bg-emerald-950 text-emerald-300 border border-emerald-500/30' : 'bg-amber-950 text-amber-300 border border-amber-500/30' ?>">
                                    COD: <?= ($h['cod_reconciliation_status'] === 'reconciled_with_admin') ? 'Reconciled with Store' : 'Cash in Hand' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-emerald-400 font-bold">Online Paid</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- Bottom Nav -->
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-slate-900/95 backdrop-blur-md border-t border-slate-800 px-6 py-2 flex items-center justify-around text-[10px] font-semibold text-slate-400">
        <a href="/delivery/dashboard" class="flex flex-col items-center gap-1 hover:text-white transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span>Orders</span>
        </a>
        <a href="/delivery/history" class="flex flex-col items-center gap-1 text-emerald-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>History</span>
        </a>
    </nav>
</body>
</html>
