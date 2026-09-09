<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('payment_request', "Payment Request") ?>

                <span class="breadcrumb-item p-3 pt-2 text-primary">
                    <i data-content="<?= labels('data_content_withdraw_request', 'If the provider needs the money for their prepaid booked services due to any circumstances, they can send a withdraw request and the withdrawal requests sent by the provider will be shown here.') ?>" class="fa fa-question-circle"></i>
                </span>
            </h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('/admin/partners') ?>"><i class="fas fa-handshake text-warning"></i> <?= labels('provider', 'Provider') ?></a></div>
                <div class="breadcrumb-item"><?= labels('payment_request', 'Payment Requests') ?></div>
            </div>
        </div>
        <div class="container-fluid card">
            <!-- <h2 class='section-title'><?= labels('payment_requests_by_provider', 'Payment Requests by Provider') ?></h2> -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-info mt-3" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong><?= labels('note', 'Note') ?>:</strong>
                        <?= labels('enable_status_update_dropdown', 'To enable bulk status dropdown, you need to first select atleast one record in table by clicking on the checkbox.') ?>
                    </div>

                    <div class="row mt-4 mb-3">
                        <div class="col-md-4 col-sm-2 mb-2">
                            <div class="input-group">
                                <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="button">
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

                        <div class="dropdown d-inline ml-2">
                            <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?= labels('download', 'Download') ?>
                            </button>
                            <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                <a class="dropdown-item" onclick="custome_export('pdf','Payment Request list','payment_request_list');"><?= labels('pdf', 'PDF') ?></a>
                                <a class="dropdown-item" onclick="custome_export('excel','Payment Request list','payment_request_list');"><?= labels('excel', 'Excel') ?></a>
                                <a class="dropdown-item" onclick="custome_export('csv','Payment Request list','payment_request_list')"><?= labels('csv', 'CSV') ?></a>
                            </div>
                        </div>


                        <div class="col-4" id="update_bulk">
                            <div class="form-group">

                                <select name="bulk_order_update" disabled id="bulk_order_update" class="form-control select2">
                                    <option value="" disabled><?= labels('bulk_update_status', 'Bulk Update Status') ?>-</option>
                                    <option value="0"><?= labels('pending', 'Pending') ?></option>
                                    <option value="1"><?= labels('approved', 'Approved') ?></option>
                                    <option value="2"><?= labels('not_approved', 'Not approved') ?></option>
                                    <option value="3"><?= labels('settled', 'Settle') ?></option>


                                </select>

                            </div>
                        </div>

                    </div>

                    <table class="table " data-fixed-columns="true" id="payment_request_list" data-pagination-successively-size="2" data-detail-formatter="user_formater" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/partners/payment_request_list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-order="desc" data-query-params="payment_request_query_paramas">
                        <thead>
                            <tr>
                                <th class="text-center multi-check" data-checkbox="true">
                                <th data-field="id" class="text-center" data-visible="true" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                <th data-field="user_id" class="text-center" data-visible="false" data-sortable="true"><?= labels('user_id', 'User ID') ?></th>
                                <th data-field="profile" class="text-center" data-visible="true" data-sortable="true"><?= labels('provider', 'Provider') ?></th>
                                <th data-field="user_type" class="text-center" data-visible="true" data-sortable="true"><?= labels('user_type', 'User Type') ?></th>
                                <th data-field="bank_details" class="text-center" data-visible="true"><?= labels('bank_details', 'Bank Details') ?></th>
                                <th data-field="amount" class="text-center" data-visible="true" data-sortable="true"><?= labels('amount', 'Amount') ?>(<?= $data['currency'] ?>)</th>
                                <th data-field="remarks" class="text-center" data-visible="true"><?= labels('remarks', 'Remarks') ?></th>
                                <th data-field="status" class="text-center" data-visible="true" data-sortable="true"><?= labels('status', 'status') ?></th>
                                <th data-field="created_at" class="text-center" data-visible="true" data-sortable="true" data-formatter="payment_request_created_at_formatter"><?= labels('created_at', 'Date Created') ?></th>


                                <th data-field="operations" class="text-center" data-events="payment_events"><?= labels('operations', 'Operations') ?></th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="edit_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered " role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"><?= labels('settle_payment_request', "Settle Payment Request") ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="<?= base_url('admin/partners/pay_partner') ?>" method="post" id="pay_partner" class="for-payment-request-form-submit-event">
                <div class="modal-body">
                    <input id="request_id" class="form-control" type="hidden" name="request_id">
                    <input id="user_id" class="form-control" type="hidden" name="user_id">
                    <input id="amount" class="form-control" type="hidden" name="amount">



                    <div class="row">

                        <div class="col-md">
                            <div class="form-group">
                                <label><?= labels('status', 'Status') ?><span class="text-danger text-sm">*</span></label>
                                <br>
                                <div id="dis_approved" class="btn-group ">
                                    <label class="btn btn-warning" data-toggle-class="btn-warning" data-toggle-passive-class="btn-default">
                                        <input type="radio" name="status" value="0"><?= labels('pending', 'Pending') ?>
                                    </label>
                                    <label class="btn btn-primary" data-toggle-class="btn-primary" data-toggle-passive-class="btn-default">
                                        <input type="radio" name="status" value="1"><?= labels('approved', 'Approved') ?>
                                    </label>
                                    <label class="btn btn-danger" data-toggle-class="btn-danger" data-toggle-passive-class="btn-default">
                                        <input type="radio" name="status" value="2" checked> <?= labels('not_approved', 'Not approved') ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md">
                            <label for="message"><?= labels('message', "Message") ?></label>
                            <textarea style="min-height:60px" rows="2" cols="20" class='form-control' name="reason" id="message"></textarea>
                        </div>
                    </div>


                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success submit_btn"><?= labels('update', "Update") ?></button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', "Close") ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
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
                        <div class="form-group ">
                            <label for="table_filters"><?= labels('table_filters', 'Table filters') ?></label>
                            <div id="columnToggleContainer">
                            </div>
                            <button class="btn btn-primary d-block mt-3" id="apply_filter"><?= labels('apply', 'Apply') ?></button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>

<script>
    $(document).on('change', '.multi-check', (event) => {
        selected = $('#payment_request_list').bootstrapTable('getSelections');

        if ((selected.length == 0)) {
            $("#bulk_order_update").attr('disabled', 'disabled');
        } else {
            $("#bulk_order_update").removeAttr('disabled');
        }
    })




    $('#bulk_order_update').change(function() {
        var request_ids = [];
        selected = $('#payment_request_list').bootstrapTable('getSelections');

        var arr = Object.values(selected);
        var i;
        var final_selection = [];
        var request_ids = arr.map(({
            id
        }) => (id));



        Swal.fire({
            title: "<?= labels('are_your_sure', 'Are you sure?') ?>",
            text: "<?= labels('you_wont_be_able_to_revert_this', 'You won\'t be able to revert this!') ?>",
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: '<?= labels('yes_proceed', 'Yes, proceed!') ?>',
            cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
        }).then((result) => {
            if (result.value) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    url: baseUrl + "/admin/partners/payment_request_multiple_update",
                    data: {
                        request_ids: request_ids,
                        status: $(this).val()
                    },
                    type: 'post',
                    success: function(response) {

                        if (response.error == false) {
                            showToastMessage(response.message, "success");
                            setTimeout(() => {
                                $('#payment_request_list').bootstrapTable('refresh')

                            }, 2000)
                        } else {
                            return showToastMessage(response.message, "error");
                        }


                        $('#update_bulk').addClass('d-none');
                        $('#update_bulk').removeClass('d-flex');
                        $('#update_bulk').removeClass('justify-content-end');
                        return;
                    },
                    error: function(response) {
                        return showToastMessage(response.message, "error");
                    }
                });
            }
        })

    });


    $(document).on('click', '.set_settlement_status', function() {
        var selected = $(this).attr('value');
        Swal.fire({
            title: "<?= labels('are_your_sure', 'Are you sure?') ?>",
            text: "<?= labels('you_wont_be_able_to_revert_this', 'You won\'t be able to revert this!') ?>",
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: '<?= labels('yes_proceed', 'Yes, proceed!') ?>',
            cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
        }).then((result) => {

            if (result.value) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    url: baseUrl + "/admin/partners/payment_request_settement_status",
                    data: {
                        id: selected,
                        status: 4
                    },
                    type: 'post',
                    success: function(response) {

                        showToastMessage(response.message, "success");
                        setTimeout(() => {
                            $('#payment_request_list').bootstrapTable('refresh')
                        }, 2000)
                        return;
                    },
                    error: function(response) {
                        return showToastMessage(response.message, "error");
                    }
                });
            }
        })

    });
    $(function() {
        $('.fa').popover({
            trigger: "hover"
        });
    });

    // Search functionality - trigger on button click instead of keydown
    // This allows users to type their search term and then click the search button
    $('.input-group-append button').on('click', function() {
        $('#payment_request_list').bootstrapTable('refresh');
    });



    // created_at is stored in UTC. The backend sends an unambiguous UTC ISO-8601
    // string in `created_at_utc`; convert it to the viewer's browser-local time.
    function payment_request_created_at_formatter(value, row) {
        var utc = row && row.created_at_utc ? row.created_at_utc : value;
        if (!utc) {
            return '';
        }
        var d = new Date(utc);
        if (isNaN(d.getTime())) {
            // Parsing failed (unexpected format) — fall back to the raw value.
            return value || '';
        }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
        ];
        var hours = d.getHours();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        var mins = d.getMinutes() < 10 ? '0' + d.getMinutes() : d.getMinutes();
        var day = d.getDate() < 10 ? '0' + d.getDate() : d.getDate();
        return day + '-' + months[d.getMonth()] + '-' + d.getFullYear() +
            ' ' + (hours < 10 ? '0' + hours : hours) + ':' + mins + ' ' + ampm;
    }

    function payment_request_query_paramas(p) {
        return {
            search: $("#customSearch").val() ? $("#customSearch").val() : p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,

        };
    }

    $(document).ready(function() {
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        var columns = [{
                field: 'id',
                label: '<?= labels('id', 'ID') ?>',
                visible: false
            },
            {
                field: 'user_id',
                label: '<?= labels('user_id', 'User ID') ?>',
                visible: false
            },
            {
                field: 'partner_name',
                label: '<?= labels('provider_name', 'Provider Name') ?>'
            },
            {
                field: 'payment_address',
                label: '<?= labels('payment_address', 'Payment Address') ?>',
                // visible: false

            },

            {
                field: 'amount',
                label: '<?= labels('amount', 'Amount') ?>(<?= $data['currency'] ?>)',
            },

            {
                field: 'remarks',
                label: '<?= labels('remarks', 'Remarks') ?>',
                visible: false,

            },
            {
                field: 'status',
                label: '<?= labels('status', 'status') ?>',
            },
            {
                field: 'created_at',
                label: '<?= labels('created_at', 'Date Created') ?>',
                visible: false,
            },
            {
                field: 'operations',
                label: '<?= labels('operations', 'Operations') ?>',
            },

        ];
        setupColumnToggle('payment_request_list', columns, 'columnToggleContainer');
    });
</script>