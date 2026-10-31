-- ==============================================================================
-- Migration: 008_telegram_notification_queue.sql
-- Project: N.A Fresh Fruits & Coconuts
-- Purpose: Dedicated database-backed Telegram order notification queue
--          and background worker heartbeat/status tracking.
-- ==============================================================================

-- 1. Dedicated Telegram Notification Queue Table
CREATE TABLE IF NOT EXISTS `telegram_notification_queue` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `event_type` VARCHAR(50) NOT NULL DEFAULT 'order_placed', -- 'order_placed', 'order_paid'
    `idempotency_key` VARCHAR(100) NOT NULL UNIQUE,
    `status` ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
    `next_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `locked_at` DATETIME NULL,
    `sent_at` DATETIME NULL,
    `telegram_message_id` VARCHAR(100) NULL,
    `last_error` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tgq_status_next` (`status`, `next_attempt_at`),
    INDEX `idx_tgq_order_id` (`order_id`),
    INDEX `idx_tgq_locked` (`locked_at`),
    INDEX `idx_tgq_idempotency` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Worker Heartbeat & Operational Settings (Additive & Safe)
INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'telegram_worker_last_heartbeat', '', 'Timestamp of last background worker heartbeat pulse'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'telegram_worker_last_heartbeat');

INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'telegram_worker_status', 'idle', 'Current operational status of Telegram worker (running, idle, stopped)'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'telegram_worker_status');

INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'telegram_worker_processed_count', '0', 'Total Telegram order notifications dispatched by background worker'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'telegram_worker_processed_count');
