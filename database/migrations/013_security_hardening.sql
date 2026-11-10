-- Migration 013: Security Hardening & Audit Infrastructure
-- N.A Fresh Fruits & Coconuts

-- 1. Add TOTP Two-Factor Authentication columns to admins table
ALTER TABLE `admins` 
    ADD COLUMN IF NOT EXISTS `two_factor_secret` VARCHAR(64) NULL AFTER `role`,
    ADD COLUMN IF NOT EXISTS `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `two_factor_secret`,
    ADD COLUMN IF NOT EXISTS `failed_attempts` INT NOT NULL DEFAULT 0 AFTER `two_factor_enabled`,
    ADD COLUMN IF NOT EXISTS `lockout_until` DATETIME NULL AFTER `failed_attempts`;

-- 2. Admin Activity Audit Logs Table
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL,
    `admin_username` VARCHAR(50) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` VARCHAR(50) NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_act_admin` (`admin_id`),
    INDEX `idx_act_action` (`action`),
    INDEX `idx_act_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Security Hardening Settings
INSERT IGNORE INTO `settings` (`key_name`, `value`, `description`) VALUES
('admin_session_timeout_minutes', '30', 'Admin inactivity session timeout in minutes'),
('admin_session_absolute_hours', '8', 'Admin absolute maximum session lifetime in hours'),
('admin_2fa_enforced', '0', 'Enforce Two-Factor Authentication for all Admin users'),
('security_max_login_attempts', '5', 'Max failed login attempts before temporary lockout'),
('security_lockout_minutes', '15', 'Temporary lockout duration in minutes');
