-- MySQL Database Schema for N.A Fresh Fruits & Coconuts
-- Production-ready, fully normalized, strict constraints, transactions-ready

-- 1. Admin Users Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'superadmin',
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Login Attempts Table (Security Throttling)
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Settings Table (Store & Delivery Settings)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_name` VARCHAR(60) NOT NULL UNIQUE,
    `value` TEXT NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Products Table
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `tagline` VARCHAR(255) NOT NULL DEFAULT 'Pure • Natural • Healthy',
    `description` TEXT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 40.00,
    `original_price` DECIMAL(10,2) NULL DEFAULT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 100,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `has_cutting_options` TINYINT(1) NOT NULL DEFAULT 0,
    `cutting_options_json` TEXT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `image_url` VARCHAR(255) NOT NULL,
    `gallery_json` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_availability` (`is_available`),
    INDEX `idx_slug` (`slug`),
    INDEX `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Customers Table
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NULL,
    `password_hash` VARCHAR(255) NULL,
    `address` TEXT NULL,
    `city` VARCHAR(80) NULL,
    `state` VARCHAR(80) NULL,
    `pincode` VARCHAR(10) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_phone` (`phone`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) NOT NULL UNIQUE,
    `customer_id` INT UNSIGNED NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `fulfilment_method` ENUM('delivery', 'pickup') NOT NULL DEFAULT 'delivery',
    `coupon_id` INT UNSIGNED NULL,
    `coupon_code` VARCHAR(50) NULL,
    `coupon_type` VARCHAR(20) NULL,
    `delivery_address` TEXT NULL,
    `delivery_area_id` INT NULL,
    `delivery_area_name` VARCHAR(150) NULL,
    `latitude` DECIMAL(10,8) NULL,
    `longitude` DECIMAL(11,8) NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `original_delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('cod', 'online') NOT NULL DEFAULT 'cod',
    `payment_status` ENUM('pending', 'authorized', 'captured', 'paid', 'refunded', 'partially_refunded', 'failed') NOT NULL DEFAULT 'pending',
    `razorpay_order_id` VARCHAR(60) NULL,
    `razorpay_payment_id` VARCHAR(60) NULL,
    `razorpay_signature` VARCHAR(255) NULL,
    `order_status` ENUM('pending', 'confirmed', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    `delivery_boy_id` INT UNSIGNED NULL DEFAULT NULL,
    `delivery_workflow_status` ENUM('unassigned', 'accepted', 'reached_shop', 'picked_up', 'out_for_delivery', 'delivered', 'cancelled') NOT NULL DEFAULT 'unassigned',
    `delivery_assigned_at` DATETIME NULL DEFAULT NULL,
    `delivery_accepted_at` DATETIME NULL DEFAULT NULL,
    `delivery_picked_up_at` DATETIME NULL DEFAULT NULL,
    `delivery_completed_at` DATETIME NULL DEFAULT NULL,
    `delivery_escalated` TINYINT(1) NOT NULL DEFAULT 0,
    `delivery_escalated_at` DATETIME NULL DEFAULT NULL,
    `tracking_hash` VARCHAR(64) NOT NULL UNIQUE,
    `estimated_delivery` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `idempotency_key` VARCHAR(64) UNIQUE NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_order_num` (`order_number`),
    INDEX `idx_status` (`order_status`),
    INDEX `idx_orders_delivery_boy_id` (`delivery_boy_id`),
    INDEX `idx_customer_phone` (`customer_phone`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_tracking_hash` (`tracking_hash`),
    INDEX `idx_rzp_order` (`razorpay_order_id`),
    INDEX `idx_rzp_payment` (`razorpay_payment_id`),
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Order Items Table (Stores immutable historical price and product snapshot)
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `preparation_option` VARCHAR(150) NULL DEFAULT NULL,
    `preparation_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `is_combo` TINYINT(1) NOT NULL DEFAULT 0,
    `combo_id` INT UNSIGNED NULL DEFAULT NULL,
    `combo_details_json` TEXT NULL DEFAULT NULL,
    `item_notes` VARCHAR(255) NULL DEFAULT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Order Status History Table
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `old_status` VARCHAR(30) NULL,
    `new_status` VARCHAR(30) NOT NULL,
    `comment` VARCHAR(255) NULL,
    `changed_by` VARCHAR(50) NOT NULL DEFAULT 'System',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. OTP / Verification Table (Rate-limited, hash-stored)
CREATE TABLE IF NOT EXISTS `otp_verifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(20) NOT NULL,
    `otp_code_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `verified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_phone_expiry` (`phone`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Customer Login Attempts Table (Security Throttling)
CREATE TABLE IF NOT EXISTS `customer_login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_ident_time` (`identifier`, `attempted_at`),
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Coupons Table
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `type` ENUM('percentage', 'fixed', 'free_delivery') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_order_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_discount_amount` DECIMAL(10,2) NULL,
    `start_date` DATETIME NULL,
    `expiry_date` DATETIME NULL,
    `max_uses` INT UNSIGNED NULL,
    `current_uses` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_uses_per_customer` INT UNSIGNED NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_coupon_code` (`code`),
    INDEX `idx_coupon_active` (`is_active`),
    INDEX `idx_coupon_dates` (`start_date`, `expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Coupon Usage Audit Table
CREATE TABLE IF NOT EXISTS `coupon_usage` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `coupon_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cu_coupon` (`coupon_id`),
    INDEX `idx_cu_order` (`order_id`),
    INDEX `idx_cu_customer` (`customer_id`),
    INDEX `idx_cu_phone` (`customer_phone`),
    CONSTRAINT `fk_cu_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cu_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cu_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Payment Transactions Table
CREATE TABLE IF NOT EXISTS `payment_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `order_number` VARCHAR(30) NOT NULL,
    `gateway` VARCHAR(30) NOT NULL DEFAULT 'razorpay',
    `gateway_order_id` VARCHAR(60) NOT NULL,
    `gateway_payment_id` VARCHAR(60) NULL,
    `gateway_signature` VARCHAR(255) NULL,
    `payment_attempt_ref` VARCHAR(64) NOT NULL UNIQUE,
    `amount_paise` BIGINT UNSIGNED NOT NULL,
    `amount_rupees` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
    `status` ENUM('initiated', 'authorized', 'captured', 'failed', 'refunded', 'partially_refunded') NOT NULL DEFAULT 'initiated',
    `signature_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `environment` ENUM('test', 'live') NOT NULL DEFAULT 'test',
    `payment_method_details` VARCHAR(100) NULL,
    `error_code` VARCHAR(60) NULL,
    `error_description` TEXT NULL,
    `raw_response_json` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pt_order` (`order_id`),
    INDEX `idx_pt_order_num` (`order_number`),
    INDEX `idx_pt_gateway_order` (`gateway_order_id`),
    INDEX `idx_pt_gateway_payment` (`gateway_payment_id`),
    INDEX `idx_pt_status` (`status`),
    INDEX `idx_pt_created_at` (`created_at`),
    CONSTRAINT `fk_pt_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Payment Webhook Events Table
CREATE TABLE IF NOT EXISTS `payment_webhook_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` VARCHAR(100) NOT NULL UNIQUE,
    `gateway` VARCHAR(30) NOT NULL DEFAULT 'razorpay',
    `event_type` VARCHAR(80) NOT NULL,
    `entity_id` VARCHAR(80) NULL,
    `status` ENUM('received', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'received',
    `payload_json` MEDIUMTEXT NOT NULL,
    `error_message` TEXT NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME NULL,
    INDEX `idx_pwe_event` (`event_id`),
    INDEX `idx_pwe_type` (`event_type`),
    INDEX `idx_pwe_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Delivery Areas Table
CREATE TABLE IF NOT EXISTS `delivery_areas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) UNIQUE,
    `delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 40.00,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    `boundary_geojson` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_delivery_areas_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Invoices Table
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(40) NOT NULL UNIQUE,
    `order_id` INT UNSIGNED NOT NULL UNIQUE,
    `customer_id` INT UNSIGNED NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `customer_email` VARCHAR(100) NULL,
    `delivery_address` TEXT NULL,
    `delivery_area_name` VARCHAR(150) NULL,
    `invoice_date` DATETIME NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(20) NOT NULL,
    `payment_status` VARCHAR(40) NOT NULL,
    `payment_reference` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_inv_number` (`invoice_number`),
    INDEX `idx_inv_order` (`order_id`),
    INDEX `idx_inv_customer` (`customer_id`),
    INDEX `idx_inv_phone` (`customer_phone`),
    INDEX `idx_inv_date` (`invoice_date`),
    INDEX `idx_inv_pay_status` (`payment_status`),
    CONSTRAINT `fk_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Invoice Sequences Table
CREATE TABLE IF NOT EXISTS `invoice_sequences` (
    `sequence_year` INT NOT NULL PRIMARY KEY,
    `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 19. Product Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Combo Offers & Bundles Table
CREATE TABLE IF NOT EXISTS `combos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `tagline` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `original_price` DECIMAL(10,2) NOT NULL,
    `image_url` VARCHAR(255) NOT NULL DEFAULT '/assets/images/hero-coconut.png',
    `stock_quantity` INT NOT NULL DEFAULT 50,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `start_date` DATETIME NULL,
    `end_date` DATETIME NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_combos_avail` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Combo Child Items Table
CREATE TABLE IF NOT EXISTS `combo_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `combo_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `notes` VARCHAR(100) NULL,
    INDEX `idx_combo_id` (`combo_id`),
    INDEX `idx_product_id` (`product_id`),
    CONSTRAINT `fk_ci_combo` FOREIGN KEY (`combo_id`) REFERENCES `combos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 22. Blinkit-Style Delivery Boy System Tables
-- ====================================================================

-- 22.1 Delivery Boys Accounts Table
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

-- 22.2 Order Assignments & Atomic Acceptance Table
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

-- 22.3 Delivery Detailed Timeline & Status Audit Table
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

-- 22.4 Delivery Boy Live GPS Location History Table
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

-- 22.5 Cash On Delivery (COD) Collections & Admin Reconciliation Table
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

-- 22.6 Customer Delivery OTP Verifications Table
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

-- 22.7 Razorpay Dynamic UPI QR Records Table
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




