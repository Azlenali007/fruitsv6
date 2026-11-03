-- ==============================================================================
-- Migration: 010_pwa_web_push_notifications.sql
-- Project: N.A Fresh Fruits & Coconuts
-- Purpose: Clean Rebuild of Web Push Notifications System
--          Supports exactly 4 categories: offers, orders, stock, news.
--          Stores push_subscriptions, notification_history, and notification_deliveries.
-- ==============================================================================

-- Drop old tables cleanly
DROP TABLE IF EXISTS `notification_deliveries`;
DROP TABLE IF EXISTS `notification_dedup`;
DROP TABLE IF EXISTS `customer_notification_preferences`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `notification_history`;
DROP TABLE IF EXISTS `push_subscriptions`;
DROP TABLE IF EXISTS `telegram_notification_queue`;

-- 1. Push Subscriptions Table
CREATE TABLE `push_subscriptions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT UNSIGNED NULL,
    `user_type` ENUM('customer', 'guest', 'admin') NOT NULL DEFAULT 'customer',
    `endpoint` TEXT NOT NULL,
    `endpoint_hash` VARCHAR(64) NOT NULL UNIQUE,
    `p256dh` TEXT NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `status` ENUM('active', 'expired', 'unsubscribed') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME NULL,
    INDEX `idx_ps_hash` (`endpoint_hash`),
    INDEX `idx_ps_customer` (`customer_id`),
    INDEX `idx_ps_status` (`status`),
    INDEX `idx_ps_user_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Notification History Table (The 4 Categories: offers, orders, stock, news)
CREATE TABLE `notification_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category` ENUM('offers', 'orders', 'stock', 'news') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `image_url` VARCHAR(255) NULL,
    `click_url` VARCHAR(255) NOT NULL DEFAULT '/',
    `target_type` ENUM('all_customers', 'single_customer', 'test') NOT NULL DEFAULT 'all_customers',
    `target_customer_id` INT UNSIGNED NULL,
    `sent_by_admin_id` INT UNSIGNED NULL,
    `success_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `failure_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_nh_category` (`category`),
    INDEX `idx_nh_created` (`created_at`),
    INDEX `idx_nh_customer` (`target_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Notification Deliveries Table
CREATE TABLE `notification_deliveries` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `notification_id` BIGINT UNSIGNED NOT NULL,
    `subscription_id` BIGINT UNSIGNED NOT NULL,
    `http_code` INT NULL,
    `status` ENUM('sent', 'failed', 'expired') NOT NULL DEFAULT 'sent',
    `error_message` VARCHAR(255) NULL,
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_nd_notif` (`notification_id`),
    INDEX `idx_nd_sub` (`subscription_id`),
    INDEX `idx_nd_status` (`status`),
    CONSTRAINT `fk_nd_history` FOREIGN KEY (`notification_id`) REFERENCES `notification_history` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Notification Settings Seed
INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'pwa_push_enabled', '1', 'Enable Progressive Web App push notifications'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'pwa_push_enabled');

INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'pwa_customer_order_alerts', '1', 'Send order progress Web Push notifications to customers'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'pwa_customer_order_alerts');
