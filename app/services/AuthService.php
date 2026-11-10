<?php
/**
 * Admin Authentication & Session Security Service
 * N.A Fresh Fruits & Coconuts
 */

class AuthService {
    private const SESSION_ADMIN_ID = 'na_admin_id';
    private const SESSION_ADMIN_USER = 'na_admin_username';
    private const SESSION_LAST_ACTIVITY = 'na_admin_last_activity';
    private const SESSION_LOGIN_TIME = 'na_admin_login_time';
    private const SESSION_2FA_PENDING = 'na_admin_2fa_pending';

    /**
     * Authenticate Admin credentials with Rate Limiting, Account Lockout, Argon2id, and 2FA
     */
    public static function login(string $username, string $password, string $ip, ?string $totpCode = null): array {
        // 1. IP-level throttling check
        if (Admin::isIpThrottled($ip)) {
            AdminAuditService::log('LOGIN_THROTTLED_IP', 'admin', null, ['ip' => $ip, 'username' => $username]);
            return [
                'success' => false,
                'error' => 'Too many failed login attempts from this network. Please wait 15 minutes before trying again.'
            ];
        }

        // 2. Find admin user
        $admin = Admin::findByUsername($username);
        if (!$admin) {
            Admin::recordLoginAttempt($ip, false);
            AdminAuditService::log('LOGIN_FAILED_UNKNOWN_USER', 'admin', null, ['ip' => $ip, 'attempted_username' => $username]);
            return [
                'success' => false,
                'error' => 'Invalid username or password.'
            ];
        }

        // 3. Account-level lockout check
        if (Admin::isAccountLocked($admin)) {
            $remaining = ceil(Admin::getLockoutRemainingSeconds($admin) / 60);
            AdminAuditService::log('LOGIN_ATTEMPT_LOCKED_ACCOUNT', 'admin', (string)$admin['id'], ['remaining_minutes' => $remaining]);
            return [
                'success' => false,
                'error' => "This administrator account is temporarily locked due to consecutive failed attempts. Please retry in {$remaining} minute(s)."
            ];
        }

        // 4. Verify password with transparent rehash
        if (!Admin::verifyPassword($password, $admin['password_hash'], (int)$admin['id'])) {
            Admin::recordLoginAttempt($ip, false);
            $failedCount = Admin::recordFailedLogin((int)$admin['id'], 5, 15);
            $remainingAttempts = max(0, 5 - $failedCount);
            
            AdminAuditService::log('LOGIN_FAILED_BAD_PASSWORD', 'admin', (string)$admin['id'], [
                'failed_count' => $failedCount,
                'remaining_attempts' => $remainingAttempts
            ]);

            if ($remainingAttempts === 0) {
                return [
                    'success' => false,
                    'error' => 'Account locked for 15 minutes due to 5 consecutive failed login attempts.'
                ];
            }

            return [
                'success' => false,
                'error' => "Invalid username or password. ({$remainingAttempts} attempt(s) remaining before temporary lockout)."
            ];
        }

        // 5. Check Two-Factor Authentication (TOTP)
        if (!empty($admin['two_factor_enabled']) && !empty($admin['two_factor_secret'])) {
            if (empty($totpCode)) {
                // Set temporary 2FA verification token valid for 5 minutes
                $_SESSION[self::SESSION_2FA_PENDING] = [
                    'admin_id' => $admin['id'],
                    'username' => $admin['username'],
                    'name' => $admin['name'],
                    'expires' => time() + 300
                ];
                return [
                    'success' => false,
                    'requires_2fa' => true,
                    'message' => 'Please enter the 6-digit verification code from your authenticator app.'
                ];
            }

            // Verify TOTP code
            require_once __DIR__ . '/TotpService.php';
            if (!TotpService::verifyCode($admin['two_factor_secret'], $totpCode)) {
                Admin::recordLoginAttempt($ip, false);
                AdminAuditService::log('LOGIN_FAILED_BAD_2FA', 'admin', (string)$admin['id']);
                return [
                    'success' => false,
                    'requires_2fa' => true,
                    'error' => 'Invalid or expired 2FA verification code. Please check your authenticator clock and try again.'
                ];
            }
        }

        // Login success: Clear failed attempts and reset temporary tokens
        Admin::resetFailedLogin((int)$admin['id']);
        Admin::clearLoginAttempts($ip);
        Admin::recordLoginAttempt($ip, true);
        Admin::updateLastLogin((int)$admin['id']);
        unset($_SESSION[self::SESSION_2FA_PENDING]);

        // Regenerate session ID to prevent session fixation attacks
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_ADMIN_ID] = (int)$admin['id'];
        $_SESSION[self::SESSION_ADMIN_USER] = $admin['username'];
        $_SESSION['na_admin_name'] = $admin['name'];
        $_SESSION['na_admin_role'] = $admin['role'] ?? 'admin';
        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
        $_SESSION[self::SESSION_LOGIN_TIME] = time();

        AdminAuditService::log('LOGIN_SUCCESS', 'admin', (string)$admin['id'], [
            'username' => $admin['username'],
            'ip' => $ip
        ], (int)$admin['id'], $admin['username']);

        return [
            'success' => true,
            'admin' => $admin
        ];
    }

    /**
     * Complete 2FA pending step
     */
    public static function complete2FA(string $totpCode, string $ip): array {
        $pending = $_SESSION[self::SESSION_2FA_PENDING] ?? null;
        if (!$pending || empty($pending['admin_id']) || time() > ($pending['expires'] ?? 0)) {
            unset($_SESSION[self::SESSION_2FA_PENDING]);
            return [
                'success' => false,
                'error' => 'Two-Factor verification session expired. Please sign in again.'
            ];
        }

        $admin = Admin::findById((int)$pending['admin_id']);
        if (!$admin || empty($admin['two_factor_secret'])) {
            unset($_SESSION[self::SESSION_2FA_PENDING]);
            return [
                'success' => false,
                'error' => 'Invalid administrator account.'
            ];
        }

        require_once __DIR__ . '/TotpService.php';
        if (!TotpService::verifyCode($admin['two_factor_secret'], $totpCode)) {
            AdminAuditService::log('LOGIN_FAILED_BAD_2FA', 'admin', (string)$admin['id']);
            return [
                'success' => false,
                'error' => 'Invalid or expired 6-digit verification code.'
            ];
        }

        unset($_SESSION[self::SESSION_2FA_PENDING]);

        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_ADMIN_ID] = (int)$admin['id'];
        $_SESSION[self::SESSION_ADMIN_USER] = $admin['username'];
        $_SESSION['na_admin_name'] = $admin['name'];
        $_SESSION['na_admin_role'] = $admin['role'] ?? 'admin';
        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
        $_SESSION[self::SESSION_LOGIN_TIME] = time();

        Admin::resetFailedLogin((int)$admin['id']);
        Admin::updateLastLogin((int)$admin['id']);

        AdminAuditService::log('LOGIN_SUCCESS_2FA', 'admin', (string)$admin['id'], [
            'username' => $admin['username'],
            'ip' => $ip
        ], (int)$admin['id'], $admin['username']);

        return [
            'success' => true,
            'admin' => $admin
        ];
    }

    public static function logout(): void {
        $adminId = self::id();
        $adminUser = $_SESSION[self::SESSION_ADMIN_USER] ?? 'unknown';

        if ($adminId) {
            AdminAuditService::log('LOGOUT', 'admin', (string)$adminId, [], $adminId, $adminUser);
        }

        unset(
            $_SESSION[self::SESSION_ADMIN_ID],
            $_SESSION[self::SESSION_ADMIN_USER],
            $_SESSION['na_admin_name'],
            $_SESSION['na_admin_role'],
            $_SESSION[self::SESSION_LAST_ACTIVITY],
            $_SESSION[self::SESSION_LOGIN_TIME],
            $_SESSION[self::SESSION_2FA_PENDING]
        );

        if (!headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public static function check(): bool {
        if (empty($_SESSION[self::SESSION_ADMIN_ID])) {
            return false;
        }

        // Verify session timeout
        $maxInactivity = (int)Setting::get('admin_session_timeout_minutes', '30') * 60;
        $maxLifetime = (int)Setting::get('admin_session_absolute_hours', '8') * 3600;

        $lastActivity = $_SESSION[self::SESSION_LAST_ACTIVITY] ?? 0;
        $loginTime = $_SESSION[self::SESSION_LOGIN_TIME] ?? 0;
        $currentTime = time();

        if ($lastActivity > 0 && ($currentTime - $lastActivity) > $maxInactivity) {
            self::logout();
            return false;
        }

        if ($loginTime > 0 && ($currentTime - $loginTime) > $maxLifetime) {
            self::logout();
            return false;
        }

        // Refresh activity timestamp
        $_SESSION[self::SESSION_LAST_ACTIVITY] = $currentTime;
        return true;
    }

    public static function id(): ?int {
        return !empty($_SESSION[self::SESSION_ADMIN_ID]) ? (int)$_SESSION[self::SESSION_ADMIN_ID] : null;
    }

    public static function adminId(): ?int {
        return self::id();
    }

    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        return Admin::findById((int)$_SESSION[self::SESSION_ADMIN_ID]);
    }

    public static function isSuperAdmin(): bool {
        if (!self::check()) {
            return false;
        }
        $role = $_SESSION['na_admin_role'] ?? null;
        if ($role === null) {
            $user = self::user();
            $role = $user['role'] ?? 'admin';
        }
        return in_array(strtolower($role), ['superadmin', 'super_admin'], true);
    }

    public static function requireAdmin(): void {
        // Strict role isolation: Delivery boy cannot access admin
        if (class_exists('DeliveryBoyAuthService') && DeliveryBoyAuthService::check() && !self::check()) {
            http_response_code(403);
            exit('Access Denied: Delivery Partner accounts cannot access Store Admin Panel.');
        }

        if (!self::check()) {
            $_SESSION['admin_intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /admin/login');
            exit;
        }
    }

    public static function requireSuperAdmin(): void {
        self::requireAdmin();
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            exit('Access Denied (403): This action requires Super Administrator privileges.');
        }
    }
}
