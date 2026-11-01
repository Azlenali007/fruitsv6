<?php
/**
 * Category Model
 * N.A Fresh Fruits & Coconuts
 */

class Category {
    public static function getAll(bool $activeOnly = true): array {
        $pdo = Database::getConnection();
        $sql = "SELECT c.*, (SELECT COUNT(*) FROM `products` p WHERE p.category_id = c.id AND p.is_available = 1) AS product_count 
                FROM `categories` c";
        if ($activeOnly) {
            $sql .= " WHERE c.`is_active` = 1";
        }
        $sql .= " ORDER BY c.`sort_order` ASC, c.`id` ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `categories` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getBySlug(string $slug): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `categories` WHERE `slug` = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int {
        $pdo = Database::getConnection();
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '') ?: self::slugify($name);
        $description = trim($data['description'] ?? '');
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $isActive = !empty($data['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO `categories` (`name`, `slug`, `description`, `sort_order`, `is_active`)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $slug, $description, $sortOrder, $isActive]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool {
        $pdo = Database::getConnection();
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '') ?: self::slugify($name);
        $description = trim($data['description'] ?? '');
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $isActive = !empty($data['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE `categories` 
            SET `name` = ?, `slug` = ?, `description` = ?, `sort_order` = ?, `is_active` = ?
            WHERE `id` = ?
        ");
        return $stmt->execute([$name, $slug, $description, $sortOrder, $isActive, $id]);
    }

    public static function delete(int $id): bool {
        $pdo = Database::getConnection();
        // Remove category linkage from products
        $stmtUp = $pdo->prepare("UPDATE `products` SET `category_id` = NULL WHERE `category_id` = ?");
        $stmtUp->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM `categories` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    public static function slugify(string $text): string {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'cat-' . substr(md5((string)time()), 0, 6) : $text;
    }
}
