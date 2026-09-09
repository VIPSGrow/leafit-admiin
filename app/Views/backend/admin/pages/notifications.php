<!-- Main Content -->
<style>
    /* Clickable notification rows: same redirect as dropdown */
    #admin_notification_list tbody tr[data-notification-id] {
        cursor: pointer;
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('notifications', 'Notifications') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('admin/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= labels('notifications', 'Notifications') ?></div>
            </div>
        </div>

        <div class="container-fluid card">
            <div class="row">
                <div class="col-md col-lg col-sm">
                    <!-- Search UI (same UX pattern as other admin tables) -->
                    <div class="row mt-4 mb-3">
                        <div class="col-md-4 col-sm-2 mb-2">
                            <div class="input-group">
                                <input type="text" class="form-control" id="adminNotificationsSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="adminNotificationsSearchBtn">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" id="adminNotificationsSearchBtn" type="button">
                                        <i class="fa fa-search d-inline"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="table-responsive mt-3">
                        <!--
                            Admin notifications table (minimal + fast)
                            ------------------------------------------------
                            Why:
                            - Admin users need the same "view all" page as providers.
                            - We keep this payload ultra-lean for speed and security.
                            
                            Endpoint:
                            - GET admin/notifications/table
                        -->
                        <table class="table table-hover table-borderd"
                            id="admin_notification_list"
                            data-toggle="table"
                            data-url="<?= base_url('admin/notifications/table') ?>"
                            data-side-pagination="server"
                            data-search="false"
                            data-pagination="true"
                            data-page-list="[10, 25, 50, 100]"
                            data-sort-name="time_ago"
                            data-sort-order="desc"
                            data-query-params="admin_notification_query_params"
                            data-row-attributes="admin_notification_row_attributes">
                            <thead>
                                <tr>
                                    <th data-field="id" class="text-center" data-visible="false"><?= labels('id', 'ID') ?></th>
                                    <th data-field="title" class="text-center"><?= labels('title', 'Title') ?></th>
                                    <th data-field="message" class="text-center" data-formatter="admin_notification_message_formatter"><?= labels('message', 'Message') ?></th>
                                    <th data-field="time_ago" class="text-center"><?= labels('time', 'Time') ?></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    /**
     * Admin notifications: custom search UI
     *
     * We avoid bootstrap-table's "live" search to reduce requests.
     * Search runs only when clicking the button or pressing Enter.
     */
    $("#adminNotificationsSearchBtn").on('click', function() {
        $('#admin_notification_list').bootstrapTable('refresh');
    });

    $("#adminNotificationsSearch").on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#adminNotificationsSearchBtn').click();
        }
    });

    function admin_notification_query_params(p) {
        return {
            search: $("#adminNotificationsSearch").val() ? $("#adminNotificationsSearch").val() : p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset
        };
    }

    /**
     * Set data attributes on each row so notification_redirects.js can redirect on row click.
     * Same redirect rules as the dropdown (event_type + context_data).
     */
    function admin_notification_row_attributes(row, index) {
        var ctx = (row.context_data && typeof row.context_data === 'object') ?
            JSON.stringify(row.context_data) :
            (row.context_data ? String(row.context_data) : '');
        return {
            'data-notification-id': row.id != null ? String(row.id) : '',
            'data-notification-event-type': (row.event_type != null && row.event_type !== '') ? String(row.event_type) : '',
            'data-notification-event-key': (row.event_key != null && row.event_key !== '') ? String(row.event_key) : '',
            'data-notification-type': (row.notification_type != null && row.notification_type !== '') ? String(row.notification_type) : '',
            'data-href': (row.url != null && row.url !== '') ? String(row.url) : '',
            'data-notification-context': ctx,
            'data-notification-panel': 'admin'
        };
    }

    /**
     * Message column formatter
     *
     * Requirement:
     * - If message is more than 50 characters, show Read more/less toggle.
     *
     * Security:
     * - Escape HTML to prevent injection.
     */
    function admin_escape_html(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function admin_notification_message_formatter(value, row, index) {
        var msg = (value === null || value === undefined) ? '' : String(value);
        var safeFull = admin_escape_html(msg);

        if (msg.length <= 50) {
            return '<span>' + safeFull + '</span>';
        }

        var id = (row && row.id !== undefined && row.id !== null) ? String(row.id) : ('idx_' + index);
        var shortText = admin_escape_html(msg.substring(0, 50) + '...');

        return '' +
            '<span class="admin-msg-short" id="admin_msg_short_' + id + '">' + shortText + '</span>' +
            '<span class="admin-msg-full d-none" id="admin_msg_full_' + id + '">' + safeFull + '</span>' +
            ' <a href="#" class="small" id="admin_msg_toggle_' + id + '" data-state="short" onclick="return admin_toggle_message(\'' + id + '\');">' +
            '<?= addslashes(labels('read_more', 'Read more')) ?>' +
            '</a>';
    }

    function admin_toggle_message(id) {
        var $short = $('#admin_msg_short_' + id);
        var $full = $('#admin_msg_full_' + id);
        var $toggle = $('#admin_msg_toggle_' + id);

        if (!$short.length || !$full.length || !$toggle.length) return false;

        var state = $toggle.attr('data-state') || 'short';
        if (state === 'short') {
            $short.addClass('d-none');
            $full.removeClass('d-none');
            $toggle.attr('data-state', 'full');
            $toggle.text('<?= addslashes(labels('read_less', 'Read less')) ?>');
        } else {
            $full.addClass('d-none');
            $short.removeClass('d-none');
            $toggle.attr('data-state', 'short');
            $toggle.text('<?= addslashes(labels('read_more', 'Read more')) ?>');
        }

        return false;
    }
</script>