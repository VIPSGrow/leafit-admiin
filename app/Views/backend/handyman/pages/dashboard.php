<?= $this->extend('backend/handyman/layouts/main') ?>

<?= $this->section('content') ?>

<div class="section-body">

    <!-- Top stat cards -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center me-3"
                        style="width: 56px; height: 56px; flex-shrink: 0;">
                        <span class="material-symbols-outlined text-primary" style="font-size: 28px;">payments</span>
                    </div>
                    <div>
                        <div class="text-muted small"><?= labels('salary', 'Salary') ?></div>
                        <div class="fs-4 fw-bold">
                            <?= esc($currency) ?> <?= number_format((float) ($salary ?? 0), 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-info-subtle d-flex align-items-center justify-content-center me-3"
                        style="width: 56px; height: 56px; flex-shrink: 0;">
                        <span class="material-symbols-outlined text-info" style="font-size: 28px;">checklist</span>
                    </div>
                    <div>
                        <div class="text-muted small"><?= labels('total_bookings', 'Total Bookings') ?></div>
                        <div class="fs-4 fw-bold"><?= (int) ($summary['total_bookings'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center me-3"
                        style="width: 56px; height: 56px; flex-shrink: 0;">
                        <span class="material-symbols-outlined text-warning" style="font-size: 28px;">star</span>
                    </div>
                    <div>
                        <div class="text-muted small"><?= labels('lead_bookings', 'Lead Bookings') ?></div>
                        <div class="fs-4 fw-bold"><?= (int) ($summary['lead_bookings'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center me-3"
                        style="width: 56px; height: 56px; flex-shrink: 0;">
                        <span class="material-symbols-outlined text-success" style="font-size: 28px;">reviews</span>
                    </div>
                    <div>
                        <div class="text-muted small"><?= labels('average_rating', 'Average Rating') ?></div>
                        <div class="fs-4 fw-bold">
                            <?= number_format((float) ($average_rating ?? 0), 1) ?>
                            <span class="text-muted fs-6 fw-normal">
                                / 5 (<?= (int) ($total_ratings ?? 0) ?> <?= labels('ratings', 'ratings') ?>)
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <!-- Bookings schedule breakdown -->
        <?php
        $todayBookingsCount = (int) ($summary['today_bookings'] ?? 0);
        $tomorrowBookingsCount = (int) ($summary['tomorrow_bookings'] ?? 0);
        $upcomingBookingsCount = (int) ($summary['upcoming_bookings'] ?? 0);
        $scheduleTotal = $todayBookingsCount + $tomorrowBookingsCount + $upcomingBookingsCount;
        ?>
        <div class="col-xl-7 col-12 mb-3 mb-xl-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom p-3">
                    <h6 class="mb-0"><?= labels('bookings_schedule', 'Bookings Schedule') ?></h6>
                </div>
                <div class="card-body p-4 d-flex align-items-center justify-content-center">
                    <div class="row w-100 align-items-center">
                        <div class="col-4 col-md-3 text-center border-end">
                            <h1 class="display-3 fw-bold text-dark mb-0 lh-1"><?= $scheduleTotal ?></h1>
                            <span class="text-muted small text-uppercase fw-semibold"
                                style="letter-spacing: 1px;"><?= labels('total', 'Total') ?></span>
                        </div>
                        <div class="col-8 col-md-9 ps-4 ps-md-5">
                            <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                                <li class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <span class="p-1 rounded-circle bg-primary me-3"></span>
                                        <span class="text-secondary fw-medium"><?= labels('today', 'Today') ?></span>
                                    </div>
                                    <span class="fw-bold text-dark fs-5"><?= $todayBookingsCount ?></span>
                                </li>
                                <li class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <span class="p-1 rounded-circle me-3"
                                            style="background-color: #fd7e14 !important;"></span>
                                        <span
                                            class="text-secondary fw-medium"><?= labels('tomorrow', 'Tomorrow') ?></span>
                                    </div>
                                    <span class="fw-bold text-dark fs-5"><?= $tomorrowBookingsCount ?></span>
                                </li>
                                <li class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <span class="p-1 rounded-circle me-3"
                                            style="background-color: #6f42c1 !important;"></span>
                                        <span
                                            class="text-secondary fw-medium"><?= labels('upcoming', 'Upcoming') ?></span>
                                    </div>
                                    <span class="fw-bold text-dark fs-5"><?= $upcomingBookingsCount ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash collection -->
        <div class="col-xl-5 col-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><?= labels('cash_collection', 'Cash Collection') ?></h6>
                    <a href="<?= base_url('handyman/cash-collection') ?>" class="small text-decoration-none">
                        <?= labels('view_all', 'View All') ?> <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center me-3"
                            style="width: 48px; height: 48px; flex-shrink: 0;">
                            <span class="material-symbols-outlined text-primary"
                                style="font-size: 24px;">account_balance_wallet</span>
                        </div>
                        <div>
                            <div class="text-muted small">
                                <?= labels('overall_cash_collected', 'Overall Cash Collected') ?>
                            </div>
                            <div class="fs-5 fw-bold"><?= esc($currency) ?>
                                <?= number_format((float) $overall_cash_total, 2) ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center me-3"
                            style="width: 48px; height: 48px; flex-shrink: 0;">
                            <span class="material-symbols-outlined text-warning"
                                style="font-size: 24px;">hourglass_top</span>
                        </div>
                        <div>
                            <div class="text-muted small">
                                <?= labels('outstanding_pending_handover', 'Outstanding — Pending Handover') ?>
                            </div>
                            <div class="fs-5 fw-bold"><?= esc($currency) ?>
                                <?= number_format((float) $outstanding_cash_total, 2) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent completed bookings -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><?= labels('recent_completed_bookings', 'Recent Completed Bookings') ?></h6>
                    <a href="<?= base_url('handyman/bookings') ?>" class="small text-decoration-none">
                        <?= labels('view_all', 'View All') ?> <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_completed_bookings)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th><?= labels('order_id', 'Order ID') ?></th>
                                        <th><?= labels('customer', 'Customer') ?></th>
                                        <th><?= labels('date_of_service', 'Service Date') ?></th>
                                        <th><?= labels('time', 'Time') ?></th>
                                        <th><?= labels('address', 'Address') ?></th>
                                        <th><?= labels('amount', 'Amount') ?> (<?= esc($currency) ?>)</th>
                                        <th class="text-center"><?= labels('operations', 'Operations') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_completed_bookings as $booking): ?>
                                        <tr>
                                            <td>#<?= (int) $booking['order_id'] ?></td>
                                            <td><?= esc($booking['customer_name'] ?? '-') ?></td>
                                            <td><?= esc($booking['date_of_service']) ?></td>
                                            <td><?= esc($booking['time_range']) ?></td>
                                            <td class="text-truncate" style="max-width: 260px;">
                                                <?= esc($booking['address'] ?? '-') ?>
                                            </td>
                                            <td><?= number_format($booking['final_total'], 2) ?></td>
                                            <td class="text-center">
                                                <a href="<?= base_url('handyman/bookings/' . (int) $booking['order_id']) ?>"
                                                    class="btn btn-sm btn-light border"
                                                    title="<?= labels('view_details', 'View Details') ?>">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <span class="material-symbols-outlined d-block mb-2" style="font-size: 40px;">inbox</span>
                            <?= labels('no_completed_bookings', 'No completed bookings yet') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?= $this->endSection() ?>