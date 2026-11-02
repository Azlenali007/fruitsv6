-- ==============================================================================
-- Migration: 010_pwa_web_push_notifications.sql
-- Project: N.A Fresh Fruits & Coconuts
-- Purpose: Remove obsolete Telegram queue & cron dependencies, and establish
--          modern PWA Web Push notification tables (subscriptions, logs, preferences, dedup).
-- ==============================================================================

-- 1. Safely drop obsolete Telegram notification queue table if present
DROP TABLE IF EXISTS `telegram_notification_queue`;

-- 2. Remove obsolete Telegram configuration settings from settings table
DELETE FROM `settings` WHERE `key_name` LIKE 'telegram_%';

-- 3. Dedicated Push Subscriptions Table
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_type` ENUM('admin', 'customer', 'guest') NOT NULL DEFAULT 'customer',
    `user_id` INT UNSIGNED NULL,
    `endpoint` TEXT NOT NULL,
    `endpoint_hash` VARCHAR(64) NOT NULL UNIQUE,
    `p256dh` TEXT NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME NULL,
    INDEX `idx_ps_user` (`user_type`, `user_id`),
    INDEX `idx_ps_hash` (`endpoint_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Notification History & Broadcast Records Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `audience` ENUM('all_customers', 'selected_customers', 'single_customer', 'admins_only') NOT NULL,
    `target_customer_id` INT UNSIGNED NULL,
    `notification_type` VARCHAR(50) NOT NULL DEFAULT 'general',
    `title` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `icon` VARCHAR(255) NULL DEFAULT '/assets/images/icons/icon-192.png',
    `image` VARCHAR(255) NULL,
    `click_url` VARCHAR(255) NOT NULL DEFAULT '/',
    `sent_by_admin_id` INT UNSIGNED NULL,
    `total_recipients` INT UNSIGNED NOT NULL DEFAULT 0,
    `success_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `failure_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_audience` (`audience`),
    INDEX `idx_notif_type` (`notification_type`),
    INDEX `idx_notif_target` (`target_customer_id`),
    INDEX `idx_notif_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Notification Deliveries Log Table
CREATE TABLE IF NOT EXISTS `notification_deliveries` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `notification_id` BIGINT UNSIGNED NOT NULL,
    `subscription_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
    `http_code` INT NULL,
    `error_message` VARCHAR(255) NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_nd_notif` (`notification_id`),
    INDEX `idx_nd_sub` (`subscription_id`),
    INDEX `idx_nd_status` (`status`),
    CONSTRAINT `fk_nd_notif` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Customer Notification Preferences Table
CREATE TABLE IF NOT EXISTS `customer_notification_preferences` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT UNSIGNED NOT NULL UNIQUE,
    `order_updates` TINYINT(1) NOT NULL DEFAULT 1,
    `promotions` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cnp_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Event Deduplication Table
CREATE TABLE IF NOT EXISTS `notification_dedup` (
    `idempotency_key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `event_type` VARCHAR(50) NOT NULL,
    `entity_id` VARCHAR(50) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ndedup_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Seed PWA Web Push Settings
INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'pwa_push_enabled', '1', 'Enable Progressive Web App push notifications'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'pwa_push_enabled');

INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'pwa_admin_order_alerts', '1', 'Send instant Web Push notifications to admin devices for new orders'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'pwa_admin_order_alerts');

INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'pwa_customer_order_alerts', '1', 'Send order progress Web Push notifications to subscribed customers'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'pwa_customer_order_alerts');
