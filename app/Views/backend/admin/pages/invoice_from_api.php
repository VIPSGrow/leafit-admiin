<?php
    $money = static function ($value) use ($currency): string {
        return $currency . number_format((float) ($value ?? 0), 2, '.', '');
    };
    $invoiceDate = !empty($order['created_at']) ? (new DateTime($order['created_at']))->format('d-m-Y') : date('d-m-Y');
    $additionalCharges = is_array($additional_charges ?? null) ? $additional_charges : [];
    $visitingCharge = (float) ($order['visiting_charges'] ?? 0);
    $visitingTaxRate = (float) ($order['visiting_tax_percentage'] ?? 18);
    $visitingTax = $visitingCharge * $visitingTaxRate / 100;
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            margin: 12mm 15mm 12mm 15mm;
        }
        body {
            font-family: dejavusans, sans-serif;
            font-size: 8pt;
            color: #0c2368;
            margin: 0;
            padding: 0;
        }
        p { margin: 0 0 3px 0; padding: 0; }
        table { border-collapse: collapse; width: 100%; }

        /* Top Header */
        .company-title {
            color: #0c2368;
            font-size: 10pt;
            font-weight: bold;
            margin-top: 6px;
            margin-bottom: 4px;
        }
        .company-info {
            color: #0c2368;
            font-size: 7.5pt;
            line-height: 1.3;
        }

        /* Invoice Heading */
        .invoice-title {
            color: #0c2368;
            font-size: 18pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-align: right;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .invoice-title span {
            color: #1aa037;
            font-weight: normal;
        }

        /* Meta details */
        .meta-table td {
            font-size: 8pt;
            padding: 2px 0;
        }
        .meta-label {
            color: #0c2368;
            font-weight: bold;
            width: 85px;
        }
        .meta-colon {
            color: #0c2368;
            width: 12px;
            text-align: center;
        }
        .meta-value {
            color: #0c2368;
            font-weight: bold;
            text-align: right;
        }
        .status-green {
            color: #1aa037;
            font-weight: bold;
        }

        /* Box styling */
        .card-box {
            border: 1px solid #c9d7f0;
            background: #ffffff;
        }
        .card-header-cell {
            background-color: #0c2368;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 4px 10px;
            text-transform: uppercase;
        }
        .card-body-table td {
            font-size: 7.5pt;
            padding: 2px 0;
            vertical-align: top;
        }
        .field-label {
            color: #0c2368;
            font-weight: bold;
            width: 85px;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border: 1px solid #c9d7f0;
            margin-top: 12px;
        }
        .items-table th {
            background-color: #0c2368;
            color: #ffffff;
            font-size: 7.2pt;
            font-weight: bold;
            padding: 6px 4px;
            border-right: 1px solid #233e8a;
        }
        .items-table td {
            font-size: 7.5pt;
            padding: 6px 4px;
            border-bottom: 1px solid #e0e8f5;
            border-right: 1px solid #e0e8f5;
            color: #0c2368;
        }
        .center { text-align: center; }
        .right { text-align: right; white-space: nowrap; }

        /* Notes Box */
        .notes-table td {
            font-size: 7.5pt;
            color: #0c2368;
            line-height: 1.4;
            padding: 2px 0;
        }

        /* Calculation Summary */
        .calc-table td {
            font-size: 8pt;
            padding: 3px 5px;
        }
        .calc-label {
            color: #0c2368;
            font-weight: bold;
            text-align: left;
        }
        .calc-val {
            color: #0c2368;
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
        }
        .total-row td {
            background-color: #0c2368;
            color: #ffffff !important;
            font-size: 10pt;
            font-weight: bold;
            padding: 7px 8px;
        }

        /* In words Box */
        .words-box {
            border: 1px solid #c8e4cb;
            background-color: #f7fbf7;
            margin-top: 8px;
        }

        /* Footer */
        .footer-table td {
            font-size: 7.5pt;
            color: #0c2368;
            padding-top: 8px;
        }
        .footer-banner {
            background-color: #0c2368;
            color: #ffffff;
            text-align: center;
            font-size: 7.5pt;
            padding: 5px;
            margin-top: 8px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- Header Table -->
    <table width="100%">
        <tr>
            <td width="55%" valign="top">
                <img src="https://leafit-uploads.s3.ap-south-1.amazonaws.com/web_settings/1785141856_efbef9f712e7b21f8042.png" style="width: 140px; height: auto;" alt="Logo"><br>
                <div class="company-title">ACTIVEPRO TECHNOLOGY PRIVATE LIMITED</div>
                <div class="company-info">&#9679; 1st Floor, Sahadevkhunta, Balasore, Odisha - 756001</div>
                <div class="company-info">&#9673; GSTIN/UIN: 21AAWCA9895N1ZK</div>
            </td>
            <td width="45%" valign="top" align="right">
                <div class="invoice-title"><span>—</span> TAX INVOICE <span>—</span></div>
                <table class="meta-table" align="right">
                    <tr>
                        <td class="meta-label">&#9632; Invoice No.</td>
                        <td class="meta-colon">:</td>
                        <td class="meta-value"><?= esc($order['invoice_no'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="meta-label">&#9632; Invoice Date</td>
                        <td class="meta-colon">:</td>
                        <td class="meta-value"><?= esc($invoiceDate) ?></td>
                    </tr>
                    <tr>
                        <td class="meta-label">&#9673; Due Date</td>
                        <td class="meta-colon">:</td>
                        <td class="meta-value"><?= esc($invoiceDate) ?></td>
                    </tr>
                    <tr>
                        <td class="meta-label">&#10003; Status</td>
                        <td class="meta-colon">:</td>
                        <td class="meta-value status-green"><?= esc(ucfirst($order['status'] ?? 'Completed')) ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Billing & Service Boxes -->
    <table width="100%" style="margin-top: 10px;">
        <tr>
            <!-- Left Card: Billing Address -->
            <td width="48.5%" valign="top" class="card-box">
                <table>
                    <tr>
                        <td class="card-header-cell" width="50%">BILLING ADDRESS</td>
                        <td width="50%"></td>
                    </tr>
                </table>
                <div style="padding: 8px 10px;">
                    <table class="card-body-table">
                        <tr>
                            <td class="field-label">&#9679; Name</td>
                            <td width="10">:</td>
                            <td><?= esc($user_details['username'] ?? '') ?></td>
                        </tr>
                        <?php if (empty($hide_customer_contact)): ?>
                            <tr>
                                <td class="field-label">&#9993; Email</td>
                                <td width="10">:</td>
                                <td><?= esc($user_details['email'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <td class="field-label">&#9742; Phone</td>
                                <td width="10">:</td>
                                <td><?= esc($user_details['phone'] ?? '') ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($order['address']) && ($order['address_id'] ?? '0') !== '0'): ?>
                        <tr>
                            <td class="field-label">&#9679; Address</td>
                            <td width="10">:</td>
                            <td><?= esc($order['address']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="field-label">&#9673; GSTIN/UIN</td>
                            <td width="10">:</td>
                            <td><?= esc($user_details['gstin'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>
            </td>

            <td width="3%"></td>

            <!-- Right Card: Booking Details -->
            <td width="48.5%" valign="top" class="card-box">
                <table>
                    <tr>
                        <td class="card-header-cell" width="70%">BOOKING &amp; SERVICE DETAILS</td>
                        <td width="30%"></td>
                    </tr>
                </table>
                <div style="padding: 8px 10px;">
                    <table class="card-body-table">
                        <tr>
                            <td class="field-label">&#9632; Booking ID</td>
                            <td width="10">:</td>
                            <td><?= esc($order['booking_id'] ?? $order['id'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="field-label">&#9632; Service Date</td>
                            <td width="10">:</td>
                            <td><?= esc(!empty($order['service_date']) ? date('d-m-Y', strtotime($order['service_date'])) : $invoiceDate) ?></td>
                        </tr>
                        <?php if (!empty($order['service_location'])): ?>
                        <tr>
                            <td class="field-label">&#9679; Service Location</td>
                            <td width="10">:</td>
                            <td><?= esc($order['service_location']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="field-label">&#10003; Payment</td>
                            <td width="10">:</td>
                            <td><?= esc(ucfirst($order['payment_method'] ?? 'Online')) ?></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="28%" align="left">Service / Item</th>
                <th width="6%">Qty</th>
                <th width="12%" align="right">Rate (<?= esc($currency) ?>)</th>
                <th width="12%" align="right">Discount (<?= esc($currency) ?>)</th>
                <th width="13%" align="right">Taxable Amount (<?= esc($currency) ?>)</th>
                <th width="12%" align="right">Tax</th>
                <th width="13%" align="right" style="border-right: none;">Amount (<?= esc($currency) ?>)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $index => $row): ?>
                <?php
                    $rowQuantity = max(1, (int) ($row['quantity'] ?? 1));
                    $rowTaxableAmount = (float) str_replace([',', $currency], '', (string) ($row['net_amount'] ?? 0));
                    $rowTaxRate = (float) preg_replace('/[^0-9.]/', '', (string) ($row['tax'] ?? 0));
                    $rowTaxAmount = $rowTaxableAmount * $rowQuantity * $rowTaxRate / 100;
                ?>
                <tr>
                    <td class="center"><?= $index + 1 ?></td>
                    <td><?= esc(strip_tags($row['service_title'] ?? '')) ?></td>
                    <td class="center"><?= esc($row['quantity'] ?? '1') ?></td>
                    <td class="right"><?= esc($row['price'] ?? '') ?></td>
                    <td class="right"><?= esc($row['discount'] ?? '') ?></td>
                    <td class="right"><?= esc($row['net_amount'] ?? '') ?></td>
                    <td class="right"><?= number_format($rowTaxRate, 2, '.', '') ?>% (<?= $money($rowTaxAmount) ?>)</td>
                    <td class="right" style="border-right: none;"><?= esc($row['subtotal'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if ($visitingCharge != 0): ?>
                <tr>
                    <td class="center"><?= count($rows) + 1 ?></td>
                    <td>Visiting Charge</td>
                    <td class="center">1</td>
                    <td class="right"><?= $money($visitingCharge) ?></td>
                    <td class="right"><?= $money(0) ?></td>
                                        <td class="right"><?= $money($visitingCharge) ?></td>
                                        <td class="right"><?= number_format($visitingTaxRate, 2, '.', '') ?>% (<?= $money($visitingTax) ?>)</td>
                                        <td class="right" style="border-right: none;"><?= $money($visitingCharge + $visitingTax) ?></td>
                </tr>
            <?php endif; ?>

            <?php foreach ($additionalCharges as $chargeIndex => $charge): ?>
                <tr>
                    <td class="center"><?= count($rows) + ($order['visiting_charges'] != 0 ? 2 : 1) + $chargeIndex ?></td>
                    <td><?= esc($charge['name'] ?? 'Additional Charge') ?></td>
                    <td class="center">1</td>
                    <td class="right"><?= $money($charge['charge'] ?? 0) ?></td>
                    <td class="right"><?= $money(0) ?></td>
                    <td class="right"><?= $money($charge['charge'] ?? 0) ?></td>
                    <td class="right"><?= number_format((float) ($charge['tax_percentage'] ?? 0), 2, '.', '') ?>% (<?= $money($charge['tax_amount'] ?? 0) ?>)</td>
                    <td class="right" style="border-right: none;"><?= $money((float) ($charge['charge'] ?? 0) + (float) ($charge['tax_amount'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Notes & Calculation Section -->
    <table width="100%" style="margin-top: 10px;">
        <tr>
            <!-- Notes Box -->
            <td width="48.5%" valign="top" class="card-box">
                <table>
                    <tr>
                        <td class="card-header-cell" width="30%">NOTES</td>
                        <td width="70%"></td>
                    </tr>
                </table>
                <div style="padding: 8px 10px;">
                    <table class="notes-table">
                        <tr>
                            <td width="10" valign="top">&bull;</td>
                            <td>Goods once sold will not be taken back or exchanged.</td>
                        </tr>
                        <tr>
                            <td width="10" valign="top">&bull;</td>
                            <td>Warranty, if any, is as per the manufacturer / brand policy.</td>
                        </tr>
                        <tr>
                            <td width="10" valign="top">&bull;</td>
                            <td>For any queries, please contact our customer support.</td>
                        </tr>
                    </table>
                </div>
            </td>

            <td width="3%"></td>

            <!-- Calculation Box -->
            <td width="48.5%" valign="top">
                <table class="calc-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="calc-label">Taxable Amount</td>
                        <td class="calc-val"><?= $money($order['total'] ?? 0) ?></td>
                    </tr>
                    <?php if ((float) ($order['promo_discount'] ?? 0) > 0): ?>
                    <tr>
                        <td class="calc-label">Promo Code Discount</td>
                        <td class="calc-val">-<?= $money($order['promo_discount'] ?? 0) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ((float) ($order['visiting_charges'] ?? 0) != 0): ?>
                    <tr>
                        <td class="calc-label">Visiting Charges</td>
                        <td class="calc-val">+<?= $money($order['visiting_charges'] ?? 0) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($additionalCharges as $charge): ?>
                    <tr>
                        <td class="calc-label"><?= esc($charge['name'] ?? 'Additional Charge') ?></td>
                        <td class="calc-val">+<?= $money($charge['charge'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php /* Additional Charge Tax is included in the combined Tax / GST row. */ ?>
                    <tr>
                        <td class="calc-label">Tax / GST</td>
                        <td class="calc-val">+<?= $money($order['tax'] ?? 0) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Total Invoice Value (in figure)</td>
                        <td style="text-align: right; color: #ffffff;"><?= $money($order['final_total'] ?? 0) ?></td>
                    </tr>
                </table>

                <!-- In Words -->
                <table class="words-box" width="100%" cellpadding="6">
                    <tr>
                        <td width="30" align="center" valign="middle">
                            <div style="border: 1px solid #1aa037; color: #1aa037; font-weight: bold; padding: 2px 4px; font-size: 8pt; text-align: center;">
                                <?= esc($currency) ?>
                            </div>
                        </td>
                        <td style="font-size: 7.5pt; color: #0c2368; line-height: 1.3;">
                            <strong>Total Invoice Value (in words):</strong><br>
                            Paid as per the selected payment method.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Footer Area -->
    <table class="footer-table" width="100%" style="margin-top: 15px; border-top: 1px solid #c9d7f0;">
        <tr>
            <td width="25%" valign="top">
                &#9742; <strong>1800 1233577</strong><br>
                <span style="color: #666; font-size: 7pt; margin-left: 12px;">(Toll Free)</span>
            </td>
            <td width="25%" valign="top">
                &#9993; support@leafit.in
            </td>
            <td width="20%" valign="top">
                &#9673; www.leafit.in
            </td>
            <td width="30%" valign="top">
                &#9679; 1st Floor, Sahadevkhunta,<br>
                <span style="margin-left: 12px;">Balasore, Odisha - 756001</span>
            </td>
        </tr>
    </table>

    <div class="footer-banner">
        Smart People &nbsp;|&nbsp; Safe Homes &nbsp;|&nbsp; Connected India
    </div>

</body>
</html>