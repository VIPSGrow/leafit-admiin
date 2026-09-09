<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('handymen_cash_collections', 'Handymen Cash Collections') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('handymen_cash_collections', 'Handymen Cash Collections') ?>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-muted">
                    <?= labels('collect_from_handyman_desc', 'COD cash collected by your handymen, not yet handed over to you. Collect the amount once you physically receive it back from them.') ?>
                </p>
                <div id="toolbar" class="mb-2 d-flex align-items-center flex-wrap">
                    <div class="position-relative mr-2" style="width: 260px;">
                        <input type="text" class="form-control search-icon-input" id="handymanCashCollectionSearch"
                            placeholder="<?= labels('search_handymen', 'Search handymen…') ?>"
                            style="padding-right: 36px;">
                        <button type="button" id="handymanCashCollectionSearchBtn" class="search-icon-btn"
                            title="<?= labels('search', 'Search') ?>"
                            style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="dropdown d-inline">
                        <button class="btn export_download dropdown-toggle" type="button"
                            id="handymanCashCollectionExportBtn" data-toggle="dropdown" aria-haspopup="true"
                            aria-expanded="false">
                            <?= labels('download', "Download") ?>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="handymanCashCollectionExportBtn">
                            <a class="dropdown-item"
                                onclick="custome_export('pdf','Handymen Cash Collections','handyman_cash_collection_table');"><?= labels('pdf', "PDF") ?></a>
                            <a class="dropdown-item"
                                onclick="custome_export('excel','Handymen Cash Collections','handyman_cash_collection_table');"><?= labels('excel', "Excel") ?></a>
                            <a class="dropdown-item"
                                onclick="custome_export('csv','Handymen Cash Collections','handyman_cash_collection_table')"><?= labels('csv', "CSV") ?></a>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-borderd" id="handyman_cash_collection_table"
                        data-toggle="table" data-toolbar="#toolbar"
                        data-url="<?= base_url('partner/handyman-cash-collection/list_data') ?>" data-pagination="false"
                        data-search="false" data-show-refresh="false" data-show-columns="false" data-show-export="false"
                        data-export-types="['txt','excel','csv']"
                        data-export-options='{"fileName": "handymen-cash-collections","ignoreColumn": ["handyman_id"]}'
                        data-query-params="handymanCashCollectionQueryParams">
                        <thead>
                            <tr>
                                <th data-field="handyman_name" class="text-center"><?= labels('handyman', 'Handyman') ?>
                                </th>
                                <th data-field="order_id" class="text-center"><?= labels('order_id', 'Order ID') ?></th>
                                <th data-field="customer_name" class="text-center"><?= labels('customer', 'Customer') ?>
                                </th>
                                <th data-field="amount_display" class="text-center"><?= labels('amount', 'Amount') ?>
                                    (<?= $currency ?>)</th>
                                <th data-field="outstanding_amount" class="text-center">
                                    <?= labels('outstanding_amount', 'Outstanding Amount') ?> (<?= $currency ?>)
                                </th>
                                <th data-field="handyman_id" class="text-center"
                                    data-formatter="handymanCollectFormatter" data-events="handymanCollectEvents">
                                    <?= labels('action', 'Action') ?>
                                </th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="collect_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= base_url('partner/handyman-cash-collection/collect') ?>" id="collect_form"
                class="create-form-without-reset" data-fv data-table="#handyman_cash_collection_table">
                <div class="modal-header">
                    <h5 class="modal-title" id="collect_modal_label">
                        <i class="fa fa-hand-holding-usd text-primary mr-2"></i>
                        <?= labels('collect_from_handyman', 'Collect from Handyman') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="handyman_id" id="collect_handyman_id">
                    <div class="form-group">
                        <div class="o-media o-media--middle">
                            <img id="collect_handyman_image" class="o-media__img images_in_card" src="" alt="">
                            <div class="o-media__body">
                                <div class="provider_name_table" id="collect_handyman_name"></div>
                                <div class="provider_email_table" id="collect_handyman_email"></div>
                                <div class="provider_email_table" id="collect_handyman_phone"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?= labels('outstanding_amount', 'Outstanding Amount') ?> (<?= $currency ?>)</label>
                        <p class="text-muted" id="collect_outstanding_display"></p>
                    </div>
                    <div class="form-group">
                        <label for="collect_amount" class="required"><?= labels('amount', 'Amount') ?></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="collect_amount"
                            name="amount" data-rules="required" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="collect_message"><?= labels('message', 'Message') ?></label>
                        <textarea class="form-control" id="collect_message" name="message" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        data-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                    <button type="submit"
                        class="btn btn-primary submit_btn"><?= labels('collect_from_handyman', 'Collect from Handyman') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.handymanCashCollectionQueryParams = function (params) {
        params.search = $('#handymanCashCollectionSearch').val().trim();
        return params;
    };

    $(document).ready(function () {
        function triggerHandymanCashCollectionSearch() {
            $('#handyman_cash_collection_table').bootstrapTable('refresh');
        }

        $('#handymanCashCollectionSearchBtn').on('click', triggerHandymanCashCollectionSearch);
        $('#handymanCashCollectionSearch').on('keydown', function (e) {
            if (e.key === 'Enter') triggerHandymanCashCollectionSearch();
        });
    });

    function handymanCollectFormatter(value, row) {
        return '<button type="button" class="btn btn-sm btn-primary btn-icon btn-collect-from-handyman" data-handyman-id="' + row.handyman_id + '" title="<?= labels('collect_from_handyman', 'Collect from Handyman') ?>">'
            + '<i class="fa fa-hand-holding-usd"></i></button>';
    }

    window.handymanCollectEvents = {
        'click .btn-collect-from-handyman': function (e, value, row) {
            $('#collect_handyman_id').val(row.handyman_id);
            $('#collect_handyman_image').attr('src', row.handyman_image);
            $('#collect_handyman_name').text(row.handyman_name);
            $('#collect_handyman_email').text(row.handyman_email);
            $('#collect_handyman_phone').text(row.handyman_phone);
            $('#collect_outstanding_display').text(row.outstanding_amount);
            $('#collect_amount').attr('max', row.outstanding_amount).val('');
            $('#collect_message').val('');
            $('#createModal').modal('show');
        },
    };
</script>