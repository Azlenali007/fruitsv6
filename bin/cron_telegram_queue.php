<?php
/**
 * Telegram Queue Cron Job Processor
 * N.A Fresh Fruits & Coconuts
 *
 * Designed exclusively for Linux / cPanel Server-Side Cron Execution.
 * Runs non-interactively via PHP CLI every 30 seconds.
 *
 * Hosting Crontab Setup (2-pass 30s schedule):
 *   * * * * * /usr/bin/php /app/applet/bin/cron_telegram_queue.php >> /app/applet/storage/logs/telegram_cron.log 2>&1
 *   * * * * * sleep 30 && /usr/bin/php /app/applet/bin/cron_telegram_queue.php >> /app/applet/storage/logs/telegram_cron.log 2>&1
 */

// 1. Strict CLI Environment Guard
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script must be executed via PHP CLI cron.\n";
    exit(1);
}

// 2. Resource Limits & Configuration
ini_set('memory_limit', '128M');
set_time_limit(60);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 3. Concurrency Lock: Prevent overlapping runs if previous pass is still processing
$lockFilePath = __DIR__ . '/../storage/logs/cron_telegram.lock';
@mkdir(dirname($lockFilePath), 0755, true);
$lockFp = @fopen($lockFilePath, 'w+');

if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    // Another cron execution is currently active; exit cleanly
    echo "[" . date('Y-m-d H:i:s') . "] [Cron] Another queue processing cycle is currently running. Skipping.\n";
    exit(0);
}

// 4. Load Application Framework
require_once __DIR__ . '/../app/bootstrap.php';

$startTime = microtime(true);
$runTimestamp = date('Y-m-d H:i:s');

try {
    // Process queue batch (batch size 10)
    $summary = TelegramQueueService::processBatch(10);

    // Record cron heartbeat and telemetry in database
    TelegramQueueService::recordCronExecution($summary);

    $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
    $claimed = $summary['claimed'] ?? 0;
    $sent = $summary['sent'] ?? 0;
    $retrying = $summary['retrying'] ?? 0;
    $failed = $summary['failed'] ?? 0;
    $skipped = $summary['skipped'] ?? 0;
    $recovered = $summary['recovered'] ?? 0;

    $logLine = "[{$runTimestamp}] [Cron 30s] Completed: {$claimed} jobs processed (Sent: {$sent}, Retrying: {$retrying}, Failed: {$failed}, Skipped: {$skipped}, Recovered: {$recovered}) in {$elapsedMs}ms\n";
    echo $logLine;

} catch (Throwable $e) {
    $errLine = "[{$runTimestamp}] [Cron 30s] ERROR: " . $e->getMessage() . "\n";
    echo $errLine;

    try {
        Setting::set('telegram_cron_last_status', 'error');
        Setting::set('telegram_cron_last_summary', 'Error: ' . $e->getMessage());
    } catch (Throwable $dbErr) {}
} finally {
    // Release file lock
    if ($lockFp) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}

exit(0);
