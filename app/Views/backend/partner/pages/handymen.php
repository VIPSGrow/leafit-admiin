<?php
helper('function');
$password_rules = get_password_rules();

function renderHandymanCustomFieldInput(string $inputName, string $fieldType, string $requiredAttr, array $fileConfig = []): string
{
    $inputType = match (true) {
        $fieldType === 'number' => 'number',
        $fieldType === 'date' => 'date',
        default => 'text',
    };
    $dataRules = $requiredAttr ? 'data-rules="required"' : '';
    $commonAttrs = sprintf('class="form-control" name="%s" id="%s" %s %s', $inputName, $inputName, $requiredAttr, $dataRules);
    return match ($fieldType) {
        'file' => sprintf(
            '<input type="file" class="filepond-custom-field" name="%s" id="%s" %s %s%s>',
            $inputName,
            $inputName,
            $requiredAttr,
            $dataRules,
            (!empty($fileConfig['max_files']) && (int) $fileConfig['max_files'] > 1) ? ' multiple' : ''
        ),
        'textarea' => sprintf('<textarea %s></textarea>', $commonAttrs),
        default => sprintf('<input type="%s" %s>', $inputType, $commonAttrs),
    };
}
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
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('handymen') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('partner/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i><?= labels('Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= labels('handymen') ?></div>
            </div>
        </div>

        <div class="container-fluid card">
            <div class="row">
                <div class="col-md-12">
                    <div class="row mt-4 mb-3 align-items-center">
                        <div class="col d-flex align-items-center flex-wrap">
                            <div class="position-relative mr-2" style="width: 260px;">
                                <input type="text" class="form-control search-icon-input" id="handymanSearch"
                                    placeholder="<?= labels('search_handymen', 'Search handymen…') ?>"
                                    style="padding-right: 36px;">
                                <button type="button" id="handymanSearchBtn"
                                    title="<?= labels('search', 'Search') ?>"
                                    style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer;"
                                    class="hm-search-icon-btn search-icon-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <button class="btn btn-light border py-2" id="filterButton"
                                title="<?= labels('filters', 'Filters') ?>">
                                <span class="material-symbols-outlined"
                                    style="font-size: 20px; display: block; line-height: 1;">
                                    filter_alt
                                </span>
                            </button>
                            <div class="d-inline dropdown ml-2">
                                <button class="btn export_download dropdown-toggle" type="button"
                                    id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <?= labels('download', 'Download') ?>
                                </button>
                                <div class="dropdown-menu" x-placement="bottom-start"
                                    style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                    <a class="dropdown-item" onclick="custome_export('pdf','Handyman list','handyman_list');"><?= labels('pdf', 'PDF') ?></a>
                                    <a class="dropdown-item" onclick="custome_export('excel','Handyman list','handyman_list');"><?= labels('excel', 'Excel') ?></a>
                                    <a class="dropdown-item" onclick="custome_export('csv','Handyman list','handyman_list');"><?= labels('csv', 'CSV') ?></a>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary" onclick="openHandymanModal(null)">
                                <i class="fas fa-plus"></i> <?= labels('add_handyman') ?>
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="handyman_list" data-toggle="table"
                            data-side-pagination="server" data-pagination="true"
                            data-url="<?= base_url('partner/handymen/list') ?>" data-sort-name="id"
                            data-sort-order="desc" data-page-list="[10, 25, 50, 100]"
                            data-pagination-successively-size="2" data-show-refresh="false" data-search="false"
                            data-show-columns="false" data-show-export="false"
                            data-export-types="['pdf','excel','csv']"
                            data-export-options='{"fileName": "handyman_list","ignoreColumn": ["operations", "profile"]}'
                            data-query-params="handymanQueryParams">
                            <thead>
                                <tr>
                                    <th data-field="id" data-visible="false" data-sortable="true" class="text-center">
                                        <?= labels('id', 'ID') ?>
                                    </th>
                                    <th data-field="profile" class="text-center w-25">
                                        <?= labels('profile', 'Profile') ?>
                                    </th>
                                    <th data-field="username" data-visible="false" data-sortable="true" class="text-center">
                                        <?= labels('username', 'Username') ?>
                                    </th>
                                    <th data-field="phone" data-visible="false" data-sortable="true" class="text-center">
                                        <?= labels('phone_number', 'Phone Number') ?>
                                    </th>
                                    <th data-field="email" data-visible="false" data-sortable="true" class="text-center">
                                        <?= labels('email', 'Email') ?>
                                    </th>
                                    <th data-field="status_badge" class="text-center">
                                        <?= labels('status', 'Status') ?>
                                    </th>
                                    <!-- <th data-field="availability_badge" class="text-center">
                                        <?= labels('availability', 'Availability') ?>
                                    </th> -->
                                    <th data-field="rating_display" class="text-center">
                                        <?= labels('rating', 'Rating') ?>
                                    </th>
                                    <th data-field="completed_bookings_display" class="text-center">
                                        <?= labels('completed_bookings', 'Completed Bookings') ?>
                                    </th>
                                    <th data-field="joined_on_display" data-visible="false" class="text-center">
                                        <?= labels('joined_on', 'Joined On') ?>
                                    </th>
                                    <th data-field="salary_display" data-visible="false" class="text-center">
                                        <?= labels('salary', 'Salary') ?> (<?= esc($currency) ?>)
                                    </th>
                                    <th data-field="address_display" data-visible="false" class="text-center">
                                        <?= labels('address', 'Address') ?>
                                    </th>
                                    <th data-field="operations" class="text-center" data-events="handyman_events">
                                        <?= labels('operations', 'Operations') ?>
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div id="filterBackdrop"></div>
<div class="drawer" id="filterDrawer">
    <section class="drawer-header bg-new-primary d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <div class="bg-white m-3 text-new-primary"
                style="box-shadow:0px 8px 26px #00b9f02e;display:inline-block;padding:10px;height:45px;width:45px;border-radius:15px;">
                <span class="material-symbols-outlined">filter_alt</span>
            </div>
            <h3 class="mb-0" style="display:inline-block;font-size:16px;margin-left:10px;">
                <?= labels('filters', 'Filters') ?>
            </h3>
        </div>
        <div id="cancelButton" style="cursor:pointer;">
            <span class="material-symbols-outlined mr-2">cancel</span>
        </div>
    </section>
    <section class="drawer-body">
        <div class="row mt-4 mx-2">
            <div class="col-md-12">
                <div class="form-group">
                    <label><?= labels('table_filters', 'Table filters') ?></label>
                    <div id="columnToggleContainer"></div>
                    <button class="btn btn-primary d-block mt-3" id="apply_filter">
                        <?= labels('apply', 'Apply') ?>
                    </button>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add Handyman Modal -->
<div class="modal fade" id="addHandymanModal" tabindex="-1" role="dialog" aria-labelledby="addHandymanModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addHandymanModalLabel"><?= labels('add_handyman') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form method="post" action="<?= base_url('partner/handymen/store') ?>" id="add_handyman_form"
                class="create-form" enctype="multipart/form-data" novalidate data-fv
                data-table="#handyman_list" data-success-function="onHandymanFormSaved">
                <input type="hidden" name="handyman_id" id="handyman_id" value="">
                <?= csrf_field() ?>

                <div class="modal-body">
                    <hr class="mt-0">
                    <?php
                    $sorted_languages = sort_languages_with_default_first($languages);
                    $default_lang_code = '';
                    foreach ($sorted_languages as $l) {
                        if (!empty($l['is_default'])) {
                            $default_lang_code = $l['code'];
                            break;
                        }
                    }
                    ?>

                    <div class="row align-items-start mb-3">
                        <!-- Username — multilanguage tabs -->
                        <div class="col-9 col-md-9">
                            <label class="required d-block mb-2"><?= labels('username') ?></label>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2" id="handymanLangTabs">
                                <?php foreach ($sorted_languages as $lang): ?>
                                    <div class="handyman-lang-tab <?= $lang['is_default'] ? 'active-lang' : '' ?>"
                                        data-lang="<?= esc($lang['code']) ?>" style="cursor:pointer; padding:0.4rem 0.75rem; font-size:0.875rem;
                                               border-bottom: 2px solid <?= $lang['is_default'] ? 'var(--primary-color,#6777ef)' : 'transparent' ?>;
                                               color: <?= $lang['is_default'] ? 'var(--primary-color,#6777ef)' : '#6c757d' ?>;
                                               transition: all 0.2s ease;">
                                        <?= esc($lang['language']) ?>
                                        <?= $lang['is_default'] ? ' (' . labels('default') . ')' : '' ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php foreach ($sorted_languages as $lang):
                                $code = $lang['code'];
                                $isDefault = !empty($lang['is_default']);
                                $suffix = $isDefault ? '' : ' (' . $code . ')';
                                ?>
                                <div class="handyman-lang-panel" id="handymanLangPanel-<?= $code ?>"
                                    <?= $isDefault ? 'data-default-panel' : '' ?>
                                    style="<?= $code === $default_lang_code ? '' : 'display:none;' ?>">
                                    <input type="text" class="form-control" name="username[<?= $code ?>]"
                                        id="username_<?= $code ?>"
                                        placeholder="<?= labels('please_enter_username') . $suffix ?>"
                                        <?= $isDefault ? 'required data-rules="required"' : '' ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Status -->
                        <div class="col-3 col-md-3 d-flex align-items-end justify-content-center">
                            <div class="d-flex align-items-center">
                                <label class="mb-0 mr-2 text-muted" for="handyman_status"><?= labels('status') ?></label>
                                <div class="custom-control custom-switch mb-0">
                                    <input type="checkbox" class="custom-control-input" id="handyman_status" name="status" checked>
                                    <label class="custom-control-label" for="handyman_status"></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Phone Number -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone" class="required"><?= labels('phone_number') ?></label>
                                <div class="phone-input-group">
                                    <div class="country_code">
                                        <?php if ($single_country_code): ?>
                                            <input type="text" class="form-control" name="country_code" id="country_code"
                                                value="<?= esc($single_country_data['calling_code'] ?? $default_calling_code) ?>"
                                                readonly>
                                        <?php else: ?>
                                            <select class="form-control select2" name="country_code" id="country_code">
                                                <?php foreach ($country_codes as $cc):
                                                    $selected = ($default_calling_code === $cc['calling_code']) ? 'selected' : '';
                                                    echo "<option value='" . esc($cc['calling_code']) . "' $selected>" . esc($cc['calling_code']) . "</option>";
                                                endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        placeholder="<?= labels('enter_mobile_number') ?>"
                                        data-rules="required">
                                </div>
                            </div>
                        </div>

                         <!-- Email (optional) -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email"><?= labels('email') ?></label>
                                <input type="email" class="form-control" id="email" name="email"
                                    placeholder="<?= labels('please_enter_email') ?>"
                                    data-rules="email">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <!-- Password -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password" class="required"><?= labels('password') ?></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password"
                                        placeholder="<?= labels('password') ?>"
                                        data-rules="required">
                                    <div class="input-group-append">
                                        <span class="input-group-text toggle-password" style="cursor:pointer;">
                                            <i class="fa fa-eye-slash" id="togglePasswordIcon"></i>
                                        </span>
                                    </div>
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
                        </div>

                        <!-- Salary -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="salary" class="required"><?= labels('salary', 'Salary') ?> (<?= esc($currency) ?>)</label>
                                <input type="number" class="form-control" id="salary" name="salary"
                                    min="0" step="0.01" required data-rules="required"
                                    placeholder="<?= labels('please_enter_salary', 'Please enter salary') ?>">
                            </div>
                        </div>

                        <!-- Address (optional) -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="address">
                                    <?= labels('address', 'Address') ?>
                                </label>
                                <textarea class="form-control" id="address" name="address" rows="2"
                                    placeholder="<?= labels('please_enter_address') ?>" style="height: 100px;"></textarea>
                            </div>
                        </div>

                        <!-- Profile Image -->
                        <div class="col-md-6" id="profile_image_col">
                            <div class="form-group">
                                <label class="required" id="profile_image_label" for="profile_image"><?= labels('image') ?></label>
                                <input type="file" class="filepond" id="profile_image" name="profile_image"
                                    accept="image/*" data-rules="required">
                                <div id="handyman_profile_preview" style="display:none; margin-top:0.5rem;">
                                    <img id="handyman_profile_preview_img" src="" alt=""
                                        style="height:80px; width:80px; border-radius:8px; object-fit:cover;">
                                </div>
                            </div>
                        </div>
                    </div>

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
                                ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="<?= $inputName ?>" class="<?= $required ? 'required' : '' ?>">
                                            <?= esc($fieldLabel) ?>
                                        </label>
                                        <?= renderHandymanCustomFieldInput($inputName, $fieldType, $requiredAttr, $fieldFileConfig) ?>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?= labels('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary submit_btn">
                        <?= labels('save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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

    .hm-search-icon-btn { color: var(--primary, #007bff); transition: color 0.15s; }
    .hm-search-icon-btn:hover { color: var(--primary-dark, #0056b3); }

    #handyman_list a.o-media--middle:hover .provider_name_table {
        text-decoration: underline;
    }
</style>

<script>
    window.handymanQueryParams = function (params) {
        params.search = $('#handymanSearch').val().trim();
        return params;
    };

    $(document).ready(function () {
        // Operations dropdown clipping is now fixed centrally (partner.js —
        // detaches the menu to <body> on show.bs.dropdown), applies to every
        // bootstrap-table on this panel, no per-page code needed here.

        for_drawer('#filterButton', '#filterDrawer', '#filterBackdrop', '#cancelButton');
        var columns = fetchColumns('handyman_list');
        setupColumnToggle('handyman_list', columns, 'columnToggleContainer');

        function triggerHandymanSearch() {
            $('#handyman_list').bootstrapTable('refresh');
        }
        $('#handymanSearchBtn').on('click', triggerHandymanSearch);
        $('#handymanSearch').on('keydown', function (e) {
            if (e.key === 'Enter') triggerHandymanSearch();
        });

        $('.toggle-password').on('click', function () {
            var $input = $('#password');
            var $icon = $('#togglePasswordIcon');
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            }
        });

        <?php if (!$single_country_code): ?>
            if ($.fn.select2) {
                $('#country_code').select2({ minimumResultsForSearch: 5, width: '100px', dropdownParent: $('#addHandymanModal') });
                $('#country_code').val('<?= esc($default_calling_code) ?>').trigger('change');
            }
        <?php endif; ?>

        // Language tab switching for username — only active panel visible at a time
        var activeHandymanLang = '<?= esc($default_lang_code) ?>';
        $(document).on('click', '.handyman-lang-tab', function () {
            var lang = $(this).data('lang');
            if (lang === activeHandymanLang) return;
            $('.handyman-lang-tab').css({ 'border-bottom-color': 'transparent', 'color': '#6c757d' });
            $(this).css({ 'border-bottom-color': 'var(--primary-color,#6777ef)', 'color': 'var(--primary-color,#6777ef)' });
            $('#handymanLangPanel-' + lang).show();
            $('#handymanLangPanel-' + activeHandymanLang).hide();
            activeHandymanLang = lang;
        });

        // FilePond detaches #profile_image from the DOM on first parse() — cache the
        // reference now so later attribute toggling still targets the real node.
        var $profileImageInput = $('#profile_image');

        $('#addHandymanModal').on('shown.bs.modal', function () {
            if (typeof FilePond !== 'undefined') {
                FilePond.parse(document.querySelector('#addHandymanModal'));
            }
        });

        $('#addHandymanModal').on('hidden.bs.modal', function () {
            if (typeof FilePond !== 'undefined') {
                $(this).find('.filepond--root').each(function () {
                    var pond = FilePond.find(this);
                    if (pond) pond.removeFiles();
                });
            }
            $('#add_handyman_form')[0].reset();
            $('.handyman-lang-panel').hide();
            $('#handymanLangPanel-<?= esc($default_lang_code) ?>').show();
            $('.handyman-lang-tab').css({ 'border-bottom-color': 'transparent', 'color': '#6c757d' });
            $('#handymanLangTabs .handyman-lang-tab').first().css({ 'border-bottom-color': 'var(--primary-color,#6777ef)', 'color': 'var(--primary-color,#6777ef)' });
            activeHandymanLang = '<?= esc($default_lang_code) ?>';
            $('#salary').val('');
            $('#togglePasswordIcon').removeClass('fa-eye').addClass('fa-eye-slash');
            $('#password').attr('type', 'password').attr('required', 'required').attr('data-rules', 'required').trigger('input');
            $('#password').closest('.form-group').closest('.col-md-6').show();
            $profileImageInput.attr('required', 'required').attr('data-rules', 'required');
            $('#profile_image_label').addClass('required');
            $('#profile_image_col').removeClass('col-12').addClass('col-md-6');
            $('#handyman_id').val('');
            $('#addHandymanModalLabel').text(addHandymanLabel);
            $('#add_handyman_form').attr('action', storeUrl);
            $('#handyman_profile_preview_img').attr('src', '');
            $('#handyman_profile_preview').hide();
        });

        var handymanActiveBookingTitle = '<?= labels('handyman_active_booking_title', 'Handyman Assigned to Active Booking') ?>';
        var okLabel = '<?= labels('okay', 'Okay') ?>';
        var handymanActiveBookingOrderLabel = '<?= labels('handyman_active_booking_order', 'Order') ?>';
        var handymanActiveBookingCustomerLabel = '<?= labels('handyman_active_booking_customer', 'Customer') ?>';
        var handymanActiveBookingStatusLabel = '<?= labels('handyman_active_booking_status', 'Status') ?>';

        function buildActiveBookingsListHtml(bookings) {
            if (!bookings || !bookings.length) return '';
            var rows = bookings.map(function (b) {
                return '<li style="margin-bottom:6px;">'
                    + '<strong>' + handymanActiveBookingOrderLabel + ' #' + b.order_id + '</strong>'
                    + ' — ' + handymanActiveBookingCustomerLabel + ': ' + (b.customer_name || '-')
                    + ', ' + handymanActiveBookingStatusLabel + ': ' + (b.order_status || b.handyman_status || '-')
                    + '</li>';
            }).join('');
            return '<ul style="text-align:left; max-height:220px; overflow-y:auto; padding-left:1.2rem; margin-top:0.75rem;">' + rows + '</ul>';
        }

        function showHandymanActiveBookingModal(message, bookings) {
            Swal.fire({
                title: handymanActiveBookingTitle,
                html: '<div style="text-align:left;">' + message + '</div>' + buildActiveBookingsListHtml(bookings),
                icon: 'warning',
                confirmButtonText: okLabel,
                showCancelButton: false,
            });
        }

        // Pre-check run before delete/deactivate confirm — backend re-runs the same
        // check inside delete()/toggleStatus() as a fail-safe against races, but the
        // panel must never let the user reach the confirm dialog when it already knows.
        function checkHandymanActiveBookings(handymanId, onChecked) {
            var data = new FormData(); data.append('id', handymanId);
            ajaxRequest('POST', '<?= base_url('partner/handymen/active-bookings') ?>', data,
                null,
                function (res) { onChecked(!!res.active_booking, res.bookings || [], res.message); },
                function (res) { onChecked(false, [], res.message); },
                null,
                false
            );
        }

        var addHandymanLabel  = '<?= labels('add_handyman') ?>';
        var editHandymanLabel = '<?= labels('edit_handyman', 'Edit Handyman') ?>';
        var storeUrl          = '<?= base_url('partner/handymen/store') ?>';
        var updateUrl         = '<?= base_url('partner/handymen/update') ?>';

        window.openHandymanModal = function (row) {
            var isEdit = !!row;
            $('#addHandymanModalLabel').text(isEdit ? editHandymanLabel : addHandymanLabel);
            $('#add_handyman_form').attr('action', isEdit ? updateUrl : storeUrl);
            $('#handyman_id').val(isEdit ? row.id : '');

            // Profile image: required only on add; col-md-6 on add, col-12 on edit
            var $profileCol = $('#profile_image_col');
            if (isEdit) {
                $profileImageInput.removeAttr('required').removeAttr('data-rules');
                $('#profile_image_label').removeClass('required');
                $profileCol.removeClass('col-md-6').addClass('col-12');
            } else {
                $profileImageInput.attr('required', 'required').attr('data-rules', 'required');
                $('#profile_image_label').addClass('required');
                $profileCol.removeClass('col-12').addClass('col-md-6');
            }

            // Password: required and visible only on add
            var $passwordInput = $('#password');
            var $passwordGroup = $passwordInput.closest('.form-group').closest('.col-md-6');
            if (isEdit) {
                $passwordInput.removeAttr('required').removeAttr('data-rules');
                $passwordGroup.hide();
            } else {
                $passwordInput.attr('required', 'required').attr('data-rules', 'required');
                $passwordGroup.show();
            }

            // Profile image preview
            if (isEdit && row.image_url) {
                $('#handyman_profile_preview_img').attr('src', row.image_url);
                $('#handyman_profile_preview').show();
            } else {
                $('#handyman_profile_preview_img').attr('src', '');
                $('#handyman_profile_preview').hide();
            }

            if (isEdit) {
                // Prefill username per language
                if (row.translations) {
                    $.each(row.translations, function (code, data) {
                        $('#username_' + code).val(data.username || '');
                    });
                }
                // Show default lang tab
                var $defaultTab = $('#handymanLangTabs .handyman-lang-tab').first();
                $defaultTab.trigger('click');

                $('#phone').val(row.phone_number || '');
                $('#email').val(row.email || '');
                $('#address').val(row.address || '');
                $('#salary').val(row.salary || '');
                $('#handyman_status').prop('checked', row.active == 1);

                <?php if (!$single_country_code): ?>
                if ($.fn.select2) { $('#country_code').val(row.country_code).trigger('change'); }
                <?php else: ?>
                $('#country_code').val(row.country_code);
                <?php endif; ?>

                // Prefill custom fields (file inputs can't be prefilled, skip them)
                if (row.custom_field_values) {
                    $.each(row.custom_field_values, function (name, value) {
                        var $field = $('#' + name);
                        if ($field.length && $field.attr('type') !== 'file') {
                            $field.val(value);
                        }
                    });
                }
            }

            $('#addHandymanModal').modal('show');
        }

        // Bootstrap-table events — data-events="handyman_events" on the operations column
        window.handyman_events = {
            'click .handyman-edit': function (e, value, row) {
                e.preventDefault();
                openHandymanModal(row);
            },
            'click .handyman-toggle-status': function (e, value, row) {
                e.preventDefault();
                var $trigger = $(e.currentTarget);

                function doToggle() {
                    var data = new FormData(); data.append('id', row.id);
                    formAjaxRequest('POST', '<?= base_url('partner/handymen/toggle-status') ?>', data, null, $trigger,
                        function (res) { $('#handyman_list').bootstrapTable('refresh'); },
                        function (res) {
                            if (res && res.active_booking) {
                                showHandymanActiveBookingModal(res.message, res.bookings);
                            }
                        }
                    );
                }

                // Deactivating only — activation never needs the active-booking check.
                if (row.active == 1) {
                    checkHandymanActiveBookings(row.id, function (hasActive, bookings, message) {
                        if (hasActive) {
                            showHandymanActiveBookingModal(message, bookings);
                        } else {
                            doToggle();
                        }
                    });
                } else {
                    doToggle();
                }
            },
            'click .handyman-toggle-availability': function (e, value, row) {
                e.preventDefault();
                var data = new FormData(); data.append('id', row.id);
                formAjaxRequest('POST', '<?= base_url('partner/handymen/toggle-availability') ?>', data, null, $(e.currentTarget),
                    function (res) { $('#handyman_list').bootstrapTable('refresh'); }
                );
            },
            'click .handyman-delete': function (e, value, row) {
                e.preventDefault();
                var $trigger = $(e.currentTarget);

                function askConfirmAndDelete() {
                    Swal.fire({
                        title: are_your_sure,
                        text: you_wont_be_able_to_revert_this,
                        icon: 'error',
                        showCancelButton: true,
                        confirmButtonText: yes_proceed,
                        cancelButtonText: cancel,
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            var data = new FormData(); data.append('id', row.id);
                            formAjaxRequest('POST', '<?= base_url('partner/handymen/delete') ?>', data, null, $trigger,
                                function (res) { $('#handyman_list').bootstrapTable('refresh'); },
                                function (res) {
                                    if (res && res.active_booking) {
                                        showHandymanActiveBookingModal(res.message, res.bookings);
                                    }
                                }
                            );
                        }
                    });
                }

                checkHandymanActiveBookings(row.id, function (hasActive, bookings, message) {
                    if (hasActive) {
                        showHandymanActiveBookingModal(message, bookings);
                    } else {
                        askConfirmAndDelete();
                    }
                });
            },
        };

        window.onHandymanFormSaved = function () {
            $('#addHandymanModal').modal('hide');
        };
    });
</script>