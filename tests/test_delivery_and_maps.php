<?php
/**
 * Dedicated Google Maps Integration & Jaora-Only Delivery Test Suite
 * N.A Fresh Fruits & Coconuts
 */

require_once __DIR__ . '/../app/bootstrap.php';

$pdo = Database::getConnection();
$mapsPass = 0;
$mapsFail = 0;

function it_map(string $testName, bool $condition, string $detail = '') {
    global $mapsPass, $mapsFail;
    if ($condition) {
        $mapsPass++;
        echo "  [PASS] {$testName}\n";
    } else {
        $mapsFail++;
        echo "  [FAIL] {$testName} - {$detail}\n";
    }
}

echo "========================================================\n";
echo "16. GOOGLE MAPS PLATFORM & JAORA RESTRICTION TESTS\n";
echo "========================================================\n\n";

// -------------------------------------------------------------
// A. VERIFIED FIXED SHOP LOCATION & GOOGLE MAPS CONFIGURATION
// -------------------------------------------------------------
echo "A. FIXED SHOP LOCATION & GOOGLE MAPS CONFIG:\n";

$storeLat = DeliveryDispatchService::getStoreLat();
$storeLng = DeliveryDispatchService::getStoreLng();
$storeAddr = DeliveryDispatchService::getStoreAddress();
$gmpKey = DeliveryDispatchService::getGoogleMapsApiKey();

it_map("1. Store Latitude strictly equals 23.635784", abs($storeLat - 23.635784) < 0.000001, "Actual: {$storeLat}");
it_map("2. Store Longitude strictly equals 75.124683", abs($storeLng - 75.124683) < 0.000001, "Actual: {$storeLng}");
it_map("3. Store Address is located in Jaora, MP (PIN 457226)", str_contains($storeAddr, 'Jaora') && str_contains($storeAddr, '457226'), "Address: {$storeAddr}");
it_map("4. Google Maps API Key is provisioned and non-empty", !empty($gmpKey) && str_starts_with($gmpKey, 'AIza'), "Key: {$gmpKey}");

// -------------------------------------------------------------
// B. JAORA BOUNDARY RESTRICTION & PIN VERIFICATION
// -------------------------------------------------------------
echo "\nB. STRICT JAORA-ONLY DELIVERY RESTRICTION:\n";

// B1. Inside Jaora coordinates
$insideCheck = AreaService::validateJaoraDelivery("Station Road, Jaora, MP 457226", 23.635784, 75.124683);
it_map("5. Coordinates inside Jaora polygon are validated successfully", $insideCheck['valid'] === true, $insideCheck['error'] ?? '');

// B2. Outside Jaora coordinates (Bhopal coordinates)
$outsideCheck = AreaService::validateJaoraDelivery("Arera Colony, Bhopal, MP 462001", 23.2599, 77.4126);
it_map("6. Coordinates outside Jaora polygon are strictly blocked", $outsideCheck['valid'] === false);
it_map("7. Blocked outside delivery returns exact required message", 
    $outsideCheck['error'] === 'Sorry, currently we deliver only within Jaora, Madhya Pradesh - 457226.', 
    "Returned: " . ($outsideCheck['error'] ?? '')
);

// B3. Non-457226 PIN code in address is blocked
$badPinCheck = AreaService::validateJaoraDelivery("Street 5, Ratlam, Madhya Pradesh - 457001", 23.635784, 75.124683);
it_map("8. Non-457226 PIN code is blocked even if spoofed coords provided", 
    $badPinCheck['valid'] === false && $badPinCheck['error'] === 'Sorry, currently we deliver only within Jaora, Madhya Pradesh - 457226.',
    $badPinCheck['error'] ?? ''
);

// B4. Shop pickup is independently available
$testCust = Customer::findByPhone('9876543210');
if (!$testCust) {
    $cId = Customer::create(['name' => 'Jaora Customer', 'phone' => '9876543210']);
    $testCust = Customer::findById($cId);
}
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_ID] = (int)$testCust['id'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_NAME] = $testCust['name'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_PHONE] = $testCust['phone'];

$pickupProd = Product::getPrimary();
CartService::clear();
CartService::addItem($pickupProd['id'], 1);

Setting::set('shop_pickup_enabled', '1');

$pickupCheck = OrderService::processCheckout([
    CSRF_TOKEN_KEY => csrf_token(),
    'customer_name' => 'Jaora Customer',
    'customer_phone' => '9876543210',
    'fulfilment_method' => 'pickup',
    'delivery_address' => 'Store Self-Pickup',
    'payment_method' => 'cod',
    'idempotency_key' => 'idem_pickup_' . uniqid()
]);

Setting::set('shop_pickup_enabled', '0');

it_map("9. Store Self-Pickup order can be placed with ₹0 delivery charge when enabled", 
    $pickupCheck['success'] === true && (float)$pickupCheck['delivery_charge'] === 0.0,
    $pickupCheck['error'] ?? ''
);

// -------------------------------------------------------------
// C. DELIVERY BOY ACCEPTANCE ATOMICITY & RACE CONDITION GUARD
// -------------------------------------------------------------
echo "\nC. DELIVERY BOY ATOMIC ACCEPTANCE & WORKFLOW:\n";

// Create 2 test riders
$pdo->exec("DELETE FROM `delivery_boys` WHERE `login_id` LIKE 'maps_rider_%'");
$r1 = DeliveryBoy::create([
    'name' => 'Maps Rider 1',
    'mobile' => '9888800001',
    'login_id' => 'maps_rider_1',
    'password' => 'secret123',
    'status' => 'active'
]);
$r2 = DeliveryBoy::create([
    'name' => 'Maps Rider 2',
    'mobile' => '9888800002',
    'login_id' => 'maps_rider_2',
    'password' => 'secret123',
    'status' => 'active'
]);

// Place a delivery order inside Jaora
$prod = Product::getPrimary();
CartService::clear();
CartService::addItem($prod['id'], 1);

// Authenticate customer
$cust = Customer::findByPhone('9876543210');
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_ID] = (int)$cust['id'];

$orderRes = OrderService::processCheckout([
    CSRF_TOKEN_KEY => csrf_token(),
    'customer_name' => 'Jaora Customer',
    'customer_phone' => '9876543210',
    'fulfilment_method' => 'delivery',
    'delivery_address' => 'Station Road, Jaora, MP 457226',
    'latitude' => 23.635784,
    'longitude' => 75.124683,
    'payment_method' => 'cod',
    'idempotency_key' => 'idem_maps_ord_' . uniqid()
]);

it_map("10. Delivery order inside Jaora created successfully", $orderRes['success'] === true);
$orderId = (int)$orderRes['order_id'];

// Rider 1 accepts order
$accept1 = DeliveryDispatchService::acceptOrderAtomic($orderId, $r1);
it_map("11. First rider accepts order atomically", $accept1['success'] === true && !empty($accept1['redirect_url']));

// Rider 2 attempts to accept the SAME order simultaneously
$accept2 = DeliveryDispatchService::acceptOrderAtomic($orderId, $r2);
it_map("12. Simultaneous second acceptance is atomically blocked", $accept2['success'] === false);

// -------------------------------------------------------------
// D. STRICT 9-STEP WORKFLOW STATE MACHINE
// -------------------------------------------------------------
echo "\nD. STRICT WORKFLOW STATE MACHINE TRANSITIONS:\n";

// Attempt invalid transition: accepted -> delivered directly (MUST FAIL)
$badJump = DeliveryDispatchService::updateWorkflowStatus($orderId, $r1, 'delivered');
it_map("13. Skipping directly from 'accepted' to 'delivered' is strictly blocked", $badJump['success'] === false);

// Transition to reached_shop
$step1 = DeliveryDispatchService::updateWorkflowStatus($orderId, $r1, 'reached_shop');
it_map("14. Step 1: Transition to 'reached_shop' succeeds", $step1['success'] === true);

// Transition to picked_up
$step2 = DeliveryDispatchService::updateWorkflowStatus($orderId, $r1, 'picked_up');
it_map("15. Step 2: Transition to 'picked_up' succeeds", $step2['success'] === true);

// Transition to out_for_delivery
$step3 = DeliveryDispatchService::updateWorkflowStatus($orderId, $r1, 'out_for_delivery');
it_map("16. Step 3: Transition to 'out_for_delivery' generates delivery OTP", $step3['success'] === true);

// Check OTP was generated
$otpInfo = DeliveryDispatchService::getOtpInfo($orderId);
$otp = $otpInfo['plain_otp_for_dev'] ?? '';
it_map("17. Valid 4-digit numeric OTP generated", strlen($otp) === 4 && ctype_digit($otp));

// Attempt to deliver without COD payment collection
$prematureDelivery = DeliveryDispatchService::updateWorkflowStatus($orderId, $r1, 'delivered', 23.635784, 75.124683, $otp);
it_map("18. Delivery blocked before COD payment is collected", $prematureDelivery['success'] === false && !empty($prematureDelivery['requires_cod_collection']));

// Record COD cash collection
$cashCollected = DeliveryDispatchService::recordCodCollection($orderId, $r1, (float)$orderRes['total_amount'], 'Cash collected at doorstep');
it_map("19. COD cash collection recorded", $cashCollected['success'] === true);

// Now verify OTP and complete delivery
$finalDelivery = DeliveryDispatchService::updateWorkflowStatus($orderId, $r1, 'delivered', 23.635784, 75.124683, $otp);
it_map("20. Valid OTP completes delivery", $finalDelivery['success'] === true);

$finalOrder = Order::getById($orderId);
it_map("21. Final order status is 'delivered'", $finalOrder['order_status'] === 'delivered');
it_map("22. Final workflow status is 'delivered'", $finalOrder['delivery_workflow_status'] === 'delivered');

// Cleanup
$pdo->exec("DELETE FROM `orders` WHERE `id` = {$orderId}");
$pdo->exec("DELETE FROM `delivery_boys` WHERE `login_id` LIKE 'maps_rider_%'");

echo "\n========================================================\n";
echo "GOOGLE MAPS & JAORA RESTRICTION SUMMARY: {$mapsPass} PASSED, {$mapsFail} FAILED\n";
echo "========================================================\n";

if ($mapsFail > 0) {
    exit(1);
}
