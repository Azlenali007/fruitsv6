<?php
/**
 * Automated Test Suite: Telegram Notification Queue & 30-Second Background Worker
 * N.A Fresh Fruits & Coconuts
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/bootstrap.php';

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$message}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "12. TELEGRAM QUEUE & 30-SECOND WORKER TESTS\n";
echo "========================================================\n";

$pdo = Database::getConnection();

// Snapshot original settings to guarantee 100% clean restoration
$originalSettings = [
    'telegram_notifications_enabled' => Setting::get('telegram_notifications_enabled', '0'),
    'telegram_bot_token' => Setting::get('telegram_bot_token', ''),
    'telegram_chat_id' => Setting::get('telegram_chat_id', ''),
    'telegram_send_on_cod' => Setting::get('telegram_send_on_cod', '1'),
    'telegram_send_on_online_paid' => Setting::get('telegram_send_on_online_paid', '1'),
    'cod_enabled' => Setting::get('cod_enabled', '1'),
    'razorpay_enabled' => Setting::get('razorpay_enabled', '0')
];

// -----------------------------------------------------------------------------
// SECTION A: DATABASE SCHEMA & MIGRATION VERIFICATION
// -----------------------------------------------------------------------------
echo "A. DATABASE TABLE & INDEXES:\n";

$stmtTable = $pdo->query("SHOW TABLES LIKE 'telegram_notification_queue'");
$tableExists = (bool)$stmtTable->fetch();
assert_test($tableExists, "telegram_notification_queue table exists in database");

$stmtCols = $pdo->query("SHOW COLUMNS FROM `telegram_notification_queue`");
$columns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
$expectedCols = ['id', 'order_id', 'event_type', 'idempotency_key', 'status', 'attempts', 'max_attempts', 'next_attempt_at', 'locked_at', 'sent_at', 'telegram_message_id', 'last_error', 'created_at', 'updated_at'];

$hasAllCols = count(array_diff($expectedCols, $columns)) === 0;
assert_test($hasAllCols, "All expected queue columns exist (idempotency, status, attempts, locked_at, etc.)");

$stmtIdx = $pdo->query("SHOW INDEX FROM `telegram_notification_queue`");
$indexes = [];
while ($row = $stmtIdx->fetch(PDO::FETCH_ASSOC)) {
    $indexes[] = $row['Key_name'];
}
assert_test(in_array('idempotency_key', $indexes) || in_array('idx_tgq_idempotency', $indexes), "Unique idempotency key index exists");
assert_test(in_array('idx_tgq_status_next', $indexes), "Composite performance index (status, next_attempt_at) exists");

// -----------------------------------------------------------------------------
// SECTION B: QUEUE INSERTION & IDEMPOTENCY
// -----------------------------------------------------------------------------
echo "\nB. QUEUE ENQUEUEING & IDEMPOTENCY:\n";

// Clean any previous test rows
$testOrderId = 999999;
$pdo->exec("DELETE FROM `telegram_notification_queue` WHERE `order_id` = {$testOrderId}");

$res1 = TelegramQueueService::enqueue($testOrderId, 'order_placed');
assert_test($res1['success'] === true, "Enqueuing new order notification returns success");
assert_test($res1['already_queued'] === false, "First enqueue is marked as newly queued");
assert_test($res1['queue_id'] > 0, "Queue ID generated: " . $res1['queue_id']);

// Check row in DB
$stmtRow = $pdo->prepare("SELECT * FROM `telegram_notification_queue` WHERE `id` = ?");
$stmtRow->execute([$res1['queue_id']]);
$row1 = $stmtRow->fetch(PDO::FETCH_ASSOC);
assert_test($row1['status'] === 'pending', "Newly enqueued notification has status 'pending'");
assert_test((int)$row1['attempts'] === 0, "Attempts initialized to 0");
assert_test($row1['idempotency_key'] === "tg_order_{$testOrderId}_order_placed", "Idempotency key generated correctly");

// Idempotency test: duplicate enqueue
$res2 = TelegramQueueService::enqueue($testOrderId, 'order_placed');
assert_test($res2['success'] === true, "Duplicate enqueue call succeeds safely");
assert_test($res2['already_queued'] === true, "Duplicate enqueue detected existing record (already_queued = true)");
assert_test($res2['queue_id'] === $res1['queue_id'], "Returned same queue ID without duplicating row");

// Verify row count remains exactly 1
$stmtCount = $pdo->query("SELECT COUNT(*) FROM `telegram_notification_queue` WHERE `order_id` = {$testOrderId}");
assert_test((int)$stmtCount->fetchColumn() === 1, "Database contains exactly 1 queue record for order");

// -----------------------------------------------------------------------------
// SECTION C: CHECKOUT COD HOOK VERIFICATION
// -----------------------------------------------------------------------------
echo "\nC. STOREFRONT COD CHECKOUT INTEGRATION:\n";

// Ensure COD is enabled
Setting::set('cod_enabled', '1');

// Create test customer if not exists
$cust = Customer::findByPhone('9876543210');
if (!$cust) {
    Customer::register([
        'name' => 'Queue Test User',
        'phone' => '9876543210',
        'password' => 'pass123456',
        'address' => 'Jaora Main Road, Ratlam'
    ]);
    $cust = Customer::findByPhone('9876543210');
}
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_ID] = (int)$cust['id'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_NAME] = $cust['name'];

// Get active product
$pStmt = $pdo->query("SELECT * FROM `products` WHERE `is_available` = 1 LIMIT 1");
$prod = $pStmt->fetch(PDO::FETCH_ASSOC);

// Populate cart
CartService::clear();
CartService::addItem((int)$prod['id'], 2);

// Register mock handler for Razorpay order creation
RazorpayService::setMockHandler(function (string $method, string $endpoint, array $data = []) {
    if ($endpoint === '/orders') {
        return [
            'success' => true,
            'http_code' => 200,
            'data' => [
                'id' => 'order_mock_' . bin2hex(random_bytes(6)),
                'amount' => $data['amount'] ?? 10000,
                'currency' => 'INR',
                'status' => 'created'
            ]
        ];
    }
    return ['success' => true, 'http_code' => 200, 'data' => []];
});

// Place COD Order
$orderPost = [
    CSRF_TOKEN_KEY => csrf_token(),
    'customer_name' => 'Queue Test User',
    'customer_phone' => '9876543210',
    'payment_method' => 'cod',
    'fulfilment_method' => 'delivery',
    'delivery_address' => 'Station Road, Jaora, Madhya Pradesh',
    'idempotency_key' => 'idem_tg_cod_' . uniqid()
];

$orderRes = OrderService::processCheckout($orderPost);
assert_test(!empty($orderRes['success']), "COD order successfully placed via OrderService");
$codOrderId = (int)$orderRes['order_id'];

// Check that order is automatically enqueued into telegram_notification_queue
$stmtCodQueue = $pdo->prepare("SELECT * FROM `telegram_notification_queue` WHERE `order_id` = ? AND `event_type` = 'order_placed'");
$stmtCodQueue->execute([$codOrderId]);
$codQueueRow = $stmtCodQueue->fetch(PDO::FETCH_ASSOC);

assert_test(!empty($codQueueRow), "COD order was automatically queued into telegram_notification_queue");
assert_test($codQueueRow['status'] === 'pending', "COD queue status is 'pending' waiting for worker");

// -----------------------------------------------------------------------------
// SECTION D: RAZORPAY PAYMENT VERIFICATION INTEGRATION
// -----------------------------------------------------------------------------
echo "\nD. RAZORPAY VERIFIED PAYMENT INTEGRATION:\n";

// Create online order
CartService::addItem((int)$prod['id'], 1);
Setting::set('razorpay_enabled', '1');
$onlinePost = [
    CSRF_TOKEN_KEY => csrf_token(),
    'customer_name' => 'Queue Online User',
    'customer_phone' => '9876543210',
    'payment_method' => 'online',
    'fulfilment_method' => 'delivery',
    'delivery_address' => 'Station Road, Jaora',
    'idempotency_key' => 'idem_tg_online_' . uniqid()
];
$onlineRes = OrderService::processCheckout($onlinePost);
assert_test(!empty($onlineRes['success']) && !empty($onlineRes['requires_razorpay']), "Online order created awaiting payment");
$onlineOrderId = (int)$onlineRes['order_id'];

// Check that online order was NOT enqueued as order_paid yet
$stmtPrePay = $pdo->prepare("SELECT * FROM `telegram_notification_queue` WHERE `order_id` = ? AND `event_type` = 'order_paid'");
$stmtPrePay->execute([$onlineOrderId]);
assert_test(empty($stmtPrePay->fetch()), "Unpaid / unverified online order is NOT enqueued into Telegram queue");

// Simulate payment verification success
Order::updatePaymentAndOrderStatus($onlineOrderId, 'captured', 'confirmed', [
    'razorpay_payment_id' => 'pay_test_' . time()
]);
TelegramService::enqueueOrderNotification($onlineOrderId, 'order_paid');

$stmtPostPay = $pdo->prepare("SELECT * FROM `telegram_notification_queue` WHERE `order_id` = ? AND `event_type` = 'order_paid'");
$stmtPostPay->execute([$onlineOrderId]);
$paidQueueRow = $stmtPostPay->fetch(PDO::FETCH_ASSOC);
assert_test(!empty($paidQueueRow), "Verified captured online order is enqueued into Telegram queue as 'order_paid'");
assert_test($paidQueueRow['status'] === 'pending', "Paid order queue status is 'pending'");

// -----------------------------------------------------------------------------
// SECTION E: ATOMIC JOB CLAIMING & CONCURRENCY PROTECTION
// -----------------------------------------------------------------------------
echo "\nE. ATOMIC JOB CLAIMING & CONCURRENCY PROTECTION:\n";

$claimedJobs = TelegramQueueService::claimNextJobs(2);
assert_test(count($claimedJobs) > 0, "claimNextJobs successfully claimed pending jobs");

$firstJob = $claimedJobs[0];
assert_test($firstJob['status'] === 'processing', "Claimed job status transitioned to 'processing'");
assert_test(!empty($firstJob['locked_at']), "Claimed job has non-null locked_at timestamp");

// Verify that a concurrent claim will NOT re-claim already locked jobs
$concurrentJobs = TelegramQueueService::claimNextJobs(2);
$claimedIds = array_column($claimedJobs, 'id');
$concurrentIds = array_column($concurrentJobs, 'id');
$overlap = array_intersect($claimedIds, $concurrentIds);
assert_test(empty($overlap), "Concurrent claim did NOT re-claim already locked jobs (Zero overlap)");

// -----------------------------------------------------------------------------
// SECTION F: ZOMBIE / STUCK JOB RECOVERY
// -----------------------------------------------------------------------------
echo "\nF. ZOMBIE / STUCK JOB RECOVERY:\n";

// Artificially lock a job with timestamp 10 minutes in the past
$pdo->prepare("
    UPDATE `telegram_notification_queue`
    SET `status` = 'processing', `locked_at` = DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    WHERE `id` = ?
")->execute([$firstJob['id']]);

$recoveredCount = TelegramQueueService::recoverStuckJobs(5);
assert_test($recoveredCount >= 1, "recoverStuckJobs recovered at least 1 zombie job (found: {$recoveredCount})");

$stmtRecovered = $pdo->prepare("SELECT `status`, `locked_at`, `last_error` FROM `telegram_notification_queue` WHERE `id` = ?");
$stmtRecovered->execute([$firstJob['id']]);
$recoveredRow = $stmtRecovered->fetch(PDO::FETCH_ASSOC);

assert_test($recoveredRow['status'] === 'pending', "Recovered job status restored to 'pending'");
assert_test($recoveredRow['locked_at'] === null, "Recovered job locked_at reset to NULL");
assert_test(str_contains($recoveredRow['last_error'], 'Recovered from zombie processing state'), "Last error records recovery audit notice");

// -----------------------------------------------------------------------------
// SECTION G: RETRY MECHANICS & EXPONENTIAL BACKOFF
// -----------------------------------------------------------------------------
echo "\nG. RETRY MECHANICS & EXPONENTIAL BACKOFF:\n";

// Test processJob with Telegram notifications disabled
Setting::set('telegram_notifications_enabled', '0');
$processRes1 = TelegramQueueService::processJob($recoveredRow = array_merge($firstJob, ['status' => 'processing', 'attempts' => 0]));

assert_test($processRes1['skipped'] === true, "When Telegram is disabled, job is safely skipped without throwing error");

// Re-enable and test failure backoff using mock handler (simulated 504 gateway timeout)
TelegramService::setMockHandler(function($token, $chatId, $text) {
    return ['success' => false, 'error' => 'Gateway connection timeout', 'http_code' => 504];
});
Setting::set('telegram_notifications_enabled', '1');
Setting::set('telegram_bot_token', '987654321:AAEk92384923849_validFormatToken');
Setting::set('telegram_chat_id', '-100123456789');

// Mock a job with attempts = 1
$testJobRow = [
    'id' => $firstJob['id'],
    'order_id' => $codOrderId,
    'event_type' => 'order_placed',
    'attempts' => 1,
    'max_attempts' => 5
];

$failRes = TelegramQueueService::processJob($testJobRow);
assert_test($failRes['success'] === false, "Simulated network timeout failed as expected");
assert_test(isset($failRes['retry_in_seconds']), "Scheduled exponential backoff retry in " . ($failRes['retry_in_seconds'] ?? 0) . "s");

$stmtRetryCheck = $pdo->prepare("SELECT `status`, `attempts`, `next_attempt_at`, `last_error` FROM `telegram_notification_queue` WHERE `id` = ?");
$stmtRetryCheck->execute([$firstJob['id']]);
$retryRow = $stmtRetryCheck->fetch(PDO::FETCH_ASSOC);

assert_test($retryRow['status'] === 'pending', "Retry keeps job in 'pending' status for next attempt");
assert_test((int)$retryRow['attempts'] === 2, "Attempts count incremented to 2");
assert_test(!empty($retryRow['last_error']), "Error message recorded in last_error column");

// Max attempts test: when attempts >= 5
$maxJobRow = [
    'id' => $firstJob['id'],
    'order_id' => $codOrderId,
    'event_type' => 'order_placed',
    'attempts' => 4,
    'max_attempts' => 5
];
$maxFailRes = TelegramQueueService::processJob($maxJobRow);
assert_test(!empty($maxFailRes['failed_permanently']), "After 5th failed attempt, job is marked permanently failed");

$stmtMaxCheck = $pdo->prepare("SELECT `status` FROM `telegram_notification_queue` WHERE `id` = ?");
$stmtMaxCheck->execute([$firstJob['id']]);
assert_test($stmtMaxCheck->fetchColumn() === 'failed', "Queue row status transitioned to 'failed'");

// -----------------------------------------------------------------------------
// SECTION H: RETRY FAILED JOBS & QUEUE METRICS
// -----------------------------------------------------------------------------
echo "\nH. ADMIN RETRY & QUEUE METRICS:\n";

$retried = TelegramQueueService::retryFailedJobs();
assert_test($retried >= 1, "retryFailedJobs successfully re-queued {$retried} failed jobs back to pending");

$stmtAfterRetry = $pdo->prepare("SELECT `status`, `attempts` FROM `telegram_notification_queue` WHERE `id` = ?");
$stmtAfterRetry->execute([$firstJob['id']]);
$afterRetryRow = $stmtAfterRetry->fetch(PDO::FETCH_ASSOC);
assert_test($afterRetryRow['status'] === 'pending', "Failed job status reset to 'pending'");
assert_test((int)$afterRetryRow['attempts'] === 0, "Attempts reset to 0 for fresh retry cycle");

// Telemetry Stats Check
$stats = TelegramQueueService::getQueueStats();
assert_test(isset($stats['pending']) && isset($stats['sent']) && isset($stats['failed']), "getQueueStats returns status counts");
assert_test($stats['total'] >= 2, "getQueueStats total count is accurate (found: {$stats['total']})");
assert_test(isset($stats['is_worker_alive']), "getQueueStats contains worker health heartbeat indicator");

// -----------------------------------------------------------------------------
// SECTION I: CLI WORKER SCRIPT VERIFICATION (--once)
// -----------------------------------------------------------------------------
// Disable notifications for clean CLI cycle test without unexpected external network calls
Setting::set('telegram_notifications_enabled', '0');

$workerCmd = "php " . escapeshellarg(__DIR__ . '/../bin/telegram_worker.php') . " --once --verbose";
exec($workerCmd, $cmdOutput, $exitCode);

assert_test($exitCode === 0, "php bin/telegram_worker.php --once exited with code 0");
$outputStr = implode("\n", $cmdOutput);
assert_test(str_contains($outputStr, 'Starting Telegram Notification Worker'), "Worker initialized cleanly");
assert_test(str_contains($outputStr, 'Single pass complete'), "Worker executed single pass and stopped cleanly");

// Check that worker heartbeat was updated in database
$updatedStats = TelegramQueueService::getQueueStats();
assert_test(!empty($updatedStats['worker_heartbeat']), "Worker recorded heartbeat in database: " . $updatedStats['worker_heartbeat']);
assert_test($updatedStats['seconds_since_heartbeat'] < 10, "Worker heartbeat timestamp is fresh (<10s ago, actual: {$updatedStats['seconds_since_heartbeat']}s)");

// -----------------------------------------------------------------------------
// SECTION J: SERVER-SIDE CRON SCRIPT VERIFICATION (30-SECOND CRON)
// -----------------------------------------------------------------------------
echo "\nJ. SERVER CRON SCRIPT & TELEMETRY VERIFICATION:\n";

$cronCmd = "/usr/bin/php " . escapeshellarg(__DIR__ . '/../bin/cron_telegram_queue.php');
exec($cronCmd, $cronOutput, $cronExitCode);

assert_test($cronExitCode === 0, "php bin/cron_telegram_queue.php executed with code 0");
$cronOutputStr = implode("\n", $cronOutput);
assert_test(str_contains($cronOutputStr, '[Cron 30s] Completed:'), "Cron processor output reports successful batch cycle");

$cronStats = TelegramQueueService::getQueueStats();
assert_test(!empty($cronStats['cron_last_execution_at']), "Cron recorded execution timestamp in database: " . $cronStats['cron_last_execution_at']);
assert_test($cronStats['seconds_since_cron'] <= 10, "Cron execution is fresh (<= 10s ago, actual: {$cronStats['seconds_since_cron']}s)");
assert_test($cronStats['is_cron_active'] === true, "Cron status is verified ACTIVE");
assert_test(is_executable(__DIR__ . '/../bin/cron_telegram_queue.php'), "bin/cron_telegram_queue.php has executable permissions");
assert_test(is_executable(__DIR__ . '/../bin/cron_30s.sh'), "bin/cron_30s.sh wrapper has executable permissions");

// Clean test artifacts & restore original settings
foreach ($originalSettings as $k => $v) {
    Setting::set($k, (string)$v);
}
TelegramService::setMockHandler(null);

$pdo->exec("DELETE FROM `telegram_notification_queue` WHERE `order_id` IN ({$testOrderId}, {$codOrderId}, {$onlineOrderId})");
$pdo->exec("DELETE FROM `orders` WHERE `id` IN ({$codOrderId}, {$onlineOrderId})");
$pdo->exec("DELETE FROM `order_items` WHERE `order_id` IN ({$codOrderId}, {$onlineOrderId})");

echo "\n========================================================\n";
echo "TELEGRAM QUEUE & WORKER SUITE: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    exit($failed > 0 ? 1 : 0);
}
