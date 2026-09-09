<?php
// Ensure partner_translations variable is available with fallback
// This variable contains translation data for all languages when duplicating a partner
// It's populated by the fetchAndSetTranslationData method in the Partners controller
if (!isset($partner_translations) || !is_array($partner_translations)) {
    $partner_translations = [];
}
helper('function');
$password_rules = get_password_rules();

function renderCustomFieldInput(string $fieldKey, string $fieldType, string $requiredAttr, string $placeholder = '', array $fileConfig = [], string $existingValue = ''): string
{
    $inputType = match (true) {
        $fieldType === 'number' => 'number',
        $fieldKey === 'account_number' => 'number',
        $fieldType === 'date' => 'date',
        default => 'text',
    };
    $commonAttrs = sprintf('class="form-control" name="%s" id="%s" %s', $fieldKey, $fieldKey, $requiredAttr);
    $valueAttr = $existingValue !== '' ? sprintf(' value="%s"', htmlspecialchars($existingValue, ENT_QUOTES, 'UTF-8')) : '';
    return match ($fieldType) {
        'file' => sprintf(
            '<input type="file" class="filepond-custom-field" name="%s" id="%s" %s%s>',
            $fieldKey,
            $fieldKey,
            $requiredAttr,
            (!empty($fileConfig['max_files']) && (int) $fileConfig['max_files'] > 1) ? ' multiple' : ''
        ),
        'textarea' => sprintf('<textarea %s>%s</textarea>', $commonAttrs, htmlspecialchars($existingValue, ENT_QUOTES, 'UTF-8')),
        default => sprintf(
            '<input type="%s" %s%s%s>',
            $inputType,
            $commonAttrs,
            $placeholder !== '' ? sprintf(' placeholder="%s"', htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8')) : '',
            $valueAttr
        ),
    };
}

// Compute default language code early (needed for stepperLabels before the language loop)
$sorted_languages = sort_languages_with_default_first($languages);
$current_language = '';
foreach ($sorted_languages as $_lang) {
    if ($_lang['is_default'] == 1) {
        $current_language = $_lang['code'];
        break;
    }
}
?>
<script>
    window.PASSWORD_RULES = {
        minLength: <?= (int) $password_rules['min_length'] ?>,
        requireUppercase: <?= (int) $password_rules['require_uppercase'] ?>,
        requireLowercase: <?= (int) $password_rules['require_lowercase'] ?>,
        requireNumber: <?= (int) $password_rules['require_number'] ?>,
        requireSpecial: <?= (int) $password_rules['require_special'] ?>
    };
</script>
<div class="main-content">
    <!-- ------------------------------------------------------------------- -->
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('add_provider', "Add Provider") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/partners') ?>"><i
                            class="fas fa-handshake text-warning"></i> <?= labels('provider', 'Provider') ?></a></div>
                <div class="breadcrumb-item"><?= labels('add_provider', " Add Provider") ?></a></div>
            </div>
        </div>
        <?= form_open('/admin/partner/insert_partner', ['method' => "post", 'class' => 'add-provider-with-subscription', 'id' => 'add_partner', 'enctype' => "multipart/form-data", 'novalidate' => 'novalidate']); ?>

        <script>
            window.stepperLabels = {
                basic_info: "<?= labels('basic_info', 'Basic Info') ?>",
                business_settings: "<?= labels('business_settings', 'Business Settings') ?>",
                location: "<?= labels('location', 'Location') ?>",
                working_hours: "<?= labels('working_hours', 'Working Hours') ?>",
                scheduling_configuration: "<?= labels('scheduling_configuration', 'Scheduling Configuration') ?>",
                configure_scheduling_subtitle: "<?= labels('configure_scheduling_subtitle', 'Configure how booking slots are generated and the rules for the booking window.') ?>",
                media_and_docs: "<?= labels('media_and_docs', 'Media & Docs') ?>",
                bank_details: "<?= labels('bank_details', 'Bank Details') ?>",
                seo_settings: "<?= labels('seo_settings', 'SEO Settings') ?>",
                review: "<?= labels('review_step', 'Review') ?>",
                name: "<?= labels('name', 'Name') ?>",
                company_name: "<?= labels('company_name', 'Company Name') ?>",
                about_provider: "<?= labels('about_provider', 'About Provider') ?>",
                email: "<?= labels('email', 'Email') ?>",
                phone_number: "<?= labels('phone_number', 'Phone Number') ?>",
                login_type: "<?= labels('login_type', 'Login Type') ?>",
                slug: "<?= labels('slug', 'Slug') ?>",
                type: "<?= labels('type', 'Type') ?>",
                visiting_charges: "<?= labels('visiting_charges', 'Visiting Charges') ?>",
                advance_booking_days: "<?= labels('advance_booking_days', 'Advance Booking Days') ?>",
                number_Of_members: "<?= labels('number_Of_members', 'Number of Members') ?>",
                max_serviceable_distance: "<?= labels('max_serviceable_distance', 'Max Serviceable Distance') ?>",
                at_store: "<?= labels('at_store', 'At Store') ?>",
                at_doorstep: "<?= labels('at_doorstep', 'At Doorstep') ?>",
                allow_post_booking_chat: "<?= labels('allow_post_booking_chat', 'Allow Post Booking Chat') ?>",
                allow_pre_booking_chat: "<?= labels('allow_pre_booking_chat', 'Allow Pre Booking Chat') ?>",
                need_approval_for_the_service: "<?= labels('need_approval_for_the_service', 'Need Approval for Service') ?>",
                city: "<?= labels('city', 'City') ?>",
                address: "<?= labels('address', 'Address') ?>",
                latitude: "<?= labels('latitude', 'Latitude') ?>",
                longitude: "<?= labels('longitude', 'Longitude') ?>",
                working_days: "<?= labels('working_days', 'Working Days') ?>",
                image: "<?= labels('image', 'Image') ?>",
                banner_image: "<?= labels('banner_image', 'Banner Image') ?>",
                other_images: "<?= labels('other_images', 'Other Images') ?>",
                meta_title: "<?= labels('meta_title', 'Meta Title') ?>",
                meta_keywords: "<?= labels('meta_keywords', 'Meta Keywords') ?>",
                meta_description: "<?= labels('meta_description', 'Meta Description') ?>",
                edit: "<?= labels('edit', 'Edit') ?>",
                enabled: "<?= labels('enabled', 'Enabled') ?>",
                disabled_label: "<?= labels('disabled_label', 'Disabled') ?>",
                closed: "<?= labels('closed', 'Closed') ?>",
                defaultLanguageCode: "<?= $current_language ?>",
                provider_identity_contact_account: "<?= labels('provider_identity_contact_account', 'Provider identity, contact details, and account setup') ?>",
                configure_business_charges: "<?= labels('configure_business_charges', 'Configure business type, charges, and operational preferences') ?>",
                set_provider_location: "<?= labels('set_provider_location', 'Set the provider service location on the map') ?>",
                configure_working_schedule: "<?= labels('configure_working_schedule', 'Configure the weekly working schedule') ?>",
                upload_images_documents: "<?= labels('upload_images_documents', 'Upload profile images and required documents') ?>",
                enter_bank_account_details: "<?= labels('enter_bank_account_details', 'Enter bank account and payment details') ?>",
                configure_seo_meta_tags: "<?= labels('configure_seo_meta_tags', 'Configure SEO meta tags for better search visibility') ?>",
                verify_details_before_submitting: "<?= labels('verify_details_before_submitting', 'Verify all provider details before submitting') ?>",
                validation_required: "<?= labels('validation_required', '{field} is required.') ?>",
                validation_invalid_email: "<?= labels('validation_invalid_email', 'Please enter a valid email address.') ?>",
                validation_invalid_value: "<?= labels('validation_invalid_value', 'Please enter a valid value for {field}.') ?>",
                validation_invalid_format: "<?= labels('validation_invalid_format', 'Please enter a valid format for {field}.') ?>",
                validation_too_short: "<?= labels('validation_too_short', '{field} must be at least {min} characters.') ?>",
                validation_min_value: "<?= labels('validation_min_value', '{field} must be at least {min}.') ?>",
                working_hours_applied_to_all: "<?= labels('working_hours_applied_to_all', 'Working hours applied to all days') ?>",
                working_schedule: "<?= labels('working_schedule', 'Working Schedule') ?>",
                working_schedule_subtitle: "<?= labels('working_schedule_subtitle', 'Configure when your services are available to customers.') ?>",
                shift_end_after_start: "<?= labels('shift_end_after_start', 'End time must be after start time') ?>",
                password_requirements_not_met: "<?= labels('password_requirements_not_met', 'Password does not meet the requirements. Please check the rules above.') ?>"
            };
        </script>

        <div class="stepper-container">
            <!-- Mobile horizontal step indicator (hidden on desktop) -->
            <div class="stepper-horizontal">
                <?php
                $stepIcons = [
                    1 => 'fa-user',
                    2 => 'fa-briefcase',
                    3 => 'fa-map-marker-alt',
                    4 => 'fa-clock',
                    5 => 'fa-sliders-h',
                    6 => 'fa-images',
                    7 => 'fa-university',
                    8 => 'fa-magnifying-glass',
                    9 => 'fa-check-circle',
                ];
                $stepLabelsMap = [
                    1 => ['basic_info', 'Basic Info'],
                    2 => ['business_settings', 'Business Settings'],
                    3 => ['location', 'Location'],
                    4 => ['working_hours', 'Working Hours'],
                    5 => ['scheduling_configuration', 'Scheduling Configuration'],
                    6 => ['media_and_docs', 'Media & Docs'],
                    7 => ['bank_details', 'Bank Details'],
                    8 => ['seo_settings', 'SEO Settings'],
                    9 => ['review_step', 'Review'],
                ];
                foreach ($stepIcons as $num => $icon):
                    $activeClass = $num === 1 ? ' active' : ' disabled';
                    ?>
                    <div class="step-h-item<?= $activeClass ?>" data-step="<?= $num ?>">
                        <div class="step-h-icon"><i class="fas <?= $icon ?>"></i></div>
                        <span class="step-h-label"><?= labels($stepLabelsMap[$num][0], $stepLabelsMap[$num][1]) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="stepper-sidebar">
                <div class="step-item active" data-step="1">
                    <div class="step-icon"><i class="fas fa-user"></i></div>
                    <span class="step-label"><?= labels('basic_info', 'Basic Info') ?></span>
                </div>
                <div class="step-item disabled" data-step="2">
                    <div class="step-icon"><i class="fas fa-briefcase"></i></div>
                    <span class="step-label"><?= labels('business_settings', 'Business Settings') ?></span>
                </div>
                <div class="step-item disabled" data-step="3">
                    <div class="step-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <span class="step-label"><?= labels('location', 'Location') ?></span>
                </div>
                <div class="step-item disabled" data-step="4">
                    <div class="step-icon"><i class="fas fa-clock"></i></div>
                    <span class="step-label"><?= labels('working_hours', 'Working Hours') ?></span>
                </div>
                <div class="step-item disabled" data-step="5">
                    <div class="step-icon"><i class="fas fa-sliders-h"></i></div>
                    <span
                        class="step-label"><?= labels('scheduling_configuration', 'Scheduling Configuration') ?></span>
                </div>
                <div class="step-item disabled" data-step="6">
                    <div class="step-icon"><i class="fas fa-images"></i></div>
                    <span class="step-label"><?= labels('media_and_docs', 'Media & Docs') ?></span>
                </div>
                <div class="step-item disabled" data-step="7">
                    <div class="step-icon"><i class="fas fa-university"></i></div>
                    <span class="step-label"><?= labels('bank_details', 'Bank Details') ?></span>
                </div>
                <div class="step-item disabled" data-step="8">
                    <div class="step-icon"><i class="fas fa-magnifying-glass"></i></div>
                    <span class="step-label"><?= labels('seo_settings', 'SEO Settings') ?></span>
                </div>
                <div class="step-item disabled" data-step="9">
                    <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                    <span class="step-label"><?= labels('review_step', 'Review') ?></span>
                </div>
            </div>

            <div class="stepper-content">
                <div class="step-content-header">
                    <div class="step-content-header-text">
                        <h2 id="stepper-step-title"><?= labels('basic_info', 'Basic Info') ?></h2>
                        <p id="stepper-step-subtitle">
                            <?= labels('provider_identity_contact_account', 'Provider identity, contact details, and account setup') ?>
                        </p>
                    </div>
                    <button type="button" id="apply_to_all_days"
                        class="btn btn-light text-primary font-weight-medium d-none align-items-center">
                        <i class="far fa-copy mr-2"></i>
                        <?= labels('apply_to_all', 'Apply to all') ?>
                    </button>
                </div>
                <div class="stepper-progress-bar">
                    <div class="progress-fill" style="width: 14%"></div>
                </div>

                <!-- STEP 1: Basic Info -->
                <div class="step-panel active" data-step="1">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                foreach ($sorted_languages as $index => $language) {
                                    ?>
                                    <div class="language-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-<?= $language['code'] ?>" data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span
                                            class="language-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?>    <?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;">
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    foreach ($sorted_languages as $index => $language) {
                        ?>
                        <div id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="username<?= $language['code'] ?>"
                                            <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('name', 'Name') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <input id="username<?= $language['code'] ?>" class="form-control" type="text"
                                            name="username[<?= $language['code'] ?>]"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('name', 'Name') ?> <?= labels('here', ' Here ') ?>"
                                            <?= $language['code'] == $current_language ? 'required' : '' ?>
                                            value="<?= isset($partner_translations[$language['code']]['username']) ? htmlspecialchars($partner_translations[$language['code']]['username']) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="company_name<?= $language['code'] ?>"
                                            <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('company_name', 'Company Name') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <input id="company_name<?= $language['code'] ?>" class="form-control" type="text"
                                            name="company_name[<?= $language['code'] ?>]"
                                            placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('company_name', 'the company name ') ?> <?= labels('here', ' Here ') ?>"
                                            <?= $language['code'] == $current_language ? 'required' : '' ?>
                                            <?= $language['is_default'] ? 'data-slug-source data-slug-target="#provider_slug"' : '' ?>
                                            value="<?= isset($partner_translations[$language['code']]['company_name']) ? htmlspecialchars($partner_translations[$language['code']]['company_name']) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="about_provider<?= $language['code'] ?>"
                                            <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('about_provider', 'About Provider') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <textarea id="about_provider<?= $language['code'] ?>" style="min-height:60px"
                                            class="form-control" <?= $language['code'] == $current_language ? 'required' : '' ?> type="text" name="about_provider[<?= $language['code'] ?>]" rowspan="10"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('about_provider', 'About Provider') ?> <?= labels('here', ' Here ') ?>"><?= isset($partner_translations[$language['code']]['about']) ? htmlspecialchars($partner_translations[$language['code']]['about']) : '' ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <label for="long_description<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('description', 'Description') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                    <textarea rows=10 class='form-control h-50 summernotes custome_reset'
                                        name="long_description[<?= $language['code'] ?>]"
                                        data-required="<?= $language['code'] == $current_language ? 'true' : 'false' ?>"><?= isset($partner_translations[$language['code']]['long_description']) ? htmlspecialchars($partner_translations[$language['code']]['long_description']) : '' ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="stepper-section-divider">
                        <p><?= labels('account_details', 'Account Details') ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="email" class="required"><?= labels('email', 'Email') ?></label>
                                <input id="email" class="form-control" type="email" name="email"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('email', 'Email') ?> <?= labels('here', ' Here ') ?>"
                                    value="<?= ((defined('ALLOW_VIEW_KEYS') && ALLOW_VIEW_KEYS == 0)) ? "XXXX@gmail.com" : (isset($personal_details['email']) ? $personal_details['email'] : "") ?>"
                                    required>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="phone"
                                    class="required"><?= labels('phone_number', 'Phone Number') ?></label>
                                <?php
                                $country_codes_list = fetch_details('country_codes');
                                $system_country_code = fetch_details('country_codes', ['is_default' => 1])[0];
                                $default_country_code = isset($system_country_code['calling_code']) ? $system_country_code['calling_code'] : "+91";
                                ?>
                                <div class="row no-gutters phone-input-row">
                                    <div class="col-4 col-md-3">
                                        <select class="form-control" name="country_code" id="country_code">
                                            <?php
                                            foreach ($country_codes_list as $key => $country_code) {
                                                $code = $country_code['calling_code'];
                                                $selected = ($default_country_code == $country_code['calling_code']) ? "selected" : "";
                                                echo "<option $selected value='$code'>$code</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-8 col-md-9">
                                        <input id="phone" class="form-control" type="text" min="4" maxlength="16"
                                            name="phone"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('phone_number', 'Phone Number') ?> <?= labels('here', ' Here ') ?>"
                                            value="<?= isset($personal_details['phone']) ? $personal_details['phone'] : '' ?>"
                                            required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password" class="required"><?= labels('password', 'Password') ?></label>
                                <div class="position-relative">
                                    <input id="password" class="form-control" type="password" name="password"
                                        placeholder="<?= labels('enter', 'Enter') ?> <?= labels('password', 'Password') ?> <?= labels('here', ' Here ') ?>"
                                        required style="padding-right: 2.5rem;">
                                    <button type="button" class="btn btn-link position-absolute" id="togglePassword"
                                        style="right: 0; top: 50%; transform: translateY(-50%); border: none; background: none; padding: 0.5rem; cursor: pointer; z-index: 10; color: #6c757d;"
                                        title="<?= labels('show_password', 'Show Password') ?>">
                                        <i class="fas fa-eye-slash" id="passwordToggleIcon"></i>
                                    </button>
                                </div>
                                <!-- Password strength: rules from Authentication Settings (conditional). -->
                                <div id="password-strength-indicator" class="mt-2 small"
                                    <?= ($password_rules['min_length'] === 0) ? 'style="display:none;"' : '' ?>>
                                    <?php if ($password_rules['min_length'] > 0) { ?>
                                        <div class="password-rule" id="rule-length" data-rule="length">
                                            <span class="rule-icon" aria-hidden="true">○</span>
                                            <span
                                                class="rule-label"><?= sprintf(labels('password_strength_min_length_n', 'At least %s characters'), (int) $password_rules['min_length']) ?></span>
                                        </div>
                                    <?php } ?>
                                    <?php if (!empty($password_rules['require_number'])) { ?>
                                        <div class="password-rule" id="rule-number" data-rule="number">
                                            <span class="rule-icon" aria-hidden="true">○</span>
                                            <span
                                                class="rule-label"><?= labels('password_strength_contains_number', 'Contains a number') ?></span>
                                        </div>
                                    <?php } ?>
                                    <?php if (!empty($password_rules['require_uppercase'])) { ?>
                                        <div class="password-rule" id="rule-uppercase" data-rule="uppercase">
                                            <span class="rule-icon" aria-hidden="true">○</span>
                                            <span
                                                class="rule-label"><?= labels('password_strength_contains_uppercase', 'Contains an uppercase letter') ?></span>
                                        </div>
                                    <?php } ?>
                                    <?php if (!empty($password_rules['require_lowercase'])) { ?>
                                        <div class="password-rule" id="rule-lowercase" data-rule="lowercase">
                                            <span class="rule-icon" aria-hidden="true">○</span>
                                            <span
                                                class="rule-label"><?= labels('password_strength_contains_lowercase', 'Contains a lowercase letter') ?></span>
                                        </div>
                                    <?php } ?>
                                    <?php if (!empty($password_rules['require_special'])) { ?>
                                        <div class="password-rule" id="rule-special" data-rule="special">
                                            <span class="rule-icon" aria-hidden="true">○</span>
                                            <span
                                                class="rule-label"><?= labels('password_strength_contains_special', 'Contains a special character') ?></span>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <!-- Login type: copied from duplicated provider; admin only. -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="login_type"
                                    class="required"><?= labels('login_type', 'Login Type') ?></label>
                                <?php
                                $dup_login_type = isset($personal_details['loginType']) ? $personal_details['loginType'] : 'phone';
                                $login_settings = get_settings('login_settings', true);
                                $email_auth_enabled = ($login_settings['email_authentication_enabled'] ?? 0) == 1;
                                ?>
                                <select class="form-control" name="login_type" id="login_type" required>
                                    <option value="phone" <?= ($dup_login_type === 'phone' || !$email_auth_enabled) ? 'selected' : '' ?>><?= labels('phone', 'Phone') ?></option>
                                    <?php if ($email_auth_enabled): ?>
                                        <option value="email" <?= ($dup_login_type === 'email') ? 'selected' : '' ?>>
                                            <?= labels('email', 'Email') ?></option>
                                    <?php endif; ?>
                                </select>
                                <small
                                    class="form-text text-muted"><?= labels('login_type_note', 'How the provider will sign in (phone or email).') ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Business Settings -->
                <div class="step-panel" data-step="2">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="provider_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                <input id="provider_slug" class="form-control" type="text" name="provider_slug"
                                    value="<?= isset($partner_details['slug']) ? $partner_details['slug'] : '' ?>"
                                    placeholder="<?= labels('enter_the_slug', 'Enter the slug') ?> ">
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    <?= labels('slug_note', 'Note: The slug must always be in English for better SEO and URL compatibility.') ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="type" class="required"><?= labels('type', 'Type') ?></label>
                                <select class="select2" name="type" id="type" required>
                                    <option disabled selected><?= labels('select_type', 'Select Type') ?></option>
                                    <option value="0" <?= isset($partner_details['type']) && $partner_details['type'] == '0' ? 'selected' : '' ?>>
                                        <?= labels('individual', 'Individual') ?></option>
                                    <option value="1" <?= isset($partner_details['type']) && $partner_details['type'] == '1' ? 'selected' : '' ?>>
                                        <?= labels('organization', 'Organization') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="visiting_charges "
                                    class="required"><?= labels('visiting_charges', 'Visiting Charges') ?><strong>(
                                        <?= $currency ?> )</strong>
                                </label>
                                <i data-content="<?= labels('data_content_for_visiting_charge', 'The customer will pay these fixed charges for every booking made at their doorstep.') ?>"
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <input id="visiting_charges" class="form-control" type="number" name="visiting_charges"
                                    min="0"
                                    value="<?= isset($partner_details['visiting_charges']) ? $partner_details['visiting_charges'] : '' ?>"
                                    oninput="this.value = Math.abs(this.value)"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('visiting_charges', 'Visiting Charges') ?> <?= labels('here', ' Here ') ?>"
                                    required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="number_of_members"
                                    class="required"><?= labels('number_Of_members', 'Number of Members') ?></label>
                                <i data-content="<?= labels('data_content_for_number_of_member', 'Currently, we\'re only gathering the total number of providers members for reference. Later on, we intend to use this information for future updates.') ?>"
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <input id="number_of_members" class="form-control" type="number"
                                    name="number_of_members" min="1"
                                    value="<?= isset($partner_details['number_of_members']) ? $partner_details['number_of_members'] : '' ?>"
                                    oninput="this.value = Math.abs(this.value)"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('number_Of_members', 'Number of Members') ?> <?= labels('here', ' Here ') ?>"
                                    required>
                            </div>
                        </div>
                        <?php if (($max_serviceable_distance_type ?? 'global') === 'provider_wise'): ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label
                                    for="max_serviceable_distance"><?= labels('max_serviceable_distance', 'Max Serviceable Distance') ?><strong>
                                        (
                                        <?= labels('km', 'km') ?>)
                                    </strong></label>
                                <i data-content="<?= labels('data_content_for_max_serviceable_distance', 'The system will use the distance values (KM) you provide to determine which bookings the provider can service.') ?>"
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <input id="max_serviceable_distance" class="form-control" type="number"
                                    name="max_serviceable_distance" min="0" oninput="this.value = Math.abs(this.value)"
                                    value="<?= isset($partner_details['max_serviceable_distance']) ? $partner_details['max_serviceable_distance'] : '' ?>"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('max_serviceable_distance', 'Max Serviceable Distance') ?> <?= labels('here', ' Here ') ?>">
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="stepper-section-divider">
                        <p><?= labels('preferences_and_toggles', 'Preferences & Toggles') ?></p>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card stepper-toggle-card active">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('is_approved', 'Is Approved') ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="is_approved"
                                                    name="is_approved" checked>
                                                <label class="custom-control-label" for="is_approved"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('is_approved_description', 'Enable to approve this provider immediately upon creation') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div
                                class="card stepper-toggle-card <?= isset($partner_details['at_store']) && $partner_details['at_store'] == '1' ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('at_store', 'At Store') ?></strong></div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="at_store"
                                                    name="at_store" <?= isset($partner_details['at_store']) && $partner_details['at_store'] == '1' ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="at_store"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('at_store_description', 'The provider needs to perform the service at their store. The customer will arrive at the store on a specific date and time.') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div
                                class="card stepper-toggle-card <?= isset($partner_details['at_doorstep']) && $partner_details['at_doorstep'] == '1' ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('at_doorstep', 'At Doorstep') ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="at_doorstep"
                                                    name="at_doorstep" <?= isset($partner_details['at_doorstep']) && $partner_details['at_doorstep'] == '1' ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="at_doorstep"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('at_doorstep_description', "The provider has to go to the customer's place to do the job. They must arrive at the customer's place on a set date and time.") ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div
                                class="card stepper-toggle-card <?= isset($partner_details['chat']) && $partner_details['chat'] == '1' ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col">
                                            <strong><?= labels('allow_post_booking_chat', 'Allow Post Booking Chat') ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="post_chat"
                                                    name="chat" <?= isset($partner_details['chat']) && $partner_details['chat'] == '1' ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="post_chat"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('post_booking_chat_description', 'Allow chat between customer and provider after a booking is confirmed') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div
                                class="card stepper-toggle-card <?= isset($partner_details['pre_chat']) && $partner_details['pre_chat'] == '1' ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col">
                                            <strong><?= labels('allow_pre_booking_chat', 'Allow Pre Booking Chat') ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="pre_chat"
                                                    name="pre_chat" <?= isset($partner_details['pre_chat']) && $partner_details['pre_chat'] == '1' ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="pre_chat"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('pre_booking_chat_description', 'Allow chat between customer and provider before a booking is made') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div
                                class="card stepper-toggle-card <?= isset($partner_details['need_approval_for_the_service']) && $partner_details['need_approval_for_the_service'] == '1' ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col">
                                            <strong><?= labels('need_approval_for_the_service', 'Need approval for the service ?') ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input"
                                                    id="need_approval_for_the_service"
                                                    name="need_approval_for_the_service"
                                                    <?= isset($partner_details['need_approval_for_the_service']) && $partner_details['need_approval_for_the_service'] == '1' ? 'checked' : '' ?>>
                                                <label class="custom-control-label"
                                                    for="need_approval_for_the_service"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small
                                                class="text-muted"><?= labels('need_approval_description', 'If enabled, the admin must approve services added by the provider before they are visible to customers') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Location -->
                <div class="step-panel" data-step="3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label
                                    for="partner_location"><?= labels('current_location', 'Current Location') ?></label>
                                <input id="partner_location" class="form-control" type="text" name="partner_location"
                                    placeholder="<?= labels('enter_a_location', 'Enter a location') ?>">
                                <ul id="suggestions" class="list-group position-absolute w-100" style="z-index: 1000;">
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div id="map_wrapper_div_partner">
                                <div id="partner_map">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-4">
                            <div class="form-group">
                                <div class="cities" id="cities_select">
                                    <label for="city" class="required"><?= labels('city', 'City') ?></label>
                                    <input type="text" id="city" name="city" class="form-control"
                                        placeholder="<?= labels('enter_your_providers_city_name', "Enter your provider's city name") ?>"
                                        value="<?= isset($personal_details['city']) ? $personal_details['city'] : '' ?>"
                                        required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-4">
                            <div class="form-group">
                                <label for="partner_latitude" class="required">
                                    <?= labels('latitude', 'Latitude') ?></label>
                                <input id="partner_latitude" class="form-control" type="text" name="partner_latitude"
                                    placeholder="<?= labels('latitude', 'Latitude') ?>"
                                    value="<?= isset($personal_details['latitude']) ? $personal_details['latitude'] : '' ?>"
                                    pattern="^-?(90(\.0{1,7})?|[0-8][0-9](\.[0-9]{1,7})?|[0-9](\.[0-9]{1,7})?)$"
                                    title="<?= labels('please_enter_valid_latitude', 'Latitude: -90 to 90, max 7 decimal places') ?>"
                                    required>
                            </div>
                        </div>
                        <div class="col-md-4 mt-4">
                            <div class="form-group">
                                <label for="partner_longitude"
                                    class="required"><?= labels('longitude', 'Longitude') ?></label>
                                <input id="partner_longitude" class="form-control" type="text" name="partner_longitude"
                                    placeholder="<?= labels('longitude', 'Longitude') ?>"
                                    value="<?= isset($personal_details['longitude']) ? $personal_details['longitude'] : '' ?>"
                                    pattern="^-?(180(\.0{1,7})?|1[0-7][0-9](\.[0-9]{1,7})?|[0-9]{1,2}(\.[0-9]{1,7})?)$"
                                    title="<?= labels('please_enter_valid_longitude', 'Longitude: -180 to 180, max 7 decimal places') ?>"
                                    required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="address" class="required"><?= labels('address', 'Address') ?></label>
                                <textarea id="address" class="form-control" style="min-height:60px" name="address"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('address', 'Address') ?> <?= labels('here', ' Here ') ?>"
                                    required><?= isset($partner_details['address']) ? trim($partner_details['address']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Working Hours -->
                <div class="step-panel" data-step="4">
                    <div class="working-days-list" id="working_days_grid">
                        <?php
                        $days = [
                            'monday' => 'Monday',
                            'tuesday' => 'Tuesday',
                            'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday',
                            'friday' => 'Friday',
                            'saturday' => 'Saturday',
                            'sunday' => 'Sunday'
                        ];
                        foreach ($days as $key => $day):
                            $dayShifts = $provider_shifts_by_day[$key] ?? [];
                            if (!empty($dayShifts)) {
                                $primaryShift = $dayShifts[0];
                                $opening_time = substr((string) $primaryShift['opening_time'], 0, 5);
                                $closing_time = substr((string) $primaryShift['closing_time'], 0, 5);
                                $is_open = (int) $primaryShift['is_open'] === 1;
                                $extraShifts = array_slice($dayShifts, 1);
                            } else {
                                $opening_time = '09:00';
                                $closing_time = '10:00';
                                $is_open = false;
                                $extraShifts = [];
                            }
                            ?>
                            <div class="schedule-day-card schedule-day-row <?= $is_open ? 'active' : '' ?>"
                                data-day="<?= $key ?>">
                                <div class="schedule-day-head">
                                    <div class="custom-control custom-switch mb-0">
                                        <input type="checkbox" class="custom-control-input day-toggle"
                                            id="day_toggle_<?= $key ?>" name="<?= $key ?>" <?= $is_open ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="day_toggle_<?= $key ?>"></label>
                                    </div>
                                    <div class="schedule-day-icon">
                                        <i class="far fa-clock"></i>
                                    </div>
                                    <h6 class="schedule-day-name mb-0"><?= labels($key, $day) ?></h6>
                                </div>

                                <div class="schedule-day-shifts">
                                    <div class="day-time-row d-flex align-items-center">
                                        <div class="flex-fill">
                                            <input type="time" class="form-control form-control-sm start_time"
                                                name="start_time[]" value="<?= $opening_time ?>">
                                        </div>
                                        <span class="text-muted mx-2 font-weight-bold">—</span>
                                        <div class="flex-fill endTime">
                                            <input type="time" class="form-control form-control-sm end_time"
                                                name="end_time[]" value="<?= $closing_time ?>">
                                        </div>
                                        <button type="button" class="btn btn-add-shift"
                                            aria-label="<?= labels('add_break_shift', 'Add Break / Shift') ?>"
                                            data-tooltip="<?= labels('add_break_shift', 'Add Break / Shift') ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <div class="extra-shifts">
                                        <?php foreach ($extraShifts as $extra):
                                            $extraStart = substr((string) ($extra['opening_time'] ?? ''), 0, 5);
                                            $extraEnd = substr((string) ($extra['closing_time'] ?? ''), 0, 5);
                                            ?>
                                            <div class="day-time-row d-flex align-items-center extra-shift-row">
                                                <div class="flex-fill">
                                                    <input type="time" class="form-control form-control-sm extra_start_time"
                                                        name="extra_shifts[<?= $key ?>][start][]" value="<?= $extraStart ?>">
                                                </div>
                                                <span class="text-muted mx-2 font-weight-bold">—</span>
                                                <div class="flex-fill">
                                                    <input type="time" class="form-control form-control-sm extra_end_time"
                                                        name="extra_shifts[<?= $key ?>][end][]" value="<?= $extraEnd ?>">
                                                </div>
                                                <button type="button" class="btn btn-sm btn-remove-shift ml-2"
                                                    aria-label="<?= labels('remove_shift', 'Remove shift') ?>">
                                                    <i class="far fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <span class="schedule-day-closed"><?= labels('closed', 'Closed') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- STEP 5: Scheduling Configuration -->
                <?php
                $ss = $slot_settings ?? [];
                $ss_interval = (int) ($ss['slot_interval'] ?? 30);
                $ss_allowMulti = (int) ($ss['allow_multiple_bookings'] ?? 0) === 1;
                $ss_capacity = (int) ($ss['slot_capacity'] ?? 1);
                $ss_minValue = (int) ($ss['min_advance_booking_value'] ?? 1);
                $ss_minUnit = (string) ($ss['min_advance_booking_unit'] ?? 'hours');
                $ss_maxDays = (int) ($ss['max_advance_booking_days'] ?? 30);
                $ss_sameDay = (int) ($ss['same_day_booking'] ?? 1) === 1;
                $ss_bufBefore = (int) ($ss['buffer_before'] ?? 0);
                $ss_bufAfter = (int) ($ss['buffer_after'] ?? 0);
                $ss_isIndividual = isset($partner_details['type']) && (int) $partner_details['type'] === 0;
                ?>
                <div class="step-panel" data-step="5" data-panel="scheduling">
                    <div class="scheduling-config-section">
                        <div class="stepper-section-divider">
                            <p><?= labels('slot_behavior', 'Slot Behavior') ?></p>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="slot_interval"
                                        class="required"><?= labels('slot_interval', 'Slot Interval') ?></label>
                                    <div class="input-group">
                                        <input id="slot_interval" class="form-control" type="number"
                                            name="slot_interval" min="5" max="240" step="5" value="<?= $ss_interval ?>"
                                            placeholder="5 - 240"
                                            title="<?= labels('slot_interval_invalid', 'Enter a value in steps of 5, between 5 and 240 minutes.') ?>"
                                            required>
                                        <div class="input-group-append">
                                            <span class="input-group-text"><?= labels('minutes', 'minutes') ?></span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        <?= labels('slot_interval_helper', 'Determines how booking start times are generated.') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4 my-1" id="capacity_settings_wrapper" <?= $ss_isIndividual ? 'style="display:none;"' : '' ?>>
                                <div class="form-group">
                                    <div class="d-flex">
                                        <div class="custom-control custom-switch mb-0"
                                            style="padding-top:0;align-items: unset;">
                                            <input type="checkbox" class="custom-control-input"
                                                id="allow_multiple_bookings" name="allow_multiple_bookings"
                                                <?= $ss_allowMulti ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="allow_multiple_bookings"></label>
                                        </div>
                                        <label for="allow_multiple_bookings" class="mb-0 ml-2">
                                            <?= labels('allow_multiple_bookings', 'Allow Multiple Concurrent Bookings') ?>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <?= labels('allow_multiple_bookings_description', 'Enable when more than one booking can be served at the same time (e.g. multiple staff or chairs).') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4" id="slot_capacity_col"
                                style="display: <?= (!$ss_isIndividual && $ss_allowMulti) ? 'block' : 'none' ?>;">
                                <div class="form-group">
                                    <label for="slot_capacity"
                                        class="required"><?= labels('concurrent_booking_capacity', 'Concurrent Booking Capacity') ?></label>
                                    <input id="slot_capacity" class="form-control" type="number" name="slot_capacity"
                                        min="1" max="100" value="<?= $ss_capacity ?>"
                                        oninput="this.value = Math.max(1, Math.min(100, Math.abs(parseInt(this.value) || 1)))"
                                        placeholder="<?= labels('enter', 'Enter') ?> <?= labels('concurrent_booking_capacity', 'Concurrent Booking Capacity') ?>">
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        <?= labels('concurrent_booking_capacity_helper', 'Maximum simultaneous booking capacity.') ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="stepper-section-divider">
                            <p><?= labels('booking_window_rules', 'Booking Window Rules') ?></p>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="min_advance_booking"
                                        class="required"><?= labels('minimum_advance_booking', 'Minimum Advance Booking') ?></label>
                                    <div class="input-group">
                                        <input id="min_advance_booking" class="form-control" type="number"
                                            name="min_advance_booking" min="0" value="<?= $ss_minValue ?>"
                                            oninput="this.value = Math.abs(parseInt(this.value) || 0)" required>
                                        <div class="input-group-append">
                                            <select class="form-control" name="min_advance_booking_unit"
                                                id="min_advance_booking_unit" style="min-width:110px;">
                                                <option value="minutes" <?= $ss_minUnit === 'minutes' ? 'selected' : '' ?>>
                                                    <?= labels('minutes', 'Minutes') ?></option>
                                                <option value="hours" <?= $ss_minUnit === 'hours' ? 'selected' : '' ?>>
                                                    <?= labels('hours', 'Hours') ?></option>
                                                <option value="days" <?= $ss_minUnit === 'days' ? 'selected' : '' ?>>
                                                    <?= labels('days', 'Days') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        <?= labels('min_advance_booking_helper', 'Earliest a customer can book before service start.') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="advance_booking_days"
                                        class="required"><?= labels('maximum_future_booking_days', 'Maximum Future Booking Days') ?></label>
                                    <input id="advance_booking_days" class="form-control" type="number"
                                        name="advance_booking_days" min="1" value="<?= $ss_maxDays ?>"
                                        oninput="this.value = Math.abs(parseInt(this.value) || 1)" required>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        <?= labels('max_future_booking_helper', 'Customers can book this many days into the future.') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="d-flex">
                                        <div class="custom-control custom-switch mb-0"
                                            style="padding-top:0;align-items: unset;">
                                            <input type="checkbox" class="custom-control-input" id="same_day_booking"
                                                name="same_day_booking" <?= $ss_sameDay ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="same_day_booking"></label>
                                        </div>
                                        <label for="same_day_booking" class="mb-0 ml-2">
                                            <?= labels('same_day_booking', 'Same Day Booking') ?>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <?= labels('same_day_booking_description', 'Allow customers to place bookings for today.') ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="stepper-section-divider">
                            <p><?= labels('default_buffer_rules', 'Default Buffer Rules') ?></p>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label
                                        for="buffer_before"><?= labels('buffer_before_booking', 'Buffer Before Booking') ?></label>
                                    <div class="input-group">
                                        <input id="buffer_before" class="form-control" type="number"
                                            name="buffer_before" min="0" value="<?= $ss_bufBefore ?>"
                                            oninput="this.value = Math.abs(parseInt(this.value) || 0)">
                                        <div class="input-group-append">
                                            <span class="input-group-text"><?= labels('minutes', 'minutes') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label
                                        for="buffer_after"><?= labels('buffer_after_booking', 'Buffer After Booking') ?></label>
                                    <div class="input-group">
                                        <input id="buffer_after" class="form-control" type="number" name="buffer_after"
                                            min="0" value="<?= $ss_bufAfter ?>"
                                            oninput="this.value = Math.abs(parseInt(this.value) || 0)">
                                        <div class="input-group-append">
                                            <span class="input-group-text"><?= labels('minutes', 'minutes') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    <?= labels('buffer_helper', 'Additional unavailable time added before or after bookings.') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 6: Media & Docs -->
                <div class="step-panel" data-step="6">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="image"
                                    class="<?= (!empty($partner_details) && isset($partner_details['partner_image'])) ? '' : 'required' ?>"><?= labels('image', 'Image') ?>
                                </label>
                                <small>(<?= labels('partner_image_recommended_size', 'We recommend 80x80 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="image" id="image" accept="image/*"
                                    <?= (!empty($partner_details) && isset($partner_details['partner_image'])) ? '' : 'required' ?>>
                                <?php if (!empty($partner_details) && isset($partner_details['partner_image'])): ?>
                                    <div class="mt-2">
                                        <img src="<?= esc($partner_details['partner_image']) ?>" alt="Provider Image"
                                            style="max-width: 120px; max-height: 80px; border-radius: 8px; border: 1px solid #d6d6dd;">
                                        <input type="hidden" name="existing_image"
                                            value="<?= esc($partner_details['partner_image']) ?>">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="banner_image"
                                    class="<?= (!empty($partner_details) && isset($partner_details['banner'])) ? '' : 'required' ?>"><?= labels('banner_image', 'Banner Image') ?></label>
                                <small>(<?= labels('partner_banner_image_recommended_size', 'We recommend 378x190 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="banner_image" id="banner_image"
                                    accept="image/*" <?= (!empty($partner_details) && isset($partner_details['banner'])) ? '' : 'required' ?>>
                                <?php if (!empty($partner_details) && isset($partner_details['banner'])): ?>
                                    <div class="mt-2">
                                        <img src="<?= esc($partner_details['banner']) ?>" alt="Banner Image"
                                            style="max-width: 120px; max-height: 80px; border-radius: 8px; border: 1px solid #d6d6dd;">
                                        <input type="hidden" name="existing_banner_image"
                                            value="<?= esc($partner_details['banner']) ?>">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group"> <label for="other_service_image_selector"
                                    class=""><?= labels('other_images', 'Other Image') ?></label>
                                <small>(<?= labels('other_image_recommended_size', 'We recommend 960 x 540 pixels') ?>)</small>
                                <input type="file" name="other_service_image_selector[]" class="filepond logo"
                                    id="other_service_image_selector" accept="image/*" multiple>
                                <div class="row mt-2" id="other_images_container">
                                    <?php
                                    if (!empty($partner_details['other_images'])) {
                                        $other_images_data = is_array($partner_details['other_images']) ?
                                            $partner_details['other_images'] :
                                            json_decode($partner_details['other_images'], true);

                                        if (is_array($other_images_data) && count($other_images_data) > 0) { ?>
                                            <div class="col-12 mb-2">
                                                <button type="button"
                                                    class="btn btn-primary btn-sm remove-all-other-images"><?= labels('remove_all_images', 'Remove All Images') ?></button>
                                            </div>
                                        <?php }

                                        if (is_array($other_images_data)) {
                                            foreach ($other_images_data as $index => $image) { ?>
                                                <div class="col-md-4 mb-2 other-image-container">
                                                    <div class="position-relative">
                                                        <img alt="no image found" width="130px"
                                                            style="border: solid #d6d6dd 1px; border-radius: 12px;" height="100px"
                                                            class="mt-2"
                                                            src="<?= isset($image) ? (strpos($image, 'http') === 0 ? $image : base_url($image)) : "" ?>">
                                                        <input type="hidden" name="existing_other_images[]"
                                                            value="<?= strpos($image, 'http') === 0 ? str_replace(base_url(), '', $image) : $image ?>">
                                                        <button type="button" class="btn btn-sm btn-danger remove-other-image"
                                                            data-image-index="<?= $index ?>"
                                                            style="position: absolute; top: 5px; right: 5px;"><i
                                                                class="fas fa-times"></i></button>
                                                        <input type="hidden" name="remove_other_images[<?= $index ?>]" value="0"
                                                            class="remove-flag">
                                                    </div>
                                                </div>
                                            <?php }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php $fileFieldConfigs = []; ?>
                    <div class="row">
                        <?php foreach ($documents_custom_fields ?? [] as $field):
                            $cfId = (int) ($field['id'] ?? 0);
                            $inputName = 'cf_' . $cfId;
                            $fieldType = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                            $required = !empty($field['required']);
                            $initialLabel = $custom_field_labels_by_language[$cfId][$current_language] ?? ($field['field_label'] ?? '');
                            $requiredAttr = $required ? 'required' : '';
                            $fieldFileConfig = is_array($field['file_config'] ?? null) ? $field['file_config'] : [];
                            if ($fieldType === 'file') {
                                $fileFieldConfigs[$inputName] = $fieldFileConfig;
                            }
                            ?>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="<?= $inputName ?>" class="<?= $required ? 'required' : '' ?>">
                                        <span class="pcf-custom-label-text" data-custom-field-id="<?= $cfId ?>">
                                            <?= htmlspecialchars($initialLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </label>

                                    <?= renderCustomFieldInput($inputName, $fieldType, $requiredAttr, '', $fieldFileConfig, '') ?>

                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>

                <!-- STEP 7: Bank Details -->
                <div class="step-panel" data-step="7">
                    <div class="bank-details-section">
                        <?php
                        $bankFields = array_values($bank_details_custom_fields ?? []);
                        $bankChunks = array_chunk($bankFields, 3);
                        ?>

                        <?php foreach ($bankChunks as $chunk): ?>
                            <div class="row">
                                <?php foreach ($chunk as $field):
                                    $cfId = (int) ($field['id'] ?? 0);
                                    $inputName = 'cf_' . $cfId;
                                    $fieldType = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                                    $required = !empty($field['required']);
                                    $initialLabel = $custom_field_labels_by_language[$cfId][$current_language] ?? ($field['field_label'] ?? '');
                                    $requiredAttr = $required ? 'required' : '';
                                    $placeholder = labels('enter', 'Enter') . ' ' . $initialLabel . ' ' . labels('here', 'Here');
                                    $fieldFileConfig = is_array($field['file_config'] ?? null) ? $field['file_config'] : [];
                                    $existingValue = (string) ($custom_field_values[$cfId] ?? '');
                                    if ($fieldType === 'file') {
                                        $fileFieldConfigs[$inputName] = $fieldFileConfig;
                                    }
                                    ?>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="<?= $inputName ?>" class="<?= $required ? 'required' : '' ?>">
                                                <span class="pcf-custom-label-text" data-custom-field-id="<?= $cfId ?>">
                                                    <?= htmlspecialchars($initialLabel, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </label>

                                            <?= renderCustomFieldInput($inputName, $fieldType, $requiredAttr, $placeholder, $fieldFileConfig, $fieldType !== 'file' ? $existingValue : '') ?>
                                        </div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        <?php endforeach ?>

                    </div>
                    <input type="hidden" name="partner_id_for_sub_bar" id="partner_id_for_sub_bar"
                        value="<?= service('uri')->getSegments()[3] ?>">
                </div>

                <!-- STEP 8: SEO Settings -->
                <div class="step-panel" data-step="8">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                foreach ($sorted_languages as $index => $language) {
                                    if ($language['is_default'] == 1) {
                                        $current_language_seo = $language['code'];
                                    }
                                    ?>
                                    <div class="language-option-seo position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-seo-<?= $language['code'] ?>" data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span
                                            class="language-text-seo px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?>    <?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-underline-seo"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;">
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    foreach ($sorted_languages as $index => $language) {
                        ?>
                        <div id="translationDivSeo-<?= $language['code'] ?>" <?= $language['code'] == $current_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label
                                            for="meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>"
                                            class="fa fa-question-circle" data-original-title="" title=""
                                            data-toggle="popover"></i>
                                        <input id="meta_title<?= $language['code'] ?>" class="form-control" type="text"
                                            name="meta_title[<?= $language['code'] ?>]"
                                            placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>"
                                            maxlength="255"
                                            value="<?= isset($partner_translations[$language['code']]['seo_title']) ? esc($partner_translations[$language['code']]['seo_title']) : '' ?>">
                                        <small
                                            class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label
                                            for="meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>"
                                            class="fa fa-question-circle" data-original-title="" title=""
                                            data-toggle="popover"></i>
                                        <input id="meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem"
                                            class="w-100" type="text" name="meta_keywords[<?= $language['code'] ?>][]"
                                            placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>"
                                            value="<?= isset($partner_translations[$language['code']]['seo_keywords']) ? esc($partner_translations[$language['code']]['seo_keywords']) : '' ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label
                                            for="meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>"
                                            class="fa fa-question-circle" data-original-title="" title=""
                                            data-toggle="popover"></i>
                                        <textarea id="meta_description<?= $language['code'] ?>" style="min-height:60px"
                                            class="form-control" type="text"
                                            name="meta_description[<?= $language['code'] ?>]" rowspan="10"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>"
                                            maxlength="500"><?= isset($partner_translations[$language['code']]['seo_description']) ? esc($partner_translations[$language['code']]['seo_description']) : '' ?></textarea>
                                        <small
                                            class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label
                                            for="schema_markup<?= $language['code'] ?>"><?= labels('schema_markup', 'Schema Markup') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                            data-toggle="popover" class="fa fa-question-circle" data-original-title=""
                                            title=""></i>
                                        <textarea id="schema_markup<?= $language['code'] ?>" style="min-height:60px"
                                            class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]"
                                            rowspan="10"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"><?= isset($partner_translations[$language['code']]['seo_schema_markup']) ? esc($partner_translations[$language['code']]['seo_schema_markup']) : '' ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                                <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>"
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="meta_image" id="meta_image" accept="image/*">
                                <?php if (!empty($partner_seo_settings['image'])): ?>
                                    <div class="position-relative d-inline-block mt-2" id="existing-meta-image-container">
                                        <img src="<?= esc($partner_seo_settings['image']) ?>" alt="SEO Image"
                                            style="max-width: 120px; max-height: 80px; border-radius: 8px;">
                                        <input type="hidden" name="existing_meta_image"
                                            value="<?= esc($partner_seo_settings['image']) ?>">
                                        <button type="button"
                                            class="btn btn-sm btn-danger remove-provider-seo-image-duplicate"
                                            style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; padding: 0; border-radius: 50%; font-size: 10px;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                <small
                                    class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 9: Review -->
                <div class="step-panel" data-step="9">
                    <div id="stepper-review-content"></div>
                </div>

                <!-- Footer -->
                <div class="stepper-footer">
                    <button type="button" class="btn-step-back" disabled style="visibility:hidden;">
                        <i class="fas fa-chevron-left"></i> <?= labels('back', 'Back') ?>
                    </button>
                    <button type="button" class="btn-step-next">
                        <?= labels('next_step', 'Next Step') ?> <i class="fas fa-chevron-right"></i>
                    </button>
                    <button type="submit" class="btn-step-submit submit_btn" style="display:none;">
                        <i class="fas fa-check"></i> <?= labels('add_provider', 'Add Provider') ?>
                    </button>
                </div>
            </div>
        </div>
        <?= form_close() ?>
    </section>
    <!-- ----------------------------------------------------------------------------------------------------- -->
</div>

<div class="modal fade" id="partner_subscriptions_add" tabindex="-1" role="dialog"
    aria-labelledby="partner_subscriptions_add" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl " role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLongTitle">
                    <?= labels('change_renew_plan', 'Change / Renew Subscription Plan') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="background-color: #f4f6f9;">
                <div class="row">
                    <?php if (!empty($subscription_details)): ?>
                        <?php foreach ($subscription_details as $row) { ?>
                            <div class="col-md-6 mb-md-3">
                                <div class="plan d-flex flex-column h-100">
                                    <div class="inner  h-100">
                                        <div class="plan_title">
                                            <b><?= !empty($row['translated_name']) ? $row['translated_name'] : $row['name'] ?></b>
                                        </div>
                                        <?php
                                        $price = calculate_subscription_price($row['id']);
                                        ;
                                        ?>
                                        <h5>
                                            <p class="plan_price"><b><?= $currency ?><?= $price[0]['price_with_tax'] ?></b></p>
                                        </h5>
                                        <ul class="features mb-3">
                                            <li>
                                                <span class="icon">
                                                    <svg height="24" width="24" viewBox="0 0 24 24"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M0 0h24v24H0z" fill="none"></path>
                                                        <path fill="currentColor"
                                                            d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z">
                                                        </path>
                                                    </svg>
                                                </span>
                                                <span><strong>
                                                        <?php
                                                        if ($row['order_type'] == "unlimited") {
                                                            echo labels('enjoyUnlimitedOrders', "Unlimited Orders: No limits, just success.");
                                                        } else {
                                                            echo labels('enjoyGenerousOrderLimitOf', "Enjoy a generous order limit of") . " " . $row['max_order_limit'] . " " . labels('ordersDuringYourSubscriptionPeriod', "orders during your subscription period");
                                                        }
                                                        ?>
                                                    </strong></span>
                                            </li>
                                            <li>
                                                <span class="icon">
                                                    <svg height="24" width="24" viewBox="0 0 24 24"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M0 0h24v24H0z" fill="none"></path>
                                                        <path fill="currentColor"
                                                            d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z">
                                                        </path>
                                                    </svg>
                                                </span>
                                                <span><strong>
                                                        <?php
                                                        if ($row['duration'] == "unlimited") {
                                                            echo labels('enjoySubscriptionForUnlimitedDays', "Lifetime Subscription – seize success without limits!");
                                                        } else {
                                                            echo labels('yourSubscriptionWillBeValidTill', "Your subscription will be valid for") . " " . $row['duration'] . " " . labels('days', "Days");
                                                        }
                                                        ?>
                                                    </strong>
                                            </li>
                                            <li>
                                                <span class="icon">
                                                    <svg height="24" width="24" viewBox="0 0 24 24"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path fill="currentColor"
                                                            d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z">
                                                        </path>
                                                    </svg>
                                                </span>
                                                <strong>
                                                    <?php
                                                    if ($row['is_commision'] == "yes") {
                                                        echo labels('commissionWillBeAppliedToYourEarnings', "Commission will be applied to your earnings");
                                                    } else {
                                                        echo labels('noNeedToPayExtraCommission', "Your income, your rules – no hidden commission charges on your profits");
                                                    }
                                                    ?>
                                                </strong>
                                            </li>
                                            <li>
                                                <span class="icon">
                                                    <svg height="24" width="24" viewBox="0 0 24 24"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M0 0h24v24H0z" fill="none"></path>
                                                        <path fill="currentColor"
                                                            d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z">
                                                        </path>
                                                    </svg>
                                                </span>
                                                <strong>
                                                    <?php
                                                    if ($row['is_commision'] == "yes") {
                                                        echo labels('commissionThreshold', "Pay on Delivery threshold: The Pay on Service option will be closed, once the cash of the " . $currency . $row['commission_threshold']) . " " . labels('AmountIsReached', " amount is reached");
                                                    } else {
                                                        echo labels('noThresholdOnPayOnDeliveryAmount', "There is no threshold on the Pay on Service amount.");
                                                    }
                                                    ?>
                                                </strong>
                                            </li>

                                            <?php if ($price[0]['tax_percentage'] != "0") { ?>
                                                <li>
                                                    <span class="icon">
                                                        <svg height="24" width="24" viewBox="0 0 24 24"
                                                            xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M0 0h24v24H0z" fill="none"></path>
                                                            <path fill="currentColor"
                                                                d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z">
                                                            </path>
                                                        </svg>
                                                    </span>
                                                    <strong>
                                                        <?php
                                                        echo labels('tax_included', $price[0]['tax_percentage'] . "% tax included");
                                                        ?>
                                                    </strong>
                                                </li>
                                            <?php } ?>
                                            <a href="javascript:void(0);" class="toggle-description">
                                                <span class="icon" style="font-size: 11px;">
                                                    <i class="fa-solid fa-eye fa-sm"></i>
                                                    <i class="fa-solid fa-eye-slash fa-sm"></i>
                                                </span>
                                                <span class="text"><?= labels('view_description', 'View Description') ?></span>
                                            </a>
                                            <div class="description">
                                                <?= $row['description'] ?>
                                            </div>
                                        </ul>
                                    </div>
                                    <form class="needs-validation" id="make_payment_for_subscription1" method="POST"
                                        action="<?= base_url('admin/assign_subscription_to_partner') ?>">
                                        <input type="hidden" name="stripe_key_id" id="stripe_key_id"
                                            value="pk_test_51Hh90WLYfObhNTTwooBHwynrlfiPo2uwxyCVqGNNCWGmpdOHuaW4rYS9cDldKJ1hxV5ik52UXUDSYgEM66OX45550065US7tRX" />
                                        <input id="subscription_id" name="subscription_id" class="form-control"
                                            value="<?= $row['id'] ?>" type="hidden" name="">
                                        <input id="payment_method" name="payment_method" class="form-control" value="stripe"
                                            type="hidden" name="">
                                        <input type="hidden" name="stripe_client_secret" id="stripe_client_secret" value="" />
                                        <input type="hidden" name="partner_id" id="partner_id"
                                            value="<?= service('uri')->getSegments()[3] ?>">
                                        <input type="hidden" name="stripe_payment_id" id="stripe_payment_id" value="" />
                                        <div class="card-footer mt-auto">
                                            <div class="form-group m-0 p-0">
                                                <button type="button" class="btn btn-block text-white"
                                                    style="background-color:#344052;"
                                                    onclick="confirmAssign(<?= $row['id'] ?>)">Assign</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php } ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <?= labels('no_subscription_available', 'No subscription plans are available right now.') ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"> <?= labels('close', 'Close') ?>
                </button>
                <button type="button" class="btn btn-primary"> <?= labels('save_changes', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // At store / at doorstep mutual fallback — at least one must be on
        $('#at_store').on('change', function () {
            if (!this.checked && !$('#at_doorstep').prop('checked')) {
                $('#at_doorstep').prop('checked', true).trigger('change');
            }
        });
        $('#at_doorstep').on('change', function () {
            if (!this.checked && !$('#at_store').prop('checked')) {
                $('#at_store').prop('checked', true).trigger('change');
            }
        });

        // Handle individual image removal with toggle functionality
        $('.remove-other-image').on('click', function () {
            const button = this;
            const container = $(this).closest('.position-relative');
            const removeFlag = container.find('.remove-flag');

            if (removeFlag.length) {
                if (removeFlag.val() === "0") {
                    removeFlag.val("1");
                    container.find('img').css('opacity', '0.5');
                    $(button).removeClass('btn-danger').addClass('btn-primary');
                    $(button).html('<i class="fas fa-undo"></i>');
                } else {
                    removeFlag.val("0");
                    container.find('img').css('opacity', '1');
                    $(button).removeClass('btn-primary').addClass('btn-danger');
                    $(button).html('<i class="fas fa-times"></i>');
                }
            }
        });

        // Handle remove all images button
        $('.remove-all-other-images').on('click', function () {
            if (confirm('<?= labels('are_you_sure_to_remove_all_images', 'Are you sure you want to remove all images?') ?>')) {
                const otherImagesContainer = $('#other_images_container');
                const imageContainers = otherImagesContainer.find('.other-image-container');

                imageContainers.each(function () {
                    const container = $(this).find('.position-relative');
                    const removeFlag = container.find('.remove-flag');
                    const button = container.find('.remove-other-image');

                    if (removeFlag.length) {
                        removeFlag.val("1");
                        container.find('img').css('opacity', '0.5');
                        button.removeClass('btn-danger').addClass('btn-primary');
                        button.html('<i class="fas fa-undo"></i>');
                    }
                });
            }
        });

        // Handle file input change to update required attributes for existing images
        function updateImageRequiredAttributes() {
            const mainImageInput = $('#image');
            const existingMainImage = $('input[name="existing_image"]').val();
            const mainImageLabel = mainImageInput.siblings('label');
            if (existingMainImage) {
                mainImageInput.prop('required', false);
                mainImageLabel.removeClass('required');
            } else {
                mainImageInput.prop('required', true);
                mainImageLabel.addClass('required');
            }

            const bannerImageInput = $('#banner_image');
            const existingBannerImage = $('input[name="existing_banner_image"]').val();
            const bannerImageLabel = bannerImageInput.siblings('label');
            if (existingBannerImage) {
                bannerImageInput.prop('required', false);
                bannerImageLabel.removeClass('required');
            } else {
                bannerImageInput.prop('required', true);
                bannerImageLabel.addClass('required');
            }
        }

        updateImageRequiredAttributes();
    });

    $('#type').change(function () {
        var doc = document.getElementById("type");
        if (doc.options[doc.selectedIndex].value == 0) {
            $("#number_of_members").val('1');
            $("#number_of_members").attr("readOnly", "readOnly");
        } else if (doc.options[doc.selectedIndex].value == 1) {
            $("#number_of_members").val('');
            $("#number_of_members").removeAttr("readOnly");
        }
        applyCapacityVisibility();
    }).trigger('change');

    function applyCapacityVisibility() {
        var typeVal = String($('#type').val());
        if (typeVal === '0') {
            $('#capacity_settings_wrapper').hide();
            $('#slot_capacity_col').hide();
        } else {
            $('#capacity_settings_wrapper').show();
            toggleCapacityField();
        }
    }

    function toggleCapacityField() {
        var allow = $('#allow_multiple_bookings').is(':checked');
        var typeVal = String($('#type').val());
        $('#slot_capacity_col').toggle(allow && typeVal !== '0');
    }

    $(document).on('change', '#allow_multiple_bookings', toggleCapacityField);
    $(document).ready(function () { applyCapacityVisibility(); });
    $('.start_time').change(function () {
        var doc = $(this).val();
        $(this).parent().siblings(".endTime").children().attr('min', doc);
    });
</script>

<script>
    function confirmAssign(subscriptionId) {
        event.preventDefault();
        Swal.fire({
            title: "<?= labels('are_your_sure', 'Are you sure?') ?>",
            text: "<?= labels('once_you_assign_this_subscription_plan_you_cannot_assign_again_until_the_current_plan_expires_choose_wisely', 'Once you assign this subscription plan, you cannot assign again until the current plan expires. Choose wisely!') ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '<?= labels('yes_proceed', 'Yes, proceed!') ?>',
            cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('subscription_id').value = subscriptionId;
                document.getElementById('make_payment_for_subscription1').submit();
            }
        });
    }

    // Handle provider SEO image removal for duplicate scenario (UI only, no database deletion)
    $(document).on('click', '.remove-provider-seo-image-duplicate', function () {
        const button = $(this);
        const container = button.closest('.position-relative');
        if (confirm('<?= labels('are_you_sure_to_remove_seo_image', 'Are you sure you want to remove this SEO image?') ?>')) {
            container.remove();
        }
    });

    // Initialize Tagify for meta keywords field
    $(document).ready(function () {
        <?php foreach ($sorted_languages as $language) { ?>
            var metaKeywordsInput<?= $language['code'] ?> = document.querySelector('input[id=meta_keywords<?= $language['code'] ?>]');
            if (metaKeywordsInput<?= $language['code'] ?> != null) {
                new Tagify(metaKeywordsInput<?= $language['code'] ?>);
            }
        <?php } ?>
    });
</script>

<script>
    $(function () {
        let popoverTimer;
        let currentPopover = null;
        let isOverPopover = false;
        let isOverTrigger = false;

        $('[data-toggle="popover"]').popover({
            html: true,
            trigger: 'manual',
            container: 'body'
        }).on('mouseenter', function () {
            const $this = $(this);
            isOverTrigger = true;
            clearTimeout(popoverTimer);

            if (currentPopover && currentPopover[0] !== $this[0]) {
                currentPopover.popover('hide');
            }

            currentPopover = $this;
            $this.popover('show');

        }).on('mouseleave', function () {
            isOverTrigger = false;
            startHideTimer();
        });

        $(document).on('mouseenter', '.popover', function () {
            isOverPopover = true;
            clearTimeout(popoverTimer);
        }).on('mouseleave', '.popover', function () {
            isOverPopover = false;
            startHideTimer();
        });

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

<script>
    // Password requirements validation on submit (uses Authentication Settings)
    $(document).on('submit', '#add_partner', function (e) {
        if (typeof window.passwordStrengthValid === 'function' && !window.passwordStrengthValid()) {
            e.preventDefault();
            if (typeof toastr !== 'undefined') {
                toastr.warning("<?= labels('password_requirements_not_met', 'Password does not meet the requirements. Please check the rules above.') ?>");
            } else {
                alert("<?= labels('password_requirements_not_met', 'Password does not meet the requirements. Please check the rules above.') ?>");
            }
            return false;
        }
    });

    // Simple password visibility toggle for duplicate provider form
    document.addEventListener('DOMContentLoaded', function () {
        const passwordInput = document.getElementById('password');
        const toggleButton = document.getElementById('togglePassword');
        const toggleIcon = document.getElementById('passwordToggleIcon');

        if (!passwordInput || !toggleButton || !toggleIcon) {
            return;
        }

        toggleButton.addEventListener('click', function () {
            const showingPassword = passwordInput.type === 'text';
            passwordInput.type = !showingPassword ? 'password' : 'text';

            toggleIcon.classList.toggle('fa-eye-slash', !showingPassword);
            toggleIcon.classList.toggle('fa-eye', showingPassword);

            toggleButton.setAttribute(
                'title',
                showingPassword ?
                    "<?= labels('show_password', 'Show Password') ?>" :
                    "<?= labels('hide_password', 'Hide Password') ?>"
            );
        });
    });
</script>

<script>
    $(document).ready(function () {
        // Ensure FilePond plugins are registered before creating custom field instances,
        // since this inline script runs before custom.js which normally registers them.
        if (typeof FilePondPluginFileValidateType !== 'undefined') {
            FilePond.registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateSize, FilePondPluginFileValidateType);
        }

        // FilePond initialization for custom field file inputs.
        const customFieldFileConfigs = <?= json_encode($fileFieldConfigs ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        Object.keys(customFieldFileConfigs).forEach(function (fieldKey) {
            var el = document.getElementById(fieldKey);
            if (!el) { return; }
            var cfg = customFieldFileConfigs[fieldKey] || {};
            var allowedTypes = Array.isArray(cfg.allowed_types) ? cfg.allowed_types : [];
            // Convert file extensions to MIME types for FilePond's acceptedFileTypes.
            var extToMime = {
                '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.gif': 'image/gif',
                '.webp': 'image/webp', '.bmp': 'image/bmp', '.svg': 'image/svg+xml',
                '.tif': 'image/tiff', '.tiff': 'image/tiff',
                '.mp4': 'video/mp4', '.mov': 'video/quicktime', '.avi': 'video/x-msvideo',
                '.mkv': 'video/x-matroska', '.webm': 'video/webm', '.wmv': 'video/x-ms-wmv',
                '.flv': 'video/x-flv', '.m4v': 'video/x-m4v',
                '.mp3': 'audio/mpeg', '.wav': 'audio/wav', '.aac': 'audio/aac',
                '.ogg': 'audio/ogg', '.flac': 'audio/flac', '.m4a': 'audio/x-m4a', '.wma': 'audio/x-ms-wma',
                '.pdf': 'application/pdf',
                '.doc': 'application/msword', '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                '.xls': 'application/vnd.ms-excel', '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                '.ppt': 'application/vnd.ms-powerpoint', '.pptx': 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                '.txt': 'text/plain', '.csv': 'text/csv', '.rtf': 'application/rtf'
            };
            allowedTypes = allowedTypes.map(function (t) { return extToMime[t.toLowerCase()] || t; })
                .filter(function (v, i, a) { return a.indexOf(v) === i; });
            var maxSizeMb = parseInt(cfg.max_size_mb, 10);
            var maxFiles = Math.max(1, parseInt(cfg.max_files, 10) || 1);

            var opts = {
                credits: null,
                storeAsFile: true,
                allowMultiple: maxFiles > 1,
                maxFiles: maxFiles,
                labelIdle: drag_and_drop_files_here + ' ' + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
                allowFileSizeValidation: maxSizeMb > 0,
                labelMaxFileSizeExceeded: file_is_too_large,
                labelMaxFileSize: maximum_file_size_is + ' {filesize}',
                allowFileTypeValidation: allowedTypes.length > 0,
                labelFileTypeNotAllowed: file_of_invalid_type,
                fileValidateTypeLabelExpectedTypes: 'Expects {allButLastType} or {lastType}',
                allowPdfPreview: true,
                pdfPreviewHeight: 320,
                pdfComponentExtraParams: 'toolbar=0&navpanes=0&scrollbar=0&view=fitH',
                allowVideoPreview: true,
                allowAudioPreview: true,
            };
            if (maxSizeMb > 0) { opts.maxFileSize = maxSizeMb + 'MB'; }
            if (allowedTypes.length > 0) { opts.acceptedFileTypes = allowedTypes; }

            $(el).filepond(opts);
        });

        // select default language
        let default_language = '<?= $current_language ?>';
        const customFieldFallbackLanguage = default_language;
        const customFieldLabelsByLanguage = <?= json_encode($custom_field_labels_by_language ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Swap custom field label text instantly on language switch.
        function updateCustomFieldLabels(language) {
            document.querySelectorAll('.pcf-custom-label-text[data-custom-field-key]').forEach(function (el) {
                const key = el.getAttribute('data-custom-field-key');
                const byLang = customFieldLabelsByLanguage?.[key] ?? null;
                const nextLabel = (byLang && byLang[language]) ? byLang[language] :
                    (byLang && byLang[customFieldFallbackLanguage]) ? byLang[customFieldFallbackLanguage] : null;
                if (nextLabel !== null) { el.textContent = nextLabel; }
            });
        }

        $(document).on('click', '.language-option', function () {
            const language = $(this).data('language');

            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            if (language != default_language) {
                $('#translationDiv-' + language).show();
                $('#translationDiv-' + default_language).hide();
            }

            // Update provider documents + bank details labels.
            updateCustomFieldLabels(language);

            default_language = language;
        });

        // SEO language switching
        let default_language_seo = '<?= $current_language_seo ?>';

        $(document).on('click', '.language-option-seo', function () {
            const language = $(this).data('language');

            $('.language-underline-seo').css('width', '0%');
            $('#language-seo-' + language).find('.language-underline-seo').css('width', '100%');

            $('.language-text-seo').removeClass('text-primary fw-medium');
            $('.language-text-seo').addClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').removeClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').addClass('text-primary');

            if (language != default_language_seo) {
                $('#translationDivSeo-' + language).show();
                $('#translationDivSeo-' + default_language_seo).hide();
            }

            default_language_seo = language;
        });
    });
</script>