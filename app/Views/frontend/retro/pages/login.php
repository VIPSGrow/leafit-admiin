<!-- Main Content -->
<?php
$session = \Config\Services::session();
$is_rtl = $session->get('is_rtl');
$language = $session->get('language');
$default_language = fetch_details('languages', ['is_default' => '1']);

if (($is_rtl != 0 && (empty($language) || $language == "")) || $default_language[0]['is_rtl'] == "1") {
    $is_rtl = 1;
} else {
    $is_rtl = 0;
}
?>
<?php if ($is_rtl === null): ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            var isRtl = localStorage.getItem("is_rtl");
            var language = localStorage.getItem("language");
            if (isRtl !== null) {
                isRtl = JSON.parse(isRtl);
                language = JSON.parse(language);
                $.ajax({
                    type: "POST",
                    url: baseUrl + "/lang/updateIsRtl",
                    data: { is_rtl: isRtl, language: language },
                    success: function (response) { location.reload(); },
                    error: function () { }
                });
            }
        });
    </script>
<?php endif; ?>
<?php
$data = get_settings('general_settings', true);
isset($data['primary_color']) && $data['primary_color'] != "" ? $primary_color = $data['primary_color'] : $primary_color = '#05a6e8';
isset($data['secondary_color']) && $data['secondary_color'] != "" ? $secondary_color = $data['secondary_color'] : $secondary_color = '#003e64';
isset($data['primary_shadow']) && $data['primary_shadow'] != "" ? $primary_shadow = $data['primary_shadow'] : $primary_shadow = '#05A6E8';

// Country codes (used by partner partial)
$country_code_model = new \App\Models\Country_code_model();
$country_codes = $country_code_model->findAll();
$system_country_code = $country_code_model->where('is_default', 1)->first();
$default_calling_code = !empty($system_country_code['calling_code']) ? $system_country_code['calling_code'] : '+91';
$single_country_code = (count($country_codes) == 1);
$single_country_data = $single_country_code ? $country_codes[0] : null;

// URL context
$isAdminLogin = (current_url() == base_url() . 'admin/login');
$isPartnerLogin = (current_url() == base_url() . 'partner/login');
$isHandymanLogin = (current_url() == base_url() . 'handyman/login');
$loginForm = $isAdminLogin ? 'admin' : ($isPartnerLogin ? 'partner' : 'handyman');
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

    .bg-primary {
        background-color:
            <?= $primary_color ?>
            !important;
    }

    /* Single-border flex input wrapper — Bootstrap input-group does not support this shape */
    .phone-input-group,
    .custom-password-group {
        display: flex;
        align-items: center;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        background-color: #fff;
        overflow: hidden;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .phone-input-group:focus-within,
    .custom-password-group:focus-within {
        border-color:
            <?= $primary_color ?>
        ;
        box-shadow: 0 0 0 0.2rem rgba(5, 166, 232, 0.15);
    }

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

    .toggle-password:hover {
        color:
            <?= $primary_color ?>
        ;
    }

    .phone-input-group .form-control,
    .custom-password-group .form-control {
        border: none !important;
        box-shadow: none !important;
        flex: 1;
        min-width: 0;
    }

    /* Country code slot — border-right separator + max-width not available as Bootstrap utilities */
    .country_code select,
    .country_code input[readonly] {
        border: none;
        border-right: 1px solid #ced4da;
        background: transparent;
        padding: 0.5rem;
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

<div class="auth" style="overflow: hidden; background-image: url('<?= $login_image_url ?>');">
    <div class="login-wrapper">
        <section class="container-fluid" data-aos="fade-up">
            <div>
                <div id="app">
                    <section class="section">
                        <div class="container-fluid">
                            <div class="row d-flex justify-content-<?= $is_rtl == 1 ? 'start' : 'end' ?>">
                                <div
                                    class="col-12 col-lg-6 col-md-8 col-sm-8 col-xl-4 <?= $is_rtl == 1 ? 'ms-md-5' : 'me-md-5' ?> <?= $is_rtl == 1 ? 'mx-lg-3' : 'my-lg-3' ?> -<?= $is_rtl == 1 ? '' : 'offset-sm-2' ?>">
                                    <div class="card rounded-3" style="padding: 30px;">

                                        <!-- Logo -->
                                        <div class="text-center mb-4 mt-2 w-100">
                                            <?php if ($isAdminLogin): ?>
                                                <img style="width: 60%;"
                                                    src="<?= isset($data['logo']) && $data['logo'] != "" ? base_url("public/uploads/site/" . $data['logo']) : base_url('public/backend/assets/img/news/img01.jpg') ?>"
                                                    alt="">
                                            <?php else: ?>
                                                <img style="width: 60%;"
                                                    src="<?= isset($data['partner_logo']) && $data['partner_logo'] != "" ? base_url("public/uploads/site/" . $data['partner_logo']) : base_url('public/backend/assets/img/news/img01.jpg') ?>"
                                                    alt="">
                                            <?php endif; ?>
                                        </div>

                                        <div class="row">
                                            <div class="col-md">
                                                <div class="card-body p-0">
                                                    <div class="row">
                                                        <div class="col-md">

                                                            <!-- Form partial -->
                                                            <?php include __DIR__ . '/_login_forms/' . $loginForm . '.php'; ?>

                                                            <!-- Role switch buttons -->
                                                            <div class="mt-3 mb-2">
                                                                <div class="d-flex align-items-center my-4">
                                                                    <hr class="flex-fill m-0">
                                                                    <span
                                                                        class="px-2 text-muted"><?= labels('other_panel_logins', 'Access other panels') ?></span>
                                                                    <hr class="flex-fill m-0">
                                                                </div>
                                                                <div class="d-flex gap-2">
                                                                    <?php if (!$isAdminLogin): ?>
                                                                        <a href="<?= base_url('admin/login') ?>"
                                                                            class="btn bg-primary text-white flex-fill"><?= labels('login_as_admin', 'Login as Admin') ?></a>
                                                                    <?php endif; ?>
                                                                    <?php if (!$isPartnerLogin): ?>
                                                                        <a href="<?= base_url('partner/login') ?>"
                                                                            class="btn bg-primary text-white flex-fill"><?= labels('login_as_provider', 'Login as Provider') ?></a>
                                                                    <?php endif; ?>
                                                                    <?php if (!$isHandymanLogin): ?>
                                                                        <a href="<?= base_url('handyman/login') ?>"
                                                                            class="btn bg-primary text-white flex-fill"><?= labels('login_as_handyman', 'Login as Handyman') ?></a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0): ?>
                                                <div class="col-md-12">
                                                    <div class="row">
                                                        <?php if ($isAdminLogin): ?>
                                                            <div class="col-md-12">
                                                                <div class="d-flex align-items-center justify-content-between p-3 text-white"
                                                                    style="background-color: var(--primary-color); border: 1px solid #e2e8f0; border-radius: 8px;">
                                                                    <div>
                                                                        <h6 class="mb-2"
                                                                            style="font-size: 14px; font-weight: 700;">
                                                                            <?= strtoupper(labels('admin_login', 'ADMIN LOGIN')) ?>
                                                                        </h6>
                                                                        <div style="font-size: 12px;">
                                                                            <span
                                                                                class="me-4"><strong><?= labels('mobile', 'Mobile') ?>:</strong>
                                                                                9876543210</span>
                                                                            <span><strong><?= labels('password', 'Password') ?>:</strong>
                                                                                12345678</span>
                                                                        </div>
                                                                    </div>
                                                                    <div onclick="copy_admin_cred()"
                                                                        class="copy_credentials bg-white shadow-sm d-flex align-items-center justify-content-center"
                                                                        style="width: 38px; height: 38px; border-radius: 8px; border: 1px solid #e2e8f0; cursor: pointer; color: var(--primary-color); transition: all 0.2s ease;"
                                                                        title="Copy Admin Credentials">
                                                                        <i class="fa-regular fa-copy"
                                                                            style="font-size: 1rem;"></i>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($isPartnerLogin): ?>
                                                            <div class="col-md-12">
                                                                <div class="d-flex text-white align-items-center justify-content-between p-3"
                                                                    style="background-color: var(--primary-color); border-radius: 12px;">
                                                                    <div>
                                                                        <h6 class="mb-2"
                                                                            style="font-size: 14px; font-weight: 700;">
                                                                            <?= strtoupper(labels('provider_login', 'PROVIDER LOGIN')) ?>
                                                                        </h6>
                                                                        <div style="font-size: 12px;">
                                                                            <span
                                                                                class="me-4"><strong><?= labels('mobile', 'Mobile') ?>:</strong>
                                                                                1234567890</span>
                                                                            <span><strong><?= labels('password', 'Password') ?>:</strong>
                                                                                12345678</span>
                                                                        </div>
                                                                    </div>
                                                                    <div onclick="copy_provider_cred()"
                                                                        class="copy_credentials bg-white shadow-sm d-flex align-items-center justify-content-center"
                                                                        style="width: 38px; height: 38px; border-radius: 8px; border: 1px solid #e2e8f0; cursor: pointer; color: var(--primary-color); transition: all 0.2s ease;"
                                                                        title="Copy Provider Credentials">
                                                                        <i class="fa-regular fa-copy"
                                                                            style="font-size: 1rem;"></i>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <div class="text-center mt-4 mb-0">
                                                <?php
                                                $data = get_settings('general_settings', true);

                                                function getCopyrightDetails($data)
                                                {
                                                    $session = \Config\Services::session();
                                                    $current_language = $session->get('language_code');

                                                    if (!$current_language) {
                                                        $default_lang = fetch_details('languages', ['is_default' => 1], ['code']);
                                                        $current_language = !empty($default_lang) ? $default_lang[0]['code'] : 'en';
                                                    }

                                                    if (isset($data['copyright_details']) && is_array($data['copyright_details'])) {
                                                        if (isset($data['copyright_details'][$current_language]) && !empty($data['copyright_details'][$current_language])) {
                                                            return $data['copyright_details'][$current_language];
                                                        }
                                                        foreach ($data['copyright_details'] as $lang => $copyright) {
                                                            if (!empty($copyright)) {
                                                                return $copyright;
                                                            }
                                                        }
                                                    } else if (isset($data['copyright_details']) && is_string($data['copyright_details']) && !empty($data['copyright_details'])) {
                                                        return $data['copyright_details'];
                                                    }

                                                    return "edemand copyright";
                                                }

                                                echo getCopyrightDetails($data);
                                                ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
    $(document).on('click', '.toggle-password', function () {
        $(this).toggleClass("fa-eye fa-eye-slash");
        var input = $("#password");
        input.attr('type') === 'password' ? input.attr('type', 'text') : input.attr('type', 'password');
    });

    function copy_admin_cred() {
        if (window.location.href.indexOf('/admin/login') === -1) {
            window.location.href = '<?= base_url() ?>admin/login?autofill=admin';
            return;
        }
        $('#identity').val('9876543210');
        $('#password').val('12345678');
        iziToast.success({
            title: "",
            message: "<?= labels('admin_credentials_copied_successfully', 'Admin Credentials Copied successfully!') ?>",
            position: "topRight",
        });
    }

    function copy_provider_cred() {
        if (window.location.href.indexOf('/partner/login') === -1) {
            window.location.href = '<?= base_url() ?>partner/login?autofill=provider';
            return;
        }
        var isSingleCountryCode = <?= $single_country_code ? 'true' : 'false' ?>;
        var selectedCountryCode = "+91";
        if (isSingleCountryCode) {
            $('#country_code').val(selectedCountryCode);
        } else {
            $('#country_code').val(selectedCountryCode).trigger('change');
        }
        $('#identity').val('1234567890');
        $('#password').val('12345678');
        iziToast.success({
            title: "",
            message: "<?= labels('provider_credentials_copied_successfully', 'Provider Credentials Copied successfully!') ?>",
            position: "topRight",
        });
    }

    $(document).ready(function () {
        // Handle ?autofill=admin on admin login page
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autofill') === 'admin' && window.location.href.indexOf('/admin/login') !== -1) {
            copy_admin_cred();
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        // password_changed toast
        if (urlParams.get('password_changed') === '1') {
            iziToast.success({
                title: "",
                message: "<?= labels('password_changed_successfully', 'Password changed successfully') ?>",
                position: "topRight",
                timeout: 5000,
                progressBar: true,
            });
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
    });
</script>