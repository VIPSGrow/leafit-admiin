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
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('web_settings', "Web settings") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('/admin/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item">
                    <a href="<?= base_url('/admin/settings/system-settings') ?>">
                        <?= labels('system_settings', "System Settings") ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= labels('web_settings', "Web settings") ?></div>
            </div>
        </div>
        <ul class="justify-content-start nav nav-fill nav-pills pl-3 py-2 setting" id="gen-list">
            <div class="row">
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/web_setting') ?>"
                        id="pills-general_settings-tab">
                        <?= labels('web_settings', "Web Settings") ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/web-landing-page-settings') ?>"
                        id="pills-about_us">
                        <?= labels('landing_page_settings', "Landing Page Settings") ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?= base_url('admin/settings/become-provider-setting') ?>"
                        id="pills-about_us">
                        <?= labels('become_provider_page_settings', "Become Provider Page Settings") ?>
                    </a>
                </li>
            </div>
        </ul>

        <form id="become_provider_form" action="<?= base_url('admin/settings/become-provider-setting-update') ?>"
            method="post" enctype="multipart/form-data">
            <div class="row mb-4">
                <?php
                // Handle backward compatibility for all sections - support both new array format and old JSON string format
                $hero_section = isset($hero_section) ? (is_array($hero_section) ? $hero_section : json_decode($hero_section, true)) : [];
                $how_it_work_section = isset($how_it_work_section) ? (is_array($how_it_work_section) ? $how_it_work_section : json_decode($how_it_work_section, true)) : [];
                $category_section = isset($category_section) ? (is_array($category_section) ? $category_section : json_decode($category_section, true)) : [];
                $subscription_section = isset($subscription_section) ? (is_array($subscription_section) ? $subscription_section : json_decode($subscription_section, true)) : [];
                $top_providers_section = isset($top_providers_section) ? (is_array($top_providers_section) ? $top_providers_section : json_decode($top_providers_section, true)) : [];
                $review_section = isset($review_section) ? (is_array($review_section) ? $review_section : json_decode($review_section, true)) : [];
                $faq_section = isset($faq_section) ? (is_array($faq_section) ? $faq_section : json_decode($faq_section, true)) : [];
                $feature_section = isset($feature_section) ? (is_array($feature_section) ? $feature_section : json_decode($feature_section, true)) : [];
                ?>
                <!-- Hero Section -->
                <div class="col-md-12 col-sm-12 col-xl-12 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3 m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition"><?= labels('hero_section', 'Hero Section') ?></div>
                            </div>
                            <div class="col d-flex justify-content-end mt-4">
                                <input type="checkbox" id="hero_section_status" class="status-switch"
                                    name="hero_section_status" <?= (isset($hero_section['status']) && $hero_section['status'] == '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row col-md-12 mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_hero_section_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-hero-section-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-hero-section-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-hero-section-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-hero-section-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <?php
                                foreach ($languages as $index => $language) {
                                    ?>
                                    <div class="col-md-12" id="translationHeroSectionDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_hero_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="hero_section_short_headline<?= $language['code'] ?>"
                                                        class=""><?= labels('short_headline', 'Short Headline') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="hero_section_short_headline<?= $language['code'] ?>" value="<?php
                                                      // Handle both new multi-language format and old single string format
                                                      if (isset($hero_section['short_headline'][$language['code']])) {
                                                          echo $hero_section['short_headline'][$language['code']];
                                                      } else if (is_string($hero_section['short_headline']) && $language['is_default'] == 1) {
                                                          echo $hero_section['short_headline'];
                                                      } else {
                                                          echo "";
                                                      }
                                                      ?>" class="form-control" type="text"
                                                        name="hero_section_short_headline[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', 'the short headline ') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="hero_section_title<?= $language['code'] ?>"
                                                        class=""><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="hero_section_title<?= $language['code'] ?>"
                                                        class="form-control" value="<?php
                                                        // Handle both new multi-language format and old single string format
                                                        if (isset($hero_section['title'][$language['code']])) {
                                                            echo $hero_section['title'][$language['code']];
                                                        } else if (is_string($hero_section['title']) && $language['is_default'] == 1) {
                                                            echo $hero_section['title'];
                                                        } else {
                                                            echo "";
                                                        }
                                                        ?>" type="text"
                                                        name="hero_section_title[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'the title') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="hero_section_description<?= $language['code'] ?>"
                                                        class=""><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <textarea name="hero_section_description[<?= $language['code'] ?>]"
                                                        id="hero_section_description<?= $language['code'] ?>"
                                                        class="form-control"
                                                        placeholder="<?= labels('description', 'the description') ?> <?= labels('here', ' Here ') ?>"><?php
                                                               // Handle both new multi-language format and old single string format
                                                               if (isset($hero_section['description'][$language['code']])) {
                                                                   echo $hero_section['description'][$language['code']];
                                                               } else if (is_string($hero_section['description']) && $language['is_default'] == 1) {
                                                                   echo $hero_section['description'];
                                                               } else {
                                                                   echo "";
                                                               }
                                                               ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for='hero_section_images'><?= labels('images', "Images") ?></label>
                                        <small>(<?= labels('become_provider_hero_section_image_recommended_size', 'We recommend 532 x 590 pixels') ?>)</small>
                                        <input type="file" name="hero_section_images[]" multiple class="filepond logo"
                                            id="hero_section_images" accept="image/*">

                                        <?php
                                        if (!empty($hero_section['images'])) {
                                            $other_images = ($hero_section['images']);
                                            $disk = fetch_current_file_manager();

                                            ?>
                                            <div class="row">
                                                <?php foreach ($other_images as $key => $row) { ?>
                                                    <?php
                                                    if ($disk == "aws_s3") {
                                                        $image_url = fetch_cloud_front_url('become_provider', $row['image']);
                                                    } else if ($disk == "local_server") {
                                                        $image_url = base_url('public/uploads/become_provider/' . $row['image']);
                                                    } else {
                                                        $image_url = base_url('public/uploads/become_provider/' . $row['image']);
                                                    }
                                                    ?>
                                                    <div class="col-xl-4 col-md-12">
                                                        <div class="position-relative">
                                                            <img alt="no image found" width="130px"
                                                                style="border: solid 1; border-radius: 12px;" height="100px"
                                                                class="mt-2" id="image_preview"
                                                                src="<?= isset($image_url) ? ($image_url) : "" ?>">
                                                            <button type="button"
                                                                class="btn btn-sm btn-danger position-absolute"
                                                                style="top: 5px; right: 5px; padding: 0px 5px;"
                                                                onclick="markImageForRemoval(this, <?= $key ?>)">
                                                                <i class="fa fa-times"></i>
                                                            </button>
                                                            <input type="hidden"
                                                                name="hero_section_images_existing[<?= $key ?>][image]"
                                                                value="<?= $row['image'] ?>">
                                                            <input type="hidden"
                                                                name="hero_section_images_existing[<?= $key ?>][disk]"
                                                                value="<?= $disk ?>">
                                                            <input type="hidden"
                                                                name="hero_section_images_existing[<?= $key ?>][remove]"
                                                                value="0" class="remove-flag">
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- How It work  Section -->
                <div class="col-md-12 col-sm-12 col-xl-12  mb-3">
                    <div class="card h-100">
                        <div class="row pl-3 m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition">
                                    <?= labels('how_it_works_section', 'How It Works Section') ?>
                                </div>
                            </div>
                            <div class="col d-flex justify-content-end  mt-4 ">
                                <input type="checkbox" id="how_it_work_section_status" class="status-switch"
                                    name="how_it_work_section_status" <?= (isset($how_it_work_section['status']) && $how_it_work_section['status'] == '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row col-md-12 mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_how_it_work_section_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-how-it-work-section-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-how-it-work-section-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-how-it-work-section-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-how-it-work-section-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <?php
                                foreach ($languages as $index => $language) {
                                    ?>
                                    <div class="col-md-12" id="translationHowItWorkSectionDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_how_it_work_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="how_it_work_section_short_headline<?= $language['code'] ?>"
                                                        class=""><?= labels('short_headline', 'Short Headline') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="how_it_work_section_short_headline<?= $language['code'] ?>"
                                                        value="<?php
                                                        // Handle both new multi-language format and old single string format
                                                        if (isset($how_it_work_section['short_headline'][$language['code']])) {
                                                            echo $how_it_work_section['short_headline'][$language['code']];
                                                        } else if (is_string($how_it_work_section['short_headline']) && $language['is_default'] == 1) {
                                                            echo $how_it_work_section['short_headline'];
                                                        } else {
                                                            echo "";
                                                        }
                                                        ?>" class="form-control" type="text"
                                                        name="how_it_work_section_short_headline[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', 'short headline ') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="how_it_work_section_title<?= $language['code'] ?>"
                                                        class=""><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="how_it_work_section_title<?= $language['code'] ?>"
                                                        class="form-control" value="<?php
                                                        // Handle both new multi-language format and old single string format
                                                        if (isset($how_it_work_section['title'][$language['code']])) {
                                                            echo $how_it_work_section['title'][$language['code']];
                                                        } else if (is_string($how_it_work_section['title']) && $language['is_default'] == 1) {
                                                            echo $how_it_work_section['title'];
                                                        } else {
                                                            echo "";
                                                        }
                                                        ?>" type="text"
                                                        name="how_it_work_section_title[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'title') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="how_it_work_section_description<?= $language['code'] ?>"
                                                        class=""><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <textarea
                                                        name="how_it_work_section_description[<?= $language['code'] ?>]"
                                                        id="how_it_work_section_description<?= $language['code'] ?>"
                                                        class="form-control"
                                                        placeholder="<?= labels('description', 'description') ?> <?= labels('here', ' Here ') ?>"><?php
                                                               // Handle both new multi-language format and old single string format
                                                               if (isset($how_it_work_section['description'][$language['code']])) {
                                                                   echo $how_it_work_section['description'][$language['code']];
                                                               } else if (is_string($how_it_work_section['description']) && $language['is_default'] == 1) {
                                                                   echo $how_it_work_section['description'];
                                                               } else {
                                                                   echo "";
                                                               }
                                                               ?></textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <label for="steps" class=""> <?= labels('steps', "Steps") ?>
                                                    <?= $language['is_default'] ? '' : ' (' . $language['code'] . ')' ?></label>
                                                <?php
                                                // Handle backward compatibility for steps data storage
                                                $steps = [];
                                                if (isset($how_it_work_section['steps'])) {
                                                    if (is_array($how_it_work_section['steps'])) {
                                                        // New format: already a clean PHP array with multilingual structure
                                                        // Check if this language exists in the steps array
                                                        if (isset($how_it_work_section['steps'][$language['code']])) {
                                                            $steps = $how_it_work_section['steps'][$language['code']];
                                                        } else {
                                                            $steps = [];
                                                        }
                                                    } else if (is_string($how_it_work_section['steps'])) {
                                                        // Old format: JSON string (likely from default language only)
                                                        // Decode it and only show for default language
                                                        if ($language['is_default'] == 1) {
                                                            $decoded_steps = json_decode($how_it_work_section['steps'], true) ?: [];
                                                            $steps = $decoded_steps;
                                                        } else {
                                                            // For non-default languages, start with empty array
                                                            $steps = [];
                                                        }
                                                    }
                                                }
                                                ?>
                                                <?php if (count($steps) == 0): ?>
                                                    <div id="how_it_work_section_steps_container<?= $language['code'] ?>">
                                                        <div class="row input-group mb-2">
                                                            <div class="col-md-5">
                                                                <input id="how_it_work_section_steps" class="form-control"
                                                                    placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('enter_title', "Enter title") ?> <?= labels('here', ' Here ') ?>"
                                                                    type="text"
                                                                    name="how_it_work_section_steps[<?= $language['code'] ?>][0][title]">
                                                            </div>
                                                            <div class="col-md-5">
                                                                <input id="how_it_work_section_steps" class="form-control"
                                                                    placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('enter_description', "Enter description") ?> <?= labels('here', ' Here ') ?>"
                                                                    type="text"
                                                                    name="how_it_work_section_steps[<?= $language['code'] ?>][0][description]">
                                                            </div>
                                                            <div class="col-md-2 d-flex">
                                                                <button type="button"
                                                                    class="btn btn-outline-primary mr-2 add-how-it-work-steps">
                                                                    <i class="fa fa-plus"></i>
                                                                </button>
                                                                <button type="button"
                                                                    class="btn btn-outline-danger remove-how-it-work-steps"
                                                                    style="display: none;">
                                                                    <i class="fa fa-minus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div id="how_it_work_section_steps_container<?= $language['code'] ?>">
                                                        <?php $first_step_index = array_key_first($steps); ?>
                                                        <?php foreach ($steps as $step_index => $step): ?>
                                                            <div class="row input-group mb-2">
                                                                <div class="col-md-5">
                                                                    <input id="how_it_work_section_steps"
                                                                        value="<?= isset($step['title']) ? esc($step['title']) : '' ?>"
                                                                        class="form-control"
                                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('enter_title', "Enter title") ?> <?= labels('here', ' Here ') ?>"
                                                                        type="text"
                                                                        name="how_it_work_section_steps[<?= $language['code'] ?>][<?= $step_index; ?>][title]">
                                                                </div>
                                                                <div class="col-md-5">
                                                                    <input id="how_it_work_section_steps"
                                                                        value="<?= isset($step['description']) ? esc($step['description']) : '' ?>"
                                                                        class="form-control"
                                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('enter_description', "Enter description") ?> <?= labels('here', ' Here ') ?>"
                                                                        type="text"
                                                                        name="how_it_work_section_steps[<?= $language['code'] ?>][<?= $step_index; ?>][description]">
                                                                </div>
                                                                <?php if ($step_index === $first_step_index): ?>
                                                                    <div class="col-md-2 d-flex">
                                                                        <button type="button"
                                                                            class="btn btn-outline-primary mr-2 add-how-it-work-steps">
                                                                            <i class="fa fa-plus"></i>
                                                                        </button>
                                                                        <button type="button"
                                                                            class="btn btn-outline-danger remove-how-it-work-steps"
                                                                            style="display: none;">
                                                                            <i class="fa fa-minus"></i>
                                                                        </button>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="col-md-2">
                                                                        <button type="button"
                                                                            class="btn btn-outline-danger remove-how-it-work-steps">
                                                                            <i class="fa fa-minus"></i>
                                                                        </button>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Feature section -->
                <div class="col-md-12 col-sm-12 col-xl-12 mb-3">
                    <?php

                    $feature = isset($feature_section['features']) ? ($feature_section['features']) : [];

                    ?>
                    <div class="card h-100">
                        <div class="row pl-3 m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition"><?= labels('feature_section', 'Feature Section') ?>
                                </div>
                            </div>
                            <div class="col d-flex justify-content-end mt-4">
                                <input type="checkbox" id="feature_section_status" class="status-switch"
                                    name="feature_section_status" <?= (isset($feature_section['status']) && $feature_section['status'] == '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row col-md-12 ml-3">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_feature_section_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-feature-section-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-feature-section-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-feature-section-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-feature-section-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php if (count($feature) == 0): ?>
                                <div class="card-body" id="feature_section_features_container">
                                    <div class="card">
                                        <div
                                            class="d-flex justify-content-between align-items-center m-0 border_bottom_for_cards">
                                            <div class="col-auto">
                                                <div class="my-3 toggleButttonPostition"><?= labels('feature', "Feature") ?>
                                                    1</div>
                                            </div>
                                            <div class="col d-flex justify-content-end ">
                                                <button type="button" class="btn btn-outline-primary add-feature-section">
                                                    <i class="fa fa-plus"></i>
                                                    <label for='add_feature'
                                                        class="m-0"><?= labels('add_feature', "Add Feature") ?></label>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="row  feature-section-card card-body  mb-2" data-section-index="0">
                                            <?php
                                            foreach ($languages as $index => $language) {
                                                ?>
                                                <div class="col-md-12 translationFeatureSectionDiv-<?= $language['code'] ?>"
                                                    <?= $language['code'] == $current_feature_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label for="feature_section_short_headline" class="">
                                                                    <?= labels('short_headline', 'Short Headline') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                                <input id="feature_section_short_headline" class="form-control"
                                                                    type="text"
                                                                    name="feature_section_feature[0][<?= $language['code'] ?>][short_headline]"
                                                                    placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', 'short headline ') ?> <?= labels('here', ' Here ') ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label for="feature_section_title"
                                                                    class=""><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                                <input id="feature_section_title" class="form-control"
                                                                    type="text"
                                                                    name="feature_section_feature[0][<?= $language['code'] ?>][title]"
                                                                    placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'title') ?> <?= labels('here', ' Here ') ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label for="feature_section_description"
                                                                    class=""><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                                <textarea
                                                                    name="feature_section_feature[0][<?= $language['code'] ?>][description]"
                                                                    id="feature_section_description" class="form-control"
                                                                    placeholder="<?= labels('description', 'description') ?> <?= labels('here', ' Here ') ?>"></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="feature_section_position"
                                                        class=""><?= labels('position', 'Position') ?></label>
                                                    <select class="form-control" name="feature_section_feature[0][position]"
                                                        id="feature_section_position">
                                                        <option disabled selected>
                                                            <?= labels('select_position', 'Select Position') ?>
                                                        </option>
                                                        <option value="right">
                                                            <?= labels('right', 'Right') ?>
                                                        </option>
                                                        <option value="left">
                                                            <?= labels('left', 'Left') ?>
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label
                                                        for='feature_section_image'><?= labels('image', "Image") ?></label>
                                                    <small>(<?= labels('become_provider_feature_section_image_recommended_size', 'We recommend 645 x 645 pixels') ?>)</small>
                                                    <input type="file" name="feature_section_feature[0][image]"
                                                        class="filepond logo" id="feature_section_images" accept="image/*">
                                                    <input type="hidden" name="feature_section_feature[0][exist_image]"
                                                        value="new">
                                                    <input type="hidden" name="feature_section_feature[0][exist_disk]"
                                                        value="<?= fetch_current_file_manager(); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card-body" id="feature_section_features_container">
                                    <?php foreach ($feature as $index => $value): ?>
                                        <div class="card">
                                            <div
                                                class="d-flex justify-content-between align-items-center m-0 border_bottom_for_cards">
                                                <div class="col-auto">
                                                    <div class="my-3 toggleButttonPostition"><?= labels('feature', "Feature") ?>
                                                        <?= $index + 1; ?>
                                                    </div>
                                                </div>
                                                <?php if ($index == 0): ?>
                                                    <div class="col d-flex justify-content-end ">
                                                        <button type="button" class="btn btn-outline-primary add-feature-section">
                                                            <i class="fa fa-plus"></i>
                                                            <label for='add_feature'
                                                                class="m-0"><?= labels('add_feature', "Add Feature") ?></label>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="col d-flex justify-content-end">
                                                        <button type="button" class="btn btn-outline-danger remove-feature-section">
                                                            <i class="fa fa-minus"></i>
                                                            <label
                                                                class="m-0"><?= labels('remove_feature', "Remove Feature") ?></label>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="row  feature-section-card card-body  mb-2"
                                                data-section-index="<?= $index ?>">
                                                <?php
                                                foreach ($languages as $lang_index => $language) {
                                                    ?>
                                                    <div class="col-md-12 translationFeatureSectionDiv-<?= $language['code'] ?>"
                                                        <?= $language['code'] == $current_feature_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label
                                                                        for="feature_section_short_headline<?= $index ?>_<?= $language['code'] ?>"
                                                                        class="">
                                                                        <?= labels('short_headline', 'Short Headline') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                                    <input
                                                                        id="feature_section_short_headline<?= $index ?>_<?= $language['code'] ?>"
                                                                        class="form-control" value="<?php
                                                                        // Handle both new multi-language format and old single string format
                                                                        if (isset($value['short_headline'][$language['code']])) {
                                                                            echo $value['short_headline'][$language['code']];
                                                                        } else if (is_string($value['short_headline']) && $language['is_default'] == 1) {
                                                                            echo $value['short_headline'];
                                                                        } else {
                                                                            echo "";
                                                                        }
                                                                        ?>" type="text"
                                                                        name="feature_section_feature[<?= $index ?>][<?= $language['code'] ?>][short_headline]"
                                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', 'short headline ') ?> <?= labels('here', ' Here ') ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label
                                                                        for="feature_section_title<?= $index ?>_<?= $language['code'] ?>"
                                                                        class=""><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                                    <input
                                                                        id="feature_section_title<?= $index ?>_<?= $language['code'] ?>"
                                                                        value="<?php
                                                                        // Handle both new multi-language format and old single string format
                                                                        if (isset($value['title'][$language['code']])) {
                                                                            echo $value['title'][$language['code']];
                                                                        } else if (is_string($value['title']) && $language['is_default'] == 1) {
                                                                            echo $value['title'];
                                                                        } else {
                                                                            echo "";
                                                                        }
                                                                        ?>" class="form-control" type="text"
                                                                        name="feature_section_feature[<?= $index ?>][<?= $language['code'] ?>][title]"
                                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'title') ?> <?= labels('here', ' Here ') ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label
                                                                        for="feature_section_description<?= $index ?>_<?= $language['code'] ?>"
                                                                        class=""><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                                    <textarea
                                                                        name="feature_section_feature[<?= $index ?>][<?= $language['code'] ?>][description]"
                                                                        id="feature_section_description<?= $index ?>_<?= $language['code'] ?>"
                                                                        class="form-control"
                                                                        placeholder="<?= labels('description', 'description') ?> <?= labels('here', ' Here ') ?>"><?php
                                                                               // Handle both new multi-language format and old single string format
                                                                               if (isset($value['description'][$language['code']])) {
                                                                                   echo $value['description'][$language['code']];
                                                                               } else if (is_string($value['description']) && $language['is_default'] == 1) {
                                                                                   echo $value['description'];
                                                                               } else {
                                                                                   echo "";
                                                                               }
                                                                               ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="feature_section_position"
                                                            class=""><?= labels('position', 'Position') ?></label>
                                                        <select class="form-control"
                                                            name="feature_section_feature[<?= $index ?>][position]"
                                                            id="feature_section_position">
                                                            <option disabled><?= labels('select_position', 'Select Position') ?>
                                                            </option>
                                                            <option value="right" <?= isset($value['position']) && $value['position'] == "right" ? 'selected="selected"' : "" ?>>
                                                                <?= labels('right', 'Right') ?>
                                                            </option>
                                                            <option value="left" <?= isset($value['position']) && $value['position'] == "left" ? 'selected="selected"' : "" ?>>
                                                                <?= labels('left', 'Left') ?>
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label
                                                            for='feature_section_image'><?= labels('image', "Image") ?></label>
                                                        <small>(<?= labels('become_provider_feature_section_image_recommended_size', 'We recommend 645 x 645 pixels') ?>)</small>

                                                        <?php
                                                        $disk = fetch_current_file_manager();

                                                        ?>
                                                        <input type="file" name="feature_section_feature[<?= $index ?>][image]"
                                                            class="filepond logo" id="feature_section_images" accept="image/*">
                                                        <input type="hidden"
                                                            name="feature_section_feature[<?= $index ?>][exist_image]"
                                                            value="<?= $value['image']; ?>">
                                                        <input type="hidden"
                                                            name="feature_section_feature[<?= $index ?>][exist_disk]"
                                                            value="<?= $disk; ?>">

                                                        <?php
                                                        // Check if image is empty - use default image as fallback
                                                        if (empty($value['image'])) {
                                                            $image_url = base_url('public/backend/assets/default.png');
                                                        } else {
                                                            // Process image based on storage disk type
                                                            if ($disk == "aws_s3") {
                                                                $image_url = fetch_cloud_front_url('become_provider', $value['image']);
                                                            } else if ($disk == "local_server") {
                                                                $image_url = base_url('public/uploads/become_provider/' . $value['image']);
                                                            } else {
                                                                $image_url = base_url('public/backend/assets/img/news/img01.jpg');
                                                            }
                                                        }
                                                        ?>

                                                        <div class="position-relative">
                                                            <img class="settings_logo" src="<?= $image_url; ?>">
                                                            <!-- Removed duplicate hidden fields - they are already defined above -->
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Categroy section -->
                <div class="col-md-12 col-sm-12 col-xl-12 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3 m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition">
                                    <?= labels('category_section', 'Category Section') ?>
                                </div>
                            </div>
                            <div class="col d-flex justify-content-end  mt-4 ">
                                <input type="checkbox" id="category_section_status" class="status-switch"
                                    name="category_section_status" <?= (isset($category_section['status']) && $category_section['status'] == '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row col-md-12 mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_category_section_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-category-section-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-category-section-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-category-section-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-category-section-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <?php
                                foreach ($languages as $index => $language) {
                                    ?>
                                    <div class="col-md-12" id="translationCategorySectionDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_category_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="category_section_short_headline<?= $language['code'] ?>"
                                                        class=""><?= labels('short_headline', 'Short Headline') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="category_section_short_headline<?= $language['code'] ?>"
                                                        value="<?php
                                                        // Handle both new multi-language format and old single string format
                                                        if (isset($category_section['short_headline'][$language['code']])) {
                                                            echo $category_section['short_headline'][$language['code']];
                                                        } else if (is_string($category_section['short_headline']) && $language['is_default'] == 1) {
                                                            echo $category_section['short_headline'];
                                                        } else {
                                                            echo "";
                                                        }
                                                        ?>" class="form-control" type="text"
                                                        name="category_section_short_headline[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', 'the short headline ') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="category_section_title<?= $language['code'] ?>"
                                                        class=""><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="category_section_title<?= $language['code'] ?>" value="<?php
                                                      // Handle both new multi-language format and old single string format
                                                      if (isset($category_section['title'][$language['code']])) {
                                                          echo $category_section['title'][$language['code']];
                                                      } else if (is_string($category_section['title']) && $language['is_default'] == 1) {
                                                          echo $category_section['title'];
                                                      } else {
                                                          echo "";
                                                      }
                                                      ?>" class="form-control" type="text"
                                                        name="category_section_title[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'the title') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="category_section_description<?= $language['code'] ?>"
                                                        class=""><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <textarea name="category_section_description[<?= $language['code'] ?>]"
                                                        id="category_section_description<?= $language['code'] ?>"
                                                        class="form-control"
                                                        placeholder="<?= labels('description', 'the description') ?> <?= labels('here', ' Here ') ?>"><?php
                                                               // Handle both new multi-language format and old single string format
                                                               if (isset($category_section['description'][$language['code']])) {
                                                                   echo $category_section['description'][$language['code']];
                                                               } else if (is_string($category_section['description']) && $language['is_default'] == 1) {
                                                                   echo $category_section['description'];
                                                               } else {
                                                                   echo "";
                                                               }
                                                               ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                            <?php $category_ids = isset($category_section['category_ids']) ? $category_section['category_ids'] : [] ?>
                            <div class="col-md-6">
                                <div class="categories form-group" id="categories">
                                    <label for="category_item"
                                        class="required"><?= labels('choose_a_category', 'Choose a Category') ?></label>
                                    <select id="category_item" class="form-control select2"
                                        name="category_section_category_ids[]" multiple style="margin-bottom: 20px;">
                                        <option value=""> <?= labels('select', 'Select') ?>
                                            <?= labels('category', 'Category') ?>
                                        </option>
                                        <?php foreach ($categories_name as $category): ?>
                                            <?php
                                            if (is_string($category_ids)) {
                                                $category_ids = explode(',', $category_ids);
                                            }
                                            $selected = in_array($category['id'], $category_ids) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $category['id'] ?>" <?= $selected ?>><?= $category['name'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Subscription section -->
                <div class="col-md-12 col-sm-12 col-xl-12 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3 m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition">
                                    <?= labels('subscription_section', 'Subscription Section') ?>
                                </div>
                            </div>
                            <div class="col d-flex justify-content-end  mt-4 ">
                                <input type="checkbox" id="subscription_section_status" class="status-switch"
                                    name="subscription_section_status" <?= (isset($subscription_section['status']) && $subscription_section['status'] == '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row col-md-12 mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_subscription_section_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-subscription-section-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-subscription-section-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-subscription-section-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-subscription-section-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <?php
                                foreach ($languages as $index => $language) {
                                    ?>
                                    <div class="col-md-12" id="translationSubscriptionSectionDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_subscription_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="subscription_section_short_headline<?= $language['code'] ?>"
                                                        class=""><?= labels('short_headline', 'Short Headline') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="subscription_section_short_headline<?= $language['code'] ?>"
                                                        value="<?php
                                                        // Handle both new multi-language format and old single string format
                                                        if (isset($subscription_section['short_headline'][$language['code']])) {
                                                            echo $subscription_section['short_headline'][$language['code']];
                                                        } else if (is_string($subscription_section['short_headline']) && $language['is_default'] == 1) {
                                                            echo $subscription_section['short_headline'];
                                                        } else {
                                                            echo "";
                                                        }
                                                        ?>" class="form-control" type="text"
                                                        name="subscription_section_short_headline[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', 'the short headline ') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="subscription_section_title<?= $language['code'] ?>"
                                                        class=""><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="subscription_section_title<?= $language['code'] ?>" value="<?php
                                                      // Handle both new multi-language format and old single string format
                                                      if (isset($subscription_section['title'][$language['code']])) {
                                                          echo $subscription_section['title'][$language['code']];
                                                      } else if (is_string($subscription_section['title']) && $language['is_default'] == 1) {
                                                          echo $subscription_section['title'];
                                                      } else {
                                                          echo "";
                                                      }
                                                      ?>" class="form-control" type="text"
                                                        name="subscription_section_title[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'the title') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="subscription_section_description<?= $language['code'] ?>"
                                                        class=""><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <textarea
                                                        name="subscription_section_description[<?= $language['code'] ?>]"
                                                        id="subscription_section_description<?= $language['code'] ?>"
                                                        class="form-control"
                                                        placeholder="<?= labels('description', 'the description') ?> <?= labels('here', ' Here ') ?>"><?php
                                                               // Handle both new multi-language format and old single string format
                                                               if (isset($subscription_section['description'][$language['code']])) {
                                                                   echo $subscription_section['description'][$language['code']];
                                                               } else if (is_string($subscription_section['description']) && $language['is_default'] == 1) {
                                                                   echo $subscription_section['description'];
                                                               } else {
                                                                   echo "";
                                                               }
                                                               ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Top Providers section -->
                <div class="col-md-12 col-sm-12 col-xl-12 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3 m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition">
                                    <?= labels('top_providers_section', 'Top Providers Section') ?>
                                </div>
                            </div>
                            <div class="col d-flex justify-content-end  mt-4 ">
                                <input type="checkbox" id="top_providers_section_status" class="status-switch"
                                    name="top_providers_section_status" <?= (isset($top_providers_section['status']) && $top_providers_section['status'] == '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row col-md-12 mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_top_providers_section_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-top-providers-section-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-top-providers-section-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-top-providers-section-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-top-providers-section-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <?php
                                foreach ($languages as $index => $language) {
                                    ?>
                                    <div class="col-md-12" id="translationTopProvidersSectionDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_top_providers_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label
                                                        for="top_providers_section_short_headline<?= $language['code'] ?>"
                                                        class=""><?= labels('short_headline', 'Short Headline') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="top_providers_section_short_headline<?= $language['code'] ?>"
                                                        value="<?php
                                                        // Handle both new multi-language format and old single string format
                                                        if (isset($top_providers_section['short_headline'][$language['code']])) {
                                                            echo $top_providers_section['short_headline'][$language['code']];
                                                        } else if (is_string($top_providers_section['short_headline']) && $language['is_default'] == 1) {
                                                            echo $top_providers_section['short_headline'];
                                                        } else {
                                                            echo "";
                                                        }
                                                        ?>" class="form-control" type="text"
                                                        name="top_providers_section_short_headline[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', 'the short headline ') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="top_providers_section_title<?= $language['code'] ?>"
                                                        class=""><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="top_providers_section_title<?= $language['code'] ?>" value="<?php
                                                      // Handle both new multi-language format and old single string format
                                                      if (isset($top_providers_section['title'][$language['code']])) {
                                                          echo $top_providers_section['title'][$language['code']];
                                                      } else if (is_string($top_providers_section['title']) && $language['is_default'] == 1) {
                                                          echo $top_providers_section['title'];
                                                      } else {
                                                          echo "";
                                                      }
                                                      ?>" class="form-control" type="text"
                                                        name="top_providers_section_title[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'the title') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="top_providers_section_description<?= $language['code'] ?>"
                                                        class=""><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <textarea
                                                        name="top_providers_section_description[<?= $language['code'] ?>]"
                                                        id="top_providers_section_description<?= $language['code'] ?>"
                                                        class="form-control"
                                                        placeholder="<?= labels('description', 'the description') ?> <?= labels('here', ' Here ') ?>"><?php
                                                               // Handle both new multi-language format and old single string format
                                                               if (isset($top_providers_section['description'][$language['code']])) {
                                                                   echo $top_providers_section['description'][$language['code']];
                                                               } else if (is_string($top_providers_section['description']) && $language['is_default'] == 1) {
                                                                   echo $top_providers_section['description'];
                                                               } else {
                                                                   echo "";
                                                               }
                                                               ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Review section -->
                <div class="col-md-12 col-sm-12 col-xl-12 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3 m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition"><?= labels('review_section', 'Review Section') ?>
                                </div>
                            </div>
                            <div class="col d-flex justify-content-end  mt-4 ">
                                <input type="checkbox" id="review_section_status" class="status-switch"
                                    name="review_section_status" <?= (isset($review_section['status']) && $review_section['status'] == '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row col-md-12 mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_review_section_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-review-section-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-review-section-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-review-section-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-review-section-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <?php
                                foreach ($languages as $index => $language) {
                                    ?>
                                    <div class="col-md-12" id="translationReviewSectionDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_review_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="review_section_short_headline<?= $language['code'] ?>"
                                                        class=""><?= labels('short_headline', 'Short Headline') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="review_section_short_headline<?= $language['code'] ?>" value="<?php
                                                      // Handle both new multi-language format and old single string format
                                                      if (isset($review_section['short_headline'][$language['code']])) {
                                                          echo $review_section['short_headline'][$language['code']];
                                                      } else if (is_string($review_section['short_headline']) && $language['is_default'] == 1) {
                                                          echo $review_section['short_headline'];
                                                      } else {
                                                          echo "";
                                                      }
                                                      ?>" class="form-control" type="text"
                                                        name="review_section_short_headline[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', 'the short headline ') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="review_section_title<?= $language['code'] ?>"
                                                        class=""><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="review_section_title<?= $language['code'] ?>" value="<?php
                                                      // Handle both new multi-language format and old single string format
                                                      if (isset($review_section['title'][$language['code']])) {
                                                          echo $review_section['title'][$language['code']];
                                                      } else if (is_string($review_section['title']) && $language['is_default'] == 1) {
                                                          echo $review_section['title'];
                                                      } else {
                                                          echo "";
                                                      }
                                                      ?>" class="form-control" type="text"
                                                        name="review_section_title[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'the title') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="review_section_description<?= $language['code'] ?>"
                                                        class=""><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <textarea name="review_section_description[<?= $language['code'] ?>]"
                                                        id="review_section_description<?= $language['code'] ?>"
                                                        class="form-control"
                                                        placeholder="<?= labels('description', 'the description') ?> <?= labels('here', ' Here ') ?>"><?php
                                                               // Handle both new multi-language format and old single string format
                                                               if (isset($review_section['description'][$language['code']])) {
                                                                   echo $review_section['description'][$language['code']];
                                                               } else if (is_string($review_section['description']) && $language['is_default'] == 1) {
                                                                   echo $review_section['description'];
                                                               } else {
                                                                   echo "";
                                                               }
                                                               ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <button id="select-ratings" type="button"
                                class="btn btn-primary"><?= labels('select_ratings', "Select ratings") ?></button>
                            <div class="col-12 mt-2">
                                <div class="form-group mb-0">
                                    <label
                                        for='rating_section_id'><?= labels('selected_ratings', "Selected Ratings") ?></label>
                                </div>
                                <div id="selected-ratings">
                                    <?php
                                    $rating_ids = isset($review_section['rating_ids']) ? $review_section['rating_ids'] : [];

                                    // Handle different possible formats of rating_ids
                                    if (is_string($rating_ids) && !empty($rating_ids)) {
                                        // If it's a string, split by comma
                                        $rating_ids = explode(',', $rating_ids);
                                    } else if (is_array($rating_ids) && isset($rating_ids[0])) {
                                        // If it's an array with first element being a string
                                        $rating_ids = explode(',', $rating_ids[0]);
                                    } else if (is_array($rating_ids)) {
                                        // If it's already an array of IDs, keep as is
                                        $rating_ids = $rating_ids;
                                    } else {
                                        $rating_ids = [];
                                    }

                                    // Clean up any empty values and convert to integers
                                    $rating_ids = array_filter(array_map('trim', $rating_ids));
                                    $rating_ids = array_map('intval', $rating_ids);

                                    $rating_map = array_column($services_ratings, null, 'id');
                                    foreach ($rating_ids as $index => $id):
                                        if (isset($rating_map[$id])):
                                            $rating = $rating_map[$id];

                                            // Fix profile image URL - ensure it doesn't have duplicate base_url
                                            $profile_image = $rating['profile_image'];
                                            if (strpos($profile_image, 'http') !== 0) {
                                                $profile_image = base_url($profile_image);
                                            }
                                            ?>
                                            <div
                                                class="card author-box card-primary <?= $index >= 2 ? 'd-none more-ratings' : '' ?>">
                                                <div class="card-body">
                                                    <div class="author-box-left">
                                                        <img alt="image" src="<?= $profile_image ?>"
                                                            class="rounded-circle author-box-picture">
                                                    </div>
                                                    <div class="author-box-details">
                                                        <div class="author-box-name"><?= $rating['username'] ?></div>
                                                        <?php
                                                        $created_at = isset($rating['created_at']) ? $rating['created_at'] : '';
                                                        $formatted_date = $created_at ? (new DateTime($created_at))->format('j M Y, g:i A') : '';
                                                        ?>
                                                        <div class="author-box-job">
                                                            <?= htmlspecialchars($formatted_date, ENT_QUOTES, 'UTF-8') ?>
                                                        </div>
                                                        <div class="author-box-description">
                                                            <p class="p-0 m-0">
                                                                <?= htmlspecialchars($rating['comment'], ENT_QUOTES, 'UTF-8') ?>
                                                            </p>
                                                        </div>
                                                        <div class="float-right mt-sm-0">
                                                            <i
                                                                class="fa-solid fa-star text-warning mr-1"></i><?= $rating['rating'] ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                        endif;
                                    endforeach;
                                    ?>
                                    <?php if (count($rating_ids) > 2): ?>
                                        <div class="row">
                                            <div class="col-md-12 d-flex justify-content-end">
                                                <button id="view-more-ratings" type="button"
                                                    class="btn btn-primary"><?= labels('view_more', "View More") ?></button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- FAQs section -->
                <div class="col-md-12 col-sm-12 col-xl-12 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3 m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition"><?= labels('faq_section', 'FAQ Section') ?></div>
                            </div>
                            <div class="col d-flex justify-content-end  mt-4 ">
                                <input type="checkbox" id="faq_section_status" class="status-switch"
                                    name="faq_section_status" <?= (isset($faq_section['status']) && $faq_section['status'] == '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- FAQ Section Header Fields -->
                            <div class="row col-md-12 mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_faq_section_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-faq-section-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-faq-section-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-faq-section-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-faq-section-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <?php
                                foreach ($languages as $index => $language) {
                                    ?>
                                    <div class="col-md-12" id="translationFaqSectionDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_faq_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="faq_section_short_headline<?= $language['code'] ?>"
                                                        class=""><?= labels('short_headline', 'Short Headline') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="faq_section_short_headline<?= $language['code'] ?>" value="<?php
                                                      // Handle both new multi-language format and old single string format
                                                      if (isset($faq_section['short_headline'][$language['code']])) {
                                                          echo $faq_section['short_headline'][$language['code']];
                                                      } else if (is_string($faq_section['short_headline']) && $language['is_default'] == 1) {
                                                          echo $faq_section['short_headline'];
                                                      } else {
                                                          echo "";
                                                      }
                                                      ?>" class="form-control" type="text"
                                                        name="faq_section_short_headline[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', 'the short headline ') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="faq_section_title<?= $language['code'] ?>"
                                                        class=""><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="faq_section_title<?= $language['code'] ?>" value="<?php
                                                      // Handle both new multi-language format and old single string format
                                                      if (isset($faq_section['title'][$language['code']])) {
                                                          echo $faq_section['title'][$language['code']];
                                                      } else if (is_string($faq_section['title']) && $language['is_default'] == 1) {
                                                          echo $faq_section['title'];
                                                      } else {
                                                          echo "";
                                                      }
                                                      ?>" class="form-control" type="text"
                                                        name="faq_section_title[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'the title') ?> <?= labels('here', ' Here ') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="faq_section_description<?= $language['code'] ?>"
                                                        class=""><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <textarea name="faq_section_description[<?= $language['code'] ?>]"
                                                        id="faq_section_description<?= $language['code'] ?>"
                                                        class="form-control"
                                                        placeholder="<?= labels('description', 'the description') ?> <?= labels('here', ' Here ') ?>"><?php
                                                               // Handle both new multi-language format and old single string format
                                                               if (isset($faq_section['description'][$language['code']])) {
                                                                   echo $faq_section['description'][$language['code']];
                                                               } else if (is_string($faq_section['description']) && $language['is_default'] == 1) {
                                                                   echo $faq_section['description'];
                                                               } else {
                                                                   echo "";
                                                               }
                                                               ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                            <!-- FAQ Items Section -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <label for="faqs" class="form-label fw-bold"><?= labels('faqs', 'FAQs') ?></label>

                                    <?php
                                    // Handle backward compatibility for FAQs data storage
                                    $faqs = [];
                                    $is_multilingual_format = false;

                                    if (isset($faq_section['faqs'])) {
                                        if (is_array($faq_section['faqs'])) {
                                            // Check if it's the new format (array of objects with multilingual structure)
                                            if (!empty($faq_section['faqs']) && isset($faq_section['faqs'][0]) && is_array($faq_section['faqs'][0])) {
                                                // Check if it has the new multilingual structure
                                                if (isset($faq_section['faqs'][0]['question']) && is_array($faq_section['faqs'][0]['question'])) {
                                                    // New format: multilingual structure - keep original structure
                                                    $faqs = $faq_section['faqs'];
                                                    $is_multilingual_format = true;
                                                } else {
                                                    // Old array format: convert to new multilingual format
                                                    $default_lang_code = '';
                                                    foreach ($languages as $lang) {
                                                        if ($lang['is_default'] == 1) {
                                                            $default_lang_code = $lang['code'];
                                                            break;
                                                        }
                                                    }

                                                    $faqs = [];
                                                    foreach ($faq_section['faqs'] as $old_faq) {
                                                        $faqs[] = [
                                                            'question' => [
                                                                $default_lang_code => $old_faq['question'] ?? ''
                                                            ],
                                                            'answer' => [
                                                                $default_lang_code => $old_faq['answer'] ?? ''
                                                            ]
                                                        ];
                                                    }
                                                    $is_multilingual_format = true;
                                                }
                                            }
                                        } else if (is_string($faq_section['faqs'])) {
                                            // Old format: JSON string - convert to new multilingual format
                                            $old_faqs = json_decode($faq_section['faqs'], true) ?: [];
                                            if (!empty($old_faqs)) {
                                                // Convert old format to new multilingual format
                                                // Put old data into default language
                                                $default_lang_code = '';
                                                foreach ($languages as $lang) {
                                                    if ($lang['is_default'] == 1) {
                                                        $default_lang_code = $lang['code'];
                                                        break;
                                                    }
                                                }

                                                $faqs = [];
                                                foreach ($old_faqs as $old_faq) {
                                                    $faqs[] = [
                                                        'question' => [
                                                            $default_lang_code => $old_faq['question'] ?? ''
                                                        ],
                                                        'answer' => [
                                                            $default_lang_code => $old_faq['answer'] ?? ''
                                                        ]
                                                    ];
                                                }
                                                $is_multilingual_format = true;
                                            } else {
                                                $faqs = [];
                                            }
                                        }
                                    }
                                    ?>

                                    <!-- FAQ Items Container -->
                                    <div id="faq_section_faqs_container">
                                        <?php if (empty($faqs)): ?>
                                            <!-- Default empty FAQ -->
                                            <div class="faq-item mb-4 p-3 border rounded" data-faq-index="0">
                                                <!-- Language tabs and remove button on same line -->
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                                        <?php foreach ($languages as $lang_index => $lang): ?>
                                                            <div class="language-faq-item-option position-relative <?= $lang['is_default'] ? 'selected' : '' ?>"
                                                                data-language="<?= $lang['code'] ?>" data-faq-index="0"
                                                                style="cursor: pointer; padding: 0.5rem 0;">
                                                                <span
                                                                    class="language-faq-item-text px-2 <?= $lang['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                                    style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                                    <?= $lang['language'] ?>
                                                                    <?= $lang['is_default'] ? '(Default)' : '' ?>
                                                                </span>
                                                                <div class="language-faq-item-underline position-absolute bottom-0 start-0 bg-primary"
                                                                    style="height: 2px; width: <?= $lang['is_default'] ? '100%' : '0%' ?>; transition: width 0.3s ease;">
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm remove-faq-item"
                                                        style="display: none;">
                                                        <i class="fa fa-trash"></i> Remove
                                                    </button>
                                                </div>

                                                <!-- FAQ content for each language -->
                                                <?php foreach ($languages as $lang_index => $lang): ?>
                                                    <div class="faq-language-content" data-faq-index="0"
                                                        data-language="<?= $lang['code'] ?>"
                                                        style="<?= $lang['is_default'] ? 'display: block;' : 'display: none;' ?>">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label
                                                                        class="form-label"><?= labels('question', 'Question') ?>
                                                                        <?= $lang['is_default'] ? '' : '(' . $lang['code'] . ')' ?></label>
                                                                    <input type="text" class="form-control"
                                                                        name="faqs[0][question][<?= $lang['code'] ?>]"
                                                                        placeholder="<?= labels('enter_question', 'Enter Question') ?>"
                                                                        value="">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label class="form-label"><?= labels('answer', 'Answer') ?>
                                                                        <?= $lang['is_default'] ? '' : '(' . $lang['code'] . ')' ?></label>
                                                                    <textarea class="form-control" rows="3"
                                                                        name="faqs[0][answer][<?= $lang['code'] ?>]"
                                                                        placeholder="<?= labels('enter_answer', 'Enter Answer') ?>"></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <!-- Existing FAQs -->
                                            <?php foreach ($faqs as $faq_index => $faq): ?>
                                                <div class="faq-item mb-4 p-3 border rounded"
                                                    data-faq-index="<?= $faq_index ?>">
                                                    <!-- Language tabs and remove button on same line -->
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <div class="d-flex flex-wrap align-items-center gap-4">
                                                            <?php foreach ($languages as $lang_index => $lang): ?>
                                                                <div class="language-faq-item-option position-relative <?= $lang['is_default'] ? 'selected' : '' ?>"
                                                                    data-language="<?= $lang['code'] ?>"
                                                                    data-faq-index="<?= $faq_index ?>"
                                                                    style="cursor: pointer; padding: 0.5rem 0;">
                                                                    <span
                                                                        class="language-faq-item-text px-2 <?= $lang['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                                        style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                                        <?= $lang['language'] ?>
                                                                        <?= $lang['is_default'] ? '(Default)' : '' ?>
                                                                    </span>
                                                                    <div class="language-faq-item-underline position-absolute bottom-0 start-0 bg-primary"
                                                                        style="height: 2px; width: <?= $lang['is_default'] ? '100%' : '0%' ?>; transition: width 0.3s ease;">
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <button type="button"
                                                            class="btn btn-outline-danger btn-sm remove-faq-item"
                                                            <?= count($faqs) <= 1 ? 'style="display: none;"' : '' ?>>
                                                            <i class="fa fa-minus"></i>
                                                        </button>
                                                    </div>

                                                    <!-- FAQ content for each language -->
                                                    <?php foreach ($languages as $lang_index => $lang): ?>
                                                        <div class="faq-language-content" data-faq-index="<?= $faq_index ?>"
                                                            data-language="<?= $lang['code'] ?>"
                                                            style="<?= $lang['is_default'] ? 'display: block;' : 'display: none;' ?>">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label
                                                                            class="form-label"><?= labels('question', 'Question') ?>
                                                                            <?= $lang['is_default'] ? '' : '(' . $lang['code'] . ')' ?></label>
                                                                        <input type="text" class="form-control"
                                                                            name="faqs[<?= $faq_index ?>][question][<?= $lang['code'] ?>]"
                                                                            placeholder="<?= labels('enter_question', 'Enter Question') ?>"
                                                                            value="<?= esc($is_multilingual_format ? ($faq['question'][$lang['code']] ?? '') : ($lang['is_default'] ? ($faq['question'] ?? '') : '')) ?>">
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label class="form-label"><?= labels('answer', 'Answer') ?>
                                                                            <?= $lang['is_default'] ? '' : '(' . $lang['code'] . ')' ?></label>
                                                                        <textarea class="form-control" rows="3"
                                                                            name="faqs[<?= $faq_index ?>][answer][<?= $lang['code'] ?>]"
                                                                            placeholder="<?= labels('enter_answer', 'Enter Answer') ?>"><?= esc($is_multilingual_format ? ($faq['answer'][$lang['code']] ?? '') : ($lang['is_default'] ? ($faq['answer'] ?? '') : '')) ?></textarea>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Add FAQ Button -->
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-outline-primary" id="add-faq-item">
                                            <i class="fa fa-plus"></i> <?= labels('add_faq', 'Add FAQ') ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($permissions['update']['settings'] == 1): ?>
                <!-- Hidden input for storing selected rating IDs -->
                <input type="hidden" name="new_rating_ids" id="new_rating_ids"
                    value="<?= isset($rating_ids) && is_array($rating_ids) ? implode(',', $rating_ids) : (isset($rating_ids) ? $rating_ids : "") ?>">

                <div class="row mt-3">
                    <div class="col-md d-flex justify-content-end">
                        <input type="submit" name="update" id="update" value="<?= labels('save_changes', "Save") ?>"
                            class="btn btn-lg bg-new-primary">
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </section>
</div>

<div id="ratingsModal" class="modal fade" tabindex="-1" aria-labelledby="ratingsModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('select_rating', 'Select Rating') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php
                $rating_ids = isset($rating_ids) ? $rating_ids : [];
                ?>
                <table class="table table-bordered table-hover" id="slider_list" data-fixed-columns="true"
                    data-pagination-successively-size="2" data-detail-formatter="user_formater" data-auto-refresh="true"
                    data-toggle="table" data-url="<?= base_url("admin/settings/review-list") ?>"
                    data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
                    data-search="false" data-show-columns="false" data-show-columns-search="true"
                    data-show-refresh="false" data-sort-name="id" data-sort-order="desc"
                    data-query-params="review_query_param">
                    <thead>
                        <tr>
                            <th class="text-center multi-check" data-checkbox="true"></th>
                            <th data-field="id" class="text-center" data-visible="false" data-sortable="true">
                                <?= labels('id', 'ID') ?>
                            </th>
                            <th data-field="comment" class="text-center"><?= labels('comment', 'Comment') ?></th>
                            <th data-field="partner_name" class="text-center">
                                <?= labels('provider_name', 'Provider Name') ?>
                            </th>
                            <th data-field="rated_on" class="text-center"><?= labels('rated_on', 'Rated On') ?></th>
                            <th data-field="stars" class="text-center"><?= labels('rating', 'Rating') ?></th>
                            <th data-field="service_name" class="text-center"><?= labels('service', 'Service') ?></th>
                            <th data-field="user_name" class="text-center"><?= labels('username', 'User Name') ?></th>
                        </tr>
                    </thead>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="saveRatings"><?= labels('save', 'Save') ?></button>
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function () {
        const sections = [{
            id: 'hero_section_status',
            status: <?= isset($hero_section['status']) ? $hero_section['status'] : 0 ?>
        },
        {
            id: 'how_it_work_section_status',
            status: <?= isset($how_it_work_section['status']) ? $how_it_work_section['status'] : 0 ?>
        },
        {
            id: 'feature_section_status',
            status: <?= isset($feature_section['status']) ? $feature_section['status'] : 0 ?>
        },
        {
            id: 'category_section_status',
            status: <?= isset($category_section['status']) ? $category_section['status'] : 0 ?>
        },
        {
            id: 'subscription_section_status',
            status: <?= isset($subscription_section['status']) ? $subscription_section['status'] : 0 ?>
        },
        {
            id: 'top_providers_section_status',
            status: <?= isset($top_providers_section['status']) ? $top_providers_section['status'] : 0 ?>
        },
        {
            id: 'review_section_status',
            status: <?= isset($review_section['status']) ? $review_section['status'] : 0 ?>
        },
        {
            id: 'faq_section_status',
            status: <?= isset($faq_section['status']) ? $faq_section['status'] : 0 ?>
        },
        ];
        sections.forEach(function (section) {
            if (section.status == 1) {
                $('#' + section.id).siblings('.switchery').addClass('active-content').removeClass('deactive-content');
            } else {
                $('#' + section.id).siblings('.switchery').addClass('deactive-content').removeClass('active-content');
            }
        });
    });
    $(document).ready(function () {
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
        $(document).ready(function () {
            const sectionIds = [
                'hero_section_status',
                'how_it_work_section_status',
                'feature_section_status',
                'category_section_status',
                'subscription_section_status',
                'top_providers_section_status',
                'review_section_status',
                'faq_section_status'
            ];
            sectionIds.forEach(function (id) {
                var sectionStatus = document.querySelector('#' + id);
                sectionStatus.addEventListener('change', function () {
                    handleSwitchChange(sectionStatus);
                });
            });
        });
    });
    document.addEventListener('DOMContentLoaded', function () {
        let current_how_it_work_section_language = '<?= $current_how_it_work_section_language ?>';
        // How it works section functionality
        let stepsContainer = document.getElementById('how_it_work_section_steps_container' + current_how_it_work_section_language);
        if (!stepsContainer) {
            console.error('Steps container not found');
            return;
        }

        $(document).on('click', '.language-how-it-work-section-option', function () {
            const language = $(this).data('language');

            $('.language-how-it-work-section-underline').css('width', '0%');
            $('#language-how-it-work-section-' + language).find('.language-how-it-work-section-underline').css('width', '100%');

            $('.language-how-it-work-section-text').removeClass('text-primary fw-medium');
            $('.language-how-it-work-section-text').addClass('text-muted');
            $('#language-how-it-work-section-' + language).find('.language-how-it-work-section-text').removeClass('text-muted');
            $('#language-how-it-work-section-' + language).find('.language-how-it-work-section-text').addClass('text-primary');

            if (language != current_how_it_work_section_language) {
                $('#translationHowItWorkSectionDiv-' + language).show();
                $('#translationHowItWorkSectionDiv-' + current_how_it_work_section_language).hide();
            }

            current_how_it_work_section_language = language;
            stepsContainer = document.getElementById('how_it_work_section_steps_container' + language);

            // Handle add step button click
            let addStepButtons = stepsContainer.querySelectorAll('.add-how-it-work-steps');
            addStepButtons.forEach(button => {
                button.addEventListener('click', handleAddStep);
            });

            // // Add event listeners to existing remove buttons
            let removeStepButtons = stepsContainer.querySelectorAll('.remove-how-it-work-steps');
            removeStepButtons.forEach(button => {
                button.addEventListener('click', handleRemoveStep);
            });
        });

        // Show/hide remove button on the first row based on number of steps
        function updateFirstRowRemoveButton() {
            const rows = stepsContainer.querySelectorAll('.input-group');
            const firstRowRemoveBtn = rows[0]?.querySelector('.remove-how-it-work-steps');

            if (firstRowRemoveBtn) {
                if (rows.length > 1) {
                    firstRowRemoveBtn.style.display = 'inline-block';
                } else {
                    firstRowRemoveBtn.style.display = 'none';
                }
            }
        }

        // Handle add step button click
        let addStepButtons = stepsContainer.querySelectorAll('.add-how-it-work-steps');
        addStepButtons.forEach(button => {
            button.addEventListener('click', handleAddStep);
        });

        function handleAddStep() {
            // Determine the next index based on current elements
            const currentCount = stepsContainer.querySelectorAll('.input-group').length;
            const newTagInput = `
            <div class="row input-group mb-2"> 
                <div class="col-md-5"> 
                    <input class="form-control" placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', 'Title') ?> <?= labels('here', ' Here ') ?>" type="text" name="how_it_work_section_steps[${current_how_it_work_section_language}][${currentCount}][title]">
                </div> 
                <div class="col-md-5"> 
                    <input class="form-control" placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('description', 'Description') ?> <?= labels('here', ' Here ') ?>" type="text" name="how_it_work_section_steps[${current_how_it_work_section_language}][${currentCount}][description]"> 
                </div> 
                <div class="col-md-2"> 
                    <button type="button" class="btn btn-outline-danger remove-how-it-work-steps"> 
                        <i class="fa fa-minus"></i>
                    </button>
                </div> 
            </div>`;
            stepsContainer.insertAdjacentHTML('beforeend', newTagInput);

            // Add event listener to the new remove button
            const newRemoveButton = stepsContainer.querySelector('.input-group:last-child .remove-how-it-work-steps');
            if (newRemoveButton) {
                newRemoveButton.addEventListener('click', handleRemoveStep);
            }

            updateFirstRowRemoveButton();
        }

        // Function to handle remove step button click
        function handleRemoveStep() {
            const stepToRemove = this.closest('.input-group');
            if (stepToRemove) {
                stepToRemove.remove();

                // Re-index remaining fields
                const rows = stepsContainer.querySelectorAll('.input-group');
                rows.forEach((row, index) => {
                    const titleInput = row.querySelector('input[name*="[title]"]');
                    const descInput = row.querySelector('input[name*="[description]"]');

                    if (titleInput) {
                        titleInput.name = `how_it_work_section_steps[${current_how_it_work_section_language}][${index}][title]`;
                    }

                    if (descInput) {
                        descInput.name = `how_it_work_section_steps[${current_how_it_work_section_language}][${index}][description]`;
                    }
                });

                updateFirstRowRemoveButton();
            }
        }

        // Add event listeners to existing remove buttons
        let removeStepButtons = stepsContainer.querySelectorAll('.remove-how-it-work-steps');
        removeStepButtons.forEach(button => {
            button.addEventListener('click', handleRemoveStep);
        });

        // Initialize on page load
        updateFirstRowRemoveButton();

        let current_faq_section_language = '<?= $current_faq_section_language ?>';

        // FAQ Section functionality - New multilingual structure
        let faqContainer = document.getElementById('faq_section_faqs_container');

        if (faqContainer) {

            // FAQ language tab switching within each FAQ item
            $(document).on('click', '.language-faq-item-option', function () {
                const language = $(this).data('language');
                const faqIndex = $(this).data('faq-index');

                // Update visual state for this FAQ's language tabs
                $(`.language-faq-item-option[data-faq-index="${faqIndex}"] .language-faq-item-underline`).css('width', '0%');
                $(this).find('.language-faq-item-underline').css('width', '100%');

                $(`.language-faq-item-option[data-faq-index="${faqIndex}"] .language-faq-item-text`).removeClass('text-primary fw-medium').addClass('text-muted');
                $(this).find('.language-faq-item-text').removeClass('text-muted').addClass('text-primary fw-medium');

                // Show/hide content for this FAQ
                $(`.faq-language-content[data-faq-index="${faqIndex}"]`).hide();
                $(`.faq-language-content[data-faq-index="${faqIndex}"][data-language="${language}"]`).show();
            });

            // Add new FAQ item
            $(document).on('click', '#add-faq-item', function () {
                const currentCount = faqContainer.querySelectorAll('.faq-item').length;
                const newFaqHtml = createNewFaqHtml(currentCount);
                faqContainer.insertAdjacentHTML('beforeend', newFaqHtml);
                updateFaqRemoveButtons();
            });

            // Remove FAQ item
            $(document).on('click', '.remove-faq-item', function () {
                if (faqContainer.querySelectorAll('.faq-item').length > 1) {
                    $(this).closest('.faq-item').remove();
                    reindexFaqItems();
                    updateFaqRemoveButtons();
                }
            });

            // Helper function to create new FAQ HTML
            function createNewFaqHtml(index) {
                const languages = <?= json_encode($languages) ?>;
                let languageTabsHtml = '';
                let languageContentHtml = '';

                // Generate language tabs
                languages.forEach((lang, langIndex) => {
                    const isDefault = lang.is_default == 1;
                    languageTabsHtml += `
                        <div class="language-faq-item-option position-relative ${isDefault ? 'selected' : ''}"
                            data-language="${lang.code}" data-faq-index="${index}"
                            style="cursor: pointer; padding: 0.5rem 0;">
                            <span class="language-faq-item-text px-2 ${isDefault ? 'text-primary fw-medium' : 'text-muted'}"
                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                ${lang.language}${isDefault ? '(Default)' : ''}
                            </span>
                            <div class="language-faq-item-underline position-absolute bottom-0 start-0 bg-primary"
                                style="height: 2px; width: ${isDefault ? '100%' : '0%'}; transition: width 0.3s ease;">
                            </div>
                        </div>`;

                    // Generate language content
                    languageContentHtml += `
                        <div class="faq-language-content" data-faq-index="${index}" data-language="${lang.code}" 
                            style="${isDefault ? 'display: block;' : 'display: none;'}">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label"><?= labels('question', 'Question') ?> ${isDefault ? '' : '(' + lang.code + ')'}</label>
                                        <input type="text" class="form-control" 
                                            name="faqs[${index}][question][${lang.code}]" 
                                            placeholder="<?= labels('enter_question', 'Enter Question') ?>" value="">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label"><?= labels('answer', 'Answer') ?> ${isDefault ? '' : '(' + lang.code + ')'}</label>
                                        <textarea class="form-control" rows="3"
                                            name="faqs[${index}][answer][${lang.code}]" 
                                            placeholder="<?= labels('enter_answer', 'Enter Answer') ?>"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                });

                return `
                    <div class="faq-item mb-4 p-3 border rounded" data-faq-index="${index}">
                        <!-- Language tabs and remove button on same line -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                ${languageTabsHtml}
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm remove-faq-item">
                                <i class="fa fa-minus"></i>
                            </button>
                        </div>
                        
                        <!-- FAQ content for each language -->
                        ${languageContentHtml}
                    </div>`;
            }

            // Helper function to reindex FAQ items
            function reindexFaqItems() {
                const faqItems = faqContainer.querySelectorAll('.faq-item');
                faqItems.forEach((item, index) => {
                    // Update data-faq-index
                    item.setAttribute('data-faq-index', index);

                    // Update language tab data-faq-index
                    const languageTabs = item.querySelectorAll('.language-faq-item-option');
                    languageTabs.forEach(tab => {
                        tab.setAttribute('data-faq-index', index);
                    });

                    // Update content data-faq-index and form names
                    const languageContents = item.querySelectorAll('.faq-language-content');
                    languageContents.forEach(content => {
                        content.setAttribute('data-faq-index', index);

                        // Update input names to use the new faqs[index] format
                        const inputs = content.querySelectorAll('input, textarea');
                        inputs.forEach(input => {
                            const name = input.getAttribute('name');
                            if (name) {
                                // Replace both old formats: faq_section_faqs[index] and faqs[index]
                                const newName = name.replace(/faq_section_faqs\[\d+\]|faqs\[\d+\]/, `faqs[${index}]`);
                                input.setAttribute('name', newName);
                            }
                        });
                    });
                });
            }

            // Helper function to update remove button visibility
            function updateFaqRemoveButtons() {
                const faqItems = faqContainer.querySelectorAll('.faq-item');
                const removeButtons = faqContainer.querySelectorAll('.remove-faq-item');

                removeButtons.forEach(button => {
                    if (faqItems.length <= 1) {
                        button.style.display = 'none';
                    } else {
                        button.style.display = 'inline-block';
                    }
                });
            }

            // Initialize FAQ functionality
            updateFaqRemoveButtons();
        }

        // FAQ Section language tab switching
        $(document).on('click', '.language-faq-section-option', function () {
            const language = $(this).data('language');

            // Update visual state for FAQ section language tabs
            $('.language-faq-section-option .language-faq-section-underline').css('width', '0%');
            $(this).find('.language-faq-section-underline').css('width', '100%');

            $('.language-faq-section-option .language-faq-section-text').removeClass('text-primary fw-medium').addClass('text-muted');
            $(this).find('.language-faq-section-text').removeClass('text-muted').addClass('text-primary fw-medium');

            // Show/hide FAQ section content for different languages
            $('[id^="translationFaqSectionDiv-"]').hide();
            $(`#translationFaqSectionDiv-${language}`).show();
        });
    });

    // Function to mark an image for removal
    function markImageForRemoval(button, index) {
        const parentContainer = button.closest('.position-relative');
        const removeFlag = parentContainer.querySelector('.remove-flag');

        if (removeFlag) {
            // Toggle the removal state
            if (removeFlag.value === "0") {
                // Mark for removal
                removeFlag.value = "1";
                parentContainer.querySelector('img').style.opacity = "0.5";
                button.classList.remove('btn-danger');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="fa fa-undo"></i>';
            } else {
                // Unmark for removal
                removeFlag.value = "0";
                parentContainer.querySelector('img').style.opacity = "1";
                button.classList.remove('btn-success');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="fa fa-times"></i>';
            }
        }
    }
</script>
<?php if (count($rating_ids) > 2): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var viewMoreButton = document.getElementById('view-more-ratings');
            if (viewMoreButton) {
                viewMoreButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    document.querySelectorAll('.more-ratings').forEach(rating => rating.classList.remove('d-none'));
                    this.style.display = 'none';
                });
            }
        });
    </script>
<?php endif; ?>
<script>
    $(document).ready(function () {
        // Get the rating IDs from the review section data
        var reviewSection = <?= json_encode($review_section ?? []); ?>;
        var ratingIds = [];

        // Process rating IDs from review section
        if (reviewSection.rating_ids) {
            if (typeof reviewSection.rating_ids === 'string') {
                ratingIds = reviewSection.rating_ids.split(',').map(function (id) {
                    return parseInt(id.trim());
                }).filter(function (id) {
                    return !isNaN(id) && id > 0;
                });
            } else if (Array.isArray(reviewSection.rating_ids)) {
                if (reviewSection.rating_ids.length > 0 && typeof reviewSection.rating_ids[0] === 'string') {
                    ratingIds = reviewSection.rating_ids[0].split(',').map(function (id) {
                        return parseInt(id.trim());
                    }).filter(function (id) {
                        return !isNaN(id) && id > 0;
                    });
                } else {
                    ratingIds = reviewSection.rating_ids.map(function (id) {
                        return parseInt(id);
                    }).filter(function (id) {
                        return !isNaN(id) && id > 0;
                    });
                }
            }
        }

        $('#slider_list').bootstrapTable({
            onLoadSuccess: function (data) {
                setTimeout(function () {
                    if (ratingIds.length > 0) {
                        // Try different approaches to check the rows
                        ratingIds.forEach(function (id) {
                            // Method 1: Using checkBy with field and values
                            $('#slider_list').bootstrapTable('checkBy', {
                                field: 'id',
                                values: [id]
                            });

                            // Method 2: Using check with index (backup method)
                            var rowIndex = -1;
                            if (data && data.rows) {
                                for (var i = 0; i < data.rows.length; i++) {
                                    if (parseInt(data.rows[i].id) === parseInt(id)) {
                                        rowIndex = i;
                                        break;
                                    }
                                }
                                if (rowIndex >= 0) {
                                    $('#slider_list').bootstrapTable('check', rowIndex);
                                }
                            }
                        });
                    }
                }, 100); // Increased timeout for better reliability
            },
            onCheck: function (row) {
                var rowId = parseInt(row.id);
                if (!ratingIds.includes(rowId)) {
                    ratingIds.push(rowId);

                }
            },
            onUncheck: function (row) {
                var rowId = parseInt(row.id);
                ratingIds = ratingIds.filter(id => id !== rowId);
            }
        });
        $('#saveRatings').click(function () {
            // Convert array to comma-separated string
            var ratingIdsString = ratingIds.join(',');

            // CRITICAL FIX: Update the hidden input field with selected rating IDs
            $('#new_rating_ids').val(ratingIdsString);
            $('#ratingsModal').modal('hide');
        });
        document.getElementById('select-ratings').addEventListener('click', function () {
            $('#ratingsModal').modal('show');
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('feature_section_features_container');
        let sectionIndex = container.querySelectorAll('.card').length;

        let current_feature_section_language = '<?= $current_feature_section_language ?>';

        $(document).on('click', '.language-feature-section-option', function () {
            const language = $(this).data('language');

            $('.language-feature-section-underline').css('width', '0%');
            $('#language-feature-section-' + language).find('.language-feature-section-underline').css('width', '100%');

            $('.language-feature-section-text').removeClass('text-primary fw-medium');
            $('.language-feature-section-text').addClass('text-muted');
            $('#language-feature-section-' + language).find('.language-feature-section-text').removeClass('text-muted');
            $('#language-feature-section-' + language).find('.language-feature-section-text').addClass('text-primary');

            if (language != current_feature_section_language) {
                $('.translationFeatureSectionDiv-' + language).show();
                $('.translationFeatureSectionDiv-' + current_feature_section_language).hide();
            }

            current_feature_section_language = language;
        });

        // Handle all click events in the container
        container.addEventListener('click', function (event) {
            // Handle Add Feature button
            if (event.target.closest('.add-feature-section')) {
                const newSection = `
                <div class="card">
                    <div class="d-flex justify-content-between align-items-center m-0 border_bottom_for_cards">
                        <div class="col-auto">
                            <div class="my-3 toggleButttonPostition"><?= labels('feature', "Feature") ?> ${sectionIndex + 1}</div>
                        </div>
                        <div class="col d-flex justify-content-end">
                            <button type="button" class="btn btn-outline-danger remove-feature-section">
                                <i class="fa fa-minus"></i>
                                <label class="m-0"><?= labels('remove_feature', "Remove Feature") ?></label>
                            </button>
                        </div>
                    </div>
                    <div class="row feature-section-card card-body mb-3" data-section-index="${sectionIndex}">
                        <?php
                        foreach ($languages as $index => $language) {
                            ?>
                        <div class="col-md-12 translationFeatureSectionDiv-<?= $language['code'] ?>" ${'<?= $language['code'] ?>' == current_feature_section_language ? 'style="display: block;"' : 'style="display: none;"'}>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class=""><?= labels('short_headline', "Short Headline") ?> <?= $language['is_default'] ? '' : ' (' . $language['code'] . ')' ?></label>
                                        <input class="form-control" type="text" 
                                            name="feature_section_feature[${sectionIndex}][<?= $language['code'] ?>][short_headline]" 
                                            placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('short_headline', "short headline") ?> <?= labels('here', ' Here ') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class=""><?= labels('title', "Title") ?> <?= $language['is_default'] ? '' : ' (' . $language['code'] . ')' ?></label>
                                        <input class="form-control" type="text" 
                                            name="feature_section_feature[${sectionIndex}][<?= $language['code'] ?>][title]" 
                                            placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('title', "title") ?> <?= labels('here', ' Here ') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class=""><?= labels('description', "Description") ?> <?= $language['is_default'] ? '' : ' (' . $language['code'] . ')' ?></label>
                                        <textarea name="feature_section_feature[${sectionIndex}][<?= $language['code'] ?>][description]" 
                                            class="form-control" 
                                            placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('description', "description") ?> <?= labels('here', ' Here ') ?>"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class=""><?= labels('position', "Position") ?></label>
                                <select class="form-control" name="feature_section_feature[${sectionIndex}][position]">
                                    <option disabled selected><?= labels('select_position', "Select Position") ?></option>
                                    <option value="right"><?= labels('right', "Right") ?></option>
                                    <option value="left"><?= labels('left', "Left") ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= labels('image', "Image") ?></label> <small>(<?= labels('become_provider_feature_section_image_recommended_size', 'We recommend 645 x 645 pixels') ?>)</small>
                                <input name="feature_section_feature[${sectionIndex}][image]" type="file" class="filepond logo" accept="image/*">
                                <input type="hidden" name="feature_section_feature[${sectionIndex}][exist_image]" value="new">
                                <input type="hidden" name="feature_section_feature[${sectionIndex}][exist_disk]" value="<?= fetch_current_file_manager(); ?>">
                            </div>
                        </div>
                    </div>
                </div>`;

                container.insertAdjacentHTML('beforeend', newSection);

                const fileInput = container.querySelector(`.feature-section-card[data-section-index="${sectionIndex}"] .filepond.logo`);

                if (fileInput) {
                    FilePond.create(fileInput, {
                        credits: null,
                        allowFileSizeValidation: true,
                        maxFileSize: "25MB",
                        labelMaxFileSizeExceeded: "File is too large",
                        labelMaxFileSize: "Maximum file size is {filesize}",
                        allowFileTypeValidation: true,
                        acceptedFileTypes: ["image/*"],
                        labelFileTypeNotAllowed: "File of invalid type",
                        fileValidateTypeLabelExpectedTypes: "Expects {allButLastType} or {lastType}",
                        storeAsFile: true,
                        allowPdfPreview: true,
                        pdfPreviewHeight: 320,
                        pdfComponentExtraParams: "toolbar=0&navpanes=0&scrollbar=0&view=fitH",
                        allowVideoPreview: true,
                        allowAudioPreview: true,
                    });
                } else {
                    console.error("File input not found for section index:", sectionIndex);
                }

                sectionIndex++;

                // Update all section indices
                const sections = container.querySelectorAll('.card');
                sections.forEach((section, index) => {
                    const label = section.querySelector('.toggleButttonPostition');
                    if (label) {
                        label.textContent = `<?= labels('feature', "Feature") ?> ${index + 1}`;
                    }
                    const featureSection = section.querySelector('.feature-section-card');
                    if (featureSection) {
                        featureSection.setAttribute('data-section-index', index);
                    }
                });
            }

            // Handle Remove Feature button
            if (event.target.closest('.remove-feature-section')) {
                const section = event.target.closest('.card');
                if (section && container.querySelectorAll('.card').length > 1) {
                    section.remove();

                    // Update remaining section indices
                    const sections = container.querySelectorAll('.card');
                    sections.forEach((section, index) => {
                        const label = section.querySelector('.toggleButttonPostition');
                        if (label) {
                            label.textContent = `<?= labels('feature', "Feature") ?> ${index + 1}`;
                        }
                        const featureSection = section.querySelector('.feature-section-card');
                        if (featureSection) {
                            featureSection.setAttribute('data-section-index', index);
                        }
                    });
                    sectionIndex = sections.length;
                }
            }
        });
    });
</script>

<script>
    function review_query_param(p) {
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
        };
    }
</script>
<script>
    $(document).ready(function () {
        // select default language
        let current_hero_section_language = '<?= $current_hero_section_language ?>';

        $(document).on('click', '.language-hero-section-option', function () {
            const language = $(this).data('language');

            $('.language-hero-section-underline').css('width', '0%');
            $('#language-hero-section-' + language).find('.language-hero-section-underline').css('width', '100%');

            $('.language-hero-section-text').removeClass('text-primary fw-medium');
            $('.language-hero-section-text').addClass('text-muted');
            $('#language-hero-section-' + language).find('.language-hero-section-text').removeClass('text-muted');
            $('#language-hero-section-' + language).find('.language-hero-section-text').addClass('text-primary');

            if (language != current_hero_section_language) {
                $('#translationHeroSectionDiv-' + language).show();
                $('#translationHeroSectionDiv-' + current_hero_section_language).hide();
            }

            current_hero_section_language = language;
        });

        let current_category_section_language = '<?= $current_category_section_language ?>';

        $(document).on('click', '.language-category-section-option', function () {
            const language = $(this).data('language');

            $('.language-category-section-underline').css('width', '0%');
            $('#language-category-section-' + language).find('.language-category-section-underline').css('width', '100%');

            $('.language-category-section-text').removeClass('text-primary fw-medium');
            $('.language-category-section-text').addClass('text-muted');
            $('#language-category-section-' + language).find('.language-category-section-text').removeClass('text-muted');
            $('#language-category-section-' + language).find('.language-category-section-text').addClass('text-primary');

            if (language != current_category_section_language) {
                $('#translationCategorySectionDiv-' + language).show();
                $('#translationCategorySectionDiv-' + current_category_section_language).hide();
            }

            current_category_section_language = language;
        });

        let current_subscription_section_language = '<?= $current_subscription_section_language ?>';

        $(document).on('click', '.language-subscription-section-option', function () {
            const language = $(this).data('language');

            $('.language-subscription-section-underline').css('width', '0%');
            $('#language-subscription-section-' + language).find('.language-subscription-section-underline').css('width', '100%');

            $('.language-subscription-section-text').removeClass('text-primary fw-medium');
            $('.language-subscription-section-text').addClass('text-muted');
            $('#language-subscription-section-' + language).find('.language-subscription-section-text').removeClass('text-muted');
            $('#language-subscription-section-' + language).find('.language-subscription-section-text').addClass('text-primary');

            if (language != current_subscription_section_language) {
                $('#translationSubscriptionSectionDiv-' + language).show();
                $('#translationSubscriptionSectionDiv-' + current_subscription_section_language).hide();
            }

            current_subscription_section_language = language;
        });

        let current_top_providers_section_language = '<?= $current_top_providers_section_language ?>';

        $(document).on('click', '.language-top-providers-section-option', function () {
            const language = $(this).data('language');

            $('.language-top-providers-section-underline').css('width', '0%');
            $('#language-top-providers-section-' + language).find('.language-top-providers-section-underline').css('width', '100%');

            $('.language-top-providers-section-text').removeClass('text-primary fw-medium');
            $('.language-top-providers-section-text').addClass('text-muted');
            $('#language-top-providers-section-' + language).find('.language-top-providers-section-text').removeClass('text-muted');
            $('#language-top-providers-section-' + language).find('.language-top-providers-section-text').addClass('text-primary');

            if (language != current_top_providers_section_language) {
                $('#translationTopProvidersSectionDiv-' + language).show();
                $('#translationTopProvidersSectionDiv-' + current_top_providers_section_language).hide();
            }

            current_top_providers_section_language = language;
        });

        let current_review_section_language = '<?= $current_review_section_language ?>';

        $(document).on('click', '.language-review-section-option', function () {
            const language = $(this).data('language');

            $('.language-review-section-underline').css('width', '0%');
            $('#language-review-section-' + language).find('.language-review-section-underline').css('width', '100%');

            $('.language-review-section-text').removeClass('text-primary fw-medium');
            $('.language-review-section-text').addClass('text-muted');
            $('#language-review-section-' + language).find('.language-review-section-text').removeClass('text-muted');
            $('#language-review-section-' + language).find('.language-review-section-text').addClass('text-primary');

            if (language != current_review_section_language) {
                $('#translationReviewSectionDiv-' + language).show();
                $('#translationReviewSectionDiv-' + current_review_section_language).hide();
            }

            current_review_section_language = language;
        });

        $('form[action*="become-provider-setting-update"]').on('submit', function (e) {
            e.preventDefault();
            var $btn = $(this).find('[type="submit"]');
            var originalVal = $btn.val();
            $btn.prop('disabled', true);
            var formData = new FormData(this);
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res.error === false) {
                        showToastMessage(res.message, 'success');
                    } else {
                        showToastMessage(res.message, 'error');
                    }
                    setTimeout(function () { window.location.reload(); }, 3000);
                },
                error: function () {
                    showToastMessage('<?= labels("something_went_wrong", "Something went wrong") ?>', 'error');
                    setTimeout(function () { window.location.reload(); }, 3000);
                },
                complete: function () {
                    $btn.prop('disabled', false).val(originalVal);
                }
            });
        });
    });
</script>