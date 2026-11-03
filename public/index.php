<?php
/**
 * Public Front Controller & Router
 * N.A Fresh Fruits & Coconuts (PHP 8.2 & MySQL)
 */

// Allow PHP built-in web server to serve existing static files directly
if (php_sapi_name() === 'cli-server') {
    $staticPath = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($staticPath)) {
        return false;
    }
}

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/InstallerController.php';

// Parse incoming request path
$rawUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$uri = urldecode($rawUri);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ----------------------------------------------------
// SECURITY ENFORCEMENT & ACCESS CONTROL
// ----------------------------------------------------

// 1. Block Path Traversal & Null-Byte Injection Attacks
if (str_contains($rawUri, '..') || str_contains($uri, '..') || str_contains($rawUri, "\0") || str_contains($uri, "\0")) {
    http_response_code(400);
    exit('Illegal path characters detected.');
}

// 2. Block Direct Web Access to Internal Application Directories
$blockedPrefixes = ['/app', '/storage', '/database', '/cron', '/bin', '/tests', '/node_modules', '/.git', '/.env'];
foreach ($blockedPrefixes as $prefix) {
    if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
        http_response_code(403);
        exit('Access to internal application directories is forbidden.');
    }
}

// 3. Block Direct Web Access to Protected File Extensions
if (preg_match('/\.(sql|log|lock|env|md|sh|yml|yaml|ini|bak|swp|ts|tsx)$/i', $uri)) {
    http_response_code(403);
    exit('Direct access to protected file types is forbidden.');
}

// 4. Validate Host Header (Mitigate Host Header Injection & Poisoning)
$httpHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
$hostOnly = strtolower(explode(':', $httpHost)[0]);
if (!empty($hostOnly)) {
    $allowedHosts = [
        'nifruitjaora.shop',
        'www.nifruitjaora.shop',
        'localhost',
        '127.0.0.1',
        '::1'
    ];
    $isAllowed = in_array($hostOnly, $allowedHosts, true) || str_ends_with($hostOnly, '.run.app');
    if (!$isAllowed) {
        $envAllowed = getenv('APP_ALLOWED_HOSTS') ? explode(',', getenv('APP_ALLOWED_HOSTS')) : [];
        foreach ($envAllowed as $ea) {
            if ($hostOnly === strtolower(trim($ea))) {
                $isAllowed = true;
                break;
            }
        }
    }
    if (!$isAllowed) {
        http_response_code(400);
        exit('Invalid or untrusted Host header.');
    }

    // Canonical Redirection: Redirect www to apex domain, and enforce HTTPS in production
    if ($hostOnly === 'www.nifruitjaora.shop') {
        header('Location: https://nifruitjaora.shop' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
    if ($hostOnly === 'nifruitjaora.shop') {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if (!$isHttps && php_sapi_name() !== 'cli-server') {
            header('Location: https://nifruitjaora.shop' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
            exit;
        }
    }
}

// 5. Standard Security Response Headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Normalize slashes (e.g. /shop/ -> /shop)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

// Client IP helper
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (str_contains($clientIp, ',')) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

// ----------------------------------------------------
// 1. WEB INSTALLER ROUTE HANDLING
// ----------------------------------------------------
if ($uri === '/install' || str_starts_with($uri, '/install/')) {
    InstallerController::handle();
    exit;
}

// ----------------------------------------------------
// 2. INSTALLATION STATE CHECKING
// ----------------------------------------------------
if (!is_installed()) {
    // If DB is already configured and populated, self-heal the lock file
    if (Database::isConfiguredAndPopulated()) {
        $storageDir = dirname(INSTALL_LOCK_FILE);
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        @file_put_contents(INSTALL_LOCK_FILE, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'status' => 'auto_recovered',
            'verified' => true
        ], JSON_PRETTY_PRINT));
    } else {
        // Direct uninstalled users cleanly to Web Installer
        header('Location: /install?step=1');
        exit;
    }
}

// ----------------------------------------------------
// 3. MAIN APPLICATION ROUTING
// ----------------------------------------------------
try {
    switch ($uri) {
        // ------------------------------------------------
        // Customer Routes
        // ------------------------------------------------
        case '':
        case '/':
        case '/index.html':
        case '/index.php':
            $product = Product::getPrimary();
            if (!$product) {
                // If product is missing, self-seed default product if database tables exist
                try {
                    $pdo = Database::getConnection();
                    $stmtProd = $pdo->prepare("
                        INSERT INTO `products` (`name`, `slug`, `tagline`, `description`, `price`, `stock_quantity`, `is_available`, `image_url`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtProd->execute([
                        'Fresh Coconut Water',
                        'fresh-coconut-water',
                        'Pure • Natural • Healthy',
                        'Fresh tender coconut water, straight from nature. No added sugar, no preservatives, no chemicals.',
                        40.00,
                        150,
                        1,
                        '/assets/images/product-main.png'
                    ]);
                    $product = Product::getPrimary();
                } catch (Exception $e) {
                    // non-blocking
                }
            }
            require __DIR__ . '/../app/views/home.php';
            break;

        case '/shop':
            $categories = Category::getAll(true);
            $selectedCategory = isset($_GET['cat']) ? trim($_GET['cat']) : 'all';
            $search = trim($_GET['q'] ?? '');
            $catId = null;
            if ($selectedCategory && $selectedCategory !== 'all' && $selectedCategory !== 'combos') {
                $cObj = Category::getBySlug($selectedCategory);
                if ($cObj) {
                    $catId = (int)$cObj['id'];
                }
            }
            $products = ($selectedCategory === 'combos') ? [] : Product::getAll(true, $catId, $search);
            $combos = ($selectedCategory === 'all' || $selectedCategory === 'combos') ? Combo::getAll(true) : [];
            require __DIR__ . '/../app/views/shop.php';
            break;

        case '/cart':
            require __DIR__ . '/../app/views/cart.php';
            break;

        case '/cart/add':
            if ($method === 'POST') {
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                    || isset($_POST['is_ajax']);

                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    if ($isAjax) {
                        json_response(['success' => false, 'error' => 'Session expired. Please reload and try again.'], 403);
                    }
                    set_flash('error', 'Session expired. Please try again.');
                    header('Location: /shop');
                    exit;
                }

                $type = trim($_POST['type'] ?? 'product');
                $qty = max(1, (int)($_POST['quantity'] ?? 1));
                $optionId = !empty($_POST['option_id']) ? trim($_POST['option_id']) : null;

                if ($type === 'combo' || !empty($_POST['combo_id'])) {
                    $comboId = (int)($_POST['combo_id'] ?? $_POST['id'] ?? 0);
                    $res = CartService::addCombo($comboId, $qty);
                } else {
                    $productId = (int)($_POST['product_id'] ?? $_POST['id'] ?? 1);
                    $res = CartService::addItem($productId, $qty, $optionId);
                }

                if ($isAjax) {
                    json_response($res);
                }

                if (!$res['success']) {
                    set_flash('error', $res['error']);
                } else {
                    set_flash('success', 'Item added to your cart.');
                }
                header('Location: /cart');
                exit;
            }
            header('Location: /cart');
            break;

        case '/cart/update':
            if ($method === 'POST') {
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                    || isset($_POST['is_ajax']);

                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    if ($isAjax) {
                        json_response(['success' => false, 'error' => 'Security token expired.'], 403);
                    }
                    header('Location: /cart');
                    exit;
                }
                $itemKey = trim($_POST['item_key'] ?? $_POST['product_id'] ?? '');
                $qty = (int)($_POST['quantity'] ?? 1);
                $res = CartService::updateQuantity($itemKey, $qty);

                if ($isAjax) {
                    json_response($res);
                }
                header('Location: /cart');
                exit;
            }
            header('Location: /cart');
            break;

        case '/cart/remove':
            if ($method === 'POST') {
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                    || isset($_POST['is_ajax']);

                if (verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    $itemKey = trim($_POST['item_key'] ?? $_POST['product_id'] ?? '');
                    $res = CartService::removeItem($itemKey);
                    if ($isAjax) {
                        json_response($res);
                    }
                    set_flash('info', 'Item removed from cart.');
                }
            }
            header('Location: /cart');
            break;

        case '/cart/clear':
            if ($method === 'POST') {
                if (verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    CartService::clear();
                    set_flash('info', 'Cart cleared.');
                }
            }
            header('Location: /cart');
            break;

        case '/cart/apply-coupon':
            if ($method === 'POST') {
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                    || isset($_POST['is_ajax']);

                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    if ($isAjax) {
                        json_response(['success' => false, 'error' => 'Security token expired. Please refresh the page.'], 403);
                    }
                    set_flash('error', 'Security token expired. Please try again.');
                    $redirect = $_POST['redirect_to'] ?? '/cart';
                    header('Location: ' . $redirect);
                    exit;
                }

                $code = trim($_POST['coupon_code'] ?? '');
                $customer = CustomerAuthService::user();
                $customerId = $customer ? (int)$customer['id'] : null;
                $phone = $customer['phone'] ?? trim($_POST['customer_phone'] ?? '');
                $fulfilment = trim($_POST['fulfilment_method'] ?? 'delivery');

                $res = CartService::applyCoupon($code, $customerId, $phone, $fulfilment);
                if ($isAjax) {
                    $totals = CartService::calculateCheckoutTotals($fulfilment, $customerId, $phone);
                    json_response(array_merge($res, ['totals' => $totals]));
                }

                if ($res['success']) {
                    set_flash('success', $res['message']);
                } else {
                    set_flash('error', $res['error']);
                }

                $redirect = $_POST['redirect_to'] ?? '/cart';
                header('Location: ' . $redirect);
                exit;
            }
            header('Location: /cart');
            break;

        case '/cart/remove-coupon':
            if ($method === 'POST') {
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                    || isset($_POST['is_ajax']);

                if (verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    CartService::removeCoupon();
                    if ($isAjax) {
                        $customer = CustomerAuthService::user();
                        $customerId = $customer ? (int)$customer['id'] : null;
                        $phone = $customer['phone'] ?? trim($_POST['customer_phone'] ?? '');
                        $fulfilment = trim($_POST['fulfilment_method'] ?? 'delivery');
                        $totals = CartService::calculateCheckoutTotals($fulfilment, $customerId, $phone);
                        json_response(['success' => true, 'message' => 'Coupon removed.', 'totals' => $totals]);
                    }
                    set_flash('info', 'Coupon removed from your order.');
                }
                $redirect = $_POST['redirect_to'] ?? '/cart';
                header('Location: ' . $redirect);
                exit;
            }
            header('Location: /cart');
            break;

        case '/checkout':
            CustomerAuthService::requireLogin('/checkout');
            require __DIR__ . '/../app/views/checkout.php';
            break;

        case '/checkout/place':
            if ($method === 'POST') {
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

                if (!CustomerAuthService::check()) {
                    if ($isAjax) {
                        json_response(['success' => false, 'error' => 'Please log in or create an account to place your order.', 'auth_required' => true], 401);
                    }
                    set_flash('error', 'Please log in or create an account to place your order.');
                    $_SESSION['customer_intended_url'] = '/checkout';
                    header('Location: /login');
                    exit;
                }

                $result = OrderService::processCheckout($_POST);
                if ($result['success']) {
                    $_SESSION['last_customer_name'] = $_POST['customer_name'] ?? '';
                    $_SESSION['last_customer_phone'] = $_POST['customer_phone'] ?? '';
                    $_SESSION['last_customer_address'] = $_POST['delivery_address'] ?? '';

                    if (!empty($result['requires_razorpay'])) {
                        json_response($result);
                    }

                    if ($isAjax) {
                        json_response([
                            'success' => true,
                            'redirect_url' => '/confirmation?num=' . urlencode($result['order_number']) . '&h=' . urlencode($result['tracking_hash'])
                        ]);
                    }

                    header('Location: /confirmation?num=' . urlencode($result['order_number']) . '&h=' . urlencode($result['tracking_hash']));
                    exit;
                } else {
                    if ($isAjax) {
                        json_response([
                            'success' => false,
                            'error' => $result['error'],
                            'auth_required' => !empty($result['auth_required'])
                        ], 400);
                    }
                    set_flash('error', $result['error']);
                    if (!empty($result['auth_required'])) {
                        $_SESSION['customer_intended_url'] = '/checkout';
                        header('Location: /login');
                    } else {
                        header('Location: /checkout');
                    }
                    exit;
                }
            }
            header('Location: /checkout');
            break;

        case '/payment/razorpay/verify':
            if ($method === 'POST') {
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    json_response(['success' => false, 'error' => 'Security token expired. Please reload and retry.'], 403);
                }

                $razorpayOrderId = trim($_POST['razorpay_order_id'] ?? '');
                $razorpayPaymentId = trim($_POST['razorpay_payment_id'] ?? '');
                $razorpaySignature = trim($_POST['razorpay_signature'] ?? '');
                $attemptRef = trim($_POST['attempt_reference'] ?? '');

                if (empty($razorpayOrderId) || empty($razorpayPaymentId) || empty($razorpaySignature)) {
                    json_response(['success' => false, 'error' => 'Incomplete payment response parameters received.'], 400);
                }

                // 1. Locate Transaction & Associated Order
                $tx = null;
                if (!empty($attemptRef)) {
                    $tx = PaymentTransaction::getByAttemptReference($attemptRef);
                }
                if (!$tx && !empty($razorpayOrderId)) {
                    $tx = PaymentTransaction::getByGatewayOrderId($razorpayOrderId);
                }

                if (!$tx) {
                    json_response(['success' => false, 'error' => 'Payment transaction record not found.'], 404);
                }

                $order = Order::getById((int)$tx['order_id']);
                if (!$order) {
                    json_response(['success' => false, 'error' => 'Order record not found.'], 404);
                }

                // Check if this payment was already successfully processed (Idempotent callback protection)
                if ($tx['status'] === 'captured' && $order['payment_status'] === 'captured') {
                    CartService::clear();
                    json_response([
                        'success' => true,
                        'is_duplicate' => true,
                        'redirect_url' => '/confirmation?num=' . urlencode($order['order_number']) . '&h=' . urlencode($order['tracking_hash'])
                    ]);
                }

                // 2. Cryptographic Signature Verification (HMAC SHA256)
                $isValidSignature = RazorpayService::verifyPaymentSignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature);
                if (!$isValidSignature) {
                    PaymentTransaction::updateStatus((int)$tx['id'], 'failed', [
                        'gateway_payment_id' => $razorpayPaymentId,
                        'gateway_signature' => $razorpaySignature,
                        'error_message' => 'Cryptographic signature verification failed (HMAC mismatch)'
                    ]);
                    json_response(['success' => false, 'error' => 'Payment signature verification failed. Transaction rejected.'], 400);
                }

                // 3. Gateway Payment Status & Amount / Currency Verification
                $fetchResult = RazorpayService::fetchPayment($razorpayPaymentId, $razorpayOrderId ?: $attemptRef);
                if (!$fetchResult['success']) {
                    PaymentTransaction::updateStatus((int)$tx['id'], 'failed', [
                        'gateway_payment_id' => $razorpayPaymentId,
                        'error_message' => $fetchResult['error'] ?? 'Could not verify payment with Razorpay'
                    ]);
                    json_response(['success' => false, 'error' => 'Payment could not be verified with gateway.'], 400);
                }

                $payData = $fetchResult['payment'] ?? $fetchResult['data'] ?? [];
                $actualAmount = (int)($payData['amount'] ?? 0);
                $actualCurrency = strtoupper(trim((string)($payData['currency'] ?? 'INR')));
                $expectedAmount = (int)$tx['amount_paise'] > 0 ? (int)$tx['amount_paise'] : (int)round((float)$order['total_amount'] * 100);

                if ($actualCurrency !== 'INR' || $actualAmount !== $expectedAmount) {
                    PaymentTransaction::updateStatus((int)$tx['id'], 'failed', [
                        'gateway_payment_id' => $razorpayPaymentId,
                        'error_message' => "Amount/currency mismatch. Expected {$expectedAmount} INR, received {$actualAmount} {$actualCurrency}"
                    ]);
                    // Secure logging: Never expose secret keys or sensitive customer credentials
                    $maskedPayId = RazorpayService::maskSecret($razorpayPaymentId);
                    error_log(sprintf(
                        "[%s] [Razorpay Verification Error] Order: %s, Tx ID: %d, Expected: %d INR, Received: %d %s, Payment Ref: %s",
                        date('Y-m-d H:i:s'),
                        $order['order_number'] ?? 'N/A',
                        $tx['id'] ?? 0,
                        $expectedAmount,
                        $actualAmount,
                        $actualCurrency,
                        $maskedPayId
                    ));
                    json_response(['success' => false, 'error' => 'Payment amount or currency mismatch. Transaction rejected.'], 400);
                }

                // Auto-capture payment if authorized and not auto-captured by gateway
                if (($payData['status'] ?? '') === 'authorized' && empty($payData['captured'])) {
                    $capRes = RazorpayService::capturePayment($razorpayPaymentId, $expectedAmount, 'INR');
                    if ($capRes['success']) {
                        $payData['status'] = 'captured';
                        $payData['captured'] = true;
                    }
                }

                // 4. Update Transaction, Confirm Order, and Clear Cart
                PaymentTransaction::updateStatus((int)$tx['id'], 'captured', [
                    'gateway_payment_id' => $razorpayPaymentId,
                    'gateway_signature' => $razorpaySignature,
                    'raw_response' => json_encode($payData)
                ]);

                Order::updatePaymentAndOrderStatus((int)$order['id'], 'captured', 'confirmed', [
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'razorpay_signature' => $razorpaySignature
                ]);

                // Auto-create or mark invoice as Paid
                $updatedOrder = Order::getById((int)$order['id']);
                Invoice::createForOrder($updatedOrder, $razorpayPaymentId);

                // Instant PWA Web Push notification to admins and customer
                NotificationService::sendNewOrderToAdmins((int)$order['id']);
                NotificationService::sendOrderStatusToCustomer((int)$order['id'], 'payment_confirmed');

                CartService::clear();

                json_response([
                    'success' => true,
                    'redirect_url' => '/confirmation?num=' . urlencode($order['order_number']) . '&h=' . urlencode($order['tracking_hash'])
                ]);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        case '/webhooks/razorpay':
            if ($method === 'POST') {
                $rawPayload = file_get_contents('php://input');
                $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

                if (empty($rawPayload)) {
                    json_response(['error' => 'Empty webhook payload'], 400);
                }

                // 1. Verify Webhook Signature (HMAC SHA256)
                if (!RazorpayService::verifyWebhookSignature($rawPayload, $signature)) {
                    json_response(['error' => 'Invalid webhook signature'], 400);
                }

                $data = json_decode($rawPayload, true);
                if (!$data) {
                    json_response(['error' => 'Invalid JSON payload'], 400);
                }

                $eventId = $data['event_id'] ?? ($data['id'] ?? md5($rawPayload));
                $eventType = $data['event'] ?? 'unknown';

                // 2. Webhook Event Deduplication
                if (PaymentTransaction::isWebhookEventProcessed($eventId)) {
                    json_response(['status' => 'already_processed']);
                }

                PaymentTransaction::recordWebhookEvent($eventId, $eventType, $rawPayload);

                // 3. Process Specific Events
                if (in_array($eventType, ['payment.captured', 'order.paid'])) {
                    $payEntity = $data['payload']['payment']['entity'] ?? [];
                    $gatewayOrderId = $payEntity['order_id'] ?? '';
                    $gatewayPaymentId = $payEntity['id'] ?? '';

                    if (!empty($gatewayOrderId)) {
                        $order = Order::getByRazorpayOrderId($gatewayOrderId);
                        if ($order) {
                            Order::updatePaymentAndOrderStatus((int)$order['id'], 'captured', 'confirmed', [
                                'razorpay_payment_id' => $gatewayPaymentId
                            ]);
                            $updatedOrder = Order::getById((int)$order['id']);
                            Invoice::createForOrder($updatedOrder, $gatewayPaymentId);
                            NotificationService::sendNewOrderToAdmins((int)$order['id']);
                            NotificationService::sendOrderStatusToCustomer((int)$order['id'], 'payment_confirmed');

                            $tx = PaymentTransaction::getByGatewayOrderId($gatewayOrderId);
                            if ($tx) {
                                PaymentTransaction::updateStatus((int)$tx['id'], 'captured', [
                                    'gateway_payment_id' => $gatewayPaymentId,
                                    'raw_response' => json_encode($payEntity)
                                ]);
                            }
                        }
                    }
                } elseif ($eventType === 'payment.failed') {
                    $payEntity = $data['payload']['payment']['entity'] ?? [];
                    $gatewayOrderId = $payEntity['order_id'] ?? '';
                    $gatewayPaymentId = $payEntity['id'] ?? '';
                    $errorDesc = $payEntity['error_description'] ?? 'Payment failed';

                    if (!empty($gatewayOrderId)) {
                        $tx = PaymentTransaction::getByGatewayOrderId($gatewayOrderId);
                        if ($tx) {
                            PaymentTransaction::updateStatus((int)$tx['id'], 'failed', [
                                'gateway_payment_id' => $gatewayPaymentId,
                                'error_message' => $errorDesc,
                                'raw_response' => json_encode($payEntity)
                            ]);
                        }
                    }
                } elseif ($eventType === 'refund.processed') {
                    $refundEntity = $data['payload']['refund']['entity'] ?? [];
                    $gatewayPaymentId = $refundEntity['payment_id'] ?? '';

                    if (!empty($gatewayPaymentId)) {
                        $tx = PaymentTransaction::getByGatewayPaymentId($gatewayPaymentId);
                        if ($tx) {
                            PaymentTransaction::updateStatus((int)$tx['id'], 'refunded');
                            Order::updatePaymentAndOrderStatus((int)$tx['order_id'], 'refunded');
                        }
                    }
                }

                // 4. Mark Event Processed
                PaymentTransaction::markWebhookEventProcessed($eventId);

                json_response(['status' => 'success']);
            }
            json_response(['error' => 'Method not allowed'], 405);
            break;

        // ------------------------------------------------
        // Customer Authentication & Account Routes
        // ------------------------------------------------
        case '/register':
            if (CustomerAuthService::check()) {
                header('Location: /account');
                exit;
            }
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Session token mismatch. Please try again.');
                } else {
                    $res = CustomerAuthService::register($_POST, $clientIp);
                    if ($res['success']) {
                        set_flash('success', 'Account registered successfully! Welcome to N.A Fresh Fruits & Coconuts.');
                        $target = $_SESSION['customer_intended_url'] ?? '/account';
                        unset($_SESSION['customer_intended_url']);
                        header('Location: ' . $target);
                        exit;
                    } else {
                        set_flash('error', $res['error']);
                    }
                }
            }
            require __DIR__ . '/../app/views/customer/register.php';
            break;

        case '/login':
            if (CustomerAuthService::check()) {
                header('Location: /account');
                exit;
            }
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Session token expired. Please try again.');
                } else {
                    $identifier = trim($_POST['identifier'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $res = CustomerAuthService::login($identifier, $password, $clientIp);
                    if ($res['success']) {
                        $cUser = $res['customer'];
                        set_flash('success', 'Welcome back, ' . e($cUser['name']) . '!');
                        $target = $_SESSION['customer_intended_url'] ?? '/account';
                        unset($_SESSION['customer_intended_url']);
                        header('Location: ' . $target);
                        exit;
                    } else {
                        set_flash('error', $res['error']);
                    }
                }
            }
            require __DIR__ . '/../app/views/customer/login.php';
            break;

        case '/logout':
            if ($method === 'POST') {
                verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '');
            }
            CustomerAuthService::logout();
            set_flash('info', 'You have been safely logged out.');
            header('Location: /');
            exit;
            break;

        case '/account':
            CustomerAuthService::requireLogin('/account');
            require __DIR__ . '/../app/views/customer/account.php';
            break;

        case '/account/profile':
            CustomerAuthService::requireLogin('/account');
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token expired. Please try again.');
                } else {
                    $cid = CustomerAuthService::id();
                    $update = [
                        'name' => trim($_POST['name'] ?? ''),
                        'email' => trim($_POST['email'] ?? ''),
                        'address' => trim($_POST['address'] ?? ''),
                        'city' => trim($_POST['city'] ?? ''),
                        'pincode' => trim($_POST['pincode'] ?? '')
                    ];
                    if (!empty($update['email']) && Customer::emailExists($update['email'], $cid)) {
                        set_flash('error', 'That email address is already in use by another account.');
                    } else {
                        Customer::updateProfile($cid, $update);
                        $_SESSION[CustomerAuthService::SESSION_CUSTOMER_NAME] = $update['name'];
                        set_flash('success', 'Your account details have been updated.');
                    }
                }
            }
            header('Location: /account');
            exit;
            break;

        case '/my-orders':
            CustomerAuthService::requireLogin('/my-orders');
            require __DIR__ . '/../app/views/customer/my-orders.php';
            break;

        case '/account/orders/invoice':
            $orderId = (int)($_GET['order_id'] ?? ($_GET['id'] ?? 0));
            $order = $orderId > 0 ? Order::getById($orderId) : null;
            if (!$order && !empty($_GET['num'])) {
                $order = Order::getByOrderNumber(trim($_GET['num']));
            }
            if (!$order) {
                set_flash('error', 'Order not found.');
                header('Location: /');
                exit;
            }

            // Authorization: admin, owner customer, or tracking hash bearer
            $canAccess = false;
            if (AuthService::check()) {
                $canAccess = true;
            } elseif (CustomerAuthService::check() && (int)$order['customer_id'] === CustomerAuthService::id()) {
                $canAccess = true;
            } elseif (!empty($_GET['h']) && hash_equals((string)$order['tracking_hash'], (string)$_GET['h'])) {
                $canAccess = true;
            }

            if (!$canAccess) {
                set_flash('error', 'Please log in to view your invoice.');
                $_SESSION['customer_intended_url'] = '/account/orders/invoice?order_id=' . $order['id'];
                header('Location: /login');
                exit;
            }

            // Render printable A4 HTML invoice
            echo InvoiceService::renderHtml($order);
            exit;
            break;

        case '/account/orders/invoice/download':
            $orderId = (int)($_GET['order_id'] ?? ($_GET['id'] ?? 0));
            $order = $orderId > 0 ? Order::getById($orderId) : null;
            if (!$order && !empty($_GET['num'])) {
                $order = Order::getByOrderNumber(trim($_GET['num']));
            }
            if (!$order) {
                set_flash('error', 'Order not found.');
                header('Location: /');
                exit;
            }

            $canAccess = false;
            if (AuthService::check()) {
                $canAccess = true;
            } elseif (CustomerAuthService::check() && (int)$order['customer_id'] === CustomerAuthService::id()) {
                $canAccess = true;
            } elseif (!empty($_GET['h']) && hash_equals((string)$order['tracking_hash'], (string)$_GET['h'])) {
                $canAccess = true;
            }

            if (!$canAccess) {
                set_flash('error', 'Please log in to download your invoice.');
                $_SESSION['customer_intended_url'] = '/account/orders/invoice/download?order_id=' . $order['id'];
                header('Location: /login');
                exit;
            }

            // Stream pure native PHP PDF
            InvoiceService::streamPdf($order);
            exit;
            break;

        case '/confirmation':
            $orderNumber = $_GET['num'] ?? '';
            $trackingHash = $_GET['h'] ?? '';
            $order = null;
            if (!empty($trackingHash)) {
                $order = Order::getByTrackingHash($trackingHash);
            } else if (!empty($orderNumber)) {
                $order = Order::getByOrderNumber($orderNumber);
            }
            if (!$order) {
                header('Location: /');
                exit;
            }
            require __DIR__ . '/../app/views/confirmation.php';
            break;

        case '/track':
            $foundOrder = null;
            if (!empty($_GET['h'])) {
                $foundOrder = Order::getByTrackingHash($_GET['h']);
            } else if (!empty($_GET['num'])) {
                $foundOrder = Order::getByOrderNumber(trim($_GET['num']));
            } else if (!empty($_GET['phone'])) {
                $phoneOrders = Order::getByPhone(trim($_GET['phone']));
                $foundOrder = !empty($phoneOrders) ? $phoneOrders[0] : null;
            }
            require __DIR__ . '/../app/views/track.php';
            break;

        // ------------------------------------------------
        // Admin Routes
        // ------------------------------------------------
        case '/admin/login':
            if (AuthService::check()) {
                header('Location: /admin');
                exit;
            }
            $loginError = '';
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    $loginError = 'Session token mismatch. Please try again.';
                } else {
                    $username = trim($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $res = AuthService::login($username, $password, $clientIp);
                    if ($res['success']) {
                        $redirect = $_SESSION['admin_intended_url'] ?? '/admin';
                        unset($_SESSION['admin_intended_url']);
                        header('Location: ' . $redirect);
                        exit;
                    } else {
                        $loginError = $res['error'];
                    }
                }
            }
            require __DIR__ . '/../app/views/admin/login.php';
            break;

        case '/admin/logout':
            if ($method === 'POST') {
                verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '');
            }
            AuthService::logout();
            set_flash('info', 'You have been safely logged out of Store Admin.');
            header('Location: /admin/login');
            exit;
            break;

        case '/admin':
        case '/admin/dashboard':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/dashboard.php';
            break;

        case '/admin/orders':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/orders.php';
            break;

        case '/admin/orders/view':
            AuthService::requireAdmin();
            $orderId = (int)($_GET['id'] ?? 0);
            $order = Order::getById($orderId);
            if (!$order) {
                set_flash('error', 'Order not found.');
                header('Location: /admin/orders');
                exit;
            }
            require __DIR__ . '/../app/views/admin/order-detail.php';
            break;

        case '/admin/orders/update-status':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'CSRF verification failed.');
                    header('Location: /admin/orders');
                    exit;
                }
                $orderId = (int)($_POST['order_id'] ?? 0);
                $newStatus = trim($_POST['new_status'] ?? '');
                $comment = trim($_POST['comment'] ?? 'Status updated by admin');
                $admin = AuthService::user();
                $changedBy = $admin['name'] ?? 'Admin';

                $success = Order::updateStatus($orderId, $newStatus, $comment, $changedBy);
                if ($success) {
                    $statusEventMap = [
                        'confirmed' => 'order_confirmed',
                        'preparing' => 'being_prepared',
                        'ready' => 'order_ready',
                        'out_for_delivery' => 'out_for_delivery',
                        'delivered' => 'order_delivered',
                        'cancelled' => 'order_cancelled'
                    ];
                    if (isset($statusEventMap[$newStatus])) {
                        NotificationService::sendOrderStatusToCustomer($orderId, $statusEventMap[$newStatus]);
                    }
                    set_flash('success', "Order #$orderId updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . ".");
                } else {
                    set_flash('error', 'Failed to update order status.');
                }
                header('Location: /admin/orders/view?id=' . $orderId);
                exit;
            }
            header('Location: /admin/orders');
            break;

        case '/admin/invoices':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/invoices.php';
            break;

        case '/admin/invoices/view':
            AuthService::requireAdmin();
            $orderId = (int)($_GET['order_id'] ?? ($_GET['id'] ?? 0));
            $order = $orderId > 0 ? Order::getById($orderId) : null;
            if (!$order) {
                set_flash('error', 'Order not found.');
                header('Location: /admin/invoices');
                exit;
            }
            echo InvoiceService::renderHtml($order);
            exit;
            break;

        case '/admin/invoices/download':
            AuthService::requireAdmin();
            $orderId = (int)($_GET['order_id'] ?? ($_GET['id'] ?? 0));
            $order = $orderId > 0 ? Order::getById($orderId) : null;
            if (!$order) {
                set_flash('error', 'Order not found.');
                header('Location: /admin/invoices');
                exit;
            }
            InvoiceService::streamPdf($order);
            exit;
            break;

        case '/admin/products':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/products.php';
            break;

        case '/admin/products/new':
            AuthService::requireAdmin();
            $product = null;
            require __DIR__ . '/../app/views/admin/product-form.php';
            break;

        case '/admin/products/edit':
            AuthService::requireAdmin();
            $productId = (int)($_GET['id'] ?? 0);
            $product = Product::getById($productId);
            if (!$product) {
                set_flash('error', 'Product not found.');
                header('Location: /admin/products');
                exit;
            }
            require __DIR__ . '/../app/views/admin/product-form.php';
            break;

        case '/admin/products/create':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token mismatch.');
                    header('Location: /admin/products');
                    exit;
                }

                $imageUrl = trim($_POST['image_url'] ?? '/assets/images/product-main.png');
                if (!empty($_FILES['product_image']['name']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['product_image']['tmp_name'];
                    $fileSize = $_FILES['product_image']['size'];
                    if ($fileSize <= MAX_UPLOAD_SIZE) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $fileTmp);
                        finfo_close($finfo);
                        $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                        if (isset($mimeToExt[$mime])) {
                            $ext = $mimeToExt[$mime];
                            $safeName = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            if (move_uploaded_file($fileTmp, UPLOAD_DIR . $safeName)) {
                                $imageUrl = '/assets/uploads/' . $safeName;
                            }
                        }
                    }
                }

                $createData = [
                    'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                    'name' => trim($_POST['name'] ?? ''),
                    'slug' => trim($_POST['slug'] ?? ''),
                    'tagline' => trim($_POST['tagline'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'price' => max(0.0, (float)($_POST['price'] ?? 0)),
                    'original_price' => !empty($_POST['original_price']) ? (float)$_POST['original_price'] : null,
                    'stock_quantity' => max(0, (int)($_POST['stock_quantity'] ?? 0)),
                    'is_available' => isset($_POST['is_available']) ? 1 : 0,
                    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                    'has_cutting_options' => isset($_POST['has_cutting_options']) ? 1 : 0,
                    'cutting_options_json' => !empty($_POST['cutting_options_json']) ? $_POST['cutting_options_json'] : null,
                    'image_url' => $imageUrl
                ];

                Product::create($createData);
                set_flash('success', 'Product created successfully in MySQL.');
                header('Location: /admin/products');
                exit;
            }
            header('Location: /admin/products');
            break;

        case '/admin/products/update':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token mismatch.');
                    header('Location: /admin/products');
                    exit;
                }

                $productId = (int)($_POST['product_id'] ?? 1);
                $imageUrl = trim($_POST['image_url'] ?? '/assets/images/product-main.png');

                // Handle image upload if provided
                if (!empty($_FILES['product_image']['name']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['product_image']['tmp_name'];
                    $fileSize = $_FILES['product_image']['size'];

                    if ($fileSize <= MAX_UPLOAD_SIZE) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $fileTmp);
                        finfo_close($finfo);

                        $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                        if (isset($mimeToExt[$mime])) {
                            $ext = $mimeToExt[$mime];
                            $safeName = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            if (move_uploaded_file($fileTmp, UPLOAD_DIR . $safeName)) {
                                $imageUrl = '/assets/uploads/' . $safeName;
                            }
                        }
                    }
                }

                $updateData = [
                    'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                    'name' => trim($_POST['name'] ?? ''),
                    'slug' => trim($_POST['slug'] ?? ''),
                    'tagline' => trim($_POST['tagline'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'price' => max(0.0, (float)($_POST['price'] ?? 0)),
                    'original_price' => !empty($_POST['original_price']) ? (float)$_POST['original_price'] : null,
                    'stock_quantity' => max(0, (int)($_POST['stock_quantity'] ?? 0)),
                    'is_available' => isset($_POST['is_available']) ? 1 : 0,
                    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                    'has_cutting_options' => isset($_POST['has_cutting_options']) ? 1 : 0,
                    'cutting_options_json' => !empty($_POST['cutting_options_json']) ? $_POST['cutting_options_json'] : null,
                    'image_url' => $imageUrl
                ];

                Product::update($productId, $updateData);
                set_flash('success', 'Product details and stock successfully saved in MySQL.');
                header('Location: /admin/products');
                exit;
            }
            header('Location: /admin/products');
            break;

        case '/admin/products/delete':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    $prodId = (int)($_POST['product_id'] ?? 0);
                    if ($prodId > 0) {
                        Product::delete($prodId);
                        set_flash('info', 'Product removed from catalog.');
                    }
                }
            }
            header('Location: /admin/products');
            break;

        // Categories Management
        case '/admin/categories':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/categories.php';
            break;

        case '/admin/categories/save':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token mismatch.');
                    header('Location: /admin/categories');
                    exit;
                }

                $catId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'slug' => trim($_POST['slug'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'sort_order' => (int)($_POST['sort_order'] ?? 0),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];

                if ($catId > 0) {
                    Category::update($catId, $data);
                    set_flash('success', 'Category updated successfully.');
                } else {
                    Category::create($data);
                    set_flash('success', 'New category created.');
                }
            }
            header('Location: /admin/categories');
            break;

        case '/admin/categories/delete':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    $catId = (int)($_POST['category_id'] ?? 0);
                    if ($catId > 0) {
                        Category::delete($catId);
                        set_flash('info', 'Category removed.');
                    }
                }
            }
            header('Location: /admin/categories');
            break;

        // Combo Offers & Bundles Management
        case '/admin/combos':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/combos.php';
            break;

        case '/admin/combos/new':
            AuthService::requireAdmin();
            $combo = null;
            require __DIR__ . '/../app/views/admin/combo-form.php';
            break;

        case '/admin/combos/edit':
            AuthService::requireAdmin();
            $comboId = (int)($_GET['id'] ?? 0);
            $combo = Combo::getById($comboId);
            if (!$combo) {
                set_flash('error', 'Combo not found.');
                header('Location: /admin/combos');
                exit;
            }
            require __DIR__ . '/../app/views/admin/combo-form.php';
            break;

        case '/admin/combos/create':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token mismatch.');
                    header('Location: /admin/combos');
                    exit;
                }

                $items = !empty($_POST['items_json']) ? json_decode($_POST['items_json'], true) : [];
                $comboData = [
                    'name' => trim($_POST['name'] ?? ''),
                    'slug' => trim($_POST['slug'] ?? ''),
                    'tagline' => trim($_POST['tagline'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'price' => max(0.0, (float)($_POST['price'] ?? 0)),
                    'original_price' => max(0.0, (float)($_POST['original_price'] ?? 0)),
                    'image_url' => trim($_POST['image_url'] ?? '/assets/images/hero-coconut.png'),
                    'stock_quantity' => max(0, (int)($_POST['stock_quantity'] ?? 50)),
                    'is_available' => isset($_POST['is_available']) ? 1 : 0
                ];

                Combo::create($comboData, is_array($items) ? $items : []);
                set_flash('success', 'Combo offer created successfully.');
                header('Location: /admin/combos');
                exit;
            }
            header('Location: /admin/combos');
            break;

        case '/admin/combos/update':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token mismatch.');
                    header('Location: /admin/combos');
                    exit;
                }

                $comboId = (int)($_POST['combo_id'] ?? 0);
                $items = !empty($_POST['items_json']) ? json_decode($_POST['items_json'], true) : [];
                $comboData = [
                    'name' => trim($_POST['name'] ?? ''),
                    'slug' => trim($_POST['slug'] ?? ''),
                    'tagline' => trim($_POST['tagline'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'price' => max(0.0, (float)($_POST['price'] ?? 0)),
                    'original_price' => max(0.0, (float)($_POST['original_price'] ?? 0)),
                    'image_url' => trim($_POST['image_url'] ?? '/assets/images/hero-coconut.png'),
                    'stock_quantity' => max(0, (int)($_POST['stock_quantity'] ?? 50)),
                    'is_available' => isset($_POST['is_available']) ? 1 : 0
                ];

                Combo::update($comboId, $comboData, is_array($items) ? $items : []);
                set_flash('success', 'Combo offer updated successfully.');
                header('Location: /admin/combos');
                exit;
            }
            header('Location: /admin/combos');
            break;

        case '/admin/combos/delete':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    $comboId = (int)($_POST['combo_id'] ?? 0);
                    if ($comboId > 0) {
                        Combo::delete($comboId);
                        set_flash('info', 'Combo offer deleted.');
                    }
                }
            }
            header('Location: /admin/combos');
            break;

        // ------------------------------------------------
        // Admin Coupon Routes
        // ------------------------------------------------
        case '/admin/coupons':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/coupons.php';
            break;

        case '/admin/coupons/create':
            AuthService::requireAdmin();
            $coupon = null;
            require __DIR__ . '/../app/views/admin/coupon-form.php';
            break;

        case '/admin/coupons/store':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token mismatch. Please try again.');
                    header('Location: /admin/coupons/create');
                    exit;
                }
                $res = Coupon::create($_POST);
                if ($res['success']) {
                    set_flash('success', 'Coupon ' . strtoupper(trim($_POST['code'])) . ' created successfully.');
                    header('Location: /admin/coupons');
                    exit;
                } else {
                    set_flash('error', $res['error']);
                    header('Location: /admin/coupons/create');
                    exit;
                }
            }
            header('Location: /admin/coupons');
            break;

        case '/admin/coupons/edit':
            AuthService::requireAdmin();
            $couponId = (int)($_GET['id'] ?? 0);
            $coupon = Coupon::getById($couponId);
            if (!$coupon) {
                set_flash('error', 'Coupon not found.');
                header('Location: /admin/coupons');
                exit;
            }
            require __DIR__ . '/../app/views/admin/coupon-form.php';
            break;

        case '/admin/coupons/update':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token mismatch.');
                    header('Location: /admin/coupons');
                    exit;
                }
                $couponId = (int)($_POST['coupon_id'] ?? 0);
                $res = Coupon::update($couponId, $_POST);
                if ($res['success']) {
                    set_flash('success', 'Coupon successfully updated.');
                    header('Location: /admin/coupons');
                    exit;
                } else {
                    set_flash('error', $res['error']);
                    header('Location: /admin/coupons/edit?id=' . $couponId);
                    exit;
                }
            }
            header('Location: /admin/coupons');
            break;

        case '/admin/coupons/toggle-status':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'CSRF verification failed.');
                    header('Location: /admin/coupons');
                    exit;
                }
                $couponId = (int)($_POST['coupon_id'] ?? 0);
                Coupon::toggleStatus($couponId);
                set_flash('success', 'Coupon status updated.');
            }
            header('Location: /admin/coupons');
            break;

        case '/admin/coupons/delete':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'CSRF verification failed.');
                    header('Location: /admin/coupons');
                    exit;
                }
                $couponId = (int)($_POST['coupon_id'] ?? 0);
                $success = Coupon::delete($couponId);
                if ($success) {
                    set_flash('success', 'Coupon successfully deleted or deactivated.');
                } else {
                    set_flash('error', 'Failed to delete coupon.');
                }
            }
            header('Location: /admin/coupons');
            break;

        case '/admin/coupons/view':
            AuthService::requireAdmin();
            $couponId = (int)($_GET['id'] ?? 0);
            $coupon = Coupon::getById($couponId);
            if (!$coupon) {
                set_flash('error', 'Coupon not found.');
                header('Location: /admin/coupons');
                exit;
            }
            require __DIR__ . '/../app/views/admin/coupon-detail.php';
            break;

        case '/admin/customers':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/customers.php';
            break;

        case '/admin/customers/view':
            AuthService::requireAdmin();
            $customerId = (int)($_GET['id'] ?? 0);
            $customer = Customer::findById($customerId);
            if (!$customer) {
                set_flash('error', 'Customer not found.');
                header('Location: /admin/customers');
                exit;
            }
            require __DIR__ . '/../app/views/admin/customer-detail.php';
            break;

        case '/admin/settings':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/settings.php';
            break;

        case '/admin/settings/update':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token expired.');
                    header('Location: /admin/settings');
                    exit;
                }

                $waUrl = trim($_POST['whatsapp_channel_url'] ?? '');
                if (!empty($waUrl) && !str_starts_with($waUrl, 'https://')) {
                    set_flash('error', 'WhatsApp Channel Link must be a valid secure HTTPS link (e.g. https://whatsapp.com/channel/...).');
                    header('Location: /admin/settings');
                    exit;
                }

                $settingsData = [
                    'local_delivery_enabled' => isset($_POST['local_delivery_enabled']) ? '1' : '0',
                    'shop_pickup_enabled' => isset($_POST['shop_pickup_enabled']) ? '1' : '0',
                    'delivery_charge' => number_format((float)($_POST['delivery_charge'] ?? 40), 2, '.', ''),
                    'min_order_amount' => number_format((float)($_POST['min_order_amount'] ?? 40), 2, '.', ''),
                    'free_delivery_above' => number_format((float)($_POST['free_delivery_above'] ?? 500), 2, '.', ''),
                    'service_areas' => trim($_POST['service_areas'] ?? ''),
                    'store_name' => trim($_POST['store_name'] ?? 'N.A Fresh Fruits & Coconuts'),
                    'store_phone' => trim($_POST['store_phone'] ?? '+91 98765 43210'),
                    'store_address' => trim($_POST['store_address'] ?? ''),
                    'store_open_time' => trim($_POST['store_open_time'] ?? '07:00 AM'),
                    'store_close_time' => trim($_POST['store_close_time'] ?? '09:00 PM'),
                    'whatsapp_popup_enabled' => isset($_POST['whatsapp_popup_enabled']) ? '1' : '0',
                    'whatsapp_channel_url' => $waUrl,
                    'whatsapp_popup_heading' => trim($_POST['whatsapp_popup_heading'] ?? 'Join Our WhatsApp Channel'),
                    'whatsapp_popup_message' => trim($_POST['whatsapp_popup_message'] ?? 'Stay updated with fresh daily harvest arrivals, exclusive seasonal offers, and health tips!'),
                    'whatsapp_popup_button_text' => trim($_POST['whatsapp_popup_button_text'] ?? 'Join WhatsApp Channel'),
                    'whatsapp_popup_frequency' => in_array($_POST['whatsapp_popup_frequency'] ?? '', ['session', 'daily', 'weekly', 'always']) ? $_POST['whatsapp_popup_frequency'] : 'session',
                ];

                $settingsData['pwa_push_enabled'] = isset($_POST['pwa_push_enabled']) ? '1' : '0';
                $settingsData['pwa_admin_order_alerts'] = isset($_POST['pwa_admin_order_alerts']) ? '1' : '0';
                $settingsData['pwa_customer_order_alerts'] = isset($_POST['pwa_customer_order_alerts']) ? '1' : '0';

                Setting::setMany($settingsData);
                set_flash('success', 'Store delivery, business, and PWA notification settings successfully saved.');
                header('Location: /admin/settings');
                exit;
            }
            header('Location: /admin/settings');
            break;

        // ------------------------------------------------
        // PWA Web Push Notification Routes
        // ------------------------------------------------
        case '/admin/notifications':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/notifications.php';
            break;

        case '/admin/notifications/send':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true) ?? $_POST;
                $token = $input['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
                if (!verify_csrf_token($token)) {
                    json_response(['success' => false, 'error' => 'Security token invalid or expired.'], 403);
                }

                $audience = trim($input['audience'] ?? 'all_customers');
                $notifType = trim($input['type'] ?? 'promo');

                // Enforce Superadmin permission for bulk promotional notifications
                $admin = AuthService::user();
                if ($audience === 'all_customers' && $notifType === 'promo' && ($admin['role'] ?? '') !== 'superadmin') {
                    json_response(['success' => false, 'error' => 'Only Superadmins can broadcast bulk promotional notifications.'], 403);
                }

                $targetCust = $input['customer_id'] ?? null;
                $res = NotificationService::sendBroadcast([
                    'title' => $input['title'] ?? '',
                    'body' => $input['body'] ?? '',
                    'image' => $input['image'] ?? null,
                    'url' => $input['url'] ?? '/',
                    'type' => $notifType
                ], $audience, $targetCust, (int)($admin['id'] ?? 0));

                json_response($res);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        case '/admin/notifications/test-admin':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                $pdo = Database::getConnection();
                $stmt = $pdo->query("SELECT * FROM `push_subscriptions` WHERE `user_type` = 'admin'");
                $adminSubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                if (empty($adminSubs)) {
                    json_response(['success' => false, 'error' => 'No admin devices currently enrolled. Click "Enrol Current Admin Device" first.'], 400);
                }

                $sent = 0;
                $failed = 0;
                $testPayload = [
                    'title' => 'Admin Push Test Alert 🥥',
                    'body' => 'Web Push notification is active and communicating with your device!',
                    'icon' => '/assets/images/icons/icon-192.png',
                    'badge' => '/assets/images/icons/badge-72.png',
                    'tag' => 'admin-test-' . time(),
                    'data' => ['url' => '/admin/notifications']
                ];

                foreach ($adminSubs as $sub) {
                    $res = WebPushService::sendNotification($sub, $testPayload);
                    if ($res['success']) {
                        $sent++;
                    } else {
                        $failed++;
                        if (!empty($res['expired'])) {
                            NotificationService::removeSubscriptionById((int)$sub['id']);
                        }
                    }
                }

                json_response([
                    'success' => true,
                    'total' => count($adminSubs),
                    'sent' => $sent,
                    'failed' => $failed
                ]);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        case '/api/push/vapid-public-key':
            try {
                $pubKey = WebPushService::getPublicKey();
                json_response(['success' => true, 'publicKey' => $pubKey]);
            } catch (Throwable $e) {
                json_response(['success' => false, 'error' => $e->getMessage()], 500);
            }
            break;

        case '/api/push/subscribe':
            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true) ?? $_POST;

                // Enforce CSRF protection
                $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ($input['_csrf_token'] ?? ''));
                if (!verify_csrf_token($csrfToken)) {
                    json_response(['success' => false, 'error' => 'Security token expired. Please refresh the page and try again.'], 403);
                }

                $endpoint = trim($input['endpoint'] ?? '');
                $p256dh = trim($input['p256dh'] ?? '');
                $auth = trim($input['auth'] ?? '');
                $userType = trim($input['user_type'] ?? 'customer');

                $userId = null;
                if ($userType === 'admin') {
                    if (!AuthService::check()) {
                        json_response(['success' => false, 'error' => 'Admin authentication required to enroll this device as Admin.'], 401);
                    }
                    $userId = (int)AuthService::adminId();
                } elseif (CustomerAuthService::check()) {
                    $userId = (int)CustomerAuthService::customerId();
                    $userType = 'customer';
                } else {
                    $userType = 'guest';
                    $_SESSION['guest_push_endpoint_hash'] = hash('sha256', $endpoint);
                }

                $res = NotificationService::subscribe($endpoint, $p256dh, $auth, $userType, $userId);
                json_response($res);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        case '/api/push/device-status':
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? $_POST;
            $endpoint = trim($input['endpoint'] ?? ($_GET['endpoint'] ?? ''));

            if (empty($endpoint)) {
                json_response(['success' => false, 'error' => 'Endpoint parameter required'], 400);
            }

            $status = NotificationService::getDeviceStatus($endpoint);
            $status['success'] = true;
            $status['is_admin_logged_in'] = AuthService::check();
            $status['is_customer_logged_in'] = CustomerAuthService::check();
            json_response($status);
            break;

        case '/api/push/test-device':
            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true) ?? $_POST;

                $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ($input['_csrf_token'] ?? ''));
                if (!verify_csrf_token($csrfToken)) {
                    json_response(['success' => false, 'error' => 'Security token expired.'], 403);
                }

                $endpoint = trim($input['endpoint'] ?? '');
                $role = trim($input['role'] ?? 'admin');

                if ($role === 'admin' && !AuthService::check()) {
                    json_response(['success' => false, 'error' => 'Admin authorization required.'], 401);
                }

                $res = NotificationService::sendTestToDevice($endpoint, $role);
                json_response($res);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        case '/api/push/unsubscribe':
            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true) ?? $_POST;

                $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ($input['_csrf_token'] ?? ''));
                if (!verify_csrf_token($csrfToken)) {
                    json_response(['success' => false, 'error' => 'Security token expired.'], 403);
                }

                $endpoint = trim($input['endpoint'] ?? '');
                $success = NotificationService::unsubscribe($endpoint);
                json_response(['success' => $success]);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        case '/account/notifications/preferences':
            CustomerAuthService::requireLogin('/account');
            if ($method === 'POST') {
                $token = $_POST[CSRF_TOKEN_KEY] ?? '';
                if (!verify_csrf_token($token)) {
                    set_flash('error', 'Security token expired.');
                    header('Location: /account');
                    exit;
                }
                $customerId = (int)CustomerAuthService::customerId();
                $orderUpdates = isset($_POST['order_updates']);
                $promotions = isset($_POST['promotions']);

                NotificationService::setCustomerPreferences($customerId, $orderUpdates, $promotions);
                set_flash('success', 'Notification preferences saved successfully.');
                header('Location: /account');
                exit;
            }
            header('Location: /account');
            break;

        case '/admin/reports':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/reports.php';
            break;

        // ------------------------------------------------
        // Admin Delivery Areas Routes
        // ------------------------------------------------
        case '/admin/areas':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/areas.php';
            break;

        case '/admin/areas/create':
            AuthService::requireAdmin();
            $area = null;
            require __DIR__ . '/../app/views/admin/area-form.php';
            break;

        case '/admin/areas/store':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'CSRF verification failed.');
                    header('Location: /admin/areas/create');
                    exit;
                }
                $res = DeliveryArea::create($_POST);
                if ($res['success']) {
                    set_flash('success', 'Delivery area ' . e($_POST['name']) . ' created successfully.');
                    header('Location: /admin/areas');
                    exit;
                } else {
                    set_flash('error', $res['error']);
                    header('Location: /admin/areas/create');
                    exit;
                }
            }
            header('Location: /admin/areas');
            break;

        case '/admin/areas/edit':
            AuthService::requireAdmin();
            $areaId = (int)($_GET['id'] ?? 0);
            $area = DeliveryArea::getById($areaId);
            if (!$area) {
                set_flash('error', 'Delivery area not found.');
                header('Location: /admin/areas');
                exit;
            }
            require __DIR__ . '/../app/views/admin/area-form.php';
            break;

        case '/admin/areas/update':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'CSRF verification failed.');
                    header('Location: /admin/areas');
                    exit;
                }
                $areaId = (int)($_POST['area_id'] ?? 0);
                $res = DeliveryArea::update($areaId, $_POST);
                if ($res['success']) {
                    set_flash('success', 'Delivery area and map boundary successfully updated.');
                    header('Location: /admin/areas');
                    exit;
                } else {
                    set_flash('error', $res['error']);
                    header('Location: /admin/areas/edit?id=' . $areaId);
                    exit;
                }
            }
            header('Location: /admin/areas');
            break;

        case '/admin/areas/toggle-status':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'CSRF verification failed.');
                } else {
                    $areaId = (int)($_POST['area_id'] ?? 0);
                    DeliveryArea::toggleStatus($areaId);
                    set_flash('success', 'Area status updated.');
                }
            }
            header('Location: /admin/areas');
            break;

        case '/admin/areas/delete':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'CSRF verification failed.');
                } else {
                    $areaId = (int)($_POST['area_id'] ?? 0);
                    $delRes = DeliveryArea::delete($areaId);
                    if ($delRes['success']) {
                        set_flash('success', $delRes['message']);
                    } else {
                        set_flash('error', 'Failed to delete area.');
                    }
                }
            }
            header('Location: /admin/areas');
            break;

        // ------------------------------------------------
        // Public API: Check Delivery Location & Charge
        // ------------------------------------------------
        case '/api/check-delivery-location':
            if ($method === 'POST') {
                $rawInput = file_get_contents('php://input');
                $jsonData = json_decode($rawInput, true) ?: [];

                $rawLat = $_POST['lat'] ?? ($jsonData['lat'] ?? ($jsonData['latitude'] ?? null));
                $rawLng = $_POST['lng'] ?? ($jsonData['lng'] ?? ($jsonData['longitude'] ?? null));
                $rawAreaId = $_POST['area_id'] ?? ($jsonData['area_id'] ?? null);

                $lat = ($rawLat !== null && is_numeric($rawLat)) ? (float)$rawLat : null;
                $lng = ($rawLng !== null && is_numeric($rawLng)) ? (float)$rawLng : null;
                $areaId = ($rawAreaId !== null && is_numeric($rawAreaId)) ? (int)$rawAreaId : null;

                $eligibility = AreaService::checkDeliveryEligibility($lat, $lng, $areaId);
                json_response([
                    'success' => true,
                    'deliverable' => $eligibility['deliverable'],
                    'area_id' => $eligibility['area_id'],
                    'area_name' => $eligibility['area_name'],
                    'delivery_charge' => $eligibility['delivery_charge'],
                    'message' => $eligibility['message']
                ]);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        case '/admin/payments':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/payments.php';
            break;

        case '/admin/payment-settings':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/payment-settings.php';
            break;

        case '/admin/payment-settings/update':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token expired. Please refresh the page.');
                    header('Location: /admin/payment-settings');
                    exit;
                }

                $res = RazorpayService::saveSettings($_POST);
                if ($res['success']) {
                    set_flash('success', 'Razorpay credentials, webhook secret, and COD settings successfully saved.');
                } else {
                    set_flash('error', $res['error'] ?? 'Failed to update payment settings.');
                }
                header('Location: /admin/payment-settings');
                exit;
            }
            header('Location: /admin/payment-settings');
            break;

        case '/admin/payment-settings/test-connection':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                $testRes = RazorpayService::testConnection();
                json_response($testRes);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        // ------------------------------------------------
        // Live Traffic & Visitor Analytics Routes
        // ------------------------------------------------
        case '/admin/analytics':
            AuthService::requireAdmin();
            $rangePreset = $_GET['range'] ?? 'today';
            $customFrom = $_GET['from'] ?? null;
            $customTo = $_GET['to'] ?? null;
            $dateRange = AnalyticsService::resolveDateRange($rangePreset, $customFrom, $customTo);
            $metrics = AnalyticsService::getDashboardMetrics($dateRange);
            $liveCount = AnalyticsService::getActiveVisitorCount();
            $liveVisitors = AnalyticsService::getActiveVisitors(15);
            require __DIR__ . '/../app/views/admin/analytics.php';
            break;

        case '/api/analytics/track':
            if ($method === 'POST') {
                $rawInput = file_get_contents('php://input');
                $jsonData = json_decode($rawInput, true) ?: [];
                $payload = !empty($jsonData) ? $jsonData : $_POST;
                $res = AnalyticsService::recordHit($payload);
                json_response($res);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        case '/api/analytics/heartbeat':
            if ($method === 'POST') {
                $rawInput = file_get_contents('php://input');
                $jsonData = json_decode($rawInput, true) ?: [];
                $sessionToken = $jsonData['session_token'] ?? ($_POST['session_token'] ?? '');
                $path = $jsonData['path'] ?? ($_POST['path'] ?? '/');
                if (!empty($sessionToken)) {
                    AnalyticsService::recordHeartbeat($sessionToken, $path);
                    json_response(['success' => true]);
                }
                json_response(['success' => false, 'error' => 'Missing session token'], 400);
            }
            json_response(['success' => false, 'error' => 'Method not allowed'], 405);
            break;

        case '/api/analytics/live':
            AuthService::requireAdmin();
            $count = AnalyticsService::getActiveVisitorCount();
            $visitors = AnalyticsService::getActiveVisitors(15);
            json_response([
                'success' => true,
                'live_count' => $count,
                'visitors' => $visitors,
                'timestamp' => date('h:i:s A')
            ]);
            break;

        // Proper 404 handler for missing routes
        default:
            http_response_code(404);
            echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>404 Page Not Found - N.A Fresh Fruits & Coconuts</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-[#f8faf8] flex items-center justify-center min-h-screen text-center p-4'><div class='bg-white p-8 rounded-3xl shadow-xs border border-gray-100 max-w-md w-full space-y-4'><div class='w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-[#235d3a] flex items-center justify-center font-black text-2xl'>NA</div><h1 class='text-4xl font-extrabold text-gray-900'>404</h1><p class='text-sm text-gray-500'>The page you requested could not be found.</p><div class='pt-2'><a href='/' class='bg-[#235d3a] hover:bg-[#1b4a2e] text-white text-xs font-bold py-3 px-6 rounded-xl transition inline-block'>Back to Storefront</a></div></div></body></html>";
            break;
    }
} catch (Exception $e) {
    // State D: Safe Configuration / Database Error Handler
    error_log("Application Execution Error: " . $e->getMessage());
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Error - N.A Fresh Fruits & Coconuts</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-[#f8faf8] flex items-center justify-center min-h-screen p-4 text-center">
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 max-w-md w-full space-y-4">
            <div class="w-14 h-14 mx-auto rounded-full bg-red-50 text-red-600 flex items-center justify-center">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900">Database Connection Notice</h1>
            <p class="text-xs text-gray-500 leading-relaxed">
                The store could not establish a connection to MySQL database. Please verify your MySQL server status or re-configure credentials.
            </p>
            <div class="pt-2 flex flex-col gap-2">
                <button onclick="window.location.reload()" class="bg-[#235d3a] text-white text-xs font-bold py-2.5 px-4 rounded-xl hover:bg-[#1b4a2e] transition shadow-xs">
                    Retry Connection
                </button>
                <?php if (is_installed() && AuthService::check()): ?>
                <a href="/install?admin_repair=1" class="bg-emerald-50 text-emerald-800 text-xs font-semibold py-2.5 px-4 rounded-xl border border-emerald-200 hover:bg-emerald-100 transition">
                    Run Administrator Database Diagnostics
                </a>
                <?php elseif (!is_installed()): ?>
                <a href="/install?step=1" class="bg-gray-100 text-gray-700 text-xs font-semibold py-2.5 px-4 rounded-xl hover:bg-gray-200 transition">
                    Run Web Installation Wizard
                </a>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
}
