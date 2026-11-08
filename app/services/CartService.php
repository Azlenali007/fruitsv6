<?php
/**
 * Cart Service
 * N.A Fresh Fruits & Coconuts
 * Supports multi-product cart, coconut cutting/preparation options, combo bundles,
 * stock caps, and server-authoritative discount & checkout totals.
 */

class CartService {
    public const SESSION_KEY = 'na_cart';
    public const COUPON_SESSION_KEY = 'na_applied_coupon';

    /**
     * Normalize cart session items into structured verified items
     */
    public static function getCart(): array {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $rawItems = $_SESSION[self::SESSION_KEY];
        $verifiedItems = [];
        $subtotal = 0.00;
        $totalItemsCount = 0;

        foreach ($rawItems as $key => $entry) {
            // Support legacy format: $key = (int)$productId, $entry = (int)$qty
            if (is_numeric($key) && is_numeric($entry)) {
                $type = 'product';
                $id = (int)$key;
                $qty = (int)$entry;
                $optionId = null;
            } elseif (is_array($entry)) {
                $type = $entry['type'] ?? 'product';
                $id = (int)($entry['id'] ?? 0);
                $qty = (int)($entry['quantity'] ?? 1);
                $optionId = $entry['option_id'] ?? null;
            } else {
                continue;
            }

            if ($qty <= 0 || $id <= 0) {
                unset($_SESSION[self::SESSION_KEY][$key]);
                continue;
            }

            if ($type === 'combo') {
                $combo = Combo::getById($id);
                if (!$combo || !$combo['is_available'] || (int)$combo['stock_quantity'] <= 0) {
                    unset($_SESSION[self::SESSION_KEY][$key]);
                    continue;
                }

                $safeQty = min($qty, (int)$combo['stock_quantity']);
                if ($safeQty <= 0) {
                    unset($_SESSION[self::SESSION_KEY][$key]);
                    continue;
                }

                $unitPrice = (float)$combo['price'];
                $lineTotal = round($unitPrice * $safeQty, 2);
                $subtotal += $lineTotal;
                $totalItemsCount += $safeQty;

                $itemKey = 'combo_' . $id;

                $verifiedItems[] = [
                    'key' => $itemKey,
                    'type' => 'combo',
                    'is_combo' => true,
                    'product_id' => 0,
                    'combo_id' => $combo['id'],
                    'name' => $combo['name'],
                    'tagline' => $combo['tagline'] ?: 'Special Offer Bundle',
                    'price' => $unitPrice,
                    'unit_price' => $unitPrice,
                    'original_price' => (float)$combo['original_price'],
                    'image_url' => $combo['image_url'],
                    'quantity' => $safeQty,
                    'max_stock' => (int)$combo['stock_quantity'],
                    'preparation_option' => null,
                    'preparation_charge' => 0.00,
                    'preparation_option_id' => null,
                    'combo_items' => $combo['items'],
                    'subtotal' => $lineTotal
                ];
            } else {
                // Standard Product
                $product = Product::getById($id);
                if (!$product || !$product['is_available'] || (int)$product['stock_quantity'] <= 0) {
                    unset($_SESSION[self::SESSION_KEY][$key]);
                    continue;
                }

                $safeQty = min($qty, (int)$product['stock_quantity']);
                if ($safeQty <= 0) {
                    unset($_SESSION[self::SESSION_KEY][$key]);
                    continue;
                }

                // Check cutting option if applicable
                $optVal = Product::validateCuttingOption($product, $optionId);
                $basePrice = (float)$product['price'];
                $optCharge = $optVal['has_option'] ? (float)$optVal['extra_charge'] : 0.00;
                $unitPrice = $basePrice + $optCharge;
                $lineTotal = round($unitPrice * $safeQty, 2);
                $subtotal += $lineTotal;
                $totalItemsCount += $safeQty;

                $itemKey = self::makeProductKey($id, $optVal['option_id']);

                $verifiedItems[] = [
                    'key' => $itemKey,
                    'type' => 'product',
                    'is_combo' => false,
                    'product_id' => $product['id'],
                    'combo_id' => null,
                    'name' => $product['name'],
                    'tagline' => $product['tagline'],
                    'base_price' => $basePrice,
                    'price' => $unitPrice,
                    'unit_price' => $unitPrice,
                    'original_price' => !empty($product['original_price']) ? (float)$product['original_price'] : null,
                    'image_url' => $product['image_url'],
                    'quantity' => $safeQty,
                    'max_stock' => (int)$product['stock_quantity'],
                    'preparation_option' => $optVal['option_name'],
                    'preparation_charge' => $optCharge,
                    'preparation_option_id' => $optVal['option_id'],
                    'store_type' => $product['store_type'] ?? 'coconut',
                    'subtotal' => $lineTotal
                ];
            }
        }

        return [
            'items' => $verifiedItems,
            'count' => $totalItemsCount,
            'subtotal' => round($subtotal, 2)
        ];
    }

    public static function makeProductKey(int $productId, ?string $optionId): string {
        return 'prod_' . $productId . '_' . ($optionId ? preg_replace('~[^a-zA-Z0-9_-]~', '', $optionId) : 'default');
    }

    /**
     * Add standard product with optional cutting option
     */
    public static function addItem(int $productId, int $quantity = 1, ?string $optionId = null): array {
        $product = Product::getById($productId);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.'];
        }
        if (!$product['is_available'] || (int)$product['stock_quantity'] <= 0) {
            return ['success' => false, 'error' => 'This product is currently out of stock.'];
        }

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        // Validate option
        $optVal = Product::validateCuttingOption($product, $optionId);
        $finalOptId = $optVal['option_id'];
        $itemKey = self::makeProductKey($productId, $finalOptId);

        $existingQty = 0;
        if (isset($_SESSION[self::SESSION_KEY][$itemKey])) {
            $existingQty = is_array($_SESSION[self::SESSION_KEY][$itemKey]) 
                ? (int)($_SESSION[self::SESSION_KEY][$itemKey]['quantity'] ?? 0)
                : (int)$_SESSION[self::SESSION_KEY][$itemKey];
        } elseif (isset($_SESSION[self::SESSION_KEY][$productId]) && is_numeric($_SESSION[self::SESSION_KEY][$productId])) {
            // Migrate legacy numeric key
            $existingQty = (int)$_SESSION[self::SESSION_KEY][$productId];
            unset($_SESSION[self::SESSION_KEY][$productId]);
        }

        $newQty = $existingQty + max(1, $quantity);
        if ($newQty > (int)$product['stock_quantity']) {
            $newQty = (int)$product['stock_quantity'];
        }

        $_SESSION[self::SESSION_KEY][$itemKey] = [
            'type' => 'product',
            'id' => $productId,
            'quantity' => max(1, $newQty),
            'option_id' => $finalOptId
        ];

        return ['success' => true, 'cart' => self::getCart()];
    }

    /**
     * Add combo bundle to cart
     */
    public static function addCombo(int $comboId, int $quantity = 1): array {
        $combo = Combo::getById($comboId);
        if (!$combo) {
            return ['success' => false, 'error' => 'Combo offer not found.'];
        }
        if (!$combo['is_available'] || (int)$combo['stock_quantity'] <= 0) {
            return ['success' => false, 'error' => 'This combo offer is currently out of stock.'];
        }

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $itemKey = 'combo_' . $comboId;
        $existingQty = 0;
        if (isset($_SESSION[self::SESSION_KEY][$itemKey])) {
            $existingQty = is_array($_SESSION[self::SESSION_KEY][$itemKey]) 
                ? (int)($_SESSION[self::SESSION_KEY][$itemKey]['quantity'] ?? 0)
                : (int)$_SESSION[self::SESSION_KEY][$itemKey];
        }

        $newQty = $existingQty + max(1, $quantity);
        if ($newQty > (int)$combo['stock_quantity']) {
            $newQty = (int)$combo['stock_quantity'];
        }

        $_SESSION[self::SESSION_KEY][$itemKey] = [
            'type' => 'combo',
            'id' => $comboId,
            'quantity' => max(1, $newQty),
            'option_id' => null
        ];

        return ['success' => true, 'cart' => self::getCart()];
    }

    /**
     * Update quantity by item key or legacy product ID
     */
    public static function updateQuantity($itemKeyOrProductId, int $quantity): array {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $key = (string)$itemKeyOrProductId;

        if ($quantity <= 0) {
            return self::removeItem($key);
        }

        // Direct key lookup
        if (isset($_SESSION[self::SESSION_KEY][$key])) {
            $entry = $_SESSION[self::SESSION_KEY][$key];
            if (is_array($entry)) {
                $maxStock = 999;
                if (($entry['type'] ?? '') === 'combo') {
                    $c = Combo::getById((int)$entry['id']);
                    $maxStock = $c ? (int)$c['stock_quantity'] : 0;
                } else {
                    $p = Product::getById((int)$entry['id']);
                    $maxStock = $p ? (int)$p['stock_quantity'] : 0;
                }
                $_SESSION[self::SESSION_KEY][$key]['quantity'] = min($quantity, $maxStock);
            } else {
                $p = Product::getById((int)$key);
                $maxStock = $p ? (int)$p['stock_quantity'] : 0;
                $_SESSION[self::SESSION_KEY][$key] = min($quantity, $maxStock);
            }
            return ['success' => true, 'cart' => self::getCart()];
        }

        // Legacy numeric fallback search
        if (is_numeric($key)) {
            $prodId = (int)$key;
            foreach ($_SESSION[self::SESSION_KEY] as $k => $entry) {
                if ((is_array($entry) && ($entry['type'] ?? '') === 'product' && (int)$entry['id'] === $prodId) || (is_numeric($k) && (int)$k === $prodId)) {
                    $p = Product::getById($prodId);
                    $maxStock = $p ? (int)$p['stock_quantity'] : 0;
                    if (is_array($_SESSION[self::SESSION_KEY][$k])) {
                        $_SESSION[self::SESSION_KEY][$k]['quantity'] = min($quantity, $maxStock);
                    } else {
                        $_SESSION[self::SESSION_KEY][$k] = min($quantity, $maxStock);
                    }
                    return ['success' => true, 'cart' => self::getCart()];
                }
            }
        }

        return ['success' => false, 'error' => 'Cart item not found.'];
    }

    /**
     * Remove item by key or legacy product ID
     */
    public static function removeItem($itemKeyOrProductId): array {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return ['success' => true, 'cart' => self::getCart()];
        }

        $key = (string)$itemKeyOrProductId;

        if (isset($_SESSION[self::SESSION_KEY][$key])) {
            unset($_SESSION[self::SESSION_KEY][$key]);
            return ['success' => true, 'cart' => self::getCart()];
        }

        // Legacy numeric fallback
        if (is_numeric($key)) {
            $prodId = (int)$key;
            foreach ($_SESSION[self::SESSION_KEY] as $k => $entry) {
                if ((is_array($entry) && ($entry['type'] ?? '') === 'product' && (int)$entry['id'] === $prodId) || (is_numeric($k) && (int)$k === $prodId)) {
                    unset($_SESSION[self::SESSION_KEY][$k]);
                }
            }
        }

        return ['success' => true, 'cart' => self::getCart()];
    }

    public static function clear(): void {
        $_SESSION[self::SESSION_KEY] = [];
        unset($_SESSION[self::COUPON_SESSION_KEY]);
    }

    public static function applyCoupon(string $code, ?int $customerId = null, string $phone = '', string $fulfilment = 'delivery'): array {
        $cart = self::getCart();
        $subtotal = (float)$cart['subtotal'];

        if ($subtotal <= 0) {
            return ['success' => false, 'error' => 'Your cart is empty. Please add items before applying a coupon.'];
        }

        $cleanCode = strtoupper(trim($code));

        // Support 50% Loyalty Reward Vouchers
        if (str_starts_with($cleanCode, 'NA50-')) {
            $loyaltyVal = LoyaltyService::validateVoucher($cleanCode, $customerId ?? 0, $subtotal);
            if (!$loyaltyVal['valid']) {
                return ['success' => false, 'error' => $loyaltyVal['error']];
            }
            $_SESSION[self::COUPON_SESSION_KEY] = $cleanCode;
            return [
                'success' => true,
                'message' => '50% Loyalty Voucher applied successfully! Discount: ₹' . number_format($loyaltyVal['discount_amount'], 2),
                'coupon' => [
                    'code' => $cleanCode,
                    'type' => 'percentage',
                    'discount_value' => $loyaltyVal['discount_percent'],
                    'name' => '50% Loyalty Reward Voucher',
                    'is_loyalty' => true,
                    'is_loyalty_voucher' => true
                ],
                'is_loyalty_voucher' => true,
                'calculation' => [
                    'discount_amount' => $loyaltyVal['discount_amount'],
                    'delivery_discount' => 0.00
                ]
            ];
        }

        $val = Coupon::validateCoupon($cleanCode, $subtotal, $customerId, $phone, $fulfilment);
        if (!$val['valid']) {
            return ['success' => false, 'error' => $val['error']];
        }

        $coupon = $val['coupon'];
        $_SESSION[self::COUPON_SESSION_KEY] = $coupon['code'];

        $deliveryCharge = 0.00;
        if ($fulfilment === 'delivery' && $subtotal > 0) {
            $freeAbove = (float)Setting::get('free_delivery_above', '500.00');
            $deliveryCharge = ($subtotal >= $freeAbove) ? 0.00 : (float)Setting::get('delivery_charge', '40.00');
        }

        $calc = Coupon::calculateDiscount($coupon, $subtotal, $deliveryCharge);

        return [
            'success' => true,
            'message' => 'Coupon ' . $coupon['code'] . ' applied successfully!',
            'coupon' => $coupon,
            'calculation' => $calc
        ];
    }

    public static function removeCoupon(): void {
        unset($_SESSION[self::COUPON_SESSION_KEY]);
    }

    public static function getAppliedCoupon(?int $customerId = null, string $phone = '', string $fulfilment = 'delivery'): ?array {
        if (empty($_SESSION[self::COUPON_SESSION_KEY])) {
            return null;
        }

        $code = strtoupper(trim($_SESSION[self::COUPON_SESSION_KEY]));
        $cart = self::getCart();
        $subtotal = (float)$cart['subtotal'];

        if ($subtotal <= 0) {
            self::removeCoupon();
            return null;
        }

        if ($customerId === null) {
            if (class_exists('CustomerAuthService') && CustomerAuthService::check()) {
                $customerId = CustomerAuthService::id();
            } elseif (!empty($_SESSION['customer_id'])) {
                $customerId = (int)$_SESSION['customer_id'];
            }
        }

        // Check if loyalty voucher (NA50-...)
        if (str_starts_with($code, 'NA50-')) {
            $loyaltyVal = LoyaltyService::validateVoucher($code, $customerId ?? 0, $subtotal);
            if (!$loyaltyVal['valid']) {
                self::removeCoupon();
                return null;
            }
            return [
                'coupon' => [
                    'id' => 0,
                    'code' => $loyaltyVal['code'],
                    'type' => 'percentage',
                    'discount_value' => $loyaltyVal['discount_percent'],
                    'name' => '50% Loyalty Reward Voucher',
                    'is_loyalty' => true,
                    'is_loyalty_voucher' => true
                ],
                'is_loyalty_voucher' => true,
                'calculation' => [
                    'discount_amount' => $loyaltyVal['discount_amount'],
                    'delivery_discount' => 0.00
                ]
            ];
        }

        $val = Coupon::validateCoupon($code, $subtotal, $customerId, $phone, $fulfilment);
        if (!$val['valid']) {
            self::removeCoupon();
            return null;
        }

        $deliveryCharge = 0.00;
        if ($fulfilment === 'delivery' && $subtotal > 0) {
            $freeAbove = (float)Setting::get('free_delivery_above', '500.00');
            $deliveryCharge = ($subtotal >= $freeAbove) ? 0.00 : (float)Setting::get('delivery_charge', '40.00');
        }

        $calc = Coupon::calculateDiscount($val['coupon'], $subtotal, $deliveryCharge);
        return [
            'coupon' => $val['coupon'],
            'calculation' => $calc
        ];
    }

    public static function calculateCheckoutTotals(string $fulfilment = 'delivery', ?int $customerId = null, string $phone = ''): array {
        $cart = self::getCart();
        $subtotal = (float)$cart['subtotal'];
        $originalDeliveryCharge = 0.00;

        if ($fulfilment === 'delivery' && $subtotal > 0) {
            $freeAbove = (float)Setting::get('free_delivery_above', '500.00');
            if ($subtotal >= $freeAbove) {
                $originalDeliveryCharge = 0.00;
            } else {
                $originalDeliveryCharge = (float)Setting::get('delivery_charge', '40.00');
            }
        }

        $discountAmount = 0.00;
        $deliveryDiscount = 0.00;
        $appliedCoupon = self::getAppliedCoupon($customerId, $phone, $fulfilment);

        if ($appliedCoupon) {
            $discountAmount = (float)$appliedCoupon['calculation']['discount_amount'];
            $deliveryDiscount = (float)$appliedCoupon['calculation']['delivery_discount'];
        }

        $effectiveDelivery = max(0.00, $originalDeliveryCharge - $deliveryDiscount);
        $total = max(0.00, $subtotal - $discountAmount) + $effectiveDelivery;

        return [
            'subtotal' => round($subtotal, 2),
            'original_delivery_charge' => round($originalDeliveryCharge, 2),
            'delivery_charge' => round($effectiveDelivery, 2),
            'discount_amount' => round($discountAmount, 2),
            'delivery_discount' => round($deliveryDiscount, 2),
            'total_savings' => round($discountAmount + $deliveryDiscount, 2),
            'total' => round($total, 2),
            'coupon' => $appliedCoupon['coupon'] ?? null,
            'coupon_calculation' => $appliedCoupon['calculation'] ?? null,
            'items' => $cart['items'],
            'count' => $cart['count']
        ];
    }

    /**
     * Quick Set Quantity for 1-tap card steppers (+ / -)
     */
    public static function quickSetQuantity(int $productId, int $quantity, ?string $optionId = null): array {
        $product = Product::getById($productId);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.'];
        }

        $optVal = Product::validateCuttingOption($product, $optionId);
        $finalOptId = $optVal['option_id'];
        $itemKey = self::makeProductKey($productId, $finalOptId);

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        if ($quantity <= 0) {
            self::removeItem($itemKey);
            $cart = self::getCart();
            return [
                'success' => true,
                'product_id' => $productId,
                'quantity' => 0,
                'cart_count' => $cart['count'],
                'cart_subtotal' => $cart['subtotal'],
                'cart' => $cart
            ];
        }

        if (!$product['is_available'] || (int)$product['stock_quantity'] <= 0) {
            return ['success' => false, 'error' => 'This product is out of stock.'];
        }

        $cappedQty = min($quantity, (int)$product['stock_quantity']);
        $_SESSION[self::SESSION_KEY][$itemKey] = [
            'type' => 'product',
            'id' => $productId,
            'quantity' => $cappedQty,
            'option_id' => $finalOptId
        ];

        $cart = self::getCart();
        return [
            'success' => true,
            'product_id' => $productId,
            'quantity' => $cappedQty,
            'cart_count' => $cart['count'],
            'cart_subtotal' => $cart['subtotal'],
            'cart' => $cart
        ];
    }

    /**
     * Buy Again: Reorder previous purchase products with fresh price and stock checks
     */
    public static function reorderFromOrder(int $orderId, ?int $customerId = null): array {
        $order = Order::getById($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ($customerId !== null && !empty($order['customer_id']) && (int)$order['customer_id'] !== $customerId) {
            return ['success' => false, 'error' => 'Unauthorized order access.'];
        }

        $items = Order::getOrderItems($orderId);
        if (empty($items)) {
            return ['success' => false, 'error' => 'No items found in this order to reorder.'];
        }

        $addedCount = 0;
        $unavailableItems = [];

        foreach ($items as $item) {
            if (!empty($item['is_combo']) && !empty($item['combo_id'])) {
                $combo = Combo::getById((int)$item['combo_id']);
                if ($combo && $combo['is_available'] && (int)$combo['stock_quantity'] > 0) {
                    $qty = min((int)$item['quantity'], (int)$combo['stock_quantity']);
                    self::addCombo((int)$item['combo_id'], $qty);
                    $addedCount += $qty;
                } else {
                    $unavailableItems[] = $item['product_name'] . ' (Combo unavailable)';
                }
            } else {
                $productId = (int)$item['product_id'];
                $product = Product::getById($productId);
                if ($product && $product['is_available'] && (int)$product['stock_quantity'] > 0) {
                    $qty = min((int)$item['quantity'], (int)$product['stock_quantity']);
                    $optId = !empty($item['preparation_option']) ? $item['preparation_option'] : null;
                    self::addItem($productId, $qty, $optId);
                    $addedCount += $qty;
                } else {
                    $unavailableItems[] = $item['product_name'] . ' (Out of stock)';
                }
            }
        }

        $cart = self::getCart();

        if ($addedCount === 0) {
            return [
                'success' => false,
                'error' => 'All items from this past order are currently out of stock.',
                'unavailable_items' => $unavailableItems,
                'cart' => $cart
            ];
        }

        $msg = "Added {$addedCount} item(s) to your cart.";
        if (!empty($unavailableItems)) {
            $msg .= " (Note: " . implode(', ', $unavailableItems) . ")";
        }

        return [
            'success' => true,
            'message' => $msg,
            'added_count' => $addedCount,
            'unavailable_items' => $unavailableItems,
            'cart' => $cart
        ];
    }
}
