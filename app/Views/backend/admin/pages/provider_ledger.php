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
                    <div class="col-md-3">
                        <label for="providerLedgerDateFrom"><?= labels('date_from', 'Date From') ?></label>
                        <input type="date" id="providerLedgerDateFrom" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="providerLedgerDateTo"><?= labels('date_to', 'Date To') ?></label>
                        <input type="date" id="providerLedgerDateTo" class="form-control">
                    </div>
                    <div class="col-md-12 mt-3">
                        <button type="button" id="providerLedgerApplyDates" class="btn btn-primary"><?= labels('apply_filter', 'Apply Filter') ?></button>
                        <button type="button" id="providerLedgerClearDates" class="btn btn-light"><?= labels('clear', 'Clear') ?></button>
                    </div>
                </div>

                <div id="providerLedgerEmpty" class="alert alert-light border mb-4">
                    <?= labels('select_provider_to_view_ledger', 'Select a provider to view payment totals and ledger entries.') ?>
                </div>
                <div id="providerLedgerError" class="alert alert-danger d-none mb-4"></div>

                <div id="providerLedgerSummary" class="row d-none mb-4">
                    <div class="col-md-3 mb-3"><div class="card border-left-primary h-100"><div class="card-body"><small><?= labels('provider', 'Provider') ?></small><h5 id="ledgerProviderName" class="mb-0"></h5><small id="ledgerProviderContact"></small></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-info h-100"><div class="card-body"><small><?= labels('total_amount', 'Gross Booking Total') ?></small><h4 id="ledgerGrossTotal" class="mb-0"></h4><small id="ledgerBookingCount"></small></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-success h-100"><div class="card-body"><small><?= labels('online_received_by_admin', 'Online Received by Admin') ?></small><h4 id="ledgerOnlineReceived" class="mb-0"></h4></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-warning h-100"><div class="card-body"><small><?= labels('cash_collected_by_provider', 'Cash Collected by Provider') ?></small><h4 id="ledgerCashCollected" class="mb-0"></h4></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-success h-100"><div class="card-body"><small><?= labels('commission_received_in_cash', 'Commission Received in Cash') ?></small><h4 id="ledgerAdminCashReceived" class="mb-0"></h4></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-danger h-100"><div class="card-body"><small><?= labels('admin_commission', 'Admin Commission') ?> (<span id="ledgerCommissionRate"></span>%)</small><h4 id="ledgerCommissionTotal" class="mb-0"></h4></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-primary h-100"><div class="card-body"><small><?= labels('provider_net_amount', 'Provider Net Amount') ?></small><h4 id="ledgerProviderNet" class="mb-0"></h4></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-left-warning h-100"><div class="card-body"><small><?= labels('admin_to_collect', 'Admin to Collect from Provider') ?></small><h4 id="ledgerSettlementDue" class="mb-0"></h4></div></div></div>
                </div>
                <small id="ledgerBookingNote" class="d-none text-muted mb-3"></small>

                <div id="providerLedgerTableWrap" class="d-none">
                    <h5><?= labels('ledger_entries', 'Ledger Entries') ?></h5>
                    <div class="table-responsive">
                        <table class="table table-hover" id="provider_ledger_list" data-toggle="table" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100]" data-search="false" data-show-refresh="false" data-sort-name="id" data-sort-order="desc" data-query-params="providerLedgerQueryParams">
                            <thead><tr>
                                <th data-field="id" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                <th data-field="order_id" data-formatter="providerLedgerOrderIdFormatter"><?= labels('order_id', 'Order ID') ?></th>
                                <th data-field="message"><?= labels('message', 'Message') ?></th>
                                <th data-field="type_badge"><?= labels('type', 'Ledger Type') ?></th>
                                <th data-field="date" data-sortable="true"><?= labels('date', 'Date') ?></th>
                                <th data-field="total_amount"><?= labels('total_amount', 'Total Amount') ?> (<?= esc($currency) ?>)</th>
                                <th data-field="amount"><?= labels('amount', 'Entry Amount') ?> (<?= esc($currency) ?>)</th>
                                <th data-field="online_amount"><?= labels('online_received', 'Online Received') ?> (<?= esc($currency) ?>)</th>
                                <th data-field="cash_amount"><?= labels('cash_collected', 'Cash Collected') ?> (<?= esc($currency) ?>)</th>
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
    var providerLedgerDateRange = { date_from: '', date_to: '' };

    function providerLedgerMoney(amount) {
        return providerLedgerCurrency + ' ' + Number(amount || 0).toFixed(2);
    }

    function providerLedgerQueryParams(params) {
        return {
            limit: params.limit,
            offset: params.offset,
            sort: params.sort,
            order: params.order,
            search: params.search || '',
            date_from: providerLedgerDateRange.date_from,
            date_to: providerLedgerDateRange.date_to
        };
    }

    function providerLedgerOrderIdFormatter(value, row, index) {
        if (!value) {
            return '<span class="text-muted">-</span>';
        }
        var orderNum = parseInt(value);
        if (isNaN(orderNum)) {
            return value.toString();
        }
        return '<a href="' + "<?= base_url() ?>" + '/admin/orders/veiw_orders/' + orderNum + '" target="_blank" class="text-primary" style="text-decoration: underline;">Order #' + orderNum + '</a>';
    }

    function providerLedgerLoad() {
        var providerId = $('#providerLedgerSelect').val();
        var table = $('#provider_ledger_list');
        $('#providerLedgerError').addClass('d-none').text('');
        if (!providerId) {
            $('#providerLedgerEmpty').removeClass('d-none');
            $('#providerLedgerSummary, #providerLedgerTableWrap').addClass('d-none');
            $('#ledgerBookingNote').addClass('d-none');
            table.bootstrapTable('removeAll');
            return;
        }

        var query = $.param(providerLedgerDateRange);
        $.getJSON(providerLedgerDetailsUrl + '/' + providerId + '?' + query)
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
                $('#ledgerGrossTotal').text(providerLedgerMoney(response.bookings.gross_total));
                $('#ledgerOnlineReceived').text(providerLedgerMoney(response.bookings.online_received));
                $('#ledgerCashCollected').text(providerLedgerMoney(response.bookings.cash_collected));
                $('#ledgerAdminCashReceived').text(providerLedgerMoney(response.bookings.admin_cash_received));
                $('#ledgerCommissionRate').text(Number(response.commission_percentage || 0).toFixed(2));
                $('#ledgerCommissionTotal').text(providerLedgerMoney(response.bookings.commission_total));
                $('#ledgerProviderNet').text(providerLedgerMoney(response.bookings.provider_net));
                $('#ledgerSettlementDue').text(providerLedgerMoney(response.bookings.settlement_due));
                $('#ledgerBookingCount').text(response.bookings.completed + ' completed / ' + response.bookings.total + ' total bookings');
                $('#ledgerBookingNote').text('Pending bookings: ' + response.bookings.pending + ' | Cancelled: ' + response.bookings.cancelled + '. Date range uses booking service date and settlement entry date. Commission is calculated on completed, non-refunded bookings and paid add-ons.')
                    .removeClass('d-none');
                table.bootstrapTable('refreshOptions', {
                    url: providerLedgerHistoryUrl + '/' + providerId,
                    pageNumber: 1
                });
            })
            .fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to load provider ledger.';
                $('#providerLedgerError').removeClass('d-none').text(message);
            });
    }

    $('#providerLedgerSelect').on('change', providerLedgerLoad);
    $('#providerLedgerApplyDates').on('click', function () {
        var dateFrom = $('#providerLedgerDateFrom').val();
        var dateTo = $('#providerLedgerDateTo').val();
        if (dateFrom && dateTo && dateFrom > dateTo) {
            $('#providerLedgerError').removeClass('d-none').text('Date From must be before or equal to Date To.');
            return;
        }
        providerLedgerDateRange = { date_from: dateFrom, date_to: dateTo };
        providerLedgerLoad();
    });
    $('#providerLedgerClearDates').on('click', function () {
        $('#providerLedgerDateFrom, #providerLedgerDateTo').val('');
        providerLedgerDateRange = { date_from: '', date_to: '' };
        providerLedgerLoad();
    });
</script>
