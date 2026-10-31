<?php
/**
 * Analytics Service - N.A Fresh Fruits & Coconuts
 * Lightweight, privacy-conscious first-party visitor and traffic tracking.
 * Never collects sensitive form fields, payment details, or personal data.
 */

class AnalyticsService {
    public const COOKIE_VISITOR_ID = 'na_vid';
    public const COOKIE_SESSION_ID = 'na_sid';
    public const SESSION_TIMEOUT_MINUTES = 30;
    public const ACTIVE_WINDOW_MINUTES = 5;

    /**
     * Parse User-Agent into device type, browser and OS
     */
    public static function parseUserAgent(?string $ua): array {
        $ua = (string)$ua;
        $device = 'desktop';
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/(mobi|ipod|iphone|android|opera mini|blackberry|iemobile)/i', $ua)) {
            $device = 'mobile';
        }

        // Browser detection
        $browser = 'Other';
        if (preg_match('/edg/i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/chrome|crios/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/firefox|fxios/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/opera|opr/i', $ua)) {
            $browser = 'Opera';
        } elseif (preg_match('/samsungbrowser/i', $ua)) {
            $browser = 'Samsung Internet';
        }

        // OS detection
        $os = 'Other';
        if (preg_match('/android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $ua)) {
            $os = 'iOS';
        } elseif (preg_match('/windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $ua)) {
            $os = 'Linux';
        }

        return [
            'device_type' => $device,
            'browser' => $browser,
            'os' => $os
        ];
    }

    /**
     * Classify traffic source based on UTM parameters, ref query, and Referrer
     */
    public static function classifySource(?string $referrer, array $queryParams): array {
        $utmSource = strtolower(trim($queryParams['utm_source'] ?? ''));
        $utmMedium = strtolower(trim($queryParams['utm_medium'] ?? ''));
        $utmCampaign = trim($queryParams['utm_campaign'] ?? '');
        $refParam = strtolower(trim($queryParams['ref'] ?? ($queryParams['source'] ?? '')));

        // 1. QR Code Campaign
        if ($refParam === 'qr' || $utmSource === 'qr' || !empty($queryParams['qr'])) {
            return [
                'type' => 'qr_code',
                'name' => 'QR Code Campaign',
                'utm_source' => $utmSource ?: 'qr',
                'utm_medium' => $utmMedium ?: 'offline_print',
                'utm_campaign' => $utmCampaign ?: 'store_standee_qr'
            ];
        }

        // 2. Paid Ads (Google / Meta / Instagram)
        if (!empty($queryParams['gclid']) || $utmMedium === 'cpc' || str_contains($utmSource, 'google_ads')) {
            return [
                'type' => 'google_ads',
                'name' => 'Google Ads',
                'utm_source' => $utmSource ?: 'google_ads',
                'utm_medium' => 'cpc',
                'utm_campaign' => $utmCampaign
            ];
        }

        if (!empty($queryParams['fbclid']) && ($utmMedium === 'paid_social' || str_contains($utmSource, 'ads'))) {
            return [
                'type' => 'meta_ads',
                'name' => 'Instagram / Facebook Ads',
                'utm_source' => $utmSource ?: 'meta_ads',
                'utm_medium' => 'paid_social',
                'utm_campaign' => $utmCampaign
            ];
        }

        // 3. Explicit UTM Sources
        if (!empty($utmSource)) {
            if (str_contains($utmSource, 'whatsapp')) {
                return ['type' => 'whatsapp', 'name' => 'WhatsApp', 'utm_source' => $utmSource, 'utm_medium' => $utmMedium, 'utm_campaign' => $utmCampaign];
            }
            if (str_contains($utmSource, 'instagram') || str_contains($utmSource, 'ig')) {
                return ['type' => 'instagram', 'name' => 'Instagram', 'utm_source' => $utmSource, 'utm_medium' => $utmMedium, 'utm_campaign' => $utmCampaign];
            }
            if (str_contains($utmSource, 'youtube') || str_contains($utmSource, 'yt')) {
                return ['type' => 'youtube', 'name' => 'YouTube', 'utm_source' => $utmSource, 'utm_medium' => $utmMedium, 'utm_campaign' => $utmCampaign];
            }
            if (str_contains($utmSource, 'facebook') || str_contains($utmSource, 'fb')) {
                return ['type' => 'facebook', 'name' => 'Facebook', 'utm_source' => $utmSource, 'utm_medium' => $utmMedium, 'utm_campaign' => $utmCampaign];
            }
            if (str_contains($utmSource, 'google')) {
                return ['type' => 'google_search', 'name' => 'Google Search', 'utm_source' => $utmSource, 'utm_medium' => $utmMedium, 'utm_campaign' => $utmCampaign];
            }
            return ['type' => 'campaign', 'name' => ucfirst($utmSource), 'utm_source' => $utmSource, 'utm_medium' => $utmMedium, 'utm_campaign' => $utmCampaign];
        }

        // 4. Inspect HTTP Referrer URL
        if (!empty($referrer)) {
            $host = strtolower(parse_url($referrer, PHP_URL_HOST) ?? '');
            
            if (str_contains($host, 'whatsapp') || str_contains($referrer, 'com.whatsapp')) {
                return ['type' => 'whatsapp', 'name' => 'WhatsApp', 'utm_source' => 'whatsapp', 'utm_medium' => 'chat', 'utm_campaign' => ''];
            }
            if (str_contains($host, 'instagram.com')) {
                return ['type' => 'instagram', 'name' => 'Instagram', 'utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => ''];
            }
            if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
                return ['type' => 'youtube', 'name' => 'YouTube', 'utm_source' => 'youtube', 'utm_medium' => 'video', 'utm_campaign' => ''];
            }
            if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) {
                return ['type' => 'facebook', 'name' => 'Facebook', 'utm_source' => 'facebook', 'utm_medium' => 'social', 'utm_campaign' => ''];
            }
            if (str_contains($host, 'google.')) {
                return ['type' => 'google_search', 'name' => 'Google Search', 'utm_source' => 'google', 'utm_medium' => 'organic', 'utm_campaign' => ''];
            }
            if (str_contains($host, 'bing.') || str_contains($host, 'duckduckgo.')) {
                return ['type' => 'search', 'name' => 'Search Engine (' . $host . ')', 'utm_source' => 'search', 'utm_medium' => 'organic', 'utm_campaign' => ''];
            }

            // Exclude self-referrals
            $appHost = strtolower(parse_url(Setting::get('store_url', ''), PHP_URL_HOST) ?? '');
            if (!empty($appHost) && str_contains($host, $appHost)) {
                return ['type' => 'direct', 'name' => 'Direct / Internal', 'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null];
            }

            return ['type' => 'referral', 'name' => $host ?: 'Referral Website', 'utm_source' => 'referral', 'utm_medium' => 'web', 'utm_campaign' => ''];
        }

        // 5. Default Fallback
        return [
            'type' => 'direct',
            'name' => 'Direct / Unknown',
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null
        ];
    }

    /**
     * Clean and sanitize URL path for privacy (removes sensitive parameters)
     */
    public static function sanitizePath(string $rawPath): string {
        $parts = parse_url($rawPath);
        $path = $parts['path'] ?? '/';
        if (empty($path)) $path = '/';

        // Keep benign campaign params, strip all sensitive ones
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $allowed = ['utm_source', 'utm_medium', 'utm_campaign', 'ref', 'source', 'category', 'filter', 'id'];
            $cleanQuery = array_intersect_key($query, array_flip($allowed));
            if (!empty($cleanQuery)) {
                $path .= '?' . http_build_query($cleanQuery);
            }
        }
        return substr($path, 0, 255);
    }

    /**
     * Record a page hit (called asynchronously or via /api/analytics/track)
     */
    public static function recordHit(array $data): array {
        try {
            $pdo = Database::getConnection();

            $visitorId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['visitor_id'] ?? '');
            if (empty($visitorId)) {
                $visitorId = 'vid_' . bin2hex(random_bytes(16));
            }

            $rawPath = trim($data['path'] ?? '/');
            // Skip tracking admin routes or internal assets
            if (str_starts_with($rawPath, '/admin') || str_starts_with($rawPath, '/assets') || str_starts_with($rawPath, '/api/analytics')) {
                return ['success' => true, 'skipped' => true];
            }

            $cleanPath = self::sanitizePath($rawPath);
            $pageTitle = substr(strip_tags((string)($data['title'] ?? '')), 0, 250);
            $referrer = substr((string)($data['referrer'] ?? ''), 0, 500);
            $ua = (string)($data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $parsedUa = self::parseUserAgent($ua);

            $queryParams = [];
            if (!empty($data['query'])) {
                $queryParams = is_array($data['query']) ? $data['query'] : [];
            } elseif (!empty($_GET)) {
                $queryParams = $_GET;
            }

            $source = self::classifySource($referrer, $queryParams);

            // Determine approximate city/country from headers or defaults (privacy-friendly)
            $city = trim($_SERVER['HTTP_CF_IPCITY'] ?? ($_SERVER['HTTP_X_FORWARDED_CITY'] ?? 'Jaora'));
            $region = trim($_SERVER['HTTP_CF_IPREGION'] ?? 'Madhya Pradesh');
            $country = trim($_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'India');

            // 1. Upsert Visitor Record
            $stmtVis = $pdo->prepare("
                INSERT INTO `analytics_visitors` 
                    (`visitor_id`, `first_seen_at`, `last_seen_at`, `total_visits`, `device_type`, `browser`, `os`, `city`, `region`, `country`)
                VALUES 
                    (?, NOW(), NOW(), 1, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    `last_seen_at` = NOW(),
                    `total_visits` = `total_visits` + 1,
                    `device_type` = VALUES(`device_type`),
                    `browser` = VALUES(`browser`),
                    `os` = VALUES(`os`)
            ");
            $stmtVis->execute([
                $visitorId,
                $parsedUa['device_type'],
                $parsedUa['browser'],
                $parsedUa['os'],
                $city ?: 'Jaora',
                $region ?: 'Madhya Pradesh',
                $country ?: 'India'
            ]);

            // Check if returning visitor
            $stmtRet = $pdo->prepare("SELECT `total_visits` FROM `analytics_visitors` WHERE `visitor_id` = ?");
            $stmtRet->execute([$visitorId]);
            $totalVisits = (int)$stmtRet->fetchColumn();
            $isReturning = $totalVisits > 1 ? 1 : 0;

            // 2. Active Session Management (30-min window)
            $sessionToken = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['session_token'] ?? '');
            $session = null;
            if (!empty($sessionToken)) {
                $stmtSess = $pdo->prepare("
                    SELECT * FROM `analytics_sessions` 
                    WHERE `session_token` = ? AND `visitor_id` = ? 
                      AND `last_active_at` >= DATE_SUB(NOW(), INTERVAL " . self::SESSION_TIMEOUT_MINUTES . " MINUTE)
                    LIMIT 1
                ");
                $stmtSess->execute([$sessionToken, $visitorId]);
                $session = $stmtSess->fetch();
            }

            if ($session) {
                // Update active session
                $stmtUp = $pdo->prepare("
                    UPDATE `analytics_sessions`
                    SET `last_active_at` = NOW(),
                        `current_path` = ?,
                        `page_views_count` = `page_views_count` + 1,
                        `is_active` = 1
                    WHERE `id` = ?
                ");
                $stmtUp->execute([$cleanPath, (int)$session['id']]);
            } else {
                // Create new session
                $sessionToken = 'sid_' . bin2hex(random_bytes(16));
                $stmtIns = $pdo->prepare("
                    INSERT INTO `analytics_sessions`
                        (`session_token`, `visitor_id`, `source_type`, `source_name`, `utm_source`, `utm_medium`, `utm_campaign`, `referrer_url`, `entry_path`, `current_path`, `started_at`, `last_active_at`, `page_views_count`, `is_active`, `is_returning`)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1, 1, ?)
                ");
                $stmtIns->execute([
                    $sessionToken,
                    $visitorId,
                    $source['type'],
                    $source['name'],
                    $source['utm_source'] ?? null,
                    $source['utm_medium'] ?? null,
                    $source['utm_campaign'] ?? null,
                    $referrer ?: null,
                    $cleanPath,
                    $cleanPath,
                    $isReturning
                ]);
            }

            // 3. Record Page View
            $stmtPv = $pdo->prepare("
                INSERT INTO `analytics_page_views`
                    (`session_token`, `visitor_id`, `url_path`, `page_title`, `viewed_at`)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmtPv->execute([$sessionToken, $visitorId, $cleanPath, $pageTitle]);

            return [
                'success' => true,
                'visitor_id' => $visitorId,
                'session_token' => $sessionToken,
                'is_returning' => (bool)$isReturning
            ];
        } catch (Throwable $e) {
            error_log("Analytics recordHit error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Record heartbeat to keep live visitor status active
     */
    public static function recordHeartbeat(string $sessionToken, string $currentPath = '/'): bool {
        try {
            $pdo = Database::getConnection();
            $cleanPath = self::sanitizePath($currentPath);
            $stmt = $pdo->prepare("
                UPDATE `analytics_sessions`
                SET `last_active_at` = NOW(),
                    `current_path` = ?,
                    `is_active` = 1
                WHERE `session_token` = ?
            ");
            $stmt->execute([$cleanPath, $sessionToken]);
            if ($stmt->rowCount() > 0) {
                return true;
            }
            $check = $pdo->prepare("SELECT 1 FROM `analytics_sessions` WHERE `session_token` = ? LIMIT 1");
            $check->execute([$sessionToken]);
            return (bool)$check->fetchColumn();
        } catch (Throwable $e) {
            error_log("Analytics recordHeartbeat error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Real-Time Live Active Visitors Count (active within last 5 minutes)
     */
    public static function getActiveVisitorCount(int $windowMinutes = self::ACTIVE_WINDOW_MINUTES): int {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT `visitor_id`) 
                FROM `analytics_sessions` 
                WHERE `last_active_at` >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ");
            $stmt->execute([$windowMinutes]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Get List of Active Visitors Currently Browsing the Website
     */
    public static function getActiveVisitors(int $limit = 20, int $windowMinutes = self::ACTIVE_WINDOW_MINUTES): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT 
                    s.`session_token`,
                    s.`visitor_id`,
                    s.`current_path`,
                    s.`source_name`,
                    s.`started_at`,
                    s.`last_active_at`,
                    s.`page_views_count`,
                    v.`device_type`,
                    v.`browser`,
                    v.`os`,
                    v.`city`,
                    v.`country`,
                    TIMESTAMPDIFF(SECOND, s.`last_active_at`, NOW()) AS `seconds_ago`
                FROM `analytics_sessions` s
                LEFT JOIN `analytics_visitors` v ON s.`visitor_id` = v.`visitor_id`
                WHERE s.`last_active_at` >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY s.`last_active_at` DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $windowMinutes, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Resolve Date Range from preset or custom values
     */
    public static function resolveDateRange(string $preset = 'today', ?string $customFrom = null, ?string $customTo = null): array {
        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $todayStr = $now->format('Y-m-d');

        switch ($preset) {
            case 'yesterday':
                $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
                return [
                    'preset' => 'yesterday',
                    'from' => $yesterday . ' 00:00:00',
                    'to' => $yesterday . ' 23:59:59',
                    'label' => 'Yesterday (' . date('d M', strtotime($yesterday)) . ')'
                ];

            case '7d':
            case 'last_7_days':
                $from = (clone $now)->modify('-6 days')->format('Y-m-d');
                return [
                    'preset' => '7d',
                    'from' => $from . ' 00:00:00',
                    'to' => $todayStr . ' 23:59:59',
                    'label' => 'Last 7 Days'
                ];

            case '30d':
            case 'last_30_days':
                $from = (clone $now)->modify('-29 days')->format('Y-m-d');
                return [
                    'preset' => '30d',
                    'from' => $from . ' 00:00:00',
                    'to' => $todayStr . ' 23:59:59',
                    'label' => 'Last 30 Days'
                ];

            case 'custom':
                $f = !empty($customFrom) ? date('Y-m-d', strtotime($customFrom)) : (clone $now)->modify('-7 days')->format('Y-m-d');
                $t = !empty($customTo) ? date('Y-m-d', strtotime($customTo)) : $todayStr;
                return [
                    'preset' => 'custom',
                    'from' => $f . ' 00:00:00',
                    'to' => $t . ' 23:59:59',
                    'label' => date('d M Y', strtotime($f)) . ' - ' . date('d M Y', strtotime($t)),
                    'custom_from' => $f,
                    'custom_to' => $t
                ];

            case 'today':
            default:
                return [
                    'preset' => 'today',
                    'from' => $todayStr . ' 00:00:00',
                    'to' => $todayStr . ' 23:59:59',
                    'label' => 'Today (' . date('d M Y') . ')'
                ];
        }
    }

    /**
     * Get Aggregated Dashboard Analytics Metrics for Date Range
     */
    public static function getDashboardMetrics(array $range): array {
        $pdo = Database::getConnection();
        $from = $range['from'];
        $to = $range['to'];

        // 1. Total Visits (Sessions)
        $stmtSess = $pdo->prepare("
            SELECT COUNT(*) FROM `analytics_sessions` 
            WHERE `started_at` BETWEEN ? AND ?
        ");
        $stmtSess->execute([$from, $to]);
        $totalVisits = (int)$stmtSess->fetchColumn();

        // 2. Unique Visitors
        $stmtUniq = $pdo->prepare("
            SELECT COUNT(DISTINCT `visitor_id`) FROM `analytics_sessions` 
            WHERE `started_at` BETWEEN ? AND ?
        ");
        $stmtUniq->execute([$from, $to]);
        $uniqueVisitors = (int)$stmtUniq->fetchColumn();

        // 3. Total Page Views
        $stmtPv = $pdo->prepare("
            SELECT COUNT(*) FROM `analytics_page_views` 
            WHERE `viewed_at` BETWEEN ? AND ?
        ");
        $stmtPv->execute([$from, $to]);
        $totalPageViews = (int)$stmtPv->fetchColumn();

        // 4. New vs Returning Visitors
        $stmtNew = $pdo->prepare("
            SELECT COUNT(DISTINCT `visitor_id`) FROM `analytics_visitors`
            WHERE `first_seen_at` BETWEEN ? AND ?
        ");
        $stmtNew->execute([$from, $to]);
        $newVisitors = (int)$stmtNew->fetchColumn();
        $returningVisitors = max(0, $uniqueVisitors - $newVisitors);

        // 5. Daily Trend Graph Data
        $stmtTrend = $pdo->prepare("
            SELECT 
                DATE(`viewed_at`) as `date`,
                COUNT(*) as `page_views`,
                COUNT(DISTINCT `visitor_id`) as `unique_visitors`
            FROM `analytics_page_views`
            WHERE `viewed_at` BETWEEN ? AND ?
            GROUP BY DATE(`viewed_at`)
            ORDER BY `date` ASC
        ");
        $stmtTrend->execute([$from, $to]);
        $trendRows = $stmtTrend->fetchAll();

        // 6. Traffic Sources Breakdown
        $stmtSources = $pdo->prepare("
            SELECT 
                `source_name`,
                `source_type`,
                COUNT(*) as `visits_count`,
                COUNT(DISTINCT `visitor_id`) as `unique_count`
            FROM `analytics_sessions`
            WHERE `started_at` BETWEEN ? AND ?
            GROUP BY `source_name`, `source_type`
            ORDER BY `visits_count` DESC
            LIMIT 10
        ");
        $stmtSources->execute([$from, $to]);
        $sources = $stmtSources->fetchAll();

        // 7. Top Visited Pages
        $stmtPages = $pdo->prepare("
            SELECT 
                `url_path`,
                MAX(`page_title`) as `sample_title`,
                COUNT(*) as `views_count`,
                COUNT(DISTINCT `visitor_id`) as `unique_visitors`
            FROM `analytics_page_views`
            WHERE `viewed_at` BETWEEN ? AND ?
            GROUP BY `url_path`
            ORDER BY `views_count` DESC
            LIMIT 15
        ");
        $stmtPages->execute([$from, $to]);
        $topPages = $stmtPages->fetchAll();

        // 8. Device Breakdown
        $stmtDevice = $pdo->prepare("
            SELECT 
                v.`device_type`,
                COUNT(DISTINCT s.`session_token`) as `sessions_count`
            FROM `analytics_sessions` s
            JOIN `analytics_visitors` v ON s.`visitor_id` = v.`visitor_id`
            WHERE s.`started_at` BETWEEN ? AND ?
            GROUP BY v.`device_type`
        ");
        $stmtDevice->execute([$from, $to]);
        $deviceBreakdown = $stmtDevice->fetchAll();

        // 9. Location Summary (Visitor approximate cities)
        $stmtLoc = $pdo->prepare("
            SELECT 
                v.`city`,
                v.`region`,
                v.`country`,
                COUNT(DISTINCT s.`visitor_id`) as `visitors_count`
            FROM `analytics_sessions` s
            JOIN `analytics_visitors` v ON s.`visitor_id` = v.`visitor_id`
            WHERE s.`started_at` BETWEEN ? AND ?
            GROUP BY v.`city`, v.`region`, v.`country`
            ORDER BY `visitors_count` DESC
            LIMIT 10
        ");
        $stmtLoc->execute([$from, $to]);
        $visitorLocations = $stmtLoc->fetchAll();

        // 10. Actual Customer Order Delivery Areas (Real conversions)
        $stmtOrders = $pdo->prepare("
            SELECT 
                COALESCE(NULLIF(`delivery_area_name`, ''), 'Standard Jaora') as `area_name`,
                COUNT(*) as `order_count`,
                SUM(`total_amount`) as `revenue`
            FROM `orders`
            WHERE `created_at` BETWEEN ? AND ?
            GROUP BY `area_name`
            ORDER BY `order_count` DESC
            LIMIT 10
        ");
        $stmtOrders->execute([$from, $to]);
        $orderAreas = $stmtOrders->fetchAll();

        // 11. Marketing Campaigns breakdown (UTMs)
        $stmtCamp = $pdo->prepare("
            SELECT 
                `utm_campaign`,
                `utm_source`,
                `utm_medium`,
                COUNT(*) as `sessions_count`,
                COUNT(DISTINCT `visitor_id`) as `unique_visitors`
            FROM `analytics_sessions`
            WHERE `started_at` BETWEEN ? AND ? 
              AND `utm_campaign` IS NOT NULL 
              AND `utm_campaign` != ''
            GROUP BY `utm_campaign`, `utm_source`, `utm_medium`
            ORDER BY `sessions_count` DESC
            LIMIT 10
        ");
        $stmtCamp->execute([$from, $to]);
        $campaigns = $stmtCamp->fetchAll();

        return [
            'total_visits' => $totalVisits,
            'unique_visitors' => $uniqueVisitors,
            'total_page_views' => $totalPageViews,
            'new_visitors' => $newVisitors,
            'returning_visitors' => $returningVisitors,
            'pages_per_visit' => $totalVisits > 0 ? round($totalPageViews / $totalVisits, 1) : 0,
            'trend' => $trendRows,
            'sources' => $sources,
            'top_pages' => $topPages,
            'devices' => $deviceBreakdown,
            'locations' => $visitorLocations,
            'order_areas' => $orderAreas,
            'campaigns' => $campaigns
        ];
    }
}
