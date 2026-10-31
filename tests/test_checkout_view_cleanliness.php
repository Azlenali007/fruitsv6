<?php
/**
 * Test Suite: Checkout View Cleanliness & Alpine.js Component Integrity
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

echo "========================================================\n";
echo "RUNNING CHECKOUT VIEW & SCRIPT INTEGRITY TEST SUITE\n";
echo "========================================================\n";

$passCount = 0;
$failCount = 0;

function assertView(bool $condition, string $title): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$title}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title}\n";
    }
}

// 1. Setup Customer Session with Address containing quotes and newlines
$testCustomerPhone = '9876543210';
$customer = Customer::findByPhone($testCustomerPhone);
if (!$customer) {
    $cId = Customer::create([
        'name' => 'Dr. Rajesh "Raju" O\'Connor',
        'phone' => $testCustomerPhone,
        'password' => 'Pass@123',
        'address' => "Flat #402, Royal Palms Apt.\nNear \"City Hospital\", M.G. Road & Station\nJaora - 457226"
    ]);
    $customer = Customer::findById(is_array($cId) ? (int)$cId['id'] : (int)$cId);
} else {
    Customer::updateProfile((int)$customer['id'], [
        'name' => 'Dr. Rajesh "Raju" O\'Connor',
        'address' => "Flat #402, Royal Palms Apt.\nNear \"City Hospital\", M.G. Road & Station\nJaora - 457226"
    ]);
    $customer = Customer::findById((int)$customer['id']);
}

$_SESSION[CustomerAuthService::SESSION_CUSTOMER_ID] = (int)$customer['id'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_PHONE] = $testCustomerPhone;
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_NAME] = $customer['name'];

CartService::clear();
$products = Product::getAll();
$prod = $products[0];
CartService::addItem((int)$prod['id'], 2);

// 2. Render Checkout View to Buffer
ob_start();
require __DIR__ . '/../app/views/checkout.php';
$html = ob_get_clean();

// 3. Test: No leaked JavaScript expressions in HTML Body
$bodyWithoutScripts = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
assertView(strpos($bodyWithoutScripts, '= this.freeDeliveryAbove') === false, 'No leaked "= this.freeDeliveryAbove" in HTML body');
assertView(strpos($bodyWithoutScripts, 'return Math.max(0, this.defaultDeliveryFee') === false, 'No leaked "Math.max" in HTML body');
assertView(strpos($bodyWithoutScripts, 'get total()') === false, 'No leaked "get total()" in HTML body');
assertView(strpos($bodyWithoutScripts, 'get effectiveDeliveryFee()') === false, 'No leaked "get effectiveDeliveryFee()" in HTML body');

// 4. Test: Component pattern x-data attribute is clean
assertView(strpos($html, 'x-data="checkoutPage(window.__CHECKOUT_CONFIG__)"') !== false, 'Clean x-data="checkoutPage(window.__CHECKOUT_CONFIG__)" present');

// 5. Test: window.__CHECKOUT_CONFIG__ is valid JSON
preg_match('/window\.__CHECKOUT_CONFIG__\s*=\s*(\{.*?\});/s', $html, $matches);
assertView(!empty($matches[1]), 'window.__CHECKOUT_CONFIG__ object extracted from <script>');

$parsedConfig = json_decode($matches[1] ?? '', true);
assertView(json_last_error() === JSON_ERROR_NONE, 'window.__CHECKOUT_CONFIG__ is syntactically valid JSON');
assertView(!empty($parsedConfig['customerName']), 'Config preserves customer name with special chars: ' . ($parsedConfig['customerName'] ?? ''));
assertView(!empty($parsedConfig['customerAddress']), 'Config preserves multiline customer address');
assertView(isset($parsedConfig['subtotal']), 'Config subtotal is present (' . ($parsedConfig['subtotal'] ?? '') . ')');
assertView(isset($parsedConfig['defaultDeliveryFee']), 'Config defaultDeliveryFee is present');
assertView(isset($parsedConfig['freeDeliveryAbove']), 'Config freeDeliveryAbove is present');
assertView(!empty($parsedConfig['csrfToken']), 'Config contains valid session CSRF token');

// 6. Test: Alpine.data registration present in script
assertView(strpos($html, "Alpine.data('checkoutPage'") !== false, "Alpine.data('checkoutPage') component registration found");
assertView(strpos($html, "document.addEventListener('alpine:init'") !== false, "alpine:init event listener registered");

// 7. Test: Delivery Area API endpoint verification
$locCheckRes = AreaService::checkDeliveryEligibility(null, null, null);
assertView($locCheckRes['deliverable'] === true, 'Default store delivery eligibility is deliverable');
assertView($locCheckRes['delivery_charge'] > 0, 'Default store delivery charge returned: ₹' . $locCheckRes['delivery_charge']);

// 8. Test: Razorpay Checkout script conditionally rendered
assertView(strpos($html, 'https://checkout.razorpay.com/v1/checkout.js') !== false, 'Razorpay checkout.js script included');

// 9. Test: Payment Methods UI elements present
assertView(strpos($html, "paymentMethod === 'cod'") !== false, 'Cash on Delivery selector present');
assertView(strpos($html, "paymentMethod === 'online'") !== false, 'Razorpay online payment selector present');

// 10. Test: Place Order button disabled state and submit handler
assertView(strpos($html, '@submit="handleCheckoutSubmit($event)"') !== false, 'Form submit handled by handleCheckoutSubmit');
assertView(strpos($html, 'x-text="submitButtonText"') !== false, 'Button text bound to submitButtonText getter');

echo "========================================================\n";
echo "CHECKOUT VIEW CLEANLINESS SUITE: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
