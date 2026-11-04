<?php
/**
 * DeliveryDispatchService
 * N.A Fresh Fruits & Coconuts
 *
 * Core engine for Blinkit-style automated dispatch, atomic order locking,
 * delivery workflow state machine, live GPS routing & ETA, OTP confirmation,
 * and Cash-on-Delivery reconciliation.
 */

class DeliveryDispatchService {
    // Verified fixed shop coordinates (N.A Fresh Fruits & Coconuts, Jaora)
    public const STORE_LAT = 23.635784;
    public const STORE_LNG = 75.124683;
    public const STORE_NAME = 'N.A Fresh Fruits & Coconuts - Main Store';
    public const STORE_ADDRESS = 'Station Road, Opp. Municipal Market, Jaora, MP 457226';

    // Escalation timeout in seconds (5 minutes)
    public const ESCALATION_TIMEOUT_SECONDS = 300;

    public static function getStoreLat(): float {
        return (float)Setting::get('store_latitude', (string)self::STORE_LAT);
    }

    public static function getStoreLng(): float {
        return (float)Setting::get('store_longitude', (string)self::STORE_LNG);
    }

    public static function getStoreAddress(): string {
        return Setting::get('store_address', self::STORE_ADDRESS);
    }

    public static function getGoogleMapsApiKey(): string {
        return Setting::get('google_maps_api_key', 'AIzaSyABvsHD8lil-4uRWC5lUT_qIQgGMpV0wGk');
    }

    /**
     * Get available incoming orders ready for a specific rider to accept.
     * Excludes orders already rejected by this rider or assigned to another rider.
     */
    public static function getAvailableOrdersForRider(int $riderId): array {
        $pdo = Database::getConnection();

        // Trigger escalation check for stale orders
        self::escalateUnacceptedOrders();

        $sql = "
            SELECT
                o.id,
                o.order_number,
                o.customer_name,
                o.customer_phone,
                o.delivery_address,
                o.delivery_area_name,
                o.latitude,
                o.longitude,
                o.subtotal,
                o.delivery_charge,
                o.total_amount,
                o.payment_method,
                o.payment_status,
                o.order_status,
                o.delivery_workflow_status,
                o.delivery_escalated,
                o.created_at,
                TIMESTAMPDIFF(SECOND, o.created_at, NOW()) AS seconds_elapsed,
                (
                    SELECT COUNT(*)
                    FROM order_items oi
                    WHERE oi.order_id = o.id
                ) AS item_count,
                (
                    SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ')
                    FROM order_items oi
                    WHERE oi.order_id = o.id
                ) AS items_preview
            FROM orders o
            WHERE o.fulfilment_method = 'delivery'
              AND o.delivery_workflow_status = 'unassigned'
              AND o.order_status NOT IN ('cancelled', 'delivered')
              AND NOT EXISTS (
                  SELECT 1 FROM order_assignments oa
                  WHERE oa.order_id = o.id
                    AND oa.delivery_boy_id = ?
                    AND oa.status = 'rejected'
              )
            ORDER BY o.id DESC
            LIMIT 20
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$riderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ATOMIC FIRST-ACCEPT ORDER ASSIGNMENT
     * Uses InnoDB SELECT ... FOR UPDATE within a transaction to guarantee
     * that no two delivery boys can accept the same order simultaneously.
     */
    public static function acceptOrderAtomic(int $orderId, int $riderId): array {
        $rider = DeliveryBoy::getById($riderId);
        if (!$rider || $rider['status'] !== 'active') {
            return ['success' => false, 'error' => 'Your delivery partner account is inactive or not found. Please contact store management.'];
        }

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            // Lock order row for update
            $stmt = $pdo->prepare("
                SELECT id, order_number, delivery_boy_id, delivery_workflow_status, order_status, total_amount, payment_method, payment_status
                FROM orders
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Order not found.'];
            }

            if ($order['delivery_workflow_status'] !== 'unassigned' || !empty($order['delivery_boy_id'])) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'error' => 'Order #' . $order['order_number'] . ' was already accepted by another delivery partner.',
                    'already_taken' => true
                ];
            }

            // Assign order to this rider
            $upStmt = $pdo->prepare("
                UPDATE orders
                SET delivery_boy_id = ?,
                    delivery_workflow_status = 'accepted',
                    delivery_assigned_at = NOW(),
                    delivery_accepted_at = NOW(),
                    order_status = CASE WHEN order_status = 'pending' THEN 'confirmed' ELSE order_status END
                WHERE id = ?
            ");
            $upStmt->execute([$riderId, $orderId]);

            // Record acceptance in assignments table
            $assignStmt = $pdo->prepare("
                INSERT INTO order_assignments (
                    order_id, delivery_boy_id, status, assigned_by, offered_at, responded_at
                ) VALUES (?, ?, 'accepted', 'auto', NOW(), NOW())
            ");
            $assignStmt->execute([$orderId, $riderId]);

            // Append to timeline
            self::logTimeline(
                $orderId,
                $riderId,
                'accepted',
                'Order Accepted by Delivery Partner',
                'Delivery partner accepted the order and is heading towards the store for pickup.',
                'delivery_boy',
                $riderId
            );

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Order #' . $order['order_number'] . ' successfully accepted!',
                'order_id' => $orderId,
                'redirect_url' => '/delivery/order?id=' . $orderId,
                'order' => $order
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('DeliveryDispatchService::acceptOrderAtomic error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Database error while accepting order: ' . $e->getMessage()];
        }
    }

    /**
     * Reject an order for this rider.
     * Leaves order available for other eligible delivery boys.
     */
    public static function rejectOrder(int $orderId, int $riderId, ?string $reason = null): array {
        $pdo = Database::getConnection();

        $stmtCheck = $pdo->prepare("SELECT delivery_workflow_status, delivery_boy_id FROM orders WHERE id = ?");
        $stmtCheck->execute([$orderId]);
        $order = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        // If the rider had already accepted it, disallow simple reject without admin intervention
        if (!empty($order['delivery_boy_id']) && (int)$order['delivery_boy_id'] === $riderId && $order['delivery_workflow_status'] !== 'unassigned') {
            return ['success' => false, 'error' => 'You have already accepted this order. Please contact Store Admin if you are unable to fulfill it.'];
        }

        // Record rejection
        $stmt = $pdo->prepare("
            INSERT INTO order_assignments (
                order_id, delivery_boy_id, status, rejection_reason, assigned_by, offered_at, responded_at
            ) VALUES (?, ?, 'rejected', ?, 'auto', NOW(), NOW())
        ");
        $stmt->execute([$orderId, $riderId, $reason]);

        self::logTimeline(
            $orderId,
            $riderId,
            'rejected',
            'Order Passed by Delivery Partner',
            'A delivery partner passed on this delivery. The system is offering it to other nearby partners.',
            'delivery_boy',
            $riderId
        );

        return ['success' => true, 'message' => 'Order rejected. It will not be shown to you again.'];
    }

    /**
     * Escalate unaccepted orders past timeout to Admin Panel
     */
    public static function escalateUnacceptedOrders(int $timeoutSeconds = self::ESCALATION_TIMEOUT_SECONDS): int {
        $pdo = Database::getConnection();

        $sql = "
            SELECT id, order_number
            FROM orders
            WHERE fulfilment_method = 'delivery'
              AND delivery_workflow_status = 'unassigned'
              AND delivery_escalated = 0
              AND order_status NOT IN ('cancelled', 'delivered')
              AND TIMESTAMPDIFF(SECOND, created_at, NOW()) >= ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$timeoutSeconds]);
        $unaccepted = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $escalatedCount = 0;
        foreach ($unaccepted as $ord) {
            $up = $pdo->prepare("UPDATE orders SET delivery_escalated = 1, delivery_escalated_at = NOW() WHERE id = ?");
            $up->execute([$ord['id']]);

            self::logTimeline(
                (int)$ord['id'],
                null,
                'escalated',
                'Escalated to Admin (No Partner Accepted)',
                'No delivery partner accepted this order within ' . round($timeoutSeconds / 60) . ' minutes. Escalated for manual store manager assignment.',
                'system',
                null
            );
            $escalatedCount++;
        }

        return $escalatedCount;
    }

    /**
     * Admin Manual Assignment or Reassignment
     */
    public static function adminAssignOrder(int $orderId, int $riderId, int $adminId, ?string $notes = null): array {
        $pdo = Database::getConnection();

        $rider = DeliveryBoy::getById($riderId);
        if (!$rider || $rider['status'] !== 'active') {
            return ['success' => false, 'error' => 'Selected delivery partner is not active.'];
        }

        $stmtOrd = $pdo->prepare("SELECT id, order_number, delivery_boy_id, delivery_workflow_status FROM orders WHERE id = ?");
        $stmtOrd->execute([$orderId]);
        $order = $stmtOrd->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        $isReassignment = !empty($order['delivery_boy_id']) && (int)$order['delivery_boy_id'] !== $riderId;
        $prevRiderId = $order['delivery_boy_id'];

        $up = $pdo->prepare("
            UPDATE orders
            SET delivery_boy_id = ?,
                delivery_workflow_status = 'accepted',
                delivery_assigned_at = NOW(),
                delivery_accepted_at = NOW(),
                delivery_escalated = 0,
                order_status = CASE WHEN order_status = 'pending' THEN 'confirmed' ELSE order_status END
            WHERE id = ?
        ");
        $up->execute([$riderId, $orderId]);

        // Insert assignment record
        $ins = $pdo->prepare("
            INSERT INTO order_assignments (
                order_id, delivery_boy_id, status, assigned_by, offered_at, responded_at
            ) VALUES (?, ?, 'accepted', 'admin', NOW(), NOW())
        ");
        $ins->execute([$orderId, $riderId]);

        $title = $isReassignment ? 'Order Reassigned by Store Admin' : 'Order Assigned by Store Admin';
        $desc = "Store manager manually assigned this order to partner {$rider['name']} ({$rider['mobile']})." . ($notes ? " Note: {$notes}" : "");

        self::logTimeline($orderId, $riderId, 'admin_assigned', $title, $desc, 'admin', $adminId);

        return ['success' => true, 'message' => "Order #{$order['order_number']} successfully assigned to {$rider['name']}."];
    }

    /**
     * BLINKIT-STYLE DELIVERY WORKFLOW STATE MACHINE
     * Strict transitions:
     * 1. accepted -> reached_shop
     * 2. reached_shop -> picked_up
     * 3. picked_up -> out_for_delivery (Auto-generates delivery OTP)
     * 4. out_for_delivery -> delivered (Requires customer OTP or Admin override)
     */
    public static function updateWorkflowStatus(
        int $orderId,
        int $riderId,
        string $targetStatus,
        ?float $lat = null,
        ?float $lng = null,
        ?string $otp = null
    ): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT o.*, db.name AS rider_name, db.mobile AS rider_mobile
            FROM orders o
            LEFT JOIN delivery_boys db ON db.id = o.delivery_boy_id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ((int)$order['delivery_boy_id'] !== $riderId) {
            return ['success' => false, 'error' => 'You are not assigned to this order.'];
        }

        $currentStatus = $order['delivery_workflow_status'] ?? 'unassigned';

        // Update rider location if provided
        if ($lat !== null && $lng !== null) {
            DeliveryBoy::updateLocation($riderId, $lat, $lng, null, null, null, $orderId);
        }

        // Validate state transitions
        $allowedTransitions = [
            'accepted' => ['reached_shop'],
            'reached_shop' => ['picked_up'],
            'picked_up' => ['out_for_delivery'],
            'out_for_delivery' => ['delivered']
        ];

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($targetStatus, $allowedTransitions[$currentStatus], true)) {
            $expected = implode(', ', $allowedTransitions[$currentStatus] ?? ['none']);
            return [
                'success' => false,
                'error' => "Invalid status transition from '{$currentStatus}' to '{$targetStatus}'. Next expected step: {$expected}."
            ];
        }

        // Specific actions per transition
        if ($targetStatus === 'reached_shop') {
            $up = $pdo->prepare("UPDATE orders SET delivery_workflow_status = 'reached_shop' WHERE id = ?");
            $up->execute([$orderId]);

            self::logTimeline(
                $orderId,
                $riderId,
                'reached_shop',
                'Delivery Partner Reached Store',
                'Delivery partner has arrived at the store and is waiting for order packaging.',
                'delivery_boy',
                $riderId,
                $lat,
                $lng
            );

            return ['success' => true, 'message' => 'Status updated: Reached Store. Please collect the packed items.'];
        }

        if ($targetStatus === 'picked_up') {
            $up = $pdo->prepare("
                UPDATE orders
                SET delivery_workflow_status = 'picked_up',
                    delivery_picked_up_at = NOW()
                WHERE id = ?
            ");
            $up->execute([$orderId]);

            self::logTimeline(
                $orderId,
                $riderId,
                'picked_up',
                'Order Picked Up by Partner',
                'Fresh coconuts and fruits picked up from store counter. Partner preparing for dispatch.',
                'delivery_boy',
                $riderId,
                $lat,
                $lng
            );

            return ['success' => true, 'message' => 'Order picked up! Now start moving towards customer location.'];
        }

        if ($targetStatus === 'out_for_delivery') {
            // Generate OTP for customer delivery confirmation
            $generatedOtp = self::generateOtp($orderId);

            $up = $pdo->prepare("
                UPDATE orders
                SET delivery_workflow_status = 'out_for_delivery',
                    order_status = 'confirmed'
                WHERE id = ?
            ");
            $up->execute([$orderId]);

            self::logTimeline(
                $orderId,
                $riderId,
                'out_for_delivery',
                'Out for Delivery (Live GPS Tracking Active)',
                'Delivery partner is on the way to customer address. Live GPS location is active. Share delivery OTP with partner upon arrival.',
                'delivery_boy',
                $riderId,
                $lat,
                $lng
            );

            return [
                'success' => true,
                'message' => 'Delivery is now LIVE! GPS tracking is enabled for customer and store.',
                'otp_generated' => true
            ];
        }

        if ($targetStatus === 'delivered') {
            // Check COD payment status
            if ($order['payment_method'] === 'cod' && !in_array($order['payment_status'], ['paid', 'captured'], true)) {
                // Must have recorded COD cash collection before marking delivered
                $stmtCod = $pdo->prepare("SELECT id FROM delivery_cod_collections WHERE order_id = ?");
                $stmtCod->execute([$orderId]);
                if (!$stmtCod->fetch()) {
                    return [
                        'success' => false,
                        'error' => 'Please collect ₹' . number_format((float)$order['total_amount'], 2) . ' cash or verify payment before marking order as Delivered.',
                        'requires_cod_collection' => true
                    ];
                }
            }

            // Verify Customer Delivery OTP
            if (empty($otp)) {
                return ['success' => false, 'error' => 'Please enter the 4-6 digit Customer Delivery OTP.'];
            }

            $otpRes = self::verifyOtp($orderId, $otp);
            if (!$otpRes['success']) {
                return $otpRes;
            }

            // Mark order as completed & delivered
            $up = $pdo->prepare("
                UPDATE orders
                SET delivery_workflow_status = 'delivered',
                    order_status = 'delivered',
                    delivery_completed_at = NOW()
                WHERE id = ?
            ");
            $up->execute([$orderId]);

            self::logTimeline(
                $orderId,
                $riderId,
                'delivered',
                'Order Delivered Successfully',
                'Customer verified delivery via OTP. Order delivered and closed.',
                'delivery_boy',
                $riderId,
                $lat,
                $lng
            );

            return ['success' => true, 'message' => 'Congratulations! Order #' . $order['order_number'] . ' successfully delivered!'];
        }

        return ['success' => false, 'error' => 'Unknown status.'];
    }

    /**
     * GENERATE SECURE 4-DIGIT DELIVERY OTP
     */
    public static function generateOtp(int $orderId): string {
        $pdo = Database::getConnection();

        // 4-digit numeric OTP easy for customer to read on mobile
        $otp = sprintf('%04d', random_int(1000, 9999));
        $hash = password_hash($otp, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

        $stmt = $pdo->prepare("
            INSERT INTO delivery_otp_verifications (
                order_id, otp_code_hash, plain_otp_for_dev, attempts, expires_at, created_at
            ) VALUES (?, ?, ?, 0, ?, NOW())
            ON DUPLICATE KEY UPDATE
                otp_code_hash = VALUES(otp_code_hash),
                plain_otp_for_dev = VALUES(plain_otp_for_dev),
                attempts = 0,
                expires_at = VALUES(expires_at),
                verified_at = NULL,
                is_admin_override = 0
        ");
        $stmt->execute([$orderId, $hash, $otp, $expiresAt]);

        return $otp;
    }

    /**
     * VERIFY CUSTOMER DELIVERY OTP
     */
    public static function verifyOtp(int $orderId, string $enteredOtp): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM delivery_otp_verifications WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Generate on the fly if not existing
            self::generateOtp($orderId);
            return ['success' => false, 'error' => 'OTP was just generated. Please check with customer.'];
        }

        if (!empty($row['is_admin_override'])) {
            return ['success' => true, 'message' => 'Verified via Admin Emergency Override.'];
        }

        if (!empty($row['verified_at'])) {
            return ['success' => true, 'message' => 'OTP already verified.'];
        }

        if (strtotime($row['expires_at']) < time()) {
            return ['success' => false, 'error' => 'Customer Delivery OTP has expired. Please request a new OTP.'];
        }

        if ((int)$row['attempts'] >= (int)$row['max_attempts']) {
            return [
                'success' => false,
                'error' => 'Maximum OTP attempts exceeded (3/3). Please contact Store Admin for emergency verification.'
            ];
        }

        $enteredOtp = trim($enteredOtp);
        $isValid = password_verify($enteredOtp, $row['otp_code_hash']) || ($enteredOtp === $row['plain_otp_for_dev']);

        if (!$isValid) {
            $pdo->prepare("UPDATE delivery_otp_verifications SET attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);
            $remaining = (int)$row['max_attempts'] - ((int)$row['attempts'] + 1);
            return [
                'success' => false,
                'error' => "Invalid Delivery OTP. {$remaining} attempts remaining before account lock."
            ];
        }

        // Mark verified
        $pdo->prepare("UPDATE delivery_otp_verifications SET verified_at = NOW() WHERE id = ?")->execute([$row['id']]);

        return ['success' => true, 'message' => 'OTP verified successfully.'];
    }

    /**
     * ADMIN EMERGENCY OVERRIDE FOR DELIVERY CONFIRMATION
     * Mandatory audit reason stored in DB
     */
    public static function adminEmergencyOverrideOtp(int $orderId, int $adminId, string $reason): array {
        $pdo = Database::getConnection();

        $reason = trim($reason);
        if (strlen($reason) < 5) {
            return ['success' => false, 'error' => 'Please provide a clear justification reason for delivery override.'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO delivery_otp_verifications (
                order_id, otp_code_hash, plain_otp_for_dev, attempts, expires_at, verified_at,
                is_admin_override, override_reason, override_admin_id, created_at
            ) VALUES (?, 'OVERRIDE', '0000', 0, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW(), 1, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                verified_at = NOW(),
                is_admin_override = 1,
                override_reason = VALUES(override_reason),
                override_admin_id = VALUES(override_admin_id)
        ");
        $stmt->execute([$orderId, $reason, $adminId]);

        self::logTimeline(
            $orderId,
            null,
            'admin_override',
            'Delivery OTP Override by Admin',
            "Store Admin authorized delivery completion override. Reason: {$reason}",
            'admin',
            $adminId
        );

        return ['success' => true, 'message' => 'Delivery OTP successfully overridden by Admin.'];
    }

    /**
     * Get OTP information for display in customer tracking & admin view
     */
    public static function getOtpInfo(int $orderId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM delivery_otp_verifications WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * CASH ON DELIVERY (COD) COLLECTION BY RIDER
     */
    public static function recordCodCollection(int $orderId, int $riderId, float $amount, ?string $notes = null): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id, order_number, total_amount, payment_method, payment_status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        // Insert or update collection record
        $ins = $pdo->prepare("
            INSERT INTO delivery_cod_collections (
                order_id, delivery_boy_id, amount, status, collected_at, notes, created_at
            ) VALUES (?, ?, ?, 'collected_by_rider', NOW(), ?, NOW())
            ON DUPLICATE KEY UPDATE
                amount = VALUES(amount),
                notes = VALUES(notes),
                collected_at = NOW()
        ");
        $ins->execute([$orderId, $riderId, $amount, $notes]);

        // Update order payment status
        $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$orderId]);

        self::logTimeline(
            $orderId,
            $riderId,
            'cod_collected',
            'Cash Collected by Delivery Partner',
            "Delivery partner collected ₹" . number_format($amount, 2) . " cash from customer for order #{$order['order_number']}.",
            'delivery_boy',
            $riderId
        );

        return ['success' => true, 'message' => 'Cash of ₹' . number_format($amount, 2) . ' recorded successfully.'];
    }

    /**
     * ADMIN RECONCILIATION OF COD CASH
     */
    public static function reconcileCodCollection(int $collectionId, int $adminId, ?string $notes = null): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE delivery_cod_collections
            SET status = 'reconciled_with_admin',
                reconciled_at = NOW(),
                reconciled_by_admin_id = ?,
                notes = CONCAT(COALESCE(notes, ''), ' | Reconciled: ', ?)
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $notes ?? 'Settled in store cash box', $collectionId]);

        return ['success' => true, 'message' => 'Cash collection successfully reconciled with store register.'];
    }

    /**
     * Get all COD collections with filter
     */
    public static function getCodCollections(?string $status = null, ?int $riderId = null): array {
        $pdo = Database::getConnection();

        $where = [];
        $params = [];

        if ($status !== null) {
            $where[] = "c.status = ?";
            $params[] = $status;
        }
        if ($riderId !== null) {
            $where[] = "c.delivery_boy_id = ?";
            $params[] = $riderId;
        }

        $whereSql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

        $sql = "
            SELECT
                c.*,
                o.order_number,
                o.customer_name,
                o.customer_phone,
                o.total_amount AS order_total,
                db.name AS rider_name,
                db.mobile AS rider_mobile,
                adm.name AS reconciled_by_admin_name
            FROM delivery_cod_collections c
            JOIN orders o ON o.id = c.order_id
            JOIN delivery_boys db ON db.id = c.delivery_boy_id
            LEFT JOIN admins adm ON adm.id = c.reconciled_by_admin_id
            {$whereSql}
            ORDER BY c.collected_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Append record to delivery timeline
     */
    public static function logTimeline(
        int $orderId,
        ?int $riderId,
        string $event,
        string $title,
        ?string $description = null,
        string $actorType = 'delivery_boy',
        ?int $actorId = null,
        ?float $lat = null,
        ?float $lng = null
    ): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO delivery_timeline (
                    order_id, delivery_boy_id, event, title, description, lat, lng, actor_type, actor_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$orderId, $riderId, $event, $title, $description, $lat, $lng, $actorType, $actorId]);
        } catch (Throwable $e) {
            error_log('DeliveryDispatchService::logTimeline error: ' . $e->getMessage());
        }
    }

    /**
     * Get complete timeline for an order
     */
    public static function getTimeline(int $orderId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT t.*, db.name AS rider_name
            FROM delivery_timeline t
            LEFT JOIN delivery_boys db ON db.id = t.delivery_boy_id
            WHERE t.order_id = ?
            ORDER BY t.created_at ASC, t.id ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get active order currently assigned to a rider
     */
    public static function getRiderActiveOrder(int $riderId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT
                o.*,
                (
                    SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ')
                    FROM order_items oi
                    WHERE oi.order_id = o.id
                ) AS items_preview,
                (
                    SELECT SUM(oi.quantity)
                    FROM order_items oi
                    WHERE oi.order_id = o.id
                ) AS total_items_count
            FROM orders o
            WHERE o.delivery_boy_id = ?
              AND o.delivery_workflow_status IN ('accepted', 'reached_shop', 'picked_up', 'out_for_delivery')
            ORDER BY o.id DESC
            LIMIT 1
        ");
        $stmt->execute([$riderId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Get rider completed deliveries
     */
    public static function getRiderHistory(int $riderId, int $limit = 30): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.order_number,
                o.customer_name,
                o.customer_phone,
                o.delivery_address,
                o.delivery_area_name,
                o.total_amount,
                o.payment_method,
                o.payment_status,
                o.delivery_workflow_status,
                o.delivery_completed_at,
                c.amount AS cod_collected_amount,
                c.status AS cod_reconciliation_status
            FROM orders o
            LEFT JOIN delivery_cod_collections c ON c.order_id = o.id
            WHERE o.delivery_boy_id = ?
              AND o.delivery_workflow_status = 'delivered'
            ORDER BY o.delivery_completed_at DESC, o.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $riderId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compute realistic delivery distance (km) and driving ETA (minutes)
     * using Haversine formula + urban road tortuosity factor (1.3x)
     */
    public static function calculateEtaAndDistance(float $lat1, float $lon1, float $lat2, float $lon2): array {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $straightLineKm = $earthRadiusKm * $c;

        // Urban route road factor (actual roads are ~30% longer than straight line)
        $roadDistanceKm = round($straightLineKm * 1.3, 1);
        if ($roadDistanceKm < 0.1) {
            $roadDistanceKm = 0.1;
        }

        // Two-wheeler average speed in Indian cities ~ 20 km/h (0.33 km/min)
        $travelMinutes = ceil(($roadDistanceKm / 20.0) * 60);
        // Add 2 minutes for stop & parking
        $etaMinutes = max(3, (int)$travelMinutes + 2);

        return [
            'straight_line_km' => round($straightLineKm, 2),
            'road_distance_km' => $roadDistanceKm,
            'eta_minutes' => $etaMinutes,
            'formatted_distance' => $roadDistanceKm . ' km',
            'formatted_eta' => $etaMinutes . ' mins'
        ];
    }
}
