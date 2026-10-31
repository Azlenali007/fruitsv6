<?php
/**
 * Telegram Notification Background Worker
 * N.A Fresh Fruits & Coconuts
 *
 * Standalone PHP CLI Daemon / Cron Worker.
 * Continuously polls and processes pending order notifications from telegram_notification_queue.
 *
 * Usage:
 *   Continuous 30s Daemon:
 *     php bin/telegram_worker.php
 *
 *   Single Pass (Cron / CI / Testing):
 *     php bin/telegram_worker.php --once
 *
 *   Custom Sleep Interval:
 *     php bin/telegram_worker.php --sleep=30
 */

// 1. Strict CLI Check
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script can only be executed via the PHP CLI environment.\n";
    exit(1);
}

// 2. Set memory limit, infinite execution time, and error reporting
ini_set('memory_limit', '128M');
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 3. Load Application Bootstrap
require_once __DIR__ . '/../app/bootstrap.php';

// Parse CLI Arguments
$options = getopt('', ['once', 'batch:', 'sleep:', 'verbose']);
$runOnce = isset($options['once']);
$batchSize = isset($options['batch']) ? max(1, (int)$options['batch']) : 10;
$sleepSeconds = isset($options['sleep']) ? max(1, (int)$options['sleep']) : 30;
$verbose = isset($options['verbose']);

// Log helper
function worker_log(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[{$timestamp}] [TelegramWorker] {$message}\n";
    echo $formatted;
    @file_put_contents(__DIR__ . '/../storage/logs/telegram_worker.log', $formatted, FILE_APPEND);
}

worker_log("Starting Telegram Notification Worker (PID: " . getmypid() . ", Mode: " . ($runOnce ? "Single-Run" : "Continuous Loop ({$sleepSeconds}s)") . ")...");

// Signal Handling for Graceful Shutdown
$shouldShutdown = false;
if (function_exists('pcntl_signal')) {
    declare(ticks = 1);
    $signalHandler = function (int $signo) use (&$shouldShutdown) {
        worker_log("Received signal {$signo}. Finishing active batch and preparing graceful shutdown...");
        $shouldShutdown = true;
    };
    @pcntl_signal(SIGTERM, $signalHandler);
    @pcntl_signal(SIGINT, $signalHandler);
}

// Initial Heartbeat
TelegramQueueService::updateHeartbeat('running');

// Main Worker Loop
$iteration = 0;
do {
    $iteration++;
    $cycleStart = microtime(true);

    try {
        // Process current batch
        $summary = TelegramQueueService::processBatch($batchSize);

        $claimed = $summary['claimed'] ?? 0;
        $sent = $summary['sent'] ?? 0;
        $failed = $summary['failed'] ?? 0;
        $skipped = $summary['skipped'] ?? 0;
        $retrying = $summary['retrying'] ?? 0;
        $recovered = $summary['recovered'] ?? 0;

        if ($claimed > 0 || $recovered > 0 || $verbose) {
            $parts = [];
            if ($sent > 0) $parts[] = "Delivered: {$sent}";
            if ($skipped > 0) $parts[] = "Deferred: {$skipped}";
            if ($retrying > 0) $parts[] = "Backoff-Scheduled: {$retrying}";
            if ($failed > 0) $parts[] = "Permanently-Failed: {$failed}";
            if ($recovered > 0) $parts[] = "Zombie-Recovered: {$recovered}";
            if (empty($parts)) $parts[] = "No-Op";
            $statusStr = implode(', ', $parts);
            worker_log("Cycle #{$iteration}: {$claimed} jobs ({$statusStr}) in " . round((microtime(true) - $cycleStart) * 1000, 2) . "ms");
        }

    } catch (Throwable $e) {
        worker_log("CRITICAL ERROR in Worker Cycle #{$iteration}: " . $e->getMessage());
        TelegramQueueService::updateHeartbeat('idle');
    }

    if ($runOnce) {
        worker_log("Single pass complete (--once flag provided). Exiting cleanly.");
        break;
    }

    if ($shouldShutdown) {
        worker_log("Graceful shutdown confirmed. Setting worker status to stopped.");
        break;
    }

    // High-resolution sleep in 1-second chunks to allow rapid response to signals
    for ($s = 0; $s < $sleepSeconds; $s++) {
        if ($shouldShutdown) {
            break 2;
        }
        sleep(1);
    }

} while (!$shouldShutdown);

// Final cleanup
TelegramQueueService::updateHeartbeat('stopped');
worker_log("Telegram Notification Worker stopped cleanly.");
exit(0);
