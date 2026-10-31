<?php
/**
 * Test Suite: Checkout Bug Fixes & Payment Methods
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

echo "========================================================\n";
echo "RUNNING CHECKOUT BUG FIXES & PAYMENT METHODS TEST SUITE\n";
echo "========================================================\n";

$passCount = 0;
$failCount = 0;

function assertCheck(bool $condition, string $title): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$title}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title}\n";
    }
}

// Set up authenticated test customer
$customerPhone = '9876543210';
$customer = Customer::findByPhone($customerPhone);
if (!$customer) {
    $cId = Customer::create([
        'name' => 'Checkout Tester',
        'phone' => $customerPhone,
        'password' => 'Pass@123',
        'address' => '42 Test Colony, Jaora'
    ]);
    $customer = Customer::findById(is_array($cId) ? (int)$cId['id'] : (int)$cId);
}
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_ID] = (int)$customer['id'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_PHONE] = $customerPhone;
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_NAME] = $customer['name'];

// 1. Delivery Charge Calculation & Visibility Tests
CartService::clear();
$products = Product::getAll();
$prod = $products[0];
CartService::addItem((int)$prod['id'], 2); // 2 x 40 = 80 (< 500)

$totalsStandard = CartService::calculateCheckoutTotals('delivery', (int)$customer['id'], $customerPhone);
assertCheck($totalsStandard['subtotal'] == 80.00, 'Cart subtotal matches items (₹80.00)');
assertCheck($totalsStandard['delivery_charge'] > 0, 'Standard delivery charge is present and non-zero (₹' . $totalsStandard['delivery_charge'] . ')');
assertCheck($totalsStandard['total'] == (80.00 + $totalsStandard['delivery_charge']), 'Total correctly includes product subtotal and delivery charge');

// 2. Free Delivery Condition Test (Subtotal >= threshold)
CartService::clear();
CartService::addItem((int)$prod['id'], 15); // 15 x 40 = 600 (>= 500)
$totalsFree = CartService::calculateCheckoutTotals('delivery', (int)$customer['id'], $customerPhone);
assertCheck($totalsFree['subtotal'] == 600.00, 'High value subtotal is ₹600.00');
assertCheck($totalsFree['delivery_charge'] == 0.00, 'Free delivery applied automatically when subtotal >= ₹500');
assertCheck($totalsFree['total'] == 600.00, 'Final payable excludes delivery charge when free');

// 3. Coupon Free Delivery Test
CartService::clear();
CartService::addItem((int)$prod['id'], 3); // 3 x 40 = 120
$couponRes = CartService::applyCoupon('FREEDELIVERY', (int)$customer['id'], $customerPhone);
assertCheck($couponRes['success'] === true, 'FREEDELIVERY coupon applied');
$totalsCoupon = CartService::calculateCheckoutTotals('delivery', (int)$customer['id'], $customerPhone);
assertCheck($totalsCoupon['delivery_discount'] > 0, 'Coupon delivery discount is recorded');
assertCheck($totalsCoupon['delivery_charge'] == 0.00, 'Delivery charge reduced to ₹0.00 via coupon');
assertCheck($totalsCoupon['total'] == 120.00, 'Final payable equals subtotal without delivery charge');
CartService::removeCoupon();

// 4. COD Order Placement Test
CartService::clear();
CartService::addItem((int)$prod['id'], 2);
$codData = [
    CSRF_TOKEN_KEY => csrf_token(),
    'customer_name' => $customer['name'],
    'customer_phone' => $customerPhone,
    'delivery_address' => 'Station Road, Opposite Railway Station, Jaora - 457226',
    'fulfilment_method' => 'delivery',
    'payment_method' => 'cod',
    'idempotency_key' => 'cod_test_' . bin2hex(random_bytes(8))
];
$codOrder = OrderService::processCheckout($codData);
assertCheck($codOrder['success'] === true, 'COD order placed successfully');
assertCheck($codOrder['requires_razorpay'] === false, 'COD order does not require Razorpay redirect');

$dbCodOrder = Order::getById((int)$codOrder['order_id']);
assertCheck($dbCodOrder['payment_status'] === 'pending', 'COD payment status is strictly Pending / Pay on Delivery (NEVER marked Paid prematurely)');
assertCheck($dbCodOrder['order_status'] === 'confirmed', 'COD order status is confirmed');

// 5. Razorpay Online Order Initiation Test
CartService::clear();
CartService::addItem((int)$prod['id'], 2);
$onlineData = [
    CSRF_TOKEN_KEY => csrf_token(),
    'customer_name' => $customer['name'],
    'customer_phone' => $customerPhone,
    'delivery_address' => 'Mahatma Gandhi Marg, Near Bus Stand, Jaora - 457226',
    'fulfilment_method' => 'delivery',
    'payment_method' => 'online',
    'idempotency_key' => 'online_test_' . bin2hex(random_bytes(8))
];
$onlineOrder = OrderService::processCheckout($onlineData);
assertCheck($onlineOrder['success'] === true, 'Online order initiated successfully');
assertCheck($onlineOrder['requires_razorpay'] === true, 'Online order returns requires_razorpay = true');
assertCheck(!empty($onlineOrder['razorpay_order_id']), 'Server generated Razorpay gateway order_id');
assertCheck($onlineOrder['amount'] == round((float)$onlineOrder['order_total'] * 100), 'Razorpay amount is in integer paise matching order total');

$dbOnlineOrder = Order::getById((int)$onlineOrder['order_id']);
assertCheck($dbOnlineOrder['payment_status'] === 'pending', 'Online payment status is initialized to Pending before capture');

// 6. Switching Payment Methods Test
assertCheck(RazorpayService::isCodEnabled() === true, 'COD is selectable and active');
assertCheck(RazorpayService::isRazorpayEnabled() === true, 'Razorpay online is selectable and active');

// 7. Invalid Checkout Information Handling Tests
$invalidNameData = $codData;
$invalidNameData['customer_name'] = '';
$invalidNameData['idempotency_key'] = 'inv_name_' . bin2hex(random_bytes(8));
$invNameRes = OrderService::processCheckout($invalidNameData);
assertCheck($invNameRes['success'] === false, 'Blank customer name rejected');

$invalidPhoneData = $codData;
$invalidPhoneData['customer_phone'] = '123';
$invalidPhoneData['idempotency_key'] = 'inv_phone_' . bin2hex(random_bytes(8));
$invPhoneRes = OrderService::processCheckout($invalidPhoneData);
assertCheck($invPhoneRes['success'] === false, 'Invalid mobile number (<10 digits) rejected');

$invalidAddrData = $codData;
$invalidAddrData['delivery_address'] = 'Short';
$invalidAddrData['idempotency_key'] = 'inv_addr_' . bin2hex(random_bytes(8));
$invAddrRes = OrderService::processCheckout($invalidAddrData);
assertCheck($invAddrRes['success'] === false, 'Short/incomplete delivery address rejected');

// 8. Unserviceable Delivery Area Test
$unserviceableData = $codData;
$unserviceableData['latitude'] = 28.6139; // Delhi coordinates (outside Jaora polygon boundaries)
$unserviceableData['longitude'] = 77.2090;
$unserviceableData['idempotency_key'] = 'inv_geo_' . bin2hex(random_bytes(8));
// Temporarily create active polygon boundary to test spatial rejection
$areaRes = DeliveryArea::create([
    'name' => 'Jaora Exclusive Zone',
    'boundary_geojson' => json_encode([
        'type' => 'Polygon',
        'coordinates' => [[[75.12, 23.88], [75.14, 23.88], [75.14, 23.90], [75.12, 23.90], [75.12, 23.88]]]
    ]),
    'delivery_charge' => 35.00,
    'status' => 'active'
]);
$geoElig = AreaService::checkDeliveryEligibility(28.6139, 77.2090);
assertCheck($geoElig['deliverable'] === false, 'Out-of-zone location correctly detected as undeliverable');

// Cleanup test area
if ($areaRes['success']) {
    DeliveryArea::delete((int)$areaRes['id']);
}

// 9. Repeated Place Order Clicks / Idempotency Test
CartService::clear();
CartService::addItem((int)$prod['id'], 2);
$repeatKey = 'idempotent_test_' . bin2hex(random_bytes(8));
$repeatData = $codData;
$repeatData['idempotency_key'] = $repeatKey;
$firstPlacement = OrderService::processCheckout($repeatData);
assertCheck($firstPlacement['success'] === true, 'First order click places order');

$duplicatePlacement = OrderService::processCheckout($repeatData);
assertCheck($duplicatePlacement['success'] === true, 'Second identical click handled idempotently');
assertCheck($duplicatePlacement['order_id'] == $firstPlacement['order_id'], 'Second click returns existing order (no duplicate duplicate orders created)');

// 10. Server-Verified Payment State Test
$mockPaymentId = 'pay_mock_' . bin2hex(random_bytes(6));
PaymentTransaction::updateStatus((int)$onlineOrder['transaction_id'], 'captured', [
    'gateway_payment_id' => $mockPaymentId
]);
Order::updatePaymentAndOrderStatus((int)$onlineOrder['order_id'], 'captured', 'confirmed', [
    'razorpay_payment_id' => $mockPaymentId
]);
$capturedOrder = Order::getById((int)$onlineOrder['order_id']);
assertCheck($capturedOrder['payment_status'] === 'captured', 'Verified online payment marked captured');
assertCheck($capturedOrder['order_status'] === 'confirmed', 'Verified online order confirmed');

echo "========================================================\n";
echo "CHECKOUT FIXES SUITE SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
