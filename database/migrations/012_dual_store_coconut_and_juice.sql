-- Migration 012: Dual Store Experience (Coconut Store & Juice Store)
-- N.A Fresh Fruits & Coconuts

-- 1. Add store_type to categories table if not exists
SET @exist_cat := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'store_type');
SET @sql_cat := IF(@exist_cat = 0, 'ALTER TABLE `categories` ADD COLUMN `store_type` ENUM(\'coconut\', \'juice\') NOT NULL DEFAULT \'coconut\' AFTER `slug`, ADD INDEX `idx_categories_store_type` (`store_type`)', 'SELECT 1');
PREPARE stmt_cat FROM @sql_cat;
EXECUTE stmt_cat;
DEALLOCATE PREPARE stmt_cat;

-- 2. Add store_type to products table if not exists
SET @exist_prod := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'store_type');
SET @sql_prod := IF(@exist_prod = 0, 'ALTER TABLE `products` ADD COLUMN `store_type` ENUM(\'coconut\', \'juice\') NOT NULL DEFAULT \'coconut\' AFTER `category_id`, ADD INDEX `idx_products_store_type` (`store_type`)', 'SELECT 1');
PREPARE stmt_prod FROM @sql_prod;
EXECUTE stmt_prod;
DEALLOCATE PREPARE stmt_prod;

-- 3. Add store_type to order_items table if not exists
SET @exist_oi := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'store_type');
SET @sql_oi := IF(@exist_oi = 0, 'ALTER TABLE `order_items` ADD COLUMN `store_type` ENUM(\'coconut\', \'juice\') NOT NULL DEFAULT \'coconut\' AFTER `product_name`', 'SELECT 1');
PREPARE stmt_oi FROM @sql_oi;
EXECUTE stmt_oi;
DEALLOCATE PREPARE stmt_oi;

-- 4. Add store_type to orders table if not exists
SET @exist_ord := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'store_type');
SET @sql_ord := IF(@exist_ord = 0, 'ALTER TABLE `orders` ADD COLUMN `store_type` ENUM(\'coconut\', \'juice\', \'mixed\') NOT NULL DEFAULT \'coconut\' AFTER `fulfilment_method`', 'SELECT 1');
PREPARE stmt_ord FROM @sql_ord;
EXECUTE stmt_ord;
DEALLOCATE PREPARE stmt_ord;

-- 5. Seed Juice Categories
INSERT INTO `categories` (`name`, `slug`, `store_type`, `description`, `sort_order`, `is_active`, `created_at`) VALUES
('Fresh Citrus Juices', 'fresh-citrus-juices', 'juice', 'Pure cold-extracted Sweet Lime, Orange and Citrus power', 1, 1, NOW()),
('Exotic & Berry Blends', 'exotic-berry-blends', 'juice', 'Rich ruby Pomegranate, tropical Pineapple and Berry smoothies', 2, 1, NOW()),
('Seasonal Fruit Shakes', 'seasonal-fruit-shakes', 'juice', 'Thick creamy Alphonso Mango, Banana, and Chikoo fruit shakes', 3, 1, NOW()),
('Detox & Cold-Pressed', 'detox-cold-pressed', 'juice', 'Vitalizing ABC Detox, fresh Sugarcane with ginger and Watermelon coolers', 4, 1, NOW())
ON DUPLICATE KEY UPDATE `store_type` = 'juice', `is_active` = 1;

-- 6. Seed Juice Products
-- Get Category IDs dynamically
SET @cat_citrus := (SELECT id FROM categories WHERE slug = 'fresh-citrus-juices' LIMIT 1);
SET @cat_exotic := (SELECT id FROM categories WHERE slug = 'exotic-berry-blends' LIMIT 1);
SET @cat_shakes := (SELECT id FROM categories WHERE slug = 'seasonal-fruit-shakes' LIMIT 1);
SET @cat_detox  := (SELECT id FROM categories WHERE slug = 'detox-cold-pressed' LIMIT 1);

INSERT INTO `products` (
    `category_id`, `store_type`, `name`, `slug`, `tagline`, `description`,
    `price`, `original_price`, `stock_quantity`, `is_available`,
    `has_cutting_options`, `cutting_options_json`, `is_featured`, `sort_order`, `image_url`, `created_at`
) VALUES
(
    @cat_citrus, 'juice', 'Fresh Sweet Mosambi Juice', 'fresh-sweet-mosambi-juice',
    'Pure • Sweet Lime • Cold Extracted',
    'Cold-pressed freshly upon order from premium sweet limes. Zero added water, zero preservatives. Naturally rich in Vitamin C.',
    50.00, 60.00, 150, 1,
    0, NULL, 1, 1, '/assets/images/juice/mosambi-juice.jpg', NOW()
),
(
    @cat_exotic, 'juice', 'Royal Pomegranate (Anar) Juice', 'royal-pomegranate-anar-juice',
    '100% Pure Anar • Antioxidant Rich',
    'Extracted from hand-selected ruby red pomegranates from Maharashtra farms. Thick, nutrient-dense, and refreshing.',
    90.00, 110.00, 80, 1,
    0, NULL, 1, 2, '/assets/images/juice/pomegranate-juice.jpg', NOW()
),
(
    @cat_citrus, 'juice', 'Valencia Orange Juice (Nagpur)', 'valencia-orange-juice-nagpur',
    'Zesty • Pulp Loaded • Pure Sunshine',
    'Freshly squeezed farm Nagpur oranges bursting with natural citrus oils and sweet pulp. Served chilled without synthetic sugar.',
    70.00, 85.00, 100, 1,
    0, NULL, 1, 3, '/assets/images/juice/orange-juice.jpg', NOW()
),
(
    @cat_exotic, 'juice', 'Golden Pineapple Cooler with Mint', 'golden-pineapple-cooler-mint',
    'Tropical Sweet • Fresh Mint Infused',
    'Ripe queen pineapple cold-crushed with fresh mountain pudina leaves and rock salt. Exceptionally rejuvenating.',
    60.00, 75.00, 90, 1,
    0, NULL, 0, 4, '/assets/images/juice/pineapple-juice.jpg', NOW()
),
(
    @cat_citrus, 'juice', 'Ganga Jamuna Citrus Blend', 'ganga-jamuna-citrus-blend',
    'Classic Duo • Mosambi & Orange',
    'The iconic Indian juice bar specialty: equal balance of sweet lime and Nagpur orange pressed together.',
    65.00, 80.00, 120, 1,
    0, NULL, 1, 5, '/assets/images/juice/ganga-jamuna-juice.jpg', NOW()
),
(
    @cat_shakes, 'juice', 'Fresh Alphonso Mango Shake with Malai', 'fresh-alphonso-mango-shake-malai',
    'Royal Mango • Fresh Milk Cream',
    'Thick luscious shake blended from real Alphonso mango pulp and cold milk, topped with fresh coconut malai shreds.',
    80.00, 95.00, 70, 1,
    0, NULL, 1, 6, '/assets/images/juice/mango-shake.jpg', NOW()
),
(
    @cat_detox, 'juice', 'Fresh Sugarcane Juice with Ginger & Lemon', 'fresh-sugarcane-juice-ginger-lemon',
    'Crushed Fresh • Desi Refreshment',
    'Sweet clean sugarcane stalks crushed fresh with spicy ginger, lemon zest, and black salt. Natural instant electrolyte recharge.',
    35.00, 45.00, 200, 1,
    0, NULL, 0, 7, '/assets/images/juice/sugarcane-juice.jpg', NOW()
),
(
    @cat_detox, 'juice', 'Crisp Watermelon Cooler', 'crisp-watermelon-cooler',
    'Hydrating • Sweet Red Melon',
    'Pure cold-extracted summer watermelon juice with zero seeds, chilled to perfection. Pure hydration in a glass.',
    45.00, 55.00, 110, 1,
    0, NULL, 0, 8, '/assets/images/juice/watermelon-juice.jpg', NOW()
),
(
    @cat_detox, 'juice', 'ABC Immunity Detox Juice', 'abc-immunity-detox-juice',
    'Apple • Beetroot • Carrot',
    'Doctor-recommended immunity powerhouse: fresh crunchy Apple, deep earthy Beetroot, and sweet red Carrots cold pressed together.',
    85.00, 100.00, 60, 1,
    0, NULL, 1, 9, '/assets/images/juice/abc-detox-juice.jpg', NOW()
),
(
    @cat_shakes, 'juice', 'Strawberry Banana Smoothie', 'strawberry-banana-smoothie',
    'Rich Berries • Creamy Banana',
    'Luscious Mahabaleshwar strawberries and sweet bananas blended with cold dairy into a velvety antioxidant meal shake.',
    80.00, 95.00, 50, 1,
    0, NULL, 0, 10, '/assets/images/juice/strawberry-banana-shake.jpg', NOW()
)
ON DUPLICATE KEY UPDATE `store_type` = 'juice', `is_available` = 1;

-- 7. Ensure Coconut Products are explicitly marked as coconut
UPDATE `products` SET `store_type` = 'coconut' WHERE `slug` IN (
    'fresh-coconut-water', 'tender-coconut', 'malai-coconut',
    'thick-coconut-malai-bowl', 'coconut-malai-bar', 'green-tender-coconut',
    'pudina-coconut-cooler', 'tender-coconut-malai-bowl'
);

-- 8. Add Dual Store Settings
INSERT INTO `settings` (`key_name`, `value`, `description`, `created_at`) VALUES
('juice_store_enabled', '1', 'Enable Juice Store experience and switching button', NOW()),
('store_switch_button_enabled', '1', 'Display Looking for Juice / Looking for Coconut switcher', NOW()),
('switch_to_juice_text', 'Looking for Juice?', 'Button label to switch to Juice Store', NOW()),
('switch_to_coconut_text', 'Looking for Coconut?', 'Button label to switch to Coconut Store', NOW()),
('juice_store_name', 'N.A Fresh Fruit Juices & Shakes', 'Branding title for Juice Store', NOW()),
('juice_store_tagline', '100% Pure Cold-Pressed Juices • Fresh on Order • Jaora', 'Tagline for Juice Store', NOW())
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
