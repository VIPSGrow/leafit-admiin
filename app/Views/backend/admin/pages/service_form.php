<?php
$check_payment_gateway = get_settings('payment_gateways_settings', true);
$cod_setting = $check_payment_gateway['cod_setting'];

// ---------------------------------------------------------------------------
// Mode setup
// ---------------------------------------------------------------------------
// Single view used by add_service_view(), edit_service() and duplicate() in
// app/Controllers/Admin/Services.php. The controller sets $mode to one of
// 'add' | 'edit' | 'clone'. Pre-fill blocks render only when $has_record is
// true. The SEO image removal AJAX runs only in edit mode (clone has no SEO
// record yet). Sidebar starts unlocked for edit + clone via window.stepperMode.
$mode = $mode ?? 'add';
$is_edit = $mode === 'edit';
$is_clone = $mode === 'clone';
$is_add = $mode === 'add';
$has_record = $is_edit || $is_clone;

$service = $service ?? [];
$service_seo_settings = $service_seo_settings ?? [];

$action_url = $is_edit ? '/admin/services/update_service' : '/admin/services/insert_service';
$form_id = $is_edit ? 'update_service' : 'add_service';
$form_class = $is_edit ? 'update-form' : 'form-submit-event';

$submit_label = $is_edit
    ? labels('edit_service', 'Edit Service')
    : ($is_clone ? labels('clone_service', 'Clone Service') : labels('add_services', 'Add Service'));

$page_heading = $is_edit
    ? labels('edit_service', 'Edit Service')
    : ($is_clone ? labels('clone_service', 'Clone Service') : labels('services', 'Services'));

$breadcrumb_label = $is_edit
    ? labels('edit_service', 'Edit Service')
    : ($is_clone ? labels('clone_service', 'Clone Service') : labels('add_services', 'Add Service'));

$val = function ($key, $default = '') use ($service) {
    return isset($service[$key]) ? $service[$key] : $default;
};
$tval = function ($lang, $key, $default = '') use ($service) {
    if (isset($service['translated_' . $lang][$key]) && $service['translated_' . $lang][$key] !== '') {
        return $service['translated_' . $lang][$key];
    }
    if (isset($service[$key])) {
        return $service[$key];
    }
    return $default;
};
$tseo = function ($lang, $key, $default = '') use ($service) {
    return $service['translated_seo_' . $lang][$key] ?? $default;
};

$sorted_languages = sort_languages_with_default_first($languages);
$default_language = '';
$default_language_faqs = '';
$default_language_seo = '';
foreach ($sorted_languages as $l) {
    if ($l['is_default'] == 1) {
        $default_language = $l['code'];
        $default_language_faqs = $l['code'];
        $default_language_seo = $l['code'];
        break;
    }
}
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= $page_heading ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/services') ?>"><i
                            class="fas fa-tools text-warning"></i> <?= labels('service', 'Service') ?></a></div>
                <div class="breadcrumb-item"><?= $breadcrumb_label ?></div>
            </div>
        </div>

        <?= form_open($action_url, ['method' => "post", 'class' => $form_class, 'id' => $form_id, 'enctype' => "multipart/form-data", 'novalidate' => 'novalidate']); ?>

        <?php if ($has_record): ?>
            <input type="hidden" name="service_id" id="service_id" value="<?= esc($val('id')) ?>">
        <?php endif; ?>

        <script>
            // Stepper mode: 'edit' unlocks all sidebar items immediately. Both edit
            // and clone unlock — clone reuses existing data so navigation should
            // not be locked behind sequential validation.
            window.stepperMode = <?= json_encode($has_record ? 'edit' : 'add') ?>;
            window.SERVICE_FORM_MODE = <?= json_encode($mode) ?>;
            window.SERVICE_FORM_HAS_RECORD = <?= json_encode($has_record) ?>;
            window.SERVICE_FORM_IS_EDIT = <?= json_encode($is_edit) ?>;

            window.stepperLabels = {
                basic_info: "<?= labels('basic_info', 'Basic Info') ?>",
                service_details: "<?= labels('service_details', 'Service Details') ?>",
                media_and_files: "<?= labels('media_and_files', 'Media & Files') ?>",
                price_details: "<?= labels('price_details', 'Price Details') ?>",
                faqs: "<?= labels('faqs', 'FAQs') ?>",
                seo_settings: "<?= labels('seo_settings', 'SEO Settings') ?>",
                service_option: "<?= labels('service_option', 'Service Options') ?>",
                review_step: "<?= labels('review_step', 'Review') ?>",
                basic_info_subtitle: "<?= labels('service_basic_info_subtitle', 'Service title, tags, and description per language') ?>",
                service_details_subtitle: "<?= labels('service_details_subtitle', 'Provider, category and task configuration') ?>",
                media_and_files_subtitle: "<?= labels('service_media_subtitle', 'Service image, gallery and supporting documents') ?>",
                price_details_subtitle: "<?= labels('service_price_subtitle', 'Pricing and tax configuration') ?>",
                faqs_subtitle: "<?= labels('service_faqs_subtitle', 'Common questions and answers per language') ?>",
                seo_settings_subtitle: "<?= labels('service_seo_subtitle', 'Search engine optimization meta tags') ?>",
                service_options_subtitle: "<?= labels('service_options_subtitle', 'Booking rules and visibility toggles') ?>",
                review_subtitle: "<?= labels('service_review_subtitle', 'Verify the service details before submitting') ?>",
                title: "<?= labels('title_of_the_service', 'Title of the service') ?>",
                tags: "<?= labels('tags', 'Tags') ?>",
                short_description: "<?= labels('short_description', 'Short Description') ?>",
                description: "<?= labels('description', 'Description') ?>",
                select_provider: "<?= labels('select_provider', 'Select Provider') ?>",
                category: "<?= labels('category', 'Category') ?>",
                slug: "<?= labels('slug', 'Slug') ?>",
                duration_to_perform_task: "<?= labels('duration_to_perform_task', 'Duration to Perform Task') ?>",
                members_required_to_perform_task: "<?= labels('members_required_to_perform_task', 'Members Required to Perform Task') ?>",
                max_quantity_allowed_for_services: "<?= labels('max_quantity_allowed_for_services', 'Max Quantity allowed for services') ?>",
                image: "<?= labels('image', 'Image') ?>",
                other_images: "<?= labels('other_images', 'Other Images') ?>",
                files: "<?= labels('files', 'Files') ?>",
                price: "<?= labels('price', 'Price') ?>",
                discounted_price: "<?= labels('discounted_price', 'Discounted Price') ?>",
                currency: "<?= esc($currency ?? '') ?>",
                tax: "<?= labels('select_tax', 'Select Tax') ?>",
                tax_type: "<?= labels('type', 'Type') ?>",
                meta_title: "<?= labels('meta_title', 'Meta Title') ?>",
                meta_keywords: "<?= labels('meta_keywords', 'Meta Keywords') ?>",
                meta_description: "<?= labels('meta_description', 'Meta Description') ?>",
                schema_markup: "<?= labels('schema_markup', 'Schema Markup') ?>",
                is_cancelable: "<?= labels('is_cancelable_?', 'Is Cancelable') ?>",
                pay_later_allowed: "<?= labels('pay_later_allowed', 'Pay Later Allowed') ?>",
                at_store: "<?= labels('at_store', 'At Store') ?>",
                at_doorstep: "<?= labels('at_doorstep', 'At Doorstep') ?>",
                approve_service: "<?= labels('approve_service', 'Approve Service') ?>",
                cancelable_before: "<?= labels('cancelable_before', 'Cancelable before') ?>",
                minutes: "<?= labels('minutes', 'minutes') ?>",
                status: "<?= labels('status', 'Status') ?>",
                edit: "<?= labels('edit', 'Edit') ?>",
                enabled: "<?= labels('enabled', 'Enabled') ?>",
                disabled_label: "<?= labels('disabled_label', 'Disabled') ?>",
                yes: "<?= labels('yes', 'Yes') ?>",
                no: "<?= labels('no', 'No') ?>",
                defaultLanguageCode: "<?= $default_language ?>",
                validation_required: "<?= labels('validation_required', '{field} is required.') ?>",
                validation_invalid_value: "<?= labels('validation_invalid_value', 'Please enter a valid value for {field}.') ?>",
                validation_invalid_format: "<?= labels('validation_invalid_format', 'Please enter a valid format for {field}.') ?>",
                validation_min_value: "<?= labels('validation_min_value', '{field} must be at least {min}.') ?>",
                validation_max_value: "<?= labels('validation_max_value', '{field} must be at most {max}.') ?>",
                individual_provider_members_must_be_one: "<?= labels('individual_provider_members_must_be_one', 'An individual provider can have only 1 member') ?>"
            };
        </script>

        <div class="stepper-container">
            <!-- Mobile horizontal step indicator -->
            <div class="stepper-horizontal">
                <?php
                $stepIcons = [
                    1 => 'fa-info-circle',
                    2 => 'fa-tools',
                    3 => 'fa-images',
                    4 => 'fa-dollar-sign',
                    5 => 'fa-question-circle',
                    6 => 'fa-magnifying-glass',
                    7 => 'fa-sliders-h',
                    8 => 'fa-check-circle',
                ];
                $stepLabelsMap = [
                    1 => ['basic_info', 'Basic Info'],
                    2 => ['service_details', 'Service Details'],
                    3 => ['media_and_files', 'Media & Files'],
                    4 => ['price_details', 'Price Details'],
                    5 => ['faqs', 'FAQs'],
                    6 => ['seo_settings', 'SEO Settings'],
                    7 => ['service_option', 'Service Options'],
                    8 => ['review_step', 'Review'],
                ];
                foreach ($stepIcons as $num => $icon):
                    $activeClass = $num === 1 ? ' active' : ($has_record ? ' completed' : ' disabled');
                    ?>
                    <div class="step-h-item<?= $activeClass ?>" data-step="<?= $num ?>">
                        <div class="step-h-icon"><i
                                class="fas <?= $num === 1 ? $icon : ($has_record ? 'fa-check' : $icon) ?>"></i></div>
                        <span class="step-h-label"><?= labels($stepLabelsMap[$num][0], $stepLabelsMap[$num][1]) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="stepper-sidebar">
                <?php foreach ($stepIcons as $num => $icon):
                    $activeClass = $num === 1 ? 'active' : ($has_record ? 'completed' : 'disabled');
                    $iconClass = $num === 1 ? $icon : ($has_record ? 'fa-check' : $icon);
                    ?>
                    <div class="step-item <?= $activeClass ?>" data-step="<?= $num ?>">
                        <div class="step-icon"><i class="fas <?= $iconClass ?>"></i></div>
                        <span class="step-label"><?= labels($stepLabelsMap[$num][0], $stepLabelsMap[$num][1]) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="stepper-content">
                <?php $status_checked = $has_record ? ($val('status') == '1') : true; ?>
                <div class="step-content-header">
                    <div class="step-content-header-text">
                        <h2 id="stepper-step-title"><?= labels('basic_info', 'Basic Info') ?></h2>
                        <p id="stepper-step-subtitle">
                            <?= labels('service_basic_info_subtitle', 'Service title, tags, and description per language') ?>
                        </p>
                    </div>
                </div>
                <div class="stepper-progress-bar">
                    <div class="progress-fill" style="width: 12%"></div>
                </div>

                <!-- ============================================================
                     STEP 1: Basic Info (multilingual)
                ============================================================ -->
                <div class="step-panel active" data-step="1">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php foreach ($sorted_languages as $language): ?>
                                    <div class="language-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-<?= $language['code'] ?>" data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span
                                            class="language-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?>     <?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($sorted_languages as $language):
                        $code = $language['code'];
                        $is_default = $language['is_default'] == 1;
                        $lang_suffix = $is_default ? '' : ' (' . $code . ')';
                        ?>
                        <div id="translationDiv-<?= $code ?>" <?= $code == $default_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="service_title<?= $code ?>" <?= $is_default ? 'class="required"' : '' ?>><?= labels('title_of_the_service', 'Title of the service') . $lang_suffix ?></label>
                                        <input class="form-control" type="text" name="title[<?= $code ?>]"
                                            id="service_title<?= $code ?>"
                                            value="<?= esc($has_record ? $tval($code, 'title') : '') ?>" <?= $is_default ? 'required data-slug-source data-slug-target="#service_slug"' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tags<?= $code ?>" <?= $is_default ? 'class="required"' : '' ?>><?= labels('tags', 'Tags') . $lang_suffix ?></label>
                                        <i data-content=" <?= labels('data_content_for_tags', 'These tags will help find the services while users search for the services.') ?>"
                                            class="fa fa-question-circle" data-original-title="" title=""
                                            data-toggle="popover"></i>
                                        <input id="tags<?= $code ?>" style="border-radius: 0.25rem"
                                            class="w-100 translation-tags" type="text" name="tags[<?= $code ?>][]"
                                            placeholder="<?= labels('press_enter_to_add_tag', 'press enter to add tag') ?>"
                                            value="<?= esc($has_record ? $tval($code, 'tags') : '') ?>" <?= $is_default ? 'required' : '' ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description<?= $code ?>" <?= $is_default ? 'class="required"' : '' ?>><?= labels('short_description', 'Short Description') . $lang_suffix ?></label>
                                        <textarea id="description<?= $code ?>" rows="4" class="form-control"
                                            style="min-height:60px" name="description[<?= $code ?>]" <?= $is_default ? 'required' : '' ?>><?= esc($has_record ? $tval($code, 'description') : '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <label for="long_description<?= $code ?>" <?= $is_default ? 'class="required"' : '' ?>><?= labels('description', 'Description') . $lang_suffix ?></label>
                                    <textarea id="long_description<?= $code ?>" rows="10"
                                        class="form-control h-50 summernotes custome_reset"
                                        name="long_description[<?= $code ?>]" <?= $is_default ? 'required' : '' ?>><?= $has_record ? $tval($code, 'long_description') : '' ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ============================================================
                     STEP 2: Service Details
                ============================================================ -->
                <div class="step-panel" data-step="2">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="partner"
                                    class="required"><?= labels('select_provider', 'Select Provider') ?></label>
                                <select id="partner" class="form-control w-100 select2" name="partner" required>
                                    <option value=""><?= labels('select_provider', 'Select Provider') ?></option>
                                    <?php foreach ($partner_name as $pn):
                                        $selected = ($has_record && isset($service['user_id']) && $service['user_id'] == $pn['id']) ? 'selected' : '';
                                        $display = isset($pn['display_company_name']) ? $pn['display_company_name'] : $pn['company_name'];
                                        ?>
                                        <option value="<?= $pn['id'] ?>" <?= $selected ?>
                                            data-members="<?= $pn['number_of_members'] ?? '' ?>"
                                            data-type="<?= $pn['type'] ?? '' ?>"
                                            data-at_store="<?= $pn['at_store'] ?>"
                                            data-at_doorstep="<?= $pn['at_doorstep'] ?>"
                                            data-need_approval_for_the_service="<?= $pn['need_approval_for_the_service'] ?>">
                                            <?= $display . ' - ' . $pn['username'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category_item"
                                    class="required"><?= labels('choose_a_category_for_your_service', 'Choose a Category for your service') ?></label>
                                <?php if (empty($categories_name)): ?>
                                    <div id="no_category_notice" class="d-flex align-items-center p-2 border rounded">
                                        <span
                                            class="text-muted small mr-2"><?= labels('no_categories_available', 'No categories available.') ?></span>
                                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal"
                                            data-target="#add_category_modal_service">
                                            <i class="fas fa-plus"></i> <?= labels('add_category', 'Add Category') ?>
                                        </button>
                                    </div>
                                    <select id="category_item" class="form-control select2" name="categories" required
                                        style="display:none;">
                                        <option value=""><?= labels('select', 'Select') ?>
                                            <?= labels('category', 'Category') ?>
                                        </option>
                                    </select>
                                <?php else: ?>
                                    <select id="category_item" class="form-control select2" name="categories" required>
                                        <option value=""><?= labels('select', 'Select') ?>
                                            <?= labels('category', 'Category') ?>
                                        </option>
                                        <?php
                                        $selected_category_id = $has_record && isset($service['category_id']) ? $service['category_id'] : null;
                                        echo render_categories_options($categories_name, 0, 0, $selected_category_id);
                                        ?>
                                    </select>
                                    <small class="form-text">
                                        <a href="#" class="text-primary" data-toggle="modal"
                                            data-target="#add_category_modal_service">
                                            <i class="fas fa-plus"></i>
                                            <?= labels('add_new_category', 'Add new category') ?>
                                        </a>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="service_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                <input id="service_slug" class="form-control" type="text" name="service_slug"
                                    value="<?= esc($has_record ? $val('slug') : '') ?>"
                                    placeholder="<?= labels('enter_the_slug', 'Enter the slug') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="stepper-section-divider">
                        <p><?= labels('perform_task', 'Perform Task') ?></p>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="duration"
                                    class="required"><?= labels('duration_to_perform_task', 'Duration to Perform Task') ?></label>
                                <i data-content="<?= labels('data_content_for_duration_perform_task', 'The duration will be used to figure out how long the service will take and to determine available timeslots when the customer book their services.') ?>"
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text myDivClass" style="height: 42px;">
                                            <span class="mySpanClass"><?= labels('minutes', 'Minutes') ?></span>
                                        </div>
                                    </div>
                                    <input type="number" style="height: 42px;" class="form-control" name="duration"
                                        id="duration" min="1" oninput="this.value = Math.abs(this.value)"
                                        placeholder="<?= labels('duration_to_perform_task', 'Duration to Perform service') ?>"
                                        value="<?= esc($has_record ? $val('duration') : '') ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="members"
                                    class="required"><?= labels('members_required_to_perform_task', 'Members Required to Perform Task') ?></label>
                                <i data-content=" <?= labels('data_content_for_member_required', 'We\'re just collecting the number of team members who will be doing the service. This helps us show customers how many people will be working on their service.') ?> "
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <input id="members" class="form-control" type="number" name="members" min="1"
                                    oninput="this.value = Math.abs(this.value)"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('members_required_to_perform_task', 'Members Required to Perform Task') ?> <?= labels('here', ' Here ') ?>"
                                    value="<?= esc($has_record ? $val('number_of_members_required') : '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="max_qty"
                                    class="required"><?= labels('max_quantity_allowed_for_services', 'Max Quantity allowed for services') ?></label>
                                <i data-content="<?= labels('data_content_for_max_quality_allowed', 'Users can add up to a maximum of X quantity of a specific service when adding services to the cart.') ?>"
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <input id="max_qty" class="form-control" type="number" min="1"
                                    oninput="this.value = Math.abs(this.value)" name="max_qty"
                                    placeholder="<?= labels('max_quantity_allowed_for_services', 'Max Quantity allowed for services') ?>"
                                    value="<?= esc($has_record ? $val('max_quantity_allowed') : '') ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     STEP 3: Media & Files
                ============================================================ -->
                <div class="step-panel" data-step="3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="service_image_selector"
                                    class="required"><?= labels('image', 'Image') ?></label>
                                <small>(<?= labels('service_image_recommended_size', 'We recommend 424 x 551 pixels') ?>)</small><br>
                                <?php if ($has_record): ?>
                                    <input type="file" name="service_image_selector<?= $is_edit ? '_edit' : '' ?>"
                                        class="filepond logo" id="service_image_selector" accept="image/*"
                                        onchange="loadServiceImage(event)">
                                    <img alt="no image found" width="130px" style="border: solid 1; border-radius: 12px;"
                                        height="100px" class="mt-2" id="image_preview" src="<?= esc($val('image')) ?>">
                                <?php else: ?>
                                    <input type="file" name="service_image_selector" class="filepond logo"
                                        id="service_image_selector" accept="image/*" required>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label
                                    for="other_service_image_selector"><?= labels('other_images', 'Other Image') ?></label>
                                <small>(<?= labels('other_image_recommended_size', 'We recommend 960 x 540 pixels') ?>)</small><br>
                                <input type="file" name="other_service_image_selector<?= $is_edit ? '_edit' : '' ?>[]"
                                    class="filepond logo" id="other_service_image_selector" accept="image/*" multiple>
                                <?php if ($has_record && !empty($service['other_images'])): ?>
                                    <div class="row mt-2" id="other_images_container">
                                        <div class="col-12 mb-2">
                                            <button type="button"
                                                class="btn btn-primary btn-sm remove-all-other-images"><?= labels('remove_all_images', 'Remove All Images') ?></button>
                                        </div>
                                        <?php foreach ($service['other_images'] as $index => $image): ?>
                                            <div class="col-md-3 mb-2 other-image-container">
                                                <div class="position-relative d-inline-block mt-2">
                                                    <img alt="no image found" width="130px"
                                                        style="border-radius: 10px; display: block;" height="100px"
                                                        src="<?= esc($image) ?>">
                                                    <input type="hidden" name="existing_other_images[]"
                                                        value="<?= esc(str_replace(base_url(), '', $image)) ?>">
                                                    <button type="button" class="remove-other-image"
                                                        data-image-index="<?= $index ?>"
                                                        style="position:absolute;top:-8px;right:-8px;width:22px;height:22px;border-radius:50%;background:red;border:2px solid #fff;color:#fff;font-size:11px;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0;box-shadow:0 1px 4px rgba(0,0,0,.25);"><i
                                                            class="fas fa-times"></i></button>
                                                    <input type="hidden" name="remove_other_images[<?= $index ?>]" value="0"
                                                        class="remove-flag">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="files"><?= labels('files', 'Files') ?></label>
                                <input type="file" name="files<?= $is_edit ? '_edit' : '' ?>[]"
                                    class="filepond-docs<?= $is_edit ? ' logo' : '' ?>" id="files" multiple>
                                <?php if ($has_record && !empty($service['files'])): ?>
                                    <div class="row mt-2" id="files_container">
                                        <div class="col-12 mb-2">
                                            <button type="button"
                                                class="btn btn-primary btn-sm remove-all-files"><?= labels('remove_all_files', 'Remove All Files') ?></button>
                                        </div>
                                        <?php foreach ($service['files'] as $index => $file): ?>
                                            <div class="col-md-3 mb-2 file-container">
                                                <div class="position-relative">
                                                    <div class="p-2" style="border-radius: 8px; background-color:#f2f1f6">
                                                        <a href="<?= esc($file) ?>" class="file-link"
                                                            target="_blank"><?= labels('view_uploaded_file') ?></a>
                                                    </div>
                                                    <input type="hidden" name="existing_files[]" value="<?= esc($file) ?>">
                                                    <button type="button" class="btn btn-sm btn-danger remove-file"
                                                        data-file-index="<?= $index ?>"
                                                        style="position: absolute; top: 5px; right: 5px;"><i
                                                            class="fas fa-times"></i></button>
                                                    <input type="hidden" name="remove_files[<?= $index ?>]" value="0"
                                                        class="remove-flag">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     STEP 4: Price Details
                ============================================================ -->
                <div class="step-panel" data-step="4">
                    <?php
                    $tax_type_val = $has_record ? $val('tax_type') : '';
                    $tax_id_val = $has_record ? $val('tax_id') : '';
                    ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tax_type" class="required"><?= labels('price', 'Price') ?>
                                    <?= labels('type', 'Type') ?></label>
                                <select name="tax_type" id="tax_type" class="form-control" required>
                                    <option value="excluded" <?= $tax_type_val === 'excluded' ? 'selected' : '' ?>>
                                        <?= labels('tax_excluded_in_price', 'Tax Excluded In Price') ?>
                                    </option>
                                    <option value="included" <?= $tax_type_val === 'included' ? 'selected' : '' ?>>
                                        <?= labels('tax_included_in_price', 'Tax Included In Price') ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tax_id" class="required"><?= labels('select_tax', 'Select Tax') ?></label>
                                <?php if (empty($tax_data)): ?>
                                    <div id="no_tax_notice" class="d-flex align-items-center p-2 border rounded">
                                        <span
                                            class="text-muted small mr-2"><?= labels('no_tax_available', 'No tax available.') ?></span>
                                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal"
                                            data-target="#add_tax_modal_service">
                                            <i class="fas fa-plus"></i> <?= labels('add_tax', 'Add Tax') ?>
                                        </button>
                                    </div>
                                    <select id="tax" name="tax_id" required class="form-control w-100 select2"
                                        style="display:none;">
                                        <option value=""><?= labels('select_tax', 'Select Tax') ?></option>
                                    </select>
                                <?php else: ?>
                                    <select id="tax" name="tax_id" required class="form-control w-100 select2">
                                        <option value=""><?= labels('select_tax', 'Select Tax') ?></option>
                                        <?php foreach ($tax_data as $pn):
                                            $selected = ($has_record && $tax_id_val == $pn['id']) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $pn['id'] ?>" <?= $selected ?>>
                                                <?= $pn['title'] ?>(<?= $pn['percentage'] ?>%)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text">
                                        <a href="#" class="text-primary" data-toggle="modal"
                                            data-target="#add_tax_modal_service">
                                            <i class="fas fa-plus"></i> <?= labels('add_new_tax', 'Add new tax') ?>
                                        </a>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="price" class="required"><?= labels('price', 'Price') ?> (<?= esc($currency ?? '') ?>)</label>
                                <input id="price" class="form-control" type="number" name="price"
                                    placeholder="<?= labels('price', 'Price') ?>" min="1"
                                    oninput="this.value = Math.abs(this.value)"
                                    value="<?= esc($has_record ? $val('price') : '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="discounted_price"
                                    class="required"><?= labels('discounted_price', 'Discounted Price') ?> (<?= esc($currency ?? '') ?>)</label>
                                <input id="discounted_price" class="form-control" type="number" name="discounted_price"
                                    min="0" oninput="this.value = Math.abs(this.value)"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('discounted_price', 'Discounted Price') ?> <?= labels('here', ' Here ') ?>"
                                    value="<?= esc($has_record ? $val('discounted_price') : '') ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     STEP 5: FAQs (multilingual)
                ============================================================ -->
                <div class="step-panel" data-step="5">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php foreach ($sorted_languages as $language): ?>
                                    <div class="language-option-faqs position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-faqs-<?= $language['code'] ?>" data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span
                                            class="language-text-faqs px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?>     <?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-underline-faqs"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($sorted_languages as $language):
                        $code = $language['code'];
                        $is_default = $language['is_default'] == 1;
                        $lang_suffix = $is_default ? '' : ' (' . $code . ')';
                        $currentFaqs = [];
                        if ($has_record) {
                            if (isset($service['translated_' . $code]['faqs']) && is_array($service['translated_' . $code]['faqs'])) {
                                $currentFaqs = $service['translated_' . $code]['faqs'];
                            } elseif ($is_default && isset($service['faqs']) && is_array($service['faqs'])) {
                                $currentFaqs = $service['faqs'];
                            }
                        }
                        ?>
                        <div class="row" id="translationFaqsDiv-<?= $code ?>" <?= $code == $default_language_faqs ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="col-md-12">
                                <div class="faq-container" data-language="<?= $code ?>">
                                    <div class="faq-items-wrapper">
                                        <?php if (!empty($currentFaqs)): ?>
                                            <?php foreach ($currentFaqs as $i => $faq): ?>
                                                <div class="faq-item row mb-3" data-faq-index="<?= $i ?>">
                                                    <div class="col-md-5">
                                                        <div class="form-group">
                                                            <label
                                                                for="faq_question_<?= $code ?>_<?= $i ?>"><?= labels('question', 'Question') . $lang_suffix ?></label>
                                                            <input type="text" class="form-control faq-question"
                                                                name="faq_question_<?= $code ?>_<?= $i ?>"
                                                                placeholder="<?= labels('enter_question', 'Enter the question here') ?>"
                                                                value="<?= esc($faq['question'] ?? '') ?>" />
                                                        </div>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <div class="form-group">
                                                            <label
                                                                for="faq_answer_<?= $code ?>_<?= $i ?>"><?= labels('answer', 'Answer') . $lang_suffix ?></label>
                                                            <div class="d-flex align-items-center">
                                                                <input type="text" class="form-control faq-answer"
                                                                    name="faq_answer_<?= $code ?>_<?= $i ?>"
                                                                    placeholder="<?= labels('enter_answer', 'Enter the answer here') ?>"
                                                                    value="<?= esc($faq['answer'] ?? '') ?>" />
                                                                <button type="button" class="btn btn-danger remove-faq-btn ml-2">
                                                                    <i class="fas fa-minus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="faq-item row mb-3" data-faq-index="0">
                                                <div class="col-md-5">
                                                    <div class="form-group">
                                                        <label
                                                            for="faq_question_<?= $code ?>_0"><?= labels('question', 'Question') . $lang_suffix ?></label>
                                                        <input type="text" class="form-control faq-question"
                                                            name="faq_question_<?= $code ?>_0"
                                                            placeholder="<?= labels('enter_question', 'Enter the question here') ?>" />
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="form-group">
                                                        <label
                                                            for="faq_answer_<?= $code ?>_0"><?= labels('answer', 'Answer') . $lang_suffix ?></label>
                                                        <div class="d-flex align-items-center">
                                                            <input type="text" class="form-control faq-answer"
                                                                name="faq_answer_<?= $code ?>_0"
                                                                placeholder="<?= labels('enter_answer', 'Enter the answer here') ?>" />
                                                            <button type="button" class="btn btn-danger remove-faq-btn ml-2"
                                                                style="display: none;">
                                                                <i class="fas fa-minus"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <button type="button" class="btn btn-primary add-faq-btn"
                                                data-language="<?= $code ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ============================================================
                     STEP 6: SEO Settings (multilingual)
                ============================================================ -->
                <div class="step-panel" data-step="6">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php foreach ($sorted_languages as $language): ?>
                                    <div class="language-option-seo position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-seo-<?= $language['code'] ?>" data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span
                                            class="language-text-seo px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?>     <?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-underline-seo"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($sorted_languages as $language):
                        $code = $language['code'];
                        $is_default = $language['is_default'] == 1;
                        $lang_suffix = $is_default ? '' : ' (' . $code . ')';
                        $seo_title = $seo_keywords = $seo_description = $seo_schema = '';
                        if ($has_record) {
                            $seo_title = $tseo($code, 'seo_title', $is_default ? ($service_seo_settings['title'] ?? '') : '');
                            $seo_keywords = $tseo($code, 'seo_keywords', $is_default ? ($service_seo_settings['keywords'] ?? '') : '');
                            $seo_description = $tseo($code, 'seo_description', $is_default ? ($service_seo_settings['description'] ?? '') : '');
                            $seo_schema = $tseo($code, 'seo_schema_markup', $is_default ? ($service_seo_settings['schema_markup'] ?? '') : '');
                        }
                        ?>
                        <div id="translationDivSeo-<?= $code ?>" <?= $code == $default_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label
                                            for="meta_title<?= $code ?>"><?= labels('meta_title', 'Meta Title') . $lang_suffix ?></label>
                                        <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>"
                                            class="fa fa-question-circle" data-original-title="" title=""
                                            data-toggle="popover"></i>
                                        <input id="meta_title<?= $code ?>" class="form-control" type="text"
                                            name="meta_title[<?= $code ?>]"
                                            placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>"
                                            maxlength="255" value="<?= esc($seo_title) ?>">
                                        <small
                                            class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label
                                            for="meta_keywords<?= $code ?>"><?= labels('meta_keywords', 'Meta Keywords') . $lang_suffix ?></label>
                                        <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>"
                                            class="fa fa-question-circle" data-original-title="" title=""
                                            data-toggle="popover"></i>
                                        <input id="meta_keywords<?= $code ?>" style="border-radius: 0.25rem"
                                            class="w-100 seo-meta-keywords" type="text" name="meta_keywords[<?= $code ?>][]"
                                            placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>"
                                            value="<?= esc($seo_keywords) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label
                                            for="meta_description<?= $code ?>"><?= labels('meta_description', 'Meta Description') . $lang_suffix ?></label>
                                        <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>"
                                            class="fa fa-question-circle" data-original-title="" title=""
                                            data-toggle="popover"></i>
                                        <textarea id="meta_description<?= $code ?>" style="min-height:60px"
                                            class="form-control" name="meta_description[<?= $code ?>]"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>"
                                            maxlength="500"><?= esc($seo_description) ?></textarea>
                                        <small
                                            class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label
                                            for="schema_markup<?= $code ?>"><?= labels('schema_markup', 'Schema Markup') . $lang_suffix ?></label>
                                        <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                            data-toggle="popover" class="fa fa-question-circle" data-original-title=""
                                            title=""></i>
                                        <textarea id="schema_markup<?= $code ?>" style="min-height:60px"
                                            class="form-control" name="schema_markup[<?= $code ?>]"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"><?= esc($seo_schema) ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?></label>
                                <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>"
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="meta_image" id="meta_image" accept="image/*">
                                <small
                                    class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                                <?php if ($is_edit && !empty($service_seo_settings['image'])): ?>
                                    <div class="position-relative d-inline-block mt-2">
                                        <img src="<?= esc($service_seo_settings['image']) ?>" alt="SEO Image"
                                            style="max-width: 120px; max-height: 80px; border-radius: 8px;">
                                        <button type="button" class="btn btn-sm btn-danger remove-service-seo-image"
                                            data-service-id="<?= esc($service_seo_settings['service_id'] ?? '') ?>"
                                            data-seo-id="<?= esc($service_seo_settings['id'] ?? '') ?>"
                                            style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; padding: 0; border-radius: 50%; font-size: 10px;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     STEP 7: Service Options
                ============================================================ -->
                <div class="step-panel" data-step="7">
                    <?php
                    $is_cancelable_checked = $has_record && $val('is_cancelable') == '1';
                    $pay_later_checked = $has_record && $val('is_pay_later_allowed') == '1';
                    $at_store_checked = $has_record && $val('at_store') == '1';
                    $at_doorstep_checked = $has_record && $val('at_doorstep') == '1';
                    $approve_service_checked = $has_record && $val('approved_by_admin') == '1';
                    $approve_service_value = $has_record ? $val('approved_by_admin', '0') : '0';
                    $cancelable_till_value = $has_record ? $val('cancelable_till', '') : '';
                    ?>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card stepper-toggle-card <?= $status_checked ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('status', 'Status') ?></strong></div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="status"
                                                    name="status" <?= $status_checked ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="status"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('status_description', 'When disabled, this service is not visible to customers.') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stepper-toggle-card <?= $is_cancelable_checked ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col">
                                            <strong><?= labels('is_cancelable_?', 'Is Cancelable') ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="is_cancelable"
                                                    name="is_cancelable" <?= $is_cancelable_checked ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="is_cancelable"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('data_content_for_is_cancellable', 'Can customers cancel their booking if they\'ve already booked this service?') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($cod_setting == 1): ?>
                            <div class="col-md-4">
                                <div class="card stepper-toggle-card <?= $pay_later_checked ? 'active' : '' ?>">
                                    <div class="card-body p-3">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col">
                                                <strong><?= labels('pay_later_allowed', 'Pay Later Allowed') ?></strong>
                                            </div>
                                            <div class="col-auto">
                                                <div class="custom-control custom-switch mb-0">
                                                    <input type="checkbox" class="custom-control-input" id="pay_later"
                                                        name="pay_later" <?= $pay_later_checked ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="pay_later"></label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row no-gutters mt-2">
                                            <div class="col"><small
                                                    class="text-muted"><?= labels('data_content_for_paylater_allowed', 'If this option is enabled, customers can book the service and pay after the booking is completed. Generally, this is known as the Cash On Delivery option.') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-4" id="service_at_store">
                            <div class="card stepper-toggle-card <?= $at_store_checked ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('at_store', 'At Store') ?></strong></div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="at_store"
                                                    name="at_store" <?= $at_store_checked ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="at_store"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('data_content_for_service_at_store', 'If this feature is enabled, customers can book the service at the provider\'s location.') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4" id="service_at_doorstep">
                            <div class="card stepper-toggle-card <?= $at_doorstep_checked ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('at_doorstep', 'At Doorstep') ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="at_doorstep"
                                                    name="at_doorstep" <?= $at_doorstep_checked ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="at_doorstep"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('data_content_for_service_at_doorstep', 'If this feature is enabled, customers can book the service at their location.') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4" id="service_approve_service">
                            <div class="card stepper-toggle-card <?= $approve_service_checked ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col">
                                            <strong><?= labels('approve_service', 'Approve Service') ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="hidden" name="approve_service_value"
                                                    value="<?= esc($approve_service_value) ?>"
                                                    id="approve_service_value">
                                                <input type="checkbox" class="custom-control-input" id="approve_service"
                                                    name="approve_service" <?= $approve_service_checked ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="approve_service"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('data_content_for_approve_service', 'This toggle controls whether the service is approved and visible to users.') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3" id="cancel_order" <?= !$is_cancelable_checked ? 'style="display:none"' : '' ?>>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="cancelable_till"
                                    class="required"><?= labels('cancelable_before', 'Cancelable before') ?></label>
                                <i data-content="<?= labels('data_content_for_cancellable_before', 'If customer can cancel the service, they can cancel their booking X minutes before it starts.') ?>"
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text myDivClass" style="height: 42px;">
                                            <span class="mySpanClass"><?= labels('minutes', 'Minutes') ?></span>
                                        </div>
                                    </div>
                                    <input type="number" style="height: 42px;" class="form-control"
                                        name="cancelable_till" id="cancelable_till" placeholder="Ex. 30" min="0"
                                        value="<?= esc($cancelable_till_value) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     STEP 8: Review
                ============================================================ -->
                <div class="step-panel" data-step="8">
                    <div id="stepper-review-content"></div>
                </div>

                <!-- ============================================================
                     Footer
                ============================================================ -->
                <div class="stepper-footer">
                    <button type="button" class="btn-step-back" disabled style="visibility:hidden;">
                        <i class="fas fa-chevron-left"></i> <?= labels('back', 'Back') ?>
                    </button>
                    <button type="button" class="btn-step-next">
                        <?= labels('next_step', 'Next Step') ?> <i class="fas fa-chevron-right"></i>
                    </button>
                    <button type="submit" class="btn-step-submit submit_btn" style="display:none;">
                        <i class="fas fa-check"></i> <?= $submit_label ?>
                    </button>
                </div>
            </div>
        </div>

        <?= form_close() ?>
    </section>
</div>

<!-- ============================================================
     Switchery wiring + cancelable_till sync + image preview helper
============================================================ -->
<script>
    function syncCancelRow() {
        if ($('#is_cancelable').is(':checked')) $('#cancel_order').show();
        else $('#cancel_order').hide();
    }

    $(document).ready(function () {
        $(document).on('change', '#is_cancelable', syncCancelRow);

        // Re-sync when step 7 becomes active (panel was hidden at document.ready,
        // so jQuery show/hide was unreliable at init time).
        var panel7 = document.querySelector('.step-panel[data-step="7"]');
        if (panel7) {
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.target.classList.contains('active')) syncCancelRow();
                });
            }).observe(panel7, { attributes: true, attributeFilter: ['class'] });
        }

        $('#approve_service').on('change', function () {
            this.value = this.checked ? 1 : 0;
            $('#approve_service_value').val(this.value);
        });

        $('#service_image_selector').on('change', function () {
            var filename = $(this).val();
            if (/^\s*$/.test(filename)) {
                $(".file-upload").removeClass('active');
                $("#noFile").text("No file chosen...");
            } else {
                $(".file-upload").addClass('active');
                $("#noFile").text(filename.replace("C:\\fakepath\\", ""));
            }
        });
    });

    function loadServiceImage(event) {
        var image = document.getElementById('image_preview');
        if (!image) return;

        var files = event.target.files;
        if (!files && typeof FilePond !== 'undefined') {
            var pond = FilePond.find(event.target);
            if (pond) {
                var pondFiles = pond.getFiles();
                if (pondFiles.length > 0) {
                    image.src = URL.createObjectURL(pondFiles[0].file);
                    return;
                }
            }
        }

        if (files && files[0]) {
            image.src = URL.createObjectURL(files[0]);
        }
    }
</script>

<!-- ============================================================
     Partner change -> toggle service options visibility + member prefill
============================================================ -->
<script>
    $(document).ready(function () {
        function applyPartnerOptions() {
            var $opt = $('#partner option:selected');
            if (!$opt.length || !$opt.val()) return;

            var members = parseInt($opt.data('members'), 10);
            var partnerType = parseInt($opt.data('type'), 10); // 0 = individual, 1 = organization
            var $membersField = $('#members');

            if (!isNaN(members)) {
                if (partnerType === 0) {
                    // Individual: always 1 member, readonly, max=1 so step validation catches any bypass
                    var indMsg = (window.stepperLabels && window.stepperLabels.individual_provider_members_must_be_one)
                        ? window.stepperLabels.individual_provider_members_must_be_one
                        : 'An individual provider can have only 1 member';
                    $membersField.val(1).prop('readonly', true).attr('max', 1).attr('data-max-message', indMsg);
                } else {
                    // Organization: editable, cap at provider's member count
                    $membersField.prop('readonly', false).attr('max', members).removeAttr('data-max-message');
                    if (!window.SERVICE_FORM_HAS_RECORD) {
                        $membersField.val(members);
                    } else {
                        // On edit/duplicate: clamp saved value to provider's max
                        var saved = parseInt($membersField.val(), 10);
                        if (!isNaN(saved) && saved > members) {
                            $membersField.val(members);
                        }
                    }
                }
            }

            var atStore = parseInt($opt.data('at_store'), 10);
            var atDoorstep = parseInt($opt.data('at_doorstep'), 10);
            var needApprove = parseInt($opt.data('need_approval_for_the_service'), 10);

            $('#service_at_store').toggle(atStore === 1);
            $('#service_at_doorstep').toggle(atDoorstep === 1);

            if (atStore !== 1) $('#at_store').prop('checked', false).trigger('change');
            if (atDoorstep !== 1) $('#at_doorstep').prop('checked', false).trigger('change');

            if (needApprove === 0) {
                $('#service_approve_service').hide();
                $('#approve_service_value').val(1);
            } else {
                $('#service_approve_service').show();
            }
        }

        applyPartnerOptions();
        $('#partner').on('change', applyPartnerOptions);
    });
</script>

<!-- ============================================================
     Popover hover
============================================================ -->
<script>
    $(function () {
        let popoverTimer, currentPopover = null, isOverPopover = false, isOverTrigger = false;
        $('[data-toggle="popover"]').popover({ html: true, trigger: 'manual', container: 'body' })
            .on('mouseenter', function () {
                const $this = $(this);
                isOverTrigger = true;
                clearTimeout(popoverTimer);
                if (currentPopover && currentPopover[0] !== $this[0]) currentPopover.popover('hide');
                currentPopover = $this;
                $this.popover('show');
            })
            .on('mouseleave', function () { isOverTrigger = false; startHideTimer(); });
        $(document).on('mouseenter', '.popover', function () { isOverPopover = true; clearTimeout(popoverTimer); })
            .on('mouseleave', '.popover', function () { isOverPopover = false; startHideTimer(); });
        function startHideTimer() {
            clearTimeout(popoverTimer);
            popoverTimer = setTimeout(function () {
                if (!isOverTrigger && !isOverPopover && currentPopover) {
                    currentPopover.popover('hide');
                    currentPopover = null;
                }
            }, 150);
        }
    });
</script>

<!-- ============================================================
     Language tab switching (main + faqs + seo)
============================================================ -->
<script>
    $(document).ready(function () {
        let current_language = <?= json_encode($default_language) ?>;
        let current_language_faqs = <?= json_encode($default_language_faqs) ?>;
        let current_language_seo = <?= json_encode($default_language_seo) ?>;

        $(document).on('click', '.language-option', function () {
            const language = $(this).data('language');
            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');
            $('.language-text').removeClass('text-primary fw-medium').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted').addClass('text-primary');
            if (language != current_language) {
                $('#translationDiv-' + language).show();
                $('#translationDiv-' + current_language).hide();
            }
            current_language = language;
        });

        $(document).on('click', '.language-option-faqs', function () {
            const language = $(this).data('language');
            $('.language-underline-faqs').css('width', '0%');
            $('#language-faqs-' + language).find('.language-underline-faqs').css('width', '100%');
            $('.language-text-faqs').removeClass('text-primary fw-medium').addClass('text-muted');
            $('#language-faqs-' + language).find('.language-text-faqs').removeClass('text-muted').addClass('text-primary');
            if (language != current_language_faqs) {
                $('#translationFaqsDiv-' + language).show();
                $('#translationFaqsDiv-' + current_language_faqs).hide();
            }
            current_language_faqs = language;
        });

        $(document).on('click', '.language-option-seo', function () {
            const language = $(this).data('language');
            $('.language-underline-seo').css('width', '0%');
            $('#language-seo-' + language).find('.language-underline-seo').css('width', '100%');
            $('.language-text-seo').removeClass('text-primary fw-medium').addClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').removeClass('text-muted').addClass('text-primary fw-medium');
            $('[id^="translationDivSeo-"]').hide();
            $('#translationDivSeo-' + language).show();
            current_language_seo = language;
        });
    });
</script>

<!-- ============================================================
     Category select2 — strip indentation dashes from selected display.
     render_categories_options() emits options like "&nbsp;&nbsp;— Sub";
     templateSelection renders the clean leaf name once chosen.
============================================================ -->
<script>
    function cleanCategoryName(text) {
        if (!text) return '';
        // Strip leading non-breaking spaces, em-dashes/hyphens, regular spaces
        return text.replace(/^[\s \-—–]+/, '').trim();
    }
    // scripts.js initializes all `.select2` via a global selector inside its own
    // $(document).ready handler. That handler is registered AFTER ours (scripts.js
    // is loaded near the bottom of include-scripts.php), so any select2 init we
    // run inside our own ready callback gets clobbered by the generic init that
    // follows. Wrap our init in setTimeout(_, 0) so it runs after every ready
    // callback — including the generic one — has finished.
    $(document).ready(function () {
        setTimeout(function () {
            var $cat = $('#category_item');
            if (!$cat.length) return;
            if ($cat.hasClass('select2-hidden-accessible')) {
                $cat.select2('destroy');
            }
            $cat.select2({
                width: '100%',
                templateSelection: function (data) {
                    if (!data.id) return data.text;
                    return cleanCategoryName(data.text);
                }
            });
            if ($cat[0] && $cat[0].style.display === 'none') {
                $cat.next('.select2-container').hide();
            }

            var $catParent = $('#cat_modal_parent_id');
            if ($catParent.length) {
                if ($catParent.hasClass('select2-hidden-accessible')) {
                    $catParent.select2('destroy');
                }
                $catParent.select2({
                    width: '100%',
                    templateSelection: function (data) {
                        if (!data.id) return data.text;
                        return cleanCategoryName(data.text);
                    }
                });
                if ($catParent[0] && $catParent[0].style.display === 'none') {
                    $catParent.next('.select2-container').hide();
                }
            }

            var $tax = $('#tax');
            if ($tax.length) {
                if ($tax.hasClass('select2-hidden-accessible')) {
                    $tax.select2('destroy');
                }
                $tax.select2({
                    width: '100%'
                });
                if ($tax[0] && $tax[0].style.display === 'none') {
                    $tax.next('.select2-container').hide();
                }
            }
        }, 0);
    });
</script>

<!-- ============================================================
     Tagify init
============================================================ -->
<script>
    $(document).ready(function () {
        document.querySelectorAll('.translation-tags').forEach(function (input) {
            if (input != null) new Tagify(input);
        });
        document.querySelectorAll('.seo-meta-keywords').forEach(function (input) {
            if (input != null) new Tagify(input);
        });
    });
</script>

<!-- ============================================================
     FAQ manager (add / edit / clone unified)
============================================================ -->
<script>
    class ServiceFAQManager {
        constructor() {
            this.faqCounters = {};
            this.initializeFAQSystem();
        }
        initializeFAQSystem() {
            <?php foreach ($sorted_languages as $language):
                $code = $language['code'];
                $count = 0;
                if ($has_record) {
                    if (isset($service['translated_' . $code]['faqs']) && is_array($service['translated_' . $code]['faqs'])) {
                        $count = count($service['translated_' . $code]['faqs']);
                    } elseif ($language['is_default'] == 1 && isset($service['faqs']) && is_array($service['faqs'])) {
                        $count = count($service['faqs']);
                    }
                }
                ?>
                this.faqCounters[<?= json_encode($code) ?>] = <?= (int) $count ?>;
            <?php endforeach; ?>
            this.bindEvents();
            this.updateRemoveButtons();
        }
        bindEvents() {
            $(document).on('click', '.add-faq-btn', this.addFAQ.bind(this));
            $(document).on('click', '.remove-faq-btn', this.removeFAQ.bind(this));
            $('#<?= $form_id ?>').on('submit', this.restructureFAQData.bind(this));
        }
        addFAQ(event) {
            const language = $(event.currentTarget).data('language');
            this.faqCounters[language]++;
            const faqIndex = this.faqCounters[language];
            const faqHTML = `
                <div class="faq-item row mb-3" data-faq-index="${faqIndex}">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="faq_question_${language}_${faqIndex}"><?= labels('question', 'Question') ?> (${language})</label>
                            <input type="text" class="form-control faq-question"
                                   name="faq_question_${language}_${faqIndex}"
                                   placeholder="<?= labels('enter_question', 'Enter the question here') ?>" />
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="faq_answer_${language}_${faqIndex}"><?= labels('answer', 'Answer') ?> (${language})</label>
                            <div class="d-flex align-items-center">
                                <input type="text" class="form-control faq-answer"
                                       name="faq_answer_${language}_${faqIndex}"
                                       placeholder="<?= labels('enter_answer', 'Enter the answer here') ?>" />
                                <button type="button" class="btn btn-danger remove-faq-btn ml-2">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
            $(event.currentTarget).closest('.faq-container').find('.faq-items-wrapper').append(faqHTML);
            this.updateRemoveButtons();
        }
        removeFAQ(event) {
            $(event.currentTarget).closest('.faq-item').remove();
            this.updateRemoveButtons();
        }
        updateRemoveButtons() {
            $('.faq-container').each((i, c) => {
                const items = $(c).find('.faq-item');
                const buttons = $(c).find('.remove-faq-btn');
                if (items.length > 1) buttons.show();
                else buttons.hide();
            });
        }
        collectFAQData() {
            const faqData = {};
            <?php foreach ($sorted_languages as $language): ?>
                faqData[<?= json_encode($language['code']) ?>] = [];
            <?php endforeach; ?>
            $('.faq-container').each((i, c) => {
                const language = $(c).data('language');
                const items = [];
                $(c).find('.faq-item').each((j, it) => {
                    const q = $(it).find('.faq-question').val().trim();
                    const a = $(it).find('.faq-answer').val().trim();
                    if (q || a) items.push([q, a]);
                });
                faqData[language] = items;
            });
            return faqData;
        }
        restructureFAQData() {
            const faqData = this.collectFAQData();
            let inp = $('#faq_data_input');
            if (inp.length === 0) {
                inp = $('<input type="hidden" name="faqs" id="faq_data_input" />');
                $('#<?= $form_id ?>').append(inp);
            }
            inp.val(JSON.stringify(faqData));
        }
    }
    $(document).ready(function () { window.serviceFaqManager = new ServiceFAQManager(); });
</script>

<?php if ($has_record): ?>
    <!-- ============================================================
     Existing other-images / files — soft-delete toggle handlers
    ============================================================ -->
    <script>
        $(document).ready(function () {
            $('.remove-other-image').on('click', function () {
                const button = this;
                const container = $(this).closest('.position-relative');
                const removeFlag = container.find('.remove-flag');
                if (!removeFlag.length) return;
                if (removeFlag.val() === "0") {
                    removeFlag.val("1");
                    container.find('img').css('opacity', '0.4');
                    $(button).css('background', 'rgba(13,110,253,0.9)').html('<i class="fas fa-undo"></i>');
                } else {
                    removeFlag.val("0");
                    container.find('img').css('opacity', '1');
                    $(button).css('background', 'red').html('<i class="fas fa-times"></i>');
                }
            });

            $('.remove-all-other-images').on('click', function () {
                Swal.fire({
                    title: '<?= labels('are_your_sure', 'Are you sure?') ?>',
                    text: '<?= labels('are_you_sure_to_remove_all_images') ?>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!') ?>',
                    cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    $('#other_images_container').find('.other-image-container').each(function () {
                        const container = $(this).find('.position-relative');
                        const removeFlag = container.find('.remove-flag');
                        const button = container.find('.remove-other-image');
                        if (!removeFlag.length) return;
                        removeFlag.val("1");
                        container.find('img').css('opacity', '0.4');
                        button.css('background', 'rgba(13,110,253,0.9)').html('<i class="fas fa-undo"></i>');
                    });
                });
            });

            $('.remove-file').on('click', function () {
                const button = this;
                const container = $(this).closest('.position-relative');
                const removeFlag = container.find('.remove-flag');
                if (!removeFlag.length) return;
                if (removeFlag.val() === "0") {
                    removeFlag.val("1");
                    container.find('.file-link, .p-2').css('opacity', '0.5');
                    $(button).removeClass('btn-danger').addClass('btn-primary').html('<i class="fas fa-undo"></i>');
                } else {
                    removeFlag.val("0");
                    container.find('.file-link, .p-2').css('opacity', '1');
                    $(button).removeClass('btn-primary').addClass('btn-danger').html('<i class="fas fa-times"></i>');
                }
            });

            $('.remove-all-files').on('click', function () {
                Swal.fire({
                    title: '<?= labels('are_your_sure', 'Are you sure?') ?>',
                    text: '<?= labels('are_you_sure_to_remove_all_files', 'Are you sure you want to remove all files?') ?>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!') ?>',
                    cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    $('#files_container').find('.file-container').each(function () {
                        const container = $(this).find('.position-relative');
                        const removeFlag = container.find('.remove-flag');
                        const button = container.find('.remove-file');
                        if (!removeFlag.length) return;
                        removeFlag.val("1");
                        container.find('.file-link, .p-2').css('opacity', '0.5');
                        button.removeClass('btn-danger').addClass('btn-primary').html('<i class="fas fa-undo"></i>');
                    });
                });
            });
        });
    </script>
<?php endif; ?>

<?php if ($is_edit): ?>
    <!-- ============================================================
     SEO image removal AJAX (edit only)
    ============================================================ -->
    <script>
        $(document).ready(function () {
            $(document).on('click', '.remove-service-seo-image', function () {
                const button = $(this);
                const serviceId = button.data('service-id');
                const seoId = button.data('seo-id');
                Swal.fire({
                    title: '<?= labels('are_your_sure', 'Are you sure?') ?>',
                    text: '<?= labels('are_you_sure_to_remove_seo_image') ?>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!') ?>',
                    cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                    $.ajax({
                        url: '<?= base_url('admin/services/remove_seo_image') ?>',
                        type: 'POST',
                        data: { service_id: serviceId, seo_id: seoId, <?= csrf_token() ?>: '<?= csrf_hash() ?>' },
                        dataType: 'json',
                        success: function (response) {
                            if (response.error === false) {
                                button.closest('.position-relative').remove();
                                showToastMessage(response.message, 'success');
                            } else {
                                showToastMessage(response.message, 'error');
                                button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                            }
                        },
                        error: function () {
                            showToastMessage('<?= labels('error_occured', 'An error occurred while removing the SEO image') ?>', 'error');
                            button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        }
                    });
                });
            });
            $(document).on('change', '#meta_image', function () {
                $('.remove-service-seo-image').prop('disabled', false).html('<i class="fas fa-times"></i>');
            });
        });
    </script>
<?php endif; ?>

<!-- ============================================================
     Add Tax Modal (inline quick-add from service form)
============================================================ -->
<div class="modal fade" id="add_tax_modal_service" tabindex="-1" aria-labelledby="add_tax_modal_service_label"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="add_tax_modal_service_label"><?= labels('add_tax', 'Add Tax') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-4 mb-3">
                    <?php foreach ($sorted_languages as $language): ?>
                        <div class="language-option-tax-modal position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                            id="tax-modal-lang-<?= $language['code'] ?>" data-language="<?= $language['code'] ?>"
                            style="cursor: pointer; padding: 0.5rem 0;">
                            <span
                                class="language-text-tax-modal px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                <?= $language['language'] ?>     <?= $language['is_default'] ? ' (Default)' : '' ?>
                            </span>
                            <div class="language-underline-tax-modal"
                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($sorted_languages as $language):
                    $is_default_tmod = $language['is_default'] == 1;
                    $code_tmod = $language['code'];
                    ?>
                    <div class="form-group" id="tax-modal-title-div-<?= $code_tmod ?>" <?= $is_default_tmod ? '' : 'style="display:none;"' ?>>
                        <label for="tax_modal_title_<?= $code_tmod ?>" <?= $is_default_tmod ? 'class="required"' : '' ?>><?= labels('title', 'Title') . ($is_default_tmod ? '' : ' (' . $code_tmod . ')') ?></label>
                        <input id="tax_modal_title_<?= $code_tmod ?>" class="form-control" type="text"
                            placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>">
                    </div>
                <?php endforeach; ?>

                <div class="form-group">
                    <label for="tax_modal_percentage" class="required"><?= labels('percentage', 'Percentage') ?></label>
                    <input id="tax_modal_percentage" class="form-control" type="number" step="0.01" min="0"
                        placeholder="<?= labels('enter_percentage_here', 'Enter the percentage here') ?>">
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input id="tax_modal_status" class="custom-control-input" type="checkbox" checked>
                        <label for="tax_modal_status"
                            class="custom-control-label"><?= labels('enable', 'Enable') ?></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                <button type="button" class="btn btn-primary" id="save_tax_service_btn">
                    <span class="save-tax-text"><?= labels('add_tax', 'Add Tax') ?></span>
                    <span class="save-tax-spinner d-none"><i class="fas fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Add Category Modal (inline quick-add from service form)
============================================================ -->
<div class="modal fade" id="add_category_modal_service" tabindex="-1" aria-labelledby="add_category_modal_service_label"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="add_category_modal_service_label">
                    <?= labels('add_category', 'Add Category') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-4 mb-3">
                    <?php foreach ($sorted_languages as $language): ?>
                        <div class="language-option-cat-modal position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                            id="cat-modal-lang-<?= $language['code'] ?>" data-language="<?= $language['code'] ?>"
                            style="cursor: pointer; padding: 0.5rem 0;">
                            <span
                                class="language-text-cat-modal px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                <?= $language['language'] ?>     <?= $language['is_default'] ? ' (Default)' : '' ?>
                            </span>
                            <div class="language-underline-cat-modal"
                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row">
                    <?php foreach ($sorted_languages as $language):
                        $is_default_cmod = $language['is_default'] == 1;
                        $code_cmod = $language['code'];
                        ?>
                        <div class="col-md-6" id="cat-modal-name-div-<?= $code_cmod ?>" <?= $is_default_cmod ? '' : 'style="display:none;"' ?>>
                            <div class="form-group">
                                <label for="cat_modal_name_<?= $code_cmod ?>" <?= $is_default_cmod ? 'class="required"' : '' ?>><?= labels('name', 'Name') . ($is_default_cmod ? '' : ' (' . $code_cmod . ')') ?></label>
                                <input id="cat_modal_name_<?= $code_cmod ?>" class="form-control" type="text"
                                    placeholder="<?= labels('enter_name_of_category', 'Enter the name of the Category here') ?>"
                                    <?= $is_default_cmod ? 'data-slug-source data-slug-target="#cat_modal_slug"' : '' ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cat_modal_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                            <input id="cat_modal_slug" class="form-control" type="text"
                                placeholder="<?= labels('enter_category_slug_here', 'Enter the slug of the Category here') ?>">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label for="cat_modal_type" class="required"><?= labels('type', 'Type') ?></label>
                            <select id="cat_modal_type" class="form-control">
                                <option value="0"><?= labels('category', 'Category') ?></option>
                                <option value="1"><?= labels('sub_category', 'Sub Category') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="col-12" id="cat_modal_parent_row" style="display:none;">
                        <div class="form-group">
                            <label for="cat_modal_parent_id"
                                class="required"><?= labels('select_parent_category', 'Select Parent Category') ?></label>
                            <?php if (empty($categories_name)): ?>
                                <div id="no_parent_category_notice" class="d-flex align-items-center p-2 border rounded">
                                    <span
                                        class="text-muted small mr-2"><?= labels('no_categories_available', 'No categories available.') ?></span>
                                </div>
                                <select id="cat_modal_parent_id" class="form-control" style="display:none;">
                                    <option value=""><?= labels('select_parent_category', 'Select Parent Category') ?>
                                    </option>
                                </select>
                            <?php else: ?>
                                <select id="cat_modal_parent_id" class="form-control select2">
                                    <option value=""><?= labels('select_parent_category', 'Select Parent Category') ?>
                                    </option>
                                    <?php echo render_categories_options($categories_name, 0, 0); ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cat_modal_image" class="required"><?= labels('image', 'Image') ?></label>
                            <small>(<?= labels('category_image_recommended_size', 'We recommend 60x60 pixels') ?>)</small>
                            <input type="file" id="cat_modal_image" class="form-control-file filepond mt-1"
                                accept="image/*">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="cat_modal_dark_color"
                                class="required"><?= labels('dark_theme_color', 'Dark Theme Color') ?></label>
                            <input type="color" id="cat_modal_dark_color" class="form-control" value="#000000">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="cat_modal_light_color"
                                class="required"><?= labels('light_theme_color', 'Light Theme Color') ?></label>
                            <input type="color" id="cat_modal_light_color" class="form-control" value="#FFFFFF">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                <button type="button" class="btn btn-primary" id="save_category_service_btn">
                    <span class="save-cat-text"><?= labels('add_category', 'Add Category') ?></span>
                    <span class="save-cat-spinner d-none"><i class="fas fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Quick-add Tax + Category modal logic
============================================================ -->
<script>
    (function () {
        var defaultLang = <?= json_encode($default_language) ?>;

        // ── Tax modal ────────────────────────────────────────────
        var taxModalLang = defaultLang;

        $(document).on('click', '.language-option-tax-modal', function () {
            var lang = $(this).data('language');
            $('.language-underline-tax-modal').css('width', '0%');
            $('#tax-modal-lang-' + lang).find('.language-underline-tax-modal').css('width', '100%');
            $('.language-text-tax-modal').removeClass('text-primary fw-medium').addClass('text-muted');
            $('#tax-modal-lang-' + lang).find('.language-text-tax-modal').removeClass('text-muted').addClass('text-primary fw-medium');
            $('[id^="tax-modal-title-div-"]').hide();
            $('#tax-modal-title-div-' + lang).show();
            taxModalLang = lang;
        });

        $('#add_tax_modal_service').on('hidden.bs.modal', function () {
            $('[id^="tax-modal-title-div-"] input').val('');
            $('#tax_modal_percentage').val('');
            $('#tax_modal_status').prop('checked', true);
            $('[id^="tax-modal-title-div-"]').hide();
            $('#tax-modal-title-div-' + defaultLang).show();
            $('.language-underline-tax-modal').css('width', '0%');
            $('#tax-modal-lang-' + defaultLang).find('.language-underline-tax-modal').css('width', '100%');
            $('.language-text-tax-modal').removeClass('text-primary fw-medium').addClass('text-muted');
            $('#tax-modal-lang-' + defaultLang).find('.language-text-tax-modal').removeClass('text-muted').addClass('text-primary fw-medium');
            taxModalLang = defaultLang;
        });

        $('#save_tax_service_btn').on('click', function () {
            var defaultTitle = $('#tax_modal_title_' + defaultLang).val().trim();
            var percentage = $('#tax_modal_percentage').val().trim();

            if (!defaultTitle) {
                if (typeof showToastMessage === 'function') showToastMessage(window.stepperLabels.validation_required.replace('{field}', '<?= labels('title', 'Title') ?>'), 'error');
                return;
            }
            if (percentage === '') {
                if (typeof showToastMessage === 'function') showToastMessage(window.stepperLabels.validation_required.replace('{field}', '<?= labels('percentage', 'Percentage') ?>'), 'error');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);
            $('.save-tax-text').addClass('d-none');
            $('.save-tax-spinner').removeClass('d-none');

            var formData = new FormData();
            <?php foreach ($sorted_languages as $language): ?>
                formData.append('title[<?= $language['code'] ?>]', $('#tax_modal_title_<?= $language['code'] ?>').val() || '');
            <?php endforeach; ?>
            formData.append('percentage', percentage);
            formData.append('tax_status', $('#tax_modal_status').is(':checked') ? '1' : '0');
            formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            $.ajax({
                url: '<?= base_url('admin/tax/add_tax') ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.error === false) {
                        if (typeof showToastMessage === 'function') showToastMessage(response.message, 'success');
                        $('#add_tax_modal_service').modal('hide');
                        refreshTaxSelect();
                    } else {
                        if (typeof showToastMessage === 'function') showToastMessage(response.message || '<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
                    }
                },
                error: function () {
                    if (typeof showToastMessage === 'function') showToastMessage('<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $('.save-tax-text').removeClass('d-none');
                    $('.save-tax-spinner').addClass('d-none');
                }
            });
        });

        function refreshTaxSelect() {
            $.ajax({
                url: '<?= base_url('admin/tax/list') ?>',
                type: 'GET',
                data: { limit: 1000, offset: 0, sort: 'id', order: 'desc', search: '' },
                dataType: 'json',
                success: function (response) {
                    var rows = response.rows || [];
                    var $select = $('#tax');
                    $select.empty().append('<option value=""><?= labels('select_tax', 'Select Tax') ?></option>');
                    rows.forEach(function (row) {
                        $select.append('<option value="' + row.id + '">' + row.title + '(' + row.percentage + '%)</option>');
                    });
                    if (rows.length > 0) {
                        $select.show();
                        $('#no_tax_notice').hide();
                        if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
                        $select.select2({ width: '100%' });
                        if (rows[0] && rows[0].id) {
                            $select.val(rows[0].id).trigger('change');
                        }
                    } else {
                        $select.hide();
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.next('.select2-container').hide();
                        }
                        $('#no_tax_notice').show();
                    }
                }
            });
        }

        // ── Category modal ───────────────────────────────────────
        var catModalLang = defaultLang;

        $(document).on('click', '.language-option-cat-modal', function () {
            var lang = $(this).data('language');
            $('.language-underline-cat-modal').css('width', '0%');
            $('#cat-modal-lang-' + lang).find('.language-underline-cat-modal').css('width', '100%');
            $('.language-text-cat-modal').removeClass('text-primary fw-medium').addClass('text-muted');
            $('#cat-modal-lang-' + lang).find('.language-text-cat-modal').removeClass('text-muted').addClass('text-primary fw-medium');
            $('[id^="cat-modal-name-div-"]').hide();
            $('#cat-modal-name-div-' + lang).show();
            catModalLang = lang;
        });

        $('#cat_modal_type').on('change', function () {
            $(this).val() === '1' ? $('#cat_modal_parent_row').show() : $('#cat_modal_parent_row').hide();
        });

        $('#add_category_modal_service').on('hidden.bs.modal', function () {
            $('[id^="cat-modal-name-div-"] input').val('');
            $('#cat_modal_slug').val('');
            $('#cat_modal_type').val('0');
            $('#cat_modal_parent_row').hide();
            var catImagePond = typeof FilePond !== 'undefined' ? FilePond.find(document.getElementById('cat_modal_image')) : null;
            if (catImagePond) { catImagePond.removeFiles(); } else { $('#cat_modal_image').val(''); }
            $('#cat_modal_dark_color').val('#000000');
            $('#cat_modal_light_color').val('#FFFFFF');
            $('[id^="cat-modal-name-div-"]').hide();
            $('#cat-modal-name-div-' + defaultLang).show();
            $('.language-underline-cat-modal').css('width', '0%');
            $('#cat-modal-lang-' + defaultLang).find('.language-underline-cat-modal').css('width', '100%');
            $('.language-text-cat-modal').removeClass('text-primary fw-medium').addClass('text-muted');
            $('#cat-modal-lang-' + defaultLang).find('.language-text-cat-modal').removeClass('text-muted').addClass('text-primary fw-medium');
            catModalLang = defaultLang;
        });

        $('#save_category_service_btn').on('click', function () {
            var defaultName = $('#cat_modal_name_' + defaultLang).val().trim();
            var slug = $('#cat_modal_slug').val().trim();

            var imageInput = $('#cat_modal_image')[0];
            var image = (imageInput && imageInput.files && imageInput.files.length > 0) ? imageInput.files[0] : null;

            // If using FilePond, try to get the file from FilePond
            if (!image && typeof FilePond !== 'undefined' && imageInput) {
                var pond = FilePond.find(imageInput);
                if (pond && pond.getFiles().length > 0) {
                    image = pond.getFiles()[0].file;
                }
            }

            var catType = $('#cat_modal_type').val();
            var parentId = $('#cat_modal_parent_id').val();

            if (!defaultName) {
                if (typeof showToastMessage === 'function') showToastMessage(window.stepperLabels.validation_required.replace('{field}', '<?= labels('name', 'Name') ?>'), 'error');
                return;
            }
            if (!slug) {
                if (typeof showToastMessage === 'function') showToastMessage(window.stepperLabels.validation_required.replace('{field}', '<?= labels('slug', 'Slug') ?>'), 'error');
                return;
            }
            if (!image) {
                if (typeof showToastMessage === 'function') showToastMessage(window.stepperLabels.validation_required.replace('{field}', '<?= labels('image', 'Image') ?>'), 'error');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);
            $('.save-cat-text').addClass('d-none');
            $('.save-cat-spinner').removeClass('d-none');

            var formData = new FormData();
            <?php foreach ($sorted_languages as $language): ?>
                formData.append('name[<?= $language['code'] ?>]', $('#cat_modal_name_<?= $language['code'] ?>').val() || '');
            <?php endforeach; ?>
            formData.append('category_slug', slug);
            formData.append('make_parent', catType);
            if (catType === '1') formData.append('parent_id', parentId);
            formData.append('image', image);
            formData.append('dark_theme_color', $('#cat_modal_dark_color').val());
            formData.append('light_theme_color', $('#cat_modal_light_color').val());
            formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            $.ajax({
                url: '<?= base_url('admin/category/add_category') ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.error === false) {
                        if (typeof showToastMessage === 'function') showToastMessage(response.message, 'success');
                        $('#add_category_modal_service').modal('hide');
                        refreshCategorySelect(response.data && response.data.category_id ? response.data.category_id : null);
                    } else {
                        if (typeof showToastMessage === 'function') showToastMessage(response.message || '<?= labels('error_occurred', 'An error ocurred') ?>', 'error');
                    }
                },
                error: function () {
                    if (typeof showToastMessage === 'function') showToastMessage('<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $('.save-cat-text').removeClass('d-none');
                    $('.save-cat-spinner').addClass('d-none');
                }
            });
        });

        function buildCategoryOptions(categories, parentId, depth, selectedId) {
            var html = '';
            categories.forEach(function (cat) {
                if (cat.parent_id == parentId) {
                    var indent = '    '.repeat(depth);
                    var prefix = depth > 0 ? '— ' : '';
                    var name = indent + prefix + $('<div>').text(cat.name).html();
                    var selected = (selectedId && cat.id == selectedId) ? ' selected' : '';
                    html += '<option value="' + cat.id + '"' + selected + '>' + name + '</option>';
                    html += buildCategoryOptions(categories, cat.id, depth + 1, selectedId);
                }
            });
            return html;
        }

        function refreshCategorySelect(newCategoryId) {
            $.ajax({
                url: '<?= base_url('admin/categories/get_all_for_select') ?>',
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    var categories = Array.isArray(response) ? response : Object.values(response || {});
                    var $select = $('#category_item');
                    $select.empty().append('<option value=""><?= labels('select', 'Select') ?> <?= labels('category', 'Category') ?></option>');
                    $select.append(buildCategoryOptions(categories, 0, 0, newCategoryId || null));

                    var $parentSelect = $('#cat_modal_parent_id');
                    $parentSelect.empty().append('<option value=""><?= labels('select_parent_category', 'Select Parent Category') ?></option>');
                    $parentSelect.append(buildCategoryOptions(categories, 0, 0, null));

                    if (categories.length > 0) {
                        $select.show();
                        $('#no_category_notice').hide();
                        if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
                        $select.select2({
                            width: '100%',
                            templateSelection: function (data) {
                                if (!data.id) return data.text;
                                return (typeof cleanCategoryName === 'function') ? cleanCategoryName(data.text) : data.text;
                            }
                        });
                        if (newCategoryId) {
                            $select.val(newCategoryId).trigger('change');
                        }

                        $parentSelect.show();
                        $('#no_parent_category_notice').hide();
                        if ($parentSelect.hasClass('select2-hidden-accessible')) $parentSelect.select2('destroy');
                        $parentSelect.select2({
                            width: '100%',
                            templateSelection: function (data) {
                                if (!data.id) return data.text;
                                return (typeof cleanCategoryName === 'function') ? cleanCategoryName(data.text) : data.text;
                            }
                        });
                    } else {
                        $select.hide();
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.next('.select2-container').hide();
                        }
                        $('#no_category_notice').show();

                        $parentSelect.hide();
                        if ($parentSelect.hasClass('select2-hidden-accessible')) {
                            $parentSelect.next('.select2-container').hide();
                        }
                        $('#no_parent_category_notice').show();
                    }
                }
            });
        }
    })();
</script>