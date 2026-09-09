<?php
$orderLat  = !empty($order['order_latitude'])    ? (float) $order['order_latitude']    : null;
$orderLng  = !empty($order['order_longitude'])   ? (float) $order['order_longitude']   : null;
$hmLat     = !empty($live_tracking['latitude'])  ? (float) $live_tracking['latitude']  : null;
$hmLng     = !empty($live_tracking['longitude']) ? (float) $live_tracking['longitude'] : null;
?>

<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('live_tracking', 'Live Tracking') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('admin/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('admin/orders') ?>">
                        <i class="fas fa-list-alt text-primary"></i> <?= labels('bookings', 'Bookings') ?>
                    </a>
                </div>
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('admin/orders/veiw_orders/' . (int) $order['id']) ?>">
                        <i class="fas fa-receipt text-primary"></i> <?= labels('booking_details', 'Booking Details') ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= labels('live_tracking', 'Live Tracking') ?></div>
            </div>
        </div>

        <div class="section-body">
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm" style="border-radius: 0.75rem; overflow: hidden;">
                        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fas fa-map-marked-alt text-info mr-2"></i>
                                <?= labels('live_tracking', 'Live Tracking') ?>
                                <?php if (!empty($order['invoice_no'])): ?>
                                    &mdash; <span class="badge badge-primary"><?= esc($order['invoice_no']) ?></span>
                                <?php endif; ?>
                            </h6>
                            <a href="<?= base_url('admin/orders/veiw_orders/' . (int) $order['id']) ?>"
                                class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-1"></i><?= labels('back', 'Back') ?>
                            </a>
                        </div>

                        <?php if ($orderLat === null || $orderLng === null): ?>
                            <div class="card-body text-center py-5">
                                <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted"><?= labels('order_location_not_available', 'Order location is not available.') ?></p>
                            </div>
                        <?php elseif ($hmLat === null || $hmLng === null): ?>
                            <div class="card-body text-center py-5">
                                <i class="fas fa-satellite-dish fa-3x text-muted mb-3"></i>
                                <p class="text-muted">
                                    <?php if (($tracker_type ?? 'handyman') === 'partner'): ?>
                                        <?= labels('provider_location_not_available', 'Provider location is not yet available. Tracking begins when provider is on the way.') ?>
                                    <?php else: ?>
                                        <?= labels('handyman_location_not_available', 'Handyman location is not yet available. Tracking begins when handyman is on the way.') ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Legend -->
                            <div class="card-body border-bottom py-2 d-flex align-items-center flex-wrap" style="gap: 1rem;">
                                <span class="d-flex align-items-center">
                                    <img src="<?= base_url('public/uploads/site/customer_pin.svg') ?>" alt="" style="width:16px;height:16px;margin-right:6px;">
                                    <span class="text-muted" style="font-size:13px;"><?= labels('service_location', 'Service Location') ?></span>
                                </span>
                                <span class="d-flex align-items-center">
                                    <img src="<?= base_url('public/uploads/site/handyman.svg') ?>" alt="" style="width:16px;height:16px;margin-right:6px;">
                                    <span class="text-muted" style="font-size:13px;">
                                        <?php if (($tracker_type ?? 'handyman') === 'partner'): ?>
                                            <?= esc($handyman_name) ?> (<?= labels('provider', 'Provider') ?>)
                                        <?php else: ?>
                                            <?= esc($handyman_name) ?> (<?= labels('handyman', 'Handyman') ?>)
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <span class="ml-auto text-muted" style="font-size:12px;">
                                    <i class="fas fa-sync-alt mr-1"></i>
                                    <?= labels('auto_refresh', 'Auto-refreshes every 30 seconds') ?>
                                </span>
                            </div>
                            <div id="live-tracking-map" style="width:100%;height:520px;"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php if ($orderLat !== null && $orderLng !== null && $hmLat !== null && $hmLng !== null): ?>
<script>
    window.initPageMap = function () {
        LiveTrackingMap.init({
            containerId: 'live-tracking-map',
            pollUrl: '<?= base_url('admin/orders/tracker_location/' . (int) $order['id']) ?>',
            orderPos: { lat: <?= $orderLat ?>, lng: <?= $orderLng ?> },
            handymanPos: { lat: <?= $hmLat ?>, lng: <?= $hmLng ?> },
            orderTitle: '<?= esc(addslashes($order['address'] ?? labels('service_location', 'Service Location'))) ?>',
            handymanTitle: '<?= esc(addslashes($handyman_name)) ?>',
            customerIconUrl: '<?= base_url('public/uploads/site/customer_pin.svg') ?>',
            handymanIconUrl: '<?= base_url('public/uploads/site/handyman.svg') ?>',
            endedMessage: '<?= esc(labels('live_tracking_ended', 'Handyman has arrived. Live tracking stopped.')) ?>'
        });
    };
</script>
<?php endif; ?>
