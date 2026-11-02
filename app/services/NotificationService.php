<?php
/**
 * PWA Web Push Notification Service
 * N.A Fresh Fruits & Coconuts
 * 
 * Manages device subscriptions, customer preferences, event deduplication,
 * admin order alerts, customer order lifecycle updates, and promotional broadcasts.
 * Zero cron dependencies - direct backend event dispatch.
 */

class NotificationService {

    /**
     * Subscribe a client device to Web Push notifications
     */
    public static function subscribe(string $endpoint, string $p256dh, string $auth, string $userType = 'customer', ?int $userId = null, ?string $userAgent = null): array {
        $endpoint = trim($endpoint);
        $p256dh = trim($p256dh);
        $auth = trim($auth);

        if (empty($endpoint) || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Invalid push endpoint URL.'];
        }
        if (empty($p256dh) || empty($auth)) {
            return ['success' => false, 'error' => 'Invalid encryption keys from browser.'];
        }

        // Validate user type
        if (!in_array($userType, ['admin', 'customer', 'guest'], true)) {
            $userType = 'customer';
        }

        // If user is admin, enforce active admin session
        if ($userType === 'admin') {
            if (!AuthService::check()) {
                return ['success' => false, 'error' => 'Unauthorized: Admin authentication required to register admin push device.'];
            }
            $userId = AuthService::adminId();
        } elseif ($userType === 'customer' && !empty($userId)) {
            // Customer device
        }

        $endpointHash = hash('sha256', $endpoint);
        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO `push_subscriptions` 
                    (`user_type`, `user_id`, `endpoint`, `endpoint_hash`, `p256dh`, `auth`, `user_agent`, `last_used_at`)
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    `user_type` = VALUES(`user_type`),
                    `user_id` = VALUES(`user_id`),
                    `p256dh` = VALUES(`p256dh`),
                    `auth` = VALUES(`auth`),
                    `user_agent` = VALUES(`user_agent`),
                    `last_used_at` = NOW()
            ");
            $stmt->execute([
                $userType,
                $userId,
                $endpoint,
                $endpointHash,
                $p256dh,
                $auth,
                substr($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250)
            ]);
            $subId = (int)$pdo->lastInsertId();
            $stmt->closeCursor();

            return ['success' => true, 'subscription_id' => $subId];
        } catch (Throwable $e) {
            error_log("Failed to save push subscription: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error while saving subscription.'];
        }
    }

    /**
     * Unsubscribe a client device
     */
    public static function unsubscribe(string $endpoint): bool {
        $endpoint = trim($endpoint);
        if (empty($endpoint)) {
            return false;
        }

        $endpointHash = hash('sha256', $endpoint);
        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare("DELETE FROM `push_subscriptions` WHERE `endpoint_hash` = ?");
            $stmt->execute([$endpointHash]);
            $res = ($stmt->rowCount() > 0);
            $stmt->closeCursor();
            return $res;
        } catch (Throwable $e) {
            error_log("Failed to unsubscribe: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send immediate push notification to authorized admin devices on new order
     *
     * @param int|array $orderOrId
     * @return array
     */
    public static function sendNewOrderToAdmins(int|array $orderOrId): array {
        if (is_array($orderOrId)) {
            $order = $orderOrId;
            $orderId = (int)($order['id'] ?? 0);
        } else {
            $orderId = (int)$orderOrId;
            $order = Order::getById($orderId);
        }

        $dedupKey = "new_order_admin_{$orderId}";
        if (self::isDuplicateEvent($dedupKey)) {
            return ['success' => true, 'duplicate' => true, 'skipped' => true, 'reason' => 'Already notified admins for order ' . $orderId];
        }

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found: ' . $orderId];
        }

        $amountFormatted = number_format((float)$order['total_amount'], 2, '.', '');
        $customerName = trim($order['customer_name'] ?? 'Customer');
        $orderNumber = $order['order_number'] ?? ('#' . $orderId);
        $paymentMethodLabel = ($order['payment_method'] === 'cod') ? 'Cash on Delivery' : 'Online Paid (Razorpay)';

        $title = "New Order Received! 🥥";
        $body = "Order #{$orderNumber} | Customer: {$customerName} | Amount: ₹{$amountFormatted} ({$paymentMethodLabel})";
        $clickUrl = "/admin/orders/view?id={$orderId}";

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/images/icons/icon-192.png',
            'badge' => '/assets/images/icons/badge-72.png',
            'image' => '/assets/images/product-main.png',
            'tag' => 'admin-order-' . $orderId,
            'data' => [
                'type' => 'new_order',
                'order_id' => $orderId,
                'url' => $clickUrl,
                'timestamp' => time()
            ]
        ];

        // Retrieve all admin subscriptions
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM `push_subscriptions` WHERE `user_type` = 'admin'");
        $adminSubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (empty($adminSubs)) {
            self::recordDeduplication($dedupKey, 'new_order_admin', (string)$orderId);
            return ['success' => true, 'recipients' => 0, 'message' => 'No admin devices currently subscribed.'];
        }

        // Create notification record
        $stmtNotif = $pdo->prepare("
            INSERT INTO `notifications` 
                (`audience`, `target_customer_id`, `notification_type`, `title`, `body`, `icon`, `image`, `click_url`, `total_recipients`)
            VALUES 
                ('admins_only', NULL, 'order_new', ?, ?, ?, ?, ?, ?)
        ");
        $stmtNotif->execute([
            $title,
            $body,
            $payload['icon'],
            $payload['image'],
            $clickUrl,
            count($adminSubs)
        ]);
        $notifId = (int)$pdo->lastInsertId();
        $stmtNotif->closeCursor();

        $successCount = 0;
        $failCount = 0;

        foreach ($adminSubs as $sub) {
            $res = WebPushService::sendNotification($sub, $payload);
            if ($res['success']) {
                $successCount++;
                self::recordDelivery($notifId, (int)$sub['id'], 'sent', $res['http_code'], null);
            } else {
                $failCount++;
                self::recordDelivery($notifId, (int)$sub['id'], 'failed', $res['http_code'], $res['error']);
                // Automatically clean up expired / revoked subscriptions
                if (!empty($res['expired'])) {
                    self::removeSubscriptionById((int)$sub['id']);
                }
            }
        }

        // Update notification stats
        $stmtUp = $pdo->prepare("UPDATE `notifications` SET `success_count` = ?, `failure_count` = ? WHERE `id` = ?");
        $stmtUp->execute([$successCount, $failCount, $notifId]);
        $stmtUp->closeCursor();

        self::recordDeduplication($dedupKey, 'new_order_admin', (string)$orderId);

        return [
            'success' => true,
            'total' => count($adminSubs),
            'sent' => $successCount,
            'failed' => $failCount,
            'notification_id' => $notifId
        ];
    }

    /**
     * Send order status update notification to the customer who placed the order
     *
     * @param int $orderId
     * @param string $eventType e.g. 'order_confirmed', 'being_prepared', 'order_ready', 'out_for_delivery', 'order_delivered', 'order_cancelled', 'payment_confirmed'
     * @return array
     */
    public static function sendOrderStatusToCustomer(int $orderId, string $eventType): array {
        $dedupKey = "order_status_{$eventType}_{$orderId}";
        if (self::isDuplicateEvent($dedupKey)) {
            return ['success' => true, 'skipped' => true, 'reason' => 'Already notified customer for event ' . $eventType];
        }

        $order = Order::getById($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found: ' . $orderId];
        }

        $customerId = !empty($order['customer_id']) ? (int)$order['customer_id'] : null;
        $orderNumber = $order['order_number'] ?? ('#' . $orderId);

        // Check customer notification preferences
        if ($customerId !== null && !self::getCustomerPreference($customerId, 'order_updates')) {
            return ['success' => true, 'skipped' => true, 'reason' => 'Customer opted out of order notifications.'];
        }

        $statusTitles = [
            'order_confirmed' => "Order Confirmed! 🥥",
            'being_prepared' => "Your Order is Being Prepared 🍃",
            'order_ready' => "Order Ready for Pickup / Dispatch ✨",
            'out_for_delivery' => "Out for Delivery 🛵",
            'order_delivered' => "Order Delivered! Enjoy Pure Hydration 🌴",
            'order_cancelled' => "Order Cancelled Notice",
            'payment_confirmed' => "Payment Verified & Confirmed 💳"
        ];

        $statusBodies = [
            'order_confirmed' => "Order #{$orderNumber} is confirmed! Our farm team is preparing your fresh tender coconut water.",
            'being_prepared' => "Order #{$orderNumber} is cleanly sliced, bottled, and packed for you.",
            'order_ready' => "Order #{$orderNumber} is packed and ready. Doorstep rider is on the way!",
            'out_for_delivery' => "Rider is heading to your doorstep with Order #{$orderNumber}. Keep your phone handy!",
            'order_delivered' => "Order #{$orderNumber} has been delivered successfully. Thank you for choosing N.A Fresh Fruits!",
            'order_cancelled' => "Order #{$orderNumber} has been cancelled. If paid online, refund is processed automatically.",
            'payment_confirmed' => "Payment of ₹" . number_format((float)$order['total_amount'], 2) . " for Order #{$orderNumber} was successfully verified."
        ];

        $title = $statusTitles[$eventType] ?? "Order Update: #{$orderNumber}";
        $body = $statusBodies[$eventType] ?? "Your order #{$orderNumber} status is now " . ucfirst(str_replace('_', ' ', $eventType));
        $clickUrl = "/orders/track?order_number=" . urlencode($orderNumber);

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/images/icons/icon-192.png',
            'badge' => '/assets/images/icons/badge-72.png',
            'image' => '/assets/images/product-main.png',
            'tag' => 'order-status-' . $orderId . '-' . $eventType,
            'data' => [
                'type' => 'order_status',
                'order_id' => $orderId,
                'event' => $eventType,
                'url' => $clickUrl,
                'timestamp' => time()
            ]
        ];

        // Look for customer subscriptions
        $pdo = Database::getConnection();
        $subs = [];

        if ($customerId !== null) {
            $stmt = $pdo->prepare("SELECT * FROM `push_subscriptions` WHERE `user_type` = 'customer' AND `user_id` = ?");
            $stmt->execute([$customerId]);
            $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
        }

        if (empty($subs)) {
            // Also check session subscription if guest placed order
            if (!empty($_SESSION['guest_push_endpoint_hash'])) {
                $stmtG = $pdo->prepare("SELECT * FROM `push_subscriptions` WHERE `endpoint_hash` = ?");
                $stmtG->execute([$_SESSION['guest_push_endpoint_hash']]);
                $subs = $stmtG->fetchAll(PDO::FETCH_ASSOC);
                $stmtG->closeCursor();
            }
        }

        if (empty($subs)) {
            self::recordDeduplication($dedupKey, 'order_status', (string)$orderId);
            return ['success' => true, 'recipients' => 0, 'message' => 'No push device registered for this customer.'];
        }

        // Record notification
        $stmtNotif = $pdo->prepare("
            INSERT INTO `notifications` 
                (`audience`, `target_customer_id`, `notification_type`, `title`, `body`, `icon`, `image`, `click_url`, `total_recipients`)
            VALUES 
                ('single_customer', ?, 'order_status', ?, ?, ?, ?, ?, ?)
        ");
        $stmtNotif->execute([
            $customerId,
            $title,
            $body,
            $payload['icon'],
            $payload['image'],
            $clickUrl,
            count($subs)
        ]);
        $notifId = (int)$pdo->lastInsertId();
        $stmtNotif->closeCursor();

        $successCount = 0;
        $failCount = 0;

        foreach ($subs as $sub) {
            $res = WebPushService::sendNotification($sub, $payload);
            if ($res['success']) {
                $successCount++;
                self::recordDelivery($notifId, (int)$sub['id'], 'sent', $res['http_code'], null);
            } else {
                $failCount++;
                self::recordDelivery($notifId, (int)$sub['id'], 'failed', $res['http_code'], $res['error']);
                if (!empty($res['expired'])) {
                    self::removeSubscriptionById((int)$sub['id']);
                }
            }
        }

        $stmtUp = $pdo->prepare("UPDATE `notifications` SET `success_count` = ?, `failure_count` = ? WHERE `id` = ?");
        $stmtUp->execute([$successCount, $failCount, $notifId]);
        $stmtUp->closeCursor();

        self::recordDeduplication($dedupKey, 'order_status', (string)$orderId);

        return [
            'success' => true,
            'total' => count($subs),
            'sent' => $successCount,
            'failed' => $failCount,
            'notification_id' => $notifId
        ];
    }

    /**
     * Send promotional announcement or broadcast from Admin Center
     *
     * @param array $data ['title' => ..., 'body' => ..., 'image' => ..., 'url' => ..., 'type' => ...]
     * @param string $audience 'all_customers', 'selected_customers', 'single_customer', 'admins_only'
     * @param int|array|null $targetCustomers
     * @param int|null $adminId
     * @return array
     */
    public static function sendBroadcast(array $data, string $audience, $targetCustomers = null, ?int $adminId = null): array {
        $title = trim($data['title'] ?? '');
        $body = trim($data['body'] ?? '');
        $image = !empty($data['image']) ? trim($data['image']) : null;
        $url = !empty($data['url']) ? trim($data['url']) : '/';
        $notifType = !empty($data['type']) ? trim($data['type']) : 'announcement';

        if (empty($title) || empty($body)) {
            return ['success' => false, 'error' => 'Notification title and body are required.'];
        }

        // Restrict click URL to safe internal relative destinations
        if (!str_starts_with($url, '/')) {
            $url = '/' . ltrim($url, '/');
        }

        // Rate limit bulk promotional broadcasts: max 1 per 60 seconds
        if ($audience === 'all_customers') {
            $pdo = Database::getConnection();
            $stmtRL = $pdo->query("SELECT MAX(`created_at`) FROM `notifications` WHERE `audience` = 'all_customers'");
            $lastSent = $stmtRL->fetchColumn();
            $stmtRL->closeCursor();
            if ($lastSent && (time() - strtotime($lastSent) < 60)) {
                $rem = 60 - (time() - strtotime($lastSent));
                return ['success' => false, 'error' => "Rate limit: Please wait {$rem} seconds before sending another bulk broadcast."];
            }
        }

        $pdo = Database::getConnection();
        $subs = [];

        if ($audience === 'admins_only') {
            $stmt = $pdo->query("SELECT * FROM `push_subscriptions` WHERE `user_type` = 'admin'");
            $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
        } elseif ($audience === 'single_customer') {
            $custId = is_numeric($targetCustomers) ? (int)$targetCustomers : 0;
            // Respect promotional preference if promotional
            if ($notifType === 'promo' && !self::getCustomerPreference($custId, 'promotions')) {
                return ['success' => false, 'error' => 'Customer has not enabled promotional notifications.'];
            }
            $stmt = $pdo->prepare("SELECT * FROM `push_subscriptions` WHERE `user_type` = 'customer' AND `user_id` = ?");
            $stmt->execute([$custId]);
            $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
        } elseif ($audience === 'selected_customers' && is_array($targetCustomers)) {
            $custIds = array_filter(array_map('intval', $targetCustomers));
            if (!empty($custIds)) {
                $in = implode(',', $custIds);
                // Filter out customers who opted out of promotions if promo
                if ($notifType === 'promo') {
                    $stmtPref = $pdo->query("SELECT `customer_id` FROM `customer_notification_preferences` WHERE `promotions` = 1 AND `customer_id` IN ({$in})");
                    $allowedCusts = $stmtPref->fetchAll(PDO::FETCH_COLUMN);
                    $stmtPref->closeCursor();
                    if (empty($allowedCusts)) {
                        return ['success' => false, 'error' => 'None of the selected customers have enabled promotional notifications.'];
                    }
                    $in = implode(',', array_map('intval', $allowedCusts));
                }
                $stmt = $pdo->query("SELECT * FROM `push_subscriptions` WHERE `user_type` = 'customer' AND `user_id` IN ({$in})");
                $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
            }
        } else {
            // all_customers
            if ($notifType === 'promo') {
                // Only send to customers with explicit promotions opt-in
                $stmt = $pdo->query("
                    SELECT s.* 
                    FROM `push_subscriptions` s
                    LEFT JOIN `customer_notification_preferences` p ON p.`customer_id` = s.`user_id`
                    WHERE s.`user_type` = 'customer' 
                      AND (p.`promotions` = 1)
                ");
            } else {
                // Non-promotional store announcement: sent to all active customer subscriptions
                $stmt = $pdo->query("SELECT * FROM `push_subscriptions` WHERE `user_type` = 'customer'");
            }
            $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
        }

        if (empty($subs)) {
            return ['success' => true, 'total' => 0, 'sent' => 0, 'failed' => 0, 'message' => 'No subscribed devices found for the selected audience.'];
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/images/icons/icon-192.png',
            'badge' => '/assets/images/icons/badge-72.png',
            'image' => $image,
            'tag' => 'broadcast-' . time(),
            'data' => [
                'type' => $notifType,
                'url' => $url,
                'timestamp' => time()
            ]
        ];

        // Save notification record
        $stmtNotif = $pdo->prepare("
            INSERT INTO `notifications` 
                (`audience`, `target_customer_id`, `notification_type`, `title`, `body`, `icon`, `image`, `click_url`, `sent_by_admin_id`, `total_recipients`)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $targetCustId = is_numeric($targetCustomers) ? (int)$targetCustomers : null;
        $stmtNotif->execute([
            $audience,
            $targetCustId,
            $notifType,
            $title,
            $body,
            $payload['icon'],
            $image,
            $url,
            $adminId,
            count($subs)
        ]);
        $notifId = (int)$pdo->lastInsertId();
        $stmtNotif->closeCursor();

        $successCount = 0;
        $failCount = 0;

        foreach ($subs as $sub) {
            $res = WebPushService::sendNotification($sub, $payload);
            if ($res['success']) {
                $successCount++;
                self::recordDelivery($notifId, (int)$sub['id'], 'sent', $res['http_code'], null);
            } else {
                $failCount++;
                self::recordDelivery($notifId, (int)$sub['id'], 'failed', $res['http_code'], $res['error']);
                if (!empty($res['expired'])) {
                    self::removeSubscriptionById((int)$sub['id']);
                }
            }
        }

        $stmtUp = $pdo->prepare("UPDATE `notifications` SET `success_count` = ?, `failure_count` = ? WHERE `id` = ?");
        $stmtUp->execute([$successCount, $failCount, $notifId]);
        $stmtUp->closeCursor();

        return [
            'success' => true,
            'total' => count($subs),
            'sent' => $successCount,
            'failed' => $failCount,
            'notification_id' => $notifId
        ];
    }

    /**
     * Get subscriber count statistics
     */
    public static function getStats(): array {
        $pdo = Database::getConnection();

        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM `push_subscriptions` WHERE `user_type` = 'admin'")->fetchColumn();
        $customerCount = (int)$pdo->query("SELECT COUNT(DISTINCT `endpoint_hash`) FROM `push_subscriptions` WHERE `user_type` = 'customer'")->fetchColumn();
        $totalSent = (int)$pdo->query("SELECT COUNT(*) FROM `notification_deliveries` WHERE `status` = 'sent'")->fetchColumn();
        $totalFailed = (int)$pdo->query("SELECT COUNT(*) FROM `notification_deliveries` WHERE `status` = 'failed'")->fetchColumn();

        return [
            'admin_devices' => $adminCount,
            'customer_devices' => $customerCount,
            'total_delivered' => $totalSent,
            'total_failed' => $totalFailed,
            'vapid_public_key' => WebPushService::getPublicKey()
        ];
    }

    /**
     * Customer preferences getter returning full associative array
     */
    public static function getCustomerPreferences(int $customerId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT `order_updates`, `promotions` FROM `customer_notification_preferences` WHERE `customer_id` = ?");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return [
                'order_updates' => 1,
                'promotions' => 1
            ];
        }

        return [
            'order_updates' => (int)$row['order_updates'],
            'promotions' => (int)$row['promotions']
        ];
    }

    /**
     * Customer preference getter with default order_updates=1, promotions=0
     */
    public static function getCustomerPreference(int $customerId, string $key): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT `order_updates`, `promotions` FROM `customer_notification_preferences` WHERE `customer_id` = ?");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return ($key === 'order_updates'); // order_updates true by default
        }

        return !empty($row[$key]);
    }

    /**
     * Set customer notification preferences
     */
    public static function setCustomerPreferences(int $customerId, bool $orderUpdates, bool $promotions): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO `customer_notification_preferences` 
                (`customer_id`, `order_updates`, `promotions`)
            VALUES 
                (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                `order_updates` = VALUES(`order_updates`),
                `promotions` = VALUES(`promotions`),
                `updated_at` = NOW()
        ");
        $res = $stmt->execute([$customerId, $orderUpdates ? 1 : 0, $promotions ? 1 : 0]);
        $stmt->closeCursor();
        return $res;
    }

    private static function isDuplicateEvent(string $key): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT 1 FROM `notification_dedup` WHERE `idempotency_key` = ?");
        $stmt->execute([$key]);
        $exists = (bool)$stmt->fetchColumn();
        $stmt->closeCursor();
        return $exists;
    }

    private static function recordDeduplication(string $key, string $eventType, string $entityId): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT IGNORE INTO `notification_dedup` (`idempotency_key`, `event_type`, `entity_id`) VALUES (?, ?, ?)");
            $stmt->execute([$key, $eventType, $entityId]);
            $stmt->closeCursor();
        } catch (Throwable $ign) {}
    }

    private static function recordDelivery(int $notifId, int $subId, string $status, ?int $httpCode, ?string $err): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO `notification_deliveries` 
                    (`notification_id`, `subscription_id`, `status`, `http_code`, `error_message`)
                VALUES 
                    (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$notifId, $subId, $status, $httpCode, substr($err ?? '', 0, 250)]);
            $stmt->closeCursor();
        } catch (Throwable $ign) {}
    }

    public static function removeSubscriptionById(int $subId): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM `push_subscriptions` WHERE `id` = ?");
            $stmt->execute([$subId]);
            $stmt->closeCursor();
        } catch (Throwable $ign) {}
    }
}
