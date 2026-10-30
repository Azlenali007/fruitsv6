<?php
/**
 * Invoice Service
 * N.A Fresh Fruits & Coconuts
 * Provides professional A4 printable invoice views and pure PHP PDF generation.
 */

class InvoiceService {

    /**
     * Get complete invoice details including order and item snapshots
     */
    public static function getInvoiceFullData(int $invoiceId): ?array {
        $invoice = Invoice::getById($invoiceId);
        if (!$invoice) {
            return null;
        }

        $order = Order::getById((int)$invoice['order_id']);
        if (!$order) {
            return null;
        }

        $items = Order::getItems((int)$order['id']);

        return [
            'invoice' => $invoice,
            'order' => $order,
            'items' => $items
        ];
    }

    /**
     * Get complete invoice details by order ID
     */
    public static function getInvoiceFullDataByOrder(int $orderId): ?array {
        $invoice = Invoice::getByOrderId($orderId);
        $order = Order::getById($orderId);
        if (!$order) {
            return null;
        }

        // If invoice doesn't exist yet for this order, create it if eligible
        if (!$invoice) {
            $createRes = Invoice::createForOrder($order);
            if ($createRes['success']) {
                $invoice = $createRes['invoice'];
            }
        }

        if (!$invoice) {
            return null;
        }

        $items = Order::getItems($orderId);

        return [
            'invoice' => $invoice,
            'order' => $order,
            'items' => $items
        ];
    }

    /**
     * Resolve full invoice, order and items data from order or invoice record
     */
    public static function resolveFullData($orderOrInvoice): ?array {
        if (!$orderOrInvoice || !is_array($orderOrInvoice)) {
            return null;
        }

        if (isset($orderOrInvoice['invoice_number'])) {
            $invoice = $orderOrInvoice;
            $order = Order::getById((int)$invoice['order_id']);
            $items = $order ? Order::getItems((int)$order['id']) : [];
            return ['invoice' => $invoice, 'order' => $order ?: [], 'items' => $items];
        }

        // It is an order record
        $order = $orderOrInvoice;
        $orderId = (int)$order['id'];
        $invoice = Invoice::getByOrderId($orderId);
        if (!$invoice) {
            $res = Invoice::createForOrder($order);
            $invoice = $res['invoice'] ?? null;
        }
        $items = Order::getItems($orderId);
        return ['invoice' => $invoice ?: [], 'order' => $order, 'items' => $items];
    }

    /**
     * Render printable, beautiful A4 HTML invoice
     */
    public static function renderHtml($invoiceOrOrder, ?array $order = null, ?array $items = null, bool $forDownload = false): string {
        if ($order === null) {
            $resolved = self::resolveFullData($invoiceOrOrder);
            if (!$resolved || empty($resolved['invoice'])) {
                throw new Exception("Invoice could not be resolved for order.");
            }
            $invoice = $resolved['invoice'];
            $order = $resolved['order'];
            $items = $resolved['items'];
        } else {
            $invoice = $invoiceOrOrder;
            $items = $items ?? [];
        }

        $storeName = Setting::get('store_name', 'N.A Fresh Fruits & Coconuts');
        $storeTagline = Setting::get('store_tagline', 'Pure • Natural • Refreshing Fresh Tender Coconut Water');
        $storePhone = Setting::get('store_phone', '+91 98765 43210');
        $storeEmail = Setting::get('store_email', 'contact@nafruitsandcoconuts.com');
        $storeAddress = Setting::get('store_address', '123, Green Park, Near Main Road, Bhopal, Madhya Pradesh - 462016');

        $invNumber = $invoice['invoice_number'] ?? 'NAF-BILL';
        $invDate = !empty($invoice['invoice_date']) ? date('d M Y, h:i A', strtotime($invoice['invoice_date'])) : date('d M Y, h:i A');
        $orderNumber = $order['order_number'] ?? ('#' . $order['id']);
        $custName = $invoice['customer_name'];
        $custPhone = $invoice['customer_phone'];
        $custEmail = $invoice['customer_email'] ?: '-';
        $address = $invoice['delivery_address'] ?: 'Customer address on record';
        $areaName = $invoice['delivery_area_name'] ?: 'Jaora Area';

        $subtotal = number_format((float)$invoice['subtotal'], 2);
        $discount = (float)$invoice['discount_amount'];
        $deliveryCharge = (float)$invoice['delivery_charge'];
        $total = number_format((float)$invoice['total_amount'], 2);

        $payMethod = $invoice['payment_method'];
        $payStatus = $invoice['payment_status'];
        $isPaid = str_contains(strtolower($payStatus), 'paid') && !str_contains(strtolower($payStatus), 'pending');
        $payRef = $invoice['payment_reference'] ?: '-';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Invoice <?= htmlspecialchars($invNumber) ?> - <?= htmlspecialchars($storeName) ?></title>
            <link rel="icon" href="/favicon.ico">
            <style>
                @page {
                    size: A4;
                    margin: 15mm;
                }
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    color: #1f2937;
                    background: #f3f4f6;
                    padding: 24px;
                    font-size: 13px;
                    line-height: 1.5;
                }
                .invoice-wrapper {
                    max-width: 800px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 16px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                    overflow: hidden;
                    border: 1px solid #e5e7eb;
                }
                .top-actions {
                    max-width: 800px;
                    margin: 0 auto 16px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 9px 18px;
                    font-size: 13px;
                    font-weight: 700;
                    border-radius: 10px;
                    text-decoration: none;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                }
                .btn-primary {
                    background: #235d3a;
                    color: #ffffff;
                }
                .btn-primary:hover {
                    background: #1b4a2e;
                }
                .btn-secondary {
                    background: #ffffff;
                    color: #374151;
                    border: 1px solid #d1d5db;
                }
                .btn-secondary:hover {
                    background: #f9fafb;
                }
                .header-bar {
                    background: linear-gradient(135deg, #235d3a 0%, #153e24 100%);
                    color: #ffffff;
                    padding: 32px 36px;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                }
                .brand-title {
                    font-size: 24px;
                    font-weight: 800;
                    letter-spacing: -0.5px;
                    margin-bottom: 4px;
                }
                .brand-tagline {
                    font-size: 12px;
                    color: #d1fae5;
                    font-weight: 500;
                    margin-bottom: 12px;
                }
                .brand-contact {
                    font-size: 11px;
                    color: #e5e7eb;
                    line-height: 1.6;
                }
                .invoice-meta {
                    text-align: right;
                }
                .invoice-badge {
                    display: inline-block;
                    background: rgba(255, 255, 255, 0.2);
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    margin-bottom: 8px;
                }
                .invoice-num {
                    font-size: 20px;
                    font-weight: 800;
                    font-family: monospace;
                    letter-spacing: 0.5px;
                }
                .invoice-date {
                    font-size: 12px;
                    color: #d1fae5;
                    margin-top: 4px;
                }
                .body-content {
                    padding: 32px 36px;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 24px;
                    padding-bottom: 24px;
                    border-bottom: 1px solid #f3f4f6;
                    margin-bottom: 24px;
                }
                .info-block h4 {
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    color: #9ca3af;
                    margin-bottom: 8px;
                }
                .info-block p {
                    font-size: 13px;
                    color: #374151;
                    line-height: 1.6;
                }
                .info-block strong {
                    color: #111827;
                    font-weight: 700;
                }
                .table-container {
                    margin-bottom: 24px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    text-align: left;
                }
                th {
                    background: #f9fafb;
                    color: #4b5563;
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    padding: 12px 16px;
                    border-bottom: 1px solid #e5e7eb;
                }
                td {
                    padding: 14px 16px;
                    border-bottom: 1px solid #f3f4f6;
                    color: #374151;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .totals-section {
                    display: flex;
                    justify-content: flex-end;
                    margin-bottom: 24px;
                }
                .totals-table {
                    width: 320px;
                }
                .totals-table tr td {
                    padding: 8px 12px;
                    border: none;
                }
                .totals-table .total-row td {
                    border-top: 2px solid #235d3a;
                    font-size: 16px;
                    font-weight: 800;
                    color: #235d3a;
                    padding-top: 12px;
                }
                .payment-box {
                    background: #f9fafb;
                    border-radius: 12px;
                    padding: 16px 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border: 1px solid #e5e7eb;
                    margin-bottom: 24px;
                }
                .status-pill {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 5px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 700;
                }
                .status-paid {
                    background: #dcfce7;
                    color: #15803d;
                    border: 1px solid #bbf7d0;
                }
                .status-pending {
                    background: #fef3c7;
                    color: #b45309;
                    border: 1px solid #fde68a;
                }
                .footer-notes {
                    border-top: 1px solid #f3f4f6;
                    padding-top: 20px;
                    text-align: center;
                    color: #6b7280;
                    font-size: 12px;
                }
                .footer-notes strong {
                    color: #235d3a;
                }
                @media print {
                    body {
                        background: #ffffff;
                        padding: 0;
                    }
                    .top-actions {
                        display: none !important;
                    }
                    .invoice-wrapper {
                        box-shadow: none;
                        border: none;
                        max-width: 100%;
                    }
                }
            </style>
        </head>
        <body>

            <div class="top-actions">
                <a href="javascript:history.back()" class="btn btn-secondary">
                    &larr; Back
                </a>
                <div style="display: flex; gap: 8px;">
                    <a href="?download=pdf" class="btn btn-primary">
                        📥 Download PDF
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        🖨️ Print
                    </button>
                </div>
            </div>

            <div class="invoice-wrapper">
                <!-- Header with Brand & Meta -->
                <div class="header-bar">
                    <div>
                        <div class="brand-title"><?= htmlspecialchars($storeName) ?></div>
                        <div class="brand-tagline"><?= htmlspecialchars($storeTagline) ?></div>
                        <div class="brand-contact">
                            <?= htmlspecialchars($storeAddress) ?><br>
                            Phone: <?= htmlspecialchars($storePhone) ?> &bull; Email: <?= htmlspecialchars($storeEmail) ?>
                        </div>
                    </div>
                    <div class="invoice-meta">
                        <div class="invoice-badge">Tax Invoice / Bill</div>
                        <div class="invoice-num"><?= htmlspecialchars($invNumber) ?></div>
                        <div class="invoice-date">Date: <?= htmlspecialchars($invDate) ?></div>
                        <div class="invoice-date">Order: <strong>#<?= htmlspecialchars($orderNumber) ?></strong></div>
                    </div>
                </div>

                <div class="body-content">
                    <!-- Customer & Delivery Info -->
                    <div class="info-grid">
                        <div class="info-block">
                            <h4>Billed & Delivered To</h4>
                            <p>
                                <strong><?= htmlspecialchars($custName) ?></strong><br>
                                Phone: <?= htmlspecialchars($custPhone) ?><br>
                                Email: <?= htmlspecialchars($custEmail) ?>
                            </p>
                        </div>
                        <div class="info-block">
                            <h4>Delivery Address</h4>
                            <p>
                                <?= nl2br(htmlspecialchars($address)) ?><br>
                                <strong>Locality:</strong> <?= htmlspecialchars($areaName) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th>Item Description</th>
                                    <th class="text-right" style="width: 120px;">Unit Price</th>
                                    <th class="text-center" style="width: 90px;">Qty</th>
                                    <th class="text-right" style="width: 130px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx = 1; foreach ($items as $item): ?>
                                <tr>
                                    <td class="text-center" style="color: #9ca3af;"><?= $idx++ ?></td>
                                    <td>
                                        <strong style="color: #111827;"><?= htmlspecialchars($item['product_name']) ?></strong>
                                        <div style="font-size: 11px; color: #6b7280;">100% Natural Farm Fresh Produce</div>
                                    </td>
                                    <td class="text-right">₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                                    <td class="text-center"><strong><?= (int)$item['quantity'] ?></strong></td>
                                    <td class="text-right" style="font-weight: 700;">₹<?= number_format((float)$item['subtotal'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary & Totals -->
                    <div class="totals-section">
                        <table class="totals-table">
                            <tr>
                                <td>Items Subtotal:</td>
                                <td class="text-right font-medium">₹<?= $subtotal ?></td>
                            </tr>
                            <?php if ($discount > 0): ?>
                            <tr style="color: #15803d;">
                                <td>Coupon Discount (<?= htmlspecialchars($order['coupon_code'] ?? 'PROMO') ?>):</td>
                                <td class="text-right font-bold">-₹<?= number_format($discount, 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td>Delivery Charges:</td>
                                <td class="text-right font-medium">
                                    <?php if ($deliveryCharge <= 0): ?>
                                        <span style="color: #15803d; font-weight: 700;">FREE</span>
                                    <?php else: ?>
                                        ₹<?= number_format($deliveryCharge, 2) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="total-row">
                                <td>Total Amount:</td>
                                <td class="text-right">₹<?= $total ?></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Payment Information Bar -->
                    <div class="payment-box">
                        <div>
                            <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">
                                Payment Details
                            </div>
                            <div style="font-size: 13px; font-weight: 700; color: #111827;">
                                <?= htmlspecialchars($payMethod) ?>
                            </div>
                            <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                                Reference: <code style="font-family: monospace;"><?= htmlspecialchars($payRef) ?></code>
                            </div>
                        </div>
                        <div>
                            <span class="status-pill <?= $isPaid ? 'status-paid' : 'status-pending' ?>">
                                <?= $isPaid ? '✓' : '⏱' ?> <?= htmlspecialchars($payStatus) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Footer Notes -->
                    <div class="footer-notes">
                        <p>Thank you for choosing <strong><?= htmlspecialchars($storeName) ?></strong>!</p>
                        <p style="margin-top: 4px; font-size: 11px; color: #9ca3af;">
                            For customer care or delivery assistance, please reach out to us at <?= htmlspecialchars($storePhone) ?>.
                        </p>
                    </div>
                </div>
            </div>

        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate pure native PDF 1.4 bytecode without external libraries
     * Returns raw PDF binary string
     */
    public static function generatePdfString($invoiceOrOrder, ?array $order = null, ?array $items = null): string {
        if ($order === null) {
            $resolved = self::resolveFullData($invoiceOrOrder);
            if (!$resolved || empty($resolved['invoice'])) {
                throw new Exception("Invoice could not be resolved for order.");
            }
            $invoice = $resolved['invoice'];
            $order = $resolved['order'];
            $items = $resolved['items'];
        } else {
            $invoice = $invoiceOrOrder;
            $items = $items ?? [];
        }

        $invNumber = $invoice['invoice_number'] ?? 'NAF-BILL';
        $filename = "Invoice-" . preg_replace('/[^A-Za-z0-9_-]/', '', $invNumber) . ".pdf";

        $storeName = Setting::get('store_name', 'N.A Fresh Fruits & Coconuts');
        $storePhone = Setting::get('store_phone', '+91 98765 43210');
        $storeEmail = Setting::get('store_email', 'contact@nafruitsandcoconuts.com');
        $storeAddress = Setting::get('store_address', '123, Green Park, Bhopal, MP');

        $invDate = date('d M Y, h:i A', strtotime($invoice['invoice_date']));
        $orderNumber = $order['order_number'] ?? ('#' . $order['id']);
        $custName = $invoice['customer_name'];
        $custPhone = $invoice['customer_phone'];
        $address = $invoice['delivery_address'] ?: 'Customer address on record';
        $areaName = $invoice['delivery_area_name'] ?: 'Jaora Area';

        $subtotal = number_format((float)$invoice['subtotal'], 2);
        $discount = (float)$invoice['discount_amount'];
        $deliveryCharge = (float)$invoice['delivery_charge'];
        $total = number_format((float)$invoice['total_amount'], 2);

        $payMethod = $invoice['payment_method'];
        $payStatus = $invoice['payment_status'];
        $isPaid = str_contains(strtolower($payStatus), 'paid') && !str_contains(strtolower($payStatus), 'pending');
        $payRef = $invoice['payment_reference'] ?: '-';

        // Build clean PDF stream content
        // Standard A4: Width = 595.28 pt, Height = 841.89 pt
        // Margins: Left = 40, Right = 555
        $p = [];
        
        // Header Rectangle in Emerald (#235d3a -> 0.137, 0.365, 0.227)
        $p[] = "q";
        $p[] = "0.137 0.365 0.227 rg";
        $p[] = "40 730 515 80 re f";
        $p[] = "Q";

        // Header Text
        $p[] = "BT";
        $p[] = "/F2 18 Tf";
        $p[] = "1 1 1 rg";
        $p[] = "55 780 Td";
        $p[] = "(" . self::escapePdfText($storeName) . ") Tj";
        $p[] = "ET";

        $p[] = "BT";
        $p[] = "/F1 9 Tf";
        $p[] = "0.82 0.98 0.89 rg";
        $p[] = "55 765 Td";
        $p[] = "(Pure . Natural . Refreshing Fresh Tender Coconut Water) Tj";
        $p[] = "ET";

        $p[] = "BT";
        $p[] = "/F1 8 Tf";
        $p[] = "0.9 0.9 0.9 rg";
        $p[] = "55 745 Td";
        $p[] = "(" . self::escapePdfText($storeAddress . " | Tel: " . $storePhone) . ") Tj";
        $p[] = "ET";

        // Invoice Meta on Right
        $p[] = "BT";
        $p[] = "/F2 14 Tf";
        $p[] = "1 1 1 rg";
        $p[] = "370 780 Td";
        $p[] = "(TAX INVOICE) Tj";
        $p[] = "ET";

        $p[] = "BT";
        $p[] = "/F2 11 Tf";
        $p[] = "1 1 1 rg";
        $p[] = "370 762 Td";
        $p[] = "(" . self::escapePdfText($invNumber) . ") Tj";
        $p[] = "ET";

        $p[] = "BT";
        $p[] = "/F1 8 Tf";
        $p[] = "0.82 0.98 0.89 rg";
        $p[] = "370 746 Td";
        $p[] = "(Date: " . self::escapePdfText($invDate) . ") Tj";
        $p[] = "ET";

        $p[] = "BT";
        $p[] = "/F1 8 Tf";
        $p[] = "0.82 0.98 0.89 rg";
        $p[] = "370 735 Td";
        $p[] = "(Order Ref: #" . self::escapePdfText($orderNumber) . ") Tj";
        $p[] = "ET";

        // Customer & Delivery Details Section
        $p[] = "q 0.96 0.96 0.96 rg 40 655 515 60 re f Q";
        $p[] = "q 0.85 0.85 0.85 RG 1 w 40 655 515 60 re s Q";

        $p[] = "BT /F2 8 Tf 0.5 0.5 0.5 rg 55 700 Td (BILLED TO:) Tj ET";
        $p[] = "BT /F2 10 Tf 0.1 0.1 0.1 rg 55 686 Td (" . self::escapePdfText($custName) . ") Tj ET";
        $p[] = "BT /F1 9 Tf 0.3 0.3 0.3 rg 55 672 Td (Phone: " . self::escapePdfText($custPhone) . ") Tj ET";

        $p[] = "BT /F2 8 Tf 0.5 0.5 0.5 rg 300 700 Td (DELIVERY ADDRESS:) Tj ET";
        $p[] = "BT /F1 9 Tf 0.2 0.2 0.2 rg 300 686 Td (" . self::escapePdfText(substr($address, 0, 45)) . ") Tj ET";
        $p[] = "BT /F1 9 Tf 0.2 0.2 0.2 rg 300 672 Td (Locality: " . self::escapePdfText($areaName) . ") Tj ET";

        // Table Header
        $tableY = 625;
        $p[] = "q 0.93 0.95 0.93 rg 40 {$tableY} 515 22 re f Q";
        $p[] = "q 0.8 0.85 0.8 RG 1 w 40 {$tableY} 515 22 re s Q";

        $p[] = "BT /F2 9 Tf 0.137 0.365 0.227 rg 50 " . ($tableY + 7) . " Td (#) Tj ET";
        $p[] = "BT /F2 9 Tf 0.137 0.365 0.227 rg 80 " . ($tableY + 7) . " Td (ITEM DESCRIPTION) Tj ET";
        $p[] = "BT /F2 9 Tf 0.137 0.365 0.227 rg 330 " . ($tableY + 7) . " Td (UNIT PRICE) Tj ET";
        $p[] = "BT /F2 9 Tf 0.137 0.365 0.227 rg 420 " . ($tableY + 7) . " Td (QTY) Tj ET";
        $p[] = "BT /F2 9 Tf 0.137 0.365 0.227 rg 480 " . ($tableY + 7) . " Td (AMOUNT) Tj ET";

        // Table Rows
        $rowY = $tableY;
        $idx = 1;
        foreach ($items as $item) {
            $rowY -= 24;
            $p[] = "q 0.9 0.9 0.9 RG 0.5 w 40 {$rowY} 515 24 re s Q";
            $p[] = "BT /F1 9 Tf 0.4 0.4 0.4 rg 50 " . ($rowY + 8) . " Td ({$idx}) Tj ET";
            $p[] = "BT /F2 9 Tf 0.1 0.1 0.1 rg 80 " . ($rowY + 8) . " Td (" . self::escapePdfText($item['product_name']) . ") Tj ET";
            $p[] = "BT /F1 9 Tf 0.2 0.2 0.2 rg 330 " . ($rowY + 8) . " Td (Rs. " . number_format((float)$item['unit_price'], 2) . ") Tj ET";
            $p[] = "BT /F2 9 Tf 0.1 0.1 0.1 rg 425 " . ($rowY + 8) . " Td (" . (int)$item['quantity'] . ") Tj ET";
            $p[] = "BT /F2 9 Tf 0.1 0.1 0.1 rg 480 " . ($rowY + 8) . " Td (Rs. " . number_format((float)$item['subtotal'], 2) . ") Tj ET";
            $idx++;
        }

        // Summary Section
        $sumY = $rowY - 20;
        $p[] = "BT /F1 9 Tf 0.3 0.3 0.3 rg 350 " . ($sumY) . " Td (Items Subtotal:) Tj ET";
        $p[] = "BT /F1 9 Tf 0.1 0.1 0.1 rg 480 " . ($sumY) . " Td (Rs. {$subtotal}) Tj ET";

        if ($discount > 0) {
            $sumY -= 16;
            $p[] = "BT /F1 9 Tf 0.1 0.6 0.2 rg 350 " . ($sumY) . " Td (Coupon Discount:) Tj ET";
            $p[] = "BT /F2 9 Tf 0.1 0.6 0.2 rg 480 " . ($sumY) . " Td (-Rs. " . number_format($discount, 2) . ") Tj ET";
        }

        $sumY -= 16;
        $p[] = "BT /F1 9 Tf 0.3 0.3 0.3 rg 350 " . ($sumY) . " Td (Delivery Charge:) Tj ET";
        $delText = $deliveryCharge <= 0 ? "FREE" : "Rs. " . number_format($deliveryCharge, 2);
        $p[] = "BT /F1 9 Tf 0.1 0.1 0.1 rg 480 " . ($sumY) . " Td ({$delText}) Tj ET";

        $sumY -= 20;
        $p[] = "q 0.137 0.365 0.227 RG 1.5 w 340 {$sumY} 215 0 m 555 {$sumY} l s Q";

        $sumY -= 18;
        $p[] = "BT /F2 12 Tf 0.137 0.365 0.227 rg 350 " . ($sumY) . " Td (FINAL PAYABLE:) Tj ET";
        $p[] = "BT /F2 12 Tf 0.137 0.365 0.227 rg 480 " . ($sumY) . " Td (Rs. {$total}) Tj ET";

        // Payment status block
        $payY = $sumY - 45;
        $p[] = "q 0.97 0.97 0.97 rg 40 {$payY} 515 36 re f Q";
        $p[] = "q 0.85 0.85 0.85 RG 1 w 40 {$payY} 515 36 re s Q";

        $p[] = "BT /F2 8 Tf 0.5 0.5 0.5 rg 55 " . ($payY + 22) . " Td (PAYMENT METHOD) Tj ET";
        $p[] = "BT /F2 9 Tf 0.1 0.1 0.1 rg 55 " . ($payY + 10) . " Td (" . self::escapePdfText($payMethod) . " | Ref: " . self::escapePdfText(substr($payRef, 0, 28)) . ") Tj ET";

        $p[] = "BT /F2 8 Tf 0.5 0.5 0.5 rg 350 " . ($payY + 22) . " Td (PAYMENT STATUS) Tj ET";
        $statusColor = $isPaid ? "0.08 0.5 0.2" : "0.7 0.3 0.0";
        $p[] = "BT /F2 9 Tf {$statusColor} rg 350 " . ($payY + 10) . " Td (" . self::escapePdfText($payStatus) . ") Tj ET";

        // Thank you footer
        $footY = 100;
        $p[] = "q 0.88 0.88 0.88 RG 0.5 w 40 {$footY} 515 0 m 555 {$footY} l s Q";
        $p[] = "BT /F2 10 Tf 0.137 0.365 0.227 rg 190 " . ($footY - 20) . " Td (Thank you for choosing N.A Fresh Fruits & Coconuts!) Tj ET";
        $p[] = "BT /F1 8 Tf 0.5 0.5 0.5 rg 180 " . ($footY - 34) . " Td (For queries, delivery updates or support, contact " . self::escapePdfText($storePhone) . ") Tj ET";

        $streamContent = implode("\n", $p);
        $streamLength = strlen($streamContent);

        // Build valid PDF 1.4 Object Graph
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>";
        $objects[4] = "<< /Length {$streamLength} >>\nstream\n{$streamContent}\nendstream";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $output = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [];
        
        for ($i = 1; $i <= 6; $i++) {
            $offsets[$i] = strlen($output);
            $output .= "{$i} 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefOffset = strlen($output);
        $output .= "xref\n0 7\n";
        $output .= "0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $output .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $output .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $output;
    }

    /**
     * Stream pure native PDF 1.4 bytecode without external libraries
     * Directly serves PDF attachment to browser
     */
    public static function streamPdf($invoiceOrOrder, ?array $order = null, ?array $items = null): void {
        if ($order === null) {
            $resolved = self::resolveFullData($invoiceOrOrder);
            if (!$resolved || empty($resolved['invoice'])) {
                throw new Exception("Invoice could not be resolved for order.");
            }
            $invoice = $resolved['invoice'];
            $order = $resolved['order'];
            $items = $resolved['items'];
        } else {
            $invoice = $invoiceOrOrder;
            $items = $items ?? [];
        }

        $invNumber = $invoice['invoice_number'] ?? 'NAF-BILL';
        $filename = "Invoice-" . preg_replace('/[^A-Za-z0-9_-]/', '', $invNumber) . ".pdf";
        $output = self::generatePdfString($invoice, $order, $items);

        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($output));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $output;
        exit;
    }

    private static function escapePdfText(string $text): string {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        // Clean to ASCII compatible representation
        return preg_replace('/[^\x20-\x7E]/', '', $text);
    }
}
