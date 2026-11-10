<?php
/**
 * Admin Activity Audit Service
 * N.A Fresh Fruits & Coconuts
 *
 * Records immutable audit trail of all sensitive administrator actions.
 */

class AdminAuditService {
    public static function log(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $details = null,
        ?int $adminId = null,
        ?string $adminUsername = null
    ): void {
        try {
            $pdo = Database::getConnection();
            
            // Resolve Admin identity
            if ($adminId === null) {
                $adminId = AuthService::id() ?? 0;
            }
            if ($adminUsername === null) {
                $adminUsername = $_SESSION['na_admin_username'] ?? 'system';
            }

            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

            $detailsJson = !empty($details) ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

            $stmt = $pdo->prepare("
                INSERT INTO `admin_activity_logs` 
                (`admin_id`, `admin_username`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $adminId,
                $adminUsername,
                $action,
                $entityType,
                $entityId !== null ? (string)$entityId : null,
                $detailsJson,
                $ip,
                $userAgent
            ]);
        } catch (Throwable $t) {
            // Audit logging failure should not break app flow, but log to error log
            error_log('AdminAuditService error: ' . $t->getMessage());
        }
    }

    public static function getRecentLogs(int $limit = 50, ?string $filterAction = null): array {
        try {
            $pdo = Database::getConnection();
            if (!empty($filterAction)) {
                $stmt = $pdo->prepare("
                    SELECT * FROM `admin_activity_logs` 
                    WHERE `action` = ?
                    ORDER BY `id` DESC LIMIT ?
                ");
                $stmt->bindValue(1, $filterAction, PDO::PARAM_STR);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM `admin_activity_logs` 
                    ORDER BY `id` DESC LIMIT ?
                ");
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $t) {
            return [];
        }
    }
}
