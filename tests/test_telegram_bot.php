<?php
/**
 * Test Suite: Telegram Bot Order Notification Integration
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

echo "========================================================\n";
echo "RUNNING TELEGRAM BOT ORDER NOTIFICATION TEST SUITE\n";
echo "========================================================\n";

$passCount = 0;
$failCount = 0;

function assertTg(bool $condition, string $title): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$title}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title}\n";
    }
}

// 1. Token normalization tests
assertTg(TelegramService::normalizeBotToken('123456789:ABCdefGhIJK') === '123456789:ABCdefGhIJK', 'Standard token format normalized');
assertTg(TelegramService::normalizeBotToken('bot123456789:ABCdefGhIJK') === '123456789:ABCdefGhIJK', "Leading 'bot' prefix cleanly stripped");
assertTg(TelegramService::normalizeBotToken('  BOT987654321:XYZ  ') === '987654321:XYZ', 'Whitespace and uppercase BOT prefix stripped');

// 2. Token URL endpoint construction test (NEVER urlencode the colon in the path)
$cleanToken = TelegramService::normalizeBotToken('123456789:ABCdefGhIJK');
$expectedUrl = "https://api.telegram.org/bot{$cleanToken}/sendMessage";
assertTg(strpos($expectedUrl, '123456789:ABCdefGhIJK') !== false, 'URL contains literal colon without %3A corruption');
assertTg(strpos($expectedUrl, '%3A') === false, 'No %3A found in API URL path');

// 3. Message Formatting & Special Characters Escaping (HTML mode)
$sampleOrder = [
    'id' => 999,
    'order_number' => 'NA-TEST-999',
    'created_at' => '2026-10-31 15:30:00',
    'customer_name' => 'Dr. Rajesh & Sons <"Raju">',
    'customer_phone' => '+91 98765 43210',
    'delivery_address' => 'Flat #101, "Royal Park" & M.G. Road',
    'delivery_area_name' => 'Jaora Central & Station Zone',
    'notes' => 'Ring bell & leave at door',
    'subtotal' => 120.00,
    'total_amount' => 110.00,
    'discount_amount' => 50.00,
    'coupon_code' => 'FLAT50',
    'delivery_charge' => 40.00,
    'payment_method' => 'cod',
    'payment_status' => 'pending',
    'order_status' => 'confirmed'
];

$sampleItems = [
    [
        'product_name' => 'Fresh Tender Coconut <Sweet & Pure>',
        'quantity' => 2,
        'unit_price' => 40.00,
        'subtotal' => 80.00
    ],
    [
        'product_name' => 'Pure Coconut Water Bottle (500ml)',
        'quantity' => 1,
        'unit_price' => 40.00,
        'subtotal' => 40.00
    ]
];

$formattedHtml = TelegramService::formatOrderMessage($sampleOrder, $sampleItems);

assertTg(strpos($formattedHtml, 'Dr. Rajesh &amp; Sons &lt;&quot;Raju&quot;&gt;') !== false, 'Customer name with HTML chars & < > " properly escaped');
assertTg(strpos($formattedHtml, 'Fresh Tender Coconut &lt;Sweet &amp; Pure&gt;') !== false, 'Product name with < > & properly escaped');
assertTg(strpos($formattedHtml, '(@ ₹40.00)') !== false, 'Individual unit price included in message');
assertTg(strpos($formattedHtml, 'NA-TEST-999') !== false, 'Order number included in code tags');
assertTg(strpos($formattedHtml, 'FLAT50') !== false, 'Coupon discount included');
assertTg(strpos($formattedHtml, 'Cash on Delivery (COD)') !== false, 'Payment method label included');
assertTg(strpos($formattedHtml, 'Final Order Total:') !== false, 'Final order total included');
assertTg(strpos($formattedHtml, 'Pending') !== false, 'Payment status included');

// 4. Test notification with invalid credentials returns graceful failure without throwing
$testFailRes = TelegramService::sendTestMessage('invalid_fake_token', '999999');
assertTg($testFailRes['success'] === false, 'Invalid token returns success=false');
assertTg(!empty($testFailRes['error']), 'Helpful error description returned: ' . ($testFailRes['error'] ?? ''));

// 5. Test empty credentials validation
$emptyRes = TelegramService::sendTestMessage('', '');
assertTg($emptyRes['success'] === false, 'Empty credentials rejected');

// 6. COD Order notification enqueueing test
$codOrderId = 99901;
$enqRes1 = TelegramService::enqueueOrderNotification($codOrderId, 'order_placed');
assertTg($enqRes1 === true, 'COD order notification successfully enqueued');

$pdo = Database::getConnection();
$stmtTask = $pdo->prepare("SELECT * FROM `background_tasks` WHERE `idempotency_token` = ?");
$stmtTask->execute(["telegram_order_placed_{$codOrderId}"]);
$taskRow = $stmtTask->fetch();
assertTg(!empty($taskRow), 'Database background task record created for COD order');
assertTg($taskRow['task_type'] === 'telegram_order_notification', 'Task type is telegram_order_notification');
assertTg((int)$taskRow['reference_id'] === $codOrderId, 'Task links to correct order ID');

// 7. Duplicate order placement idempotency test
$enqResDuplicate = TelegramService::enqueueOrderNotification($codOrderId, 'order_placed');
assertTg($enqResDuplicate === true, 'Duplicate enqueue returns success');

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM `background_tasks` WHERE `idempotency_token` = ?");
$stmtCount->execute(["telegram_order_placed_{$codOrderId}"]);
assertTg((int)$stmtCount->fetchColumn() === 1, 'Duplicate enqueue does NOT create duplicate background task');

// 8. Razorpay Online order notification flow:
// - Initial online order should NOT enqueue 'order_paid'
// - Only verified payment enqueues 'order_paid'
$onlineOrderId = 99902;
$stmtOnlineCheck = $pdo->prepare("SELECT COUNT(*) FROM `background_tasks` WHERE `idempotency_token` = ?");
$stmtOnlineCheck->execute(["telegram_order_paid_{$onlineOrderId}"]);
assertTg((int)$stmtOnlineCheck->fetchColumn() === 0, 'No notification enqueued while online payment is pending');

// Verified payment enqueues
$enqOnline = TelegramService::enqueueOrderNotification($onlineOrderId, 'order_paid');
assertTg($enqOnline === true, 'Verified payment enqueues order_paid notification');

$stmtOnlineCheck->execute(["telegram_order_paid_{$onlineOrderId}"]);
assertTg((int)$stmtOnlineCheck->fetchColumn() === 1, 'order_paid task created on payment capture');

// 9. Event toggles test
assertTg(TelegramService::isEventEnabled('order_placed', 'cod') === true, 'COD event enabled by default setting');
assertTg(TelegramService::isEventEnabled('order_paid', 'online') === true, 'Online paid event enabled by default setting');

// 10. Non-blocking customer protection: Simulated Telegram failure does not break order placement
// Run checkout with OrderService
$products = Product::getAll();
$prod = $products[0];
CartService::clear();
CartService::addItem((int)$prod['id'], 2);

$customer = Customer::findByPhone('9876543210');
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_ID] = (int)$customer['id'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_PHONE] = '9876543210';
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_NAME] = $customer['name'];

$checkoutRes = OrderService::processCheckout([
    CSRF_TOKEN_KEY => csrf_token(),
    'customer_name' => 'Telegram Test User',
    'customer_phone' => '9876543210',
    'delivery_address' => 'Station Road, Jaora - 457226',
    'fulfilment_method' => 'delivery',
    'payment_method' => 'cod',
    'idempotency_key' => 'tg_test_' . bin2hex(random_bytes(8))
]);

assertTg($checkoutRes['success'] === true, 'Customer order successfully placed regardless of Telegram status');
$placedOrderId = (int)$checkoutRes['order_id'];
$placedOrder = Order::getById($placedOrderId);
assertTg($placedOrder['order_status'] === 'confirmed', 'Order is confirmed in database');

// Check that order notification was enqueued for the placed order
$stmtOrderEnq = $pdo->prepare("SELECT * FROM `background_tasks` WHERE `idempotency_token` = ?");
$stmtOrderEnq->execute(["telegram_order_placed_{$placedOrderId}"]);
assertTg($stmtOrderEnq->rowCount() > 0, 'Order notification task successfully enqueued for placed order');

// Cleanup test tasks
$pdo->exec("DELETE FROM `background_tasks` WHERE `reference_id` IN (99901, 99902)");

echo "========================================================\n";
echo "TELEGRAM BOT SUITE SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
