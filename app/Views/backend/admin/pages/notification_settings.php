<?php
$db = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$permissions = get_permission($user1[0]['id']);

$categoryIcons = [
    'Provider Management' => 'fas fa-user-tie',
    'Withdrawals & Payments' => 'fas fa-wallet',
    'Service Management' => 'fas fa-concierge-bell',
    'User Accounts' => 'fas fa-users',
    'Bookings' => 'fas fa-calendar-check',
    'Ratings & Reviews' => 'fas fa-star',
    'Communication' => 'fas fa-comments',
    'Promotions' => 'fas fa-tags',
    'Subscriptions' => 'fas fa-repeat',
    'Payment Gateway' => 'fas fa-credit-card',
    'Custom Job Requests' => 'fas fa-tools',
    'System & Policy' => 'fas fa-shield-alt',
    'Content' => 'fas fa-newspaper',
];
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-3">
            <h1><?= labels('notification_settings', 'Notification Settings') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a
                        href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a>
                </div>
                <div class="breadcrumb-item"><?= labels('notification_settings', 'Notification Settings') ?></div>
            </div>
        </div>

        <div class="section-body">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h4 class="mb-0">
                        <?= labels('notifications') ?>
                    </h4>
                    <div style="position:relative;width:240px">
                        <i class="fa fa-search"
                            style="position:absolute;top:50%;left:12px;transform:translateY(-50%);color:#aaa;pointer-events:none;z-index:1"></i>
                        <input type="text" id="ns-search" class="form-control"
                            style="border-radius:50px;padding-left:34px"
                            placeholder="<?= labels('search_notifications', 'Search Notifications…') ?>">
                    </div>
                </div>

                <div class="card-body p-0">
                    <form action="<?= base_url('admin/settings/notification_setting_update') ?>" method="post"
                        id="notification_setting_update">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="ns-table">
                                <thead class="thead-light" style="position:sticky;top:0;z-index:2">
                                    <tr>
                                        <th class="p-3" style="min-width:220px"><?= labels('module', 'Module') ?></th>
                                        <th class="p-3 text-center" style="min-width:100px">
                                            <?= labels('email', 'Email') ?>
                                            <div class="mt-1">
                                                <div class="custom-control custom-switch d-inline-flex">
                                                    <input id="select-all-email"
                                                        class="custom-control-input ns-select-all" type="checkbox"
                                                        data-channel="email">
                                                    <label for="select-all-email" class="custom-control-label"></label>
                                                </div>
                                            </div>
                                        </th>
                                        <th class="p-3 text-center" style="min-width:100px">
                                            <?= labels('sms', 'SMS') ?>
                                            <div class="mt-1">
                                                <div class="custom-control custom-switch d-inline-flex">
                                                    <input id="select-all-sms"
                                                        class="custom-control-input ns-select-all" type="checkbox"
                                                        data-channel="sms">
                                                    <label for="select-all-sms" class="custom-control-label"></label>
                                                </div>
                                            </div>
                                        </th>
                                        <th class="p-3 text-center" style="min-width:120px">
                                            <?= labels('notification', 'Notification') ?>
                                            <div class="mt-1">
                                                <div class="custom-control custom-switch d-inline-flex">
                                                    <input id="select-all-notification"
                                                        class="custom-control-input ns-select-all" type="checkbox"
                                                        data-channel="notification">
                                                    <label for="select-all-notification"
                                                        class="custom-control-label"></label>
                                                </div>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notification_categories as $category => $modules): ?>
                                        <?php
                                        $icon = $categoryIcons[$category] ?? 'fas fa-bell';
                                        $catSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $category));
                                        ?>
                                        <!-- Category header row -->
                                        <tr class="ns-category-row" data-category="<?= esc($catSlug) ?>">
                                            <td colspan="4" class="px-3 py-2"
                                                style="background:#f8f9fa;border-top:2px solid #e9ecef">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span class="font-weight-bold text-dark">
                                                        <i class="<?= $icon ?> mr-2 text-primary"></i>
                                                        <?= esc($category) ?>
                                                        <span class="badge badge-light ml-1 text-muted ns-cat-count"
                                                            data-category="<?= esc($catSlug) ?>"><?= count($modules) ?>
                                                            <?= labels('notifications') ?></span>
                                                    </span>
                                                    <button type="button"
                                                        class="btn btn-sm btn-link text-muted p-0 ns-cat-toggle"
                                                        data-category="<?= esc($catSlug) ?>" title="Toggle category">
                                                        <i class="fas fa-chevron-up ns-cat-chevron"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <?php foreach ($modules as $module): ?>
                                            <tr class="ns-module-row" data-category="<?= esc($catSlug) ?>"
                                                data-module="<?= esc($module) ?>">
                                                <td class="pl-4">
                                                    <a href="<?= base_url('admin/settings/sms-email-preview/' . $module) ?>"
                                                        class="text-body">
                                                        <?= labels($module, ucwords(str_replace('_', ' ', $module))) ?>
                                                    </a>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <div class="custom-control custom-switch d-inline-flex">
                                                        <input id="<?= $module ?>_email"
                                                            class="custom-control-input ns-channel-check" type="checkbox"
                                                            name="<?= $module ?>_email" value="true" data-channel="email">
                                                        <label for="<?= $module ?>_email" class="custom-control-label"></label>
                                                    </div>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <div class="custom-control custom-switch d-inline-flex">
                                                        <input id="<?= $module ?>_sms"
                                                            class="custom-control-input ns-channel-check" type="checkbox"
                                                            name="<?= $module ?>_sms" value="true" data-channel="sms">
                                                        <label for="<?= $module ?>_sms" class="custom-control-label"></label>
                                                    </div>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <div class="custom-control custom-switch d-inline-flex">
                                                        <input id="<?= $module ?>_notification"
                                                            class="custom-control-input ns-channel-check" type="checkbox"
                                                            name="<?= $module ?>_notification" value="true"
                                                            data-channel="notification">
                                                        <label for="<?= $module ?>_notification"
                                                            class="custom-control-label"></label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- No results row (hidden by default) -->
                        <div id="ns-no-results" class="text-center py-5 text-muted" style="display:none!important">
                            <i class="fas fa-search fa-2x mb-2"></i>
                            <p><?= labels('no_notifications_found') ?></p>
                        </div>

                        <?php if ($permissions['update']['settings'] == 1): ?>
                            <div class="row p-3">
                                <div class="col-md d-flex justify-content-lg-end m-1">
                                    <div class="form-group mb-0">
                                        <input type="submit" name="update" id="update"
                                            value="<?= labels('save_changes', 'Save Changes') ?>"
                                            class="btn btn-primary px-4" />
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    $(document).ready(function () {
        var current_settings = <?= json_encode($current_settings); ?>;

        // Restore saved state — values are '1'/'0' strings; "0" is truthy in JS, so compare explicitly
        $.each(current_settings, function (key, value) {
            $('#' + key).prop('checked', value === '1' || value === 1 || value === true);
        });

        // Sync "select all" header checkboxes — always counts ALL rows, not just visible
        function updateSelectAllState() {
            ['email', 'sms', 'notification'].forEach(function (ch) {
                var total = $('.ns-channel-check[data-channel="' + ch + '"]').length;
                var checked = $('.ns-channel-check[data-channel="' + ch + '"]:checked').length;
                var $all = $('#select-all-' + ch);
                if (total === 0) {
                    $all.prop('indeterminate', false).prop('checked', false);
                } else if (checked === total) {
                    $all.prop('indeterminate', false).prop('checked', true);
                } else if (checked === 0) {
                    $all.prop('indeterminate', false).prop('checked', false);
                } else {
                    $all.prop('indeterminate', true);
                }
            });
        }

        updateSelectAllState();

        // Select-all column header toggle
        $('.ns-select-all').on('change', function () {
            var ch = $(this).data('channel');
            var checked = $(this).prop('checked');
            $('.ns-channel-check[data-channel="' + ch + '"]').prop('checked', checked);
            updateSelectAllState();
        });

        // Individual toggle → update select-all state
        $('.ns-channel-check').on('change', function () {
            updateSelectAllState();
        });

        // Category collapse/expand
        $('.ns-cat-toggle').on('click', function () {
            var cat = $(this).data('category');
            var $rows = $('.ns-module-row[data-category="' + cat + '"]');
            var $icon = $(this).find('.ns-cat-chevron');
            if ($rows.first().is(':visible')) {
                $rows.hide();
                $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            } else {
                $rows.show();
                $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            }
            updateSelectAllState();
        });

        // Search filter
        $('#ns-search').on('input', function () {
            var q = $(this).val().toLowerCase().trim();
            var anyVisible = false;

            if (q === '') {
                $('.ns-module-row, .ns-category-row').show();
                anyVisible = true;
            } else {
                $('.ns-category-row').hide();
                $('.ns-module-row').each(function () {
                    var name = $(this).data('module').replace(/_/g, ' ');
                    if (name.indexOf(q) !== -1) {
                        $(this).show();
                        var cat = $(this).data('category');
                        $('.ns-category-row[data-category="' + cat + '"]').show();
                        anyVisible = true;
                    } else {
                        $(this).hide();
                    }
                });
                // Restore chevron for visible categories
                $('.ns-category-row:visible .ns-cat-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-up');
            }

            if (anyVisible) {
                $('#ns-no-results').hide();
                $('#ns-table').show();
            } else {
                $('#ns-no-results').show();
                $('#ns-table').hide();
            }

            updateSelectAllState();
        });

        // Form submit
        $('#notification_setting_update').on('submit', function (e) {
            e.preventDefault();
            var $btn = $(this).find('[type="submit"]');
            var originalVal = $btn.val();
            $btn.prop('disabled', true);
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                success: function (res) {
                    if (res.error === false) {
                        showToastMessage(res.message, 'success');
                        current_settings = {};
                        $('.ns-channel-check').each(function () {
                            current_settings[$(this).attr('name')] = $(this).prop('checked');
                        });
                    } else {
                        showToastMessage(res.message, 'error');
                    }
                },
                error: function () {
                    showToastMessage('<?= labels("something_went_wrong", "Something went wrong") ?>', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).val(originalVal);
                }
            });
        });
    });
</script>