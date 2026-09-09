<?php
$data = get_settings('general_settings', true);
isset($data['primary_color']) && $data['primary_color'] != "" ? $primary_color = $data['primary_color'] : $primary_color = '#05a6e8';
isset($data['secondary_color']) && $data['secondary_color'] != "" ? $secondary_color = $data['secondary_color'] : $secondary_color = '#003e64';
isset($data['primary_shadow']) && $data['primary_shadow'] != "" ? $primary_shadow = $data['primary_shadow'] : $primary_shadow = '#05A6E8';
$authentication_mode = $data['authentication_mode'];

$loginSettings = get_settings('login_settings', true);
$phone_authentication_enabled = (int) ($loginSettings['phone_authentication_enabled'] ?? 1);
$email_authentication_enabled = (int) ($loginSettings['email_authentication_enabled'] ?? 0);

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

// Detect smart login mode - similar to login.php
// Check if we're on partner forgot password page to determine authentication options
$smart_login = false;

// Get query parameter to check user type
$request = \Config\Services::request();
$userType = $request->getGet('userType');

if ($userType == 'partner') {
    // Check if both phone and email authentication are enabled
    if ($phone_authentication_enabled && $email_authentication_enabled) {
        $smart_login = true;
    } elseif ($email_authentication_enabled) {
        $smart_login = 'email';
    } elseif ($phone_authentication_enabled) {
        $smart_login = 'phone';
    }
}

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
<!-- Password strength indicator: shared CSS and JS (same as register.php) -->
<link rel="stylesheet" href="<?= base_url('public/frontend/retro/css/password-strength.css') ?>">
<script src="<?= base_url('public/frontend/retro/js/password-strength.js') ?>"></script>
<div class="auth " style="overflow: hidden; background-image: url('<?= $login_image_url ?>');">
    <div class="join_us_as_provider">
        <section class="section">
            <section data-aos="fade-up">

                <div class="d-flex justify-content-<?= $is_rtl == 1 ? 'start' : 'end' ?> m-3 row">
                    <div class="col-12 col-md-4 col-sm-11 me-md-5 mt-5">
                        <?php
                        $data = get_settings('general_settings', true);
                        ?>
                        <div class="">
                            <div id="" class='alert'><?php echo $message; ?></div>
                        </div>
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
                                            <label for="number" class="form-label" id="identityLabel">
                                                <?php
                                                if ($userType !== 'partner') {
                                                    echo labels('phone_number', 'Phone Number');
                                                } elseif ($smart_login === 'email') {
                                                    echo labels('email', 'Email');
                                                } elseif ($smart_login === true || $smart_login === '1' || $smart_login === 1) {
                                                    echo labels('email_or_phone', 'Email or Phone');
                                                } else {
                                                    echo labels('phone_number', 'Phone Number');
                                                }
                                                ?>
                                            </label>

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
                                                    <input id="number" class="form-control" type="text" name="number"
                                                        placeholder="<?= labels('enter_mobile_number', 'Enter Mobile Number') ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3 re_captcha">
                                            <div id="rec"></div>
                                        </div>
                                        <div class="mb-3">
                                            <button type="button" class="btn bg-primary text-white btn-lg w-100 mt-2"
                                                id="sender_forgot_password"><?= labels('submit', 'Submit') ?></button>
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
                                    <?= form_open('/auth/reset_password_otp', ['id' => 'forgot_password']); ?>

                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <input type="hidden" id="phone" class="form-control" name='phone'
                                                placeholder="Mobile Number" required min="0" readOnly />
                                            <input id="store_country_code" class="form-control" type="hidden"
                                                name="store_country_code">

                                            <div class="mb-3">
                                                <label for='password'
                                                    class="form-label"><?= labels('password', 'Password') ?></label>
                                                <div class="custom-input-group">
                                                    <i class="fa fa-lock input-icon-left"></i>
                                                    <input type="password" id="password" class="form-control"
                                                        name='password'
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

                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for='password_confirm'
                                                    class="form-label"><?= labels('confirm_password', 'Confirm Password') ?></label>
                                                <div class="custom-input-group">
                                                    <i class="fa fa-lock input-icon-left"></i>
                                                    <input type="password" id="password_confirm" class="form-control"
                                                        name='password_confirm'
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
                                        <input type="hidden" id="userType" name="userType" value="">

                                    </div>

                                    <div class="mb-3">
                                        <button type="submit" class="btn bg-primary text-white btn-lg w-100 mt-2">
                                            <?= labels('submit', 'Submit') ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md d-flex justify-content-center">
                                    <?= labels('back_to', 'Back To') ?> &nbsp; <a
                                        href="<?= base_url($userType === 'handyman' ? 'handyman/login' : ($userType === 'admin' ? 'admin/login' : 'partner/login')) ?>"
                                        class=""><b><?= labels('login', 'Login') ?></b></a><br>
                                </div>
                                <!-- <php
                                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                                    ?>
                                    <div class="col-sm-12 mt-3">
                                        <div class="alert alert-warning mb-0">
                                            <b><?= labels('note', 'Note') ?>:</b>
                                            <?= labels('cannot_register_close_codecanyon_frame', 'If you cannot Register here, please close the codecanyon frame by clicking on') ?>
                                            <b>x <?= labels('remove_frame', 'Remove Frame') ?></b>
                                            <?= labels('button_top_right_corner', 'button from top right corner on the page or') ?>
                                            <a href="https://edemand.erestro.me/auth/create_user" target="_blank">&gt;&gt;
                                                <?= labels('click_here', 'Click here') ?> &lt;&lt;</a>
                                        </div>
                                    </div>
                                <php } ?> -->
                            </div>
                        </div>
                    </div>
            </section>

        </section>
    </div>
</div>
<!-- Start Signup Section-->

<!-- Signup Section End -->
<script>
    $(document).ready(function () {
        // Password requirements validation on submit (Authentication Settings)
        $('#forgot_password').on('submit', function (e) {
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

        // Function to get query parameters from the URL
        function getQueryParam(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }

        // Get the value of the "partner" query parameter
        const userType = getQueryParam("userType");
        if (userType === "partner") {
            $('#userType').val("partner");
        } else if (userType === "handyman") {
            $('#userType').val("handyman");
        } else {
            $('#userType').val("admin");
        }

        // Handle single country code display logic
        var isSingleCountryCode = <?= $single_country_code ? 'true' : 'false' ?>;
        var currentURL = window.location.href;
        var isAdminLogin = (window.location.href.indexOf('/admin/login') !== -1);
        var isPartnerLogin = (window.location.href.indexOf('/partner/login') !== -1);
        var smartLogin = "<?= $smart_login ?>";

        // Smart login setup for partner forgot password
        if (userType === "partner") {
            if (smartLogin === 'email') {
                // Email only mode
                $('#number').attr('type', 'email').attr('placeholder', '<?= labels('enter_email', 'Enter your email') ?>');
                $('.country_code').hide();
                $('#identity-icon').removeClass('fa-user fa-phone').addClass('fa-envelope');
            } else if (smartLogin === 'phone') {
                // Phone only mode
                $('#number').attr('type', 'tel').attr('placeholder', '<?= labels('enter_mobile_number', 'Enter Mobile Number') ?>');
                $('.country_code').show();
                $('#identity-icon').removeClass('fa-user fa-envelope').addClass('fa-phone');
            } else if (smartLogin === '1') {
                // Both modes - start in neutral mode
                $('#number').attr('type', 'text').attr('placeholder', '<?= labels('email_or_phone', 'Email or Phone number') ?>');
                $('.country_code').hide();
                $('#identity-icon').removeClass('fa-envelope fa-phone').addClass('fa-user');
            }
        }

        // Dynamic input detection for smart login (when both email and phone are enabled)
        $('#number').on('input', function () {
            if (smartLogin !== '1') return;

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
            // Check for letters or @ symbol - definitely an email
            else if (val.includes('@') || /[a-zA-Z]/.test(val)) {
                newType = 'email';
                showCountryCode = false;
            }
            // If it's only digits - treat as phone (using 'tel' for better UX)
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
                $('.country_code').show();
            } else {
                $('.country_code').hide();
            }
        });

        // Debug removed for production

        if (isAdminLogin) {
            $('.country_code').hide();
            $('#country_code').val("");
        } else if (isPartnerLogin) {
            if (!isSingleCountryCode) {
                // Only set dropdown value if it's not a single country code (dropdown exists)
                var selectedCountryCode = "<?php echo $default_calling_code; ?>";
                $('#country_code').val(selectedCountryCode).trigger('change');
            }
        }

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

        // Add back button logic for OTP step
        $('#btn-back-to-send').on('click', function () {
            $('.otp_show').hide();
            $('#send').show().addClass('fade-in-section');
            $('.step').html(1);
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