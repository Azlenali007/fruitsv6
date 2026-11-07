-- Migration: 011_delivery_pwa_and_loyalty_rewards.sql
-- N.A Fresh Fruits & Coconuts
-- Adds Loyalty Point System, Instagram Story Rewards, Vouchers, and Rider PWA State

-- 1. Instagram Story Submissions Table
CREATE TABLE IF NOT EXISTS `loyalty_story_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `order_id` INT NOT NULL UNIQUE,
    `order_number` VARCHAR(50) NOT NULL,
    `instagram_username` VARCHAR(100) NOT NULL,
    `story_proof_path` VARCHAR(255) NOT NULL,
    `story_proof_filename` VARCHAR(255) NOT NULL,
    `coins_awarded` INT NOT NULL DEFAULT 0,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT NULL,
    `reviewed_by` INT NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_lss_customer` (`customer_id`),
    INDEX `idx_lss_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Customer Loyalty Wallets Table
CREATE TABLE IF NOT EXISTS `loyalty_wallets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL UNIQUE,
    `current_balance` INT NOT NULL DEFAULT 0,
    `total_earned` INT NOT NULL DEFAULT 0,
    `total_redeemed` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_lw_balance` (`current_balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Loyalty Transactions Auditable Ledger
CREATE TABLE IF NOT EXISTS `loyalty_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `amount` INT NOT NULL,
    `balance_after` INT NOT NULL,
    `type` ENUM('story_reward', 'voucher_redemption', 'admin_adjustment', 'order_reversal') NOT NULL,
    `reference_id` VARCHAR(100) NULL,
    `description` VARCHAR(255) NOT NULL,
    `admin_id` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_lt_cust` (`customer_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Loyalty Discount Vouchers Table
CREATE TABLE IF NOT EXISTS `loyalty_vouchers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT NOT NULL,
    `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    `coins_spent` INT NOT NULL DEFAULT 100,
    `min_order_amount` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    `max_discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 150.00,
    `is_used` TINYINT(1) NOT NULL DEFAULT 0,
    `used_order_id` INT NULL,
    `used_at` DATETIME NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_lv_code` (`code`),
    INDEX `idx_lv_cust` (`customer_id`, `is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Add default settings for loyalty & Instagram
INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('loyalty_enabled', '1', 'Enable Customer Instagram Story Loyalty Point System'),
('instagram_handle', '@nafreshfruitsjaora', 'Official Instagram profile username/handle for tags'),
('instagram_profile_url', 'https://instagram.com/nafreshfruitsjaora', 'Official Instagram link for customers to view profile'),
('loyalty_coins_per_story', '50', 'Coins awarded to customer per verified Instagram story'),
('loyalty_redemption_threshold', '100', 'Coins required to redeem a 50% discount voucher'),
('loyalty_voucher_discount_percent', '50', 'Discount percentage provided by loyalty voucher'),
('loyalty_voucher_max_discount', '150.00', 'Maximum rupee discount cap for 50% voucher'),
('loyalty_voucher_min_order', '100.00', 'Minimum order amount in rupees to apply 50% voucher'),
('loyalty_voucher_expiry_days', '30', 'Validity period in days for generated 50% vouchers'),
('loyalty_allow_resubmission', '1', 'Allow customer to resubmit story proof if rejected')
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
