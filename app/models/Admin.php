<?php
/**
 * Admin Model & Auth Verification
 * N.A Fresh Fruits & Coconuts
 */

class Admin {
    /**
     * Get preferred secure password hashing algorithm
     */
    public static function getHashAlgorithm(): string {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /**
     * Hash password with Argon2id (or bcrypt fallback)
     */
    public static function hashPassword(string $password): string {
        $algo = self::getHashAlgorithm();
        $options = ($algo === PASSWORD_BCRYPT) ? ['cost' => 12] : ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];
        return password_hash($password, $algo, $options);
    }

    public static function findByUsername(string $username): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `username` = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function isIpThrottled(string $ip): bool {
        $pdo = Database::getConnection();
        // Allow max 5 failed attempts per IP in the last 15 minutes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM `login_attempts` 
            WHERE `ip_address` = ? AND `success` = 0 AND `attempted_at` > (NOW() - INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ip]);
        return (int)$stmt->fetchColumn() >= 5;
    }

    public static function clearLoginAttempts(string $ip): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM `login_attempts` WHERE `ip_address` = ?");
        $stmt->execute([$ip]);
    }

    public static function recordLoginAttempt(string $ip, bool $success): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO `login_attempts` (`ip_address`, `success`, `attempted_at`) VALUES (?, ?, NOW())");
        $stmt->execute([$ip, $success ? 1 : 0]);
    }

    public static function isAccountLocked(array $admin): bool {
        if (!empty($admin['lockout_until'])) {
            $lockoutTs = strtotime($admin['lockout_until']);
            return ($lockoutTs > time());
        }
        return false;
    }

    public static function getLockoutRemainingSeconds(array $admin): int {
        if (!empty($admin['lockout_until'])) {
            $remaining = strtotime($admin['lockout_until']) - time();
            return max(0, $remaining);
        }
        return 0;
    }

    public static function recordFailedLogin(int $adminId, int $maxAttempts = 5, int $lockoutMinutes = 15): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT `failed_attempts` FROM `admins` WHERE `id` = ?");
        $stmt->execute([$adminId]);
        $current = (int)$stmt->fetchColumn();
        $newCount = $current + 1;

        if ($newCount >= $maxAttempts) {
            $lockoutDate = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
            $lockStmt = $pdo->prepare("
                UPDATE `admins` 
                SET `failed_attempts` = ?, `lockout_until` = ?, `updated_at` = NOW()
                WHERE `id` = ?
            ");
            $lockStmt->execute([$newCount, $lockoutDate, $adminId]);
        } else {
            $updateStmt = $pdo->prepare("
                UPDATE `admins` 
                SET `failed_attempts` = ?, `updated_at` = NOW()
                WHERE `id` = ?
            ");
            $updateStmt->execute([$newCount, $adminId]);
        }

        return $newCount;
    }

    public static function resetFailedLogin(int $adminId): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE `admins` 
            SET `failed_attempts` = 0, `lockout_until` = NULL, `updated_at` = NOW()
            WHERE `id` = ?
        ");
        $stmt->execute([$adminId]);
    }

    public static function verifyPassword(string $password, string $hash, int $adminId): bool {
        if (!password_verify($password, $hash)) {
            return false;
        }

        // Automatic rehash if algorithm has changed (e.g. from bcrypt to Argon2id)
        $algo = self::getHashAlgorithm();
        if (password_needs_rehash($hash, $algo)) {
            $newHash = self::hashPassword($password);
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE `admins` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?");
            $stmt->execute([$newHash, $adminId]);
        }

        return true;
    }

    public static function updateLastLogin(int $id): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE `admins` SET `last_login_at` = NOW() WHERE `id` = ?");
        $stmt->execute([$id]);
    }

    public static function updatePassword(int $id, string $newPassword): bool {
        $hash = self::hashPassword($newPassword);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE `admins` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?");
        return $stmt->execute([$hash, $id]);
    }

    public static function enableTwoFactor(int $adminId, string $secret): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE `admins` 
            SET `two_factor_secret` = ?, `two_factor_enabled` = 1, `updated_at` = NOW()
            WHERE `id` = ?
        ");
        return $stmt->execute([$secret, $adminId]);
    }

    public static function disableTwoFactor(int $adminId): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE `admins` 
            SET `two_factor_secret` = NULL, `two_factor_enabled` = 0, `updated_at` = NOW()
            WHERE `id` = ?
        ");
        return $stmt->execute([$adminId]);
    }
}
