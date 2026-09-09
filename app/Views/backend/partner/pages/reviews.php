<?= helper('form'); ?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('reviews', 'Reviews') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
            </div>
        </div>
        <div class="container-fluid card">
            <div class="row">
                <div class="col-md-12">
                    <div class="row mt-4 mb-3">
                        <div class="col-md-4 col-sm-2 mb-2">
                            <div class="input-group">
                                <input type="text" class="form-control" id="customSearch"
                                    placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search"
                                    aria-describedby="customSearchBtn">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" id="customSearchBtn" type="button">
                                        <i class="fa fa-search d-inline"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-secondary  ml-2 filter_button" id="filterButton">
                            <span class="material-symbols-outlined mt-1">
                                filter_alt
                            </span>
                        </button>
                        <div class="d-inline dropdown ml-2 mr-3">
                            <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?= labels('download', 'Download') ?>
                            </button>
                            <div class="dropdown-menu" x-placement="bottom-start"
                                style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                <a class="dropdown-item" onclick="custome_export('pdf','Rating list','rating_table');">
                                    <?= labels('pdf', 'PDF') ?> </a>
                                <a class="dropdown-item"
                                    onclick="custome_export('excel','Rating list','rating_table');">
                                    <?= labels('excel', 'Excel') ?> </a>
                                <a class="dropdown-item" onclick="custome_export('csv','Rating list','rating_table')">
                                    <?= labels('csv', 'CSV') ?> </a>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-borderd" data-fixed-columns="true"
                            data-pagination-successively-size="2" id="rating_table" data-show-export="false"
                            data-export-types="['txt','excel','csv']"
                            data-export-options='{"fileName": "invoice-order-list","ignoreColumn": ["action"]}'
                            data-auto-refresh="true" data-show-columns="false" data-search="false"
                            data-show-refresh="false" data-toggle="table"
                            data-page-list="[5, 10, 25, 50, 100, 200, All]" data-side-pagination="server"
                            data-pagination="true" data-url="<?= base_url("partner/review_list") ?>" data-sort-name="id"
                            data-sort-order="desc" data-query-params="rating_query_params" data-service-id="">
                            <thead>
                                <tr>
                                    <th data-field="id" class="text-center" data-sortable="true">
                                        <?= labels('id', 'ID') ?>
                                    </th>
                                    <th data-field="translated_service_name" class="text-center" data-visible="true"
                                        data-sortable="true"><?= labels('service_name', 'Service') ?></th>
                                    <th data-field="user_name" class="text-center" data-visible="true">
                                        <?= labels('user_name', 'User') ?>
                                    </th>
                                    <th data-field="comment" class="text-center" data-visible="true">
                                        <?= labels('comment', 'Comment') ?>
                                    </th>
                                    <th data-field="created_at" class="text-center" data-visible="true">
                                        <?= labels('rated_on', 'Rated On') ?>
                                    </th>
                                    <th data-field="view_images" class="text-center" data-visible="true">
                                        <?= labels('images', 'Images') ?>
                                    </th>
                                    <th data-field="stars"><?= labels('rating', 'Rating') ?></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<div id="filterBackdrop"></div>
<div class="drawer" id="filterDrawer">
    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="bg-new-primary" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <div class="bg-white m-3 text-new-primary"
                            style="box-shadow: 0px 8px 26px #00b9f02e; display: inline-block; padding: 10px; height: 45px; width: 45px; border-radius: 15px;">
                            <span class="material-symbols-outlined">
                                filter_alt
                            </span>
                        </div>
                        <h3 class="mb-0" style="display: inline-block; font-size: 16px; margin-left: 10px;">
                            <?= labels('filters', 'Filters') ?>
                        </h3>
                    </div>
                    <div id="cancelButton" style="cursor: pointer;">
                        <span class="material-symbols-outlined mr-2">
                            cancel
                        </span>
                    </div>
                </div>
                <div class="row mt-4 mx-2">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label
                                for="rating_star_filter"><?= labels('rating_star_filter', 'Filter Rating by Star') ?></label>
                            <select name="rating_star_filter" id="rating_star_filter" class="form-control select2">
                                <option value=""><?= labels('select', 'Select') ?>-</option>
                                <option value="1"><?= labels('1', '1') ?></option>
                                <option value="2"><?= labels('2', '2') ?></option>
                                <option value="3"><?= labels('3', '3') ?></option>
                                <option value="4"><?= labels('4', '4') ?></option>
                                <option value="5"><?= labels('5', '5') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group ">
                            <label for="table_filters"><?= labels('table_filters', 'Table filters') ?></label>
                            <div id="columnToggleContainer"></div>
                            <button class="btn btn-primary d-block mt-3"
                                id="apply_filter"><?= labels('apply', 'Apply') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    // Search button click handler - triggers table refresh when search button is clicked
    $("#customSearchBtn").on('click', function () {
        $('#rating_table').bootstrapTable('refresh');
    });

    // Allow Enter key to trigger search button click
    $("#customSearch").on('keypress', function (e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#customSearchBtn').click();
        }
    });
    var rating_star_filter = "";
    $("#rating_star_filter").on("change", function () {
        rating_star_filter = $(this).find("option:selected").val();
    });

    function rating_query_params(p) {
        return {
            search: $("#customSearch").val() ? $("#customSearch").val() : p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            rating_star_filter: rating_star_filter,
        };
    }
    $("#apply_filter").on("click", function (e) {
        $("#rating_table").bootstrapTable("refresh");
    });
    $(document).ready(function () {
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        // We match each checkbox field with the exact table data-field so toggling never leaves stray columns visible.
        // Using translated_service_name keeps the toggle logic in sync with the bootstrap table column names.
        var columns = [{
            field: 'id',
            label: '<?= labels('id', 'ID') ?>',
            visible: false
        },
        {
            field: 'translated_service_name',
            label: '<?= labels('service_name', 'Service') ?>'
        },
        {
            field: 'user_name',
            label: '<?= labels('user_name', 'User') ?>'
        },
        {
            field: 'comment',
            label: '<?= labels('comment', 'Comment') ?>',
            visible: false
        },
        {
            field: 'rated_on',
            label: '<?= labels('rated_on', 'Rated On') ?>',
        },
        {
            field: 'view_images',
            label: '<?= labels('images', 'Images') ?>',
        },
        {
            field: 'stars',
            label: '<?= labels('rating', 'Rating') ?>',
        },
        ];
        setupColumnToggle('rating_table', columns, 'columnToggleContainer');

        // Track review viewed event when reviews page loads
        // Note: This tracks general review viewing. If viewing specific service reviews, service_id should be passed
        if (typeof trackReviewViewed === 'function') {
            // Track general review viewing (all reviews)
            // If you need to track specific service reviews, get service_id from URL or filter
            trackReviewViewed('');
        }
    });
</script>