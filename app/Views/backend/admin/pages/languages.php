<?php
helper('form');
$db      = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$permissions = get_permission($user1[0]['id']);
?>
<div class="main-wrapper ">
    <!-- Main Content -->
    <div class="main-content">
        <section class="section">
            <div class="section-header mt-2">
                <h1><?= labels("languages", "Languages") ?></h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                    <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                    <div class="breadcrumb-item"><?= labels("languages", "Languages") ?></a></div>
                </div>
            </div>
            <div class="section-body">
                <div id="output-status"></div>
                <!-- Form Card - Top Position -->
                <div class="row mb-2">
                    <?php if ($permissions['create']['settings'] == 1) : ?>
                        <div class="col-12">
                            <?= form_open('/admin/languages/insert', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add', 'enctype' => "multipart/form-data"]); ?>
                            <div class="card">
                                <div class="row m-0">
                                    <div class="col border_bottom_for_cards">
                                        <div class="toggleButttonPostition"><?= labels('add', 'Add') ?></div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label for="name"><?= labels('language', 'Language') ?> <span class="text-danger">*</span></label>
                                                <input id="name" required class="form-control" type="text" name="language_name" placeholder="<?= labels('enter_name_of_language', 'Enter the name of the langauge here') ?>">
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label for="Code"><?= labels('language_code', 'Code') ?> <span class="text-danger">*</span></label>
                                                <input id="name" required class="form-control" type="text" name="language_code" placeholder="<?= labels('enter_name_of_code', 'Enter the name of the code here') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-12 col-md-6">
                                            <div class="form-group sample-files-section">
                                                <label for=""><?= labels('sample_files', 'Sample Files') ?></label>
                                                <div class="row">
                                                    <div class="col-6 col-sm-3 text-center mb-3">
                                                        <small class="text-muted d-block"><?= labels('panel', 'Panel') ?></small>
                                                        <a class="btn btn-sm text-primary download-sample-btn"
                                                            href="<?= APP_URL ?>download_sample_file/panel"
                                                            data-category="panel"
                                                            title="Download Panel Sample File">
                                                            <span class="material-symbols-outlined" style="font-size:30px;">download_for_offline</span>
                                                        </a>
                                                    </div>
                                                    <div class="col-6 col-sm-3 text-center mb-3">
                                                        <small class="text-muted d-block"><?= labels('web', 'Web') ?></small>
                                                        <a class="btn btn-sm text-primary download-sample-btn"
                                                            href="<?= APP_URL ?>download_sample_file/web"
                                                            data-category="web"
                                                            title="Download Web Sample File">
                                                            <span class="material-symbols-outlined" style="font-size:30px;">download_for_offline</span>
                                                        </a>
                                                    </div>
                                                    <div class="col-6 col-sm-3 text-center mb-3">
                                                        <small class="text-muted d-block"><?= labels('customer_app', 'Customer App') ?></small>
                                                        <a class="btn btn-sm text-primary download-sample-btn"
                                                            href="<?= APP_URL ?>download_sample_file/customer"
                                                            data-category="customer"
                                                            title="Download Customer App Sample File">
                                                            <span class="material-symbols-outlined" style="font-size:30px;">download_for_offline</span>
                                                        </a>
                                                    </div>
                                                    <div class="col-6 col-sm-3 text-center mb-3">
                                                        <small class="text-muted d-block"><?= labels('provider_app', 'Provider App') ?></small>
                                                        <a class="btn btn-sm text-primary download-sample-btn"
                                                            href="<?= APP_URL ?>download_sample_file/provider"
                                                            data-category="provider"
                                                            title="Download Provider App Sample File">
                                                            <span class="material-symbols-outlined" style="font-size:30px;">download_for_offline</span>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-12 col-md-6">
                                            <div class="form-group">
                                                <label for="language_image" class="required"><?= labels('language_image', 'Language Image') ?></label> <small>(<?= labels('promocode_image_recommended_size', 'We recommend 50 x 50 pixels') ?>)</small>
                                                <input type="file" name="language_image" id="language_image" accept="image/*" class="filepond">
                                            </div>
                                        </div>
                                        <div class="custom-switch col-sm-12 col-md-6">
                                            <div class="form-group">
                                                <label for=""><?= labels('is_rtl', 'Is RTL ') ?></label>
                                                <input id="is_rtl" class="custom-control-input " type="checkbox" name="is_rtl" value="true">
                                                <label for="is_rtl" class="custom-control-label mt-3">
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="" class="required"><?= labels('language_files', 'Language Files') ?></label>
                                                <div class="row">
                                                    <div class="col-12 col-sm-6 col-md-3 text-center mb-3">
                                                        <small class="text-muted d-block mb-2"><?= labels('panel', 'Panel') ?></small>
                                                        <input type="file" name="language_json_panel" id="language_json_panel" accept="application/json" class="filepond">
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-3 text-center mb-3">
                                                        <small class="text-muted d-block mb-2"><?= labels('web', 'Web') ?></small>
                                                        <input type="file" name="language_json_web" id="language_json_web" accept="application/json" class="filepond">
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-3 text-center mb-3">
                                                        <small class="text-muted d-block mb-2"><?= labels('customer_app', 'Customer App') ?></small>
                                                        <input type="file" name="language_json_customer_app" id="language_json_customer_app" accept="application/json" class="filepond">
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-3 text-center mb-3">
                                                        <small class="text-muted d-block mb-2"><?= labels('provider_app', 'Provider App') ?></small>
                                                        <input type="file" name="language_json_provider_app" id="language_json_provider_app" accept="application/json" class="filepond">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 d-flex justify-content-end">
                                            <button type="submit" class="btn btn-lg bg-new-primary submit_btn"><?= labels('submit', 'Submit') ?></button>
                                            <?= form_close() ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Card - Bottom Position -->
            <div class="row">
                <?php if ($permissions['read']['settings'] == 1) : ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                                <div class="toggleButttonPostition"><?= labels('language_settings', 'Language Settings') ?></div>
                            </div>
                            <div class="card-body">
                                <div class="col-md-12">
                                    <table class="table " id="language_list" data-pagination="true" data-pagination-successively-size="2" data-detail-formatter="user_formater" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/language/list") ?>" data-side-pagination="server" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-sort-name="id" data-sort-order="DESC" data-query-params="language_query">
                                        <thead>
                                            <tr>
                                                <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                                <th data-field="language" class="text-center"><?= labels('name', 'Name') ?></th>
                                                <th data-field="code" class="text-center"><?= labels('language_code', 'Code') ?></th>
                                                <th data-field="image" class="text-center" data-formatter="languageImageFormatter"><?= labels('image', 'Image') ?></th>
                                                <th data-field="is_rtl" class="text-center"><?= labels('is_rtl', 'Is RTL') ?></th>
                                                <th data-field="default" class="text-center" data-sortable="true"><?= labels('default', 'Default') ?></th>
                                                <th data-field="operations" class="text-center" data-events="language_events"><?= labels('operations', 'Operations') ?></th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<!-- update modal -->
<div class="modal fade" id="update_modal" tabindex="-1" aria-labelledby="update_modal_thing" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"><?= labels('update_language', 'Update Language') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?= form_open('admin/language/update', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'edit_language', 'enctype' => "multipart/form-data"]); ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_name"><?= labels('name', 'Name') ?></label>
                            <input id="edit_name" required class="form-control" type="text" name="edit_name" placeholder="Enter the name of the language here">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_code"><?= labels('language_code', 'Code') ?></label>
                            <input id="edit_code" class="form-control" type="text" name="edit_code" placeholder="Enter the name of the code here">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="update_language_image"><?= labels('language_image', 'Language Image') ?></label>
                            <input type="file" name="update_language_image" id="update_language_image" accept="image/*" class="filepond">
                            <div id="current_image_preview" class="mt-2" style="display: none;">
                                <img id="current_image" src="" alt="Current Image" style="max-width: 100px; max-height: 100px; border-radius: 5px;">
                                <small class="d-block text-muted"><?= labels('current_image', 'Current Image') ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="custom-switch p-0 m-0">
                            <div class="form-group">
                                <label for=""><?= labels('is_rtl', 'Is RTL ') ?></label>
                                <input id="is_rtl_edit" class="custom-control-input " type="checkbox" name="is_rtl" value="true">
                                <label for="is_rtl_edit" class="custom-control-label mt-3">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="id" id="id">
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="form-group">
                            <label for=""><?= labels('language_files', 'Language Files') ?></label>
                            <div class="row">
                                <div class="col-sm-12 col-md-6 text-center mb-3">
                                    <small class="text-muted d-block"><?= labels('panel', 'Panel') ?></small>
                                    <input type="file" name="update_language_json_panel" id="update_language_json_panel" accept="application/json" class="filepond">
                                    <a class="btn btn-sm btn-outline-primary download-category-file" data-category="panel"><?= labels('download', 'Download') ?></a>
                                </div>
                                <div class="col-sm-12 col-md-6 text-center mb-3">
                                    <small class="text-muted d-block"><?= labels('web', 'Web') ?></small>
                                    <input type="file" name="update_language_json_web" id="update_language_json_web" accept="application/json" class="filepond">
                                    <a class="btn btn-sm btn-outline-primary download-category-file" data-category="web"><?= labels('download', 'Download') ?></a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 col-md-6 text-center mb-3">
                                    <small class="text-muted d-block"><?= labels('customer_app', 'Customer App') ?></small>
                                    <input type="file" name="update_language_json_customer_app" id="update_language_json_customer_app" accept="application/json" class="filepond">
                                    <a class="btn btn-sm btn-outline-primary download-category-file" data-category="customer_app"><?= labels('download', 'Download') ?></a>
                                </div>
                                <div class="col-sm-12 col-md-6 text-center mb-3">
                                    <small class="text-muted d-block"><?= labels('provider_app', 'Provider App') ?></small>
                                    <input type="file" name="update_language_json_provider_app" id="update_language_json_provider_app" accept="application/json" class="filepond">
                                    <a class="btn btn-sm btn-outline-primary download-category-file" data-category="provider_app"><?= labels('download', 'Download') ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary submit_btn"><?= labels('update_language', 'Update Language') ?></button>
                <?php form_close() ?>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
    // Language image formatter for bootstrap table (same format as Partners_model)
    function languageImageFormatter(value, row, index) {
        if (value && value !== 'null') {
            return '<div class="o-media o-media--middle">' +
                '<a href="' + value + '" data-lightbox="language-image-' + row.id + '">' +
                '<img class="o-media__img images_in_card" src="' + value + '" alt="Language Image" style="max-width: 50px; max-height: 50px; border-radius: 5px;">' +
                '</a>' +
                '</div>';
        } else {
            return '<span class="text-muted">No Image</span>';
        }
    }

    $(document).on('click', '.download-category-file', function() {
        var category = $(this).data('category');
        var languageCode = $('#edit_code').val();

        if (!languageCode) {
            iziToast.error({
                title: "",
                message: "Please select a language first",
                position: "topRight",
            });
            return;
        }

        var downloadUrl = baseUrl + '/download_old_file/' + languageCode + '/' + category;

        var tempLink = document.createElement('a');
        tempLink.href = downloadUrl;
        tempLink.download = languageCode + '_' + category + '.json';
        tempLink.style.display = 'none';
        document.body.appendChild(tempLink);

        tempLink.click();

        document.body.removeChild(tempLink);
    });

    $(document).on('click', '.download-sample-btn', function(e) {
        e.preventDefault();

        var category = $(this).data('category');
        var downloadUrl = $(this).attr('href');
        var $btn = $(this);
        var originalContent = $btn.html();

        $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        $btn.addClass('disabled');

        var tempLink = document.createElement('a');
        tempLink.href = downloadUrl;
        tempLink.download = 'sample_' + category + '.json';
        tempLink.style.display = 'none';
        document.body.appendChild(tempLink);

        setTimeout(function() {
            tempLink.click();

            document.body.removeChild(tempLink);

            setTimeout(function() {
                $btn.html(originalContent);
                $btn.removeClass('disabled');
            }, 500);
        }, 800);
    });

    $(document).ready(function() {
        $('.download-sample-btn').tooltip({
            placement: 'top',
            trigger: 'hover'
        });
    });
</script>
<script type="text/javascript">
    $(document).on('click', '.store_default_language', function() {
        var id = $(this).data("id");
        var $button = $(this);
        var originalText = $button.text();

        // Show loading state
        $button.prop('disabled', true).text('Setting...');

        $.ajax({
            url: baseUrl + "/admin/language/store_default_language",
            type: "POST",
            dataType: "json",
            data: {
                id: id
            },
            success: function(result) {
                if (result && result.error === false) {
                    iziToast.success({
                        title: "",
                        message: result.message,
                        position: "topRight",
                    });

                    // Refresh the table and page
                    $("#language_list").bootstrapTable("refresh");
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    // Show error message
                    var errorMessage = result && result.message ? result.message : "Failed to set default language";
                    iziToast.error({
                        title: "",
                        message: errorMessage,
                        position: "topRight",
                    });

                    // Reset button state
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                iziToast.error({
                    title: "",
                    message: "Failed to set default language. Please try again.",
                    position: "topRight",
                });

                // Reset button state
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
</script>

<script>
    // Function to refresh language dropdown
    function refreshLanguageDropdown() {
        $.ajax({
            url: baseUrl + "/admin/language/get_dropdown_data",
            type: "GET",
            dataType: "json",
            success: function(response) {
                if (response.error === false) {
                    updateLanguageDropdown(response.languages, response.default_language);
                }
            },
            error: function() {
                console.log('Failed to refresh language dropdown');
            }
        });
    }

    // Function to update the language dropdown HTML
    function updateLanguageDropdown(languages, defaultLanguage) {
        var session = '<?= session()->get('lang') ?>';
        var currentLanguage = session || (defaultLanguage ? defaultLanguage.code : 'en');

        if (languages.length > 1) {
            // Multiple languages - show dropdown
            var dropdownHtml = '<li class="dropdown navbar_dropdown mr-2 mt-2" id="language-dropdown-container">' +
                '<a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">' +
                '<div class="d-inline-block" id="current-language-display">' + currentLanguage.toUpperCase() + '</div>' +
                '</a>' +
                '<div class="dropdown-menu dropdown-menu-right" id="language-dropdown-menu">';

            languages.forEach(function(language) {
                var isActive = (language.code === currentLanguage) ? 'text-primary' : '';
                var isDefault = (language.id == defaultLanguage.id) ? 'selected' : '';
                dropdownHtml += '<span onclick="set_locale(\'' + language.code + '\')" class="dropdown-item has-icon ' + isActive + '" ' + isDefault + '>' +
                    language.code.toUpperCase() + ' - ' + language.language.charAt(0).toUpperCase() + language.language.slice(1) +
                    '</span>';
            });

            dropdownHtml += '</div></li>';

            // Replace single language display with dropdown
            $('#single-language-container').replaceWith(dropdownHtml);
        } else {
            // Single language - show badge
            var singleLanguageHtml = '<li class="nav-item my-auto ml-2 mr-2" id="single-language-container">' +
                '<span class="badge badge-primary mt-2" style="border-radius: 8px!important;">' +
                '<p class="p-0 m-0">' + languages[0].code.toUpperCase() + '</p>' +
                '</span></li>';

            // Replace dropdown with single language display
            $('#language-dropdown-container').replaceWith(singleLanguageHtml);
        }
    }

    // Image preview functionality is now handled in window_event.js
    // This ensures the correct image is shown for each language in the edit modal
    // The fix addresses the issue where all languages showed the same image (first language's image)
</script>