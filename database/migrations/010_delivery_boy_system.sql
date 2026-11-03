-- ====================================================================
-- MIGRATION: 010_delivery_boy_system.sql
-- Blinkit-Style Delivery Boy Management, Atomic Dispatch & Live GPS
-- ====================================================================

-- 1. Delivery Boys Accounts Table
CREATE TABLE IF NOT EXISTS `delivery_boys` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL UNIQUE,
    `login_id` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `is_online` TINYINT(1) NOT NULL DEFAULT 0,
    `current_lat` DECIMAL(10, 8) NULL,
    `current_lng` DECIMAL(11, 8) NULL,
    `last_location_updated_at` DATETIME NULL,
    `vehicle_type` VARCHAR(50) NOT NULL DEFAULT 'Bike / Scooter',
    `vehicle_number` VARCHAR(50) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_rider_status_online` (`status`, `is_online`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add Delivery Workflow Columns to Orders Table (Idempotent)
SET @dbname = DATABASE();
SET @tablename = 'orders';

-- delivery_boy_id
SET @prepexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='delivery_boy_id');
SET @statement = IF(@prepexists = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_boy_id` INT UNSIGNED NULL DEFAULT NULL AFTER `order_status`, ADD INDEX `idx_orders_delivery_boy_id` (`delivery_boy_id`)', 'SELECT 1');
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- delivery_workflow_status
SET @prepexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='delivery_workflow_status');
SET @statement = IF(@prepexists = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_workflow_status` ENUM(\'unassigned\', \'accepted\', \'reached_shop\', \'picked_up\', \'out_for_delivery\', \'delivered\', \'cancelled\') NOT NULL DEFAULT \'unassigned\' AFTER `delivery_boy_id`', 'SELECT 1');
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- delivery_assigned_at
SET @prepexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='delivery_assigned_at');
SET @statement = IF(@prepexists = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_assigned_at` DATETIME NULL DEFAULT NULL AFTER `delivery_workflow_status`', 'SELECT 1');
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- delivery_accepted_at
SET @prepexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='delivery_accepted_at');
SET @statement = IF(@prepexists = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_accepted_at` DATETIME NULL DEFAULT NULL AFTER `delivery_assigned_at`', 'SELECT 1');
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- delivery_picked_up_at
SET @prepexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='delivery_picked_up_at');
SET @statement = IF(@prepexists = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_picked_up_at` DATETIME NULL DEFAULT NULL AFTER `delivery_accepted_at`', 'SELECT 1');
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- delivery_completed_at
SET @prepexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='delivery_completed_at');
SET @statement = IF(@prepexists = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_completed_at` DATETIME NULL DEFAULT NULL AFTER `delivery_picked_up_at`', 'SELECT 1');
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- delivery_escalated
SET @prepexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='delivery_escalated');
SET @statement = IF(@prepexists = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_escalated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `delivery_completed_at`', 'SELECT 1');
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- delivery_escalated_at
SET @prepexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='delivery_escalated_at');
SET @statement = IF(@prepexists = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_escalated_at` DATETIME NULL DEFAULT NULL AFTER `delivery_escalated`', 'SELECT 1');
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- 3. Order Assignments & Atomic Acceptance Table
CREATE TABLE IF NOT EXISTS `order_assignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `delivery_boy_id` INT UNSIGNED NOT NULL,
    `status` ENUM('offered', 'accepted', 'rejected', 'escalated', 'cancelled') NOT NULL DEFAULT 'offered',
    `rejection_reason` VARCHAR(255) NULL,
    `assigned_by` ENUM('auto', 'admin') NOT NULL DEFAULT 'auto',
    `offered_at` DATETIME NOT NULL,
    `responded_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_assign_order_status` (`order_id`, `status`),
    INDEX `idx_assign_rider_status` (`delivery_boy_id`, `status`),
    CONSTRAINT `fk_assign_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assign_rider` FOREIGN KEY (`delivery_boy_id`) REFERENCES `delivery_boys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Delivery Detailed Timeline & Status Audit Table
CREATE TABLE IF NOT EXISTS `delivery_timeline` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `delivery_boy_id` INT UNSIGNED NULL,
    `event` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `lat` DECIMAL(10, 8) NULL,
    `lng` DECIMAL(11, 8) NULL,
    `actor_type` ENUM('system', 'delivery_boy', 'admin', 'customer') NOT NULL DEFAULT 'delivery_boy',
    `actor_id` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_timeline_order` (`order_id`),
    CONSTRAINT `fk_timeline_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_timeline_rider` FOREIGN KEY (`delivery_boy_id`) REFERENCES `delivery_boys` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Delivery Boy Live GPS Location History Table
CREATE TABLE IF NOT EXISTS `delivery_boy_locations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `delivery_boy_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED NULL,
    `lat` DECIMAL(10, 8) NOT NULL,
    `lng` DECIMAL(11, 8) NOT NULL,
    `heading` DECIMAL(6, 2) NULL,
    `speed` DECIMAL(6, 2) NULL,
    `accuracy` DECIMAL(8, 2) NULL,
    `recorded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_loc_rider_order_time` (`delivery_boy_id`, `order_id`, `recorded_at`),
    CONSTRAINT `fk_loc_rider` FOREIGN KEY (`delivery_boy_id`) REFERENCES `delivery_boys` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_loc_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Cash On Delivery (COD) Collections & Admin Reconciliation Table
CREATE TABLE IF NOT EXISTS `delivery_cod_collections` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL UNIQUE,
    `delivery_boy_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('collected_by_rider', 'reconciled_with_admin') NOT NULL DEFAULT 'collected_by_rider',
    `collected_at` DATETIME NOT NULL,
    `reconciled_at` DATETIME NULL,
    `reconciled_by_admin_id` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cod_rider_status` (`delivery_boy_id`, `status`),
    CONSTRAINT `fk_cod_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cod_rider` FOREIGN KEY (`delivery_boy_id`) REFERENCES `delivery_boys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Customer Delivery OTP Verifications Table
CREATE TABLE IF NOT EXISTS `delivery_otp_verifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL UNIQUE,
    `otp_code_hash` VARCHAR(255) NOT NULL,
    `plain_otp_for_dev` VARCHAR(10) NULL,
    `attempts` INT NOT NULL DEFAULT 0,
    `max_attempts` INT NOT NULL DEFAULT 3,
    `expires_at` DATETIME NOT NULL,
    `verified_at` DATETIME NULL,
    `is_admin_override` TINYINT(1) NOT NULL DEFAULT 0,
    `override_reason` TEXT NULL,
    `override_admin_id` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_otp_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Razorpay Dynamic UPI QR Records Table
CREATE TABLE IF NOT EXISTS `delivery_dynamic_qrs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `razorpay_qr_id` VARCHAR(100) NULL,
    `qr_image_url` TEXT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('active', 'paid', 'expired', 'failed') NOT NULL DEFAULT 'active',
    `razorpay_payment_id` VARCHAR(100) NULL,
    `api_response_raw` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_qr_order_status` (`order_id`, `status`),
    CONSTRAINT `fk_dynamic_qr_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
