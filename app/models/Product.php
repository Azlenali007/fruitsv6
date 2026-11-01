<?php
/**
 * Product Model
 * N.A Fresh Fruits & Coconuts
 * Supports multi-product catalog, categories, pricing, stock, multi-images,
 * and coconut preparation / cutting options.
 */

class Product {
    public static function getPrimary(): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM `products` p LEFT JOIN `categories` c ON c.id = p.category_id WHERE p.`slug` = 'fresh-coconut-water' LIMIT 1");
        $prod = $stmt->fetch();
        if (!$prod) {
            $stmt2 = $pdo->query("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM `products` p LEFT JOIN `categories` c ON c.id = p.category_id ORDER BY p.`sort_order` ASC, p.`id` ASC LIMIT 1");
            $prod = $stmt2->fetch();
        }
        return self::formatProduct($prod);
    }

    public static function getById(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM `products` p LEFT JOIN `categories` c ON c.id = p.category_id WHERE p.`id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $prod = $stmt->fetch();
        return self::formatProduct($prod);
    }

    public static function getBySlug(string $slug): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM `products` p LEFT JOIN `categories` c ON c.id = p.category_id WHERE p.`slug` = ? LIMIT 1");
        $stmt->execute([$slug]);
        $prod = $stmt->fetch();
        return self::formatProduct($prod);
    }

    public static function getAll(bool $activeOnly = false, ?int $categoryId = null, ?string $search = null): array {
        $pdo = Database::getConnection();
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
                FROM `products` p 
                LEFT JOIN `categories` c ON c.id = p.category_id";
        $where = [];
        $params = [];

        if ($activeOnly) {
            $where[] = "p.`is_available` = 1 AND p.`stock_quantity` > 0";
        }

        if ($categoryId !== null && $categoryId > 0) {
            $where[] = "p.`category_id` = ?";
            $params[] = $categoryId;
        }

        if ($search !== null && trim($search) !== '') {
            $where[] = "(p.`name` LIKE ? OR p.`tagline` LIKE ? OR p.`description` LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY p.`sort_order` ASC, p.`id` ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        foreach ($products as &$p) {
            $p = self::formatProduct($p);
        }

        return $products;
    }

    public static function create(array $data): int {
        $pdo = Database::getConnection();
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '') ?: Category::slugify($name);
        $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        $tagline = trim($data['tagline'] ?? '');
        $description = trim($data['description'] ?? '');
        $price = max(0.00, (float)($data['price'] ?? 0));
        $originalPrice = !empty($data['original_price']) ? (float)$data['original_price'] : null;
        $stock = max(0, (int)($data['stock_quantity'] ?? 0));
        $isAvailable = !empty($data['is_available']) ? 1 : 0;
        $imageUrl = trim($data['image_url'] ?? '') ?: '/assets/images/product-main.png';
        $galleryJson = !empty($data['gallery_json']) ? $data['gallery_json'] : json_encode([$imageUrl]);
        $hasCutting = !empty($data['has_cutting_options']) ? 1 : 0;
        $cuttingJson = !empty($data['cutting_options_json']) ? $data['cutting_options_json'] : null;
        $sortOrder = (int)($data['sort_order'] ?? 0);

        $stmt = $pdo->prepare("
            INSERT INTO `products` (
                `category_id`, `name`, `slug`, `tagline`, `description`,
                `price`, `original_price`, `stock_quantity`, `is_available`,
                `has_cutting_options`, `cutting_options_json`, `image_url`, `gallery_json`, `sort_order`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $categoryId, $name, $slug, $tagline, $description,
            $price, $originalPrice, $stock, $isAvailable,
            $hasCutting, $cuttingJson, $imageUrl, $galleryJson, $sortOrder
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool {
        $pdo = Database::getConnection();
        $fields = [];
        $values = [];

        $allowedFields = [
            'category_id', 'name', 'slug', 'tagline', 'description',
            'price', 'original_price', 'stock_quantity', 'is_available',
            'has_cutting_options', 'cutting_options_json', 'image_url', 'gallery_json', 'sort_order'
        ];

        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "`$f` = ?";
                $values[] = $data[$f];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE `products` SET " . implode(', ', $fields) . ", `updated_at` = NOW() WHERE `id` = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public static function delete(int $id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM `products` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Decode and return cutting options array
     */
    public static function getCuttingOptions(array $product): array {
        if (empty($product['has_cutting_options']) || empty($product['cutting_options_json'])) {
            return [];
        }
        $options = json_decode($product['cutting_options_json'], true);
        return is_array($options) ? $options : [];
    }

    /**
     * Validate and retrieve selected cutting option with authoritative price
     */
    public static function validateCuttingOption(array $product, ?string $optionId): array {
        $options = self::getCuttingOptions($product);
        if (empty($options)) {
            return [
                'has_option' => false,
                'option_id' => null,
                'option_name' => null,
                'extra_charge' => 0.00
            ];
        }

        if (!empty($optionId)) {
            foreach ($options as $opt) {
                if (($opt['id'] ?? '') === $optionId) {
                    return [
                        'has_option' => true,
                        'option_id' => $opt['id'],
                        'option_name' => $opt['name'] ?? $opt['id'],
                        'extra_charge' => max(0.00, (float)($opt['price'] ?? 0))
                    ];
                }
            }
        }

        // Default to first option if not matched or empty
        $default = $options[0] ?? [];
        return [
            'has_option' => true,
            'option_id' => $default['id'] ?? 'default',
            'option_name' => $default['name'] ?? 'Standard Cut',
            'extra_charge' => max(0.00, (float)($default['price'] ?? 0))
        ];
    }

    private static function formatProduct(?array $prod): ?array {
        if (!$prod) {
            return null;
        }
        if (!empty($prod['gallery_json'])) {
            $prod['gallery'] = json_decode($prod['gallery_json'], true) ?: [];
        } else {
            $prod['gallery'] = [$prod['image_url']];
        }
        $prod['cutting_options'] = self::getCuttingOptions($prod);
        return $prod;
    }
}
