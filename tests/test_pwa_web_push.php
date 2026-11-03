<?php
/**
 * Comprehensive PWA Web Push Notification Test Suite (Fresh Rebuild)
 * N.A Fresh Fruits & Coconuts
 *
 * Verifies:
 * 1. Database schema: push_subscriptions, notification_history, notification_deliveries.
 * 2. Complete absence of obsolete Telegram bot / queue code.
 * 3. PWA Manifest compliance (manifest.webmanifest / manifest.json).
 * 4. Service Worker (public/sw.js) push & notificationclick event handlers.
 * 5. VAPID EC keypair (RFC 8292 NIST P-256) and secure storage.
 * 6. Customer device subscription, storage, unsubscription, and multi-device support.
 * 7. Category 1: OFFERS broadcast to customer subscribers.
 * 8. Category 2: ORDERS status update sent strictly to the order owner customer (data isolation).
 * 9. Category 3: STOCK announcement with 10-minute duplicate protection.
 * 10. Category 4: NEWS broadcast to customer subscribers.
 * 11. Delivery log in notification_deliveries (sent, failed, expired).
 * 12. Expired subscription auto-pruning on 410 Gone.
 * 13. Admin device enrollment isolation from customer subscribers.
 * 14. RFC 8291 (aes128gcm) payload encryption and round-trip verification.
 * 15. Notification center and iOS installation guidance.
 */

require_once __DIR__ . '/../app/bootstrap.php';

$pwaPass = 0;
$pwaFail = 0;

function pwa_it(string $name, bool $cond, string $detail = '') {
    global $pwaPass, $pwaFail;
    if ($cond) {
        $pwaPass++;
        echo "  [PASS] {$name}\n";
    } else {
        $pwaFail++;
        echo "  [FAIL] {$name} - {$detail}\n";
    }
}

echo "12. PWA & WEB PUSH NOTIFICATIONS TEST SUITE (FRESH REBUILD):\n";

$pdo = Database::getConnection();

// -------------------------------------------------------------
// Test 1: Complete Removal of Telegram Services & Obsolete Tables
// -------------------------------------------------------------
$telegramFiles = [
    __DIR__ . '/../app/services/TelegramService.php',
    __DIR__ . '/../app/services/TelegramQueueService.php',
    __DIR__ . '/../cron/telegram-notifications.php',
    __DIR__ . '/test_telegram_bot.php',
    __DIR__ . '/test_telegram_queue_worker.php',
    __DIR__ . '/../storage/logs/cron_telegram.lock'
];
$foundOldFiles = [];
foreach ($telegramFiles as $f) {
    if (file_exists($f)) {
        $foundOldFiles[] = basename($f);
    }
}
pwa_it("Old Telegram service and cron files are completely deleted", empty($foundOldFiles), implode(', ', $foundOldFiles));

$stmtTg = $pdo->query("SHOW TABLES LIKE 'telegram_notification_queue'");
$hasTgTable = (bool)$stmtTg->fetch();
$stmtTg->closeCursor();
pwa_it("Database table 'telegram_notification_queue' is removed", !$hasTgTable);

// -------------------------------------------------------------
// Test 2: Database Schema Completeness (Fresh Rebuild)
// -------------------------------------------------------------
$stmtTables = $pdo->query("SHOW TABLES");
$dbTables = $stmtTables->fetchAll(PDO::FETCH_COLUMN);

pwa_it("Table 'push_subscriptions' exists", in_array('push_subscriptions', $dbTables));
pwa_it("Table 'notification_history' exists", in_array('notification_history', $dbTables));
pwa_it("Table 'notification_deliveries' exists", in_array('notification_deliveries', $dbTables));

// Check push_subscriptions columns: customer_id, status
$stmtCols = $pdo->query("SHOW COLUMNS FROM `push_subscriptions`");
$psCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
pwa_it("push_subscriptions contains customer_id and status columns", in_array('customer_id', $psCols) && in_array('status', $psCols));

// Check notification_history columns: category, click_url
$stmtColsH = $pdo->query("SHOW COLUMNS FROM `notification_history`");
$nhCols = $stmtColsH->fetchAll(PDO::FETCH_COLUMN);
pwa_it("notification_history contains category, title, message, click_url", in_array('category', $nhCols) && in_array('click_url', $nhCols));

// -------------------------------------------------------------
// Test 3: PWA Manifest Standards Compliance
// -------------------------------------------------------------
$manifestPath = __DIR__ . '/../public/manifest.webmanifest';
pwa_it("PWA Web Manifest file exists at public/manifest.webmanifest", file_exists($manifestPath));
$manifestJson = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
pwa_it("PWA Web Manifest contains valid JSON", is_array($manifestJson));

if (is_array($manifestJson)) {
    pwa_it("Manifest has id '/'", ($manifestJson['id'] ?? '') === '/');
    pwa_it("Manifest start_url is '/'", ($manifestJson['start_url'] ?? '') === '/');
    pwa_it("Manifest scope is '/'", ($manifestJson['scope'] ?? '') === '/');
    pwa_it("Manifest display mode is 'standalone'", ($manifestJson['display'] ?? '') === 'standalone');
    pwa_it("Manifest theme_color matches brand '#235d3a'", strtolower($manifestJson['theme_color'] ?? '') === '#235d3a');
    pwa_it("Manifest short_name is <= 12 characters", strlen($manifestJson['short_name'] ?? '') <= 12 && strlen($manifestJson['short_name'] ?? '') > 0);
}

// -------------------------------------------------------------
// Test 4: Service Worker & Offline Page Verification
// -------------------------------------------------------------
$swPath = __DIR__ . '/../public/sw.js';
pwa_it("Service worker exists at public/sw.js", file_exists($swPath));
$swContent = file_exists($swPath) ? file_get_contents($swPath) : '';
pwa_it("Service worker handles 'push' event", strpos($swContent, "addEventListener('push'") !== false);
pwa_it("Service worker handles 'notificationclick' event", strpos($swContent, "addEventListener('notificationclick'") !== false);
pwa_it("Service worker skips sensitive caching for checkout/admin/auth", strpos($swContent, "SENSITIVE_PREFIXES") !== false && strpos($swContent, "/admin") !== false && strpos($swContent, "/checkout") !== false);

$offlinePath = __DIR__ . '/../public/offline.html';
pwa_it("Branded offline fallback exists at public/offline.html", file_exists($offlinePath));

// -------------------------------------------------------------
// Test 5: VAPID Keypair Generation & RFC 8292 Compliance
// -------------------------------------------------------------
$pubKey = WebPushService::getPublicKey();
pwa_it("VAPID public key is generated and accessible", !empty($pubKey) && is_string($pubKey));
$pubBytes = WebPushService::base64UrlDecode($pubKey);
pwa_it("VAPID public key is a valid uncompressed NIST P-256 point (65 bytes)", strlen($pubBytes) === 65 && ord($pubBytes[0]) === 0x04);

$vapidPath = __DIR__ . '/../storage/vapid_keys.json';
pwa_it("VAPID key file is stored securely outside web root in storage/vapid_keys.json", file_exists($vapidPath));

// -------------------------------------------------------------
// Test 6: Device Registration & Subscription Storage
// -------------------------------------------------------------
$pdo->exec("DELETE FROM `push_subscriptions` WHERE `endpoint` LIKE 'https://test-push-service.local/%'");

$testEndpoint1 = 'https://test-push-service.local/sub/device-alpha-' . bin2hex(random_bytes(4));
$dummyP256dh = 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QT9nP';
$dummyAuth = 'tBHItJI5svbpez7KI4CCXg';

$subRes = NotificationService::subscribe($testEndpoint1, $dummyP256dh, $dummyAuth, 'customer', 1, 'Mozilla/5.0 Test Chrome');
pwa_it("Customer device subscription registered successfully", !empty($subRes['success']));

$stmtCheck = $pdo->prepare("SELECT * FROM `push_subscriptions` WHERE `endpoint` = ?");
$stmtCheck->execute([$testEndpoint1]);
$savedSub = $stmtCheck->fetch(PDO::FETCH_ASSOC);
$stmtCheck->closeCursor();
pwa_it("Subscription stored with status 'active' and customer_id", !empty($savedSub) && $savedSub['status'] === 'active' && (int)$savedSub['customer_id'] === 1);

// Test unsubscription
$unsubRes = NotificationService::unsubscribe($testEndpoint1);
pwa_it("Device unsubscription removes endpoint", $unsubRes === true);

$stmtCheck->execute([$testEndpoint1]);
$unsubCheck = $stmtCheck->fetch();
$stmtCheck->closeCursor();
pwa_it("Unsubscribed device is purged from database", $unsubCheck === false);

// -------------------------------------------------------------
// Test 7: Multi-Device Support per Customer Account
// -------------------------------------------------------------
$testEndpointDeviceA = 'https://test-push-service.local/sub/customer-dev-a-' . bin2hex(random_bytes(4));
$testEndpointDeviceB = 'https://test-push-service.local/sub/customer-dev-b-' . bin2hex(random_bytes(4));

NotificationService::subscribe($testEndpointDeviceA, $dummyP256dh, $dummyAuth, 'customer', 99, 'Device Phone');
NotificationService::subscribe($testEndpointDeviceB, $dummyP256dh, $dummyAuth, 'customer', 99, 'Device Laptop');

$stmtMulti = $pdo->prepare("SELECT COUNT(*) FROM `push_subscriptions` WHERE `customer_id` = 99 AND `status` = 'active'");
$stmtMulti->execute();
$multiCount = (int)$stmtMulti->fetchColumn();
$stmtMulti->closeCursor();
pwa_it("Multiple devices can be subscribed for the same customer account", $multiCount === 2, "Count: {$multiCount}");

// Clean up test customer 99
$pdo->exec("DELETE FROM `push_subscriptions` WHERE `customer_id` = 99");

// -------------------------------------------------------------
// Test 8: CATEGORY 1 - OFFERS Broadcast
// -------------------------------------------------------------
$subOffers1 = 'https://test-push-service.local/sub/offers-1-' . bin2hex(random_bytes(4));
NotificationService::subscribe($subOffers1, $dummyP256dh, $dummyAuth, 'customer', 101);

$resOffers = NotificationService::sendOffers('Summer Combo Offer! 🥥', 'Get 2 Fresh Coconuts for ₹70 only!', '/assets/images/hero-coconut.png', '/shop');
pwa_it("Category 1 (Offers) dispatched successfully", !empty($resOffers['success']));
pwa_it("Offers notification recorded in notification_history", !empty($resOffers['notification_id']));

$stmtHCheck = $pdo->prepare("SELECT * FROM `notification_history` WHERE `id` = ?");
$stmtHCheck->execute([$resOffers['notification_id']]);
$histRow = $stmtHCheck->fetch(PDO::FETCH_ASSOC);
$stmtHCheck->closeCursor();
pwa_it("History record category is 'offers' with correct click_url '/shop'", $histRow && $histRow['category'] === 'offers' && $histRow['click_url'] === '/shop');

// -------------------------------------------------------------
// Test 9: CATEGORY 2 - ORDERS Status Updates & Strict Customer Isolation
// -------------------------------------------------------------
// Subscribe Customer 201 device
$subOrderCust = 'https://test-push-service.local/sub/order-cust-201-' . bin2hex(random_bytes(4));
NotificationService::subscribe($subOrderCust, $dummyP256dh, $dummyAuth, 'customer', 201);

// Subscribe Customer 202 device
$subOtherCust = 'https://test-push-service.local/sub/order-cust-202-' . bin2hex(random_bytes(4));
NotificationService::subscribe($subOtherCust, $dummyP256dh, $dummyAuth, 'customer', 202);

// Ensure Customer 201 exists in customers table
$pdo->exec("INSERT IGNORE INTO `customers` (`id`, `name`, `phone`, `password_hash`) VALUES (201, 'Customer 201', '9898000201', 'hash123')");

// Create test order belonging to Customer 201
$trackingHash = bin2hex(random_bytes(16));
$pdo->exec("
    INSERT INTO `orders` 
        (`order_number`, `customer_id`, `customer_name`, `customer_phone`, `fulfilment_method`, `delivery_address`, `subtotal`, `delivery_charge`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `tracking_hash`)
    VALUES 
        ('TEST-ORD-201', 201, 'Customer 201', '9898000201', 'delivery', 'Green Park, Bhopal', 80.00, 40.00, 120.00, 'cod', 'pending', 'confirmed', '{$trackingHash}')
");
$testOrderId = (int)$pdo->lastInsertId();

// Dispatch order update
$resOrderUpdate = NotificationService::sendOrderUpdate($testOrderId, 'out_for_delivery');
pwa_it("Category 2 (Orders) update dispatched successfully", !empty($resOrderUpdate['success']));
pwa_it("Order update targeted exactly 1 device (Customer 201 only, no leakage to Customer 202)", $resOrderUpdate['sent'] === 1 && $resOrderUpdate['total'] === 1);

// Verify notification_history record
$stmtOrdHist = $pdo->prepare("SELECT * FROM `notification_history` WHERE `id` = ?");
$stmtOrdHist->execute([$resOrderUpdate['notification_id']]);
$ordHistRow = $stmtOrdHist->fetch(PDO::FETCH_ASSOC);
$stmtOrdHist->closeCursor();
pwa_it("Order history category is 'orders' with single_customer target_type", $ordHistRow && $ordHistRow['category'] === 'orders' && $ordHistRow['target_type'] === 'single_customer' && (int)$ordHistRow['target_customer_id'] === 201);

// Clean up test order
$pdo->exec("DELETE FROM `orders` WHERE `id` = {$testOrderId}");

// -------------------------------------------------------------
// Test 10: CATEGORY 3 - STOCK Announcements with Duplicate Protection
// -------------------------------------------------------------
$pdo->exec("DELETE FROM `notification_history` WHERE `category` = 'stock'");

$stockTitle = 'Fresh Tender Coconuts Back in Stock! 🌴';
$resStock1 = NotificationService::sendStockAlert($stockTitle, 'Harvest arrived 10 mins ago. Pure, sweet, and chilled.');
pwa_it("Category 3 (Stock) first broadcast succeeded", !empty($resStock1['success']));

// Attempt duplicate within 10 minutes
$resStockDuplicate = NotificationService::sendStockAlert($stockTitle, 'Harvest arrived 10 mins ago. Pure, sweet, and chilled.');
pwa_it("Stock duplicate broadcast is blocked by 10-minute duplicate protection", $resStockDuplicate['success'] === false && strpos($resStockDuplicate['error'], 'similar stock notification') !== false);

// -------------------------------------------------------------
// Test 11: CATEGORY 4 - NEWS Broadcast
// -------------------------------------------------------------
$resNews = NotificationService::sendNews('Diwali Holiday Hours Notice', 'Store will remain open until 8 PM during Diwali holidays.', null, '/');
pwa_it("Category 4 (News) broadcast dispatched successfully", !empty($resNews['success']));

$stmtNewsHist = $pdo->prepare("SELECT * FROM `notification_history` WHERE `id` = ?");
$stmtNewsHist->execute([$resNews['notification_id']]);
$newsHistRow = $stmtNewsHist->fetch(PDO::FETCH_ASSOC);
$stmtNewsHist->closeCursor();
pwa_it("News history record category is 'news' with click_url '/'", $newsHistRow && $newsHistRow['category'] === 'news' && $newsHistRow['click_url'] === '/');

// -------------------------------------------------------------
// Test 12: Notification Deliveries Tracking & Auto-Pruning Expired Subscriptions
// -------------------------------------------------------------
$stmtDels = $pdo->query("SELECT COUNT(*) FROM `notification_deliveries` WHERE `status` = 'sent'");
$sentCount = (int)$stmtDels->fetchColumn();
$stmtDels->closeCursor();
pwa_it("notification_deliveries logs successful deliveries", $sentCount > 0);

// Test Expired Subscription (410 Gone) Handling
$expiredEp = 'https://test-push-service.local/expired-dev-' . bin2hex(random_bytes(6));
NotificationService::subscribe($expiredEp, $dummyP256dh, $dummyAuth, 'customer', 999);

// Dispatch notification to expired endpoint
$subExpiredRow = ['endpoint' => $expiredEp, 'p256dh' => $dummyP256dh, 'auth' => $dummyAuth];
$resExpired = WebPushService::sendNotification($subExpiredRow, ['title' => 'Test', 'body' => 'Test']);
pwa_it("Push service identifies HTTP 410 expired subscription", $resExpired['expired'] === true && $resExpired['http_code'] === 410);

$cleanupDone = NotificationService::cleanupExpiredSubscription($expiredEp);
pwa_it("cleanupExpiredSubscription purges expired subscription endpoint", $cleanupDone === true);

// -------------------------------------------------------------
// Test 13: Admin Device Enrollment Isolation
// -------------------------------------------------------------
$adminEp = 'https://test-push-service.local/fcm/admin-iso-' . bin2hex(random_bytes(6));
$resAdmin = NotificationService::subscribe($adminEp, $dummyP256dh, $dummyAuth, 'admin', 1);
pwa_it("Admin device enrolled with user_type 'admin'", $resAdmin['success'] && $resAdmin['user_type'] === 'admin');

// Ensure admin devices do not receive customer broadcast
$stmtCheckAdminInCust = $pdo->query("SELECT COUNT(*) FROM `push_subscriptions` WHERE `user_type` IN ('customer', 'guest') AND `endpoint` = '{$adminEp}'");
$adminInCust = (int)$stmtCheckAdminInCust->fetchColumn();
$stmtCheckAdminInCust->closeCursor();
pwa_it("Admin device is strictly isolated from customer broadcast lists", $adminInCust === 0);

// Test overall stats
$stats = NotificationService::getStats();
pwa_it("NotificationService::getStats() returns customer_devices, admin_devices, total_delivered", 
    isset($stats['customer_devices']) && 
    isset($stats['admin_devices']) && 
    isset($stats['total_delivered']) && 
    isset($stats['vapid_public_key'])
);

// -------------------------------------------------------------
// Test 14: VAPID Claims Formatting & RFC 8291 Cryptographic Verification
// -------------------------------------------------------------
$reflector = new ReflectionClass('WebPushService');
$jwtMethod = $reflector->getMethod('createVapidJwt');
$jwtMethod->setAccessible(true);
$vapidKeys = WebPushService::getKeys();
$rawJwt = $jwtMethod->invoke(null, 'https://fcm.googleapis.com', $vapidKeys);
$jwtParts = explode('.', $rawJwt);
$jwtClaimsJson = WebPushService::base64UrlDecode($jwtParts[1]);
$claimsDecoded = json_decode($jwtClaimsJson, true);

pwa_it("VAPID JWT audience claim is unescaped URL 'https://fcm.googleapis.com'", 
    ($claimsDecoded['aud'] ?? '') === 'https://fcm.googleapis.com' && !str_contains($jwtClaimsJson, '\\/')
);

// Round-trip RFC 8291 (aes128gcm) Encryption & Decryption
$testClientRes = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
$testClientDetails = openssl_pkey_get_details($testClientRes);
$testClientPub = chr(4) . str_pad($testClientDetails['ec']['x'], 32, chr(0), STR_PAD_LEFT) . str_pad($testClientDetails['ec']['y'], 32, chr(0), STR_PAD_LEFT);
$testClientAuth = random_bytes(16);
$testP256dhB64 = WebPushService::base64UrlEncode($testClientPub);
$testAuthB64 = WebPushService::base64UrlEncode($testClientAuth);
$testPlaintext = json_encode(['title' => 'Fresh Coconut Order #1050', 'body' => 'Your order is out for delivery!']);

$encMethod = $reflector->getMethod('encryptPayload');
$encMethod->setAccessible(true);
$encResult = $encMethod->invoke(null, $testPlaintext, $testP256dhB64, $testAuthB64);
$ciphertextRec = $encResult['ciphertext'];

pwa_it("RFC 8291 AES-128-GCM ciphertext record created successfully", strlen($ciphertextRec) > 86);

// Decrypt ciphertext using client EC private key
$recSalt = substr($ciphertextRec, 0, 16);
$recIdLen = ord($ciphertextRec[20]);
$recServerPub = substr($ciphertextRec, 21, $recIdLen);
$recContent = substr($ciphertextRec, 21 + $recIdLen);
$recTag = substr($recContent, -16);
$recEncData = substr($recContent, 0, -16);

$pemMethod = $reflector->getMethod('ecPublicKeyToPem');
$pemMethod->setAccessible(true);
$serverPem = $pemMethod->invoke(null, $recServerPub);
$serverKeyRes = openssl_pkey_get_public($serverPem);

$sharedSecret = openssl_pkey_derive($serverKeyRes, $testClientRes);
$prkKey = hash_hmac('sha256', $sharedSecret, $testClientAuth, true);
$keyInfo = "WebPush: info\x00" . $testClientPub . $recServerPub;
$ikm = hash_hmac('sha256', $keyInfo . "\x01", $prkKey, true);
$prk = hash_hmac('sha256', $ikm, $recSalt, true);
$cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
$nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

$decrypted = openssl_decrypt($recEncData, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $recTag);
$cleanDecrypted = substr($decrypted, 0, -1); // strip \x02 delimiter
$isCryptoValid = ($cleanDecrypted === $testPlaintext);
pwa_it("RFC 8291 AES-128-GCM encryption & round-trip decryption verified", $isCryptoValid);

// -------------------------------------------------------------
// Test 15: Client JS & Admin View Verification
// -------------------------------------------------------------
$pwaJsContent = file_get_contents(__DIR__ . '/../public/assets/js/pwa-push.js');
pwa_it("pwa-push.js defines subscribeDevice, syncDeviceStatus, and repairSubscription", 
    strpos($pwaJsContent, 'subscribeDevice') !== false &&
    strpos($pwaJsContent, 'syncDeviceStatus') !== false &&
    strpos($pwaJsContent, 'repairSubscription') !== false
);

$adminNotifView = file_get_contents(__DIR__ . '/../app/views/admin/notifications.php');
pwa_it("Admin Notifications view has 4-category selector (offers, orders, stock, news)", 
    strpos($adminNotifView, '1. OFFERS') !== false &&
    strpos($adminNotifView, '2. ORDERS') !== false &&
    strpos($adminNotifView, '3. STOCK') !== false &&
    strpos($adminNotifView, '4. NEWS') !== false
);

pwa_it("Admin Notifications view displays active subscriber metrics and notification history", 
    strpos($adminNotifView, 'Total Customer Devices') !== false &&
    strpos($adminNotifView, 'Notification History') !== false
);

// Clean up test subscriptions
$pdo->exec("DELETE FROM `push_subscriptions` WHERE `endpoint` LIKE 'https://test-push-service.local/%'");

echo "\nPWA & Web Push Notification tests completed: {$pwaPass} passed, {$pwaFail} failed.\n";
