<?php
$cf_perms = isset($custom_fields_permissions) ? $custom_fields_permissions : ['create' => false, 'read' => true, 'update' => false, 'delete' => false];
?>
<style>
    .drag-handle {
        cursor: grab;
    }

    .sortable-ghost {
        opacity: 0.4 !important;
        background: rgba(var(--secondary-color), 0.05) !important;
    }

    .sortable-chosen {
        background: rgba(var(--secondary-color), 0.05) !important;
        box-shadow: 0 8px 20px rgba(var(--secondary-color), 0.15);
    }

    .drag-handle:active {
        cursor: grabbing;
    }

    #providerCustomFieldTabs .nav-link {
        padding: 0.5rem 1.25rem;
    }

    #providerCustomFieldTabs .nav-link.active {
        background-color: var(--primary-color, #0073f0);
        color: #fff;
    }

    /* Language selector styling - matches Categories create form. */
    #providerCustomFieldModal .pcf-language-option {
        cursor: pointer;
        padding: 0.5rem 0;
    }

    #providerCustomFieldModal .pcf-language-text {
        font-size: 0.875rem;
        transition: color 0.3s ease;
        white-space: nowrap;
    }

    #providerCustomFieldModal .pcf-language-text.pcf-lang-active {
        color: var(--primary-color, #0073f0);
        font-weight: 500;
    }

    #providerCustomFieldModal .pcf-language-underline {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 2px;
        background: var(--primary-color, #0073f0);
        transition: width 0.3s ease;
        border-radius: 1px;
        width: 0%;
    }

    /* Page-scoped drawer defaults to prevent first-paint overlap before JS init. */
    #custom_fields_filter_drawer {
        transform: translateX(100%);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    #custom_fields_filter_drawer.open {
        transform: translateX(0);
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    [dir="rtl"] #custom_fields_filter_drawer {
        transform: translateX(-100%);
    }

    #custom_fields_filter_backdrop {
        display: none;
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1>
                <?= labels('custom_fields', 'Custom Fields')?>
            </h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('/admin/dashboard')?>">
                        <i class="fas fa-home-alt text-primary"></i>
                        <?= labels('dashboard', 'Dashboard')?>
                    </a>
                </div>
                <div class="breadcrumb-item">
                    <i class="fas fa-sliders-h text-warning"></i>
                    <?= labels('custom_fields', 'Custom Fields')?>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="card mb-0 shadow-sm">
            <div class="card-body p-0">
                <ul class="nav nav-pills nav-fill" id="providerCustomFieldTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active py-3" id="profile-tab" data-toggle="tab"
                            href="#tab-provider-profile" role="tab" aria-controls="tab-provider-profile" aria-selected="true">
                            <i class="fas fa-file-alt mr-2"></i><?= labels('provider_documents', 'Provider Documents')?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-3" id="bank-tab" data-toggle="tab"
                            href="#tab-provider-bank" role="tab" aria-controls="tab-provider-bank" aria-selected="false">
                            <i class="fas fa-university mr-2"></i><?= labels('provider_bank_details', 'Provider Bank Details')?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-3" id="customer-address-tab" data-toggle="tab"
                            href="#tab-customer-address" role="tab" aria-controls="tab-customer-address" aria-selected="false">
                            <i class="fas fa-map-marker-alt mr-2"></i><?= labels('customer_address_fields', 'Customer Address Fields')?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-3" id="handyman-tab" data-toggle="tab"
                            href="#tab-handyman-details" role="tab" aria-controls="tab-handyman-details" aria-selected="false">
                            <i class="fas fa-tools mr-2"></i><?= labels('handyman_custom_fields', 'Handyman Custom Fields')?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <!-- Toolbar -->
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-5 col-sm-6 mb-2">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="pcfCustomSearch"
                                        placeholder="<?= labels('search_here', 'Search here!')?>"
                                        aria-label="Search" aria-describedby="pcfCustomSearchBtn">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" id="pcfCustomSearchBtn" type="button">
                                            <i class="fa fa-search d-inline"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-secondary ml-2 mb-2 filter_button" type="button"
                                id="custom_fields_filter_button" aria-label="<?= labels('filters', 'Filters')?>">
                                <span class="material-symbols-outlined mt-1">filter_alt</span>
                            </button>
                            <div class="col d-flex justify-content-end align-items-center mb-2">
                                <?php if ($cf_perms['create']) { ?>
                                    <button type="button" class="btn btn-primary" id="pcfAddFieldBtn">
                                        <i class="fas fa-plus mr-1"></i>
                                        <?= labels('add_field', 'Add Field')?>
                                    </button>
                                <?php } ?>
                            </div>
                        </div>
                        <!-- Tab content — full width -->
                        <div class="tab-content" id="providerCustomFieldTabContent">
                            <?= view('backend/partials/custom_fields/table', [
                                'tab_pane_id' => 'tab-provider-profile',
                                'tab_pane_class' => 'tab-pane fade show active',
                                'table_id' => 'pcf_table_profile',
                                'custom_fields_group' => 'documents',
                                'cf_perms' => $cf_perms,
                            ]) ?>
                            <?= view('backend/partials/custom_fields/table', [
                                'tab_pane_id' => 'tab-provider-bank',
                                'tab_pane_class' => 'tab-pane fade',
                                'table_id' => 'pcf_table_bank',
                                'custom_fields_group' => 'bank_details',
                                'cf_perms' => $cf_perms,
                            ]) ?>
                            <?= view('backend/partials/custom_fields/table', [
                                'tab_pane_id' => 'tab-customer-address',
                                'tab_pane_class' => 'tab-pane fade',
                                'table_id' => 'pcf_table_address',
                                'custom_fields_group' => 'customer_address',
                                'cf_perms' => $cf_perms,
                            ]) ?>
                            <?= view('backend/partials/custom_fields/table', [
                                'tab_pane_id' => 'tab-handyman-details',
                                'tab_pane_class' => 'tab-pane fade',
                                'table_id' => 'pcf_table_handyman',
                                'custom_fields_group' => 'handyman_details',
                                'cf_perms' => $cf_perms,
                            ]) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div id="custom_fields_filter_backdrop"></div>
    <div class="drawer" id="custom_fields_filter_drawer">
        <section class="section">
            <div class="row">
                <div class="col-md-12">
                    <div class="bg-new-primary" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center;">
                            <div class="bg-white m-3 text-new-primary"
                                style="box-shadow: 0px 8px 26px #00b9f02e; display: inline-block; padding: 10px; height: 45px; width: 45px; border-radius: 15px;">
                                <span class="material-symbols-outlined">filter_alt</span>
                            </div>
                            <h3 class="mb-0" style="display: inline-block; font-size: 16px; margin-left: 10px;">
                                <?= labels('filters', 'Filters')?>
                            </h3>
                        </div>
                        <div id="custom_fields_filter_cancel" style="cursor: pointer;">
                            <span class="material-symbols-outlined mr-2">cancel</span>
                        </div>
                    </div>
                    <div class="row mt-4 mx-2">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="custom_fields_column_toggle_container"><?= labels('table_filters', 'Table filters')?></label>
                                <div id="custom_fields_column_toggle_container"></div>
                                <button class="btn btn-primary d-block mt-3" type="button" id="custom_fields_apply_filter">
                                    <?= labels('apply', 'Apply')?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Add/Edit Field modal -->
<div class="modal fade" id="providerCustomFieldModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pcfModalTitle">
                    <?= labels('add_custom_field', 'Add Custom Field')?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?= labels('close', 'Close')?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="providerCustomFieldForm">
                    <div class="container-fluid">
                        <input type="hidden" id="pcf_id" value="">
                        <input type="hidden" id="pcf_field_group" value="documents">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required" for="pcf_field_type">
                                        <?= labels('field_type', 'Field Type')?>
                                    </label>
                                    <select id="pcf_field_type" class="form-control">
                                        <option value="text">
                                            <?= labels('text', 'Text')?>
                                        </option>
                                        <option value="number">
                                            <?= labels('number', 'Number')?>
                                        </option>
                                        <option value="textarea">
                                            <?= labels('textarea', 'Textarea')?>
                                        </option>
                                        <option value="file">
                                            <?= labels('file', 'File')?>
                                        </option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <?= labels(
    'pcf_type_hint',
    'Only field types supported by the current schema are shown.'
)?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required" for="pcf_context">
                                        <?= labels('context', 'Context')?>
                                    </label>
                                    <select id="pcf_context" class="form-control" disabled>
                                        <option value="documents">
                                            <?= labels('provider_documents', 'Provider Documents')?>
                                        </option>
                                        <option value="bank_details">
                                            <?= labels('provider_bank_details', 'Provider Bank Details')?>
                                        </option>
                                        <option value="customer_address">
                                            <?= labels('customer_address_fields', 'Customer Address Fields')?>
                                        </option>
                                        <option value="handyman_details">
                                            <?= labels('handyman_custom_fields', 'Handyman Custom Fields')?>
                                        </option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <?= labels(
    'pcf_context_hint',
    'Context is chosen by the active tab and cannot be changed here.'
)?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="pcfFileConfigRow" style="display:none;">
                            <div class="col-md-12">
                                <div class="alert alert-info py-2 mb-2" style="border-radius: 8px;">
                                    <strong><?= labels('file_config', 'File configuration') ?>:</strong>
                                    <?= labels('file_config_hint', 'Used only when field type is "File".') ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pcf_file_allowed_types">
                                        <?= labels('allowed_file_types', 'Allowed File Types') ?>
                                    </label>
                                    <select id="pcf_file_allowed_types" class="form-control" multiple>
                                        <option value="images"><?= labels('images', 'Images') ?></option>
                                        <option value="video"><?= labels('video', 'Video') ?></option>
                                        <option value="audio"><?= labels('audio', 'Audio') ?></option>
                                        <option value="documents"><?= labels('documents', 'Documents') ?></option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <?= labels('allowed_file_types_required_note', 'At least one file type must be selected.') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="pcf_file_max_size_mb">
                                        <?= labels('max_file_size_mb', 'Max Size (MB)') ?>
                                    </label>
                                    <input
                                        type="number"
                                        id="pcf_file_max_size_mb"
                                        class="form-control"
                                        min="1"
                                        max="5"
                                        step="1"
                                        value="1"
                                    >
                                    <small class="form-text text-muted">
                                        <?= labels('max_file_size_note', 'Min 1 MB, max 5 MB.') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="pcf_file_max_files">
                                        <?= labels('max_number_of_files', 'Max # of Files') ?>
                                    </label>
                                    <input
                                        type="number"
                                        id="pcf_file_max_files"
                                        class="form-control"
                                        min="1"
                                        step="1"
                                        value="1"
                                    >
                                    <small class="form-text text-muted">
                                        <?= labels('max_files_note', 'Minimum is 1.') ?>
                                    </small>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>
                                        <?= labels('required', 'Required')?>
                                    </label>
                                    <div>
                                        <label class="custom-switch">
                                            <input type="checkbox" class="custom-switch-input" id="pcf_is_required">
                                            <span class="custom-switch-indicator"></span>
                                            <span class="custom-switch-description">
                                                <?= labels('pcf_required_hint', 'This field shall be required')?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>
                                        <?= labels('visible', 'Visible')?>
                                    </label>
                                    <div>
                                        <label class="custom-switch">
                                            <input type="checkbox" class="custom-switch-input" id="pcf_is_active"
                                                checked>
                                            <span class="custom-switch-indicator"></span>
                                            <span class="custom-switch-description">
                                                <?= labels('pcf_visible_hint', 'This field shall be visible in the forms.')?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-12">
                                <?php
// Sort languages so default language appears first (same as Categories create form).
$sorted_languages = [];
if (isset($languages) && is_array($languages)) {
    $sorted_languages = function_exists('sort_languages_with_default_first')
        ? sort_languages_with_default_first($languages)
        : $languages;
}

$pcf_current_language = '';
foreach ($sorted_languages as $lang) {
    if (!empty($lang['is_default'])) {
        $pcf_current_language = (string)$lang['code'];
        break;
    }
}
if ($pcf_current_language === '' && !empty($sorted_languages[0]['code'])) {
    $pcf_current_language = (string)$sorted_languages[0]['code'];
}
?>

                                <input type="hidden" id="pcf_default_language_code"
                                    value="<?= esc($pcf_current_language)?>">

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div class="d-flex flex-wrap align-items-center gap-4">
                                            <?php foreach ($sorted_languages as $language) { ?>
                                            <div class="pcf-language-option position-relative <?=!empty($language['is_default']) ? 'selected' : ''?>"
                                                id="pcf-language-<?= esc($language['code'])?>"
                                                data-language="<?= esc($language['code'])?>">
                                                <span
                                                    class="pcf-language-text px-2 <?=!empty($language['is_default']) ? 'pcf-lang-active' : 'text-muted'?>">
                                                    <?= esc($language['language'] ?? '')?>
                                                    <?=!empty($language['is_default']) ? '(Default)' : ''?>
                                                </span>
                                                <div class="pcf-language-underline"
                                                    style="width: <?=!empty($language['is_default']) ? '100%' : '0'?>;">
                                                </div>
                                            </div>
                                            <?php
}?>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <?php foreach ($sorted_languages as $language) { ?>
                                    <?php
    $code = (string)($language['code'] ?? '');
    $isDefault = !empty($language['is_default']);
?>
                                    <div class="col-md-6" id="pcfTranslationDiv-<?= esc($code)?>"
                                        <?=$code===$pcf_current_language ? 'style="display: block;"'
                                        : 'style="display: none;"'?>>
                                        <div class="form-group">
                                            <label for="pcf_label_<?= esc($code)?>" <?=$isDefault ? 'class="required"'
                                                : ''?>>
                                                <?= labels('label', 'Label') . ($isDefault ? '' : ' (' . strtoupper($code) . ')')?>
                                            </label>
                                            <input type="text" id="pcf_label_<?= esc($code)?>"
                                                name="field_label[<?= esc($code)?>]" class="form-control"
                                                placeholder="<?= labels('enter_label_here', 'Enter label here')?>"
                                                <?=$isDefault ? 'required' : ''?>>
                                            <?php if ($isDefault) { ?>
                                            <small class="form-text text-muted">
                                                <?= labels('pcf_label_hint', 'This is the display name shown in the forms.')?>
                                            </small>
                                            <?php
    }?>
                                        </div>
                                    </div>
                                    <?php
}?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <?= labels('cancel', 'Cancel')?>
                </button>
                <button type="button" class="btn btn-primary" id="pcfSaveBtn">
                    <?= labels('save', 'Save')?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var pcfCsrfName = '<?= csrf_token() ?>';
    var pcfCsrfHash = '<?= csrf_hash() ?>';
    var CAN_UPDATE_CUSTOM_FIELDS = <?= $cf_perms['update'] ? 'true' : 'false' ?>;
    var CAN_DELETE_CUSTOM_FIELDS = <?= $cf_perms['delete'] ? 'true' : 'false' ?>;

    // Modal title strings — Add vs Edit depending on whether pcf_id is set.
    var pcfModalTitleAdd = <?= json_encode(labels('add_custom_field', 'Add Custom Field')) ?>;
    var pcfModalTitleEdit = <?= json_encode(labels('edit_custom_field', 'Edit Custom Field')) ?>;

    // Category → extensions map (mirrors CustomFieldsUpsertValidator::FILE_TYPE_CATEGORIES).
    var pcfFileTypeCategories = {
        images:    ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg', '.tif', '.tiff'],
        video:     ['.mp4', '.mov', '.avi', '.mkv', '.webm', '.wmv', '.flv', '.m4v'],
        audio:     ['.mp3', '.wav', '.aac', '.ogg', '.flac', '.m4a', '.wma'],
        documents: ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.txt', '.csv', '.rtf']
    };

    // Reverse-maps a stored extensions array back to category names for select2 pre-selection.
    function pcf_extensions_to_categories(extensions) {
        if (!Array.isArray(extensions) || extensions.length === 0) {
            return [];
        }
        var categories = [];
        Object.keys(pcfFileTypeCategories).forEach(function (cat) {
            var catExts = pcfFileTypeCategories[cat];
            var allPresent = catExts.length > 0 && catExts.every(function (ext) {
                return extensions.indexOf(ext) !== -1;
            });
            if (allPresent) {
                categories.push(cat);
            }
        });
        return categories;
    }

    // Decode escaped values sent by backend for safe text rendering.
    function pcf_decode_html(value) {
        return $('<textarea/>').html((value || '').toString()).text();
    }

    // Fill form inputs from clicked row before opening edit modal.
    function pcf_prefill_modal_for_edit(row) {
        row = row || {};
        $('#pcf_id').val((row.id || '').toString());
        $('#pcf_field_type').val((row.field_type_plain || '').toString());
        // Reset file config defaults. If DB has file_config JSON, we will override below.
        $('#pcf_file_allowed_types').val(null);
        $('#pcf_file_max_size_mb').val(1);
        $('#pcf_file_max_files').val(1);

        // Load file_config JSON and map it back to the UI inputs.
        var fileConfigRaw = pcf_decode_html(row.file_config_plain || '');
        var fileConfig = {};
        if (fileConfigRaw) {
            try {
                fileConfig = JSON.parse(fileConfigRaw);
            } catch (e) {
                fileConfig = {};
            }
        }

        var allowedTypesArr = fileConfig.allowed_types;
        if (!Array.isArray(allowedTypesArr)) {
            allowedTypesArr = [];
        }
        // Set native value only — shown.bs.modal will trigger select2 sync after modal is visible.
        $('#pcf_file_allowed_types').val(pcf_extensions_to_categories(allowedTypesArr));

        var maxSize = parseInt(fileConfig.max_size_mb, 10);
        if (isNaN(maxSize) || maxSize < 1) maxSize = 1;
        if (maxSize > 5) maxSize = 5;
        $('#pcf_file_max_size_mb').val(maxSize);

        var maxFiles = parseInt(fileConfig.max_files, 10);
        $('#pcf_file_max_files').val(isNaN(maxFiles) || maxFiles < 1 ? 1 : maxFiles);

        pcf_toggle_file_config_row();
        $('#pcf_is_required').prop('checked', parseInt(row.required_raw, 10) === 1);
        $('#pcf_is_active').prop('checked', parseInt(row.visible_raw, 10) === 1);

        var fieldGroup = (row.field_group_plain || '').toString();
        if (fieldGroup === 'documents' || fieldGroup === 'bank_details' || fieldGroup === 'customer_address' || fieldGroup === 'handyman_details') {
            $('#pcf_context').val(fieldGroup);
            $('#pcf_field_group').val(fieldGroup);
        }

        // Prefill all language label inputs from row translations data.
        $('#providerCustomFieldModal input[name^="field_label["]').val('');
        var translations = row.translations || {};
        $.each(translations, function (langCode, label) {
            $('#pcf_label_' + langCode).val(label);
        });
        // Fallback: if no translations loaded, use base label for default language.
        var defaultLanguage = ($('#pcf_default_language_code').val() || '').toString();
        if (defaultLanguage && !translations[defaultLanguage]) {
            $('#pcf_label_' + defaultLanguage).val(pcf_decode_html(row.field_label_plain || ''));
        }
    }

    function pcf_refresh_active_table() {
        var $activeTable = $("#providerCustomFieldTabContent .tab-pane.active table[data-toggle='table']");
        if ($activeTable.length) {
            $activeTable.bootstrapTable('refresh');
        }
    }

    // Groups that must always retain at least one field and one visible field.
    var pcfProtectedGroups = ['bank_details', 'customer_address'];

    // Get the bootstrap-table jQuery element for the currently active tab.
    function pcf_get_active_table() {
        return $("#providerCustomFieldTabContent .tab-pane.active table[data-toggle='table']");
    }

    // Exposed for bootstrap-table data-events (see partial: custom_fields/table.php).
    window.custom_fields_action_events = {
        'click .custom-fields-edit-btn': function (e, value, row) {
            e.preventDefault();
            pcf_prefill_modal_for_edit(row);
            $('#pcfModalTitle').text(pcfModalTitleEdit);
            $('#providerCustomFieldModal').modal('show');
        },
        'click .custom-fields-delete-btn': function (e, value, row) {
            e.preventDefault();
            e.stopPropagation();

            var group = (row.field_group_plain || '').toString();
            if (pcfProtectedGroups.indexOf(group) !== -1) {
                var $tbl = pcf_get_active_table();
                var allRows = $tbl.length ? $tbl.bootstrapTable('getData') : [];

                // Block deleting the last field in the group.
                if (allRows.length <= 1) {
                    if (typeof showToastMessage === 'function') {
                        showToastMessage('<?= labels('cannot_delete_last_custom_field', 'At least one custom field must exist. You cannot delete the last field.')?>', 'error');
                    }
                    return;
                }

                // Block deleting the last visible field in the group.
                if (parseInt(row.visible_raw, 10) === 1) {
                    var visibleCount = 0;
                    for (var i = 0; i < allRows.length; i++) {
                        if (parseInt(allRows[i].visible_raw, 10) === 1) {
                            visibleCount++;
                        }
                    }
                    if (visibleCount <= 1) {
                        if (typeof showToastMessage === 'function') {
                            showToastMessage('<?= labels('cannot_delete_last_visible_custom_field', 'At least one custom field must remain visible. Make another field visible before deleting this one.')?>', 'error');
                        }
                        return;
                    }
                }
            }

            Swal.fire({
                title: '<?= labels('are_your_sure', 'Are you sure?')?>',
                text: "<?= labels('you_wont_be_able_to_revert_this', "You won't be able to revert this!")?>",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!')?>',
                cancelButtonText: '<?= labels('cancel', 'Cancel')?>'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                $.ajax({
                    url: "<?= base_url('admin/custom-fields/delete')?>",
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        [pcfCsrfName]: pcfCsrfHash,
                        id: row.id
                    },
                    success: function (response) {
                        if (response && response.csrfName && response.csrfHash) {
                            pcfCsrfName = response.csrfName;
                            pcfCsrfHash = response.csrfHash;
                        }

                        if (response && response.error === false) {
                            if (typeof showToastMessage === 'function') {
                                showToastMessage(response.message || '<?= labels('data_deleted_successfully', 'Data deleted successfully')?>', 'success');
                            }
                            pcf_refresh_active_table();
                            return;
                        }

                        if (typeof showToastMessage === 'function') {
                            showToastMessage((response && response.message) ? response.message : '<?= labels('something_went_wrong', 'Something went wrong')?>', 'error');
                        }
                    },
                    error: function () {
                        if (typeof showToastMessage === 'function') {
                            showToastMessage('<?= labels('something_went_wrong', 'Something went wrong')?>', 'error');
                        }
                    }
                });
            });
        }
    };

    // Keep the modal "Context" (documents/bank_details) aligned with the active tab.
    // We store the DB value in a hidden input (`pcf_field_group`) because the
    // visible dropdown is disabled (the admin chooses context via tabs).
    function pcf_get_active_group() {
        var $activeTab = $("#providerCustomFieldTabs .nav-link.active");
        var href = ($activeTab.attr("href") || "").toString();

        // Current tabs map:
        // - #tab-provider-profile  => documents
        // - #tab-provider-bank     => bank_details
        // - #tab-customer-address  => customer_address
        // - #tab-handyman-details  => handyman_details
        if (href === "#tab-provider-bank") {
            return "bank_details";
        }
        if (href === "#tab-customer-address") {
            return "customer_address";
        }
        if (href === "#tab-handyman-details") {
            return "handyman_details";
        }
        return "documents";
    }

    function pcf_sync_context_inputs() {
        var group = pcf_get_active_group();
        $("#pcf_context").val(group);
        $("#pcf_field_group").val(group);
        pcf_filter_field_types();
    }

    function pcf_filter_field_types() {
        var group = pcf_get_active_group();
        var allowed;
        if (group === 'documents') {
            allowed = ['file'];
        } else if (group === 'handyman_details') {
            allowed = ['text', 'number', 'textarea', 'file'];
        } else {
            // bank_details and customer_address: text, number, textarea only
            allowed = ['text', 'number', 'textarea'];
        }

        var $select = $('#pcf_field_type');
        var currentVal = $select.val();

        $select.find('option').each(function () {
            var val = $(this).val();
            if (allowed.indexOf(val) !== -1) {
                $(this).show().prop('disabled', false);
            } else {
                $(this).hide().prop('disabled', true);
            }
        });

        // If currently selected value is no longer allowed, reset to first allowed.
        if (allowed.indexOf(currentVal) === -1) {
            $select.val(allowed[0]);
        }

        pcf_toggle_file_config_row();
    }

    function pcf_toggle_file_config_row() {
        var fieldType = ($('#pcf_field_type').val() || '').toString();
        if (fieldType === 'file') {
            $('#pcfFileConfigRow').show();
        } else {
            $('#pcfFileConfigRow').hide();
        }
    }

    function pcf_apply_file_config_defaults() {
        // Keep defaults very simple. Validator will normalize again server-side.
        var fieldType = ($('#pcf_field_type').val() || '').toString();
        if (fieldType !== 'file') {
            return;
        }

        var maxSize = parseInt($('#pcf_file_max_size_mb').val(), 10);
        if (isNaN(maxSize) || maxSize < 1) {
            $('#pcf_file_max_size_mb').val(1);
        } else if (maxSize > 5) {
            $('#pcf_file_max_size_mb').val(5);
        }

        var maxFiles = parseInt($('#pcf_file_max_files').val(), 10);
        if (isNaN(maxFiles) || maxFiles < 1) {
            $('#pcf_file_max_files').val(1);
        }
    }

    function pcf_get_allowed_types() {
        var $s = $('#pcf_file_allowed_types');
        // Prefer Select2's internal data (authoritative) when initialized.
        if ($s.hasClass('select2-hidden-accessible')) {
            return $.map($s.select2('data'), function (item) { return item.id; });
        }
        return $s.val() || [];
    }

    function pcf_build_file_config_json() {
        // Build the JSON config for file fields from the UI inputs.
        var allowedTypesArr = pcf_get_allowed_types();

        var maxSize = parseInt($('#pcf_file_max_size_mb').val(), 10);
        if (isNaN(maxSize) || maxSize < 1) {
            maxSize = 1;
        } else if (maxSize > 5) {
            maxSize = 5;
        }

        var maxFiles = parseInt($('#pcf_file_max_files').val(), 10);
        if (isNaN(maxFiles) || maxFiles < 1) {
            maxFiles = 1;
        }

        return JSON.stringify({
            allowed_types: allowedTypesArr,
            max_size_mb: maxSize,
            max_files: maxFiles
        });
    }

    // Search button click handler - triggers refresh on the active table.
    $("#pcfCustomSearchBtn").on('click', function () {
        pcf_refresh_active_table();
    });

    // Global name must match data-query-params on the table (partial: custom_fields/table.php).
    function custom_fields_query_params(p) {
        return {
            search: $("#pcfCustomSearch").val() ? $("#pcfCustomSearch").val() : p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
        };
    }

    // When switching tabs, refresh the newly shown table (so it fetches correct group).
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr("href");
        var $table = $(target).find("table[data-toggle='table']");
        if ($table.length) {
            $table.bootstrapTable('refresh');
        }
        // If the modal is already open, also keep its context in sync.
        pcf_sync_context_inputs();
    });

    // When opening the modal, sync context to the active tab.
    $('#providerCustomFieldModal').on('show.bs.modal', function () {
        pcf_sync_context_inputs();
        pcf_toggle_file_config_row();
        pcf_apply_file_config_defaults();
    });

    // Language switcher for label field (mirrors Categories create form behavior, scoped to this modal).
    $(document).ready(function () {
        // Defensive reset to ensure drawer is closed on initial paint/navigation restores.
        $('#custom_fields_filter_drawer').removeClass('open');
        $('#custom_fields_filter_backdrop').hide();

        // Filter drawer + table column toggles (admin partners pattern). Same options apply to both tabs' tables.
        if (typeof for_drawer === 'function') {
            for_drawer('#custom_fields_filter_button', '#custom_fields_filter_drawer', '#custom_fields_filter_backdrop', '#custom_fields_filter_cancel');
        }
        $('#custom_fields_filter_backdrop').on('click', function () {
            $('#custom_fields_filter_drawer').removeClass('open');
            $(this).fadeOut(200);
        });
        $(document).on('mousedown', function (e) {
            var $drawer = $('#custom_fields_filter_drawer');
            if (!$drawer.hasClass('open')) {
                return;
            }

            var $target = $(e.target);
            var clickedInsideDrawer = $target.closest('#custom_fields_filter_drawer').length > 0;
            var clickedFilterButton = $target.closest('#custom_fields_filter_button').length > 0;

            if (!clickedInsideDrawer && !clickedFilterButton) {
                $drawer.removeClass('open');
                $('#custom_fields_filter_backdrop').fadeOut(200);
            }
        });

        var customFieldsTableIds = ['pcf_table_profile', 'pcf_table_bank', 'pcf_table_address', 'pcf_table_handyman'];

        function customFieldsApplyColumnVisibilityFromDrawer() {
            $('.custom-fields-column-toggle').each(function () {
                var field = $(this).data('field');
                var isVisible = $(this).prop('checked');
                customFieldsTableIds.forEach(function (tid) {
                    var $t = $('#' + tid);
                    if (!$t.length) {
                        return;
                    }
                    try {
                        if (isVisible) {
                            $t.bootstrapTable('showColumn', field);
                        } else {
                            $t.bootstrapTable('hideColumn', field);
                        }
                    } catch (ignore) {
                        // Table may not be initialized yet on first paint; Apply will work after load.
                    }
                });
            });
        }

        (function customFieldsInitColumnToggleDrawer() {
            var $container = $('#custom_fields_column_toggle_container');
            $container.empty();
            var colIndex = 0;
            var row;
            $('#pcf_table_profile thead th').each(function () {
                var $th = $(this);
                var field = $th.data('field');
                if (!field || field === 'operations') {
                    return;
                }
                var label = $th.text().trim();
                var visible = $th.data('visible') !== false;
                if (colIndex % 2 === 0) {
                    row = $('<div>').addClass('row');
                    $container.append(row);
                }
                var checkbox = $('<input>')
                    .attr('type', 'checkbox')
                    .addClass('custom-fields-column-toggle')
                    .data('field', field)
                    .prop('checked', visible);
                var $label = $('<label>').append(checkbox).append(' ' + label);
                var columnDiv = $('<div>').addClass('col-md-6');
                columnDiv.append($label);
                row.append(columnDiv);
                colIndex++;
            });
            customFieldsApplyColumnVisibilityFromDrawer();
        })();

        $('#custom_fields_apply_filter').on('click', function () {
            $('#custom_fields_filter_drawer').removeClass('open');
            $('#custom_fields_filter_backdrop').fadeOut(200);
            customFieldsApplyColumnVisibilityFromDrawer();
        });

        let current_language = ($('#pcf_default_language_code').val() || '').toString();

        // Add flow should always open with a clean modal state.
        $('#pcfAddFieldBtn').on('click', function () {
            $('#providerCustomFieldForm')[0].reset();
            $('#pcf_id').val('');
            $('#pcfModalTitle').text(pcfModalTitleAdd);
            pcf_sync_context_inputs();
            const def = ($('#pcf_default_language_code').val() || '').toString();
            if (def) {
                $('#pcf-language-' + def).trigger('click');
            }
            $('#providerCustomFieldModal').modal('show');
        });

        // Show/hide file configuration based on field type selection.
        $('#pcf_field_type').on('change', function () {
            pcf_toggle_file_config_row();
            pcf_apply_file_config_defaults();
        });

        // Fallback: pick first language if default was not resolved.
        if (!current_language) {
            const firstLang = $('#providerCustomFieldModal .pcf-language-option').first().data('language');
            current_language = (firstLang || '').toString();
            $('#pcf_default_language_code').val(current_language);
        }

        $(document).on('click', '#providerCustomFieldModal .pcf-language-option', function () {
            const language = ($(this).data('language') || '').toString();
            if (!language || language === current_language) {
                return;
            }

            // Underlines
            $('#providerCustomFieldModal .pcf-language-underline').css('width', '0%');
            $('#pcf-language-' + language).find('.pcf-language-underline').css('width', '100%');

            // Text styles
            $('#providerCustomFieldModal .pcf-language-text')
                .removeClass('pcf-lang-active')
                .addClass('text-muted');
            $('#pcf-language-' + language).find('.pcf-language-text')
                .removeClass('text-muted')
                .addClass('pcf-lang-active');

            // Panels
            $('#pcfTranslationDiv-' + language).show();
            $('#pcfTranslationDiv-' + current_language).hide();

            current_language = language;
        });

        // Ensure default language UI is selected on modal open.
        $('#providerCustomFieldModal').on('show.bs.modal', function () {
            const def = ($('#pcf_default_language_code').val() || '').toString();
            if (def) {
                $('#pcf-language-' + def).trigger('click');
            }
        });

        // Save custom field via AJAX.
        $('#pcfSaveBtn').on('click', function () {
            var $btn = $(this);
            var pcfId = parseInt(($('#pcf_id').val() || '').toString(), 10);
            var fieldType = ($('#pcf_field_type').val() || '').toString().trim();
            var fieldGroup = ($('#pcf_field_group').val() || '').toString().trim();
            var labelsByLanguage = {};
            $('#providerCustomFieldModal input[name^="field_label["]').each(function () {
                var inputName = $(this).attr('name') || '';
                var match = inputName.match(/^field_label\[(.+)\]$/);
                if (match && match[1]) {
                    labelsByLanguage[match[1]] = ($(this).val() || '').toString().trim();
                }
            });

            var defaultLanguage = ($('#pcf_default_language_code').val() || '').toString().trim();
            if (!labelsByLanguage[defaultLanguage]) {
                if (typeof showToastMessage === 'function') {
                    showToastMessage('<?= labels('default_language_label_required', 'Label is required for default language.')?>', 'error');
                }
                return;
            }

            if (fieldType === 'file' && pcf_get_allowed_types().length === 0) {
                if (typeof showToastMessage === 'function') {
                    showToastMessage('<?= labels('file_types_required', 'Please select at least one allowed file type.')?>', 'error');
                }
                return;
            }

            // When updating a field in a protected group: block if unchecking visible would leave zero visible fields.
            var isVisible = $('#pcf_is_active').is(':checked') ? 1 : 0;
            if (pcfId > 0 && isVisible === 0 && pcfProtectedGroups.indexOf(fieldGroup) !== -1) {
                var $tbl = pcf_get_active_table();
                var allRows = $tbl.length ? $tbl.bootstrapTable('getData') : [];
                var visibleCount = 0;
                for (var vi = 0; vi < allRows.length; vi++) {
                    if (parseInt(allRows[vi].visible_raw, 10) === 1) {
                        visibleCount++;
                    }
                }
                // Check if this row is currently visible and we'd be hiding the last one.
                var currentRow = null;
                for (var ri = 0; ri < allRows.length; ri++) {
                    if (parseInt(allRows[ri].id, 10) === pcfId) {
                        currentRow = allRows[ri];
                        break;
                    }
                }
                if (currentRow && parseInt(currentRow.visible_raw, 10) === 1 && visibleCount <= 1) {
                    if (typeof showToastMessage === 'function') {
                        showToastMessage('<?= labels('cannot_hide_last_custom_field', 'At least one custom field must remain visible.')?>', 'error');
                    }
                    return;
                }
            }

            $btn.prop('disabled', true);
            $.ajax({
                url: pcfId > 0
                    ? "<?= base_url('admin/custom-fields/update')?>"
                    : "<?= base_url('admin/custom-fields/save')?>",
                type: 'POST',
                dataType: 'json',
                data: {
                    [pcfCsrfName]: pcfCsrfHash,
                    id: pcfId > 0 ? pcfId : 0,
                    field_type: fieldType,
                    field_group: fieldGroup,
                    required: $('#pcf_is_required').is(':checked') ? 1 : 0,
                    visible: $('#pcf_is_active').is(':checked') ? 1 : 0,
                        field_label: labelsByLanguage,
                    file_config: fieldType === 'file' ? pcf_build_file_config_json() : null
                },
                success: function (response) {
                    if (response && response.csrfName && response.csrfHash) {
                        pcfCsrfName = response.csrfName;
                        pcfCsrfHash = response.csrfHash;
                    }

                    if (response && response.error === false) {
                        if (typeof showToastMessage === 'function') {
                            showToastMessage(response.message || '<?= labels('data_saved_successfully', 'Data saved successfully')?>', 'success');
                        }
                        $('#providerCustomFieldModal').modal('hide');
                        $('#providerCustomFieldForm')[0].reset();
                        $('#pcf_file_allowed_types').val(null).trigger('change');
                        pcf_sync_context_inputs();
                        var $activeTable = $("#providerCustomFieldTabContent .tab-pane.active table[data-toggle='table']");
                        if ($activeTable.length) {
                            // Refresh table in sort_order after successful save.
                            $activeTable.bootstrapTable('refreshOptions', {
                                sortName: 'sort_order',
                                sortOrder: 'asc'
                            });
                            $activeTable.bootstrapTable('refresh');
                        }
                        return;
                    }

                    if (typeof showToastMessage === 'function') {
                        showToastMessage((response && response.message) ? response.message : '<?= labels('something_went_wrong', 'Something went wrong')?>', 'error');
                    }
                },
                error: function () {
                    if (typeof showToastMessage === 'function') {
                        showToastMessage('<?= labels('something_went_wrong', 'Something went wrong')?>', 'error');
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        });

        // Initialize select2 for allowed file types on first modal open (element must be visible for
        // correct width calculation), then sync native select state to select2 on every open.
        $('#providerCustomFieldModal').on('shown.bs.modal', function () {
            var $s = $('#pcf_file_allowed_types');
            if (!$s.hasClass('select2-hidden-accessible')) {
                $s.select2({
                    dropdownParent: $('#providerCustomFieldModal'),
                    placeholder: '<?= labels('select_file_types', 'Select file types...') ?>',
                    allowClear: true,
                    width: '100%'
                });
            }
            // Sync whatever native value was set by prefill or form reset.
            $s.trigger('change');
        });
    });
</script>

<script>
    // --- Sortable drag-and-drop reorder (mirrors featured_sections pattern) ---
    var pcfSortableInstances = {};
    var pcfTableIds = ['pcf_table_profile', 'pcf_table_bank', 'pcf_table_address', 'pcf_table_handyman'];

    function pcfInitSortable(tableId) {
        var tbody = document.querySelector('#' + tableId + ' tbody');
        if (!tbody || pcfSortableInstances[tableId]) return;

        pcfSortableInstances[tableId] = new Sortable(tbody, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            forceFallback: true,
            fallbackOnBody: true,
            delay: 0,
            delayOnTouchOnly: true,
            touchStartThreshold: 3,
        });
    }

    function pcfCheckUpdateOrderButton(tableId) {
        var rowCount = $('#' + tableId).bootstrapTable('getData').length;
        var $btn = $(".pcf-update-order-btn[data-table-id='" + tableId + "']");
        if (rowCount <= 1) {
            $btn.addClass('d-none');
        } else {
            $btn.removeClass('d-none');
        }
    }

    // Init sortable + toggle button after bootstrap-table loads data for each table.
    pcfTableIds.forEach(function (tid) {
        $('#' + tid).on('post-body.bs.table', function () {
            // Destroy old instance so it re-binds after table refresh/pagination.
            if (pcfSortableInstances[tid]) {
                pcfSortableInstances[tid].destroy();
                pcfSortableInstances[tid] = null;
            }
            pcfInitSortable(tid);
            pcfCheckUpdateOrderButton(tid);
        });
    });

    // "Update Order" button click handler.
    $(document).on('click', '.pcf-update-order-btn', function () {
        var $btn = $(this);
        var tableId = $btn.data('table-id');
        var group = $btn.data('group');

        var ids = [];
        $('#' + tableId + ' tbody tr').each(function () {
            var id = $(this).data('uniqueid');
            if (id) ids.push(id);
        });

        if (ids.length === 0) return;

        $btn.prop('disabled', true);
        var originalHtml = $btn.html();
        $btn.removeClass('btn-primary').addClass('btn-secondary');
        $btn.html('<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>');

        $.ajax({
            type: 'POST',
            url: "<?= base_url('admin/custom-fields/change-order') ?>",
            dataType: 'json',
            data: {
                [pcfCsrfName]: pcfCsrfHash,
                ids: JSON.stringify(ids),
                group: group
            },
            success: function (result) {
                if (result && result.csrfName && result.csrfHash) {
                    pcfCsrfName = result.csrfName;
                    pcfCsrfHash = result.csrfHash;
                }
                if (result && result.error === false) {
                    if (typeof showToastMessage === 'function') {
                        showToastMessage(result.message || '<?= labels('order_updated_successfully', 'Order updated successfully') ?>', 'success');
                    }
                    // Refresh the table to reflect new sort_order values.
                    $('#' + tableId).bootstrapTable('refresh');
                } else {
                    if (typeof showToastMessage === 'function') {
                        showToastMessage((result && result.message) ? result.message : '<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
                    }
                }
            },
            error: function () {
                if (typeof showToastMessage === 'function') {
                    showToastMessage('<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
                }
            },
            complete: function () {
                $btn.prop('disabled', false);
                $btn.removeClass('btn-secondary').addClass('btn-primary');
                $btn.html(originalHtml);
            }
        });
    });
</script>
