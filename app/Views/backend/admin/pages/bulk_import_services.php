<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('bulk_service_update', 'Bulk Service Update') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('bulk_service_update', 'Bulk Service Update') ?></div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card card-primary h-100">
                    <div class="card-header">
                        <h4><?= labels('step_1', 'Step 1') ?></h4>
                        <div class="card-header-action">
                            <img height="50" width="50" src="<?= base_url("public/uploads/site/file.png")  ?>" class="" alt="">
                        </div>
                    </div>
                    <div class="card-body">
                        <h6 class="text-dark"><?= labels('download_excel_file', 'Download Excel File') ?></h6>
                        <ul class="p-3">
                            <li>
                                <?= labels('download_format_instruction', 'Download the format file and fill it with proper data.') ?>
                            </li>
                            <li>
                                <?= labels('download_review_example', 'You can download the example file to understand how the data must be filled.') ?>
                            </li>
                            <li>
                                <?= labels('upload_excel', ' Have to upload excel file.') ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-primary h-100">
                    <div class="card-header">
                        <h4><?= labels('step_2', 'Step 2') ?></h4>
                        <div class="card-header-action">
                            <img height="50" width="50" src="<?= base_url("public/uploads/site/data-transfer.png") ?>" class="" alt="">
                        </div>
                    </div>
                    <div class="card-body">
                        <h6 class="text-dark"><?= labels('match_data_instruction', ' Match Spread sheet data according to instruction') ?></h6>
                        <ul class="p-3">
                            <li><?= labels('validate_spreadsheet', 'Ensure that all data in the spreadsheet adheres to the specified formats and values.') ?></li>
                            <li><?= labels('download_review_example', 'Download and review the example file provided to understand the required structure and format for the data. This file serves as a template to help you fill in your data correctly.') ?></li>
                            <li><?= labels('upload_excel_instruction', 'You need to upload an Excel file (<code>.xlsx</code>) for the bulk import process.') ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-primary h-100">
                    <div class="card-header">
                        <h4><?= labels('step_3', 'Step 3') ?></h4>
                        <div class="card-header-action">
                            <img height="50" width="50" src="<?= base_url("public/uploads/site/file_upload.png")  ?>" class="" alt="">
                        </div>
                    </div>
                    <div class="card-body">
                        <h6 class="text-dark"><?= labels('upload_excel', 'Upload Excel File') ?></h6>
                        <ul class="p-3">
                            <li>
                                <?= labels('ensure_correct_headers', 'Ensure the first row contains the correct headers matching the template.') ?>
                            </li>
                            <li>
                                <?= labels('validate_data', ' Review and validate your data thoroughly before uploading to avoid errors during the
                                import process.') ?>
                            </li>
                            <li>
                                <?= labels('fill_mandatory_fields', 'Ensure all mandatory fields are filled and follow the specified formats strictly.') ?>
                            </li>
                            <li>
                                <?= labels('upload_excel',  'Have to upload excel file.') ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h4><?= labels('download_files', 'Download Files') ?></h4>
                    </div>
                    <div class="card-body">
                        <div class="row mt-4">
                            <!-- Top Row: Add Service Data and Instructions -->
                            <div class="col-md-4 col-sm-12 mb-4">
                                <a href="<?= base_url("/admin/services/download-sample-for-insert/") ?>" class="btn btn-lg btn-outline-primary w-100 d-flex align-items-center justify-content-center download-card-btn" id="downloadInsert">
                                    <i class="fas fa-arrow-circle-down mr-2"></i>
                                    <?= labels('add_service_data', 'Add Service Data') ?>
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-12 mb-4">
                                <a href="<?= base_url("/admin/services/Service-Add-Instructions/") ?>" class="btn btn-lg btn-outline-primary w-100 d-flex align-items-center justify-content-center download-card-btn" id="addInstructions">
                                    <i class="fas fa-arrow-circle-down mr-2"></i>
                                    <?= labels('add_service_instructions', 'Add Service Instructions') ?>
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-12 mb-4">
                                <a href="<?= base_url("/admin/services/Service-Update-Instructions/") ?>" class="btn btn-lg btn-outline-primary w-100 d-flex align-items-center justify-content-center download-card-btn" id="updateInstructions">
                                    <i class="fas fa-arrow-circle-down mr-2"></i>
                                    <?= labels('update_service_instructions', 'Update Service Instructions') ?>
                                </a>
                            </div>
                        </div>

                        <form action="<?= base_url("/admin/services/download-sample-for-update") ?>" method="post" id="downloadForm">
                            <div class="row g-3">
                                <!-- Second Row: Select2 and Update Service Data Button -->
                                <div class="col-md-8 col-sm-12">
                                    <select id="service_partner_ids" class="form-control select2 select2-lg" multiple name="partners[]" data-placeholder="<?= labels('select_provider(s)', 'Select Provider(s)') ?>">
                                        <option></option>
                                        <?php foreach ($partner_name as $pn) : ?>
                                            <option value="<?= $pn['id'] ?>">
                                                <?= $pn['company_name'] . ' - ' . $pn['username'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 col-sm-12 mt-md-0 mt-4">
                                    <button type="submit" class="btn btn-lg btn-outline-primary w-100 d-flex align-items-center justify-content-center download-card-btn" id="downloadUpdate">
                                        <i class="fas fa-arrow-circle-down mr-2"></i>
                                        <?= labels('update_service_data', 'Update Service Data') ?>
                                    </button>
                                </div>
                            </div>

                            <div class="row px-3 mt-3">
                                <div id="selected-providers"></div>
                                <input type="hidden" name="partners" id="selected_partner_ids">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h4><?= labels('upload_file', 'Upload File') ?></h4>
                    </div>
                    <div class="card-body">
                        <?= form_open(
                            '/admin/services/bulk_import_service_upload',
                            ['method' => "post", 'class' => 'form-submit-event', 'id' => 'update_service', 'enctype' => "multipart/form-data"]
                        ); ?>
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <input type="file" class="filepond-excel" name="file" id="file" required>
                            </div>
                            <div class="col-md-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-lg bg-new-primary submit_btn"><?= labels('submit', 'Submit') ?></button>
                            </div>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
    /* Hide selected items inside select2 - completely remove from layout */
    .select2-selection__rendered .select2-selection__choice {
        display: none !important;
    }

    /* Force search field to be full width to show placeholder and position it at the start */
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex !important;
        flex-direction: row !important;
        width: 100% !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-search--inline {
        width: 100% !important;
        float: none !important;
        order: -1 !important;
        /* Move to beginning */
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
    }

    .select2-container--default .select2-search--inline .select2-search__field {
        width: 100% !important;
        margin-top: 30px !important;
        line-height: normal !important;
        margin-left: 0 !important;
        margin-bottom: 0 !important;
        height: 100% !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    /* Keep select box height nice and matching buttons */
    .select2-container--default .select2-selection--multiple,
    .download-card-btn {
        height: 50px !important;
        min-height: 50px !important;
        padding: 0 15px !important;
        /* Removed vertical padding for perfect flex centering */
        border-radius: 6px;
        border: 1px solid #ced4da;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .select2-selection__rendered {
        padding-left: 0 !important;
        display: flex !important;
        align-items: center !important;
        width: 100% !important;
        height: 100% !important;
    }

    .select2-selection--multiple {
        min-height: 50px !important;
    }

    #selected-providers {
        position: relative;
        z-index: 20;
        /* ABOVE select2 */
        display: inline-flex !important;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    .select2-container {
        z-index: 1 !important;
    }

    #selected-providers>* {
        flex: 0 0 auto !important;
    }

    .provider-chip {
        display: inline-flex !important;
        align-items: center;
        white-space: nowrap;
        width: auto !important;
        max-width: max-content !important;
        padding: 5px 10px;
        border-radius: 20px;
        color: #fff;
        background: var(--primary-color);
        border: 1px solid var(--primary-color);
    }

    .provider-chip button {
        margin-left: 8px;
        border: none;
        background: none;
        cursor: pointer;
        font-weight: bold;
        color: #fff;
    }


    .provider-chip button:hover {
        color: #dc3545;
    }

    .provider-chip {
        pointer-events: auto !important;
    }

    .provider-chip button {
        pointer-events: auto !important;
        cursor: pointer;
    }

    /* Ensure selected items (even when hovered) always show the primary color */
    .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: var(--primary-color) !important;
        color: #fff !important;
    }

    /* Ensure highlighted (hovered) items are distinct from selected items */
    .select2-container--default .select2-results__option--selected {
        background-color: var(--primary-color) !important;
        color: #fff !important;
    }
</style>

<script>
    $(document).ready(function() {
        const $select = $('#service_partner_ids');
        const $chips = $('#selected-providers');
        const $hidden = $('#selected_partner_ids');

        const selectedMap = new Map();

        $select.select2({
            placeholder: $select.data('placeholder'),
            multiple: true,
            closeOnSelect: true, // changed to true
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: 0, // always show search
            dropdownAutoWidth: true
        });


        // SELECT
        $select.on('select2:select', function(e) {
            const id = String(e.params.data.id);
            const text = e.params.data.text;

            selectedMap.set(id, text);

            syncSelectFromMap();
        });


        // UNSELECT
        $select.on('select2:unselect', function(e) {
            const id = String(e.params.data.id);

            selectedMap.delete(id);

            syncSelectFromMap();
        });


        // CHIP REMOVE
        $(document).on('click', '.remove-chip', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const id = String($(this).data('id'));

            selectedMap.delete(id);

            syncSelectFromMap();
        });

        function syncSelectFromMap() {
            const values = [...selectedMap.keys()];

            $select.val(values).trigger('change.select2');

            // Force placeholder to stay visible and ensure it's at the start
            setTimeout(() => {
                const placeholder = $select.data('placeholder');
                const $searchField = $select.next().find('.select2-search__field');
                $searchField.attr('placeholder', placeholder).css('width', '100%');
            }, 10);

            renderChips();
        }



        function renderChips() {
            $chips.empty();

            selectedMap.forEach((text, id) => {
                $chips.append(`
                    <div class="provider-chip">
                        ${text}
                        <button type="button" class="remove-chip" data-id="${id}">&times;</button>
                    </div>
                `);
            });

            $hidden.val([...selectedMap.keys()].join(','));
        }


    });
</script>