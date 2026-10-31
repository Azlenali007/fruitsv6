<?php
/**
 * Telegram Queue Service
 * N.A Fresh Fruits & Coconuts
 *
 * Manages database-backed persistent queue for Telegram order notifications.
 * Provides atomic job locking, stuck job recovery, exponential backoff retries,
 * worker heartbeat telemetry, and queue analytics.
 */

class TelegramQueueService {

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SENT       = 'sent';
    const STATUS_FAILED     = 'failed';

    /**
     * Enqueue an order notification safely and idempotently.
     * Prevents duplicate queue entries for the same order and event.
     *
     * @param int $orderId
     * @param string $eventType 'order_placed' (COD) or 'order_paid' (Razorpay verified)
     * @return array Result with success flag, queue item ID, and status
     */
    public static function enqueue(int $orderId, string $eventType = 'order_placed'): array {
        if ($orderId <= 0) {
            return ['success' => false, 'error' => 'Invalid order ID'];
        }

        $idempotencyKey = "tg_order_{$orderId}_{$eventType}";

        try {
            $pdo = Database::getConnection();

            // Check if already in queue
            $stmtCheck = $pdo->prepare("SELECT `id`, `status`, `attempts` FROM `telegram_notification_queue` WHERE `idempotency_key` = ?");
            $stmtCheck->execute([$idempotencyKey]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                return [
                    'success' => true,
                    'already_queued' => true,
                    'queue_id' => (int)$existing['id'],
                    'status' => $existing['status']
                ];
            }

            // Insert new pending job
            $stmtInsert = $pdo->prepare("
                INSERT INTO `telegram_notification_queue` 
                (`order_id`, `event_type`, `idempotency_key`, `status`, `attempts`, `max_attempts`, `next_attempt_at`, `created_at`)
                VALUES (?, ?, ?, 'pending', 0, 5, NOW(), NOW())
            ");
            $stmtInsert->execute([$orderId, $eventType, $idempotencyKey]);
            $newId = (int)$pdo->lastInsertId();

            return [
                'success' => true,
                'already_queued' => false,
                'queue_id' => $newId,
                'status' => self::STATUS_PENDING
            ];
        } catch (Throwable $e) {
            error_log("TelegramQueueService::enqueue Error for Order #{$orderId}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Atomically claim pending jobs for processing by worker.
     *
     * @param int $limit Maximum batch size (default: 5)
     * @return array Array of claimed job rows
     */
    public static function claimNextJobs(int $limit = 5): array {
        $pdo = Database::getConnection();
        $limit = max(1, min(50, $limit));

        try {
            $pdo->beginTransaction();

            // Select pending jobs that are eligible for attempt
            $stmtSelect = $pdo->prepare("
                SELECT `id` FROM `telegram_notification_queue`
                WHERE `status` = 'pending' AND `next_attempt_at` <= NOW()
                ORDER BY `id` ASC
                LIMIT {$limit}
                FOR UPDATE
            ");
            $stmtSelect->execute();
            $ids = $stmtSelect->fetchAll(PDO::FETCH_COLUMN);

            if (empty($ids)) {
                $pdo->commit();
                return [];
            }

            // Mark as processing and record locked_at timestamp
            $inClause = implode(',', array_map('intval', $ids));
            $pdo->exec("
                UPDATE `telegram_notification_queue`
                SET `status` = 'processing', `locked_at` = NOW()
                WHERE `id` IN ({$inClause})
            ");

            $pdo->commit();

            // Fetch complete records for the claimed jobs
            $stmtFetch = $pdo->prepare("
                SELECT * FROM `telegram_notification_queue`
                WHERE `id` IN ({$inClause})
                ORDER BY `id` ASC
            ");
            $stmtFetch->execute();
            return $stmtFetch->fetchAll(PDO::FETCH_ASSOC);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("TelegramQueueService::claimNextJobs Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Process a single claimed job.
     *
     * @param array $job Queue row
     * @return array Result details
     */
    public static function processJob(array $job): array {
        $jobId = (int)$job['id'];
        $orderId = (int)$job['order_id'];
        $eventType = $job['event_type'] ?? 'order_placed';
        $attempts = (int)($job['attempts'] ?? 0) + 1;
        $maxAttempts = (int)($job['max_attempts'] ?? 5);

        $pdo = Database::getConnection();

        // 1. Verify Telegram integration is enabled and configured
        if (!TelegramService::isEnabled()) {
            // Keep in queue, delay next attempt by 2 minutes until admin turns it on
            $stmtDelay = $pdo->prepare("
                UPDATE `telegram_notification_queue`
                SET `status` = 'pending',
                    `locked_at` = NULL,
                    `next_attempt_at` = DATE_ADD(NOW(), INTERVAL 2 MINUTE),
                    `last_error` = 'Telegram notifications are disabled in store settings'
                WHERE `id` = ?
            ");
            $stmtDelay->execute([$jobId]);
            return ['success' => false, 'skipped' => true, 'error' => 'Telegram disabled in settings'];
        }

        $botToken = TelegramService::getBotToken();
        $chatId = TelegramService::getChatId();

        if (empty($botToken) || empty($chatId) || TelegramService::isPlaceholderToken($botToken)) {
            $stmtDelay = $pdo->prepare("
                UPDATE `telegram_notification_queue`
                SET `status` = 'pending',
                    `locked_at` = NULL,
                    `next_attempt_at` = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
                    `last_error` = 'Telegram Bot Token or Chat ID not configured in admin settings'
                WHERE `id` = ?
            ");
            $stmtDelay->execute([$jobId]);
            return ['success' => false, 'skipped' => true, 'error' => 'Bot Token or Chat ID missing or placeholder'];
        }

        // 2. Fetch Order Data
        $order = Order::getById($orderId);
        if (!$order) {
            // Order was deleted or invalid - mark permanently failed
            $stmtFail = $pdo->prepare("
                UPDATE `telegram_notification_queue`
                SET `status` = 'failed',
                    `attempts` = ?,
                    `locked_at` = NULL,
                    `last_error` = 'Order record not found in database'
                WHERE `id` = ?
            ");
            $stmtFail->execute([$attempts, $jobId]);
            return ['success' => false, 'error' => 'Order not found'];
        }

        $items = Order::getItems($orderId);

        // 3. Dispatch Notification via TelegramService
        $result = TelegramService::sendOrderNotification($order, $items, $eventType);

        // 4. Handle Telegram response
        if (!empty($result['success'])) {
            if (!empty($result['skipped'])) {
                // Event setting skipped (e.g. COD alerts turned off in telegram toggles)
                $note = $result['message'] ?? 'Notification skipped by filter';
                $stmtSkip = $pdo->prepare("
                    UPDATE `telegram_notification_queue`
                    SET `status` = 'sent',
                        `sent_at` = NOW(),
                        `locked_at` = NULL,
                        `last_error` = ?
                    WHERE `id` = ?
                ");
                $stmtSkip->execute([$note, $jobId]);
                return ['success' => true, 'skipped' => true];
            }

            // Real Telegram API success
            $msgId = null;
            if (!empty($result['data']['message_id'])) {
                $msgId = (string)$result['data']['message_id'];
            }

            $stmtSuccess = $pdo->prepare("
                UPDATE `telegram_notification_queue`
                SET `status` = 'sent',
                    `sent_at` = NOW(),
                    `telegram_message_id` = ?,
                    `locked_at` = NULL,
                    `last_error` = NULL,
                    `attempts` = ?
                WHERE `id` = ?
            ");
            $stmtSuccess->execute([$msgId, $attempts, $jobId]);

            // Increment processed count setting
            try {
                $currCount = (int)Setting::get('telegram_worker_processed_count', '0');
                Setting::set('telegram_worker_processed_count', (string)($currCount + 1));
            } catch (Throwable $e) {
                // Non-fatal
            }

            return ['success' => true, 'telegram_message_id' => $msgId];
        }

        // 5. Telegram delivery failed -> Handle Retry / Exponential Backoff
        $errorMsg = $result['error'] ?? 'Unknown Telegram delivery failure';

        // Bad credentials cannot succeed on retry - fail immediately and alert admin
        if (!empty($result['http_code']) && ($result['http_code'] === 401 || $result['http_code'] === 404)) {
            $stmtMaxFail = $pdo->prepare("
                UPDATE `telegram_notification_queue`
                SET `status` = 'failed',
                    `attempts` = ?,
                    `locked_at` = NULL,
                    `last_error` = ?
                WHERE `id` = ?
            ");
            $stmtMaxFail->execute([$attempts, $errorMsg, $jobId]);
            return ['success' => false, 'failed_permanently' => true, 'error' => $errorMsg];
        }

        if ($attempts >= $maxAttempts) {
            // Reached max attempts - mark as permanently failed
            $stmtMaxFail = $pdo->prepare("
                UPDATE `telegram_notification_queue`
                SET `status` = 'failed',
                    `attempts` = ?,
                    `locked_at` = NULL,
                    `last_error` = ?
                WHERE `id` = ?
            ");
            $stmtMaxFail->execute([$attempts, $errorMsg, $jobId]);
            return ['success' => false, 'failed_permanently' => true, 'error' => $errorMsg];
        }

        // Exponential backoff: attempt 1: 30s, attempt 2: 60s, attempt 3: 120s, attempt 4: 240s
        $backoffSeconds = min(600, (int)pow(2, $attempts - 1) * 30);

        $stmtRetry = $pdo->prepare("
            UPDATE `telegram_notification_queue`
            SET `status` = 'pending',
                `attempts` = ?,
                `locked_at` = NULL,
                `next_attempt_at` = DATE_ADD(NOW(), INTERVAL ? SECOND),
                `last_error` = ?
            WHERE `id` = ?
        ");
        $stmtRetry->execute([$attempts, $backoffSeconds, $errorMsg, $jobId]);

        return ['success' => false, 'retry_in_seconds' => $backoffSeconds, 'error' => $errorMsg];
    }

    /**
     * Recover zombie jobs stuck in 'processing' state (e.g. from crashed/killed workers).
     *
     * @param int $timeoutMinutes Minutes after which locked job is considered stuck (default: 5)
     * @return int Number of recovered jobs
     */
    public static function recoverStuckJobs(int $timeoutMinutes = 5): int {
        try {
            $pdo = Database::getConnection();
            $timeoutMinutes = max(1, $timeoutMinutes);
            $stmt = $pdo->prepare("
                UPDATE `telegram_notification_queue`
                SET `status` = 'pending',
                    `locked_at` = NULL,
                    `last_error` = CONCAT(COALESCE(`last_error`, ''), ' [Recovered from zombie processing state]')
                WHERE `status` = 'processing'
                  AND `locked_at` IS NOT NULL
                  AND `locked_at` < DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ");
            $stmt->execute([$timeoutMinutes]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log("TelegramQueueService::recoverStuckJobs Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Process a batch of pending notifications.
     * Used by the CLI worker and on-demand admin trigger.
     *
     * @param int $batchSize
     * @return array Summary of processed batch
     */
    public static function processBatch(int $batchSize = 10): array {
        self::updateHeartbeat('running');

        // Recover any jobs stuck in processing > 5 minutes
        $recovered = self::recoverStuckJobs(5);

        // Claim next eligible batch
        $jobs = self::claimNextJobs($batchSize);

        $summary = [
            'recovered' => $recovered,
            'claimed' => count($jobs),
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'retrying' => 0
        ];

        foreach ($jobs as $job) {
            $res = self::processJob($job);
            if (!empty($res['success'])) {
                if (!empty($res['skipped'])) {
                    $summary['skipped']++;
                } else {
                    $summary['sent']++;
                }
            } else {
                if (!empty($res['failed_permanently'])) {
                    $summary['failed']++;
                } else {
                    $summary['retrying']++;
                }
            }
        }

        self::updateHeartbeat('idle');
        return $summary;
    }

    /**
     * Retry all failed jobs by resetting their status to pending.
     *
     * @return int Count of re-enqueued jobs
     */
    public static function retryFailedJobs(): int {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                UPDATE `telegram_notification_queue`
                SET `status` = 'pending',
                    `attempts` = 0,
                    `locked_at` = NULL,
                    `next_attempt_at` = NOW()
                WHERE `status` = 'failed'
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log("TelegramQueueService::retryFailedJobs Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update worker heartbeat and status in database.
     *
     * @param string $status 'running', 'idle', 'stopped'
     */
    public static function updateHeartbeat(string $status = 'running'): void {
        try {
            $now = date('Y-m-d H:i:s');
            Setting::set('telegram_worker_last_heartbeat', $now);
            Setting::set('telegram_worker_status', $status);
        } catch (Throwable $e) {
            // Ignore DB errors during heartbeat update
        }
    }

    /**
     * Record server-side Cron execution heartbeat and execution metrics.
     *
     * @param array $summary Batch execution summary
     */
    public static function recordCronExecution(array $summary = []): void {
        try {
            $now = date('Y-m-d H:i:s');
            Setting::set('telegram_cron_last_execution_at', $now);
            Setting::set('telegram_cron_last_status', 'active');
            
            $claimed = $summary['claimed'] ?? 0;
            $sent = $summary['sent'] ?? 0;
            $failed = $summary['failed'] ?? 0;
            $retrying = $summary['retrying'] ?? 0;
            $skipped = $summary['skipped'] ?? 0;
            $summaryText = "{$claimed} processed ({$sent} delivered, {$retrying} scheduled, {$failed} failed, {$skipped} skipped)";
            Setting::set('telegram_cron_last_summary', $summaryText);

            $runs = (int)Setting::get('telegram_cron_total_runs', '0') + 1;
            Setting::set('telegram_cron_total_runs', (string)$runs);

            if ($sent > 0) {
                Setting::set('telegram_last_delivery_at', $now);
            }
        } catch (Throwable $e) {
            error_log("Failed to record Telegram cron execution: " . $e->getMessage());
        }
    }

    /**
     * Get queue telemetry and cron health statistics for admin monitor.
     *
     * @return array
     */
    public static function getQueueStats(): array {
        $pdo = Database::getConnection();

        $stats = [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
            'cron_status' => 'inactive',
            'is_cron_active' => false,
            'cron_last_execution_at' => '',
            'seconds_since_cron' => 999999,
            'cron_summary' => '',
            'cron_total_runs' => 0,
            'last_delivery_at' => '',
            'worker_status' => 'stopped',
            'worker_heartbeat' => '',
            'seconds_since_heartbeat' => 999999,
            'is_worker_alive' => false,
            'total_processed_count' => (int)Setting::get('telegram_worker_processed_count', '0'),
            'php_binary' => (defined('PHP_BINARY') && PHP_BINARY) ? PHP_BINARY : '/usr/bin/php',
            'project_root' => defined('ROOT_PATH') ? ROOT_PATH : realpath(__DIR__ . '/../../'),
            'recent_jobs' => []
        ];

        try {
            // Count by status
            $stmtCounts = $pdo->query("
                SELECT `status`, COUNT(*) as cnt
                FROM `telegram_notification_queue`
                GROUP BY `status`
            ");
            while ($row = $stmtCounts->fetch(PDO::FETCH_ASSOC)) {
                $status = $row['status'];
                $count = (int)$row['cnt'];
                if (isset($stats[$status])) {
                    $stats[$status] = $count;
                }
                $stats['total'] += $count;
            }

            // Cron & worker telemetry analysis - direct query for zero-staleness real-time stats
            $stmtCron = $pdo->query("
                SELECT `key_name`, `value` FROM `settings` 
                WHERE `key_name` IN (
                    'telegram_cron_last_execution_at',
                    'telegram_cron_last_status',
                    'telegram_cron_last_summary',
                    'telegram_cron_total_runs',
                    'telegram_last_delivery_at',
                    'telegram_worker_last_heartbeat',
                    'telegram_worker_status'
                )
            ");
            $meta = $stmtCron->fetchAll(PDO::FETCH_KEY_PAIR);

            $cronLast = $meta['telegram_cron_last_execution_at'] ?? '';
            $stats['cron_last_execution_at'] = $cronLast;
            $stats['cron_summary'] = $meta['telegram_cron_last_summary'] ?? '';
            $stats['cron_total_runs'] = (int)($meta['telegram_cron_total_runs'] ?? 0);
            $stats['last_delivery_at'] = $meta['telegram_last_delivery_at'] ?? '';

            if (!empty($cronLast)) {
                $diff = time() - strtotime($cronLast);
                $stats['seconds_since_cron'] = max(0, $diff);

                // Verified Active ONLY if cron has executed within last 75 seconds (for 30s schedule)
                if ($diff <= 75) {
                    $stats['cron_status'] = 'active';
                    $stats['is_cron_active'] = true;
                } elseif ($diff <= 180) {
                    $stats['cron_status'] = 'delayed';
                    $stats['is_cron_active'] = false;
                } else {
                    $stats['cron_status'] = 'inactive';
                    $stats['is_cron_active'] = false;
                }
            }

            $workerHeartbeat = $meta['telegram_worker_last_heartbeat'] ?? '';
            $stats['worker_heartbeat'] = $workerHeartbeat;
            $stats['worker_status'] = $meta['telegram_worker_status'] ?? 'stopped';
            if (!empty($workerHeartbeat)) {
                $diffWorker = time() - strtotime($workerHeartbeat);
                $stats['seconds_since_heartbeat'] = max(0, $diffWorker);
                $stats['is_worker_alive'] = ($diffWorker <= 90);
            }

            // Recent 5 queue entries
            $stmtRecent = $pdo->query("
                SELECT q.*, o.order_number, o.customer_name, o.total_amount, o.payment_method
                FROM `telegram_notification_queue` q
                LEFT JOIN `orders` o ON q.order_id = o.id
                ORDER BY q.id DESC
                LIMIT 5
            ");
            $stats['recent_jobs'] = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

        } catch (Throwable $e) {
            error_log("TelegramQueueService::getQueueStats Error: " . $e->getMessage());
        }

        return $stats;
    }
}
