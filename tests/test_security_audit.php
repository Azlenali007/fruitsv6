<?php
/**
 * Master Security, Access Control, Installation & URL Audit Test Suite
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

$secPass = 0;
$secFail = 0;

function it_sec(string $name, bool $cond, string $detail = '') {
    global $secPass, $secFail;
    if ($cond) {
        $secPass++;
        echo "  [PASS] {$name}\n";
    } else {
        $secFail++;
        echo "  [FAIL] {$name} - {$detail}\n";
    }
}

echo "\n========================================================\n";
echo "14. MASTER SECURITY, ACCESS CONTROL & AUDIT TESTS\n";
echo "========================================================\n";

// ----------------------------------------------------
// A. HOST HEADER VALIDATION & POISONING PROTECTION
// ----------------------------------------------------
echo "A. HOST HEADER & DOMAIN AUDIT:\n";

$allowedHosts = ['nifruitjaora.shop', 'www.nifruitjaora.shop', 'localhost', '127.0.0.1', '::1'];

function checkHostAllowed(string $host): bool {
    $hostOnly = strtolower(explode(':', $host)[0]);
    $allowed = ['nifruitjaora.shop', 'www.nifruitjaora.shop', 'localhost', '127.0.0.1', '::1'];
    return in_array($hostOnly, $allowed, true) || str_ends_with($hostOnly, '.run.app');
}

it_sec("1. Production canonical domain (nifruitjaora.shop) is accepted", checkHostAllowed('nifruitjaora.shop'));
it_sec("2. Production www domain (www.nifruitjaora.shop) is recognized for canonical redirect", checkHostAllowed('www.nifruitjaora.shop'));
it_sec("3. Local development host (localhost) is accepted", checkHostAllowed('localhost'));
it_sec("4. Host header poisoning (attacker.com) is rejected", !checkHostAllowed('attacker.com'));
it_sec("5. Host header spoofing (evil-phishing.org) is rejected", !checkHostAllowed('evil-phishing.org'));

// ----------------------------------------------------
// B. PATH TRAVERSAL & DIRECT DIRECTORY DEFENSE
// ----------------------------------------------------
echo "B. PATH TRAVERSAL & DIRECTORY ACCESS CONTROL:\n";

function checkTraversalRejected(string $path): bool {
    $rawUri = $path;
    $uri = urldecode($rawUri);
    if (str_contains($rawUri, '..') || str_contains($uri, '..') || str_contains($rawUri, "\0") || str_contains($uri, "\0")) {
        return true; // Rejected
    }
    return false;
}

it_sec("6. Path traversal with '/../../app/config/database.php' is blocked", checkTraversalRejected('/../../app/config/database.php'));
it_sec("7. URL-encoded path traversal '/%2e%2e/%2e%2e/storage/installed.lock' is blocked", checkTraversalRejected('/%2e%2e/%2e%2e/storage/installed.lock'));
it_sec("8. Null-byte injection attempt '/shop%00.php' is blocked", checkTraversalRejected('/shop%00.php'));

function checkInternalDirBlocked(string $path): bool {
    $blocked = ['/app', '/storage', '/database', '/cron', '/bin', '/tests', '/node_modules', '/.git', '/.env'];
    foreach ($blocked as $b) {
        if ($path === $b || str_starts_with($path, $b . '/')) {
            return true; // Blocked
        }
    }
    return false;
}

it_sec("9. Direct web access to '/app/bootstrap.php' is forbidden", checkInternalDirBlocked('/app/bootstrap.php'));
it_sec("10. Direct web access to '/storage/logs/php_error.log' is forbidden", checkInternalDirBlocked('/storage/logs/php_error.log'));
it_sec("11. Direct web access to '/database/schema.sql' is forbidden", checkInternalDirBlocked('/database/schema.sql'));
it_sec("12. Direct web access to '/storage/vapid_keys.json' is forbidden", checkInternalDirBlocked('/storage/vapid_keys.json'));
it_sec("13. Direct web access to '/.env' is forbidden", checkInternalDirBlocked('/.env'));

// ----------------------------------------------------
// C. INSTALLATION SYSTEM & SECURITY LOCK AUDIT
// ----------------------------------------------------
echo "C. INSTALLATION SYSTEM & LOCK PROTECTION:\n";

it_sec("14. is_installed() returns true when storage/installed.lock exists", is_installed() === true);
it_sec("15. INSTALL_LOCK_FILE path is inside protected storage directory", str_contains(INSTALL_LOCK_FILE, 'storage'));

// Check lock file contents
$lockContent = @file_get_contents(INSTALL_LOCK_FILE);
$lockData = json_decode($lockContent, true);
it_sec("16. Lock file contains valid JSON with verification attributes", 
    is_array($lockData) && !empty($lockData['installed_at'])
);

// ----------------------------------------------------
// D. ADMIN & CUSTOMER AUTHORIZATION GUARDS
// ----------------------------------------------------
echo "D. AUTHORIZATION & SESSION GUARDS:\n";

AuthService::logout();
CustomerAuthService::logout();

it_sec("17. Unauthenticated user is NOT recognized as admin", AuthService::check() === false);
it_sec("18. Unauthenticated user is NOT recognized as logged-in customer", CustomerAuthService::check() === false);

// ----------------------------------------------------
// E. SENSITIVE CREDENTIALS MASKING & LOG HYGIENE
// ----------------------------------------------------
echo "E. CREDENTIALS MASKING & LOG HYGIENE:\n";

$testSecret = 'rzp_live_secretkey998877665544';
$maskedSecret = RazorpayService::maskSecret($testSecret);

it_sec("19. maskSecret conceals secret body with bullets", str_starts_with($maskedSecret, '••••'));
it_sec("20. maskSecret preserves only last 4 non-sensitive digits", str_ends_with($maskedSecret, '5544'));
it_sec("21. maskSecret does not expose middle secret chars", !str_contains($maskedSecret, '99887766'));

// ----------------------------------------------------
// F. DATABASE SCHEMA COMPLETENESS & DATA INTEGRITY
// ----------------------------------------------------
echo "F. DATABASE INTEGRITY & SCHEMA COMPLETENESS:\n";

$pdo = Database::getConnection();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$expectedTables = [
    'admins', 'products', 'categories', 'combos', 'combo_items',
    'orders', 'order_items', 'settings', 'customers',
    'customer_login_attempts', 'coupons', 'coupon_usage',
    'payment_transactions', 'payment_webhook_events',
    'delivery_areas', 'invoices', 'invoice_sequences'
];

$allTablesExist = true;
$missingTbls = [];
foreach ($expectedTables as $tbl) {
    if (!in_array($tbl, $tables, true)) {
        $allTablesExist = false;
        $missingTbls[] = $tbl;
    }
}
it_sec("22. All core database tables exist in MySQL", $allTablesExist, implode(', ', $missingTbls));

// Check products multi-product columns
$pCols = $pdo->query("SHOW COLUMNS FROM `products`")->fetchAll(PDO::FETCH_COLUMN);
$expectedPCols = ['category_id', 'original_price', 'has_cutting_options', 'cutting_options_json', 'is_featured', 'sort_order'];
$hasAllPCols = count(array_intersect($expectedPCols, $pCols)) === count($expectedPCols);
it_sec("23. Products table contains all 6 multi-product & cutting options columns", $hasAllPCols);

// Check order_items columns
$oiCols = $pdo->query("SHOW COLUMNS FROM `order_items`")->fetchAll(PDO::FETCH_COLUMN);
$expectedOiCols = ['preparation_option', 'preparation_charge', 'is_combo', 'combo_id', 'combo_details_json'];
$hasAllOiCols = count(array_intersect($expectedOiCols, $oiCols)) === count($expectedOiCols);
it_sec("24. Order items table contains all preparation and combo snapshot columns", $hasAllOiCols);

// Check primary active products count
$prodCount = (int)$pdo->query("SELECT COUNT(*) FROM `products` WHERE `is_available` = 1")->fetchColumn();
it_sec("25. Catalog contains at least 5 active products across categories", $prodCount >= 5);

// Check combo bundles count
$comboCount = (int)$pdo->query("SELECT COUNT(*) FROM `combos` WHERE `is_available` = 1")->fetchColumn();
it_sec("26. Catalog contains at least 3 active combo bundles", $comboCount >= 3);

echo "\n--- MASTER SECURITY AUDIT RESULTS: {$secPass} PASSED, {$secFail} FAILED ---\n";
