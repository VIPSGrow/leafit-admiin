<!-- Main Content -->
<?php
function return_bytes_chat($size_str)
{
    switch (substr($size_str, -1)) {
        case 'M':
        case 'm':
            return (int)$size_str * 1048576;
        case 'K':
        case 'k':
            return (int)$size_str * 1024;
        case 'G':
        case 'g':
            return (int)$size_str * 1073741824;
        default:
            return $size_str;
    }
}
$maxFileSizeStr = ini_get("upload_max_filesize");
$maxFileSizeBytes = return_bytes_chat($maxFileSizeStr);
$maxFileSizeMB = $maxFileSizeBytes / (1024 * 1024);
?>
<style>
    .drag-handle { cursor: grab; }
    .drag-handle:active { cursor: grabbing; }
    .sortable-ghost { opacity: 0.4 !important; background: rgba(var(--secondary-color), 0.05) !important; }
    .sortable-chosen { background: rgba(var(--secondary-color), 0.05) !important; box-shadow: 0 8px 20px rgba(var(--secondary-color), 0.15); }
    .chat-toggle-card {
        transition: all 0.3s ease;
        background-color: #f8f9fa;
        cursor: pointer;
    }
    .chat-toggle-card .custom-switch {
        padding: 0;
        width: 1.75rem;
        height: 1.5rem;
        align-items: unset;
    }
    .chat-toggle-card .custom-switch .custom-control-label::before {
        left: 0;
        top: 0.25rem;
    }
    .chat-toggle-card .custom-switch .custom-control-label::after {
        left: 2px;
        top: calc(0.25rem + 2px);
    }
    .chat-toggle-card:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08) !important;
    }
    .chat-toggle-card.active {
        border-color: var(--primary-color, #007bff) !important;
        background-color: color-mix(in srgb, var(--primary-color, #007bff) 10%, white);
    }
</style>
<div class="main-content">
    <section class="section" id="pill-chat_settings" role="tabpanel">
        <div class="section-header mt-2">
            <h1><?= labels('chat_settings', 'Chat Settings') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('admin/settings/chat-settings') ?>"><?= labels('chat_settings', "Chat Settings") ?></a></div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="card mb-0 shadow-sm">
            <div class="card-body p-0">
                <ul class="nav nav-pills nav-fill" id="chatSettingsTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active py-3" id="general-tab" data-toggle="pill" href="#tab-general" role="tab" aria-controls="tab-general" aria-selected="true">
                            <i class="fas fa-cog mr-2"></i><?= labels('general', 'General') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-3" id="pre-booking-tab" data-toggle="pill" href="#tab-pre-booking" role="tab" aria-controls="tab-pre-booking" aria-selected="false">
                            <i class="fas fa-comments mr-2"></i><?= labels('pre_booking_questions', 'Pre-Booking Questions') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-3" id="customer-post-booking-tab" data-toggle="pill" href="#tab-customer-post-booking" role="tab" aria-controls="tab-customer-post-booking" aria-selected="false">
                            <i class="fas fa-comments mr-2"></i><?= labels('customer_post_booking_questions', 'Customer Post-Booking Questions') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-3" id="provider-post-booking-tab" data-toggle="pill" href="#tab-provider-post-booking" role="tab" aria-controls="tab-provider-post-booking" aria-selected="false">
                            <i class="fas fa-comments mr-2"></i><?= labels('provider_post_booking_questions', 'Provider Post-Booking Questions') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-3" id="customer-admin-support-tab" data-toggle="pill" href="#tab-customer-admin-support" role="tab" aria-controls="tab-customer-admin-support" aria-selected="false">
                            <i class="fas fa-headset mr-2"></i><?= labels('customer_admin_support', 'Customer Admin Support') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-3" id="provider-admin-support-tab" data-toggle="pill" href="#tab-provider-admin-support" role="tab" aria-controls="tab-provider-admin-support" aria-selected="false">
                            <i class="fas fa-headset mr-2"></i><?= labels('provider_admin_support', 'Provider Admin Support') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content mt-4" id="chatSettingsTabContent">

            <!-- General Tab -->
            <div class="tab-pane fade show active" id="tab-general" role="tabpanel" aria-labelledby="general-tab">
                <form method="post" action="<?= base_url('admin/settings/chat-settings/save-settings') ?>">
                    <?= csrf_field() ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">

                            <!-- Message Limits Section -->
                            <div class="mb-5">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-sliders-h text-primary mr-3 fa-lg"></i>
                                    <h3 class="mb-0 font-weight-bolder"><?= labels('message_limits', 'Message Limits') ?></h3>
                                </div>
                                <p class="text-muted mb-4"><?= labels('message_limits_description', 'Configure the maximum limits for files, file sizes, and text length in chat messages.') ?></p>

                                <div class="row">
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="card border-2 h-100" style="background-color: #f8f9fa;">
                                            <div class="card-body p-3">
                                                <label for='maxFilesOrImagesInOneMessage' class="font-weight-bold"><?= labels('maxFilesOrImagesInOneMessage', "Max File Or Images In One message") ?></label>
                                                <small class="d-block text-muted mb-2"><?= labels('note_max_file_or_image_allowed_in_one_message', 'Maximum File or image allowed in one message') ?></small>
                                                <input type='number' min="0" class="form-control" name='maxFilesOrImagesInOneMessage' id='maxFilesOrImagesInOneMessage' value="<?= isset($maxFilesOrImagesInOneMessage) ? $maxFilesOrImagesInOneMessage : '' ?>" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="card border-2 h-100" style="background-color: #f8f9fa;">
                                            <div class="card-body p-3">
                                                <label for='maxFileSizeInMBCanBeSent' class="font-weight-bold"><?= labels('max_file_size_in_mb_can_be_sent', "Max File Size In MB Can be sent") ?></label>
                                                <small class="d-block text-muted mb-2"><?= labels('note_max_size', 'The maximum size') ?> (<?= round($maxFileSizeMB, 2) ?> MB) <?= labels('allowed_sending_files', 'allowed for sending files') ?></small>
                                                <input type='number' class="form-control" max="<?= round($maxFileSizeMB, 2) ?>" name='maxFileSizeInMBCanBeSent' id='maxFileSizeInMBCanBeSent' value="<?= isset($maxFileSizeInMBCanBeSent) ? $maxFileSizeInMBCanBeSent : '' ?>" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="card border-2 h-100" style="background-color: #f8f9fa;">
                                            <div class="card-body p-3">
                                                <label for="maxCharactersInATextMessage" class="font-weight-bold"><?= labels('maxCharactersInATextMessage', "Max Characters in a text message") ?></label>
                                                <small class="d-block text-muted mb-2"><?= labels('note_max_characters_allowed_in_text_message', 'The maximum number of characters allowed in a text message') ?></small>
                                                <input type="number" min="0" class="form-control" name="maxCharactersInATextMessage" id="maxCharactersInATextMessage" value="<?= isset($maxCharactersInATextMessage) ? $maxCharactersInATextMessage : '' ?>" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Chat Features Section -->
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-message text-primary mr-3 fa-lg"></i>
                                    <h3 class="mb-0 font-weight-bolder"><?= labels('chat_features', 'Chat Features') ?></h3>
                                </div>
                                <p class="text-muted mb-4"><?= labels('chat_features_description', 'Enable or disable chat capabilities for different booking stages and file sharing options.') ?></p>
                            <div class="row">
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card border-2 h-100 chat-toggle-card <?= (!empty($allow_pre_booking_chat)) ? 'active' : '' ?>">
                                        <div class="card-body p-3">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col"><strong><?= labels('allow_pre_booking_chat', 'Allow Pre Booking Chat') ?></strong></div>
                                                <div class="col-auto">
                                                    <div class="custom-control custom-switch mb-0">
                                                        <input type="checkbox" class="custom-control-input chat-toggle" id="allow_pre_booking_chat" name="allow_pre_booking_chat" <?= (!empty($allow_pre_booking_chat)) ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="allow_pre_booking_chat"></label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row no-gutters mt-2">
                                                <div class="col"><small class="text-muted"><?= labels('allow_pre_booking_chat_hint', 'Let users chat with providers before placing a booking.') ?></small></div>
                                            </div>
                                            <div class="row no-gutters mt-2">
                                                <div class="col">
                                                    <span class="badge badge-pill chat-toggle-badge <?= (!empty($allow_pre_booking_chat)) ? 'badge-success' : 'badge-secondary' ?>"><?= (!empty($allow_pre_booking_chat)) ? labels('enabled', 'Enabled') : labels('disabled', 'Disabled') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card border-2 h-100 chat-toggle-card <?= (!empty($allow_post_booking_chat)) ? 'active' : '' ?>">
                                        <div class="card-body p-3">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col"><strong><?= labels('allow_post_booking_chat', 'Allow Post Booking Chat') ?></strong></div>
                                                <div class="col-auto">
                                                    <div class="custom-control custom-switch mb-0">
                                                        <input type="checkbox" class="custom-control-input chat-toggle" id="allow_post_booking_chat" name="allow_post_booking_chat" <?= (!empty($allow_post_booking_chat)) ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="allow_post_booking_chat"></label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row no-gutters mt-2">
                                                <div class="col"><small class="text-muted"><?= labels('allow_post_booking_chat_hint', 'Let users chat with providers after a booking is placed.') ?></small></div>
                                            </div>
                                            <div class="row no-gutters mt-2">
                                                <div class="col">
                                                    <span class="badge badge-pill chat-toggle-badge <?= (!empty($allow_post_booking_chat)) ? 'badge-success' : 'badge-secondary' ?>"><?= (!empty($allow_post_booking_chat)) ? labels('enabled', 'Enabled') : labels('disabled', 'Disabled') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card border-2 h-100 chat-toggle-card <?= (!empty($enable_chat_image_upload)) ? 'active' : '' ?>">
                                        <div class="card-body p-3">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col"><strong><?= labels('enable_chat_image_upload', "Enable Chat Image Upload") ?></strong></div>
                                                <div class="col-auto">
                                                    <div class="custom-control custom-switch mb-0">
                                                        <input type="checkbox" class="custom-control-input chat-toggle" id="enable_chat_image_upload" name="enable_chat_image_upload" <?= (!empty($enable_chat_image_upload)) ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="enable_chat_image_upload"></label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row no-gutters mt-2">
                                                <div class="col"><small class="text-muted"><?= labels('enable_chat_image_upload_hint', 'Allow users to send images in chat messages.') ?></small></div>
                                            </div>
                                            <div class="row no-gutters mt-2">
                                                <div class="col">
                                                    <span class="badge badge-pill chat-toggle-badge <?= (!empty($enable_chat_image_upload)) ? 'badge-success' : 'badge-secondary' ?>"><?= (!empty($enable_chat_image_upload)) ? labels('enabled', 'Enabled') : labels('disabled', 'Disabled') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card border-2 h-100 chat-toggle-card <?= (!empty($enable_chat_file_upload)) ? 'active' : '' ?>">
                                        <div class="card-body p-3">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col"><strong><?= labels('enable_chat_file_upload', "Enable Chat File Upload") ?></strong></div>
                                                <div class="col-auto">
                                                    <div class="custom-control custom-switch mb-0">
                                                        <input type="checkbox" class="custom-control-input chat-toggle" id="enable_chat_file_upload" name="enable_chat_file_upload" <?= (!empty($enable_chat_file_upload)) ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="enable_chat_file_upload"></label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row no-gutters mt-2">
                                                <div class="col"><small class="text-muted"><?= labels('enable_chat_file_upload_hint', 'Allow users to send files in chat messages.') ?></small></div>
                                            </div>
                                            <div class="row no-gutters mt-2">
                                                <div class="col">
                                                    <span class="badge badge-pill chat-toggle-badge <?= (!empty($enable_chat_file_upload)) ? 'badge-success' : 'badge-secondary' ?>"><?= (!empty($enable_chat_file_upload)) ? labels('enabled', 'Enabled') : labels('disabled', 'Disabled') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>

                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="row mt-3">
                        <div class="col-lg-12 d-flex justify-content-end">
                            <a href="<?= base_url('admin/settings/system-settings') ?>" class="btn btn-light mr-2"><?= labels('cancel', 'Cancel') ?></a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-2"></i><?= labels('save_changes', 'Save Changes') ?></button>
                        </div>
                    </div>
                </form>
            </div>

            <?php
            $sorted_languages = sort_languages_with_default_first($languages);
            $default_lang_code = 'en';
            foreach ($sorted_languages as $lang) {
                if ($lang['is_default'] == 1) {
                    $default_lang_code = $lang['code'];
                    break;
                }
            }

            echo view('backend/partials/chat_questions/panel', [
                'tab_id'            => 'pre-booking',
                'tab_class'         => 'tab-pane fade',
                'question_type'     => 'pre_booking',
                'icon'              => 'comments',
                'title'             => labels('pre_booking_questions', 'Pre-Booking Questions'),
                'description'       => labels('pre_booking_questions_description', 'Quick suggestions shown to users before they make a booking.'),
                'sorted_languages'  => $sorted_languages,
                'default_lang_code' => $default_lang_code,
            ]);

            echo view('backend/partials/chat_questions/panel', [
                'tab_id'            => 'customer-post-booking',
                'tab_class'         => 'tab-pane fade',
                'question_type'     => 'customer_post_booking',
                'icon'              => 'comments',
                'title'             => labels('customer_post_booking_questions', 'Customer Post-Booking Questions'),
                'description'       => labels('customer_post_booking_questions_description', 'Common queries shown to customers after a successful booking.'),
                'sorted_languages'  => $sorted_languages,
                'default_lang_code' => $default_lang_code,
            ]);

            echo view('backend/partials/chat_questions/panel', [
                'tab_id'            => 'provider-post-booking',
                'tab_class'         => 'tab-pane fade',
                'question_type'     => 'provider_post_booking',
                'icon'              => 'comments',
                'title'             => labels('provider_post_booking_questions', 'Provider Post-Booking Questions'),
                'description'       => labels('provider_post_booking_questions_description', 'Prefilled questions shown to providers after a booking is placed.'),
                'sorted_languages'  => $sorted_languages,
                'default_lang_code' => $default_lang_code,
            ]);

            echo view('backend/partials/chat_questions/panel', [
                'tab_id'            => 'customer-admin-support',
                'tab_class'         => 'tab-pane fade',
                'question_type'     => 'customer_admin_support',
                'icon'              => 'headset',
                'title'             => labels('customer_admin_support_questions', 'Customer Admin Support Questions'),
                'description'       => labels('customer_admin_support_questions_description', 'Categorized issues when customers request human admin assistance.'),
                'sorted_languages'  => $sorted_languages,
                'default_lang_code' => $default_lang_code,
            ]);

            echo view('backend/partials/chat_questions/panel', [
                'tab_id'            => 'provider-admin-support',
                'tab_class'         => 'tab-pane fade',
                'question_type'     => 'provider_admin_support',
                'icon'              => 'headset',
                'title'             => labels('provider_admin_support_questions', 'Provider Admin Support Questions'),
                'description'       => labels('provider_admin_support_questions_description', 'Categorized issues when providers request human admin assistance.'),
                'sorted_languages'  => $sorted_languages,
                'default_lang_code' => $default_lang_code,
            ]);
            ?>

        </div>
    </section>
</div>

<script>
    var defaultLang = '<?= $default_lang_code ?>';

    // Language tab switching within question sections
    $(document).on('click', '.lang-tab', function() {
        var lang = $(this).data('lang');
        var section = $(this).data('section');
        $(this).closest('[data-question-section]').find('.lang-tab-underline').css('width', '0');
        $(this).find('.lang-tab-underline').css('width', '100%');
        $(this).closest('[data-question-section]').find('.lang-tab-text').removeClass('text-primary font-weight-bold').addClass('text-muted');
        $(this).find('.lang-tab-text').removeClass('text-muted').addClass('text-primary font-weight-bold');
        $('.lang-input-group[data-section="' + section + '"]').hide();
        $('.lang-input-group[data-section="' + section + '"][data-lang="' + lang + '"]').show();
    });

    // Add or Update question via AJAX
    $(document).on('click', '.btn-add-question', function() {
        var $btn = $(this);
        var section = $btn.data('section');
        var questionType = $btn.data('type');
        var editId = $btn.data('edit-id');

        var translations = {};
        var hasDefault = false;
        $('.question-input[data-section="' + section + '"]').each(function() {
            var lang = $(this).data('lang');
            var val = $.trim($(this).val());
            if (val) {
                translations[lang] = val;
                if (lang === defaultLang) hasDefault = true;
            }
        });

        if (!hasDefault) {
            document.getElementById(section + '_input_' + defaultLang).focus();
            return;
        }

        var isEdit = editId && editId > 0;
        var url = isEdit
            ? '<?= base_url("admin/settings/chat-settings/update-question") ?>'
            : '<?= base_url("admin/settings/chat-settings/add-question") ?>';
        var payload = isEdit
            ? { id: editId, translations: translations }
            : { question_type: questionType, translations: translations };

        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: url,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function(res) {
                if (res.error === false) {
                    iziToast.success({ title: '', message: res.message, position: 'topRight' });
                    $('#' + CSS.escape(section + 'QuestionsTable')).bootstrapTable('refresh');
                    resetEditMode(section);
                } else {
                    iziToast.error({ title: '', message: res.message, position: 'topRight' });
                }
            },
            error: function() {
                iziToast.error({ title: '', message: '<?= labels("something_went_wrong", "Something went wrong") ?>', position: 'topRight' });
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Cancel edit — reset inputs and switch back to Add mode
    $(document).on('click', '.btn-cancel-edit', function() {
        resetEditMode($(this).data('section'));
    });

    function resetEditMode(section) {
        $('.question-input[data-section="' + section + '"]').val('');
        var $btns = $('.btn-add-question[data-section="' + section + '"]');
        $btns.data('edit-id', '').attr('data-edit-id', '');
        $btns.find('.btn-action-icon').addClass('fa-save');
        $btns.find('.btn-action-text').text('<?= labels("save", "Save") ?>');
        $('.btn-cancel-edit[data-section="' + section + '"]').addClass('d-none');
        document.getElementById(section + '_input_' + defaultLang).focus();
    }

    // Enter key triggers Add/Save
    $(document).on('keypress', '.question-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $(this).closest('.lang-input-group').find('.btn-add-question').click();
        }
    });

    // Bootstrap-table events for operations column
    window.chat_question_events = {
        'click .btn-edit-question': function(e, value, row) {
            var $table = $(e.currentTarget).closest('table');
            var section = $table.attr('id').replace('QuestionsTable', '');
            var translations = row.translations || {};

            // Prefill all language inputs (fall back to base question for default language)
            $('.question-input[data-section="' + section + '"]').each(function() {
                var lang = $(this).data('lang');
                $(this).val(translations[lang] || (lang === defaultLang ? row.question : '') || '');
            });

            // Switch Add buttons to Save mode
            var $btns = $('.btn-add-question[data-section="' + section + '"]');
            $btns.data('edit-id', row.id).attr('data-edit-id', row.id);
            $btns.find('.btn-action-text').text('<?= labels("save", "Save") ?>');
            $('.btn-cancel-edit[data-section="' + section + '"]').removeClass('d-none');

            // Focus default language input
            document.getElementById(section + '_input_' + defaultLang).focus();
        },
        'click .btn-delete-question': function(e, value, row) {
            var $btn = $(e.currentTarget);
            var $table = $btn.closest('table');

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: '<?= base_url("admin/settings/chat-settings/delete-question") ?>',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: row.id }),
                success: function(res) {
                    if (res.error === false) {
                        iziToast.success({ title: '', message: res.message, position: 'topRight' });
                        $table.bootstrapTable('refresh');
                    } else {
                        iziToast.error({ title: '', message: res.message, position: 'topRight' });
                        $btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
                    }
                },
                error: function() {
                    iziToast.error({ title: '', message: '<?= labels("something_went_wrong", "Something went wrong") ?>', position: 'topRight' });
                    $btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
                }
            });
        }
    };

    // Toggle card active state and badge on change
    var enabledText = '<?= labels("enabled", "Enabled") ?>';
    var disabledText = '<?= labels("disabled", "Disabled") ?>';
    $(document).on('change', '.chat-toggle', function() {
        var $card = $(this).closest('.chat-toggle-card');
        var $badge = $card.find('.chat-toggle-badge');
        if (this.checked) {
            $card.addClass('active');
            $badge.removeClass('badge-secondary').addClass('badge-success').text(enabledText);
        } else {
            $card.removeClass('active');
            $badge.removeClass('badge-success').addClass('badge-secondary').text(disabledText);
        }
    });

    // Make the entire card clickable for toggling
    $(document).on('click', '.chat-toggle-card', function(e) {
        // If the click was directly on the toggle or label, let the browser handle it
        if ($(e.target).closest('.custom-control').length) {
            return;
        }
        var $checkbox = $(this).find('.chat-toggle');
        $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
    });

    // --- Sortable drag-and-drop reorder with manual save ---
    var cqSortableInstances = {};
    var cqTableIds = ['pre-bookingQuestionsTable', 'customer-post-bookingQuestionsTable', 'provider-post-bookingQuestionsTable', 'customer-admin-supportQuestionsTable', 'provider-admin-supportQuestionsTable'];

    function cqInitSortable(tableId) {
        var tbody = document.querySelector('#' + CSS.escape(tableId) + ' tbody');
        if (!tbody || cqSortableInstances[tableId]) return;

        cqSortableInstances[tableId] = new Sortable(tbody, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            forceFallback: true,
            fallbackOnBody: true,
            delay: 0,
            delayOnTouchOnly: true,
            touchStartThreshold: 3,
        });
    }

    function cqCheckUpdateOrderButton(tableId) {
        var rowCount = $('#' + CSS.escape(tableId)).bootstrapTable('getData').length;
        var $btn = $(".cq-update-order-btn[data-table-id='" + tableId + "']");
        if (rowCount <= 1) {
            $btn.addClass('d-none');
        } else {
            $btn.removeClass('d-none');
        }
    }

    // Init sortable + toggle button after bootstrap-table loads data
    cqTableIds.forEach(function(tid) {
        $('#' + CSS.escape(tid)).on('post-body.bs.table', function() {
            if (cqSortableInstances[tid]) {
                cqSortableInstances[tid].destroy();
                cqSortableInstances[tid] = null;
            }
            cqInitSortable(tid);
            cqCheckUpdateOrderButton(tid);
        });
    });

    // "Update Order" button click handler
    $(document).on('click', '.cq-update-order-btn', function() {
        var $btn = $(this);
        var tableId = $btn.data('table-id');

        var ids = [];
        $('#' + CSS.escape(tableId) + ' tbody tr').each(function() {
            var id = $(this).data('uniqueid');
            if (id) ids.push(id);
        });

        if (ids.length === 0) return;

        var originalHtml = $btn.html();
        $btn.prop('disabled', true).removeClass('btn-primary').addClass('btn-secondary');
        $btn.html('<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>');

        $.ajax({
            url: '<?= base_url("admin/settings/chat-settings/reorder-questions") ?>',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ ids: ids }),
            success: function(res) {
                if (res.error === false) {
                    iziToast.success({ title: '', message: res.message, position: 'topRight' });
                } else {
                    iziToast.error({ title: '', message: res.message, position: 'topRight' });
                }
                $('#' + CSS.escape(tableId)).bootstrapTable('refresh');
            },
            error: function() {
                iziToast.error({ title: '', message: '<?= labels("something_went_wrong", "Something went wrong") ?>', position: 'topRight' });
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-primary');
                $btn.html(originalHtml);
            }
        });
    });
</script>
