<?php
/**
 * DeliveryBoy Model
 * N.A Fresh Fruits & Coconuts
 */

class DeliveryBoy {
    public static function create(array $data): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO `delivery_boys` (
                `name`, `mobile`, `login_id`, `password_hash`, `status`,
                `is_online`, `vehicle_type`, `vehicle_number`, `created_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            trim($data['name'] ?? ''),
            trim($data['mobile'] ?? ''),
            trim($data['login_id'] ?? ''),
            password_hash($data['password'] ?? '', PASSWORD_DEFAULT),
            $data['status'] ?? 'active',
            !empty($data['is_online']) ? 1 : 0,
            $data['vehicle_type'] ?? 'Bike / Scooter',
            trim($data['vehicle_number'] ?? '')
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function getById(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `delivery_boys` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public static function getByLoginId(string $loginId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `delivery_boys` WHERE `login_id` = ? LIMIT 1");
        $stmt->execute([trim($loginId)]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public static function getByMobile(string $mobile): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `delivery_boys` WHERE `mobile` = ? LIMIT 1");
        $stmt->execute([trim($mobile)]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public static function getAll(?string $status = null): array {
        $pdo = Database::getConnection();
        if ($status !== null) {
            $stmt = $pdo->prepare("SELECT * FROM `delivery_boys` WHERE `status` = ? ORDER BY `name` ASC");
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->query("SELECT * FROM `delivery_boys` ORDER BY `name` ASC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function update(int $id, array $data): bool {
        $pdo = Database::getConnection();
        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = "`name` = ?";
            $params[] = trim($data['name']);
        }
        if (isset($data['mobile'])) {
            $fields[] = "`mobile` = ?";
            $params[] = trim($data['mobile']);
        }
        if (isset($data['login_id'])) {
            $fields[] = "`login_id` = ?";
            $params[] = trim($data['login_id']);
        }
        if (!empty($data['password'])) {
            $fields[] = "`password_hash` = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (isset($data['status'])) {
            $fields[] = "`status` = ?";
            $params[] = $data['status'];
        }
        if (isset($data['is_online'])) {
            $fields[] = "`is_online` = ?";
            $params[] = !empty($data['is_online']) ? 1 : 0;
        }
        if (isset($data['vehicle_type'])) {
            $fields[] = "`vehicle_type` = ?";
            $params[] = trim($data['vehicle_type']);
        }
        if (isset($data['vehicle_number'])) {
            $fields[] = "`vehicle_number` = ?";
            $params[] = trim($data['vehicle_number']);
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE `delivery_boys` SET " . implode(', ', $fields) . " WHERE `id` = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public static function setOnlineStatus(int $id, bool $isOnline): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE `delivery_boys` SET `is_online` = ? WHERE `id` = ?");
        return $stmt->execute([$isOnline ? 1 : 0, $id]);
    }

    public static function updateLocation(
        int $id,
        float $lat,
        float $lng,
        ?float $heading = null,
        ?float $speed = null,
        ?float $accuracy = null,
        ?int $orderId = null
    ): bool {
        $pdo = Database::getConnection();

        // 1. Update latest coordinates on delivery_boys table
        $stmt = $pdo->prepare("
            UPDATE `delivery_boys`
            SET `current_lat` = ?, `current_lng` = ?, `last_location_updated_at` = NOW()
            WHERE `id` = ?
        ");
        $stmt->execute([$lat, $lng, $id]);

        // 2. Append to location history log for breadcrumbs and dispute resolution
        $stmtLog = $pdo->prepare("
            INSERT INTO `delivery_boy_locations` (
                `delivery_boy_id`, `order_id`, `lat`, `lng`, `heading`, `speed`, `accuracy`, `recorded_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtLog->execute([$id, $orderId, $lat, $lng, $heading, $speed, $accuracy]);

        // 3. Prune old location history older than 48 hours to prevent bloat
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM `delivery_boy_locations` WHERE `recorded_at` < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
        }

        return true;
    }

    public static function getActiveOnlineRiders(): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT * FROM `delivery_boys`
            WHERE `status` = 'active' AND `is_online` = 1
            ORDER BY `last_location_updated_at` DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getPerformanceStats(int $riderId): array {
        $pdo = Database::getConnection();

        // Total delivered orders
        $stmtDel = $pdo->prepare("
            SELECT COUNT(*) FROM `orders`
            WHERE `delivery_boy_id` = ? AND `delivery_workflow_status` = 'delivered'
        ");
        $stmtDel->execute([$riderId]);
        $totalDelivered = (int)$stmtDel->fetchColumn();

        // Total COD collected and pending reconciliation
        $stmtCod = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN `status` = 'collected_by_rider' THEN `amount` ELSE 0 END), 0) AS `pending_cash`,
                COALESCE(SUM(CASE WHEN `status` = 'reconciled_with_admin' THEN `amount` ELSE 0 END), 0) AS `reconciled_cash`,
                COALESCE(SUM(`amount`), 0) AS `total_collected`
            FROM `delivery_cod_collections`
            WHERE `delivery_boy_id` = ?
        ");
        $stmtCod->execute([$riderId]);
        $cod = $stmtCod->fetch(PDO::FETCH_ASSOC);

        // Active orders currently in progress
        $stmtActive = $pdo->prepare("
            SELECT COUNT(*) FROM `orders`
            WHERE `delivery_boy_id` = ? AND `delivery_workflow_status` IN ('accepted', 'reached_shop', 'picked_up', 'out_for_delivery')
        ");
        $stmtActive->execute([$riderId]);
        $activeOrders = (int)$stmtActive->fetchColumn();

        return [
            'total_delivered' => $totalDelivered,
            'active_orders' => $activeOrders,
            'pending_cash' => (float)($cod['pending_cash'] ?? 0),
            'reconciled_cash' => (float)($cod['reconciled_cash'] ?? 0),
            'total_collected' => (float)($cod['total_collected'] ?? 0)
        ];
    }
}
