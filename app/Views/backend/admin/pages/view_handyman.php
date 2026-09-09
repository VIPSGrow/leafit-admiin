<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('view_handyman', 'View Handyman') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('admin/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('admin/handymen') ?>">
                        <i class="fas fa-user-cog text-primary"></i> <?= labels('handymen', 'Handymen') ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= esc($handyman['username']) ?></div>
            </div>
        </div>

        <div class="container-fluid card">
            <div class="row p-3 align-items-center">
                <div class="col-auto">
                    <?php if (!empty($handyman['image'])): ?>
                        <img src="<?= esc($handyman['image']) ?>" class="rounded-circle" width="70" height="70"
                            style="object-fit:cover;">
                    <?php else: ?>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white"
                            style="width:70px;height:70px;font-size:28px;font-weight:bold;">
                            <?= strtoupper(substr($handyman['username'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col">
                    <h4 class="mb-1"><?= esc($handyman['username']) ?></h4>
                    <div class="text-muted small">
                        <?php if (!empty($handyman['phone'])): ?><span class="mr-3"><i
                                    class="fas fa-phone mr-1"></i><?= esc($handyman['phone']) ?></span><?php endif; ?>
                        <?php if (!empty($handyman['email'])): ?><span class="mr-3"><i
                                    class="fas fa-envelope mr-1"></i><?= esc($handyman['email']) ?></span><?php endif; ?>
                        <?php if (!empty($handyman['address'])): ?><span class="mr-3"><i
                                    class="fas fa-map-marker-alt mr-1"></i><?= esc($handyman['address']) ?></span><?php endif; ?>
                        <?php if (!empty($handyman['provider_name'])): ?><span><i
                                    class="fas fa-store mr-1"></i><?= esc($handyman['provider_name']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="col-auto mt-3 mt-md-0">
                    <?= $handyman['active'] === 1
                        ? '<span class="badge badge-success">' . labels('active', 'Active') . '</span>'
                        : '<span class="badge badge-danger">' . labels('inactive', 'Inactive') . '</span>' ?>
                    <!-- <= $handyman['is_available'] === 1
                        ? '<span class="badge badge-success ml-1">' . labels('available', 'Available') . '</span>'
                        : '<span class="badge badge-secondary ml-1">' . labels('unavailable', 'Unavailable') . '</span>' ?> -->
                </div>
            </div>

            <div class="card-header p-0">
                <ul class="nav nav-tabs card-header-tabs px-3" id="viewHandymanTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active text-body" id="tab-overview" data-toggle="tab" href="#pane-overview"
                            role="tab"><i
                                class="fas fa-chart-pie mr-2 text-white"></i><?= labels('overview', 'Overview') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-body" id="tab-bookings" data-toggle="tab" href="#pane-bookings"
                            role="tab"><i
                                class="fas fa-calendar-check mr-2"></i><?= labels('bookings', 'Bookings') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-body" id="tab-reviews" data-toggle="tab" href="#pane-reviews"
                            role="tab"><i class="fas fa-star mr-2"></i><?= labels('reviews', 'Reviews') ?></a>
                    </li>
                    <?php if (!empty($handyman_custom_fields)): ?>
                        <li class="nav-item">
                            <a class="nav-link text-body" id="tab-custom-fields" data-toggle="tab"
                                href="#pane-custom-fields" role="tab"><i
                                    class="fas fa-list-alt mr-2"></i><?= labels('custom_fields', 'Custom Fields') ?></a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="tab-content p-3">
                <!-- Overview -->
                <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
                    <div class="row mb-3">
                        <div class="col-md col-6 mb-3 mb-md-0">
                            <div class="card border shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted"><i
                                            class="fas fa-star text-warning mr-1"></i><?= labels('average_rating', 'Average Rating') ?>
                                    </div>
                                    <h4 class="mb-0 d-flex align-items-center" id="stat_rating_row"
                                        title="<?= labels('total_reviews_received', 'Total number of reviews received') ?>">
                                        <span id="stat_rating_stars" class="d-flex align-items-center"></span>
                                        <span id="stat_rating_value" class="ml-1">0.0</span>
                                        <span class="text-muted small ml-1" id="stat_rating_count">(0)</span>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md col-6 mb-3 mb-md-0">
                            <div class="card border shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted"><i
                                            class="fas fa-clipboard-list text-primary mr-1"></i><?= labels('total_bookings', 'Total Bookings') ?>
                                    </div>
                                    <h4 class="mb-0" id="stat_total_bookings">0</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md col-6 mb-3 mb-md-0">
                            <div class="card border shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted"><i
                                            class="fas fa-star text-warning mr-1"></i><?= labels('lead_bookings', 'Lead Bookings') ?>
                                    </div>
                                    <h4 class="mb-0" id="stat_lead_bookings">0</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md col-6 mb-3 mb-md-0">
                            <div class="card border shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted"><i
                                            class="fas fa-calendar-day text-info mr-1"></i><?= labels('today_bookings', "Today's Bookings") ?>
                                    </div>
                                    <h4 class="mb-0" id="stat_today_bookings">0</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md col-6 mb-3 mb-md-0">
                            <div class="card border shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted"><i
                                            class="fas fa-calendar-alt text-secondary mr-1"></i><?= labels('upcoming_bookings', 'Upcoming Bookings') ?>
                                    </div>
                                    <h4 class="mb-0" id="stat_upcoming_bookings">0</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md col-6">
                            <div class="card border shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted"><i
                                            class="fas fa-check-circle text-success mr-1"></i><?= labels('completed_bookings', 'Completed Bookings') ?>
                                    </div>
                                    <h4 class="mb-0" id="stat_completed_count">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border shadow-sm mb-3">
                        <div class="card-header bg-white border-bottom py-3">
                            <i
                                class="fas fa-check-circle text-success mr-2"></i><?= labels('recent_completed_bookings', 'Recent Completed Bookings') ?>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover" id="recent_completed_table" data-toggle="table"
                                    data-pagination="false" data-show-refresh="false" data-search="false"
                                    data-show-columns="false" data-show-export="false">
                                    <thead class="thead-light">
                                        <tr>
                                            <th data-field="order_id" class="text-center">
                                                <?= labels('order_id', 'Order Id') ?>
                                            </th>
                                            <th data-field="customer_name" class="text-center">
                                                <?= labels('customer', 'Customer') ?>
                                            </th>
                                            <th data-field="scheduled_display" class="text-center">
                                                <?= labels('scheduled_on', 'Scheduled On') ?>
                                            </th>
                                            <th data-field="amount_display" class="text-center">
                                                <?= labels('amount', 'Amount') ?>
                                            </th>
                                            <th data-field="operations" class="text-center">
                                                <?= labels('operations', 'Operations') ?>
                                            </th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card border shadow-sm" style="border-radius:0.75rem; overflow:hidden;">
                        <div class="card-header bg-white border-bottom py-3">
                            <i
                                class="fas fa-map-marked-alt text-info mr-2"></i><?= labels('handyman_location', 'Handyman Location') ?>
                        </div>
                        <div id="handyman_map_message" class="card-body text-center py-5">
                            <i class="fas fa-satellite-dish fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">
                                <?= labels('handyman_not_tracked', "Handyman isn't on an active booking right now, so live location isn't available.") ?>
                            </p>
                        </div>
                        <div id="handyman_map" style="width:100%;height:420px;display:none;"></div>
                    </div>
                </div>

                <!-- Bookings -->
                <div class="tab-pane fade" id="pane-bookings" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover" id="handyman_bookings_table" data-toggle="table"
                            data-side-pagination="server" data-pagination="true"
                            data-url="<?= base_url('admin/handymen/bookings-list/' . (int) $handyman['id']) ?>"
                            data-page-list="[10, 25, 50, 100]" data-pagination-successively-size="2"
                            data-show-refresh="false" data-search="false" data-show-columns="false"
                            data-show-export="false" data-sort-name="order_id" data-sort-order="desc">
                            <thead class="thead-light">
                                <tr>
                                    <th data-field="order_id" class="text-center"><?= labels('order_id', 'Order Id') ?>
                                    </th>
                                    <th data-field="customer_name" class="text-center">
                                        <?= labels('customer', 'Customer') ?>
                                    </th>
                                    <th data-field="status_badge" class="text-center"><?= labels('status', 'Status') ?>
                                    </th>
                                    <th data-field="service_type_badge" class="text-center">
                                        <?= labels('service_type', 'Service Type') ?>
                                    </th>
                                    <th data-field="scheduled_display" class="text-center">
                                        <?= labels('scheduled_on', 'Scheduled On') ?>
                                    </th>
                                    <th data-field="amount_display" class="text-center">
                                        <?= labels('amount', 'Amount') ?>
                                    </th>
                                    <th data-field="address_display" class="text-center">
                                        <?= labels('address', 'Address') ?>
                                    </th>
                                    <th data-field="operations" class="text-center">
                                        <?= labels('operations', 'Operations') ?>
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>

                <!-- Reviews -->
                <div class="tab-pane fade" id="pane-reviews" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover" id="handyman_reviews_table" data-toggle="table"
                            data-side-pagination="server" data-pagination="true"
                            data-url="<?= base_url('admin/handymen/reviews-list/' . (int) $handyman['id']) ?>"
                            data-page-list="[10, 25, 50, 100]" data-pagination-successively-size="2"
                            data-show-refresh="false" data-search="false" data-show-columns="false"
                            data-show-export="false" data-sort-name="hr.id" data-sort-order="desc">
                            <thead class="thead-light">
                                <tr>
                                    <th data-field="customer_cell"><?= labels('customer', 'Customer') ?></th>
                                    <th data-field="rating_stars" class="text-center" style="width:140px;">
                                        <?= labels('rating', 'Rating') ?>
                                    </th>
                                    <th data-field="review_text"><?= labels('review', 'Review') ?></th>
                                    <th data-field="images_cell" class="text-center" style="width:160px;">
                                        <?= labels('images', 'Images') ?>
                                    </th>
                                    <th data-field="rated_on" class="text-center" style="width:170px;">
                                        <?= labels('rated_on', 'Rated On') ?>
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>

                <?php if (!empty($handyman_custom_fields)): ?>
                    <!-- Custom Fields -->
                    <div class="tab-pane fade" id="pane-custom-fields" role="tabpanel">
                        <div class="row text-center">
                            <?php foreach ($handyman_custom_fields as $field): ?>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="h6 font-weight-bold mb-1"><?= esc($field['label']) ?></div>
                                            <div class="mt-1">
                                                <?php if ($field['value'] === null || $field['value'] === ''): ?>
                                                    &mdash;
                                                <?php elseif ($field['field_type'] === 'file'): ?>
                                                    <a href="<?= esc($field['value']) ?>" target="_blank" rel="noopener">
                                                        <img src="<?= esc($field['value']) ?>" alt="<?= esc($field['label']) ?>"
                                                            class="rounded border" height="80" style="object-fit:cover;">
                                                    </a>
                                                <?php else: ?>
                                                    <?= esc($field['value']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<!-- Review Images Modal -->
<div id="reviewImagesModal" class="modal fade" tabindex="-1" aria-labelledby="reviewImagesModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center w-100">
                <h5 class="modal-title m-0" id="reviewImagesModalLabel"><?= labels('images', 'Images') ?></h5>
                <button type="button" class="close m-0" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body d-flex flex-wrap gap-2" id="reviewImagesModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    var HANDYMAN_ID = <?= (int) $handyman['id'] ?>;
    var CURRENCY = <?= json_encode($currency) ?>;
    var handymanMapInitialised = false;
    var pendingMapData = null;
    var googleMapsReady = false;

    function renderRatingStars(average) {
        var rounded = Math.round(average);
        var stars = '';
        for (var i = 1; i <= 5; i++) {
            stars += '<i class="fa' + (i <= rounded ? 's' : 'r') + ' fa-star text-warning"></i>';
        }
        return stars;
    }

    function loadHandymanOverview() {
        ajaxRequest('GET', '<?= base_url('admin/handymen/overview/') ?>' + HANDYMAN_ID, null, null, function (res) {
            $('#stat_completed_count').text(res.completed_bookings_count);
            $('#stat_total_bookings').text(res.total_bookings);
            $('#stat_lead_bookings').text(res.lead_bookings);
            $('#stat_today_bookings').text(res.bookings.today_bookings);
            $('#stat_upcoming_bookings').text(res.bookings.upcoming_bookings);

            var avgRating = parseFloat(res.average_rating || 0);
            var totalReviews = parseInt(res.total_reviews || 0, 10);
            $('#stat_rating_stars').html(renderRatingStars(avgRating));
            $('#stat_rating_value').text(avgRating.toFixed(1));
            $('#stat_rating_count').text('(' + totalReviews + ')');
            var ratingCaption = totalReviews === 1
                ? '<?= labels('based_on_one_review', 'Based on 1 review') ?>'
                : '<?= labels('based_on_reviews', 'Based on {count} reviews') ?>'.replace('{count}', totalReviews);
            $('#stat_rating_row').attr('title', ratingCaption);

            var recentCompletedData = res.recent_completed_bookings.map(function (row) {
                var viewUrl = '<?= base_url('admin/orders/veiw_orders/') ?>' + row.order_id;
                return {
                    order_id: row.order_id,
                    customer_name: row.customer_name || '-',
                    scheduled_display: row.date_of_service || '-',
                    amount_display: CURRENCY + ' ' + parseFloat(row.final_total || 0).toFixed(2),
                    operations: '<a href="' + viewUrl + '" class="btn btn-primary btn-sm" title="<?= labels('view_order') ?>"><i class="fa fa-eye"></i></a>'
                };
            });
            $('#recent_completed_table').bootstrapTable('load', recentCompletedData);

            if (res.map.available) {
                $('#handyman_map_message').hide();
                $('#handyman_map').show();
                pendingMapData = res.map;
                if (googleMapsReady) {
                    initHandymanMap(pendingMapData);
                }
            } else {
                $('#handyman_map_message').show();
                $('#handyman_map').hide();
            }
        });
    }

    function initHandymanMap(mapData) {
        handymanMapInitialised = true;
        LiveTrackingMap.init({
            containerId: 'handyman_map',
            pollUrl: '<?= base_url('admin/handymen/tracker-location/') ?>' + HANDYMAN_ID,
            orderPos: { lat: mapData.order_latitude, lng: mapData.order_longitude },
            handymanPos: { lat: mapData.handyman_latitude, lng: mapData.handyman_longitude },
            orderTitle: mapData.order_address || '<?= esc(labels('service_location', 'Service Location')) ?>',
            handymanTitle: '<?= esc(addslashes($handyman['username'])) ?>',
            customerIconUrl: '<?= base_url('public/uploads/site/customer_pin.svg') ?>',
            handymanIconUrl: '<?= base_url('public/uploads/site/handyman.svg') ?>',
            endedMessage: '<?= esc(labels('live_tracking_ended', 'Handyman has arrived. Live tracking stopped.')) ?>'
        });
    }

    $(document).ready(function () {
        loadHandymanOverview();

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $('#viewHandymanTabs .nav-link i').removeClass('text-white');
            $(e.target).find('i').addClass('text-white');

            var target = $(e.target).attr('href');
            var initedFlags = $(this).data('inited') || {};
            if (!initedFlags[target]) {
                initedFlags[target] = true;
                $(this).data('inited', initedFlags);
                return;
            }
            if (target === '#pane-bookings') $('#handyman_bookings_table').bootstrapTable('refresh');
            else if (target === '#pane-reviews') $('#handyman_reviews_table').bootstrapTable('refresh');
        });

        $(document).on('click', '.view-review-images', function () {
            var images = $(this).data('images');
            var $body = $('#reviewImagesModalBody').empty();
            if (Array.isArray(images) && images.length > 0) {
                images.forEach(function (url) {
                    $body.append('<a href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" class="rounded border" height="120" style="object-fit:cover;"></a>');
                });
            } else {
                $body.append('<p class="text-muted mb-0"><?= labels('no_images', 'No Images') ?></p>');
            }
            $('#reviewImagesModal').modal('show');
        });
    });

    // Google Maps JS API global callback (see include-scripts.php) invokes window.initPageMap
    // once loaded; the map for this page is only built once overview data (fetched independently,
    // may resolve before or after the Maps script) has confirmed an active booking to track.
    window.initPageMap = function () {
        googleMapsReady = true;
        if (pendingMapData && !handymanMapInitialised) {
            initHandymanMap(pendingMapData);
        }
    };
</script>