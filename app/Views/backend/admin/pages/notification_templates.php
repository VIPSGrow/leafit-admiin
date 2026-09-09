<div class="main-content">
    <section class="section">
        <div class="section-header mt-3">
            <h1><?= labels('notification_templates', 'Notification Templates') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item"><?= labels('notification_templates', 'Notification Templates') ?></a></div>
            </div>
        </div>
        <div class="section-body">
            <div class=" card">
                <div class="card-body">
                    <table class="table" data-fixed-columns="true" id="category_list" data-pagination-successively-size="2" data-detail-formatter="category_formater" data-query-params="category_query_params" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/settings/notification-templates-list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="desc">
                        <thead>
                            <tr>
                                <th data-field="id" data-visible="true" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                <th data-field="event_key" class="text-center"><?= labels('event_key', 'Event Key') ?></th>
                                <th data-field="title" class="text-center"><?= labels('title', 'Title') ?></th>
                                <th data-field="body" class="text-center"><?= labels('body', 'Body') ?></th>
                                <th data-field="parameters" class="text-center"><?= labels('parameters', 'Parameters') ?></th>
                                <th data-field="created_at" class="text-center"><?= labels('created_at', 'Created At') ?></th>
                                <th data-field="updated_at" class="text-center"><?= labels('updated_at', 'Updated At') ?></th>
                                <th data-field="operations" class="text-center"><?= labels('operations', 'Operations') ?></th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>