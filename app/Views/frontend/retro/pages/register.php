<!-- Start Signup Section-->
<!-- End Breadcrumbs -->
<?php
$data = get_settings('general_settings', true);
isset($data['primary_color']) && $data['primary_color'] != "" ? $primary_color = $data['primary_color'] : $primary_color = '#05a6e8';
isset($data['secondary_color']) && $data['secondary_color'] != "" ? $secondary_color = $data['secondary_color'] : $secondary_color = '#003e64';
isset($data['primary_shadow']) && $data['primary_shadow'] != "" ? $primary_shadow = $data['primary_shadow'] : $primary_shadow = '#05A6E8';
$authentication_mode = $data['authentication_mode'];

// Provider registration OTP verification can be done via phone and/or email.
// Keep step 1 aligned with the provider login options:
// - If both modes are enabled => allow entering Email OR Phone
// - If only one is enabled => keep it restricted to that mode
$loginSettings = get_settings('login_settings', true);
$phone_authentication_enabled = (int) ($loginSettings['phone_authentication_enabled'] ?? 1);
$email_authentication_enabled = (int) ($loginSettings['email_authentication_enabled'] ?? 0);

// Keep same behavior as provider login (`login.php`):
// - both enabled => smart switch (PHP boolean true becomes "1" in JS)
// - only email enabled => force email mode
// - only phone enabled => force phone mode
$smart_register = false;
if ($phone_authentication_enabled && $email_authentication_enabled) {
    $smart_register = true;
} elseif ($email_authentication_enabled) {
    $smart_register = 'email';
} elseif ($phone_authentication_enabled) {
    $smart_register = 'phone';
}
$session = \Config\Services::session();
$is_rtl = $session->get('is_rtl');
$language = $session->get('language');
$default_language = fetch_details('languages', ['is_default' => '1']);

// Only check default language's RTL status if no language is set in session
// Otherwise, use the explicit is_rtl value from session
if (empty($language) && !isset($is_rtl)) {
    $is_rtl = $default_language[0]['is_rtl'];
} elseif ($is_rtl === null) {
    // Fallback if is_rtl is not set but language is
    $is_rtl = 0;
}

// Convert to integer value for consistency
$is_rtl = (int) $is_rtl;

?>
<style>
    body {
        --primary-color:
            <?= $primary_color ?>
        ;
        --secondary-color:
            <?= $secondary_color ?>
        ;
    }

    /* Fade animation */
    .fade-in-section {
        animation: fadeIn 0.4s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Brand primary override — Bootstrap uses its own --bs-primary variable */
    .bg-primary {
        background-color:
            <?= $primary_color ?>
            !important;
    }

    /* Custom input wrappers — single-border flex containers */
    .phone-input-group,
    .custom-input-group {
        display: flex;
        align-items: center;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        background-color: #fff;
        overflow: hidden;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .phone-input-group:focus-within,
    .custom-input-group:focus-within {
        border-color:
            <?= $primary_color ?>
        ;
        box-shadow: 0 0 0 0.2rem rgba(5, 166, 232, 0.15);
    }

    /* Icon slots */
    .input-icon-left {
        padding: 0 0.75rem;
        color: #94a3b8;
        flex-shrink: 0;
    }

    .input-icon-right {
        padding: 0 0.75rem;
        color: #94a3b8;
        cursor: pointer;
        flex-shrink: 0;
    }

    /* Inputs/textareas inside wrappers — remove their own border */
    .phone-input-group .form-control,
    .custom-input-group .form-control {
        border: none !important;
        box-shadow: none !important;
        flex: 1;
        min-width: 0;
    }

    /* Country code slot inside phone group */
    .country_code {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
    }

    .country_code select,
    .country_code input[readonly] {
        border: none;
        border-right: 1px solid #ced4da;
        background: transparent;
        padding: 0.5rem 0.5rem;
        max-width: 90px;
        box-shadow: none;
        outline: none;
        font-size: 0.9rem;
        text-align: center;
        color: #495057;
    }

    .country_code input[readonly] {
        cursor: not-allowed;
    }
</style>
<?php helper('function');
$password_rules = get_password_rules(); ?>
<script>
    window.PASSWORD_RULES = {
        minLength: <?= (int) $password_rules['min_length'] ?>,
        requireUppercase: <?= (int) $password_rules['require_uppercase'] ?>,
        requireLowercase: <?= (int) $password_rules['require_lowercase'] ?>,
        requireNumber: <?= (int) $password_rules['require_number'] ?>,
        requireSpecial: <?= (int) $password_rules['require_special'] ?>
    };
</script>
<!-- Password strength indicator: shared CSS and JS (used also on forgot_password) -->
<link rel="stylesheet" href="<?= base_url('public/frontend/retro/css/password-strength.css') ?>">
<script src="<?= base_url('public/frontend/retro/js/password-strength.js') ?>"></script>
<div class="auth" style="overflow: hidden; background-image: url('<?= $login_image_url ?>');">
    <div class="join_us_as_provider">
        <section class="section">
            <section data-aos="fade-up">

                <div class="d-flex justify-content-<?= $is_rtl == 1 ? 'start' : 'end' ?> m-3 row">
                    <div class="col-12 col-md-6 col-sm-11 me-md-5 mt-5">
                        <?php
                        $data = get_settings('general_settings', true);
                        ?>

                        <div class="card p-4 mb-5 shadow-sm border-0 rounded-3">

                            <!-- Hidden original step tracker for JS compatibility -->
                            <div class="d-none"><span class="step">1</span></div>

                            <div class="text-center my-4">
                                <?php if (current_url() == base_url() . '/admin/login') { ?>
                                    <img style="width: 50%; max-width: 200px;"
                                        src="<?= isset($data['logo']) && $data['logo'] != "" ? base_url("public/uploads/site/" . $data['logo']) : base_url('public/backend/assets/img/news/img01.jpg') ?>"
                                        alt="">
                                <?php } else { ?>
                                    <img style="width: 50%; max-width: 200px;"
                                        src="<?= isset($data['partner_logo']) && $data['partner_logo'] != "" ? base_url("public/uploads/site/" . $data['partner_logo']) : base_url('public/backend/assets/img/news/img01.jpg') ?>"
                                        alt="">
                                <?php } ?>
                            </div>

                            <div class="col-md">

                                <div class="card-body" id="step_1">
                                    <div id="send">
                                        <div class="mb-3">
                                            <label for="number"
                                                class="form-label"><?= labels('email_or_phone', 'Email or Phone number') ?></label>

                                            <?php
                                            // Use the model to get country codes (handles soft deletes automatically)
                                            $country_code_model = new \App\Models\Country_code_model();
                                            $country_codes = $country_code_model->findAll();
                                            $system_country_code = $country_code_model->where('is_default', 1)->first();
                                            $default_calling_code = '';
                                            if (!empty($system_country_code['calling_code'])) {
                                                $default_calling_code = $system_country_code['calling_code'];
                                            } else {
                                                $default_calling_code = '+91';
                                            }

                                            // Check if there's only one country code available
                                            $single_country_code = (count($country_codes) == 1);
                                            $single_country_data = $single_country_code ? $country_codes[0] : null;
                                            ?>
                                            <div class="phone-input-group">
                                                <i id="identity-icon" class="fa fa-user input-icon-left"></i>
                                                <div class="country_code">
                                                    <?php if ($single_country_code): ?>
                                                        <?php
                                                        $calling_code_value = $single_country_data['calling_code'] ?? $default_calling_code;
                                                        ?>
                                                        <input type="text" class="form-control" name="country_code"
                                                            id="country_code" value="<?= esc($calling_code_value) ?>"
                                                            readonly>
                                                    <?php else: ?>
                                                        <select class="form-control" name="country_code" id="country_code">
                                                            <?php
                                                            foreach ($country_codes as $key => $country_code) {
                                                                $code = $country_code['calling_code'];
                                                                $selected = ($default_calling_code == $country_code['calling_code']) ? "selected" : "";
                                                                echo "<option $selected value='$code'>$code</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                                <div id="numberInputDiv" class="flex-fill d-flex align-items-center">
                                                    <input id="number" class="form-control" type="text" name="number1"
                                                        placeholder="<?= labels('enter_email_or_phone', 'Email or phone') ?>"
                                                        required>
                                                    <input type="hidden" id="otp_identity" value="">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3 re_captcha">
                                            <div id="rec"></div>
                                        </div>
                                        <div class="mb-3">
                                            <button type="button" class="btn bg-primary text-white btn-lg w-100 mt-2"
                                                id="sender"><?= labels('submit', 'Submit') ?></button>
                                        </div>
                                    </div>

                                    <div class="otp_show">
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label for="otp" class="form-label mb-0"
                                                    style="color: #334155; font-weight: 500; font-size: 15px;"><?= labels('received_otp', 'Received OTP') ?></label>
                                                <a href="javascript:void(0)" id="btn-back-to-send"
                                                    class="text-decoration-none"
                                                    style="color: #0d6efd; font-size: 14px; font-weight: 500;">
                                                    <?= labels('change_number_email', 'Change Number / Email') ?>
                                                </a>
                                            </div>
                                            <input id="otp"
                                                class="form-control form-control-md text-center bg-light rounded"
                                                style="letter-spacing: 0.4em; font-size: 1.5rem;" type="number"
                                                name="otp" placeholder="· · · · · ·">
                                        </div>
                                        <div class="mb-3">
                                            <?php
                                            if ($authentication_mode == "sms_gateway") {
                                                $function_name = "sms_codeverify()";
                                            } else {
                                                $function_name = "codeverify()";
                                            }
                                            ?>
                                            <button type="button" class="btn bg-primary btn-lg text-white w-100 mb-2"
                                                id="check"
                                                onclick="<?= $function_name; ?>"><?= labels('verify_otp', 'Verify OTP') ?></button>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body" id="step_2">
                                    <?= form_open('auth/create_partner', ['id' => 'registerdff']); ?>
                                    <!--
                                      Persist provider verification method:
                                      - email OTP => loginType=email
                                      - phone OTP => loginType=phone
                                      JS sets this after successful OTP verification.
                                    -->
                                    <input type="hidden" id="loginType" name="loginType" value="phone" />
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="first_name"
                                                    class="form-label"><?= labels('user_name', 'User name') ?></label>
                                                <div class="custom-input-group">
                                                    <i class="fa fa-user input-icon-left"></i>
                                                    <input type="text" id="first_name" class="form-control"
                                                        name="username"
                                                        placeholder="<?= labels('first_name', 'First name') ?>"
                                                        required />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email"
                                                    class="form-label"><?= labels('email', 'Email') ?></label>
                                                <div class="custom-input-group">
                                                    <i class="fa fa-envelope input-icon-left"></i>
                                                    <input type="email" id="email" class="form-control" name="email"
                                                        placeholder="<?= labels('email_id', 'Email Id') ?>" required />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="phone"
                                                    class="form-label"><?= labels('phone_number', 'Phone Number') ?></label>
                                                <!--
                                                  Country code dropdown from DB; phone number saved with country_code in users table.
                                                  Editable for email-OTP flow (phone collected in step 2).
                                                  For phone-OTP flow, JS copies step 1 country code into this field.
                                                -->
                                                <div class="phone-input-group">
                                                    <i class="fa fa-phone input-icon-left"></i>
                                                    <div class="country_code store-country-code-wrap">
                                                        <?php if ($single_country_code): ?>
                                                            <input type="text" class="form-control"
                                                                name="store_country_code" id="store_country_code"
                                                                value="<?= esc($single_country_data['calling_code'] ?? $default_calling_code) ?>"
                                                                readonly>
                                                        <?php else: ?>
                                                            <select class="form-control select2" name="store_country_code"
                                                                id="store_country_code">
                                                                <?php
                                                                foreach ($country_codes as $key => $country_code) {
                                                                    $code = $country_code['calling_code'];
                                                                    $selected = ($default_calling_code == $country_code['calling_code']) ? "selected" : "";
                                                                    echo "<option $selected value='" . esc($code) . "'>" . esc($code) . "</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="number-input-wrap flex-fill d-flex align-items-center">
                                                        <input type="text" id="phone" class="form-control" name="phone"
                                                            placeholder="<?= labels('mobile_number', 'Mobile Number') ?>"
                                                            required min="0" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="company_name"
                                                    class="form-label"><?= labels('company_name', 'Company Name') ?></label>
                                                <div class="custom-input-group">
                                                    <i class="fa fa-building input-icon-left"></i>
                                                    <input type="text" id="company_name" class="form-control"
                                                        name="company_name"
                                                        placeholder="<?= labels('company_name', 'Company Name') ?>"
                                                        required />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="password"
                                                    class="form-label"><?= labels('password', 'Password') ?></label>
                                                <div class="custom-input-group">
                                                    <i class="fa fa-lock input-icon-left"></i>
                                                    <input type="password" id="password" class="form-control"
                                                        name="password"
                                                        placeholder="<?= labels('password', 'Password') ?>" required />
                                                    <i class="fa fa-eye-slash toggle-password input-icon-right"
                                                        title="Toggle Password Visibility"></i>
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
                                                        <div class="password-rule" id="rule-uppercase"
                                                            data-rule="uppercase">
                                                            <span class="rule-icon" aria-hidden="true">○</span>
                                                            <span
                                                                class="rule-label"><?= labels('password_strength_contains_uppercase', 'Contains an uppercase letter') ?></span>
                                                        </div>
                                                    <?php } ?>
                                                    <?php if (!empty($password_rules['require_lowercase'])) { ?>
                                                        <div class="password-rule" id="rule-lowercase"
                                                            data-rule="lowercase">
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
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="password_confirm"
                                                    class="form-label"><?= labels('confirm_password', 'Confirm Password') ?></label>
                                                <div class="custom-input-group">
                                                    <i class="fa fa-lock input-icon-left"></i>
                                                    <input type="password" id="password_confirm" class="form-control"
                                                        name="password_confirm"
                                                        placeholder="<?= labels('confirm_password', 'Confirm Password') ?>"
                                                        required />
                                                    <i class="fa fa-eye-slash toggle-password input-icon-right"
                                                        title="Toggle Password Visibility"></i>
                                                </div>
                                                <small id="password-match-msg" class="text-danger mt-1"
                                                    style="display: none;">
                                                    <i class="fa fa-exclamation-circle"></i>
                                                    <?= labels('passwords_do_not_match', 'Passwords do not match') ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check mt-4 mb-3">
                                            <input class="form-check-input" type="checkbox" value="" id="acceptTerms">
                                            <label class="form-check-label" for="acceptTerms">
                                                <?= labels('i_agree_to', 'I have read and agree to the') ?> <a
                                                    href="<?= base_url('admin/settings/legal-pages/preview/terms_conditions') ?>"
                                                    target="_blank"
                                                    style="color: var(--primary-color); font-weight: 600; text-decoration: none;"><?= labels('terms_and_conditions', 'Terms & Conditions') ?></a>
                                                <?= labels('and', 'and') ?> <a
                                                    href="<?= base_url('admin/settings/legal-pages/preview/privacy_policy') ?>"
                                                    target="_blank"
                                                    style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
                                                    <?= labels('privacy_policy', 'Privacy Policy') ?>
                                                </a>
                                            </label>
                                        </div>

                                        <button type="submit" class="btn bg-primary btn-lg w-100 mt-2 text-white"
                                            id="btn-register" disabled>
                                            <?= labels('register', 'Register') ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md d-flex justify-content-center">
                                    <?= labels('back_to', ' Back To') ?> &nbsp; <a
                                        href="<?= base_url('partner/login') ?>"
                                        class=""><b><?= labels('login', 'Login') ?></b></a><br>
                                </div>
                            </div>

                        </div>
                    </div>
            </section>

        </section>
    </div>
</div>


<!-- Signup Section End -->
<script>
    $(document).ready(function () {
        // Password requirements validation on submit (Authentication Settings)
        $('#registerdff').on('submit', function (e) {
            // Check password match first
            var password = $('#password').val();
            var confirmPassword = $('#password_confirm').val();
            if (password !== confirmPassword) {
                e.preventDefault();
                $('#password-match-msg').show();
                $('#password_confirm').closest('.custom-input-group').css('border-color', '#dc3545');
                if (typeof toastr !== 'undefined') {
                    toastr.error("<?= labels('passwords_do_not_match', 'Passwords do not match') ?>");
                }
                return false;
            }

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

        // Match the same email/phone input behavior as `login.php`
        var isSingleCountryCode = <?= $single_country_code ? 'true' : 'false' ?>;
        var smartRegister = "<?= $smart_register ?>";

        // Set default country code (when dropdown exists)
        if (!isSingleCountryCode) {
            var selectedCountryCode = "<?php echo $default_calling_code; ?>";
            $('#country_code').val(selectedCountryCode).trigger('change');
        }

        // Apply initial mode (email-only / phone-only / smart).
        // Only show/hide step 1's country code (#step_1 .country_code), so step 2's dropdown is never hidden.
        if (smartRegister === 'email') {
            $('#number').attr('type', 'email').attr('placeholder', 'Enter your email');
            $('#step_1 .country_code').hide();
            $('#identity-icon').removeClass('fa-user fa-phone').addClass('fa-envelope');
        } else if (smartRegister === 'phone') {
            $('#number').attr('type', 'tel').attr('placeholder', 'Enter phone number');
            $('#step_1 .country_code').show();
            $('#identity-icon').removeClass('fa-user fa-envelope').addClass('fa-phone');
        } else if (smartRegister === '1') {
            // Start in neutral mode (smart switch based on input)
            $('#number').attr('type', 'text').attr('placeholder', 'Email or phone');
            $('#step_1 .country_code').hide();
            $('#identity-icon').removeClass('fa-envelope fa-phone').addClass('fa-user');
        }

        // Smart switch (only when both are enabled). Scope to step 1 so step 2 country dropdown stays visible.
        $('#number').on('input', function () {
            if (smartRegister !== '1') return;

            var input = $(this);
            var val = input.val();
            var currentType = input.attr('type');
            var newType = 'text';
            var showCountryCode = true;

            // If empty, reset to neutral state
            if (!val || val.length === 0) {
                newType = 'text';
                showCountryCode = false;
            }
            // If it contains letters or @ => email
            else if (val.includes('@') || /[a-zA-Z]/.test(val)) {
                newType = 'email';
                showCountryCode = false;
            }
            // If digits only => phone
            else if (/^\d+$/.test(val)) {
                newType = 'tel';
                showCountryCode = true;
            }

            // Apply changes only if necessary
            if (currentType !== newType) {
                input.attr('type', newType);
                input.val(val); // Restore value to prevent data loss during type switch

                // Update icon based on input type
                var iconElement = $('#identity-icon');
                if (newType === 'email') {
                    iconElement.removeClass('fa-user fa-phone').addClass('fa-envelope');
                } else if (newType === 'tel') {
                    iconElement.removeClass('fa-user fa-envelope').addClass('fa-phone');
                } else {
                    iconElement.removeClass('fa-envelope fa-phone').addClass('fa-user');
                }
            }

            if (showCountryCode) {
                $('#step_1 .country_code').show();
            } else {
                $('#step_1 .country_code').hide();
            }
        });

        // Prevent any interference with single country code display
        if (isSingleCountryCode) {
            // Ensure the country code element is an input, not a select
            var countryCodeElement = $('#country_code');
            if (countryCodeElement.length > 0 && countryCodeElement.prop('tagName') !== 'INPUT') {
                // Force it to be an input if it's not
                var currentValue = countryCodeElement.val();
                countryCodeElement.replaceWith('<input type="text" class="form-control" name="country_code" id="country_code" value="' + currentValue + '" readonly>');
            }
        }

        // Default country code for step 2 (used when email OTP is used and no phone country was selected)
        window.registerDefaultCallingCode = "<?= addslashes($default_calling_code) ?>";

        // Initialize Select2 for the Step 2 country code dropdown if it exists as a select
        if ($('#store_country_code').is('select')) {
            $('#store_country_code').select2({
                minimumResultsForSearch: 0, // allow search
                width: '100%'
            });
        }

        // Terms & Conditions checkbox logic
        $('#acceptTerms').on('change', function () {
            $('#btn-register').prop('disabled', !this.checked);
        });

        // Add back button logic for OTP step
        $('#btn-back-to-send').on('click', function () {
            $('.otp_show').hide();
            $('#send').show().addClass('fade-in-section');
            $('.step').html(1);
        });

        // Toggle password visibility
        $('.toggle-password').on('click', function () {
            $(this).toggleClass('fa-eye-slash fa-eye');
            var input = $(this).closest('.custom-input-group').find('input[type="password"], input[type="text"]').first();
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
            } else {
                input.attr('type', 'password');
            }
        });

        // Inline password match validation
        function validatePasswordMatch() {
            var password = $('#password').val();
            var confirmPassword = $('#password_confirm').val();

            if (confirmPassword.length > 0) {
                if (password !== confirmPassword) {
                    $('#password-match-msg').show();
                    $('#password_confirm').closest('.custom-input-group').css('border-color', '#dc3545');
                } else {
                    $('#password-match-msg').hide();
                    $('#password_confirm').closest('.custom-input-group').css('border-color', '#28a745'); // Match green
                }
            } else {
                $('#password-match-msg').hide();
                $('#password_confirm').closest('.custom-input-group').css('border-color', '#ced4da'); // Default border
            }
        }

        $('#password, #password_confirm').on('input', validatePasswordMatch);

        // Observe hidden .step element for changes made by existing custom.js
        const stepObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                let currentStep = parseInt($('.step').text());
                if (!isNaN(currentStep)) {
                    // Add fade-in animation to newly shown steps
                    if (currentStep === 2) {
                        $('.otp_show').addClass('fade-in-section');
                    } else if (currentStep === 3) {
                        $('#step_2').addClass('fade-in-section');
                    }
                }
            });
        });

        const stepElement = document.querySelector('.step');
        if (stepElement) {
            stepObserver.observe(stepElement, { childList: true, subtree: true, characterData: true });
        }
    });
</script>