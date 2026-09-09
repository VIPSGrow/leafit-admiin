<?php
$cp_perms = isset($custom_page_permissions) ? $custom_page_permissions : ['create' => false, 'read' => true, 'update' => false, 'delete' => false];
?>
<div class="main-content">
    <section class="section">
        <!-- custom pages header -->
        <div class="section-header mt-2">
            <h1><?= labels('custom_pages', "Custom Pages") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('custom_pages', 'Custom Pages') ?></a></div>
            </div>
        </div>

        <!-- custom pages list -->
        <div class="container-fluid card">
            <div class="row mt-4 mb-3">
                <!-- search button -->
                <div class="col-md-4 col-sm-2 mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" id="custom_page_search_button">
                                <i class="fa fa-search d-inline"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- filter and add buttons -->
                <div class="col col d-flex justify-content-end">
                    <button class="btn btn-secondary ml-2 filter_button" id="filterButton">
                        <span class="material-symbols-outlined mt-1">
                            filter_alt
                        </span>
                    </button>
                    <?php if ($cp_perms['create']) { ?>
                        <div class="text-center ml-2">
                            <a href="<?= base_url("admin/custom-pages/add"); ?>" class="btn btn-primary" style="height: 39px;font-size:14px">
                                <i class="fa fa-plus-circle mr-1 mt-2"></i><?= labels('add_custom_page', 'Add Custom Page') ?>
                            </a>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php if ($cp_perms['read']) { ?>
                <div class="row ">
                    <!-- custom pages list table -->
                    <div class="col-md-12">
                        <table class="table " data-fixed-columns="true" id="custom_pages_list" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/custom-pages/list") ?>" data-query-params="custom_page_query_params" data-side-pagination="server" data-pagination="true" data-pagination-successively-size="1" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-sort-name="id" data-sort-order="desc">
                            <thead>
                                <tr>
                                    <th data-field="id" class="text-left" data-sortable="true" data-visible="false"><?= labels('id', 'ID') ?></th>
                                    <th data-field="translated_title" class="text-left"><?= labels('title', 'Title') ?></th>
                                    <th data-field="slug" class="text-left"><?= labels('slug', 'Slug') ?></th>
                                    <th data-field="content" class="text-left" data-formatter="contentFormatter" data-visible="true"><?= labels('content', 'Content') ?></th>
                                    <th data-field="status" class="text-center" data-formatter="statusFormatter"><?= labels('status', 'Status') ?></th>
                                    <th data-field="created_at" class="text-left" data-sortable="true" data-visible="true"><?= labels('created_at', 'Created At') ?></th>
                                    <th data-field="operations" class="text-center" data-events="action_events"><?= labels('operations', 'Operations') ?></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>
</div>

<!-- Filter Drawer -->
<div id="filterBackdrop"></div>
<div class="drawer" id="filterDrawer">
    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="bg-new-primary" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <div class="bg-white m-3 text-new-primary" style="box-shadow: 0px 8px 26px #00b9f02e; display: inline-block; padding: 10px; height: 45px; width: 45px; border-radius: 15px;">
                            <span class="material-symbols-outlined">
                                filter_alt
                            </span>
                        </div>
                        <h3 class="mb-0" style="display: inline-block; font-size: 16px; margin-left: 10px;"><?= labels('filters', "Filters") ?></h3>
                    </div>
                    <div id="cancelButton" style="cursor: pointer;">
                        <span class="material-symbols-outlined mr-2">
                            cancel
                        </span>
                    </div>
                </div>
                <div class="row mt-4 mx-2">
                    <div class="col-md-12">
                        <div class="form-group ">
                            <label for="table_filters"><?= labels('table_filters', 'Table filters') ?></label>
                            <div id="columnToggleContainer">
                            </div>
                            <button class="btn btn-primary d-block mt-3" id="apply_filter"><?= labels('apply', 'Apply') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="<?= base_url('public/backend/assets/js/custom-pages.js') ?>"></script>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const CAN_UPDATE_CUSTOM_PAGES = <?= $cp_perms['update'] ? 'true' : 'false' ?>;

    $(document).ready(function() {
        // Initialize filter drawer
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        var dynamicColumns = fetchColumns('custom_pages_list');
        setupColumnToggle('custom_pages_list', dynamicColumns, 'columnToggleContainer');

        // Refresh table on search button click
        $('#custom_page_search_button').click(function() {
            $('#custom_pages_list').bootstrapTable('refresh');
        });

        // Refresh table on Enter key in search input
        $('#customSearch').on('keyup', function(e) {
            if (e.keyCode === 13) {
                $('#custom_pages_list').bootstrapTable('refresh');
            }
        });
    });

    window.action_events = {
        'click .delete-custom-page': function(e, value, row, index) {
            Swal.fire({
                title: '<?= labels('are_your_sure', 'Are you sure?') ?>',
                text: "<?= labels('you_wont_be_able_to_revert_this', "You won't be able to revert this!") ?>",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!') ?>',
                cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post(
                        BASE_URL + "/admin/custom-pages/delete", {
                            [csrfName]: csrfHash,
                            id: row.id,
                        },
                        function(data) {
                            csrfName = data.csrfName;
                            csrfHash = data.csrfHash;
                            if (data.error == false) {
                                showToastMessage(data.message, "success");
                                $('#custom_pages_list').bootstrapTable('refresh');
                            }
                        }
                    );
                }
            });
        }
    };

    // Custom page query params function to include language parameter
    function custom_page_query_params(params) {
        // Use system default language since language switching is removed
        const currentLanguage = '<?= get_current_language() ?>';

        // Add language parameter to query
        params.language_code = currentLanguage;

        // Add search parameter if search input has value
        const searchValue = $('#customSearch').val();
        if (searchValue && searchValue.trim() !== '') {
            params.search = searchValue.trim();
        }

        return params;
    }
</script>
