<?= $this->extend('backend/handyman/layouts/main') ?>

<?= $this->section('content') ?>

<div class="section-body">
    <div class="row">
        <!-- Profile Update Card -->
        <div class="col-lg-8 col-md-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom px-4 pt-3 pb-3">
                    <h4>
                        <i class="fas fa-user-cog text-primary me-2"></i>
                        <?= labels('profile_update', 'Profile Update') ?>
                    </h4>
                </div>
                <div class="card-body px-4 py-4">
                    <form method="post" action="<?= base_url('handyman/profile/update') ?>" id="update_profile_form"
                        class="create-form-without-reset" enctype="multipart/form-data" novalidate data-fv
                        data-success-function="onProfileUpdateSuccess">
                        <?= csrf_field() ?>

                        <!-- Username Tabbed Multi-language Inputs -->
                        <div class="form-group mb-4">
                            <label class="required d-block mb-2"><?= labels('username', 'Username') ?></label>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2" id="handymanLangTabs">
                                <?php foreach ($languages as $lang): ?>
                                    <div class="handyman-lang-tab <?= $lang['is_default'] ? 'active-lang' : '' ?>"
                                        data-lang="<?= esc($lang['code']) ?>" style="cursor:pointer; padding:0.4rem 0.75rem; font-size:0.875rem;
                                               border-bottom: 2px solid <?= $lang['is_default'] ? 'var(--primary-color,#6777ef)' : 'transparent' ?>;
                                               color: <?= $lang['is_default'] ? 'var(--primary-color,#6777ef)' : '#6c757d' ?>;
                                               transition: all 0.2s ease;">
                                        <?= esc($lang['language']) ?>
                                        <?= $lang['is_default'] ? ' (' . labels('default', 'Default') . ')' : '' ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php foreach ($languages as $lang):
                                $code = $lang['code'];
                                $isDefault = !empty($lang['is_default']);
                                $suffix = $isDefault ? '' : ' (' . $code . ')';
                                ?>
                                <div class="handyman-lang-panel" id="handymanLangPanel-<?= $code ?>"
                                    <?= $isDefault ? 'data-default-panel' : '' ?>
                                    style="<?= $isDefault ? '' : 'display:none;' ?>">
                                    <input type="text" class="form-control" name="username[<?= $code ?>]"
                                        id="username_<?= $code ?>"
                                        value="<?= esc($translations[$code] ?? ($isDefault ? $user_details['username'] : '')) ?>"
                                        placeholder="<?= labels('please_enter_username', 'Please enter username') . $suffix ?>"
                                        <?= $isDefault ? 'required data-rules="required"' : '' ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="row">
                            <!-- Email -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="email"><?= labels('email', 'Email') ?></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?= esc($user_details['email'] ?? '') ?>"
                                        placeholder="<?= labels('please_enter_email', 'Please enter email') ?>"
                                        data-rules="email">
                                </div>
                            </div>

                            <!-- Phone Number -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="phone" class="required"><?= labels('phone_number', 'Phone Number') ?></label>
                                    <div class="phone-input-group">
                                        <div class="country_code">
                                            <?php if ($single_country_code): ?>
                                                <input type="text" class="form-control" name="country_code" id="country_code"
                                                    value="<?= esc($user_details['country_code'] ?: ($single_country_data['calling_code'] ?? $default_calling_code)) ?>"
                                                    readonly>
                                            <?php else: ?>
                                                <select class="form-control select2" name="country_code" id="country_code">
                                                    <?php foreach ($country_codes as $cc):
                                                        $selected = (($user_details['country_code'] ?: $default_calling_code) === $cc['calling_code']) ? 'selected' : '';
                                                        echo "<option value='" . esc($cc['calling_code']) . "' $selected>" . esc($cc['calling_code']) . "</option>";
                                                    endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                            value="<?= esc($user_details['phone'] ?? '') ?>"
                                            placeholder="<?= labels('enter_mobile_number', 'Enter Mobile Number') ?>"
                                            data-rules="required">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Address -->
                            <div class="col-md-6 mb-4">
                                <div class="form-group mb-0">
                                    <label for="address"><?= labels('address', 'Address') ?></label>
                                    <textarea class="form-control" id="address" name="address" rows="3"
                                        placeholder="<?= labels('please_enter_address', 'Please enter address') ?>" style="height: 100px;"><?= esc($handyman_details['address'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Salary (read-only — set by partner) -->
                            <div class="col-md-6 mb-4">
                                <div class="form-group mb-0">
                                    <label for="salary"><?= labels('salary', 'Salary') ?> (<?= esc($currency) ?>)</label>
                                    <input type="number" class="form-control" id="salary"
                                        value="<?= esc($handyman_details['salary'] ?? '') ?>"
                                        disabled>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Profile Image -->
                            <div class="col-12 mb-4">
                                <div class="form-group mb-0">
                                    <label id="profile_image_label" for="profile_image"><?= labels('profile_image', 'Profile Image') ?></label>
                                    <input type="file" class="filepond" id="profile_image" name="profile_image" accept="image/*">
                                    <div id="handyman_profile_preview" style="<?= !empty($user_details['image_url']) ? '' : 'display:none;' ?> margin-top:0.5rem;">
                                        <img id="handyman_profile_preview_img" src="<?= esc($user_details['image_url'] ?? '') ?>" alt=""
                                            style="height:80px; width:80px; border-radius:8px; object-fit:cover;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Fields -->
                        <?php if (!empty($handyman_custom_fields)): ?>
                            <div class="row">
                                <?php foreach ($handyman_custom_fields as $field):
                                    $cfId = (int) ($field['id'] ?? 0);
                                    $inputName = 'cf_' . $cfId;
                                    $fieldType = $field['field_type'];
                                    $required = !empty($field['required']);
                                    $fieldLabel = $field['field_label'];
                                    $requiredAttr = $required ? 'required' : '';
                                    $fieldFileConfig = is_array($field['file_config'] ?? null) ? $field['file_config'] : [];
                                    $currentVal = $custom_field_values[$cfId] ?? '';
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label for="<?= $inputName ?>" class="<?= $required ? 'required' : '' ?>">
                                                <?= esc($fieldLabel) ?>
                                            </label>
                                            <?php if ($fieldType === 'file'): ?>
                                                <input type="file" class="filepond-custom-field" name="<?= $inputName ?>" id="<?= $inputName ?>" <?= $requiredAttr ?> <?= $required ? 'data-rules="required"' : '' ?> <?= (!empty($fieldFileConfig['max_files']) && (int) $fieldFileConfig['max_files'] > 1) ? ' multiple' : '' ?>>
                                                <?php if ($currentVal !== ''): ?>
                                                    <?php 
                                                    $ext = strtolower(pathinfo($currentVal, PATHINFO_EXTENSION));
                                                    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff'];
                                                    if (in_array($ext, $imageExts, true)): ?>
                                                        <div class="mt-2">
                                                            <img src="<?= esc($currentVal) ?>" alt="<?= esc($fieldLabel) ?>" style="max-width: 120px; max-height: 80px; border-radius: 8px; border: 1px solid #d6d6dd;">
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="margin-top:0.5rem;">
                                                            <a href="<?= $currentVal ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-3">
                                                                <i class="fas fa-eye me-1"></i> <?= labels('view_current_file', 'View Current File') ?>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php elseif ($fieldType === 'textarea'): ?>
                                                <textarea class="form-control" name="<?= $inputName ?>" id="<?= $inputName ?>" <?= $requiredAttr ?> <?= $required ? 'data-rules="required"' : '' ?>><?= esc($currentVal) ?></textarea>
                                            <?php else: 
                                                $inputType = match (true) {
                                                    $fieldType === 'number' => 'number',
                                                    $fieldType === 'date' => 'date',
                                                    default => 'text',
                                                };
                                                ?>
                                                <input type="<?= $inputType ?>" class="form-control" name="<?= $inputName ?>" id="<?= $inputName ?>" value="<?= esc($currentVal) ?>" <?= $requiredAttr ?> <?= $required ? 'data-rules="required"' : '' ?>>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        <?php endif ?>

                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary submit_btn px-4 py-2 rounded-3">
                                <?= labels('save_changes', 'Save Changes') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Password Change Card -->
        <div class="col-lg-4 col-md-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom px-4 pt-3 pb-3">
                    <h4>
                        <i class="fas fa-key text-warning me-2"></i>
                        <?= labels('change_password', 'Change Password') ?>
                    </h4>
                </div>
                <div class="card-body px-4 py-4">
                    <form method="post" action="<?= base_url('handyman/profile/change_password') ?>" id="change_password_form" class="create-form" novalidate data-fv>
                        <?= csrf_field() ?>

                        <!-- Current Password -->
                        <div class="form-group mb-3">
                            <label for="old_password" class="required"><?= labels('current_password', 'Current Password') ?></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="old_password" name="old_password"
                                    placeholder="<?= labels('enter_current_password', 'Enter Current Password') ?>"
                                    required data-rules="required">
                                <span class="input-group-text toggle-password" style="cursor:pointer; background: transparent; border-left: none;">
                                    <i class="fa fa-eye-slash" style="color: #6c757d;"></i>
                                </span>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div class="form-group mb-3">
                            <label for="new_password" class="required"><?= labels('new_password', 'New Password') ?></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="new_password"
                                    placeholder="<?= labels('enter_new_password', 'Enter New Password') ?>"
                                    required data-rules="required">
                                <span class="input-group-text toggle-password" style="cursor:pointer; background: transparent; border-left: none;">
                                    <i class="fa fa-eye-slash" style="color: #6c757d;"></i>
                                </span>
                            </div>

                            <!-- Password strength indicator (hidden when minLength === 0) -->
                            <div id="password-strength-indicator" class="mt-2 small"
                                <?= ($password_rules['min_length'] === 0) ? 'style="display:none;"' : '' ?>>
                                <?php if ($password_rules['min_length'] > 0): ?>
                                    <div class="password-rule" data-rule="length">
                                        <span class="rule-icon" aria-hidden="true">○</span>
                                        <span class="rule-label"><?= sprintf(labels('password_strength_min_length_n', 'At least %s characters'), (int) $password_rules['min_length']) ?></span>
                                    </div>
                                <?php endif ?>
                                <?php if (!empty($password_rules['require_number'])): ?>
                                    <div class="password-rule" data-rule="number">
                                        <span class="rule-icon" aria-hidden="true">○</span>
                                        <span class="rule-label"><?= labels('password_strength_contains_number', 'Contains a number') ?></span>
                                    </div>
                                <?php endif ?>
                                <?php if (!empty($password_rules['require_uppercase'])): ?>
                                    <div class="password-rule" data-rule="uppercase">
                                        <span class="rule-icon" aria-hidden="true">○</span>
                                        <span class="rule-label"><?= labels('password_strength_contains_uppercase', 'Contains an uppercase letter') ?></span>
                                    </div>
                                <?php endif ?>
                                <?php if (!empty($password_rules['require_lowercase'])): ?>
                                    <div class="password-rule" data-rule="lowercase">
                                        <span class="rule-icon" aria-hidden="true">○</span>
                                        <span class="rule-label"><?= labels('password_strength_contains_lowercase', 'Contains a lowercase letter') ?></span>
                                    </div>
                                <?php endif ?>
                                <?php if (!empty($password_rules['require_special'])): ?>
                                    <div class="password-rule" data-rule="special">
                                        <span class="rule-icon" aria-hidden="true">○</span>
                                        <span class="rule-label"><?= labels('password_strength_contains_special', 'Contains a special character') ?></span>
                                    </div>
                                <?php endif ?>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group mb-4">
                            <label for="confirm_password" class="required"><?= labels('confirm_new_password', 'Confirm New Password') ?></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                    placeholder="<?= labels('confirm_new_password', 'Confirm New Password') ?>"
                                    required data-rules="required">
                                <span class="input-group-text toggle-password" style="cursor:pointer; background: transparent; border-left: none;">
                                    <i class="fa fa-eye-slash" style="color: #6c757d;"></i>
                                </span>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary submit_btn px-4 py-2 rounded-3 text-white">
                                <?= labels('change_password_btn', 'Change Password') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_styles') ?>
<!-- Profile page opts out of Turbo entirely: forces a normal full-page load/reload
     instead of a Turbo Drive visit, so its form submissions run as plain jQuery
     ajax with no Turbo morph/cache interaction. -->
<meta name="turbo-visit-control" content="reload">
<style>
    .phone-input-group {
        display: flex;
        align-items: center;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        background-color: #fff;
        overflow: hidden;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .phone-input-group:focus-within {
        border-color: var(--primary-color, #05a6e8);
        box-shadow: 0 0 0 0.2rem rgba(5, 166, 232, 0.15);
    }

    .phone-input-group .form-control {
        border: none !important;
        box-shadow: none !important;
    }

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
        max-width: 100px;
        box-shadow: none;
        outline: none;
        font-size: 0.9rem;
        text-align: center;
        color: #495057;
    }

    .country_code input[readonly] {
        cursor: not-allowed;
    }



    .active-lang {
        font-weight: bold;
    }

</style>
<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
    window.PASSWORD_RULES = {
        minLength: <?= (int) $password_rules['min_length'] ?>,
        requireUppercase: <?= (int) $password_rules['require_uppercase'] ?>,
        requireLowercase: <?= (int) $password_rules['require_lowercase'] ?>,
        requireNumber: <?= (int) $password_rules['require_number'] ?>,
        requireSpecial: <?= (int) $password_rules['require_special'] ?>
    };

    function handymanProfileSetup() {
        if (!document.getElementById('update_profile_form')) return;

        // Toggle password visibility (namespaced + delegated so re-running setup() on turbo:load never double-binds)
        $(document).off('click.handymanProfile', '.toggle-password').on('click.handymanProfile', '.toggle-password', function () {
            var $input = $(this).closest('.input-group').find('input');
            var $icon = $(this).find('i');
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            }
        });

        // Select2 for country code selection
        <?php if (!$single_country_code): ?>
            if ($.fn.select2) {
                var $countryCode = $('#country_code');
                if ($countryCode.hasClass('select2-hidden-accessible')) {
                    $countryCode.select2('destroy');
                }
                $countryCode.select2({ minimumResultsForSearch: 0, width: '100px' });
            }
        <?php endif; ?>

        // Language tab switching for username
        $(document).off('click.handymanProfile', '.handyman-lang-tab').on('click.handymanProfile', '.handyman-lang-tab', function () {
            var lang = $(this).data('lang');
            $('.handyman-lang-tab').removeClass('active-lang').css({ 'border-bottom-color': 'transparent', 'color': '#6c757d' });
            $(this).addClass('active-lang').css({ 'border-bottom-color': 'var(--primary-color,#6777ef)', 'color': 'var(--primary-color,#6777ef)' });
            $('.handyman-lang-panel:not([data-default-panel])').hide();
            $('#handymanLangPanel-' + lang).show();
        });

        // Captured before FilePond.parse() removes it — needed for image preview swap.
        var profileImageInput = document.getElementById('profile_image');

        if (typeof FilePond !== 'undefined' && profileImageInput && !FilePond.find(profileImageInput)) {
            FilePond.setOptions({ credits: null });
            FilePond.parse(document.body);
        }

        window.onProfileUpdateSuccess = function (response) {
            if (!response.image_url) return;

            var $preview = $('#handyman_profile_preview');
            var $img     = $('#handyman_profile_preview_img');

            function swapPreviewImage() {
                var tempImg = new Image();
                tempImg.onload = function () {
                    $preview.show();
                    $img.fadeTo(150, 0, function () {
                        $img.attr('src', response.image_url).fadeTo(200, 1);
                    });
                };
                tempImg.src = response.image_url;
            }

            if (typeof FilePond !== 'undefined' && profileImageInput) {
                var pond = FilePond.find(profileImageInput);
                if (pond && pond.getFiles().length > 0) {
                    pond.removeFiles();
                    setTimeout(swapPreviewImage, 250);
                    return;
                }
            }
            swapPreviewImage();
        };
    }

    document.addEventListener('turbo:load', handymanProfileSetup);
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.Turbo === 'undefined') {
            handymanProfileSetup();
        }
    });
</script>
<?= $this->endSection() ?>