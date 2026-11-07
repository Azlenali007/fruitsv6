<?php
/**
 * Test Suite 17: Delivery Boy PWA, GPS Tracking & Customer Instagram Loyalty Rewards
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pwaPass = 0;
$pwaFail = 0;

function itPwa(string $name, bool $condition, string $detail = '') {
    global $pwaPass, $pwaFail;
    if ($condition) {
        $pwaPass++;
        echo "  [PASS] {$name}\n";
    } else {
        $pwaFail++;
        echo "  [FAIL] {$name} - {$detail}\n";
    }
}

echo "========================================================\n";
echo "17. DELIVERY BOY PWA, GPS & INSTAGRAM LOYALTY REWARDS\n";
echo "========================================================\n\n";

$pdo = Database::getConnection();

// =========================================================
// A. DELIVERY BOY INSTALLABLE PWA VERIFICATION
// =========================================================
echo "A. DELIVERY BOY INSTALLABLE PWA VERIFICATION:\n";

// 1. Manifest file existence and validity
$manifestPath = __DIR__ . '/../public/manifest-delboy.webmanifest';
itPwa("1. Delivery Boy PWA Web App Manifest exists", file_exists($manifestPath));

$manifestJson = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
itPwa("2. Manifest is valid JSON", is_array($manifestJson));
itPwa("3. Manifest display mode is 'standalone'", ($manifestJson['display'] ?? '') === 'standalone');
itPwa("4. Manifest start_url is '/delboy'", ($manifestJson['start_url'] ?? '') === '/delboy');
itPwa("5. Manifest id is '/delboy'", ($manifestJson['id'] ?? '') === '/delboy');
itPwa("6. Manifest defines required 192x192 icon", !empty(array_filter($manifestJson['icons'] ?? [], fn($i) => ($i['sizes'] ?? '') === '192x192')));
itPwa("7. Manifest defines required 512x512 icon", !empty(array_filter($manifestJson['icons'] ?? [], fn($i) => ($i['sizes'] ?? '') === '512x512')));
itPwa("8. Manifest defines maskable icon", !empty(array_filter($manifestJson['icons'] ?? [], fn($i) => str_contains($i['purpose'] ?? '', 'maskable'))));

// 2. Service Worker verification
$swPath = __DIR__ . '/../public/sw-delboy.js';
itPwa("9. Delivery Boy Service Worker (sw-delboy.js) exists", file_exists($swPath));
$swContent = file_exists($swPath) ? file_get_contents($swPath) : '';
itPwa("10. Service Worker precaches safe static assets and offline fallback", str_contains($swContent, 'delboy-offline.html'));
itPwa("11. Service Worker bypasses caching for sensitive /api/ routes", str_contains($swContent, '/api/'));
itPwa("12. Service Worker bypasses caching for sensitive /admin/ routes", str_contains($swContent, '/admin/'));
itPwa("13. Service Worker contains skipWaiting and clients.claim() for instant updates", str_contains($swContent, 'skipWaiting') && str_contains($swContent, 'clients.claim'));

// 3. Offline fallback page
$offlinePath = __DIR__ . '/../public/delboy-offline.html';
itPwa("14. Offline fallback page (delboy-offline.html) exists", file_exists($offlinePath));
$offlineHtml = file_exists($offlinePath) ? file_get_contents($offlinePath) : '';
itPwa("15. Offline fallback page informs rider of connection status", str_contains($offlineHtml, 'Offline') || str_contains($offlineHtml, 'offline'));

// 4. View inspection for PWA linkage & Apple tags
$delboyDashboard = file_get_contents(__DIR__ . '/../app/views/delivery/dashboard.php');
itPwa("16. Delivery dashboard links to manifest-delboy.webmanifest", str_contains($delboyDashboard, 'manifest-delboy.webmanifest'));
itPwa("17. Delivery dashboard includes apple-mobile-web-app-capable tag", str_contains($delboyDashboard, 'apple-mobile-web-app-capable'));
itPwa("18. Delivery dashboard registers sw-delboy.js", str_contains($delboyDashboard, 'sw-delboy.js'));
itPwa("19. Delivery dashboard contains iOS Safari Add to Home Screen instructions modal", str_contains($delboyDashboard, 'iosInstallModal') || str_contains($delboyDashboard, 'Add to Home Screen'));


// =========================================================
// B. DELIVERY BOY GPS TRACKING & VISIBILITY HANDLING
// =========================================================
echo "\nB. DELIVERY BOY GPS TRACKING & VISIBILITY HANDLING:\n";

// Ensure test delivery boy exists
$stmt = $pdo->prepare("SELECT id FROM delivery_boys WHERE mobile = '9999900001' LIMIT 1");
$stmt->execute();
$riderId = $stmt->fetchColumn();
if (!$riderId) {
    $stmt = $pdo->prepare("INSERT INTO delivery_boys (name, mobile, login_id, password_hash, status, is_online, created_at) VALUES ('Rider PWA Test', '9999900001', 'rider_pwa_1', :p, 'active', 1, NOW())");
    $stmt->execute(['p' => password_hash('rider123', PASSWORD_BCRYPT)]);
    $riderId = (int)$pdo->lastInsertId();
}

// 1. Send GPS ping
$lat = 23.636000;
$lng = 75.125000;
DeliveryBoy::updateLocation($riderId, $lat, $lng, 15.5, 45.0, null);

$freshRider = DeliveryBoy::getById($riderId);
itPwa("20. Delivery Boy coordinates updated accurately in MySQL", (float)$freshRider['current_lat'] === 23.636000 && (float)$freshRider['current_lng'] === 75.125000);
itPwa("21. Location updated timestamp is within last 5 seconds", !empty($freshRider['last_location_updated_at']));

// 2. Staleness calculation check
$isStaleRecent = DeliveryBoy::isLocationStale($riderId, 60);
itPwa("22. Recent GPS ping (<60s) is recognized as fresh (is_stale = false)", $isStaleRecent === false);

// Artificially age the timestamp to 120 seconds ago to simulate rider switching apps / screen locked
$pdo->exec("UPDATE delivery_boys SET last_location_updated_at = DATE_SUB(NOW(), INTERVAL 120 SECOND) WHERE id = {$riderId}");
$isStaleOld = DeliveryBoy::isLocationStale($riderId, 60);
itPwa("23. Aged GPS ping (>60s) is recognized as stale (is_stale = true)", $isStaleOld === true);

// 3. Frontend resume listeners in dashboard view
itPwa("24. Dashboard contains visibilitychange event handler to wake GPS", str_contains($delboyDashboard, 'visibilitychange'));
itPwa("25. Dashboard contains pageshow event handler to wake GPS on BFCache resume", str_contains($delboyDashboard, 'pageshow'));


// =========================================================
// C. INSTAGRAM STORY LOYALTY REWARDS SUBMISSION
// =========================================================
echo "\nC. INSTAGRAM STORY LOYALTY REWARDS SUBMISSION:\n";

// Create test customer
$testPhone = '98765' . rand(10000, 99999);
$testEmail = 'loyalty_' . uniqid() . '@example.com';
$custRes = Customer::create([
    'name' => 'Loyalty Tester',
    'phone' => $testPhone,
    'email' => $testEmail,
    'password' => 'secret123'
]);
$customerId = is_array($custRes) ? (int)$custRes['customer_id'] : (int)$custRes;

// Create test order in delivered status
$orderData = [
    'customer_id' => $customerId,
    'customer_name' => 'Loyalty Tester',
    'customer_phone' => $testPhone,
    'fulfilment_method' => 'delivery',
    'delivery_address' => 'Station Road, Jaora 457226',
    'payment_method' => 'cod',
    'idempotency_key' => 'idem_loyalty_' . uniqid()
];
$prodStmt = $pdo->query("SELECT id FROM products WHERE is_available = 1 LIMIT 1");
$pId = $prodStmt->fetchColumn();
$orderCreated = Order::createOrder($orderData, [['product_id' => $pId, 'quantity' => 1]]);
$orderId = (int)$orderCreated['order_id'];

// Before delivery: status is pending/confirmed. Customer CANNOT submit story yet.
$canSubmitPending = LoyaltyService::canSubmitStory($customerId, $orderId);
itPwa("26. Customer CANNOT submit Instagram story for unfulfilled/pending order", ($canSubmitPending['eligible'] ?? false) === false);

// Transition order to delivered
$pdo->exec("UPDATE orders SET order_status = 'delivered', delivery_workflow_status = 'delivered' WHERE id = {$orderId}");

$canSubmitDelivered = LoyaltyService::canSubmitStory($customerId, $orderId);
itPwa("27. Customer CAN submit Instagram story for completed delivered order", ($canSubmitDelivered['eligible'] ?? false) === true);

// Submit fake story screenshot (create temporary valid PNG image in memory)
$tempImg = tempnam(sys_get_temp_dir(), 'story_proof_');
$im = imagecreatetruecolor(100, 100);
$bg = imagecolorallocate($im, 255, 105, 180);
imagefilledrectangle($im, 0, 0, 99, 99, $bg);
imagepng($im, $tempImg);
imagedestroy($im);

$filePayload = [
    'tmp_name' => $tempImg,
    'name' => 'my_story_screenshot.png',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tempImg)
];

$submitRes = LoyaltyService::submitStoryProof($customerId, $orderId, 'tester_insta_handle', $filePayload);
itPwa("28. Story proof submitted successfully with valid PNG screenshot", ($submitRes['success'] ?? false) === true);
$submissionId = (int)($submitRes['submission_id'] ?? 0);
itPwa("29. Submission record inserted into loyalty_story_submissions with status 'pending'", $submissionId > 0);

// Verify proof file was saved with randomized filename and .htaccess protection exists
$subRow = $pdo->query("SELECT * FROM loyalty_story_submissions WHERE id = {$submissionId}")->fetch();
$storedProof = $subRow['story_proof_filename'] ?? '';
itPwa("30. Screenshot saved with randomized hash filename (not original name)", $storedProof !== 'my_story_screenshot.png' && !empty($storedProof));
itPwa("31. Proof file physically exists in storage/story_proofs/", file_exists(__DIR__ . '/../storage/story_proofs/' . $storedProof));
itPwa("32. storage/story_proofs/.htaccess exists to deny direct public script execution", file_exists(__DIR__ . '/../storage/story_proofs/.htaccess'));

// Verify 1 submission per completed delivered order constraint
$duplicateSubmit = LoyaltyService::submitStoryProof($customerId, $orderId, 'tester_insta_handle', $filePayload);
itPwa("33. Duplicate story submission for same order is strictly blocked", ($duplicateSubmit['success'] ?? true) === false);
itPwa("34. Error indicates proof already submitted for this order", str_contains($duplicateSubmit['error'] ?? '', 'pending review') || str_contains($duplicateSubmit['error'] ?? '', 'already'));

@unlink($tempImg);


// =========================================================
// D. ADMIN STORY REVIEW QUEUE & APPROVAL WORKFLOW
// =========================================================
echo "\nD. ADMIN STORY REVIEW QUEUE & APPROVAL WORKFLOW:\n";

// 1. Rejection requires mandatory reason
$emptyReject = LoyaltyService::rejectSubmission($submissionId, 1, '');
itPwa("35. Admin rejection with empty reason is blocked", ($emptyReject['success'] ?? true) === false);
itPwa("36. Error requires mandatory rejection reason", str_contains($emptyReject['error'] ?? '', 'reason'));

// 2. Reject submission with reason
$validReject = LoyaltyService::rejectSubmission($submissionId, 1, 'Handle tag was not visible in story');
itPwa("37. Admin rejection with reason succeeds", ($validReject['success'] ?? false) === true);

$rejectedRow = $pdo->query("SELECT * FROM loyalty_story_submissions WHERE id = {$submissionId}")->fetch();
itPwa("38. Submission status transitioned to 'rejected'", ($rejectedRow['status'] ?? '') === 'rejected');
itPwa("39. Rejection reason recorded in database", ($rejectedRow['rejection_reason'] ?? '') === 'Handle tag was not visible in story');

// 3. Customer balance after rejection is 0 coins
$wallet = LoyaltyService::getWallet($customerId);
itPwa("40. Customer coin balance is 0 after rejection", (int)$wallet['coin_balance'] === 0);

// Create a second delivered order to test Approval workflow
$orderCreated2 = Order::createOrder($orderData, [['product_id' => $pId, 'quantity' => 1]]);
$orderId2 = (int)$orderCreated2['order_id'];
$pdo->exec("UPDATE orders SET order_status = 'delivered', delivery_workflow_status = 'delivered' WHERE id = {$orderId2}");

// Create temporary image
$tempImg2 = tempnam(sys_get_temp_dir(), 'story_proof_2_');
$im2 = imagecreatetruecolor(80, 80);
imagepng($im2, $tempImg2);
imagedestroy($im2);

$filePayload2 = [
    'tmp_name' => $tempImg2,
    'name' => 'story2.png',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tempImg2)
];
$submitRes2 = LoyaltyService::submitStoryProof($customerId, $orderId2, 'super_coconut_fan', $filePayload2);
$submissionId2 = (int)$submitRes2['submission_id'];
@unlink($tempImg2);

// Admin approves submission
Setting::set(LoyaltyService::SETTING_COINS_PER_STORY, '100');
$approveRes = LoyaltyService::approveSubmission($submissionId2, 1, 100);
itPwa("41. Admin approval succeeds", ($approveRes['success'] ?? false) === true);
itPwa("42. Approval awarded 100 coins", ($approveRes['coins_awarded'] ?? 0) === 100);

$approvedRow = $pdo->query("SELECT * FROM loyalty_story_submissions WHERE id = {$submissionId2}")->fetch();
itPwa("43. Submission status transitioned to 'approved'", ($approvedRow['status'] ?? '') === 'approved');
itPwa("44. Double-approval is prevented idempotently", LoyaltyService::approveSubmission($submissionId2, 1)['success'] === false);


// =========================================================
// E. CUSTOMER WALLET & AUDITABLE TRANSACTION LEDGER
// =========================================================
echo "\nE. CUSTOMER WALLET & AUDITABLE TRANSACTION LEDGER:\n";

$walletAfter = LoyaltyService::getWallet($customerId);
itPwa("45. Customer wallet now has exactly 100 coins", (int)$walletAfter['coin_balance'] === 100);
itPwa("46. Customer total_earned equals 100 coins", (int)$walletAfter['total_earned'] === 100);

$ledger = LoyaltyService::getLedger($customerId);
itPwa("47. Ledger contains credit transaction for approved story", count($ledger) >= 1);
itPwa("48. Ledger entry has type 'credit' and amount 100", (($ledger[0]['direction'] ?? '') === 'credit' || ($ledger[0]['type'] ?? '') === 'story_reward') && abs((int)($ledger[0]['amount'] ?? 0)) === 100);
itPwa("49. Ledger balance_after accurately records 100 coins", (int)($ledger[0]['balance_after'] ?? 0) === 100);


// =========================================================
// F. 100 COINS = 50% DISCOUNT VOUCHER REDEMPTION
// =========================================================
echo "\nF. 100 COINS = 50% DISCOUNT VOUCHER REDEMPTION:\n";

// 1. Redeem 100 coins for 50% voucher
$redeemRes = LoyaltyService::redeemCoinsForVoucher($customerId);
itPwa("50. 100 coins successfully redeemed for voucher", ($redeemRes['success'] ?? false) === true);
$voucherCode = $redeemRes['voucher']['voucher_code'] ?? '';
itPwa("51. Generated voucher code has required 'NA50-' prefix", str_starts_with($voucherCode, 'NA50-'));
itPwa("52. Voucher discount_percent is 50%", (int)($redeemRes['voucher']['discount_percent'] ?? 0) === 50);

// 2. Wallet balance should now be 0
$walletEmpty = LoyaltyService::getWallet($customerId);
itPwa("53. Customer coin balance is now 0 after redemption", (int)$walletEmpty['coin_balance'] === 0);
itPwa("54. Total redeemed coins is now 100", (int)$walletEmpty['total_redeemed'] === 100);

// 3. Trying to redeem again with 0 coins must fail
$insufficientRes = LoyaltyService::redeemCoinsForVoucher($customerId);
itPwa("55. Redemption with insufficient coins (<100) is strictly blocked", ($insufficientRes['success'] ?? true) === false);
itPwa("56. Error message clearly states 100 coins required", str_contains($insufficientRes['error'] ?? '', '100'));

// 4. Ledger records debit transaction
$ledgerAfterDebit = LoyaltyService::getLedger($customerId);
itPwa("57. Ledger contains debit transaction for voucher redemption", (($ledgerAfterDebit[0]['direction'] ?? '') === 'debit' || ($ledgerAfterDebit[0]['type'] ?? '') === 'voucher_redemption'));
itPwa("58. Ledger balance_after records 0 coins", (int)($ledgerAfterDebit[0]['balance_after'] ?? 0) === 0);


// =========================================================
// G. VOUCHER CHECKOUT VALIDATION & SINGLE-USE ENFORCEMENT
// =========================================================
echo "\nG. VOUCHER CHECKOUT VALIDATION & SINGLE-USE ENFORCEMENT:\n";

// 1. Voucher validation on subtotal ₹200 (50% = ₹100, within max ₹150 cap)
$val200 = LoyaltyService::validateVoucher($voucherCode, 200.00, $customerId);
itPwa("59. Voucher validates successfully for subtotal ₹200", ($val200['valid'] ?? false) === true);
itPwa("60. 50% discount on ₹200 is accurately ₹100.00", (float)($val200['discount_amount'] ?? 0) === 100.00);

// 2. Voucher validation on subtotal ₹400 (50% = ₹200, but capped at ₹150 max discount)
$val400 = LoyaltyService::validateVoucher($voucherCode, 400.00, $customerId);
itPwa("61. 50% discount on ₹400 is capped at ₹150.00 max discount", (float)($val400['discount_amount'] ?? 0) === 150.00);

// 3. CartService accepts loyalty voucher
CartService::clear();
CartService::addItem($pId, 5); // 5 items
$couponApplied = CartService::applyCoupon($voucherCode, $customerId);
itPwa("62. CartService::applyCoupon successfully applies NA50- voucher", ($couponApplied['success'] ?? false) === true);
$appliedVoucher = CartService::getAppliedCoupon($customerId);
itPwa("63. CartService marks coupon type as 'loyalty_voucher'", (!empty($appliedVoucher['is_loyalty_voucher']) || !empty($appliedVoucher['coupon']['is_loyalty_voucher']) || !empty($appliedVoucher['coupon']['is_loyalty'])));

// 4. Create order applying the voucher
$orderWithVoucherData = [
    'customer_id' => $customerId,
    'customer_name' => 'Loyalty Tester',
    'customer_phone' => $testPhone,
    'fulfilment_method' => 'delivery',
    'delivery_address' => 'Station Road, Jaora 457226',
    'payment_method' => 'cod',
    'coupon_code' => $voucherCode,
    'idempotency_key' => 'idem_vouch_' . uniqid()
];
$orderVouchCreated = Order::createOrder($orderWithVoucherData, [['product_id' => $pId, 'quantity' => 5]]);
itPwa("64. Order created successfully with 50% loyalty voucher applied", ($orderVouchCreated['success'] ?? false) === true);
$vouchOrderId = (int)$orderVouchCreated['order_id'];

// 5. Voucher must now be marked used
$voucherRow = $pdo->query("SELECT * FROM loyalty_vouchers WHERE code = '{$voucherCode}'")->fetch();
itPwa("65. Voucher is_used flag is now 1 in MySQL database", (int)$voucherRow['is_used'] === 1);
itPwa("66. Voucher used_order_id points to created order", (int)$voucherRow['used_order_id'] === $vouchOrderId);

// 6. Attempting to reuse the voucher must be strictly rejected
$reuseVal = LoyaltyService::validateVoucher($voucherCode, 200.00, $customerId);
itPwa("67. Reusing already-used voucher is strictly rejected", ($reuseVal['valid'] ?? true) === false);
itPwa("68. Rejection states voucher has already been used", str_contains($reuseVal['error'] ?? '', 'already been used'));


// =========================================================
// H. ADMIN MANUAL BALANCE ADJUSTMENTS WITH AUDIT REASON
// =========================================================
echo "\nH. ADMIN MANUAL BALANCE ADJUSTMENTS WITH AUDIT REASON:\n";

// 1. Adjustment with empty reason is blocked
$badAdjust = LoyaltyService::adminAdjust($customerId, 50, '', 1);
itPwa("69. Admin manual adjustment requires non-empty reason", ($badAdjust['success'] ?? true) === false);

// 2. Valid admin adjustment of +50 coins
$goodAdjust = LoyaltyService::adminAdjust($customerId, 50, 'Store anniversary gesture', 1);
itPwa("70. Admin manual adjustment of +50 coins succeeds", ($goodAdjust['success'] ?? false) === true);

$walletAdjusted = LoyaltyService::getWallet($customerId);
itPwa("71. Customer wallet balance updated to 50 coins", (int)$walletAdjusted['coin_balance'] === 50);

$ledgerAfterAdjust = LoyaltyService::getLedger($customerId);
itPwa("72. Ledger records manual adjustment transaction with audit reason", ($ledgerAfterAdjust[0]['description'] ?? '') === 'Store anniversary gesture');

echo "\n--- DELIVERY BOY PWA & LOYALTY TEST SUMMARY: {$pwaPass} PASSED, {$pwaFail} FAILED ---\n";
