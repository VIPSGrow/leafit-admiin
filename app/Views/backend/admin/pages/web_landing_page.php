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
                    <a class="nav-link active" href="<?= base_url('admin/settings/web-landing-page-settings') ?>"
                        id="pills-about_us">
                        <?= labels('landing_page_settings', "Landing Page Settings") ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/become-provider-setting') ?>"
                        id="pills-about_us">
                        <?= labels('become_provider_page_settings', "Become Provider Page Settings") ?>
                    </a>
                </li>
            </div>
        </ul>
        <?= form_open_multipart(base_url('admin/settings/web-landing-page-settings-update')) ?>

        <div class="row mb-4">
            <!-- Disable Landing Page Settings Section -->
            <div class="col-12">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col">
                            <div class="toggleButttonPostition">
                                <?= labels('disable_landing_page_settings', "Disable Landing Page Settings") ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4">
                            <input type="checkbox" class="status-switch" id="disable_landing_page_settings"
                                name="disable_landing_page_settings_status"
                                <?= isset($disable_landing_page_settings_status) && ($disable_landing_page_settings_status == "1" || $disable_landing_page_settings_status == 1 || $disable_landing_page_settings_status == "on") ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="notification_div" class="alert alert-primary alert-has-icon">
                            <div class="alert-body">
                                <div id="status" class=""><i class="fa-solid fa-circle-exclamation mr-2"></i>
                                    <?= labels('note_for_landing_page_settings', "Note: If you disable showing the landing page settings, then the latitude and longitude set below will be used by default") ?>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for='default_latitude'><?= labels('latitude', "Latitude") ?></label>
                                    <input type="text" name="default_latitude" class="form-control custome_reset"
                                        id="default_latitude"
                                        value="<?= isset($default_latitude) ? $default_latitude : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for='default_longitude'><?= labels('longitude', "Longitude") ?></label>
                                    <input type="text" name="default_longitude" class="form-control custome_reset"
                                        id="default_longitude"
                                        value="<?= isset($default_longitude) ? $default_longitude : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <!-- Logos Section -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col">
                            <div class="toggleButttonPostition"><?= labels('logos', "Logos") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label
                                        for='landing_page_logo'><?= labels('landing_page_logo', "Landing Page Logo") ?></label>
                                    <small>(<?= labels('web_logo_recommended_size', 'We recommend 182 x 60 pixels') ?>)</small>
                                    <input type="file" name="landing_page_logo" class="filepond logo"
                                        id="landing_page_logo" accept="image/*">
                                    <?php

                                    $disk = fetch_current_file_manager();

                                    if (isset($disk)) {
                                        if ($disk === "aws_s3") {
                                            $landing_page_logo = fetch_cloud_front_url('web_settings', $landing_page_logo);
                                        } elseif ($disk === "local_server") {
                                            $landing_page_logo = base_url("public/uploads/web_settings/" . $landing_page_logo);
                                        }
                                    } else {
                                        $landing_page_logo = base_url('public/backend/assets/img/news/img01.jpg'); // Default logo
                                    }
                                    if (isset($disk)) {
                                        if ($disk === "aws_s3") {
                                            $landing_page_backgroud_image = fetch_cloud_front_url('web_settings', $landing_page_backgroud_image);
                                        } elseif ($disk === "local_server") {
                                            $landing_page_backgroud_image = base_url("public/uploads/web_settings/" . $landing_page_backgroud_image);
                                        }
                                    } else {
                                        $landing_page_backgroud_image = base_url('public/backend/assets/img/news/img01.jpg'); // Default logo
                                    }
                                    ?>
                                    <img class="settings_logo" src="<?= $landing_page_logo; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label
                                        for='landing_page_backgroud_image'><?= labels('landing_page_image', "Landing Page Image") ?></label>
                                    <small
                                        class="text-muted">(<?= labels('web_size_slider_recommend', 'We recommend 1920 x 800 pixels') ?>)</small>
                                    <input type="file" name="landing_page_backgroud_image" class="filepond logo"
                                        id="landing_page_backgroud_image" accept="image/*">
                                    <img class="settings_logo" src="<?= $landing_page_backgroud_image; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- FAQ Section -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('faq_section', "FAQ Section") ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4">
                            <input type="checkbox" class="status-switch" id="faq_section_status"
                                name="faq_section_status" <?= isset($faq_section_status) && $faq_section_status == "1" ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                foreach ($languages as $index => $language) {
                                    if ($language['is_default'] == 1) {
                                        $current_faq_language = $language['code'];
                                    }
                                    ?>
                                    <div class="language-faq-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-faq-<?= $language['code'] ?>" data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span
                                            class="language-faq-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?>    <?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-faq-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php
                        foreach ($languages as $index => $language) {
                            ?>
                            <div id="translationFaqDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_faq_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="form-group">
                                    <label
                                        for='faq_section_title<?= $language['code'] ?>'><?= labels('faq_section_title', "FAQ Section Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input type='text' class="form-control custome_reset"
                                        name='faq_section_title[<?= $language['code'] ?>]'
                                        id='faq_section_title<?= $language['code'] ?>'
                                        value="<?php
                                        // Handle both new multi-language format and old single string format
                                        if (isset($faq_section_title[$language['code']])) {
                                            echo $faq_section_title[$language['code']];
                                        } else if (is_string($faq_section_title) && $language['is_default'] == 1) {
                                            echo $faq_section_title;
                                        } else {
                                            echo "";
                                        }
                                        ?>">
                                </div>
                                <div class="form-group">
                                    <label
                                        for='faq_section_description<?= $language['code'] ?>'><?= labels('faq_section_description', "FAQ Section Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input type='text' class="form-control custome_reset"
                                        name='faq_section_description[<?= $language['code'] ?>]'
                                        id='faq_section_description<?= $language['code'] ?>'
                                        value="<?php
                                        // Handle both new multi-language format and old single string format
                                        if (isset($faq_section_description[$language['code']])) {
                                            echo $faq_section_description[$language['code']];
                                        } else if (is_string($faq_section_description) && $language['is_default'] == 1) {
                                            echo $faq_section_description;
                                        } else {
                                            echo "";
                                        }
                                        ?>">
                                </div>
                            </div>
                        <?php } ?>
                        <div id="notification_div" class="alert alert-primary alert-has-icon">
                            <div class="alert-icon"><i class="fa-solid fa-circle-exclamation mr-2"></i></div>
                            <div class="alert-body">
                                <div id="status" class="">
                                    <?= labels('you_can_add_data_from', "You can add data from") ?> <a
                                        href="<?= base_url('admin/faqs') ?>"><?= labels('faqs', "FAQs") ?></a> </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <!-- Rating Section -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('rating_section', "Rating Section") ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4">
                            <input type="checkbox" class="status-switch" id="rating_section_status"
                                name="rating_section_status" <?= isset($rating_section_status) && $rating_section_status == "1" ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="new_rating_ids[]" id="new_rating_ids" value=<?= isset($rating_ids[0]) ? $rating_ids[0] : "" ?>>
                        <div class="mb-3">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                foreach ($languages as $index => $language) {
                                    if ($language['is_default'] == 1) {
                                        $current_rating_language = $language['code'];
                                    }
                                    ?>
                                    <div class="language-rating-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-rating-<?= $language['code'] ?>"
                                        data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span
                                            class="language-rating-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?>    <?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-rating-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php
                        foreach ($languages as $index => $language) {
                            ?>
                            <div id="translationRatingDiv-<?= $language['code'] ?>" <?= $language['is_default'] == 1 ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="form-group">
                                    <label
                                        for='rating_section_title<?= $language['code'] ?>'><?= labels('rating_section_title', "Rating Section Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input type='text' class="form-control custome_reset"
                                        name='rating_section_title[<?= $language['code'] ?>]'
                                        id='rating_section_title<?= $language['code'] ?>'
                                        value="<?php
                                        // Handle both new multi-language format and old single string format
                                        if (isset($rating_section_title[$language['code']])) {
                                            echo $rating_section_title[$language['code']];
                                        } else if (is_string($rating_section_title) && $language['is_default'] == 1) {
                                            echo $rating_section_title;
                                        } else {
                                            echo "";
                                        }
                                        ?>">
                                </div>
                                <div class="form-group">
                                    <label
                                        for='rating_section_description<?= $language['code'] ?>'><?= labels('rating_section_description', "Rating Section Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input type='text' class="form-control custome_reset"
                                        name='rating_section_description[<?= $language['code'] ?>]'
                                        id='rating_section_description<?= $language['code'] ?>'
                                        value="<?php
                                        // Handle both new multi-language format and old single string format
                                        if (isset($rating_section_description[$language['code']])) {
                                            echo $rating_section_description[$language['code']];
                                        } else if (is_string($rating_section_description) && $language['is_default'] == 1) {
                                            echo $rating_section_description;
                                        } else {
                                            echo "";
                                        }
                                        ?>">
                                </div>
                            </div>
                        <?php } ?>
                        <button id="select-ratings" type="button"
                            class="btn btn-primary"><?= labels('select_ratings', "Select ratings") ?></button>
                        <div class="col-12 mt-2">
                            <div class="form-group mb-0">
                                <label
                                    for='rating_section_id'><?= labels('selected_ratings', "Selected Ratings") ?></label>
                            </div>
                            <div id="selected-ratings">
                                <?php
                                if (isset($rating_ids) && is_array($rating_ids) && isset($rating_ids[0])) {
                                    $rating_ids = explode(',', $rating_ids[0]);
                                } else {
                                    $rating_ids = [];
                                }
                                $rating_map = array_column($services_ratings, null, 'id');
                                foreach ($rating_ids as $index => $id):
                                    if (isset($rating_map[$id])):
                                        $rating = $rating_map[$id];
                                        ?>
                                        <div
                                            class="card author-box card-primary <?= $index >= 2 ? 'd-none more-ratings' : '' ?>">
                                            <div class="card-body">
                                                <div class="author-box-left">
                                                    <img alt="image" src="<?= $rating['profile_image'] ?>"
                                                        class="rounded-circle author-box-picture">
                                                </div>
                                                <div class="author-box-details">
                                                    <div class="author-box-name"><?= $rating['username'] ?></div>
                                                    <?php
                                                    $created_at = isset($rating['created_at']) ? $rating['created_at'] : '';
                                                    $formatted_date = $created_at ? (new DateTime($created_at))->format('j M Y, g:i A') : '';
                                                    ?>
                                                    <div class="author-box-job">
                                                        <?= htmlspecialchars($formatted_date, ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="author-box-description">
                                                        <p class="p-0 m-0">
                                                            <?= htmlspecialchars($rating['comment'], ENT_QUOTES, 'UTF-8') ?></p>
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
            <!-- Category Section -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('category_section', "Category Section") ?>
                            </div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4">
                            <input type="checkbox" class="status-switch" id="category_section_status"
                                name="category_section_status" <?= isset($category_section_status) && $category_section_status == "1" ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                foreach ($languages as $index => $language) {
                                    if ($language['is_default'] == 1) {
                                        $current_category_language = $language['code'];
                                    }
                                    ?>
                                    <div class="language-category-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-category-<?= $language['code'] ?>"
                                        data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span
                                            class="language-category-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?>    <?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-category-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php
                        foreach ($languages as $index => $language) {
                            ?>
                            <div id="translationCategoryDiv-<?= $language['code'] ?>" <?= $language['is_default'] == 1 ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="form-group">
                                    <label
                                        for='category_section_title<?= $language['code'] ?>'><?= labels('category_section_title', "Category Section Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input type='text' class="form-control custome_reset"
                                        name='category_section_title[<?= $language['code'] ?>]'
                                        id='category_section_title<?= $language['code'] ?>'
                                        value="<?php
                                        // Handle both new multi-language format and old single string format
                                        if (isset($category_section_title[$language['code']])) {
                                            echo $category_section_title[$language['code']];
                                        } else if (is_string($category_section_title) && $language['is_default'] == 1) {
                                            echo $category_section_title;
                                        } else {
                                            echo "";
                                        }
                                        ?>">
                                </div>
                                <div class="form-group">
                                    <label
                                        for='category_section_description<?= $language['code'] ?>'><?= labels('category_section_description', "Category Section Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input type='text' class="form-control custome_reset"
                                        name='category_section_description[<?= $language['code'] ?>]'
                                        id='category_section_description<?= $language['code'] ?>'
                                        value="<?php
                                        // Handle both new multi-language format and old single string format
                                        if (isset($category_section_description[$language['code']])) {
                                            echo $category_section_description[$language['code']];
                                        } else if (is_string($category_section_description) && $language['is_default'] == 1) {
                                            echo $category_section_description;
                                        } else {
                                            echo "";
                                        }
                                        ?>">
                                </div>
                            </div>
                        <?php } ?>
                        <?php $category_ids = isset($category_ids) ? $category_ids : [] ?>
                        <div class="col-md-6">
                            <div class="categories form-group" id="categories">
                                <label for="category_item"
                                    class="required"><?= labels('choose_a_category', 'Choose a Category') ?></label>
                                <select id="category_item" class="form-control select2" name="categories[]" multiple
                                    style="margin-bottom: 20px;">
                                    <option value=""> <?= labels('select', 'Select') ?>
                                        <?= labels('category', 'Category') ?> </option>
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
        </div>
        <div class="row mb-4">
            <div class="col-md-12 col-sm-12 col-xl-12">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('process_flow', "Process Flow") ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4">
                            <input type="checkbox" class="status-switch" id="process_flow_status"
                                name="process_flow_status" <?= isset($process_flow_status) && $process_flow_status == "1" ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_process_flow_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-process-flow-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-process-flow-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-process-flow-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>    <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-process-flow-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php
                        foreach ($languages as $index => $language) {
                            ?>
                            <div id="translationProcessFlowDiv-<?= $language['code'] ?>" <?= $language['is_default'] == 1 ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label
                                                for='web_tagline<?= $language['code'] ?>'><?= labels('landing_page_title', "Landing Page Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <input type='text' class="form-control custome_reset"
                                                name='landing_page_title[<?= $language['code'] ?>]'
                                                id='landing_page_title<?= $language['code'] ?>'
                                                value="<?php
                                                // Handle both new multi-language format and old single string format
                                                if (isset($landing_page_title[$language['code']])) {
                                                    echo $landing_page_title[$language['code']];
                                                } else if (is_string($landing_page_title) && $language['is_default'] == 1) {
                                                    echo $landing_page_title;
                                                } else {
                                                    echo "";
                                                }
                                                ?>" />
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label
                                                for='process_flow_title<?= $language['code'] ?>'><?= labels('process_flow_title', "Process Flow Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <input type='text' class="form-control custome_reset"
                                                name='process_flow_title[<?= $language['code'] ?>]'
                                                id='process_flow_title<?= $language['code'] ?>'
                                                value="<?php
                                                // Handle both new multi-language format and old single string format
                                                if (isset($process_flow_title[$language['code']])) {
                                                    echo $process_flow_title[$language['code']];
                                                } else if (is_string($process_flow_title) && $language['is_default'] == 1) {
                                                    echo $process_flow_title;
                                                } else {
                                                    echo "";
                                                }
                                                ?>" />
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label
                                                for='process_flow_description<?= $language['code'] ?>'><?= labels('process_flow_description', "Process Flow Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <textarea rows=3 class='form-control h-50 custome_reset'
                                                name="process_flow_description[<?= $language['code'] ?>]"
                                                id='process_flow_description<?= $language['code'] ?>'><?php
                                                  // Handle both new multi-language format and old single string format
                                                  if (isset($process_flow_description[$language['code']])) {
                                                      echo $process_flow_description[$language['code']];
                                                  } else if (is_string($process_flow_description) && $language['is_default'] == 1) {
                                                      echo $process_flow_description;
                                                  } else {
                                                      echo "";
                                                  }
                                                  ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label
                                                for='footer_description<?= $language['code'] ?>'><?= labels('footer_Description', "Footer Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <textarea rows=3 class='form-control h-50 custome_reset'
                                                name="footer_description[<?= $language['code'] ?>]"
                                                id='footer_description<?= $language['code'] ?>'><?php
                                                  // Handle both new multi-language format and old single string format
                                                  if (isset($footer_description[$language['code']])) {
                                                      echo $footer_description[$language['code']];
                                                  } else if (is_string($footer_description) && $language['is_default'] == 1) {
                                                      echo $footer_description;
                                                  } else {
                                                      echo "";
                                                  }
                                                  ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <!-- </div> -->
                        <!-- Step 1 -->
                        <div class="row">
                            <div class="col-12 mb-3">
                                <h5><?= labels('step_1', "Step 1") ?></h5>
                            </div>
                            <?php
                            foreach ($languages as $index => $language) {
                                ?>
                                <div class="col-md-6" id="translationStep1Div-<?= $language['code'] ?>"
                                    <?= $language['is_default'] == 1 ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label
                                                    for='step_1_title<?= $language['code'] ?>'><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <input type='text' class="form-control custome_reset"
                                                    name='step_1_title[<?= $language['code'] ?>]'
                                                    id='step_1_title<?= $language['code'] ?>'
                                                    value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($step_1_title[$language['code']])) {
                                                        echo $step_1_title[$language['code']];
                                                    } else if (is_string($step_1_title) && $language['is_default'] == 1) {
                                                        echo $step_1_title;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>" />
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label
                                                    for='step_1_description<?= $language['code'] ?>'><?= labels('description', "Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <input type='text' class="form-control custome_reset"
                                                    name='step_1_description[<?= $language['code'] ?>]'
                                                    id='step_1_description<?= $language['code'] ?>'
                                                    value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($step_1_description[$language['code']])) {
                                                        echo $step_1_description[$language['code']];
                                                    } else if (is_string($step_1_description) && $language['is_default'] == 1) {
                                                        echo $step_1_description;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for='step_1_image'><?= labels('image', "Image") ?></label>
                                    <small>(<?= labels('process_flow_image_recommended_size', 'We recommend 130 x 130 pixels') ?>)</small>
                                    <input type="file" id="step_1_image" name="step_1_image" accept="image/*"
                                        class="filepond logo">
                                </div>
                            </div>
                            <?php
                            if (isset($disk)) {
                                if ($disk === "aws_s3") {
                                    $step_1_image = fetch_cloud_front_url('web_settings', $step_1_image);
                                    $step_2_image = fetch_cloud_front_url('web_settings', $step_2_image);
                                    $step_3_image = fetch_cloud_front_url('web_settings', $step_3_image);
                                    $step_4_image = fetch_cloud_front_url('web_settings', $step_4_image);
                                } elseif ($disk === "local_server") {
                                    $step_1_image = base_url("public/uploads/web_settings/" . $step_1_image);
                                    $step_2_image = base_url("public/uploads/web_settings/" . $step_2_image);
                                    $step_3_image = base_url("public/uploads/web_settings/" . $step_3_image);
                                    $step_4_image = base_url("public/uploads/web_settings/" . $step_4_image);
                                }
                            } else {
                                $step_1_image = base_url('public/backend/assets/img/news/img01.jpg'); // Default logo
                                $step_2_image = base_url('public/backend/assets/img/news/img01.jpg'); // Default logo
                                $step_3_image = base_url('public/backend/assets/img/news/img01.jpg'); // Default logo
                                $step_4_image = base_url('public/backend/assets/img/news/img01.jpg'); // Default logo
                            
                            }

                            ?>
                            <div class="col-md-3">



                                <img class="settings_logo" src="<?= $step_1_image; ?>">
                            </div>
                        </div>
                        <!-- Step 2 -->
                        <div class="row">
                            <div class="col-12 mb-3">
                                <h5><?= labels('step_2', "Step 2") ?></h5>
                            </div>
                            <?php
                            foreach ($languages as $index => $language) {
                                ?>
                                <div class="col-md-6" id="translationStep2Div-<?= $language['code'] ?>"
                                    <?= $language['is_default'] == 1 ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label
                                                    for='step_2_title<?= $language['code'] ?>'><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <input type='text' class="form-control custome_reset"
                                                    name='step_2_title[<?= $language['code'] ?>]'
                                                    id='step_2_title<?= $language['code'] ?>'
                                                    value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($step_2_title[$language['code']])) {
                                                        echo $step_2_title[$language['code']];
                                                    } else if (is_string($step_2_title) && $language['is_default'] == 1) {
                                                        echo $step_2_title;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>" />
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label
                                                    for='step_2_description<?= $language['code'] ?>'><?= labels('description', "Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <input type='text' class="form-control custome_reset"
                                                    name='step_2_description[<?= $language['code'] ?>]'
                                                    id='step_2_description<?= $language['code'] ?>'
                                                    value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($step_2_description[$language['code']])) {
                                                        echo $step_2_description[$language['code']];
                                                    } else if (is_string($step_2_description) && $language['is_default'] == 1) {
                                                        echo $step_2_description;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for='step_2_image'><?= labels('image', "Image") ?></label>
                                    <small>(<?= labels('process_flow_image_recommended_size', 'We recommend 130 x 130 pixels') ?>)</small>
                                    <input type="file" id="step_2_image" name="step_2_image" accept="image/*"
                                        class="filepond logo">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <img class="settings_logo" src="<?= $step_2_image; ?>">
                            </div>
                        </div>
                        <!-- Step 3 -->
                        <div class="row">
                            <div class="col-12 mb-3">
                                <h5><?= labels('step_3', "Step 3") ?></h5>
                            </div>
                            <?php
                            foreach ($languages as $index => $language) {
                                ?>
                                <div class="col-md-6" id="translationStep3Div-<?= $language['code'] ?>"
                                    <?= $language['is_default'] == 1 ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label
                                                    for='step_3_title<?= $language['code'] ?>'><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <input type='text' class="form-control custome_reset"
                                                    name='step_3_title[<?= $language['code'] ?>]'
                                                    id='step_3_title<?= $language['code'] ?>'
                                                    value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($step_3_title[$language['code']])) {
                                                        echo $step_3_title[$language['code']];
                                                    } else if (is_string($step_3_title) && $language['is_default'] == 1) {
                                                        echo $step_3_title;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>" />
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label
                                                    for='step_3_description<?= $language['code'] ?>'><?= labels('description', "Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <input type='text' class="form-control custome_reset"
                                                    name='step_3_description[<?= $language['code'] ?>]'
                                                    id='step_3_description<?= $language['code'] ?>'
                                                    value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($step_3_description[$language['code']])) {
                                                        echo $step_3_description[$language['code']];
                                                    } else if (is_string($step_3_description) && $language['is_default'] == 1) {
                                                        echo $step_3_description;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for='step_3_image'><?= labels('image', "Image") ?></label>
                                    <small>(<?= labels('process_flow_image_recommended_size', 'We recommend 130 x 130 pixels') ?>)</small>
                                    <input type="file" id="step_3_image" name="step_3_image" accept="image/*"
                                        class="filepond logo">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <img class="settings_logo" src="<?= $step_3_image; ?>">
                            </div>
                        </div>
                        <!-- Step 4 -->
                        <div class="row">
                            <div class="col-12 mb-3">
                                <h5><?= labels('step_4', "Step 4") ?></h5>
                            </div>
                            <?php
                            foreach ($languages as $index => $language) {
                                ?>
                                <div class="col-md-6" id="translationStep4Div-<?= $language['code'] ?>"
                                    <?= $language['is_default'] == 1 ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label
                                                    for='step_4_title<?= $language['code'] ?>'><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <input type='text' class="form-control custome_reset"
                                                    name='step_4_title[<?= $language['code'] ?>]'
                                                    id='step_4_title<?= $language['code'] ?>'
                                                    value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($step_4_title[$language['code']])) {
                                                        echo $step_4_title[$language['code']];
                                                    } else if (is_string($step_4_title) && $language['is_default'] == 1) {
                                                        echo $step_4_title;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>" />
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label
                                                    for='step_4_description<?= $language['code'] ?>'><?= labels('description', "Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <input type='text' class="form-control custome_reset"
                                                    name='step_4_description[<?= $language['code'] ?>]'
                                                    id='step_4_description<?= $language['code'] ?>'
                                                    value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($step_4_description[$language['code']])) {
                                                        echo $step_4_description[$language['code']];
                                                    } else if (is_string($step_4_description) && $language['is_default'] == 1) {
                                                        echo $step_4_description;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for='step_4_image'><?= labels('image', "Image") ?></label>
                                    <small>(<?= labels('process_flow_image_recommended_size', 'We recommend 130 x 130 pixels') ?>)</small>
                                    <input type="file" id="step_4_image" name="step_4_image" accept="image/*"
                                        class="filepond logo">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <img class="settings_logo" src="<?= $step_4_image; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($permissions['update']['settings'] == 1): ?>
            <div class="row mt-3">
                <div class="col-md d-flex justify-content-end">
                    <input type="submit" name="update" id="update" value="<?= labels('save_changes', "Save") ?>"
                        class="btn btn-lg bg-new-primary">
                </div>
            </div>
        <?php endif; ?>
        <?= form_close() ?>
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
                                <?= labels('id', 'ID') ?></th>
                            <th data-field="comment" class="text-center"><?= labels('comment', 'Comment') ?></th>
                            <th data-field="partner_name" class="text-center">
                                <?= labels('provider_name', 'Provider Name') ?></th>
                            <th data-field="profile_image" class="text-center"><?= labels('image', 'Image') ?></th>
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
        <?php
        if (isset($rating_section_status) && $rating_section_status == 1) { ?>
            $('#rating_section_status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php } else { ?>
            $('#rating_section_status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php }
        ?>
        <?php
        if (isset($faq_section_status) && $faq_section_status == 1) { ?>
            $('#faq_section_status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php } else { ?>
            $('#faq_section_status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php }
        ?>
        <?php
        if (isset($category_section_status) && $category_section_status == 1) { ?>
            $('#category_section_status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php } else { ?>
            $('#category_section_status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php }
        ?>
        <?php
        if (isset($process_flow_status) && $process_flow_status == 1) { ?>
            $('#process_flow_status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php } else { ?>
            $('#process_flow_status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php }
        ?>
        <?php
        if (isset($disable_landing_page_settings_status) && ($disable_landing_page_settings_status == 1 || $disable_landing_page_settings_status == "1" || $disable_landing_page_settings_status == "on")) { ?>
            $('#disable_landing_page_settings').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
            // If disable landing page settings is already checked on page load, make latitude and longitude required
            $('#default_latitude, #default_longitude').attr('required', 'required').addClass('required-field');
        <?php } else { ?>
            $('#disable_landing_page_settings').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php }
        ?>
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

        // Function to properly update Switchery visual state
        function updateSwitcheryState(checkbox) {
            if (checkbox && checkbox.nextElementSibling) {
                const switchery = checkbox.nextElementSibling;

                // Method 1: Try to trigger Switchery's internal update
                if (window.Switchery && checkbox.switchery) {
                    checkbox.switchery.setPosition(checkbox.checked);
                }

                // Method 2: Update CSS classes
                if (checkbox.checked) {
                    switchery.classList.add('active-content');
                    switchery.classList.remove('deactive-content');
                } else {
                    switchery.classList.add('deactive-content');
                    switchery.classList.remove('active-content');
                }

                // Method 3: Force visual update by manipulating the inner elements
                const switcherySmall = switchery.querySelector('.switchery-small');
                if (switcherySmall) {
                    if (checkbox.checked) {
                        switcherySmall.style.left = '20px';
                        switcherySmall.style.backgroundColor = '#26a69a';
                    } else {
                        switcherySmall.style.left = '0px';
                        switcherySmall.style.backgroundColor = '#dfe7ee';
                    }
                }

                // Method 4: Trigger a change event to notify Switchery
                const changeEvent = new Event('change', {
                    bubbles: true
                });
                checkbox.dispatchEvent(changeEvent);
            }
        }

        // Alternative function to destroy and recreate Switchery
        function recreateSwitchery(checkbox) {
            if (checkbox && checkbox.nextElementSibling) {
                const switchery = checkbox.nextElementSibling;

                // Destroy existing Switchery
                if (checkbox.switchery) {
                    checkbox.switchery.destroy();
                }

                // Remove the switchery element
                if (switchery && switchery.parentNode) {
                    switchery.parentNode.removeChild(switchery);
                }

                // Recreate Switchery
                if (window.Switchery) {
                    setTimeout(() => {
                        new Switchery(checkbox, {
                            size: 'small',
                            color: '#26a69a',
                            secondaryColor: '#dfe7ee'
                        });
                    }, 50);
                }
            }
        }

        // Wait a bit for Switchery to initialize
        setTimeout(function () {
            var rating_section_status = document.querySelector('#rating_section_status');
            var faq_section_status = document.querySelector('#faq_section_status');
            var category_section_status = document.querySelector('#category_section_status');
            var process_flow_status = document.querySelector('#process_flow_status');
            var disable_landing_page_settings_status = document.querySelector('#disable_landing_page_settings');

            // Define the array of other switches that should turn off disable_landing_page_settings
            const otherSwitches = [
                'rating_section_status',
                'faq_section_status',
                'category_section_status',
                'process_flow_status'
            ];

            // Function to turn off disable landing page settings when any other switch is toggled on
            function turnOffDisableLandingPage() {
                const disableSwitch = document.getElementById('disable_landing_page_settings');
                if (disableSwitch && disableSwitch.checked) {
                    disableSwitch.checked = false;
                    updateSwitcheryState(disableSwitch);

                    // Remove required attributes from latitude/longitude
                    const latitudeInput = document.getElementById('default_latitude');
                    const longitudeInput = document.getElementById('default_longitude');
                    if (latitudeInput) {
                        latitudeInput.removeAttribute('required');
                        latitudeInput.classList.remove('required-field');
                    }
                    if (longitudeInput) {
                        longitudeInput.removeAttribute('required');
                        longitudeInput.classList.remove('required-field');
                    }
                }
            }

            // Add event listeners to all other switches using the array
            otherSwitches.forEach(switchId => {
                const switchElement = document.getElementById(switchId);
                if (switchElement) {
                    switchElement.addEventListener('change', function () {
                        // Call the original handler first
                        handleSwitchChange(this);

                        // If this switch is turned ON, turn OFF the disable landing page settings
                        if (this.checked) {
                            turnOffDisableLandingPage();
                        }
                    });
                }
            });

            // Only add event listener if the element exists
            if (disable_landing_page_settings_status) {
                disable_landing_page_settings_status.addEventListener('change', function () {
                    handleSwitchChange(disable_landing_page_settings_status);

                    // Get latitude and longitude input fields
                    const latitudeInput = document.getElementById('default_latitude');
                    const longitudeInput = document.getElementById('default_longitude');

                    if (this.checked) {
                        // When disable landing page settings is checked, make latitude and longitude required
                        if (latitudeInput) {
                            latitudeInput.setAttribute('required', 'required');
                            latitudeInput.classList.add('required-field');
                        }
                        if (longitudeInput) {
                            longitudeInput.setAttribute('required', 'required');
                            longitudeInput.classList.add('required-field');
                        }

                        // Automatically disable all other toggles
                        const otherToggleIds = ['rating_section_status', 'faq_section_status', 'category_section_status', 'process_flow_status'];

                        otherToggleIds.forEach(toggleId => {
                            const toggleElement = document.getElementById(toggleId);
                            if (toggleElement) {
                                toggleElement.checked = false;

                                // Try multiple methods to update the visual state
                                updateSwitcheryState(toggleElement);

                                // If the visual update didn't work, try recreating the Switchery
                                setTimeout(() => {
                                    const switchery = toggleElement.nextElementSibling;
                                    if (switchery && !switchery.classList.contains('deactive-content')) {
                                        recreateSwitchery(toggleElement);
                                    }
                                }, 100);

                            }
                        });
                    } else {
                        // When disable landing page settings is unchecked, remove required attribute
                        if (latitudeInput) {
                            latitudeInput.removeAttribute('required');
                            latitudeInput.classList.remove('required-field');
                        }
                        if (longitudeInput) {
                            longitudeInput.removeAttribute('required');
                            longitudeInput.classList.remove('required-field');
                        }

                        // Note: We don't automatically enable other toggles when disable is turned off
                        // as the user might have intentionally disabled them
                    }
                });
            }
        }, 500); // Wait 500ms for Switchery to initialize

        // Additional delay to ensure Switchery is fully initialized and force correct state
        setTimeout(function () {
            const disableSwitch = document.getElementById('disable_landing_page_settings');
            if (disableSwitch) {
                const phpValue = '<?= isset($disable_landing_page_settings_status) ? $disable_landing_page_settings_status : "0" ?>';

                // Force the checkbox to match the PHP value
                if (phpValue == "1" || phpValue == 1 || phpValue == "on") {
                    disableSwitch.checked = true;
                } else {
                    disableSwitch.checked = false;
                }

                // Force update the Switchery visual state
                updateSwitcheryState(disableSwitch);

                // If that doesn't work, recreate the Switchery
                setTimeout(() => {
                    const switchery = disableSwitch.nextElementSibling;
                    if (switchery && ((phpValue == "1" || phpValue == 1 || phpValue == "on") && !switchery.classList.contains('active-content'))) {
                        recreateSwitchery(disableSwitch);
                    }
                }, 100);
            }
        }, 1000); // Wait 1 second total

        // Fallback: Use jQuery event delegation for the toggle
        $(document).on('change', '#disable_landing_page_settings', function () {

            // Get latitude and longitude input fields
            const latitudeInput = document.getElementById('default_latitude');
            const longitudeInput = document.getElementById('default_longitude');

            if (this.checked) {
                // When disable landing page settings is checked, make latitude and longitude required
                if (latitudeInput) {
                    latitudeInput.setAttribute('required', 'required');
                    latitudeInput.classList.add('required-field');
                }
                if (longitudeInput) {
                    longitudeInput.setAttribute('required', 'required');
                    longitudeInput.classList.add('required-field');
                }

                // Automatically disable all other toggles
                const otherToggles = [{
                    element: rating_section_status,
                    name: 'rating_section_status'
                },
                {
                    element: faq_section_status,
                    name: 'faq_section_status'
                },
                {
                    element: category_section_status,
                    name: 'category_section_status'
                },
                {
                    element: process_flow_status,
                    name: 'process_flow_status'
                }
                ];

                otherToggles.forEach(toggle => {
                    if (toggle.element) {
                        toggle.element.checked = false;

                        // Try multiple methods to update the visual state
                        updateSwitcheryState(toggle.element);

                        // If the visual update didn't work, try recreating the Switchery
                        setTimeout(() => {
                            const switchery = toggle.element.nextElementSibling;
                            if (switchery && !switchery.classList.contains('deactive-content')) {
                                recreateSwitchery(toggle.element);
                            }
                        }, 100);

                    }
                });
            } else {
                // When disable landing page settings is unchecked, remove required attribute
                if (latitudeInput) {
                    latitudeInput.removeAttribute('required');
                    latitudeInput.classList.remove('required-field');
                }
                if (longitudeInput) {
                    longitudeInput.removeAttribute('required');
                    longitudeInput.classList.remove('required-field');
                }

                // Note: We don't automatically enable other toggles when disable is turned off as the user might have intentionally disabled them
            }
        });
    });

    function review_query_param(p) {
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
        };
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
        var ratingIds = <?= json_encode($rating_ids); ?>;
        $('#slider_list').bootstrapTable({
            onLoadSuccess: function (data) {
                setTimeout(function () {
                    ratingIds.forEach(function (id) {
                        $('#slider_list').bootstrapTable('checkBy', {
                            field: 'id',
                            values: [id]
                        });
                    });
                }, 0);
            },
            onCheck: function (row) {
                if (!ratingIds.includes(row.id)) {
                    ratingIds.push(row.id);
                }
            },
            onUncheck: function (row) {
                ratingIds = ratingIds.filter(id => id !== row.id);
            }
        });
        $('#saveRatings').click(function () {
            $('#new_rating_ids').val(ratingIds);
            $('#ratingsModal').modal('hide');
        });
        document.getElementById('select-ratings').addEventListener('click', function () {
            $('#ratingsModal').modal('show');
        });
    });
</script>

<script>
    // JavaScript validation for latitude and longitude decimal places (max 6 decimal places)
    $(document).ready(function () {
        // Function to limit decimal places for latitude and longitude inputs
        function limitDecimalPlaces(input, maxDecimals) {
            input.addEventListener('input', function () {
                let value = this.value;

                // Remove any non-numeric characters except decimal point and minus sign
                value = value.replace(/[^0-9.-]/g, '');

                // Handle multiple decimal points
                let parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }

                // Limit decimal places
                if (parts.length === 2 && parts[1].length > maxDecimals) {
                    value = parts[0] + '.' + parts[1].substring(0, maxDecimals);
                }

                // Update the input value
                this.value = value;
            });

            // Also handle paste events
            input.addEventListener('paste', function (e) {
                setTimeout(() => {
                    let value = this.value;
                    value = value.replace(/[^0-9.-]/g, '');
                    let parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    if (parts.length === 2 && parts[1].length > maxDecimals) {
                        value = parts[0] + '.' + parts[1].substring(0, maxDecimals);
                    }
                    this.value = value;
                }, 0);
            });
        }

        // Apply decimal place limitation to latitude and longitude inputs
        const latitudeInput = document.getElementById('default_latitude');
        const longitudeInput = document.getElementById('default_longitude');

        if (latitudeInput) {
            limitDecimalPlaces(latitudeInput, 6);
        }

        if (longitudeInput) {
            limitDecimalPlaces(longitudeInput, 6);
        }

        // Form submission — AJAX with FormData (supports file uploads)
        $('form[action*="web-landing-page-settings-update"]').on('submit', function (e) {
            e.preventDefault();
            var $btn = $(this).find('[type="submit"]');
            var originalVal = $btn.val();

            // Client-side lat/lng validation when disable switch is on
            var disableSwitch = document.getElementById('disable_landing_page_settings');
            var latitudeInput = document.getElementById('default_latitude');
            var longitudeInput = document.getElementById('default_longitude');
            if (disableSwitch && disableSwitch.checked) {
                if (!latitudeInput.value || latitudeInput.value.trim() === '') {
                    iziToast.error({ title: '', message: '<?= labels("latitude_is_required", "Latitude is required when disable landing page settings is enabled") ?>', position: 'topRight' });
                    latitudeInput.focus();
                    return false;
                }
                if (!longitudeInput.value || longitudeInput.value.trim() === '') {
                    iziToast.error({ title: '', message: '<?= labels("longitude_is_required", "Longitude is required when disable landing page settings is enabled") ?>', position: 'topRight' });
                    longitudeInput.focus();
                    return false;
                }
                var lat = parseFloat(latitudeInput.value);
                var lng = parseFloat(longitudeInput.value);
                if (isNaN(lat) || lat < -90 || lat > 90) {
                    iziToast.error({ title: '', message: '<?= labels("please_enter_valid_latitude", "Please enter a valid latitude (between -90 and 90)") ?>', position: 'topRight' });
                    latitudeInput.focus();
                    return false;
                }
                if (isNaN(lng) || lng < -180 || lng > 180) {
                    iziToast.error({ title: '', message: '<?= labels("please_enter_valid_longitude", "Please enter a valid longitude (between -180 and 180)") ?>', position: 'topRight' });
                    longitudeInput.focus();
                    return false;
                }
            }

            $btn.prop('disabled', true);
            var formData = new FormData(this);
            formData.append('update', '1');
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res.error === false) {
                        iziToast.success({ title: '', message: res.message, position: 'topRight' });
                    } else {
                        iziToast.error({ title: '', message: res.message, position: 'topRight' });
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                },
                error: function () {
                    iziToast.error({ title: '', message: '<?= labels("something_went_wrong", "Something went wrong") ?>', position: 'topRight' });
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                },
                complete: function () {
                    $btn.prop('disabled', false).val(originalVal);
                }
            });
        });
    });
</script>
<script>
    $(document).ready(function () {
        // select default language
        let current_faq_language = '<?= $current_faq_language ?>';

        $(document).on('click', '.language-faq-option', function () {
            const language = $(this).data('language');

            $('.language-faq-underline').css('width', '0%');
            $('#language-faq-' + language).find('.language-faq-underline').css('width', '100%');

            $('.language-faq-text').removeClass('text-primary fw-medium');
            $('.language-faq-text').addClass('text-muted');
            $('#language-faq-' + language).find('.language-faq-text').removeClass('text-muted');
            $('#language-faq-' + language).find('.language-faq-text').addClass('text-primary');

            if (language != current_faq_language) {
                $('#translationFaqDiv-' + language).show();
                $('#translationFaqDiv-' + current_faq_language).hide();
            }

            current_faq_language = language;
        });

        let current_rating_language = '<?= $current_rating_language ?>';

        $(document).on('click', '.language-rating-option', function () {
            const language = $(this).data('language');

            $('.language-rating-underline').css('width', '0%');
            $('#language-rating-' + language).find('.language-rating-underline').css('width', '100%');

            $('.language-rating-text').removeClass('text-primary fw-medium');
            $('.language-rating-text').addClass('text-muted');
            $('#language-rating-' + language).find('.language-rating-text').removeClass('text-muted');
            $('#language-rating-' + language).find('.language-rating-text').addClass('text-primary');

            if (language != current_rating_language) {
                $('#translationRatingDiv-' + language).show();
                $('#translationRatingDiv-' + current_rating_language).hide();
            }

            current_rating_language = language;
        });

        let current_category_language = '<?= $current_category_language ?>';

        $(document).on('click', '.language-category-option', function () {
            const language = $(this).data('language');

            $('.language-category-underline').css('width', '0%');
            $('#language-category-' + language).find('.language-category-underline').css('width', '100%');

            $('.language-category-text').removeClass('text-primary fw-medium');
            $('.language-category-text').addClass('text-muted');
            $('#language-category-' + language).find('.language-category-text').removeClass('text-muted');
            $('#language-category-' + language).find('.language-category-text').addClass('text-primary');

            if (language != current_category_language) {
                $('#translationCategoryDiv-' + language).show();
                $('#translationCategoryDiv-' + current_category_language).hide();
            }

            current_category_language = language;
        });

        let current_process_flow_language = '<?= $current_process_flow_language ?>';

        $(document).on('click', '.language-process-flow-option', function () {
            const language = $(this).data('language');

            $('.language-process-flow-underline').css('width', '0%');
            $('#language-process-flow-' + language).find('.language-process-flow-underline').css('width', '100%');

            $('.language-process-flow-text').removeClass('text-primary fw-medium');
            $('.language-process-flow-text').addClass('text-muted');
            $('#language-process-flow-' + language).find('.language-process-flow-text').removeClass('text-muted');
            $('#language-process-flow-' + language).find('.language-process-flow-text').addClass('text-primary');

            if (language != current_process_flow_language) {
                $('#translationProcessFlowDiv-' + language).show();
                $('#translationStep1Div-' + language).show();
                $('#translationStep2Div-' + language).show();
                $('#translationStep3Div-' + language).show();
                $('#translationStep4Div-' + language).show();
                $('#translationProcessFlowDiv-' + current_process_flow_language).hide();
                $('#translationStep1Div-' + current_process_flow_language).hide();
                $('#translationStep2Div-' + current_process_flow_language).hide();
                $('#translationStep3Div-' + current_process_flow_language).hide();
                $('#translationStep4Div-' + current_process_flow_language).hide();
            }

            current_process_flow_language = language;
        });
    });
</script>