<!-- Main Content -->
<?php

$db = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$permissions = get_permission($user1[0]['id']);

// Load languages for multilanguage SEO support
$languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
$sorted_languages = function_exists('sort_languages_with_default_first') ? sort_languages_with_default_first($languages) : $languages;

// Get default language for SEO fields
$default_language_seo = '';
foreach ($sorted_languages as $lang) {
    if ($lang['is_default'] == 1) {
        $default_language_seo = $lang['code'];
        break;
    }
}
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('seo_settings', "SEO Settings") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>

                <div class="breadcrumb-item"><?= labels('seo_settings', "SEO Settings") ?></a></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class=" card">
                    <?= helper('form'); ?>
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col">
                            <div class="toggleButttonPostition"><?= labels('seo_settings', "SEO Settings") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?= form_open('/admin/settings/add-seo-settings', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add', 'enctype' => "multipart/form-data"]); ?>

                        <div class="form-group">
                            <label for="page" class="required"><?= labels('page', "Page") ?></label>
                            <select name="page" id="page" class="form-control" required>
                                <option value=""><?= labels('select_page', "Select Page") ?></option>
                                <option value="home"><?= labels('home', "Home") ?></option>
                                <option value="become-provider"><?= labels('become_provider', "Become Provider") ?></option>
                                <option value="landing-page"><?= labels('landing_page', "Landing Page") ?></option>
                                <option value="about-us"><?= labels('about_us', "About Us") ?></option>
                                <option value="contact-us"><?= labels('contact_us', "Contact Us") ?></option>
                                <option value="providers-page"><?= labels('providers_page', "Providers Page") ?></option>
                                <option value="services-page"><?= labels('services_page', "Services Page") ?></option>
                                <option value="terms-and-conditions"><?= labels('terms_and_conditions', "Terms and Conditions") ?></option>
                                <option value="privacy-policy"><?= labels('privacy_policy', "Privacy Policy") ?></option>
                                <option value="faqs"><?= labels('faqs', "FAQs") ?></option>
                                <option value="blogs"><?= labels('blogs', "Blogs") ?></option>
                                <option value="site-map"><?= labels('site_map', "Site Map") ?></option>
                            </select>
                        </div>
                        <!-- Language tabs for SEO fields -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($sorted_languages as $index => $language) {
                                    ?>
                                        <div class="language-seo-option-general position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-seo-general-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span class="language-seo-text-general px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?><?= $language['is_default'] ? ' (Default)' : '' ?>
                                            </span>
                                            <div class="language-seo-underline-general"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <!-- SEO fields for each language -->
                        <?php foreach ($sorted_languages as $index => $language) { ?>
                            <div id="translationDivSeoGeneral-<?= $language['code'] ?>" <?= $language['code'] == $default_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="meta_title<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('meta_title', "Meta Title") . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="meta_title<?= $language['code'] ?>" class="form-control seo-title-field" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255" <?= $language['is_default'] ? 'required' : '' ?>>
                                            <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="meta_keywords<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('meta_keywords', 'Meta Keywords') . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100 seo-keywords-tagify" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>">
                                        </div>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="meta_description<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('meta_description', 'Meta Description') . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <textarea id="meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control seo-description-field" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500" <?= $language['is_default'] ? 'required' : '' ?>></textarea>
                                            <small class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="schema_markup<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('schema_markup', 'Schema Markup') . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                            <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                                data-toggle="popover"
                                                class="fa fa-question-circle"
                                                data-original-title=""
                                                title=""></i>
                                            <textarea id="schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control seo-schema-field" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>" <?= $language['is_default'] ? 'required' : '' ?>></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="form-group">
                            <label for="image" class="required"><?= labels('image', 'Image') ?> </label>
                            <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>" class="fa fa-question-circle" data-original-title="" title=""></i> <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                            <input type="file" class="filepond" name="image" id="image" accept="image/*" required>
                            <small class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                        </div>
                        <div class=" d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary submit_btn"><?= labels('submit', "Submit") ?></button>
                        </div>

                        <?= form_close(); ?>
                    </div>
                </div>
            </div>
            <?php if ($permissions['read']['seo_settings'] == 1) : ?>

                <div class="col-md-8">
                    <div class=" card">
                        <div class="row">
                            <div class="col-lg">
                                <div class="row border_bottom_for_cards m-0">
                                    <div class="col">
                                        <div class="toggleButttonPostition"><?= labels('seo_settings', "SEO Settings") ?></div>
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
                                                    </div>
                                                </div>
                                            </div>
                                            <table class="table" data-pagination-successively-size="2" data-query-params="system_tax_query_params" id="user_list" data-detail-formatter="user_formater" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/settings/seo-settings-list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="desc">
                                                <thead>
                                                    <tr>
                                                        <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                                        <th data-field="image" class="text-center" data-sortable="true"><?= labels('image', 'Image') ?></th>
                                                        <th data-field="page" class="text-center" data-sortable="true"><?= labels('page', 'Page') ?></th>
                                                        <th data-field="title" class="text-center" data-sortable="true"><?= labels('title', 'Title') ?></th>
                                                        <th data-field="description" class="text-center" data-sortable="true"><?= labels('description', 'Description') ?></th>
                                                        <th data-field="keywords" class="text-center" data-sortable="true"><?= labels('keywords', 'Keywords') ?></th>
                                                        <th data-field="operations" class="text-center" data-events="action_events"><?= labels('operations', 'Operations') ?></th>
                                                    </tr>
                                                </thead>
                                            </table>
                                        </div>
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
                    <?= form_open('admin/settings/update-seo-settings', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'edit_seo_settings', 'enctype' => "multipart/form-data"]); ?>
                    <input type="hidden" name="id" id="id">
                    <div class="form-group">
                        <label for="page" class="required"><?= labels('page', "Page") ?></label>
                        <select name="page" id="edit_page" class="form-control" disabled>
                            <option value=""><?= labels('select_page', "Select Page") ?></option>
                            <option value="home"><?= labels('home', "Home") ?></option>
                            <option value="become-provider"><?= labels('become_provider', "Become Provider") ?></option>
                            <option value="landing-page"><?= labels('landing_page', "Landing Page") ?></option>
                            <option value="about-us"><?= labels('about_us', "About Us") ?></option>
                            <option value="contact-us"><?= labels('contact_us', "Contact Us") ?></option>
                            <option value="providers-page"><?= labels('providers_page', "Providers Page") ?></option>
                            <option value="services-page"><?= labels('services_page', "Services Page") ?></option>
                            <option value="terms-and-conditions"><?= labels('terms_and_conditions', "Terms and Conditions") ?></option>
                            <option value="privacy-policy"><?= labels('privacy_policy', "Privacy Policy") ?></option>
                            <option value="faqs"><?= labels('faqs', "FAQs") ?></option>
                            <option value="blogs"><?= labels('blogs', "Blogs") ?></option>
                            <option value="site-map"><?= labels('site_map', "Site Map") ?></option>
                        </select>
                        <input type="hidden" name="page" id="edit_page_hidden" value="">
                    </div>
                    <!-- Language tabs for SEO fields in edit modal -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                foreach ($sorted_languages as $index => $language) {
                                ?>
                                    <div class="language-seo-option-edit position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-seo-edit-<?= $language['code'] ?>"
                                        data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span class="language-seo-text-edit px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?><?= $language['is_default'] ? ' (Default)' : '' ?>
                                        </span>
                                        <div class="language-seo-underline-edit"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- SEO fields for each language in edit modal -->
                    <?php foreach ($sorted_languages as $index => $language) { ?>
                        <div id="translationDivSeoEdit-<?= $language['code'] ?>" <?= $language['code'] == $default_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="edit_meta_title<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('meta_title', "Meta Title") . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                        <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <input id="edit_meta_title<?= $language['code'] ?>" class="form-control seo-title-field-edit" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255" <?= $language['is_default'] ? 'required' : '' ?>>
                                        <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="edit_meta_keywords<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('meta_keywords', 'Meta Keywords') . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                        <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <input id="edit_meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="edit_meta_description<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('meta_description', 'Meta Description') . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                        <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <textarea id="edit_meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control seo-description-field-edit" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500" <?= $language['is_default'] ? 'required' : '' ?>></textarea>
                                        <small class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="edit_schema_markup<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('schema_markup', 'Schema Markup') . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                        <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                            data-toggle="popover"
                                            class="fa fa-question-circle"
                                            data-original-title=""
                                            title=""></i>
                                        <textarea id="edit_schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control seo-schema-field-edit" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>" <?= $language['is_default'] ? 'required' : '' ?>></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group">
                        <label for="image"><?= labels('image', 'Image') ?></label> <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                        <input type="file" class="filepond" name="image" id="edit_image" accept="image/*">
                        <small class="form-text text-muted"><?= labels('upload_image_formats_optional', 'Optional: Upload new image to replace current one. Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                        <!-- Current Image Preview -->
                        <div class="current-image-container mt-3" style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; background-color: #f8f9fa; display: none;">
                            <div class="d-flex align-items-center">
                                <img id="current_image_display" src="" alt="Current SEO Image" style="max-width: 100px; max-height: 100px; object-fit: cover; border-radius: 4px;">
                                <div class="ml-3">
                                    <strong><?= labels('current_image', 'Current Image') ?></strong><br>
                                    <small class="text-muted"><?= labels('current_seo_image', 'Upload a new image above to replace it') ?></small>
                                </div>
                            </div>
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

<!-- Include the reusable SEO Counters JavaScript -->
<script src="<?= base_url('public/backend/assets/js/seo-settings.js') ?>"></script>

<script>
    $(function() {
        let popoverTimer;
        let currentPopover = null;
        let isOverPopover = false;
        let isOverTrigger = false;

        $('[data-toggle="popover"]').popover({
            html: true,
            trigger: 'manual',
            container: 'body'
        }).on('mouseenter', function() {
            const $this = $(this);
            isOverTrigger = true;
            clearTimeout(popoverTimer);

            // Hide other popovers
            if (currentPopover && currentPopover[0] !== $this[0]) {
                currentPopover.popover('hide');
            }

            currentPopover = $this;
            $this.popover('show');

        }).on('mouseleave', function() {
            isOverTrigger = false;
            startHideTimer();
        });

        // Handle popover content hover
        $(document).on('mouseenter', '.popover', function() {
            isOverPopover = true;
            clearTimeout(popoverTimer);
        }).on('mouseleave', '.popover', function() {
            isOverPopover = false;
            startHideTimer();
        });

        function startHideTimer() {
            clearTimeout(popoverTimer);
            popoverTimer = setTimeout(function() {
                if (!isOverTrigger && !isOverPopover && currentPopover) {
                    currentPopover.popover('hide');
                    currentPopover = null;
                }
            }, 150);
        }
    });
    /** LANGUAGE TAB SWITCHING FOR SEO FIELDS */

    // SEO language tab switching for add form
    $(document).ready(function() {
        // select default language for SEO
        let default_language_seo_general = '<?= $default_language_seo ?>';

        $(document).on('click', '.language-seo-option-general', function() {
            const language = $(this).data('language');

            $('.language-seo-underline-general').css('width', '0%');
            $('#language-seo-general-' + language).find('.language-seo-underline-general').css('width', '100%');

            $('.language-seo-text-general').removeClass('text-primary fw-medium');
            $('.language-seo-text-general').addClass('text-muted');
            $('#language-seo-general-' + language).find('.language-seo-text-general').removeClass('text-muted');
            $('#language-seo-general-' + language).find('.language-seo-text-general').addClass('text-primary fw-medium');

            if (language != default_language_seo_general) {
                $('#translationDivSeoGeneral-' + language).show();
                $('#translationDivSeoGeneral-' + default_language_seo_general).hide();
            }

            default_language_seo_general = language;
        });
    });

    // SEO language tab switching for edit modal
    $(document).ready(function() {
        // select default language for SEO
        let default_language_seo_edit = '<?= $default_language_seo ?>';

        $(document).on('click', '.language-seo-option-edit', function() {
            const language = $(this).data('language');

            $('.language-seo-underline-edit').css('width', '0%');
            $('#language-seo-edit-' + language).find('.language-seo-underline-edit').css('width', '100%');

            $('.language-seo-text-edit').removeClass('text-primary fw-medium');
            $('.language-seo-text-edit').addClass('text-muted');
            $('#language-seo-edit-' + language).find('.language-seo-text-edit').removeClass('text-muted');
            $('#language-seo-edit-' + language).find('.language-seo-text-edit').addClass('text-primary fw-medium');

            if (language != default_language_seo_edit) {
                $('#translationDivSeoEdit-' + language).show();
                $('#translationDivSeoEdit-' + default_language_seo_edit).hide();
            }

            default_language_seo_edit = language;
        });
    });

    /** TAGIFY MANAGEMENT FOR KEYWORDS */

    // Initialize Tagify for SEO keywords fields (main form)
    $(document).ready(function() {
        // Initialize Tagify for main form keywords (using class selector)
        var seoKeywordsInputs = document.querySelectorAll('.seo-keywords-tagify');
        seoKeywordsInputs.forEach(function(input) {
            if (input != null && !input._tagify) {
                new Tagify(input);
            }
        });

        // Initialize Tagify for modal keywords (using ID selector pattern like categories)
        var editKeywordsInputs = document.querySelectorAll('input[id^="edit_meta_keywords"]');
        if (editKeywordsInputs != null && editKeywordsInputs.length > 0) {
            editKeywordsInputs.forEach(function(input) {
                if (input && !input._tagify) {
                    new Tagify(input);
                }
            });
        }
    });

    // SEO Settings Page-specific functionality
    $(document).ready(function() {
        // Initialize counters from external file
        window.SEOCounters.init();

        // Page dropdown management - specific to this page
        const BASE_URL = "<?= base_url() ?>";
        let existingPages = new Set();

        /** DROPDOWN MANAGEMENT */

        // Load existing pages from server
        async function loadExistingPages() {
            try {
                const response = await fetch(`${BASE_URL}/admin/settings/get-existing-seo-pages`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (!data.error && Array.isArray(data.existing_pages)) {
                    existingPages = new Set(data.existing_pages);
                    updateDropdownOptions();
                    // Check if we should hide the form after loading existing pages
                    checkAndHideFormIfNeeded();
                }
            } catch (error) {
                console.error('Error loading existing pages:', error);
            }
        }

        // Get all available page options from the dropdown
        function getAllAvailablePages() {
            const pageSelect = document.getElementById('page');
            if (!pageSelect) return [];

            const availablePages = [];
            const options = pageSelect.querySelectorAll('option');

            options.forEach(option => {
                // Skip the default empty option
                if (option.value && option.value.trim() !== '') {
                    availablePages.push(option.value);
                }
            });

            return availablePages;
        }

        // Check if all pages have entries and hide form if needed
        function checkAndHideFormIfNeeded() {
            const availablePages = getAllAvailablePages();
            const allPagesHaveEntries = availablePages.length > 0 &&
                availablePages.every(page => existingPages.has(page));

            const formColumn = document.querySelector('.col-md-4');
            const tableColumn = document.querySelector('.col-md-8, .col-md-12'); // pick whichever exists

            if (!formColumn || !tableColumn) return; // fail-safe

            if (allPagesHaveEntries) {
                // Hide form column
                formColumn.classList.add('d-none');

                // Reset + set table to full width
                tableColumn.classList.remove('col-md-8', 'col-md-12');
                tableColumn.classList.add('col-md-12');
            } else {
                // Show form column
                formColumn.classList.remove('d-none');

                // Reset + set table back to 8-cols
                tableColumn.classList.remove('col-md-8', 'col-md-12');
                tableColumn.classList.add('col-md-8');
            }
        }


        // Update dropdown options based on existing pages
        function updateDropdownOptions() {
            const pageSelect = document.getElementById('page');
            if (!pageSelect) return;

            const options = pageSelect.querySelectorAll('option');
            options.forEach(option => {
                if (option.value && existingPages.has(option.value)) {
                    option.style.display = 'none';
                } else {
                    option.style.display = '';
                }
            });

            // Reset to default option (empty value)
            pageSelect.value = '';
            pageSelect.selectedIndex = 0;
        }

        /** CRUD OPERATIONS */

        // Edit SEO settings - using standard project pattern
        window.edit_seo_setting = function(id) {
            $.get(BASE_URL + "/admin/settings/get-seo-settings?id=" + id, function(data) {
                if (!data.error) {
                    // console.log(data.data);
                    // Populate the edit modal with data
                    $('#id').val(data.data.id);
                    $('#edit_page').val(data.data.page);
                    $('#edit_page_hidden').val(data.data.page);

                    // Populate multilanguage fields
                    <?php foreach ($sorted_languages as $language) { ?>
                        const langCode<?= $language['code'] ?> = '<?= $language['code'] ?>';
                        const translatedData<?= $language['code'] ?> = data.data['translated_' + langCode<?= $language['code'] ?>];

                        // Populate title
                        $('#edit_meta_title' + langCode<?= $language['code'] ?>).val(
                            translatedData<?= $language['code'] ?>?.title || (langCode<?= $language['code'] ?> === '<?= $default_language_seo ?>' ? (data.data.title || '') : '')
                        );

                        // Populate description
                        $('#edit_meta_description' + langCode<?= $language['code'] ?>).val(
                            translatedData<?= $language['code'] ?>?.description || (langCode<?= $language['code'] ?> === '<?= $default_language_seo ?>' ? (data.data.description || '') : '')
                        );

                        // Populate schema markup
                        $('#edit_schema_markup' + langCode<?= $language['code'] ?>).val(
                            translatedData<?= $language['code'] ?>?.schema_markup || (langCode<?= $language['code'] ?> === '<?= $default_language_seo ?>' ? (data.data.schema_markup || '') : '')
                        );

                        // Handle keywords with Tagify
                        const editKeywordsInput<?= $language['code'] ?> = document.getElementById('edit_meta_keywords' + langCode<?= $language['code'] ?>);
                        const keywordsValue<?= $language['code'] ?> = translatedData<?= $language['code'] ?>?.keywords || (langCode<?= $language['code'] ?> === '<?= $default_language_seo ?>' ? (data.data.keywords || '') : '');

                        // Initialize Tagify if not already initialized
                        if (editKeywordsInput<?= $language['code'] ?> && !editKeywordsInput<?= $language['code'] ?>._tagify) {
                            editKeywordsInput<?= $language['code'] ?>._tagify = new Tagify(editKeywordsInput<?= $language['code'] ?>);
                        }

                        // Populate keywords
                        if (editKeywordsInput<?= $language['code'] ?> && editKeywordsInput<?= $language['code'] ?>._tagify) {
                            editKeywordsInput<?= $language['code'] ?>._tagify.removeAllTags();
                            if (keywordsValue<?= $language['code'] ?> && keywordsValue<?= $language['code'] ?>.trim() !== '') {
                                const keywords<?= $language['code'] ?> = keywordsValue<?= $language['code'] ?>.split(',').map(keyword => keyword.trim()).filter(keyword => keyword !== '');
                                editKeywordsInput<?= $language['code'] ?>._tagify.addTags(keywords<?= $language['code'] ?>);
                            }
                        }
                    <?php } ?>

                    // Update counters for edit form (multilanguage fields)
                    <?php foreach ($sorted_languages as $language) { ?>
                        window.SEOCounters.updateCharacterCounter('edit_meta_title<?= $language['code'] ?>', window.SEOCounters.MAX_LENGTHS.title);
                        window.SEOCounters.updateCharacterCounter('edit_meta_description<?= $language['code'] ?>', window.SEOCounters.MAX_LENGTHS.description);
                        setTimeout(() => {
                            window.SEOCounters.updateKeywordCounter('edit_meta_keywords<?= $language['code'] ?>');
                        }, 100);
                    <?php } ?>

                    // Handle current image preview
                    const currentImageDisplay = $('#current_image_display');
                    const currentImageContainer = $('.current-image-container');

                    if (data.data.image_url && data.data.image_url.trim() !== '' && currentImageDisplay.length) {
                        currentImageDisplay.attr('src', data.data.image_url);
                        currentImageContainer.show();
                    } else {
                        currentImageContainer.hide();
                    }

                    // Clear FilePond files in edit form
                    const editImageInput = document.getElementById('edit_image');
                    if (editImageInput && editImageInput._filepond) {
                        editImageInput._filepond.removeFiles();
                    }

                    // Show the edit modal
                    $('#update_modal').modal('show');
                } else {
                    showToastMessage(data.message, 'error');
                }
            }).fail(function() {
                showToastMessage('Failed to load SEO settings data. Please try again.', 'error');
            });
        };

        // Delete SEO settings - using standard project pattern
        window.delete_seo_setting = function(id) {
            Swal.fire({
                title: "<?= labels('are_your_sure', 'Are you sure?') ?>",
                text: "<?= labels('you_wont_be_able_to_revert_this', 'You won\'t be able to revert this!') ?>",
                icon: "error",
                showCancelButton: true,
                confirmButtonText: '<?= labels('yes_proceed', 'Yes, proceed!') ?>',
                cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post(
                        BASE_URL + "/admin/settings/delete-seo-settings", {
                            [csrfName]: csrfHash,
                            id: id,
                        },
                        function(data) {
                            csrfName = data.csrfName;
                            csrfHash = data.csrfHash;

                            if (data.error == false) {
                                showToastMessage(data.message, "success");

                                // Refresh the table and reload existing pages
                                setTimeout(() => {
                                    $("#user_list").bootstrapTable("refresh");
                                    loadExistingPages();
                                }, 1000);
                            } else {
                                showToastMessage(data.message, "error");
                            }
                        }
                    );
                }
            });
        };

        /** BOOTSTRAP TABLE ACTION EVENTS */
        window.action_events = {
            'click .edit_seo_setting': function(e, value, row, index) {
                e.preventDefault();
                e.stopPropagation();
                edit_seo_setting(row.id);
            },
            'click .delete_seo_setting': function(e, value, row, index) {
                e.preventDefault();
                e.stopPropagation();
                delete_seo_setting(row.id);
            }
        };

        /** INITIALIZATION */

        // Initialize page dropdown functionality
        loadExistingPages();

        // Function to reset SEO counters after form submission
        function resetSeoCounters() {
            // Use the external reset function for counters
            if (window.SEOCounters && window.SEOCounters.resetCounters) {
                window.SEOCounters.resetCounters();
            }

            // Clear Tagify tags in main form (all languages)
            var seoKeywordsInputs = document.querySelectorAll('.seo-keywords-tagify');
            seoKeywordsInputs.forEach(function(input) {
                if (input && input._tagify) {
                    input._tagify.removeAllTags();
                }
            });
        }

        // Setup form submission listeners to refresh dropdown
        // Listen for successful form submissions using the standard project pattern
        $(document).ajaxSuccess(function(event, xhr, settings) {
            // Check if this is a successful SEO settings form submission
            if (settings.url &&
                (settings.url.includes('add-seo-settings') || settings.url.includes('update-seo-settings')) &&
                settings.type === 'POST' &&
                xhr.responseJSON &&
                xhr.responseJSON.error === false) {

                // Reset SEO counters after successful form submission (only for add, not update)
                if (settings.url.includes('add-seo-settings')) {
                    setTimeout(() => {
                        resetSeoCounters();
                    }, 100);
                }

                // Reload existing pages to update dropdown and form visibility
                setTimeout(() => {
                    loadExistingPages();
                }, 500);
            }
        });

        // Listen for form reset events triggered by the global form handler
        $('#add').on('reset', function() {
            // Reset counters when form is reset
            setTimeout(() => {
                resetSeoCounters();
            }, 50);
        });

        // Refresh dropdown after modal closes (for update operations)
        $('#update_modal').on('hidden.bs.modal', function() {
            setTimeout(() => {
                loadExistingPages();
            }, 500);
        });

        // Reset edit form counters when modal is closed
        $('#update_modal').on('hidden.bs.modal', function() {
            // Clear Tagify tags in edit form (all languages)
            var editKeywordsInputs = document.querySelectorAll('#update_modal input[id^="edit_meta_keywords"]');
            editKeywordsInputs.forEach(function(input) {
                if (input && input._tagify) {
                    input._tagify.removeAllTags();
                }
            });

            // Reset edit form counters (all languages)
            setTimeout(() => {
                <?php foreach ($sorted_languages as $language) { ?>
                    window.SEOCounters.updateCharacterCounter('edit_meta_title<?= $language['code'] ?>', window.SEOCounters.MAX_LENGTHS.title);
                    window.SEOCounters.updateCharacterCounter('edit_meta_description<?= $language['code'] ?>', window.SEOCounters.MAX_LENGTHS.description);
                    window.SEOCounters.updateKeywordCounter('edit_meta_keywords<?= $language['code'] ?>');
                <?php } ?>
            }, 100);
        });

        // Update character counters when edit modal is shown
        $('#update_modal').on('shown.bs.modal', function() {
            // Reinitialize Tagify for all edit keywords fields if not already initialized
            var editKeywordsInputs = document.querySelectorAll('#update_modal input[id^="edit_meta_keywords"]');
            editKeywordsInputs.forEach(function(input) {
                if (input != null && !input._tagify) {
                    new Tagify(input);
                }
            });

            // Update counters for current content (multilanguage fields)
            <?php foreach ($sorted_languages as $language) { ?>
                window.SEOCounters.updateCharacterCounter('edit_meta_title<?= $language['code'] ?>', window.SEOCounters.MAX_LENGTHS.title);
                window.SEOCounters.updateCharacterCounter('edit_meta_description<?= $language['code'] ?>', window.SEOCounters.MAX_LENGTHS.description);
                setTimeout(() => {
                    window.SEOCounters.updateKeywordCounter('edit_meta_keywords<?= $language['code'] ?>');
                }, 100);
            <?php } ?>
        });
    });
</script>