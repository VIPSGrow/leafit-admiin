<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('provider_ledger', 'Provider Ledger') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('/admin/partners') ?>"><?= labels('provider', 'Provider') ?></a></div>
                <div class="breadcrumb-item"><?= labels('provider_ledger', 'Provider Ledger') ?></div>
            </div>
        </div>
        <div class="section-body">
            <div class="container-fluid card">
                <div class="row mt-4 mb-3 align-items-end">
                    <div class="col-md-6">
                        <label for="providerLedgerSelect"><?= labels('select_provider', 'Select Provider') ?></label>
                        <select id="providerLedgerSelect" class="form-control select2">
                            <option value=""><?= labels('select_provider', 'Select Provider') ?></option>
                            <?php foreach ($providers as $provider): ?>
                                <option value="<?= (int) $provider['id'] ?>">
                                    <?= esc($provider['company_name'] ?: $provider['name']) ?> (#<?= (int) $provider['id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="providerLedgerEmpty" class="alert alert-light border mb-4">
                    <?= labels('select_provider_to_view_ledger', 'Select a provider to view payment, pending amounts, and ledger entries.') ?>
                </div>
                <div id="providerLedgerError" class="alert alert-danger d-none mb-4"></div>

                <div id="providerLedgerSummary" class="row d-none mb-4">
                    <div class="col-md-3 mb-3"><div class="card border-left-primary h-100"><div class="card-body"><small><?= labels('provider', 'Provider') ?></small><h5 id="ledgerProviderName" class="mb-0"></h5><small id="ledgerProviderContact"></small></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-success h-100"><div class="card-body"><small><?= labels('available_payment', 'Available Payment') ?></small><h4 id="ledgerBalance" class="mb-0"></h4></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-warning h-100"><div class="card-body"><small><?= labels('pending_payment', 'Pending Payment') ?></small><h4 id="ledgerPendingAmount" class="mb-0"></h4><small id="ledgerPendingCount"></small></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-info h-100"><div class="card-body"><small><?= labels('total_bookings', 'Total Bookings') ?></small><h4 id="ledgerTotalBookings" class="mb-0"></h4></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-warning h-100"><div class="card-body"><small><?= labels('pending_bookings', 'Pending Bookings') ?></small><h4 id="ledgerPendingBookings" class="mb-0"></h4><small id="ledgerCompletedBookings"></small></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-info h-100"><div class="card-body"><small><?= labels('cash_collection_pending', 'Cash Collection Pending') ?></small><h4 id="ledgerPayable" class="mb-0"></h4></div></div></div>
                </div>

                <div id="providerLedgerTableWrap" class="d-none">
                    <h5><?= labels('ledger_entries', 'Ledger Entries') ?></h5>
                    <div class="table-responsive">
                        <table class="table table-hover" id="provider_ledger_list" data-toggle="table" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100]" data-search="false" data-show-refresh="false" data-sort-name="id" data-sort-order="desc" data-query-params="providerLedgerQueryParams">
                            <thead><tr>
                                <th data-field="id" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                <th data-field="message"><?= labels('message', 'Message') ?></th>
                                <th data-field="type_badge"><?= labels('type', 'Ledger Type') ?></th>
                                <th data-field="date" data-sortable="true"><?= labels('date', 'Date') ?></th>
                                <th data-field="total_amount"><?= labels('total_amount', 'Total Amount') ?> (<?= esc($currency) ?>)</th>
                                <th data-field="amount"><?= labels('amount', 'Amount') ?> (<?= esc($currency) ?>)</th>
                                <th data-field="commission_amount"><?= labels('commission_amount', 'Commission Amount') ?> (<?= esc($currency) ?>)</th>
                                <th data-field="status_badge"><?= labels('status', 'Status') ?></th>
                                <th data-field="payment_status"><?= labels('payment_status', 'Payment Status') ?></th>
                            </tr></thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    var providerLedgerDetailsUrl = "<?= base_url('admin/partners/provider_ledger_details') ?>";
    var providerLedgerHistoryUrl = "<?= base_url('admin/partners/provider_ledger_list') ?>";
    var providerLedgerCurrency = "<?= esc($currency) ?>";

    function providerLedgerMoney(amount) {
        return providerLedgerCurrency + ' ' + Number(amount || 0).toFixed(2);
    }

    function providerLedgerQueryParams(params) {
        return { limit: params.limit, offset: params.offset, sort: params.sort, order: params.order, search: params.search || '' };
    }

    $('#providerLedgerSelect').on('change', function () {
        var providerId = $(this).val();
        var table = $('#provider_ledger_list');
        $('#providerLedgerError').addClass('d-none').text('');
        if (!providerId) {
            $('#providerLedgerEmpty').removeClass('d-none');
            $('#providerLedgerSummary, #providerLedgerTableWrap').addClass('d-none');
            table.bootstrapTable('removeAll');
            return;
        }

        $.getJSON(providerLedgerDetailsUrl + '/' + providerId)
            .done(function (response) {
                if (response.error) {
                    $('#providerLedgerError').removeClass('d-none').text(response.message);
                    return;
                }
                var provider = response.provider;
                $('#providerLedgerEmpty').addClass('d-none');
                $('#providerLedgerSummary, #providerLedgerTableWrap').removeClass('d-none');
                $('#ledgerProviderName').text(provider.company_name || provider.username);
                $('#ledgerProviderContact').text(provider.email || provider.phone || '');
                $('#ledgerBalance').text(providerLedgerMoney(provider.balance));
                $('#ledgerPendingAmount').text(providerLedgerMoney(response.pending_requests.amount));
                $('#ledgerPendingCount').text(response.pending_requests.count + ' pending request(s)');
                $('#ledgerPayable').text(providerLedgerMoney(provider.payable_commision));
                $('#ledgerTotalBookings').text(response.bookings.total);
                $('#ledgerPendingBookings').text(response.bookings.pending);
                $('#ledgerCompletedBookings').text(response.bookings.completed + ' completed / ' + response.bookings.cancelled + ' cancelled');
                table.bootstrapTable('refreshOptions', { url: providerLedgerHistoryUrl + '/' + providerId, pageNumber: 1 });
            })
            .fail(function () {
                $('#providerLedgerError').removeClass('d-none').text('Unable to load provider ledger.');
            });
    });
</script>
