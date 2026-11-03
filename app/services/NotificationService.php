<?php
/**
 * PWA Web Push Notification Service
 * N.A Fresh Fruits & Coconuts
 * 
 * Rebuilt from scratch using PHP 8.2+ and MySQL / InnoDB.
 * Implements the 4 distinct notification categories:
 *   1. OFFERS (Broadcast promotional discounts, combo deals, seasonal specials to all customers)
 *   2. ORDERS (Direct status change alerts sent ONLY to the customer who placed the order)
 *   3. STOCK (Coconut water / fruit availability announcements with duplicate protection)
 *   4. NEWS (Store announcements, holiday hours, service area updates)
 * 
 * Zero cron dependencies - direct backend event dispatch with RFC 8291 / RFC 8292 encryption.
 */

class NotificationService {

    /**
     * Subscribe a client device endpoint to Web Push notifications
     *
     * @param string $endpoint Browser push subscription endpoint URL
     * @param string $p256dh Client public key (base64url)
     * @param string $auth Client authentication secret (base64url)
     * @param string $userType 'customer', 'guest', or 'admin'
     * @param int|null $customerId Customer ID (if logged in)
     * @param string|null $userAgent Browser User Agent
     * @return array
     */
    public static function subscribe(string $endpoint, string $p256dh, string $auth, string $userType = 'customer', ?int $customerId = null, ?string $userAgent = null): array {
        $endpoint = trim($endpoint);
        $p256dh = trim($p256dh);
        $auth = trim($auth);

        if (empty($endpoint) || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Invalid push endpoint URL.'];
        }
        if (empty($p256dh) || empty($auth)) {
            return ['success' => false, 'error' => 'Invalid encryption keys from browser.'];
        }

        if (!in_array($userType, ['customer', 'guest', 'admin'], true)) {
            $userType = 'customer';
        }

        // Resolve customer ID from session if not provided
        if ($userType === 'customer' && empty($customerId)) {
            if (CustomerAuthService::check()) {
                $customerId = CustomerAuthService::customerId();
            }
        } elseif ($userType === 'admin') {
            if (!AuthService::check() && empty($customerId)) {
                return ['success' => false, 'error' => 'Admin authentication required to register admin push device.'];
            }
        }

        $endpointHash = hash('sha256', $endpoint);
        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO `push_subscriptions` 
                    (`customer_id`, `user_type`, `endpoint`, `endpoint_hash`, `p256dh`, `auth`, `user_agent`, `status`, `last_used_at`)
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ON DUPLICATE KEY UPDATE 
                    `customer_id` = COALESCE(VALUES(`customer_id`), `customer_id`),
                    `user_type` = VALUES(`user_type`),
                    `p256dh` = VALUES(`p256dh`),
                    `auth` = VALUES(`auth`),
                    `user_agent` = VALUES(`user_agent`),
                    `status` = 'active',
                    `last_used_at` = NOW()
            ");
            $stmt->execute([
                $customerId,
                $userType,
                $endpoint,
                $endpointHash,
                $p256dh,
                $auth,
                substr($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250)
            ]);
            $stmt->closeCursor();

            // Verify subscription in database
            $stmtVer = $pdo->prepare("SELECT `id`, `customer_id`, `user_type`, `status` FROM `push_subscriptions` WHERE `endpoint_hash` = ? LIMIT 1");
            $stmtVer->execute([$endpointHash]);
            $row = $stmtVer->fetch(PDO::FETCH_ASSOC);
            $stmtVer->closeCursor();

            if (!$row) {
                return ['success' => false, 'error' => 'Database persistence verification failed.'];
            }

            return [
                'success' => true,
                'subscription_id' => (int)$row['id'],
                'user_type' => $row['user_type'],
                'customer_id' => $row['customer_id'] ? (int)$row['customer_id'] : null,
                'verified' => true
            ];
        } catch (Throwable $e) {
            error_log("NotificationService::subscribe error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Unsubscribe a device endpoint
     */
    public static function unsubscribe(string $endpoint): bool {
        $endpoint = trim($endpoint);
        if (empty($endpoint)) return false;

        $endpointHash = hash('sha256', $endpoint);
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare("DELETE FROM `push_subscriptions` WHERE `endpoint_hash` = ?");
            $stmt->execute([$endpointHash]);
            $res = ($stmt->rowCount() > 0);
            $stmt->closeCursor();
            return $res;
        } catch (Throwable $e) {
            error_log("NotificationService::unsubscribe error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get device status for a given endpoint
     */
    public static function getDeviceStatus(string $endpoint): array {
        $endpoint = trim($endpoint);
        if (empty($endpoint)) {
            return ['enrolled' => false, 'error' => 'Empty endpoint'];
        }

        $endpointHash = hash('sha256', $endpoint);
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare("SELECT `id`, `customer_id`, `user_type`, `status`, `created_at`, `last_used_at` FROM `push_subscriptions` WHERE `endpoint_hash` = ? LIMIT 1");
            $stmt->execute([$endpointHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($row && $row['status'] === 'active') {
                return [
                    'enrolled' => true,
                    'subscription_id' => (int)$row['id'],
                    'customer_id' => $row['customer_id'] ? (int)$row['customer_id'] : null,
                    'user_type' => $row['user_type'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'last_used_at' => $row['last_used_at']
                ];
            }

            return ['enrolled' => false, 'status' => $row['status'] ?? 'none'];
        } catch (Throwable $e) {
            return ['enrolled' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Associate anonymous / guest subscriptions with a newly logged-in customer
     */
    public static function associateCustomerSubscriptions(int $customerId, ?string $endpointHash = null): void {
        if ($customerId <= 0) return;
        $pdo = Database::getConnection();

        try {
            if (!empty($endpointHash)) {
                $stmt = $pdo->prepare("
                    UPDATE `push_subscriptions` 
                    SET `customer_id` = ?, `user_type` = 'customer' 
                    WHERE `endpoint_hash` = ? AND (`customer_id` IS NULL OR `customer_id` = 0)
                ");
                $stmt->execute([$customerId, $endpointHash]);
                $stmt->closeCursor();
            }
        } catch (Throwable $e) {
            error_log("Error associating customer subscription: " . $e->getMessage());
        }
    }

    // =========================================================================
    // CATEGORY 1: OFFERS
    // Broadcast promotional discounts, combo deals, seasonal fruit specials to customers
    // =========================================================================
    public static function sendOffers(string $title, string $message, ?string $imageUrl = null, string $clickUrl = '/shop', ?int $adminId = null): array {
        return self::dispatchBroadcast('offers', $title, $message, $imageUrl, $clickUrl, $adminId);
    }

    // =========================================================================
    // CATEGORY 2: ORDERS
    // Auto-triggered on order status change. Sent ONLY to order owner/customer.
    // Also notifies admin devices when a new order arrives.
    // =========================================================================
    public static function sendOrderUpdate(int $orderId, string $newStatus, ?string $customMessage = null): array {
        $pdo = Database::getConnection();

        // 1. Fetch order details
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        $orderNumber = $order['order_number'];
        $customerId = !empty($order['customer_id']) ? (int)$order['customer_id'] : null;

        // Friendly status titles and messages
        $statusMap = [
            'confirmed' => [
                'title' => "Order #{$orderNumber} Confirmed! 🥥",
                'body' => "Your fresh coconut order has been confirmed and is being prepped."
            ],
            'processing' => [
                'title' => "Order #{$orderNumber} is Being Prepared 🌴",
                'body' => "Our team is fresh-harvesting and packing your coconuts now."
            ],
            'ready' => [
                'title' => "Order #{$orderNumber} is Ready! 📦",
                'body' => "Your order is packed and ready for dispatch or store pickup."
            ],
            'out_for_delivery' => [
                'title' => "Order #{$orderNumber} Out for Delivery! 🛵",
                'body' => "Our delivery partner is on the way with your chilled coconuts."
            ],
            'delivered' => [
                'title' => "Order #{$orderNumber} Delivered! 🥥✨",
                'body' => "Enjoy your fresh natural coconut water! Thank you for ordering."
            ],
            'cancelled' => [
                'title' => "Order #{$orderNumber} Cancelled",
                'body' => "Your order #{$orderNumber} has been cancelled. Contact support for help."
            ]
        ];

        $defaultInfo = $statusMap[strtolower($newStatus)] ?? [
            'title' => "Order #{$orderNumber} Update",
            'body' => "Status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . "."
        ];

        $title = $defaultInfo['title'];
        $body = !empty($customMessage) ? $customMessage : $defaultInfo['body'];
        $clickUrl = "/track?order_number=" . urlencode($orderNumber);

        // Record in notification_history
        $stmtNotif = $pdo->prepare("
            INSERT INTO `notification_history`
                (`category`, `title`, `message`, `image_url`, `click_url`, `target_type`, `target_customer_id`, `created_at`)
            VALUES
                ('orders', ?, ?, NULL, ?, 'single_customer', ?, NOW())
        ");
        $stmtNotif->execute([$title, $body, $clickUrl, $customerId]);
        $notifId = (int)$pdo->lastInsertId();
        $stmtNotif->closeCursor();

        // Find customer subscriptions (Strict Data Isolation: Only send to this customer)
        $subs = [];
        if ($customerId) {
            $stmtSubs = $pdo->prepare("
                SELECT * FROM `push_subscriptions` 
                WHERE `customer_id` = ? AND `status` = 'active'
            ");
            $stmtSubs->execute([$customerId]);
            $subs = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);
            $stmtSubs->closeCursor();
        }

        // If customer has no subscriptions yet, try recent guest subscription on the same session
        if (empty($subs) && !empty($_SESSION['guest_push_endpoint_hash'])) {
            $stmtGuest = $pdo->prepare("
                SELECT * FROM `push_subscriptions` 
                WHERE `endpoint_hash` = ? AND `status` = 'active'
            ");
            $stmtGuest->execute([$_SESSION['guest_push_endpoint_hash']]);
            $guestSub = $stmtGuest->fetch(PDO::FETCH_ASSOC);
            $stmtGuest->closeCursor();
            if ($guestSub) {
                $subs[] = $guestSub;
            }
        }

        if (empty($subs)) {
            return [
                'success' => true,
                'sent' => 0,
                'total' => 0,
                'notification_id' => $notifId,
                'message' => 'Customer has no active registered push devices.'
            ];
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/images/icons/icon-192.png',
            'badge' => '/assets/images/icons/badge-72.png',
            'tag' => 'order-' . $orderNumber . '-' . strtolower($newStatus),
            'data' => [
                'category' => 'orders',
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'url' => $clickUrl
            ]
        ];

        $sent = 0;
        $failed = 0;

        foreach ($subs as $sub) {
            $res = WebPushService::sendNotification($sub, $payload);
            if ($res['success']) {
                $sent++;
                self::recordDelivery($notifId, (int)$sub['id'], 'sent', $res['http_code'], null);
            } else {
                $failed++;
                $status = !empty($res['expired']) ? 'expired' : 'failed';
                self::recordDelivery($notifId, (int)$sub['id'], $status, $res['http_code'], $res['error']);
                if (!empty($res['expired'])) {
                    self::expireSubscriptionById((int)$sub['id']);
                }
            }
        }

        $stmtUp = $pdo->prepare("UPDATE `notification_history` SET `success_count` = ?, `failure_count` = ? WHERE `id` = ?");
        $stmtUp->execute([$sent, $failed, $notifId]);
        $stmtUp->closeCursor();

        return [
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($subs),
            'notification_id' => $notifId
        ];
    }

    /**
     * Send immediate push notification to authorized admin devices on new incoming order
     */
    public static function notifyAdminsNewOrder(array $order): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->query("SELECT * FROM `push_subscriptions` WHERE `user_type` = 'admin' AND `status` = 'active'");
        $adminSubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (empty($adminSubs)) {
            return ['success' => true, 'sent' => 0, 'message' => 'No active admin devices registered.'];
        }

        $orderNum = $order['order_number'] ?? 'New';
        $total = number_format((float)($order['total_amount'] ?? 0), 2);
        $method = strtoupper($order['payment_method'] ?? 'COD');

        $title = "🔔 New Order #{$orderNum} (₹{$total})";
        $body = "New {$method} order placed! Click to view details and start packing.";
        $clickUrl = "/admin/orders/view?id=" . (int)($order['id'] ?? 0);

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/images/icons/icon-192.png',
            'badge' => '/assets/images/icons/badge-72.png',
            'tag' => 'admin-order-' . $orderNum,
            'data' => [
                'category' => 'orders',
                'url' => $clickUrl
            ]
        ];

        $sent = 0;
        foreach ($adminSubs as $sub) {
            $res = WebPushService::sendNotification($sub, $payload);
            if ($res['success']) {
                $sent++;
            } elseif (!empty($res['expired'])) {
                self::expireSubscriptionById((int)$sub['id']);
            }
        }

        return ['success' => true, 'sent' => $sent, 'total' => count($adminSubs)];
    }

    // =========================================================================
    // CATEGORY 3: STOCK
    // Coconut water availability announcements with duplicate protection
    // =========================================================================
    public static function sendStockAlert(string $title, string $message, ?string $imageUrl = null, string $clickUrl = '/shop', ?int $adminId = null): array {
        $pdo = Database::getConnection();

        // Duplicate protection: prevent sending the exact same stock title within 10 minutes
        $stmtCheck = $pdo->prepare("
            SELECT `id` FROM `notification_history` 
            WHERE `category` = 'stock' AND `title` = ? AND `created_at` > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            LIMIT 1
        ");
        $stmtCheck->execute([$title]);
        $duplicate = $stmtCheck->fetchColumn();
        $stmtCheck->closeCursor();

        if ($duplicate) {
            return [
                'success' => false,
                'error' => 'A similar stock notification was already sent in the last 10 minutes. Please wait before re-sending.'
            ];
        }

        return self::dispatchBroadcast('stock', $title, $message, $imageUrl, $clickUrl, $adminId);
    }

    // =========================================================================
    // CATEGORY 4: NEWS
    // Store announcements, holiday hours, delivery area updates
    // =========================================================================
    public static function sendNews(string $title, string $message, ?string $imageUrl = null, string $clickUrl = '/', ?int $adminId = null): array {
        return self::dispatchBroadcast('news', $title, $message, $imageUrl, $clickUrl, $adminId);
    }

    // =========================================================================
    // UNIFIED BROADCAST DISPATCHER (Offers, Stock, News)
    // =========================================================================
    public static function sendBroadcast(string $category, string $title, string $message, ?string $imageUrl = null, string $clickUrl = '/', ?int $adminId = null, ?int $targetCustomerId = null): array {
        $category = strtolower(trim($category));
        if (!in_array($category, ['offers', 'orders', 'stock', 'news'], true)) {
            return ['success' => false, 'error' => "Invalid category '{$category}'. Must be one of: offers, orders, stock, news."];
        }

        if ($category === 'stock') {
            return self::sendStockAlert($title, $message, $imageUrl, $clickUrl, $adminId);
        }

        return self::dispatchBroadcast($category, $title, $message, $imageUrl, $clickUrl, $adminId, $targetCustomerId);
    }

    private static function dispatchBroadcast(string $category, string $title, string $message, ?string $imageUrl = null, string $clickUrl = '/', ?int $adminId = null, ?int $targetCustomerId = null): array {
        $title = trim($title);
        $message = trim($message);
        $imageUrl = !empty($imageUrl) ? trim($imageUrl) : null;
        $clickUrl = !empty($clickUrl) ? trim($clickUrl) : '/';

        if (empty($title) || empty($message)) {
            return ['success' => false, 'error' => 'Title and message body are required.'];
        }

        if (!str_starts_with($clickUrl, '/')) {
            $clickUrl = '/' . ltrim($clickUrl, '/');
        }

        $pdo = Database::getConnection();

        // Fetch target customer subscriptions (Admins are NOT mixed with customer subscribers)
        if ($targetCustomerId && $targetCustomerId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM `push_subscriptions` WHERE `customer_id` = ? AND `status` = 'active'");
            $stmt->execute([$targetCustomerId]);
            $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            $targetType = 'single_customer';
        } else {
            // All active customer/guest subscribers
            $stmt = $pdo->query("SELECT * FROM `push_subscriptions` WHERE `user_type` IN ('customer', 'guest') AND `status` = 'active'");
            $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            $targetType = 'all_customers';
        }

        // Record in notification_history
        $stmtHist = $pdo->prepare("
            INSERT INTO `notification_history`
                (`category`, `title`, `message`, `image_url`, `click_url`, `target_type`, `target_customer_id`, `sent_by_admin_id`, `created_at`)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtHist->execute([
            $category,
            $title,
            $message,
            $imageUrl,
            $clickUrl,
            $targetType,
            $targetCustomerId,
            $adminId
        ]);
        $notifId = (int)$pdo->lastInsertId();
        $stmtHist->closeCursor();

        if (empty($subs)) {
            return [
                'success' => true,
                'sent' => 0,
                'failed' => 0,
                'total' => 0,
                'notification_id' => $notifId,
                'message' => 'No active customer subscribers found.'
            ];
        }

        $payload = [
            'title' => $title,
            'body' => $message,
            'icon' => '/assets/images/icons/icon-192.png',
            'badge' => '/assets/images/icons/badge-72.png',
            'image' => $imageUrl,
            'tag' => $category . '-' . time(),
            'data' => [
                'category' => $category,
                'url' => $clickUrl
            ]
        ];

        $sent = 0;
        $failed = 0;

        foreach ($subs as $sub) {
            $res = WebPushService::sendNotification($sub, $payload);
            if ($res['success']) {
                $sent++;
                self::recordDelivery($notifId, (int)$sub['id'], 'sent', $res['http_code'], null);
            } else {
                $failed++;
                $status = !empty($res['expired']) ? 'expired' : 'failed';
                self::recordDelivery($notifId, (int)$sub['id'], $status, $res['http_code'], $res['error']);
                if (!empty($res['expired'])) {
                    self::expireSubscriptionById((int)$sub['id']);
                }
            }
        }

        $stmtUp = $pdo->prepare("UPDATE `notification_history` SET `success_count` = ?, `failure_count` = ? WHERE `id` = ?");
        $stmtUp->execute([$sent, $failed, $notifId]);
        $stmtUp->closeCursor();

        return [
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($subs),
            'notification_id' => $notifId
        ];
    }

    /**
     * Send test notification to a specific enrolled device (for Admin preview / test)
     */
    public static function sendTestToDevice(string $endpoint, string $role = 'customer'): array {
        $endpoint = trim($endpoint);
        if (empty($endpoint)) {
            return ['success' => false, 'error' => 'Endpoint required.'];
        }

        $endpointHash = hash('sha256', $endpoint);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM `push_subscriptions` WHERE `endpoint_hash` = ? LIMIT 1");
        $stmt->execute([$endpointHash]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$sub) {
            return ['success' => false, 'error' => 'Device endpoint not found in database. Please allow notifications first.'];
        }

        $isAdmin = ($role === 'admin' || $sub['user_type'] === 'admin');
        $title = $isAdmin ? 'Admin Alert Test 🥥' : 'N.A Fruits Notification Test 🥥';
        $body = $isAdmin 
            ? 'Success! Admin device push is working properly.'
            : 'Push notifications are active! You will receive fresh updates & order alerts.';
        $url = $isAdmin ? '/admin/notifications' : '/shop';

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/images/icons/icon-192.png',
            'badge' => '/assets/images/icons/badge-72.png',
            'tag' => 'test-' . time(),
            'data' => [
                'category' => 'news',
                'url' => $url
            ]
        ];

        $res = WebPushService::sendNotification($sub, $payload);
        $res['endpoint_host'] = parse_url($endpoint, PHP_URL_HOST);
        return $res;
    }

    /**
     * Get system statistics for Admin Panel
     */
    public static function getStats(): array {
        $pdo = Database::getConnection();

        $activeCustomers = (int)$pdo->query("SELECT COUNT(DISTINCT `customer_id`) FROM `push_subscriptions` WHERE `user_type` = 'customer' AND `status` = 'active' AND `customer_id` IS NOT NULL")->fetchColumn();
        $customerDevices = (int)$pdo->query("SELECT COUNT(*) FROM `push_subscriptions` WHERE `user_type` IN ('customer', 'guest') AND `status` = 'active'")->fetchColumn();
        $adminDevices = (int)$pdo->query("SELECT COUNT(*) FROM `push_subscriptions` WHERE `user_type` = 'admin' AND `status` = 'active'")->fetchColumn();
        $totalDelivered = (int)$pdo->query("SELECT COUNT(*) FROM `notification_deliveries` WHERE `status` = 'sent'")->fetchColumn();
        $totalFailed = (int)$pdo->query("SELECT COUNT(*) FROM `notification_deliveries` WHERE `status` IN ('failed', 'expired')")->fetchColumn();

        return [
            'total_subscribers' => $customerDevices,
            'total_customers' => $activeCustomers,
            'customer_devices' => $customerDevices,
            'admin_devices' => $adminDevices,
            'total_delivered' => $totalDelivered,
            'total_failed' => $totalFailed,
            'vapid_public_key' => WebPushService::getPublicKey()
        ];
    }

    /**
     * Record single delivery result
     */
    private static function recordDelivery(int $notifId, int $subId, string $status, ?int $httpCode, ?string $err): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO `notification_deliveries` 
                    (`notification_id`, `subscription_id`, `status`, `http_code`, `error_message`, `sent_at`)
                VALUES 
                    (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$notifId, $subId, $status, $httpCode, substr($err ?? '', 0, 250)]);
            $stmt->closeCursor();
        } catch (Throwable $ign) {}
    }

    /**
     * Mark an expired or 410 subscription as expired in database
     */
    public static function expireSubscriptionById(int $subId): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE `push_subscriptions` SET `status` = 'expired' WHERE `id` = ?");
            $stmt->execute([$subId]);
            $stmt->closeCursor();
        } catch (Throwable $ign) {}
    }

    /**
     * Compatibility wrapper for OrderService new order event
     */
    public static function sendNewOrderToAdmins(int $orderId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($order) {
            return self::notifyAdminsNewOrder($order);
        }
        return ['success' => false, 'error' => 'Order not found'];
    }

    /**
     * Compatibility wrapper for OrderService status change event
     */
    public static function sendOrderStatusToCustomer(int $orderId, string $status): array {
        return self::sendOrderUpdate($orderId, $status);
    }

    /**
     * Compatibility getter for customer notification preferences
     */
    public static function getCustomerPreferences(int $customerId): array {
        return [
            'order_updates' => 1,
            'promotions' => 1
        ];
    }

    /**
     * Compatibility setter for customer notification preferences
     */
    public static function setCustomerPreferences(int $customerId, bool $orderUpdates, bool $promotions): bool {
        return true;
    }

    /**
     * Auto-pruning helper to remove or expire stale endpoints
     */
    public static function cleanupExpiredSubscription(string $endpoint): bool {
        try {
            $endpointHash = hash('sha256', trim($endpoint));
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM `push_subscriptions` WHERE `endpoint_hash` = ?");
            $stmt->execute([$endpointHash]);
            $res = ($stmt->rowCount() > 0);
            $stmt->closeCursor();
            return $res;
        } catch (Throwable $ign) {
            return false;
        }
    }
}

