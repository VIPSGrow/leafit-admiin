<?php

use app\Services\utility\PermissionService;
// $user1 = fetch_details('users', ["phone" => $_SESSION['identity']],);
$db = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$userId = !empty($user1) ? $user1[0]['id'] : '0';

$permissionService = new PermissionService();
?>

<div class="main-content">
    <section class="section">

        <div class="section-header mt-2">
            <div class="row w-100 align-items-center">
                <div class="col">
                    <h1><?= labels('sliders', "Sliders") ?></h1>
                </div>
                <div class="col-auto">
                    <div class="section-header-breadcrumb d-flex flex-wrap">
                        <div class="breadcrumb-item active">
                            <a href="<?= base_url('/admin/dashboard') ?>">
                                <i class="fas fa-home-alt text-primary"></i>
                                <?= labels('Dashboard', 'Dashboard') ?>
                            </a>
                        </div>
                        <div class="breadcrumb-item"><?= labels('sliders', 'Sliders') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">

            <?php if ($permissionService->can($userId, 'create', 'sliders')): ?>
                <div class="col-xl-4 col-lg-5 col-md-6 col-sm-12 mb-4">

                    <?= form_open('/admin/sliders/add_slider', [
                        'method' => "post",
                        'class' => 'form-submit-event',
                        'id' => 'add',
                        'enctype' => "multipart/form-data"
                    ]); ?>

                    <div class="card h-100">
                        <div class="row mx-0 border_bottom_for_cards d-flex align-items-center">
                            <div class="col">
                                <div class="toggleButttonPostition">
                                    <?= labels('add_new_slider', 'Add New Slider') ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="slider_switch" name="slider_switch" checked>
                                    <label class="custom-control-label" for="slider_switch"></label>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row">

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="required"><?= labels('type', 'Type') ?></label>
                                        <select id="type" class="form-control select2" name="type">
                                            <option value=""><?= labels('select_type', 'Select Type') ?></option>
                                            <option value="default"><?= labels('default', 'Default') ?></option>
                                            <option value="Category"><?= labels('category', 'Category') ?></option>
                                            <option value="provider"><?= labels('provider', 'Provider') ?></option>
                                            <option value="url"><?= labels('url', 'URL') ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <!-- Category select - only visible when type is "Category" -->
                                    <div id="slider_categories_select" class="form-group conditional categories d-none">
                                        <label class="required"><?= labels('category', 'Category') ?></label>
                                        <select id="Category_item" class="form-control select2" name="Category_item">
                                            <option value=""><?= labels('select', 'Select') ?></option>
                                            <?php foreach ($categories_name as $Category): ?>
                                                <option value="<?= $Category['id'] ?>"><?= $Category['name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <!-- Provider select - only visible when type is "provider" -->
                                    <div id="slider_provider_select" class="form-group conditional services d-none">
                                        <label class="required"><?= labels('provider', 'Provider') ?></label>
                                        <select id="service_item" class="form-control select2" name="service_item">
                                            <option value=""><?= labels('select', 'Select') ?></option>
                                            <?php foreach ($provider_title as $provider): ?>
                                                <option value="<?= $provider['partner_id'] ?>"><?= $provider['company_name'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <!-- URL input - only visible when type is "url" -->
                                    <div id="url_section" class="form-group conditional d-none">
                                        <label class="required"><?= labels('url', 'URL') ?></label>
                                        <input type="url" class="form-control" id="slider_url" name="url">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="required"><?= labels('app_image', 'App Image') ?></label>
                                        <small>(<?= labels('app_slider_size_recommend', 'We recommend 345 x 145 pixels') ?>)</small>
                                        <input type="file" name="app_image" class="filepond" accept="image/*">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="required"><?= labels('web_image', 'Web Image') ?></label>
                                        <small>(<?= labels('web_size_slider_recommend', 'We recommend 1920 x 800 pixels') ?>)</small>
                                        <input type="file" name="web_image" class="filepond" accept="image/*">
                                    </div>
                                </div>

                            </div>

                            <div class="row">
                                <div class="col text-right">
                                    <button type="submit" class="btn bg-new-primary submit_btn">
                                        <?= labels('add_new_slider', 'Add New Slider') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?= form_close() ?>

                </div>
            <?php endif; ?>


            <!-- RIGHT SIDE TABLE -->
            <div class="col-xl-8 col-lg-7 col-md-6 col-sm-12">

                <?php if ($permissionService->can($userId, 'read', 'sliders')): ?>

                    <div class="card w-100 h-100">
                        <div class="card-header">
                            <div class="toggleButttonPostition">
                                <?= labels('all_sliders', 'All Sliders') ?>
                            </div>
                        </div>

                        <div class="card-body">

                            <div class="row mb-3 align-items-center">

                                <div class="col-auto mb-2 d-flex">
                                    <div class="btn bg-emerald-blue tag text-emerald-blue mr-2 filters_table cursor-pointer"
                                        id="slider_filter_all">
                                        <?= labels('all', 'All') ?>
                                    </div>
                                    <div class="btn bg-emerald-success tag text-emerald-success mr-2 filters_table cursor-pointer"
                                        id="slider_filter_active">
                                        <?= labels('active', 'Active') ?>
                                    </div>
                                    <div class="btn bg-emerald-danger tag text-emerald-danger filters_table cursor-pointer"
                                        id="slider_filter_deactive">
                                        <?= labels('deactive', 'Deactive') ?>
                                    </div>
                                </div>

                                <div class="col-md mb-2">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="customSearch"
                                            placeholder="<?= labels('search_here', 'Search here!') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" id="customSearchBtn">
                                                <i class="fa fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-auto mb-2">
                                    <button class="btn btn-secondary filter_button" id="filterButton">
                                        <span class="material-symbols-outlined">filter_alt</span>
                                    </button>
                                </div>

                                <div class="col-auto mb-2">
                                    <div class="dropdown">
                                        <button class="btn export_download dropdown-toggle" data-toggle="dropdown">
                                            <?= labels('download', 'Download') ?>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item"
                                                onclick="custome_export('pdf','Slider list','slider_list');"><?= labels('pdf', 'PDF') ?></a>
                                            <a class="dropdown-item"
                                                onclick="custome_export('excel','Slider list','slider_list');"><?= labels('excel', 'Excel') ?></a>
                                            <a class="dropdown-item"
                                                onclick="custome_export('csv','Slider list','slider_list');"><?= labels('csv', 'CSV') ?></a>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="table-responsive">
                                <table class="table" id="slider_list" data-toggle="table"
                                    data-url="<?= base_url("admin/sliders/list") ?>" data-pagination="true"
                                    data-side-pagination="server" data-page-list="[5,10,25,50,100,200,All]"
                                    data-sort-name="id" data-sort-order="DESC" data-query-params="slider_query_params">
                                    <thead>
                                        <tr>
                                            <th data-field="id" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                            <th data-field="slider_app_image"><?= labels('app_image', 'App Image') ?></th>
                                            <th data-field="slider_web_image"><?= labels('web_image', 'Web Image') ?></th>
                                            <th data-field="translated_type" data-sortable="true">
                                                <?= labels('type', 'Type') ?>
                                            </th>
                                            <th data-field="status" class="text-center" data-sortable="true">
                                                <?= labels('status', 'Status') ?>
                                            </th>
                                            <th data-field="created_at" data-sortable="true">
                                                <?= labels('created_at', 'Created At') ?>
                                            </th>
                                            <th data-field="url" data-visible="false"><?= labels('url', 'URL') ?></th>
                                            <th data-field="operations" class="text-center" data-events="slider_events">
                                                <?= labels('operations', 'Operations') ?>
                                            </th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>

                        </div>
                    </div>

                <?php endif; ?>
            </div>

        </div>
    </section>
</div>

<!-- Filter Drawer -->
<div id="filterBackdrop"></div>
<div class="drawer" id="filterDrawer">
    <div class="bg-new-primary d-flex justify-content-between align-items-center p-3">
        <div class="d-flex align-items-center">
            <div class="bg-white text-new-primary rounded p-2 mr-3 filter-icon-box">
                <span class="material-symbols-outlined">filter_alt</span>
            </div>
            <h3 class="mb-0" style="font-size: 16px;"><?= labels('filters', 'Filters') ?></h3>
        </div>
        <div id="cancelButton" class="cursor-pointer">
            <span class="material-symbols-outlined">cancel</span>
        </div>
    </div>
    <div class="p-4">
        <div class="form-group">
            <label for="table_filters"><?= labels('table_filters', 'Table filters') ?></label>
            <div id="columnToggleContainer"></div>
            <button class="btn btn-primary d-block mt-3" id="apply_filter"><?= labels('apply', 'Apply') ?></button>
        </div>
    </div>
</div>

<!-- Update Modal -->
<div class="modal fade" id="update_modal" tabindex="-1" aria-labelledby="update_modal_thing" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <?= form_open('/admin/sliders/update_slider', ['method' => "post", 'class' => 'update-form-submit-event', 'id' => 'update_slider', 'enctype' => "multipart/form-data"]); ?>
            <div class="modal-header border-bottom">
                <div class="row w-100 align-items-center m-0">
                    <div class="col pl-3">
                        <div class="toggleButttonPostition"><?= labels('edit_slider', 'Edit Slider') ?></div>
                    </div>
                    <!-- Status switch: aligns right on desktop, left on mobile for LTR, right on mobile for RTL -->
                    <div class="col-auto d-flex justify-content-md-end justify-content-start pr-3">
                        <div class="custom-control custom-switch mt-2">
                            <input type="checkbox" class="custom-control-input" id="edit_slider_switch" name="edit_slider_switch">
                            <label class="custom-control-label" for="edit_slider_switch"></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="id">
                <div class="row">
                    <div class="col-md-6 col-12 mb-3">
                        <div class="form-group">
                            <label class="required"><?= labels('select_type', 'Select Type') ?></label>
                            <select id="type_1" class="form-control select2" name="type_1">
                                <option value=""><?= labels('select_type', 'Select Type') ?></option>
                                <option value="default"><?= labels('default', 'Default') ?></option>
                                <option value="Category"><?= labels('category', 'Category') ?></option>
                                <option value="provider"><?= labels('provider', 'Provider') ?></option>
                                <option value="url"><?= labels('url', 'URL') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-12 mb-3">
                        <!-- Category select - only visible when type is "Category" -->
                        <div class="form-group categories d-none" id="categories_select_1">
                            <label for="Category_item_1"
                                class="required"><?= labels('choose_category', 'Choose a Category') ?></label>
                            <select id="Category_item_1" class="form-control select2" name="Category_item_1">
                                <option value=""><?= labels('select_category', 'Select Category') ?></option>
                                <?php foreach ($categories_name as $Category): ?>
                                    <option value="<?= $Category['id'] ?>"><?= $Category['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Provider select - only visible when type is "provider" -->
                        <div class="form-group services d-none" id="services_select_1">
                            <label class="required"
                                for="service_item_1"><?= labels('provider', 'Choose a Provider') ?></label>
                            <select id="service_item_1" class="form-control select2" name="service_item_1">
                                <option value=""><?= labels('select', 'Select') ?> <?= labels('provider', 'Provider') ?>
                                </option>
                                <?php foreach ($provider_title as $service): ?>
                                    <option value="<?= $service['partner_id'] ?>"><?= $service['company_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- URL input - only visible when type is "url" -->
                        <div class="form-group d-none" id="edit_url_section">
                            <label for="edit_slider_url" class="required"><?= labels('url', 'URL') ?></label>
                            <input type="url" class="form-control" id="edit_slider_url" name="url">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 col-12 mb-3">
                        <div class="form-group">
                            <label class="required"><?= labels('app_image', 'App Image') ?></label>
                            <small
                                class="d-block text-muted mb-2">(<?= labels('app_slider_size_recommend', 'We recommend to use 345 X 145 pixels') ?>)</small>
                            <input type="file" name="app_image" class="filepond" accept="image/*">
                        </div>
                    </div>
                    <div class="col-md-4 col-12 mb-3 d-flex align-items-end">
                        <img src="" class="img-thumbnail rounded"
                            style="height: 100px; width: 100px; object-fit: cover;" alt="app_image_preview"
                            id="offer_image">
                    </div>
                    <div class="col-md-8 col-12 mb-3">
                        <div class="form-group">
                            <label class="required"><?= labels('web_image', 'Web Image') ?></label>
                            <small
                                class="d-block text-muted mb-2">(<?= labels('web_size_slider_recommend', 'We recommend to use 1920 x 800 pixels') ?>)</small>
                            <input type="file" name="web_image" class="filepond" accept="image/*">
                        </div>
                    </div>
                    <div class="col-md-4 col-12 mb-3 d-flex align-items-end">
                        <img src="" class="img-thumbnail rounded"
                            style="height: 100px; width: 100px; object-fit: cover;" alt="web_image_preview"
                            id="offer_web_image">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit"
                    class="btn bg-new-primary submit_btn"><?= labels('edit_slider', 'Edit Slider') ?></button>
                <?php form_close() ?>
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function () {
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        var dynamicColumns = fetchColumns('slider_list');
        setupColumnToggle('slider_list', dynamicColumns, 'columnToggleContainer');
    });
</script>
<script>
    filter_value = "";
    // Filter button handlers - refresh slider table when filter is clicked
    $("#slider_filter_all").on("click", function () {
        filter_value = "";
        $("#slider_list").bootstrapTable("refresh");
    });
    $("#slider_filter_active").on("click", function () {
        filter_value = "1";
        $("#slider_list").bootstrapTable("refresh");
    });
    $("#slider_filter_deactive").on("click", function () {
        filter_value = "0";
        $("#slider_list").bootstrapTable("refresh");
    });

    // Search button handler - refresh slider table when search button is clicked
    $("#customSearchBtn").on('click', function () {
        $('#slider_list').bootstrapTable('refresh');
    });


    function slider_query_params(p) {
        return {
            search: $("#customSearch").val() ? $("#customSearch").val() : p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            slider_filter: filter_value,
        };
    }
</script>
<script>
    // keep select2 search usable inside the edit modal by binding dropdowns to the modal itself
    // This ensures dropdowns open within the modal viewport instead of outside
    (function () {
        const $updateModal = $('#update_modal');
        // Include all select2 dropdowns in the modal: type, category, and provider selects
        const $modalSelects = $('#type_1, #Category_item_1, #service_item_1');

        function initModalSelect2() {
            $modalSelects.each(function () {
                const $select = $(this);
                const placeholderText = $select.data('placeholder') || $select.find('option:first').text();
                // Destroy existing select2 instance if it exists to avoid conflicts
                if ($select.data('select2')) {
                    $select.select2('destroy');
                }
                // Initialize select2 with dropdownParent set to modal to keep dropdown visible
                $select.select2({
                    width: '100%',
                    dropdownParent: $updateModal,
                    placeholder: placeholderText
                });
            });
        }

        // Initialize select2 when modal is shown
        $updateModal.on('shown.bs.modal', initModalSelect2);

        // Also initialize if modal is already visible (edge case)
        if ($updateModal.is(':visible')) {
            initModalSelect2();
        }
    })();
</script>
<script>
    // Handle type dropdown change in edit modal to show/hide conditional fields
    // This ensures only the relevant field is visible based on selected type
    // Using d-none class ensures hidden elements don't take up space in the layout
    $(document).ready(function () {
        // Handle type change event for edit modal
        $('#type_1').on('change', function () {
            const selectedType = $(this).val();

            // Hide all conditional fields first
            $('#categories_select_1, #services_select_1, #edit_url_section').addClass('d-none');

            // Show the appropriate field based on selected type
            if (selectedType === 'Category') {
                $('#categories_select_1').removeClass('d-none');
            } else if (selectedType === 'provider') {
                $('#services_select_1').removeClass('d-none');
            } else if (selectedType === 'url') {
                $('#edit_url_section').removeClass('d-none');
            }
            // If type is "default" or empty, all fields remain hidden
        });

        // Trigger change event on modal open to set initial state based on current value
        $('#update_modal').on('shown.bs.modal', function () {
            $('#type_1').trigger('change');
        });
    });
</script>