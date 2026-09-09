<?= $this->extend('backend/handyman/layouts/main') ?>

<?= $this->section('content') ?>

<style>
    #handyman_notification_list tbody tr[data-notification-id] {
        cursor: pointer;
    }
</style>

<div class="section-body">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <section class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0"
                            id="handyman_notification_list"
                            data-toggle="table"
                            data-url="<?= base_url('handyman/notifications/table') ?>"
                            data-side-pagination="server"
                            data-search="true"
                            data-show-refresh="true"
                            data-pagination="true"
                            data-page-list="[10, 25, 50, 100]"
                            data-sort-name="time_ago"
                            data-sort-order="desc"
                            data-row-attributes="handymanNotificationRowAttributes">
                            <thead class="thead-light">
                                <tr>
                                    <th data-field="id" class="text-center" data-visible="false"><?= labels('id', 'ID') ?></th>
                                    <th data-field="title" class="text-center"><?= labels('title', 'Title') ?></th>
                                    <th data-field="message" class="text-center" data-formatter="handymanNotificationMessageFormatter"><?= labels('message', 'Message') ?></th>
                                    <th data-field="time_ago" class="text-center" style="width: 130px;"><?= labels('time', 'Time') ?></th>
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
    var HANDYMAN_READ_MORE_LABEL = "<?= esc(labels('read_more', 'Read more'), 'js') ?>";
    var HANDYMAN_READ_LESS_LABEL = "<?= esc(labels('read_less', 'Read less'), 'js') ?>";

    function handymanNotificationRowAttributes(row, index) {
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
            'data-notification-panel': 'handyman'
        };
    }

    function handymanNotificationEscapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function handymanNotificationMessageFormatter(value, row, index) {
        var msg = (value === null || value === undefined) ? '' : String(value);
        var safeFull = handymanNotificationEscapeHtml(msg);

        if (msg.length <= 50) {
            return '<span>' + safeFull + '</span>';
        }

        var shortText = handymanNotificationEscapeHtml(msg.substring(0, 50) + '...');

        // x-data element injected into a bootstrap-table row — Alpine's MutationObserver
        // auto-initializes it on insert, same as any statically-present component.
        return '' +
            '<div x-data="{ expanded: false }">' +
            '<span x-show="!expanded">' + shortText + '</span>' +
            '<span x-show="expanded" x-cloak>' + safeFull + '</span>' +
            ' <a href="#" class="small" x-on:click.prevent="expanded = !expanded" ' +
            'x-text="expanded ? HANDYMAN_READ_LESS_LABEL : HANDYMAN_READ_MORE_LABEL"></a>' +
            '</div>';
    }
</script>

<?= $this->endSection() ?>
