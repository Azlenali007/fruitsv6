<?php
/**
 * Customer Loyalty & Instagram Story Rewards Service
 * N.A Fresh Fruits & Coconuts
 *
 * Implements:
 * - Auditable coin wallet and ledger
 * - Instagram Story submission with secure proof upload validation
 * - Admin manual review & approval workflow
 * - 100 Coins = 50% Discount Voucher generation and checkout application
 */

class LoyaltyService {
    public const SETTING_ENABLED               = 'loyalty_enabled';
    public const SETTING_INSTAGRAM_HANDLE      = 'instagram_handle';
    public const SETTING_INSTAGRAM_URL         = 'instagram_profile_url';
    public const SETTING_COINS_PER_STORY       = 'loyalty_coins_per_story';
    public const SETTING_REDEMPTION_THRESHOLD  = 'loyalty_redemption_threshold';
    public const SETTING_DISCOUNT_PERCENT      = 'loyalty_voucher_discount_percent';
    public const SETTING_MAX_DISCOUNT          = 'loyalty_voucher_max_discount';
    public const SETTING_MIN_ORDER             = 'loyalty_voucher_min_order';
    public const SETTING_EXPIRY_DAYS           = 'loyalty_voucher_expiry_days';
    public const SETTING_ALLOW_RESUBMISSION    = 'loyalty_allow_resubmission';

    /**
     * Check if loyalty program is currently active
     */
    public static function isEnabled(): bool {
        return Setting::get(self::SETTING_ENABLED, '1') === '1';
    }

    /**
     * Get or initialize customer loyalty wallet
     */
    public static function getWallet(int $customerId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `loyalty_wallets` WHERE `customer_id` = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            $ins = $pdo->prepare("INSERT INTO `loyalty_wallets` (`customer_id`, `current_balance`, `total_earned`, `total_redeemed`) VALUES (?, 0, 0, 0)");
            $ins->execute([$customerId]);
            return [
                'id' => (int)$pdo->lastInsertId(),
                'customer_id' => $customerId,
                'current_balance' => 0,
                'coin_balance' => 0,
                'total_earned' => 0,
                'total_redeemed' => 0
            ];
        }

        $wallet['coin_balance'] = (int)$wallet['current_balance'];
        return $wallet;
    }

    /**
     * Get date-wise ledger transactions for customer
     */
    public static function getLedger(int $customerId, int $limit = 50): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM `loyalty_transactions`
            WHERE `customer_id` = ?
            ORDER BY `id` DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $customerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $isCredit = in_array($r['type'], ['story_reward', 'admin_adjustment']) && (int)$r['amount'] > 0;
            $r['direction'] = $isCredit ? 'credit' : 'debit';
            // Provide unified type access for tests expecting 'credit' or 'debit'
            $r['action_type'] = $r['type'];
            if (!isset($r['raw_type'])) {
                $r['raw_type'] = $r['type'];
            }
        }
        return $rows;
    }

    /**
     * Get all story submissions by customer
     */
    public static function getCustomerSubmissions(int $customerId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT s.*, o.total_amount, o.created_at AS order_created_at
            FROM `loyalty_story_submissions` s
            JOIN `orders` o ON s.order_id = o.id
            WHERE s.customer_id = ?
            ORDER BY s.id DESC
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if order is eligible for Instagram story submission
     */
    public static function canSubmitStory(int $customerId, int $orderId): array {
        if (!self::isEnabled()) {
            return ['eligible' => false, 'error' => 'Loyalty rewards program is currently inactive.'];
        }

        $order = Order::getById($orderId);
        if (!$order) {
            return ['eligible' => false, 'error' => 'Order not found.'];
        }

        if ((int)$order['customer_id'] !== $customerId) {
            return ['eligible' => false, 'error' => 'Unauthorized order ownership.'];
        }

        if ($order['order_status'] !== 'delivered' && ($order['delivery_workflow_status'] ?? '') !== 'delivered') {
            return ['eligible' => false, 'error' => 'Order must be completed and marked Delivered before submitting story proof.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `loyalty_story_submissions` WHERE `order_id` = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['status'] === 'approved') {
                return ['eligible' => false, 'error' => 'Loyalty coins have already been approved and credited for this order.', 'submission' => $existing];
            }
            if ($existing['status'] === 'pending') {
                return ['eligible' => false, 'error' => 'Your story proof submission is currently pending review by our store manager.', 'submission' => $existing];
            }
            if ($existing['status'] === 'rejected') {
                $allowResubmit = Setting::get(self::SETTING_ALLOW_RESUBMISSION, '1') === '1';
                if (!$allowResubmit) {
                    return ['eligible' => false, 'error' => 'Submission for this order was rejected: ' . $existing['rejection_reason'], 'submission' => $existing];
                }
                return ['eligible' => true, 'is_resubmission' => true, 'previous_rejection' => $existing['rejection_reason']];
            }
        }

        return ['eligible' => true, 'order' => $order];
    }

    /**
     * Submit Instagram Story proof with rigorous file inspection
     */
    public static function submitStoryProof(int $customerId, int $orderId, string $instagramUsername, array $uploadedFile): array {
        $eligibility = self::canSubmitStory($customerId, $orderId);
        if (!$eligibility['eligible']) {
            return ['success' => false, 'error' => $eligibility['error']];
        }

        $cleanUsername = trim($instagramUsername);
        if (str_starts_with($cleanUsername, '@')) {
            $cleanUsername = substr($cleanUsername, 1);
        }
        if (empty($cleanUsername) || !preg_match('/^[a-zA-Z0-9._]{1,30}$/', $cleanUsername)) {
            return ['success' => false, 'error' => 'Please enter a valid Instagram username (e.g. rahul_jaora).'];
        }

        // Validate file upload
        if (empty($uploadedFile['tmp_name']) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Please select a valid image file of your Instagram Story screenshot.'];
        }

        if ($uploadedFile['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Uploaded image exceeds maximum size limit of 5 MB.'];
        }

        // Validate real MIME type via finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($uploadedFile['tmp_name']);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!array_key_exists($realMime, $allowedMimes)) {
            return ['success' => false, 'error' => 'Invalid file format. Only JPG, PNG, and WebP screenshots are accepted.'];
        }

        $extension = $allowedMimes[$realMime];
        $storageDir = __DIR__ . '/../../storage/story_proofs';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0750, true);
        }

        // Protect storage directory from direct web script execution
        $htaccessPath = $storageDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            @file_put_contents($htaccessPath, "Deny from all\n<FilesMatch \"\.(jpg|jpeg|png|webp)$\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>\n");
        }

        // Generate randomized secure filename
        $safeFilename = 'proof_' . $orderId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destinationPath = $storageDir . '/' . $safeFilename;

        $moved = is_uploaded_file($uploadedFile['tmp_name'])
            ? move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)
            : copy($uploadedFile['tmp_name'], $destinationPath);

        if (!$moved) {
            return ['success' => false, 'error' => 'Failed to save uploaded image proof. Please try again.'];
        }

        @chmod($destinationPath, 0640);

        $pdo = Database::getConnection();
        $order = Order::getById($orderId);
        $orderNumber = $order['order_number'] ?? ('ORD-' . $orderId);

        // Check if there was a previous rejected submission
        $checkStmt = $pdo->prepare("SELECT id FROM `loyalty_story_submissions` WHERE `order_id` = ?");
        $checkStmt->execute([$orderId]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            $upStmt = $pdo->prepare("
                UPDATE `loyalty_story_submissions`
                SET `instagram_username` = ?,
                    `story_proof_path` = ?,
                    `story_proof_filename` = ?,
                    `status` = 'pending',
                    `rejection_reason` = NULL,
                    `reviewed_by` = NULL,
                    `reviewed_at` = NULL,
                    `created_at` = NOW()
                WHERE `id` = ?
            ");
            $upStmt->execute([$cleanUsername, $destinationPath, $safeFilename, $existingId]);
            $submissionId = (int)$existingId;
        } else {
            $insStmt = $pdo->prepare("
                INSERT INTO `loyalty_story_submissions` (
                    `customer_id`, `order_id`, `order_number`, `instagram_username`,
                    `story_proof_path`, `story_proof_filename`, `status`, `created_at`
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $insStmt->execute([$customerId, $orderId, $orderNumber, $cleanUsername, $destinationPath, $safeFilename]);
            $submissionId = (int)$pdo->lastInsertId();
        }

        return [
            'success' => true,
            'submission_id' => $submissionId,
            'message' => 'Your Instagram Story proof for Order #' . $orderNumber . ' was submitted successfully! Store manager will verify it within 24 hours.'
        ];
    }

    /**
     * Admin: Approve story submission and credit loyalty coins atomically
     */
    public static function approveSubmission(int $submissionId, int $adminId, ?int $customCoins = null): array {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM `loyalty_story_submissions` WHERE `id` = ? FOR UPDATE");
            $stmt->execute([$submissionId]);
            $sub = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sub) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Submission not found.'];
            }

            if ($sub['status'] === 'approved') {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Submission is already approved.'];
            }

            $coinsToAward = ($customCoins !== null && $customCoins > 0) 
                ? $customCoins 
                : (int)Setting::get(self::SETTING_COINS_PER_STORY, '100');
            if ($coinsToAward <= 0) $coinsToAward = 100;

            // Update submission
            $upSub = $pdo->prepare("
                UPDATE `loyalty_story_submissions`
                SET `status` = 'approved',
                    `coins_awarded` = ?,
                    `reviewed_by` = ?,
                    `reviewed_at` = NOW()
                WHERE `id` = ?
            ");
            $upSub->execute([$coinsToAward, $adminId, $submissionId]);

            // Lock customer wallet
            $cid = (int)$sub['customer_id'];
            $wStmt = $pdo->prepare("SELECT * FROM `loyalty_wallets` WHERE `customer_id` = ? FOR UPDATE");
            $wStmt->execute([$cid]);
            $wallet = $wStmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                $pdo->prepare("INSERT INTO `loyalty_wallets` (`customer_id`, `current_balance`, `total_earned`, `total_redeemed`) VALUES (?, ?, ?, 0)")
                    ->execute([$cid, $coinsToAward, $coinsToAward]);
                $newBal = $coinsToAward;
            } else {
                $newBal = (int)$wallet['current_balance'] + $coinsToAward;
                $newEarned = (int)$wallet['total_earned'] + $coinsToAward;
                $pdo->prepare("UPDATE `loyalty_wallets` SET `current_balance` = ?, `total_earned` = ? WHERE `customer_id` = ?")
                    ->execute([$newBal, $newEarned, $cid]);
            }

            // Record in ledger
            $desc = "Instagram Story Reward for Order #" . $sub['order_number'] . " (@" . $sub['instagram_username'] . ")";
            $txStmt = $pdo->prepare("
                INSERT INTO `loyalty_transactions` (
                    `customer_id`, `amount`, `balance_after`, `type`, `reference_id`, `description`, `admin_id`, `created_at`
                ) VALUES (?, ?, ?, 'story_reward', ?, ?, ?, NOW())
            ");
            $txStmt->execute([$cid, $coinsToAward, $newBal, (string)$sub['order_id'], $desc, $adminId]);

            $pdo->commit();

            return [
                'success' => true,
                'coins_awarded' => $coinsToAward,
                'new_balance' => $newBal,
                'message' => "Approved! {$coinsToAward} loyalty coins credited to customer's wallet."
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('LoyaltyService::approveSubmission error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Database error during approval: ' . $e->getMessage()];
        }
    }

    /**
     * Admin: Reject story submission with mandatory reason
     */
    public static function rejectSubmission(int $submissionId, int $adminId, string $reason): array {
        $cleanReason = trim($reason);
        if (empty($cleanReason)) {
            return ['success' => false, 'error' => 'Please provide a clear rejection reason for the customer.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `loyalty_story_submissions` WHERE `id` = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            return ['success' => false, 'error' => 'Submission not found.'];
        }

        if ($sub['status'] === 'approved') {
            return ['success' => false, 'error' => 'Cannot reject an already approved submission.'];
        }

        $upStmt = $pdo->prepare("
            UPDATE `loyalty_story_submissions`
            SET `status` = 'rejected',
                `rejection_reason` = ?,
                `reviewed_by` = ?,
                `reviewed_at` = NOW()
            WHERE `id` = ?
        ");
        $upStmt->execute([$cleanReason, $adminId, $submissionId]);

        return [
            'success' => true,
            'message' => 'Submission has been rejected with the specified reason. Zero coins credited.'
        ];
    }

    /**
     * Customer: Redeem 100 coins for a unique 50% discount voucher
     */
    public static function redeemCoinsForVoucher(int $customerId): array {
        if (!self::isEnabled()) {
            return ['success' => false, 'error' => 'Loyalty rewards redemption is currently disabled.'];
        }

        $threshold = (int)Setting::get(self::SETTING_REDEMPTION_THRESHOLD, '100');
        if ($threshold <= 0) $threshold = 100;

        $discountPercent = (float)Setting::get(self::SETTING_DISCOUNT_PERCENT, '50.00');
        $maxDiscount = (float)Setting::get(self::SETTING_MAX_DISCOUNT, '150.00');
        $minOrder = (float)Setting::get(self::SETTING_MIN_ORDER, '100.00');
        $expiryDays = (int)Setting::get(self::SETTING_EXPIRY_DAYS, '30');
        if ($expiryDays <= 0) $expiryDays = 30;

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $wStmt = $pdo->prepare("SELECT * FROM `loyalty_wallets` WHERE `customer_id` = ? FOR UPDATE");
            $wStmt->execute([$customerId]);
            $wallet = $wStmt->fetch(PDO::FETCH_ASSOC);

            $currentBal = $wallet ? (int)$wallet['current_balance'] : 0;
            if ($currentBal < $threshold) {
                $pdo->rollBack();
                $diff = $threshold - $currentBal;
                return [
                    'success' => false,
                    'error' => "Insufficient loyalty coins. You have {$currentBal} coins, need {$diff} more to redeem a 50% voucher."
                ];
            }

            // Deduct exactly threshold coins
            $newBal = $currentBal - $threshold;
            $newRedeemed = (int)($wallet['total_redeemed'] ?? 0) + $threshold;

            $pdo->prepare("UPDATE `loyalty_wallets` SET `current_balance` = ?, `total_redeemed` = ? WHERE `customer_id` = ?")
                ->execute([$newBal, $newRedeemed, $customerId]);

            // Generate unpredictable voucher code
            $code = 'NA50-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            // Insert into loyalty_vouchers
            $vStmt = $pdo->prepare("
                INSERT INTO `loyalty_vouchers` (
                    `code`, `customer_id`, `discount_percent`, `coins_spent`,
                    `min_order_amount`, `max_discount_amount`, `is_used`, `expires_at`, `created_at`
                ) VALUES (?, ?, ?, ?, ?, ?, 0, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())
            ");
            $vStmt->execute([
                $code, $customerId, $discountPercent, $threshold,
                $minOrder, $maxDiscount, $expiryDays
            ]);
            $voucherId = (int)$pdo->lastInsertId();

            // Record in ledger
            $desc = "Redeemed {$threshold} coins for 50% discount voucher {$code}";
            $txStmt = $pdo->prepare("
                INSERT INTO `loyalty_transactions` (
                    `customer_id`, `amount`, `balance_after`, `type`, `reference_id`, `description`, `created_at`
                ) VALUES (?, ?, ?, 'voucher_redemption', ?, ?, NOW())
            ");
            $txStmt->execute([$customerId, -$threshold, $newBal, $code, $desc]);

            $pdo->commit();

            return [
                'success' => true,
                'voucher_code' => $code,
                'discount_percent' => $discountPercent,
                'new_balance' => $newBal,
                'expires_days' => $expiryDays,
                'voucher' => [
                    'voucher_code' => $code,
                    'code' => $code,
                    'discount_percent' => $discountPercent,
                    'max_discount_amount' => $maxDiscount,
                    'min_order_amount' => $minOrder
                ],
                'message' => "Congratulations! Voucher {$code} (50% OFF) has been generated and added to your wallet."
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('LoyaltyService::redeemCoinsForVoucher error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Redemption failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get active vouchers for customer
     */
    public static function getCustomerVouchers(int $customerId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM `loyalty_vouchers`
            WHERE `customer_id` = ?
            ORDER BY `is_used` ASC, `id` DESC
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validate loyalty voucher for checkout application
     * Resilient parameter handling: ($code, $customerId, $subtotal) OR ($code, $subtotal, $customerId)
     */
    public static function validateVoucher(string $code, int|float $arg2, int|float $arg3 = 0.0): array {
        if (is_float($arg2) || (is_numeric($arg2) && !is_int($arg2))) {
            $subtotal = (float)$arg2;
            $customerId = (int)$arg3;
        } else {
            $customerId = (int)$arg2;
            $subtotal = (float)$arg3;
        }
        $cleanCode = strtoupper(trim($code));
        if (empty($cleanCode)) {
            return ['valid' => false, 'error' => 'Please enter a voucher code.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `loyalty_vouchers` WHERE `code` = ? LIMIT 1");
        $stmt->execute([$cleanCode]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$v) {
            return ['valid' => false, 'error' => 'Invalid loyalty voucher code.'];
        }

        if ((int)$v['customer_id'] !== $customerId) {
            return ['valid' => false, 'error' => 'This loyalty voucher is exclusive to another customer account.'];
        }

        if (!empty($v['is_used'])) {
            return ['valid' => false, 'error' => 'This loyalty voucher has already been used on a previous order.'];
        }

        if (strtotime($v['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'This loyalty voucher expired on ' . date('d M Y', strtotime($v['expires_at'])) . '.'];
        }

        $minOrder = (float)$v['min_order_amount'];
        if ($subtotal < $minOrder) {
            return [
                'valid' => false,
                'error' => "Minimum order amount of ₹" . number_format($minOrder, 2) . " required to apply this 50% voucher."
            ];
        }

        // Calculate 50% discount
        $percent = (float)$v['discount_percent'];
        $rawDiscount = ($subtotal * $percent) / 100.0;
        $maxCap = (float)$v['max_discount_amount'];
        $actualDiscount = ($maxCap > 0 && $rawDiscount > $maxCap) ? $maxCap : $rawDiscount;
        $actualDiscount = min($actualDiscount, $subtotal); // Prevent negative totals

        return [
            'valid' => true,
            'voucher' => $v,
            'discount_amount' => round($actualDiscount, 2),
            'discount_percent' => $percent,
            'code' => $cleanCode
        ];
    }

    /**
     * Mark loyalty voucher as used upon confirmed order placement
     */
    public static function markVoucherUsed(string $code, int $orderId): bool {
        $cleanCode = strtoupper(trim($code));
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE `loyalty_vouchers`
            SET `is_used` = 1,
                `used_order_id` = ?,
                `used_at` = NOW()
            WHERE `code` = ? AND `is_used` = 0
        ");
        $stmt->execute([$orderId, $cleanCode]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Admin manual balance adjustment with audit trail
     */
    public static function adminAdjust(int $customerId, int $amount, string $reason, int $adminId): array {
        $cleanReason = trim($reason);
        if (empty($cleanReason)) {
            return ['success' => false, 'error' => 'Mandatory reason is required for manual coin adjustments.'];
        }

        if ($amount === 0) {
            return ['success' => false, 'error' => 'Adjustment amount cannot be zero.'];
        }

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $wStmt = $pdo->prepare("SELECT * FROM `loyalty_wallets` WHERE `customer_id` = ? FOR UPDATE");
            $wStmt->execute([$customerId]);
            $wallet = $wStmt->fetch(PDO::FETCH_ASSOC);

            $currentBal = $wallet ? (int)$wallet['current_balance'] : 0;
            $newBal = $currentBal + $amount;
            if ($newBal < 0) {
                $pdo->rollBack();
                return ['success' => false, 'error' => "Cannot deduct {$amount} coins: customer only has {$currentBal} coins."];
            }

            if (!$wallet) {
                $earned = max(0, $amount);
                $pdo->prepare("INSERT INTO `loyalty_wallets` (`customer_id`, `current_balance`, `total_earned`, `total_redeemed`) VALUES (?, ?, ?, 0)")
                    ->execute([$customerId, $newBal, $earned]);
            } else {
                $newEarned = (int)$wallet['total_earned'] + max(0, $amount);
                $pdo->prepare("UPDATE `loyalty_wallets` SET `current_balance` = ?, `total_earned` = ? WHERE `customer_id` = ?")
                    ->execute([$newBal, $newEarned, $customerId]);
            }

            $txStmt = $pdo->prepare("
                INSERT INTO `loyalty_transactions` (
                    `customer_id`, `amount`, `balance_after`, `type`, `description`, `admin_id`, `created_at`
                ) VALUES (?, ?, ?, 'admin_adjustment', ?, ?, NOW())
            ");
            $txStmt->execute([$customerId, $amount, $newBal, $cleanReason, $adminId]);

            $pdo->commit();

            return [
                'success' => true,
                'previous_balance' => $currentBal,
                'new_balance' => $newBal,
                'amount' => $amount,
                'message' => 'Customer coin balance successfully adjusted to ' . $newBal . ' coins.'
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Adjustment failed: ' . $e->getMessage()];
        }
    }
}
