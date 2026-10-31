<?php
/**
 * Telegram Order Notification Service
 * N.A Fresh Fruits & Coconuts
 * Automatic, reliable, non-blocking order alerts for Cash on Delivery and verified Razorpay payments.
 */

class TelegramService {

    /**
     * Check if Telegram notifications are enabled in store settings or environment
     */
    public static function isEnabled(): bool {
        $dbVal = Setting::get('telegram_notifications_enabled', null);
        if ($dbVal !== null) {
            return $dbVal === '1';
        }
        $envVal = getenv('TELEGRAM_NOTIFICATIONS_ENABLED') ?: ($_ENV['TELEGRAM_NOTIFICATIONS_ENABLED'] ?? '');
        return $envVal === '1' || strtolower((string)$envVal) === 'true';
    }

    /**
     * Get configured Telegram Bot Token (server-side only, never expose to client)
     */
    public static function getBotToken(): string {
        $val = trim((string)Setting::get('telegram_bot_token', ''));
        if (empty($val)) {
            $val = trim((string)(getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? '')));
        }
        return self::normalizeBotToken($val);
    }

    /**
     * Get configured Telegram Chat / Channel ID
     */
    public static function getChatId(): string {
        $val = trim((string)Setting::get('telegram_chat_id', ''));
        if (empty($val)) {
            $val = trim((string)(getenv('TELEGRAM_CHAT_ID') ?: ($_ENV['TELEGRAM_CHAT_ID'] ?? '')));
        }
        return $val;
    }

    /**
     * Normalize bot token by stripping redundant 'bot' prefix and trimming whitespace
     */
    public static function normalizeBotToken(string $token): string {
        $token = trim($token);
        if (str_starts_with(strtolower($token), 'bot')) {
            $token = substr($token, 3);
        }
        return trim($token);
    }

    /**
     * Check if specific event is enabled in store settings
     */
    public static function isEventEnabled(string $event, string $paymentMethod): bool {
        if ($paymentMethod === 'cod') {
            return Setting::get('telegram_send_on_cod', '1') === '1';
        }
        if ($event === 'order_paid') {
            return Setting::get('telegram_send_on_online_paid', '1') === '1';
        }
        return true;
    }

    /**
     * Enqueue order notification asynchronously in the database-backed task queue.
     * Prevents duplicate notifications for the same order and event.
     *
     * @param int $orderId
     * @param string $event Event type ('order_placed' or 'order_paid')
     * @return bool
     */
    public static function enqueueOrderNotification(int $orderId, string $event = 'order_placed'): bool {
        try {
            $idempotencyToken = "telegram_{$event}_{$orderId}";
            BackgroundTaskService::enqueue('telegram_order_notification', $orderId, [
                'order_id' => $orderId,
                'event' => $event,
                'enqueued_at' => date('Y-m-d H:i:s')
            ], $idempotencyToken);
            return true;
        } catch (Throwable $e) {
            error_log("Failed to enqueue Telegram order notification for Order #{$orderId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Synchronously dispatch order notification (used for testing or direct dispatch)
     */
    public static function dispatchOrderNotificationSync(int $orderId, string $event = 'order_placed'): array {
        $order = Order::getById($orderId);
        if (!$order) {
            return ['success' => false, 'error' => "Order #{$orderId} not found."];
        }
        $items = Order::getItems($orderId);
        return self::sendOrderNotification($order, $items, $event);
    }

    /**
     * Dispatch notification for an order
     * Called by BackgroundTaskService worker, NEVER blocks customer checkout
     *
     * @param array $order Order record
     * @param array $items Order items
     * @param string $event Event type ('order_placed' or 'order_paid')
     * @return array Result
     */
    public static function sendOrderNotification(array $order, array $items = [], string $event = 'order_placed'): array {
        // 1. Check if Telegram notifications are enabled
        if (!self::isEnabled()) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Telegram order notifications are currently disabled in store settings.'
            ];
        }

        $token = self::getBotToken();
        $chatId = self::getChatId();

        if (empty($token) || empty($chatId)) {
            error_log("Telegram order notification skipped for Order #{$order['id']}: Bot Token or Chat ID not configured.");
            return [
                'success' => false,
                'skipped' => true,
                'error' => 'Telegram Bot Token or Chat ID is not configured.'
            ];
        }

        // 2. Check event toggle (COD vs Online paid)
        $paymentMethod = $order['payment_method'] ?? 'cod';
        if (!self::isEventEnabled($event, $paymentMethod)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => "Telegram notifications for event '{$event}' ({$paymentMethod}) are disabled in store settings."
            ];
        }

        // 3. Format message in clean, robust HTML mode
        $messageHtml = self::formatOrderMessage($order, $items);

        // 4. Send via Telegram Bot API
        return self::sendApiMessage($token, $chatId, $messageHtml, 'HTML');
    }

    /**
     * Send test message from admin settings panel
     */
    public static function sendTestMessage(?string $token = null, ?string $chatId = null): array {
        $token = !empty($token) ? trim($token) : self::getBotToken();
        $chatId = !empty($chatId) ? trim($chatId) : self::getChatId();

        if (empty($token) || empty($chatId)) {
            return [
                'success' => false,
                'error' => 'Please provide both Telegram Bot Token and Chat ID to run a test.'
            ];
        }

        $storeName = Setting::get('store_name', 'N.A Fresh Fruits & Coconuts');
        $text = "🥥 <b>" . self::escapeHtml($storeName) . " — Test Notification</b>\n\n"
              . "✅ <b>Success!</b> Your Telegram Bot is successfully connected and verified.\n"
              . "📦 Order notifications will arrive in this chat automatically whenever new orders are confirmed.\n\n"
              . "⏱ <b>Timestamp:</b> " . date('d M Y, h:i A');

        $res = self::sendApiMessage($token, $chatId, $text, 'HTML');
        if ($res['success']) {
            $res['message'] = 'Test notification delivered successfully to your Telegram chat!';
        }
        return $res;
    }

    /**
     * Format professional, highly readable Telegram notification message in HTML parse mode.
     * Escapes all dynamic text to prevent parse errors.
     */
    public static function formatOrderMessage(array $order, array $items = []): string {
        $storeName = Setting::get('store_name', 'N.A Fresh Fruits & Coconuts');
        $orderNumber = $order['order_number'] ?? ('#' . ($order['id'] ?? ''));
        $customerName = !empty($order['customer_name']) ? $order['customer_name'] : 'Valued Customer';
        $customerPhone = $order['customer_phone'] ?? '';
        $address = $order['delivery_address'] ?? 'Jaora Delivery';
        $areaName = $order['delivery_area_name'] ?? '';
        $orderDate = !empty($order['created_at']) ? date('d M Y, h:i A', strtotime($order['created_at'])) : date('d M Y, h:i A');

        $subtotal = 0.00;
        $orderTotal = (float)($order['total_amount'] ?? 0);
        $discountAmount = (float)($order['discount_amount'] ?? 0);
        $deliveryCharge = (float)($order['delivery_charge'] ?? 0);

        $isOnline = ($order['payment_method'] ?? 'cod') === 'online';
        $methodLabel = $isOnline ? 'Razorpay Online' : 'Cash on Delivery (COD)';
        $paymentStatus = ucfirst($order['payment_status'] ?? 'pending');
        $orderStatus = ucfirst($order['order_status'] ?? 'confirmed');

        $lines = [];
        $lines[] = "🥥 <b>NEW ORDER RECEIVED — " . self::escapeHtml($storeName) . "</b>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "📦 <b>Order Number:</b> <code>" . self::escapeHtml($orderNumber) . "</code>";
        $lines[] = "⏱ <b>Date & Time:</b> " . self::escapeHtml($orderDate);
        $lines[] = "👤 <b>Customer:</b> " . self::escapeHtml($customerName);
        $lines[] = "📞 <b>Mobile:</b> <code>" . self::escapeHtml($customerPhone) . "</code>";
        if (!empty($areaName)) {
            $lines[] = "📍 <b>Area / Locality:</b> " . self::escapeHtml($areaName);
        }
        $lines[] = "🏠 <b>Delivery Address:</b> " . self::escapeHtml($address);
        if (!empty($order['notes'])) {
            $lines[] = "📝 <b>Landmark / Notes:</b> " . self::escapeHtml($order['notes']);
        }
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🛒 <b>ORDERED ITEMS:</b>";

        if (empty($items) && !empty($order['id'])) {
            $items = Order::getItems((int)$order['id']);
        }

        foreach ($items as $item) {
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $pName = $item['product_name'] ?? 'Fresh Tender Coconut';
            $unitPrice = (float)($item['unit_price'] ?? ($item['price'] ?? 0));
            $lineSubtotal = (float)($item['subtotal'] ?? ($unitPrice * $qty));
            $subtotal += $lineSubtotal;

            $lines[] = " • <b>" . self::escapeHtml($pName) . "</b> × {$qty} (@ ₹" . number_format($unitPrice, 2) . ") = ₹" . number_format($lineSubtotal, 2);
        }

        if ($subtotal <= 0 && $orderTotal > 0) {
            $subtotal = max(0.00, $orderTotal - $deliveryCharge + $discountAmount);
        }

        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🧾 <b>Subtotal:</b> ₹" . number_format($subtotal, 2);
        if ($discountAmount > 0) {
            $couponCode = !empty($order['coupon_code']) ? " ({$order['coupon_code']})" : '';
            $lines[] = "🏷 <b>Discount{$couponCode}:</b> -₹" . number_format($discountAmount, 2);
        }
        $lines[] = "🚚 <b>Delivery Charge:</b> " . ($deliveryCharge <= 0 ? "FREE" : "₹" . number_format($deliveryCharge, 2));
        $lines[] = "💰 <b>Final Order Total:</b> <b>₹" . number_format($orderTotal, 2) . "</b>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "💳 <b>Payment Method:</b> " . self::escapeHtml($methodLabel);
        $lines[] = "📊 <b>Payment Status:</b> " . self::escapeHtml($paymentStatus);
        $lines[] = "⚡ <b>Order Status:</b> " . self::escapeHtml($orderStatus);

        return implode("\n", $lines);
    }

    /**
     * Escape special HTML characters for Telegram HTML parse mode
     */
    public static function escapeHtml(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Send HTTP POST request to Telegram Bot API.
     * Uses literal unencoded bot token path, JSON payload, and robust error logging.
     *
     * @param string $token Telegram Bot Token
     * @param string $chatId Target Chat or Channel ID
     * @param string $text Message content
     * @param string $parseMode 'HTML' or 'Markdown'
     * @return array Result array with success flag and details
     */
    public static function sendApiMessage(string $token, string $chatId, string $text, string $parseMode = 'HTML'): array {
        $cleanToken = self::normalizeBotToken($token);
        $cleanChatId = trim($chatId);

        if (empty($cleanToken) || empty($cleanChatId)) {
            return ['success' => false, 'error' => 'Bot Token and Chat ID are required.'];
        }

        // Direct valid endpoint without urlencode on the path
        $url = "https://api.telegram.org/bot{$cleanToken}/sendMessage";

        $payload = [
            'chat_id' => $cleanChatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true
        ];

        // Mask token for safe error logs (e.g. 123456...wxyz)
        $tokenMask = (strlen($cleanToken) > 10) 
            ? substr($cleanToken, 0, 6) . '...' . substr($cleanToken, -4) 
            : '***';

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                error_log("Telegram API cURL Error (Bot {$tokenMask}): {$err}");
                return ['success' => false, 'error' => "cURL communication error: {$err}"];
            }

            $decoded = json_decode((string)$response, true);
            if ($httpCode === 200 && !empty($decoded['ok'])) {
                return ['success' => true, 'data' => $decoded['result'] ?? []];
            }

            $msg = $decoded['description'] ?? ("HTTP " . ($httpCode ?: 'No response'));
            error_log("Telegram API Failed (Bot {$tokenMask}, HTTP {$httpCode}): {$msg}");
            return ['success' => false, 'error' => "Telegram API returned: {$msg}", 'http_code' => $httpCode];
        }

        // Fallback using file_get_contents with stream context if cURL is unavailable
        $options = [
            'http' => [
                'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 8,
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            return ['success' => false, 'error' => 'HTTP request to Telegram failed.'];
        }
        $decoded = json_decode($result, true);
        if (!empty($decoded['ok'])) {
            return ['success' => true, 'data' => $decoded['result'] ?? []];
        }
        return ['success' => false, 'error' => $decoded['description'] ?? 'Telegram API request failed'];
    }
}
