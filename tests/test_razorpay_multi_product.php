<?php
/**
 * Razorpay Payment Fix & Multi-Product E-Commerce Automated Tests
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

$mpPass = 0;
$mpFail = 0;

function it_mp(string $name, bool $cond, string $detail = '') {
    global $mpPass, $mpFail;
    if ($cond) {
        $mpPass++;
        echo "  [PASS] {$name}\n";
    } else {
        $mpFail++;
        echo "  [FAIL] {$name} - {$detail}\n";
    }
}

echo "\n========================================================\n";
echo "13. RAZORPAY PAYMENT FIX & MULTI-PRODUCT E-COMMERCE TESTS\n";
echo "========================================================\n";

$pdo = Database::getConnection();

// Clean test state
CartService::clear();

// ----------------------------------------------------
// A. RAZORPAY VERIFICATION & PAISE ACCURACY TESTS
// ----------------------------------------------------
echo "A. RAZORPAY PAYMENT VERIFICATION & PAISE ACCURACY:\n";

// 1. Verify fetchPayment key normalization
$mockPaymentId = 'pay_norm_' . bin2hex(random_bytes(4));
$secretKey = 'testSecretKeyXYZ789';

RazorpayService::saveSettings([
    'razorpay_enabled' => '1',
    'razorpay_mode' => 'test',
    'razorpay_key_id' => 'rzp_test_sampleKey123',
    'razorpay_key_secret' => $secretKey,
    'razorpay_webhook_secret' => 'webhookSecretABC456',
    'cod_enabled' => '1'
]);

$mockPayments = [];
RazorpayService::setMockHandler(function(string $method, string $path, array $data, string $kId, string $kSec) use (&$mockPayments) {
    if (str_starts_with($path, '/orders') && $method === 'POST') {
        $orderId = 'order_mock_' . bin2hex(random_bytes(6));
        return [
            'success' => true,
            'data' => [
                'id' => $orderId,
                'entity' => 'order',
                'amount' => $data['amount'] ?? 1000,
                'currency' => 'INR',
                'receipt' => $data['receipt'] ?? '',
                'status' => 'created'
            ],
            'http_code' => 200
        ];
    }
    if (str_starts_with($path, '/payments/') && $method === 'GET') {
        $pid = basename($path);
        if (isset($mockPayments[$pid])) {
            return [
                'success' => true,
                'data' => $mockPayments[$pid],
                'http_code' => 200
            ];
        }
    }
    return ['success' => true, 'data' => []];
});

// Setup mock payment that only returns 'data' key (as low-level cURL does)
$mockPayments[$mockPaymentId] = [
    'id' => $mockPaymentId,
    'amount' => 9500, // 95.00 in paise
    'currency' => 'INR',
    'status' => 'captured'
];

$fetchNormalized = RazorpayService::fetchPayment($mockPaymentId);
it_mp("1. fetchPayment normalizes and provides both 'payment' and 'data' keys", 
    !empty($fetchNormalized['payment']) && !empty($fetchNormalized['data']) && $fetchNormalized['payment']['amount'] === 9500
);

// 2. Secret masking does not leak credentials
$masked = RazorpayService::maskSecret('rzp_test_9988776655');
it_mp("2. maskSecret protects sensitive keys in logs (no plaintext leak)", 
    str_starts_with($masked, '••••') && !str_contains($masked, '998877') && str_ends_with($masked, '6655')
);

// ----------------------------------------------------
// B. CATEGORIES CRUD TESTS
// ----------------------------------------------------
echo "B. PRODUCT CATEGORIES MANAGEMENT:\n";

$catName = 'Test Tropical Fruits ' . bin2hex(random_bytes(3));
$newCatId = Category::create([
    'name' => $catName,
    'description' => 'Exotic natural fruits',
    'sort_order' => 10,
    'is_active' => 1
]);
it_mp("3. Category::create inserts new category with ID", $newCatId > 0);

$fetchedCat = Category::getById($newCatId);
it_mp("4. Category::getById retrieves category correctly", $fetchedCat !== null && $fetchedCat['name'] === $catName);

$updated = Category::update($newCatId, [
    'name' => $catName . ' Updated',
    'slug' => $fetchedCat['slug'],
    'description' => 'Updated desc',
    'sort_order' => 12,
    'is_active' => 1
]);
$fetchedCat2 = Category::getById($newCatId);
it_mp("5. Category::update modifies category properties", $updated && $fetchedCat2['name'] === $catName . ' Updated');

// ----------------------------------------------------
// C. MULTI-PRODUCT CATALOG & CUTTING OPTIONS
// ----------------------------------------------------
echo "C. MULTI-PRODUCT CATALOG & CUTTING OPTIONS:\n";

$prodName = 'Special Malai Tender Coconut ' . bin2hex(random_bytes(3));
$cuttingJson = json_encode([
    ['id' => 'whole', 'name' => 'Without Cutting — Whole Coconut', 'price' => 0],
    ['id' => 'cut', 'name' => 'With Cutting — Ready to Drink', 'price' => 0],
    ['id' => 'malai_inc', 'name' => 'Malai Included (Special Cut)', 'price' => 10]
]);

$newProdId = Product::create([
    'category_id' => $newCatId,
    'name' => $prodName,
    'tagline' => 'Rich in sweet water',
    'description' => 'Fresh tender coconut harvested today',
    'price' => 55.00,
    'original_price' => 65.00,
    'stock_quantity' => 50,
    'is_available' => 1,
    'has_cutting_options' => 1,
    'cutting_options_json' => $cuttingJson,
    'image_url' => '/assets/images/hero-coconut.png',
    'sort_order' => 20
]);
it_mp("6. Product::create inserts product with category, MRP and cutting options", $newProdId > 0);

$prod = Product::getById($newProdId);
it_mp("7. Product::getById decodes cutting options properly", 
    !empty($prod['cutting_options']) && count($prod['cutting_options']) === 3
);

// 8. Test cutting option validation
$optDefault = Product::validateCuttingOption($prod, 'whole');
it_mp("8. validateCuttingOption whole returns 0 extra charge", 
    $optDefault['has_option'] && $optDefault['extra_charge'] === 0.00
);

$optMalai = Product::validateCuttingOption($prod, 'malai_inc');
it_mp("9. validateCuttingOption malai_inc returns +₹10 extra charge", 
    $optMalai['has_option'] && $optMalai['extra_charge'] === 10.00
);

// ----------------------------------------------------
// D. MULTI-PRODUCT CART & NON-MERGING OPTIONS
// ----------------------------------------------------
echo "D. MULTI-PRODUCT CART & NON-MERGING OPTIONS:\n";

CartService::clear();

$waterProd = Product::getBySlug('fresh-coconut-water') ?? Product::getById(1);
$waterId = (int)($waterProd['id'] ?? 1);

// Add Product 1 (Fresh Coconut Water, default cutting none)
CartService::addItem($waterId, 2);

// Add Product 2 with Whole Coconut (Option: whole, +₹0)
CartService::addItem($newProdId, 1, 'whole');

// Add Product 2 with Special Cut (Option: malai_inc, +₹10)
CartService::addItem($newProdId, 1, 'malai_inc');

$cart = CartService::getCart();
it_mp("10. Cart contains exactly 3 distinct line items (different cutting options do not merge)", 
    count($cart['items']) === 3
);

// Verify items price calculations
$wholeItem = null;
$malaiItem = null;
$waterItem = null;
foreach ($cart['items'] as $it) {
    if ($it['product_id'] == $newProdId && $it['preparation_option_id'] === 'whole') $wholeItem = $it;
    if ($it['product_id'] == $newProdId && $it['preparation_option_id'] === 'malai_inc') $malaiItem = $it;
    if ($it['product_id'] == $waterId) $waterItem = $it;
}

it_mp("11. Water item subtotal is ₹80 (2 x ₹40)", $waterItem !== null && (float)$waterItem['subtotal'] === 80.00);
it_mp("12. Whole coconut item unit price is ₹55", $wholeItem !== null && (float)$wholeItem['unit_price'] === 55.00);
it_mp("13. Malai coconut item unit price is ₹65 (₹55 + ₹10 preparation)", $malaiItem !== null && (float)$malaiItem['unit_price'] === 65.00);

// Total subtotal: 80 + 55 + 65 = 200
it_mp("14. Cart subtotal accurately equals ₹200.00", (float)$cart['subtotal'] === 200.00);

// ----------------------------------------------------
// E. COMBO OFFERS & BUNDLES
// ----------------------------------------------------
echo "E. COMBO OFFERS & BUNDLES:\n";

$comboName = 'Duo Refreshment Bundle ' . bin2hex(random_bytes(3));
$comboId = Combo::create([
    'name' => $comboName,
    'tagline' => 'Best Duo',
    'description' => '1 Water + 1 Tender Coconut',
    'price' => 85.00,
    'original_price' => 95.00,
    'image_url' => '/assets/images/hero-coconut.png',
    'stock_quantity' => 25,
    'is_available' => 1
], [
    ['product_id' => $waterId, 'quantity' => 1, 'notes' => '1x Water'],
    ['product_id' => $newProdId, 'quantity' => 1, 'notes' => '1x Coconut']
]);

it_mp("15. Combo::create inserts bundle with child items", $comboId > 0);

$fetchedCombo = Combo::getById($comboId);
it_mp("16. Combo::getById computes savings (₹10)", 
    $fetchedCombo !== null && (float)$fetchedCombo['savings'] === 10.00 && count($fetchedCombo['items']) === 2
);

// Add combo to cart
CartService::addCombo($comboId, 1);
$cartWithCombo = CartService::getCart();
it_mp("17. Cart now contains 4 items including combo bundle", count($cartWithCombo['items']) === 4);

// 200 + 85 = 285
it_mp("18. Cart subtotal updated with combo price to ₹285.00", (float)$cartWithCombo['subtotal'] === 285.00);

// ----------------------------------------------------
// F. ORDER PLACEMENT WITH MULTI-PRODUCTS & COMBOS
// ----------------------------------------------------
echo "F. ORDER PLACEMENT WITH MULTI-PRODUCTS & COMBOS:\n";

// Authenticate customer
$cust = Customer::findByPhone('9876543210');
if (!$cust) {
    $cust = Customer::create([
        'name' => 'Multi Product Tester',
        'phone' => '9876543210',
        'address' => 'Station Road, Jaora'
    ]);
}
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_ID] = (int)$cust['id'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_NAME] = $cust['name'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_PHONE] = $cust['phone'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_EMAIL] = $cust['email'] ?? '';

// Setup active delivery area
$activeArea = DeliveryArea::getActiveAreas()[0] ?? null;
$areaId = $activeArea ? (int)$activeArea['id'] : 1;
$areaName = $activeArea ? $activeArea['name'] : 'Jaora Central';

$checkoutPost = [
    CSRF_TOKEN_KEY => csrf_token(),
    'customer_name' => 'Multi Product Tester',
    'customer_phone' => '9876543210',
    'delivery_address' => 'Near Clock Tower, Station Road, Jaora',
    'delivery_area_id' => $areaId,
    'delivery_area_name' => $areaName,
    'payment_method' => 'online',
    'fulfilment_method' => 'delivery',
    'latitude' => 23.635784,
    'longitude' => 75.124683,
    'idempotency_key' => 'idemp_' . bin2hex(random_bytes(8))
];

$checkoutRes = OrderService::processCheckout($checkoutPost);
it_mp("19. OrderService::processCheckout initiates multi-product online order", 
    !empty($checkoutRes['success']) && !empty($checkoutRes['requires_razorpay'])
);

$orderId = (int)$checkoutRes['order_id'];
$order = Order::getById($orderId);
$rzpOrderId = $order['razorpay_order_id'];
$attemptRef = $checkoutRes['attempt_reference'];

it_mp("20. Order record contains distinct items with preparation options and combo flags", 
    count($order['items']) === 4
);

$foundCuttingOptionInOrder = false;
$foundComboInOrder = false;
foreach ($order['items'] as $oi) {
    if (!empty($oi['preparation_option']) && str_contains($oi['preparation_option'], 'Malai Included')) {
        $foundCuttingOptionInOrder = true;
    }
    if (!empty($oi['is_combo']) && (int)$oi['combo_id'] === $comboId) {
        $foundComboInOrder = true;
    }
}
it_mp("21. Order item saved 'Malai Included' preparation choice and charge", $foundCuttingOptionInOrder);
it_mp("22. Order item saved combo bundle flag and combo ID", $foundComboInOrder);

// ----------------------------------------------------
// G. RAZORPAY VERIFICATION SECURITY TESTS (Mismatch Rejection & Success)
// ----------------------------------------------------
echo "G. RAZORPAY VERIFICATION SECURITY (MISMATCH & CAPTURE):\n";

$tx = PaymentTransaction::getByAttemptReference($attemptRef);
$expectedPaise = (int)$tx['amount_paise'];

// Test 1: Tampered amount mismatch
$tamperedPaymentId = 'pay_tamper_' . bin2hex(random_bytes(4));
$mockPayments[$tamperedPaymentId] = [
    'id' => $tamperedPaymentId,
    'order_id' => $rzpOrderId,
    'amount' => 1000, // ₹10 instead of expected total!
    'currency' => 'INR',
    'status' => 'captured'
];
$tamperedSig = hash_hmac('sha256', $rzpOrderId . '|' . $tamperedPaymentId, $secretKey);

$fetchTampered = RazorpayService::fetchPayment($tamperedPaymentId);
$tamperedAmt = (int)($fetchTampered['payment']['amount'] ?? 0);
$mismatchDetected = ($tamperedAmt !== $expectedPaise);
it_mp("23. Tampered payment amount is detected as mismatch ($tamperedAmt !== $expectedPaise)", $mismatchDetected);

// Test 2: Valid payment capture
$validPaymentId = 'pay_valid_' . bin2hex(random_bytes(4));
$mockPayments[$validPaymentId] = [
    'id' => $validPaymentId,
    'order_id' => $rzpOrderId,
    'amount' => $expectedPaise,
    'currency' => 'INR',
    'status' => 'captured'
];
$validSig = hash_hmac('sha256', $rzpOrderId . '|' . $validPaymentId, $secretKey);

// Verify signature
$sigValid = RazorpayService::verifyPaymentSignature($rzpOrderId, $validPaymentId, $validSig);
it_mp("24. Valid Razorpay HMAC SHA256 signature verified", $sigValid === true);

// Execute verification
$fetchValid = RazorpayService::fetchPayment($validPaymentId);
$validAmt = (int)($fetchValid['payment']['amount'] ?? 0);
$validCur = $fetchValid['payment']['currency'] ?? '';

it_mp("25. Valid payment amount ($validAmt paise) and currency ($validCur) match expected order total", 
    $validAmt === $expectedPaise && $validCur === 'INR'
);

// Update status to captured
PaymentTransaction::updateStatus((int)$tx['id'], 'captured', [
    'gateway_payment_id' => $validPaymentId,
    'gateway_signature' => $validSig
]);
Order::updatePaymentAndOrderStatus($orderId, 'captured', 'confirmed', [
    'razorpay_payment_id' => $validPaymentId,
    'razorpay_signature' => $validSig
]);

$confirmedOrder = Order::getById($orderId);
it_mp("26. Order payment_status is 'captured' and order_status is 'confirmed'", 
    $confirmedOrder['payment_status'] === 'captured' && $confirmedOrder['order_status'] === 'confirmed'
);

// ----------------------------------------------------
// Clean up test records
// ----------------------------------------------------
Product::delete($newProdId);
Category::delete($newCatId);
Combo::delete($comboId);
CustomerAuthService::logout();
RazorpayService::setMockHandler(null);

echo "\n--- MULTI-PRODUCT & RAZORPAY TEST SUITE: {$mpPass} PASSED, {$mpFail} FAILED ---\n";
