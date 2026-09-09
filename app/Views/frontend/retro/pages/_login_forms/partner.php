<?php
$smart_login = false;
if ($phone_authentication_enabled && $email_authentication_enabled) {
    $smart_login = true;
} elseif ($email_authentication_enabled) {
    $smart_login = 'email';
} elseif ($phone_authentication_enabled) {
    $smart_login = 'phone';
}
?>

<?= form_open('auth/login', ['method' => 'post']); ?>
<input type="hidden" name="login_context" value="partner">
<input type="hidden" name="login_type" id="login_type"
    value="<?= ($smart_login === 'phone') ? 'phone' : (($smart_login === 'email') ? 'email' : 'phone') ?>">

<div class="mb-3">
    <label for="identity" class="form-label">
        <?= labels('email_or_phone', 'Email or Phone number') ?>
    </label>
    <div class="phone-input-group">
        <i id="identity-icon" class="fa fa-user input-icon-left"></i>
        <div class="country_code d-flex align-items-center flex-shrink-0 d-none">
            <?php if ($single_country_code): ?>
                <input type="text" class="form-control"
                    name="country_code" id="country_code"
                    value="<?= esc($single_country_data['calling_code'] ?? $default_calling_code) ?>" readonly>
            <?php else: ?>
                <select class="form-control select2" name="country_code" id="country_code">
                    <?php foreach ($country_codes as $country_code): ?>
                        <?php
                        $code = $country_code['calling_code'];
                        $selected = ($default_calling_code == $code) ? 'selected' : '';
                        echo "<option $selected value='$code'>$code</option>";
                        ?>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <div id="identityInputDiv" class="d-flex align-items-center flex-fill">
            <input id="identity" type="text"
                class="form-control"
                name="identity" tabindex="1"
                placeholder="<?= labels('enter_email_or_phone', 'Enter email or phone') ?>"
                required autofocus>
        </div>
    </div>
    <div class="invalid-feedback">
        <?= labels('please_fill_in_your', 'Please fill in your') ?>
        <?= labels('phone_number', 'Phone Number') ?>
    </div>
</div>

<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <label for="password" class="form-label mb-0"><?= labels('password', 'Password') ?></label>
        <a href="#" class="text-small text-new-primary text-decoration-none fw-500"
            id="forgot-password-link" style="font-size: 0.85rem;">
            <?= labels('forgot_password', 'Forgot Password?') ?>
        </a>
    </div>
    <div class="custom-password-group mb-2">
        <i class="fa fa-lock input-icon-left"></i>
        <input id="password" type="password"
            class="form-control"
            name="password" tabindex="2" required
            placeholder="<?= labels('enter_your_password', 'Enter your password') ?>">
        <i class="fa-sharp fa-solid fa-eye-slash toggle-password input-icon-right"></i>
    </div>
</div>

<div class="row mb-3">
    <div class="col-6">
        <div class="form-check">
            <input type="checkbox" id="remember" name="remember" value="1" class="form-check-input" />
            <label class="form-check-label" for="remember"><?= labels('remember_me', 'Remember me') ?></label>
        </div>
    </div>
</div>

<div class="small text-muted text-center mb-3 mt-0">
    <?= labels('by_continuing_you_agree', 'By continuing, you agree to our'); ?>
    <a href="<?= base_url('admin/settings/legal-pages/preview/terms_conditions') ?>"
        class="text-decoration-none" style="color: var(--primary-color);">
        <?= labels('terms_and_conditions', 'Terms & Conditions'); ?>
    </a> &
    <a href="<?= base_url('admin/settings/legal-pages/preview/privacy_policy') ?>"
        class="text-decoration-none" style="color: var(--primary-color);">
        <?= labels('privacy_policy', 'Privacy Policy'); ?>
    </a>
</div>

<div class="mb-3">
    <button type="submit" class="btn bg-primary text-white btn-lg w-100 fs-6" tabindex="4">
        <?= labels('login_to_portal', 'Log in to Portal') ?> <i class="fa-solid fa-arrow-right ms-2"></i>
    </button>
</div>

<div class="text-muted text-center mt-4 mb-4">
    <?= labels('dont_have_account', 'Don\'t have an account ?') ?>
    <a class="text-new-primary" href="<?= base_url('auth/create_user') ?>">
        <b><?= labels('join_us_as_provider', 'Join us as provider') ?></b>
    </a>
</div>

<?php if (isset($_SESSION['logout_msg'])): ?>
    <div class="alert alert-primary" id="logout_msg"><?= $_SESSION['logout_msg'] ?></div>
<?php endif; ?>
<?php if (isset($message) && !empty($message)): ?>
    <div class="alert alert-danger" id="logout_msg">
        <div class="mt-2"><?= $message ?></div>
    </div>
<?php endif; ?>

<?= form_close() ?>

<script>
    $(document).ready(function () {
        var isSingleCountryCode = <?= $single_country_code ? 'true' : 'false' ?>;
        var smartLogin = "<?= $smart_login ?>";

        $('#forgot-password-link').attr('href', '<?= site_url(route_to('auth-forgot-password')) ?>?userType=partner');

        if (smartLogin === 'email') {
            $('#identity').attr('type', 'email').attr('placeholder', 'Enter your email');
            $('#login_type').val('email');
        } else if (smartLogin === 'phone') {
            $('#identity').attr('type', 'tel').attr('placeholder', 'Enter phone number');
            $('#login_type').val('phone');
        } else if (smartLogin === '1') {
            $('#identity').attr('type', 'text').attr('placeholder', 'Email or phone');
            $('#login_type').val('phone');
        }

        if (!isSingleCountryCode && $('#country_code').is('select')) {
            $('#country_code').select2({
                minimumResultsForSearch: 0,
                width: '100%'
            });
            var selectedCountryCode = "<?= $default_calling_code ?>";
            $('#country_code').val(selectedCountryCode).trigger('change');
        }

        if (isSingleCountryCode) {
            var countryCodeElement = $('#country_code');
            if (countryCodeElement.length > 0 && countryCodeElement.prop('tagName') !== 'INPUT') {
                var currentValue = countryCodeElement.val();
                countryCodeElement.replaceWith('<input type="text" class="form-control" name="country_code" id="country_code" value="' + currentValue + '" readonly>');
            }
        }

        $('#identity').on('input', function () {
            if (smartLogin !== '1' && smartLogin !== 'phone') return;

            var input = $(this);
            var val = input.val();
            var showCountryCode = false;

            if (smartLogin === 'phone') {
                showCountryCode = val.length > 0;
            } else {
                var currentType = input.attr('type');
                var newType = 'text';

                if (!val || val.length === 0) {
                    newType = 'text';
                    showCountryCode = false;
                } else if (val.includes('@') || /[a-zA-Z]/.test(val)) {
                    newType = 'email';
                    showCountryCode = false;
                } else if (/^\d+$/.test(val)) {
                    newType = 'tel';
                    showCountryCode = true;
                }

                if (currentType !== newType) {
                    input.attr('type', newType);
                    input.val(val);

                    if (newType === 'email') {
                        $('#identity-icon').removeClass('fa-phone fa-user').addClass('fa-envelope');
                    } else if (newType === 'tel') {
                        $('#identity-icon').removeClass('fa-user fa-envelope').addClass('fa-phone');
                    } else {
                        $('#identity-icon').removeClass('fa-phone fa-envelope').addClass('fa-user');
                    }

                    try {
                        var el = input[0];
                        var pos = val.length;
                        el.setSelectionRange(pos, pos);
                    } catch (e) {}
                }

                $('#login_type').val(newType === 'email' ? 'email' : 'phone');
            }

            if (showCountryCode) {
                $('.country_code').removeClass('d-none');
            } else {
                $('.country_code').addClass('d-none');
            }
        });

        $('form').on('submit', function () {
            var val = $('#identity').val() || '';
            if (val.indexOf('@') !== -1 || /[a-zA-Z]/.test(val)) {
                $('#login_type').val('email');
            } else {
                $('#login_type').val('phone');
            }
        });

        // Handle autofill from URL param
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autofill') === 'provider') {
            copy_provider_cred();
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
    });
</script>
