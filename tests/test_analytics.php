<?php
/**
 * Test Suite: Live Traffic & Visitor Analytics
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

echo "========================================================\n";
echo "RUNNING LIVE TRAFFIC & VISITOR ANALYTICS TEST SUITE\n";
echo "========================================================\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $title): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$title}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title}\n";
    }
}

// 1. Direct Visit Test
$v1 = 'test_vid_direct_' . bin2hex(random_bytes(4));
$hitDirect = AnalyticsService::recordHit([
    'visitor_id' => $v1,
    'path' => '/',
    'title' => 'Home - Fresh Coconut Water',
    'referrer' => '',
    'query' => [],
    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36'
]);
assertTest($hitDirect['success'] === true, 'Direct visit hit recorded successfully');
assertTest(!empty($hitDirect['session_token']), 'Session token generated for direct visit');

// 2. WhatsApp UTM Visit Test
$v2 = 'test_vid_whatsapp_' . bin2hex(random_bytes(4));
$hitWhatsapp = AnalyticsService::recordHit([
    'visitor_id' => $v2,
    'path' => '/shop',
    'title' => 'Shop Coconut Water',
    'referrer' => 'https://api.whatsapp.com/',
    'query' => ['utm_source' => 'whatsapp', 'utm_medium' => 'chat', 'utm_campaign' => 'fresh_harvest_morning'],
    'user_agent' => 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Mobile Safari/537.36'
]);
assertTest($hitWhatsapp['success'] === true, 'WhatsApp campaign hit recorded successfully');

// 3. Instagram UTM Visit Test
$v3 = 'test_vid_ig_' . bin2hex(random_bytes(4));
$hitIg = AnalyticsService::recordHit([
    'visitor_id' => $v3,
    'path' => '/product/1',
    'title' => 'Tender Coconut Water 500ml',
    'referrer' => 'https://l.instagram.com/',
    'query' => ['utm_source' => 'instagram', 'utm_medium' => 'reels_bio', 'utm_campaign' => 'summer_cool'],
    'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'
]);
assertTest($hitIg['success'] === true, 'Instagram campaign hit recorded successfully');

// 4. YouTube UTM Visit Test
$v4 = 'test_vid_yt_' . bin2hex(random_bytes(4));
$hitYt = AnalyticsService::recordHit([
    'visitor_id' => $v4,
    'path' => '/',
    'title' => 'Home - N.A Fresh Fruits',
    'referrer' => 'https://www.youtube.com/',
    'query' => ['utm_source' => 'youtube', 'utm_medium' => 'video_description'],
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
]);
assertTest($hitYt['success'] === true, 'YouTube referrer hit recorded successfully');

// 5. Google Campaign Link Test (Google Ads)
$v5 = 'test_vid_gads_' . bin2hex(random_bytes(4));
$hitGads = AnalyticsService::recordHit([
    'visitor_id' => $v5,
    'path' => '/shop',
    'title' => 'Shop Pure Coconut Water',
    'referrer' => 'https://www.google.com/',
    'query' => ['utm_source' => 'google_ads', 'utm_medium' => 'cpc', 'utm_campaign' => 'coconut_water_jaora', 'gclid' => 'testGclid123'],
    'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/122.0.0.0 Mobile'
]);
assertTest($hitGads['success'] === true, 'Google Ads campaign recorded successfully');

// 6. QR Code Standee Campaign Link Test
$v6 = 'test_vid_qr_' . bin2hex(random_bytes(4));
$hitQr = AnalyticsService::recordHit([
    'visitor_id' => $v6,
    'path' => '/cart',
    'title' => 'Cart - N.A Fresh Fruits',
    'referrer' => '',
    'query' => ['ref' => 'qr', 'utm_campaign' => 'storefront_standee_qr'],
    'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5) Safari/604.1'
]);
assertTest($hitQr['success'] === true, 'QR code campaign visit recorded successfully');

// 7. Multi-Page Browsing in Same Session Test
$subsequentHit = AnalyticsService::recordHit([
    'visitor_id' => $v2,
    'session_token' => $hitWhatsapp['session_token'],
    'path' => '/checkout',
    'title' => 'Checkout - Fresh Coconut Water',
    'referrer' => '/shop',
    'query' => []
]);
assertTest($subsequentHit['success'] === true, 'Subsequent page hit in same session recorded');

// 8. Heartbeat Test
$heartbeatOk = AnalyticsService::recordHeartbeat($hitWhatsapp['session_token'], '/checkout');
assertTest($heartbeatOk === true, 'Heartbeat successfully updated session last_active_at');

// 9. Live Active Visitors Count Test
$activeCount = AnalyticsService::getActiveVisitorCount(5);
assertTest($activeCount >= 5, "Live active visitor count is accurate (found: {$activeCount})");

$activeList = AnalyticsService::getActiveVisitors(10, 5);
assertTest(count($activeList) >= 5, "Active visitors list returned (count: " . count($activeList) . ")");
assertTest(!empty($activeList[0]['current_path']), 'Active visitor contains current browsing path');

// 10. Date Range Resolver Tests
$rangeToday = AnalyticsService::resolveDateRange('today');
assertTest($rangeToday['preset'] === 'today', 'Preset today resolved properly');

$range7d = AnalyticsService::resolveDateRange('7d');
assertTest($range7d['preset'] === '7d', 'Preset 7d resolved properly');

$rangeCust = AnalyticsService::resolveDateRange('custom', '2026-10-01', '2026-10-31');
assertTest(str_starts_with($rangeCust['from'], '2026-10-01'), 'Custom date range resolved properly');

// 11. Dashboard Metrics Aggregation Tests
$metrics = AnalyticsService::getDashboardMetrics($rangeToday);
assertTest($metrics['total_visits'] >= 6, 'Dashboard metrics total_visits computed');
assertTest($metrics['unique_visitors'] >= 6, 'Dashboard metrics unique_visitors computed');
assertTest($metrics['total_page_views'] >= 7, 'Dashboard metrics total_page_views computed');
assertTest(!empty($metrics['sources']), 'Dashboard metrics sources breakdown populated');
assertTest(!empty($metrics['top_pages']), 'Dashboard metrics top_pages populated');
assertTest(!empty($metrics['devices']), 'Dashboard metrics device breakdown populated');

// 12. Privacy Verification Test
$pdo = Database::getConnection();
$stmtPiiCheck = $pdo->query("SELECT `url_path` FROM `analytics_page_views` WHERE `url_path` LIKE '%password%' OR `url_path` LIKE '%token%' OR `url_path` LIKE '%card%'");
$piiRows = $stmtPiiCheck->fetchAll();
assertTest(empty($piiRows), 'Privacy check: No sensitive form tokens or passwords saved in analytics page views');

// 13. Source Classification Accuracy Checks
$sources = array_column($metrics['sources'], 'source_name');
assertTest(in_array('WhatsApp', $sources), 'WhatsApp classified in sources breakdown');
assertTest(in_array('Instagram', $sources), 'Instagram classified in sources breakdown');
assertTest(in_array('YouTube', $sources), 'YouTube classified in sources breakdown');
assertTest(in_array('Google Ads', $sources), 'Google Ads classified in sources breakdown');
assertTest(in_array('QR Code Campaign', $sources), 'QR Code Campaign classified in sources breakdown');

// 14. Device Classification Accuracy Checks
$devices = array_column($metrics['devices'], 'device_type');
assertTest(in_array('mobile', $devices), 'Mobile devices classified properly');
assertTest(in_array('desktop', $devices), 'Desktop devices classified properly');

echo "========================================================\n";
echo "ANALYTICS SUITE SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
