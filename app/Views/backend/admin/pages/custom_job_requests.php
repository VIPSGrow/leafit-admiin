<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('custom_job_requests', "Custom Job Requests") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('custom_job_requests', "Custom Job Requests") ?></a></div>
            </div>
        </div>
        <?= helper('form'); ?>
        <div class="row">
            <div class="col-md-12">
                <div class="container-fluid card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="row mt-4 mb-3 ">
                                    <div class="col-md-4 col-sm-2 mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" id="customSearchBtn" type="button">
                                                    <i class="fa fa-search d-inline"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown d-inline ml-2">
                                        <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <?= labels('download', 'Download') ?>
                                        </button>
                                        <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                            <a class="dropdown-item" onclick="custome_export('pdf','Custom Job Requests','custom_job_requests_list');"><?= labels('pdf', 'PDF') ?></a>
                                            <a class="dropdown-item" onclick="custome_export('excel','Custom Job Requests','custom_job_requests_list');"><?= labels('excel', 'Excel') ?></a>
                                            <a class="dropdown-item" onclick="custome_export('csv','Custom Job Requests','custom_job_requests_list')"><?= labels('csv', 'CSV') ?></a>
                                        </div>
                                    </div>
                                </div>
                                <table class="table " data-fixed-columns="true" id="custom_job_requests_list" data-detail-formatter="user_formater"
                                    data-toggle="table"
                                    data-url="<?= base_url("admin/custom-job-requests-list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
                                    data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id"
                                    data-query-params="custom_job_requests_query_params" data-pagination-successively-size="2">
                                    <thead>
                                        <tr>
                                            <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                            <th data-field="username" class="text-center"><?= labels('username', 'Username') ?></th>
                                            <th data-field="service_title" class="text-center"><?= labels('title', 'Title') ?></th>
                                            <th data-field="truncateWords_service_short_description" class="text-center"><?= labels('short_description', 'Short Description') ?></th>
                                            <th data-field="category_name" class="text-center"><?= labels('category', 'Category') ?></th>
                                            <th data-field="total_bids" class="text-center"><?= labels('total_bids', 'Total Bids') ?></th>
                                            <th data-field="status" class="text-center"><?= labels('status', 'Status') ?></th>
                                            <th data-field="cancel_reason" class="text-center"><?= labels('cancellation_reason', 'Cancellation Reason') ?></th>
                                            <th data-field="operation" class="text-center"><?= labels('view_more', 'View More') ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    // Search button click handler - triggers table refresh when search button is clicked
    $("#customSearchBtn").on('click', function() {
        $('#custom_job_requests_list').bootstrapTable('refresh');
    });

    // Allow Enter key to trigger search button click
    $("#customSearch").on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#customSearchBtn').click();
        }
    });

    // Define the query params function globally to ensure it's available
    window.custom_job_requests_query_params = function(p) {
        // console.log('custom_job_requests_query_params called with:', p);
        var params = {
            search: $("#customSearch").val() ? $("#customSearch").val() : p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
        };
        // console.log('custom_job_requests_query_params returning:', params);
        return params;
    };

    // $("#customSearch").on('keydown', function() {
    //     $('#custom_job_requests_list').bootstrapTable('refresh');
    // });



    // // Check if Bootstrap Table is properly initialized
    // $(document).ready(function() {
    //     console.log('Document ready - checking Bootstrap Table initialization');
    //     console.log('Table element exists:', $('#custom_job_requests_list').length > 0);
    //     console.log('Bootstrap Table data:', $('#custom_job_requests_list').data('bootstrap.table'));
    //     console.log('custom_job_requests_query_params function exists:', typeof window.custom_job_requests_query_params);

    //     // Wait for Bootstrap Table to initialize
    //     setTimeout(function() {
    //         console.log('Checking Bootstrap Table after delay...');
    //         console.log('Bootstrap Table data after delay:', $('#custom_job_requests_list').data('bootstrap.table'));

    //         // If table is not initialized, try to initialize it manually
    //         if (!$('#custom_job_requests_list').data('bootstrap.table')) {
    //             console.log('Bootstrap Table not initialized, attempting manual initialization...');
    //             $('#custom_job_requests_list').bootstrapTable('destroy');
    //             $('#custom_job_requests_list').bootstrapTable({
    //                 url: '<?= base_url("admin/custom-job-requests-list") ?>',
    //                 sidePagination: 'server',
    //                 pagination: true,
    //                 pageList: [5, 10, 25, 50, 100, 200, 'All'],
    //                 search: false,
    //                 showColumns: false,
    //                 showColumnsSearch: true,
    //                 showRefresh: false,
    //                 sortName: 'id',
    //                 sortOrder: 'DESC',
    //                 queryParams: 'custom_job_requests_query_params',
    //                 paginationSuccessivelySize: 2
    //             });
    //         }

    //         // Force refresh to test
    //         console.log('Forcing table refresh...');
    //         $('#custom_job_requests_list').bootstrapTable('refresh');
    //     }, 1000);
    // });
</script>