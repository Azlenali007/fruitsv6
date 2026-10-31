<?php
/**
 * Live Traffic & Visitor Analytics Dashboard View
 * N.A Fresh Fruits & Coconuts Admin Portal
 */
$adminTitle = 'Live Traffic & Visitor Analytics - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'analytics';

require_once __DIR__ . '/layout-header.php';
?>

<div class="space-y-6" x-data="{
    liveCount: <?= (int)$liveCount ?>,
    lastUpdated: '<?= date('h:i:s A') ?>',
    liveVisitors: <?= json_encode($liveVisitors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    autoRefresh: true,
    refreshInterval: null,
    pollLive() {
        fetch('/api/analytics/live')
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    this.liveCount = d.live_count;
                    this.lastUpdated = d.timestamp;
                    if (d.visitors) {
                        this.liveVisitors = d.visitors;
                    }
                }
            })
            .catch(e => console.error('Live poll failed', e));
    },
    init() {
        this.refreshInterval = setInterval(() => {
            if (this.autoRefresh) {
                this.pollLive();
            }
        }, 5000);
    }
}">

    <!-- Top Bar: Header & Live Indicator -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-[#235d3a] flex items-center justify-center font-bold text-lg">
                    📊
                </div>
                <div>
                    <h1 class="text-xl font-extrabold text-gray-900 tracking-tight">Live Traffic & Visitor Analytics</h1>
                    <p class="text-xs text-gray-500">Real-time visitor telemetry, UTM campaign channels, device statistics & order geography</p>
                </div>
            </div>
        </div>

        <!-- Real-Time Active Visitor Widget -->
        <div class="flex items-center gap-4 bg-emerald-50/70 border border-emerald-200/80 px-4 py-2.5 rounded-2xl">
            <div class="flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-[#235d3a]"></span>
                </span>
                <span class="text-xs font-bold uppercase tracking-wider text-[#235d3a]">Live Active</span>
            </div>
            <div class="h-6 w-px bg-emerald-200"></div>
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-gray-900 leading-none" x-text="liveCount"><?= $liveCount ?></span>
                <span class="text-[11px] font-semibold text-gray-500">browsing now</span>
            </div>
            <div class="hidden sm:block text-[10px] text-gray-400 border-l border-emerald-200 pl-3">
                Updated: <span x-text="lastUpdated" class="font-mono font-medium"><?= date('h:i:s A') ?></span>
            </div>
            <button 
                type="button" 
                @click="pollLive()" 
                title="Refresh live status"
                class="text-[#235d3a] hover:text-[#1b4a2e] transition p-1 hover:bg-emerald-100 rounded-lg cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </div>
    </div>

    <!-- Date Range Filter Bar -->
    <div class="bg-white p-4 rounded-3xl border border-gray-100 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider mr-1">Time Period:</span>
            
            <a href="/admin/analytics?range=today" 
               class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition <?= ($dateRange['preset'] ?? '') === 'today' ? 'bg-[#235d3a] text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                Today
            </a>
            
            <a href="/admin/analytics?range=yesterday" 
               class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition <?= ($dateRange['preset'] ?? '') === 'yesterday' ? 'bg-[#235d3a] text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                Yesterday
            </a>

            <a href="/admin/analytics?range=7d" 
               class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition <?= ($dateRange['preset'] ?? '') === '7d' ? 'bg-[#235d3a] text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                Last 7 Days
            </a>

            <a href="/admin/analytics?range=30d" 
               class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition <?= ($dateRange['preset'] ?? '') === '30d' ? 'bg-[#235d3a] text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                Last 30 Days
            </a>
        </div>

        <!-- Custom Date Range Form -->
        <form action="/admin/analytics" method="GET" class="flex flex-wrap items-center gap-2 text-xs">
            <input type="hidden" name="range" value="custom">
            <label class="text-gray-500 font-semibold">From:</label>
            <input 
                type="date" 
                name="from" 
                value="<?= e(substr($dateRange['from'], 0, 10)) ?>" 
                class="bg-gray-50 border border-gray-200 rounded-xl px-2.5 py-1.5 text-xs font-medium focus:ring-2 focus:ring-[#235d3a] focus:outline-none"
            >
            <label class="text-gray-500 font-semibold">To:</label>
            <input 
                type="date" 
                name="to" 
                value="<?= e(substr($dateRange['to'], 0, 10)) ?>" 
                class="bg-gray-50 border border-gray-200 rounded-xl px-2.5 py-1.5 text-xs font-medium focus:ring-2 focus:ring-[#235d3a] focus:outline-none"
            >
            <button 
                type="submit" 
                class="bg-[#235d3a] hover:bg-[#1b4a2e] text-white font-bold px-3.5 py-1.5 rounded-xl transition shadow-xs cursor-pointer"
            >
                Filter
            </button>
        </form>
    </div>

    <!-- Active Filter Badge -->
    <div class="flex items-center justify-between text-xs text-gray-500 px-2">
        <div>
            Showing analytics for: <span class="font-bold text-gray-900"><?= e($dateRange['label']) ?></span>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>First-Party Cookieless/Privacy-Preserving Telemetry</span>
        </div>
    </div>

    <!-- 5 Core Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5 sm:gap-4">
        
        <!-- 1. Total Visits (Sessions) -->
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-gray-100 shadow-xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Total Visits</span>
                <span class="text-sm">🧭</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-gray-900"><?= number_format($metrics['total_visits']) ?></p>
            <p class="text-[11px] text-gray-500">Browsing sessions</p>
        </div>

        <!-- 2. Unique Visitors -->
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-gray-100 shadow-xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Unique Visitors</span>
                <span class="text-sm">👥</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-emerald-800"><?= number_format($metrics['unique_visitors']) ?></p>
            <p class="text-[11px] text-gray-500">Distinct individuals</p>
        </div>

        <!-- 3. Total Page Views -->
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-gray-100 shadow-xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Page Views</span>
                <span class="text-sm">📄</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-blue-800"><?= number_format($metrics['total_page_views']) ?></p>
            <p class="text-[11px] text-gray-500">Avg <?= $metrics['pages_per_visit'] ?> / visit</p>
        </div>

        <!-- 4. New Visitors -->
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-gray-100 shadow-xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">New Visitors</span>
                <span class="text-sm">✨</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-amber-700"><?= number_format($metrics['new_visitors']) ?></p>
            <p class="text-[11px] text-gray-500">First-time discovery</p>
        </div>

        <!-- 5. Returning Visitors -->
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-gray-100 shadow-xs space-y-1 col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Returning Visitors</span>
                <span class="text-sm">🔄</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-purple-800"><?= number_format($metrics['returning_visitors']) ?></p>
            <p class="text-[11px] text-gray-500">Repeat store visits</p>
        </div>

    </div>

    <!-- Charts Section: Traffic Trend & Traffic Sources -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left 2 Cols: Traffic Over Time -->
        <div class="lg:col-span-2 bg-white p-5 sm:p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Traffic Over Time</h2>
                    <p class="text-[11px] text-gray-400">Page Views & Unique Visitors daily trend</p>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="flex items-center gap-1.5 font-bold text-[#235d3a]">
                        <span class="w-3 h-3 rounded-full bg-[#235d3a]"></span> Page Views
                    </span>
                    <span class="flex items-center gap-1.5 font-bold text-blue-600">
                        <span class="w-3 h-3 rounded-full bg-blue-500"></span> Unique Visitors
                    </span>
                </div>
            </div>

            <div class="h-64 sm:h-72 relative">
                <?php if (!empty($metrics['trend'])): ?>
                <canvas id="trafficTrendChart"></canvas>
                <?php else: ?>
                <div class="h-full flex flex-col items-center justify-center text-center text-gray-400">
                    <span class="text-3xl mb-1">📈</span>
                    <p class="text-xs font-semibold">No recorded traffic for this specific date range yet.</p>
                    <p class="text-[11px]">As visitors browse the store, real-time points will plot automatically.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right 1 Col: Traffic Sources Breakdown Doughnut -->
        <div class="bg-white p-5 sm:p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Traffic Channels</h2>
                <p class="text-[11px] text-gray-400">Source acquisition breakdown</p>
            </div>

            <div class="h-56 relative flex items-center justify-center">
                <?php if (!empty($metrics['sources'])): ?>
                <canvas id="trafficSourcesChart"></canvas>
                <?php else: ?>
                <div class="h-full flex flex-col items-center justify-center text-center text-gray-400">
                    <span class="text-3xl mb-1">🥧</span>
                    <p class="text-xs font-semibold">No channel data yet</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Compact Legend List -->
            <div class="space-y-1.5 text-xs pt-1 max-h-36 overflow-y-auto">
                <?php foreach (array_slice($metrics['sources'], 0, 5) as $src): ?>
                <div class="flex items-center justify-between py-1 border-b border-gray-50">
                    <span class="font-bold text-gray-700 truncate"><?= e($src['source_name']) ?></span>
                    <span class="font-bold text-gray-900 bg-gray-50 px-2 py-0.5 rounded-lg border border-gray-100"><?= number_format($src['visits_count']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Live Visitors Real-Time Active Table -->
    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Visitors Currently Browsing</h2>
                    <p class="text-[11px] text-gray-400">Active session heartbeats within the last 5 minutes</p>
                </div>
            </div>
            <span class="text-xs font-bold bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full">
                <span x-text="liveVisitors.length"><?= count($liveVisitors) ?></span> Active
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100 uppercase text-[10px] tracking-wider">
                        <th class="py-2.5 px-3">Visitor ID</th>
                        <th class="py-2.5 px-3">Device & Browser</th>
                        <th class="py-2.5 px-3">Traffic Source</th>
                        <th class="py-2.5 px-3">Current Path</th>
                        <th class="py-2.5 px-3">Location</th>
                        <th class="py-2.5 px-3 text-right">Last Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template x-for="v in liveVisitors" :key="v.session_token">
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-2.5 px-3 font-mono font-bold text-gray-800" x-text="v.visitor_id.substring(0, 14) + '...'"></td>
                            <td class="py-2.5 px-3">
                                <span class="capitalize font-semibold text-gray-700" x-text="v.device_type"></span>
                                <span class="text-[11px] text-gray-400" x-text="'• ' + (v.browser || 'Browser')"></span>
                            </td>
                            <td class="py-2.5 px-3 font-bold text-emerald-700" x-text="v.source_name"></td>
                            <td class="py-2.5 px-3 font-mono text-[11px] text-gray-600 truncate max-w-xs" x-text="v.current_path"></td>
                            <td class="py-2.5 px-3 text-gray-500" x-text="(v.city || 'Jaora') + ', ' + (v.country || 'India')"></td>
                            <td class="py-2.5 px-3 text-right font-medium text-emerald-800">
                                <span x-text="v.seconds_ago !== undefined ? (v.seconds_ago < 60 ? v.seconds_ago + 's ago' : Math.floor(v.seconds_ago/60) + 'm ago') : 'Just now'"></span>
                            </td>
                        </tr>
                    </template>
                    <?php if (empty($liveVisitors)): ?>
                    <tr x-show="liveVisitors.length === 0">
                        <td colspan="6" class="py-8 text-center text-gray-400">
                            No external visitors active right now. Open storefront in another tab to observe live heartbeat.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Row: Top Visited Pages & Marketing Campaigns -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Top Visited Pages -->
        <div class="bg-white p-5 sm:p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Most Visited Pages</h2>
                <p class="text-[11px] text-gray-400">Pages receiving the highest traffic</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-100 uppercase text-[10px] tracking-wider">
                            <th class="py-2.5 px-2">Page URL</th>
                            <th class="py-2.5 px-2 text-right">Views</th>
                            <th class="py-2.5 px-2 text-right">Unique</th>
                            <th class="py-2.5 px-2 text-right">% Share</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php 
                        $totalViews = max(1, (int)$metrics['total_page_views']);
                        foreach ($metrics['top_pages'] as $page): 
                            $share = round(($page['views_count'] / $totalViews) * 100, 1);
                        ?>
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-2.5 px-2 font-mono font-medium text-gray-800 truncate max-w-xs">
                                <?php if ($page['url_path'] === '/'): ?>
                                    <span class="bg-emerald-50 text-emerald-800 font-bold px-1.5 py-0.5 rounded text-[10px] mr-1">HOME</span>
                                <?php elseif (str_contains($page['url_path'], '/cart')): ?>
                                    <span class="bg-amber-50 text-amber-800 font-bold px-1.5 py-0.5 rounded text-[10px] mr-1">CART</span>
                                <?php elseif (str_contains($page['url_path'], '/checkout')): ?>
                                    <span class="bg-blue-50 text-blue-800 font-bold px-1.5 py-0.5 rounded text-[10px] mr-1">CHECKOUT</span>
                                <?php elseif (str_contains($page['url_path'], '/product/')): ?>
                                    <span class="bg-purple-50 text-purple-800 font-bold px-1.5 py-0.5 rounded text-[10px] mr-1">PRODUCT</span>
                                <?php endif; ?>
                                <?= e($page['url_path']) ?>
                            </td>
                            <td class="py-2.5 px-2 text-right font-bold text-gray-900"><?= number_format($page['views_count']) ?></td>
                            <td class="py-2.5 px-2 text-right font-medium text-gray-600"><?= number_format($page['unique_visitors']) ?></td>
                            <td class="py-2.5 px-2 text-right font-bold text-[#235d3a]"><?= $share ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($metrics['top_pages'])): ?>
                        <tr><td colspan="4" class="py-6 text-center text-gray-400">No page views recorded in this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Marketing Campaigns (UTM) -->
        <div class="bg-white p-5 sm:p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Campaign Tracking (UTM)</h2>
                <p class="text-[11px] text-gray-400">Performance of promotional links and marketing initiatives</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-100 uppercase text-[10px] tracking-wider">
                            <th class="py-2.5 px-2">Campaign Name</th>
                            <th class="py-2.5 px-2">Source / Medium</th>
                            <th class="py-2.5 px-2 text-right">Sessions</th>
                            <th class="py-2.5 px-2 text-right">Unique Visitors</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($metrics['campaigns'] as $camp): ?>
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-2.5 px-2 font-bold text-gray-900 truncate max-w-xs">
                                📢 <?= e($camp['utm_campaign']) ?>
                            </td>
                            <td class="py-2.5 px-2 text-gray-600">
                                <span class="bg-gray-100 px-2 py-0.5 rounded font-mono text-[11px]"><?= e($camp['utm_source']) ?> / <?= e($camp['utm_medium']) ?></span>
                            </td>
                            <td class="py-2.5 px-2 text-right font-bold text-gray-900"><?= number_format($camp['sessions_count']) ?></td>
                            <td class="py-2.5 px-2 text-right font-bold text-emerald-800"><?= number_format($camp['unique_visitors']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($metrics['campaigns'])): ?>
                        <tr>
                            <td colspan="4" class="py-6 text-center text-gray-400">
                                No custom campaign links used in this period.<br>
                                <span class="text-[11px] text-gray-400">Share links with <code class="text-[#235d3a]">?utm_source=whatsapp&utm_campaign=harvest_deal</code> to track.</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Row: Devices & Geography / Order Areas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Device Categories -->
        <div class="bg-white p-5 sm:p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Device Breakdown</h2>
                <p class="text-[11px] text-gray-400">Mobile vs Desktop usage</p>
            </div>

            <div class="space-y-3 pt-1">
                <?php 
                $totalSessions = max(1, (int)$metrics['total_visits']);
                $devMap = [];
                foreach ($metrics['devices'] as $d) {
                    $devMap[$d['device_type']] = (int)$d['sessions_count'];
                }
                $mobileCount = $devMap['mobile'] ?? 0;
                $desktopCount = $devMap['desktop'] ?? 0;
                $tabletCount = $devMap['tablet'] ?? 0;
                $allDev = max(1, $mobileCount + $desktopCount + $tabletCount);
                ?>

                <!-- Mobile -->
                <div class="space-y-1">
                    <div class="flex justify-between text-xs font-bold text-gray-700">
                        <span>📱 Mobile Phones</span>
                        <span><?= round(($mobileCount / $allDev) * 100, 1) ?>% (<?= $mobileCount ?>)</span>
                    </div>
                    <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden">
                        <div class="bg-[#235d3a] h-full rounded-full" style="width: <?= round(($mobileCount / $allDev) * 100) ?>%"></div>
                    </div>
                </div>

                <!-- Desktop -->
                <div class="space-y-1">
                    <div class="flex justify-between text-xs font-bold text-gray-700">
                        <span>💻 Desktop & Laptops</span>
                        <span><?= round(($desktopCount / $allDev) * 100, 1) ?>% (<?= $desktopCount ?>)</span>
                    </div>
                    <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden">
                        <div class="bg-blue-600 h-full rounded-full" style="width: <?= round(($desktopCount / $allDev) * 100) ?>%"></div>
                    </div>
                </div>

                <!-- Tablets -->
                <div class="space-y-1">
                    <div class="flex justify-between text-xs font-bold text-gray-700">
                        <span>📟 Tablets</span>
                        <span><?= round(($tabletCount / $allDev) * 100, 1) ?>% (<?= $tabletCount ?>)</span>
                    </div>
                    <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden">
                        <div class="bg-purple-600 h-full rounded-full" style="width: <?= round(($tabletCount / $allDev) * 100) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visitor Approximate Locations -->
        <div class="bg-white p-5 sm:p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Visitor Locations</h2>
                <p class="text-[11px] text-gray-400">Approximate regional presence</p>
            </div>

            <div class="space-y-2 max-h-48 overflow-y-auto text-xs">
                <?php foreach ($metrics['locations'] as $loc): ?>
                <div class="flex items-center justify-between py-1.5 border-b border-gray-50">
                    <div class="flex items-center gap-2">
                        <span>📍</span>
                        <span class="font-bold text-gray-800"><?= e($loc['city'] ?: 'Jaora') ?></span>
                        <span class="text-[11px] text-gray-400">(<?= e($loc['country'] ?: 'India') ?>)</span>
                    </div>
                    <span class="font-bold text-gray-900 bg-gray-50 px-2 py-0.5 rounded-md border border-gray-100"><?= number_format($loc['visitors_count']) ?> visitors</span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($metrics['locations'])): ?>
                <p class="text-center text-gray-400 py-6">No location data for this period.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Order Delivery Localities -->
        <div class="bg-white p-5 sm:p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Order Delivery Zones</h2>
                <p class="text-[11px] text-gray-400">Where actual customer orders are shipped</p>
            </div>

            <div class="space-y-2 max-h-48 overflow-y-auto text-xs">
                <?php foreach ($metrics['order_areas'] as $oa): ?>
                <div class="flex items-center justify-between py-1.5 border-b border-gray-50">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[#235d3a] font-bold">🥥</span>
                        <span class="font-bold text-gray-800 truncate"><?= e($oa['area_name']) ?></span>
                    </div>
                    <div class="text-right">
                        <span class="font-bold text-[#235d3a]"><?= number_format($oa['order_count']) ?> orders</span>
                        <span class="text-[10px] text-gray-400 block">₹<?= number_format((float)$oa['revenue'], 2) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($metrics['order_areas'])): ?>
                <p class="text-center text-gray-400 py-6">No completed orders during this time period.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- Chart.js Scripts Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Traffic Trend Line Chart
    <?php if (!empty($metrics['trend'])): ?>
    const trendCtx = document.getElementById('trafficTrendChart');
    if (trendCtx) {
        const trendLabels = <?= json_encode(array_map(fn($t) => date('d M', strtotime($t['date'])), $metrics['trend'])) ?>;
        const trendViews = <?= json_encode(array_map(fn($t) => (int)$t['page_views'], $metrics['trend'])) ?>;
        const trendVisitors = <?= json_encode(array_map(fn($t) => (int)$t['unique_visitors'], $metrics['trend'])) ?>;

        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Page Views',
                        data: trendViews,
                        borderColor: '#235d3a',
                        backgroundColor: 'rgba(35, 93, 58, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2.5
                    },
                    {
                        label: 'Unique Visitors',
                        data: trendVisitors,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.05)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2.5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.04)' }
                    },
                    x: {
                        ticks: { font: { size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    // 2. Traffic Sources Doughnut Chart
    <?php if (!empty($metrics['sources'])): ?>
    const srcCtx = document.getElementById('trafficSourcesChart');
    if (srcCtx) {
        const srcLabels = <?= json_encode(array_column($metrics['sources'], 'source_name')) ?>;
        const srcCounts = <?= json_encode(array_map('intval', array_column($metrics['sources'], 'visits_count'))) ?>;
        const bgColors = [
            '#235d3a', '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', 
            '#ec4899', '#06b6d4', '#64748b', '#84cc16', '#6366f1'
        ];

        new Chart(srcCtx, {
            type: 'doughnut',
            data: {
                labels: srcLabels,
                datasets: [{
                    data: srcCounts,
                    backgroundColor: bgColors.slice(0, srcLabels.length),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                cutout: '68%'
            }
        });
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
