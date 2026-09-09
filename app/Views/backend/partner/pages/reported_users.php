<?php
$current_url = current_url();
?>
<div class="main-content">
    <section class="section" id="pill-about_us" role="tabpanel">
        <div class="section-header mt-2">
            <h1> <?= labels('blocked_users', "Blocked Users") ?>
                <span class="breadcrumb-item p-3 pt-2 text-primary">
                </span>
            </h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/partner/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('blocked_users', "Blocked Users") ?></div>
            </div>
        </div>

        <div class="container-fluid">

            <div class="card row" style="border:0!important;border-radius:0!important">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="row mt-4">
                                <div class="col-md-4 col-sm-2">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" id="customSearchBtn" type="button">
                                                <i class="fa fa-search d-inline"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive block_user_table">

                    <table class="table " data-fixed-columns="true" id="user_reports_list" data-detail-formatter="user_formater"
                        data-auto-refresh="true" data-toggle="table"
                        data-url="<?= base_url("partner/reported_users/list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
                        data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="DESC"
                        data-query-params="provider_reports_query_params" data-events="provider_reports_events">
                        <thead>
                            <tr>
                                <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                <th data-field="reported_name" class="text-center"><?= labels('reported_user', 'Reported User') ?></th>
                                <th data-field="report_reason" class="text-center"><?= labels('reason', 'Reason') ?></th>
                                <th data-field="additional_info" class="text-center"><?= labels('additional_info', 'Additional Info') ?></th>
                                <th data-field="created_at" class="text-center" data-sortable="true"><?= labels('reported_at', 'Reported At') ?></th>
                                <th data-field="operations" class="text-center" data-events="provider_reports_events"><?= labels('operations', 'Operations') ?></th>
                            </tr>
                        </thead>
                    </table>
                </div>

            </div>

        </div>
    </section>
</div>

<!-- View Report Modal -->
<div class="modal fade" id="viewReportModal" tabindex="-1" role="dialog" aria-labelledby="viewReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewReportModalLabel"><?= labels('report_details', 'Report Details') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><?= labels('reporter_details', 'Reporter Details') ?></h6>
                        <p><strong><?= labels('name', 'Name') ?>:</strong> <span id="reporter_name"></span></p>
                        <p><strong><?= labels('email', 'Email') ?>:</strong> <span id="reporter_email"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6><?= labels('reported_user_details', 'Reported User Details') ?></h6>
                        <p><strong><?= labels('name', 'Name') ?>:</strong> <span id="reported_name"></span></p>
                        <p><strong><?= labels('email', 'Email') ?>:</strong> <span id="reported_email"></span></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6><?= labels('report_details', 'Report Details') ?></h6>
                        <p><strong><?= labels('reason', 'Reason') ?>:</strong> <span id="report_reason"></span></p>
                        <p><strong><?= labels('additional_information', 'Additional Information') ?>:</strong></p>
                        <div id="additional_info" class="border p-3 rounded"></div>
                        <p class="mt-3"><strong><?= labels('reported_at', 'Reported At') ?>:</strong> <span id="reported_at"></span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Search button click handler - triggers table refresh when search button is clicked
        $("#customSearchBtn").on("click", function() {
            $("#user_reports_list").bootstrapTable("refresh");
        });

        // Allow Enter key to trigger search button click
        $("#customSearch").on('keypress', function(e) {
            if (e.which == 13) {
                e.preventDefault();
                $('#customSearchBtn').click();
            }
        });
    });

    function provider_reports_query_params(params) {
        params.search = $('#customSearch').val();
        return params;
    }

    window.provider_reports_events = {
        'click .view-user-report': function(e, value, row, index) {
            $.ajax({
                url: baseUrl + '/partner/reported_users/view/' + row.id,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.error === false) {
                        const report = response.data;
                        console.log(report);

                        // Display reporter and reported user details
                        // The backend ensures correct emails based on context:
                        // - When provider blocks user: reporter = provider, reported = user
                        // - When user blocks provider: reporter = user, reported = provider
                        $('#reporter_name').text(report.reporter_name || '');
                        $('#reporter_email').text(report.reporter_email || '');
                        $('#reported_name').text(report.reported_name || '');
                        $('#reported_email').text(report.reported_email || '');
                        $('#report_reason').text(report.report_reason || '');
                        $('#additional_info').text(report.additional_info || '<?= labels('no_additional_info', 'No additional information provided') ?>');

                        // Use formatted time if available, otherwise use original
                        // This fixes the incorrect time display issue
                        const displayTime = report.created_at_formatted || report.created_at || '';
                        $('#reported_at').text(displayTime);

                        $('#viewReportModal').modal('show');
                    } else {
                        showToastMessage(response.message, "error");
                    }
                },
                error: function() {
                    showToastMessage("<?= labels('something_went_wrong', 'Something went wrong') ?>", "error");
                }
            });
        }
    };
</script>