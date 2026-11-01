<?php
/**
 * Telegram Order Notifications - Single PHP Cron Processor
 * N.A Fresh Fruits & Coconuts
 *
 * Designed exclusively for Linux / cPanel Server-Side Cron Execution.
 * Runs non-interactively via PHP CLI once per minute from crontab.
 * Inside this script, it executes TWO cycles approximately 30 seconds apart,
 * providing 100% automatic 30-second processing with ONLY ONE server Cron Job entry.
 *
 * Hosting Crontab Setup (Single Entry):
 *   * * * * * /usr/bin/php /app/applet/cron/telegram-notifications.php >> /app/applet/storage/logs/telegram_cron.log 2>&1
 */

// 1. Strict CLI Guard
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script must be executed via PHP CLI cron.\n";
    exit(1);
}

// 2. Resource Limits & Configuration
ini_set('memory_limit', '128M');
set_time_limit(90); // Allow sufficient time for both 30s cycles
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 3. Concurrency Lock: Prevent overlapping runs if previous minute's run is still finishing
$lockFilePath = __DIR__ . '/../storage/logs/telegram_cron.lock';
@mkdir(dirname($lockFilePath), 0755, true);
$lockFp = @fopen($lockFilePath, 'w+');

if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] [Cron] Another Telegram notification cycle is currently running. Skipping.\n";
    exit(0);
}

// 4. Load Application Framework
require_once __DIR__ . '/../app/bootstrap.php';

// Parse optional CLI options (e.g. --once for rapid testing)
$options = getopt('', ['once', 'verbose']);
$runOnce = isset($options['once']);

// Helper function to process a single queue cycle
function run_telegram_queue_cycle(int $cycleNumber, int $totalCycles = 2): array {
    $startTime = microtime(true);
    $runTimestamp = date('Y-m-d H:i:s');

    try {
        // Process eligible batch from queue
        $summary = TelegramQueueService::processBatch(10);

        // Record heartbeat, timestamp and stats in database
        TelegramQueueService::recordCronExecution($summary);

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        $claimed = $summary['claimed'] ?? 0;
        $sent = $summary['sent'] ?? 0;
        $retrying = $summary['retrying'] ?? 0;
        $failed = $summary['failed'] ?? 0;
        $skipped = $summary['skipped'] ?? 0;
        $recovered = $summary['recovered'] ?? 0;

        $logLine = "[{$runTimestamp}] [Cron Cycle {$cycleNumber}/{$totalCycles}] Completed: {$claimed} jobs processed (Sent: {$sent}, Retrying: {$retrying}, Failed: {$failed}, Skipped: {$skipped}, Recovered: {$recovered}) in {$elapsedMs}ms\n";
        echo $logLine;

        return $summary;

    } catch (Throwable $e) {
        $errLine = "[{$runTimestamp}] [Cron Cycle {$cycleNumber}/{$totalCycles}] ERROR: " . $e->getMessage() . "\n";
        echo $errLine;

        try {
            Setting::set('telegram_cron_last_status', 'error');
            Setting::set('telegram_cron_last_summary', 'Error: ' . $e->getMessage());
        } catch (Throwable $dbErr) {}

        return ['claimed' => 0, 'error' => $e->getMessage()];
    }
}

try {
    // -------------------------------------------------------------
    // CYCLE 1: Immediate execution at start of the minute
    // -------------------------------------------------------------
    run_telegram_queue_cycle(1, 2);

    // If --once flag is passed (e.g. in test suites), exit after cycle 1
    if (!$runOnce) {
        // -------------------------------------------------------------
        // Wait 30 seconds before cycle 2
        // -------------------------------------------------------------
        sleep(30);

        // -------------------------------------------------------------
        // CYCLE 2: Second execution approximately 30 seconds later
        // -------------------------------------------------------------
        run_telegram_queue_cycle(2, 2);
    }

} finally {
    // Release concurrency lock
    if ($lockFp) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}

exit(0);
