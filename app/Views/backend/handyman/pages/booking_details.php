<?= $this->extend('backend/handyman/layouts/main') ?>

<?= $this->section('page_styles') ?>
<style>
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

    html[dir="rtl"] .bh-hs-step:not(:last-child)::after {
        left: auto;
        right: 50%;
    }

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
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$orderStatus = $booking['status'] ?? 'pending';
$handymanStatus = $booking['handyman_status'] ?? 'assigned';

$orderStatusColors = [
    'completed' => 'success',
    'cancelled' => 'danger',
    'declined' => 'danger',
    'active' => 'warning',
    'on_the_way' => 'info',
    'arrived' => 'info',
    'started' => 'warning',
    'booking_ended' => 'warning',
    'pending' => 'secondary',
];

$handymanStatusColors = [
    'assigned' => 'secondary',
    'accepted' => 'primary',
    'rejected' => 'danger',
    'on_the_way' => 'info',
    'arrived' => 'warning',
    'started' => 'warning',
    'booking_ended' => 'warning',
    'completed' => 'success',
];

$handymanStatusColor = $handymanStatusColors[$handymanStatus] ?? 'secondary';

// Block status change unless payment is successful (COD is exempt)
$isPaymentPending = ($booking['payment_status'] ?? '') !== 'success' && ($booking['payment_method'] ?? '') !== 'cod';

// Additional-charge payment pending — mirrors validate_order_status() 'completed' guard in function_helper.php
$isAdditionalChargePaymentPending = (empty($booking['payment_method_of_additional_charge']) || $booking['payment_method_of_additional_charge'] !== 'cod')
    && !empty($booking['total_additional_charge']) && $booking['total_additional_charge'] != 0
    && in_array($booking['payment_status_of_additional_charge'] ?? '', ['', '0'], true);

$isAtStore = ((int) ($booking['address_id'] ?? 1)) === 0;

// Allowed next transitions per current status
// 'accepted'/'rejected' removed — handymen are auto-accepted on assignment by provider
$transitions = $isAtStore
    ? [
        'assigned' => [], // was: ['accepted', 'rejected']
        'accepted' => ['started'], // was: ['started', 'rejected']
        'started' => ['booking_ended', 'completed'],
        'booking_ended' => ['completed'],
        'rejected' => [],
        'completed' => [],
    ]
    : [
        'assigned' => [], // was: ['accepted', 'rejected']
        'accepted' => ['on_the_way'], // was: ['on_the_way', 'rejected']
        'on_the_way' => ['arrived'],
        'arrived' => ['started'],
        'started' => ['booking_ended', 'completed'],
        'booking_ended' => ['completed'],
        'rejected' => [],
        'completed' => [],
    ];
$nextStatuses = $isPaymentPending ? [] : ($transitions[$handymanStatus] ?? []);

$statusLabels = [
    'accepted' => labels('accept', 'Accept'),
    'rejected' => labels('reject', 'Reject'),
    'on_the_way' => labels('on_the_way', 'On The Way'),
    'arrived' => labels('arrived', 'Arrived'),
    'started' => labels('start', 'Start'),
    'booking_ended' => labels('end_booking', 'End Booking'),
    'completed' => labels('complete_booking', 'Complete Booking'),
];

$statusBtnClasses = [
    'accepted' => 'btn-success',
    'rejected' => 'btn-danger',
    'on_the_way' => 'btn-info',
    'arrived' => 'btn-warning',
    'started' => 'btn-warning',
    'booking_ended' => 'btn-primary',
    'completed' => 'btn-success',
];

$workStartedProof = !empty($booking['work_started_proof']) ? json_decode($booking['work_started_proof'], true) : [];
?>

<div class="section-body">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body px-4 py-3 d-flex align-items-center flex-wrap gap-3">
            <span class="fw-bold text-dark fs-6">INV-<?= (int) $booking['id'] ?></span>
            <div id="order_status_badge_wrap" class="ms-auto">
                <span class="badge bg-<?= $orderStatusColors[$orderStatus] ?? 'secondary' ?> px-3 py-2"
                    style="font-size: 14px;">
                    <?= ucfirst(labels($orderStatus)) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="row">

        <!-- Left: Booking Info -->
        <div class="col-lg-8">

            <!-- Customer Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">
                    <span class="material-symbols-outlined me-2 text-primary">person</span>
                    <h6 class="mb-0 fw-bold"><?= labels('customer', 'Customer') ?></h6>
                </div>
                <div class="card-body px-4 py-3">
                    <div class="row">
                        <div class="col-sm-4 mb-3">
                            <div class="text-muted small"><?= labels('name', 'Name') ?></div>
                            <div class="fw-bold"><?= esc($booking['customer_name'] ?? '-') ?></div>
                        </div>
                        <div class="col-sm-4 mb-3">
                            <div class="text-muted small"><?= labels('phone', 'Phone') ?></div>
                            <div class="fw-bold">
                                <?= esc(trim(($booking['customer_country_code'] ?? '') . ($booking['customer_phone'] ?? '')) ?: '-') ?>
                            </div>
                        </div>
                        <div class="col-sm-4 mb-3">
                            <div class="text-muted small"><?= labels('email', 'Email') ?></div>
                            <div class="fw-bold"><?= esc($booking['customer_email'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Info Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">
                    <span class="material-symbols-outlined me-2 text-primary">handyman</span>
                    <h6 class="mb-0 fw-bold"><?= labels('service_info', 'Service Info') ?></h6>
                </div>
                <div class="card-body px-4 py-3">
                    <div class="row">
                        <div class="col-sm-3 mb-3">
                            <div class="text-muted small"><?= labels('date_of_service', 'Service Date') ?></div>
                            <div class="fw-bold">
                                <?= !empty($booking['date_of_service']) ? date('M d, Y', strtotime($booking['date_of_service'])) : '-' ?>
                            </div>
                        </div>
                        <div class="col-sm-3 mb-3">
                            <div class="text-muted small"><?= labels('time', 'Time') ?></div>
                            <div class="fw-bold">
                                <?= esc(($booking['starting_time'] ?? '-') . ' – ' . ($booking['ending_time'] ?? '-')) ?>
                            </div>
                        </div>
                        <div class="col-sm-3 mb-3">
                            <div class="text-muted small"><?= labels('address', 'Address') ?></div>
                            <div class="fw-bold"><?= esc($booking['address'] ?? '-') ?></div>
                        </div>
                        <div class="col-sm-3 mb-3">
                            <div class="text-muted small"><?= labels('service_type', 'Service Type') ?></div>
                            <div class="fw-bold">
                                <?php if ($isAtStore): ?>
                                    <span class="badge bg-info"><?= labels('at_store', 'At Store') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-primary"><?= labels('at_doorstep', 'At Doorstep') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($order_services)): ?>
                        <hr class="mt-0 mb-3">
                        <h6 class="mb-3 fw-bold"><?= labels('services', 'Services') ?></h6>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($order_services as $service):
                                $qty = (int) ($service['quantity'] ?? 1);
                                $title = esc($service['service_title'] ?? '');

                                $hasImage = !empty($service['service_image']);
                                if ($hasImage) {
                                    $imageSrc = base_url($service['service_image']);
                                } else {
                                    $imageSrc = base_url('public/backend/assets/default.png');
                                }

                                $original_price = (float) ($service['price'] ?? 0);
                                $discount_price = (float) ($service['discount_price'] ?? 0);
                                $unitPrice = ($discount_price > 0) ? $discount_price : $original_price;
                                $net_amount = $unitPrice * $qty;
                                ?>
                                <div class="card border shadow-sm bg-light">
                                    <div class="card-body d-flex align-items-center p-3">
                                        <div class="position-relative me-3" style="flex-shrink: 0;">
                                            <img src="<?= $imageSrc ?>" alt="<?= $title ?>"
                                                onerror="this.src='<?= base_url('public/backend/assets/default.png') ?>'"
                                                style="width: 54px; height: 54px; object-fit: cover; border-radius: 0.75rem;">
                                            <span class="badge bg-primary position-absolute"
                                                style="top: -6px; right: -6px;">x<?= $qty ?></span>
                                        </div>
                                        <div class="flex-fill" style="min-width: 0;">
                                            <h6 class="fw-bold text-dark mb-1 text-truncate"><?= $title ?></h6>
                                            <small class="text-muted">
                                                <?= labels('base_price', 'Base Price') ?>:
                                                <strong><?= $currency . number_format($original_price, 2) ?></strong>
                                                <?php if ($discount_price > 0): ?>
                                                    &nbsp;&bull;&nbsp; <?= labels('discount', 'Discount') ?>: <span
                                                        class="text-success fw-bold"><?= $currency . number_format($original_price - $discount_price, 2) ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="text-end ms-3" style="flex-shrink: 0;">
                                            <small
                                                class="text-uppercase text-muted fw-bold d-block mb-1"><?= labels('net_total', 'Net Total') ?></small>
                                            <h5 class="fw-bold text-dark mb-0"><?= $currency . number_format($net_amount, 2) ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assigned Handymen -->
            <div id="assigned_handymen_wrap">
                <?php if (!empty($assigned_handymen)): ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">
                            <span class="material-symbols-outlined me-2 text-primary">group</span>
                            <h6 class="mb-0 fw-bold"><?= labels('assigned_handymen', 'Assigned Handymen') ?></h6>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php 
                            usort($assigned_handymen, function($a, $b) {
                                return $b['is_lead'] <=> $a['is_lead'];
                            });
                            foreach ($assigned_handymen as $ah): 
                            ?>
                                <li class="list-group-item px-4 py-2 d-flex align-items-center gap-3">
                                    <img src="<?= esc($ah['image']) ?>" alt="" width="36" height="36"
                                        class="rounded-circle object-fit-cover border flex-shrink-0">
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="fw-semibold text-truncate"><?= esc($ah['username']) ?></div>
                                        <div class="text-muted small">
                                            <?= ucfirst(labels($ah['handyman_status'])) ?>
                                        </div>
                                    </div>
                                    <?php if ($ah['is_lead']): ?>
                                        <span class="badge bg-primary"><?= labels('lead', 'Lead') ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div><!-- /assigned_handymen_wrap -->

        </div>

        <!-- Right: Summary + Action Center -->
        <div class="col-lg-4">

            <!-- My Status Card -->
            <?php
            $steps = $isAtStore
                ? ['assigned', 'accepted', 'started', 'booking_ended', 'completed']
                : ['assigned', 'accepted', 'on_the_way', 'arrived', 'started', 'booking_ended', 'completed'];
            $stepLabels = [
                'assigned' => labels('assigned', 'Assigned'),
                'accepted' => labels('accepted', 'Accepted'),
                'on_the_way' => labels('on_the_way', 'On The Way'),
                'arrived' => labels('arrived', 'Arrived'),
                'started' => labels('started', 'Started'),
                'booking_ended' => labels('booking_ended', 'Booking Ended'),
                'completed' => labels('completed', 'Completed'),
            ];
            $stepIcons = [
                'assigned' => 'assignment_ind',
                'accepted' => 'check_circle',
                'on_the_way' => 'directions_car',
                'arrived' => 'location_on',
                'started' => 'construction',
                'booking_ended' => 'handshake',
                'completed' => 'task_alt',
            ];
            $currentStepIndex = array_search($handymanStatus, $steps);
            $isRejected = $handymanStatus === 'rejected';
            ?>
            <div class="card shadow-sm border-0 mb-4">
                <div
                    class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <span class="material-symbols-outlined me-2 text-primary">timeline</span>
                        <h6 class="mb-0 fw-bold"><?= labels('my_status', 'My Status') ?></h6>
                    </div>
                    <div id="live_track_btn_wrap">
                        <?php if ($handymanStatus === 'on_the_way'): ?>
                            <a href="<?= base_url('handyman/bookings/live_tracking/' . (int) $booking['id']) ?>"
                                class="btn btn-sm btn-outline-info" id="btn_live_track">
                                <span class="material-symbols-outlined"
                                    style="font-size:16px;vertical-align:-4px;">directions</span>
                                <?= labels('live_track_route', 'Live Track Route') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body px-4 py-3" id="stepper_card_body">
                    <?php if ($isRejected): ?>
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3"
                            style="background:#fff1f0;border:1px solid #ffccc7;">
                            <span class="material-symbols-outlined text-danger" style="font-size:28px;">cancel</span>
                            <div>
                                <div class="fw-bold text-danger"><?= labels('rejected', 'Rejected') ?></div>
                                <?php if (!empty($booking['rejected_reason'])): ?>
                                    <div class="text-muted small mt-1"><?= esc($booking['rejected_reason']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bh-hstepper">
                            <?php foreach ($steps as $i => $step):
                                $isDone = $currentStepIndex !== false && $i < $currentStepIndex;
                                $isCurrent = $currentStepIndex !== false && $i === $currentStepIndex;
                                $state = $isDone ? 'done' : ($isCurrent ? 'current' : 'pending');
                                ?>
                                <div class="bh-hs-step <?= $state ?>">
                                    <div class="bh-hs-dot">
                                        <span class="material-symbols-outlined">
                                            <?= $isDone ? 'check' : ($stepIcons[$step] ?? 'circle') ?>
                                        </span>
                                    </div>
                                    <div class="bh-hs-label <?= $state ?>"><?= $stepLabels[$step] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="status_card_footer_wrap">
                    <?php if ($isPaymentPending && !in_array($handymanStatus, ['rejected', 'completed'])): ?>
                        <div class="card-footer bg-white border-top px-4 py-3">
                            <div class="d-flex align-items-center gap-2 p-2 rounded-3"
                                style="background:#fff8e1;border:1px solid #ffe082;">
                                <span class="material-symbols-outlined text-warning" style="font-size:20px;">warning</span>
                                <div class="small text-muted">
                                    <?= payment_block_message($booking['payment_status'] ?? null) ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif (!empty($nextStatuses) && !empty($booking['is_lead'])): ?>
                        <div class="card-footer bg-white border-top px-4 py-3">
                            <div class="d-grid gap-2">
                                <?php foreach ($nextStatuses as $ns):
                                    $btnClass = $statusBtnClasses[$ns] ?? 'btn-secondary';
                                    $label = $statusLabels[$ns] ?? ucfirst(str_replace('_', ' ', $ns));
                                    $orderId = $booking['id'];
                                    if ($ns === 'rejected'): ?>
                                        <button type="button" class="btn btn-sm <?= $btnClass ?>" data-bs-toggle="modal"
                                            data-bs-target="#rejectReasonModal">
                                            <?= $label ?>
                                        </button>
                                    <?php elseif ($ns === 'started'): ?>
                                        <button type="button" class="btn btn-sm <?= $btnClass ?>" data-bs-toggle="modal"
                                            data-bs-target="#startedModal">
                                            <?= $label ?>
                                        </button>
                                    <?php elseif ($ns === 'booking_ended'): ?>
                                        <button type="button" class="btn btn-sm <?= $btnClass ?>" data-bs-toggle="modal"
                                            data-bs-target="#bookingEndedModal">
                                            <?= $label ?>
                                        </button>
                                    <?php elseif ($ns === 'completed'): ?>
                                        <button type="button" class="btn btn-sm <?= $btnClass ?> btn-complete-check">
                                            <?= $label ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm <?= $btnClass ?> btn-update-status"
                                            data-status="<?= $ns ?>" data-order-id="<?= $orderId ?>">
                                            <?= $label ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div><!-- /status_card_footer_wrap -->
            </div>

            <!-- Instructions -->
            <?php if (!empty($booking['remarks'])): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">
                        <span class="material-symbols-outlined me-2 text-primary">notes</span>
                        <h6 class="mb-0 fw-bold"><?= labels('instructions', 'Instructions') ?></h6>
                    </div>
                    <div class="card-body px-4 py-3">
                        <p class="mb-0"><?= esc($booking['remarks']) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payment Summary -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">
                    <span class="material-symbols-outlined me-2 text-primary">payments</span>
                    <h6 class="mb-0 fw-bold"><?= labels('payment_summary', 'Payment Summary') ?></h6>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-4">
                        <span class="text-muted"><?= labels('total', 'Total') ?></span>
                        <span
                            class="fw-bold"><?= esc($currency) . number_format((float) ($booking['subtotal'] ?? 0), 2) ?></span>
                    </li>
                    <?php if (isset($booking['visiting_charges']) && $booking['visiting_charges'] != "0"): ?>
                        <li class="list-group-item d-flex justify-content-between px-4">
                            <span class="text-muted"><?= labels('service_charge', 'Service Charge') ?></span>
                            <span
                                class="fw-bold"><?= esc($currency) . number_format((float) ($booking['visiting_charges'] ?? 0), 2) ?></span>
                        </li>
                    <?php endif; ?>
                    
                    
                    <?php if (!empty($booking['additional_charges'])): ?>
                        <?php foreach (($booking['additional_charges']) as $charge): ?>
                            <li class="list-group-item d-flex justify-content-between px-4">
                                <span class="text-muted"><?= !empty($charge['name']) ? esc($charge['name']) : 'N/A' ?></span>
                                <span
                                    class="fw-bold"><?= esc($currency) . number_format((float) ($charge['charge'] ?? 0), 2) ?></span>
                            </li>
                            
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between px-4">
                        <span class="text-muted">
                            <?= labels('tax_amount', 'Tax Amount') ?>
                            <?php if (!empty($booking['tax_type'])): ?>
                                (<?= ucfirst(labels(esc($booking['tax_type']))) ?>)
                            <?php endif; ?>
                        </span>
                        <span
                            class="fw-bold"><?= esc($currency) . number_format((float) ($booking['tax_amount'] ?? 0), 2) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-4">
                        <span class="text-muted"><?= labels('promo_code', 'Promo Code') ?></span>
                        <span
                            class="fw-bold"><?= !empty($booking['promo_discount']) ? esc($currency) . number_format((float) $booking['promo_discount'], 2) : labels('no_data_found', 'No data found') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-4 fw-bold bg-light">
                        <span><?= labels('payable_total', 'Payable Total') ?> (<?= esc($currency) ?>)</span>
                        <span
                            class="text-primary"><?= number_format((float) ($booking['final_total'] ?? 0), 2) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-4">
                        <span class="text-muted"><?= labels('payment_method', 'Payment Method') ?></span>
                        <span><?= esc(strtoupper(labels($booking['payment_method']) ?? '-')) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-4">
                        <span class="text-muted"><?= labels('payment_status', 'Payment Status') ?></span>
                        <?php
                        $ps = $booking['payment_status'] ?? '';
                        if ($ps === 'success'):
                            ?>
                            <span
                                class="badge bg-success d-flex align-items-center"><?= labels('success', 'Success') ?></span>
                        <?php elseif ($ps === 'failed'): ?>
                            <span class="badge bg-danger d-flex align-items-center"><?= labels('failed', 'Failed') ?></span>
                        <?php else: ?>
                            <span
                                class="badge bg-warning d-flex align-items-center"><?= labels('pending', 'Pending') ?></span>
                        <?php endif; ?>
                    </li>
                    <?php if (!empty($booking['total_additional_charge'])): ?>
                        <li class="list-group-item d-flex justify-content-between px-4">
                            <span
                                class="text-muted"><?= labels('payment_method_of_additional_charge', 'Payment Method Of Additional Charges') ?></span>
                            <span>
                                <?= !empty($booking['payment_method_of_additional_charge']) ? ($booking['payment_method_of_additional_charge'] === 'cod' ? labels('pay_on_service', 'Pay On Service') : esc(strtoupper($booking['payment_method_of_additional_charge']))) : '-' ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-4">
                            <span
                                class="text-muted"><?= labels('payment_status_of_additional_charges', 'Payment Status of Additional Charges') ?></span>
                            <?php
                            $pac = $booking['payment_status_of_additional_charge'] ?? '0';
                            if ($pac === '1'):
                                ?>
                                <span
                                    class="badge bg-success d-flex align-items-center"><?= labels('success', 'Success') ?></span>
                            <?php elseif ($pac === '2'): ?>
                                <span class="badge bg-danger d-flex align-items-center"><?= labels('failed', 'Failed') ?></span>
                            <?php else: ?>
                                <span
                                    class="badge bg-warning d-flex align-items-center"><?= labels('pending', 'Pending') ?></span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-4 fw-bold">
                            <span><?= labels('total_additional_charges', 'Total Additional Charges') ?>
                                (<?= esc($currency) ?>)</span>
                            <span><?= number_format((float) $booking['total_additional_charge'], 2) ?></span>
                        </li>
                        <?php if (!empty($booking['additional_charges'])): ?>
                            <?php foreach ($booking['additional_charges'] as $charge): ?>
                                <li class="list-group-item d-flex justify-content-between px-4">
                                    <span class="text-muted"><?= esc($charge['name'] ?? 'N/A') ?></span>
                                    <span><?= esc($currency) ?><?= number_format((float) ($charge['charge'] ?? 0), 2) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>

        </div>
    </div>

    <!-- Work Proof Row -->
    <?php
    $workCompletedProof = !empty($booking['work_completed_proof']) ? json_decode($booking['work_completed_proof'], true) : [];
    $videoExt = ['mov', 'mp4', 'm3u8', 'ts', '3gp', 'avi', 'wmv'];
    ?>
    <div class="row">
        <div class="col-md-6" id="work_started_proof_wrap">
            <?php if (!empty($workStartedProof)): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">
                        <span class="material-symbols-outlined me-2 text-primary">construction</span>
                        <h6 class="mb-0 fw-bold"><?= labels('work_started_proof', 'Work Started Proof') ?></h6>
                    </div>
                    <div class="card-body px-4 py-3">
                        <div class="row g-2">
                            <?php foreach ($workStartedProof as $proofFile):
                                $ext = strtolower(pathinfo($proofFile, PATHINFO_EXTENSION));
                                ?>
                                <div class="col-3">
                                    <?php if (in_array($ext, $videoExt)): ?>
                                        <video controls class="rounded border w-100" style="height:120px;object-fit:cover;">
                                            <source src="<?= esc($proofFile) ?>" type="video/mp4">
                                        </video>
                                    <?php else: ?>
                                        <a href="<?= esc($proofFile) ?>" target="_blank">
                                            <img src="<?= esc($proofFile) ?>" alt="" class="rounded border w-100"
                                                style="height:120px;object-fit:cover;">
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-6" id="work_completed_proof_wrap">
            <?php if (!empty($workCompletedProof)): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">
                        <span class="material-symbols-outlined me-2 text-success">task_alt</span>
                        <h6 class="mb-0 fw-bold"><?= labels('work_completed_proof', 'Work Completed Proof') ?></h6>
                    </div>
                    <div class="card-body px-4 py-3">
                        <div class="row g-2">
                            <?php foreach ($workCompletedProof as $proofFile):
                                $ext = strtolower(pathinfo($proofFile, PATHINFO_EXTENSION));
                                ?>
                                <div class="col-3">
                                    <?php if (in_array($ext, $videoExt)): ?>
                                        <video controls class="rounded border w-100" style="height:120px;object-fit:cover;">
                                            <source src="<?= esc($proofFile) ?>" type="video/mp4">
                                        </video>
                                    <?php else: ?>
                                        <a href="<?= esc($proofFile) ?>" target="_blank">
                                            <img src="<?= esc($proofFile) ?>" alt="" class="rounded border w-100"
                                                style="height:120px;object-fit:cover;">
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div><!-- /work proof row -->
</div>

<!-- Started Modal -->
<div class="modal fade" id="startedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('started', 'Started') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <?= labels('work_started_proof_optional', 'You may upload work started proof. This is optional.') ?>
                </p>
                <div class="mb-2">
                    <label
                        class="form-label small fw-bold"><?= labels('work_started_proof', 'Work Started Proof') ?></label>
                    <input type="file" class="filepond" id="started_proof_files" name="work_started_files[]" multiple>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-warning submit_btn" id="confirm_started_btn"
                    data-order-id="<?= $booking['id'] ?>"><?= labels('confirm', 'Confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Reason Modal -->
<div class="modal fade" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('reject_booking', 'Reject Booking') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label"><?= labels('reason', 'Reason') ?> <span class="text-danger">*</span></label>
                <textarea id="reject_reason_input" class="form-control" rows="3"
                    placeholder="<?= labels('enter_reason', 'Enter reason...') ?>"></textarea>
                <div class="invalid-feedback" id="reject_reason_error">
                    <?= labels('reason_required', 'Reason is required') ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-danger submit_btn" id="confirm_reject_btn"
                    data-order-id="<?= $booking['id'] ?>"><?= labels('reject', 'Reject') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Booking Ended Modal -->
<div class="modal fade" id="bookingEndedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('booking_ended', 'Booking Ended') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label
                        class="form-label small fw-bold"><?= labels('work_completed_proof', 'Work Completed Proof') ?></label>
                    <p class="text-muted small mb-2">
                        <?= labels('work_completed_proof_optional', 'You may upload work completed proof. This is optional.') ?>
                    </p>
                    <input type="file" class="filepond" id="booking_ended_proof_files" name="work_complete_files[]"
                        multiple>
                </div>
                <div id="additional_charges_container">
                    <label
                        class="form-label small fw-bold"><?= labels('additional_charges', 'Additional Charges') ?></label>
                    <p class="text-muted small mb-3">
                        <?= labels('additional_charges_optional', 'You may add additional charges if applicable. This is optional.') ?>
                    </p>
                    <div class="additional-charge-row row g-2 mb-2">
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
                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-primary submit_btn" id="confirm_booking_ended_btn"
                    data-order-id="<?= $booking['id'] ?>"><?= labels('confirm', 'Confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Complete Modal -->
<div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('complete_booking', 'Complete Booking') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($is_otp_enable === '1'): ?>
                    <p class="text-muted small mb-2">
                        <?= labels('enter_otp_from_customer', 'Enter the OTP provided by the customer to complete this booking.') ?>
                    </p>
                    <input type="text" id="completion_otp_input" class="form-control text-center"
                        placeholder="<?= labels('enter_otp', 'Enter OTP') ?>" maxlength="6" inputmode="numeric"
                        autocomplete="off">
                    <div class="invalid-feedback" id="otp_error"><?= labels('otp_required', 'OTP is required') ?></div>
                <?php else: ?>
                    <p class="mb-0">
                        <?= labels('confirm_complete_booking', 'Are you sure you want to mark this booking as completed?') ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-success submit_btn" id="confirm_complete_btn"
                    data-order-id="<?= $booking['id'] ?>"
                    data-otp-required="<?= $is_otp_enable ?>"><?= labels('complete', 'Complete') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Additional Charge Payment Pending Modal -->
<div class="modal fade" id="additionalChargePendingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('additional_charge_payment_pending_title', 'Payment Pending') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    <?= labels('additional_charge_payment_pending_warning', "Customer hasn't selected a payment method and paid the additional charges yet. Please wait until the payment is completed before finishing this booking.") ?>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal"><?= labels('close', 'Close') ?></button>
                <button type="button" class="btn btn-primary"
                    data-bs-dismiss="modal"><?= labels('ok', 'Okay') ?></button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
    // Named setup fn re-run on every turbo:load (see handyman/pages/profile.php for the same
    // pattern) — under Turbo's morph renderer this inline script's closures (orderId, updateUrl,
    // etc.) and event bindings go stale on soft navigation between booking_details visits unless
    // re-initialized. All bindings below are namespaced + off-before-on so repeat calls never
    // stack duplicate handlers (which previously caused .btn-update-status — including the
    // on_the_way geolocation capture — to misfire or duplicate after Turbo integration).
    function bookingDetailsHandymanSetup() {
        if (!document.getElementById('stepper_card_body')) return;

        var updateUrl = '<?= base_url('handyman/bookings/update_status') ?>';
        var orderId = <?= (int) $booking['id'] ?>;
        var otpEnabled = <?= $is_otp_enable === '1' ? 'true' : 'false' ?>;
        var additionalChargePaymentPending = <?= $isAdditionalChargePaymentPending ? 'true' : 'false' ?>;

        var IS_AT_STORE = <?= $isAtStore ? 'true' : 'false' ?>;
        var STEPS_DOORSTEP = ['assigned', 'accepted', 'on_the_way', 'arrived', 'started', 'booking_ended', 'completed'];
        var STEPS_AT_STORE = ['assigned', 'accepted', 'started', 'booking_ended', 'completed'];
        var STEPS = IS_AT_STORE ? STEPS_AT_STORE : STEPS_DOORSTEP;
        var STEP_LABELS = {
            assigned: '<?= esc(labels('assigned', 'Assigned'), 'js') ?>',
            accepted: '<?= esc(labels('accepted', 'Accepted'), 'js') ?>',
            rejected: '<?= esc(labels('rejected', 'Rejected'), 'js') ?>',
            on_the_way: '<?= esc(labels('on_the_way', 'On The Way'), 'js') ?>',
            arrived: '<?= esc(labels('arrived', 'Arrived'), 'js') ?>',
            started: '<?= esc(labels('started', 'Started'), 'js') ?>',
            booking_ended: '<?= esc(labels('booking_ended', 'Booking Ended'), 'js') ?>',
            completed: '<?= esc(labels('completed', 'Completed'), 'js') ?>'
        };
        var STEP_ICONS = {
            assigned: 'assignment_ind', accepted: 'check_circle', on_the_way: 'directions_car',
            arrived: 'location_on', started: 'construction', booking_ended: 'handshake', completed: 'task_alt'
        };
        var BTN_CLASSES = {
            accepted: 'btn-success', rejected: 'btn-danger', on_the_way: 'btn-info',
            arrived: 'btn-warning', started: 'btn-warning', booking_ended: 'btn-primary', completed: 'btn-success'
        };
        var BTN_LABELS = {
            accepted: '<?= esc(labels('accept', 'Accept'), 'js') ?>',
            rejected: '<?= esc(labels('reject', 'Reject'), 'js') ?>',
            on_the_way: '<?= esc(labels('on_the_way', 'On The Way'), 'js') ?>',
            arrived: '<?= esc(labels('arrived', 'Arrived'), 'js') ?>',
            started: '<?= esc(labels('start', 'Start'), 'js') ?>',
            booking_ended: '<?= esc(labels('end_booking', 'End Booking'), 'js') ?>',
            completed: '<?= esc(labels('complete_booking', 'Complete Booking'), 'js') ?>'
        };
        var ORDER_STATUS_COLORS = {
            completed: 'success', cancelled: 'danger', declined: 'danger',
            active: 'warning', on_the_way: 'info', arrived: 'info',
            started: 'warning', booking_ended: 'warning', pending: 'secondary'
        };
        var LBL_PAYMENT_PENDING = '<?= esc(payment_block_message($booking['payment_status'] ?? null), 'js') ?>';
        var LBL_LIVE_TRACK = '<?= esc(labels('live_track_route', 'Live Track Route'), 'js') ?>';
        var LBL_TRACKING_ACTIVE = '<?= esc(labels('live_tracking_active', 'Route tracking is active'), 'js') ?>';
        var LBL_WORK_PROOF = '<?= esc(labels('work_started_proof', 'Work Started Proof'), 'js') ?>';
        var LBL_ASSIGNED_HANDYMEN = '<?= esc(labels('assigned_handymen', 'Assigned Handymen'), 'js') ?>';
        var LBL_LEAD = '<?= esc(labels('lead', 'Lead'), 'js') ?>';
        var LBL_WORK_COMPLETED_PROOF = '<?= esc(labels('work_completed_proof', 'Work Completed Proof'), 'js') ?>';

        // ── Helpers ───────────────────────────────────────────────────────

        function escHtml(str) {
            return $('<div>').text(str || '').html();
        }

        function ucWords(str) {
            return str.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        }

        // ── Render: stepper ───────────────────────────────────────────────

        function renderStepper(d) {
            var steps = ('is_at_store' in d) ? (d.is_at_store ? STEPS_AT_STORE : STEPS_DOORSTEP) : STEPS;
            var $body = $('#stepper_card_body');
            if (d.handyman_status === 'rejected') {
                var reasonHtml = d.rejected_reason
                    ? '<div class="text-muted small mt-1">' + escHtml(d.rejected_reason) + '</div>'
                    : '';
                $body.html(
                    '<div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:#fff1f0;border:1px solid #ffccc7;">'
                    + '<span class="material-symbols-outlined text-danger" style="font-size:28px;">cancel</span>'
                    + '<div><div class="fw-bold text-danger">' + STEP_LABELS.rejected + '</div>' + reasonHtml + '</div>'
                    + '</div>'
                );
            } else {
                var currentIdx = steps.indexOf(d.handyman_status);
                var html = '<div class="bh-hstepper">';
                for (var i = 0; i < steps.length; i++) {
                    var step = steps[i];
                    var isDone = currentIdx !== -1 && i < currentIdx;
                    var isCurr = currentIdx !== -1 && i === currentIdx;
                    var state = isDone ? 'done' : (isCurr ? 'current' : 'pending');
                    var icon = isDone ? 'check' : (STEP_ICONS[step] || 'circle');
                    html += '<div class="bh-hs-step ' + state + '">'
                        + '<div class="bh-hs-dot"><span class="material-symbols-outlined">' + icon + '</span></div>'
                        + '<div class="bh-hs-label ' + state + '">' + escHtml(STEP_LABELS[step] || step) + '</div>'
                        + '</div>';
                }
                html += '</div>';
                $body.html(html);
            }
        }

        // ── Render: action footer ─────────────────────────────────────────

        function renderActionFooter(d) {
            if ('additional_charge_payment_pending' in d) {
                additionalChargePaymentPending = !!d.additional_charge_payment_pending;
            }
            var $wrap = $('#status_card_footer_wrap');
            if (d.is_payment_pending && d.handyman_status !== 'rejected' && d.handyman_status !== 'completed') {
                $wrap.html(
                    '<div class="card-footer bg-white border-top px-4 py-3">'
                    + '<div class="d-flex align-items-center gap-2 p-2 rounded-3" style="background:#fff8e1;border:1px solid #ffe082;">'
                    + '<span class="material-symbols-outlined text-warning" style="font-size:20px;">warning</span>'
                    + '<div class="small text-muted">' + escHtml(d.payment_pending_message || LBL_PAYMENT_PENDING) + '</div>'
                    + '</div></div>'
                );
            } else if (d.is_lead && d.next_statuses && d.next_statuses.length > 0) {
                var btns = '';
                for (var i = 0; i < d.next_statuses.length; i++) {
                    var ns = d.next_statuses[i];
                    var cls = BTN_CLASSES[ns] || 'btn-secondary';
                    var lbl = BTN_LABELS[ns] || ucWords(ns);
                    if (ns === 'rejected') {
                        btns += '<button type="button" class="btn btn-sm ' + cls + '" data-bs-toggle="modal" data-bs-target="#rejectReasonModal">' + lbl + '</button>';
                    } else if (ns === 'started') {
                        btns += '<button type="button" class="btn btn-sm ' + cls + '" data-bs-toggle="modal" data-bs-target="#startedModal">' + lbl + '</button>';
                    } else if (ns === 'booking_ended') {
                        btns += '<button type="button" class="btn btn-sm ' + cls + '" data-bs-toggle="modal" data-bs-target="#bookingEndedModal">' + lbl + '</button>';
                    } else if (ns === 'completed') {
                        btns += '<button type="button" class="btn btn-sm ' + cls + ' btn-complete-check">' + lbl + '</button>';
                    } else {
                        btns += '<button type="button" class="btn btn-sm ' + cls + ' btn-update-status" data-status="' + ns + '" data-order-id="' + orderId + '">' + lbl + '</button>';
                    }
                }
                $wrap.html('<div class="card-footer bg-white border-top px-4 py-3"><div class="d-grid gap-2">' + btns + '</div></div>');
            } else {
                $wrap.html('');
            }
        }

        // ── Render: order status badge ────────────────────────────────────

        function renderOrderStatusBadge(orderStatus) {
            var color = ORDER_STATUS_COLORS[orderStatus] || 'secondary';
            $('#order_status_badge_wrap').html(
                '<span class="badge bg-' + color + ' px-3 py-2" style="font-size:14px;">' + escHtml(ucWords(orderStatus)) + '</span>'
            );
        }

        // ── Render: live track card ───────────────────────────────────────

        function renderLiveTrackCard(handymanStatus) {
            var $wrap = $('#live_track_btn_wrap');
            if (handymanStatus === 'on_the_way') {
                $wrap.html(
                    '<a href="<?= base_url('handyman/bookings/live_tracking/' . (int) $booking['id']) ?>" class="btn btn-sm btn-outline-info" id="btn_live_track">'
                    + '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:-4px;">directions</span>'
                    + ' ' + escHtml(LBL_LIVE_TRACK)
                    + '</a>'
                );
            } else {
                $wrap.html('');
            }
        }

        // ── Render: assigned handymen ─────────────────────────────────────

        function renderAssignedHandymen(handymen) {
            var $wrap = $('#assigned_handymen_wrap');
            if (!handymen || handymen.length === 0) { $wrap.html(''); return; }
            var items = '';
            for (var i = 0; i < handymen.length; i++) {
                var h = handymen[i];
                var leadBadge = h.is_lead ? '<span class="badge bg-primary">' + escHtml(LBL_LEAD) + '</span>' : '';
                items += '<li class="list-group-item px-4 py-2 d-flex align-items-center gap-3">'
                    + '<img src="' + escHtml(h.image) + '" alt="" width="36" height="36" class="rounded-circle object-fit-cover border flex-shrink-0">'
                    + '<div class="flex-grow-1 min-width-0">'
                    + '<div class="fw-semibold text-truncate">' + escHtml(h.username) + '</div>'
                    + '<div class="text-muted small">' + escHtml(ucWords(h.handyman_status)) + '</div>'
                    + '</div>' + leadBadge + '</li>';
            }
            $wrap.html(
                '<div class="card shadow-sm border-0 mb-4">'
                + '<div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">'
                + '<span class="material-symbols-outlined me-2 text-primary">group</span>'
                + '<h6 class="mb-0 fw-bold">' + escHtml(LBL_ASSIGNED_HANDYMEN) + '</h6>'
                + '</div>'
                + '<ul class="list-group list-group-flush">' + items + '</ul>'
                + '</div>'
            );
        }

        // ── Render: work started proof ────────────────────────────────────

        function renderWorkStartedProof(proofFiles) {
            var $wrap = $('#work_started_proof_wrap');
            if (!proofFiles || proofFiles.length === 0) { $wrap.html(''); return; }
            var videoExts = ['mov', 'mp4', 'm3u8', 'ts', '3gp', 'avi', 'wmv'];
            var items = '';
            for (var i = 0; i < proofFiles.length; i++) {
                var f = proofFiles[i];
                var ext = f.split('.').pop().toLowerCase();
                if (videoExts.indexOf(ext) !== -1) {
                    items += '<div class="col-3"><video controls class="rounded border w-100" style="height:120px;object-fit:cover;"><source src="' + escHtml(f) + '" type="video/mp4"></video></div>';
                } else {
                    items += '<div class="col-3"><a href="' + escHtml(f) + '" target="_blank"><img src="' + escHtml(f) + '" alt="" class="rounded border w-100" style="height:120px;object-fit:cover;"></a></div>';
                }
            }
            $wrap.html(
                '<div class="card shadow-sm border-0 mb-4">'
                + '<div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">'
                + '<span class="material-symbols-outlined me-2 text-primary">construction</span>'
                + '<h6 class="mb-0 fw-bold">' + escHtml(LBL_WORK_PROOF) + '</h6>'
                + '</div>'
                + '<div class="card-body px-4 py-3"><div class="row g-2">' + items + '</div></div>'
                + '</div>'
            );
        }

        // ── Render: work completed proof ──────────────────────────────────

        function renderWorkCompletedProof(proofFiles) {
            var $wrap = $('#work_completed_proof_wrap');
            if (!proofFiles || proofFiles.length === 0) { $wrap.html(''); return; }
            var videoExts = ['mov', 'mp4', 'm3u8', 'ts', '3gp', 'avi', 'wmv'];
            var items = '';
            for (var i = 0; i < proofFiles.length; i++) {
                var f = proofFiles[i];
                var ext = f.split('.').pop().toLowerCase();
                if (videoExts.indexOf(ext) !== -1) {
                    items += '<div class="col-3"><video controls class="rounded border w-100" style="height:120px;object-fit:cover;"><source src="' + escHtml(f) + '" type="video/mp4"></video></div>';
                } else {
                    items += '<div class="col-3"><a href="' + escHtml(f) + '" target="_blank"><img src="' + escHtml(f) + '" alt="" class="rounded border w-100" style="height:120px;object-fit:cover;"></a></div>';
                }
            }
            $wrap.html(
                '<div class="card shadow-sm border-0 mb-4">'
                + '<div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center">'
                + '<span class="material-symbols-outlined me-2 text-success">task_alt</span>'
                + '<h6 class="mb-0 fw-bold">' + escHtml(LBL_WORK_COMPLETED_PROOF) + '</h6>'
                + '</div>'
                + '<div class="card-body px-4 py-3"><div class="row g-2">' + items + '</div></div>'
                + '</div>'
            );
        }

        // ── Refresh after a status change ───────────────────────────────────
        // No Turbo on this page — every status change (stepper, footer buttons,
        // payment summary, additional-charge pending state) just does a plain
        // full page reload so every section is guaranteed to reflect the
        // server's current state, no partial-refresh drift possible.
        function refreshFullPage() {
            setTimeout(function () { location.reload(); }, 2000);
        }

        // ── Event handlers ────────────────────────────────────────────────

        // Direct status buttons (no modal)
        $(document).off('click.bhBooking', '.btn-update-status').on('click.bhBooking', '.btn-update-status', function () {
            var $btn = $(this);
            var status = $btn.data('status');
            var oid = $btn.data('order-id');

            if (status === 'on_the_way' && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        doStatusUpdate({ order_id: oid, status: status, latitude: pos.coords.latitude, longitude: pos.coords.longitude }, $btn);
                    },
                    function () {
                        doStatusUpdate({ order_id: oid, status: status }, $btn);
                    },
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                );
                return;
            }

            doStatusUpdate({ order_id: oid, status: status }, $btn);
        });

        // Complete button — gate the verify-OTP modal behind the additional-charge payment check
        $(document).off('click.bhBooking', '.btn-complete-check').on('click.bhBooking', '.btn-complete-check', function () {
            if (additionalChargePaymentPending) {
                $('#additionalChargePendingModal').modal('show');
            } else {
                $('#completeModal').modal('show');
            }
        });

        // FilePond proof inputs — captured before FilePond replaces the elements
        var startedProofInput = document.getElementById('started_proof_files');
        var bookingEndedProofInput = document.getElementById('booking_ended_proof_files');

        $('#startedModal').off('hidden.bs.modal.bhBooking').on('hidden.bs.modal.bhBooking', function () {
            if (typeof FilePond !== 'undefined' && startedProofInput) {
                var pond = FilePond.find(startedProofInput);
                if (pond) { pond.removeFiles(); }
            }
        });

        $('#bookingEndedModal').off('hidden.bs.modal.bhBooking').on('hidden.bs.modal.bhBooking', function () {
            if (typeof FilePond !== 'undefined' && bookingEndedProofInput) {
                var pond = FilePond.find(bookingEndedProofInput);
                if (pond) { pond.removeFiles(); }
            }
        });

        $('#confirm_started_btn').off('click.bhBooking').on('click.bhBooking', function () {
            var oid = $(this).data('order-id');
            var formData = new FormData();
            formData.append('order_id', oid);
            formData.append('status', 'started');
            if (typeof FilePond !== 'undefined' && startedProofInput) {
                var pond = FilePond.find(startedProofInput);
                if (pond) {
                    var pondFiles = pond.getFiles();
                    for (var i = 0; i < pondFiles.length; i++) {
                        formData.append('work_started_files[]', pondFiles[i].file);
                    }
                }
            }
            $('#startedModal').modal('hide');
            formAjaxRequest('POST', updateUrl, formData, null, $(this),
                function () { refreshFullPage(); },
                null
            );
        });

        // Reject
        $('#confirm_reject_btn').off('click.bhBooking').on('click.bhBooking', function () {
            var reason = $('#reject_reason_input').val().trim();
            if (!reason) { $('#reject_reason_input').addClass('is-invalid'); return; }
            var oid = $(this).data('order-id');
            $('#rejectReasonModal').modal('hide');
            doStatusUpdate({ order_id: oid, status: 'rejected', rejected_reason: reason }, $(this));
        });
        $('#rejectReasonModal').off('show.bs.modal.bhBooking').on('show.bs.modal.bhBooking', function () {
            $('#reject_reason_input').val('').removeClass('is-invalid');
        });

        // Booking Ended — optional additional charges
        $('#additional_charges_container').off('click.bhBooking', '.btn-add-charge').on('click.bhBooking', '.btn-add-charge', function () {
            var row = $(this).closest('.additional-charge-row').clone();
            row.find('input').val('');
            row.find('.btn-add-charge').removeClass('btn-add-charge').addClass('btn-remove-charge').text('-');
            $('#additional_charges_container').append(row);
        });
        $('#additional_charges_container').off('click.bhBooking', '.btn-remove-charge').on('click.bhBooking', '.btn-remove-charge', function () {
            $(this).closest('.additional-charge-row').remove();
        });

        $('#confirm_booking_ended_btn').off('click.bhBooking').on('click.bhBooking', function () {
            var $btn = $(this);
            var oid = $btn.data('order-id');
            var charges = [];
            $('#additional_charges_container .additional-charge-row').each(function () {
                var name = $(this).find('.charge-name').val().trim();
                var amount = $(this).find('.charge-amount').val().trim();
                if (name && amount) { charges.push({ name: name, charge: amount }); }
            });
            var formData = new FormData();
            formData.append('order_id', oid);
            formData.append('status', 'booking_ended');
            $.each(charges, function (i, c) {
                formData.append('booking_ended_additional_charges[' + i + '][name]', c.name);
                formData.append('booking_ended_additional_charges[' + i + '][charge]', c.charge);
            });
            if (typeof FilePond !== 'undefined' && bookingEndedProofInput) {
                var pond = FilePond.find(bookingEndedProofInput);
                if (pond) {
                    pond.getFiles().forEach(function (f) {
                        formData.append('work_complete_files[]', f.file);
                    });
                }
            }
            $('#bookingEndedModal').modal('hide');
            formAjaxRequest('POST', updateUrl, formData, null, $btn,
                function () { refreshFullPage(); },
                null
            );
        });

        // Complete — OTP if enabled
        $('#confirm_complete_btn').off('click.bhBooking').on('click.bhBooking', function () {
            var oid = $(this).data('order-id');
            var payload = { order_id: oid, status: 'completed' };
            if (otpEnabled) {
                var otp = $('#completion_otp_input').val().trim();
                if (!otp) { $('#completion_otp_input').addClass('is-invalid'); return; }
                payload.otp = otp;
            }
            $('#completeModal').modal('hide');
            formAjaxRequest('POST', updateUrl, (function () {
                var fd = new FormData();
                $.each(payload, function (key, val) { fd.append(key, val); });
                return fd;
            })(), null, $(this),
                function () { refreshFullPage(); },
                null
            );
        });
        $('#completeModal').off('show.bs.modal.bhBooking').on('show.bs.modal.bhBooking', function () {
            $('#completion_otp_input').val('').removeClass('is-invalid');
        });

        function doStatusUpdate(payload, $btn) {
            var formData = new FormData();
            $.each(payload, function (key, val) { formData.append(key, val); });
            formAjaxRequest('POST', updateUrl, formData, null, $btn,
                function () { refreshFullPage(); },
                null
            );
        }
    }

    document.addEventListener('turbo:load', bookingDetailsHandymanSetup);
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.Turbo === 'undefined') {
            bookingDetailsHandymanSetup();
        }
    });
</script>
<?= $this->endSection() ?>