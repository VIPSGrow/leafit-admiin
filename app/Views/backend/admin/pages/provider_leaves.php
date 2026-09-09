<?php
$fcLang         = strtolower(str_replace('_', '-', get_current_language()));
$fcLocalePath   = FCPATH . 'backend/assets/fullcalendar/dist/locales/' . $fcLang . '.global.min.js';
$fcLocaleExists = ($fcLang !== 'en') && file_exists($fcLocalePath);
?>
<!-- FullCalendar v6 ships CSS inlined into the JS bundle — no separate <link> needed. -->

<style>
    /* Daterangepicker selected cells use the theme primary color so the
       picker matches the admin UI. */
    .daterangepicker td.active,
    .daterangepicker td.active:hover,
    .daterangepicker td.in-range.start-date,
    .daterangepicker td.in-range.end-date {
        background-color: var(--primary-color) !important; color: #fff !important;
    }

    /* Today's date highlight in daterangepicker — primary theme color with reduced opacity,
       and the current date cell is styled as a circle. */
    .daterangepicker td.today:not(.active) {
        background-color: color-mix(in srgb, var(--primary-color) 15%, transparent) !important;
        color: var(--primary-color) !important;
        border-radius: 50% !important;
    }

    /* FullCalendar v6 — wire its button CSS vars to the admin primary color. */
    .fc {
        --fc-button-bg-color:            var(--primary-color);
        --fc-button-border-color:        var(--primary-color);
        --fc-button-hover-bg-color:      var(--primary-color);
        --fc-button-hover-border-color:  var(--primary-color);
        --fc-button-active-bg-color:     var(--primary-color);
        --fc-button-active-border-color: var(--primary-color);
        --fc-now-indicator-color:        var(--primary-color);
    }

    /* Today's cell background highlight in FullCalendar — primary theme color with reduced opacity. */
    .fc .fc-day-today {
        background-color: transparent !important;
    }

    /* Include today's date number in a clean solid circle of the primary theme color. */
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

    /* Calendar shell — plain bordered panel, matches the bootstrap-table look. */
    .leaves-calendar-card {
        min-height: 650px;
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 4px;
        padding: 16px;
    }

    /* Toolbar */
    .fc .fc-toolbar.fc-header-toolbar { margin-bottom: 16px; }
    .fc .fc-toolbar-title { font-size: 1.25rem; font-weight: 600; color: #34395e; }

    /* Buttons — standard solid bootstrap buttons, 4px corners. */
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
    .fc .fc-button-primary:not(:disabled).fc-button-active { filter: brightness(0.88); }
    .fc .fc-prev-button, .fc .fc-next-button { padding: 6px 11px !important; }

    /* Grid — square corners, standard table border color. */
    .fc .fc-scrollgrid,
    .fc .fc-scrollgrid-section > *,
    .fc td, .fc th { 
        border-color: #e3e6f0 !important; 
        border-radius: 5px !important;
    }

    /* Header row — light grey fill like a bootstrap <thead>. */
    .fc .fc-col-header-cell { background: #f9fafc; padding: 10px 0; }
    .fc .fc-col-header-cell-cushion {
        color: #6c757d;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
    }

    /* Day cells — plain numbers, no circular treatment. */
    .fc .fc-daygrid-day-number {
        color: #495057;
        font-size: 0.85rem;
        padding: 6px 8px;
        text-decoration: none;
    }
    .fc .fc-day-other .fc-daygrid-day-number { color: #adb5bd; }

    /* Events — flat colored bars with a small radius. */
    .fc .fc-event {
        cursor: pointer;
        background-color: color-mix(in srgb, var(--primary-color) 10%, transparent) !important;
        color: #000 !important;
        border: none !important;
        border-left: 3px solid var(--primary-color) !important;
        border-radius: 3px !important;
        padding: 3px 6px !important;
        font-size: 0.8rem !important;
        margin: 2px 4px !important;
        transition: background-color 0.15s ease, filter 0.15s ease;
    }
    .fc .fc-event:hover {
        background-color: color-mix(in srgb, var(--primary-color) 15%, transparent) !important;
        filter: brightness(0.95);
    }
    .fc-event-main {
        color: var(--primary-color) !important;
    }

    .fc .fc-daygrid-event-dot { display: none; }
    .fc .fc-event .fc-event-line-title {
        font-weight: 600; line-height: 1.2;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: inherit;
    }
    .fc .fc-event .fc-event-line-sub {
        font-size: 0.72rem; opacity: 0.85; line-height: 1.3;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: inherit;
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
        display: inline-flex !important;
        align-items: center !important;
        margin: 2px 2px 4px 4px !important;
        transition: background-color 0.15s ease !important;
    }
    .fc .fc-daygrid-more-link:hover {
        background-color: color-mix(in srgb, var(--primary-color) 20%, transparent) !important;
    }

    /* Per-day event-count chip (top-right of cell). */
    .fc .fc-daygrid-day-top { justify-content: space-between; align-items: center; }
    .leaves-day-count { font-size: 0.72rem; font-weight: 600; color: #6c757d; margin: 6px 8px 0 0; }

    /* Popover + time grid */
    .fc .fc-popover {
        border-radius: 4px !important;
        border: 1px solid #e3e6f0 !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }
    .fc .fc-popover-header { background: #f9fafc !important; padding: 8px 12px !important; }
    .fc .fc-popover-title { font-weight: 600 !important; color: #34395e !important; }
    .fc .fc-timegrid-slot { height: 2.4em !important; }

    /* Tab strip — minimal, sits inside the card. */
    .leaves-view-tabs { border-bottom: none !important; margin: 0 !important; padding: 0 !important; }
    .leaves-view-tabs .nav-link {
        color: #5f6368; font-weight: 500; border: none !important; padding: 8px 12px !important;
        border-radius: 4px !important;
        transition: color 0.2s ease, background-color 0.2s ease;
    }
    .leaves-view-tabs .nav-link:hover { color: #202124; background-color: #f1f3f4; }
    .leaves-view-tabs .nav-link.active {
        color: var(--primary-color) !important; background: color-mix(in srgb, var(--primary-color) 12%, transparent) !important;
    }
    .leaves-view-tabs .nav-link i { font-size: 1.05rem; }

    /* Modal — structured per-shift rows. */
    #leave_details_modal .modal-content, #day_leaves_modal .modal-content { border-radius: 4px; }
    #leave_details_modal .modal-header, #day_leaves_modal .modal-header { padding: 16px 20px; }
    #leave_details_modal .modal-body, #day_leaves_modal .modal-body { padding: 20px; }
    #leave_details_modal .modal-footer, #day_leaves_modal .modal-footer { padding: 12px 20px; }
    #leave_details_modal .shift-row, #day_leaves_modal .shift-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.6rem 0.9rem; border: 1px solid #e3e6f0; border-radius: 4px;
        margin-bottom: 0.5rem; background: #fff;
    }
    #leave_details_modal .shift-row .shift-badge, #day_leaves_modal .shift-row .shift-badge {
        background: var(--primary-color); color: #fff;
        padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;
    }
    #leave_details_modal .shift-row .shift-time, #day_leaves_modal .shift-row .shift-time {
        font-size: 0.875rem; color: #495057; font-weight: 500;
    }

    #day_leaves_modal .day-leave-card {
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    #day_leaves_modal .day-leave-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
    }

    /* Date range field — inline clear (×) button sits within the input. */
    .leaves-input-wrap { position: relative; }
    .leaves-input-wrap #leaves_date_range { padding-right: 2rem; }
    .leaves-input-icon,
    .leaves-input-clear {
        position: absolute; top: 50%; right: 10px; transform: translateY(-50%);
        width: 20px; height: 20px; padding: 0;
        border: none; background: transparent;
        font-size: 0.9rem; line-height: 1; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        pointer-events: none;
    }
    .leaves-input-icon { color: var(--primary-color); }
    .leaves-input-clear { color: #adb5bd; pointer-events: auto; }
    .leaves-input-clear:hover { color: #495057; }
    /* Hide calendar icon when clear (x) is visible. */
    .leaves-input-wrap:has(.leaves-input-clear:not(.d-none)) .leaves-input-icon { display: none; }

    .fc-toolbar-chunk { margin-right: 0.55rem !important; }

    @media (max-width: 768px) {
        #leaves_pane_calendar {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        #leaves_calendar {
            min-width: 750px !important;
        }
    }
</style>

<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('provider_leaves', 'Provider Leaves') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><i class="fas fa-calendar-times text-danger"></i> <?= labels('provider_leaves', 'Provider Leaves') ?></div>
            </div>
        </div>
        <div class="container-fluid card">
            <div class="row mt-3 mb-3 align-items-end leaves-filter-row">
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                        <div class="input-group-append">
                            <button class="btn btn-primary" id="customSearchBtn" type="button">
                                <i class="fa fa-search d-inline"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="leaves-input-wrap">
                        <input type="text" class="form-control" id="leaves_date_range" autocomplete="off"
                            placeholder="<?= labels('select_date_range', 'Select date or date range') ?>">
                        <span class="leaves-input-icon" id="leaves_date_range_icon">
                            <i class="fa fa-calendar"></i>
                        </span>
                        <button type="button" class="leaves-input-clear d-none" id="leaves_date_range_clear" title="<?= labels('clear_filter', 'Clear Filter') ?>">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="leaves-calendar-card mb-4">
                <ul class="nav leaves-view-tabs" role="tablist">
                    <li class="nav-item ml-2">
                        <a class="nav-link active" id="leaves_tab_calendar" data-toggle="tab" href="#leaves_pane_calendar" role="tab">
                            <i class="fas fa-calendar-alt"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="leaves_tab_list" data-toggle="tab" href="#leaves_pane_list" role="tab">
                            <i class="fas fa-list"></i>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="leaves_pane_calendar" role="tabpanel">
                        <div id="leaves_calendar"></div>
                    </div>
                    <div class="tab-pane fade" id="leaves_pane_list" role="tabpanel">
                        <div class="row mb-3">
                            <div class="col-lg">
                                <table class="table" id="provider_leaves_list" data-toggle="table"
                                    data-url="<?= base_url('admin/partners/provider_leaves_list') ?>"
                                    data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 25, 50, 100, 200, All]"
                                    data-sort-name="leave_date" data-sort-order="desc"
                                    data-query-params="provider_leaves_query_params"
                                    data-pagination-successively-size="1">
                                    <thead>
                                        <tr>
                                            <th data-field="leave_date" class="text-center"><?= labels('leave_date', 'Leave Date') ?></th>
                                            <th data-field="partner_id" class="text-center" data-sortable="true"><?= labels('provider_id', 'Provider Id') ?></th>
                                            <th data-field="partner_profile" class="text-center w-25" data-sortable="false"><span style="padding: 130px;"><?= labels('provider', 'Provider') ?></span></th>
                                            <th data-field="day" class="text-center" data-sortable="true"><?= labels('day', 'Day') ?></th>
                                            <th data-field="shifts_count" class="text-center" data-sortable="false"><?= labels('shifts_on_leave', 'Shifts On Leave') ?></th>
                                            <th data-field="shifts" class="text-center" data-sortable="false"><?= labels('shift_details', 'Shift Details') ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Leave Details Modal — populated on calendar event click. -->
<div class="modal fade" id="leave_details_modal" tabindex="-1" aria-labelledby="leave_details_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leave_details_modal_title"><?= labels('leave_details', 'Leave Details') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 class="mb-1" id="leave_details_provider_name"></h6>
                    <div class="text-muted small" id="leave_details_company"></div>
                    <div class="text-muted small" id="leave_details_contact"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-uppercase text-muted small font-weight-bold"><?= labels('leave_date', 'Leave Date') ?></div>
                        <div id="leave_details_date" class="font-weight-medium"></div>
                    </div>
                    <div class="col-6">
                        <div class="text-uppercase text-muted small font-weight-bold"><?= labels('day', 'Day') ?></div>
                        <div id="leave_details_day" class="font-weight-medium"></div>
                    </div>
                </div>
                <div>
                    <div class="text-uppercase text-muted small font-weight-bold mb-2">
                        <?= labels('shifts_on_leave', 'Shifts On Leave') ?>
                        (<span id="leave_details_shifts_count">0</span>)
                    </div>
                    <div id="leave_details_shifts"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Day Leaves Modal — populated on moreLinkClick. -->
<div class="modal fade" id="day_leaves_modal" tabindex="-1" aria-labelledby="day_leaves_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="day_leaves_modal_title"><?= labels('day_leaves', 'Provider Leaves on') ?> <span id="day_leaves_modal_date"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="day_leaves_list_container"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('public/backend/assets/fullcalendar/dist/index.global.min.js') ?>"></script>
<?php if ($fcLocaleExists): ?>
<script src="<?= base_url('public/backend/assets/fullcalendar/dist/locales/' . $fcLang . '.global.min.js') ?>"></script>
<?php endif; ?>

<script>
    // Shared filter state — drives both the bootstrap-table (List tab) and the
    // FullCalendar event source (Calendar tab). Calendar refetches on filter change;
    // table calls bootstrapTable('refresh') which re-invokes provider_leaves_query_params.
    var providerLeavesUserSorted = false;
    var providerLeavesDateFrom   = '';
    var providerLeavesDateTo     = '';

    // bootstrap-table query-params hook — read at the moment of refresh.
    function provider_leaves_query_params(p) {
        return {
            search:      $('#customSearch').val() || p.search || '',
            limit:       p.limit,
            sort:        p.sort,
            order:       p.order,
            offset:      p.offset,
            user_sorted: providerLeavesUserSorted ? 1 : 0,
            date_from:   providerLeavesDateFrom,
            date_to:     providerLeavesDateTo,
        };
    }

    $(document).ready(function () {
        // ============================================================
        // FullCalendar (Calendar tab) — lazy init on first activation.
        // Color per provider via deterministic hash for visual scan.
        // ============================================================
        var fcInstance      = null;
        var fcInitialised   = false;
        var listInitialised = false;

        function initCalendar() {
            if (fcInitialised) return;
            fcInitialised = true;

            var calendarEl = document.getElementById('leaves_calendar');
            if (!calendarEl || typeof FullCalendar === 'undefined') return;

            fcInstance = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: <?= json_encode($fcLang) ?>,
                headerToolbar: {
                    start:  'title',
                    center: '',
                    end:    'prev,next dayGridMonth,timeGridWeek,today'
                },
                height: 'auto',
                dayMaxEvents: 2,
                moreLinkText: function (num) {
                    return '+ ' + num;
                },
                moreLinkClick: function (info) {
                    var dateStr = moment(info.date).format('YYYY-MM-DD');
                    $('#day_leaves_modal_date').text(dateStr);
                    
                    var $container = $('#day_leaves_list_container').empty();
                    
                    var events = info.allSegs.map(function (seg) {
                        return seg.event;
                    });
                    
                    events.forEach(function (ev) {
                        var p = ev.extendedProps || {};
                        var name = p.partner_name || ev.title || '';
                        var company = p.company_name || '';
                        var shifts = Array.isArray(p.shifts) ? p.shifts : [];
                        
                        var $card = $('<div class="card mb-3 border shadow-sm day-leave-card"></div>');
                        var $cardBody = $('<div class="card-body p-3"></div>');
                        
                        $cardBody.append('<h6 class="text-primary mb-1">' + name + '</h6>');
                        if (company) {
                            $cardBody.append('<div class="text-muted small mb-2">' + company + '</div>');
                        }
                        
                        var $shiftsTitle = $('<div class="text-uppercase text-muted small font-weight-bold mb-2">Shifts On Leave (' + shifts.length + ')</div>');
                        $cardBody.append($shiftsTitle);
                        
                        var $shiftsContainer = $('<div></div>');
                        if (shifts.length === 0) {
                            $shiftsContainer.append('<div class="text-muted small">—</div>');
                        } else {
                            shifts.forEach(function (sh) {
                                var $row = $('<div class="shift-row mb-2"></div>');
                                $row.append('<span class="shift-badge">Shift ' + sh.number + '</span>');
                                $row.append('<span class="shift-time">' + sh.start + ' &ndash; ' + sh.end + '</span>');
                                $shiftsContainer.append($row);
                            });
                        }
                        $cardBody.append($shiftsContainer);
                        $card.append($cardBody);
                        $card.on('click', function () {
                            $('#day_leaves_modal').modal('hide');
                            showLeaveDetails(ev);
                        });
                        $container.append($card);
                    });
                    
                    $('#day_leaves_modal').modal('show');
                    return 'none'; // prevents popover from showing
                },
                navLinks: true,
                events: function (info, success, failure) {
                    // When the user sets an explicit date range, scope events to that range
                    // and ignore the calendar's visible-month bounds. Otherwise use the
                    // visible range so navigation re-fetches naturally.
                    var dateFrom = providerLeavesDateFrom || info.startStr.substr(0, 10);
                    var dateTo   = providerLeavesDateTo   || info.endStr.substr(0, 10);

                    $.ajax({
                        url: '<?= base_url('admin/partners/provider_leaves_calendar') ?>',
                        method: 'GET',
                        dataType: 'json',
                        data: {
                            date_from: dateFrom,
                            date_to:   dateTo,
                            search:    $('#customSearch').val() || ''
                        }
                    }).done(function (resp) {
                        var events = (resp && Array.isArray(resp.events)) ? resp.events
                                   : (Array.isArray(resp) ? resp : []);
                        success(events);
                    }).fail(function () {
                        success([]);
                    });
                },
                eventContent: function (arg) {
                    // Two-line event card: provider name + shifts summary.
                    var p = arg.event.extendedProps || {};
                    var name = p.partner_name || arg.event.title || '';
                    var shiftList = Array.isArray(p.shifts) ? p.shifts : [];
                    var sub = shiftList.length
                        ? shiftList.map(function (s) { return 'Shift ' + s.number; }).join(', ')
                        : '';

                    var title = document.createElement('div');
                    title.className = 'fc-event-line-title';
                    title.textContent = name;
                    var subEl = document.createElement('div');
                    subEl.className = 'fc-event-line-sub';
                    subEl.textContent = sub;
                    return { domNodes: [title, subEl] };
                },
                eventClick: function (info) {
                    showLeaveDetails(info.event);
                },
                datesSet: function (info) {
                    $('#leaves_calendar .fc-toolbar-title').addClass('ml-2');
                }
            });
            fcInstance.render();
            $('#leaves_calendar .fc-toolbar-title').addClass('ml-2');
        }

        function showLeaveDetails(event) {
            var p = event.extendedProps || {};
            $('#leave_details_provider_name').text(p.partner_name || '');
            $('#leave_details_company').text(p.company_name || '');
            var contact = '';
            if (p.email) contact += p.email;
            if (p.phone) contact += (contact ? ' · ' : '') + p.phone;
            $('#leave_details_contact').text(contact);
            $('#leave_details_date').text(p.leave_date || '');
            $('#leave_details_day').text(p.day || '');
            $('#leave_details_shifts_count').text(p.shifts_count || 0);

            var shifts = Array.isArray(p.shifts) ? p.shifts : [];
            var $list = $('#leave_details_shifts').empty();
            if (shifts.length === 0) {
                $list.append('<div class="text-muted small">—</div>');
            } else {
                shifts.forEach(function (sh) {
                    var $row = $('<div class="shift-row"></div>');
                    $row.append('<span class="shift-badge"><?= labels('shift', 'Shift') ?> ' + sh.number + '</span>');
                    $row.append('<span class="shift-time">' + sh.start + ' &ndash; ' + sh.end + '</span>');
                    $list.append($row);
                });
            }
            $('#leave_details_modal').modal('show');
        }

        // Lazy-init each view on first activation:
        //   - Calendar must render after its container is visible (FC measures DOM)
        //   - bootstrap-table is initialised by data-toggle; we just call refresh on tab show
        initCalendar();

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr('href');
            if (target === '#leaves_pane_calendar') {
                if (fcInstance) {
                    fcInstance.updateSize();
                    fcInstance.refetchEvents();
                }
            } else if (target === '#leaves_pane_list') {
                if (!listInitialised) {
                    listInitialised = true; // bootstrap-table auto-inits via data-toggle
                } else {
                    $('#provider_leaves_list').bootstrapTable('refresh');
                }
            }
        });

        // ============================================================
        // Search — fans out to both calendar refetch and table refresh.
        // ============================================================
        function applySearchFilter() {
            if (fcInstance) fcInstance.refetchEvents();
            if ($('#provider_leaves_list').length) {
                $('#provider_leaves_list').bootstrapTable('refresh');
            }
        }
        $('#customSearchBtn').on('click', applySearchFilter);
        $('#customSearch').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                applySearchFilter();
            }
        });

        $('#provider_leaves_list').on('sort.bs.table', function () {
            providerLeavesUserSorted = true;
        });

        // ============================================================
        // Date range picker — single picker that accepts a single date or a
        // dragged range. Apply triggers both calendar refetch and table refresh.
        // Cancel/Clear/external "Clear Filter" all reset to empty + refresh.
        // ============================================================
        var $picker = $('#leaves_date_range');
        if (typeof $picker.daterangepicker === 'function') {
            $picker.daterangepicker({
                autoUpdateInput: false,
                locale: { format: 'YYYY-MM-DD', cancelLabel: '<?= labels('clear', 'Clear') ?>' },
                opens: 'right',
                alwaysShowCalendars: true,
            });

            $picker.on('apply.daterangepicker', function (ev, picker) {
                var s = picker.startDate.format('YYYY-MM-DD');
                var e = picker.endDate.format('YYYY-MM-DD');
                $picker.val(s === e ? s : s + ' to ' + e);
                providerLeavesDateFrom = s;
                providerLeavesDateTo   = e;
                $('#leaves_date_range_clear').removeClass('d-none');
                if (fcInstance) {
                    fcInstance.gotoDate(s);
                    fcInstance.refetchEvents();
                }
                if ($('#provider_leaves_list').length) {
                    $('#provider_leaves_list').bootstrapTable('refresh');
                }
            });

            function resetLeavesFilter() {
                $picker.val('');
                providerLeavesDateFrom = '';
                providerLeavesDateTo   = '';
                $('#leaves_date_range_clear').addClass('d-none');
                var inst = $picker.data('daterangepicker');
                if (inst) {
                    var todayMoment = moment().startOf('day');
                    inst.setStartDate(todayMoment);
                    inst.setEndDate(todayMoment);
                    if (typeof inst.updateView === 'function') inst.updateView();
                    if (typeof inst.updateCalendars === 'function') inst.updateCalendars();
                }
                if (fcInstance) fcInstance.refetchEvents();
                if ($('#provider_leaves_list').length) {
                    $('#provider_leaves_list').bootstrapTable('refresh');
                }
            }

            $picker.on('cancel.daterangepicker', resetLeavesFilter);
            $('#leaves_date_range_clear').on('click', resetLeavesFilter);
        }
    });
</script>
