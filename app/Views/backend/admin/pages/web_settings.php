<style>
    .color-swatch-wrapper {
        position: relative;
        width: 36px;
        height: 36px;
        flex-shrink: 0;
    }

    .color-swatch-wrapper input[type="color"] {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        border: none;
        padding: 0;
    }

    .color-swatch-preview {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 2px solid #e0e0e0;
        pointer-events: none;
        transition: border-color 0.2s ease;
    }

    .color-swatch-wrapper:hover .color-swatch-preview {
        border-color: #0277fa;
    }

    .color-picker-input {
        border-left: none !important;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: 0.875rem;
        letter-spacing: 0.5px;
    }

    .color-field-group .input-group {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        padding: 4px 8px;
        align-items: center;
        gap: 8px;
        background: #fff;
    }

    .color-field-group .input-group:focus-within {
        border-color: #0277fa;
        box-shadow: 0 0 0 3px rgba(2, 119, 250, 0.1);
    }

    .color-field-group .form-control {
        border: none !important;
        box-shadow: none !important;
        padding-left: 4px;
        background: transparent;
    }

    .color-field-group label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }
</style>
<!-- Main Content -->
<?php
$permissionService = new \App\Services\utility\PermissionService();
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('web_settings', "Web settings") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a
                        href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a>
                </div>
                <div class="breadcrumb-item"><?= labels('web_settings', "Web settings") ?></div>
            </div>
        </div>


        <ul class="justify-content-start nav nav-fill nav-pills pl-3 py-2 setting" id="gen-list">
            <div class="row">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="<?= base_url('admin/settings/web_setting') ?>"
                        id="pills-general_settings-tab" aria-selected="true">
                        <?= labels('web_settings', "Web Settings") ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/web-landing-page-settings') ?>"
                        id="pills-about_us" aria-selected="false">
                        <?= labels('landing_page_settings', "Landing Page Settings") ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/become-provider-setting') ?>"
                        id="pills-about_us">
                        <?= labels('become_provider_page_settings', "Become Provider Page Settings") ?>
                    </a>
                </li>
            </div>
        </ul>

        <?= form_open_multipart(base_url('admin/settings/web_setting_update')) ?>
        <!-- Browser timezone sent so maintenance schedule times are stored in UTC (admin enters in local time, e.g. IST). -->
        <input type="hidden" name="maintenance_schedule_entry_timezone" id="maintenance_schedule_entry_timezone"
            value="">
        <div class="row mb-4">
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row pl-3 m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('maintenance_mode', "Maintenance Mode") ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    // Sort languages so default language appears first for better UI
                                    $sorted_languages = sort_languages_with_default_first($languages);
                                    foreach ($sorted_languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_maintenance_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-maintenance-option mb-3 position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-maintenance-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-maintenance-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-maintenance-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label><?= labels('start_and_end_date', "Start And End Date") ?></label>
                                    <input type="text" name="customer_web_maintenance_schedule_date"
                                        id="customer_web_maintenance_schedule_date" class="form-control daterange-cus "
                                        value="<?php echo $customer_web_maintenance_schedule_date ?? "" ?>">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <?php
                                // Use the same sorted languages for form fields
                                foreach ($sorted_languages as $index => $language) {
                                    ?>
                                    <div class="form-group mb-0" id="translationMaintenanceDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_maintenance_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <label><?= labels('message_for_customer_web', "Message for Customer Application") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <textarea class="form-control" style="min-height:60px"
                                            name="message_for_customer_web[<?= $language['code'] ?>]"
                                            style="min-height:60px" rows="1"><?php
                                            // Handle both new multi-language format and old single string format
                                            if (isset($message_for_customer_web[$language['code']])) {
                                                echo $message_for_customer_web[$language['code']];
                                            } else if (is_string($message_for_customer_web) && $language['is_default'] == 1) {
                                                echo $message_for_customer_web;
                                            } else {
                                                echo "";
                                            }
                                            ?></textarea>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-12  mt-2">
                                <div class="form-group">
                                    <label><?= labels('maintenance_mode', "Maintenance Mode") ?></label>
                                    <br>
                                    <label class=" mt-1 " style="padding-top:0">
                                        <?php
                                        // Use customer_web_maintenance_mode (old field name) - ensure it has a default value if not set
                                        $customer_web_maintenance_mode_value = isset($customer_web_maintenance_mode) ? $customer_web_maintenance_mode : 0;

                                        // Simple check: if customer_web_maintenance_mode is 1, checkbox should be checked
                                        $isChecked = ($customer_web_maintenance_mode_value == 1 || $customer_web_maintenance_mode_value == "1");

                                        ?>
                                        <input type="checkbox" class="status-switch"
                                            name="customer_web_maintenance_mode" id="customer_web_maintenance_mode"
                                            <?php echo $isChecked ? "checked" : "" ?>
                                            value="<?= $customer_web_maintenance_mode_value; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-sm-12 col-xl-6 ">
                <div class="card h-100">
                    <div class="row m-0 border_bottom_for_cards">
                        <div class="col ">
                            <div class="toggleButttonPostition">
                                <?= labels('app_download_section', "App download Section") ?>
                            </div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4">

                            <?php $app_section_status = isset($app_section_status) ? $app_section_status : 0; ?>

                            <?php if ($app_section_status == 1): ?>
                                <input type="checkbox" id="app_section_status" name="app_section_status"
                                    class="status-switch" value="1" checked>
                            <?php else: ?>
                                <input type="checkbox" id="app_section_status" name="app_section_status"
                                    class="status-switch" value="0">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    // Sort languages so default language appears first for better UI
                                    $sorted_app_section_languages = sort_languages_with_default_first($languages);
                                    foreach ($sorted_app_section_languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_app_section_language = $language['code'];
                                        }
                                        ?>
                                        <div class="language-app-section-option mb-3 position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-app-section-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span
                                                class="language-app-section-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?>
                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-app-section-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php
                            // Use the same sorted languages for form fields
                            foreach ($sorted_app_section_languages as $index => $language) {
                                ?>
                                <div class="col-6" id="translationAppSectionDiv-<?= $language['code'] ?>"
                                    <?= $language['code'] == $current_app_section_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="form-group">
                                        <label
                                            for='web_title<?= $language['code'] ?>'><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <input type='text' class="form-control custome_reset"
                                            name='web_title[<?= $language['code'] ?>]'
                                            id='web_title<?= $language['code'] ?>' value="<?php
                                              // Handle both new multi-language format and old single string format
                                              if (isset($web_title[$language['code']])) {
                                                  echo $web_title[$language['code']];
                                              } else if (is_string($web_title) && $language['is_default'] == 1) {
                                                  echo $web_title;
                                              } else {
                                                  echo "";
                                              }
                                              ?>" />
                                    </div>
                                </div>
                            <?php } ?>

                            <div class="col-6">
                                <div class="form-group">
                                    <label for='playstore_url'><?= labels('playstore_url', "Playstore URL ") ?></label>
                                    <div class="input-group">
                                        <input type='text' class="form-control custome_reset" name='playstore_url'
                                            id='playstore_url'
                                            value="<?= isset($playstore_url) ? $playstore_url : '' ?>" />
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button"
                                                onclick="copyToClipboard('playstore_url')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label
                                        for='applestore_url'><?= labels('applestore_url', "Applestore URL") ?></label>
                                    <div class="input-group">
                                        <input type='text' class="form-control custome_reset" name='applestore_url'
                                            id='applestore_url'
                                            value="<?= isset($applestore_url) ? $applestore_url : '' ?>" />
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button"
                                                onclick="copyToClipboard('applestore_url')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 mb-3">
                <div class="card h-100">
                    <div class="row m-0 border_bottom_for_cards">
                        <div class="col ">
                            <div class="toggleButttonPostition">
                                <?= labels('register_provider_from_web_settings', "Register Provider From Web Settings") ?>
                            </div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4">

                            <?php $register_provider_from_web_setting_status = isset($register_provider_from_web_setting_status) ? $register_provider_from_web_setting_status : 0; ?>

                            <?php if ($register_provider_from_web_setting_status == 1): ?>
                                <input type="checkbox" id="register_provider_from_web_setting_status"
                                    name="register_provider_from_web_setting_status" class="status-switch" value="1"
                                    checked>
                            <?php else: ?>
                                <input type="checkbox" id="register_provider_from_web_setting_status"
                                    name="register_provider_from_web_setting_status" class="status-switch" value="0">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label
                                        for='partner_login_url'><?= labels('partner_login_url', "Partner Login URL") ?></label>
                                    <div class="input-group">
                                        <input type='text' class="form-control custome_reset" name='partner_login_url'
                                            id='partner_login_url' value="<?= base_url('partner/login') ?>" readonly />
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button"
                                                onclick="copyToClipboard('partner_login_url')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label
                                        for='partner_register_url'><?= labels('partner_register_url', "Partner Register URL") ?></label>
                                    <div class="input-group">
                                        <input type='text' class="form-control custome_reset"
                                            name='partner_register_url' id='partner_register_url'
                                            value="<?= base_url('auth/create_user') ?>" readonly />
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button"
                                                onclick="copyToClipboard('partner_register_url')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 col-sm-12 col-xl-12 mb-3">
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
                                    <label for='logo'><?= labels('logo', "Logo") ?></label>
                                    <small>(<?= labels('web_logo_recommended_size', 'We recommend 182 x 60 pixels') ?>)</small>
                                    <input type="file" name="web_logo" class="filepond logo" id="web_logo"
                                        accept="image/*">

                                    <?php
                                    $disk = fetch_current_file_manager();

                                    if (isset($disk) && $disk === "aws_s3") {
                                        $web_logo = fetch_cloud_front_url('web_settings', $web_logo);
                                    } elseif (isset($disk) && $disk === "local_server") {
                                        $web_logo = base_url("public/uploads/web_settings/" . $web_logo);
                                    } else {
                                        $web_logo = base_url('public/backend/assets/img/news/img01.jpg');
                                    }
                                    ?>
                                    <img class="settings_logo" src="<?= $web_logo; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for='favicon'><?= labels('favicon', "Favicon") ?></label>
                                    <small>(<?= labels('favicon_recommended_size', 'We recommend 16 x 16 pixels') ?>)</small>
                                    <input type="file" name="web_favicon" class="filepond logo" id="web_favicon"
                                        accept="image/*">

                                    <?php
                                    $disk = fetch_current_file_manager();

                                    if (isset($disk) && $disk === "aws_s3") {
                                        $web_favicon = fetch_cloud_front_url('web_settings', $web_favicon);
                                    } elseif (isset($disk) && $disk === "local_server") {
                                        $web_favicon = base_url("public/uploads/web_settings/" . $web_favicon);
                                    } else {
                                        $web_favicon = base_url('public/backend/assets/img/news/img01.jpg');
                                    }
                                    ?>
                                    <img class="settings_logo" src="<?= $web_favicon; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for='halfLogo'><?= labels('half_logo', "Half Logo") ?></label>
                                    <small>(<?= labels('web_half_logo_recommended_size', 'We recommend 60 x 60 pixels') ?>)</small>
                                    <input type="file" name="web_half_logo" class="filepond logo" id="web_half_logo"
                                        accept="image/*">



                                    <?php
                                    $disk = fetch_current_file_manager();

                                    if (isset($disk) && $disk === "aws_s3") {
                                        $web_half_logo = fetch_cloud_front_url('web_settings', $web_half_logo);
                                    } elseif (isset($disk) && $disk === "local_server") {
                                        $web_half_logo = base_url("public/uploads/web_settings/" . $web_half_logo);
                                    } else {
                                        $web_half_logo = base_url('public/backend/assets/img/news/img01.jpg');
                                    }
                                    ?>
                                    <img class="settings_logo" src="<?= $web_half_logo; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for='halfLogo'><?= labels('footer_logo', "Footer Logo") ?></label>
                                    <small>(<?= labels('web_logo_recommended_size', 'We recommend 182 x 60 pixels') ?>)</small>
                                    <input type="file" name="footer_logo" class="filepond logo" id="footer_logo"
                                        accept="image/*">
                                    <?php
                                    $disk = fetch_current_file_manager();

                                    if (isset($disk) && $disk === "aws_s3") {
                                        $footer_logo = fetch_cloud_front_url('web_settings', $footer_logo);
                                    } elseif (isset($disk) && $disk === "local_server") {
                                        $footer_logo = base_url("public/uploads/web_settings/" . $footer_logo);
                                    } else {
                                        $footer_logo = base_url('public/backend/assets/img/news/img01.jpg');
                                    }
                                    ?>
                                    <img class="settings_logo" src="<?= $footer_logo; ?>">
                                </div>

                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col d-flex align-items-center justify-content-between">
                            <div class="toggleButttonPostition">
                                <?= labels('light_theme_colors', "Light Theme Colors") ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary reset-theme-btn"
                                data-theme="light">
                                <i class="fas fa-undo-alt"></i> <?= labels('reset_to_default', 'Reset to Default') ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            $lightColors = [
                                ['id' => 'light_primary_color', 'label' => labels('primary_color', 'Primary Color'), 'default' => '#0277fa'],
                                ['id' => 'light_secondary_color', 'label' => labels('secondary_color', 'Secondary Color'), 'default' => '#000000'],
                                ['id' => 'light_bg_color', 'label' => labels('light_background_color', 'Light Background Color'), 'default' => '#0277fa12'],
                                ['id' => 'light_text_color', 'label' => labels('text_color', 'Text Color'), 'default' => '#000000'],
                                ['id' => 'light_card_bg_color', 'label' => labels('card_background_color', 'Card Background Color'), 'default' => '#ffffff'],
                                ['id' => 'light_description_text_color', 'label' => labels('description_text_color', 'Description Text Color'), 'default' => '#2121219e'],
                            ];
                            foreach ($lightColors as $color) {
                                $val = isset(${$color['id']}) ? ${$color['id']} : $color['default'];
                                // Color picker only supports 6-digit hex, so trim alpha for the swatch
                                $swatchVal = (strlen($val) === 9) ? substr($val, 0, 7) : $val;
                                ?>
                                <div class="col-md-6">
                                    <div class="form-group color-field-group">
                                        <label for="<?= $color['id'] ?>"><?= $color['label'] ?></label>
                                        <div class="input-group">
                                            <div class="color-swatch-wrapper">
                                                <div class="color-swatch-preview" id="<?= $color['id'] ?>_preview"
                                                    style="background-color: <?= $swatchVal ?>"></div>
                                                <input type="color" id="<?= $color['id'] ?>_picker"
                                                    value="<?= $swatchVal ?>"
                                                    title="<?= labels('choose_color', 'Choose Color') ?>"
                                                    oninput="syncColorField('<?= $color['id'] ?>', this.value)">
                                            </div>
                                            <input type="text" class="form-control color-picker-input"
                                                name="<?= $color['id'] ?>" id="<?= $color['id'] ?>" value="<?= $val ?>"
                                                maxlength="9" oninput="syncColorSwatch('<?= $color['id'] ?>', this.value)">
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col d-flex align-items-center justify-content-between">
                            <div class="toggleButttonPostition"><?= labels('dark_theme_colors', "Dark Theme Colors") ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary reset-theme-btn"
                                data-theme="dark">
                                <i class="fas fa-undo-alt"></i> <?= labels('reset_to_default', 'Reset to Default') ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            $darkColors = [
                                ['id' => 'dark_primary_color', 'label' => labels('primary_color', 'Primary Color'), 'default' => '#51a7f3'],
                                ['id' => 'dark_secondary_color', 'label' => labels('secondary_color', 'Secondary Color'), 'default' => '#0d1115'],
                                ['id' => 'dark_bg_color', 'label' => labels('light_background_color', 'Light Background Color'), 'default' => '#0d1115'],
                                ['id' => 'dark_text_color', 'label' => labels('text_color', 'Text Color'), 'default' => '#ffffff'],
                                ['id' => 'dark_card_bg_color', 'label' => labels('card_background_color', 'Card Background Color'), 'default' => '#0d131b'],
                                ['id' => 'dark_description_text_color', 'label' => labels('description_text_color', 'Description Text Color'), 'default' => '#ffffff'],
                            ];
                            foreach ($darkColors as $color) {
                                $val = isset(${$color['id']}) ? ${$color['id']} : $color['default'];
                                $swatchVal = (strlen($val) === 9) ? substr($val, 0, 7) : $val;
                                ?>
                                <div class="col-md-6">
                                    <div class="form-group color-field-group">
                                        <label for="<?= $color['id'] ?>"><?= $color['label'] ?></label>
                                        <div class="input-group">
                                            <div class="color-swatch-wrapper">
                                                <div class="color-swatch-preview" id="<?= $color['id'] ?>_preview"
                                                    style="background-color: <?= $swatchVal ?>"></div>
                                                <input type="color" id="<?= $color['id'] ?>_picker"
                                                    value="<?= $swatchVal ?>"
                                                    title="<?= labels('choose_color', 'Choose Color') ?>"
                                                    oninput="syncColorField('<?= $color['id'] ?>', this.value)">
                                            </div>
                                            <input type="text" class="form-control color-picker-input"
                                                name="<?= $color['id'] ?>" id="<?= $color['id'] ?>" value="<?= $val ?>"
                                                maxlength="9" oninput="syncColorSwatch('<?= $color['id'] ?>', this.value)">
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <!-- social media links -->
            <div class="col-md-12 col-sm-12 col-xl-12">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col">
                            <div class="toggleButttonPostition">
                                <?= labels('social_media_links', "Social Media Links") ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="list_wrapper">
                            <div class="row">

                                <div class="col-xs-4 col-sm-4 col-md-5">
                                    <div class="form-group m-0">
                                        <label for="title"><?= labels('url', "URL") ?></label>
                                        <input name="social_media[0][url]" type="text"
                                            placeholder="<?= labels('enter_url_here', 'Enter the URL here') ?>"
                                            class="form-control social_media_url_change" />
                                        <input type="hidden" name="social_media[0][exist_url]" value="new">
                                    </div>
                                </div>
                                <div class="col-xs-7 col-sm-7 col-md-5">
                                    <div class="form-group m-0">
                                        <label for="image"><?= labels('image', "Image") ?></label>
                                        <small>(<?= labels('social_media_icon_recommended_size', 'We recommend 30 x 30 pixels') ?>)</small>
                                        <input name="social_media[0][file]" type="file" class="filepond logo "
                                            data-your-attribute="social_media_file" accept="image/*">
                                        <input type="hidden" name="social_media[0][exist_file]" value="new">
                                        <img class="settings_logo" src="">
                                    </div>
                                </div>
                                <div class="col-xs-1 col-sm-1 col-md-2 mt-4">
                                    <button class="btn btn-primary list_add_button" type="button">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Cookie Consent Section -->
        <div class="row mb-4">
            <div class="col-md-12 col-sm-12 col-xl-12">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col">
                            <div class="toggleButttonPostition"><?= labels('cookie_consent', "Cookie Consent") ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4">
                            <?php $cookie_consent_status = isset($cookie_consent_status) ? $cookie_consent_status : 0; ?>

                            <?php if ($cookie_consent_status == 1): ?>
                                <input type="checkbox" id="cookie_consent_status" name="cookie_consent_status"
                                    class="status-switch" value="1" checked>
                            <?php else: ?>
                                <input type="checkbox" id="cookie_consent_status" name="cookie_consent_status"
                                    class="status-switch" value="0">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Cookie Consent Disabled Message -->
                        <div id="cookie-consent-disabled-message" style="display: none;">
                            <div class="alert alert-info d-flex align-items-center" role="alert">
                                <i class="fas fa-info-circle mr-2"></i>
                                <div>
                                    <strong><?= labels('cookie_consent_disabled', 'Cookie Consent Disabled') ?></strong>
                                    <small><?= labels('enable_cookie_consent_to_show_fields', 'Please enable Cookie Consent above to show the configuration fields.') ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="cookie-consent-fields">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                        <?php
                                        // Sort languages so default language appears first for better UI
                                        $sorted_cookie_consent_languages = sort_languages_with_default_first($languages);
                                        foreach ($sorted_cookie_consent_languages as $index => $language) {
                                            if ($language['is_default'] == 1) {
                                                $current_cookie_consent_language = $language['code'];
                                            }
                                            ?>
                                            <div class="language-cookie-consent-option mb-3 position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                id="language-cookie-consent-<?= $language['code'] ?>"
                                                data-language="<?= $language['code'] ?>"
                                                style="cursor: pointer; padding: 0.5rem 0;">
                                                <span
                                                    class="language-cookie-consent-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                    style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                    <?= $language['language'] ?>
                                                    <?= $language['is_default'] ? '(Default)' : '' ?>
                                                </span>
                                                <div class="language-cookie-consent-underline"
                                                    style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php
                                // Use the same sorted languages for form fields
                                foreach ($sorted_cookie_consent_languages as $index => $language) {
                                    ?>
                                    <div class="col-md-12" id="translationCookieConsentDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_cookie_consent_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="form-group">
                                            <label for="cookie_consent_title_<?= $language['code'] ?>"
                                                class="required"><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <input type="text" class="form-control"
                                                name="cookie_consent_title[<?= $language['code'] ?>]"
                                                id="cookie_consent_title_<?= $language['code'] ?>" value="<?php
                                                  // Handle both new multi-language format and old single string format
                                                  if (isset($cookie_consent_title[$language['code']])) {
                                                      echo $cookie_consent_title[$language['code']];
                                                  } else if (is_string($cookie_consent_title) && $language['is_default'] == 1) {
                                                      echo $cookie_consent_title;
                                                  } else {
                                                      echo "";
                                                  }
                                                  ?>"
                                                placeholder="<?= labels('enter_cookie_consent_title', 'Enter cookie consent title') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-12" id="translationCookieConsentDescDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_cookie_consent_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="form-group">
                                            <label for="cookie_consent_description_<?= $language['code'] ?>"
                                                class="required"><?= labels('description', "Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <textarea class="form-control"
                                                name="cookie_consent_description[<?= $language['code'] ?>]"
                                                id="cookie_consent_description_<?= $language['code'] ?>" rows="3"
                                                placeholder="<?= labels('enter_cookie_consent_description', 'Enter cookie consent description') ?>"><?php
                                                   // Handle both new multi-language format and old single string format
                                                   if (isset($cookie_consent_description[$language['code']])) {
                                                       echo $cookie_consent_description[$language['code']];
                                                   } else if (is_string($cookie_consent_description) && $language['is_default'] == 1) {
                                                       echo $cookie_consent_description;
                                                   } else {
                                                       echo "";
                                                   }
                                                   ?></textarea>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <?php if ($permissionService->can($currentUserId, 'update', 'settings')): ?>

            <div class="row mt-3">
                <div class="col-md d-flex justify-content-end">
                    <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Save") ?>'
                        class='btn btn-lg bg-new-primary' />
                    <?= form_close() ?>
                </div>
            </div>

        <?php endif; ?>

</div>
</div>
</section>
</div>
<script>
    function syncColorField(id, color) {
        document.getElementById(id).value = color;
        document.getElementById(id + '_preview').style.backgroundColor = color;
    }
    function syncColorSwatch(id, value) {
        var hex = value.substring(0, 7);
        if (/^#[0-9a-fA-F]{6}/.test(hex)) {
            document.getElementById(id + '_picker').value = hex;
            document.getElementById(id + '_preview').style.backgroundColor = hex;
        }
    }

    $(document).on('click', '.reset-theme-btn', function () {
        var btn = $(this);
        var theme = btn.data('theme');
        var originalHtml = btn.html();

        $.ajax({
            url: '<?= base_url("admin/settings/reset-theme-colors") ?>',
            type: 'POST',
            data: {
                theme: theme,
                [csrfName]: csrfHash
            },
            beforeSend: function () {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            },
            success: function (res) {
                if (res.error === false) {
                    // Update each color field and swatch with the default value
                    $.each(res.data, function (key, value) {
                        var el = document.getElementById(key);
                        if (el) {
                            el.value = value;
                            var swatchVal = (value.length === 9) ? value.substring(0, 7) : value;
                            var picker = document.getElementById(key + '_picker');
                            var preview = document.getElementById(key + '_preview');
                            if (picker) picker.value = swatchVal;
                            if (preview) preview.style.backgroundColor = swatchVal;
                        }
                    });
                    showToastMessage(res.message, 'success');
                } else {
                    showToastMessage(res.message, 'error');
                }
            },
            error: function () {
                showToastMessage('<?= labels('SOMETHING_WENT_WRONG', SOMETHING_WENT_WRONG) ?>', 'error');
            },
            complete: function () {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
</script>
<script>
    $(document).ready(function () {
        var x = 0; // Initial field counter
        var list_maxField = 10; // Input fields increment limitation
        // Once add button is clicked
        $('.list_add_button').click(function () {
            // Check maximum number of input fields
            if (x < list_maxField) {
                x++; // Increment field counter
                var list_fieldHTML = `
                <div class="row">
                    <div class="col-xs-4 col-sm-4 col-md-5">
                        <div class="form-group">
                            <label for="title"><?= labels('url', "URL") ?></label>
                            <input name="social_media[${x}][url]" type="text" placeholder="<?= labels('enter_url_here', 'Enter the URL here') ?>" class="form-control social_media_url_change"/>
                            <input type="hidden" name="social_media[${x}][exist_url]" value="social_media[${x}][url]">

                        </div>
                    </div>
                    <div class="col-xs-7 col-sm-7 col-md-5">
                        <div class="form-group">
                            <label for="image"><?= labels('image', "Image") ?></label> <small>(<?= labels('social_media_icon_recommended_size', 'We recommend 30 x 30 pixels') ?>)</small>
                            <input name="social_media[${x}][file]" type="file" class="filepond logo new-row " accept="image/*" data-your-attribute="social_media_file" required>
                            <input type="hidden" name="social_media[${x}][exist_file]" value="social_media[${x}][file]">
                            <input type="hidden" name="social_media[${x}][exist_disk]" value="social_media[${x}][disk]">

                        </div>
                    </div>
                    <div class="col-xs-1 col-sm-1 col-md-2 mt-4">
                        <button class="list_remove_button btn btn-danger" type="button">-</button>
                    </div>
                </div>`;
                // Append field HTML
                $('.list_wrapper').append(list_fieldHTML);
                // Initialize FilePond only for the new row
                $('.list_wrapper .new-row').last().filepond({
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
            }
        });
        // Once remove button is clicked
        $('.list_wrapper').on('click', '.list_remove_button', function () {
            $(this).closest('div.row').remove(); // Remove field HTML
            x--; // Decrement field counter
        });
    });
    document.addEventListener("DOMContentLoaded", function () {
        var app_section_status = document.querySelector('#app_section_status');

        if (app_section_status) {
            app_section_status.addEventListener('change', function () {
                // Call external handler if defined
                if (typeof handleSwitchChange === 'function') {
                    handleSwitchChange(app_section_status);
                }

                // Update value attribute based on checked state
                this.value = this.checked ? '1' : '0';

                // Show/hide cookie consent fields based on toggle state
                // if (this.checked) {
                //     $('.cookie-consent-fields').slideDown();
                // } else {
                //     $('.cookie-consent-fields').slideUp();
                // }
            });
        } else {
            console.warn("Checkbox with ID #cookie_consent_status not found.");
        }

        const cookie_consent_status = document.querySelector('#cookie_consent_status');
        if (cookie_consent_status) {
            cookie_consent_status.addEventListener('change', function () {
                // Call external handler if defined
                if (typeof handleSwitchChange === 'function') {
                    handleSwitchChange(cookie_consent_status);
                }

                // Update value attribute based on checked state
                this.value = this.checked ? '1' : '0';

                // Show/hide cookie consent fields based on toggle state
                // if (this.checked) {
                //     $('.cookie-consent-fields').slideDown();
                // } else {
                //     $('.cookie-consent-fields').slideUp();
                // }
            });
        } else {
            console.warn("Checkbox with ID #cookie_consent_status not found.");
        }

        // Handle register provider from web setting status toggle
        const register_provider_from_web_setting_status = document.querySelector('#register_provider_from_web_setting_status');
        if (register_provider_from_web_setting_status) {
            register_provider_from_web_setting_status.addEventListener('change', function () {
                // Call external handler if defined
                if (typeof handleSwitchChange === 'function') {
                    handleSwitchChange(register_provider_from_web_setting_status);
                }

                // Update value attribute based on checked state
                this.value = this.checked ? '1' : '0';
            });
        } else {
            console.warn("Checkbox with ID #register_provider_from_web_setting_status not found.");
        }
    });
</script>
<script>
    var baseUrl = "<?= base_url() ?>"; // Define the base URL

    $(document).ready(function () {


        // Use customer_web_maintenance_mode (old field name) instead of web_maintenance_mode
        var customer_web_maintenance_mode = document.querySelector('#customer_web_maintenance_mode');

        if (customer_web_maintenance_mode) {
            customer_web_maintenance_mode.addEventListener('change', function () {
                handleSwitchChange(customer_web_maintenance_mode);

                // Update value attribute based on checked state
                this.value = this.checked ? '1' : '0';
            });
        } else {
            console.warn("Checkbox with ID #customer_web_maintenance_mode not found.");
        }

        // Cookie consent toggle
        var cookie_consent_status = document.querySelector('#cookie_consent_status');

        if (cookie_consent_status) {
            cookie_consent_status.addEventListener('change', function () {

                handleSwitchChange(cookie_consent_status);

                // Update value attribute based on checked state
                this.value = this.checked ? '1' : '0';
                // Show/hide cookie consent fields based on toggle state
                if (this.checked) {
                    $('.cookie-consent-fields').slideDown();
                    $('#cookie-consent-disabled-message').hide();
                } else {
                    $('.cookie-consent-fields').slideUp();
                    $('#cookie-consent-disabled-message').show();
                }
            });
        } else {
            console.warn("Checkbox with ID #cookie_consent_status not found.");
        }

        // Initialize cookie consent fields visibility on page load
        if ($('#cookie_consent_status').is(':checked')) {
            $('.cookie-consent-fields').show();
            $('#cookie-consent-disabled-message').hide();
        } else {
            $('.cookie-consent-fields').hide();
            $('#cookie-consent-disabled-message').show();
        }

        //for status
        <?php
        $app_section_status = isset($app_section_status) ? $app_section_status : 0;
        if ($app_section_status == 1) { ?>
            $('#app_section_status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php } else { ?>
            $('#app_section_status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php }


        // Use customer_web_maintenance_mode (old field name) instead of web_maintenance_mode
        $customer_web_maintenance_mode = isset($customer_web_maintenance_mode) ? $customer_web_maintenance_mode : 0;
        if ($customer_web_maintenance_mode == 1) { ?>
            $('#customer_web_maintenance_mode').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php } else { ?>
            $('#customer_web_maintenance_mode').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php }

        $cookie_consent_status = isset($cookie_consent_status) ? $cookie_consent_status : 0;
        if ($cookie_consent_status == 1) { ?>
            $('#cookie_consent_status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php } else { ?>
            $('#cookie_consent_status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php }

        // Initialize register provider from web setting status toggle
        $register_provider_from_web_setting_status = isset($register_provider_from_web_setting_status) ? $register_provider_from_web_setting_status : 0;
        if ($register_provider_from_web_setting_status == 1) { ?>
            $('#register_provider_from_web_setting_status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        <?php } else { ?>
            $('#register_provider_from_web_setting_status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');
        <?php }
        ?>

        // handleSwitchChange($('#cookie_consent_status')[0]);
        // $('#cookie_consent_status').on('change', function() {
        //     handleSwitchChange(this);
        //     console.log("hello world");
        //     console.log(this.value);
        //     this.value = this.checked ? 1 : 0;
        // });

        function handleSwitchChange(checkbox) {
            // Find the Switchery element - it's usually the next sibling
            var switchery = checkbox.nextElementSibling;

            // If nextElementSibling is not a switchery element, look for it
            if (!switchery || !switchery.classList.contains('switchery')) {
                switchery = checkbox.parentNode.querySelector('.switchery');
            }

            if (switchery) {
                if (checkbox.checked) {
                    switchery.classList.add('active-content');
                    switchery.classList.remove('deactive-content');
                } else {
                    switchery.classList.add('deactive-content');
                    switchery.classList.remove('active-content');
                }
            }
        }
    });
</script>
<script>
    $(document).ready(function () {
        <?php
        $social_media = isset($social_media) && is_array($social_media) ? $social_media : [];
        $social_media = array_values($social_media); // Ensure the array keys are sequential
        ?>
        var x = <?= count($social_media) ?>; // Initial field counter
        // Function to add a new row with input fields
        function addRow(url, file, exist_url, disk) {

            let fileUrl = '';

            // Corrected PHP logic outside of JavaScript
            <?php
            $fileUrlAws = fetch_cloud_front_url("web_settings", ""); // Corrected for concatenation in JavaScript
            $fileUrlLocal = base_url("public/uploads/web_settings/");
            ?>

            if (disk === "aws_s3") {
                fileUrl = '<?= $fileUrlAws ?>' + file;
            } else if (disk === "local_server") {
                fileUrl = '<?= $fileUrlLocal ?>' + file;
            }


            var newRow = `
                <div class="row">
                    <div class="col-xs-4 col-sm-4 col-md-5">
                        <div class="form-group">
                            <label for="title"><?= labels('url', "URL") ?></label>
                            <input name="social_media[${x}][url]" type="text" value="${url}" class="form-control social_media_url_change" />
                            <input type="hidden" name="social_media[${x}][exist_url]" value="${exist_url}">
                            </div>
                            </div>
                            <div class="col-xs-7 col-sm-7 col-md-5">
                            <div class="form-group">
                            <label for="image"><?= labels('image', "Image") ?></label> <small>(<?= labels('social_media_icon_recommended_size', 'We recommend 30 x 30 pixels') ?>)</small>
                            <input name="social_media[${x}][file]" type="file" class="filepond logo " accept="image/*" >
                            <img class="settings_logo" src="${fileUrl}" >
                            <input type="hidden" name="social_media[${x}][exist_file]" value="${file}">
                            <input type="hidden" name="social_media[${x}][exist_disk]" value="${disk}">
                            
                        </div>
                    </div>
                    <div class="col-xs-1 col-sm-1 col-md-2 mt-4">
                        <button class="btn btn-danger list_remove_button" type="button">-</button>
                    </div>
                </div>
            `;
            $(".list_wrapper").append(newRow);
            x++; // Increment the field counter
        }
        // Loop through the social_media array and add rows for each entry
        <?php foreach ($social_media as $entry): ?>
            addRow("<?= $entry['url'] ?>", "<?= $entry['file'] ?>", "<?= $entry['url'] ?>", "<?= isset($entry['disk']) ? $entry['disk'] : "local_server" ?>");
        <?php endforeach; ?>
        $(".social_media_url_change").change(function () {
            if ($(this).val() == "") {
                $(this).parent().parent().next().find('.filepond--browser').prop('required', false);
            } else {
                $(this).parent().parent().next().find('.filepond--browser').prop('required', true);
            }
        });

    });
    $(document).ready(function () {
        var isMaintenanceMode = <?= json_encode($isChecked) ?>;

        // Remove duplicate handleSwitchChange function - using the one defined above
        $('#customer_maintenance_mode').on('change', function () {
            this.value = this.checked ? 1 : 0;
        }).change();
        if ($('#customer_maintenance_mode').length > 0) {
            handleSwitchChange($('#customer_maintenance_mode')[0]);
        }
        $('#customer_maintenance_mode').on('change', function () {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });

        // Function to check if date range is valid for maintenance mode
        function checkMaintenanceDateRange() {
            var dateRangeInput = $('#customer_web_maintenance_schedule_date');
            // Use customer_web_maintenance_mode (old field name) instead of web_maintenance_mode
            var maintenanceSwitch = $('#customer_web_maintenance_mode');

            if (dateRangeInput.length && maintenanceSwitch.length) {
                var dateRangeValue = dateRangeInput.val();

                // If date range is empty, allow maintenance mode to stay as is
                if (!dateRangeValue || dateRangeValue.trim() === '') {
                    return;
                }

                // Parse the date range (assuming format like "MM/DD/YYYY - MM/DD/YYYY")
                var dateParts = dateRangeValue.split(' - ');
                if (dateParts.length === 2) {
                    var startDate = new Date(dateParts[0]);
                    var endDate = new Date(dateParts[1]);
                    var today = new Date();

                    // Set time to start of day for accurate comparison
                    today.setHours(0, 0, 0, 0);
                    startDate.setHours(0, 0, 0, 0);
                    endDate.setHours(0, 0, 0, 0);

                    // Check if the date range is not greater than or equal to today
                    // If end date is before today, turn off maintenance mode
                    if (endDate < today) {
                        if (maintenanceSwitch.is(':checked')) {
                            maintenanceSwitch.prop('checked', false);
                            maintenanceSwitch.val('0');
                            handleSwitchChange(maintenanceSwitch[0]);

                            // Show notification to user
                            console.log('Maintenance mode turned off: Date range has expired');
                        }
                    }
                }
            }
        }

        // Check date range on page load
        checkMaintenanceDateRange();

        // Check date range when date input changes
        $('#customer_web_maintenance_schedule_date').on('change', function () {
            checkMaintenanceDateRange();
        });

        // Send browser timezone so backend can convert maintenance schedule from local time to UTC
        var tzEl = document.getElementById('maintenance_schedule_entry_timezone');
        if (tzEl && typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
            tzEl.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        }
    });
</script>

<!-- Language Tab Switching JavaScript -->
<script>
    $(document).ready(function () {
        // Handle maintenance mode language tab switching
        $('.language-maintenance-option').on('click', function () {
            var selectedLanguage = $(this).data('language');

            // Remove active state from all maintenance language options
            $('.language-maintenance-option').removeClass('selected');
            $('.language-maintenance-text').removeClass('text-primary fw-medium').addClass('text-muted');
            $('.language-maintenance-underline').css('width', '0');

            // Add active state to clicked option
            $(this).addClass('selected');
            $(this).find('.language-maintenance-text').removeClass('text-muted').addClass('text-primary fw-medium');
            $(this).find('.language-maintenance-underline').css('width', '100%');

            // Hide all maintenance translation divs
            $('[id^="translationMaintenanceDiv-"]').hide();

            // Show the selected language div
            $('#translationMaintenanceDiv-' + selectedLanguage).show();
        });

        // Handle app section language tab switching
        $('.language-app-section-option').on('click', function () {
            var selectedLanguage = $(this).data('language');

            // Remove active state from all app section language options
            $('.language-app-section-option').removeClass('selected');
            $('.language-app-section-text').removeClass('text-primary fw-medium').addClass('text-muted');
            $('.language-app-section-underline').css('width', '0');

            // Add active state to clicked option
            $(this).addClass('selected');
            $(this).find('.language-app-section-text').removeClass('text-muted').addClass('text-primary fw-medium');
            $(this).find('.language-app-section-underline').css('width', '100%');

            // Hide all app section translation divs
            $('[id^="translationAppSectionDiv-"]').hide();

            // Show the selected language div
            $('#translationAppSectionDiv-' + selectedLanguage).show();
        });

        // Handle cookie consent language tab switching
        $('.language-cookie-consent-option').on('click', function () {
            var selectedLanguage = $(this).data('language');

            // Remove active state from all cookie consent language options
            $('.language-cookie-consent-option').removeClass('selected');
            $('.language-cookie-consent-text').removeClass('text-primary fw-medium').addClass('text-muted');
            $('.language-cookie-consent-underline').css('width', '0');

            // Add active state to clicked option
            $(this).addClass('selected');
            $(this).find('.language-cookie-consent-text').removeClass('text-muted').addClass('text-primary fw-medium');
            $(this).find('.language-cookie-consent-underline').css('width', '100%');

            // Hide all cookie consent translation divs
            $('[id^="translationCookieConsentDiv-"]').hide();
            $('[id^="translationCookieConsentDescDiv-"]').hide();

            // Show the selected language divs
            $('#translationCookieConsentDiv-' + selectedLanguage).show();
            $('#translationCookieConsentDescDiv-' + selectedLanguage).show();
        });

        // Initialize default language states on page load
        // This ensures the correct language is selected when page loads
        $('.language-maintenance-option.selected').trigger('click');
        $('.language-app-section-option.selected').trigger('click');
        $('.language-cookie-consent-option.selected').trigger('click');
    });

    $(document).ready(function () {
        $('form[action*="web_setting_update"]').on('submit', function (e) {
            e.preventDefault();
            var $btn = $(this).find('[type="submit"]');
            var originalVal = $btn.val();
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