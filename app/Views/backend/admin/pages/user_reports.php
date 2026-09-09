<?php
// Get RTL setting from session for proper UI alignment
$session = \Config\Services::session();
$is_rtl = $session->get('is_rtl');
$language = $session->get('language');
$default_language = fetch_details('languages', ['is_default' => '1']);

// Only check default language's RTL status if no language is set in session
// Otherwise, use the explicit is_rtl value from session
if (empty($language) && !isset($is_rtl)) {
    $is_rtl = $default_language[0]['is_rtl'];
} elseif ($is_rtl === null) {
    // Fallback if is_rtl is not set but language is
    $is_rtl = 0;
}

// Convert to integer value for consistency
$is_rtl = (int)$is_rtl;
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('blocked_users', 'Blocked Users') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('blocked_users', 'Blocked Users') ?></div>
            </div>
        </div>

        <div class="section-body">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="row mt-4 mb-3">
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
                            </div>
                            <table class="table" data-fixed-columns="true" id="user_reports_list" data-detail-formatter="user_formater"
                                data-auto-refresh="true" data-toggle="table"
                                data-url="<?= base_url("admin/user_reports/list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
                                data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="DESC"
                                data-query-params="user_reports_query_params" data-events="user_reports_events">
                                <thead>
                                    <tr>
                                        <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                        <th data-field="reporter_name" class="text-center"><?= labels('reporter', 'Reporter') ?></th>
                                        <th data-field="reported_name" class="text-center"><?= labels('reported_user', 'Reported User') ?></th>
                                        <th data-field="report_reason" class="text-center"><?= labels('reason', 'Reason') ?></th>
                                        <th data-field="created_at" class="text-center"><?= labels('reported_at', 'Reported At') ?></th>
                                        <th data-field="operations" class="text-center" data-events="user_reports_events"><?= labels('operations', 'Operations') ?></th>
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
                        <div class="report-field">
                            <span class="report-label"><?= labels('name', 'Name') ?>:</span>
                            <span class="report-value" id="reporter_name"></span>
                        </div>
                        <div class="report-field">
                            <span class="report-label"><?= labels('email', 'Email') ?>:</span>
                            <span class="report-value" id="reporter_email"></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6><?= labels('reported_user_details', 'Reported User Details') ?></h6>
                        <div class="report-field">
                            <span class="report-label"><?= labels('name', 'Name') ?>:</span>
                            <span class="report-value" id="reported_name"></span>
                        </div>
                        <div class="report-field">
                            <span class="report-label"><?= labels('email', 'Email') ?>:</span>
                            <span class="report-value" id="reported_email"></span>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6><?= labels('report_details', 'Report Details') ?></h6>
                        <div class="report-field">
                            <span class="report-label"><?= labels('reason', 'Reason') ?>:</span>
                            <span class="report-value report-reason" id="report_reason"></span>
                        </div>
                        <div class="report-field report-field-block mt-2">
                            <span class="report-label"><?= labels('additional_information', 'Additional Information') ?>:</span>
                            <div class="report-value">
                                <div id="additional_info" class="border p-3 rounded report-additional-info"></div>
                            </div>
                        </div>
                        <div class="report-field mt-3">
                            <span class="report-label"><?= labels('reported_at', 'Reported At') ?>:</span>
                            <span class="report-value" id="reported_at"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Report modal field styling - supports both LTR and RTL */
    .report-field {
        display: flex;
        flex-direction: row;
        margin-bottom: 0.75rem;
        align-items: flex-start;
    }

    /* RTL support - reverse flex direction for RTL */
    body[dir="rtl"] .report-field {
        flex-direction: row-reverse;
    }

    /* Label styling - bold and with proper spacing */
    .report-label {
        font-weight: bold;
        min-width: 120px;
        flex-shrink: 0;
        margin-right: 0.5rem;
    }

    /* RTL support for labels - margin on left side in RTL */
    body[dir="rtl"] .report-label {
        margin-right: 0;
        margin-left: 0.5rem;
        text-align: right;
    }

    /* LTR support for labels - margin on right side in LTR */
    body[dir="ltr"] .report-label {
        text-align: left;
    }

    /* Value styling - allows text wrapping and prevents overflow */
    .report-value {
        flex: 1;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
        min-width: 0;
        /* Allows flex item to shrink below content size */
    }

    /* Reason field - specific styling for long text */
    .report-reason {
        display: block;
        width: 100%;
        max-width: 100%;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
    }

    /* Additional info container - prevent overflow */
    .report-additional-info {
        width: 100%;
        max-width: 100%;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
        white-space: pre-wrap;
        /* Preserve whitespace but allow wrapping */
        overflow-x: hidden;
    }

    /* Ensure modal body content doesn't overflow */
    #viewReportModal .modal-body {
        overflow-x: hidden;
    }

    /* Block-level field - for fields with block content like additional info */
    .report-field-block {
        flex-direction: column;
        align-items: flex-start;
    }

    /* RTL support for block fields */
    body[dir="rtl"] .report-field-block {
        align-items: flex-end;
    }

    /* Block field label - full width for block fields */
    .report-field-block .report-label {
        width: 100%;
        margin-bottom: 0.5rem;
        margin-right: 0;
        margin-left: 0;
    }

    /* Block field value - full width */
    .report-field-block .report-value {
        width: 100%;
    }

    /* RTL support for field container alignment */
    body[dir="rtl"] .report-field {
        text-align: right;
    }

    body[dir="ltr"] .report-field {
        text-align: left;
    }
</style>

<script>
    // Search button click handler - triggers table refresh when search button is clicked
    $("#customSearchBtn").on('click', function() {
        $('#user_reports_list').bootstrapTable('refresh');
    });

    // Allow Enter key to trigger search button click
    $("#customSearch").on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#customSearchBtn').click();
        }
    });

    function user_reports_query_params(params) {
        params.search = $('#customSearch').val();
        return params;
    }

    window.user_reports_events = {
        'click .view-report': function(e, value, row, index) {
            $.ajax({
                url: baseUrl + '/admin/user_reports/view/' + row.id,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.error === false) {
                        const report = response.data;
                        $('#reporter_name').text(report.reporter_name);
                        $('#reporter_email').text(report.reporter_email);
                        $('#reported_name').text(report.reported_name);
                        $('#reported_email').text(report.reported_email);
                        $('#report_reason').text(report.report_reason);
                        $('#additional_info').text(report.additional_info || '<?= labels('no_additional_info', 'No additional information provided') ?>');
                        
                        // Use formatted time if available, otherwise use original
                        // This ensures consistent time display matching the provider panel
                        const displayTime = report.created_at_formatted || report.created_at || '';
                        $('#reported_at').text(displayTime);
                        
                        $('#viewReportModal').modal('show');
                    } else {
                        showToastMessage(response.message, "error");
                    }
                },
                error: function() {
                    showToastMessage("<?= labels(SOMETHING_WENT_WRONG, 'Something went wrong') ?>", "error");
                }
            });
        }
    };
</script>