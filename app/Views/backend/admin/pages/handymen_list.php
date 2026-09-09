<!-- Main Content -->
<div class="main-content">

    <section class="section" id="pill-general_settings" role="tabpanel">

        <!-- Page Header -->
        <section class="section-header mt-2">
            <h1><?= labels('handymen', 'Handymen') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('/admin/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= labels('handymen', 'Handymen') ?></div>
            </div>
        </section>

        <!-- Page Body -->
        <section class="section-body">
            <div id="output-status"></div>

            <div class="row mt-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-xl overflow-hidden">

                        <!-- Card Toolbar: Search + Status Filters -->
                        <section class="card-header bg-white border-bottom px-4 pt-3 pb-3">
                            <!-- Status Filter Pills -->
                            <div class="d-flex align-items-center flex-wrap mr-3 mb-3 mb-md-0">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill mr-2 active"
                                    id="hmg_filter_all" data-filter="">
                                    <?= labels('all', 'All') ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill mr-2"
                                    id="hmg_filter_active" data-filter="1">
                                    <?= labels('active', 'Active') ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill"
                                    id="hmg_filter_deactive" data-filter="0">
                                    <?= labels('deactive', 'Deactive') ?>
                                </button>
                            </div>

                            <!-- Top Row: Search + Filter Button -->
                            <div class="d-flex align-items-center flex-wrap">

                                <div class="position-relative mr-2" style="width: 260px;">
                                    <input type="text" class="form-control search-icon-input" id="handymanGlobalSearch"
                                        placeholder="<?= labels('search_handyman', 'Search handyman...') ?>"
                                        style="padding-right: 36px;">
                                    <button type="button" id="handymanSearchBtn"
                                        title="<?= labels('search', 'Search') ?>"
                                        style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer;"
                                        class="hm-search-icon-btn search-icon-btn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>

                                <button class="btn btn-light border pt-1" id="handymanGlobalFilterButton"
                                    title="<?= labels('filters', 'Filters') ?>">
                                    <span class="material-symbols-outlined"
                                        style="font-size: 20px; display: block; line-height: 1;">
                                        filter_alt
                                    </span>
                                </button>

                            </div>
                        </section>

                        <!-- Card Data: Handyman Table -->
                        <section class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="handyman_global_list" data-toggle="table"
                                    data-side-pagination="server" data-pagination="true"
                                    data-url="<?= base_url('admin/handymen/list') ?>"
                                    data-sort-name="id" data-sort-order="desc"
                                    data-page-list="[10, 25, 50, 100]"
                                    data-pagination-successively-size="2"
                                    data-show-refresh="false" data-search="false"
                                    data-show-columns="false" data-show-export="false"
                                    data-query-params="handymanGlobalQueryParams">
                                    <thead class="thead-light">
                                        <tr>
                                            <th data-field="id" data-visible="false" data-sortable="true"
                                                class="text-center">
                                                <?= labels('id', 'ID') ?>
                                            </th>
                                            <th data-field="profile" class="text-left" style="width: 260px;">
                                                <?= labels('profile', 'Profile') ?>
                                            </th>
                                            <th data-field="profile_image" data-visible="false" class="text-center" style="width: 64px;">
                                                <?= labels('image', 'Image') ?>
                                            </th>
                                            <th data-field="username" data-visible="false" data-sortable="true" class="text-left">
                                                <?= labels('username', 'Username') ?>
                                            </th>
                                            <th data-field="phone" data-visible="false" data-sortable="true" class="text-center">
                                                <?= labels('phone_number', 'Phone Number') ?>
                                            </th>
                                            <th data-field="email" data-visible="false" data-sortable="true" class="text-center">
                                                <?= labels('email', 'Email') ?>
                                            </th>
                                            <th data-field="provider" data-sortable="true" class="text-center">
                                                <?= labels('provider', 'Provider') ?>
                                            </th>
                                            <th data-field="status_badge" class="text-center">
                                                <?= labels('status', 'Status') ?>
                                            </th>
                                            <th data-field="salary_display" class="text-center">
                                                <?= labels('salary', 'Salary') ?> (<?= esc($currency) ?>)
                                            </th>
                                            <th data-field="completed_bookings_display" class="text-center">
                                                <?= labels('completed_bookings', 'Completed Bookings') ?>
                                            </th>
                                            <th data-field="operations" class="text-center"
                                                data-events="handyman_global_events">
                                                <?= labels('operations', 'Operations') ?>
                                            </th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </section>

                    </div>
                </div>
            </div>

        </section>

    </section>

</div>

<!-- Filter Drawer -->
<div id="filterBackdrop"></div>
<div class="drawer" id="filterDrawer">

    <!-- Drawer Header -->
    <section class="drawer-header bg-new-primary d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <div class="bg-white m-3 text-new-primary"
                style="box-shadow: 0px 8px 26px #00b9f02e; display: inline-block; padding: 10px; height: 45px; width: 45px; border-radius: 15px;">
                <span class="material-symbols-outlined">filter_alt</span>
            </div>
            <h3 class="mb-0" style="display: inline-block; font-size: 16px; margin-left: 10px;">
                <?= labels('filters', 'Filters') ?>
            </h3>
        </div>
        <div id="handymanGlobalCancelFilter" style="cursor: pointer;">
            <span class="material-symbols-outlined mr-2">cancel</span>
        </div>
    </section>

    <!-- Drawer Body: Column Toggles -->
    <section class="drawer-body">
        <div class="row mt-4 mx-2">
            <div class="col-md-12">
                <div class="form-group">
                    <label><?= labels('table_filters', 'Table filters') ?></label>
                    <div id="handymanGlobalColumnToggleContainer"></div>
                    <button class="btn btn-primary d-block mt-3" id="apply_filter">
                        <?= labels('apply', 'Apply') ?>
                    </button>
                </div>
            </div>
        </div>
    </section>

</div>

<style>
    .hm-search-icon-btn { color: var(--primary, #007bff); transition: color 0.15s; }
    .hm-search-icon-btn:hover { color: var(--primary-dark, #0056b3); }

    #handyman_global_list a.hm-global-username-link:hover { text-decoration: underline !important; }

    #handyman_global_list a.o-media--middle:hover .provider_name_table { text-decoration: underline; }
</style>

<script>
    $(document).ready(function () {
        for_drawer("#handymanGlobalFilterButton", "#filterDrawer", "#filterBackdrop", "#handymanGlobalCancelFilter");

        var columns = fetchColumns('handyman_global_list');
        setupColumnToggle('handyman_global_list', columns, 'handymanGlobalColumnToggleContainer');

        var handymanGlobalStatusFilter = '';

        var $pills = $('#hmg_filter_all, #hmg_filter_active, #hmg_filter_deactive');
        $pills.on('click', function () {
            $pills.removeClass('active');
            $(this).addClass('active');
            handymanGlobalStatusFilter = $(this).data('filter');
            $('#handyman_global_list').bootstrapTable('refresh');
        });

        function triggerHandymanSearch() {
            $('#handyman_global_list').bootstrapTable('refresh');
        }

        $('#handymanSearchBtn').on('click', triggerHandymanSearch);
        $('#handymanGlobalSearch').on('keydown', function (e) {
            if (e.key === 'Enter') triggerHandymanSearch();
        });

        window.handymanGlobalQueryParams = function (p) {
            return {
                search: $('#handymanGlobalSearch').val() || '',
                limit:  p.limit,
                sort:   p.sort,
                order:  p.order,
                offset: p.offset,
                status: handymanGlobalStatusFilter,
            };
        };

        $('#handyman_global_list').on('post-body.bs.table', function () {
            $(this).closest('.bootstrap-table').find('.pagination-detail').addClass('ml-4');
        });

        window.handyman_global_events = {
            'click .hm-global-toggle-status': function (e, value, row) {
                e.preventDefault();
                $.post('<?= base_url('admin/handymen/toggle-status') ?>', {
                    id: row.id,
                    [csrfName]: csrfHash,
                }, function (res) {
                    if (res.csrfName) csrfName = res.csrfName;
                    if (res.csrfHash) csrfHash = res.csrfHash;
                    showToastMessage(res.message, res.error ? 'error' : 'success');
                    if (!res.error) { $('#handyman_global_list').bootstrapTable('refresh'); }
                }, 'json');
            },
        };
    });
</script>
