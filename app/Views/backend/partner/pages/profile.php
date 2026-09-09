<?php
helper('function');
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

function renderCustomFieldFilePreview(string $url, string $label): string
{
    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    $escaped = esc($url);
    $escapedLabel = esc($label);

    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff'];
    $videoExts = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v'];
    $audioExts = ['mp3', 'wav', 'aac', 'ogg', 'flac', 'm4a', 'wma'];

    if (in_array($ext, $imageExts, true)) {
        return sprintf(
            '<img alt="%s" width="130px" style="border: 1px solid #e5e7eb; border-radius: 12px;" height="100px" class="mt-2" src="%s">',
            $escapedLabel,
            $escaped
        );
    }

    if (in_array($ext, $videoExts, true)) {
        return sprintf(
            '<video controls width="250" class="mt-2" style="border-radius: 12px; border: 1px solid #e5e7eb;"><source src="%s">%s</video>',
            $escaped,
            labels('video_not_supported', 'Your browser does not support the video tag.')
        );
    }

    if (in_array($ext, $audioExts, true)) {
        return sprintf(
            '<audio controls class="mt-2" style="max-width: 100%%;"><source src="%s">%s</audio>',
            $escaped,
            labels('audio_not_supported', 'Your browser does not support the audio tag.')
        );
    }

    if ($ext === 'pdf') {
        return sprintf(
            '<a href="%s" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-file-pdf mr-1"></i>%s</a>',
            $escaped,
            labels('view_pdf', 'View PDF')
        );
    }

    // All other documents — show icon + filename link for download/preview.
    $filename = basename($url);
    $iconMap = [
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'csv' => 'fa-file-csv',
        'ppt' => 'fa-file-powerpoint',
        'pptx' => 'fa-file-powerpoint',
        'txt' => 'fa-file-alt',
        'rtf' => 'fa-file-alt',
    ];
    $icon = $iconMap[$ext] ?? 'fa-file';
    return sprintf(
        '<a href="%s" target="_blank" class="btn btn-sm btn-outline-secondary mt-2"><i class="fas %s mr-1"></i>%s</a>',
        $escaped,
        $icon,
        esc($filename)
    );
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

$profile_slug_default_language = $sorted_languages[0]['code'] ?? 'en';
$profile_slug_english_language = '';
foreach ($sorted_languages as $language) {
    if ($language['is_default'] == 1) {
        $profile_slug_default_language = $language['code'];
    }
    if ($language['code'] === 'en') {
        $profile_slug_english_language = 'en';
    }
}
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('my_profile', "My Profile") ?></h1>
        </div>
        <?php if (empty($partner_details)) { ?>
            <div class="alert alert-info" role="alert">
                <?= labels('complete_kyc_then_access_panel', 'Please Complete Your KYC Then you can Access Panel') ?>
            </div>
        <?php } else if ($partner_details['is_approved'] == "0") { ?>
                <div class="alert alert-primary" role="alert">
                <?= labels('kyc_request_pending_wait_for_admin_action', 'Your KYC request is pending please wait for admin action') ?>.
                </div>
        <?php } else if ($partner_details['is_approved'] == "2") { ?>
                    <div class="alert alert-danger" role="alert">
                <?= labels('kyc_rejected_by_admin', 'Your KYC request is Rejected by Admin Please try again') ?>.
                    </div>
        <?php } ?>

        <?= form_open('/partner/update_profile', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'edit_partner', 'enctype' => "multipart/form-data", 'novalidate' => 'novalidate']); ?>

        <script>
            window.stepperMode = 'edit';
            window.stepperSkipSeo = true;
            window.stepperSkipWorkingHours = true;
            window.stepperLabels = {
                basic_info: "<?= labels('basic_info', 'Basic Info') ?>",
                business_settings: "<?= labels('business_settings', 'Business Settings') ?>",
                location: "<?= labels('location', 'Location') ?>",
                media_and_docs: "<?= labels('media_and_docs', 'Media & Docs') ?>",
                bank_details: "<?= labels('bank_details', 'Bank Details') ?>",
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
                image: "<?= labels('image', 'Image') ?>",
                banner_image: "<?= labels('banner_image', 'Banner Image') ?>",
                other_images: "<?= labels('other_images', 'Other Images') ?>",
                edit: "<?= labels('edit', 'Edit') ?>",
                enabled: "<?= labels('enabled', 'Enabled') ?>",
                disabled_label: "<?= labels('disabled_label', 'Disabled') ?>",
                closed: "<?= labels('closed', 'Closed') ?>",
                defaultLanguageCode: "<?= $current_language ?>",
                provider_identity_contact_account: "<?= labels('provider_identity_contact_account', 'Provider identity, contact details, and account setup') ?>",
                configure_business_charges: "<?= labels('configure_business_charges', 'Configure business type, charges, and operational preferences') ?>",
                set_provider_location: "<?= labels('set_provider_location', 'Set the provider service location on the map') ?>",
                upload_images_documents: "<?= labels('upload_images_documents', 'Upload profile images and required documents') ?>",
                enter_bank_account_details: "<?= labels('enter_bank_account_details', 'Enter bank account and payment details') ?>",
                verify_details_before_submitting: "<?= labels('verify_details_before_submitting', 'Verify all provider details before submitting') ?>",
                validation_required: "<?= labels('validation_required', '{field} is required.') ?>",
                validation_invalid_email: "<?= labels('validation_invalid_email', 'Please enter a valid email address.') ?>",
                validation_invalid_value: "<?= labels('validation_invalid_value', 'Please enter a valid value for {field}.') ?>",
                validation_invalid_format: "<?= labels('validation_invalid_format', 'Please enter a valid format for {field}.') ?>",
                validation_too_short: "<?= labels('validation_too_short', '{field} must be at least {min} characters.') ?>",
                validation_min_value: "<?= labels('validation_min_value', '{field} must be at least {min}.') ?>"
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
                    4 => 'fa-images',
                    5 => 'fa-university',
                    6 => 'fa-check-circle',
                ];
                $stepLabelsMap = [
                    1 => ['basic_info', 'Basic Info'],
                    2 => ['business_settings', 'Business Settings'],
                    3 => ['location', 'Location'],
                    4 => ['media_and_docs', 'Media & Docs'],
                    5 => ['bank_details', 'Bank Details'],
                    6 => ['review_step', 'Review'],
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
                    <div class="step-icon"><i class="fas fa-images"></i></div>
                    <span class="step-label"><?= labels('media_and_docs', 'Media & Docs') ?></span>
                </div>
                <div class="step-item disabled" data-step="5">
                    <div class="step-icon"><i class="fas fa-university"></i></div>
                    <span class="step-label"><?= labels('bank_details', 'Bank Details') ?></span>
                </div>
                <div class="step-item disabled" data-step="6">
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
                </div>
                <div class="stepper-progress-bar">
                    <div class="progress-fill" style="width: 16%"></div>
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
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="username<?= $language['code'] ?>"
                                            <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('name', 'Name') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <input id="username<?= $language['code'] ?>" class="form-control"
                                            value="<?= isset($partner_details['translated_' . $language['code']]['username']) ? $partner_details['translated_' . $language['code']]['username'] : (isset($data['username']) ? $data['username'] : '') ?>"
                                            type="text" name="username[<?= $language['code'] ?>]"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('name', 'Name') ?> <?= labels('here', ' Here ') ?>"
                                            <?= $language['code'] == $current_language ? 'required' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="company_name<?= $language['code'] ?>"
                                            <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('company_name', 'Company Name') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <input id="company_name<?= $language['code'] ?>" class="form-control"
                                            value="<?= isset($partner_details['translated_' . $language['code']]['company_name']) ? $partner_details['translated_' . $language['code']]['company_name'] : (isset($partner_details['company_name']) ? $partner_details['company_name'] : '') ?>"
                                            type="text" name="company_name[<?= $language['code'] ?>]"
                                            placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('company_name', 'the company name ') ?> <?= labels('here', ' Here ') ?>"
                                            <?= $language['code'] == $current_language ? 'required' : '' ?>
                                            <?= $language['is_default'] ? 'data-slug-source data-slug-target="#provider_slug"' : '' ?>>
                                    </div>
                                </div>
                              	<div class="col-md-4">
                                    <div class="form-group">
                                        <label for="gstin"
                                            ><?= labels('gstin', 'GSTIN') ?></label>
                                        <input id="gstin" class="form-control"
                                            value="<?= (isset($data['gstin']) ? $data['gstin'] : '') ?>"
                                            type="text" name="gstin"
                                            placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('gstin', 'the company gstin ') ?> <?= labels('here', ' Here ') ?>"
                                            
                                            <?= $language['is_default'] ? 'data-slug-source data-slug-target="#provider_slug"' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="about<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('about_provider', 'About Provider') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <textarea id="about<?= $language['code'] ?>" style="min-height:60px"
                                            class="form-control" type="text" name="about[<?= $language['code'] ?>]"
                                            rowspan="10"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('about_provider', 'About Provider') ?> <?= labels('here', ' Here ') ?>"
                                            <?= $language['code'] == $current_language ? 'required' : '' ?>><?= isset($partner_details['translated_' . $language['code']]['about']) ? $partner_details['translated_' . $language['code']]['about'] : (isset($partner_details['about']) ? $partner_details['about'] : '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <label for="long_description<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('description', 'Description') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                    <textarea rows=10 class='form-control h-50 summernotes custome_reset'
                                        id="long_description"
                                        name="long_description[<?= $language['code'] ?>]"><?= isset($partner_details['translated_' . $language['code']]['long_description']) ? $partner_details['translated_' . $language['code']]['long_description'] : (isset($partner_details['long_description']) ? $partner_details['long_description'] : '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="stepper-section-divider">
                        <p><?= labels('personal_details', 'Personal Details') ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="email" class="required"><?= labels('email', 'Email') ?></label>
                                <?php
                                $emailReadonly = (isset($loginType) && $loginType === 'email') ? 'readonly' : '';
                                ?>
                                <input id="email" class="form-control" type="email" name="email"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('email', 'Email') ?> <?= labels('here', ' Here ') ?>"
                                    required
                                    value="<?= ((defined('ALLOW_VIEW_KEYS') && ALLOW_VIEW_KEYS == 0)) ? "XXXX@gmail.com" : (isset($data['email']) ? $data['email'] : "") ?>"
                                    <?= $emailReadonly ?>>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="phone"
                                    class="required"><?= labels('phone_number', 'Phone Number') ?></label>
                                <?php
                                $phoneReadonly = (isset($loginType) && $loginType === 'phone') ? 'readonly' : '';
                                $countryCodeDisabled = (isset($loginType) && $loginType === 'phone') ? 'disabled' : '';
                                ?>
                                <div class="row no-gutters phone-input-row">
                                    <div class="col-4 col-md-3">
                                        <select class="form-control" name="country_code" id="country_code"
                                            <?= $countryCodeDisabled ?>>
                                            <?php
                                            foreach ($country_codes as $key => $country_code) {
                                                $code = $country_code['calling_code'];
                                                $selected = ($selected_country_code == $country_code['calling_code']) ? "selected" : "";
                                                echo "<option $selected value='$code'>$code</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-8 col-md-9">
                                        <input id="phone" class="form-control" type="number" name="phone"
                                            value="<?= $data['phone'] ?>"
                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('phone_number', 'Phone Number') ?> <?= labels('here', ' Here ') ?>"
                                            required <?= $phoneReadonly ?>>
                                    </div>
                                </div>
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
                                    placeholder="<?= labels('enter_the_slug', 'Enter the slug') ?> " required>
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
                                    <option value="0" <?= ($partner_details['type'] == 0) ? 'selected' : '' ?>>
                                        <?= labels('individual', 'Individual') ?></option>
                                    <option value="1" <?= ($partner_details['type'] == 1) ? 'selected' : '' ?>>
                                        <?= labels('organization', 'Organization') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="visiting_charges"
                                    class="required"><?= labels('visiting_charges', 'Visiting Charges') ?><strong>(
                                        <?= $currency ?> )</strong></label>
                                <i data-content="<?= labels('data_content_for_visiting_charge', 'The customer will pay these fixed charges for every booking made at their doorstep.') ?>"
                                    class="fa fa-question-circle" data-original-title="" title=""
                                    data-toggle="popover"></i>
                                <input id="visiting_charges" class="form-control" type="number"
                                    value="<?= $partner_details['visiting_charges'] ?>" name="visiting_charges" min="0"
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
                                    name="number_of_members" value="<?= $partner_details['number_of_members'] ?>"
                                    min="1" oninput="this.value = Math.abs(this.value)"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('number_Of_members', 'Number of Members') ?> <?= labels('here', ' Here ') ?>"
                                    required>
                            </div>
                        </div>
                        <?php if (($max_serviceable_distance_type ?? 'global') === 'provider_wise'): ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label
                                    for="max_serviceable_distance"><?= labels('max_serviceable_distance', 'Max Serviceable Distance') ?><strong>
                                        (<?= labels('km', 'km') ?>)</strong></label>
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
                            <div
                                class="card stepper-toggle-card <?= $partner_details['at_store'] == '1' ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('at_store', 'At Store') ?></strong></div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="at_store"
                                                    name="at_store" <?= $partner_details['at_store'] == '1' ? 'checked' : '' ?>>
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
                                class="card stepper-toggle-card <?= $partner_details['at_doorstep'] == '1' ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('at_doorstep', 'At Doorstep') ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="at_doorstep"
                                                    name="at_doorstep" <?= $partner_details['at_doorstep'] == '1' ? 'checked' : '' ?>>
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
                        <?php if ($allow_post_booking_chat == "1") { ?>
                            <div class="col-md-4">
                                <div
                                    class="card stepper-toggle-card <?= (array_key_exists('chat', $partner_details) && $partner_details['chat'] == '1') ? 'active' : '' ?>">
                                    <div class="card-body p-3">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col">
                                                <strong><?= labels('allow_post_booking_chat', 'Allow Post Booking Chat') ?></strong>
                                            </div>
                                            <div class="col-auto">
                                                <div class="custom-control custom-switch mb-0">
                                                    <input type="checkbox" class="custom-control-input" id="post_chat"
                                                        name="chat" <?php if (array_key_exists('chat', $partner_details)) {
                                                            echo ($partner_details['chat'] == "1") ? 'checked' : '';
                                                        } ?>>
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
                        <?php } ?>
                        <?php if ($allow_pre_booking_chat == "1") { ?>
                            <div class="col-md-4">
                                <div
                                    class="card stepper-toggle-card <?= (array_key_exists('pre_chat', $partner_details) && $partner_details['pre_chat'] == '1') ? 'active' : '' ?>">
                                    <div class="card-body p-3">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col">
                                                <strong><?= labels('allow_pre_booking_chat', 'Allow Pre Booking Chat') ?></strong>
                                            </div>
                                            <div class="col-auto">
                                                <div class="custom-control custom-switch mb-0">
                                                    <input type="checkbox" class="custom-control-input" id="pre_chat"
                                                        name="pre_chat" <?php if (array_key_exists('pre_chat', $partner_details)) {
                                                            echo ($partner_details['pre_chat'] == "1") ? 'checked' : '';
                                                        } ?>>
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
                        <?php } ?>
                    </div>
                </div>

                <!-- STEP 3: Location -->
                <div class="step-panel" data-step="3">
                    <?php
                    $locationRows = $provider_locations ?? [];
                    if (empty($locationRows)) {
                        $locationRows = [[
                            'address' => $partner_details['address'] ?? '',
                            'city' => $data['city'] ?? '',
                            'latitude' => $data['latitude'] ?? '',
                            'longitude' => $data['longitude'] ?? '',
                            'is_default' => 1,
                        ]];
                    }
                    ?>
                    <div class="col-12 mt-3" id="provider_locations_section" style="<?= (($partner_details['type'] ?? '0') == '1') ? '' : 'display:none;' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><?= labels('provider_locations', 'Provider Locations') ?></h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-provider-location"><i class="fas fa-plus mr-1"></i><?= labels('add_location', 'Add Location') ?></button>
                        </div>
                        <div id="provider-locations-list">
                            <?php foreach ($locationRows as $index => $location) { ?>
                                <div class="provider-location-row border rounded p-3 mb-2" data-location-index="<?= $index ?>">
                                    <div class="row">
                                        <div class="col-md-12 form-group position-relative">
                                            <label><?= labels('search_location', 'Search Location') ?></label>
                                            <input type="text" class="form-control provider-location-search" name="locations[<?= $index ?>][place]" value="<?= esc($location['address'] ?? '') ?>" autocomplete="off">
                                            <ul class="list-group provider-location-suggestions position-absolute w-100" style="z-index:1000;display:none;"></ul>
                                        </div>
                                        <div class="col-md-6 form-group"><label><?= labels('address', 'Address') ?></label><textarea class="form-control" name="locations[<?= $index ?>][address]" required><?= esc($location['address'] ?? '') ?></textarea></div>
                                        <div class="col-md-6 form-group"><label><?= labels('city', 'City') ?></label><input class="form-control" name="locations[<?= $index ?>][city]" value="<?= esc($location['city'] ?? '') ?>"></div>
                                        <div class="col-md-4 form-group"><label><?= labels('latitude', 'Latitude') ?></label><input class="form-control" name="locations[<?= $index ?>][latitude]" value="<?= esc($location['latitude'] ?? '') ?>" required></div>
                                        <div class="col-md-4 form-group"><label><?= labels('longitude', 'Longitude') ?></label><input class="form-control" name="locations[<?= $index ?>][longitude]" value="<?= esc($location['longitude'] ?? '') ?>" required></div>
                                        <div class="col-md-2 form-group d-flex align-items-center"><label class="mb-0"><input type="checkbox" name="locations[<?= $index ?>][is_default]" value="1" <?= !empty($location['is_default']) ? 'checked' : '' ?>> <?= labels('default', 'Default') ?></label></div>
                                        <div class="col-md-2 form-group d-flex align-items-center"><label class="mb-0"><input type="checkbox" name="locations[<?= $index ?>][is_active]" value="1" <?= !isset($location['is_active']) || !empty($location['is_active']) ? 'checked' : '' ?>> <?= labels('active', 'Active') ?></label><button type="button" class="btn btn-sm btn-outline-danger ml-2 remove-provider-location" title="<?= labels('remove', 'Remove') ?>"><i class="fas fa-times"></i></button></div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label
                                    for="partner_location"><?= labels('current_location', 'Current Location') ?></label>
                                <input id="partner_location" class="form-control" type="text" name="places">
                                <ul id="suggestions" class="list-group position-absolute w-100" style="z-index: 1000;">
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div id="map_wrapper_div_partner">
                                <div id="partner_map"></div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-4">
                            <div class="form-group">
                                <div class="cities" id="cities_select">
                                    <label for="city" class="required"><?= labels('city', 'City') ?></label>
                                    <input type="text" name="city" class="form-control"
                                        placeholder="<?= labels('enter_your_providers_city_name', "Enter your provider's city name") ?>"
                                        value="<?= isset($data['city']) ? $data['city'] : "" ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-4">
                            <div class="form-group">
                                <label for="partner_latitude"
                                    class="required"><?= labels('latitude', 'Latitude') ?></label>
                                <input id="partner_latitude" class="form-control" type="text" name="latitude"
                                    placeholder="<?= labels('latitude', 'Latitude') ?>" value="<?= $data['latitude'] ?>"
                                    pattern="^-?(90(\.0{1,7})?|[0-8][0-9](\.[0-9]{1,7})?|[0-9](\.[0-9]{1,7})?)$"
                                    title="<?= labels('please_enter_valid_latitude', 'Latitude: -90 to 90, max 7 decimal places') ?>"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-4 mt-4">
                            <div class="form-group">
                                <label for="partner_longitude"
                                    class="required"><?= labels('longitude', 'Longitude') ?></label>
                                <input id="partner_longitude" class="form-control" type="text" name="longitude"
                                    placeholder="<?= labels('longitude', 'Longitude') ?>"
                                    value="<?= $data['longitude'] ?>"
                                    pattern="^-?(180(\.0{1,7})?|1[0-7][0-9](\.[0-9]{1,7})?|[0-9]{1,2}(\.[0-9]{1,7})?)$"
                                    title="<?= labels('please_enter_valid_longitude', 'Longitude: -180 to 180, max 7 decimal places') ?>"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="address" class="required"><?= labels('address', 'Address') ?></label>
                                <textarea id="address" class="form-control" style="min-height:60px" name="address"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('address', 'Address') ?> <?= labels('here', ' Here ') ?>"
                                    required><?= isset($partner_details['address']) ? $partner_details['address'] : "" ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    $(function () {
                        let providerLocationIndex = $('#provider-locations-list .provider-location-row').length;
                        $('#add-provider-location').on('click', function () {
                            const index = providerLocationIndex++;
                            $('#provider-locations-list').append(`
                                <div class="provider-location-row border rounded p-3 mb-2" data-location-index="${index}">
                                    <div class="row">
                                        <div class="col-md-12 form-group position-relative"><label><?= labels('search_location', 'Search Location') ?></label><input type="text" class="form-control provider-location-search" name="locations[${index}][place]" autocomplete="off"><ul class="list-group provider-location-suggestions position-absolute w-100" style="z-index:1000;display:none;"></ul></div>
                                        <div class="col-md-6 form-group"><label><?= labels('address', 'Address') ?></label><textarea class="form-control" name="locations[${index}][address]" required></textarea></div>
                                        <div class="col-md-6 form-group"><label><?= labels('city', 'City') ?></label><input class="form-control" name="locations[${index}][city]"></div>
                                        <div class="col-md-4 form-group"><label><?= labels('latitude', 'Latitude') ?></label><input class="form-control" name="locations[${index}][latitude]" required></div>
                                        <div class="col-md-4 form-group"><label><?= labels('longitude', 'Longitude') ?></label><input class="form-control" name="locations[${index}][longitude]" required></div>
                                        <div class="col-md-2 form-group d-flex align-items-center"><label class="mb-0"><input type="checkbox" name="locations[${index}][is_default]" value="1"> <?= labels('default', 'Default') ?></label></div>
                                        <div class="col-md-2 form-group d-flex align-items-center"><label class="mb-0"><input type="checkbox" name="locations[${index}][is_active]" value="1" checked> <?= labels('active', 'Active') ?></label><button type="button" class="btn btn-sm btn-outline-danger ml-2 remove-provider-location" title="<?= labels('remove', 'Remove') ?>"><i class="fas fa-times"></i></button></div>
                                    </div>
                                </div>`);
                        });
                        $(document).on('click', '.remove-provider-location', function () {
                            if ($('#provider-locations-list .provider-location-row').length > 1) {
                                $(this).closest('.provider-location-row').remove();
                            }
                        });

                        const placesUrl = '<?= base_url('api/v1/get_places_for_app') ?>';
                        const detailsUrl = '<?= base_url('api/v1/get_place_details_for_app') ?>';
                        $(document).on('input', '.provider-location-search', function () {
                            const input = this;
                            const row = $(input).closest('.provider-location-row');
                            const suggestions = row.find('.provider-location-suggestions');
                            clearTimeout($(input).data('locationTimer'));
                            const query = input.value.trim();
                            if (!query) {
                                suggestions.empty().hide();
                                return;
                            }
                            const timer = setTimeout(function () {
                                $.getJSON(placesUrl + '?input=' + encodeURIComponent(query), function (response) {
                                    suggestions.empty();
                                    const predictions = response?.data?.predictions || [];
                                    predictions.forEach(function (place) {
                                        const item = $('<li class="list-group-item list-group-item-action" style="cursor:pointer"></li>')
                                            .text(place.description || place.name || '');
                                        item.on('click', function () {
                                            input.value = place.description || place.name || '';
                                            suggestions.hide();
                                            if (!place.place_id) return;
                                            $.getJSON(detailsUrl + '?placeid=' + encodeURIComponent(place.place_id), function (details) {
                                                const result = details?.data?.result;
                                                const location = result?.geometry?.location;
                                                if (!location) return;
                                                row.find('textarea[name$="[address]"]').val(result.formatted_address || input.value);
                                                row.find('input[name$="[latitude]"]').val(location.lat);
                                                row.find('input[name$="[longitude]"]').val(location.lng);
                                                row.find('input[name$="[city]"]').val(result.name || '');
                                            });
                                        });
                                        suggestions.append(item);
                                    });
                                    suggestions.toggle(predictions.length > 0);
                                });
                            }, 300);
                            $(input).data('locationTimer', timer);
                        });
                        $(document).on('click', function (event) {
                            if (!$(event.target).closest('.provider-location-row').length) {
                                $('.provider-location-suggestions').hide();
                            }
                        });
                    });
                </script>

                <!-- STEP 4: Media & Docs -->
                <div class="step-panel" data-step="4">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="image" class="required"><?= labels('image', 'Image') ?> </label>
                                <small>(<?= labels('partner_image_recommended_size', 'We recommend 80x80 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="image" id="image" accept="image/*">
                                <?php
                                $profileImageSrc = (!empty($data['image']) && $data['image'] !== base_url() && $data['image'] !== base_url('/'))
                                    ? $data['image']
                                    : base_url('public/backend/assets/default.png');
                                ?>
                                <div class="mt-2">
                                    <img src="<?= esc($profileImageSrc) ?>" alt="<?= labels('image', 'Image') ?>"
                                        style="max-width: 120px; max-height: 80px; border-radius: 8px; border: 1px solid #d6d6dd;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="banner_image"
                                    class="required"><?= labels('banner_image', 'Banner Image') ?></label>
                                <small>(<?= labels('partner_banner_image_recommended_size', 'We recommend 378x190 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="banner" id="banner_image" accept="image/*">
                                <?php
                                $bannerSrc = !empty($partner_details['banner']) ? $partner_details['banner'] : base_url('public/backend/assets/default.png');
                                ?>
                                <div class="mt-2">
                                    <img src="<?= esc($bannerSrc) ?>"
                                        alt="<?= labels('banner_image', 'Banner Image') ?>"
                                        style="max-width: 120px; max-height: 80px; border-radius: 8px; border: 1px solid #d6d6dd;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label
                                    for="other_service_image_selector"><?= labels('other_images', 'Other Image') ?></label>
                                <small>(<?= labels('other_image_recommended_size', 'We recommend 960 x 540 pixels') ?>)</small>
                                <input type="file" name="other_service_image_selector_edit[]" class="filepond logo"
                                    id="other_service_image_selector_edit" accept="image/*" multiple>
                                <div class="row mt-2" id="other_images_container">
                                    <?php
                                    if (!empty($partner_details['other_images'])) {
                                        if (count($partner_details['other_images']) > 0) { ?>
                                            <div class="col-12 mb-2">
                                                <button type="button"
                                                    class="btn btn-primary btn-sm remove-all-other-images"><?= labels('remove_all_images', 'Remove All Images') ?></button>
                                            </div>
                                        <?php }
                                        foreach (($partner_details['other_images']) as $index => $image) { ?>
                                            <div class="col-md-4 mb-2 other-image-container">
                                                <div class="position-relative">
                                                    <img alt="no image found" width="130px"
                                                        style="border: solid #d6d6dd 1px; border-radius: 12px;" height="100px"
                                                        class="mt-2" src="<?= $image ?>">
                                                    <input type="hidden" name="existing_other_images[]" value="<?= $image ?>">
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
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php $fileFieldConfigs = []; ?>
                    <div class="row">
                        <?php
                        foreach ($documents_custom_fields ?? [] as $field):
                            $cfId = (int) ($field['id'] ?? 0);
                            $inputName = 'cf_' . $cfId;
                            $fieldType = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                            $required = !empty($field['required']);
                            $fieldFileConfig = is_array($field['file_config'] ?? null) ? $field['file_config'] : [];
                            $existingValue = (string) ($custom_field_values[$cfId] ?? '');
                            $initialLabel = $custom_field_labels_by_language[$cfId][$current_language] ?? ($field['field_label'] ?? '');
                            $isDefaultPlaceholder = !empty($existingValue) && (
                                str_contains($existingValue, 'default.png') ||
                                str_contains($existingValue, 'default.jpg') ||
                                str_contains($existingValue, 'default.jpeg')
                            );
                            $hasExistingValue = $existingValue !== '' && !$isDefaultPlaceholder;
                            $requiredAttr = ($required && !($fieldType === 'file' && $hasExistingValue)) ? 'required' : '';
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
                                    <?= renderCustomFieldInput($inputName, $fieldType, $requiredAttr, '', $fieldFileConfig, $fieldType !== 'file' ? $existingValue : '') ?>
                                    <?php if ($fieldType === 'file' && $hasExistingValue): ?>
                                        <?= renderCustomFieldFilePreview($existingValue, $initialLabel) ?>
                                    <?php endif ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>

                <!-- STEP 5: Bank Details -->
                <div class="step-panel" data-step="5">
                    <div class="bank-details-section">
                        <?php
                        $bankFields = array_values($bank_details_custom_fields ?? []);
                        $bankChunks = array_chunk($bankFields, 3);
                        foreach ($bankChunks as $chunk): ?>
                            <div class="row">
                                <?php foreach ($chunk as $field):
                                    $cfId = (int) ($field['id'] ?? 0);
                                    $inputName = 'cf_' . $cfId;
                                    $fieldType = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                                    $required = !empty($field['required']);
                                    $requiredAttr = $required ? 'required' : '';
                                    $fieldFileConfig = is_array($field['file_config'] ?? null) ? $field['file_config'] : [];
                                    $existingValue = (string) ($custom_field_values[$cfId] ?? '');
                                    $initialLabel = $custom_field_labels_by_language[$cfId][$current_language] ?? ($field['field_label'] ?? '');
                                    $placeholder = labels('enter', 'Enter') . ' ' . $initialLabel . ' ' . labels('here', 'Here');
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
                </div>

                <!-- STEP 6: Review -->
                <div class="step-panel" data-step="6">
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
                        <i class="fas fa-check"></i> <?= labels('update', 'Update') ?>
                    </button>
                </div>
            </div>
        </div>
        <?= form_close() ?>
    </section>
</div>

<script>
    $(function () {
        var $type = $('#type');
        var $members = $('#number_of_members');
        var membersEl = $members.get(0);

        var INDIVIDUAL_MEMBERS_MSG = "<?= labels('individual_provider_members_must_be_one', 'An individual provider can have only 1 member') ?>";
        var MIN_MEMBERS_MSG = "<?= labels('number_of_members_minimum_one', 'Number of members must be at least 1') ?>";

        // Inline error rendered with the same markup the stepper uses, so its
        // clearStepErrors() pass also removes it and styling stays consistent.
        function memberGroup() {
            return $members.closest('.form-group');
        }
        function clearMembersError() {
            var g = memberGroup();
            g.removeClass('has-error');
            g.find('.stepper-field-error').remove();
        }
        function showMembersError(msg) {
            var g = memberGroup();
            g.addClass('has-error');
            if (!g.find('.stepper-field-error').length) {
                $('<div class="stepper-field-error"></div>').text(msg).appendTo(g);
            }
        }

        function isIndividual() {
            return String($type.val()) === '0';
        }

        // Keep native constraints in sync so the stepper's checkValidity()
        // pass and HTML5 validation also block invalid submits.
        function applyConstraints() {
            membersEl.min = '1';
            if (isIndividual()) {
                membersEl.max = '1';
                if (String($members.val()) !== '1') {
                    $members.val('1');
                }
                $members.attr('readonly', 'readonly');
            } else {
                membersEl.removeAttribute('max');
                $members.removeAttr('readonly');
            }
        }

        function validateMembers() {
            clearMembersError();
            var raw = String($members.val()).trim();

            if (isIndividual()) {
                if (raw !== '1') {
                    showMembersError(INDIVIDUAL_MEMBERS_MSG);
                    return false;
                }
                return true;
            }

            if (raw === '') {
                return true; // empty handled by the stepper's `required` pass
            }
            var n = Number(raw);
            if (!Number.isInteger(n) || n < 1) {
                showMembersError(MIN_MEMBERS_MSG);
                return false;
            }
            return true;
        }

        $type.on('change', function () {
            $('#provider_locations_section').toggle(!isIndividual());
            applyConstraints();
            validateMembers();
        });

        $members.on('input change', function () {
            // Math.abs handled inline on the element; just re-validate.
            validateMembers();
        });

        // Block leaving Business Settings (step 2) while members is invalid.
        $('.btn-step-next, .btn-step-submit').on('click', function () {
            if ($members.closest('.step-panel').is(':visible') && !validateMembers()) {
                memberGroup().get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        $('#provider_locations_section').toggle(!isIndividual());
        applyConstraints();
    });
</script>

<script>
    $(document).ready(function () {
        // Handle individual image removal with toggle functionality
        $(document).on('click', '.remove-other-image', function () {
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
        $(document).on('click', '.remove-all-other-images', function () {
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

        // Ensure FilePond plugins are registered before creating custom field instances,
        // since this inline script runs before partner.js which normally registers them.
        if (typeof FilePondPluginFileValidateType !== 'undefined') {
            FilePond.registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateSize, FilePondPluginFileValidateType);
        }

        // FilePond initialization for custom field file inputs
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

        // Language switching for basic info
        $(document).ready(function () {
            let default_language = '<?= $current_language ?>';
            const customFieldFallbackLanguage = default_language;
            const customFieldLabelsByLanguage = <?= json_encode($custom_field_labels_by_language ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

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

                updateCustomFieldLabels(language);

                default_language = language;
            });
        });
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