<div class="main-content">
    <section class="section" id="pill-login_settings" role="tabpanel">
        <div class="section-header mt-2">
            <h1><?= labels('authentication_settings', 'Authentication Settings') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('admin/settings/authentication-settings') ?>"><?= labels('authentication_settings', "Authentication Settings") ?></a></div>
            </div>
        </div>

        <?= form_open_multipart(base_url('admin/settings/authentication-settings')) ?>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">

                        <!-- ================= LOGIN METHODS ================= -->
                        <div class="mb-5">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-sign-in-alt text-primary mr-3 fa-lg"></i>
                                <h3 class="mb-0 font-weight-bold"><?= labels('login_methods', 'Login Methods') ?></h3>
                            </div>
                            <p class="text-muted mb-4">
                                <?= labels('login_methods_description', 'Choose how your customers and providers can access their accounts. At least one method must be enabled.') ?>
                            </p>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="card border-2 h-100 setting-card" id="phone-login-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-mobile-alt text-primary mr-2"></i>
                                                    <strong><?= labels('phone_login', 'Phone Login') ?></strong>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="phone_authentication_enabled"
                                                        name="phone_authentication_enabled"
                                                        <?= (!empty($login_settings['phone_authentication_enabled'])) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="phone_authentication_enabled"></label>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('phone_login_description', 'Allow users to sign in using their phone number with OTP verification.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <div class="card border-2 h-100 setting-card" id="email-login-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-envelope text-primary mr-2"></i>
                                                    <strong><?= labels('email_login', 'Email Login') ?></strong>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="email_authentication_enabled"
                                                        name="email_authentication_enabled"
                                                        <?= (!empty($login_settings['email_authentication_enabled'])) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="email_authentication_enabled"></label>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('email_login_description', 'Allow users to sign in using their email address with OTP verification.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <div class="card border-2 h-100 setting-card" id="social-login-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center flex-wrap">
                                                    <i class="fab fa-google text-primary mr-2"></i>
                                                    <strong class="mr-1"><?= labels('social_login', 'Social Login') ?></strong>
                                                    <span class="badge badge-info badge-pill"><?= labels('apple_google_login', 'Google & Apple') ?></span>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="social_authentication_enabled"
                                                        name="social_authentication_enabled"
                                                        <?= (!empty($login_settings['social_authentication_enabled'])) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="social_authentication_enabled"></label>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('social_login_description', 'Enable quick sign-in through Google and Apple accounts.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- ================= OTP DELIVERY ================= -->
                        <div class="mb-5" id="otp_section">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-shield-alt text-primary mr-3 fa-lg"></i>
                                <h3 class="mb-0 font-weight-bold"><?= labels('otp_delivery_method', 'OTP Delivery Method') ?></h3>
                            </div>
                            <p class="text-muted mb-4">
                                <?= labels('otp_delivery_description', 'Select how one-time passwords (OTP) should be sent to users when they log in with phone number. Only one method can be active at a time.') ?>
                            </p>

                            <div class="alert-info-box">
                                <i class="fas fa-info-circle"></i>
                                <div class="alert-info-box-content">
                                    <div class="alert-info-box-title"><?= labels('important', 'Important') ?></div>
                                    <p class="alert-info-box-text">
                                        <?= labels('otp_delivery_info', 'OTP delivery is required when Phone Login is enabled. Choose either SMS Gateway or Firebase for sending verification codes.') ?>
                                    </p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card border-2 h-100 setting-card" id="sms-gateway-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-sms text-primary mr-2"></i>
                                                    <strong><?= labels('sms_gateway', 'SMS Gateway') ?></strong>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="otp_sms_status"
                                                        name="otp_sms_status"
                                                        <?= (!empty($login_settings['otp_sms_status'])) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="otp_sms_status"></label>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('sms_gateway_description', 'Send OTP codes via traditional SMS gateway. Reliable and works with all mobile carriers.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="card border-2 h-100 setting-card" id="firebase-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fab fa-google text-primary mr-2"></i>
                                                    <strong><?= labels('firebase', 'Firebase') ?></strong>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="otp_firebase_status"
                                                        name="otp_firebase_status"
                                                        <?= (!empty($login_settings['otp_firebase_status'])) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="otp_firebase_status"></label>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('firebase_description', 'Use Firebase for OTP delivery. Provides automatic verification and spam protection.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- ================= PASSWORD LOGIN ================= -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-key text-primary mr-3 fa-lg"></i>
                                <h3 class="mb-0 font-weight-bold"><?= labels('additional_security_options', 'Additional Security Options') ?></h3>
                            </div>
                            <p class="text-muted mb-4">
                                <?= labels('additional_security_description', 'Configure optional security features to enhance user experience.') ?>
                            </p>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card border-2 h-100 setting-card" id="password-login-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-lock text-primary mr-2"></i>
                                                    <strong><?= labels('customer_password_login', 'Customer Password Login') ?></strong>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox"
                                                        class="custom-control-input"
                                                        id="customer_password_login_enabled"
                                                        name="customer_password_login_enabled"
                                                        <?= (!empty($login_settings['customer_password_login_enabled'])) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="customer_password_login_enabled"></label>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('phone_password_status_description', 'After initial OTP verification, users can set a password for faster future logins without requiring OTP each time.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- ================= PASSWORD REQUIREMENTS ================= -->
                        <div class="mb-4" id="password_requirements_section">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-lock-open text-primary mr-3 fa-lg"></i>
                                <h3 class="mb-0 font-weight-bold"><?= labels('password_requirements', 'Password Requirements') ?></h3>
                            </div>
                            <p class="text-muted mb-3">
                                <?= labels('password_requirements_description', 'Set minimum length and character rules for user passwords.') ?>
                            </p>

                            <!-- Note when minimum length is 0: no restrictions allowed -->
                            <div class="alert-info-box mb-3" id="password_limit_zero_note" style="display: none;">
                                <i class="fas fa-info-circle"></i>
                                <div class="alert-info-box-content">
                                    <div class="alert-info-box-title"><?= labels('important', 'Important') ?></div>
                                    <p class="alert-info-box-text"><?= labels('password_limit_zero_note', 'When minimum length is 0, no length or character restrictions apply. Character requirement switches are disabled.') ?></p>
                                </div>
                            </div>

                            <!-- Note when minimum length is 1: only one restriction allowed -->
                            <div class="alert-info-box mb-3" id="password_limit_one_note" style="display: none;">
                                <i class="fas fa-info-circle"></i>
                                <div class="alert-info-box-content">
                                    <div class="alert-info-box-title"><?= labels('important', 'Important') ?></div>
                                    <p class="alert-info-box-text"><?= labels('password_limit_one_note', 'With minimum length 1, only one character requirement can be enabled (a 1-character password cannot satisfy multiple rules).') ?></p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="minimum_password_length" class="font-weight-bold"><?= labels('minimum_password_length', 'Minimum password length') ?></label>
                                    <input type="number" class="form-control" id="minimum_password_length" name="minimum_password_length"
                                        min="0" max="128" step="1" value="<?= isset($login_settings['minimum_password_length']) ? esc($login_settings['minimum_password_length']) : '8' ?>"
                                        placeholder="8">
                                    <small class="text-muted"><?= labels('minimum_password_length_hint', '0 = no length or character restrictions') ?></small>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <span class="font-weight-bold d-block mb-2"><?= labels('character_requirements', 'Character requirements') ?></span>
                                </div>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input password-req-switch" id="require_at_least_one_uppercase" name="require_at_least_one_uppercase" value="1"
                                            <?= (!empty($login_settings['require_at_least_one_uppercase'])) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="require_at_least_one_uppercase"><?= labels('require_at_least_one_uppercase', 'At least one uppercase letter') ?></label>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input password-req-switch" id="require_at_least_one_lowercase" name="require_at_least_one_lowercase" value="1"
                                            <?= (!empty($login_settings['require_at_least_one_lowercase'])) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="require_at_least_one_lowercase"><?= labels('require_at_least_one_lowercase', 'At least one lowercase letter') ?></label>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input password-req-switch" id="require_at_least_one_number" name="require_at_least_one_number" value="1"
                                            <?= (!empty($login_settings['require_at_least_one_number'])) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="require_at_least_one_number"><?= labels('require_at_least_one_number', 'At least one number') ?></label>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input password-req-switch" id="require_at_least_one_special_character" name="require_at_least_one_special_character" value="1"
                                            <?= (!empty($login_settings['require_at_least_one_special_character'])) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="require_at_least_one_special_character"><?= labels('require_at_least_one_special_character', 'At least one special character') ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-lg bg-new-primary">
                    <i class="fas fa-save mr-2"></i>
                    <?= labels('save_changes', 'Save Changes') ?>
                </button>
            </div>
        </div>

        <?= form_close() ?>
    </section>
</div>

<style>
    /* Minimal custom styles for active state and hover effects */
    .setting-card {
        transition: all 0.3s ease;
        background-color: #f8f9fa;
        cursor: pointer;
    }

    .setting-card:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08) !important;
    }

    .setting-card.active {
        border-color: var(--primary, #007bff) !important;
        background-color: #e7f3ff;
    }

    .info-badge {
        display: inline-block;
        background: #e6f7ff;
        color: #0066cc;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 500;
        margin-left: 6px;
    }

    .alert-info-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: start;
        gap: 12px;
    }

    .alert-info-box i {
        color: #ffc107;
        font-size: 20px;
        margin-top: 2px;
    }

    .alert-info-box-content {
        flex: 1;
    }

    .alert-info-box-title {
        font-weight: 600;
        color: #856404;
        margin-bottom: 4px;
    }

    .alert-info-box-text {
        font-size: 14px;
        color: #856404;
        margin: 0;
    }
</style>

<script>
    $(document).ready(function() {
        // Initialize settings from controller data
        const loginSettings = <?= json_encode($login_settings ?? []) ?>;

        const phoneLogin = $('#phone_authentication_enabled');
        const emailLogin = $('#email_authentication_enabled');
        const socialLogin = $('#social_authentication_enabled');
        const otpSms = $('#otp_sms_status');
        const otpFirebase = $('#otp_firebase_status');
        const passwordLogin = $('#customer_password_login_enabled');

        /* -------------------------
           HELPERS
        -------------------------- */

        // Update card active state
        function updateCardState(cardId, isActive) {
            if (isActive) {
                $(cardId).addClass('active');
            } else {
                $(cardId).removeClass('active');
            }
        }

        // Ensure at least one login method is enabled
        function ensureOneLogin(source) {
            if (!phoneLogin.is(':checked') && !emailLogin.is(':checked') && !socialLogin.is(':checked')) {
                source.prop('checked', true);
                if (typeof toastr !== 'undefined') {
                    toastr.warning('At least one login method must be enabled.', 'Warning');
                } else {
                    alert('At least one login method must be enabled.');
                }
            }
        }

        // Ensure at least one OTP delivery method is enabled when phone login is active
        function ensureOneOtp() {
            if (phoneLogin.is(':checked') && !otpSms.is(':checked') && !otpFirebase.is(':checked')) {
                otpFirebase.prop('checked', true);
                updateCardState('#firebase-card', true);
            }
        }

        // Show/hide OTP section based on phone login status
        function handleOtpVisibility() {
            if (phoneLogin.is(':checked')) {
                $('#otp_section').slideDown(300);
                ensureOneOtp();
            } else {
                $('#otp_section').slideUp(300);
                otpSms.prop('checked', false);
                otpFirebase.prop('checked', false);
                updateCardState('#sms-gateway-card', false);
                updateCardState('#firebase-card', false);
            }
        }

        /* -------------------------
           EVENTS
        -------------------------- */

        // Phone login change handler
        phoneLogin.on('change', function() {
            updateCardState('#phone-login-card', this.checked);
            ensureOneLogin($(this));
            handleOtpVisibility();
        });

        // Email login change handler
        emailLogin.on('change', function() {
            updateCardState('#email-login-card', this.checked);
            ensureOneLogin($(this));
        });

        // Social login change handler
        socialLogin.on('change', function() {
            updateCardState('#social-login-card', this.checked);
            ensureOneLogin($(this));
        });

        // OTP SMS change handler - mutually exclusive with Firebase
        otpSms.on('change', function() {
            updateCardState('#sms-gateway-card', this.checked);
            if ($(this).is(':checked')) {
                otpFirebase.prop('checked', false);
                updateCardState('#firebase-card', false);
            } else {
                ensureOneOtp();
            }
        });

        // OTP Firebase change handler - mutually exclusive with SMS
        otpFirebase.on('change', function() {
            updateCardState('#firebase-card', this.checked);
            if ($(this).is(':checked')) {
                otpSms.prop('checked', false);
                updateCardState('#sms-gateway-card', false);
            } else {
                ensureOneOtp();
            }
        });

        // Password login change handler
        passwordLogin.on('change', function() {
            updateCardState('#password-login-card', this.checked);
        });

        /* -------------------------
           PASSWORD REQUIREMENTS (limit-based restriction logic)
        -------------------------- */
        const $minLength = $('#minimum_password_length');
        const $limitZeroNote = $('#password_limit_zero_note');
        const $limitOneNote = $('#password_limit_one_note');
        const $switches = $('.password-req-switch');

        function getPasswordLimit() {
            const val = parseInt($minLength.val(), 10);
            return isNaN(val) || val < 0 ? 0 : val;
        }

        function countCheckedSwitches() {
            return $switches.filter(':checked').length;
        }

        // Limit 0: no restrictions; disable and uncheck all. Limit 1: only one switch on. Limit 2: max 2 on, etc.
        function applyPasswordLimitRules() {
            const limit = getPasswordLimit();
            const checked = countCheckedSwitches();

            if (limit === 0) {
                $limitZeroNote.show();
                $limitOneNote.hide();
                $switches.prop('disabled', true).prop('checked', false);
                return;
            }
            $limitZeroNote.hide();
            $switches.prop('disabled', false);

            if (limit === 1) {
                $limitOneNote.show();
                if (checked > 1) {
                    $switches.filter(':checked').slice(1).prop('checked', false);
                }
                return;
            }
            $limitOneNote.hide();

            if (limit >= 2 && limit < 4 && checked > limit) {
                $switches.filter(':checked').slice(limit).prop('checked', false);
            }
        }

        // When user turns on a switch and that would exceed current limit, increase limit instead (so restriction is achievable)
        function enforceLimitOrIncreaseLength() {
            const limit = getPasswordLimit();
            const checked = countCheckedSwitches();
            if (limit >= 4) return;
            if (checked <= limit) return;
            $minLength.val(checked);
            applyPasswordLimitRules();
        }

        $minLength.on('input change', function() {
            applyPasswordLimitRules();
        });

        $switches.on('change', function() {
            const limit = getPasswordLimit();
            if (limit === 0) return;
            if (limit === 1) {
                if ($(this).is(':checked') && countCheckedSwitches() > 1) {
                    $switches.not(this).prop('checked', false);
                }
                return;
            }
            if (limit >= 2 && limit < 4 && $(this).is(':checked') && countCheckedSwitches() > limit) {
                $minLength.val(countCheckedSwitches());
            }
            applyPasswordLimitRules();
        });

        applyPasswordLimitRules();

        /* -------------------------
           INITIALIZE
        -------------------------- */

        // Initialize card states based on database values
        updateCardState('#phone-login-card', phoneLogin.is(':checked'));
        updateCardState('#email-login-card', emailLogin.is(':checked'));
        updateCardState('#social-login-card', socialLogin.is(':checked'));
        updateCardState('#sms-gateway-card', otpSms.is(':checked'));
        updateCardState('#firebase-card', otpFirebase.is(':checked'));
        updateCardState('#password-login-card', passwordLogin.is(':checked'));
        handleOtpVisibility();

        // Make the entire setting card clickable
        $(document).on('click', '.setting-card', function(e) {
            // If the click was directly on the switch or its label, let the browser handle it
            if ($(e.target).closest('.custom-control').length) {
                return;
            }
            var $checkbox = $(this).find('input[type="checkbox"].custom-control-input');
            if ($checkbox.length) {
                $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
            }
        });
    });
</script>