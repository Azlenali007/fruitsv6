-- Migration 009: Multi-Product E-commerce, Cutting Options, Categories, and Combo Bundles
-- N.A Fresh Fruits & Coconuts

-- 1. Product Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Initial Categories
INSERT IGNORE INTO `categories` (`id`, `name`, `slug`, `description`, `sort_order`, `is_active`) VALUES
(1, 'Fresh Coconuts', 'fresh-coconuts', 'Farm-fresh tender and malai coconuts harvested daily', 1, 1),
(2, 'Coconut Delights', 'coconut-delights', 'Pure thick malai scoops, malai bars and authentic coconut preparations', 2, 1),
(3, 'Fresh Fruit Juices', 'fresh-fruit-juices', '100% natural, cold-pressed and freshly squeezed fruit juices', 3, 1),
(4, 'Fresh Fruits', 'fresh-fruits', 'Seasonal hand-picked farm fruits and bowls', 4, 1),
(5, 'Combos & Offers', 'combos-offers', 'Value-packed coconut and juice family bundles with big savings', 5, 1);

-- 2. Upgrade Products Table with Category, MRP (Original Price), and Cutting Options
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `original_price` DECIMAL(10,2) NULL DEFAULT NULL AFTER `price`,
    ADD COLUMN IF NOT EXISTS `has_cutting_options` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_available`,
    ADD COLUMN IF NOT EXISTS `cutting_options_json` TEXT NULL AFTER `has_cutting_options`,
    ADD COLUMN IF NOT EXISTS `is_featured` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cutting_options_json`,
    ADD COLUMN IF NOT EXISTS `sort_order` INT NOT NULL DEFAULT 0 AFTER `is_featured`;

-- Add Index if not exists
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_name = 'products' AND index_name = 'idx_category_id' AND table_schema = DATABASE());
SET @sqlstmt := IF(@exist > 0, 'DO 0', 'ALTER TABLE `products` ADD INDEX `idx_category_id` (`category_id`)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Combo Offers / Product Bundles Tables
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

CREATE TABLE IF NOT EXISTS `combo_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `combo_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `notes` VARCHAR(100) NULL,
    INDEX `idx_combo_id` (`combo_id`),
    INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Upgrade Order Items to Store Variant, Coconut Cutting Preference, and Combo Details
ALTER TABLE `order_items`
    ADD COLUMN IF NOT EXISTS `preparation_option` VARCHAR(150) NULL DEFAULT NULL AFTER `product_name`,
    ADD COLUMN IF NOT EXISTS `preparation_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `preparation_option`,
    ADD COLUMN IF NOT EXISTS `is_combo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `preparation_charge`,
    ADD COLUMN IF NOT EXISTS `combo_id` INT UNSIGNED NULL DEFAULT NULL AFTER `is_combo`,
    ADD COLUMN IF NOT EXISTS `combo_details_json` TEXT NULL DEFAULT NULL AFTER `combo_id`,
    ADD COLUMN IF NOT EXISTS `item_notes` VARCHAR(255) NULL DEFAULT NULL AFTER `combo_details_json`;

-- 5. Seed / Update Catalog Products
-- Update Product #1 (Fresh Coconut Water)
UPDATE `products` SET
    `category_id` = 1,
    `original_price` = 50.00,
    `price` = 40.00,
    `has_cutting_options` = 0,
    `cutting_options_json` = NULL,
    `sort_order` = 1
WHERE `id` = 1;

-- Insert other products requested by user
INSERT IGNORE INTO `products` (`id`, `category_id`, `name`, `slug`, `tagline`, `description`, `price`, `original_price`, `stock_quantity`, `is_available`, `has_cutting_options`, `cutting_options_json`, `image_url`, `gallery_json`, `sort_order`) VALUES
(2, 1, 'Tender Coconut', 'tender-coconut', 'Sweet Water & Soft Creamy Flesh', 'Selected natural green tender coconut directly sourced from coastal groves. Rich in natural electrolytes, potassium, and magnesium.', 55.00, 65.00, 120, 1, 1, '[{"id":"whole","name":"Without Cutting — Whole Coconut","price":0},{"id":"cut","name":"With Cutting — Ready to Drink","price":0},{"id":"malai_inc","name":"Malai Included (Special Cut)","price":10}]', '/assets/images/hero-coconut.png', '["/assets/images/hero-coconut.png","/assets/images/product-main.png"]', 2),
(3, 1, 'Malai Coconut', 'malai-coconut', 'Thick Sweet Malai with Natural Water', 'Fully matured sweet coconut packed with soft, rich, scoopable malai. Highly nutritious and wonderfully satisfying.', 60.00, 70.00, 95, 1, 1, '[{"id":"whole","name":"Without Cutting — Whole Coconut","price":0},{"id":"cut","name":"With Cutting — Ready to Drink & Scoop","price":0}]', '/assets/images/product-main.png', '["/assets/images/product-main.png","/assets/images/cart-coconut.png"]', 3),
(4, 2, 'Thick Malai / Coconut Malai Bowl', 'thick-coconut-malai-bowl', '100% Pure Fresh Coconut Malai', 'Hand-scooped fresh coconut malai bowl. Velvety smooth, natural sweetness, no dairy or artificial thickeners added.', 70.00, 85.00, 60, 1, 0, NULL, '/assets/images/cart-coconut.png', '["/assets/images/cart-coconut.png"]', 4),
(5, 2, 'Coconut Malai Bar', 'coconut-malai-bar', 'Pure Coconut Pulp & Natural Milk', 'Delicious artisanal cold bar made with pure condensed coconut malai, cardamom, and natural cane sugar. 100% vegetarian.', 50.00, 60.00, 80, 1, 0, NULL, '/assets/images/thumb-1.png', '["/assets/images/thumb-1.png"]', 5),
(6, 3, 'Fresh Fruit Juice (Cold-Pressed)', 'fresh-fruit-juice', '100% Pure Fruit Juice • Zero Water Added', 'Freshly pressed seasonal fruit juice bursting with vital vitamins and natural antioxidants. Prepared fresh upon order confirmation.', 60.00, 75.00, 75, 1, 0, NULL, '/assets/images/thumb-2.png', '["/assets/images/thumb-2.png"]', 6),
(7, 4, 'Seasonal Fresh Fruit Bowl', 'seasonal-fresh-fruit-bowl', 'Hand-picked Papaya, Pomegranate & Kiwi', 'Cleanly peeled, hygienically chopped seasonal fresh fruit assortment packed in a sealed food-grade bowl.', 80.00, 99.00, 50, 1, 0, NULL, '/assets/images/thumb-3.png', '["/assets/images/thumb-3.png"]', 7);

-- 6. Seed Initial Combos & Bundles
INSERT IGNORE INTO `combos` (`id`, `name`, `slug`, `tagline`, `description`, `price`, `original_price`, `image_url`, `stock_quantity`, `is_available`, `sort_order`) VALUES
(1, 'Coconut Water + Malai Coconut Duo', 'coconut-water-malai-duo', 'Hydration + Rich Malai', 'Get 1 bottle of Pure Coconut Water plus 1 Malai Coconut with free cutting service included. Perfect power combo!', 90.00, 100.00, '/assets/images/hero-coconut.png', 40, 1, 1),
(2, 'Family Hydration Pack (2 Water + 1 Malai)', 'family-hydration-pack', 'Complete Family Refreshment', 'Save ₹25 with 2 chilled Coconut Waters and 1 fresh Malai Coconut. Hand-picked for the whole family.', 125.00, 140.00, '/assets/images/product-main.png', 30, 1, 2),
(3, 'Vitality Juice & Coconut Combo', 'vitality-juice-coconut-combo', 'Pure Energy & Natural Electrolytes', '1 Fresh Cold-Pressed Fruit Juice + 1 Fresh Coconut Water. Beat the heat naturally with zero added sugar.', 90.00, 100.00, '/assets/images/cart-coconut.png', 35, 1, 3);

-- Seed Combo Items
INSERT IGNORE INTO `combo_items` (`combo_id`, `product_id`, `quantity`, `notes`) VALUES
(1, 1, 1, '1x Fresh Coconut Water'),
(1, 3, 1, '1x Malai Coconut'),
(2, 1, 2, '2x Fresh Coconut Water'),
(2, 3, 1, '1x Malai Coconut'),
(3, 1, 1, '1x Fresh Coconut Water'),
(3, 6, 1, '1x Fresh Fruit Juice');
