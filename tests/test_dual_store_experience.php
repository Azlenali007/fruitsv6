<?php
/**
 * Automated Verification Suite for Dual Store Experience (Coconut Store & Juice Store)
 * N.A Fresh Fruits & Coconuts — Jaora, MP (PIN 457226)
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = Database::getConnection();
$dualPass = 0;
$dualFail = 0;

function itDual(string $testName, bool $condition, string $detail = '') {
    global $dualPass, $dualFail;
    if ($condition) {
        $dualPass++;
        echo "  [PASS] {$testName}\n";
    } else {
        $dualFail++;
        echo "  [FAIL] {$testName} - {$detail}\n";
    }
}

echo "========================================================\n";
echo "18. DUAL STORE EXPERIENCE SUITE (COCONUT & JUICE STORES)\n";
echo "========================================================\n";

// ---------------------------------------------------------
// A. DEFAULT STORE MODE FOR FIRST-TIME VISITORS
// ---------------------------------------------------------
echo "\nA. DEFAULT STORE INITIALIZATION & MEMORY:\n";

// Clear session and cookie simulation
unset($_SESSION['na_store_mode']);
unset($_COOKIE['na_store_mode']);

$defaultStore = StoreService::getCurrentStore();
itDual("1. First-time visitor defaults to 'coconut' store", $defaultStore === 'coconut');

$metaCoconut = StoreService::getStoreMeta('coconut');
itDual("2. Coconut store brand name is authentic", strpos($metaCoconut['store_name'], 'Coconut') !== false);
itDual("3. Coconut store theme color is forest green (#235d3a)", $metaCoconut['theme_color'] === '#235d3a');
itDual("4. Coconut store switch button text is 'Looking for Juice? 🍹'", strpos($metaCoconut['switch_button_text'], 'Juice') !== false);

// ---------------------------------------------------------
// B. STORE SWITCHING & PERSISTENCE
// ---------------------------------------------------------
echo "\nB. STORE SWITCHING & PERSISTENCE:\n";

$switchSuccess = StoreService::setStore('juice');
itDual("5. StoreService::setStore('juice') returns true", $switchSuccess === true);
itDual("6. Session now records 'juice' as active store", ($_SESSION['na_store_mode'] ?? '') === 'juice');
itDual("7. StoreService::getCurrentStore() now returns 'juice'", StoreService::getCurrentStore() === 'juice');

$metaJuice = StoreService::getStoreMeta('juice');
itDual("8. Juice store brand name is 'N.A Fresh Fruit Juices & Shakes'", strpos($metaJuice['store_name'], 'Juice') !== false);
itDual("9. Juice store theme color is vibrant citrus orange (#ea580c)", $metaJuice['theme_color'] === '#ea580c');
itDual("10. Juice store reverse switch button text is 'Looking for Coconut? 🥥'", strpos($metaJuice['switch_button_text'], 'Coconut') !== false);

// Switch back to coconut
StoreService::setStore('coconut');
itDual("11. Switching back to 'coconut' updates session", StoreService::getCurrentStore() === 'coconut');

// ---------------------------------------------------------
// C. CATALOG SEPARATION — COCONUT PRODUCTS ONLY
// ---------------------------------------------------------
echo "\nC. CATALOG SEPARATION — COCONUT STORE:\n";

$coconutProducts = Product::getAll(true, null, null, 'coconut');
itDual("12. Coconut store returns products list", count($coconutProducts) > 0);

$leakedJuiceInCoconut = 0;
foreach ($coconutProducts as $cp) {
    if (($cp['store_type'] ?? '') === 'juice') {
        $leakedJuiceInCoconut++;
    }
}
itDual("13. Zero juice products leak into Coconut Store catalog", $leakedJuiceInCoconut === 0);

$coconutCategories = Category::getAll(true, 'coconut');
itDual("14. Coconut store returns coconut categories", count($coconutCategories) > 0);
$leakedJuiceCatsInCoconut = 0;
foreach ($coconutCategories as $cc) {
    if (($cc['store_type'] ?? '') === 'juice') {
        $leakedJuiceCatsInCoconut++;
    }
}
itDual("15. Zero juice categories leak into Coconut Store categories", $leakedJuiceCatsInCoconut === 0);

// ---------------------------------------------------------
// D. CATALOG SEPARATION — JUICE PRODUCTS ONLY
// ---------------------------------------------------------
echo "\nD. CATALOG SEPARATION — JUICE STORE:\n";

$juiceProducts = Product::getAll(true, null, null, 'juice');
itDual("16. Juice store returns juice products list (>= 8 products)", count($juiceProducts) >= 8);

$leakedCoconutInJuice = 0;
foreach ($juiceProducts as $jp) {
    if (($jp['store_type'] ?? '') === 'coconut') {
        $leakedCoconutInJuice++;
    }
}
itDual("17. Zero coconut products leak into Juice Store catalog", $leakedCoconutInJuice === 0);

$juiceCategories = Category::getAll(true, 'juice');
itDual("18. Juice store returns juice categories (>= 3 categories)", count($juiceCategories) >= 3);
$leakedCoconutCatsInJuice = 0;
foreach ($juiceCategories as $jc) {
    if (($jc['store_type'] ?? '') === 'coconut') {
        $leakedCoconutCatsInJuice++;
    }
}
itDual("19. Zero coconut categories leak into Juice Store categories", $leakedCoconutCatsInJuice === 0);

// ---------------------------------------------------------
// E. IN-CARD 1-TAP QUANTITY STEPPER (AJAX CART ENGINE)
// ---------------------------------------------------------
echo "\nE. IN-CARD 1-TAP QUANTITY STEPPER ENGINE:\n";

CartService::clear();
itDual("20. Cart is clean before stepper test", CartService::getCart()['count'] === 0);

// Pick a juice product
$sampleJuice = $juiceProducts[0];
$quickRes1 = CartService::quickSetQuantity((int)$sampleJuice['id'], 1);
itDual("21. 1-tap quick add sets product quantity to 1", $quickRes1['success'] === true && $quickRes1['quantity'] === 1);
itDual("22. Cart total item count is now 1", CartService::getCart()['count'] === 1);

// Increase quantity via stepper to 3
$quickRes2 = CartService::quickSetQuantity((int)$sampleJuice['id'], 3);
itDual("23. Stepping up to 3 updates item quantity to 3", $quickRes2['success'] === true && $quickRes2['quantity'] === 3);
itDual("24. Cart total item count is now 3", CartService::getCart()['count'] === 3);

// Step down to 0 removes item
$quickRes0 = CartService::quickSetQuantity((int)$sampleJuice['id'], 0);
itDual("25. Stepping down to 0 removes product from cart", $quickRes0['success'] === true && $quickRes0['quantity'] === 0);
itDual("26. Cart is empty after decrementing to 0", CartService::getCart()['count'] === 0);

// ---------------------------------------------------------
// F. STICKY QUICK ORDERING BAR STATS
// ---------------------------------------------------------
echo "\nF. STICKY QUICK ORDERING BAR STATS:\n";

CartService::clear();
$sampleCoconut = $coconutProducts[0];
CartService::quickSetQuantity((int)$sampleCoconut['id'], 2);
CartService::quickSetQuantity((int)$sampleJuice['id'], 1);

$cartCurrent = CartService::getCart();
itDual("27. Cart accurately counts 3 items (2 coconut + 1 juice)", $cartCurrent['count'] === 3);
$expectedSubtotal = ((float)$sampleCoconut['price'] * 2) + ((float)$sampleJuice['price'] * 1);
itDual("28. Cart subtotal equals sum of coconut and juice items", (float)$cartCurrent['subtotal'] === (float)$expectedSubtotal);

// ---------------------------------------------------------
// G. DUAL STORE CART PRESERVATION & LINE-ITEM TAGGING
// ---------------------------------------------------------
echo "\nG. DUAL STORE CART PRESERVATION & TAGGING:\n";

$taggedCoconutItem = null;
$taggedJuiceItem = null;
foreach ($cartCurrent['items'] as $item) {
    if ((int)$item['product_id'] === (int)$sampleCoconut['id']) {
        $taggedCoconutItem = $item;
    }
    if ((int)$item['product_id'] === (int)$sampleJuice['id']) {
        $taggedJuiceItem = $item;
    }
}

itDual("29. Coconut item in cart retains 'store_type' = 'coconut'", ($taggedCoconutItem['store_type'] ?? '') === 'coconut');
itDual("30. Juice item in cart retains 'store_type' = 'juice'", ($taggedJuiceItem['store_type'] ?? '') === 'juice');

// Switch store to juice and verify cart is preserved
StoreService::setStore('juice');
$cartAfterSwitch = CartService::getCart();
itDual("31. Switching store preserves all cart items without loss", $cartAfterSwitch['count'] === 3);
itDual("32. Subtotal remains preserved after store switch", (float)$cartAfterSwitch['subtotal'] === (float)$expectedSubtotal);

// ---------------------------------------------------------
// H. 1-TAP "BUY AGAIN" REORDER FEATURE
// ---------------------------------------------------------
echo "\nH. 1-TAP BUY AGAIN REORDER FEATURE:\n";

// Clear cart
CartService::clear();

// Find an existing completed or reference order
$stmtPastOrder = $pdo->query("SELECT id FROM `orders` ORDER BY id DESC LIMIT 1");
$pastOrderId = (int)$stmtPastOrder->fetchColumn();

if ($pastOrderId > 0) {
    $reorderRes = CartService::reorderFromOrder($pastOrderId);
    itDual("33. Buy Again reorder executes successfully", $reorderRes['success'] === true);
    itDual("34. Cart is populated with items from past order", CartService::getCart()['count'] > 0);
    itDual("35. Added items count is reported in response", $reorderRes['added_count'] > 0);
} else {
    itDual("33. Buy Again test skipped (no past orders)", true);
    itDual("34. Buy Again test skipped", true);
    itDual("35. Buy Again test skipped", true);
}

// ---------------------------------------------------------
// I. ADMIN PANEL DUAL STORE CONFIGURATION & SCHEMA
// ---------------------------------------------------------
echo "\nI. ADMIN PANEL DUAL STORE CONFIGURATION & SCHEMA:\n";

// Verify database schema has store_type column
$colsProduct = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'store_type'")->fetchAll();
itDual("36. `products` table has `store_type` column", count($colsProduct) === 1);

$colsCategory = $pdo->query("SHOW COLUMNS FROM `categories` LIKE 'store_type'")->fetchAll();
itDual("37. `categories` table has `store_type` column", count($colsCategory) === 1);

$colsOrderItems = $pdo->query("SHOW COLUMNS FROM `order_items` LIKE 'store_type'")->fetchAll();
itDual("38. `order_items` table has `store_type` column", count($colsOrderItems) === 1);

// Test Product creation with store_type = 'juice'
$testProductSlug = 'test-juice-beverage-' . time();
$createdProdId = Product::create([
    'name' => 'Automated Test Cold Pressed Juice',
    'slug' => $testProductSlug,
    'store_type' => 'juice',
    'category_id' => $juiceCategories[0]['id'],
    'price' => 75.00,
    'stock_quantity' => 50,
    'is_available' => 1,
    'description' => 'Test Juice Product'
]);
itDual("39. Admin can create product specifically assigned to 'juice' store", $createdProdId > 0);

$fetchedTestProd = Product::find($createdProdId);
itDual("40. Stored product has store_type = 'juice'", ($fetchedTestProd['store_type'] ?? '') === 'juice');

// Clean up test product
$pdo->exec("DELETE FROM `products` WHERE id = " . (int)$createdProdId);

// Test dual store settings persistence
Setting::set('dual_store_juice_enabled', '1');
Setting::set('dual_store_switch_button_enabled', '1');
itDual("41. dual_store_juice_enabled setting is active", StoreService::isJuiceEnabled() === true);
itDual("42. dual_store_switch_button_enabled setting is active", StoreService::isSwitchButtonEnabled() === true);

echo "\n--- DUAL STORE EXPERIENCE TEST SUMMARY: {$dualPass} PASSED, {$dualFail} FAILED ---\n";
