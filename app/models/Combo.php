<?php
/**
 * Combo Offer & Bundle Model
 * N.A Fresh Fruits & Coconuts
 */

class Combo {
    public static function getAll(bool $activeOnly = true): array {
        $pdo = Database::getConnection();
        $sql = "SELECT c.* FROM `combos` c";
        $where = [];
        if ($activeOnly) {
            $where[] = "c.`is_available` = 1";
            $where[] = "(c.`start_date` IS NULL OR c.`start_date` <= NOW())";
            $where[] = "(c.`end_date` IS NULL OR c.`end_date` >= NOW())";
            $where[] = "c.`stock_quantity` > 0";
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY c.`sort_order` ASC, c.`id` ASC";
        $stmt = $pdo->query($sql);
        $combos = $stmt->fetchAll();

        foreach ($combos as &$combo) {
            $combo['items'] = self::getItems((int)$combo['id']);
            $combo['savings'] = max(0.00, (float)$combo['original_price'] - (float)$combo['price']);
            $combo['discount_percent'] = (float)$combo['original_price'] > 0 
                ? (int)round((($combo['savings'] / (float)$combo['original_price']) * 100))
                : 0;
        }

        return $combos;
    }

    public static function getById(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `combos` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $combo = $stmt->fetch();
        if (!$combo) {
            return null;
        }
        $combo['items'] = self::getItems($id);
        $combo['savings'] = max(0.00, (float)$combo['original_price'] - (float)$combo['price']);
        $combo['discount_percent'] = (float)$combo['original_price'] > 0 
            ? (int)round((($combo['savings'] / (float)$combo['original_price']) * 100))
            : 0;
        return $combo;
    }

    public static function getBySlug(string $slug): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `combos` WHERE `slug` = ? LIMIT 1");
        $stmt->execute([$slug]);
        $combo = $stmt->fetch();
        if (!$combo) {
            return null;
        }
        $combo['items'] = self::getItems((int)$combo['id']);
        $combo['savings'] = max(0.00, (float)$combo['original_price'] - (float)$combo['price']);
        $combo['discount_percent'] = (float)$combo['original_price'] > 0 
            ? (int)round((($combo['savings'] / (float)$combo['original_price']) * 100))
            : 0;
        return $combo;
    }

    public static function getItems(int $comboId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT ci.*, p.name AS product_name, p.price AS product_price, p.image_url AS product_image, p.stock_quantity AS product_stock
            FROM `combo_items` ci
            JOIN `products` p ON p.id = ci.product_id
            WHERE ci.combo_id = ?
            ORDER BY ci.id ASC
        ");
        $stmt->execute([$comboId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data, array $items = []): int {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $name = trim($data['name'] ?? '');
            $slug = trim($data['slug'] ?? '') ?: Category::slugify($name);
            $tagline = trim($data['tagline'] ?? '');
            $description = trim($data['description'] ?? '');
            $price = max(0.00, (float)($data['price'] ?? 0));
            $originalPrice = max($price, (float)($data['original_price'] ?? $price));
            $imageUrl = trim($data['image_url'] ?? '') ?: '/assets/images/hero-coconut.png';
            $stock = max(0, (int)($data['stock_quantity'] ?? 50));
            $isAvailable = !empty($data['is_available']) ? 1 : 0;
            $startDate = !empty($data['start_date']) ? $data['start_date'] : null;
            $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
            $sortOrder = (int)($data['sort_order'] ?? 0);

            $stmt = $pdo->prepare("
                INSERT INTO `combos` (
                    `name`, `slug`, `tagline`, `description`, `price`, `original_price`,
                    `image_url`, `stock_quantity`, `is_available`, `start_date`, `end_date`, `sort_order`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $slug, $tagline, $description, $price, $originalPrice,
                $imageUrl, $stock, $isAvailable, $startDate, $endDate, $sortOrder
            ]);
            $comboId = (int)$pdo->lastInsertId();

            if (!empty($items)) {
                $stmtItem = $pdo->prepare("
                    INSERT INTO `combo_items` (`combo_id`, `product_id`, `quantity`, `notes`)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($items as $it) {
                    $prodId = (int)($it['product_id'] ?? 0);
                    $qty = max(1, (int)($it['quantity'] ?? 1));
                    $notes = trim($it['notes'] ?? '');
                    if ($prodId > 0) {
                        $stmtItem->execute([$comboId, $prodId, $qty, $notes]);
                    }
                }
            }

            $pdo->commit();
            return $comboId;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data, ?array $items = null): bool {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $name = trim($data['name'] ?? '');
            $slug = trim($data['slug'] ?? '') ?: Category::slugify($name);
            $tagline = trim($data['tagline'] ?? '');
            $description = trim($data['description'] ?? '');
            $price = max(0.00, (float)($data['price'] ?? 0));
            $originalPrice = max($price, (float)($data['original_price'] ?? $price));
            $imageUrl = trim($data['image_url'] ?? '') ?: '/assets/images/hero-coconut.png';
            $stock = max(0, (int)($data['stock_quantity'] ?? 50));
            $isAvailable = !empty($data['is_available']) ? 1 : 0;
            $startDate = !empty($data['start_date']) ? $data['start_date'] : null;
            $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
            $sortOrder = (int)($data['sort_order'] ?? 0);

            $stmt = $pdo->prepare("
                UPDATE `combos` SET
                    `name` = ?, `slug` = ?, `tagline` = ?, `description` = ?,
                    `price` = ?, `original_price` = ?, `image_url` = ?,
                    `stock_quantity` = ?, `is_available` = ?, `start_date` = ?,
                    `end_date` = ?, `sort_order` = ?, `updated_at` = NOW()
                WHERE `id` = ?
            ");
            $stmt->execute([
                $name, $slug, $tagline, $description,
                $price, $originalPrice, $imageUrl,
                $stock, $isAvailable, $startDate,
                $endDate, $sortOrder, $id
            ]);

            if ($items !== null) {
                $stmtDel = $pdo->prepare("DELETE FROM `combo_items` WHERE `combo_id` = ?");
                $stmtDel->execute([$id]);

                $stmtItem = $pdo->prepare("
                    INSERT INTO `combo_items` (`combo_id`, `product_id`, `quantity`, `notes`)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($items as $it) {
                    $prodId = (int)($it['product_id'] ?? 0);
                    $qty = max(1, (int)($it['quantity'] ?? 1));
                    $notes = trim($it['notes'] ?? '');
                    if ($prodId > 0) {
                        $stmtItem->execute([$id, $prodId, $qty, $notes]);
                    }
                }
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): bool {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmtDelItems = $pdo->prepare("DELETE FROM `combo_items` WHERE `combo_id` = ?");
            $stmtDelItems->execute([$id]);

            $stmtDel = $pdo->prepare("DELETE FROM `combos` WHERE `id` = ?");
            $stmtDel->execute([$id]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }
}
