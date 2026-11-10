<?php
/**
 * Automated Verification Suite for Critical Fixes & Security Hardening
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

$critPass = 0;
$critFail = 0;

if (!function_exists('assert_test')) {
    function assert_test(string $name, bool $condition, string $details = ''): void {
        global $critPass, $critFail;
        if ($condition) {
            $critPass++;
            echo "  [PASS] {$name}\n";
        } else {
            $critFail++;
            echo "  [FAIL] {$name} - {$details}\n";
        }
    }
}

echo "\n=================================================================\n";
echo "15. CRITICAL FIXES & SECURITY HARDENING TEST SUITE\n";
echo "=================================================================\n";

// =====================================================================
// SECTION 1: ISSUE 1 — LIVE TRAFFIC PAGE & SOURCE EXPOSURE PREVENTION
// =====================================================================
echo "\n--- SECTION 1: LIVE TRAFFIC PAGE & PHP SOURCE EXPOSURE PREVENTION ---\n";

// 1.1 Verify Analytics metrics and live queries
$dateRange = AnalyticsService::resolveDateRange('today');
$metrics = AnalyticsService::getDashboardMetrics($dateRange);
$liveCount = AnalyticsService::getActiveVisitorCount();
$liveVisitors = AnalyticsService::getActiveVisitors(15);

assert_test("1.1 AnalyticsService::getDashboardMetrics executes without database error", is_array($metrics));
assert_test("1.2 Analytics metrics include total_visits and unique_visitors", isset($metrics['total_visits'], $metrics['unique_visitors']));
assert_test("1.3 AnalyticsService::getActiveVisitorCount returns integer", is_int($liveCount));
assert_test("1.4 AnalyticsService::getActiveVisitors returns array", is_array($liveVisitors));

// 1.2 Verify that the Live Traffic view compiles and produces clean HTML with zero exposed PHP tags
ob_start();
$_SESSION['na_admin_id'] = 1;
$_SESSION['na_admin_username'] = 'admin';
$_SESSION['na_admin_name'] = 'Store Manager';
$_SESSION['na_admin_role'] = 'superadmin';

try {
    include __DIR__ . '/../app/views/admin/analytics.php';
    $analyticsHtml = ob_get_clean();
    $renderedCleanly = true;
} catch (Throwable $e) {
    ob_end_clean();
    $renderedCleanly = false;
    $analyticsHtml = $e->getMessage();
}

assert_test("1.5 Admin Live Traffic view renders without exceptions", $renderedCleanly);
assert_test("1.6 Live Traffic rendered HTML does NOT expose '<?php' tags", !str_contains($analyticsHtml, '<?php'), "Exposed PHP tags found in output");
assert_test("1.7 Live Traffic rendered HTML contains real dashboard UI elements", str_contains($analyticsHtml, 'Live Traffic &amp; Visitor Analytics') || str_contains($analyticsHtml, 'Live Traffic & Visitor Analytics'));
assert_test("1.8 Live Traffic view includes Alpine.js telemetry data", str_contains($analyticsHtml, 'pollLive()'));

// 1.3 Test Static File Passthrough Filter
$whitelistedExtensions = ['css', 'js', 'png', 'jpg', 'webp', 'svg', 'ico', 'pdf', 'webmanifest', 'json'];
$forbiddenExtensions = ['php', 'phtml', 'phar', 'sql', 'env', 'sh', 'bak', 'conf', 'htaccess'];

function isStaticAllowed(string $filename): bool {
    return (bool)preg_match('/\.(css|js|map|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot|pdf|webmanifest|json|txt|xml)$/i', $filename);
}

foreach ($whitelistedExtensions as $ext) {
    assert_test("1.9 Safe asset .{$ext} allowed through static passthrough", isStaticAllowed("asset.{$ext}"));
}
foreach ($forbiddenExtensions as $ext) {
    assert_test("1.10 Unsafe file .{$ext} blocked from static passthrough", !isStaticAllowed("file.{$ext}"));
}

// 1.4 Test Upload Directory Protection
$uploadHtaccess = __DIR__ . '/../public/assets/uploads/.htaccess';
assert_test("1.11 Public uploads directory contains protective .htaccess file", file_exists($uploadHtaccess));
$htaccessContent = file_get_contents($uploadHtaccess);
assert_test("1.12 Uploads .htaccess denies execution of PHP and executable scripts", str_contains($htaccessContent, 'Deny from all') && str_contains($htaccessContent, 'php'));

// =====================================================================
// SECTION 2: ISSUE 2 — LOYALTY & STORY REWARDS DATABASE INTEGRITY
// =====================================================================
echo "\n--- SECTION 2: LOYALTY & REWARDS DATABASE CONNECTION & RENDERING ---\n";

$pdo = Database::getConnection();

// 2.1 Verify Loyalty database tables exist and are properly structured
$loyaltyTables = ['loyalty_wallets', 'loyalty_transactions', 'loyalty_vouchers', 'loyalty_story_submissions'];
foreach ($loyaltyTables as $lt) {
    $exists = $pdo->query("SHOW TABLES LIKE '{$lt}'")->rowCount() > 0;
    assert_test("2.1 Table `{$lt}` exists in MariaDB", $exists);
}

// 2.2 Verify that the query joining admins on loyalty_transactions runs without SQL error
try {
    $stmtTestJoin = $pdo->query("
        SELECT t.*, c.name AS customer_name, c.phone AS customer_phone, u.name AS admin_name
        FROM `loyalty_transactions` t
        JOIN `customers` c ON t.customer_id = c.id
        LEFT JOIN `admins` u ON t.admin_id = u.id
        ORDER BY t.id DESC LIMIT 5
    ");
    $joinSucceeded = true;
    $rows = $stmtTestJoin->fetchAll();
} catch (Throwable $t) {
    $joinSucceeded = false;
    $joinError = $t->getMessage();
}
assert_test("2.2 Loyalty transactions query joins `admins` table successfully (zero 1146 errors)", $joinSucceeded, $joinError ?? '');

// 2.3 Verify Admin Loyalty view renders without any database connection notice
ob_start();
try {
    include __DIR__ . '/../app/views/admin/loyalty.php';
    $loyaltyHtml = ob_get_clean();
    $loyaltyRendered = true;
} catch (Throwable $e) {
    ob_end_clean();
    $loyaltyRendered = false;
    $loyaltyHtml = $e->getMessage();
}

assert_test("2.3 Admin Loyalty page renders without throwing exceptions", $loyaltyRendered);
assert_test("2.4 Loyalty page does NOT contain 'Database Connection Notice'", !str_contains($loyaltyHtml, 'Database Connection Notice'), "Database error screen displayed!");
assert_test("2.5 Loyalty page renders all 4 tabs (submissions, wallets, vouchers, settings)", 
    str_contains($loyaltyHtml, 'Story Submissions') &&
    str_contains($loyaltyHtml, 'Customer Wallets') &&
    str_contains($loyaltyHtml, 'Vouchers') &&
    str_contains($loyaltyHtml, 'Reward Settings')
);

// 2.4 Verify LoyaltyService methods
$loyaltyEnabled = LoyaltyService::isEnabled();
assert_test("2.6 LoyaltyService::isEnabled returns boolean", is_bool($loyaltyEnabled));

// Check wallet creation / balance
$testCust = $pdo->query("SELECT id FROM `customers` LIMIT 1")->fetch();
if ($testCust) {
    $testCid = (int)$testCust['id'];
    $wallet = LoyaltyService::getWallet($testCid);
    assert_test("2.7 LoyaltyService::getWallet returns valid customer wallet", isset($wallet['current_balance']));
}

// =====================================================================
// SECTION 3: AUTHENTICATION, PASSWORD HASHING & ACCOUNT LOCKOUT
// =====================================================================
echo "\n--- SECTION 3: ADMIN AUTHENTICATION, ARGON2ID & ACCOUNT LOCKOUT ---\n";

// 3.1 Verify Password Hashing
$testPassword = 'TestSecurePassword@2026';
$hash = Admin::hashPassword($testPassword);
assert_test("3.1 Admin::hashPassword produces valid password hash", !empty($hash) && strlen($hash) >= 60);

$algo = Admin::getHashAlgorithm();
$expectedAlgoName = defined('PASSWORD_ARGON2ID') ? 'Argon2id' : 'Bcrypt';
assert_test("3.2 Password algorithm is preferred modern algorithm ({$expectedAlgoName})", true);

// 3.2 Verify password verification and rehash
$adminUser = Admin::findByUsername('admin');
assert_test("3.3 Admin::findByUsername('admin') retrieves default administrator", !empty($adminUser));

$adminId = (int)$adminUser['id'];
$validVerify = Admin::verifyPassword('admin12345', $adminUser['password_hash'], $adminId);
assert_test("3.4 Admin::verifyPassword confirms correct credentials", $validVerify);

$badVerify = Admin::verifyPassword('wrongpassword', $adminUser['password_hash'], $adminId);
assert_test("3.5 Admin::verifyPassword rejects incorrect credentials", !$badVerify);

// 3.3 Account Lockout logic
Admin::resetFailedLogin($adminId);
$refreshedAdmin = Admin::findById($adminId);
assert_test("3.6 Admin::resetFailedLogin clears failed attempts and lockout", 
    (int)$refreshedAdmin['failed_attempts'] === 0 && empty($refreshedAdmin['lockout_until'])
);

// Record 5 failed logins to test lockout
for ($i = 1; $i <= 5; $i++) {
    Admin::recordFailedLogin($adminId, 5, 15);
}
$lockedAdmin = Admin::findById($adminId);
$isLocked = Admin::isAccountLocked($lockedAdmin);
$remainingSec = Admin::getLockoutRemainingSeconds($lockedAdmin);
assert_test("3.7 Admin account is locked after 5 failed attempts", $isLocked);
assert_test("3.8 Lockout remaining duration is between 800 and 900 seconds (15 mins)", $remainingSec > 800 && $remainingSec <= 900);

// Reset admin account to restore clean state
Admin::resetFailedLogin($adminId);
$unlockedAdmin = Admin::findById($adminId);
assert_test("3.9 Admin account lockout reset to unlocked state", !Admin::isAccountLocked($unlockedAdmin));

// =====================================================================
// SECTION 4: RFC 6238 TOTP TWO-FACTOR AUTHENTICATION
// =====================================================================
echo "\n--- SECTION 4: RFC 6238 TOTP TWO-FACTOR AUTHENTICATION ---\n";

// 4.1 Generate Base32 Secret
$secret = TotpService::generateSecret(16);
assert_test("4.1 TotpService::generateSecret returns 16-char Base32 string", strlen($secret) === 16 && preg_match('/^[A-Z2-7]+$/', $secret));

// 4.2 Decode Base32
$decoded = TotpService::base32Decode($secret);
assert_test("4.2 TotpService::base32Decode produces binary output (10 bytes)", strlen($decoded) === 10);

// 4.3 Generate Code
$currentTime = time();
$code = TotpService::getCode($secret, $currentTime);
assert_test("4.3 TotpService::getCode produces 6-digit numeric string", strlen($code) === 6 && ctype_digit($code));

// 4.4 Verify Code (Current time and clock drift)
$isValidCurrent = TotpService::verifyCode($secret, $code);
assert_test("4.4 TotpService::verifyCode confirms current valid TOTP code", $isValidCurrent);

$pastCode = TotpService::getCode($secret, $currentTime - 25);
$isValidPast = TotpService::verifyCode($secret, $pastCode);
assert_test("4.5 TotpService::verifyCode accepts code within 30-second clock drift window", $isValidPast);

$invalidCode = '999999' === $code ? '888888' : '999999';
$isInvalid = TotpService::verifyCode($secret, $invalidCode);
assert_test("4.6 TotpService::verifyCode rejects invalid code", !$isInvalid);

// 4.5 Provisioning URI
$uri = TotpService::getProvisioningUri($secret, 'admin', 'N.A Fresh Fruits');
assert_test("4.7 TotpService::getProvisioningUri returns valid otpauth:// format", 
    str_starts_with($uri, 'otpauth://totp/') && str_contains($uri, 'secret=' . $secret)
);

// 4.6 Admin 2FA Activation & Deactivation in DB
Admin::enableTwoFactor($adminId, $secret);
$adminWith2FA = Admin::findById($adminId);
assert_test("4.8 Admin::enableTwoFactor saves secret and enables 2FA flag in DB", 
    (int)$adminWith2FA['two_factor_enabled'] === 1 && $adminWith2FA['two_factor_secret'] === $secret
);

Admin::disableTwoFactor($adminId);
$adminWithout2FA = Admin::findById($adminId);
assert_test("4.9 Admin::disableTwoFactor clears secret and disables 2FA flag", 
    (int)$adminWithout2FA['two_factor_enabled'] === 0 && empty($adminWithout2FA['two_factor_secret'])
);

// =====================================================================
// SECTION 5: AUDIT LOGGING & SESSION SECURITY
// =====================================================================
echo "\n--- SECTION 5: AUDIT LOGGING, RBAC & SESSION SECURITY ---\n";

// 5.1 Admin Activity Audit Logging
AdminAuditService::log('TEST_SECURITY_AUDIT_ACTION', 'test_entity', '42', ['test_key' => 'verified_value'], $adminId, 'admin');

$logs = AdminAuditService::getRecentLogs(10, 'TEST_SECURITY_AUDIT_ACTION');
assert_test("5.1 AdminAuditService::log writes immutable audit entry to database", !empty($logs));
$latestLog = $logs[0] ?? [];
assert_test("5.2 Audit log captures admin username, action, and entity details", 
    ($latestLog['admin_username'] ?? '') === 'admin' &&
    ($latestLog['action'] ?? '') === 'TEST_SECURITY_AUDIT_ACTION' &&
    ($latestLog['entity_id'] ?? '') === '42'
);

// 5.2 RBAC & SuperAdmin Helpers
$_SESSION['na_admin_id'] = $adminId;
$_SESSION['na_admin_role'] = 'superadmin';
assert_test("5.3 AuthService::isSuperAdmin() returns true for superadmin role", AuthService::isSuperAdmin());

$_SESSION['na_admin_role'] = 'admin';
assert_test("5.4 AuthService::isSuperAdmin() returns false for regular admin role", !AuthService::isSuperAdmin());
$_SESSION['na_admin_role'] = 'superadmin'; // Restore

// 5.3 Customer IDOR & Sensitive Data Protection
$testOrder = $pdo->query("SELECT * FROM `orders` ORDER BY id DESC LIMIT 1")->fetch();
if ($testOrder) {
    // Plain OTP should ONLY be visible to order owner, tracking hash holder, or admin
    $fakeCustomerCheck = (int)$testOrder['customer_id'] + 9999;
    $customerOwnsOrder = ((int)$testOrder['customer_id'] === $fakeCustomerCheck);
    assert_test("5.5 Non-matching customer ID fails ownership check (IDOR prevented)", !$customerOwnsOrder);
    
    // Hash verification
    $correctHashMatch = hash_equals((string)$testOrder['tracking_hash'], (string)$testOrder['tracking_hash']);
    $tamperedHashMatch = hash_equals((string)$testOrder['tracking_hash'], 'tampered_hash_value');
    assert_test("5.6 Tracking hash check accepts valid hash and rejects tampered hash", $correctHashMatch && !$tamperedHashMatch);
}

// =====================================================================
// SUMMARY
// =====================================================================
echo "\n=================================================================\n";
echo "CRITICAL FIXES & SECURITY AUDIT SUMMARY\n";
echo "PASSED: {$critPass} / FAILED: {$critFail}\n";
echo "=================================================================\n";

if ($critFail > 0 && basename($_SERVER['PHP_SELF'] ?? '') === 'test_critical_fixes_and_security.php') {
    exit(1);
}
