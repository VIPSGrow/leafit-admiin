<?php

/**
 * Admin Payment Refunds Page
 * 
 * Displays refund transactions from the existing transactions table
 * Shows only transactions where transaction_type = 'refund'
 */
?>

<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><?= labels('payment_refunds', 'Payment Refunds') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('/admin/dashboard') ?>"><?= labels('Dashboard', 'Dashboard') ?></a>
                </div>
                <div class="breadcrumb-item"><?= labels('payment_refunds', 'Payment Refunds') ?></div>
            </div>
        </div>
        <!-- Important Notice for Admins -->
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <strong><?= labels('important_notice', 'Important Notice') ?>:</strong>
            <?= labels('important_notice_description', 'Only update refund status after you have completed the manual refund process through your payment gateway or financial institution. Changing the status here does not automatically process the refund.') ?>
        </div>

        <!-- Refunds Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row mt-4 mb-3">
                            <div class="col-md-4 col-sm-2 mb-2">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="button" id="customSearchBtn">
                                            <i class="fa fa-search d-inline"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Filter button removed for simplicity -->
                            <div class="dropdown d-inline ml-2">
                                <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <?= labels('download', 'Download') ?>
                                </button>
                                <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                    <a class="dropdown-item" onclick="custome_export('pdf','service list','refunds-table');"><?= labels('pdf', 'PDF') ?></a>
                                    <a class="dropdown-item" onclick="custome_export('excel','service list','refunds-table');"><?= labels('excel', 'Excel') ?></a>
                                    <a class="dropdown-item" onclick="custome_export('csv','service list','refunds-table')"><?= labels('csv', 'CSV') ?></a>
                                </div>
                            </div>
                        </div>



                        <!-- Bootstrap Table -->
                        <div class="table-responsive">
                            <table class="table" data-fixed-columns="true" id="refunds-table" data-show-export="true" data-export-types="['txt','excel','csv']"
                                data-export-options='{"fileName": "refunds-list","ignoreColumn": ["actions"]}' data-auto-refresh="true"
                                data-show-columns="false" data-search="false" data-show-refresh="false" data-toggle="table"
                                data-page-list="[5, 10, 25, 50, 100, 200, All]" data-side-pagination="server" data-pagination="true"
                                data-url="<?= base_url("admin/payment_refunds/list") ?>" data-sort-name="id" data-sort-order="desc"
                                data-pagination-successively-size="2" data-query-params="refundQueryParams" data-mobile-responsive="true" data-responsive="true">
                                <thead>
                                    <tr>
                                        <th data-field="id" data-sortable="true" data-visible="false"><?= labels('id', 'ID') ?></th>
                                        <th data-field="partner_name"><?= labels('partner', 'Partner') ?></th>
                                        <th data-field="order_id" data-sortable="true"><?= labels('order_id', 'Order ID') ?></th>
                                        <th data-field="customer_name" data-sortable="true"><?= labels('customer', 'Customer') ?></th>
                                        <th data-field="refund_amount" data-sortable="true"><?= labels('amount', 'Amount') ?>(<?= $data['currency'] ?>)</th>
                                        <th data-field="payment_method" data-sortable="true"><?= labels('payment_method', 'Payment Method') ?></th>
                                        <th data-field="refund_status"><?= labels('status', 'Status') ?></th>
                                        <th data-field="txn_id"><?= labels('transaction_id', 'Transaction ID') ?></th>
                                        <th data-field="message"><?= labels('message', 'Message') ?></th>
                                        <th data-field="actions" data-events="refundActionEvents"><?= labels('operations', 'Operations') ?></th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Filter drawer removed for simplicity -->
</div>

<script>
    // Query params function for Bootstrap Table
    function refundQueryParams(params) {
        // Use custom search if available
        var customSearch = $('#customSearch').val();
        if (customSearch && customSearch.trim() !== '') {
            params.search = customSearch.trim();
        }
        return params;
    }

    // Clean response handler (no debugging)

    $(document).ready(function() {
        // Handle custom search
        $('#customSearchBtn').on('click', function() {
            var searchTerm = $('#customSearch').val();
            $('#refunds-table').bootstrapTable('refresh', {
                query: {
                    search: searchTerm
                }
            });
        });

        $('#customSearch').on('keypress', function(e) {
            if (e.which == 13) {
                $('#customSearchBtn').click();
            }
        });
    });

    // Action events for Bootstrap Table
    window.refundActionEvents = {
        'click .mark-success': function(e, value, row, index) {
            // Confirm action with user
            if (confirm('<?= labels('are_you_sure_you_want_to_mark_this_refund_as_successful', 'Are you sure you want to mark this refund as successful?') ?>')) {
                markRefundAsSuccess(row.id);
            }
        }
    };

    // Function to mark refund as successful
    function markRefundAsSuccess(transactionId) {
        // Show loading state
        $('button[data-id="' + transactionId + '"].mark-success').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '<?= base_url("admin/payment_refunds/updateStatus") ?>',
            type: 'POST',
            data: {
                id: transactionId,
                '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.error === false) {
                    // Show success message
                    alert('Success: ' + response.message);

                    // Refresh the table to show updated status
                    $('#refunds-table').bootstrapTable('refresh');
                } else {
                    // Show error message
                    alert('Error: ' + response.message);

                    // Re-enable button
                    $('button[data-id="' + transactionId + '"].mark-success').prop('disabled', false).html('<i class="fas fa-check"></i>');
                }
            },
            error: function(xhr, status, error) {
                alert('<?= labels('error_failed_to_update_refund_status', 'Error: Failed to update refund status. Please try again.') ?>');

                // Re-enable button
                $('button[data-id="' + transactionId + '"].mark-success').prop('disabled', false).html('<i class="fas fa-check"></i>');
            }
        });
    }
</script>