<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('live_tracking', 'Live Tracking') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('partner/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('partner/orders') ?>">
                        <i class="fas fa-list-alt text-primary"></i> <?= labels('bookings', 'Bookings') ?>
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
                            </h6>
                            <span class="text-muted" style="font-size:12px;">
                                <i class="fas fa-sync-alt mr-1"></i>
                                <?= labels('auto_refresh', 'Auto-refreshes every 30 seconds') ?>
                            </span>
                        </div>

                        <div id="live_map_message" class="card-body text-center py-5">
                            <i class="fas fa-satellite-dish fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">
                                <?= labels('no_handymen_on_the_way', 'No handymen are currently on the way to a service.') ?>
                            </p>
                        </div>
                        <div id="live_map" style="width:100%;height:600px;display:none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    window.initPageMap = function () {
        LiveHandymanMap.init({
            containerId: 'live_map',
            messageId: 'live_map_message',
            dataUrl: '<?= base_url('partner/orders/live_map_data') ?>',
            handymanIconUrl: '<?= base_url('public/uploads/site/handyman.svg') ?>',
            viewDetailsBaseUrl: '<?= base_url('partner/handymen/view/') ?>',
            popupLabels: {
                handyman: '<?= esc(labels('handyman', 'Handyman')) ?>',
                orderId: '<?= esc(labels('order_id', 'Order Id')) ?>',
                lastUpdated: '<?= esc(labels('last_updated', 'Last updated')) ?>',
                viewDetails: '<?= esc(labels('view_details', 'View Details')) ?>'
            }
        });
    };
</script>
