<?php
$base_url = base_url();
?>

<head>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
</head>
<style>
    /* ── Consistent border-radius for all cards ── */
    .order-detail-card {
        border-radius: 0.75rem !important;
        overflow: hidden;
    }

    .order-detail-card .card-header {
        border-radius: 0.75rem 0.75rem 0 0 !important;
    }

    .order-detail-card .card-body:last-child {
        border-radius: 0 0 0.75rem 0.75rem !important;
    }


    /* Removed custom info-tab pills styles to align tabs structure using Bootstrap and jQuery */

    /* ── Financial summary list items ── */
    .financial-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 16px;
        font-size: 14px;
    }

    .financial-list li.total-row {
        background: color-mix(in srgb, var(--primary-color) 10%, transparent) !important;
        color: #000000 !important;
        font-weight: 700;
        border-radius: 0 0 0.75rem 0.75rem;
        padding: 12px 16px;
        font-size: 15px;
    }

    .financial-list hr {
        margin: 0;
        border-color: #eee;
    }

    /* ── Quick info block icon ── */
    .info-icon-box {
        width: 42px;
        height: 42px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    }

    /* ── Cancellation Accordion CSS ── */
    .cancel-accordion-header {
        height: 42px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .cancel-accordion-header .caret-icon {
        transition: transform 0.3s;
    }

    .cancel-accordion-header:not(.collapsed) .caret-icon {
        transform: rotate(180deg);
    }

    /* ── Booked service item card ── */
    .service-item-card {
        border-radius: 0.65rem !important;
        border: 1px solid rgba(0, 0, 0, 0.06) !important;
        box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06) !important;
        background: #fff !important;
    }

    .bh-hstepper {
        display: flex;
        align-items: flex-start;
    }

    .bh-hs-step {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
    }

    .bh-hs-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 14px;
        left: 50%;
        width: 100%;
        height: 2px;
        z-index: 0;
    }

    <?php
    $session = \Config\Services::session();
    $is_rtl = $session->get('is_rtl');
    if (!isset($is_rtl)) {
        $is_rtl = fetch_details('languages', ['is_default' => 1], ['is_rtl'])[0]['is_rtl'] ?? 0;
    }
    if ($is_rtl == 1) { ?>
        .bh-hs-step:not(:last-child)::after {
            left: auto;
            right: 50%;
        }

    <?php } ?>

    .bh-hs-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        z-index: 1;
        flex-shrink: 0;
    }

    .bh-hs-dot .material-symbols-outlined {
        font-size: 14px;
    }

    .bh-hs-label {
        font-size: 11px;
        text-align: center;
        margin-top: 5px;
        line-height: 1.3;
        word-break: break-word;
        max-width: 60px;
    }

    .bh-hs-step.done .bh-hs-dot {
        background: #d1fae5;
        color: #059669;
    }

    .bh-hs-step.done::after {
        background: #059669;
    }

    .bh-hs-step.current .bh-hs-dot {
        background: var(--primary-color, #4a90e2);
        color: #fff;
    }

    .bh-hs-step.current::after {
        background: #e2e8f0;
    }

    .bh-hs-step.pending .bh-hs-dot {
        background: #f1f5f9;
        color: #94a3b8;
    }

    .bh-hs-step.pending::after {
        background: #e2e8f0;
    }

    .bh-hs-label.done {
        color: #059669;
        font-weight: 600;
    }

    .bh-hs-label.current {
        color: var(--primary-color, #4a90e2);
        font-weight: 700;
    }

    .bh-hs-label.pending {
        color: #94a3b8;
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('view_booking', 'View Booking') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/orders') ?>"><i
                            class="fas fa-list-alt text-primary"></i>
                        <?= labels('bookings', 'Bookings') ?>
                    </a></div>
                <div class="breadcrumb-item"><?= labels('booking_details', 'Booking details') ?></a></div>
            </div>
        </div>
        <?= helper('form'); ?>
        <div class="section-body">

            <!-- ══════════ ROW 1: Main Split Layout ══════════ -->
            <div class="row">

                <!-- ──────── LEFT COLUMN (8/12) ──────── -->
                <div class="col-lg-8 col-12">

                    <!-- Main Booking Card: header + services + financial summary -->
                    <div class="card border-0 shadow-sm mb-4 order-detail-card">

                        <!-- Booking Header -->
                        <div class="card-body border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center">
                                    <h3 class="mb-0 text-dark font-weight-bold mr-2"><?= labels('booking', 'Booking') ?>
                                    </h3>
                                    <?php if (!empty($order_details['invoice_no'])): ?>
                                        <span class="badge badge-primary px-3 py-2 rounded-lg">
                                            <?= esc($order_details['invoice_no']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (($order_details['status'] ?? '') === 'on_the_way'): ?>
                                    <a href="<?= base_url('partner/orders/live_tracking/' . (int) $order_details['id']) ?>"
                                        class="btn btn-sm btn-outline-info">
                                        <i
                                            class="fas fa-map-marker-alt mr-1"></i><?= labels('live_tracking', 'Live Tracking') ?>
                                    </a>
                                <?php endif; ?>
                                <?php $partner_is_tracking = ($order_details['status'] === 'on_the_way') && (($order_details['lead_handyman_status'] ?? '') !== 'on_the_way'); ?>
                            </div>

                            <!-- 3-col info row -->
                            <div class="row">
                                <!-- Status -->
                                <div class="col-md-4 col-12 mb-3 mb-md-0">
                                    <div
                                        class="d-flex align-items-center p-3 shadow-sm bg-white overlapping-card h-100">
                                        <div class="info-icon-box mr-3">
                                            <?php
                                            $statusClass = 'text-secondary';
                                            if ($order_details['status'] == 'awaiting')
                                                $statusClass = 'text-warning';
                                            elseif ($order_details['status'] == 'confirmed')
                                                $statusClass = 'text-primary';
                                            elseif (in_array($order_details['status'], ['started', 'on_the_way', 'arrived']))
                                                $statusClass = 'text-info';
                                            elseif (in_array($order_details['status'], ['completed', 'booking_ended']))
                                                $statusClass = 'text-success';
                                            elseif ($order_details['status'] == 'cancelled')
                                                $statusClass = 'text-danger';
                                            ?>
                                            <i class="fas fa-info-circle <?= $statusClass ?>"></i>
                                        </div>
                                        <div>
                                            <small
                                                class="text-muted text-uppercase font-weight-bold d-block"><?= labels('booking_status', 'Booking Status') ?></small>
                                            <span
                                                class="font-weight-bold <?= $statusClass ?> d-flex align-items-center">
                                                <span class="<?= $statusClass ?> mr-1">&#9679;</span>
                                                <?= str_replace('_', ' ', ucfirst(labels($order_details['status']))) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Date -->
                                <div class="col-md-4 col-12 mb-3 mb-md-0">
                                    <div
                                        class="d-flex align-items-center p-3 shadow-sm bg-white overlapping-card h-100">
                                        <div class="info-icon-box mr-3">
                                            <i class="far fa-calendar-alt text-primary"></i>
                                        </div>
                                        <div>
                                            <small
                                                class="text-muted text-uppercase font-weight-bold d-block"><?= labels('date_of_service', 'Date Of Service') ?></small>
                                            <span class="font-weight-bold text-dark">
                                                <?= !empty($order_details['date_of_service']) ? esc($order_details['date_of_service']) : labels('no_data_found', 'No data found') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment -->
                                <div class="col-md-4 col-12 mb-3 mb-md-0">
                                    <div
                                        class="d-flex align-items-center p-3 shadow-sm bg-white overlapping-card h-100">
                                        <div class="info-icon-box mr-3">
                                            <i class="far fa-credit-card text-success"></i>
                                        </div>
                                        <div>
                                            <small
                                                class="text-muted text-uppercase font-weight-bold d-block"><?= labels('payment_method', 'Payment Method') ?></small>
                                            <div class="d-flex align-items-center flex-wrap">
                                                <span class="font-weight-bold text-dark">
                                                    <?= !empty($order_details['payment_method']) ? (($order_details['payment_method'] == "cod") ? labels('pay_on_service', 'Pay On Service') : labels(strtolower($order_details['payment_method']), $order_details['payment_method'])) : labels('no_data_found', 'No data found') ?>
                                                </span>
                                                <?php $ps = $order_details['payment_status'] ?? '';
                                                if ($ps === 'success'): ?>
                                                    <span class="text-success font-weight-bold d-flex align-items-center">
                                                        <span class="text-success mx-2">&#9679;</span>
                                                        <?= labels('success', 'Success') ?>
                                                    </span>
                                                <?php elseif ($ps === 'failed'): ?>
                                                    <span class="text-danger font-weight-bold d-flex align-items-center">
                                                        <span class="text-danger mx-2">&#9679;</span>
                                                        <?= labels('failed', 'Failed') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-warning font-weight-bold d-flex align-items-center">
                                                        <span class="text-warning mx-2">&#9679;</span>
                                                        <?= labels('pending', 'Pending') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div><!-- /Booking Header card-body -->

                        <!-- Additional Charges Payment Info (inside main card) -->
                        <?php if ($order_details['total_additional_charge'] != 0 || $order_details['total_additional_charge'] != NULL): ?>
                            <div class="card-body border-bottom py-3">
                                <div class="row">
                                    <div class="col-md-4 mb-2 mb-md-0">
                                        <small
                                            class="text-muted text-uppercase font-weight-bold d-block"><?= labels('payment_method_of_additional_charge', 'Payment Method Of Additional Charges') ?></small>
                                        <span class="font-weight-bold text-dark">
                                            <?= !empty($order_details['payment_method_of_additional_charge']) ? (($order_details['payment_method_of_additional_charge'] == "cod") ? "Pay On Service" : labels(strtolower($order_details['payment_method_of_additional_charge']), $order_details['payment_method_of_additional_charge'])) : "-" ?>
                                        </span>
                                    </div>
                                    <div class="col-md-4 mb-2 mb-md-0">
                                        <small
                                            class="text-muted text-uppercase font-weight-bold d-block"><?= labels('payment_status_of_additional_charges', 'Payment Status of Additional Charges') ?></small>
                                        <?php
                                        $add_payment_status = $order_details['payment_status_of_additional_charge'] ?? '';
                                        if ($add_payment_status === "" || $add_payment_status === "0") {
                                            $status_text = labels('pending', 'Pending');
                                            $dot_color = 'text-warning';
                                        } else if ($add_payment_status === "1") {
                                            $status_text = labels('success', 'Success');
                                            $dot_color = 'text-success';
                                        } else if ($add_payment_status === "2") {
                                            $status_text = labels('failed', 'Failed');
                                            $dot_color = 'text-danger';
                                        } else {
                                            $status_text = labels('pending', 'Pending');
                                            $dot_color = 'text-warning';
                                        }
                                        ?>
                                        <span class="font-weight-bold <?= $dot_color ?> d-flex align-items-center">
                                            <span class="<?= $dot_color ?> mr-1">&#9679;</span>
                                            <?= $status_text ?>
                                        </span>
                                    </div>
                                    <div class="col-md-4">
                                        <small
                                            class="text-muted text-uppercase font-weight-bold d-block"><?= labels('total_additional_charges', 'Total Additional Charges') ?></small>
                                        <span class="font-weight-bold text-dark">
                                            <?= $currency . (!empty($order_details['total_additional_charge']) ? $order_details['total_additional_charge'] : '0') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Booked Services Cards -->
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="font-weight-bold text-dark mb-0 text-uppercase">
                                    <?= labels('booked_services', 'Booked Services') ?>
                                </h6>
                                <span class="badge badge-light border text-muted" id="service-item-count"></span>
                            </div>
                            <hr>
                            <div id="service-cards-container" class="mb-3"></div>

                            <!-- Financial Summary (overlapping card) -->
                            <div class="row">
                                <?php if (!empty($order_details['remarks'])): ?>
                                    <div class="col-lg-6 mb-3 mb-lg-0">
                                        <div class="shadow-sm border bg-white overlapping-card h-100">
                                            <div class="p-3 border-bottom d-flex align-items-center">
                                                <span class="material-symbols-outlined mr-2 text-primary">sticky_note_2</span>
                                                <h6 class="mb-0 font-weight-bold text-dark"><?= labels('instructions', 'Instructions') ?></h6>
                                            </div>
                                            <div class="p-3">
                                                <p class="mb-0 text-dark"><?= esc($order_details['remarks']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="col-lg-6 ml-auto">
                                    <div class="shadow-sm border bg-white overlapping-card">
                                        <ul class="financial-list mb-0 list-unstyled p-0">
                                            <li><span><?= labels('total', 'Total') ?></span><span
                                                    class="font-weight-bold"><?= $currency . ($order_subtotal ?? 0) ?></span>
                                            </li>
                                            <hr>
                                            <?php
                                            if ($order_details['visiting_charges'] != "0") { ?>
                                                <li><span><?= labels('service_charge', "Service Charge") ?></span><span
                                                        class="font-weight-bold"><?= !empty($order_details['visiting_charges']) ? $currency . $order_details['visiting_charges'] : 0 ?></span>
                                                </li>
                                                <hr>
                                            <?php }
                                            ?>
                                            
                                            
                                            <?php
                                            if (!empty($order_details['additional_charges'])) {
                                                foreach (($order_details['additional_charges']) as $key => $charge) {
                                                    ?>
                                                    <li>
                                                        <span><?= !empty($charge['name']) ? $charge['name'] : 'N/A' ?></span>
                                                        <span class="font-weight-bold">
                                                            <?= !empty($charge['charge']) ? $currency . $charge['charge'] : $currency . '0' ?>
                                                        </span>
                                                    </li>
                                                    <hr>
                                                    
                                                    <?php
                                                }
                                            } ?>
                                            <li>
                                                <span>
                                                    <?= labels('tax_amount', "Tax Amount") ?>
                                                    <?php if (!empty($order_tax_type)): ?>
                                                        (<?= ucfirst(labels(esc($order_tax_type))) ?>)
                                                    <?php endif; ?>
                                                </span>
                                                <span
                                                    class="font-weight-bold"><?= $currency . ($order_tax_amount ?? 0) ?></span>
                                            </li>
                                            <hr>
                                            <li><span><?= labels('promo_code', "Promo Code") ?></span><span
                                                    class="font-weight-bold"><?= !empty($order_details['promo_discount']) ? $order_details['promo_discount'] : labels('no_data_found', 'No data found') ?></span>
                                            </li>
                                            <li class="total-row">
                                                <span><?= labels('payable_total', "Payable Total") ?></span><span><?= $currency . ($order_final_total ?? 0) ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /Booked Services card-body -->
                    </div><!-- /Main Booking Card -->

                    <!-- Multi-day scheduling if present -->
                    <?php if (!empty($sub_order)): ?>
                        <div class="card shadow-sm border-0 order-detail-card mb-4">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i
                                        class="fas fa-calendar-week text-info mr-2"></i><?= labels('order_scheduled_for_the_multiple_days', "Order scheduled for the multiple days") ?>
                                    </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-4 col-md-3"><label class="bold"><?= labels('date', "Date") ?>:</label>
                                    </div>
                                    <div class="col-8 col-md-9"><label class="bold">
                                            <?= !empty($sub_order[0]['date_of_service']) ? $sub_order[0]['date_of_service'] : labels('no_data_found', 'No data found') ?>
                                        </label></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 col-md-3"><label
                                            class="bold"><?= labels('start_time', "Start Time") ?>:</label></div>
                                    <div class="col-8 col-md-9"><label class="bold">
                                            <?= !empty($sub_order[0]['starting_time']) ? $sub_order[0]['starting_time'] : labels('no_data_found', 'No data found') ?>
                                        </label></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 col-md-3"><label
                                            class="bold"><?= labels('end_time', "End Time") ?>:</label></div>
                                    <div class="col-8 col-md-9"><label class="bold">
                                            <?= !empty($sub_order[0]['ending_time']) ? $sub_order[0]['ending_time'] : labels('no_data_found', 'No data found') ?>
                                        </label></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 col-md-3"><label
                                            class="bold"><?= labels('duration', "Duration") ?>:</label></div>
                                    <div class="col-8 col-md-9"><label class="bold">
                                            <?= !empty($sub_order[0]['duration']) ? $sub_order[0]['duration'] : labels('no_data_found', 'No data found') ?>
                                        </label></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div><!-- /LEFT COLUMN -->

                <!-- ──────── RIGHT COLUMN (4/12) ──────── -->
                <div class="col-lg-4 col-12">

                    <!-- Action Center Card -->
                    <div class="card border-0 shadow-sm order-detail-card mb-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i
                                    class="fas fa-cogs text-primary mr-2"></i><?= labels('action_center', "Action Center") ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="myForm" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="cancel_reason_id" id="cancel_reason_id" value="">
                                <input type="hidden" name="additional_info" id="cancel_additional_info_input" value="">

                                <?php
                                $_os = $order_details['status'] ?? 'awaiting';
                                $_isStore = ($order_details['address_id'] == '0');
                                $_steps = $_isStore
                                    ? ['awaiting', 'confirmed', 'started', 'booking_ended', 'completed']
                                    : ['awaiting', 'confirmed', 'on_the_way', 'arrived', 'started', 'booking_ended', 'completed'];
                                $_stepLabels = [
                                    'awaiting' => labels('awaiting', 'Awaiting'),
                                    'confirmed' => labels('confirmed', 'Confirmed'),
                                    'on_the_way' => labels('on_the_way', 'On The Way'),
                                    'arrived' => labels('arrived', 'Arrived'),
                                    'started' => labels('started', 'Started'),
                                    'booking_ended' => labels('booking_ended', 'Booking Ended'),
                                    'completed' => labels('completed', 'Completed'),
                                ];
                                $_stepIcons = [
                                    'awaiting' => 'schedule',
                                    'confirmed' => 'check_circle',
                                    'on_the_way' => 'directions_car',
                                    'arrived' => 'location_on',
                                    'started' => 'construction',
                                    'booking_ended' => 'handshake',
                                    'completed' => 'task_alt',
                                ];
                                // Block status change unless payment is successful (COD is exempt)
                                $_isPaymentPending = ($order_details['payment_status'] ?? '') !== 'success' && ($order_details['payment_method'] ?? '') !== 'cod';
                                // Mirrors validate_order_status() 'completed' guard in function_helper.php
                                $_isAdditionalChargePaymentPending = (empty($order_details['payment_method_of_additional_charge']) || $order_details['payment_method_of_additional_charge'] !== 'cod')
                                    && !empty($order_details['total_additional_charge']) && $order_details['total_additional_charge'] != 0
                                    && in_array($order_details['payment_status_of_additional_charge'] ?? '', ['', '0'], true);
                                $_stepperPos = ($_os === 'rescheduled') ? 'confirmed' : $_os;
                                $_curIdx = array_search($_stepperPos, $_steps);
                                $_isCancelled = ($_os === 'cancelled');
                                $_isRescheduled = ($_os === 'rescheduled');
                                $_transitions = [
                                    'awaiting' => ['confirmed', 'rescheduled', 'cancelled'],
                                    'confirmed' => $_isStore ? ['started', 'rescheduled', 'cancelled'] : ['on_the_way', 'rescheduled', 'cancelled'],
                                    'rescheduled' => $_isStore ? ['started', 'cancelled'] : ['on_the_way', 'cancelled'],
                                    'on_the_way' => ['arrived'],
                                    'arrived' => ['started'],
                                    'started' => ['booking_ended', 'completed'],
                                    'booking_ended' => ['completed'],
                                ];
                                $_nextStatuses = $_transitions[$_os] ?? [];
                                $_btnClasses = [
                                    'confirmed' => 'btn-success',
                                    'cancelled' => 'btn-danger',
                                    'rescheduled' => 'btn-info',
                                    'on_the_way' => 'btn-info',
                                    'arrived' => 'btn-info',
                                    'started' => 'btn-warning',
                                    'booking_ended' => 'btn-primary',
                                    'completed' => 'btn-success',
                                ];
                                $_btnLabels = [
                                    'confirmed' => labels('confirm', 'Confirm'),
                                    'cancelled' => labels('cancel', 'Cancel'),
                                    'rescheduled' => labels('reschedule', 'Reschedule'),
                                    'on_the_way' => labels('on_the_way', 'On The Way'),
                                    'arrived' => labels('arrived', 'Arrived'),
                                    'started' => labels('start', 'Start'),
                                    'booking_ended' => labels('end_booking', 'End Booking'),
                                    'completed' => labels('complete_booking', 'Complete Booking'),
                                ];
                                ?>
                                <script>window.ADDITIONAL_CHARGE_PAYMENT_PENDING = <?= $_isAdditionalChargePaymentPending ? 'true' : 'false' ?>;</script>

                                <!-- Hidden select — JS in partner_events.js reads #status value -->
                                <select name="status" id="status" class="update_order_status" style="display:none;">
                                    <?php if (isset($order_details['status']) && !empty($order_details['status']) && $order_details['status'] == "awaiting") { ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="awaiting" selected>
                                            <?= labels('awaiting', 'Awaiting') ?>
                                        </option>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="confirmed">
                                            <?= labels('confirmed', 'Confirmed') ?>
                                        </option>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="rescheduled">
                                            <?= labels('rescheduled', 'Rescheduled') ?>
                                        </option>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="cancelled">
                                            <?= labels('cancelled', 'Cancelled') ?>
                                        </option>
                                    <?php } ?>
                                    <?php if (isset($order_details['status']) && !empty($order_details['status']) && $order_details['status'] == "confirmed") { ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="confirmed" selected>
                                            <?= labels('confirmed', 'Confirmed') ?>
                                        </option>
                                        <?php if ($_isStore): ?>
                                            <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                                data-order_id="<?= $order_details["id"] ?>" value="started">
                                                <?= labels('started', 'Started') ?>
                                            </option>
                                        <?php else: ?>
                                            <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                                data-order_id="<?= $order_details["id"] ?>" value="on_the_way">
                                                <?= labels('on_the_way', 'On The Way') ?>
                                            </option>
                                        <?php endif; ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="rescheduled">
                                            <?= labels('rescheduled', 'Rescheduled') ?>
                                        </option>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="cancelled">
                                            <?= labels('cancelled', 'Cancelled') ?>
                                        </option>
                                    <?php } ?>
                                    <?php if (isset($order_details['status']) && !empty($order_details['status']) && $order_details['status'] == "on_the_way") { ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="on_the_way" selected>
                                            <?= labels('on_the_way', 'On The Way') ?>
                                        </option>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="arrived">
                                            <?= labels('arrived', 'Arrived') ?>
                                        </option>
                                    <?php } ?>
                                    <?php if (isset($order_details['status']) && !empty($order_details['status']) && $order_details['status'] == "arrived") { ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="arrived" selected>
                                            <?= labels('arrived', 'Arrived') ?>
                                        </option>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="started">
                                            <?= labels('started', 'Started') ?>
                                        </option>
                                    <?php } ?>
                                    <?php if (isset($order_details['status']) && !empty($order_details['status']) && $order_details['status'] == "rescheduled") { ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="rescheduled" selected>
                                            <?= labels('rescheduled', 'Rescheduled') ?>
                                        </option>
                                        <?php if ($_isStore): ?>
                                            <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                                data-order_id="<?= $order_details["id"] ?>" value="started">
                                                <?= labels('started', 'Started') ?>
                                            </option>
                                        <?php else: ?>
                                            <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                                data-order_id="<?= $order_details["id"] ?>" value="on_the_way">
                                                <?= labels('on_the_way', 'On The Way') ?>
                                            </option>
                                        <?php endif; ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="cancelled">
                                            <?= labels('cancelled', 'Cancelled') ?>
                                        </option>
                                    <?php } ?>
                                    <?php if (isset($order_details['status']) && !empty($order_details['status']) && $order_details['status'] == "started") { ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="started" selected>
                                            <?= labels('started', 'Started') ?>
                                        </option>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="booking_ended">
                                            <?= labels('booking_ended', 'Booking ended') ?>
                                        </option>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="completed">
                                            <?= labels('completed', 'Completed') ?>
                                        </option>
                                    <?php } ?>
                                    <?php if (isset($order_details['status']) && !empty($order_details['status']) && $order_details['status'] == "cancelled") { ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="cancelled" selected>
                                            <?= labels('cancelled', 'Cancelled') ?>
                                        </option>
                                    <?php } ?>
                                    <?php if (isset($order_details['status']) && !empty($order_details['status']) && $order_details['status'] == "booking_ended") { ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="booking_ended" selected>
                                            <?= labels('booking_ended', 'Booking ended') ?>
                                        </option>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="completed">
                                            <?= labels('completed', 'Completed') ?>
                                        </option>
                                    <?php } ?>
                                    <?php if (isset($order_details['status']) && !empty($order_details['status']) && $order_details['status'] == "completed") { ?>
                                        <option data-customer_id="<?= $order_details["customer_id"] ?>"
                                            data-order_id="<?= $order_details["id"] ?>" value="completed" selected>
                                            <?= labels('completed', 'Completed') ?>
                                        </option>
                                    <?php } ?>
                                </select>

                                <!-- Booking Status Stepper -->
                                <div class="mb-3">
                                    <label class="font-weight-bold text-uppercase small text-muted mb-2 d-block">
                                        <?= labels('booking_status', 'Booking Status') ?>
                                    </label>
                                    <?php if ($_isCancelled): ?>
                                        <div class="d-flex align-items-center p-3 rounded"
                                            style="background:#fff1f0;border:1px solid #ffccc7;">
                                            <span class="material-symbols-outlined text-danger mr-2"
                                                style="font-size:24px;">cancel</span>
                                            <div>
                                                <div class="font-weight-bold text-danger">
                                                    <?= labels('cancelled', 'Cancelled') ?>
                                                </div>
                                                <?php if (!empty($cancel_info['reason'])): ?>
                                                    <div class="text-muted small mt-1"><?= esc($cancel_info['reason']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($_isRescheduled): ?>
                                            <div class="d-flex align-items-center p-2 rounded mb-2"
                                                style="background:#e8f4fd;border:1px solid #bee5eb;">
                                                <span class="material-symbols-outlined text-info mr-2"
                                                    style="font-size:18px;">event_repeat</span>
                                                <span
                                                    class="font-weight-bold text-info small"><?= labels('rescheduled', 'Rescheduled') ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="bh-hstepper">
                                            <?php foreach ($_steps as $_si => $_step):
                                                $_isDone = $_curIdx !== false && $_si < $_curIdx;
                                                $_isCurr = $_curIdx !== false && $_si === $_curIdx;
                                                $_state = $_isDone ? 'done' : ($_isCurr ? 'current' : 'pending');
                                                ?>
                                                <div class="bh-hs-step <?= $_state ?>">
                                                    <div class="bh-hs-dot">
                                                        <span class="material-symbols-outlined">
                                                            <?= $_isDone ? 'check' : ($_stepIcons[$_step] ?? 'circle') ?>
                                                        </span>
                                                    </div>
                                                    <div class="bh-hs-label <?= $_state ?>"><?= $_stepLabels[$_step] ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($order_details['status']) && $order_details['status'] == 'cancelled' && !empty($cancel_info) && (!empty($cancel_info['reason']) || !empty($cancel_info['additional_info']))): ?>
                                    <div class="mb-3" id="cancellationAccordion">
                                        <div class="mb-2">
                                            <div class="cancel-accordion-header border rounded d-flex justify-content-between align-items-center px-3 collapsed"
                                                data-toggle="collapse" data-target="#collapseReason" aria-expanded="false"
                                                aria-controls="collapseReason">
                                                <strong><?= labels('cancellation_reason', 'Cancellation Reason') ?></strong>
                                                <i class="fa fa-chevron-down caret-icon"></i>
                                            </div>
                                            <div id="collapseReason" class="collapse" data-parent="#cancellationAccordion">
                                                <div class="p-3 border border-top-0 rounded-bottom bg-white">
                                                    <?= !empty($cancel_info['reason']) ? esc($cancel_info['reason']) : labels('no_data_found', 'No data found') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($cancel_info['additional_info'])): ?>
                                            <div class="mb-0">
                                                <div class="cancel-accordion-header border rounded d-flex justify-content-between align-items-center px-3 collapsed"
                                                    data-toggle="collapse" data-target="#collapseAdditional"
                                                    aria-expanded="false" aria-controls="collapseAdditional">
                                                    <strong><?= labels('additional_information', 'Additional Information') ?></strong>
                                                    <i class="fa fa-chevron-down caret-icon"></i>
                                                </div>
                                                <div id="collapseAdditional" class="collapse"
                                                    data-parent="#cancellationAccordion">
                                                    <div class="p-3 border border-top-0 rounded-bottom bg-white text-break">
                                                        <?= esc($cancel_info['additional_info']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($order_details['status'] == "completed"): ?>
                                    <div class="mt-3">
                                        <div>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="order_id" value="<?= esc($order_details['id']) ?>">
                                            <button type="button" id="download_invoice_btn" class="btn btn-md btn-primary text-white btn-block">
                                                <i class="fa fa-receipt text-success mr-1"></i><?= labels('download_invoice', 'Download Invoice') ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php elseif ($order_details['status'] != "cancelled" && $_isPaymentPending): ?>
                                    <div class="mt-2 mb-2 d-flex align-items-center p-3 rounded"
                                        style="background:#fff8e1;border:1px solid #ffe082;">
                                        <span class="material-symbols-outlined text-warning mr-2"
                                            style="font-size:20px;">warning</span>
                                        <span
                                            class="text-muted small"><?= payment_block_message($order_details['payment_status'] ?? null) ?></span>
                                    </div>
                                <?php elseif ($order_details['status'] != "cancelled"): ?>
                                    <input type="hidden" name="order_id" id="order_id" value="<?= $order_details['id'] ?>">
                                    <input type="hidden" name="is_otp_enable" id="is_otp_enable"
                                        value="<?= $order_details['is_otp_enalble'] ?>">
                                    <?php if (!empty($_nextStatuses)): ?>
                                        <div class="mt-2 mb-2" id="order_action_btns">
                                            <?php foreach ($_nextStatuses as $_ns):
                                                $_bc = $_btnClasses[$_ns] ?? 'btn-secondary';
                                                $_bl = $_btnLabels[$_ns] ?? ucfirst(str_replace('_', ' ', $_ns));
                                                ?>
                                                <button type="button"
                                                    class="btn btn-sm <?= $_bc ?> btn-block mb-2 btn-set-order-status"
                                                    data-status="<?= $_ns ?>">
                                                    <?= $_bl ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <button class="btn btn-md btn-primary text-white btn-block d-none" id="change_status"
                                        type="button">
                                        <?= labels('update', "Update") ?>     <?= labels('status', "Status") ?>
                                    </button>
                                <?php endif; ?>
                            </form>

                            <?php $allowHandymanEdit = !$_isPaymentPending && in_array($order_details['status'] ?? '', ['confirmed', 'rescheduled']); ?>
                            <?php $allowLeadChange = !$_isPaymentPending && !in_array($order_details['status'] ?? '', ['on_the_way', 'arrived', 'started', 'booking_ended', 'completed', 'cancelled']); ?>

                            <?php if (in_array($order_details['status'] ?? '', ['confirmed', 'started', 'rescheduled']) && !$_isPaymentPending): ?>
                                <?php $hasAvailableHandymen = !empty($handymen) && count(array_filter($handymen, fn($h) => empty($h['conflicting_order_id']))) > 0; ?>
                                <div id="inline_handyman_section" class="border-top pt-3 mt-3">
                                    <label
                                        class="font-weight-bold text-uppercase small text-muted mb-2 d-block"><?= labels('assign_handyman', 'Assign Handyman') ?></label>
                                    <?php if (!empty($handymen)): ?>
                                        <div id="handyman_select_wrapper" <?= $hasAvailableHandymen ? '' : ' style="display:none"' ?>>
                                            <select id="inline_handyman_select" class="form-control select2" multiple="multiple"
                                                data-placeholder="<?= labels('select_handyman', 'Select Handyman') ?>">
                                                <?php foreach ($handymen as $h): ?>
                                                    <?php $isBusy = !empty($h['conflicting_order_id']); ?>
                                                    <option value="<?= (int) $h['id'] ?>" <?= $isBusy ? 'disabled' : '' ?>>
                                                        <?= esc($h['username']) ?>
                                                        <?= $isBusy ? ' (' . labels('busy', 'Busy') . ')' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-md btn-primary text-white btn-block mt-2"
                                                id="assign_handyman_btn">
                                                <i
                                                    class="fas fa-user-plus mr-1"></i><?= labels('assign_handyman', 'Assign Handyman') ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    <div
                                        class="p-3 bg-light overlapping-card no-handyman-msg <?= $hasAvailableHandymen ? 'd-none' : 'd-flex align-items-center' ?>">
                                        <i class="fas fa-user-circle text-secondary mr-3 fa-lg"></i>
                                        <span
                                            class="text-muted"><?= labels('no_handyman_available', 'No handyman available.') ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Assigned Handymen Section -->
                            <?php 
                            if (!empty($assigned_handymen)) {
                                usort($assigned_handymen, function($a, $b) {
                                    return ($b['is_lead'] ?? 0) <=> ($a['is_lead'] ?? 0);
                                });
                            }
                            $totalAssigned = count($assigned_handymen ?? []); 
                            ?>
                            <div class="border-top pt-3 mt-3" id="assigned_handymen_card" <?= (empty($assigned_handymen) || $_isPaymentPending) ? 'style="display:none"' : '' ?>>
                                <?php
                                $leadStat = $order_details['lead_handyman_status'] ?? '';
                                if (empty($leadStat) && !empty($assigned_handymen)) {
                                    foreach ($assigned_handymen as $ah) {
                                        if (!empty($ah['is_lead'])) {
                                            $leadStat = $ah['handyman_status'] ?? '';
                                            break;
                                        }
                                    }
                                    if (empty($leadStat)) {
                                        $leadStat = $assigned_handymen[0]['handyman_status'] ?? '';
                                    }
                                }
                                $leadStatusClass = 'text-secondary';
                                if ($leadStat === 'accepted') {
                                    $leadStatusClass = 'text-primary';
                                } elseif (in_array($leadStat, ['on_the_way', 'arrived', 'started'])) {
                                    $leadStatusClass = 'text-info';
                                } elseif (in_array($leadStat, ['completed', 'booking_ended'])) {
                                    $leadStatusClass = 'text-success';
                                } elseif ($leadStat === 'rejected') {
                                    $leadStatusClass = 'text-danger';
                                }
                                ?>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label
                                        class="font-weight-bold text-uppercase small text-muted mb-0"><?= labels('assigned_handymen', 'Assigned Handymen') ?></label>
                                    <div class="d-flex align-items-center">
                                        <span class="badge badge-light border text-muted"
                                            id="assigned_handymen_count"><?= $totalAssigned ?></span>
                                        <span id="lead_handyman_status_badge"
                                            class="badge badge-pill badge-light <?= $leadStatusClass ?> d-inline-flex align-items-center ml-2"
                                            <?= empty($leadStat) ? 'style="display:none;"' : '' ?>>
                                            <span class="mr-1">&#9679;</span>
                                            <span
                                                id="lead_handyman_status_text"><?= empty($leadStat) ? '' : labels($leadStat, ucfirst(str_replace('_', ' ', $leadStat))) ?></span>
                                        </span>
                                    </div>
                                </div>
                                <div id="assigned_handymen_list" data-multiple="<?= $totalAssigned > 1 ? '1' : '0' ?>">
                                    <?php foreach ($assigned_handymen ?? [] as $ah): ?>
                                        <div class="card border-0 shadow-sm mb-3 assigned-handyman-item"
                                            data-id="<?= (int) $ah['id'] ?>" data-name="<?= esc($ah['username']) ?>"
                                            data-is-lead="<?= (int) ($ah['is_lead'] ?? 0) ?>"
                                            data-status="<?= esc($ah['handyman_status'] ?? '') ?>">
                                            <div class="card-body d-flex align-items-center p-3">
                                                <?php
                                                $fileService = $fileService ?? service('fileService');
                                                $ahImageUrl = '';
                                                $ahHasImage = false;
                                                if (!empty($ah['image'])) {
                                                    $ahFilename = basename($ah['image']);
                                                    if ($ahFilename !== 'default.png' && $fileService->exists('profile', $ahFilename)) {
                                                        $ahImageUrl = $fileService->url($ahFilename, 'profile');
                                                        $ahHasImage = true;
                                                    }
                                                }
                                                ?>
                                                <?php if ($ahHasImage): ?>
                                                    <img src="<?= $ahImageUrl ?>" class="mr-3"
                                                        style="width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0;"
                                                        alt="<?= esc($ah['username']) ?>">
                                                <?php else: ?>
                                                    <div class="mr-3"
                                                        style="width:42px;height:42px;border-radius:50%;background:<?= '#' . substr(md5($ah['id']), 0, 6) ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                        <span class="text-white font-weight-bold"
                                                            style="font-size:16px;"><?= strtoupper(substr($ah['username'], 0, 1)) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-fill">
                                                    <h6 class="font-weight-bold text-dark mb-0">
                                                        <?= esc($ah['username']) ?>
                                                        <?php if (!empty($ah['is_lead']) && $totalAssigned > 1): ?>
                                                            <span
                                                                class="badge badge-warning ml-1 lead-badge"><?= labels('lead', 'Lead') ?></span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning ml-1 lead-badge"
                                                                style="display:none;"><?= labels('lead', 'Lead') ?></span>
                                                        <?php endif; ?>
                                                    </h6>
                                                </div>
                                                <?php
                                                $showLeadBtn = $allowLeadChange && $totalAssigned > 1 && ($ah['handyman_status'] ?? '') === 'accepted';
                                                $showUnassignBtn = $allowHandymanEdit && in_array($ah['handyman_status'] ?? '', ['assigned', 'accepted']);
                                                ?>
                                                <?php if ($showLeadBtn || $showUnassignBtn): ?>
                                                    <div class="ml-3 d-flex">
                                                        <?php if ($showLeadBtn): ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-warning mark-lead-btn mr-2"
                                                                <?= empty($ah['is_lead']) ? '' : 'style="display:none;"' ?>
                                                                data-id="<?= (int) $ah['id'] ?>">
                                                                <?= labels('mark_as_lead', 'Mark as Lead') ?>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($showUnassignBtn): ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-danger unassign-handyman-btn"
                                                                data-id="<?= (int) $ah['id'] ?>">
                                                                <?= labels('unassign', 'Unassign') ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div><!-- /Action Center Card -->

                    <!-- Details Tabs Card (Customer / Service / Provider) -->
                    <div class="card border-0 shadow-sm order-detail-card mb-4">
                        <div class="d-flex bg-white border-bottom"
                            style="border-radius: 0.75rem 0.75rem 0 0; overflow: hidden;" id="infoTab" role="tablist">
                            <a class="nav-link active text-primary font-weight-bold py-3 flex-fill text-center"
                                id="customer-tab" data-toggle="pill" href="#customerPane" role="tab"
                                aria-controls="customerPane" aria-selected="true"
                                style="border-bottom: 2px solid color-mix(in srgb, var(--primary-color) 50%, transparent) !important; background-color: color-mix(in srgb, var(--primary-color) 8%, transparent) !important; border-radius: 0; font-size: 14px; color: var(--primary-color) !important;"><i
                                    class="fas fa-user mr-2"></i><?= labels('customer', 'Customer') ?></a>
                            <a class="nav-link text-muted py-3 flex-fill text-center" id="service-tab"
                                data-toggle="pill" href="#servicePane" role="tab" aria-controls="servicePane"
                                aria-selected="false"
                                style="border-bottom: none !important; background-color: transparent !important; border-radius: 0; font-size: 14px;"><i
                                    class="fas fa-tools mr-2"></i><?= labels('service', 'Service') ?></a>
                            <a class="nav-link text-muted py-3 flex-fill text-center" id="provider-tab"
                                data-toggle="pill" href="#providerPane" role="tab" aria-controls="providerPane"
                                aria-selected="false"
                                style="border-bottom: none !important; background-color: transparent !important; border-radius: 0; font-size: 14px;"><i
                                    class="fas fa-store mr-2"></i><?= labels('provider', 'Provider') ?></a>
                        </div>
                        <div class="card-body tab-content" id="infoTabContent">
                            <!-- Customer Pane -->
                            <div class="tab-pane fade show active" id="customerPane" role="tabpanel"
                                aria-labelledby="customer-tab">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('name', 'Name') ?></label>
                                        <div class="d-flex align-items-center">
                                            <?php
                                            $fileService = service('fileService');
                                            $profile_image_url = '';
                                            $has_profile_image = false;
                                            if (!empty($order_details['profile_image'])) {
                                                $filename = basename($order_details['profile_image']);
                                                if ($filename !== 'default.png' && $fileService->exists('profile', $filename)) {
                                                    $profile_image_url = $fileService->url($filename, 'profile');
                                                    $has_profile_image = true;
                                                }
                                            }
                                            ?>
                                            <?php if ($has_profile_image): ?>
                                                <img src="<?= $profile_image_url ?>"
                                                    alt="<?= !empty($order_details['customer']) ? esc($order_details['customer']) : 'Customer' ?>"
                                                    class="rounded mr-2"
                                                    style="width: 30px; height: 30px; object-fit: cover; border: 1px solid #e9ecef;">
                                            <?php else: ?>
                                                <?php $first_letter = !empty($order_details['customer']) ? strtoupper(substr(trim($order_details['customer']), 0, 1)) : 'N'; ?>
                                                <span
                                                    class="d-inline-flex align-items-center justify-content-center bg-light text-dark font-weight-bold rounded mr-2"
                                                    style="width: 28px; height: 28px; font-size: 13px; border: 1px solid #e9ecef;"><?= $first_letter ?></span>
                                            <?php endif; ?>
                                            <span
                                                class="text-dark font-weight-bold"><?= !empty($order_details['customer']) ? esc($order_details['customer']) : labels('no_data_found', 'No data found') ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('contact_no', 'Contact No.') ?></label>
                                        <div class="d-flex align-items-center text-dark font-weight-bold">
                                            <i class="fas fa-phone-alt text-muted mr-2" style="font-size: 14px;"></i>
                                            <span><?= !empty($order_details['customer_no']) ? esc((!empty($order_details['customer_country_code']) ? $order_details['customer_country_code'] . ' ' : '') . $order_details['customer_no']) : labels('no_data_found', 'No data found') ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('email', 'Email') ?></label>
                                        <div class="d-flex align-items-center text-dark font-weight-bold">
                                            <i class="far fa-envelope text-muted mr-2" style="font-size: 14px;"></i>
                                            <span
                                                class="text-break"><?= !empty($order_details['customer_email']) ? esc($order_details['customer_email']) : labels('no_data_found', 'No data found') ?></span>
                                        </div>
                                    </div>
                                    <?php if ($order_details['address_id'] != "0") { ?>
                                        <div class="col-md-6 mb-0">
                                            <label class="text-uppercase text-muted font-weight-bold mb-1"
                                                style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('address', 'Address') ?></label>
                                            <div class="text-dark font-weight-bold">
                                                <?= !empty($order_details['address']) ? esc($order_details['address']) : labels('no_data_found', 'No data found') ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>

                            <!-- Service Pane -->
                            <div class="tab-pane fade" id="servicePane" role="tabpanel" aria-labelledby="service-tab">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('date', 'Date') ?></label>
                                        <div class="text-dark font-weight-bold">
                                            <?= !empty($order_details['date_of_service']) ? esc($order_details['date_of_service']) : labels('no_data_found', 'No data found') ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('duration', 'Duration') ?></label>
                                        <div class="text-dark font-weight-bold">
                                            <?= !empty($order_details['duration']) ? esc($order_details['duration']) . " " . labels('minutes', 'Minutes') : labels('no_data_found', 'No data found') ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('time_window', 'Time Window') ?></label>
                                        <div class="text-dark font-weight-bold">
                                            <?= (!empty($order_details['starting_time']) ? esc($order_details['starting_time']) : '...') . " - " . (!empty($order_details['ending_time']) ? esc($order_details['ending_time']) : '...') ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('type', 'Type') ?></label>
                                        <div>
                                            <?php if ($order_details['address_id'] == "0") { ?>
                                                <span class="badge"
                                                    style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; font-weight: 600; padding: 5px 10px; border-radius: 4px;"><?= labels('at_store', 'At Store') ?></span>
                                            <?php } else { ?>
                                                <span class="badge"
                                                    style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; font-weight: 600; padding: 5px 10px; border-radius: 4px;"><?= labels('at_doorstep', 'At Doorstep') ?></span>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <?php if ($order_details['visiting_charges'] != "0") { ?>
                                        <div class="col-md-6 mt-2">
                                            <label class="text-uppercase text-muted font-weight-bold mb-1"
                                                style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('visiting_charge', 'Visiting Charge') ?></label>
                                            <div class="text-dark font-weight-bold">
                                                <?= !empty($order_details['visiting_charges']) ? $currency . $order_details['visiting_charges'] : labels('no_data_found', 'No data found') ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>

                            <!-- Provider Pane -->
                            <div class="tab-pane fade" id="providerPane" role="tabpanel" aria-labelledby="provider-tab">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('provider', 'Provider') ?></label>
                                        <div class="text-dark font-weight-bold">
                                            <?php
                                            $company_display = !empty($order_details['translated_company_name']) ? $order_details['translated_company_name'] : (!empty($order_details['company_name']) ? $order_details['company_name'] : (!empty($order_details['translated_username']) ? $order_details['translated_username'] : (!empty($order_details['partner']) ? $order_details['partner'] : labels('no_data_found', 'No data found'))));
                                            ?>
                                            <?= esc($company_display) ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('address', 'Address') ?></label>
                                        <div class="text-dark font-weight-bold">
                                            <?= !empty($order_details['partner_address']) ? esc($order_details['partner_address']) : labels('no_data_found', 'No data found') ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <label class="text-uppercase text-muted font-weight-bold mb-1"
                                            style="font-size: 11px; letter-spacing: 0.5px;"><?= labels('contact_no', 'Contact No.') ?></label>
                                        <div class="text-dark font-weight-bold">
                                            <div>
                                                <?= !empty($order_details['partner_no']) ? esc((!empty($order_details['partner_country_code']) ? $order_details['partner_country_code'] . ' ' : '') . $order_details['partner_no']) : labels('no_data_found', 'No data found') ?>
                                            </div>
                                            <div class="text-muted font-weight-normal">
                                                <?= !empty($personal_data['email']) ? esc($personal_data['email']) : labels('no_data_found', 'No data found') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /Details Tabs Card -->

                </div><!-- /RIGHT COLUMN -->
            </div><!-- /ROW 1 -->

            <!-- ══════════ ROW 2: Work Proofs ══════════ -->
            <div class="row mb-4">
                <?php
                $has_work_started_proof = !empty($order_details['work_started_proof']);
                $has_work_completed_proof = !empty($order_details['work_completed_proof']);
                $col_class = ($has_work_started_proof && $has_work_completed_proof) ? 'col-md-6' : 'col-md-12';
                ?>
                <?php if (!empty($order_details['work_started_proof'])): ?>
                    <div class="<?= $col_class ?>">
                        <div class="card border-0 shadow-sm order-detail-card h-100">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i
                                        class="fas fa-play-circle text-info mr-2"></i><?= labels('work_started_proof', "Work Started Proof") ?>
                                    </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-center m-3 row ">
                                    <?php
                                    if (empty($order_details['work_started_proof'])) { ?>
                                        <h5><?= labels('no_data_found', "No data found") ?></h5>
                                    <?php } else
                                        $video_exytension = ['mov', 'mp4', 'm3u8', 'ts', '3gp', 'mov', 'avi', 'wmv'];
                                    foreach ($order_details['work_started_proof'] as $row) {
                                        $fileNameParts = explode('.', $row);
                                        $ext = end($fileNameParts);
                                        if ((in_array($ext, $video_exytension))) { ?>
                                            <div class="col-md-3 image_preview mr-4">
                                                <button type="button" class="btn btn-primary  myBtn" id="myBtn"
                                                    data-analystId=<?= $row ?>><?= labels('open_video', 'Open Video') ?></button>
                                            </div>
                                        <?php } else { ?>
                                            <div class="col-lg-3 col-md-3 col-sm-5 col-xxs-12 ">
                                                <a href="<?php echo $row ?>" data-lightbox="image-1" class="image_preview h-100">
                                                    <img height="150px" width="120px" src="<?php echo $row ?>" alt=""
                                                        class="img-fluid">
                                                </a>
                                            </div>
                                        <?php } ?>
                                        <?php
                                    }
                                    ?>
                                </div>
                                <div class="modal fade" id="view-video" role="dialog" aria-labelledby="view-video"
                                    aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLongTitle">
                                                    <?= labels('watch_video', 'Watch Video') ?>
                                                </h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row ">
                                                    <div class="col-md ">
                                                        <video id="video-10" height="300px" class="w-100" controls>
                                                            <source src="movie.mp4" type="video/mp4">
                                                        </video>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($order_details['work_completed_proof'])): ?>
                    <div class="<?= $col_class ?>">
                        <div class="card border-0 shadow-sm order-detail-card h-100">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i
                                        class="fas fa-check-circle text-success mr-2"></i><?= labels('work_completed_proof', "Work Completed Proof") ?>
                                    </h5>
                            </div>
                            <div class="card-body">
                                <div class="row d-flex justify-content-center m-3 ">
                                    <?php
                                    if (empty($order_details['work_completed_proof'])) { ?>
                                        <h5><?= labels('no_data_found', "No data found") ?></h5>
                                    <?php } else
                                        $video_exytension = ['mov', 'mp4', 'm3u8', 'ts', '3gp', 'mov', 'avi', 'wmv'];
                                    foreach ($order_details['work_completed_proof'] as $row) {
                                        $fileNameParts = explode('.', $row);
                                        $ext = end($fileNameParts);
                                        if ((in_array($ext, $video_exytension))) { ?>
                                            <div class=" col-md-3 image_preview ">
                                                <button type="button" class="btn btn-primary  myBtn_completed" id="myBtn_completed"
                                                    data-analystId=<?= $row ?>><?= labels('open_video', 'Open Video') ?></button>
                                            </div>
                                        <?php } else { ?>
                                            <div class="col-lg-3 col-md-3 col-sm-5 col-xxs-12">
                                                <a href="<?php echo $row ?>" data-lightbox="image-1" class="image_preview h-100">
                                                    <img height="150px" width="120px" src="<?php echo $row ?>" alt=""
                                                        class="img-fluid">
                                                </a>
                                            </div>
                                        <?php } ?>
                                        <?php
                                    }
                                    ?>
                                </div>
                                <div class="modal fade" id="view-video" role="dialog" aria-labelledby="view-video"
                                    aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLongTitle">
                                                    <?= labels('watch_video', 'Watch Video') ?>
                                                </h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row ">
                                                    <div class="col-md">
                                                        <video id="video-10" class="w-100" height="300px" controls>
                                                            <source src="movie.mp4" type="video/mp4">
                                                        </video>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div><!-- /ROW 2: Work Proofs -->

        </div><!-- /section-body -->
    </section>
</div>

<script>
    var HANDYMAN_STATUS_LABELS = {
        <?php
        $handymanStatusKeys = ['assigned', 'accepted', 'rejected', 'on_the_way', 'arrived', 'started', 'completed', 'booking_ended'];
        $handymanStatusPairs = [];
        foreach ($handymanStatusKeys as $hsk) {
            $handymanStatusPairs[] = "'" . $hsk . "': " . json_encode(labels($hsk, ucfirst(str_replace('_', ' ', $hsk))));
        }
        echo implode(",\n        ", $handymanStatusPairs);
        ?>
    };

    $(document).ready(function () {
        $('.overlapping-card').css('borderRadius', '0.75rem');

        $('#download_invoice_btn').on('click', function () {
            var form = $('<form>', {
                method: 'post',
                action: '<?= base_url('api/v1/invoice-download') ?>'
            }).appendTo('body');
            form.append($('<input>', {
                type: 'hidden',
                name: '<?= csrf_token() ?>',
                value: $('input[name="<?= csrf_token() ?>"]').first().val()
            }));
            form.append($('<input>', {
                type: 'hidden',
                name: 'order_id',
                value: '<?= esc($order_details['id']) ?>'
            }));
            form[0].submit();
        });

        // Handle custom styling and switching for tabs using jQuery
        $('#infoTab a').on('click', function (e) {
            e.preventDefault();

            // Switch tabs styling
            $('#infoTab a')
                .addClass('text-muted')
                .removeClass('text-primary font-weight-bold')
                .attr('style', 'border-bottom: none !important; background-color: transparent !important; border-radius: 0; font-size: 14px;')
                .attr('aria-selected', 'false')
                .removeClass('active');

            $(this)
                .addClass('text-primary font-weight-bold active')
                .removeClass('text-muted')
                .attr('style', 'border-bottom: 2px solid color-mix(in srgb, var(--primary-color) 50%, transparent) !important; background-color: color-mix(in srgb, var(--primary-color) 8%, transparent) !important; border-radius: 0; font-size: 14px; color: var(--primary-color) !important;')
                .attr('aria-selected', 'true');

            // Switch tab panes
            var target = $(this).attr('href');
            $('#infoTabContent .tab-pane')
                .removeClass('show active');
            $(target)
                .addClass('show active');
        });

        if ($('#inline_handyman_select').length) {
            $('#inline_handyman_select').select2({
                placeholder: $('#inline_handyman_select').data('placeholder'),
                allowClear: true,
                width: '100%'
            });
        }

        $('#status').on('change', function () {
            var val = $(this).val();
            var $section = $('#inline_handyman_section');
            if (!$section.length) return;
            if (val === 'confirmed' || val === 'rescheduled' || val === 'started') {
                $section.removeClass('d-none');
            } else {
                $section.addClass('d-none');
                if ($('#inline_handyman_select').length) {
                    $('#inline_handyman_select').val(null).trigger('change');
                }
            }
        });

        $(document).on('click', '.unassign-handyman-btn', function () {
            var $item = $(this).closest('.assigned-handyman-item');
            var handyman_id = $(this).data('id');
            var handyman_name = $item.data('name');
            $(this).prop('disabled', true);
            $.post('<?= base_url('partner/orders/unassign_handyman') ?>', {
                order_id: <?= (int) $order_details['id'] ?>,
                handyman_id: handyman_id,
                '<?= csrf_token() ?>': $('input[name="<?= csrf_token() ?>"]').val()
            }, function (res) {
                if (res.csrfName) $('input[name="' + res.csrfName + '"]').val(res.csrfHash);
                if (!res.error) {
                    showToastMessage(res.message, 'success');
                    var wasLead = $item.attr('data-is-lead') === '1';
                    $item.remove();
                    if (wasLead) {
                        $('#assigned_handymen_list .assigned-handyman-item').first().attr('data-is-lead', '1');
                    }
                    var count = $('#assigned_handymen_list .assigned-handyman-item').length;
                    $('#assigned_handymen_count').text(count);
                    if (!count) $('#assigned_handymen_card').hide();
                    refreshLeadButtonVisibility();
                    refreshLeadStatus();
                    var $sel = $('#inline_handyman_select');
                    if ($sel.length) {
                        var $existing = $sel.find('option[value="' + handyman_id + '"]');
                        if ($existing.length) {
                            $existing.prop('disabled', false).text(handyman_name);
                        } else {
                            $sel.append(new Option(handyman_name, handyman_id));
                        }
                        $sel.trigger('change');
                    } else {
                        var $section = $('#inline_handyman_section');
                        var $newSel = $('<select id="inline_handyman_select" name="handyman_ids[]" class="form-control select2" multiple="multiple"></select>');
                        $newSel.attr('data-placeholder', '<?= labels('select_handyman', 'Select Handyman') ?>');
                        $newSel.append(new Option(handyman_name, handyman_id));
                        $section.find('label').after($newSel);
                        $newSel.select2({ placeholder: $newSel.data('placeholder'), allowClear: true, width: '100%' });
                    }
                    var $handymanSection = $('#inline_handyman_section');
                    if ($handymanSection.length) {
                        $handymanSection.show().removeClass('d-none');
                        if (!$('#assign_handyman_btn').length) {
                            $handymanSection.append('<button type="button" class="btn btn-md btn-primary text-white btn-block mt-2" id="assign_handyman_btn"><i class="fas fa-user-plus mr-1"></i><?= labels('assign_handyman', 'Assign Handyman') ?></button>');
                        }
                        $('#assign_handyman_btn').show();
                        $('#handyman_select_wrapper').show();
                        $handymanSection.find('.no-handyman-msg').removeClass('d-flex align-items-center').addClass('d-none');
                    }
                } else {
                    showToastMessage(res.message, 'error');
                }
            }, 'json').fail(function () {
                showToastMessage('<?= labels('error_occured', 'Error Occurred') ?>', 'error');
            });
        });

        function handymanAvatarColor(id) {
            var hash = 0, s = String(id);
            for (var i = 0; i < s.length; i++) hash = s.charCodeAt(i) + ((hash << 5) - hash);
            return '#' + ('000000' + (hash & 0xFFFFFF).toString(16)).slice(-6);
        }

        function refreshLeadButtonVisibility() {
            var $list = $('#assigned_handymen_list');
            var count = $list.find('.assigned-handyman-item').length;
            $list.attr('data-multiple', count > 1 ? '1' : '0');
            var bookingStatus = '<?= esc($order_details['status'] ?? '') ?>';
            var allowLeadChange = <?= $allowLeadChange ? 'true' : 'false' ?>;
            $list.find('.assigned-handyman-item').each(function () {
                var isLead = $(this).attr('data-is-lead') === '1';
                var handymanStatus = $(this).attr('data-status') || '';
                var $leadBtn = $(this).find('.mark-lead-btn');
                var $leadBadge = $(this).find('.lead-badge');

                var allowLeadBtn = allowLeadChange && count > 1 && !isLead && handymanStatus === 'accepted';

                if (allowLeadBtn) {
                    $leadBtn.show();
                } else {
                    $leadBtn.hide();
                }

                if (count > 1 && isLead) {
                    $leadBadge.show();
                } else {
                    $leadBadge.hide();
                }
            });
        }

        function refreshLeadStatus() {
            var $leadItem = $('#assigned_handymen_list .assigned-handyman-item[data-is-lead="1"]');
            if (!$leadItem.length) {
                $leadItem = $('#assigned_handymen_list .assigned-handyman-item').first();
            }
            if ($leadItem.length) {
                var status = $leadItem.attr('data-status') || '';
                if (status) {
                    var statusText = HANDYMAN_STATUS_LABELS[status] || (status.replace(/_/g, ' ').charAt(0).toUpperCase() + status.replace(/_/g, ' ').slice(1));
                    var statusClass = 'text-secondary';
                    if (status === 'accepted') {
                        statusClass = 'text-primary';
                    } else if (['on_the_way', 'arrived', 'started'].indexOf(status) !== -1) {
                        statusClass = 'text-info';
                    } else if (['completed', 'booking_ended'].indexOf(status) !== -1) {
                        statusClass = 'text-success';
                    } else if (status === 'rejected') {
                        statusClass = 'text-danger';
                    }

                    $('#lead_handyman_status_badge')
                        .removeClass('text-secondary text-primary text-info text-success text-danger')
                        .addClass(statusClass)
                        .show();
                    $('#lead_handyman_status_text').text(statusText);
                } else {
                    $('#lead_handyman_status_badge').hide();
                }
            } else {
                $('#lead_handyman_status_badge').hide();
            }
        }

        $(document).on('click', '#assign_handyman_btn', function () {
            var $btn = $(this);
            var $sel = $('#inline_handyman_select');
            var ids = $sel.val();
            if (!ids || !ids.length) {
                showToastMessage('<?= labels('select_handyman', 'Select Handyman') ?>', 'error');
                return;
            }
            var data = new FormData();
            data.append('order_id', <?= (int) $order_details['id'] ?>);
            ids.forEach(function (id) { data.append('handyman_ids[]', id); });
            formAjaxRequest('POST', '<?= base_url('partner/orders/assign_handyman') ?>', data, null, $btn,
                function (res) {
                    $sel.val(null).trigger('change');
                    if (res.assigned && res.assigned.length) {
                        res.assigned.forEach(function (h) {
                            var color = handymanAvatarColor(h.id);
                            var initial = h.username.charAt(0).toUpperCase();
                            var isLead = h.is_lead ? 1 : 0;
                            var leadBadge = isLead ? '<span class="badge badge-warning ml-1 lead-badge"><?= labels('lead', 'Lead') ?></span>' : '<span class="badge badge-warning ml-1 lead-badge" style="display:none;"><?= labels('lead', 'Lead') ?></span>';
                            var html = '<div class="card border-0 shadow-sm mb-3 assigned-handyman-item" data-id="' + h.id + '" data-name="' + h.username + '" data-is-lead="' + isLead + '" data-status="accepted">' +
                                '<div class="card-body d-flex align-items-center p-3">' +
                                '<div class="mr-3" style="width:42px;height:42px;border-radius:50%;background:' + color + ';display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
                                '<span class="text-white font-weight-bold" style="font-size:16px;">' + initial + '</span></div>' +
                                '<div class="flex-fill"><h6 class="font-weight-bold text-dark mb-0">' + h.username + ' ' + leadBadge + '</h6></div>' +
                                '<div class="ml-3 d-flex gap-1">' +
                                '<button type="button" class="btn btn-sm btn-outline-warning mark-lead-btn mr-2" data-id="' + h.id + '" style="display:none;"><?= labels('mark_as_lead', 'Mark as Lead') ?></button>' +
                                '<button type="button" class="btn btn-sm btn-outline-danger unassign-handyman-btn" data-id="' + h.id + '"><?= labels('unassign', 'Unassign') ?></button>' +
                                '</div>' +
                                '</div></div>';
                            $('#assigned_handymen_list').append(html);
                            $sel.find('option[value="' + h.id + '"]').remove();
                        });
                        $sel.trigger('change');
                        if (!$sel.find('option').length) {
                            $('#handyman_select_wrapper').hide();
                            $('#inline_handyman_section .no-handyman-msg').removeClass('d-none').addClass('d-flex align-items-center');
                        }
                        var count = $('#assigned_handymen_list .assigned-handyman-item').length;
                        $('#assigned_handymen_count').text(count);
                        $('#assigned_handymen_card').show();
                        if (count > 1) {
                            $('#assigned_handymen_list .assigned-handyman-item').each(function () {
                                if (!$(this).find('.mark-lead-btn').length) {
                                    var hId = $(this).data('id');
                                    var $ml3 = $(this).find('.card-body .ml-3');
                                    if ($ml3.length) {
                                        $ml3.prepend('<button type="button" class="btn btn-sm btn-outline-warning mark-lead-btn mr-2" data-id="' + hId + '" style="display:none;"><?= labels('mark_as_lead', 'Mark as Lead') ?></button>');
                                    }
                                }
                            });
                        }
                        refreshLeadButtonVisibility();
                        refreshLeadStatus();
                    }
                },
                function (res) { showToastMessage(res.message, 'error'); }
            );
        });

        $(document).on('click', '.mark-lead-btn', function () {
            var $btn = $(this);
            var $item = $btn.closest('.assigned-handyman-item');
            var handyman_id = $btn.data('id');
            $btn.prop('disabled', true);
            $.post('<?= base_url('partner/orders/set_lead_handyman') ?>', {
                order_id: <?= (int) $order_details['id'] ?>,
                handyman_id: handyman_id,
                '<?= csrf_token() ?>': $('input[name="<?= csrf_token() ?>"]').val()
            }, function (res) {
                if (res.csrfName) $('input[name="' + res.csrfName + '"]').val(res.csrfHash);
                if (!res.error) {
                    showToastMessage(res.message, 'success');
                    $('#assigned_handymen_list .assigned-handyman-item').each(function () {
                        var isThis = $(this).data('id') == handyman_id;
                        $(this).attr('data-is-lead', isThis ? '1' : '0');
                        $(this).find('.lead-badge').toggle(isThis);
                    });
                    var $list = $('#assigned_handymen_list');
                    var $items = $list.children('.assigned-handyman-item').get();
                    $items.sort(function(a, b) {
                        var leadA = parseInt($(a).attr('data-is-lead')) || 0;
                        var leadB = parseInt($(b).attr('data-is-lead')) || 0;
                        return leadB - leadA;
                    });
                    $.each($items, function(idx, itm) { $list.append(itm); });
                    $('#assigned_handymen_list .mark-lead-btn').prop('disabled', false);
                    refreshLeadButtonVisibility();
                    refreshLeadStatus();
                } else {
                    showToastMessage(res.message, 'error');
                    $btn.prop('disabled', false);
                }
            }, 'json').fail(function () {
                showToastMessage('<?= labels('error_occured', 'Error Occurred') ?>', 'error');
                $btn.prop('disabled', false);
            });
        });

        $('#cancellationAccordion').on('show.bs.collapse', function (e) {
            $(e.target).prev('.cancel-accordion-header').addClass('bg-primary text-white');
        }).on('hide.bs.collapse', function (e) {
            $(e.target).prev('.cancel-accordion-header').removeClass('bg-primary text-white');
        });

        $(document).on('click', '.btn-set-order-status', function () {
            var status = $(this).data('status');

            if (status === 'rescheduled') {
                $('#partnerRescheduleModal').modal('show');
                return;
            }
            if (status === 'started') {
                $('#partnerStartedModal').modal('show');
                return;
            }
            if (status === 'booking_ended') {
                $('#partnerBookingEndedModal').modal('show');
                return;
            }
            if (status === 'cancelled') {
                $('#status').val('cancelled').trigger('change');
                if ($('#cancel_reason_select option').length > 1) {
                    $('#cancel_reason_select').val('');
                    $('#cancel_additional_info').val('').trigger('input');
                    $('#cancel_additional_info_wrapper').addClass('d-none');
                    $('#cancel_reason_error, #cancel_additional_info_error').addClass('d-none');
                    $('#cancelReasonModal').modal('show');
                } else {
                    $('#change_status').trigger('click');
                }
                return;
            }

            if (status === 'on_the_way') {
                var hasAssigned = $('#assigned_handymen_list .assigned-handyman-item').length > 0;
                if (!hasAssigned) {
                    Swal.fire({
                        title: '<?= labels('no_handyman_assigned', 'No Handyman Assigned') ?>',
                        text: '<?= labels('going_for_service_yourself_confirm', 'No handymen assigned to this booking. Are you sure you shall provide the service yourself?') ?>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!') ?>',
                        cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            $('#status').val(status).trigger('change');
                            $('#change_status').trigger('click');
                        }
                    });
                    return;
                }
            }

            $('#status').val(status).trigger('change');
            $('#change_status').trigger('click');
        });

        $(".myBtn").click(function () {
            var analystID = $(this).attr('data-analystId');
            var video = $("#video-10");
            const videoSource = $(this).attr('data-analystId')
            $('video source').attr('src', videoSource)
            $('video')[0].load()
            $("#view-video").modal({
                backdrop: false
            });
        });
        $(".myBtn_completed").click(function () {
            var analystID = $(this).attr('data-analystId');
            var video = $("#video-10");
            const videoSource = $(this).attr('data-analystId')
            $('video source').attr('src', videoSource)
            $('video')[0].load()
            $("#view-video").modal({
                backdrop: false
            });
        });
    });
</script>
<script>
    (function () {
        var SERVICE_URL = '<?= base_url('partner/orders/order_summary_table/' . $order_details['id']) ?>';
        var BG_CLASSES = ['bg-success', 'bg-warning', 'bg-danger', 'bg-info', 'bg-secondary'];

        function bgClassForText(text) {
            var hash = 0;
            for (var i = 0; i < text.length; i++) hash = text.charCodeAt(i) + ((hash << 5) - hash);
            return BG_CLASSES[Math.abs(hash) % BG_CLASSES.length];
        }

        function extractImageAndTitle(html) {
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            var img = tmp.querySelector('img');
            var src = img ? img.getAttribute('src') : '';
            var isDefault = !src || src.indexOf('default.png') !== -1;
            var titleEl = tmp.querySelector('.media-title');
            var title = titleEl ? titleEl.textContent.trim() : tmp.textContent.trim();
            return { src: isDefault ? '' : src, title: title };
        }

        function applyNonBootstrapStyles($c) {
            $c.find('.svc-thumb-wrap').css({ flexShrink: 0 });
            $c.find('.svc-thumb-img').css({ width: '54px', height: '54px', objectFit: 'cover', borderRadius: '0.75rem' });
            $c.find('.svc-thumb-initial').css({ width: '54px', height: '54px', borderRadius: '0.75rem' });
            $c.find('.svc-qty-badge').css({ top: '-6px', right: '-6px' });
            $c.find('.svc-body').css({ minWidth: 0 });
            $c.find('.svc-net-wrap').css({ flexShrink: 0 });
        }

        function renderCards(rows) {
            var $container = $('#service-cards-container');
            var $countBadge = $('#service-item-count');
            if (!$container.length) return;
            if (!rows || rows.length === 0) {
                $container.html('<p class="text-muted text-center py-3"><?= labels('no_data_found', 'No data found') ?></p>');
                return;
            }
            $countBadge.text(rows.length + ' <?= labels('item', 'Item') ?>' + (rows.length > 1 ? 's' : ''));
            var html = '';
            rows.forEach(function (row) {
                var info = extractImageAndTitle(row.service_title || '');
                var title = info.title || '';
                var bgClass = bgClassForText(title);
                var qty = row.quantity || 1;
                var initial = title.substring(0, 5);

                var thumbHtml = info.src
                    ? '<img src="' + info.src + '" alt="' + title.replace(/"/g, '&quot;') + '" class="svc-thumb-img">' +
                    '<div class="' + bgClass + ' text-white font-weight-bold d-none svc-thumb-initial align-items-center justify-content-center">' + initial + '</div>'
                    : '<div class="' + bgClass + ' text-white font-weight-bold d-flex svc-thumb-initial align-items-center justify-content-center">' + initial + '</div>';

                html +=
                    '<div class="card border-0 shadow-sm mb-3">' +
                    '<div class="card-body d-flex align-items-center p-3">' +
                    '<div class="position-relative mr-3 svc-thumb-wrap">' +
                    thumbHtml +
                    '<span class="badge badge-primary position-absolute svc-qty-badge">x' + qty + '</span>' +
                    '</div>' +
                    '<div class="flex-fill svc-body">' +
                    '<h6 class="font-weight-bold text-dark mb-1">' + title + '</h6>' +
                    '<small class="text-muted">' +
                    '<?= labels('base_price', 'Base Price') ?>: <strong>' + (row.price || '') + '</strong>' +
                    ' &nbsp;&bull;&nbsp; ' +
                    '<?= labels('discount', 'Discount') ?>: <span class="text-success font-weight-bold">' + (row.discount || '') + '</span>' +
                    '</small>' +
                    '</div>' +
                    '<div class="text-right ml-3 svc-net-wrap">' +
                    '<small class="text-uppercase text-muted font-weight-bold d-block mb-1"><?= labels('net_total', 'Net Total') ?></small>' +
                    '<h5 class="font-weight-bold text-dark mb-0">' + (row.net_amount || '') + '</h5>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            });
            $container.html(html);
            applyNonBootstrapStyles($container);

            $container.find('.svc-thumb-img').on('error', function () {
                $(this).addClass('d-none').next('.svc-thumb-initial').removeClass('d-none').addClass('d-flex');
            });
        }

        var $container = $('#service-cards-container');
        if ($container.length) {
            $container.html('<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1"></i></div>');
            $.getJSON(SERVICE_URL, function (data) {
                renderCards(data.rows || []);
            }).fail(function () {
                $container.html('<p class="text-danger text-center py-3"><?= labels('no_data_found', 'No data found') ?></p>');
            });
        }
    })();
</script>

<!-- Additional Charge Payment Pending Modal -->
<div class="modal fade" id="additionalChargePendingModal" tabindex="-1" role="dialog"
    aria-labelledby="additionalChargePendingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="additionalChargePendingModalLabel">
                    <?= labels('additional_charge_payment_pending_title', 'Payment Pending') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    <?= labels('additional_charge_payment_pending_warning', "Customer hasn't selected a payment method and paid the additional charges yet. Please wait until the payment is completed before finishing this booking.") ?>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                <button type="button" class="btn btn-primary" data-dismiss="modal"><?= labels('ok', 'Okay') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Cancellation reason modal -->
<div class="modal fade" id="cancelReasonModal" tabindex="-1" role="dialog" aria-labelledby="cancelReasonModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelReasonModalLabel"><?= labels('cancel_booking', 'Cancel Booking') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="cancel_reason_select"
                        class="bold"><?= labels('cancellation_reason', 'Cancellation Reason') ?> <span
                            class="text-danger">*</span></label>
                    <select class="form-control" id="cancel_reason_select" required>
                        <option value=""><?= labels('select_a_reason', 'Select a reason') ?></option>
                        <?php if (!empty($cancel_reasons)): ?>
                            <?php foreach ($cancel_reasons as $reason): ?>
                                <option value="<?= (int) $reason['id'] ?>"
                                    data-needs-additional-info="<?= (int) $reason['needs_additional_info'] ?>">
                                    <?= esc($reason['reason']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small class="text-danger d-none"
                        id="cancel_reason_error"><?= labels('cancel_reason_id_is_required', 'Cancellation reason is required') ?></small>
                </div>
                <div class="form-group d-none" id="cancel_additional_info_wrapper">
                    <label for="cancel_additional_info"
                        class="bold"><?= labels('additional_information', 'Additional Information') ?> <span
                            class="text-danger">*</span></label>
                    <textarea class="form-control" id="cancel_additional_info" rows="3" maxlength="500"
                        placeholder="<?= labels('please_provide_additional_information', 'Please provide additional information') ?>"></textarea>
                    <small class="text-muted"><span id="cancel_additional_info_count">0</span>/500</small>
                    <small class="text-danger d-none d-block"
                        id="cancel_additional_info_error"><?= labels('additional_information_is_required_for_this_reason', 'Additional information is required for this reason') ?></small>
                </div>
            </div>
            <div class="modal-footer flex-wrap">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-danger"
                    id="confirm_cancel_reason"><?= labels('confirm_cancellation', 'Confirm Cancellation') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        var cancelConfirmed = false;
        var $statusSelect = $('#status');
        var $changeBtn = $('#change_status');

        $statusSelect.on('change', function () {
            cancelConfirmed = false;
            $('#cancel_reason_id').val('');
            $('#cancel_additional_info_input').val('');
        });

        var btn = document.getElementById('change_status');
        if (btn) {
            btn.addEventListener('click', function (e) {
                var status = $statusSelect.val();
                if (status !== 'cancelled') return;
                if (cancelConfirmed) return;
                // No reasons configured — let the click pass through to the AJAX handler
                if ($('#cancel_reason_select option').length <= 1) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                $('#cancel_reason_select').val('');
                $('#cancel_additional_info').val('');
                $('#cancel_additional_info_count').text('0');
                $('#cancel_additional_info_wrapper').addClass('d-none');
                $('#cancel_reason_error').addClass('d-none');
                $('#cancel_additional_info_error').addClass('d-none');
                $('#cancelReasonModal').modal('show');
            }, true);
        }

        $('#cancel_reason_select').on('change', function () {
            $('#cancel_reason_error').addClass('d-none');
            var needs = $(this).find(':selected').data('needs-additional-info');
            if (parseInt(needs, 10) === 1) {
                $('#cancel_additional_info_wrapper').removeClass('d-none');
            } else {
                $('#cancel_additional_info_wrapper').addClass('d-none');
                $('#cancel_additional_info').val('');
                $('#cancel_additional_info_count').text('0');
                $('#cancel_additional_info_error').addClass('d-none');
            }
        });

        $('#cancel_additional_info').on('input', function () {
            var len = $(this).val().length;
            $('#cancel_additional_info_count').text(len);
            if (len > 0) $('#cancel_additional_info_error').addClass('d-none');
        });

        $('#confirm_cancel_reason').on('click', function () {
            var $sel = $('#cancel_reason_select');
            var reasonId = $sel.val();
            var needs = parseInt($sel.find(':selected').data('needs-additional-info'), 10) === 1;
            var info = $.trim($('#cancel_additional_info').val());
            var ok = true;
            if (!reasonId) {
                $('#cancel_reason_error').removeClass('d-none');
                ok = false;
            }
            if (needs && info === '') {
                $('#cancel_additional_info_error').removeClass('d-none');
                ok = false;
            }
            if (!ok) return;
            $('#cancel_reason_id').val(reasonId);
            $('#cancel_additional_info_input').val(needs ? info : '');
            cancelConfirmed = true;
            $('#cancelReasonModal').modal('hide');
            $changeBtn.trigger('click');
        });
    })();
</script>
<!-- Reschedule Modal -->
<div class="modal fade" id="partnerRescheduleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('reschedule_booking', 'Reschedule Booking') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="rescheduled_date"><?= labels('rescheduled_date', 'Rescheduled Date') ?></label>
                    <input id="rescheduled_date" class="form-control" type="date" name="rescheduled_date"
                        min="<?= esc($reschedule_min_date ?? '') ?>" <?= !empty($reschedule_max_date) ? 'max="' . esc($reschedule_max_date) . '"' : '' ?> data-min-date="<?= esc($reschedule_min_date ?? '') ?>"
                        data-max-date="<?= esc($reschedule_max_date ?? '') ?>"
                        data-advance-days="<?= esc($advance_booking_days ?? '') ?>"
                        data-min-error="<?= labels(PLEASE_SELECT_UPCOMING_DATE, 'Please select upcoming date') ?>"
                        data-max-error="<?= labels(YOU_CAN_NOT_CHOOSE_DATE_BEYOND_AVAILABLE_BOOKING_DAYS, 'You cannot choose a date beyond available booking days') ?>"
                        data-no-advance-error="<?= labels(ADVANCED_BOOKING_FOR_THIS_PARTNER_IS_NOT_AVAILABLE, 'Advanced booking for this partner is not available') ?>">
                </div>
                <div class="row" id="available-slots"
                    data-empty-msg="<?= labels('no_slot_available_on_this_date', 'No slot available on this date!') ?>">
                </div>
                <small class="text-danger d-none"
                    id="reschedule_date_error"><?= labels('please_select_rescheduled_date', 'Please select a rescheduled date') ?></small>
                <small class="text-danger d-none"
                    id="reschedule_slot_error"><?= labels('please_select_reschedule_timing', 'Please select reschedule timing') ?></small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-info"
                    id="confirm_reschedule_btn"><?= labels('confirm', 'Confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Work Started Modal -->
<div class="modal fade" id="partnerStartedModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('start_booking', 'Start Booking') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <?= labels('work_started_proof_optional', 'You may upload work started proof. This is optional.') ?>
                </p>
                <div class="form-group mb-0">
                    <label
                        class="font-weight-bold small"><?= labels('work_started_proof', 'Work Started Proof') ?></label>
                    <input type="file" class="filepond-only-images-and-videos" id="modal_work_started_files"
                        name="work_started_files[]" multiple>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-warning submit_btn"
                    id="confirm_started_btn"><?= labels('confirm', 'Confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Booking Ended Modal -->
<div class="modal fade" id="partnerBookingEndedModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('booking_ended', 'Booking Ended') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label
                        class="font-weight-bold small"><?= labels('work_completed_proof', 'Work Completed Proof') ?></label>
                    <p class="text-muted small mb-2">
                        <?= labels('work_completed_proof_optional', 'You may upload work completed proof. This is optional.') ?>
                    </p>
                    <input type="file" class="filepond-only-images-and-videos" id="modal_work_complete_files"
                        name="work_complete_files[]" multiple>
                </div>
                <div id="modal_additional_charges_container">
                    <label
                        class="font-weight-bold small"><?= labels('additional_charges', 'Additional Charges') ?></label>
                    <p class="text-muted small mb-3">
                        <?= labels('additional_charges_optional', 'You may add additional charges if applicable. This is optional.') ?>
                    </p>
                    <div class="booking_ended_additional_charge row g-2 mb-2">
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm charge-name"
                                placeholder="<?= labels('charge_name', 'Charge Name') ?>">
                        </div>
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm charge-amount" min="0" step="0.01"
                                placeholder="<?= labels('amount', 'Amount') ?>">
                        </div>
                        <div class="col-2 d-flex align-items-center">
                            <button type="button" class="btn btn-sm btn-light border btn-add-charge">+</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-primary submit_btn"
                    id="confirm_booking_ended_btn"><?= labels('confirm', 'Confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var UPDATE_URL = '<?= base_url('partner/orders/update_order_status') ?>';
        var ORDER_ID = <?= (int) $order_details['id'] ?>;

        // ── Reschedule modal ──────────────────────────────────────────────────────

        $('#partnerRescheduleModal').on('show.bs.modal', function () {
            $('#available-slots').show();
        });

        $('#partnerRescheduleModal').on('hidden.bs.modal', function () {
            $('#rescheduled_date').val('');
            $('#available-slots').empty().hide();
            $('#reschedule_date_error, #reschedule_slot_error').addClass('d-none');
        });

        $('#confirm_reschedule_btn').on('click', function () {
            var date = $('#rescheduled_date').val();
            var slot = $('input[name="reschedule"]:checked').val();
            var ok = true;
            if (!date) { $('#reschedule_date_error').removeClass('d-none'); ok = false; }
            else { $('#reschedule_date_error').addClass('d-none'); }
            if (!slot) { $('#reschedule_slot_error').removeClass('d-none'); ok = false; }
            else { $('#reschedule_slot_error').addClass('d-none'); }
            if (!ok) return;

            var fd = new FormData();
            fd.append('order_id', ORDER_ID);
            fd.append('status', 'rescheduled');
            fd.append('rescheduled_date', date);
            fd.append('reschedule', slot);
            $('#partnerRescheduleModal').modal('hide');
            formAjaxRequest('POST', UPDATE_URL, fd, null, $(this),
                function () { setTimeout(function () { location.reload(); }, 1500); }, null);
        });

        // ── Started modal ─────────────────────────────────────────────────────────

        var startedProofInput = document.getElementById('modal_work_started_files');

        $('#partnerStartedModal').on('hidden.bs.modal', function () {
            if (typeof FilePond !== 'undefined' && startedProofInput) {
                var pond = FilePond.find(startedProofInput);
                if (pond) pond.removeFiles();
            }
        });

        $('#confirm_started_btn').on('click', function () {
            var fd = new FormData();
            fd.append('order_id', ORDER_ID);
            fd.append('status', 'started');
            if (typeof FilePond !== 'undefined' && startedProofInput) {
                var pond = FilePond.find(startedProofInput);
                if (pond) {
                    pond.getFiles().forEach(function (f) { fd.append('work_started_files[]', f.file); });
                }
            }
            $('#partnerStartedModal').modal('hide');
            formAjaxRequest('POST', UPDATE_URL, fd, null, $(this),
                function () { setTimeout(function () { location.reload(); }, 1500); }, null);
        });

        // ── Booking Ended modal ───────────────────────────────────────────────────

        var bookingEndedProofInput = document.getElementById('modal_work_complete_files');

        $('#partnerBookingEndedModal').on('show.bs.modal', function () {
            $('#modal_additional_charges_container .booking_ended_additional_charge').show();
        });

        $('#partnerBookingEndedModal').on('hidden.bs.modal', function () {
            if (typeof FilePond !== 'undefined' && bookingEndedProofInput) {
                var pond = FilePond.find(bookingEndedProofInput);
                if (pond) pond.removeFiles();
            }
            // Reset charge rows to single empty row
            var $c = $('#modal_additional_charges_container');
            $c.find('.booking_ended_additional_charge').not(':first').remove();
            $c.find('.charge-name, .charge-amount').val('');
        });

        $('#modal_additional_charges_container').on('click', '.btn-add-charge', function () {
            var $row = $(this).closest('.booking_ended_additional_charge').clone();
            $row.find('input').val('');
            $row.find('.btn-add-charge').removeClass('btn-add-charge').addClass('btn-remove-charge').text('-');
            $('#modal_additional_charges_container').append($row);
        });
        $('#modal_additional_charges_container').on('click', '.btn-remove-charge', function () {
            $(this).closest('.booking_ended_additional_charge').remove();
        });

        $('#confirm_booking_ended_btn').on('click', function () {
            var fd = new FormData();
            fd.append('order_id', ORDER_ID);
            fd.append('status', 'booking_ended');

            var idx = 0;
            $('#modal_additional_charges_container .booking_ended_additional_charge').each(function () {
                var name = $(this).find('.charge-name').val().trim();
                var amount = $(this).find('.charge-amount').val().trim();
                if (name && amount) {
                    fd.append('booking_ended_additional_charges[' + idx + '][name]', name);
                    fd.append('booking_ended_additional_charges[' + idx + '][charge]', amount);
                    idx++;
                }
            });

            if (typeof FilePond !== 'undefined' && bookingEndedProofInput) {
                var pond = FilePond.find(bookingEndedProofInput);
                if (pond) {
                    pond.getFiles().forEach(function (f) { fd.append('work_complete_files[]', f.file); });
                }
            }

            $('#partnerBookingEndedModal').modal('hide');
            formAjaxRequest('POST', UPDATE_URL, fd, null, $(this),
                function () { setTimeout(function () { location.reload(); }, 1500); }, null);
        });
    })();
</script>
<?php if ($partner_is_tracking ?? false): ?>
    <script>
        (function () {
            if (!navigator.geolocation) return;
            var PUSH_URL = '<?= base_url('partner/orders/update_partner_location') ?>';
            var ORDER_ID = <?= (int) $order_details['id'] ?>;
            var lastPush = 0;
            navigator.geolocation.watchPosition(
                function (pos) {
                    var now = Date.now();
                    if (now - lastPush < 30000) return;
                    lastPush = now;
                    var fd = new FormData();
                    fd.append('order_id', ORDER_ID);
                    fd.append('latitude', pos.coords.latitude);
                    fd.append('longitude', pos.coords.longitude);
                    ajaxRequest('POST', PUSH_URL, fd, null, null, null, null, false);
                },
                null,
                { enableHighAccuracy: true, maximumAge: 30000, timeout: 10000 }
            );
        })();
    </script>
<?php endif; ?>