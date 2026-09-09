<?= form_open('auth/login', ['method' => 'post']); ?>
<input type="hidden" name="login_context" value="admin">

<div class="mb-3">
    <label for="identity" class="form-label"><?= labels('phone_number', 'Phone Number') ?></label>
    <div class="phone-input-group">
        <i class="fa fa-phone input-icon-left"></i>
        <input id="identity" type="number" min="0"
            class="form-control"
            name="identity" tabindex="1"
            placeholder="<?= labels('enter_registered_phone_number', 'Please enter registered phone number') ?>"
            required autofocus>
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

<div class="mb-3">
    <button type="submit" class="btn bg-primary text-white btn-lg w-100 fs-6" tabindex="4">
        <?= labels('login_to_portal', 'Log in to Portal') ?> <i class="fa-solid fa-arrow-right ms-2"></i>
    </button>
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
        $('#forgot-password-link').attr('href', '<?= site_url(route_to('auth-forgot-password')) ?>?userType=admin');
    });
</script>
