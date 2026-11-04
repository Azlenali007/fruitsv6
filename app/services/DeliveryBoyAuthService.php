<?php
/**
 * DeliveryBoyAuthService
 * N.A Fresh Fruits & Coconuts
 */

class DeliveryBoyAuthService {
    public const SESSION_ID     = 'delivery_boy_id';
    public const SESSION_NAME   = 'delivery_boy_name';
    public const SESSION_MOBILE = 'delivery_boy_mobile';
    public const SESSION_LOGIN  = 'delivery_boy_login_id';

    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION[self::SESSION_ID]);
    }

    public static function riderId(): ?int {
        if (!self::check()) {
            return null;
        }
        return (int)$_SESSION[self::SESSION_ID];
    }

    public static function rider(): ?array {
        $id = self::riderId();
        if (!$id) {
            return null;
        }
        return DeliveryBoy::getById($id);
    }

    public static function isThrottled(string $identifier, string $ip): bool {
        try {
            $pdo = Database::getConnection();
            // 5 failed attempts on the specific identifier (login_id or mobile)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM `delivery_boy_login_attempts`
                WHERE `identifier` = ?
                  AND `success` = 0
                  AND `attempted_at` > (NOW() - INTERVAL 15 MINUTE)
            ");
            $stmt->execute([trim($identifier)]);
            if (((int)$stmt->fetchColumn()) >= 5) {
                return true;
            }

            // Or 15 failed attempts from the same IP across multiple accounts
            $stmtIp = $pdo->prepare("
                SELECT COUNT(*) FROM `delivery_boy_login_attempts`
                WHERE `ip_address` = ?
                  AND `success` = 0
                  AND `attempted_at` > (NOW() - INTERVAL 15 MINUTE)
            ");
            $stmtIp->execute([trim($ip)]);
            return ((int)$stmtIp->fetchColumn()) >= 15;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function clearLoginAttempts(string $identifier, string $ip): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM `delivery_boy_login_attempts` WHERE `identifier` = ? OR `ip_address` = ?");
            $stmt->execute([trim($identifier), trim($ip)]);
        } catch (Exception $e) {
            // non-blocking
        }
    }

    public static function recordLoginAttempt(string $identifier, string $ip, bool $success): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO `delivery_boy_login_attempts` (`identifier`, `ip_address`, `attempted_at`, `success`)
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt->execute([trim($identifier), trim($ip), $success ? 1 : 0]);
        } catch (Exception $e) {
            // non-blocking
        }
    }

    public static function login(string $loginId, string $password, ?string $ip = null): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $loginId = trim($loginId);
        $ip = $ip ?: ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        if (empty($loginId) || empty($password)) {
            return ['success' => false, 'error' => 'Please enter both Login ID and Password.'];
        }

        if (self::isThrottled($loginId, $ip)) {
            return [
                'success' => false,
                'error' => 'Too many failed login attempts. Please wait 15 minutes before trying again.'
            ];
        }

        // Allow login by Login ID or Mobile number
        $rider = DeliveryBoy::getByLoginId($loginId) ?: DeliveryBoy::getByMobile($loginId);
        if (!$rider) {
            self::recordLoginAttempt($loginId, $ip, false);
            return ['success' => false, 'error' => 'Invalid delivery credentials.'];
        }

        if ($rider['status'] !== 'active') {
            self::recordLoginAttempt($loginId, $ip, false);
            return ['success' => false, 'error' => 'Your delivery partner account has been deactivated by Admin. Please contact store management.'];
        }

        if (!password_verify($password, $rider['password_hash'])) {
            self::recordLoginAttempt($loginId, $ip, false);
            return ['success' => false, 'error' => 'Invalid delivery credentials.'];
        }

        // Successful authentication
        self::recordLoginAttempt($loginId, $ip, true);
        self::clearLoginAttempts($loginId, $ip);

        // Regenerate session ID safely to prevent session fixation
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_ID]     = (int)$rider['id'];
        $_SESSION[self::SESSION_NAME]   = $rider['name'];
        $_SESSION[self::SESSION_MOBILE] = $rider['mobile'];
        $_SESSION[self::SESSION_LOGIN]  = $rider['login_id'];

        // Automatically set rider as Online on successful login
        DeliveryBoy::setOnlineStatus((int)$rider['id'], true);

        return ['success' => true, 'rider' => $rider];
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $riderId = self::riderId();
        if ($riderId) {
            // Set rider offline upon explicit logout
            DeliveryBoy::setOnlineStatus($riderId, false);
        }

        unset(
            $_SESSION[self::SESSION_ID],
            $_SESSION[self::SESSION_NAME],
            $_SESSION[self::SESSION_MOBILE],
            $_SESSION[self::SESSION_LOGIN]
        );

        if (!headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public static function requireLogin(string $redirect = '/delboy'): void {
        if (!self::check()) {
            $_SESSION['delivery_redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/delivery/dashboard';
            header('Location: ' . $redirect);
            exit;
        }

        // Verify account is still active in database
        $rider = self::rider();
        if (!$rider || $rider['status'] !== 'active') {
            self::logout();
            set_flash('error', 'Your delivery partner account is not active.');
            header('Location: ' . $redirect);
            exit;
        }
    }
}
