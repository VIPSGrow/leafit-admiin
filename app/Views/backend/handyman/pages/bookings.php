<?= $this->extend('backend/handyman/layouts/main') ?>

<?= $this->section('content') ?>

<div class="section-body">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">

                <!-- Table -->
                <section class="card-body p-0">
                    <div id="bookings_toolbar">
                        <select id="status_filter" class="form-control select2" style="width: 200px;">
                            <option value=""><?= labels('all_statuses', 'All Statuses') ?></option>
                            <option value="assigned"><?= labels('assigned', 'Assigned') ?></option>
                            <option value="accepted"><?= labels('accepted', 'Accepted') ?></option>
                            <option value="rejected"><?= labels('rejected', 'Rejected') ?></option>
                            <option value="on_the_way"><?= labels('on_the_way', 'On The Way') ?></option>
                            <option value="arrived"><?= labels('arrived', 'Arrived') ?></option>
                            <option value="started"><?= labels('started', 'Started') ?></option>
                            <option value="booking_ended"><?= labels('booking_ended', 'Booking Ended') ?></option>
                            <option value="completed"><?= labels('completed', 'Completed') ?></option>
                        </select>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="bookings_table" data-toggle="table"
                            data-side-pagination="server" data-pagination="true"
                            data-url="<?= base_url('handyman/bookings/list_data') ?>" data-sort-name="o.id"
                            data-sort-order="desc" data-page-list="[10, 25, 50, 100]"
                            data-pagination-successively-size="2" data-show-refresh="true" data-search="true"
                            data-show-columns="false" data-show-export="false" data-toolbar="#bookings_toolbar"
                            data-query-params="bookingQueryParams">
                            <thead class="thead-light">
                                <tr>
                                    <th data-field="id" data-sortable="true" class="text-center" style="width: 80px;">
                                        <?= labels('booking_id', 'Booking ID') ?>
                                    </th>
                                    <th data-field="customer_name" class="text-center" data-sortable="false">
                                        <?= labels('customer', 'Customer') ?>
                                    </th>
                                    <th data-field="service_date" data-sortable="true"
                                        data-sort-name="o.date_of_service" class="text-center" style="width: 130px;">
                                        <?= labels('date_of_service', 'Service Date') ?>
                                    </th>
                                    <th data-field="time_range" data-sortable="false" class="text-center"
                                        style="width: 130px;">
                                        <?= labels('time', 'Time') ?>
                                    </th>
                                    <th data-field="service_type_badge" data-sortable="false" class="text-center"
                                        style="width: 110px;">
                                        <?= labels('service_type', 'Service Type') ?>
                                    </th>
                                    <th data-field="address_short" data-sortable="false">
                                        <?= labels('address', 'Address') ?>
                                    </th>
                                    <th data-field="amount_display" data-sortable="true" data-sort-name="o.final_total"
                                        class="text-center" style="width: 110px;">
                                        <?= labels('amount', 'Amount') ?> (<?= esc($currency) ?>)
                                    </th>
                                    <th data-field="status_badge" data-sortable="true" data-sort-name="o.status"
                                        class="text-center" style="width: 110px;">
                                        <?= labels('status', 'Status') ?>
                                    </th>
                                    <th data-field="remarks_short" class="text-center">
                                        <?= labels('remarks', 'Remarks') ?>
                                    </th>
                                    <th data-field="operations" data-sortable="false" class="text-center"
                                        style="width: 80px;" data-events="bookingEvents">
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
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
    window.bookingEvents = {
        'click .btn-view-booking': function (e, value, row) {
            window.location.href = baseUrl + 'handyman/bookings/' + row.id;
        },
        'click .btn-chat-booking': function (e, value, row) {
            var userId = $(e.currentTarget).data('user-id');
            window.location.href = baseUrl + 'handyman/chat?order_id=' + row.id + '&customer_id=' + userId;
        },
    };

    window.bookingQueryParams = function (p) {
        return {
            search: p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            status_filter: $('#status_filter').val() || '',
        };
    };

    $(document).on('change', '#status_filter', function () {
        $('#bookings_table').bootstrapTable('refresh');
    });
</script>
<?= $this->endSection() ?>