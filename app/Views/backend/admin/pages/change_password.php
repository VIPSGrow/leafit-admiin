<?php
helper('function');
$password_rules = get_password_rules();
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
<div class="main-content profile-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('change_password', 'Change Password') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="<?= base_url('/admin/dashboard') ?>"><?= labels('dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('admin/profile') ?>"><?= labels('profile', 'Profile') ?></a></div>
                <div class="breadcrumb-item active"><?= labels('change_password', 'Change Password') ?></div>
            </div>
        </div>

        <div class="section-body">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-10 col-sm-12">
                    <div class="card">
                        <form id="change_password_form" action="<?= base_url('admin/profile/password/update') ?>" method="post" accept-charset="utf-8" class="form-submit-event">

                            <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                                <div class="toggleButttonPostition"><?= labels('change_password', 'Change Password') ?></div>
                            </div>

                            <div class="card-body">
                                <div class="form-group">
                                    <label for="old_password" class="required"><?= labels('old_password', 'Old Password') ?></label>
                                    <div class="position-relative">
                                        <input id="old_password" class="form-control" type="password" name="old_password" required style="padding-right: 2.5rem;">
                                        <button type="button" class="btn btn-link position-absolute toggle-pw-btn" data-target="old_password"
                                            style="right: 0; top: 50%; transform: translateY(-50%); border: none; background: none; padding: 0.5rem; cursor: pointer; z-index: 10; color: #6c757d;"
                                            title="<?= labels('show_password', 'Show Password') ?>">
                                            <i class="fas fa-eye-slash"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback d-block" id="old_password_error" style="display:none !important;"></div>
                                </div>

                                <div class="form-group">
                                    <label for="password" class="required"><?= labels('new_password', 'New Password') ?></label>
                                    <div class="position-relative">
                                        <input id="password" class="form-control" type="password" name="new_password" required style="padding-right: 2.5rem;">
                                        <button type="button" class="btn btn-link position-absolute toggle-pw-btn" data-target="password"
                                            style="right: 0; top: 50%; transform: translateY(-50%); border: none; background: none; padding: 0.5rem; cursor: pointer; z-index: 10; color: #6c757d;"
                                            title="<?= labels('show_password', 'Show Password') ?>">
                                            <i class="fas fa-eye-slash"></i>
                                        </button>
                                    </div>
                                    <div id="password-strength-indicator" class="mt-2 small" <?= ($password_rules['min_length'] === 0) ? 'style="display:none;"' : '' ?>>
                                        <?php if ($password_rules['min_length'] > 0) { ?>
                                            <div class="password-rule" id="rule-length" data-rule="length">
                                                <span class="rule-icon" aria-hidden="true">○</span>
                                                <span class="rule-label"><?= sprintf(labels('password_strength_min_length_n', 'At least %s characters'), (int) $password_rules['min_length']) ?></span>
                                            </div>
                                        <?php } ?>
                                        <?php if (!empty($password_rules['require_number'])) { ?>
                                            <div class="password-rule" id="rule-number" data-rule="number">
                                                <span class="rule-icon" aria-hidden="true">○</span>
                                                <span class="rule-label"><?= labels('password_strength_contains_number', 'Contains a number') ?></span>
                                            </div>
                                        <?php } ?>
                                        <?php if (!empty($password_rules['require_uppercase'])) { ?>
                                            <div class="password-rule" id="rule-uppercase" data-rule="uppercase">
                                                <span class="rule-icon" aria-hidden="true">○</span>
                                                <span class="rule-label"><?= labels('password_strength_contains_uppercase', 'Contains an uppercase letter') ?></span>
                                            </div>
                                        <?php } ?>
                                        <?php if (!empty($password_rules['require_lowercase'])) { ?>
                                            <div class="password-rule" id="rule-lowercase" data-rule="lowercase">
                                                <span class="rule-icon" aria-hidden="true">○</span>
                                                <span class="rule-label"><?= labels('password_strength_contains_lowercase', 'Contains a lowercase letter') ?></span>
                                            </div>
                                        <?php } ?>
                                        <?php if (!empty($password_rules['require_special'])) { ?>
                                            <div class="password-rule" id="rule-special" data-rule="special">
                                                <span class="rule-icon" aria-hidden="true">○</span>
                                                <span class="rule-label"><?= labels('password_strength_contains_special', 'Contains a special character') ?></span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="invalid-feedback d-block" id="password_error" style="display:none !important;"></div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="required"><?= labels('confirm_password', 'Confirm Password') ?></label>
                                    <div class="position-relative">
                                        <input id="confirm_password" class="form-control" type="password" name="confirm_password" required style="padding-right: 2.5rem;">
                                        <button type="button" class="btn btn-link position-absolute toggle-pw-btn" data-target="confirm_password"
                                            style="right: 0; top: 50%; transform: translateY(-50%); border: none; background: none; padding: 0.5rem; cursor: pointer; z-index: 10; color: #6c757d;"
                                            title="<?= labels('show_password', 'Show Password') ?>">
                                            <i class="fas fa-eye-slash"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback d-block" id="confirm_password_error" style="display:none !important;"></div>
                                </div>

                                <div class="form-group d-flex justify-content-end mt-4">
                                    <a href="<?= base_url('admin/profile') ?>" class="btn btn-secondary mr-2"><?= labels('cancel', 'Cancel') ?></a>
                                    <button type="submit" class="btn bg-new-primary submit_btn"><?= labels('save_changes', 'Save') ?></button>
                                </div>
                            </div>
                            <?= form_close() ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    $(document).ready(function() {
        var LBL = {
            old_required: "<?= labels('old_password', 'Old Password') ?> <?= labels('is_required', 'is required') ?>",
            new_required: "<?= labels('new_password', 'New Password') ?> <?= labels('is_required', 'is required') ?>",
            confirm_required: "<?= labels('confirm_password', 'Confirm Password') ?> <?= labels('is_required', 'is required') ?>",
            strength: "<?= labels('password_requirements_not_met', 'Password does not meet the requirements. Please check the rules above.') ?>",
            mismatch: "<?= labels('password_mismatch', 'Password Mismatch') ?>"
        };

        function setFieldError(inputId, errorElId, msg) {
            var $input = $('#' + inputId);
            var $err = $('#' + errorElId);
            if (msg) {
                $input.addClass('is-invalid');
                $err.text(msg).attr('style', 'display:block !important;');
            } else {
                $input.removeClass('is-invalid');
                $err.text('').attr('style', 'display:none !important;');
            }
        }

        function validateOld() {
            var v = $('#old_password').val();
            if (!v) { setFieldError('old_password', 'old_password_error', LBL.old_required); return false; }
            setFieldError('old_password', 'old_password_error', '');
            return true;
        }

        function validateNew() {
            var v = $('#password').val();
            if (!v) { setFieldError('password', 'password_error', LBL.new_required); return false; }
            if (typeof window.passwordStrengthValid === 'function' && !window.passwordStrengthValid()) {
                setFieldError('password', 'password_error', LBL.strength);
                return false;
            }
            setFieldError('password', 'password_error', '');
            return true;
        }

        function validateConfirm() {
            var v = $('#confirm_password').val();
            var pw = $('#password').val();
            if (!v) { setFieldError('confirm_password', 'confirm_password_error', LBL.confirm_required); return false; }
            if (v !== pw) { setFieldError('confirm_password', 'confirm_password_error', LBL.mismatch); return false; }
            setFieldError('confirm_password', 'confirm_password_error', '');
            return true;
        }

        $('#old_password').on('input blur', validateOld);
        $('#password').on('input blur', function() {
            validateNew();
            if ($('#confirm_password').val()) validateConfirm();
        });
        $('#confirm_password').on('input blur', validateConfirm);

        $('#change_password_form').on('submit', function(e) {
            var ok = validateOld();
            ok = validateNew() && ok;
            ok = validateConfirm() && ok;
            if (!ok) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
        });

        // Eye toggle for all 3 fields
        $('.toggle-pw-btn').on('click', function() {
            var target = $(this).data('target');
            var input = document.getElementById(target);
            if (!input) return;
            var icon = $(this).find('i');
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            icon.toggleClass('fa-eye-slash', showing);
            icon.toggleClass('fa-eye', !showing);
            $(this).attr('title', showing ?
                "<?= labels('show_password', 'Show Password') ?>" :
                "<?= labels('hide_password', 'Hide Password') ?>"
            );
        });
    });
</script>
