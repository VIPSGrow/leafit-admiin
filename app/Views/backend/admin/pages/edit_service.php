<?php
$check_payment_gateway = get_settings('payment_gateways_settings', true);
$cod_setting =  $check_payment_gateway['cod_setting'];
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('edit_service', "Edit Service") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('services', 'Services') ?></a></div>
            </div>
        </div>
        <?= form_open(
            '/admin/services/update_service',
            ['method' => "post", 'class' => 'update-form', 'id' => 'update_service', 'enctype' => "multipart/form-data"]
        ); ?>
        <input type="hidden" name="service_id" id="service_id" value=<?= $service['id'] ?>>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card h-100">
                    <div class="row  border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('edit_service_details', 'Edit Service Details') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 ">
                            <div class="form-group">
                                <label class="required"><?= labels('status', 'Status') ?></label>
                                <?php
                                if ($service['status'] == "1") { ?>
                                    <input type="checkbox" id="status" name="status" class="status-switch" checked>
                                <?php   } else { ?> <input type="checkbox" id="status" name="status" class="status-switch">
                                <?php  }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
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
                        foreach ($languages as $index => $language) {
                        ?>
                            <div id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $default_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="service_title<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('title_of_the_service', 'Title of the service') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?> </label>
                                            <input class="form-control" type="text" name="title[<?= $language['code'] ?>]" id="service_title<?= $language['code'] ?>" value="<?= isset($service['translated_' . $language['code']]['title']) ? $service['translated_' . $language['code']]['title'] : (isset($service['title']) ? $service['title'] : "") ?>" <?= $language['is_default'] ? 'data-slug-source data-slug-target="#service_slug"' : '' ?>>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tags<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('tags', 'Tags') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content=" <?= labels('data_content_for_tags', 'These tags will help find the services while users search for the services.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="tags<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100 translation-tags" type="text" name="tags[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_tag', 'press enter to add tag') ?>" value="<?= isset($service['translated_' . $language['code']]['tags']) ? $service['translated_' . $language['code']]['tags'] : (isset($service['tags']) ? $service['tags'] : "") ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="description<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('short_description', "Short Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <textarea id="description<?= $language['code'] ?>" rows=4 class='form-control' style="min-height:60px" name="description[<?= $language['code'] ?>]"><?= isset($service['translated_' . $language['code']]['description']) ? $service['translated_' . $language['code']]['description'] : (isset($service['description']) ? $service['description'] : "") ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <label for="long_description<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <textarea id="long_description<?= $language['code'] ?>" srows=10 class='form-control h-50 summernotes custome_reset' name="long_description[<?= $language['code'] ?>]"><?= isset($service['translated_' . $language['code']]['long_description']) ? $service['translated_' . $language['code']]['long_description'] : (isset($service['long_description']) ? $service['long_description'] : '') ?></textarea>
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
                    <div class="row m-0 border_bottom_for_cards">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('edit_service_details', 'Edit Service Details') ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <div class="jquery-script-clear"></div>
                                <div class="categories" id="categories">
                                    <label for="partner" class="required"><?= labels('select_provider', 'Select Provider') ?></label> <br>
                                    <select id="partner" class="form-control w-100 select2" name="partner">
                                        <option value=""><?= labels('select_provider', 'Select Provider') ?></option>
                                        <?php foreach ($partner_name as $pn) : ?>
                                            <option value="<?= $pn['id'] ?>" <?php echo  isset($service['user_id'])  && $service['user_id'] ==  $pn['id'] ? 'selected' : '' ?> data-at_store="<?= $pn['at_store'] ?>" data-at_doorstep="<?= $pn['at_doorstep'] ?>" data-need_approval_for_the_service="<?= $pn['need_approval_for_the_service'] ?>">
                                                <?= $pn['display_company_name'] . ' - ' . $pn['username'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <div class="categories" id="categories">
                                    <label for="category_item" class="required"><?= labels('choose_a_category_for_your_service', 'Choose a Category for your service') ?></label>
                                    <select id="category_item" class="form-control select2" name="categories" style="margin-bottom: 20px;">
                                        <option value=""><?= labels('select', 'Select') ?> <?= labels('category', 'Category') ?></option>
                                        <?php
                                        $selected_category_id = isset($service['category_id']) ? $service['category_id'] : null;
                                        echo render_categories_options($categories_name, 0, 0, $selected_category_id);
                                        ?>
                                    </select>
                                </div>
                            </div>




                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="service_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                    <input id="service_slug" class="form-control" type="text" value="<?= isset($service['slug']) ? $service['slug'] : "" ?>" name="service_slug" placeholder="<?= labels('enter_the_slug', 'Enter the slug') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card h-100 ">
                    <div class="row m-0">
                        <div class="col mb-3 " style="border-bottom: solid 1px #e5e6e9;">
                            <div class="toggleButttonPostition"><?= labels('perform_task', 'Perform Task') ?></div>
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
                                        <input type="number" style="height: 42px;" class="form-control" name="duration" id="duration" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('duration_to_perform_task', 'Duration to Perform service') ?>" value="<?= isset($service['duration']) ? $service['duration'] : "" ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="members" class="required"><?= labels('members_required_to_perform_task', 'Members Required to Perform Task') ?></label>
                                    <i data-content=" <?= labels('data_content_for_member_required', 'We\'re just collecting the number of team members who will be doing the service. This helps us show customers how many people will be working on their service.') ?> " class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input id="members" class="form-control" type="number" name="members" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('members_required_to_perform_task', 'Members Required to Perform Task') ?> <?= labels('here', ' Here ') ?>" min="0" value="<?= isset($service['number_of_members_required']) ? $service['number_of_members_required'] : "" ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="max_qty" class="required"><?= labels('max_quantity_allowed_for_services', 'Max Quantity allowed for services') ?></label>
                                    <i data-content=" <?= labels('data_content_for_max_quality_allowed', 'Users can add up to a maximum of X quantity of a specific service when adding services to the cart.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input id="max_qty" class="form-control" type="number" min="0" oninput="this.value = Math.abs(this.value)" name="max_qty" placeholder="<?= labels('max_quantity_allowed_for_services', 'Max Quantity allowed for services') ?>" value="<?= isset($service['max_quantity_allowed']) ? $service['max_quantity_allowed'] : "" ?>">
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
                    <div class="row m-0">
                        <div class="col mb-3 " style="border-bottom: solid 1px #e5e6e9;">
                            <div class="toggleButttonPostition"><?= labels('files', 'Files') ?>
                                <i data-content="<?= labels('data_content_for_files', 'You can add images, other images, or any files like brochures or PDFs so users can see more details about the service.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group"> <label for="image" class="required"><?= labels('image', 'Image') ?></label> <small>(<?= labels('service_image_recommended_size', 'We recommend 424 x 551 pixels') ?>)</small><br>
                                    <input type="file" name="service_image_selector_edit" class="filepond logo" id="service_image_selector" accept="image/*" onchange="loadServiceImage(event)">
                                    <img alt="no image found" width="130px" style="border: solid 1; border-radius: 12px;" height="100px" class="mt-2" id="image_preview" src="<?= isset($service['image']) ? ($service['image']) : "" ?>">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group"> <label for="image" class=""><?= labels('other_images', 'Other Image') ?></label> <small>(<?= labels('other_image_recommended_size', 'We recommend 960 x 540 pixels') ?>)</small><br>
                                    <input type="file" name="other_service_image_selector_edit[]" class="filepond logo" id="other_service_image_selector" accept="image/*" multiple>
                                    <div class="row mt-2" id="other_images_container">
                                        <?php if (!empty($service['other_images'])) { ?>
                                            <div class="col-12 mb-2">
                                                <button type="button" class="btn btn-primary btn-sm remove-all-other-images"><?= labels('remove_all_images', 'Remove All Images') ?></button>
                                            </div>
                                            <?php
                                            foreach ($service['other_images'] as $index => $image) { ?>
                                                <div class="col-md-3 mb-2 other-image-container">
                                                    <div class="position-relative">

                                                        <img alt="no image found" width="130px" style="border: solid 1; border-radius: 12px;" height="100px" class="mt-2" height="100px" src="<?= isset($image) ? ($image) : "" ?>">
                                                        <input type="hidden" name="existing_other_images[]" value="<?= $image ?>">
                                                        <button type="button" class="btn btn-sm btn-danger remove-other-image" data-image-index="<?= $index ?>" style="position: absolute; top: 5px; right: 5px;"><i class="fas fa-times"></i></button>
                                                        <input type="hidden" name="remove_other_images[<?= $index ?>]" value="0" class="remove-flag">
                                                    </div>
                                                </div>
                                        <?php }
                                        } ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="image"><?= labels('files', 'Files') ?></label>
                                    <input type="file" name="files_edit[]" class="filepond-docs logo" id="files" multiple>
                                    <div class="row mt-2" id="files_container">
                                        <?php if (!empty($service['files'])) { ?>
                                            <div class="col-12 mb-2">
                                                <button type="button" class="btn btn-primary btn-sm remove-all-files"><?= labels('remove_all_files', 'Remove All Files') ?></button>
                                            </div>
                                            <?php
                                            foreach ($service['files'] as $index => $file) { ?>
                                                <div class="col-md-3 mb-2 file-container">
                                                    <div class="position-relative">
                                                        <div class="p-2" style="border-radius: 8px; background-color:#f2f1f6">
                                                            <a href="<?= $file ?>" class="file-link">View uploaded File</a>
                                                        </div>
                                                        <input type="hidden" name="existing_files[]" value="<?= $file ?>">
                                                        <button type="button" class="btn btn-sm btn-danger remove-file" data-file-index="<?= $index ?>" style="position: absolute; top: 5px; right: 5px;"><i class="fas fa-times"></i></button>
                                                        <input type="hidden" name="remove_files[<?= $index ?>]" value="0" class="remove-flag">
                                                    </div>
                                                </div>
                                        <?php }
                                        } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="row m-0">
                        <div class="col mb-3 " style="border-bottom: solid 1px #e5e6e9;">
                            <div class="toggleButttonPostition"><?= labels('price_details', 'Price Details') ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tax_type" class="required"><?= labels('price', 'Price') ?> <?= labels('type', 'Type') ?></label>
                                    <select name="tax_type" id="tax_type" class="form-control">
                                        <option value="excluded" <?php echo  isset($service['tax_type'])  && $service['tax_type'] == "excluded"  ? 'selected' : '' ?>><?= labels('tax_excluded_in_price', 'Tax Excluded In Price') ?></option>
                                        <option value="included" <?php echo  isset($service['tax_type'])  && $service['tax_type'] == "included"  ? 'selected' : '' ?>><?= labels('tax_included_in_price', 'Tax Included In Price') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="jquery-script-clear"></div>
                                <div class="" id="">
                                    <label for="partner" class="required"><?= labels('select_tax', 'Select Tax') ?></label> <br>
                                    <select id="tax" name="tax_id" required class="form-control w-100" name="tax">
                                        <option value=""><?= labels('select_tax', 'Select Tax') ?></option>
                                        <?php foreach ($tax_data as $pn) : ?>
                                            <option value="<?= $pn['id'] ?>" <?php echo  isset($service['tax_id'])  && $service['tax_id'] ==  $pn['id'] ? 'selected' : '' ?>> <?= $pn['title'] ?>(<?= $pn['percentage'] ?>%)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="price" class="required"><?= labels('price', 'Price') ?></label>
                                    <input id="price" class="form-control" type="number" name="price" placeholder="<?= labels('price', 'Price') ?>" min="1" oninput="this.value = Math.abs(this.value)" value="<?= isset($service['price']) ? $service['price'] : "" ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="discounted_price" class="required"><?= labels('discounted_price', 'Discounted Price') ?></label>
                                    <input id="discounted_price" class="form-control" type="number" name="discounted_price" value="<?= isset($service['discounted_price']) ? $service['discounted_price'] : "" ?>" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('discounted_price', 'Discounted Price') ?> <?= labels('here', ' Here ') ?>">
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
                    <div class="row m-0">
                        <div class="col mb-3 " style="border-bottom: solid 1px #e5e6e9;">
                            <div class="toggleButttonPostition"><?= labels('faqs', 'Faqs') ?>
                                <i data-content=" <?= labels('data_content_for_faqs', 'You can include some general questions and answers to help users understand the service better. This will make it clearer for everyone.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
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
                        <?php
                        // Sort languages so default language appears first for better UI
                        $sorted_languages = sort_languages_with_default_first($languages);
                        foreach ($sorted_languages as $index => $language) {
                        ?>
                            <div class="row" id="translationFaqsDiv-<?= $language['code'] ?>" <?= $language['code'] == $default_language_faqs ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="col-md-12">
                                    <div class="faq-container" data-language="<?= $language['code'] ?>">
                                        <!-- FAQ items will be dynamically added here -->
                                        <div class="faq-items-wrapper">
                                            <?php
                                            // Get FAQs for this specific language
                                            $currentFaqs = [];

                                            // First, try to get FAQs from translated data (translations table)
                                            if (isset($service['translated_' . $language['code']]['faqs']) && is_array($service['translated_' . $language['code']]['faqs'])) {
                                                $currentFaqs = $service['translated_' . $language['code']]['faqs'];
                                            } elseif ($language['is_default'] == 1) {
                                                // For default language, fall back to main service FAQs if no translation found
                                                if (isset($service['faqs']) && is_array($service['faqs'])) {
                                                    $currentFaqs = $service['faqs'];
                                                }
                                            }

                                            // Display existing FAQs for this language
                                            if (!empty($currentFaqs)) {
                                                foreach ($currentFaqs as $i => $faq) {
                                            ?>
                                                    <div class="faq-item row mb-3" data-faq-index="<?= $i ?>">
                                                        <div class="col-md-5">
                                                            <div class="form-group">
                                                                <label for="faq_question_<?= $language['code'] ?>_<?= $i ?>"><?= labels('question', 'Question') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                                <input type="text"
                                                                    class="form-control faq-question"
                                                                    name="faq_question_<?= $language['code'] ?>_<?= $i ?>"
                                                                    placeholder="<?= labels('enter_question', 'Enter the question here') ?>"
                                                                    value="<?= htmlspecialchars($faq['question'] ?? '') ?>" />
                                                            </div>
                                                        </div>
                                                        <div class="col-md-5">
                                                            <div class="form-group">
                                                                <label for="faq_answer_<?= $language['code'] ?>_<?= $i ?>"><?= labels('answer', 'Answer') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                                <div class="d-flex align-items-center">
                                                                    <input type="text"
                                                                        class="form-control faq-answer"
                                                                        name="faq_answer_<?= $language['code'] ?>_<?= $i ?>"
                                                                        placeholder="<?= labels('enter_answer', 'Enter the answer here') ?>"
                                                                        value="<?= htmlspecialchars($faq['answer'] ?? '') ?>" />
                                                                    <button type="button" class="btn btn-danger remove-faq-btn ml-2">
                                                                        <i class="fas fa-minus"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                            } else {
                                                // Show one empty FAQ item if no existing FAQs
                                                ?>
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
                                                                    placeholder="<?= labels('enter_answer', 'Enter the answer here') ?>" />
                                                                <button type="button" class="btn btn-danger remove-faq-btn ml-2" style="display: none;">
                                                                    <i class="fas fa-minus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } ?>
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
                    <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="toggleButttonPostition"><?= labels('seo_settings', 'SEO Settings') ?></div>
                    </div>
                    <div class="card-body">
                        <!-- Language tabs for SEO settings -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
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

                        <?php
                        // Sort languages so default language appears first for better UI
                        $sorted_languages = sort_languages_with_default_first($languages);
                        foreach ($sorted_languages as $index => $language) {
                        ?>
                            <div id="translationSeoDiv-<?= $language['code'] ?>" <?= $language['code'] == $default_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_title_<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="meta_title_<?= $language['code'] ?>" class="form-control" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255" value="<?=
                                                                                                                                                                                                                                                                        // Get SEO title for this language from translated data
                                                                                                                                                                                                                                                                        isset($service['translated_seo_' . $language['code']]['seo_title']) ? $service['translated_seo_' . $language['code']]['seo_title'] : (isset($service_seo_settings['title']) ? $service_seo_settings['title'] : "")
                                                                                                                                                                                                                                                                        ?>">
                                            <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_keywords_<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="meta_keywords_<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100 seo-meta-keywords" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>" value="<?=
                                                                                                                                                                                                                                                                                                                            // Prefer translated keywords; only fall back to base settings for default language
                                                                                                                                                                                                                                                                                                                            isset($service['translated_seo_' . $language['code']]['seo_keywords']) ? $service['translated_seo_' . $language['code']]['seo_keywords'] : (($language['is_default'] == 1 && isset($service_seo_settings['keywords'])) ? $service_seo_settings['keywords'] : "")
                                                                                                                                                                                                                                                                                                                            ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_description_<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <textarea id="meta_description_<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500"><?=
                                                                                                                                                                                                                                                                                                                                                                                    // Get SEO description for this language from translated data
                                                                                                                                                                                                                                                                                                                                                                                    isset($service['translated_seo_' . $language['code']]['seo_description']) ? $service['translated_seo_' . $language['code']]['seo_description'] : (isset($service_seo_settings['description']) ? $service_seo_settings['description'] : "")
                                                                                                                                                                                                                                                                                                                                                                                    ?></textarea>
                                            <small class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="schema_markup_<?= $language['code'] ?>"><?= labels('schema_markup', 'Schema Markup') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                                data-toggle="popover"
                                                class="fa fa-question-circle"
                                                data-original-title=""
                                                title=""></i>
                                            <textarea id="schema_markup_<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"><?=
                                                                                                                                                                                                                                                                                                                                                        // Get SEO schema markup for this language from translated data
                                                                                                                                                                                                                                                                                                                                                        isset($service['translated_seo_' . $language['code']]['seo_schema_markup']) ? $service['translated_seo_' . $language['code']]['seo_schema_markup'] : (isset($service_seo_settings['schema_markup']) ? $service_seo_settings['schema_markup'] : "")
                                                                                                                                                                                                                                                                                                                                                        ?></textarea>
                                        </div>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                                            <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i> <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                            <input type="file" class="filepond" name="meta_image" id="meta_image" accept="image/*">
                                            <small class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                                            <?php if (!empty($service_seo_settings['image'])): ?>
                                                <div class="position-relative d-inline-block mt-2">
                                                    <img src="<?= esc($service_seo_settings['image']) ?>" alt="SEO Image" style="max-width: 120px; max-height: 80px; border-radius: 8px;">
                                                    <button type="button" class="btn btn-sm btn-danger remove-service-seo-image"
                                                        data-service-id="<?= $service_seo_settings['service_id'] ?>"
                                                        data-seo-id="<?= isset($service_seo_settings['id']) ? $service_seo_settings['id'] : '' ?>"
                                                        style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; padding: 0; border-radius: 50%; font-size: 10px;">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
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
                            <div class="toggleButttonPostition"><?= labels('service_option', 'Service Options') ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="required" for="is_cancelable"><?= labels('is_cancelable_?', 'Is Cancelable ')  ?></label>
                                <i data-content="<?= labels('data_content_for_is_cancellable', 'Can customers cancel their booking if they\'ve already booked this service?') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                <?php
                                if ($service['is_cancelable'] == "1") { ?>
                                    <input type="checkbox" id="is_cancelable" name="is_cancelable" class="status-switch" checked>
                                <?php   } else { ?>
                                    <input type="checkbox" id="is_cancelable" name="is_cancelable" class="status-switch">
                                <?php  }
                                ?>
                            </div>
                            <div class="col-md-3  <?php if ($cod_setting != 1) echo 'd-none'; ?>">
                                <label class="required"><?= labels('pay_later_allowed', 'Pay Later Allowed') ?></label>
                                <i data-content="<?= labels('data_content_for_paylater_allowed', 'If this option is enabled, customers can book the service and pay after the booking is completed. Generally, this is known as the Cash On Delivery option.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                <?php
                                if ($service['is_pay_later_allowed'] == "1") { ?>
                                    <input type="checkbox" id="pay_later" name="pay_later" class="status-switch" checked>
                                <?php   } else { ?>
                                    <input type="checkbox" id="pay_later" name="pay_later" class="status-switch">
                                <?php  }
                                ?>
                            </div>
                            <div class="col-md-3" id="service_at_store">
                                <div class="form-group">
                                    <label class="required"><?= labels('at_store', 'At Store') ?></label>
                                    <i data-content=" <?= labels('data_content_for_service_at_store', 'If this feature is enabled, customers can book the service at the provider\'s location. The customer needs to go to the provider\'s location on the chosen date and time.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input type="checkbox" id="at_store" name="at_store" class="status-switch" <?= $service['at_store'] == "1" ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-md-3" id="service_at_doorstep">
                                <div class="form-group">
                                    <label class="required"><?= labels('at_doorstep', 'At Doorstep') ?></label>
                                    <i data-content="<?= labels('data_content_for_service_at_doorstep', 'If this feature is enabled, customers can book the service at their location. The provider needs to go to the customer\'s location on the chosen date and time.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input type="checkbox" id="at_doorstep" name="at_doorstep" class="status-switch" <?= $service['at_doorstep'] == "1" ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-md-3" id="service_approve_service">
                                <div class="form-group">
                                    <label class="" for="approve_service" class="required"> <?= labels('approve_service', 'Approve Service') ?></label>
                                    <i data-content="<?= labels('data_content_for_approve_service', 'This toggle controls whether the service is approved and visible to users. If this toggle is not enabled, the service will not appear in the app or web.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input type="hidden" name="approve_service_value" value='<?= $service['approved_by_admin'] ?>' id="approve_service_value">
                                    <input type="checkbox" id="approve_service" name="approve_service" class="status-switch" <?= $service['approved_by_admin'] == "1" ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="edit_cancel">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="cancelable_till" class="required"><?= labels('cancelable_before', 'Cancelable before') ?></label>
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
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md d-flex justify-content-end">
                <button type="submit" class="btn btn-lg bg-new-primary submit_btn"><?= labels('edit_service', "Edit Service") ?></button>
                <?= form_close() ?>
            </div>
        </div>
    </section>
</div>

<script>
    $(document).ready(function() {
        let cancle = <?= (isset($service['cancelable_till']) && !empty($service['cancelable_till'])) ? $service['cancelable_till'] : "0" ?>;
        let is_cancelable = <?= $service['is_cancelable'] ?>;
        <?php if ($service['is_cancelable'] == "0") { ?>
            $('#edit_cancel').hide();
        <?php } else { ?>
            $("#edit_cancel").show()
        <?php  }
        ?>
        $("#cancelable_till").val(cancle);
        <?php
        if ($service['is_cancelable'] == 1) { ?>
            $('#is_cancelable').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php   } else { ?>
            $('#is_cancelable').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php  }
        ?>
        <?php
        if ($service['is_pay_later_allowed'] == 1) { ?>
            $('#pay_later').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php   } else { ?>
            $('#pay_later').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php  }
        ?>
        <?php
        if ($service['status'] == 1) { ?>
            $('#status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php   } else { ?>
            $('#status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php  }
        ?>

        <?php
        if ($service['at_store'] == 1) { ?>

            $('#at_store').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php   } else { ?>

            $('#at_store').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php  }
        ?>
        <?php
        if ($service['at_doorstep'] == 1) { ?>
            $('#at_doorstep').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php   } else { ?>
            $('#at_doorstep').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php  }
        ?>
        $('#approve_service').on('change', function() {
            this.value = this.checked ? 1 : 0;
            $('#approve_service_value').val(this.value);
        });

        function handleSwitchChange(checkbox) {
            var switchery = checkbox.nextElementSibling;
            if (checkbox.checked) {
                switchery.classList.add('active-content');
                switchery.classList.remove('deactive-content');
            } else {
                switchery.classList.add('deactive-content');
                switchery.classList.remove('active-content');
            }
        }
        $(document).on('change', '#is_cancelable, #pay_later, #at_store, #at_doorstep', function() {
            handleSwitchChange(this);
        });
    });

    function loadServiceImage(event) {
            var image = document.getElementById('image_preview');
            image.src = URL.createObjectURL(event.target.files[0]);
        };

        function test() {
            var tax = document.getElementById("edit_tax").value;
            document.getElementById("update_service").reset();
            document.getElementById("edit_tax").value = tax;
            document.getElementById('edit_service_image').removeAttribute('src');
        }
        $('#service_image_selector').bind('change', function() {
            var filename = $("#service_image_selector").val();
            if (/^\s*$/.test(filename)) {
                $(".file-upload").removeClass('active');
                $("#noFile").text("No file chosen...");
            } else {
                $(".file-upload").addClass('active');
                $("#noFile").text(filename.replace("C:\\fakepath\\", ""));
            }
        });
        $('#is_cancelable').on('change', function() {
            if (this.checked) {
                $("#edit_cancel").show()
            } else {
                $('#edit_cancel').hide();
            }
        }).change();
    });
</script>

<script>
    $(document).ready(function() {
        updateCheckboxDisplay();
        $("#partner").change(function() {
            updateCheckboxDisplay();
        });

        function updateCheckboxDisplay() {
            var selectedOption = $("#partner option:selected");
            var atStore = parseInt(selectedOption.data("at_store"));
            var atDoorstep = parseInt(selectedOption.data("at_doorstep"));
            $("#service_at_store").toggle(atStore === 1);
            $("#service_at_doorstep").toggle(atDoorstep === 1);
            if (atStore !== 1) {
                $("#at_store").prop("checked", false);
            }
            if (atDoorstep !== 1) {
                $("#at_doorstep").prop("checked", false);
            }
        }
    });
    $('#partner').change(function() {
        var selectedOption = $("#partner option:selected");
        var selectedPartnerId = $(this).val();
        var atStore = parseInt(selectedOption.data("at_store"));
        var atDoorstep = parseInt(selectedOption.data("at_doorstep"));
        var need_approval_for_the_service = parseInt(selectedOption.data("need_approval_for_the_service"));

        if (atStore == 0) {
            $("#service_at_store").hide();
        } else {
            $("#service_at_store").show();
        }
        if (atDoorstep == 0) {
            $("#service_at_doorstep").hide();
        } else {
            $("#service_at_doorstep").show();
        }
        if (need_approval_for_the_service == 0) {
            $("#service_approve_service").hide();
            $('#approve_service_value').val(1);
        } else {
            $("#service_approve_service").show();
        }
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

<script>
    $(document).ready(function() {
        // Handle individual image removal with toggle functionality
        $('.remove-other-image').on('click', function() {
            const button = this;
            const container = $(this).closest('.position-relative');
            const removeFlag = container.find('.remove-flag');

            if (removeFlag.length) {
                // Toggle the removal state
                if (removeFlag.val() === "0") {
                    // Mark for removal
                    removeFlag.val("1");
                    container.find('img').css('opacity', '0.5');
                    $(button).removeClass('btn-danger').addClass('btn-primary');
                    $(button).html('<i class="fas fa-undo"></i>');
                } else {
                    // Unmark for removal
                    removeFlag.val("0");
                    container.find('img').css('opacity', '1');
                    $(button).removeClass('btn-primary').addClass('btn-danger');
                    $(button).html('<i class="fas fa-times"></i>');
                }
            }
        });

        // Handle remove all images button
        $('.remove-all-other-images').on('click', function() {
            if (confirm('<?= labels('are_you_sure_to_remove_all_images', 'Are you sure you want to remove all images?') ?>')) {
                const otherImagesContainer = $('#other_images_container');
                const imageContainers = otherImagesContainer.find('.other-image-container');

                // Mark all images for removal
                imageContainers.each(function() {
                    const container = $(this).find('.position-relative');
                    const removeFlag = container.find('.remove-flag');
                    const button = container.find('.remove-other-image');

                    if (removeFlag.length) {
                        // Mark for removal
                        removeFlag.val("1");
                        container.find('img').css('opacity', '0.5');
                        button.removeClass('btn-danger').addClass('btn-success');
                        button.html('<i class="fas fa-undo"></i>');
                    }
                });
            }
        });

        // Handle individual file removal with toggle functionality
        $('.remove-file').on('click', function() {
            const button = this;
            const container = $(this).closest('.position-relative');
            const removeFlag = container.find('.remove-flag');

            if (removeFlag.length) {
                // Toggle the removal state
                if (removeFlag.val() === "0") {
                    // Mark for removal
                    removeFlag.val("1");
                    container.find('.file-link').css('opacity', '0.5');
                    container.find('.p-2').css('opacity', '0.5');
                    $(button).removeClass('btn-danger').addClass('btn-primary');
                    $(button).html('<i class="fas fa-undo"></i>');
                } else {
                    // Unmark for removal
                    removeFlag.val("0");
                    container.find('.file-link').css('opacity', '1');
                    container.find('.p-2').css('opacity', '1');
                    $(button).removeClass('btn-primary').addClass('btn-danger');
                    $(button).html('<i class="fas fa-times"></i>');
                }
            }
        });

        // Handle remove all files button
        $('.remove-all-files').on('click', function() {
            if (confirm('<?= labels('are_you_sure_to_remove_all_files', 'Are you sure you want to remove all files?') ?>')) {
                const filesContainer = $('#files_container');
                const fileContainers = filesContainer.find('.file-container');

                // Mark all files for removal
                fileContainers.each(function() {
                    const container = $(this).find('.position-relative');
                    const removeFlag = container.find('.remove-flag');
                    const button = container.find('.remove-file');

                    if (removeFlag.length) {
                        // Mark for removal
                        removeFlag.val("1");
                        container.find('.file-link').css('opacity', '0.5');
                        container.find('.p-2').css('opacity', '0.5');
                        button.removeClass('btn-danger').addClass('btn-primary');
                        button.html('<i class="fas fa-undo"></i>');
                    }
                });
            }
        });

        // Initialize Tagify for meta keywords field
        $(document).ready(function() {
            var metaKeywordsInput = document.querySelector('input[id=meta_keywords]');
            if (metaKeywordsInput != null) {
                new Tagify(metaKeywordsInput);
            }
        });

        // Handle service SEO image removal
        $(document).on('click', '.remove-service-seo-image', function() {
            const button = $(this);
            const serviceId = button.data('service-id');
            const seoId = button.data('seo-id');

            if (confirm('<?= labels('are_you_sure_to_remove_seo_image', 'Are you sure you want to remove this SEO image?') ?>')) {
                // Show loading state
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                // Make AJAX request to remove SEO image
                $.ajax({
                    url: '<?= base_url('admin/services/remove_seo_image') ?>',
                    type: 'POST',
                    data: {
                        service_id: serviceId,
                        seo_id: seoId,
                        <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error === false) {
                            // Remove the image container from DOM
                            button.closest('.position-relative').remove();
                            // Show success message
                            alert(response.message || 'SEO image removed successfully');
                            // Reset button state (even on success, since the button will be removed)
                            button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        } else {
                            // Show error message
                            alert(response.message || 'Failed to remove SEO image');
                            // Reset button
                            button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        alert('An error occurred while removing the SEO image');
                        // Reset button
                        button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                    }
                });
            }
        });

        // Handle file input change to reset button state when new image is uploaded
        $(document).on('change', '#meta_image', function() {
            // Reset any existing remove button to original state
            $('.remove-service-seo-image').prop('disabled', false).html('<i class="fas fa-times"></i>');
        });
    });
</script>

<script>
    // FAQ Management System for Edit Form - Clean Language-Grouped Structure
    class EditFAQManager {
        constructor() {
            this.currentLanguage = '<?= $default_language ?>';
            this.currentLanguageFaqs = '<?= $default_language_faqs ?>';
            this.faqCounters = {};
            this.initializeFAQSystem();
        }

        initializeFAQSystem() {
            // Initialize counters for each language based on existing FAQs
            <?php foreach ($sorted_languages as $language) { ?>
                this.faqCounters['<?= $language['code'] ?>'] = <?=
                                                                isset($service['translated_' . $language['code']]['faqs']) && is_array($service['translated_' . $language['code']]['faqs'])
                                                                    ? count($service['translated_' . $language['code']]['faqs'])
                                                                    : (($language['is_default'] == 1 && isset($service['faqs']) && is_array($service['faqs'])) ? count($service['faqs']) : 0)
                                                                ?>;
            <?php } ?>

            this.bindEvents();
            this.updateRemoveButtons();
        }

        bindEvents() {
            // Language tab switching for main content
            $(document).on('click', '.language-option', this.switchMainLanguage.bind(this));

            // Language tab switching for FAQs
            $(document).on('click', '.language-option-faqs', this.switchFaqLanguage.bind(this));

            // Add FAQ button
            $(document).on('click', '.add-faq-btn', this.addFAQ.bind(this));

            // Remove FAQ button
            $(document).on('click', '.remove-faq-btn', this.removeFAQ.bind(this));

            // Form submission - restructure FAQ data
            $('#update_service').on('submit', this.restructureFAQData.bind(this));
        }

        switchMainLanguage(event) {
            const language = $(event.currentTarget).data('language');

            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            if (language != this.currentLanguage) {
                $('#translationDiv-' + language).show();
                $('#translationDiv-' + this.currentLanguage).hide();
            }

            this.currentLanguage = language;
        }

        switchFaqLanguage(event) {
            const language = $(event.currentTarget).data('language');

            $('.language-underline-faqs').css('width', '0%');
            $('#language-faqs-' + language).find('.language-underline-faqs').css('width', '100%');

            $('.language-text-faqs').removeClass('text-primary fw-medium');
            $('.language-text-faqs').addClass('text-muted');
            $('#language-faqs-' + language).find('.language-text-faqs').removeClass('text-muted');
            $('#language-faqs-' + language).find('.language-text-faqs').addClass('text-primary');

            if (language != this.currentLanguageFaqs) {
                $('#translationFaqsDiv-' + language).show();
                $('#translationFaqsDiv-' + this.currentLanguageFaqs).hide();
            }

            this.currentLanguageFaqs = language;
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

        collectFAQData() {
            const faqData = {};

            // Initialize empty arrays for all languages
            <?php foreach ($sorted_languages as $language) { ?>
                faqData['<?= $language['code'] ?>'] = [];
            <?php } ?>

            // Collect FAQ data from each language
            $('.faq-container').each((index, container) => {
                const language = $(container).data('language');
                const languageFaqs = [];

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

        restructureFAQData(event) {
            // Collect FAQ data in clean format
            const faqData = this.collectFAQData();

            // Create hidden input for FAQ data
            let faqInput = $('#faq_data_input');
            if (faqInput.length === 0) {
                faqInput = $('<input type="hidden" name="faqs" id="faq_data_input" />');
                $('#update_service').append(faqInput);
            }

            // Set the FAQ data as JSON
            faqInput.val(JSON.stringify(faqData));

            // console.log('FAQ Data being submitted:', faqData);
        }
    }

    // Initialize Edit FAQ Manager when document is ready
    $(document).ready(function() {
        window.editFaqManager = new EditFAQManager();

        // Initialize Tagify for meta keywords field
        var metaKeywordsInput = document.querySelector('input[id=meta_keywords]');
        if (metaKeywordsInput != null) {
            new Tagify(metaKeywordsInput);
        }
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

    // SEO Language Tab Switching and Tagify Initialization
    $(document).ready(function() {
        // Language tab switching for SEO settings
        $(document).on('click', '.language-option-seo', function() {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-underline-seo').css('width', '0%');
            $('#language-seo-' + language).find('.language-underline-seo').css('width', '100%');

            // Update text styling
            $('.language-text-seo').removeClass('text-primary fw-medium');
            $('.language-text-seo').addClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').removeClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').addClass('text-primary fw-medium');

            // Show/hide translation divs
            $('[id^="translationSeoDiv-"]').hide();
            $('#translationSeoDiv-' + language).show();
        });

        // Initialize Tagify for SEO meta keywords fields
        var seoKeywordsInputs = document.querySelectorAll('.seo-meta-keywords');
        seoKeywordsInputs.forEach(function(input) {
            if (input != null) {
                new Tagify(input);
            }
        });
    });
</script>