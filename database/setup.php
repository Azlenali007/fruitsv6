<?php
/**
 * Database Migration and Seeder for N.A Fresh Fruits & Coconuts
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/config/config.php';

try {
    // 1. Connect without DB first to ensure DB exists
    $pdoRoot = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

    // 2. Connect to the database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // 3. Execute schema
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schemaSql);

    // Execute all migration files in order
    $migrationFiles = glob(__DIR__ . '/migrations/*.sql');
    if ($migrationFiles) {
        sort($migrationFiles);
        foreach ($migrationFiles as $mf) {
            $mSql = file_get_contents($mf);
            if (!empty($mSql)) {
                try {
                    $pdo->exec($mSql);
                } catch (Exception $e) {
                    // Ignore duplicate errors for idempotency
                }
            }
        }
    }

    // 4. Seed Settings
    $defaultSettings = [
        'store_name' => 'N.A Fresh Fruits & Coconuts',
        'store_tagline' => 'Fresh tender coconut water straight from farm',
        'store_phone' => '+91 98765 43210',
        'store_email' => 'support@nafruitsandcoconuts.com',
        'store_address' => 'Station Road, Opp. Municipal Market, Jaora, Madhya Pradesh - 457226',
        'currency_symbol' => '₹',
        'local_delivery_enabled' => '1',
        'shop_pickup_enabled' => '1',
        'delivery_charge' => '40.00',
        'min_order_amount' => '40.00',
        'free_delivery_above' => '500.00',
        'service_areas' => 'Jaora, Station Road, Municipal Market, Freeganj, Piploda Road, Choupaty, Ratlam Naka, Jaora City',
        'store_open_time' => '07:00 AM',
        'store_close_time' => '09:00 PM',
        'stock_deduction_policy' => 'order_placement',
        'whatsapp_popup_enabled' => '1',
        'whatsapp_channel_url' => 'https://whatsapp.com/channel/0029VaNAFreshFruits',
        'whatsapp_popup_heading' => 'Join Our WhatsApp Channel',
        'whatsapp_popup_message' => 'Get real-time fresh coconut harvest alerts, special seasonal discounts, and quick delivery updates straight on WhatsApp!',
        'whatsapp_popup_button_text' => 'Join WhatsApp Channel',
        'whatsapp_popup_frequency' => 'session',
        'cod_enabled' => '1',
        'razorpay_enabled' => '0',
        'razorpay_environment' => 'test',
        'razorpay_key_id' => '',
        'razorpay_key_secret' => '',
        'razorpay_webhook_secret' => '',
    ];

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `settings` WHERE `key_name` = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES (?, ?, ?)");
    $stmtUpdateSetting = $pdo->prepare("UPDATE `settings` SET `value` = ? WHERE `key_name` = ?");

    foreach ($defaultSettings as $key => $val) {
        $stmtCheck->execute([$key]);
        if ($stmtCheck->fetchColumn() == 0) {
            $stmtInsert->execute([$key, $val, 'Default setting for ' . $key]);
        } else if (in_array($key, ['store_address', 'service_areas'], true)) {
            $stmtUpdateSetting->execute([$val, $key]);
        }
    }

    // 5. Seed Admin User
    // Default admin: admin / admin12345
    $stmtAdmin = $pdo->prepare("SELECT COUNT(*) FROM `admins` WHERE `username` = ?");
    $stmtAdmin->execute(['admin']);
    if ($stmtAdmin->fetchColumn() == 0) {
        $passHash = password_hash('admin12345', PASSWORD_BCRYPT);
        $stmtCreateAdmin = $pdo->prepare("
            INSERT INTO `admins` (`username`, `email`, `password_hash`, `name`, `role`)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtCreateAdmin->execute([
            'admin',
            'admin@nafruitsandcoconuts.com',
            $passHash,
            'Store Manager',
            'superadmin'
        ]);
        echo "Default admin created: username 'admin', password 'admin12345'\n";
    }

    // Seed Categories
    $categoriesSeed = [
        ['name' => 'Tender Coconuts', 'slug' => 'tender-coconuts', 'description' => 'Fresh sweet tender coconuts directly from groves', 'sort_order' => 1],
        ['name' => 'Pure Coconut Water', 'slug' => 'pure-coconut-water', 'description' => 'Chilled 100% pure organic coconut water', 'sort_order' => 2],
        ['name' => 'Seasonal Fruits', 'slug' => 'seasonal-fruits', 'description' => 'Handpicked fresh farm fruits', 'sort_order' => 3],
    ];
    $catMap = [];
    $stmtCatCheck = $pdo->prepare("SELECT id FROM `categories` WHERE `slug` = ?");
    $stmtCatIns = $pdo->prepare("INSERT INTO `categories` (`name`, `slug`, `description`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, 1)");
    foreach ($categoriesSeed as $cs) {
        $stmtCatCheck->execute([$cs['slug']]);
        $cid = $stmtCatCheck->fetchColumn();
        if (!$cid) {
            $stmtCatIns->execute([$cs['name'], $cs['slug'], $cs['description'], $cs['sort_order']]);
            $cid = $pdo->lastInsertId();
        }
        $catMap[$cs['slug']] = (int)$cid;
    }

    // 6. Seed Products (Catalog with at least 5 active products across categories)
    $productsSeed = [
        [
            'category_id' => $catMap['pure-coconut-water'] ?? null,
            'name' => 'Fresh Coconut Water',
            'slug' => 'fresh-coconut-water',
            'tagline' => 'Pure • Natural • Healthy',
            'description' => 'Fresh tender coconut water, straight from nature. No added sugar, no preservatives.',
            'price' => 40.00,
            'original_price' => 50.00,
            'stock_quantity' => 150,
            'is_available' => 1,
            'has_cutting_options' => 0,
            'cutting_options_json' => null,
            'image_url' => '/assets/images/product-main.png',
            'gallery_json' => json_encode(['/assets/images/product-main.png', '/assets/images/thumb-1.png', '/assets/images/thumb-2.png'])
        ],
        [
            'category_id' => $catMap['tender-coconuts'] ?? null,
            'name' => 'Green Tender Coconut',
            'slug' => 'green-tender-coconut',
            'tagline' => 'Full of Sweet Water & Malai',
            'description' => 'Nutritious green coconut filled with fresh refreshing water and tender malai.',
            'price' => 55.00,
            'original_price' => 65.00,
            'stock_quantity' => 80,
            'is_available' => 1,
            'has_cutting_options' => 1,
            'cutting_options_json' => json_encode([
                ['id' => 'whole', 'name' => 'Without Cutting — Whole Coconut', 'price' => 0],
                ['id' => 'cut', 'name' => 'With Cutting — Ready to Drink', 'price' => 0],
                ['id' => 'malai_inc', 'name' => 'Malai Included (Special Cut)', 'price' => 10]
            ]),
            'image_url' => '/assets/images/hero-coconut.png',
            'gallery_json' => json_encode(['/assets/images/hero-coconut.png'])
        ],
        [
            'category_id' => $catMap['pure-coconut-water'] ?? null,
            'name' => 'Pudina Coconut Cooler',
            'slug' => 'pudina-coconut-cooler',
            'tagline' => 'Mint Infused Hydration',
            'description' => 'Fresh tender coconut water blended with garden mint leaves and black salt.',
            'price' => 60.00,
            'original_price' => 70.00,
            'stock_quantity' => 50,
            'is_available' => 1,
            'has_cutting_options' => 0,
            'cutting_options_json' => null,
            'image_url' => '/assets/images/product-main.png',
            'gallery_json' => json_encode(['/assets/images/product-main.png'])
        ],
        [
            'category_id' => $catMap['seasonal-fruits'] ?? null,
            'name' => 'Fresh Alphonso Mangoes (1kg)',
            'slug' => 'fresh-alphonso-mangoes',
            'tagline' => 'King of Fruits — Naturally Ripened',
            'description' => 'Sweet, juicy and aromatic naturally ripened fresh mangoes direct from farm.',
            'price' => 150.00,
            'original_price' => 180.00,
            'stock_quantity' => 40,
            'is_available' => 1,
            'has_cutting_options' => 0,
            'cutting_options_json' => null,
            'image_url' => '/assets/images/hero-coconut.png',
            'gallery_json' => json_encode(['/assets/images/hero-coconut.png'])
        ],
        [
            'category_id' => $catMap['seasonal-fruits'] ?? null,
            'name' => 'Royal Pomegranate Box (1kg)',
            'slug' => 'royal-pomegranate-box',
            'tagline' => 'Ruby Red Pearl Arils',
            'description' => 'Premium deep red sweet pomegranates rich in antioxidants.',
            'price' => 120.00,
            'original_price' => 140.00,
            'stock_quantity' => 60,
            'is_available' => 1,
            'has_cutting_options' => 0,
            'cutting_options_json' => null,
            'image_url' => '/assets/images/product-main.png',
            'gallery_json' => json_encode(['/assets/images/product-main.png'])
        ],
        [
            'category_id' => $catMap['tender-coconuts'] ?? null,
            'name' => 'Tender Coconut Malai Bowl',
            'slug' => 'tender-coconut-malai-bowl',
            'tagline' => 'Fresh Scooped Creamy Malai',
            'description' => 'Freshly scooped tender coconut malai served in chilled food-grade bowl.',
            'price' => 70.00,
            'original_price' => 85.00,
            'stock_quantity' => 30,
            'is_available' => 1,
            'has_cutting_options' => 0,
            'cutting_options_json' => null,
            'image_url' => '/assets/images/hero-coconut.png',
            'gallery_json' => json_encode(['/assets/images/hero-coconut.png'])
        ]
    ];

    $stmtProdCheck = $pdo->prepare("SELECT id FROM `products` WHERE `slug` = ?");
    $stmtProdInsert = $pdo->prepare("
        INSERT INTO `products` (
            `category_id`, `name`, `slug`, `tagline`, `description`, `price`, 
            `original_price`, `stock_quantity`, `is_available`, `has_cutting_options`, 
            `cutting_options_json`, `image_url`, `gallery_json`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtProdUpdate = $pdo->prepare("UPDATE `products` SET `is_available` = 1, `stock_quantity` = 100 WHERE `id` = ?");

    $prodIdMap = [];
    foreach ($productsSeed as $p) {
        $stmtProdCheck->execute([$p['slug']]);
        $existingId = $stmtProdCheck->fetchColumn();
        if (!$existingId) {
            $stmtProdInsert->execute([
                $p['category_id'], $p['name'], $p['slug'], $p['tagline'], $p['description'],
                $p['price'], $p['original_price'], $p['stock_quantity'], $p['is_available'],
                $p['has_cutting_options'], $p['cutting_options_json'], $p['image_url'], $p['gallery_json']
            ]);
            $existingId = $pdo->lastInsertId();
        } else {
            $stmtProdUpdate->execute([$existingId]);
        }
        $prodIdMap[$p['slug']] = (int)$existingId;
    }

    // Seed Combo Bundles (at least 3 active combo bundles)
    $combosSeed = [
        [
            'name' => 'Daily Hydration Bundle (3x Coconut Water)',
            'slug' => 'daily-hydration-bundle',
            'tagline' => 'Triple Pure Coconut Water Pack',
            'description' => '3 bottles of 100% pure fresh coconut water at a special discounted price.',
            'price' => 110.00,
            'original_price' => 120.00,
            'image_url' => '/assets/images/product-main.png',
            'items' => [
                ['slug' => 'fresh-coconut-water', 'quantity' => 3]
            ]
        ],
        [
            'name' => 'Tender Coconut & Malai Duo',
            'slug' => 'tender-coconut-malai-duo',
            'tagline' => '1 Coconut + 1 Fresh Malai Bowl',
            'description' => 'Get a whole green tender coconut plus a delicious freshly scooped malai bowl.',
            'price' => 115.00,
            'original_price' => 125.00,
            'image_url' => '/assets/images/hero-coconut.png',
            'items' => [
                ['slug' => 'green-tender-coconut', 'quantity' => 1],
                ['slug' => 'tender-coconut-malai-bowl', 'quantity' => 1]
            ]
        ],
        [
            'name' => 'Family Fresh Harvest Feast',
            'slug' => 'family-fresh-harvest-feast',
            'tagline' => '2 Coconuts + 1kg Alphonso Mangoes',
            'description' => 'Complete family refreshment package with sweet tender coconuts and ripe mangoes.',
            'price' => 230.00,
            'original_price' => 260.00,
            'image_url' => '/assets/images/hero-coconut.png',
            'items' => [
                ['slug' => 'green-tender-coconut', 'quantity' => 2],
                ['slug' => 'fresh-alphonso-mangoes', 'quantity' => 1]
            ]
        ]
    ];

    $stmtComboCheck = $pdo->prepare("SELECT id FROM `combos` WHERE `slug` = ?");
    $stmtComboIns = $pdo->prepare("
        INSERT INTO `combos` (
            `name`, `slug`, `tagline`, `description`, `price`, 
            `original_price`, `image_url`, `stock_quantity`, `is_available`, `sort_order`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 50, 1, ?)
    ");
    $stmtComboItemIns = $pdo->prepare("
        INSERT INTO `combo_items` (`combo_id`, `product_id`, `quantity`)
        VALUES (?, ?, ?)
    ");

    $cIndex = 1;
    foreach ($combosSeed as $c) {
        $stmtComboCheck->execute([$c['slug']]);
        $existingCid = $stmtComboCheck->fetchColumn();
        if (!$existingCid) {
            $stmtComboIns->execute([
                $c['name'], $c['slug'], $c['tagline'], $c['description'],
                $c['price'], $c['original_price'],
                $c['image_url'], $cIndex * 10
            ]);
            $existingCid = (int)$pdo->lastInsertId();

            foreach ($c['items'] as $ci) {
                $pid = $prodIdMap[$ci['slug']] ?? 1;
                $stmtComboItemIns->execute([$existingCid, $pid, $ci['quantity']]);
            }
        }
        $cIndex++;
    }

    // 7. Seed Sample Customer & Order #NA1024 (Matches the uploaded reference image)
    $stmtOrderCheck = $pdo->prepare("SELECT COUNT(*) FROM `orders` WHERE `order_number` = ?");
    $stmtOrderCheck->execute(['NA1024']);
    if ($stmtOrderCheck->fetchColumn() == 0) {
        // Customer
        $stmtCust = $pdo->prepare("
            INSERT INTO `customers` (`name`, `phone`, `address`, `city`, `state`, `pincode`)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtCust->execute([
            'Rahul Sharma',
            '+91 98765 43210',
            '123, Green Park, Near Main Road',
            'Bhopal',
            'Madhya Pradesh',
            '462016'
        ]);
        $customerId = $pdo->lastInsertId();

        $trackingHash = hash('sha256', 'NA1024' . microtime(true) . 'salt_coconuts');

        $stmtCreateOrder = $pdo->prepare("
            INSERT INTO `orders` (
                `order_number`, `customer_id`, `customer_name`, `customer_phone`, 
                `fulfilment_method`, `delivery_address`, `subtotal`, `delivery_charge`, 
                `total_amount`, `payment_method`, `payment_status`, `order_status`, 
                `tracking_hash`, `estimated_delivery`, `notes`, `created_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmtCreateOrder->execute([
            'NA1024',
            $customerId,
            'Rahul Sharma',
            '+91 98765 43210',
            'delivery',
            '123, Green Park, Near Main Road, Bhopal, Madhya Pradesh - 462016',
            80.00,
            40.00,
            120.00,
            'cod',
            'pending',
            'confirmed',
            $trackingHash,
            'Today, 4:30 PM - 6:30 PM',
            'Leave with security if not available'
        ]);
        $orderId = $pdo->lastInsertId();

        // Order item
        $stmtItem = $pdo->prepare("
            INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`)
            VALUES (?, 1, 'Fresh Coconut Water', 40.00, 2, 80.00)
        ");
        $stmtItem->execute([$orderId]);

        // Status history
        $stmtHist = $pdo->prepare("
            INSERT INTO `order_status_history` (`order_id`, `old_status`, `new_status`, `comment`, `changed_by`)
            VALUES (?, 'pending', 'confirmed', 'Order confirmed by store manager', 'admin')
        ");
        $stmtHist->execute([$orderId]);

        echo "Reference order #NA1024 seeded successfully.\n";
    }

    // 8. Seed Default Coupons
    $defaultCoupons = [
        [
            'code' => 'FREEDELIVERY',
            'name' => 'Free Doorstep Delivery',
            'description' => 'Enjoy free delivery on any order above ₹80',
            'type' => 'free_delivery',
            'discount_value' => 0.00,
            'min_order_amount' => 80.00,
            'max_discount_amount' => null,
            'max_uses' => 500,
            'max_uses_per_customer' => 2,
            'is_active' => 1
        ],
        [
            'code' => 'SAVE10',
            'name' => '10% Healthy Hydration',
            'description' => 'Get 10% discount on fresh coconut water up to ₹50',
            'type' => 'percentage',
            'discount_value' => 10.00,
            'min_order_amount' => 100.00,
            'max_discount_amount' => 50.00,
            'max_uses' => 1000,
            'max_uses_per_customer' => 3,
            'is_active' => 1
        ],
        [
            'code' => 'FLAT50',
            'name' => 'Flat ₹50 Super Saver',
            'description' => 'Get flat ₹50 off on orders of ₹200 or more',
            'type' => 'fixed',
            'discount_value' => 50.00,
            'min_order_amount' => 200.00,
            'max_discount_amount' => null,
            'max_uses' => 200,
            'max_uses_per_customer' => 1,
            'is_active' => 1
        ]
    ];

    $stmtCouponCheck = $pdo->prepare("SELECT COUNT(*) FROM `coupons` WHERE `code` = ?");
    $stmtCouponInsert = $pdo->prepare("
        INSERT INTO `coupons` (`code`, `name`, `description`, `type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `max_uses`, `max_uses_per_customer`, `is_active`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($defaultCoupons as $c) {
        $stmtCouponCheck->execute([$c['code']]);
        if ($stmtCouponCheck->fetchColumn() == 0) {
            $stmtCouponInsert->execute([
                $c['code'], $c['name'], $c['description'], $c['type'],
                $c['discount_value'], $c['min_order_amount'], $c['max_discount_amount'],
                $c['max_uses'], $c['max_uses_per_customer'], $c['is_active']
            ]);
        }
    }
    echo "Default coupons verified/seeded successfully.\n";

    // Record installation lock file
    $lockData = json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'version' => '1.0.0',
        'source' => 'setup.php',
        'verified' => true
    ], JSON_PRETTY_PRINT);
    @mkdir(__DIR__ . '/../storage', 0755, true);
    @file_put_contents(INSTALL_LOCK_FILE, $lockData);

    echo "Database setup and seeding completed successfully.\n";
} catch (Exception $e) {
    echo "Database Setup Error: " . $e->getMessage() . "\n";
    exit(1);
}
