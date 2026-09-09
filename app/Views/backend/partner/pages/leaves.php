<?php
$shifts_by_day = $shifts_by_day ?? [];
$day_open_state = $day_open_state ?? [];
$provider_leaves = $provider_leaves ?? [];
?>
<style>
    /* Match admin provider-leaves picker styling for selected cells. */
    .daterangepicker td.active,
    .daterangepicker td.active:hover,
    .daterangepicker td.in-range.start-date,
    .daterangepicker td.in-range.end-date {
        background-color: var(--primary-color) !important;
        color: #fff !important;
    }

    .custom-switch {
        padding-left: 1.25rem !important;
    }

    /* Date range field — inline calendar / clear (×) icon at right of input. */
    .leaves-input-wrap {
        position: relative;
    }

    .leaves-input-wrap #leaves_date_range {
        padding-right: 2rem;
    }

    .leaves-input-icon,
    .leaves-input-clear {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        padding: 0;
        border: none;
        background: transparent;
        font-size: 0.9rem;
        line-height: 1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    .leaves-input-icon {
        color: var(--primary-color);
    }

    .leaves-input-clear {
        color: #adb5bd;
        pointer-events: auto;
    }

    .leaves-input-clear:hover {
        color: #495057;
    }

    .leaves-input-wrap:has(.leaves-input-clear:not(.d-none)) .leaves-input-icon {
        display: none;
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('leaves', 'Leaves') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('leaves', 'Leaves') ?></div>
            </div>
        </div>
        <div class="container-fluid card">
            <div class="row mt-4 mb-3 align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="leaves-input-wrap">
                        <input type="text" class="form-control" id="leaves_date_range" autocomplete="off"
                            placeholder="<?= labels('select_date_range', 'Select date or date range') ?>">
                        <span class="leaves-input-icon" id="leaves_date_range_icon">
                            <i class="fa fa-calendar"></i>
                        </span>
                        <button type="button" class="leaves-input-clear d-none" id="leaves_clear_filter"
                            title="<?= labels('clear_filter', 'Clear Filter') ?>">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="col d-flex justify-content-end mb-2">
                    <button type="button" class="btn btn-primary text-white" id="open_add_leave_modal"
                        data-toggle="modal" data-target="#add_leave_modal">
                        <i class="fas fa-plus"></i> <?= labels('add_leave', 'Add Leave') ?>
                    </button>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-lg">
                    <table class="table" id="partner_leaves_list" data-toggle="table"
                        data-url="<?= base_url('partner/leaves/list') ?>" data-side-pagination="server"
                        data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
                        data-sort-name="leave_date" data-sort-order="desc"
                        data-query-params="partner_leaves_query_params" data-pagination-successively-size="1">
                        <thead>
                            <tr>
                                <th data-field="leave_date" class="text-center" data-sortable="true">
                                    <?= labels('leave_date', 'Leave Date') ?>
                                </th>
                                <th data-field="day" class="text-center" data-sortable="true">
                                    <?= labels('day', 'Day') ?>
                                </th>
                                <th data-field="shifts_count" class="text-center" data-sortable="false">
                                    <?= labels('shifts_on_leave', 'Shifts On Leave') ?>
                                </th>
                                <th data-field="shifts" class="text-center" data-sortable="false">
                                    <?= labels('shift_details', 'Shift Details') ?>
                                </th>
                                <th data-field="action" class="text-center" data-sortable="false">
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

<!-- Booking Conflict Modal -->
<div class="modal fade" id="leave_booking_conflict_modal" tabindex="-1" aria-labelledby="leave_conflict_modal_title"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leave_conflict_modal_title">
                    <i class="fas fa-exclamation-triangle text-warning mr-1"></i>
                    <?= labels('leave_booking_conflict_title', 'Active Bookings Found') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">
                    <?= labels('leave_booking_conflict_subtitle', 'Cannot add leave. Handle the following active bookings first:') ?>
                </p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th><?= labels('order_id', 'Order ID') ?></th>
                                <th><?= labels('date', 'Date') ?></th>
                                <th><?= labels('time', 'Time') ?></th>
                                <th><?= labels('customer', 'Customer') ?></th>
                                <th><?= labels('action', 'Action') ?></th>
                            </tr>
                        </thead>
                        <tbody id="conflict_bookings_tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal"><?= labels('ok', 'OK') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Add Leave Modal -->
<div class="modal fade" id="add_leave_modal" tabindex="-1" aria-labelledby="add_leave_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="add_leave_modal_title"><?= labels('add_leave', 'Add Leave') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
            </div>
            <?= form_open('/partner/leaves/add', ['method' => 'post', 'class' => 'form-submit-event', 'id' => 'partner_leaves_form', 'novalidate' => 'novalidate']); ?>
            <div class="modal-body">
                <p class="text-muted">
                    <?= labels('configure_leaves_subtitle', 'Mark the shifts on which the provider will be on leave.') ?>
                </p>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="leave_from_date"><?= labels('from_date', 'From Date') ?></label>
                            <input type="text" class="form-control leaves-datepicker" id="leave_from_date"
                                name="leave_from_date" autocomplete="off"
                                placeholder="<?= labels('select', 'Select') ?> <?= labels('from_date', 'From Date') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="leave_to_date"><?= labels('to_date', 'To Date') ?></label>
                            <input type="text" class="form-control leaves-datepicker" id="leave_to_date"
                                name="leave_to_date" autocomplete="off"
                                placeholder="<?= labels('select', 'Select') ?> <?= labels('to_date', 'To Date') ?>">
                        </div>
                    </div>
                </div>

                <div id="leaves_days_container" class="mt-2">
                    <div class="text-muted small">
                        <i class="fas fa-info-circle"></i>
                        <?= labels('leaves_select_dates_hint', 'Select From and To dates to list shifts per day. Tick the shifts on which the provider will be on leave.') ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="submit" class="btn btn-primary submit_btn">
                    <i class="fas fa-check"></i> <?= labels('save', 'Save') ?>
                </button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<script>
    // ============================================================
    // Partner Leaves — list table filter + per-row delete.
    // Mirrors admin/pages/provider_leaves.php daterange filter behavior
    // (Custom Range / Today / Tomorrow shortcut tabs + mouseup-driven
    // range classification + reset behavior).
    // ============================================================
    var partnerLeavesUserSorted = false;
    var partnerLeavesDateFrom = '';
    var partnerLeavesDateTo = '';

    function partner_leaves_query_params(p) {
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            user_sorted: partnerLeavesUserSorted ? 1 : 0,
            date_from: partnerLeavesDateFrom,
            date_to: partnerLeavesDateTo,
        };
    }

    $(document).ready(function () {
        $('#partner_leaves_list').on('sort.bs.table', function () {
            partnerLeavesUserSorted = true;
        });

        // Single picker that accepts either a single date or a range (drag across days).
        // Apply inside the picker triggers the table refresh; Cancel/Clear resets the filter.
        // Use the callback-form init so the Apply handler fires reliably even when
        // autoUpdateInput keeps the field empty until the user confirms.
        var $picker = $('#leaves_date_range');
        if (typeof $picker.daterangepicker === 'function') {
            $picker.daterangepicker({
                autoUpdateInput: false,
                locale: {
                    format: 'YYYY-MM-DD',
                    cancelLabel: '<?= labels('clear', 'Clear') ?>',
                },
                opens: 'right',
                alwaysShowCalendars: true,
            });

            $picker.on('apply.daterangepicker', function (ev, picker) {
                var s = picker.startDate.format('YYYY-MM-DD');
                var e = picker.endDate.format('YYYY-MM-DD');
                $picker.val(s === e ? s : s + ' to ' + e);
                partnerLeavesDateFrom = s;
                partnerLeavesDateTo = e;
                $('#leaves_clear_filter').removeClass('d-none');
                $('#partner_leaves_list').bootstrapTable('refresh');
            });

            // Shared reset: wipes input, filter state, and picker's internal staged range
            // back to today/today (library's default). Used by both the picker's Cancel/Clear
            // button and the external "Clear Filter" button — they MUST behave identically.
            function resetLeavesFilter() {
                $picker.val('');
                partnerLeavesDateFrom = '';
                partnerLeavesDateTo = '';
                $('#leaves_clear_filter').addClass('d-none');
                var inst = $picker.data('daterangepicker');
                if (inst) {
                    var todayMoment = moment().startOf('day');
                    inst.setStartDate(todayMoment);
                    inst.setEndDate(todayMoment);
                    if (typeof inst.updateView === 'function') inst.updateView();
                    if (typeof inst.updateCalendars === 'function') inst.updateCalendars();
                }
                $('#partner_leaves_list').bootstrapTable('refresh');
            }

            $picker.on('cancel.daterangepicker', resetLeavesFilter);
            $('#leaves_clear_filter').on('click', resetLeavesFilter);
        }

        // Per-row delete handler. Uses event delegation so re-rendered table rows still bind.
        $(document).on('click', '.delete-leave-btn', function () {
            var leaveDate = $(this).data('date');
            if (!leaveDate) return;

            Swal.fire({
                title: typeof are_your_sure !== 'undefined' ? are_your_sure : '<?= labels('are_you_sure', 'Are you Sure ?') ?>',
                text: typeof be_aware_this_shall_fordid_the_data !== 'undefined' ? be_aware_this_shall_fordid_the_data : '',
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: typeof yes_proceed !== 'undefined' ? yes_proceed : '<?= labels('yes_proceed', 'Yes, Proceed') ?>',
                cancelButtonText: typeof cancel !== 'undefined' ? cancel : '<?= labels('cancel', 'Cancel') ?>',
            }).then(function (result) {
                if (!result.isConfirmed) return;
                $.post(baseUrl + 'partner/leaves/delete', {
                    [csrfName]: csrfHash,
                    leave_date: leaveDate,
                }, function (data) {
                    if (data && data.csrfName) csrfName = data.csrfName;
                    if (data && data.csrfHash) csrfHash = data.csrfHash;
                    if (data && data.error === false) {
                        showToastMessage(data.message, 'success');
                        $('#partner_leaves_list').bootstrapTable('refresh');
                    } else {
                        showToastMessage((data && data.message) ? data.message : '<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
                    }
                });
            });
        });
    });
</script>

<script>
    // ============================================================
    // Add Leave Modal — renders shifts per day in selected date range,
    // AJAX-saves on submit, then refreshes the leaves table.
    // ============================================================
    $(document).ready(function () {
        var $fromDate = $('#leave_from_date');
        var $toDate = $('#leave_to_date');
        var $container = $('#leaves_days_container');
        if (!$fromDate.length || !$toDate.length || !$container.length) return;

        var dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        var dayLabels = {
            sunday: "<?= labels('sunday', 'Sunday') ?>",
            monday: "<?= labels('monday', 'Monday') ?>",
            tuesday: "<?= labels('tuesday', 'Tuesday') ?>",
            wednesday: "<?= labels('wednesday', 'Wednesday') ?>",
            thursday: "<?= labels('thursday', 'Thursday') ?>",
            friday: "<?= labels('friday', 'Friday') ?>",
            saturday: "<?= labels('saturday', 'Saturday') ?>"
        };
        var fullDayLabel = "<?= labels('full_day', 'Full Day') ?>";
        var shiftLabel = "<?= labels('shift', 'Shift') ?>";
        var closedLabel = "<?= labels('closed', 'Closed') ?>";
        var noShiftsLabel = "<?= labels('no_shifts_configured', 'No shifts configured for this day') ?>";
        var hintHtml = '<div class="text-muted small"><i class="fas fa-info-circle"></i> ' +
            "<?= labels('leaves_select_dates_hint', 'Select From and To dates to list shifts per day. Tick the shifts on which the provider will be on leave.') ?>" +
            '</div>';
        var invalidRangeLabel = "<?= labels('to_date_must_be_after_from_date', 'To Date must be on or after From Date') ?>";

        var shiftsByDay = <?= json_encode($shifts_by_day, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var dayOpenState = <?= json_encode($day_open_state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        function fmtIso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
        function fmtDisplay(d) { return pad(d.getDate()) + '-' + pad(d.getMonth() + 1) + '-' + d.getFullYear(); }
        function parseIso(s) {
            if (!s) return null;
            var p = s.split('-');
            if (p.length !== 3) return null;
            var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
            return isNaN(d.getTime()) ? null : d;
        }

        function getDayShifts(dayKey) {
            return {
                enabled: !!dayOpenState[dayKey],
                shifts: Array.isArray(shiftsByDay[dayKey]) ? shiftsByDay[dayKey] : []
            };
        }

        function buildDayCard(dateObj, info) {
            var iso = fmtIso(dateObj);
            var dayKey = dayKeys[dateObj.getDay()];
            var header = fmtDisplay(dateObj) + ' - ' + dayLabels[dayKey];

            var html = '<div class="leaves-day-card mb-3 p-2 border rounded" data-date="' + iso + '" data-day-label="' + header + '">';
            html += '<div class="leaves-day-label font-weight-bold mb-2">' + header + '</div>';

            if (!info.enabled || info.shifts.length === 0) {
                html += '<div class="text-muted small">' +
                    (info.enabled ? noShiftsLabel : closedLabel) + '</div>';
                html += '</div>';
                return html;
            }

            // Bootstrap grid — strict 3 per row at every breakpoint. `col-4` gives
            // each cell 33.333% width; the default row -15px side-margins paired with
            // the card's `px-3` padding keep both card edges flush and produce equal
            // 30px gutters between cells.
            html += '<div class="row mx-0">';
            html += '<div class="col-12 col-sm-6 col-md-4 px-2 mb-2">' +
                '<label class="custom-switch mb-0">' +
                '<input type="checkbox" class="custom-switch-input leave-full-day" id="leave_full_' + iso + '" data-date="' + iso + '">' +
                '<span class="custom-switch-indicator"></span>' +
                '<span class="custom-switch-description">' + fullDayLabel + '</span>' +
                '</label>' +
                '</div>';

            info.shifts.forEach(function (sh, idx) {
                var id = 'leave_' + iso + '_' + idx;
                var lbl = (info.shifts.length === 1 ? shiftLabel : (shiftLabel + ' ' + (idx + 1))) +
                    ' (' + sh.start + ' - ' + sh.end + ')';
                html += '<div class="col-12 col-sm-6 col-md-4 px-2 mb-2">' +
                    '<label class="custom-switch mb-0">' +
                    '<input type="checkbox" class="custom-switch-input leave-shift-checkbox" id="' + id + '"' +
                    ' name="leave_shifts[' + iso + '][]" value="' + idx + '"' +
                    ' data-shift-start="' + sh.start + '" data-shift-end="' + sh.end + '"' +
                    ' data-shift-label="' + lbl + '">' +
                    '<span class="custom-switch-indicator"></span>' +
                    '<span class="custom-switch-description">' + lbl + '</span>' +
                    '</label>' +
                    '</div>';
            });

            html += '</div></div>';
            return html;
        }

        function render() {
            var fromD = parseIso($fromDate.val());
            var toD = parseIso($toDate.val());

            if (!fromD || !toD) {
                $container.html(hintHtml);
                return;
            }
            if (toD < fromD) {
                $container.html('<div class="text-danger small"><i class="fas fa-exclamation-circle"></i> ' + invalidRangeLabel + '</div>');
                return;
            }

            var html = '';
            var cur = new Date(fromD.getFullYear(), fromD.getMonth(), fromD.getDate());
            var end = new Date(toD.getFullYear(), toD.getMonth(), toD.getDate());
            var safety = 0;
            while (cur <= end && safety++ < 366) {
                var dayKey = dayKeys[cur.getDay()];
                html += buildDayCard(cur, getDayShifts(dayKey));
                cur.setDate(cur.getDate() + 1);
            }
            $container.html(html || hintHtml);
        }

        var pickerMinDate = moment().startOf('day');

        if (typeof $fromDate.daterangepicker === 'function') {
            $fromDate.daterangepicker({
                locale: { format: 'YYYY-MM-DD' },
                singleDatePicker: true,
                autoUpdateInput: false,
                minDate: pickerMinDate
            });
            $toDate.daterangepicker({
                locale: { format: 'YYYY-MM-DD' },
                singleDatePicker: true,
                autoUpdateInput: false,
                minDate: pickerMinDate
            });
            $fromDate.on('apply.daterangepicker', function (ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD'));
                var toPicker = $toDate.data('daterangepicker');
                if (toPicker) toPicker.minDate = picker.startDate.clone();
                if ($toDate.val() && moment($toDate.val(), 'YYYY-MM-DD').isBefore(picker.startDate)) {
                    $toDate.val('');
                }
                render();
            });
            $toDate.on('apply.daterangepicker', function (ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD'));
                render();
            });
        } else {
            $fromDate.attr('type', 'date');
            $toDate.attr('type', 'date');
            $fromDate.add($toDate).on('change', render);
        }

        $container.on('change', '.leave-full-day', function () {
            var iso = $(this).data('date');
            var checked = this.checked;
            $container.find('.leaves-day-card[data-date="' + iso + '"] .leave-shift-checkbox')
                .prop('checked', checked);
        });
        $container.on('change', '.leave-shift-checkbox', function () {
            var $card = $(this).closest('.leaves-day-card');
            var $shifts = $card.find('.leave-shift-checkbox');
            var $full = $card.find('.leave-full-day');
            $full.prop('checked', $shifts.length > 0 && $shifts.filter(':checked').length === $shifts.length);
        });

        function resetPickerToTodayIfEmpty($input) {
            if ($input.val()) return;
            var picker = $input.data('daterangepicker');
            if (!picker) return;
            var today = moment().startOf('day');
            picker.setStartDate(today);
            picker.setEndDate(today);
            if (typeof picker.updateCalendars === 'function') picker.updateCalendars();
            else if (typeof picker.updateView === 'function') picker.updateView();
            $input.val('');
        }
        $fromDate.on('mousedown focus', function () { resetPickerToTodayIfEmpty($fromDate); });
        $toDate.on('mousedown focus', function () { resetPickerToTodayIfEmpty($toDate); });

        // Reset modal state every time it's reopened so leftovers from a prior add
        // don't leak into the next session.
        $('#add_leave_modal').on('hidden.bs.modal', function () {
            $fromDate.val('');
            $toDate.val('');
            $container.html(hintHtml);
        });
    });

    // Local submit handler — AJAX post, toast on success, refresh table, close modal.
    $(function () {
        var $form = $('#partner_leaves_form');
        if (!$form.length) return;
        $form.removeClass('form-submit-event');
        $form.on('submit', onLeavesFormSubmit);
    });

    function showLeaveBookingConflictModal(bookings) {
        var tbody = document.getElementById('conflict_bookings_tbody');
        if (!tbody) return;
        var rows = '';
        if (bookings.length === 0) {
            rows = '<tr><td colspan="5" class="text-center text-muted"><?= labels('no_bookings_found', 'No bookings found') ?></td></tr>';
        } else {
            var baseUrl = '<?= base_url('partner/orders/veiw_orders/') ?>';
            bookings.forEach(function (b) {
                rows += '<tr>'
                    + '<td>#' + (b.order_id || '') + '</td>'
                    + '<td>' + (b.date || '') + '</td>'
                    + '<td>' + (b.starting_time || '') + '</td>'
                    + '<td>' + (b.customer_name || '') + '</td>'
                    + '<td><a href="' + baseUrl + (b.order_id || '') + '" target="_blank" class="btn btn-primary btn-sm" title="<?= labels('view_the_order', 'View the Order') ?>"><i class="fa fa-eye"></i></a></td>'
                    + '</tr>';
            });
        }
        tbody.innerHTML = rows;
        $('#leave_booking_conflict_modal').modal('show');
    }

    function onLeavesFormSubmit(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var $form = $(this);
        var $submitBtn = $form.find('.submit_btn');
        var btnHtml = $submitBtn.html();
        var formData = new FormData(this);
        formData.append(csrfName, csrfHash);

        $.ajax({
            type: 'POST',
            url: $form.attr('action'),
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            dataType: 'json',
            beforeSend: function () {
                $submitBtn.prop('disabled', true);
                $submitBtn.html('<div class="spinner-border text-light spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>');
            },
            success: function (response) {
                if (response && response.csrfName) csrfName = response.csrfName;
                if (response && response.csrfHash) csrfHash = response.csrfHash;

                if (response && response.error === false) {
                    showToastMessage(response.message, 'success');
                    $('#add_leave_modal').modal('hide');
                    $('#partner_leaves_list').bootstrapTable('refresh');
                } else if (response && response.data && response.data.booking_conflict) {
                    $('#add_leave_modal').modal('hide');
                    showLeaveBookingConflictModal(response.data.bookings || []);
                } else {
                    var msg = (response && typeof response.message === 'string')
                        ? response.message
                        : '<?= labels('something_went_wrong', 'Something went wrong') ?>';
                    showToastMessage(msg, 'error');
                }
            },
            error: function () {
                showToastMessage('<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
            },
            complete: function () {
                $submitBtn.prop('disabled', false);
                $submitBtn.html(btnHtml);
            }
        });
    }
</script>