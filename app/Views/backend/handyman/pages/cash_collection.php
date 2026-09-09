<?= $this->extend('backend/handyman/layouts/main') ?>

<?= $this->section('content') ?>

<div class="section-body">

    <!-- Stat cards -->
    <div class="row mb-3">
        <div class="col-md-6 col-12 mb-3 mb-md-0">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center me-3"
                        style="width: 56px; height: 56px;">
                        <span class="material-symbols-outlined text-primary" style="font-size: 28px;">payments</span>
                    </div>
                    <div>
                        <div class="text-muted small"><?= labels('overall_cash_collected', 'Overall Cash Collected') ?>
                        </div>
                        <div class="fs-4 fw-bold"><?= esc($currency) ?> <?= number_format($overall_total, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center me-3"
                        style="width: 56px; height: 56px;">
                        <span class="material-symbols-outlined text-warning"
                            style="font-size: 28px;">hourglass_top</span>
                    </div>
                    <div>
                        <div class="text-muted small">



                            <?= labels('outstanding_pending_handover', 'Outstanding — Pending Handover') ?>
                        </div>
                        <div class="fs-4 fw-bold"><?= esc($currency) ?> <?= number_format($outstanding_total, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">

                <section class="card-body p-0">
                    <div id="cash_collection_toolbar" class="d-flex flex-wrap align-items-center gap-2 m-0">
                        <button type="button" id="cashCollectionClearFilters" class="btn btn-link btn-sm d-none">
                            <?= labels('clear_filter', 'Clear Filter') ?>
                        </button>

                        <div class="position-relative flex-grow-1 flex-md-grow-0" style="min-width: 240px;">
                            <input type="text" class="form-control form-control-sm w-100" id="cashCollectionDateRange"
                                autocomplete="off" readonly
                                placeholder="<?= labels('select_date_range', 'Select Date Range') ?>"
                                style="padding-right: 28px; cursor: pointer;">
                            <i class="fas fa-calendar-alt"
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #6c757d;"></i>
                        </div>

                        <div class="d-flex gap-2 flex-grow-1 flex-md-grow-0" id="cashCollectionTypeFilter">
                            <button type="button" class="btn btn-outline-success btn-sm cash-collection-type-btn flex-fill text-nowrap"
                                data-type="collection" data-color="success">
                                <i class="fas fa-hand-holding-usd me-1"></i>
                                <?= labels('collection', 'Collection') ?>
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm cash-collection-type-btn flex-fill text-nowrap"
                                data-type="settlement" data-color="warning">
                                <i class="fas fa-check-circle me-1"></i>
                                <?= labels('settlement', 'Settlement') ?>
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="cash_collection_table" data-toggle="table"
                            data-toolbar="#cash_collection_toolbar" data-side-pagination="server" data-pagination="true"
                            data-url="<?= base_url('handyman/cash-collection/list_data') ?>" data-sort-name="hcc.id"
                            data-sort-order="desc" data-page-list="[10, 25, 50, 100]"
                            data-pagination-successively-size="2" data-show-refresh="true" data-search="true"
                            data-show-columns="false" data-show-export="false"
                            data-query-params="cashCollectionQueryParams">
                            <thead class="thead-light">
                                <tr>
                                    <th data-field="order_id" data-sortable="false" class="text-center"
                                        style="width: 100px;">
                                        <?= labels('order_id', 'Order ID') ?>
                                    </th>
                                    <th data-field="customer_name" data-sortable="false">
                                        <?= labels('customer', 'Customer') ?>
                                    </th>
                                    <th data-field="service_date" data-sortable="true" data-sort-name="hcc.created_at"
                                        class="text-center" data-sortable="true">
                                        <?= labels('date', 'Date') ?>
                                    </th>
                                    <th data-field="amount_display" data-sortable="true" data-sort-name="hcc.amount"
                                        class="text-center" style="width: 120px;">
                                        <?= labels('amount', 'Amount') ?> (<?= esc($currency) ?>)
                                    </th>
                                    <th data-field="status_badge" data-sortable="true" data-sort-name="hcc.type"
                                        class="text-center" style="width: 170px;">
                                        <?= labels('status', 'Status') ?>
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </section>

            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
    var cashCollectionType = '';
    var cashCollectionDateFrom = '';
    var cashCollectionDateTo = '';

    window.cashCollectionQueryParams = function (p) {
        return {
            search: p.search,
            type: cashCollectionType,
            date_from: cashCollectionDateFrom,
            date_to: cashCollectionDateTo,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
        };
    };

    $(document).ready(function () {
        function triggerSearch() {
            $('#cash_collection_table').bootstrapTable('refresh');
        }

        function updateClearFiltersVisibility() {
            var hasFilters = cashCollectionType !== '' || cashCollectionDateFrom !== '' || cashCollectionDateTo !== '';
            $('#cashCollectionClearFilters').toggleClass('d-none', !hasFilters);
        }

        // Type filter — toggle: clicking the active button clears it back to "all".
        function resetTypeButtonStyles() {
            $('.cash-collection-type-btn').each(function () {
                var $b = $(this);
                var color = $b.data('color');
                $b.removeClass('active btn-' + color).addClass('btn-outline-' + color);
            });
        }

        $('.cash-collection-type-btn').on('click', function () {
            var $btn = $(this);
            var type = $btn.data('type');
            var color = $btn.data('color');
            var isActive = $btn.hasClass('active');

            resetTypeButtonStyles();

            if (isActive) {
                cashCollectionType = '';
            } else {
                $btn.addClass('active btn-' + color).removeClass('btn-outline-' + color);
                cashCollectionType = type;
            }

            updateClearFiltersVisibility();
            triggerSearch();
        });

        // Daterange picker with an explicit Apply button inside — filter only fires on Apply.
        var $dateRange = $('#cashCollectionDateRange');
        if (typeof $dateRange.daterangepicker === 'function') {
            $dateRange.daterangepicker({
                autoUpdateInput: false,
                maxDate: moment(),
                locale: {
                    format: 'YYYY-MM-DD',
                    cancelLabel: '<?= labels('clear', 'Clear') ?>',
                    applyLabel: '<?= labels('apply', 'Apply') ?>',
                },
                opens: 'left',
                alwaysShowCalendars: true,
            });

            $dateRange.on('apply.daterangepicker', function (ev, picker) {
                var s = picker.startDate.format('YYYY-MM-DD');
                var e = picker.endDate.format('YYYY-MM-DD');
                $dateRange.val(s === e ? s : s + ' to ' + e);
                cashCollectionDateFrom = s;
                cashCollectionDateTo = e;
                updateClearFiltersVisibility();
                triggerSearch();
            });

            $dateRange.on('cancel.daterangepicker', function () {
                $dateRange.val('');
                cashCollectionDateFrom = '';
                cashCollectionDateTo = '';
                updateClearFiltersVisibility();
                triggerSearch();
            });
        }

        $('#cashCollectionClearFilters').on('click', function () {
            cashCollectionType = '';
            cashCollectionDateFrom = '';
            cashCollectionDateTo = '';
            resetTypeButtonStyles();
            $dateRange.val('');
            updateClearFiltersVisibility();
            triggerSearch();
        });
    });
</script>
<?= $this->endSection() ?>