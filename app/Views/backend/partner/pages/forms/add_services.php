<?= helper('form'); ?>
<?php
$db      = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 3)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$partner = fetch_details('partner_details', ["partner_id" => $user1[0]['id']],);
$at_store = ($partner[0]['at_store']);
$at_doorstep = ($partner[0]['at_doorstep']);
?>
<div class="main-content">
    <div class="section">
        <div class="section-header mt-2">
            <h1><?= labels('add_service', 'Add Service') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('/partner/services') ?>"><?= labels('service', "Service") ?></a></div>
            </div>
        </div>
        <?= form_open('/partner/services/add_service', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_service', 'enctype' => "multipart/form-data"]); ?>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card h-100">
                    <div class="row  border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('add_service_details', 'Add Service Details') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 ">
                            <div class="form-group">
                                <label class="required"><?= labels('status', 'Status') ?></label>
                                <input type="checkbox" id="status" name="status" class="status-switch" checked>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    // Sort languages so default language appears first for better UI
                                    $sorted_languages = sort_languages_with_default_first($languages);
                                    foreach ($sorted_languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $default_language = $language['code'];
                                        }
                                    ?>
                                        <div class="language-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span class="language-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Use sorted languages for content divs as well
                        foreach ($sorted_languages as $index => $language) {
                        ?>
                            <div id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $default_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="service_title<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('title_of_the_service', 'Title of the service') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?> </label>
                                            <input class="form-control" type="text" name="title[<?= $language['code'] ?>]" id="service_title<?= $language['code'] ?>" <?= $language['is_default'] ? 'data-slug-source data-slug-target="#service_slug"' : '' ?>>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tags<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('tags', 'Tags') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content=" <?= labels('data_content_for_tags', 'These tags will help find the services while users search for the services.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="tags<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100 translation-tags" type="text" name="tags[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_tag', 'press enter to add tag') ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="description<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('short_description', "Short Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <textarea id="description<?= $language['code'] ?>" rows=4 class='form-control' style="min-height:60px" name="description[<?= $language['code'] ?>]"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <label for="long_description<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <textarea id="long_description<?= $language['code'] ?>" srows=10 class='form-control h-50 summernotes custome_reset' name="long_description[<?= $language['code'] ?>]"><?= isset($short_description) ? $short_description : '' ?></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="row pl-3">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('other_service_details', 'Other Service Details') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="service_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                    <input id="service_slug" class="form-control" type="text" name="service_slug" placeholder="<?= labels('enter_the_slug', 'Enter the slug') ?> ">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="categories form-group" id="categories">
                                    <label for="category_item" class="required"><?= labels('choose_a_category_for_your_service', 'Choose a Category for your service') ?></label>
                                    <select id="category_item" class="form-control select2" name="categories" style="margin-bottom: 20px;">
                                        <option value=""><?= labels('select_category', 'Select Category') ?></option>
                                        <?php
                                        $selected_category_id = isset($service['category_id']) ? $service['category_id'] : null;
                                        echo render_categories_options($categories, 0, 0, $selected_category_id);
                                        ?>
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card h-100 ">
                    <div class="row pl-3">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('perform_task', 'Perform Task') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="duration" class="required"><?= labels('duration_to_perform_task', 'Duration to Perform Task') ?></label>
                                    <i data-content="  <?= labels('data_content_for_duration_perform_task', 'The duration will be used to figure out how long the service will take and to determine available timeslots when the customer book their services.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text myDivClass" style="height: 42px;">
                                                <span class="mySpanClass"><?= labels('minutes', 'Minutes') ?></span>
                                            </div>
                                        </div>
                                        <input type="number" style="height: 42px;" class="form-control" name="duration" id="duration" placeholder="<?= labels('duration_of_the_service', 'Duration of the Service') ?>" min="0" oninput="this.value = Math.abs(this.value)">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="members" class="required"><?= labels('members_required_to_perform_task', 'Members required to perform Tasks') ?></label>
                                    <i data-content=" <?= labels('data_content_for_member_required', 'We\'re just collecting the number of team members who will be doing the service. This helps us show customers how many people will be working on their service.') ?> " class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input id="members" class="form-control" type="number" name="members" placeholder="<?= labels('members_required', 'Members Required') ?>" min="0" oninput="this.value = Math.abs(this.value)">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="max_qty" class="required"> <?= labels('max_quantity_allowed', 'Max Quantity allowed for services') ?></label>
                                    <i data-content=" <?= labels('data_content_for_max_quality_allowed', 'Users can add up to a maximum of X quantity of a specific service when adding services to the cart.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input id="max_qty" class="form-control" type="number" name="max_qty" placeholder="<?= labels('max_quantity_allowed', 'Max Quantity allowed for services') ?>" min="0" oninput="this.value = Math.abs(this.value)">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="card card h-100 ">
                    <div class="row pl-3">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('files', 'Files') ?>
                                <i data-content="<?= labels('data_content_for_files', 'You can add images, other images, or any files like brochures or PDFs so users can see more details about the service.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                            </div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group"> <label for="image" class="required"><?= labels('image', 'Image') ?></label> <small>(<?= labels('service_image_recommended_size', 'We recommend 424 x 551 pixels') ?>)</small>
                                    <input type="file" required name="image" class="filepond logo" id="service_image_selector" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group"> <label for="image"><?= labels('other_images', 'Other Image') ?></label> <small>(<?= labels('other_image_recommended_size', 'We recommend 960 x 540 pixels') ?>)</small>
                                    <input type="file" name="other_service_image_selector[]" class="filepond logo" id="other_service_image_selector" accept="image/*" multiple>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="image"><?= labels('files', 'Files') ?></label>
                                    <input type="file" name="files[]" class="filepond-docs" id="files" multiple>
                                </div>
                            </div>


                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="row pl-3">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('price_details', 'Price Details') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tax_type" class="required"><?= labels('tax_type', 'Tax Type') ?></label>
                                    <select name="tax_type" id="tax_type" class="form-control">
                                        <option value="excluded"><?= labels('tax_excluded_in_price', 'Tax Excluded In Price') ?></option>
                                        <option value="included"><?= labels('tax_included_in_price', 'Tax Included In Price') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="jquery-script-clear"></div>
                                <div class="" id="">
                                    <label for="partner" class="required"><?= labels('select_tax', 'Select Tax') ?></label> <br>
                                    <select id="tax" name="tax_id" class="form-control w-100" name="tax">
                                        <option value=""><?= labels('select_tax', 'Select Tax') ?></option>
                                        <?php foreach ($tax_data as $pn) : ?>
                                            <option value="<?= $pn['id'] ?>"><?= $pn['title'] ?>(<?= $pn['percentage'] ?>%)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="price" class="required"><?= labels('price', 'Price') ?></label>
                                    <input id="price" class="form-control" type="number" name="price" placeholder="<?= labels('price', 'Price') ?>" min="0" oninput="this.value = Math.abs(this.value)">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="discounted_price" class="required"><?= labels('discounted_price', 'Discounted Price') ?></label>
                                    <input id="discounted_price" class="form-control" type="number" name="discounted_price" placeholder="<?= labels('discounted_price', 'Discounted Price') ?>" min="0" oninput="this.value = Math.abs(this.value)">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card h-100">
                    <div class="row pl-3">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('Faqs', 'Faqs') ?>
                                <i data-content=" <?= labels('data_content_for_faqs', 'You can include some general questions and answers to help users understand the service better. This will make it clearer for everyone.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                            </div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Language tabs for FAQs -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    // Sort languages so default language appears first for better UI
                                    $sorted_languages = sort_languages_with_default_first($languages);
                                    foreach ($sorted_languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $default_language_faqs = $language['code'];
                                        }
                                    ?>
                                        <div class="language-option-faqs position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-faqs-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span class="language-text-faqs px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-underline-faqs"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ content for each language -->
                        <?php foreach ($sorted_languages as $index => $language) { ?>
                            <div class="row" id="translationFaqsDiv-<?= $language['code'] ?>" <?= $language['code'] == $default_language_faqs ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="col-md-12">
                                    <div class="faq-container" data-language="<?= $language['code'] ?>">
                                        <!-- FAQ items will be dynamically added here -->
                                        <div class="faq-items-wrapper">
                                            <!-- Initial empty FAQ item -->
                                            <div class="faq-item row mb-3" data-faq-index="0">
                                                <div class="col-md-5">
                                                    <div class="form-group">
                                                        <label for="faq_question_<?= $language['code'] ?>_0"><?= labels('question', 'Question') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                        <input type="text"
                                                            class="form-control faq-question"
                                                            name="faq_question_<?= $language['code'] ?>_0"
                                                            placeholder="<?= labels('enter_question', 'Enter the question here') ?>" />
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="form-group">
                                                        <label for="faq_answer_<?= $language['code'] ?>_0"><?= labels('answer', 'Answer') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                        <div class="d-flex align-items-center">
                                                            <input type="text"
                                                                class="form-control faq-answer"
                                                                name="faq_answer_<?= $language['code'] ?>_0"
                                                                placeholder="<?= labels('enter_answer', 'Enter answer') ?>" />
                                                            <button type="button" class="btn btn-danger remove-faq-btn ml-2" style="display: none;">
                                                                <i class="fas fa-minus"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Add FAQ button -->
                                        <div class="row">
                                            <div class="col-md-12">
                                                <button type="button" class="btn btn-primary add-faq-btn" data-language="<?= $language['code'] ?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card h-100">
                    <div class="row m-0">
                        <div class="col mb-3 " style="border-bottom: solid 1px #e5e6e9;">
                            <div class="toggleButttonPostition"><?= labels('seo_settings', 'SEO Settings') ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Language tabs for SEO settings -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    // Sort languages so default language appears first for better UI
                                    $sorted_languages = sort_languages_with_default_first($languages);
                                    foreach ($sorted_languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $default_language_seo = $language['code'];
                                        }
                                    ?>
                                        <div class="language-option-seo position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-seo-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span class="language-text-seo px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-underline-seo"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <!-- SEO content for each language -->
                        <?php foreach ($sorted_languages as $index => $language) { ?>
                            <div id="translationDivSeo-<?= $language['code'] ?>" <?= $language['code'] == $default_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="meta_title<?= $language['code'] ?>" class="form-control" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255">
                                            <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100 seo-meta-keywords" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <textarea id="meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500"></textarea>
                                            <small class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="schema_markup<?= $language['code'] ?>"><?= labels('schema_markup', 'Schema Markup') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                                data-toggle="popover"
                                                class="fa fa-question-circle"
                                                data-original-title=""
                                                title=""></i>
                                            <textarea id="schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- Meta Image (shared across all languages) -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                                    <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i> <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                    <input type="file" class="filepond" name="meta_image" id="meta_image" accept="image/*">
                                    <small class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card h-100">
                    <div class="row pl-3">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('service_option', 'Service Options') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="" class="required" for="is_cancelable"><?= labels('is_cancelable_?', 'Is Cancelable ')  ?></label>
                                <i data-content="<?= labels('data_content_for_is_cancellable', 'Can customers cancel their booking if they\'ve already booked this service?') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                <input type="checkbox" id="is_cancelable" name="is_cancelable" class="status-switch">
                            </div>
                            <div class="col-md-4">
                                <label class="" for="pay_later" class="required"><?= labels('pay_later_allowed', 'Pay Later Allowed') ?></label>
                                <i data-content="<?= labels('data_content_for_paylater_allowed', 'If this option is enabled, customers can book the service and pay after the booking is completed. Generally, this is known as the Cash On Delivery option.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                <input type="checkbox" id="pay_later" name="pay_later" class="status-switch">
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="required"><?= labels('status', 'Status') ?></label>
                                    <input type="checkbox" id="status" name="status" class="status-switch">
                                </div>
                            </div>
                        </div>
                        <div class="row" id="cancel_order">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="required" for="cancelable_till"><?= labels('cancelable_before', 'Cancelable before') ?></label>
                                    <i data-content="<?= labels('data_content_for_cancellable_before', 'If customer can cancel the service, they can cancel their booking X minutes before it starts. For example, if their booking is at 11:00 AM, they can cancel it up to X minutes before 11:00 AM.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text myDivClass" style="height: 42px;">
                                                <span class="mySpanClass"><?= labels('minutes', 'Minutes') ?></span>
                                            </div>
                                        </div>
                                        <input type="number" style="height: 42px;" class="form-control" name="cancelable_till" id="cancelable_till" placeholder="Ex. 30" min="0" value="">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <?php
                            if (isset($at_store) && $at_store == 1) {
                                echo '<div class="col-md-4">
                                            <div class="form-group">
                                                <label class="" for="at_store">' . labels('at_store', 'At Store') . '</label>
                                                <input type="checkbox" id="at_store" name="at_store" class="status-switch">
                                            </div>
                                        </div>';
                            }
                            if (isset($at_doorstep) && $at_doorstep == 1) {
                                echo '<div class="col-md-4">
                                            <div class="form-group">
                                                <label class="" for="at_doorstep">' . labels('at_doorstep', 'At Doorstep') . '</label>
                                                <input type="checkbox" id="at_doorstep" name="at_doorstep" class="status-switch">
                                            </div>
                                        </div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md d-flex justify-content-end">
                <button type="submit" class="btn btn-lg bg-new-primary"><?= labels('add_service', 'Add Service') ?></button>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize switch states based on their actual checked status
        // Only set deactive-content for unchecked switches
        if (!$('#is_cancelable').is(':checked')) {
            $('#is_cancelable').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        } else {
            $('#is_cancelable').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        }

        if (!$('#pay_later').is(':checked')) {
            $('#pay_later').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        } else {
            $('#pay_later').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        }

        if (!$('#status').is(':checked')) {
            $('#status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        } else {
            $('#status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        }

        if (!$('#at_store').is(':checked')) {
            $('#at_store').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        } else {
            $('#at_store').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        }

        if (!$('#at_doorstep').is(':checked')) {
            $('#at_doorstep').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        } else {
            $('#at_doorstep').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        }
        var is_cancelable = document.querySelector('#is_cancelable');
        is_cancelable.onchange = function(e) {
            if (is_cancelable.checked) {
                $(this).siblings('.switchery').addClass('active-content').removeClass('deactive-content');
            } else {
                $(this).siblings('.switchery').addClass('deactive-content').removeClass('active-content');
            }
        };
        var pay_later = document.querySelector('#pay_later');
        pay_later.onchange = function(e) {
            if (pay_later.checked) {
                $(this).siblings('.switchery').addClass('active-content').removeClass('deactive-content');
            } else {
                $(this).siblings('.switchery').addClass('deactive-content').removeClass('active-content');
            }
        };
        var status = document.querySelector('#status');
        status.onchange = function(e) {

            if (status.checked) {
                $(this).siblings('.switchery').addClass('active-content').removeClass('deactive-content');
            } else {
                $(this).siblings('.switchery').addClass('deactive-content').removeClass('active-content');
            }
        };
        var at_store = document.querySelector('#at_store');
        at_store.onchange = function(e) {
            if (at_store.checked) {
                $(this).siblings('.switchery').addClass('active-content').removeClass('deactive-content');
            } else {
                $(this).siblings('.switchery').addClass('deactive-content').removeClass('active-content');
            }
        };
        var at_doorstep = document.querySelector('#at_doorstep');
        at_doorstep.onchange = function(e) {
            if (at_doorstep.checked) {
                $(this).siblings('.switchery').addClass('active-content').removeClass('deactive-content');
            } else {
                $(this).siblings('.switchery').addClass('deactive-content').removeClass('active-content');
            }
        };
    });

    function test() {
        var tax = document.getElementById("edit_tax").value;
        document.getElementById("update_service").reset();
        document.getElementById("edit_tax").value = tax;
        document.getElementById('edit_service_image').removeAttribute('src');
    }
    $('#is_cancelable').on('change', function() {
        if (this.checked) {
            $("#cancel_order").show()
        } else {
            $('#cancel_order').hide();
        }
    }).change();
</script>

<script>
    // select default language
    $(document).ready(function() {
        let current_language = '<?= $default_language ?>';
        let current_language_faqs = '<?= $default_language_faqs ?>';
        let current_language_seo = '<?= $default_language_seo ?>';

        // Service title/description language switching
        $(document).on('click', '.language-option', function() {
            const language = $(this).data('language');

            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            if (language != current_language) {
                $('#translationDiv-' + language).show();
                $('#translationDiv-' + current_language).hide();
            }

            current_language = language;
        });

        // SEO language tab switching
        $(document).on('click', '.language-option-seo', function() {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-underline-seo').css('width', '0%');
            $('#language-seo-' + language).find('.language-underline-seo').css('width', '100%');

            // Update text styles
            $('.language-text-seo').removeClass('text-primary fw-medium');
            $('.language-text-seo').addClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').removeClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').addClass('text-primary fw-medium');

            // Show/hide SEO content divs
            if (language != current_language_seo) {
                $('#translationDivSeo-' + language).show();
                $('#translationDivSeo-' + current_language_seo).hide();
            }

            current_language_seo = language;
        });

        // Initialize Tagify for multilingual SEO meta keywords fields
        var seoMetaKeywordsInputs = document.querySelectorAll('.seo-meta-keywords');
        seoMetaKeywordsInputs.forEach(function(input) {
            if (input != null) {
                new Tagify(input);
            }
        });
    });


    // for translation tags
    $(document).ready(function() {
        var input_tags = document.querySelectorAll('.translation-tags');

        // initialize Tagify on the above input node reference
        input_tags.forEach(function(input_tag) {
            if (input_tag != null) {
                new Tagify(input_tag);
            }
        });
    });
</script>

<script>
    /**
     * FAQ Management System - Clean Language-Grouped Structure
     * 
     * This class handles the new clean FAQ data structure that groups FAQs by language
     * and ensures proper data formatting before form submission.
     * 
     * Data Structure: {"en": [["q1", "a1"], ["q2", "a2"]], "hi": [["q1", "a1"]]}
     */
    class FAQManager {
        constructor() {
            this.currentLanguage = '<?= $default_language_faqs ?>';
            this.faqCounters = {};
            this.initializeFAQSystem();
        }

        initializeFAQSystem() {
            // Initialize counters for each language
            <?php foreach ($sorted_languages as $language) { ?>
                this.faqCounters['<?= $language['code'] ?>'] = 0;
            <?php } ?>

            this.bindEvents();
            this.updateRemoveButtons();
        }

        bindEvents() {
            // Language tab switching
            $(document).on('click', '.language-option-faqs', this.switchLanguage.bind(this));

            // Add FAQ button
            $(document).on('click', '.add-faq-btn', this.addFAQ.bind(this));

            // Remove FAQ button
            $(document).on('click', '.remove-faq-btn', this.removeFAQ.bind(this));

            // Form submission - restructure FAQ data
            $('#add_service').on('submit', this.restructureFAQData.bind(this));
        }

        switchLanguage(event) {
            const language = $(event.currentTarget).data('language');

            // Update visual indicators
            $('.language-underline-faqs').css('width', '0%');
            $('#language-faqs-' + language).find('.language-underline-faqs').css('width', '100%');

            $('.language-text-faqs').removeClass('text-primary fw-medium');
            $('.language-text-faqs').addClass('text-muted');
            $('#language-faqs-' + language).find('.language-text-faqs').removeClass('text-muted');
            $('#language-faqs-' + language).find('.language-text-faqs').addClass('text-primary');

            // Show/hide FAQ sections
            if (language !== this.currentLanguage) {
                $('#translationFaqsDiv-' + language).show();
                $('#translationFaqsDiv-' + this.currentLanguage).hide();
            }

            this.currentLanguage = language;
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
                            <input type="text" 
                                   class="form-control faq-question" 
                                   name="faq_question_${language}_${faqIndex}" 
                                   placeholder="<?= labels('enter_question', 'Enter the question here') ?>" />
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="faq_answer_${language}_${faqIndex}"><?= labels('answer', 'Answer') ?> (${language})</label>
                            <div class="d-flex align-items-center">
                                <input type="text" 
                                       class="form-control faq-answer" 
                                       name="faq_answer_${language}_${faqIndex}" 
                                       placeholder="<?= labels('enter_answer', 'Enter the answer here') ?>" />
                                <button type="button" class="btn btn-danger remove-faq-btn ml-2">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $(event.currentTarget).closest('.faq-container').find('.faq-items-wrapper').append(faqHTML);
            this.updateRemoveButtons();
        }

        removeFAQ(event) {
            $(event.currentTarget).closest('.faq-item').remove();
            this.updateRemoveButtons();
        }

        updateRemoveButtons() {
            // Show remove button only if there's more than one FAQ item
            $('.faq-container').each((index, container) => {
                const faqItems = $(container).find('.faq-item');
                const removeButtons = $(container).find('.remove-faq-btn');

                if (faqItems.length > 1) {
                    removeButtons.show();
                } else {
                    removeButtons.hide();
                }
            });
        }

        /**
         * Collect FAQ data from all language containers and format it into the clean structure
         * 
         * @returns {Object} FAQ data in format: {"en": [["q1", "a1"]], "hi": [["q1", "a1"]]}
         */
        collectFAQData() {
            const faqData = {};

            // Initialize empty arrays for all languages
            <?php foreach ($sorted_languages as $language) { ?>
                faqData['<?= $language['code'] ?>'] = [];
            <?php } ?>

            // Collect FAQ data from each language container
            $('.faq-container').each((index, container) => {
                const language = $(container).data('language');
                const languageFaqs = [];

                // Process each FAQ item in this language
                $(container).find('.faq-item').each((faqIndex, faqItem) => {
                    const question = $(faqItem).find('.faq-question').val().trim();
                    const answer = $(faqItem).find('.faq-answer').val().trim();

                    // Only add FAQ if either question or answer is not empty
                    if (question || answer) {
                        languageFaqs.push([question, answer]);
                    }
                });

                faqData[language] = languageFaqs;
            });

            return faqData;
        }

        /**
         * Restructure FAQ data before form submission
         * Creates a hidden input with clean JSON data structure
         * 
         * @param {Event} event Form submission event
         */
        restructureFAQData(event) {
            // Collect FAQ data in clean format
            const faqData = this.collectFAQData();

            // Create hidden input for FAQ data
            let faqInput = $('#faq_data_input');
            if (faqInput.length === 0) {
                faqInput = $('<input type="hidden" name="faqs" id="faq_data_input" />');
                $('#add_service').append(faqInput);
            }

            // Set the FAQ data as JSON
            faqInput.val(JSON.stringify(faqData));

            console.log('FAQ Data being submitted:', faqData);
        }
    }

    // Initialize FAQ Manager when document is ready
    $(document).ready(function() {
        window.faqManager = new FAQManager();
    });
</script>

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

</script>