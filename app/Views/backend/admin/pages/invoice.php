<style>
    /* Card Container */
    #print_invoice {
        max-width: 1100px;
        margin: 0 auto;
        border: 0;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(25, 42, 70, 0.08);
        background: #ffffff;
        color: #0c2368;
        font-family: 'DejaVu Sans', sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto;
    }

    #print_invoice .card-body {
        padding: 2.2rem !important;
    }

    /* Print-Safe Table Grid (Side by Side alignment without float bugs) */
    .print-grid {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .print-grid td {
        vertical-align: top;
    }

    /* Header & Branding */
    .inv-company-title {
        color: #0c2368;
        font-size: 11.5pt;
        font-weight: 800;
        letter-spacing: 0.3px;
        margin-top: 10px;
        margin-bottom: 4px;
        text-transform: uppercase;
    }

    .inv-company-meta {
        color: #0c2368;
        font-size: 8.2pt;
        line-height: 1.45;
        margin-bottom: 3px;
    }

    .inv-headline {
        font-size: 20pt;
        font-weight: 900;
        color: #0c2368;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        text-align: right;
        margin-bottom: 10px;
    }

    .inv-headline span {
        color: #1aa037;
        font-weight: 300;
        margin: 0 5px;
    }

    .meta-box-table {
        margin-left: auto;
        width: auto;
        border-collapse: collapse;
    }

    .meta-box-table td {
        padding: 3px 0;
        font-size: 8.5pt;
        color: #0c2368;
    }

    .meta-label {
        font-weight: 600;
        width: 105px;
        text-align: left;
    }

    .meta-colon {
        width: 14px;
        text-align: center;
    }

    .meta-val {
        font-weight: 700;
        text-align: left;
    }

    .meta-val-status {
        color: #1aa037 !important;
        font-weight: 800;
    }

    /* Info Cards (Service By, Billing Address, Booking Details) */
    .inv-badge-card {
        border: 1px solid #c8d7f0 !important;
        border-radius: 8px !important;
        background: #ffffff;
        overflow: hidden;
        height: 100%;
        margin-bottom: 12px;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .inv-card-badge {
        background-color: #0c2368 !important;
        color: #ffffff !important;
        font-size: 7.8pt;
        font-weight: 800;
        letter-spacing: 0.6px;
        padding: 5.5px 14px;
        border-radius: 0 0 10px 0;
        display: inline-block;
        text-transform: uppercase;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .inv-card-content {
        padding: 10px 14px 12px 14px;
    }

    .inv-field-table {
        width: 100%;
        border-collapse: collapse;
    }

    .inv-field-table td {
        padding: 3px 0;
        font-size: 8.2pt;
        color: #0c2368;
        vertical-align: top;
    }

    .field-label {
        font-weight: 700;
        width: 75px;
    }

    .field-colon {
        width: 14px;
    }

    .field-val {
        word-break: normal;
        overflow-wrap: break-word;
    }

    /* Items Table */
    .table-responsive-clean {
        width: 100%;
        margin-top: 14px;
        overflow-x: auto;
    }

    .invoice-table-styled {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #c8d7f0;
    }

    .invoice-table-styled thead th {
        background-color: #0c2368 !important;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 7.8pt;
        padding: 8px 6px;
        border-right: 1px solid #233e8a;
        text-align: center;
        letter-spacing: 0.2px;
        text-transform: uppercase;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .invoice-table-styled thead th:last-child {
        border-right: none;
    }

    .invoice-table-styled tbody td {
        padding: 7px 6px;
        border-bottom: 1px solid #dce5f2;
        border-right: 1px solid #dce5f2;
        color: #0c2368;
        font-size: 8pt;
    }

    .invoice-table-styled tbody td:last-child {
        border-right: none;
    }

    .invoice-table-styled .text-right { text-align: right; }
    .invoice-table-styled .text-center { text-align: center; }

    /* Partner Image & Notes */
    .partner-logo-img {
        width: 75px;
        height: 75px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #dce5f2;
    }

    .notes-card {
        border: 1px solid #c8d7f0 !important;
        border-radius: 8px !important;
        background: #ffffff;
        overflow: hidden;
        margin-top: 10px;
    }

    .notes-content {
        padding: 8px 12px;
        font-size: 7.6pt;
        color: #0c2368;
        line-height: 1.55;
    }

    .notes-content ul {
        margin: 0;
        padding-left: 14px;
    }

    /* Summary Table */
    .inv-summary-table {
        width: 100%;
        border-collapse: collapse;
    }

    .inv-summary-table td {
        padding: 4px 6px;
        font-size: 8.5pt;
        color: #0c2368;
        font-weight: 600;
    }

    .inv-summary-table tr.total-row td {
        background-color: #0c2368 !important;
        color: #ffffff !important;
        font-size: 11pt;
        font-weight: 800;
        padding: 9px 10px;
        border-radius: 4px;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Total in Words */
    .words-box {
        border: 1px solid #bce2be !important;
        background: #fbfefb !important;
        border-radius: 6px;
        padding: 7px 10px;
        margin-top: 10px;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Footer Tagline & Strip */
    .inv-footer-strip {
        margin-top: 20px;
        border-top: 1px solid #cbd8ec;
        padding-top: 10px;
        color: #0c2368;
        font-size: 7.5pt;
    }

    .inv-brand-banner {
        background-color: #0c2368 !important;
        color: #ffffff !important;
        text-align: center;
        font-size: 7.5pt;
        padding: 6px;
        margin-top: 8px;
        border-radius: 4px;
        letter-spacing: 0.5px;
        font-weight: 600;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Strict Media Print Rules: Keeps layout 100% stable */
    @media print {
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }

        html,
        body {
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }

        body * {
            visibility: hidden;
        }

        .main-content,
        .main-content .section,
        .main-content .section-body,
        #print_invoice,
        #print_invoice * {
            visibility: visible !important;
        }

        .main-content,
        .main-content .section,
        .main-content .section-body {
            width: 100% !important;
            max-width: none !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        #print_invoice {
            position: relative !important;
            left: auto !important;
            top: auto !important;
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
            background: #ffffff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        #print_invoice .card-body {
            display: block !important;
            padding: 0 !important;
        }

        #section-not-to-print,
        .section-header,
        .main-sidebar,
        .main-footer,
        .navbar {
            display: none !important;
        }

        #section-not-to-print {
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            max-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }

        .partner-logo-img {
            display: block !important;
            width: 75px !important;
            min-width: 75px !important;
            max-width: 75px !important;
            height: 75px !important;
            min-height: 75px !important;
            max-height: 75px !important;
            object-fit: cover !important;
        }

        .table-responsive-clean {
            overflow: visible !important;
        }

        .invoice-table-styled,
        .print-grid {
            page-break-inside: avoid;
        }
    }
</style>

<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><?= labels('invoice', 'Invoice') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('admin/dashboard') ?>"><?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('admin/orders') ?>"><?= labels('booking', 'Booking') ?></a></div>
                <div class="breadcrumb-item"><?= labels('invoice', 'Invoice') ?></div>
            </div>
        </div>

        <div class="section-body">
            <div class="container-fluid card" id="print_invoice">
                <div class="card-body">

                    <!-- Top Header: Logo, Company & Invoice Meta -->
                    <table class="print-grid" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="55%">
                                <img src="<?= !empty($data['logo']) ? esc($data['logo']) : base_url('public/backend/assets/img/news/img01.jpg') ?>" 
                                     alt="Company Logo" 
                                     style="height: 52px; width: auto; max-width: 220px; object-fit: contain;">
                                <div class="inv-company-title"><?= esc(get_company_title_with_fallback($data)) ?></div>
                                <?php $companyAddress = getTranslatedSetting('general_settings', 'address'); ?>
                                <?php if (!empty($companyAddress)): ?>
                                    <div class="inv-company-meta">&#128205; <?= esc($companyAddress) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($data['support_email'])): ?>
                                    <div class="inv-company-meta">&#9993; <?= esc($data['support_email']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($data['phone'])): ?>
                                    <div class="inv-company-meta">&#128222; <?= esc($data['phone']) ?></div>
                                <?php endif; ?>
                                <div class="inv-company-meta">&#127760; GSTIN/UIN: 21AAWCA9895N1ZK</div>
                            </td>
                            <td width="45%" align="right">
                                <div class="inv-headline"><span>—</span> <?= labels('invoice', 'TAX INVOICE') ?> <span>—</span></div>
                                <table class="meta-box-table" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td class="meta-label">&#128196; <?= labels('invoice_no', 'Invoice no') ?></td>
                                        <td class="meta-colon">:</td>
                                        <td class="meta-val"><?= esc($order['invoice_no'] ?? '') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="meta-label">&#128197; <?= labels('invoice_date', 'Invoice Date') ?></td>
                                        <td class="meta-colon">:</td>
                                        <td class="meta-val">
                                            <?php 
                                                $invoiceDate = !empty($order['date_of_service']) ? date('d-m-Y', strtotime($order['date_of_service'])) : (!empty($order['created_at']) ? date('d-m-Y', strtotime($order['created_at'])) : date('d-m-Y'));
                                                echo esc($invoiceDate);
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="meta-label">&#128338; <?= labels('service_time', 'Service Time') ?></td>
                                        <td class="meta-colon">:</td>
                                        <td class="meta-val"><?= !empty($order['starting_time']) ? date('h:i A', strtotime($order['starting_time'])) : '-' ?></td>
                                    </tr>
                                    <tr>
                                        <td class="meta-label">&#9989; <?= labels('booking_status', 'Booking status') ?></td>
                                        <td class="meta-colon">:</td>
                                        <td class="meta-val meta-val-status"><?= esc(ucfirst($order['status'] ?? 'Completed')) ?></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <!-- Cards Row 1: SERVICE BY & BILLING ADDRESS -->
                    <table class="print-grid" cellpadding="0" cellspacing="0" style="margin-top: 14px;">
                        <tr>
                            <!-- SERVICE BY Box -->
                            <td width="48.5%">
                                <div class="inv-badge-card">
                                    <div class="inv-card-badge"><?= labels('service_by', 'SERVICE BY') ?></div>
                                    <div class="inv-card-content">
                                        <table class="inv-field-table" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td class="field-label">&#128100; <?= labels('name', 'Name') ?></td>
                                                <td class="field-colon">:</td>
                                                <td class="field-val"><strong><?= esc($partner_details['company_name'] ?? '') ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">&#9993; <?= labels('email', 'Email') ?></td>
                                                <td class="field-colon">:</td>
                                                <td class="field-val"><?= esc($partner_details['email'] ?? '') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">&#128222; <?= labels('phone', 'Phone') ?></td>
                                                <td class="field-colon">:</td>
                                                <td class="field-val"><?= esc($partner_details['phone'] ?? '') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">&#128205; <?= labels('address', 'Address') ?></td>
                                                <td class="field-colon">:</td>
                                                <td class="field-val"><?= esc($partner_details['address'] ?? '') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </td>

                            <td width="3%"></td>

                            <!-- BILLING ADDRESS Box -->
                            <td width="48.5%">
                                <div class="inv-badge-card">
                                    <div class="inv-card-badge"><?= labels('billing_address', 'BILLING ADDRESS') ?></div>
                                    <div class="inv-card-content">
                                        <table class="inv-field-table" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td class="field-label">&#128100; <?= labels('Name', 'Name') ?></td>
                                                <td class="field-colon">:</td>
                                                <td class="field-val"><strong><?= esc($user_details['username'] ?? '') ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">&#9993; <?= labels('email', 'Email') ?></td>
                                                <td class="field-colon">:</td>
                                                <td class="field-val"><?= esc($user_details['email'] ?? '') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">&#128222; <?= labels('phone', 'Phone') ?></td>
                                                <td class="field-colon">:</td>
                                                <td class="field-val"><?= esc($user_details['phone'] ?? '') ?></td>
                                            </tr>
                                            <?php if (!empty($order['address']) && ($order['address_id'] ?? '0') !== '0'): ?>
                                            <tr>
                                                <td class="field-label">&#128205; <?= labels('address', 'Address') ?></td>
                                                <td class="field-colon">:</td>
                                                <td class="field-val"><?= esc($order['address']) ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <!-- Items Table -->
                    <div class="table-responsive-clean">
                        <?php if (isset($rows) && !empty($rows)): ?>
                            <!-- Static Direct Server Rendered Table (Preferred for Print) -->
                            <table class="invoice-table-styled" cellpadding="0" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="4%">#</th>
                                        <th width="28%" align="left" style="text-align: left; padding-left: 8px;"><?= labels('services', 'Service / Item') ?></th>
                                        <th width="7%"><?= labels('quantity', 'Qty') ?></th>
                                        <th width="12%" align="right"><?= labels('price', 'Price') ?></th>
                                        <th width="12%" align="right"><?= labels('discount', 'Discount Price') ?></th>
                                        <th width="13%" align="right"><?= labels('net_amount', 'Net Amount') ?></th>
                                        <th width="11%" align="right"><?= labels('tax', 'Tax') ?></th>
                                        <th width="13%" align="right"><?= labels('sub_total_including_tax', 'Sub Total') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $index => $row): ?>
                                        <tr>
                                            <td class="text-center"><?= $index + 1 ?></td>
                                            <td style="padding-left: 8px; font-weight: 600;"><?= esc(strip_tags($row['service_title'] ?? '')) ?></td>
                                            <td class="text-center"><?= esc($row['quantity'] ?? '1') ?></td>
                                            <td class="text-right"><?= esc($row['price'] ?? '0.00') ?></td>
                                            <td class="text-right"><?= esc($row['discount'] ?? '0.00') ?></td>
                                            <td class="text-right"><?= esc($row['net_amount'] ?? '0.00') ?></td>
                                            <td class="text-right"><?= esc(($row['tax'] ?? '') . (!empty($row['tax_amount']) ? ' (' . $row['tax_amount'] . ')' : '')) ?></td>
                                            <td class="text-right"><?= esc($row['subtotal'] ?? '0.00') ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if ((float) ($order['visiting_charges'] ?? 0) != 0): ?>
                                        <tr>
                                            <td class="text-center"><?= count($rows) + 1 ?></td>
                                            <td style="padding-left: 8px;">Visiting Charge</td>
                                            <td class="text-center">1</td>
                                            <td class="text-right"><?= number_format((float)$order['visiting_charges'], 2) ?></td>
                                            <td class="text-right">0.00</td>
                                            <td class="text-right"><?= number_format((float)$order['visiting_charges'], 2) ?></td>
                                            <td class="text-right">-</td>
                                            <td class="text-right"><?= number_format((float)$order['visiting_charges'], 2) ?></td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php 
                                        $addCharges = is_array($additional_charges ?? null) ? $additional_charges : [];
                                        foreach ($addCharges as $cIdx => $cRow): 
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= count($rows) + ($order['visiting_charges'] != 0 ? 2 : 1) + $cIdx ?></td>
                                            <td style="padding-left: 8px;"><?= esc($cRow['name'] ?? 'Additional Charge') ?></td>
                                            <td class="text-center">1</td>
                                            <td class="text-right"><?= number_format((float)($cRow['charge'] ?? 0), 2) ?></td>
                                            <td class="text-right">0.00</td>
                                            <td class="text-right"><?= number_format((float)($cRow['charge'] ?? 0), 2) ?></td>
                                            <td class="text-right">-</td>
                                            <td class="text-right"><?= number_format((float)($cRow['charge'] ?? 0), 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <!-- Fallback: Bootstrap AJAX Server-Side Table -->
                            <table class="table table-bordered table-sm invoice-table-styled" id="invoice_table" 
                                   data-show-export="true" 
                                   data-export-types="['txt','excel','csv']" 
                                   data-export-options='{"fileName": "invoice-order-list","ignoreColumn": ["action"]}' 
                                   data-auto-refresh="true" 
                                   data-toggle="table" 
                                   data-search-highlight="true" 
                                   data-url="<?= base_url('admin/orders/invoice_table/' . $order['id']); ?>" 
                                   data-side-pagination="server">
                                <thead>
                                    <tr>
                                        <th data-field="service_title" data-visible="true"><?= labels('services', 'Service') ?></th>
                                        <th data-field="price" data-visible="true"><?= labels('price', 'Price') ?></th>
                                        <th data-field="discount" data-visible="true"><?= labels('discount', 'Discount Price') ?></th>
                                        <th data-field="net_amount" data-visible="true"><?= labels('net_amount', 'Net Amount') ?></th>
                                        <th data-field="tax" data-visible="true"><?= labels('tax', 'Tax') ?></th>
                                        <th data-field="tax_amount" data-visible="true"><?= labels('tax_amount', 'Tax Amount') ?></th>
                                        <th data-field="quantity" data-visible="true"><?= labels('quantity', 'Quantity') ?></th>
                                        <th data-field="subtotal" data-visible="true"><?= labels('sub_total_including_tax', 'Sub total (Including Tax)') ?></th>
                                    </tr>
                                </thead>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Bottom Section: Partner Logo, Notes & Financial Totals -->
                    <table class="print-grid" cellpadding="0" cellspacing="0" style="margin-top: 14px;">
                        <tr>
                            <!-- Left: Partner Details Image, Thank You & Notes -->
                            <td width="48.5%">
                                <table class="print-grid" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td width="90">
                                            <img src="<?= esc($partner_details['image'] ?? base_url('public/uploads/profiles/default.png')) ?>" alt="Partner logo" class="partner-logo-img">
                                        </td>
                                        <td valign="middle" style="padding-left: 12px;">
                                            <h6 style="color: #0c2368; font-weight: 800; margin: 0 0 4px 0;">
                                                <?= labels('thank_you_for_your_business', 'Thank you for your Business') ?>
                                            </h6>
                                            <span style="font-size: 7.8pt; color: #52627a;">We appreciate your trust with us.</span>
                                        </td>
                                    </tr>
                                </table>

                                <div class="notes-card">
                                    <div class="inv-card-badge">NOTES</div>
                                    <div class="notes-content">
                                        <ul>
                                            <li>Goods/Services once completed will not be disputed.</li>
                                            <li>Warranty, if any, is as per the brand/partner terms.</li>
                                            <li>For any queries, please contact our support team.</li>
                                        </ul>
                                    </div>
                                </div>
                            </td>

                            <td width="3%"></td>

                            <!-- Right: Summary Totals & Words -->
                            <td width="48.5%">
                                <table class="inv-summary-table" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td>Taxable Amount</td>
                                        <td align="right">₹<?= number_format((float)($order['total'] ?? $order['taxable_amount'] ?? 0), 2) ?></td>
                                    </tr>
                                    <?php if ((float) ($order['promo_discount'] ?? 0) > 0): ?>
                                    <tr>
                                        <td>Promo Code Discount</td>
                                        <td align="right">-₹<?= number_format((float)$order['promo_discount'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ((float) ($order['visiting_charges'] ?? 0) != 0): ?>
                                    <tr>
                                        <td>Visiting Charges</td>
                                        <td align="right">+₹<?= number_format((float)$order['visiting_charges'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php 
                                        $addCharges = is_array($additional_charges ?? null) ? $additional_charges : [];
                                        foreach ($addCharges as $cRow): 
                                    ?>
                                    <tr>
                                        <td><?= esc($cRow['name'] ?? 'Additional Charge') ?></td>
                                        <td align="right">+₹<?= number_format((float)($cRow['charge'] ?? 0), 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td>Tax / GST</td>
                                        <td align="right">+₹<?= number_format((float)($order['tax'] ?? 0), 2) ?></td>
                                    </tr>
                                    <tr class="total-row">
                                        <td>Total Invoice Value</td>
                                        <td align="right">₹<?= number_format((float)($order['final_total'] ?? 0), 2) ?></td>
                                    </tr>
                                </table>

                                <table class="words-box print-grid" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td width="30" valign="middle" align="center">
                                            <span style="border: 1px solid #238b34; color: #238b34; font-weight: bold; border-radius: 3px; padding: 2px 6px; font-size: 9pt;">₹</span>
                                        </td>
                                        <td valign="middle" style="padding-left: 8px; font-size: 7.6pt; color: #0c2368;">
                                            <strong>Total Invoice Value (in words):</strong><br>
                                            <span>Paid as per the selected payment method.</span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <!-- Bottom Footer Strip -->
                    <table class="print-grid inv-footer-strip" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="25%">
                                &#128222; <strong>1800 1233577</strong><br>
                                <span style="color: #6c757d; padding-left: 17px;">(Toll Free)</span>
                            </td>
                            <td width="25%">&#9993; <?= !empty($data['support_email']) ? esc($data['support_email']) : 'support@leafit.in' ?></td>
                            <td width="20%">&#127760; www.leafit.in</td>
                            <td width="30%">&#128205; 1st Floor, Sahadevkhunta,<br><span style="padding-left: 17px;">Balasore, Odisha - 756001</span></td>
                        </tr>
                    </table>

                    <div class="inv-brand-banner">
                        Smart People &nbsp;|&nbsp; Safe Homes &nbsp;|&nbsp; Connected India
                    </div>

                    <!-- Print Button (Hidden on Print Mode) -->
                    <div id="section-not-to-print" class="text-right mt-4">
                        <button type="button" value="Print this page" onclick="printInvoiceDocument('print_invoice')" class="btn btn-primary btn-lg">
                            <i class="fa fa-print"></i> <?= labels('print', 'Print') ?>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<script>
window.printInvoiceDocument = function (divId) {
    if (document.getElementById(divId)) {
        window.print();
    }
}
</script>