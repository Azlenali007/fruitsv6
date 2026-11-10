<?php
/**
 * TOTP (Time-Based One-Time Password) Service - RFC 6238
 * N.A Fresh Fruits & Coconuts
 *
 * Implements pure PHP Two-Factor Authentication compatible with Google Authenticator,
 * Microsoft Authenticator, Authy, and 1Password.
 */

class TotpService {
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const TIME_STEP = 30;
    private const DIGITS = 6;

    /**
     * Generate a random cryptographically secure Base32 secret key (16 characters / 80-bit)
     */
    public static function generateSecret(int $length = 16): string {
        $secret = '';
        $maxIndex = strlen(self::BASE32_CHARS) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $maxIndex)];
        }
        return $secret;
    }

    /**
     * Decode a Base32 string into binary
     */
    public static function base32Decode(string $b32): string {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        if (empty($b32)) {
            return '';
        }

        $binary = '';
        foreach (str_split($b32) as $char) {
            $val = strpos(self::BASE32_CHARS, $char);
            if ($val === false) continue;
            $binary .= sprintf('%05b', $val);
        }

        $bytes = '';
        $chunks = str_split($binary, 8);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }
        return $bytes;
    }

    /**
     * Calculate 6-digit TOTP code for given secret and timestamp
     */
    public static function getCode(string $secret, ?int $timestamp = null): string {
        $timestamp = $timestamp ?? time();
        $timeCounter = floor($timestamp / self::TIME_STEP);
        
        // Pack counter into 8-byte big-endian binary string
        $binaryCounter = pack('J', $timeCounter);

        $binarySecret = self::base32Decode($secret);
        if (empty($binarySecret)) {
            return '';
        }

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $binaryCounter, $binarySecret, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = substr($hash, $offset, 4);

        $code = unpack('N', $truncatedHash)[1] & 0x7FFFFFFF;
        $pin = $code % (10 ** self::DIGITS);

        return str_pad((string)$pin, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify submitted 6-digit code with time drift window (±1 step = ±30s)
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1): bool {
        $code = trim($code);
        if (strlen($code) !== self::DIGITS || !ctype_digit($code)) {
            return false;
        }

        $currentTime = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $expectedCode = self::getCode($secret, $currentTime + ($i * self::TIME_STEP));
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate standard otpauth:// URI for authenticator QR codes
     */
    public static function getProvisioningUri(string $secret, string $accountName, string $issuer = 'N.A Fresh Fruits'): string {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);
        return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }
}
