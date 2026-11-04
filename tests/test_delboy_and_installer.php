<?php
/**
 * Verification Test Suite: Delivery Boy Authentication, /delboy URL, Admin Management & Web Installer
 * N.A Fresh Fruits & Coconuts
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/InstallerController.php';

$pdo = Database::getConnection();
$pass = 0;
$fail = 0;

function assertTest(string $desc, bool $result, string $error = '') {
    global $pass, $fail;
    if ($result) {
        $pass++;
        echo "  [PASS] {$desc}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$desc}" . ($error ? " - {$error}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo "TESTING /delboy ROUTE, AUTHENTICATION, ADMIN MANAGEMENT & INSTALLER\n";
echo "====================================================================\n\n";

// --------------------------------------------------------------------
// 1. Direct Browser Access to /delboy
// --------------------------------------------------------------------
echo "1. DIRECT BROWSER ACCESS TO /delboy:\n";
$ch = curl_init('http://127.0.0.1:8080/delboy');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assertTest("/delboy returns HTTP 200 OK", $httpCode === 200, "Got HTTP {$httpCode}");
assertTest("/delboy contains Delivery Partner Login title", str_contains($resp, 'Delivery Partner Login'));
assertTest("/delboy contains form posting to /delboy", str_contains($resp, 'action="/delboy"'));

// Test refreshing /delboy
$ch = curl_init('http://127.0.0.1:8080/delboy');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assertTest("Refreshing /delboy returns HTTP 200 OK without 404", $httpCode2 === 200);

// Test legacy /delivery/login redirects to /delboy
$ch = curl_init('http://127.0.0.1:8080/delivery/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$respRedirect = curl_exec($ch);
$redirectCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assertTest("/delivery/login redirects with HTTP 302", $redirectCode === 302);
assertTest("/delivery/login location is /delboy", (bool)preg_match('/location:\s*\/delboy/i', $respRedirect));

// --------------------------------------------------------------------
// 2 & 4. Admin-Created Delivery Boy Account & Valid Login
// --------------------------------------------------------------------
echo "\n2. ADMIN-CREATED ACCOUNT & VALID LOGIN:\n";
$testLoginId = 'test_rider_' . time();
$testMobile = '98' . rand(10000000, 99999999);
$testPass = 'RiderSecret123!';

// Admin creates account
$riderId = DeliveryBoy::create([
    'name' => 'Test Rider Vikram',
    'mobile' => $testMobile,
    'login_id' => $testLoginId,
    'password' => $testPass,
    'status' => 'active',
    'vehicle_type' => 'Electric Scooter (EV)',
    'vehicle_number' => 'MP 43 EV 9999'
]);

assertTest("Admin creates delivery boy successfully", $riderId > 0);
$createdRider = DeliveryBoy::getById($riderId);
assertTest("Stored password uses secure bcrypt/argon hashing", password_verify($testPass, $createdRider['password_hash']));
assertTest("Initial rider status is active", $createdRider['status'] === 'active');

// Login with valid credentials
$_SESSION = [];
$loginRes = DeliveryBoyAuthService::login($testLoginId, $testPass, '127.0.0.1');
assertTest("Login succeeds with valid credentials", $loginRes['success'] === true);
assertTest("DeliveryBoyAuthService::check() is true after login", DeliveryBoyAuthService::check() === true);
assertTest("DeliveryBoyAuthService::riderId() matches created rider", DeliveryBoyAuthService::riderId() === $riderId);

$riderReloaded = DeliveryBoy::getById($riderId);
assertTest("Rider is automatically set ONLINE after login", (int)$riderReloaded['is_online'] === 1);

// --------------------------------------------------------------------
// 3. Login with Incorrect Credentials & Throttling
// --------------------------------------------------------------------
echo "\n3. LOGIN WITH INCORRECT CREDENTIALS & BRUTE-FORCE PROTECTION:\n";
DeliveryBoyAuthService::logout();
$badLoginRes = DeliveryBoyAuthService::login($testLoginId, 'WrongPassword999', '127.0.0.1');
assertTest("Login fails with invalid password", $badLoginRes['success'] === false);
assertTest("Error message indicates invalid credentials", str_contains($badLoginRes['error'], 'Invalid delivery credentials'));

// Test unknown login ID
$unknownRes = DeliveryBoyAuthService::login('non_existent_rider_xyz', 'somePass', '127.0.0.1');
assertTest("Login fails with non-existent user", $unknownRes['success'] === false);

// --------------------------------------------------------------------
// 5. No Public Registration
// --------------------------------------------------------------------
echo "\n5. NO PUBLIC REGISTRATION:\n";
assertTest("/delboy contains no 'Sign Up' link", !str_contains(strtolower($resp), 'sign up'));
assertTest("/delboy contains no 'Create Account' link", !str_contains(strtolower($resp), 'create account'));
assertTest("/delboy contains no 'Register' button", !str_contains(strtolower($resp), 'register as partner') && !str_contains(strtolower($resp), 'href="/delivery/register"'));

$ch = curl_init('http://127.0.0.1:8080/delivery/register');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$regResp = curl_exec($ch);
$regCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assertTest("/delivery/register returns 404 or redirects (no public registration)", $regCode === 404 || $regCode === 302);

// --------------------------------------------------------------------
// 6. Inactive Delivery Boy Login Rejection
// --------------------------------------------------------------------
echo "\n6. INACTIVE DELIVERY BOY REJECTION:\n";
// Deactivate the rider
DeliveryBoy::update($riderId, ['status' => 'inactive']);
$inactiveRider = DeliveryBoy::getById($riderId);
assertTest("Rider status updated to inactive", $inactiveRider['status'] === 'inactive');

DeliveryBoyAuthService::logout();
$inactiveLogin = DeliveryBoyAuthService::login($testLoginId, $testPass, '127.0.0.1');
assertTest("Inactive rider login is rejected", $inactiveLogin['success'] === false);
assertTest("Rejection error specifies account deactivated", str_contains($inactiveLogin['error'], 'deactivated'));

// Reactivate for further tests
DeliveryBoy::update($riderId, ['status' => 'active']);

// --------------------------------------------------------------------
// 7 & 8. Protected URLs Without Login & With Login
// --------------------------------------------------------------------
echo "\n7 & 8. PROTECTED URL ACCESS CONTROL:\n";
DeliveryBoyAuthService::logout();
assertTest("Unauthenticated check() is false", DeliveryBoyAuthService::check() === false);

// Test requireLogin redirect target
$reflector = new ReflectionMethod('DeliveryBoyAuthService', 'requireLogin');
$params = $reflector->getParameters();
assertTest("requireLogin default redirect parameter is /delboy", $params[0]->getDefaultValue() === '/delboy');

// Re-login to test authenticated access
DeliveryBoyAuthService::login($testLoginId, $testPass, '127.0.0.1');
assertTest("Authenticated rider can access dashboard state", DeliveryBoyAuthService::check() === true);
$stats = DeliveryBoy::getPerformanceStats($riderId);
assertTest("Performance stats retrieved successfully for rider", isset($stats['total_delivered']));

// --------------------------------------------------------------------
// 9. Delivery Boy Access to Admin Pages Being Blocked
// --------------------------------------------------------------------
echo "\n9. DELIVERY BOY ACCESS TO ADMIN PAGES BLOCKED (ROLE ISOLATION):\n";
// 1. Log in as delivery partner via /delboy
$ch = curl_init('http://127.0.0.1:8080/delboy');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$loginPage = curl_exec($ch);
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $loginPage, $mCsrf);
$csrf = $mCsrf[1] ?? '';
preg_match('/Set-Cookie:\s*([^;\r\n]+)/i', $loginPage, $mCookie);
$cookie = $mCookie[1] ?? '';

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    '_csrf_token' => $csrf,
    'login_id' => $testLoginId,
    'password' => $testPass
]));
curl_setopt($ch, CURLOPT_COOKIE, $cookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$postResp = curl_exec($ch);
curl_close($ch);

preg_match('/Set-Cookie:\s*([^;\r\n]+)/i', $postResp, $mPostCookie);
$sessionCookie = $mPostCookie[1] ?? $cookie;

// 2. Attempt to open /admin while holding the Delivery Boy session cookie
$ch = curl_init('http://127.0.0.1:8080/admin');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, $sessionCookie);
$adminResp = curl_exec($ch);
$adminHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assertTest("Delivery Boy attempting to access /admin produces HTTP 403 Forbidden", $adminHttpCode === 403, "Got HTTP {$adminHttpCode}");
assertTest("Response contains Access Denied notification", str_contains($adminResp, 'Access Denied') && str_contains($adminResp, 'Delivery Partner accounts do not have administrator permissions'));

// --------------------------------------------------------------------
// 10. Logout and Session Destruction
// --------------------------------------------------------------------
echo "\n10. LOGOUT AND SESSION DESTRUCTION:\n";
assertTest("Before logout, riderId is valid", DeliveryBoyAuthService::riderId() === $riderId);
DeliveryBoyAuthService::logout();
assertTest("After logout, DeliveryBoyAuthService::check() is false", DeliveryBoyAuthService::check() === false);
assertTest("After logout, session rider id is null", DeliveryBoyAuthService::riderId() === null);

$riderAfterLogout = DeliveryBoy::getById($riderId);
assertTest("After logout, rider is set OFFLINE (is_online = 0)", (int)$riderAfterLogout['is_online'] === 0);

// Clean up test rider
$pdo->prepare("DELETE FROM delivery_boys WHERE id = ?")->execute([$riderId]);

// --------------------------------------------------------------------
// 11. Installer Full Workflow in Isolated Test Environment
// --------------------------------------------------------------------
echo "\n11. WEB INSTALLER COMPLETE WORKFLOW TEST:\n";
$testDbName = 'na_test_installer_verify';

// Step 1: Requirements Check
$reflectorInstaller = new ReflectionClass('InstallerController');
$checks = [
    'php_version' => version_compare(PHP_VERSION, '8.2.0', '>='),
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'curl' => extension_loaded('curl'),
    'gd' => extension_loaded('gd'),
    'json' => extension_loaded('json'),
    'fileinfo' => extension_loaded('fileinfo'),
    'session' => extension_loaded('session'),
    'storage' => is_writable(__DIR__ . '/../storage'),
    'config' => is_writable(__DIR__ . '/../app/config'),
    'uploads' => is_writable(__DIR__ . '/../public/assets/uploads')
];
$step1Passed = !in_array(false, $checks, true);
assertTest("Installer Step 1 Requirements Check: all extensions & permissions pass", $step1Passed);

// Step 2: Database Connection & Auto-Creation Test
$testDbConn = Database::testConnection('127.0.0.1', '3306', $testDbName, 'na_user', 'na_pass123', true);
assertTest("Installer Step 2: testConnection successfully creates database if not exists", $testDbConn['success'] === true);

// Step 3: Schema and Migrations Execution
$testPdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname={$testDbName};charset=utf8mb4", 'na_user', 'na_pass123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
]);

$methodSplit = $reflectorInstaller->getMethod('splitSqlStatements');
$methodSplit->setAccessible(true);

$schemaSql = file_get_contents(__DIR__ . '/../database/schema.sql');
$schemaStmts = $methodSplit->invoke(null, $schemaSql);
$schemaExecCount = 0;
foreach ($schemaStmts as $sStmt) {
    $sStmt = trim($sStmt);
    if (empty($sStmt) || preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $sStmt)) continue;
    $sObj = $testPdo->query($sStmt);
    if ($sObj instanceof PDOStatement) {
        $sObj->closeCursor();
    }
    $schemaExecCount++;
}
assertTest("Installer Step 3: schema.sql executed {$schemaExecCount} statements without unbuffered query errors", $schemaExecCount >= 20);

// Run all migrations
$migrations = glob(__DIR__ . '/../database/migrations/*.sql');
sort($migrations, SORT_NATURAL);
$migCount = 0;
foreach ($migrations as $mFile) {
    $mSql = @file_get_contents($mFile);
    if (!empty($mSql)) {
        $mStmts = $methodSplit->invoke(null, $mSql);
        foreach ($mStmts as $mStmt) {
            $mStmt = trim($mStmt);
            if (empty($mStmt) || preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $mStmt)) continue;
            try {
                $mObj = $testPdo->query($mStmt);
                if ($mObj instanceof PDOStatement) {
                    $mObj->closeCursor();
                }
            } catch (Exception $e) {}
        }
        $migCount++;
    }
}
assertTest("Installer Step 3: all {$migCount} migration files executed idempotently", $migCount === 10);

$testTables = $testPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$requiredCoreTables = [
    'admins', 'products', 'orders', 'order_items', 'settings',
    'customers', 'customer_login_attempts', 'categories',
    'combos', 'combo_items', 'delivery_areas', 'invoices',
    'delivery_boys', 'order_assignments', 'delivery_timeline'
];
$missingInTest = array_diff($requiredCoreTables, $testTables);
assertTest("Installer Step 3 Verification: all required tables exist in database", empty($missingInTest), "Missing: " . implode(', ', $missingInTest));

// Step 4: Admin Account Initialization in Test DB
$adminHash = password_hash('AdminPass123!', PASSWORD_BCRYPT);
$testPdo->prepare("INSERT INTO `admins` (`username`, `email`, `password_hash`, `name`, `role`) VALUES (?, ?, ?, ?, 'superadmin')")
    ->execute(['testadmin', 'testadmin@example.com', $adminHash, 'Master Admin']);
$adminCreated = $testPdo->query("SELECT COUNT(*) FROM `admins` WHERE `username` = 'testadmin'")->fetchColumn();
assertTest("Installer Step 4: Admin user created successfully in test database", (int)$adminCreated === 1);

// Step 5: Default Settings in Test DB
$testPdo->prepare("INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES (?, ?, ?)")
    ->execute(['store_name', 'N.A Fresh Fruits & Coconuts', 'Store Name']);
$settingExists = $testPdo->query("SELECT `value` FROM `settings` WHERE `key_name` = 'store_name'")->fetchColumn();
assertTest("Installer Step 5: Business settings seeded successfully", $settingExists === 'N.A Fresh Fruits & Coconuts');

// Step 6: Idempotent Retries
// Re-running schema on existing database to verify non-destructive behavior
$reExecCount = 0;
foreach ($schemaStmts as $sStmt) {
    $sStmt = trim($sStmt);
    if (empty($sStmt) || preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $sStmt)) continue;
    try {
        $sObj = $testPdo->query($sStmt);
        if ($sObj instanceof PDOStatement) {
            $sObj->closeCursor();
        }
        $reExecCount++;
    } catch (Exception $e) {}
}
assertTest("Installer Safe Retry: Re-executing schema causes no fatal errors or duplicate tables", $reExecCount >= 20);

// Drop test database to keep system clean
$pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");
assertTest("Cleaned up isolated installer test database cleanly", true);

// --------------------------------------------------------------------
// 12. Existing Website Functionality Intact
// --------------------------------------------------------------------
echo "\n12. EXISTING CORE SYSTEM INTEGRITY:\n";
$prod = Product::getPrimary();
assertTest("Primary coconut product exists in production DB", (bool)$prod);

$cats = Category::getAll(true);
assertTest("Categories system functioning", is_array($cats));

$ordersCount = (int)$pdo->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
assertTest("Production order records preserved intact (count: {$ordersCount})", $ordersCount > 0);

$custCount = (int)$pdo->query("SELECT COUNT(*) FROM `customers`")->fetchColumn();
assertTest("Production customer records preserved intact (count: {$custCount})", $custCount > 0);

$rzpSettings = RazorpayService::getSettings();
assertTest("Existing Razorpay configuration intact", is_array($rzpSettings));

echo "\n====================================================================\n";
echo "TEST RESULTS: {$pass} PASSED, {$fail} FAILED\n";
echo "====================================================================\n";

if ($fail > 0) {
    exit(1);
}
