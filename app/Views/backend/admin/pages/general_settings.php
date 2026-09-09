<!-- Main Content new-->
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
    <section class="section" id="pill-general_settings" role="tabpanel">
        <div class="section-header mt-2">
            <h1><?= labels('general_settings', 'General Settings') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a
                        href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a>
                </div>
                <div class="breadcrumb-item "><a
                        href="<?= base_url('admin/settings/general-settings') ?>"><?= labels('general_settings', "General Settings") ?></a>
                </div>
            </div>
        </div>
        <ul class="justify-content-start nav nav-fill nav-pills pl-3 py-2 setting" id="gen-list">
            <div class="row">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page"
                        href="<?= base_url('admin/settings/general-settings') ?>" id="pills-general_settings-tab"
                        aria-selected="true">
                        <?= labels('general_settings', "General Settings") ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/about-us') ?>" id="pills-about_us"
                        aria-selected="false">
                        <?= labels('about_us', "About Us") ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/contact-us') ?>" id="pills-about_us"
                        aria-selected="false">
                        <?= labels('support_details', "Support Details") ?></a>
                </li>
            </div>
        </ul>
        <?= form_open_multipart(base_url('admin/settings/general-settings')) ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="row no-gutters">
                            <div class="col-md-3 border-right pt-3 pb-3">
                                <div class="nav flex-column nav-pills" id="general-settings-pills-tab" role="tablist"
                                    aria-orientation="vertical">
                                    <a class="nav-link active" id="gs-company-tab" data-toggle="pill" href="#gs-company"
                                        role="tab" aria-controls="gs-company" aria-selected="true">
                                        <i class="fa fa-building mr-2"></i>
                                        <?= labels('company_profile', 'Company Profile') ?>
                                    </a>
                                    <a class="nav-link" id="gs-business-tab" data-toggle="pill" href="#gs-business"
                                        role="tab" aria-controls="gs-business" aria-selected="false">
                                        <i class="fa fa-globe mr-2"></i>
                                        <?= labels('business_regional', 'Business & Regional') ?>
                                    </a>
                                    <a class="nav-link" id="gs-branding-tab" data-toggle="pill" href="#gs-branding"
                                        role="tab" aria-controls="gs-branding" aria-selected="false">
                                        <i class="fa fa-paint-brush mr-2"></i>
                                        <?= labels('branding_media', 'Branding & Media') ?>
                                    </a>
                                    <a class="nav-link" id="gs-advanced-tab" data-toggle="pill" href="#gs-advanced"
                                        role="tab" aria-controls="gs-advanced" aria-selected="false">
                                        <i class="fa fa-cogs mr-2"></i>
                                        <?= labels('advanced_settings', 'Advanced Settings') ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="tab-content p-3" id="general-settings-pills-tabContent">

                                    <!-- Company Profile -->
                                    <div class="tab-pane fade show active" id="gs-company" role="tabpanel"
                                        aria-labelledby="gs-company-tab">
                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <div class="d-flex flex-wrap align-items-center gap-4">
                                                    <?php
                                                    // Sort languages so default language appears first for better UI
                                                    $sorted_company_languages = sort_languages_with_default_first($languages);
                                                    foreach ($sorted_company_languages as $index => $language) {
                                                        if ($language['is_default'] == 1) {
                                                            $current_company_language = $language['code'];
                                                        }
                                                        ?>
                                                        <div class="language-company-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                            id="language-company-<?= $language['code'] ?>"
                                                            data-language="<?= $language['code'] ?>"
                                                            style="cursor: pointer; padding: 0.5rem 0;">
                                                            <span
                                                                class="language-company-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                                <?= $language['language'] ?>
                                                                <?= $language['is_default'] ? '(Default)' : '' ?>
                                                            </span>
                                                            <div class="language-company-underline"
                                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                                            </div>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for='company_title'
                                                        id="company_title_label"><?= labels('company_title', "Company Title") ?></label>
                                                    <?php
                                                    foreach ($sorted_company_languages as $index => $language) {
                                                        ?>
                                                        <input type='text' class="form-control custome_reset"
                                                            name='company_title[<?= $language['code'] ?>]'
                                                            id='company_title_<?= $language['code'] ?>' value="<?php
                                                              if (isset($company_title[$language['code']])) {
                                                                  echo $company_title[$language['code']];
                                                              } else if (is_string($company_title) && $language['is_default'] == 1) {
                                                                  echo $company_title;
                                                              } else {
                                                                  echo "";
                                                              }
                                                              ?>"
                                                            style="display: <?= $language['code'] == $current_company_language ? 'block' : 'none' ?>" />
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label
                                                        for='support_email'><?= labels('support_email', "support Email") ?></label>
                                                    <input type='email' class="form-control custome_reset"
                                                        name='support_email' id='support_email'
                                                        value="<?= isset($support_email) ? $support_email : '' ?>" />
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="phone"><?= labels('mobile', "Phone") ?></label>
                                                    <input type="number" min="0" class="form-control custome_reset"
                                                        name="phone" id="phone"
                                                        value="<?= isset($phone) ? $phone : '' ?>" />
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label
                                                        for="support_hours"><?= labels('support_hours', "Support Hours") ?></label>
                                                    <input type="text" class="form-control custome_reset"
                                                        name="support_hours" id="support_hours"
                                                        value="<?= isset($support_hours) ? $support_hours : '09:00 to 18:00' ?>" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="copyright_details"
                                                        id="copyright_details_label"><?= labels('copyright_details', "Copyright Details") ?></label>
                                                    <?php
                                                    foreach ($sorted_company_languages as $index => $language) {
                                                        ?>
                                                        <input type="text" class="form-control"
                                                            name="copyright_details[<?= $language['code'] ?>]"
                                                            id="copyright_details_<?= $language['code'] ?>" value="<?php
                                                              if (isset($copyright_details[$language['code']])) {
                                                                  echo $copyright_details[$language['code']];
                                                              } else if (is_string($copyright_details) && $language['is_default'] == 1) {
                                                                  echo $copyright_details;
                                                              } else {
                                                                  echo "";
                                                              }
                                                              ?>"
                                                            style="display: <?= $language['code'] == $current_company_language ? 'block' : 'none' ?>" />
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label
                                                        for="company_map_location"><?= labels('company_map_location', "Company Map Location") ?></label>
                                                    <input type="text" class="form-control" name="company_map_location"
                                                        id="company_map_location"
                                                        value="<?= htmlentities(isset($company_map_location) ? $company_map_location : '') ?>" />
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="address"
                                                        id="address_label"><?= labels('address', "Address") ?></label>
                                                    <?php
                                                    foreach ($sorted_company_languages as $index => $language) {
                                                        ?>
                                                        <textarea rows=1 class='form-control custome_reset'
                                                            name="address[<?= $language['code'] ?>]"
                                                            id="address_<?= $language['code'] ?>"
                                                            style="display: <?= $language['code'] == $current_company_language ? 'block' : 'none' ?>"><?php
                                                                    if (isset($address[$language['code']])) {
                                                                        echo $address[$language['code']];
                                                                    } else if (is_string($address) && $language['is_default'] == 1) {
                                                                        echo $address;
                                                                    } else {
                                                                        echo "";
                                                                    }
                                                                    ?></textarea>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="short_description"
                                                        id="short_description_label"><?= labels('short_description', "Short Description") ?></label>
                                                    <?php
                                                    foreach ($sorted_company_languages as $index => $language) {
                                                        ?>
                                                        <textarea rows=1 class='form-control custome_reset'
                                                            name="short_description[<?= $language['code'] ?>]"
                                                            id="short_description_<?= $language['code'] ?>"
                                                            style="display: <?= $language['code'] == $current_company_language ? 'block' : 'none' ?>"><?php
                                                                    if (isset($short_description[$language['code']])) {
                                                                        echo $short_description[$language['code']];
                                                                    } else if (is_string($short_description) && $language['is_default'] == 1) {
                                                                        echo $short_description;
                                                                    } else {
                                                                        echo "";
                                                                    }
                                                                    ?></textarea>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Business & Regional -->
                                    <div class="tab-pane fade" id="gs-business" role="tabpanel"
                                        aria-labelledby="gs-business-tab">
                                        <h6 class="font-weight-bold mb-3">
                                            <?= labels('country_currency', "Country Currency") ?></h6>
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label><?= labels('country_currency', "Country Currency Code") ?></label>
                                                    <select class="form-control select2" name="country_currency_code">
                                                        <?php foreach ($currencies as $code => $label): ?>
                                                            <option value="<?= $code ?>" <?= isset($country_currency_code) && $country_currency_code == $code ? 'selected' : '' ?>>
                                                                <?= esc($label) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label
                                                        for='currency'><?= labels('currency_symbol', "Currency Symbol") ?></label>
                                                    <input type='text' class='form-control' name='currency'
                                                        id='currency'
                                                        value="<?= isset($currency) ? $currency : '' ?>" />
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label><?= labels('decimal_point', "Decimal Point") ?></label>
                                                <select class="form-control" name="decimal_point">
                                                    <option value="0" <?php echo isset($decimal_point) && $decimal_point == '0' ? 'selected' : '' ?>>0</option>
                                                    <option value="1" <?php echo isset($decimal_point) && $decimal_point == '1' ? 'selected' : '' ?>>1</option>
                                                    <option value="2" <?php echo isset($decimal_point) && $decimal_point == '2' ? 'selected' : '' ?>>2</option>
                                                </select>
                                            </div>
                                        </div>
                                        <h6 class="font-weight-bold mb-3">
                                            <?= labels('business_settings', 'Business settings') ?></h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <input type="hidden" id="set"
                                                        value="<?= isset($system_timezone) ? $system_timezone : 'Asia/Kolkata' ?>">
                                                    <input type="hidden" name="system_timezone_gmt"
                                                        value="<?= isset($system_timezone_gmt) ? $system_timezone_gmt : '' ?>"
                                                        id="system_timezone_gmt"
                                                        value="<?= isset($system_timezone_gmt) ? $system_timezone_gmt : '+05:30' ?>" />
                                                    <label
                                                        for='timezone'><?= labels('select_time_zone', "Select Time Zone") ?></label>
                                                    <select class='form-control selectric ' name='system_timezone'
                                                        id='timezone' value="">
                                                        <option value="">--
                                                            <?= labels('select_time_zone', "Select Time Zone") ?> --
                                                        </option>
                                                        <?php foreach ($timezones as $row): ?>
                                                            <option value="<?= esc($row['timezone_id']) ?>"
                                                                data-gmt="<?= esc($row['offset_text']) ?>">
                                                                <?= esc($row['offset_text']) ?>
                                                                -
                                                                <?= esc($row['time']) ?>
                                                                -
                                                                <?= esc($row['timezone_id']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <div class="control-label">
                                                        <?= labels('booking_auto_cancel', "Booking auto cancel Duration") ?>
                                                        <span class="breadcrumb-item p-3 pt-2 text-primary">
                                                            <i data-content="<?= labels('data_content_booking_auto_cancel_duration', 'If the booking is not accepted by the provider before the added cancelable duration from the actual booking time, the booking will be automatically canceled. If the booking is pre-paid, the amount will be credited to the customer’s bank account.For example, if a customer books a service at 4:00 PM, and the cancelable duration is 30 minutes, if the provider does not accept the booking by 3:30 PM, the booking will be canceled') ?>."
                                                                class="fa fa-question-circle" data-original-title=""
                                                                title=""></i></span>
                                                    </div>
                                                    <input type="number" class="form-control"
                                                        name="booking_auto_cancle_duration"
                                                        id="booking_auto_cancle_duration"
                                                        value="<?= isset($booking_auto_cancle_duration) ? $booking_auto_cancle_duration : '30' ?>" />
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label
                                                        for="max_serviceable_distance_type"><?= labels('max_serviceable_distance_type', 'Max Serviceable Distance Type') ?></label>
                                                    <i data-content="<?= labels('data_content_for_max_serviceable_distance_type', 'if provider wise is set then individual providers must set this distance in their profile inorder for customers to see the providers in their serviceable area.') ?>"
                                                        class="fa fa-question-circle" data-original-title=""
                                                        title=""></i>
                                                    <select class="select2" name="max_serviceable_distance_type"
                                                        id="max_serviceable_distance_type">
                                                        <option value="global" <?= !isset($max_serviceable_distance_type) || $max_serviceable_distance_type == 'global' ? 'selected' : '' ?>>
                                                            <?= labels('global', 'Global') ?>
                                                        </option>
                                                        <option value="provider_wise"
                                                            <?= isset($max_serviceable_distance_type) && $max_serviceable_distance_type == 'provider_wise' ? 'selected' : '' ?>>
                                                            <?= labels('provider_wise', 'Provider-wise') ?>
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6" id="max_serviceable_distance_input_group">
                                                <div class="form-group">
                                                    <label
                                                        for="max_serviceable_distance"><?= labels('max_Serviceable_distance_in_kms', "Max Serviceable Distance") ?></label>
                                                    <i data-content=" <?= labels('data_content_for_max_serviceable_distance', 'The system will use the distance values (KM) you provide to find providers in Xkms within the location chosen by the customer. For instance, if you set it to 100 KM, customers will see providers within 100 KM of their chosen location. If there are no providers within 100 KM, it\'ll say, We are not available here.') ?>"
                                                        class="fa fa-question-circle" data-original-title=""
                                                        title=""></i>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control custome_reset"
                                                            name="max_serviceable_distance"
                                                            id="max_serviceable_distance"
                                                            value="<?= isset($max_serviceable_distance) ? $max_serviceable_distance : '' ?>" />
                                                        <div class="input-group-append">
                                                            <select class="form-control" name="distance_unit"
                                                                id="distance_unit">
                                                                <option value="km" <?= isset($distance_unit) && $distance_unit == 'km' ? 'selected' : '' ?>>
                                                                    <?= labels('kms', 'Kms') ?></option>
                                                                <option value="miles" <?= isset($distance_unit) && $distance_unit == 'miles' ? 'selected' : '' ?>>
                                                                    <?= labels('miles', 'Miles') ?>
                                                                </option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <label for="max_serviceable_distance"
                                                        class="text-danger"><?= labels('note_this_distance_is_used_while_search_nearby_partner_for_customer', " This distance is used while search nearby partner for customer") ?></label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Branding & Media -->
                                    <div class="tab-pane fade" id="gs-branding" role="tabpanel"
                                        aria-labelledby="gs-branding-tab">
                                        <div class="row">
                                            <div class="col-md-12 ">
                                                <div class="form-group">
                                                    <label
                                                        for='logo'><?= labels('login_image', "Login Image") ?></label>
                                                    <i data-content="<?= labels('data_content_for_login_image', "This picture will appear as the background on the login pages for the admin and provider panels.") ?>"
                                                        class="fa fa-question-circle" data-original-title=""
                                                        title=""></i></span>
                                                    <small>(<?= labels('login_image_recommended_size', 'We recommend 1920 x 1080 pixels') ?>)</small>
                                                </div>
                                                <input type="file" name="login_image" class="filepond logo"
                                                    id="login_image" accept="image/*">
                                                <?php if (isset($login_image) && $login_image != ""): ?>
                                                    <img class="settings_logo" style="border-radius: 8px"
                                                        src="<?= $login_image ?>">
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label
                                                        for="primary_color"><?= labels('primary_color', "Primary Color") ?></label>
                                                    <input type="text" onkeyup="change_color('change_color',this)"
                                                        oninput="change_color('change_color',this)"
                                                        class=" form-control" name="primary_color" id="primary_color"
                                                        value="<?= isset($primary_color) ? $primary_color : '' ?>" />
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label
                                                        for="secondary_color"><?= labels('secondary_color', "Secondary Color") ?></label>
                                                    <input type="text" class=" form-control" name="secondary_color"
                                                        id="secondary_color"
                                                        value="<?= isset($secondary_color) ? $secondary_color : '' ?>" />
                                                </div>
                                            </div>
                                        </div>
                                        <h6 class="font-weight-bold mb-3 mt-2">
                                            <?= labels('admin_logos', "Admin Logos") ?></h6>
                                        <div class="row">
                                            <div class="col-md-4 ">
                                                <div class="form-group">
                                                    <label for='logo'><?= labels('logo', "Logo") ?></label>
                                                    <small>(<?= labels('logo_recommended_size', 'We recommend 182 x 60 pixels') ?>)</small>
                                                    <input type="file" name="logo" class="filepond logo" id="file"
                                                        accept="image/*">
                                                    <?php if (isset($logo) && $logo != ""): ?>
                                                        <img class="settings_logo" src="<?= $logo ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 ">
                                                <div class="form-group">
                                                    <label for='favicon'><?= labels('favicon', "Favicon") ?></label>
                                                    <small>(<?= labels('half_logo_recommended_size', 'We recommend 40 x 40 pixels') ?>)</small>
                                                    <input type="file" name="favicon" class="filepond logo" id="favicon"
                                                        accept="image/*">
                                                    <?php if (isset($favicon) && $favicon != ""): ?>
                                                        <img class="settings_logo" src="<?= $favicon ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 ">
                                                <div class="form-group">
                                                    <label
                                                        for='half_logo'><?= labels('half_logo', "Half Logo") ?></label>
                                                    <small>(<?= labels('half_logo_recommended_size', 'We recommend 40 x 40 pixels') ?>)</small>
                                                    <input type="file" name="half_logo" class="filepond logo"
                                                        id="half_logo" accept="image/*">
                                                    <?php if (isset($half_logo) && $half_logo != ""): ?>
                                                        <img class="settings_logo" src="<?= $half_logo ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <h6 class="font-weight-bold mb-3 mt-2">
                                            <?= labels('provider_logos', "Provider Logos") ?></h6>
                                        <div class="row">
                                            <div class="col-md-4 ">
                                                <div class="form-group">
                                                    <label for='logo'><?= labels('logo', "Logo") ?></label>
                                                    <small>(<?= labels('logo_recommended_size', 'We recommend 182 x 60 pixels') ?>)</small>
                                                    <input type="file" name="partner_logo" class="filepond logo"
                                                        id="partner_logo" accept="image/*">
                                                    <?php if (isset($partner_logo) && $partner_logo != ""): ?>
                                                        <img class="settings_logo" src="<?= $partner_logo ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 ">
                                                <label for='favicon'><?= labels('favicon', "Favicon") ?></label>
                                                <input type="file" name="partner_favicon" class="filepond logo"
                                                    id="partner_favicon" accept="image/*">
                                                <?php if (isset($partner_favicon) && $partner_favicon != ""): ?>
                                                    <img class="settings_logo" src="<?= $partner_favicon ?>">
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 ">
                                                <div class="form-group">
                                                    <label
                                                        for='halfLogo'><?= labels('half_logo', "Half Logo") ?></label>
                                                    <small>(<?= labels('half_logo_recommended_size', 'We recommend 40 x 40 pixels') ?>)</small>
                                                    <input type="file" name="partner_half_logo" class="filepond logo"
                                                        id="partner_half_logo" accept="image/*">
                                                    <?php if (isset($partner_half_logo) && $partner_half_logo != ""): ?>
                                                        <img class="settings_logo" src="<?= $partner_half_logo ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <h6 class="font-weight-bold mb-3 mt-2">
                                            <?= labels('handyman_logos', "Handyman Logos") ?></h6>
                                        <div class="row">
                                            <div class="col-md-4 ">
                                                <div class="form-group">
                                                    <label for='handyman_logo'><?= labels('logo', "Logo") ?></label>
                                                    <small>(<?= labels('logo_recommended_size', 'We recommend 182 x 60 pixels') ?>)</small>
                                                    <input type="file" name="handyman_logo" class="filepond logo"
                                                        id="handyman_logo" accept="image/*">
                                                    <?php if (isset($handyman_logo) && $handyman_logo != ""): ?>
                                                        <img class="settings_logo" src="<?= $handyman_logo ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 ">
                                                <label
                                                    for='handyman_favicon'><?= labels('favicon', "Favicon") ?></label>
                                                <input type="file" name="handyman_favicon" class="filepond logo"
                                                    id="handyman_favicon" accept="image/*">
                                                <?php if (isset($handyman_favicon) && $handyman_favicon != ""): ?>
                                                    <img class="settings_logo" src="<?= $handyman_favicon ?>">
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 ">
                                                <div class="form-group">
                                                    <label
                                                        for='handyman_half_logo'><?= labels('half_logo', "Half Logo") ?></label>
                                                    <small>(<?= labels('half_logo_recommended_size', 'We recommend 40 x 40 pixels') ?>)</small>
                                                    <input type="file" name="handyman_half_logo" class="filepond logo"
                                                        id="handyman_half_logo" accept="image/*">
                                                    <?php if (isset($handyman_half_logo) && $handyman_half_logo != ""): ?>
                                                        <img class="settings_logo" src="<?= $handyman_half_logo ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Advanced Settings -->
                                    <div class="tab-pane fade" id="gs-advanced" role="tabpanel"
                                        aria-labelledby="gs-advanced-tab">
                                        <h6 class="font-weight-bold mb-3"><?= labels('otp_settings', "OTP Settings") ?>
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6 ">
                                                <div class="form-group">
                                                    <div class="control-label"><?= labels('otp_system', "OTP System") ?>
                                                        <span class="breadcrumb-item pt-2 text-primary">
                                                            <i data-content="<?= labels('data_content_otp_system', 'If enabled, both the provider and admin need to obtain an OTP from the customer in order to mark the booking as completed. Otherwise, if no OTP verification is required, the booking can be directly marked as completed.') ?>"
                                                                class="fa fa-question-circle" data-original-title=""
                                                                title=""></i></span></div>
                                                    <select name="otp_system" class="form-control">
                                                        <option value="0" <?php echo isset($otp_system) && $otp_system == '0' ? 'selected' : '' ?>>
                                                            <?= labels('disable', 'Disable') ?></option>
                                                        <option value="1" <?php echo isset($otp_system) && $otp_system == '1' ? 'selected' : '' ?>>
                                                            <?= labels('enable', 'Enable') ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <h6 class="font-weight-bold mb-3 mt-2">
                                            <?= labels('deep_link_settings', "Deep Link Settings") ?></h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="schema"><?= labels('schema', "Schema") ?></label>
                                                    <small class="text-grey"><?= labels('note', 'Note:') ?>
                                                        <?= labels('note_for_deeplink', 'Please add your schema here using a single word in lowercase (e.g., edemand)') ?>.</small>
                                                    <input type="text" class=" form-control" name="schema_for_deeplink"
                                                        id="schema"
                                                        value="<?= isset($schema_for_deeplink) ? htmlspecialchars($schema_for_deeplink, ENT_QUOTES, 'UTF-8') : '' ?>"
                                                        placeholder="<?= labels('your_schema', 'your schema') ?>" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-6 ">
                                                <div class="form-group">
                                                    <div class="control-label">
                                                        <?= labels('image_compression_preference', "Image Compression Preference") ?>
                                                        <span class="breadcrumb-item p-3 pt-2 text-primary">
                                                            <i data-content="<?= labels('data_content_image_compression_preference', 'If enabled, This high-quality image has been compressed to a lower quality, as per the quality provided in Image Compression Quality.') ?>"
                                                                class="fa fa-question-circle" data-original-title=""
                                                                title=""></i></span>
                                                    </div>
                                                    <select name="image_compression_preference" class="form-control"
                                                        id="image_compression_preference">
                                                        <option value="0" <?php echo isset($image_compression_preference) && $image_compression_preference == '0' ? 'selected' : '' ?>>
                                                            <?= labels('disable', 'Disable') ?>
                                                        </option>
                                                        <option value="1" <?php echo isset($image_compression_preference) && $image_compression_preference == '1' ? 'selected' : '' ?>>
                                                            <?= labels('enable', 'Enable') ?>
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6" id="image_compression_quality_input">
                                                <div class="form-group">
                                                    <div class="control-label">
                                                        <?= labels('image_compression_quality', "Image Compression Quality") ?>
                                                        <span class="breadcrumb-item p-3 pt-2 text-primary">
                                                            <i data-content="<?= labels('data_content_image_compression_quality', 'This high-quality image has been compressed to a lower quality, as per the quality provided here.') ?>"
                                                                class="fa fa-question-circle" data-original-title=""
                                                                title=""></i></span>
                                                    </div>
                                                    <input type="number" max=100 min=0 class="form-control"
                                                        name="image_compression_quality" id="image_compression_quality"
                                                        value="<?= isset($image_compression_quality) ? $image_compression_quality : '70' ?>" />
                                                </div>
                                            </div>
                                        </div>
                                        <h6 class="font-weight-bold mb-3 mt-2">
                                            <?= labels('file_manager_settings', "File Manager Settings") ?></h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="type"
                                                        class="required"><?= labels('file_manager', 'File Manager') ?></label>
                                                    <select class="select2" name="file_manager" id="file_manager"
                                                        required>
                                                        <option value="local_server" <?php echo isset($file_manager) && $file_manager == 'local_server' ? 'selected' : '' ?>>
                                                            <?= labels('local_server', 'Local Server') ?>
                                                        </option>
                                                        <option value="aws_s3" <?php echo isset($file_manager) && $file_manager == 'aws_s3' ? 'selected' : '' ?>>
                                                            <?= labels('aws_s3', 'AWS S3') ?>
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <div class="control-label">
                                                        <?= labels('file_transfer_process', "File Transfer Process") ?>
                                                    </div>
                                                    <label class="mt-2">
                                                        <input type="hidden" name="file_transfer_process"
                                                            value="<?= isset($file_transfer_process) && $file_transfer_process == 1 ? '1' : '0' ?>"
                                                            id="file_transfer_process_value">
                                                        <input type="checkbox" class="status-switch app-toggle"
                                                            id="file_transfer_process"
                                                            data-hidden="#file_transfer_process_value"
                                                            data-note="#file_transfer_note"
                                                            data-value="<?= isset($file_transfer_process) && $file_transfer_process == 1 ? 1 : 0 ?>"
                                                            value="0" <?= isset($file_transfer_process) && $file_transfer_process == 1 ? 'checked' : '' ?>>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-12" id="file_transfer_note">
                                                <div class="alert alert-light alert-has-icon">
                                                    <div class="alert-icon"><i class="far fa-lightbulb"></i></div>
                                                    <div class="alert-body">
                                                        <div class="alert-title"><?= labels('note', 'Note') ?></div>
                                                        <?= labels('enable_file_transfer_need_to_set_below_command_cron_job', 'If you enable file transfer process then you need to set below command to your cron job') ?>
                                                        ::
                                                        <br>
                                                        <p class="danger">* * * * * cd /path/to/your/project && php
                                                            spark queue:work
                                                            filemanagerchanges --sleep=3 --tries=3 -max-jobs=20
                                                            --stop-when-empty >>
                                                            /dev/null 2>&1</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row aws_s3">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label
                                                        for="aws_access_key_id"><?= labels('aws_access_key_id', "AWS Access Key ID") ?></label>
                                                    <input type="text" class=" form-control" name="aws_access_key_id"
                                                        id="aws_access_key_id"
                                                        value="<?= (isset($aws_access_key_id) && (ALLOW_VIEW_KEYS == 1)) ? $aws_access_key_id : 'your aws access key id' ?>" />
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label
                                                        for="aws_access_key_id"><?= labels('aws_secret_access_key', "AWS Secret Access Key") ?></label>
                                                    <input type="text" class=" form-control"
                                                        name="aws_secret_access_key" id="aws_secret_access_key"
                                                        value="<?= (isset($aws_secret_access_key) && (ALLOW_VIEW_KEYS == 1)) ? $aws_secret_access_key : 'you aws secret access key' ?>" />
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label
                                                        for="aws_access_key_id"><?= labels('aws_default_region', "AWS Default Region") ?></label>
                                                    <select name="aws_default_region" class="select2"
                                                        id="aws_default_region">
                                                        <option value="us-east-1" <?php echo isset($aws_default_region) && $aws_default_region == 'us-east-1' ? 'selected' : '' ?>>US
                                                            East (N. Virginia)
                                                            - us-east-1</option>
                                                        <option value="us-east-2" <?php echo isset($aws_default_region) && $aws_default_region == 'us-east-2' ? 'selected' : '' ?>>US
                                                            East (Ohio) -
                                                            us-east-2</option>
                                                        <option value="us-west-1" <?php echo isset($aws_default_region) && $aws_default_region == 'us-west-1' ? 'selected' : '' ?>>US
                                                            West (N.
                                                            California) - us-west-1</option>
                                                        <option value="us-west-2" <?php echo isset($aws_default_region) && $aws_default_region == 'us-west-2' ? 'selected' : '' ?>>US
                                                            West (Oregon) -
                                                            us-west-2</option>
                                                        <option value="ca-central-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ca-central-1' ? 'selected' : '' ?>>
                                                            Canada (Central) -
                                                            ca-central-1</option>
                                                        <option value="ca-central-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ca-central-1' ? 'selected' : '' ?>>
                                                            Canada (West) -
                                                            ca-central-1</option>
                                                        <option value="us-gov-west-1" <?php echo isset($aws_default_region) && $aws_default_region == 'us-gov-west-1' ? 'selected' : '' ?>>
                                                            GovCloud (US-West)
                                                            - us-gov-west-1</option>
                                                        <option value="us-gov-east-1" <?php echo isset($aws_default_region) && $aws_default_region == 'us-gov-east-1' ? 'selected' : '' ?>>
                                                            GovCloud (US-East)
                                                            - us-gov-east-1</option>
                                                        <option value="mx-central-1" <?php echo isset($aws_default_region) && $aws_default_region == 'mx-central-1' ? 'selected' : '' ?>>
                                                            Mexico (Central) -
                                                            mx-central-1</option>
                                                        <option value="sa-east-1" <?php echo isset($aws_default_region) && $aws_default_region == 'sa-east-1' ? 'selected' : '' ?>>Sao
                                                            Paulo, Brazil -
                                                            sa-east-1</option>
                                                        <option value="eu-west-2" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-west-2' ? 'selected' : '' ?>>
                                                            London, UK - eu-west-2
                                                        </option>
                                                        <option value="eu-central-1" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-central-1' ? 'selected' : '' ?>>
                                                            Frankfurt, Germany
                                                            - eu-central-1</option>
                                                        <option value="eu-west-1" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-west-1' ? 'selected' : '' ?>>
                                                            Ireland - eu-west-1
                                                        </option>
                                                        <option value="eu-west-3" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-west-3' ? 'selected' : '' ?>>
                                                            Paris, France -
                                                            eu-west-3</option>
                                                        <option value="eu-north-1" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-north-1' ? 'selected' : '' ?>>
                                                            Stockholm, Sweden -
                                                            eu-north-1</option>
                                                        <option value="eu-south-1" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-south-1' ? 'selected' : '' ?>>
                                                            Milan, Italy -
                                                            eu-south-1</option>
                                                        <option value="eu-south-2" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-south-2' ? 'selected' : '' ?>>
                                                            Spain - eu-south-2
                                                        </option>
                                                        <option value="me-south-1" <?php echo isset($aws_default_region) && $aws_default_region == 'me-south-1' ? 'selected' : '' ?>>
                                                            Bahrain - me-south-1
                                                        </option>
                                                        <option value="af-south-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-east-1' ? 'selected' : '' ?>>
                                                            Cape Town, South
                                                            Africa - af-south-1</option>
                                                        <option value="ap-east-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-east-1' ? 'selected' : '' ?>>
                                                            Hong Kong SAR, China -
                                                            ap-east-1</option>
                                                        <option value="ap-northeast-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-northeast-1' ? 'selected' : '' ?>>
                                                            Tokyo, Japan -
                                                            ap-northeast-1</option>
                                                        <option value="ap-northeast-2" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-northeast-2' ? 'selected' : '' ?>>
                                                            Seoul, South
                                                            Korea - ap-northeast-2</option>
                                                        <option value="ap-southeast-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-southeast-2' ? 'selected' : '' ?>>
                                                            Singapore -
                                                            ap-southeast-1</option>
                                                        <option value="ap-southeast-2" <?php echo isset($aws_default_region) && $aws_default_region == 'us-east-1' ? 'selected' : '' ?>>
                                                            Sydney, Australia -
                                                            ap-southeast-2</option>
                                                        <option value="ap-south-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-south-1' ? 'selected' : '' ?>>
                                                            Mumbai, India -
                                                            ap-south-1</option>
                                                        <option value="ap-southeast-3" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-southeast-3' ? 'selected' : '' ?>>
                                                            Jakarta,
                                                            Indonesia - ap-southeast-3</option>
                                                        <option value="cn-north-1" <?php echo isset($aws_default_region) && $aws_default_region == 'cn-north-1' ? 'selected' : '' ?>>
                                                            Beijing, China -
                                                            cn-north-1</option>
                                                        <option value="cn-northwest-1" <?php echo isset($aws_default_region) && $aws_default_region == 'cn-northwest-1' ? 'selected' : '' ?>>
                                                            Ningxia, China -
                                                            cn-northwest-1</option>
                                                        <option value="ap-northeast-3" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-northeast-3' ? 'selected' : '' ?>>
                                                            Osaka-Local,
                                                            Japan - ap-northeast-3</option>
                                                        <option value="ap-southeast-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-southeast-1' ? 'selected' : '' ?>>
                                                            Singapore -
                                                            ap-southeast-1</option>
                                                        <option value="ap-southeast-2" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-southeast-2' ? 'selected' : '' ?>>
                                                            Sydney, Australia
                                                            - ap-southeast-2</option>
                                                        <option value="ap-southeast-3" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-southeast-3' ? 'selected' : '' ?>>
                                                            Jakarta,
                                                            Indonesia - ap-southeast-3</option>
                                                        <option value="ap-northeast-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-northeast-1' ? 'selected' : '' ?>>
                                                            Tokyo, Japan -
                                                            ap-northeast-1</option>
                                                        <option value="ap-northeast-2" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-northeast-2' ? 'selected' : '' ?>>
                                                            Seoul, South
                                                            Korea - ap-northeast-2</option>
                                                        <option value="ap-northeast-3" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-northeast-3' ? 'selected' : '' ?>>
                                                            Osaka-Local,
                                                            Japan - ap-northeast-3</option>
                                                        <option value="ap-south-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-south-1' ? 'selected' : '' ?>>
                                                            Mumbai, India -
                                                            ap-south-1</option>
                                                        <option value="ap-east-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ap-east-1' ? 'selected' : '' ?>>
                                                            Hong Kong SAR, China -
                                                            ap-east-1</option>
                                                        <option value="cn-north-1" <?php echo isset($aws_default_region) && $aws_default_region == 'cn-north-1' ? 'selected' : '' ?>>
                                                            Beijing, China -
                                                            cn-north-1</option>
                                                        <option value="cn-northwest-1" <?php echo isset($aws_default_region) && $aws_default_region == 'cn-northwest-1' ? 'selected' : '' ?>>
                                                            Ningxia, China -
                                                            cn-northwest-1</option>
                                                        <option value="eu-central-1" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-central-1' ? 'selected' : '' ?>>
                                                            Frankfurt, Germany
                                                            - eu-central-1</option>
                                                        <option value="eu-west-1" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-west-1' ? 'selected' : '' ?>>
                                                            Ireland - eu-west-1
                                                        </option>
                                                        <option value="eu-west-2" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-west-2' ? 'selected' : '' ?>>
                                                            London, UK - eu-west-2
                                                        </option>
                                                        <option value="eu-west-3" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-west-3' ? 'selected' : '' ?>>
                                                            Paris, France -
                                                            eu-west-3</option>
                                                        <option value="eu-north-1" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-north-1' ? 'selected' : '' ?>>
                                                            Stockholm, Sweden -
                                                            eu-north-1</option>
                                                        <option value="eu-south-1" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-south-1' ? 'selected' : '' ?>>
                                                            Milan, Italy -
                                                            eu-south-1</option>
                                                        <option value="eu-south-2" <?php echo isset($aws_default_region) && $aws_default_region == 'eu-south-2' ? 'selected' : '' ?>>
                                                            Spain - eu-south-2
                                                        </option>
                                                        <option value="me-south-1" <?php echo isset($aws_default_region) && $aws_default_region == 'me-south-1' ? 'selected' : '' ?>>
                                                            Bahrain - me-south-1
                                                        </option>
                                                        <option value="af-south-1" <?php echo isset($aws_default_region) && $aws_default_region == 'af-south-1' ? 'selected' : '' ?>>
                                                            Cape Town, South
                                                            Africa - af-south-1</option>
                                                        <option value="sa-east-1" <?php echo isset($aws_default_region) && $aws_default_region == 'sa-east-1' ? 'selected' : '' ?>>Sao
                                                            Paulo, Brazil -
                                                            sa-east-1</option>
                                                        <option value="ca-central-1" <?php echo isset($aws_default_region) && $aws_default_region == 'ca-central-1' ? 'selected' : '' ?>>
                                                            Canada (Central) -
                                                            ca-central-1</option>
                                                        <option value="us-gov-west-1" <?php echo isset($aws_default_region) && $aws_default_region == 'us-gov-west-1' ? 'selected' : '' ?>>
                                                            GovCloud (US-West)
                                                            - us-gov-west-1</option>
                                                        <option value="us-gov-east-1" <?php echo isset($aws_default_region) && $aws_default_region == 'us-gov-east-1' ? 'selected' : '' ?>>
                                                            GovCloud (US-East)
                                                            - us-gov-east-1</option>
                                                        <option value="us-east-1" <?php echo isset($aws_default_region) && $aws_default_region == 'us-east-1' ? 'selected' : '' ?>>US
                                                            East (N. Virginia)
                                                            - us-east-1</option>
                                                        <option value="us-east-2" <?php echo isset($aws_default_region) && $aws_default_region == 'us-east-2' ? 'selected' : '' ?>>US
                                                            East (Ohio) -
                                                            us-east-2</option>
                                                        <option value="us-west-1" <?php echo isset($aws_default_region) && $aws_default_region == 'us-west-1' ? 'selected' : '' ?>>US
                                                            West (N.
                                                            California) - us-west-1</option>
                                                        <option value="us-west-2" <?php echo isset($aws_default_region) && $aws_default_region == 'us-west-2' ? 'selected' : '' ?>>US
                                                            West (Oregon) -
                                                            us-west-2</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label
                                                        for="aws_access_key_id"><?= labels('aws_bucket', "AWS Bucket") ?></label>
                                                    <input type="text" class=" form-control" name="aws_bucket"
                                                        id="aws_bucket"
                                                        value="<?= (isset($aws_bucket) && (ALLOW_VIEW_KEYS == 1)) ? $aws_bucket : 'your aws bucket' ?>" />
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label
                                                        for="aws_access_key_id"><?= labels('aws_url', "AWS URL") ?></label>
                                                    <input type="text" class=" form-control" name="aws_url" id="aws_url"
                                                        value="<?= (isset($aws_url) && (ALLOW_VIEW_KEYS == 1)) ? $aws_url : 'your_aws_url' ?>" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Provider Verification Settings removed.
             Kept in DB for backwards compatibility. -->
        <?php if ($permissions['update']['settings'] == 1): ?>
            <div class="row mb-3">
                <div class="col-md d-flex justify-content-end">
                    <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Save") ?>'
                        class='btn btn-lg bg-new-primary' />
                </div>
            </div>
        <?php endif; ?>
        <?= form_close() ?>
    </section>
</div>

<!-- Modal for Provider-wise Max Serviceable Distance Warning & Configuration -->
<div class="modal fade" id="providerWiseModal" tabindex="-1" role="dialog" aria-labelledby="providerWiseModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="providerWiseModalLabel">
                    <?= labels('provider_wise_distance_settings', 'Provider-wise Max Distance Settings') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-dark">
                <div class="alert alert-info">
                    <?= labels('provider_wise_distance_warning_content', 'Please note: If provider-wise is set, then individual providers must set this distance in their profile in order for customers to see the providers in their serviceable area.') ?>
                </div>

                <!-- Apply to All section -->
                <div class="form-group">
                    <label
                        for="apply_all_distance"><?= labels('apply_to_all_providers', 'Apply Distance to All Providers') ?></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="apply_all_distance"
                            placeholder="Enter distance (e.g. 50)" min="0">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-primary"
                                id="apply_all_distance_btn"><?= labels('apply_to_all', 'Apply to All') ?></button>
                        </div>
                    </div>
                </div>

                <!-- Provider List Container -->
                <div class="provider-list-container mt-4"
                    style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                    <label class="font-weight-bold mb-2"><?= labels('provider_list', 'Provider List') ?></label>
                    <div id="provider_cards_wrapper">
                        <div class="text-center py-3" id="provider_cards_loading">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                <button type="button" class="btn btn-primary" id="save_provider_distances_btn">
                    <?= labels('save_changes', 'Save changes') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function test() {
        $('.custome_reset').attr('value', '');
    }
    $('#otp_system').on('change', function () {
        this.value = this.checked ? 1 : 0;
    }).change();
</script>
<script>
    $(document).ready(function () {
        $('.app-toggle').each(function () {
            initToggle(this);
        });
    });

    function initToggle(checkbox) {
        const $checkbox = $(checkbox);
        const value = Number($checkbox.data('value')) || 0;
        const hiddenSelector = $checkbox.data('hidden');
        const noteSelector = $checkbox.data('note');

        const $hiddenInput = $(hiddenSelector);
        const $switchery = $checkbox.siblings('.switchery');
        const $note = noteSelector ? $(noteSelector) : null;

        if (!$hiddenInput.length) return;

        // Initial state
        checkbox.checked = value === 1;
        $hiddenInput.val(value);
        updateUI(value);

        // Change handler
        $checkbox.on('change', function () {
            const state = this.checked ? 1 : 0;
            $hiddenInput.val(state);
            updateUI(state);
        });

        function updateUI(state) {
            $switchery
                .toggleClass('yes-content', state === 1)
                .toggleClass('no-content', state !== 1);

            if ($note) {
                $note.toggle(state === 1);
            }
        }
    }

    $(function () {
        $('.fa').popover({
            trigger: "hover"
        });
    })
    if (<?= isset($image_compression_preference) && $image_compression_preference == 1 ? 'true' : 'false' ?>) {
        $("#image_compression_quality_input").show();
    } else {
        $("#image_compression_quality_input").hide();
    }
    $("#image_compression_preference").change(function () {
        if (this.value == 1) {
            $("#image_compression_quality_input").show();
        } else {
            $("#image_compression_quality_input").hide();
        }
    });
    $(document).ready(function () {
        // Assuming the PHP variable `$file_manager` is passed to the JavaScript as a global variable
        var fileManager = '<?php echo isset($file_manager) ? $file_manager : 'local_server' ?>';
        // Check if `fileManager` is defined and equals 'aws_s3'
        if (typeof fileManager !== 'undefined' && fileManager === 'aws_s3') {
            $('.aws_s3').show();
        } else {
            $('.aws_s3').hide();
        }
        // Handle changes to the file_manager select element
        $('#file_manager').change(function () {
            var selectedValue = $(this).val();
            $('.aws_s3').toggle(selectedValue === 'aws_s3');
        });

        $('#max_serviceable_distance_type').select2({
            minimumResultsForSearch: 5
        });

        function toggleMaxServiceableDistance() {
            if ($("#max_serviceable_distance_type").val() === 'global') {
                $("#max_serviceable_distance_input_group").show();
            } else {
                $("#max_serviceable_distance_input_group").hide();
            }
        }

        toggleMaxServiceableDistance();

        $("#max_serviceable_distance_type").change(function () {
            toggleMaxServiceableDistance();
            if (this.value === 'provider_wise') {
                $('#providerWiseModal').modal('show');
            }
        });

        $('#apply_all_distance_btn').on('click', function () {
            var val = $('#apply_all_distance').val();
            if (val !== '') {
                $('.provider-distance-input').val(val);
            }
        });

        $('#save_provider_distances_btn').on('click', function () {
            var formData = new FormData();
            $('.provider-distance-input').each(function () {
                formData.append('distances[' + $(this).data('provider-id') + ']', $(this).val());
            });
            ajaxRequest('POST', '<?= base_url('admin/settings/save-provider-distances') ?>',
                formData,
                null,
                function (response) {
                    showToastMessage(response.message, 'success');
                    $('#providerWiseModal').modal('hide');
                },
                function (response) {
                    showToastMessage(response.message, 'error');
                }
            );
        });

        var providerCardsLoaded = false;

        $('#providerWiseModal').on('show.bs.modal', function () {
            if (providerCardsLoaded) return;
            ajaxRequest('GET', '<?= base_url('admin/settings/get-approved-providers') ?>', {}, null,
                function (response) {
                    var providers = response.data;
                    var $wrapper = $('#provider_cards_wrapper');
                    $wrapper.empty();

                    if (!providers || providers.length === 0) {
                        $wrapper.html('<p class="text-muted text-center"><?= labels('no_approved_providers', 'No approved providers found') ?></p>');
                        return;
                    }

                    $.each(providers, function (i, p) {
                        var existingDist = (p.max_serviceable_distance !== null && p.max_serviceable_distance !== undefined) ? p.max_serviceable_distance : '';
                        var card = '<div class="card mb-2 border">' +
                            '<div class="card-body p-3">' +
                            '<div class="row align-items-center">' +
                            '<div class="col-sm-8 d-flex align-items-center">' +
                            '<div class="o-media o-media--middle d-flex align-items-center w-100">' +
                            '<div class="mr-3">' +
                            '<img class="img-fluid rounded-circle" style="width:50px;height:50px;object-fit:cover;" src="' + p.image + '" alt="' + $('<div>').text(p.name).html() + '">' +
                            '</div>' +
                            '<div class="o-media__body">' +
                            '<div class="font-weight-bold" style="font-size:15px;color:#34395e;">' + $('<div>').text(p.name).html() + '</div>' +
                            '<div class="text-muted" style="font-size:12px;">' + $('<div>').text(p.company_name).html() + '</div>' +
                            '<div class="text-muted" style="font-size:12px;">' +
                            '<span>' + $('<div>').text(p.email).html() + '</span>' +
                            (p.phone ? ' (' + $('<div>').text(p.phone).html() + ')' : '') +
                            '</div>' +
                            '</div>' +
                            '</div>' +
                            '</div>' +
                            '<div class="col-sm-4 mt-3 mt-sm-0">' +
                            '<div class="input-group">' +
                            '<input type="number" class="form-control provider-distance-input" data-provider-id="' + p.id + '" placeholder="<?= labels('distance', 'Distance') ?>" min="0" value="' + existingDist + '">' +
                            '<div class="input-group-append"><span class="input-group-text"><?= labels('kms', 'Kms') ?></span></div>' +
                            '</div>' +
                            '</div>' +
                            '</div>' +
                            '</div>' +
                            '</div>';
                        $wrapper.append(card);
                    });

                    providerCardsLoaded = true;
                },
                function () {
                    $('#provider_cards_wrapper').html('<p class="text-danger text-center"><?= labels('error_loading_providers', 'Failed to load providers') ?></p>');
                },
                null, true
            );
        });
    });
</script>
<script>
    function handleSwitchChange(checkbox) {
        const $checkbox = $(checkbox);
        const isChecked = checkbox.checked ? 1 : 0;

        const hiddenSelector = $checkbox.data('hidden');
        const noteSelector = $checkbox.data('note');

        const $hiddenInput = hiddenSelector ? $(hiddenSelector) : null;
        const $note = noteSelector ? $(noteSelector) : null;
        const $switchery = $checkbox.siblings('.switchery');

        // Sync hidden input
        if ($hiddenInput && $hiddenInput.length) {
            $hiddenInput.val(isChecked);
        }

        // Toggle note (optional)
        if ($note && $note.length) {
            $note.toggle(isChecked === 1);
        }

        // Toggle UI
        $switchery
            .toggleClass('yes-content', isChecked === 1)
            .toggleClass('no-content', isChecked !== 1);
    }

    // function handleSwitchChange(checkbox) {
    //     var isChecked = checkbox.checked;
    //     var checkboxId = checkbox.id;

    //     // FILE TRANSFER SWITCH
    //     if (checkboxId === 'file_transfer_process') {
    //         $('#file_transfer_process_value').val(isChecked ? "1" : "0");

    //         if (isChecked) {
    //             $('#file_transfer_note').show();
    //         } else {
    //             $('#file_transfer_note').hide();
    //         }
    //     }

    //     // CHAT IMAGE UPLOAD SWITCH
    //     if (checkboxId === 'enable_chat_image_upload') {
    //         $('#enable_chat_image_upload_value').val(isChecked ? "1" : "0");
    //     }

    //     // Switchery UI update (only THIS switch)
    //     var switchery = $(checkbox).siblings('.switchery');
    //     switchery
    //         .toggleClass('yes-content', isChecked)
    //         .toggleClass('no-content', !isChecked);
    // }

    $(document).ready(function () {
        // var checkbox = $('#file_transfer_process')[0];
        // handleSwitchChange(checkbox); // Initialize state
        $('#file_transfer_process').on('change', '.app-toggle', function () {
            handleSwitchChange(this);
        });

        $('#enable_chat_image_upload').on('change', '.app-toggle', function () {
            handleSwitchChange(this);
        });

        $('#enable_chat_file_upload').on('change', '.app-toggle', function () {
            handleSwitchChange(this);
        });

        // Language tab functionality for company settings - single tab system
        $('.language-company-option').on('click', function () {
            var selectedLanguage = $(this).data('language');

            // Update active tab
            $('.language-company-option').removeClass('selected');
            $('.language-company-text').removeClass('text-primary fw-medium').addClass('text-muted');
            $('.language-company-underline').css('width', '0');

            $(this).addClass('selected');
            $(this).find('.language-company-text').removeClass('text-muted').addClass('text-primary fw-medium');
            $(this).find('.language-company-underline').css('width', '100%');

            // Update labels with language code
            var isDefault = $(this).find('.language-company-text').text().includes('(Default)');
            var languageCode = isDefault ? '' : ' (' + selectedLanguage.toUpperCase() + ')';

            $('#company_title_label').text('<?= labels("company_title", "Company Title") ?>' + languageCode);
            $('#copyright_details_label').text('<?= labels("copyright_details", "Copyright Details") ?>' + languageCode);
            $('#address_label').text('<?= labels("address", "Address") ?>' + languageCode);
            $('#short_description_label').text('<?= labels("short_description", "Short Description") ?>' + languageCode);

            // Show/hide all company multilingual fields for the selected language
            $('input[name^="company_title["]').hide();
            $('input[name="company_title[' + selectedLanguage + ']"]').show();

            $('input[name^="copyright_details["]').hide();
            $('input[name="copyright_details[' + selectedLanguage + ']"]').show();

            $('textarea[name^="address["]').hide();
            $('textarea[name="address[' + selectedLanguage + ']"]').show();

            $('textarea[name^="short_description["]').hide();
            $('textarea[name="short_description[' + selectedLanguage + ']"]').show();
        });
    });

    $(document).ready(function () {
        $('form[action*="general-settings"]').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('[type="submit"]');
            var formData = new FormData(this);
            formData.append('update', '1');
            formAjaxRequest('POST', $form.attr('action'), formData, $form, $btn,
                function () {
                    setTimeout(function () { window.location.reload(); }, 3000);
                }
            );
        });
    });
</script>