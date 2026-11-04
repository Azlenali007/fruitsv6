<?php
/**
 * Admin Authentication Service
 * N.A Fresh Fruits & Coconuts
 */

class AuthService {
    private const SESSION_ADMIN_ID = 'na_admin_id';
    private const SESSION_ADMIN_USER = 'na_admin_username';

    public static function login(string $username, string $password, string $ip): array {
        if (Admin::isIpThrottled($ip)) {
            return [
                'success' => false,
                'error' => 'Too many failed login attempts. Please wait 15 minutes before trying again.'
            ];
        }

        $admin = Admin::findByUsername($username);
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            Admin::recordLoginAttempt($ip, false);
            return [
                'success' => false,
                'error' => 'Invalid username or password.'
            ];
        }

        // Login success: regenerate session ID to prevent session fixation attacks
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_ADMIN_ID] = $admin['id'];
        $_SESSION[self::SESSION_ADMIN_USER] = $admin['username'];
        $_SESSION['na_admin_name'] = $admin['name'];

        Admin::recordLoginAttempt($ip, true);
        Admin::updateLastLogin($admin['id']);

        return [
            'success' => true,
            'admin' => $admin
        ];
    }

    public static function logout(): void {
        unset($_SESSION[self::SESSION_ADMIN_ID]);
        unset($_SESSION[self::SESSION_ADMIN_USER]);
        unset($_SESSION['na_admin_name']);
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public static function check(): bool {
        return !empty($_SESSION[self::SESSION_ADMIN_ID]);
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

    public static function requireAdmin(): void {
        // Strict role isolation: If logged in as a Delivery Boy and not an Admin, deny access immediately
        if (class_exists('DeliveryBoyAuthService') && DeliveryBoyAuthService::check() && !self::check()) {
            http_response_code(403);
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Access Denied - N.A Fresh Fruits & Coconuts</title>
                <script src="https://cdn.tailwindcss.com"></script>
            </head>
            <body class="bg-slate-900 text-white min-h-screen flex items-center justify-center p-4">
                <div class="max-w-md w-full bg-slate-800 border border-red-500/30 rounded-3xl p-6 text-center space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-full bg-red-950/80 text-red-400 flex items-center justify-center border border-red-500/40">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <h1 class="text-xl font-bold text-red-200">Access Denied (403)</h1>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Delivery Partner accounts do not have administrator permissions to access the Store Admin Panel.
                    </p>
                    <div class="pt-2 flex flex-col gap-2">
                        <a href="/delivery/dashboard" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition">
                            Return to Delivery Partner Dashboard
                        </a>
                        <form action="/delivery/logout" method="POST">
                            <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                            <button type="submit" class="w-full py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 font-semibold text-xs rounded-xl transition">
                                Logout Delivery Account
                            </button>
                        </form>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        if (!self::check()) {
            $_SESSION['admin_intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /admin/login');
            exit;
        }
    }
}
