<?php
$fcLang = strtolower(str_replace('_', '-', get_current_language()));
$fcLocalePath = FCPATH . 'backend/assets/fullcalendar/dist/locales/' . $fcLang . '.global.min.js';
$fcLocaleExists = ($fcLang !== 'en') && file_exists($fcLocalePath);
?>
<style>
    .fc {
        --fc-button-bg-color: var(--primary-color);
        --fc-button-border-color: var(--primary-color);
        --fc-button-hover-bg-color: var(--primary-color);
        --fc-button-hover-border-color: var(--primary-color);
        --fc-button-active-bg-color: var(--primary-color);
        --fc-button-active-border-color: var(--primary-color);
        --fc-now-indicator-color: var(--primary-color);
    }

    .fc .fc-day-today {
        background-color: transparent !important;
    }

    .fc .fc-day-today .fc-daygrid-day-number {
        background-color: var(--primary-color) !important;
        color: #fff !important;
        border-radius: 50% !important;
        width: 28px !important;
        height: 28px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 4px 8px !important;
        padding: 0 !important;
        font-weight: 600 !important;
    }

    .orders-calendar-card {
        min-height: 650px;
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 4px;
        padding: 16px;
    }

    .fc .fc-toolbar.fc-header-toolbar {
        margin-bottom: 16px;
    }

    .fc .fc-toolbar-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #34395e;
    }

    .fc .fc-button-group {
        gap: 3px !important;
    }

    .fc .fc-button-primary {
        border-radius: 4px !important;
        padding: 6px 12px !important;
        font-size: 0.875rem !important;
        font-weight: 400 !important;
        text-transform: capitalize !important;
        box-shadow: none !important;
    }

    .fc .fc-button-primary:not(:disabled).fc-button-active {
        filter: brightness(0.88);
    }

    .fc .fc-prev-button,
    .fc .fc-next-button {
        padding: 6px 11px !important;
    }

    .fc .fc-scrollgrid,
    .fc .fc-scrollgrid-section>*,
    .fc td,
    .fc th {
        border-color: #e3e6f0 !important;
        border-radius: 5px !important;
    }

    .fc .fc-col-header-cell {
        background: #f9fafc;
        padding: 10px 0;
    }

    .fc .fc-col-header-cell-cushion {
        color: #6c757d;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
    }

    .fc .fc-daygrid-day-number {
        color: #495057;
        font-size: 0.85rem;
        padding: 6px 8px;
        text-decoration: none;
    }

    .fc .fc-day-other .fc-daygrid-day-number {
        color: #adb5bd;
    }

    .fc .fc-event {
        cursor: pointer;
        background-color: color-mix(in srgb, var(--primary-color) 10%, transparent) !important;
        color: #000 !important;
        border: none !important;
        border-left: 3px solid var(--primary-color) !important;
        border-radius: 3px !important;
        padding: 3px 6px !important;
        font-size: 0.8rem !important;
        font-weight: 600 !important;
        margin: 2px 4px !important;
        transition: background-color 0.15s ease, filter 0.15s ease;
    }

    .fc .fc-event:hover {
        background-color: color-mix(in srgb, var(--primary-color) 15%, transparent) !important;
        filter: brightness(0.95);
        text-decoration: none !important;
    }

    .fc .fc-event .fc-event-title,
    .fc .fc-event .fc-event-main,
    .fc .fc-event .fc-event-main-frame {
        color: #000 !important;
    }

    .fc .fc-daygrid-event-dot {
        display: none;
    }

    .fc .fc-daygrid-day-bottom {
        display: flex !important;
        justify-content: flex-end !important;
    }

    .fc .fc-daygrid-more-link {
        font-size: 0.78rem !important;
        font-weight: 600 !important;
        color: var(--primary-color) !important;
        background-color: color-mix(in srgb, var(--primary-color) 12%, transparent) !important;
        padding: 3px 8px !important;
        border-radius: 4px !important;
        text-decoration: none !important;
        margin: 2px 2px 4px 4px !important;
    }

    .fc .fc-daygrid-more-link:hover {
        background-color: color-mix(in srgb, var(--primary-color) 20%, transparent) !important;
    }

    .orders-view-tabs {
        border-bottom: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .orders-view-tabs .nav-link {
        color: #5f6368;
        font-weight: 500;
        border: none !important;
        padding: 8px 12px !important;
        border-radius: 4px !important;
        transition: color 0.2s ease, background-color 0.2s ease;
    }

    .orders-view-tabs .nav-link:hover {
        color: #202124;
        background-color: #f1f3f4;
    }

    .orders-view-tabs .nav-link.active {
        color: var(--primary-color) !important;
        background: color-mix(in srgb, var(--primary-color) 12%, transparent) !important;
    }

    .orders-view-tabs .nav-link i {
        font-size: 1.05rem;
    }

    .fc-toolbar-chunk {
        margin-right: 0.55rem !important;
    }

    @media (max-width: 768px) {
        #orders_pane_calendar {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }

        #orders_calendar {
            min-width: 750px !important;
        }
    }

    .fc .fc-popover {
        z-index: 1050 !important;
        background: #fff !important;
        border: 1px solid #e3e6f0 !important;
        border-radius: 6px !important;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12) !important;
    }

    .fc .fc-popover-header {
        background: #f9fafc !important;
        padding: 8px 12px !important;
        border-radius: 6px 6px 0 0 !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        color: #34395e !important;
    }

    .fc .fc-popover-body {
        padding: 6px !important;
    }
</style>
<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('bookings', 'Bookings') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('bookings', 'Bookings') ?></div>
            </div>
        </div>
        <div class="container-fluid card">
            <div class="row">
                <div class="col-md col-lg col-sm">
                    <div class="row mt-4 mb-3 align-items-center">
                        <ul class="nav orders-view-tabs ml-3 mb-2" role="tablist">
                            <li class="nav-item ml-3">
                                <a class="nav-link active" id="orders_tab_list" data-toggle="tab"
                                    href="#orders_pane_list" role="tab">
                                    <i class="fas fa-list"></i>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="orders_tab_calendar" data-toggle="tab"
                                    href="#orders_pane_calendar" role="tab">
                                    <i class="fas fa-calendar-alt"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="orders-calendar-card mb-4">
                        <div class="tab-content">
                            <div class="tab-pane fade" id="orders_pane_calendar" role="tabpanel">
                                <div id="orders_calendar"></div>
                            </div>
                            <div class="tab-pane fade show active" id="orders_pane_list" role="tabpanel">
                                <div class="d-flex flex-wrap align-items-center mb-3">
                                    <div class="input-group mb-2" style="width: auto;">
                                        <input type="text" class="form-control" id="customSearch"
                                            placeholder="<?= labels('search_here', 'Search here!') ?>"
                                            aria-label="Search" aria-describedby="customSearchBtn">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button">
                                                <i class="fa fa-search d-inline"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <button class="btn btn-secondary ml-2 mb-2 filter_button" id="filterButton">
                                        <span class="material-symbols-outlined mt-1">
                                            filter_alt
                                        </span>
                                    </button>
                                    <div class="dropdown d-inline ml-2 mb-2">
                                        <button class="btn export_download dropdown-toggle" type="button"
                                            id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                            aria-expanded="false">
                                            <?= labels('download', 'Download') ?>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item"
                                                onclick="custome_export('pdf','odrer list','partner_bookings_list');"><?= labels('pdf', 'PDF') ?></a>
                                            <a class="dropdown-item"
                                                onclick="custome_export('excel','odrer list','partner_bookings_list');"><?= labels('excel', 'Excel') ?></a>
                                            <a class="dropdown-item"
                                                onclick="custome_export('csv','odrer list','partner_bookings_list')"><?= labels('csv', 'CSV') ?></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-borderd" data-fixed-columns="true"
                                        id="partner_bookings_list" data-pagination-successively-size="2"
                                        data-show-export="false" data-export-types="['txt','excel','csv']"
                                        data-export-options='{"fileName": "invoice-order-list","ignoreColumn": ["action"]}'
                                        data-auto-refresh="true" data-show-columns="false" data-search="false"
                                        data-show-refresh="false" data-toggle="table"
                                        data-page-list="[5, 10, 25, 50, 100, 200, All]" data-side-pagination="server"
                                        data-pagination="true" data-url="<?= base_url("partner/orders/list") ?>"
                                        data-sort-name="id" data-sort-order="desc" data-query-params="orders_query1">
                                        <thead>
                                            <tr>
                                                <th data-field="id" class="text-center" data-sortable="true">
                                                    

                                                                                                    <?= labels('id', 'ID') ?></th>

                                                    
                                                                                                <th data-field="date_of_service" class="text-center">
                                                    <?= labels('date_of_service', 'Date of Service') ?></th>
                                                <th data-field="invoice_no" class="text-center" data-visible="false"
                                                    data-sortable="true"><?= labels('invoice_no', 'Invoice No') ?></th>
                                                <th data-field="user_id" class="text-center" d
                                                    ata-visible="false"

                                                                                                    data-sortable="true"><?= labels('user_id', 'User id') ?></th>

                                                    
                                                                                                <th data-field="customer" class="text-center">
                                                    <?= labels('customer', 'Customer') ?></th>
                                                <th data-field="final_total" class="text-center" data-visible="t
                                                    rue">

                                                                                                    <?= labels('final_total', 'Final total') ?>(<?= $currency ?>)</th
                                                    >

                                                                                                <th data-field="partner" class="text-center" data-sortable="false"
                                                    data-visible="false">
                                                    <?= labels('provider', 'Provider') ?></th>

                                                                                                <th data-field="total" class="text-center" data-visible="false">
                                                    <?= labels('total', 'Total') ?>(<?= $currency ?>)</th>
                                                <th data-field="promo_code" class="text-center" data-visible="false">
                                                    <?= labels('promo_code', 'Promo code') ?></th>
                                                <th data-field="promo_dis
                                                    count" class="text-center"

                                                                                                    data-visible="false">

                                                
                                                                                                        <?= labels('promo_discount', 'Promo discount') ?></th>

                                                    
                                                                                                <th data-field="admin_earnings" class="text-center"
                                                    data-visible="false">
                                                    <?= labels('admin_earning', 'Admin Earning') ?>

                                                                                                </th>

                                                    
                                                                                                <th data-field="partner_earnings" class="text-center"

                                                    
                                                                                                    data-visible="false">

                                                    
                                                                                                    <?= labels('provider_earning', 'Provider earnings') ?></th>

                                                                                                <th data-field="address_id" class="text-center" data-visible="fals
                                                    e">

                                                                                                    <?= labels('address_id', 'Address id') ?></th>
                                                <th data-field="address" class="
                                                    text-center" data-visible="false">

                                                                                                    <?= labels('address', 'Address') ?></th>
                                                <th data-field="visiting_charges" class="text-center"
                                                    data-visible="false">
                                                    <?= labels('visiting_charges', 'Visiting Charges') ?></th>
                                                <th data-field="new_start_time_with_date" class="text-center">
                                                    <?= labels('starting_time', 'Starting time') ?></th>
                                                <th data-field="new_end_time_with_date" class="text-center">
                                                    <?= labels('ending_time', 'Ending time') ?></th>
                                                <th data-field="duration" class="text-center" data-visible="false">
                                                    <?= labels('duration', 'Duration') ?></th>
                                                <th data-field="status" class="text-center">
                                                    <?= labels('status', 'Status') ?></th>
                                                <th data-field="remarks" class="text-center" data-visible="false">
                                                    <?= labels('remarks', 'Remarks') ?></th>
                                                <th data-field="operations" class="text-center"
                                                    data-events="orders_events">
                                                    <?= labels('operations', 'Operations') ?></th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<div id="filterBackdrop"></div>
<div class="drawer" id="filterDrawer">
    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="bg-new-primary" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <div class="bg-white m-3 text-new-primary"
                            style="box-shadow: 0px 8px 26px #00b9f02e; display: inline-block; padding: 10px; height: 45px; width: 45px; border-radius: 15px;">
                            <span class="material-symbols-outlined">
                                filter_alt
                            </span>
                        </div>
                        <h3 class="mb-0" style="display: inline-block; font-size: 16px; margin-left: 10px;">Filters</h3>
                    </div>
                    <div id="cancelButton" style="cursor: pointer;">
                        <span class="material-symbols-outlined mr-2">
                            cancel
                        </span>
                    </div>
                </div>
                <div class="row mt-4 mx-2">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label
                                for="order_status_filter"><?= labels('filter_booking_by_status', 'Filter Bookings by Status') ?></label>
                            <select name="order_status_filter" id="order_status_filter" class="form-control select2">
                                <option value=""><?= labels('select', 'Select') ?>-</option>
                                <option value="awaiting"><?= labels('awaiting', 'Awaiting') ?></option>
                                <option value="confirmed"><?= labels('confirmed', 'Confirmed') ?></option>
                                <option value="on_the_way"><?= labels('on_the_way', 'On The Way') ?></option>
                                <option value="arrived"><?= labels('arrived', 'Arrived') ?></option>
                                <option value="rescheduled"><?= labels('rescheduled', 'Rescheduled') ?></option>
                                <option value="cancelled"><?= labels('cancelled', 'Cancelled') ?></option>
                                <option value="completed"><?= labels('completed', 'Completed') ?></option>
                                <option value="started"><?= labels('started', 'Started') ?></option>
                                <option value="booking_ended"><?= labels('booking_ended', 'Booking Ended') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group ">
                            <label for="table_filters"><?= labels('table_filters', 'Table filters') ?></label>
                            <div id="columnToggleContainer">
                            </div>
                            <button class="btn btn-primary d-block mt-3"
                                id="apply_filter"><?= labels('apply', 'Apply') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    $(document).ready(function () {
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        var columns = fetchColumns('partner_bookings_list');
        setupColumnToggle('partner_bookings_list', columns, 'columnToggleContainer');

        // Re-init Select2 inside drawer with dropdownParent so it opens above drawer overlay
        $('#filterButton').on('click', function () {
            if ($.fn.select2) {
                if ($('#order_status_filter').hasClass('select2-hidden-accessible')) {
                    $('#order_status_filter').select2('destroy');
                }
                $('#order_status_filter').select2({
                    dropdownParent: $('#filterDrawer')
                });
            }
        });
    });
</script>
<script>
    $("#customSearch").on('keydown', function () {
        $('#partner_bookings_list').bootstrapTable('refresh');
    });
    $("#apply_filter").on('click', function () {
    $('#partner_bookings_list').bootstrapTable('refresh');
    });

    function orders_query1(p) {
        return {
            search: $("#customSearch").val() ? $("#customSearch").val() : p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            order_status_filter: $("#order_status_filter").val()
        };
    }
</script>

<script src="<?= base_url('public/backend/assets/fullcalendar/dist/index.global.min.js') ?>"></script>
<?php if ($fcLocaleExists): ?>
        <script src="<?= base_url('public/backend/assets/fullcalendar/dist/locales/' . $fcLang . '.global.min.js') ?>"></script>
<?php endif; ?>
<script>
    $(document).ready(function () {
        var fcInstance = null;
        var fcInitialised = false;

        function initOrdersCalendar() {
            if (fcInitialised) return;
            fcInitialised = true;
            var calendarEl = document.getElementById('orders_calendar');
            if (!calendarEl || typeof FullCalendar === 'undefined') return;

            fcInstance = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: <?= json_encode($fcLang) ?>,
                headerToolbar: {
                    start: 'title',
                    center: '',
                    end: 'prev,next dayGridMonth,dayGridWeek'
                },
                height: 'auto',
                dayMaxEvents: 3,
                moreLinkText: function (num) { return '+ ' + num; },
                navLinks: true,
                views: {
                    dayGridWeek: { dayMaxEvents: false },
                    dayGridDay:  { dayMaxEvents: false }
                },
                events: function (info, success, failure) {
                    $.ajax({
                        url: '<?= base_url('partner/orders/orders_calendar') ?>',
                        method: 'GET',
                        dataType: 'json',
                        data: {
                            date_from: info.startStr.substr(0, 10),
                            date_to: info.endStr.substr(0, 10),
                            search: $('#customSearch').val() || '',
                            order_status_filter: $('#order_status_filter').val() || ''
                        }
                    }).done(function (resp) {
                        var events = (resp && Array.isArray(resp.events)) ? resp.events
                            : (Array.isArray(resp) ? resp : []);
                        success(events);
                    }).fail(function () {
                        success([]);
                    });
                }
            });
            fcInstance.render();
        }

        initOrdersCalendar();

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr('href');
            if (target === '#orders_pane_calendar' && fcInstance) {
                fcInstance.updateSize();
                fcInstance.refetchEvents();
            } else if (target === '#orders_pane_list') {
                $('#partner_bookings_list').bootstrapTable('refresh');
            }
        });

        $('#customSearch').on('keypress', function (e) {
            if (e.which === 13 && fcInstance) fcInstance.refetchEvents();
        });
        $('#apply_filter').on('click', function () {
            if (fcInstance) fcInstance.refetchEvents();
        });
    });
</script>