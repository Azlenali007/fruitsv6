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

    public static function login(string $loginId, string $password): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $loginId = trim($loginId);
        if (empty($loginId) || empty($password)) {
            return ['success' => false, 'error' => 'Please enter both Login ID and Password.'];
        }

        // Allow login by Login ID or Mobile number
        $rider = DeliveryBoy::getByLoginId($loginId) ?: DeliveryBoy::getByMobile($loginId);
        if (!$rider) {
            return ['success' => false, 'error' => 'Invalid delivery credentials.'];
        }

        if ($rider['status'] !== 'active') {
            return ['success' => false, 'error' => 'Your delivery partner account has been deactivated by Admin. Please contact store management.'];
        }

        if (!password_verify($password, $rider['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid delivery credentials.'];
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
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
    }

    public static function requireLogin(string $redirect = '/delivery/login'): void {
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
