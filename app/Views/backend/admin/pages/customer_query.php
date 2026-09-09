<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('user_queries', "User Queries") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><i class="fas fa-newspaper text-warning"></i> <?= labels('customer_queries', "Customer Queries") ?></div>
            </div>
        </div>
        <div class="container-fluid card">
            <div class="card-body">
                <div class="row mt-4 mb-3">
                    <!-- Custom search wrapper keeps this page consistent with other admin tables -->
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="input-group">
                            <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                            <div class="input-group-append">
                                <button class="btn btn-primary" id="customSearchBtn" type="button">
                                    <i class="fa fa-search d-inline"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Filter trigger + export dropdown mirror the pattern used across the panel -->
                    <button class="btn btn-secondary ml-2 filter_button" id="filterButton">
                        <span class="material-symbols-outlined mt-1">
                            filter_alt
                        </span>
                    </button>
                    <div class="dropdown d-inline ml-2">
                        <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <?= labels('download', 'Download') ?>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" onclick="custome_export('pdf','Customer query list','customer_query');"><?= labels('pdf', 'PDF') ?></a>
                            <a class="dropdown-item" onclick="custome_export('excel','Customer query list','customer_query');"><?= labels('excel', 'Excel') ?></a>
                            <a class="dropdown-item" onclick="custome_export('csv','Customer query list','customer_query');"><?= labels('csv', 'CSV') ?></a>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg">
                        <div class="table-responsive">
                            <table class="table" id="customer_query" data-detail-formatter="user_formater" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/customer_queris_list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-refresh="false" data-sort-name="id" data-sort-order="desc" data-query-params="customer_query_params">
                                <thead>
                                    <tr>
                                        <th data-field="id" data-visible="false" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                        <th data-field="username" class="text-center" data-sortable="true"><?= labels('name', 'Name') ?></th>
                                        <th data-field="email" class="text-center" data-sortable="true"><?= labels('email', 'Email') ?></th>
                                        <th data-field="message" class="text-center" data-sortable="true"><?= labels('message', 'Message') ?></th>
                                        <th data-field="subject" class="text-center" data-sortable="true"><?= labels('subject', 'Subject') ?></th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<!-- Drawer + backdrop replicate the reusable column toggle UX used elsewhere -->
<div id="filterBackdrop"></div>
<div class="drawer" id="filterDrawer">
    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="bg-new-primary" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <div class="bg-white m-3 text-new-primary" style="box-shadow: 0px 8px 26px #00b9f02e; display: inline-block; padding: 10px; height: 45px; width: 45px; border-radius: 15px;">
                            <span class="material-symbols-outlined">
                                filter_alt
                            </span>
                        </div>
                        <h3 class="mb-0" style="display: inline-block; font-size: 16px; margin-left: 10px;"><?= labels('filters', 'Filters') ?></h3>
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
                            <label for="table_filters"><?= labels('table_filters', 'Table filters') ?></label>
                            <div id="columnToggleContainer"></div>
                            <button class="btn btn-primary d-block mt-3" id="apply_filter"><?= labels('apply', 'Apply') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    // Attach shared drawer behaviour and table helpers so this page feels identical to other list screens.
    $(document).ready(function() {
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");

        // Fetch dynamic columns once the table loads to supply the toggle UI.
        var dynamicColumns = fetchColumns('customer_query');
        setupColumnToggle('customer_query', dynamicColumns, 'columnToggleContainer');

        // Search button click handler - triggers table refresh when search button is clicked
        $("#customSearchBtn").on('click', function() {
            $('#customer_query').bootstrapTable('refresh');
        });

        // Allow Enter key to trigger search button click
        $("#customSearch").on('keypress', function(e) {
            if (e.which == 13) {
                e.preventDefault();
                $('#customSearchBtn').click();
            }
        });
    });

    // Pass the custom search input through to the server so filtering matches other tables.
    function customer_query_params(p) {
        return {
            search: $('#customSearch').val() ? $('#customSearch').val() : p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset
        };
    }
</script>