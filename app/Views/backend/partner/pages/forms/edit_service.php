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
            <h1><?= labels('edit_service', 'Edit Service') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('/partner/services') ?>"><?= labels('service', "Service") ?></a></div>
            </div>
        </div>
        <?= form_open('/partner/services/update_service', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'update_service', 'enctype' => "multipart/form-data"]); ?>
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
                                    <input id="service_slug" class="form-control" type="text" value="<?= isset($service['slug']) ? $service['slug'] : "" ?>" name="service_slug" placeholder="<?= labels('enter_the_slug', 'Enter the slug') ?>">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <div class="categories" id="categories">
                                    <label for="category_item" class="required"><?= labels('choose_a_category_for_your_service', 'Choose a Category for your service') ?></label>
                                    <select id="category_item" class="form-control select2" name="categories" style="margin-bottom: 20px;">
                                        <option value=""><?= labels('select', 'Select') ?> <?= labels('category', 'Category') ?></option>
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
                                        <input type="number" style="height: 42px;" class="form-control" name="duration" id="duration" value="<?= isset($service['duration']) ? $service['duration'] : "" ?>" placeholder="Duration of the Service" min="0" oninput="this.value = Math.abs(this.value)">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="members" class="required"><?= labels('members_required_to_perform_task', 'Members required to perform Tasks') ?></label>
                                    <i data-content=" <?= labels('data_content_for_member_required', 'We\'re just collecting the number of team members who will be doing the service. This helps us show customers how many people will be working on their service.') ?> " class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input id="members" class="form-control" type="number" name="members" placeholder="Members Required" min="0" oninput="this.value = Math.abs(this.value)" value="<?= isset($service['number_of_members_required']) ? $service['number_of_members_required'] : "" ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="max_qty" class="required"><?= labels('max_quantity_allowed', 'Max Quantity allowed for services') ?></label>
                                    <i data-content=" <?= labels('data_content_for_max_quality_allowed', 'Users can add up to a maximum of X quantity of a specific service when adding services to the cart.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input id="max_qty" class="form-control" type="number" name="max_qty" placeholder="Max Quantity allowed for services" min="0" oninput="this.value = Math.abs(this.value)" value="<?= isset($service['max_quantity_allowed']) ? $service['max_quantity_allowed'] : "" ?>">
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
                                <div class="form-group"> <label for="image" class="required"> <?= labels('image', 'Image') ?></label> <small>(<?= labels('service_image_recommended_size', 'We recommend 424 x 551 pixels') ?>)</small>
                                    <input type="file" name="service_image_selector_edit" class="filepond logo" id="service_image_selector" accept="image/*" onchange="loadServiceImage(event)">
                                    <img alt="no image found" width="130px" style="border: solid 1; border-radius: 12px;" height="100px" class="mt-2" id="image_preview" src="<?= isset($service['image']) ? ($service['image']) : "" ?>">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group"> <label for="image"><?= labels('other_images', 'Other Image') ?></label> <small>(<?= labels('other_image_recommended_size', 'We recommend 960 x 540 pixels') ?>)</small>
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
                                                        <img alt="no image found" width="130px" style="border: solid 1; border-radius: 12px;" height="100px" class="mt-2" src="<?= isset($image) ? ($image) : "" ?>">
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
                                    <input type="file" name="files_edit[]" class="filepond-docs" id="files" multiple>
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
                                        <option value="excluded" <?php echo  isset($service['tax_type'])  && $service['tax_type'] == "excluded"  ? 'selected' : '' ?>><?= labels('tax_excluded_in_price', 'Tax Excluded In Price') ?></option>
                                        <option value="included" <?php echo  isset($service['tax_type'])  && $service['tax_type'] == "included"  ? 'selected' : '' ?>> <?= labels('tax_included_in_price', 'Tax Included In Price') ?></option>
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
                                            <option value="<?= $pn['id'] ?>" <?php echo  isset($service['tax_id'])  && $service['tax_id'] ==  $pn['id'] ? 'selected' : '' ?>><?= $pn['title'] ?>(<?= $pn['percentage'] ?>%)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="price" class="required"><?= labels('price', 'Price') ?></label>
                                    <input id="price" class="form-control" type="number" name="price" placeholder="price" value="<?= isset($service['price']) ? $service['price'] : "" ?>" min="0" oninput="this.value = Math.abs(this.value)">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="discounted_price" class="required"><?= labels('discounted_price', 'Discounted Price') ?></label>
                                    <input id="discounted_price" class="form-control" type="number" name="discounted_price" value="<?= isset($service['discounted_price']) ? $service['discounted_price'] : "" ?>" placeholder="Discounted Price" min="0" oninput="this.value = Math.abs(this.value)">
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
                            <div class="toggleButttonPostition"><?= labels('FAQs', 'Faqs') ?>
                                <i data-content=" <?= labels('data_content_for_faqs', 'You can include some general questions and answers to help users understand the service better. This will make it clearer for everyone.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                            </div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 border_bottom_for_cards">
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
                        foreach ($languages as $index => $language) {
                        ?>
                            <div class="row" id="translationFaqsDiv-<?= $language['code'] ?>" <?= $language['code'] == $default_language_faqs ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="col-md-12">
                                    <div class="list_wrapper<?= $language['code'] ?> list_wrapper">
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

                                        // Display FAQs for this language
                                        if (!empty($currentFaqs)) {
                                            foreach ($currentFaqs as $i => $faq) {
                                        ?>
                                                <div class="row">
                                                    <div class="col-xs-4 col-sm-4 col-md-4">
                                                        <div class="form-group">
                                                            <label for="question"><?= labels('question', 'Question') ?></label>
                                                            <input name="faqs[<?= $i ?>][]" type="text" placeholder="<?= labels('enter_question', 'Enter the question here') ?>" class="form-control" value="<?= $faq['question'] ?>" />
                                                        </div>
                                                    </div>
                                                    <div class="col-xs-7 col-sm-7 col-md-4">
                                                        <div class="form-group">
                                                            <label for="answer"><?= labels('answer', 'Answer') ?></label>
                                                            <input autocomplete="off" name="faqs[<?= $i ?>][]" type="text" placeholder="<?= labels('enter_answer', 'Enter the answer here') ?>" class="form-control" value="<?= $faq['answer'] ?>" />
                                                        </div>
                                                    </div>
                                                    <div class="col-xs-1 col-sm-1 col-md-2 mt-4">
                                                        <a href="javascript:void(0);" class="existing_faq_delete_button btn btn-danger">-</a>
                                                    </div>
                                                </div>
                                        <?php
                                            }
                                        }
                                        ?>
                                    </div>
                                    <div class="row">
                                        <div class="col-xs-1 col-sm-1 col-md-2 mt-4">
                                            <a href="javascript:void(0);" class="list_add_button btn btn-primary">+</a>
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
                                            <input id="meta_title<?= $language['code'] ?>" class="form-control" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255" value="<?=
                                                                                                                                                                                                                                                                        // Get SEO title for this language from translated data
                                                                                                                                                                                                                                                                        isset($service['translated_seo_' . $language['code']]['seo_title']) ? $service['translated_seo_' . $language['code']]['seo_title'] : (isset($service_seo_settings['title']) ? $service_seo_settings['title'] : "")
                                                                                                                                                                                                                                                                        ?>">
                                            <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100 seo-meta-keywords" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>" value="<?=
                                                                                                                                                                                                                                                                                                                            // Get SEO keywords for this language; only default language falls back
                                                                                                                                                                                                                                                                                                                            isset($service['translated_seo_' . $language['code']]['seo_keywords']) ? $service['translated_seo_' . $language['code']]['seo_keywords'] : (($language['is_default'] == 1 && isset($service_seo_settings['keywords'])) ? $service_seo_settings['keywords'] : "")
                                                                                                                                                                                                                                                                                                                            ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <textarea id="meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500"><?=
                                                                                                                                                                                                                                                                                                                                                                                // Get SEO description for this language
                                                                                                                                                                                                                                                                                                                                                                                isset($service['translated_seo_' . $language['code']]['seo_description']) ? $service['translated_seo_' . $language['code']]['seo_description'] : (isset($service_seo_settings['description']) ? $service_seo_settings['description'] : "")
                                                                                                                                                                                                                                                                                                                                                                                ?></textarea>
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
                                            <textarea id="schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"><?=
                                                                                                                                                                                                                                                                                                                                                    // Get SEO schema for this language
                                                                                                                                                                                                                                                                                                                                                    isset($service['translated_seo_' . $language['code']]['seo_schema_markup']) ? $service['translated_seo_' . $language['code']]['seo_schema_markup'] : (isset($service_seo_settings['schema_markup']) ? $service_seo_settings['schema_markup'] : "")
                                                                                                                                                                                                                                                                                                                                                    ?></textarea>
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
                                    <?php if (!empty($service_seo_settings['image'])): ?>
                                        <div class="position-relative d-inline-block mt-2">
                                            <img src="<?= esc($service_seo_settings['image']) ?>" alt="SEO Image" style="max-width: 120px; max-height: 80px; border-radius: 8px;">
                                            <button type="button" class="btn btn-sm btn-danger remove-partner-service-seo-image"
                                                data-service-id="<?= $service['id'] ?>"
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
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card h-100">
                    <div class="row pl-3">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('service_options', 'Service Options') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="" for="is_cancelable" class="required"><?= labels('is_cancelable_?', 'Is Cancelable ')  ?></label>
                                <i data-content="<?= labels('data_content_for_is_cancellable', 'Can customers cancel their booking if they\'ve already booked this service?') ?>" class="fa fa-question-circle" data-original-title="" title=""></i>
                                <?php
                                if ($service['is_cancelable'] == "1") { ?>
                                    <input type="checkbox" id="is_cancelable" name="is_cancelable" class="status-switch" checked>
                                <?php   } else { ?>
                                    <input type="checkbox" id="is_cancelable" name="is_cancelable" class="status-switch">
                                <?php  }
                                ?>
                            </div>
                            <div class="col-md-4">
                                <label class="" for="pay_later" class="required"><?= labels('pay_later_allowed', 'Pay Later Allowed') ?></label>
                                <i data-content="<?= labels('data_content_for_paylater_allowed', 'If this option is enabled, customers can book the service and pay after the booking is completed. Generally, this is known as the Cash On Delivery option.') ?>" class="fa fa-question-circle" data-original-title="" title=""></i>
                                <?php
                                if ($service['is_pay_later_allowed'] == "1") { ?>
                                    <input type="checkbox" id="pay_later" name="pay_later" class="status-switch" checked>
                                <?php   } else { ?>
                                    <input type="checkbox" id="pay_later" name="pay_later" class="status-switch">
                                <?php  }
                                ?>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="required">Status</span></label>
                                    <?php
                                    if ($service['status'] == "1") { ?>
                                        <input type="checkbox" id="status" name="status" class="status-switch" checked>
                                    <?php   } else { ?> <input type="checkbox" id="status" name="status" class="status-switch">
                                    <?php  }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="cancel_order">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="cancelable_till" class="required"><?= labels('cancelable_before', 'Cancelable before') ?></label>
                                    <i data-content="<?= labels('data_content_for_cancellable_before', 'If customer can cancel the service, they can cancel their booking X minutes before it starts. For example, if their booking is at 11:00 AM, they can cancel it up to X minutes before 11:00 AM.') ?>" class="fa fa-question-circle" data-original-title="" title=""></i>
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


                            if (isset($service['at_store']) && $service['at_store'] == 1) { ?>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="" for="at_store"><?= labels('at_store', 'At Store') ?></label>
                                        <input type="checkbox" id="at_store" name="at_store" class="status-switch" checked>
                                    </div>
                                </div>
                            <?php } else {  ?>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="" for="at_store"><?= labels('at_store', 'At Store') ?></label>
                                        <input type="checkbox" id="at_store" name="at_store" class="status-switch">
                                    </div>
                                </div>
                            <?php }



                            if (isset($service['at_doorstep']) && $service['at_doorstep'] == 1) { ?>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="" for="at_doorstep"><?= labels('at_doorstep', 'At Doorstep') ?></label>
                                        <input type="checkbox" id="at_doorstep" name="at_doorstep" class="status-switch" checked>
                                    </div>
                                </div>
                            <?php } else {  ?>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="" for="at_doorstep"><?= labels('at_doorstep', 'At Doorstep') ?></label>
                                        <input type="checkbox" id="at_doorstep" name="at_doorstep" class="status-switch">
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md d-flex justify-content-end">
                <button type="submit" class="btn btn-lg bg-new-primary"><?= labels('edit_service', 'Edit Service') ?></button>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#at_store').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        $('#at_doorstep').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
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
        if (<?= $service['is_cancelable'] ?> == "1") {
            $("#edit_cancel").show()
            $('#cancelable_till').val(<?= $service['cancelable_till'] ?>);
        } else {
            $("#edit_cancel").hide();
            $('#cancelable_till').val('');
        }
    });
    var atStore = document.querySelector('#at_store');
    atStore.onchange = function(e) {
        if (atStore.checked) {
            $(this).siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        } else {
            $(this).siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        }
    };
    var atDoorstep = document.querySelector('#at_doorstep');
    atDoorstep.onchange = function(e) {
        if (atDoorstep.checked) {
            $(this).siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        } else {
            $(this).siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        }
    };

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

        $(document).on('click', '.language-option-faqs', function() {
            const language = $(this).data('language');

            $('.language-underline-faqs').css('width', '0%');
            $('#language-faqs-' + language).find('.language-underline-faqs').css('width', '100%');

            $('.language-text-faqs').removeClass('text-primary fw-medium');
            $('.language-text-faqs').addClass('text-muted');
            $('#language-faqs-' + language).find('.language-text-faqs').removeClass('text-muted');
            $('#language-faqs-' + language).find('.language-text-faqs').addClass('text-primary');

            if (language != current_language_faqs) {
                $('#translationFaqsDiv-' + language).show();
                $('#translationFaqsDiv-' + current_language_faqs).hide();
            }

            current_language_faqs = language;
        });

        // FAQ functionality is now handled by the FAQManager class
        // The old FAQ handlers have been replaced with the new FAQManager implementation

        // SEO language tab switching
        let current_language_seo = '<?= $default_language_seo ?>';
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
                        button.removeClass('btn-danger').addClass('btn-primary');
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

        // Handle partner service SEO image removal
        $(document).on('click', '.remove-partner-service-seo-image', function() {
            const button = $(this);
            const serviceId = button.data('service-id');
            const seoId = button.data('seo-id');

            if (confirm('<?= labels('are_you_sure_to_remove_seo_image', 'Are you sure you want to remove this SEO image?') ?>')) {
                // Show loading state
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                // Make AJAX request to remove SEO image
                $.ajax({
                    url: '<?= base_url('partner/services/remove_seo_image') ?>',
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
            $('.remove-partner-service-seo-image').prop('disabled', false).html('<i class="fas fa-times"></i>');
        });
    });
</script>

<script>
    /**
     * FAQ Manager for Edit Service Form
     * Handles FAQ data collection and restructuring for form submission
     */
    class FAQManager {
        constructor() {
            this.initializeEventListeners();
        }

        /**
         * Initialize event listeners for FAQ functionality
         */
        initializeEventListeners() {
            // Add FAQ button click handler
            $(document).on('click', '.list_add_button', this.addFAQ.bind(this));

            // Remove FAQ button click handler
            $(document).on('click', '.existing_faq_delete_button', this.removeFAQ.bind(this));
        }

        /**
         * Add a new FAQ entry
         * 
         * @param {Event} event Click event
         */
        addFAQ(event) {
            const button = $(event.target);
            const container = button.closest('.col-md-12');
            const listWrapper = container.find('.list_wrapper');
            const language = this.getLanguageFromContainer(container);

            // Get current FAQ count for this language
            const currentCount = listWrapper.find('.row').length;

            // Create new FAQ HTML
            const faqHTML = this.createFAQHTML(currentCount, language);

            // Add to container
            listWrapper.append(faqHTML);

            // Update remove button visibility
            this.updateRemoveButtonVisibility(container);
        }

        /**
         * Remove a FAQ entry
         * 
         * @param {Event} event Click event
         */
        removeFAQ(event) {
            const button = $(event.target);
            const faqRow = button.closest('.row');
            const container = faqRow.closest('.col-md-12');

            // Remove the FAQ row
            faqRow.remove();

            // Update remove button visibility
            this.updateRemoveButtonVisibility(container);
        }

        /**
         * Get language code from container
         * 
         * @param {jQuery} container The container element
         * @returns {string} Language code
         */
        getLanguageFromContainer(container) {
            const translationDiv = container.closest('[id^="translationFaqsDiv-"]');
            if (translationDiv.length) {
                const id = translationDiv.attr('id');
                return id.replace('translationFaqsDiv-', '');
            }
            return 'en'; // Default fallback
        }

        /**
         * Create FAQ HTML for a new entry
         * 
         * @param {number} index FAQ index
         * @param {string} language Language code
         * @returns {string} HTML string
         */
        createFAQHTML(index, language) {
            return `
                <div class="row">
                    <div class="col-xs-4 col-sm-4 col-md-4">
                        <div class="form-group">
                            <label for="question"><?= labels('question', 'Question') ?></label>
                            <input name="faqs[${index}][]" type="text" placeholder="<?= labels('enter_question', 'Enter the question here') ?>" class="form-control" />
                        </div>
                    </div>
                    <div class="col-xs-7 col-sm-7 col-md-4">
                        <div class="form-group">
                            <label for="answer"><?= labels('answer', 'Answer') ?></label>
                            <input autocomplete="off" name="faqs[${index}][]" type="text" placeholder="<?= labels('enter_answer', 'Enter the answer here') ?>" class="form-control" />
                        </div>
                    </div>
                    <div class="col-xs-1 col-sm-1 col-md-2 mt-4">
                        <a href="javascript:void(0);" class="existing_faq_delete_button btn btn-danger">-</a>
                    </div>
                </div>
            `;
        }

        /**
         * Update remove button visibility based on FAQ count
         * 
         * @param {jQuery} container The container element
         */
        updateRemoveButtonVisibility(container) {
            const listWrapper = container.find('.list_wrapper');
            const faqRows = listWrapper.find('.row');
            const removeButtons = listWrapper.find('.existing_faq_delete_button');

            if (faqRows.length > 1) {
                removeButtons.show();
            } else {
                removeButtons.hide();
            }
        }

        /**
         * Collect FAQ data from all language containers and format it into the clean structure
         * 
         * @returns {Object} FAQ data in format: {"en": [["q1", "a1"]], "hi": [["q1", "a1"]]}
         */
        collectFAQData() {
            const faqData = {};

            // Initialize empty arrays for all languages
            <?php foreach ($languages as $language) { ?>
                faqData['<?= $language['code'] ?>'] = [];
            <?php } ?>

            // Collect FAQ data from each language container
            $('.list_wrapper').each((index, container) => {
                const language = this.getLanguageFromContainer($(container));
                const languageFaqs = [];

                // Process each FAQ item in this language
                $(container).find('.row').each((faqIndex, faqItem) => {
                    const question = $(faqItem).find('input[name^="faqs"][name$="[]"]').first().val().trim();
                    const answer = $(faqItem).find('input[name^="faqs"][name$="[]"]').last().val().trim();

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
                $('#update_service').append(faqInput);
            }

            // Set the FAQ data as JSON
            faqInput.val(JSON.stringify(faqData));

            console.log('FAQ Data being submitted:', faqData);
        }
    }

    // Initialize FAQ Manager when document is ready
    $(document).ready(function() {
        window.faqManager = new FAQManager();

        // Bind FAQ data restructuring to form submission
        $('#update_service').on('submit', window.faqManager.restructureFAQData.bind(window.faqManager));

        // Track service viewed event
        <?php if (isset($clarity_event_data) && $clarity_event_data['clarity_event'] === 'service_viewed'): ?>
            if (typeof trackServiceViewed === 'function') {
                trackServiceViewed(
                    '<?= $clarity_event_data['service_id'] ?>',
                    '<?= htmlspecialchars($clarity_event_data['service_name'], ENT_QUOTES, 'UTF-8') ?>',
                    '<?= $clarity_event_data['service_price'] ?>',
                    '<?= $clarity_event_data['category_id'] ?>',
                    '<?= htmlspecialchars($clarity_event_data['category_name'], ENT_QUOTES, 'UTF-8') ?>'
                );
            }
        <?php endif; ?>
    });
</script>