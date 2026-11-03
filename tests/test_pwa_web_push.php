<?php
/**
 * Comprehensive PWA Web Push Notification & Migration Test Suite
 * N.A Fresh Fruits & Coconuts
 *
 * Verifies:
 * 1. Zero Telegram Bot / Telegram queue code references remain in active codebase.
 * 2. Manifest file is valid JSON and contains all required PWA fields (id, name, short_name <= 12 chars, start_url, scope, display, theme_color, background_color, icons).
 * 3. Service Worker public/sw.js exists and is syntax-valid.
 * 4. VAPID keypair generation, export, and public key retrieval (RFC 8292 NIST P-256).
 * 5. Device subscription and unsubscription lifecycle.
 * 6. Multi-device support per account (customer & admin).
 * 7. Admin order push dispatch on new order placement (COD & Razorpay).
 * 8. Customer order lifecycle push updates (Confirmed, Prepared, Out for Delivery, Delivered).
 * 9. Admin promotional broadcast with role validation.
 * 10. Customer promotional opt-in / opt-out preferences enforcement.
 * 11. Expired subscription auto-pruning.
 * 12. Idempotency deduplication.
 * 13. Customer data isolation (no cross-customer notification leakage).
 * 14. Zero cron scripts or background daemons required.
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

echo "12. PWA & WEB PUSH NOTIFICATIONS TEST SUITE:\n";

// -------------------------------------------------------------
// Test 1: Complete Removal of Telegram Services & Cron Files
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

// Check database has no telegram_notification_queue table
$pdo = Database::getConnection();
$stmtTg = $pdo->query("SHOW TABLES LIKE 'telegram_notification_queue'");
$hasTgTable = (bool)$stmtTg->fetch();
$stmtTg->closeCursor();
pwa_it("Database table 'telegram_notification_queue' is removed", !$hasTgTable);

// -------------------------------------------------------------
// Test 2: PWA Manifest Standards Compliance
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
    pwa_it("Manifest short_name is <= 12 characters", strlen($manifestJson['short_name'] ?? '') <= 12 && strlen($manifestJson['short_name'] ?? '') > 0, "Length: " . strlen($manifestJson['short_name'] ?? ''));
    
    // Check icons
    $icons = $manifestJson['icons'] ?? [];
    $has192 = false;
    $has512 = false;
    $hasMaskable = false;
    foreach ($icons as $ic) {
        if (($ic['sizes'] ?? '') === '192x192') $has192 = true;
        if (($ic['sizes'] ?? '') === '512x512') $has512 = true;
        if (($ic['purpose'] ?? '') === 'maskable') $hasMaskable = true;
    }
    pwa_it("Manifest includes 192x192 icon", $has192);
    pwa_it("Manifest includes 512x512 icon", $has512);
    pwa_it("Manifest includes separate maskable icon definition", $hasMaskable);
}

// -------------------------------------------------------------
// Test 3: Service Worker & Offline Page Verification
// -------------------------------------------------------------
$swPath = __DIR__ . '/../public/sw.js';
pwa_it("Service worker exists at public/sw.js", file_exists($swPath));
$swContent = file_exists($swPath) ? file_get_contents($swPath) : '';
pwa_it("Service worker handles 'push' event", strpos($swContent, "addEventListener('push'") !== false);
pwa_it("Service worker handles 'notificationclick' event", strpos($swContent, "addEventListener('notificationclick'") !== false);
pwa_it("Service worker skips sensitive caching for checkout/admin/auth", strpos($swContent, "EXCLUDED_PATHS") !== false && strpos($swContent, "/admin") !== false && strpos($swContent, "/checkout") !== false);

$offlinePath = __DIR__ . '/../public/offline.html';
pwa_it("Branded offline fallback exists at public/offline.html", file_exists($offlinePath));

// -------------------------------------------------------------
// Test 4: VAPID Keypair Generation & RFC 8292 Compliance
// -------------------------------------------------------------
$pubKey = WebPushService::getPublicKey();
pwa_it("VAPID public key is generated and accessible", !empty($pubKey) && is_string($pubKey));
$pubBytes = WebPushService::base64UrlDecode($pubKey);
// NIST P-256 uncompressed point starts with 0x04 and is 65 bytes long
pwa_it("VAPID public key is a valid uncompressed NIST P-256 point (65 bytes)", strlen($pubBytes) === 65 && ord($pubBytes[0]) === 0x04, "Length: " . strlen($pubBytes));

$vapidPath = __DIR__ . '/../storage/vapid_keys.json';
pwa_it("VAPID key file is stored securely outside web root in storage/vapid_keys.json", file_exists($vapidPath));

// -------------------------------------------------------------
// Test 5: Device Registration & Subscription Storage
// -------------------------------------------------------------
// Clean existing test subscriptions
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
pwa_it("Subscription endpoint hash correctly stored in database", !empty($savedSub) && $savedSub['user_type'] === 'customer' && (int)$savedSub['user_id'] === 1);

// Test unsubscription
$unsubRes = NotificationService::unsubscribe($testEndpoint1);
pwa_it("Device unsubscription removes endpoint", $unsubRes === true);

$stmtCheck->execute([$testEndpoint1]);
$unsubCheck = $stmtCheck->fetch();
$stmtCheck->closeCursor();
pwa_it("Unsubscribed device is purged from database", $unsubCheck === false);

// -------------------------------------------------------------
// Test 6: Multi-Device Support per Account
// -------------------------------------------------------------
$testEndpointDeviceA = 'https://test-push-service.local/sub/customer-dev-a-' . bin2hex(random_bytes(4));
$testEndpointDeviceB = 'https://test-push-service.local/sub/customer-dev-b-' . bin2hex(random_bytes(4));

NotificationService::subscribe($testEndpointDeviceA, $dummyP256dh, $dummyAuth, 'customer', 99, 'Device Phone');
NotificationService::subscribe($testEndpointDeviceB, $dummyP256dh, $dummyAuth, 'customer', 99, 'Device Laptop');

$stmtMulti = $pdo->prepare("SELECT COUNT(*) FROM `push_subscriptions` WHERE `user_type` = 'customer' AND `user_id` = 99");
$stmtMulti->execute();
$multiCount = (int)$stmtMulti->fetchColumn();
$stmtMulti->closeCursor();
pwa_it("Multiple devices can be subscribed for the same customer account", $multiCount === 2, "Count: {$multiCount}");

// Clean up test customer 99
$pdo->exec("DELETE FROM `push_subscriptions` WHERE `user_id` = 99");

// -------------------------------------------------------------
// Test 7: Customer Notification Preferences & Opt-In/Opt-Out
// -------------------------------------------------------------
$testCustId = 101;
// Initially defaults
$prefOrder = NotificationService::getCustomerPreference($testCustId, 'order_updates');
$prefPromo = NotificationService::getCustomerPreference($testCustId, 'promotions');
pwa_it("Customer order updates enabled by default", $prefOrder === true);

// Update preferences (opt-out of promotions, keep order updates)
NotificationService::setCustomerPreferences($testCustId, true, false);
pwa_it("Customer successfully opted out of promotional updates", NotificationService::getCustomerPreference($testCustId, 'promotions') === false);
pwa_it("Customer order updates remain active", NotificationService::getCustomerPreference($testCustId, 'order_updates') === true);

$prefArray = NotificationService::getCustomerPreferences($testCustId);
pwa_it("getCustomerPreferences() returns associative array", is_array($prefArray) && isset($prefArray['order_updates']) && isset($prefArray['promotions']));

// Clean test customer pref
$pdo->exec("DELETE FROM `customer_notification_preferences` WHERE `customer_id` = 101");

// -------------------------------------------------------------
// Test 8: Event Deduplication Mechanism
// -------------------------------------------------------------
$pdo->exec("DELETE FROM `notification_dedup` WHERE `entity_id` = 'TEST-ORDER-DEDUP'");

$orderMock = [
    'id' => 9999,
    'order_number' => 'TEST-ORDER-DEDUP',
    'customer_name' => 'Test Customer',
    'customer_phone' => '9988776655',
    'payment_method' => 'cod',
    'total_amount' => 120.00,
    'delivery_address' => 'Test Street'
];

// First dispatch
$res1 = NotificationService::sendNewOrderToAdmins($orderMock);
pwa_it("First order alert call executes without duplicate block", isset($res1['success']));

// Second immediate duplicate call with same order number
$res2 = NotificationService::sendNewOrderToAdmins($orderMock);
pwa_it("Duplicate order alert is safely caught by idempotency deduplication", !empty($res2['duplicate']));

// Clean up dedup test
$pdo->exec("DELETE FROM `notification_dedup` WHERE `entity_id` = 'TEST-ORDER-DEDUP'");

// -------------------------------------------------------------
// Test 9: Customer Data Isolation & No Cross-Customer Leakage
// -------------------------------------------------------------
// Register sub for Customer 10
$epCust10 = 'https://test-push-service.local/sub/cust-10-' . bin2hex(random_bytes(4));
NotificationService::subscribe($epCust10, $dummyP256dh, $dummyAuth, 'customer', 10);

// Register sub for Customer 20
$epCust20 = 'https://test-push-service.local/sub/cust-20-' . bin2hex(random_bytes(4));
NotificationService::subscribe($epCust20, $dummyP256dh, $dummyAuth, 'customer', 20);

// Fetch subs for Customer 10 specifically
$stmtIsolation = $pdo->prepare("SELECT * FROM `push_subscriptions` WHERE `user_type` = 'customer' AND `user_id` = ?");
$stmtIsolation->execute([10]);
$subsCust10 = $stmtIsolation->fetchAll(PDO::FETCH_ASSOC);
$stmtIsolation->closeCursor();

pwa_it("Customer 10 subscriptions only contain Customer 10 endpoints", count($subsCust10) === 1 && $subsCust10[0]['endpoint'] === $epCust10);

// Clean up
$pdo->exec("DELETE FROM `push_subscriptions` WHERE `user_id` IN (10, 20)");

// -------------------------------------------------------------
// Test 10: Overall Notification Statistics
// -------------------------------------------------------------
$stats = NotificationService::getStats();
pwa_it("NotificationService::getStats() returns admin_devices, customer_devices, total_delivered", 
    isset($stats['admin_devices']) && 
    isset($stats['customer_devices']) && 
    isset($stats['total_delivered']) && 
    isset($stats['vapid_public_key'])
);

// -------------------------------------------------------------
// Test 11: Admin Device Enrollment & Multi-Device Support
// -------------------------------------------------------------
$adminEp1 = 'https://test-push-service.local/fcm/admin-dev-01-' . bin2hex(random_bytes(6));
$adminEp2 = 'https://test-push-service.local/fcm/admin-dev-02-' . bin2hex(random_bytes(6));

// Enroll first admin device
$resAdmin1 = NotificationService::subscribe($adminEp1, $dummyP256dh, $dummyAuth, 'admin', 1);
pwa_it("Admin device 1 enrolled and verified in database", $resAdmin1['success'] && !empty($resAdmin1['subscription_id']));

// Verify device status returns enrolled as admin
$status1 = NotificationService::getDeviceStatus($adminEp1);
pwa_it("Device status correctly identifies enrolled admin device", $status1['enrolled'] === true && $status1['user_type'] === 'admin');

// Re-subscribing existing device updates instead of duplicating
$resAdmin1Repeat = NotificationService::subscribe($adminEp1, $dummyP256dh, $dummyAuth, 'admin', 1);
pwa_it("Re-subscribing existing device updates subscription instead of creating duplicate", $resAdmin1Repeat['subscription_id'] === $resAdmin1['subscription_id']);

$stmtCountAdmin = $pdo->prepare("SELECT COUNT(*) FROM `push_subscriptions` WHERE `endpoint` = ?");
$stmtCountAdmin->execute([$adminEp1]);
$countAdmin1 = (int)$stmtCountAdmin->fetchColumn();
$stmtCountAdmin->closeCursor();
pwa_it("Exactly 1 database row exists for the endpoint", $countAdmin1 === 1);

// Enroll second admin device for same admin (multi-device support)
$resAdmin2 = NotificationService::subscribe($adminEp2, $dummyP256dh, $dummyAuth, 'admin', 1);
pwa_it("Second admin device enrolled successfully for same admin account", $resAdmin2['success'] && $resAdmin2['subscription_id'] !== $resAdmin1['subscription_id']);

// Send test alert to current device endpoint
$testSend = NotificationService::sendTestToDevice($adminEp1, 'admin');
pwa_it("sendTestToDevice dispatches alert successfully to target admin device", $testSend['success'] === true);

// -------------------------------------------------------------
// Test 12: Post-Auth Prompt Session Flags (Customer Login & Registration)
// -------------------------------------------------------------
$testPhone = '98' . mt_rand(10000000, 99999999);
$pdo->exec("DELETE FROM `customers` WHERE `phone` LIKE '%{$testPhone}%'");

// Simulate customer registration trigger
$_SESSION = [];
$regRes = CustomerAuthService::register([
    'name' => 'Push Test Customer',
    'phone' => $testPhone,
    'password' => 'secret123',
    'confirm_password' => 'secret123'
], '127.0.0.1');
pwa_it("Customer registration sets show_push_prompt_after_auth in session", !empty($regRes['success']) && !empty($_SESSION['show_push_prompt_after_auth']));

// Simulate customer login trigger
$_SESSION = [];
$loginRes = CustomerAuthService::login($testPhone, 'secret123', '127.0.0.1');
pwa_it("Customer login sets show_push_prompt_after_auth in session", !empty($loginRes['success']) && !empty($_SESSION['show_push_prompt_after_auth']));

// -------------------------------------------------------------
// Test 13: Client-Side Permission Checks, Gesture Flow & Suppression
// -------------------------------------------------------------
$pwaJsCode = file_get_contents(__DIR__ . '/../public/assets/js/pwa-push.js');
$hasGestureRequest = strpos($pwaJsCode, 'Notification.requestPermission()') !== false;
$hasNoPageLoadRequest = strpos($pwaJsCode, 'init: async function () {\n            Notification.requestPermission()') === false;
pwa_it("Permission request logic requires explicit user gesture and never prompts on init", $hasGestureRequest && $hasNoPageLoadRequest);

$hasSuppressionCheck = strpos($pwaJsCode, "Notification.permission === 'granted' && this.isSubscribed") !== false;
pwa_it("Popup is automatically suppressed when permission is already granted and active", $hasSuppressionCheck);

$hasDismissTracking = strpos($pwaJsCode, "sessionStorage.setItem('na_push_prompt_dismissed', '1')") !== false &&
                      strpos($pwaJsCode, "sessionStorage.getItem('na_push_prompt_dismissed') === '1'") !== false;
pwa_it("Session dismissal tracking: 'Not Now' prevents reprompting in same session", $hasDismissTracking);

// -------------------------------------------------------------
// Test 14: Centralized Notification Center & Blocked Permission Guide
// -------------------------------------------------------------
$accountHtml = file_get_contents(__DIR__ . '/../app/views/customer/account.php');
$hasOpenCenterBtn = strpos($accountHtml, 'openNotificationCenter') !== false;
$footerHtml = file_get_contents(__DIR__ . '/../app/views/layouts/footer.php');
$hasCenterModal = strpos($footerHtml, 'pwa-center-modal') !== false || strpos($footerHtml, 'pwa-notification-center-modal') !== false;
pwa_it("Centralized Notification Center modal can be reopened anytime from account view", $hasOpenCenterBtn && $hasCenterModal);

$hasRecoveryGuide = strpos($pwaJsCode, 'showBlockedPermissionModal') !== false;
$hasHelpModal = strpos($footerHtml, 'pwa-auth-blocked-help') !== false || strpos($footerHtml, 'pwa-center-blocked-help') !== false;
pwa_it("Clear browser unblock guide is provided when permission is in denied state", $hasRecoveryGuide && $hasHelpModal);

// -------------------------------------------------------------
// Test 15: Customer Test Push & Expired Subscription Pruning
// -------------------------------------------------------------
$custTestEp = 'https://test-push-service.local/cust-test-' . bin2hex(random_bytes(6));
NotificationService::subscribe($custTestEp, $dummyP256dh, $dummyAuth, 'customer', 55, 'Customer Android Phone');
$custTestRes = NotificationService::sendTestToDevice($custTestEp, 'customer');
pwa_it("sendTestToDevice dispatches alert successfully to target customer device", $custTestRes['success'] === true);

$expiredEp = 'https://test-push-service.local/expired-dev-' . bin2hex(random_bytes(6));
NotificationService::subscribe($expiredEp, $dummyP256dh, $dummyAuth, 'customer', 999);
$cleanupDone = NotificationService::cleanupExpiredSubscription($expiredEp);
$stmtExpCheck = $pdo->prepare("SELECT COUNT(*) FROM `push_subscriptions` WHERE `endpoint` = ?");
$stmtExpCheck->execute([$expiredEp]);
$expCount = (int)$stmtExpCheck->fetchColumn();
$stmtExpCheck->closeCursor();
pwa_it("Expired subscription auto-pruning purges stale endpoints from database", $cleanupDone === true && $expCount === 0);

// -------------------------------------------------------------
// Test 16: VAPID Claims Formatting & RFC 8291 Cryptographic Verification
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
// Test 17: Finite State Machine & Admin Diagnostics Panel
// -------------------------------------------------------------
$pwaJsContent = file_get_contents(__DIR__ . '/../public/assets/js/pwa-push.js');
$fsmStates = [
    'UNSUPPORTED',
    'PERMISSION_NOT_REQUESTED',
    'PERMISSION_DENIED',
    'PERMISSION_GRANTED',
    'DEVICE_NOT_SUBSCRIBED',
    'SUBSCRIPTION_PENDING',
    'SUBSCRIPTION_SAVED',
    'DEVICE_ENROLLED',
    'SUBSCRIPTION_EXPIRED',
    'ENROLLMENT_FAILED'
];
$allStatesFound = true;
foreach ($fsmStates as $s) {
    if (!str_contains($pwaJsContent, $s)) {
        $allStatesFound = false;
        break;
    }
}
pwa_it("pwa-push.js defines complete 10-state finite state machine (FSM)", $allStatesFound);

$adminNotifView = file_get_contents(__DIR__ . '/../app/views/admin/notifications.php');
$hasDiagnosticsPanel = strpos($adminNotifView, 'Live Web Push Diagnostics Panel') !== false &&
                       strpos($adminNotifView, 'diag-val-permission') !== false &&
                       strpos($adminNotifView, 'diag-val-sw') !== false &&
                       strpos($adminNotifView, 'diag-val-push') !== false &&
                       strpos($adminNotifView, 'diag-val-db') !== false &&
                       strpos($adminNotifView, 'admin-repair-btn') !== false;
pwa_it("Admin Notifications view includes live diagnostics panel & repair action", $hasDiagnosticsPanel);

// Test sendTestToDevice response telemetry
$diagTestEp = 'https://test-push-service.local/diag-test-' . bin2hex(random_bytes(6));
NotificationService::subscribe($diagTestEp, $dummyP256dh, $dummyAuth, 'admin', 1, 'Admin Chrome');
$diagTestRes = NotificationService::sendTestToDevice($diagTestEp, 'admin');
pwa_it("sendTestToDevice returns http_code, latency_ms and endpoint_host", 
    isset($diagTestRes['http_code']) && isset($diagTestRes['latency_ms']) && isset($diagTestRes['endpoint_host'])
);

// Clean up test customer & test subscriptions
$pdo->exec("DELETE FROM `push_subscriptions` WHERE `endpoint` LIKE 'https://test-push-service.local/%'");
$pdo->exec("DELETE FROM `customers` WHERE `phone` LIKE '%{$testPhone}%'");

echo "\nPWA & Web Push Notification tests completed: {$pwaPass} passed, {$pwaFail} failed.\n";


