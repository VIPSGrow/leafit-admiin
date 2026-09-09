<!-- Main Content -->
<?php
$db      = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$permissionService = new \App\Services\utility\PermissionService();
$permissions = $permissionService->permissionsFor((int)$user1[0]['id']);
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('taxes', "Taxes") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>

                <div class="breadcrumb-item"><?= labels('manage_taxes', "Manage Taxes") ?></a></div>
            </div>
        </div>

        <div class="row">

            <div class="col-md-4">
                <div class=" card">
                    <?= helper('form'); ?>

                    <h1></h1>
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col">
                            <div class="toggleButttonPostition"><?= labels('manage_taxes', "Manage Taxes") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?= form_open('/admin/tax/add_tax', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add', 'enctype' => "multipart/form-data", 'novalidate' => 'novalidate']); ?>

                        <?php
                        // Language switcher for translation fields
                        if (isset($languages) && !empty($languages)) {
                            // Sort languages so default language appears first
                            $sorted_languages = sort_languages_with_default_first($languages);
                            $current_language = '';
                            foreach ($sorted_languages as $language) {
                                if ($language['is_default'] == 1) {
                                    $current_language = $language['code'];
                                    break;
                                }
                            }
                        ?>
                            <div class="form-group">
                                <div class="d-flex flex-wrap align-items-center gap-4 mb-3">
                                    <?php foreach ($sorted_languages as $index => $language) { ?>
                                        <div class="language-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-add-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span class="language-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?><?= $language['is_default'] ? ' (Default)' : '' ?>
                                            </span>
                                            <div class="language-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>

                            <?php
                            // Title fields for each language
                            foreach ($sorted_languages as $index => $language) {
                            ?>
                                <div class="form-group" id="translationDiv-add-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <label for="title_<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input id="title_<?= $language['code'] ?>" class="form-control" type="text" name="title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" <?= $language['is_default'] ? 'required' : '' ?>>
                                </div>
                            <?php } ?>

                        <?php } else { ?>
                            <!-- Fallback if languages not loaded -->
                            <div class="form-group">
                                <label for="title" class="required"><?= labels('title', "Title") ?></label>
                                <input id="title" class="form-control" type="text" name="title" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" required>
                            </div>
                        <?php } ?>

                        <div class="form-group">
                            <label for="percentage" class="required"><?= labels('percentage', "Percentage") ?></label>
                            <input id="percentage" class="form-control" type="number" name="percentage" placeholder="<?= labels('enter_percentage_here', 'Enter the percentage here') ?>" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input id="status" class="custom-control-input" type="checkbox" name="tax_status" checked>
                                <label for="status" class="custom-control-label">
                                    <span id="tax_status">
                                        <?= labels('enable', "Enable") ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div class=" d-flex justify-content-end">

                            <button type="submit" class="btn btn-primary submit_btn"><?= labels('add_tax', "Add Tax") ?></button>
                        </div>

                        <?= form_close(); ?>
                    </div>
                </div>
                <div class="card">
                    <?= helper('form'); ?>

                    <!-- Card header -->
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col">
                            <div class="toggleButttonPostition">
                                <?= labels('tax_settings', 'Tax Settings') ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <?= form_open('/admin/settings/update-tax-settings', [
                            'method' => 'post',
                            'class'  => 'form-submit-event',
                            'id'     => 'tax_settings_form'
                        ]); ?>

                        <div class="form-group">
                            <label class="mb-2">
                                <?= labels('show_on_checkout', 'Show on Checkout') ?>
                            </label>

                            <input type="hidden"
                                name="show_on_checkout"
                                id="show_on_checkout_value"
                                value="<?= !empty($show_on_checkout) ? 1 : 0 ?>">

                            <input type="checkbox"
                                id="show_on_checkout"
                                class="status-switch"
                                data-value="<?= !empty($show_on_checkout) ? 1 : 0 ?>"
                                data-hidden="#show_on_checkout_value"
                                <?= !empty($show_on_checkout) ? 'checked' : '' ?>>
                        </div>


                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <?= labels('save', 'Save') ?>
                            </button>
                        </div>

                        <?= form_close(); ?>
                    </div>
                </div>
            </div>
            <?php if ($permissionService->can((int)$user1[0]['id'], 'read', 'tax')) : ?>

                <div class="col-md-8">

                    <div class=" card">
                        <div class="row">
                            <div class="col-lg">

                                <div class="row border_bottom_for_cards m-0">
                                    <div class="col">
                                        <div class="toggleButttonPostition"><?= labels('taxes', "Taxes") ?></div>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="row mt-2">
                                        <div class="col-md-12">


                                            <div class="row pb-3 ">
                                                <div class="col-12">
                                                    <div class="row mb-3 ">

                                                        <div class="col-md-4 col-sm-2 mb-2">
                                                            <div class="input-group">
                                                                <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                                                <div class="input-group-append">
                                                                    <button class="btn btn-primary" type="button">
                                                                        <i class="fa fa-search d-inline"></i>
                                                                    </button>
                                                                </div>
                                                            </div>

                                                        </div>


                                                        <div class="dropdown d-inline ml-2">
                                                            <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                <?= labels('download', 'Download') ?>
                                                            </button>
                                                            <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                                                <a class="dropdown-item" onclick="custome_export('pdf','Tax list','user_list');"> <?= labels('pdf', 'PDF') ?></a>
                                                                <a class="dropdown-item" onclick="custome_export('excel','Tax list','user_list');"> <?= labels('excel', 'Excel') ?></a>
                                                                <a class="dropdown-item" onclick="custome_export('csv','Tax list','user_list')"> <?= labels('csv', 'CSV') ?></a>
                                                            </div>
                                                        </div>


                                                    </div>

                                                </div>
                                            </div>
                                            <table class="table" data-pagination-successively-size="2" data-query-params="system_tax_query_params" id="user_list" data-detail-formatter="user_formater" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/tax/list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="desc">
                                                <thead>
                                                    <tr>
                                                        <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                                        <th data-field="title" class="text-center" data-sortable="true"><?= labels('title', 'Title') ?></th>
                                                        <th data-field="percentage" class="text-center" data-sortable="true"><?= labels('percentage', 'Percentage') ?></th>
                                                        <th data-field="status" class="text-center" data-sortable="true"><?= labels('status', 'Status') ?></th>
                                                        <th data-field="created_at" class="text-center" data-visible="false" data-sortable="true"><?= labels('created_at', 'Created At') ?></th>
                                                        <th data-field="operations" class="text-center" data-events="taxes_events"><?= labels('operations', 'Operations') ?></th>
                                                    </tr>
                                                </thead>
                                            </table>

                                            <!-- </div> -->
                                        </div>
                                        <!-- </div> -->
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>

    </section>
    <!-- update modal -->
    <div class="modal fade" id="update_modal" tabindex="-1" aria-labelledby="update_modal_thing" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"><?= labels('edit', 'Edit') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?= form_open('admin/tax/edit_taxes', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'edit_taxes', 'enctype' => "multipart/form-data", 'novalidate']); ?>
                    <input type="hidden" name="id" id="edit_tax_id">

                    <?php
                    // Language switcher for translation fields in edit modal
                    if (isset($languages) && !empty($languages)) {
                        $sorted_languages = sort_languages_with_default_first($languages);
                        $current_modal_language = '';
                        foreach ($sorted_languages as $language) {
                            if ($language['is_default'] == 1) {
                                $current_modal_language = $language['code'];
                                break;
                            }
                        }
                    ?>
                        <div class="form-group">
                            <div class="d-flex flex-wrap align-items-center gap-4 mb-3">
                                <?php foreach ($sorted_languages as $index => $language) { ?>
                                    <div class="language-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-edit-<?= $language['code'] ?>"
                                        data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span class="language-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?><?= $language['is_default'] ? ' (Default)' : '' ?>
                                        </span>
                                        <div class="language-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <?php
                        // Title fields for each language in edit modal
                        foreach ($sorted_languages as $index => $language) {
                        ?>
                            <div class="form-group" id="translationDiv-edit-<?= $language['code'] ?>" <?= $language['code'] == $current_modal_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <label for="edit_title_<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                <input id="edit_title_<?= $language['code'] ?>" class="form-control edit-title-field" type="text" name="title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" <?= $language['is_default'] ? 'required' : '' ?>>
                            </div>
                        <?php } ?>

                    <?php } else { ?>
                        <!-- Fallback if languages not loaded -->
                        <div class="form-group">
                            <label for="edit_title" class="required"><?= labels('title', 'Title') ?></label>
                            <input id="edit_title" class="form-control" type="text" name="title" placeholder="Enter the title here" required>
                        </div>
                    <?php } ?>

                    <div class="form-group">
                        <label for="edit_percentage" class="required"><?= labels('percentage', 'Percentage') ?></label>
                        <input id="edit_percentage" class="form-control" type="number" name="percentage" step="0.01" placeholder="Enter the percentage here" required>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input id="status_edit" class="custom-control-input" type="checkbox" name="tax_status_edit" checked>
                            <label for="status_edit" class="custom-control-label">
                                <span id="tax_status_edit">
                                    <?= labels('enable', 'Enable') ?>
                                </span>
                            </label>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary submit_btn" name="submit"><?= labels('save_changes', 'Save Changes') ?></button>
                    <?php form_close() ?>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                </div>
            </div>
        </div>
    </div>


</div>


<script>
    $("#customSearch").on('keydown', function() {
        $('#user_list').bootstrapTable('refresh');
    });

    // Language switcher for add form
    document.querySelectorAll('[id^="language-add-"]').forEach(function(element) {
        element.addEventListener('click', function() {
            // Get the language code from data attribute
            var languageCode = this.getAttribute('data-language');

            // Hide all translation divs for add form
            document.querySelectorAll('[id^="translationDiv-add-"]').forEach(function(div) {
                div.style.display = 'none';
            });

            // Show the selected language div
            document.getElementById('translationDiv-add-' + languageCode).style.display = 'block';

            // Update language option styles
            document.querySelectorAll('[id^="language-add-"]').forEach(function(lang) {
                lang.classList.remove('selected');
                lang.querySelector('.language-text').classList.remove('text-primary', 'fw-medium');
                lang.querySelector('.language-text').classList.add('text-muted');
                lang.querySelector('.language-underline').style.width = '0';
            });

            // Add selected styles to clicked language
            this.classList.add('selected');
            this.querySelector('.language-text').classList.remove('text-muted');
            this.querySelector('.language-text').classList.add('text-primary', 'fw-medium');
            this.querySelector('.language-underline').style.width = '100%';
        });
    });

    // Language switcher for edit modal
    document.querySelectorAll('[id^="language-edit-"]').forEach(function(element) {
        element.addEventListener('click', function() {
            // Get the language code from data attribute
            var languageCode = this.getAttribute('data-language');

            // Hide all translation divs for edit modal
            document.querySelectorAll('[id^="translationDiv-edit-"]').forEach(function(div) {
                div.style.display = 'none';
            });

            // Show the selected language div
            document.getElementById('translationDiv-edit-' + languageCode).style.display = 'block';

            // Update language option styles
            document.querySelectorAll('[id^="language-edit-"]').forEach(function(lang) {
                lang.classList.remove('selected');
                lang.querySelector('.language-text').classList.remove('text-primary', 'fw-medium');
                lang.querySelector('.language-text').classList.add('text-muted');
                lang.querySelector('.language-underline').style.width = '0';
            });

            // Add selected styles to clicked language
            this.classList.add('selected');
            this.querySelector('.language-text').classList.remove('text-muted');
            this.querySelector('.language-text').classList.add('text-primary', 'fw-medium');
            this.querySelector('.language-underline').style.width = '100%';
        });
    });


    function handleSwitchChange(checkbox) {
        const isChecked = checkbox.checked;
        const switchery = $(checkbox).siblings('.switchery');
        if (isChecked) {
            switchery.addClass('yes-content').removeClass('no-content');
        } else {
            switchery.addClass('no-content').removeClass('yes-content');
        }

        if (checkbox.id === 'show_on_checkout') {
            $('#show_on_checkout_value').val(isChecked ? '1' : '0');
        }
    }

    $(document).ready(function() {
        $('#show_on_checkout').siblings('.switchery').addClass('no-content').removeClass('yes-content');
        $('#show_on_checkout').on('change', function() {
            handleSwitchChange(this);
        });

        initToggle($('#show_on_checkout')[0]);

        function initToggle(checkbox) {
            const $checkbox = $(checkbox);
            const value = Number($checkbox.data('value')) || 0;
            const hiddenSelector = $checkbox.data('hidden');

            const $hiddenInput = $(hiddenSelector);
            const $switchery = $checkbox.siblings('.switchery');

            if (!$hiddenInput.length) return;

            // Initial state
            checkbox.checked = value === 1;
            $hiddenInput.val(value);
            updateUI(value);

            // Change handler
            $checkbox.on('change', function() {
                const state = this.checked ? 1 : 0;
                $hiddenInput.val(state);
                updateUI(state);
            });

            function updateUI(state) {
                $switchery
                    .toggleClass('yes-content', state === 1)
                    .toggleClass('no-content', state !== 1);
            }
        }
    });
</script>