<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('preview_of_templates', "Preview Of templates") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('admin/settings/notification-settings') ?>"><?= labels('notification_settings', "Notification Settings") ?></a></div>
                <div class="breadcrumb-item "><?= labels('preview_of_templates', "Preview Of templates") ?></div>
            </div>
        </div>
        <?php $data = get_settings('general_settings', true); ?>
        <div class="row">
            <?php if (!empty($email_template)): ?>
                <!-- Email Template - Left Side (Full Height) -->
                <div class="col-md-6">
                    <div class="card email-preview-card">
                        <div class="row  m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition"><?= labels('email_template', "Email Template") ?></div>
                            </div>
                            <div class="col d-flex justify-content-end  mt-4 ">
                                <div class="text-center">
                                    <a href="javascript:void(0);" id="editEmailTemplateBtn" class="btn btn-primary">
                                        <?= labels('edit', 'Edit') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="email-header">
                                <div class="email-subject">
                                    <strong><?= $email_template['subject']; ?></strong>
                                </div>
                                <div class="email-from">
                                    <span><strong> <?= get_company_title_with_fallback($data); ?></strong> &lt;<?= $data['support_email'] ?>&gt;</span>
                                    <span><?= labels('to', "To") ?> xxxxx</span>
                                </div>
                            </div>
                            <div class="email-content">
                                <?= strip_tags(htmlspecialchars_decode(stripslashes($email_template['template'])), '<p><br>')
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif ?>

            <!-- SMS and Notification Templates - Right Side (Stacked Vertically) -->
            <div class="col-md-6">
                <?php if (!empty($sms_template)): ?>
                    <!-- SMS Template - Top Card -->
                    <div class="card sms-preview-card mb-3">
                        <div class="row  m-0 border_bottom_for_cards">
                            <div class="col-auto">
                                <div class="toggleButttonPostition"><?= labels('sms_template', "SMS Template") ?></div>
                            </div>
                            <div class="col d-flex justify-content-end  mt-4 ">
                                <div class="text-center">
                                    <a href="javascript:void(0);" id="editSMSTemplateBtn" class="btn btn-primary">
                                        <?= labels('edit', 'Edit') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <pre class="sms-template-preview p-3 sms-content"><?= htmlspecialchars($sms_template['template']) ?></pre>
                        </div>
                    </div>
                <?php endif ?>
                <!-- Notification Template - Bottom Card -->
                <div class="card notification-preview-card">
                    <div class="row  m-0 border_bottom_for_cards">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('notification_template', "Notification Template") ?></div>
                        </div>
                        <div class="col d-flex justify-content-end  mt-4 ">
                            <div class="text-center">
                                <a href="javascript:void(0);" id="editNotificationTemplateBtn" class="btn btn-primary">
                                    <?= labels('edit', 'Edit') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="notification-header">
                            <div class="notification-title mb-2">
                                <?php
                                // Get default language translation if available, otherwise use base template
                                $default_lang_code = '';
                                foreach ($languages as $lang) {
                                    if ($lang['is_default'] == 1) {
                                        $default_lang_code = $lang['code'];
                                        break;
                                    }
                                }
                                $display_title = '';
                                if (!empty($default_lang_code) && isset($notification_translations[$default_lang_code]['title']) && !empty($notification_translations[$default_lang_code]['title'])) {
                                    $display_title = $notification_translations[$default_lang_code]['title'];
                                } elseif (!empty($notification_template['title'])) {
                                    $display_title = $notification_template['title'];
                                }
                                ?>
                                <strong><?= !empty($display_title) ? htmlspecialchars($display_title) : labels('no_title', 'No Title') ?></strong>
                            </div>
                        </div>
                        <div class="notification-content">
                            <?php
                            // Get default language translation if available, otherwise use base template
                            $display_body = '';
                            if (!empty($default_lang_code) && isset($notification_translations[$default_lang_code]['body']) && !empty($notification_translations[$default_lang_code]['body'])) {
                                $display_body = $notification_translations[$default_lang_code]['body'];
                            } elseif (!empty($notification_template['body'])) {
                                $display_body = $notification_template['body'];
                            }
                            ?>
                            <pre class="notification-template-preview p-3 notification-content-text"><?= !empty($display_body) ? htmlspecialchars($display_body) : labels('no_content', 'No Content') ?></pre>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </section>
</div>
<?php if (!empty($email_template)): ?>
    <div class="modal fade" id="edit_email_modal" tabindex="-1" aria-labelledby="edit_email_modal_thing" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <?= form_open_multipart(base_url('admin/settings/edit_email_template_operation'), array('class' => 'form-submit-event')) ?>
                <div class="modal-header m-0 p-0" style="border-bottom: solid 1px #e5e6e9;">
                    <div class="row pl-3">
                        <div class="col">
                            <div class="toggleButttonPostition"><?= labels('edit_email_template', 'Edit Email Template') ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Language Tabs for Email Modal -->
                        <?php
                        // Sort languages so default language appears first for better UI (needed even for single language)
                        $sorted_languages_email = sort_languages_with_default_first($languages);
                        $current_language_email = '';
                        foreach ($sorted_languages_email as $index => $language) {
                            if ($language['is_default'] == 1) {
                                $current_language_email = $language['code'];
                            }
                        }
                        ?>
                        <?php if (count($languages) > 1): ?>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                        <?php
                                        foreach ($sorted_languages_email as $index => $language) {
                                        ?>
                                            <div class="language-option-email position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                id="language-email-<?= $language['code'] ?>"
                                                data-language="<?= $language['code'] ?>"
                                                style="cursor: pointer; padding: 0.5rem 0;">
                                                <span class="language-text-email px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                    style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                    <?= $language['language'] ?><?= $language['is_default'] ? ' (Default)' : '' ?>
                                                </span>
                                                <div class="language-underline-email"
                                                    style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>


                        <div class="row">
                            <input type="hidden" name="template_id" value="<?= $email_template['id'] ?>" />

                            <!-- Type field - readonly, similar to edit email template page -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email_type_display" class="required"><?= labels('type', "Type") ?></label>
                                    <!-- Readonly input showing translated label (not submitted in form) -->
                                    <input type="text" id="email_type_display" class="form-control"
                                        value="<?= htmlspecialchars($email_typeLabel ?? $email_template['type'] ?? '') ?>"
                                        placeholder="<?= labels('email_type', 'Email type') ?>" readonly>
                                    <!-- Hidden field with actual type value (submitted in form) -->
                                    <input type="hidden" name="email_type" value="<?= htmlspecialchars($email_template['type'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Language-specific subject fields -->
                            <!-- <div class="row mt-3"> -->
                            <?php
                            // Use sorted languages for subject fields
                            foreach ($sorted_languages_email as $index => $language) {
                                // Get translation data for this language
                                $translation_data = isset($email_translations[$language['code']]) ? $email_translations[$language['code']] : [];
                                $subject_value = !empty($translation_data['subject']) ? $translation_data['subject'] : ($language['is_default'] == 1 ? $email_template['subject'] : '');
                            ?>
                                <div class="col-md-6" id="subjectDiv-email-<?= $language['code'] ?>" <?= $language['code'] == $current_language_email ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="form-group">
                                        <label for="subject_email_<?= $language['code'] ?>" class="required"><?= labels('subject', 'Subject') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <input id="subject_email_<?= $language['code'] ?>" class="form-control" type="text" name="translations[<?= $language['code'] ?>][subject]" placeholder="<?= labels('enter_subject_here', 'Enter subject here') ?>" value="<?= $subject_value ?>">
                                    </div>
                                </div>
                            <?php } ?>
                            <!-- </div> -->
                            <!-- Parameters section - dynamically generated based on database -->
                            <div class="col-md-12 parameters-section">
                                <label><?= labels('parameters', "Parameters") ?></label>
                                <div class="form-group">
                                    <?php if (!empty($email_parameters) && is_array($email_parameters)): ?>
                                        <!-- Dynamically generate parameter buttons based on database data -->
                                        <div class="mb-2">
                                            <?php foreach ($email_parameters as $paramKey): ?>
                                                <?php
                                                // Get label for this parameter, or use the key as fallback
                                                $paramLabel = isset($parameterLabels[$paramKey])
                                                    ? $parameterLabels[$paramKey]
                                                    : ucwords(str_replace('_', ' ', $paramKey));
                                                ?>
                                                <button type="button" class="btn btn-primary btn-icon icon-left mb-2" data-variable="<?= htmlspecialchars($paramKey) ?>">
                                                    <?= htmlspecialchars($paramLabel) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Show message if no parameters are defined for this template -->
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <?= labels('no_parameters_defined', 'No parameters are defined for this template. Parameters will be automatically extracted when you save the template.') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= labels('bcc', "BCC") ?></label>
                                    <input id="bcc" style="border-radius: 0.25rem!important" class="w-100" type="text" value="<?= ($email_template['bcc']) ?>" name="bcc[]" placeholder="press enter to add bcc">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= labels('cc', "CC") ?></label>
                                    <input id="cc" style="border-radius: 0.25rem" class="w-100" type="text" name="cc[]" value="<?= $email_template['cc'] ?>" placeholder="press enter to add cc">
                                </div>
                            </div>
                            <!-- Language-specific template fields -->
                            <!-- <div class="row"> -->
                            <?php
                            foreach ($sorted_languages_email as $index => $language) {
                                // Get translation data for this language
                                $translation_data = isset($email_translations[$language['code']]) ? $email_translations[$language['code']] : [];
                                $template_value = !empty($translation_data['template']) ? $translation_data['template'] : ($language['is_default'] == 1 ? $email_template['template'] : '');
                            ?>
                                <div class="col-md-12" id="templateDiv-email-<?= $language['code'] ?>" <?= $language['code'] == $current_language_email ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="form-group">
                                        <label for="template_email_<?= $language['code'] ?>" class="required"><?= labels('template', 'Template') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <textarea rows="10" id="template_email_<?= $language['code'] ?>" class="form-control h-50 summernotes custome_reset template-editor-email" name="translations[<?= $language['code'] ?>][template]"><?= $template_value ?></textarea>
                                    </div>
                                </div>
                            <?php } ?>
                            <!-- </div> -->
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn  bg-new-primary submit_btn"><?= labels('save_changes', 'Save') ?></button>
                    <?= form_close() ?>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>
<?php if (!empty($sms_template)): ?>
    <div class="modal fade" id="edit_sms_modal" tabindex="-1" aria-labelledby="edit_sms_modal_thing" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" class="form-submit-event" action="<?= base_url('admin/settings/edit-sms-templates') ?>">
                    <div class="modal-header m-0 p-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="row pl-3">
                            <div class="col">
                                <div class="toggleButttonPostition"><?= labels('edit_sms_template', 'Edit SMS Template') ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-body">
                        <!-- Language Tabs for SMS Modal -->
                        <?php
                        // Sort languages so default language appears first for better UI (needed even for single language)
                        $sorted_languages_sms = sort_languages_with_default_first($languages);
                        $current_language_sms = '';
                        foreach ($sorted_languages_sms as $index => $language) {
                            if ($language['is_default'] == 1) {
                                $current_language_sms = $language['code'];
                            }
                        }
                        ?>
                        <?php if (count($languages) > 1): ?>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                        <?php
                                        foreach ($sorted_languages_sms as $index => $language) {
                                        ?>
                                            <div class="language-option-sms position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                id="language-sms-<?= $language['code'] ?>"
                                                data-language="<?= $language['code'] ?>"
                                                style="cursor: pointer; padding: 0.5rem 0;">
                                                <span class="language-text-sms px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                    style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                    <?= $language['language'] ?><?= $language['is_default'] ? ' (Default)' : '' ?>
                                                </span>
                                                <div class="language-underline-sms"
                                                    style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <input type="hidden" name="template_id" value="<?= $sms_template['id'] ?>" />
                            <!-- Type field - readonly, similar to edit email template page -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sms_type_display" class="required"><?= labels('type', "Type") ?></label>
                                    <!-- Readonly input showing translated label (not submitted in form) -->
                                    <input type="text" id="sms_type_display" class="form-control"
                                        value="<?= htmlspecialchars($sms_typeLabel ?? $sms_template['type'] ?? '') ?>"
                                        placeholder="<?= labels('sms_type', 'SMS type') ?>" readonly>
                                    <!-- Hidden field with actual type value (submitted in form) -->
                                    <input type="hidden" name="type" value="<?= htmlspecialchars($sms_template['type'] ?? '') ?>">
                                </div>
                            </div>
                            <!-- Language-specific title fields -->
                            <!-- <div class="row mt-3"> -->
                            <?php
                            // Use sorted languages for title fields
                            foreach ($sorted_languages_sms as $index => $language) {
                                // Get translation data for this language
                                $translation_data = isset($sms_translations[$language['code']]) ? $sms_translations[$language['code']] : [];
                                $title_value = !empty($translation_data['title']) ? $translation_data['title'] : ($language['is_default'] == 1 ? $sms_template['title'] : '');
                            ?>
                                <div class="col-md-6" id="titleDiv-sms-<?= $language['code'] ?>" <?= $language['code'] == $current_language_sms ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="form-group">
                                        <label for="title_sms_<?= $language['code'] ?>" class="required"><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <input id="title_sms_<?= $language['code'] ?>" class="form-control" type="text" name="translations[<?= $language['code'] ?>][title]" placeholder="<?= labels('enter_title_here', 'Enter title here') ?>" value="<?= $title_value ?>">
                                    </div>
                                </div>
                            <?php } ?>
                            <!-- </div> -->
                            <!-- Parameters section - dynamically generated based on database -->
                            <div class="col-md-12 sms_parameters-section">
                                <label><?= labels('parameters', "Parameters") ?></label>
                                <div class="form-group">
                                    <?php if (!empty($sms_parameters) && is_array($sms_parameters)): ?>
                                        <!-- Dynamically generate parameter buttons based on database data -->
                                        <div class="mb-2">
                                            <?php foreach ($sms_parameters as $paramKey): ?>
                                                <?php
                                                // Get label for this parameter, or use the key as fallback
                                                $paramLabel = isset($parameterLabels[$paramKey])
                                                    ? $parameterLabels[$paramKey]
                                                    : ucwords(str_replace('_', ' ', $paramKey));
                                                ?>
                                                <button type="button" class="btn btn-primary btn-icon icon-left mb-2" data-variable="<?= htmlspecialchars($paramKey) ?>">
                                                    <?= htmlspecialchars($paramLabel) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Show message if no parameters are defined for this template -->
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <?= labels('no_parameters_defined', 'No parameters are defined for this template. Parameters will be automatically extracted when you save the template.') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Gateway Template IDs (optional) - same as edit_sms_template page -->
                            <?php
                            $sms_template_based_gateways = $sms_template_based_gateways ?? ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91'];
                            $sms_gateway_template_ids = $sms_gateway_template_ids ?? [];
                            ?>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <label><?= labels('gateway_template_ids', 'Gateway Template IDs') ?> <span class="text-muted">(<?= labels('optional', 'optional') ?>)</span></label>
                                    <div class="alert alert-light border mb-2 py-2 px-3" role="region">
                                        <small class="text-body d-block mb-1">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <?= labels('gateway_template_ids_help', 'For gateways that use verified template IDs (e.g. Fast2SMS DLT, MSG91), enter the template ID from your provider. Leave empty for gateways that send the full message (e.g. Twilio).') ?>
                                        </small>
                                    </div>
                                </div>
                                <?php foreach ($sms_template_based_gateways as $gw_key => $gw_label): ?>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="gateway_template_ids_<?= htmlspecialchars($gw_key) ?>"><?= htmlspecialchars($gw_label) ?> <?= labels('template_id', 'Template ID') ?></label>
                                        <input type="text" id="gateway_template_ids_<?= htmlspecialchars($gw_key) ?>" name="gateway_template_ids[<?= htmlspecialchars($gw_key) ?>]" class="form-control" placeholder="<?= labels('enter_template_id', 'Enter template ID') ?>" value="<?= htmlspecialchars($sms_gateway_template_ids[$gw_key] ?? '') ?>" maxlength="64">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Language-specific template fields -->
                            <!-- <div class="row"> -->
                            <?php
                            foreach ($sorted_languages_sms as $index => $language) {
                                // Get translation data for this language
                                $translation_data = isset($sms_translations[$language['code']]) ? $sms_translations[$language['code']] : [];
                                $template_value = !empty($translation_data['template']) ? $translation_data['template'] : ($language['is_default'] == 1 ? $sms_template['template'] : '');
                            ?>
                                <div class="col-md-12" id="templateDiv-sms-<?= $language['code'] ?>" <?= $language['code'] == $current_language_sms ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="form-group">
                                        <label for="template_sms_<?= $language['code'] ?>" class="required"><?= labels('template', 'Template') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <textarea id="template_sms_<?= $language['code'] ?>" rows="50" placeholder="<?= labels('enter_message_here', 'Enter Message Here') ?>" class="form-control template-textarea-sms" name="translations[<?= $language['code'] ?>][template]"><?= $template_value ?></textarea>
                                    </div>
                                </div>
                            <?php } ?>
                            <!-- </div> -->
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn  bg-new-primary submit_btn"><?= labels('save_changes', 'Save') ?></button>
                        <?= form_close() ?>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                    </div>

            </div>
        </div>
    </div>
<?php endif; ?>
<div class="modal fade" id="edit_notification_modal" tabindex="-1" aria-labelledby="edit_notification_modal_thing" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" class="form-submit-event" action="<?= base_url('admin/settings/edit-notification-template-operation') ?>">
                <div class="modal-header m-0 p-0" style="border-bottom: solid 1px #e5e6e9;">
                    <div class="row pl-3">
                        <div class="col">
                            <div class="toggleButttonPostition"><?= labels('edit_notification_template', 'Edit Notification Template') ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <!-- Language Tabs for Notification Modal -->
                    <?php
                    // Sort languages so default language appears first for better UI (needed even for single language)
                    $sorted_languages_notification = sort_languages_with_default_first($languages);
                    $current_language_notification = '';
                    foreach ($sorted_languages_notification as $index => $language) {
                        if ($language['is_default'] == 1) {
                            $current_language_notification = $language['code'];
                        }
                    }
                    ?>
                    <?php if (count($languages) > 1): ?>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($sorted_languages_notification as $index => $language) {
                                    ?>
                                        <div class="language-option-notification position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-notification-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span class="language-text-notification px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?><?= $language['is_default'] ? ' (Default)' : '' ?>
                                            </span>
                                            <div class="language-underline-notification"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <input type="hidden" name="template_id" value="<?= !empty($notification_template['id']) ? $notification_template['id'] : '' ?>" />
                        <!-- Type field - readonly, similar to edit email template page -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="notification_type_display" class="required"><?= labels('type', "Type") ?></label>
                                <!-- Readonly input showing translated label (not submitted in form) -->
                                <input type="text" id="notification_type_display" class="form-control"
                                    value="<?= htmlspecialchars($notification_typeLabel ?? $notification_template['event_key'] ?? '') ?>"
                                    placeholder="<?= labels('notification_type', 'Notification type') ?>" readonly>
                                <!-- Hidden field with actual event_key value (submitted in form) -->
                                <input type="hidden" name="event_key" value="<?= htmlspecialchars($notification_template['event_key'] ?? '') ?>">
                            </div>
                        </div>
                        <!-- Language-specific title fields -->
                        <?php
                        // Use sorted languages for title fields
                        foreach ($sorted_languages_notification as $index => $language) {
                            // Get translation data for this language
                            $translation_data = isset($notification_translations[$language['code']]) ? $notification_translations[$language['code']] : [];
                            $title_value = !empty($translation_data['title']) ? $translation_data['title'] : ($language['is_default'] == 1 && !empty($notification_template['title']) ? $notification_template['title'] : '');
                        ?>
                            <div class="col-md-6" id="titleDiv-notification-<?= $language['code'] ?>" <?= $language['code'] == $current_language_notification ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="form-group">
                                    <label for="title_notification_<?= $language['code'] ?>" class="required"><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <!-- Use title[language_code] format to match controller expectations -->
                                    <input id="title_notification_<?= $language['code'] ?>" class="form-control" type="text" name="title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter title here') ?>" value="<?= $title_value ?>">
                                </div>
                            </div>
                        <?php } ?>
                        <!-- Parameters section - dynamically generated based on database -->
                        <div class="col-md-12 notification_parameters-section">
                            <label><?= labels('parameters', "Parameters") ?></label>
                            <div class="form-group">
                                <?php if (!empty($notification_parameters) && is_array($notification_parameters)): ?>
                                    <!-- Dynamically generate parameter buttons based on database data -->
                                    <div class="mb-2">
                                        <?php foreach ($notification_parameters as $paramKey): ?>
                                            <?php
                                            // Get label for this parameter, or use the key as fallback
                                            $paramLabel = isset($parameterLabels[$paramKey])
                                                ? $parameterLabels[$paramKey]
                                                : ucwords(str_replace('_', ' ', $paramKey));
                                            ?>
                                            <button type="button" class="btn btn-primary btn-icon icon-left mb-2" data-variable="<?= htmlspecialchars($paramKey) ?>">
                                                <?= htmlspecialchars($paramLabel) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Show message if no parameters are defined for this template -->
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <?= labels('no_parameters_defined', 'No parameters are defined for this template. Parameters will be automatically extracted when you save the template.') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Language-specific body/template fields -->
                        <?php
                        foreach ($sorted_languages_notification as $index => $language) {
                            // Get translation data for this language
                            $translation_data = isset($notification_translations[$language['code']]) ? $notification_translations[$language['code']] : [];
                            $body_value = !empty($translation_data['body']) ? $translation_data['body'] : ($language['is_default'] == 1 && !empty($notification_template['body']) ? $notification_template['body'] : '');
                        ?>
                            <div class="col-md-12" id="bodyDiv-notification-<?= $language['code'] ?>" <?= $language['code'] == $current_language_notification ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="form-group">
                                    <label for="body_notification_<?= $language['code'] ?>" class="required"><?= labels('body', 'Body') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <!-- Use body[language_code] format to match controller expectations -->
                                    <textarea id="body_notification_<?= $language['code'] ?>" rows="10" placeholder="<?= labels('enter_message_here', 'Enter Message Here') ?>" class="form-control template-textarea-notification" name="body[<?= $language['code'] ?>]"><?= $body_value ?></textarea>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn  bg-new-primary submit_btn"><?= labels('save_changes', 'Save') ?></button>
                    <?= form_close() ?>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('editEmailTemplateBtn').addEventListener('click', function() {
        $('#edit_email_modal').modal('show');
        // Ensure TinyMCE is initialized for all email template editors when modal opens
        setTimeout(function() {
            if (typeof tinymce !== 'undefined') {
                // Reinitialize any editors that might not have been initialized yet
                $('.template-editor-email').each(function() {
                    let editorId = $(this).attr('id');
                    if (editorId && !tinymce.get(editorId)) {
                        tinymce.init({
                            selector: '#' + editorId,
                            height: 200,
                            menubar: true,
                            plugins: [
                                "a11ychecker",
                                "advlist",
                                "advcode",
                                "advtable",
                                "autolink",
                                "checklist",
                                "export",
                                "lists",
                                "link",
                                "image",
                                "charmap",
                                "preview",
                                "code",
                                "anchor",
                                "searchreplace",
                                "visualblocks",
                                "powerpaste",
                                "fullscreen",
                                "formatpainter",
                                "insertdatetime",
                                "media",
                                "directionality",
                                "table",
                                "help",
                                "wordcount",
                                "imagetools",
                            ],
                            toolbar: "undo redo | image media | code fullscreen| formatpainter casechange blocks fontsize | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist checklist outdent indent | removeformat | ltr rtl |a11ycheck table help",
                            maxlength: null,
                            relative_urls: false,
                            remove_script_host: false,
                            document_base_url: baseUrl
                        });
                    }
                });
            }
        }, 500);
    });
    document.getElementById('editSMSTemplateBtn').addEventListener('click', function() {
        $('#edit_sms_modal').modal('show');
    });
</script>
<script>
    $(document).ready(function() {
        // Initialize select2 for email_to field if it exists
        setTimeout(() => {
            if ($('#email_to').length) {
                $('#email_to').select2();
            }
        });
    });
    if (document.getElementById("bcc") != null) {
        $(document).ready(function() {
            var input = document.querySelector('input[id=bcc]');
            new Tagify(input)
        });
    }
    if (document.getElementById("cc") != null) {
        $(document).ready(function() {
            var input = document.querySelector('input[id=cc]');
            new Tagify(input)
        });
    }
    // Language switching functionality for email modal
    $(document).ready(function() {
        let default_language_email = '<?= isset($current_language_email) ? $current_language_email : "" ?>';

        $(document).on('click', '.language-option-email', function() {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-underline-email').css('width', '0%');
            $('#language-email-' + language).find('.language-underline-email').css('width', '100%');

            // Update text styling
            $('.language-text-email').removeClass('text-primary fw-medium');
            $('.language-text-email').addClass('text-muted');
            $('#language-email-' + language).find('.language-text-email').removeClass('text-muted');
            $('#language-email-' + language).find('.language-text-email').addClass('text-primary');

            // Show/hide subject and template divs for all languages
            $('div[id^="subjectDiv-email-"]').hide();
            $('div[id^="templateDiv-email-"]').hide();
            $('#subjectDiv-email-' + language).show();
            $('#templateDiv-email-' + language).show();

            // Ensure TinyMCE editor for the newly shown language is initialized
            let visibleEditor = $('#templateDiv-email-' + language).find('.template-editor-email');
            if (visibleEditor.length > 0) {
                let editorId = visibleEditor.attr('id');
                if (editorId && typeof tinymce !== 'undefined') {
                    // If editor doesn't exist, initialize it
                    if (!tinymce.get(editorId)) {
                        setTimeout(function() {
                            tinymce.init({
                                selector: '#' + editorId,
                                height: 200,
                                menubar: true,
                                plugins: [
                                    "a11ychecker",
                                    "advlist",
                                    "advcode",
                                    "advtable",
                                    "autolink",
                                    "checklist",
                                    "export",
                                    "lists",
                                    "link",
                                    "image",
                                    "charmap",
                                    "preview",
                                    "code",
                                    "anchor",
                                    "searchreplace",
                                    "visualblocks",
                                    "powerpaste",
                                    "fullscreen",
                                    "formatpainter",
                                    "insertdatetime",
                                    "media",
                                    "directionality",
                                    "table",
                                    "help",
                                    "wordcount",
                                    "imagetools",
                                ],
                                toolbar: "undo redo | image media | code fullscreen| formatpainter casechange blocks fontsize | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist checklist outdent indent | removeformat | ltr rtl |a11ycheck table help",
                                maxlength: null,
                                relative_urls: false,
                                remove_script_host: false,
                                document_base_url: baseUrl
                            });
                        }, 100);
                    }
                }
            }

            default_language_email = language;
        });
    });

    // Language switching functionality for SMS modal
    $(document).ready(function() {
        let default_language_sms = '<?= isset($current_language_sms) ? $current_language_sms : "" ?>';

        $(document).on('click', '.language-option-sms', function() {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-underline-sms').css('width', '0%');
            $('#language-sms-' + language).find('.language-underline-sms').css('width', '100%');

            // Update text styling
            $('.language-text-sms').removeClass('text-primary fw-medium');
            $('.language-text-sms').addClass('text-muted');
            $('#language-sms-' + language).find('.language-text-sms').removeClass('text-muted');
            $('#language-sms-' + language).find('.language-text-sms').addClass('text-primary');

            // Show/hide title and template divs for all languages
            $('div[id^="titleDiv-sms-"]').hide();
            $('div[id^="templateDiv-sms-"]').hide();
            $('#titleDiv-sms-' + language).show();
            $('#templateDiv-sms-' + language).show();

            default_language_sms = language;
        });
    });

    // Update SMS parameter button clicks to work with active language textarea
    $('.sms_parameters-section .btn').click(function() {
        let variableName = $(this).data('variable');
        let formattedText = `[[${variableName}]]`;

        // Find the currently visible SMS template textarea
        let visibleTemplateDiv = $('div[id^="templateDiv-sms-"]:visible');
        let textarea;

        if (visibleTemplateDiv.length > 0) {
            // We're in a language-specific template
            textarea = visibleTemplateDiv.find('.template-textarea-sms')[0];
        } else {
            // Fallback to first available template textarea
            textarea = $('.template-textarea-sms').first()[0];
        }

        if (textarea && (textarea.selectionStart || textarea.selectionStart === 0)) {
            let startPos = textarea.selectionStart;
            let endPos = textarea.selectionEnd;
            let scrollTop = textarea.scrollTop;
            textarea.value = textarea.value.substring(0, startPos) + formattedText + textarea.value.substring(endPos, textarea.value.length);
            textarea.focus();
            textarea.selectionStart = startPos + formattedText.length;
            textarea.selectionEnd = startPos + formattedText.length;
            textarea.scrollTop = scrollTop;
        } else if (textarea) {
            textarea.value += formattedText;
            textarea.focus();
        }
    });

    // Update email parameter button clicks to work with active language TinyMCE editor
    $('.parameters-section .btn').click(function() {
        let variableName = $(this).data('variable');
        let formattedText = `[[${variableName}]]`;

        // Find the currently visible email template editor
        let visibleTemplateDiv = $('div[id^="templateDiv-email-"]:visible');
        let editorId = null;

        if (visibleTemplateDiv.length > 0) {
            // Get the textarea ID from the visible div
            let textareaId = visibleTemplateDiv.find('.template-editor-email').attr('id');
            if (textareaId) {
                // Try to get the TinyMCE editor instance
                if (typeof tinymce !== 'undefined') {
                    let editor = tinymce.get(textareaId);
                    if (editor) {
                        editor.execCommand('mceInsertContent', false, formattedText);
                        return;
                    }
                    // If editor not found, try activeEditor as fallback
                    if (tinymce.activeEditor) {
                        tinymce.activeEditor.execCommand('mceInsertContent', false, formattedText);
                        return;
                    }
                }
            }
        }

        // Fallback to active editor if available
        if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
            tinymce.activeEditor.execCommand('mceInsertContent', false, formattedText);
        }
    });

    // Notification template modal handlers
    document.getElementById('editNotificationTemplateBtn').addEventListener('click', function() {
        $('#edit_notification_modal').modal('show');
    });

    // Language switching functionality for notification modal
    $(document).ready(function() {
        let default_language_notification = '<?= isset($current_language_notification) ? $current_language_notification : "" ?>';

        $(document).on('click', '.language-option-notification', function() {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-underline-notification').css('width', '0%');
            $('#language-notification-' + language).find('.language-underline-notification').css('width', '100%');

            // Update text styling
            $('.language-text-notification').removeClass('text-primary fw-medium');
            $('.language-text-notification').addClass('text-muted');
            $('#language-notification-' + language).find('.language-text-notification').removeClass('text-muted');
            $('#language-notification-' + language).find('.language-text-notification').addClass('text-primary');

            // Show/hide title and body divs for all languages
            $('div[id^="titleDiv-notification-"]').hide();
            $('div[id^="bodyDiv-notification-"]').hide();
            $('#titleDiv-notification-' + language).show();
            $('#bodyDiv-notification-' + language).show();

            default_language_notification = language;
        });

        // Parameters are now dynamically displayed based on database, no need for show/hide logic
    });

    // Update notification parameter button clicks to work with active language textarea
    $('.notification_parameters-section .btn').click(function() {
        let variableName = $(this).data('variable');
        let formattedText = `[[${variableName}]]`;

        // Find the currently visible notification template textarea
        let visibleBodyDiv = $('div[id^="bodyDiv-notification-"]:visible');
        let textarea;

        if (visibleBodyDiv.length > 0) {
            // We're in a language-specific template
            textarea = visibleBodyDiv.find('.template-textarea-notification')[0];
        } else {
            // Fallback to first available template textarea
            textarea = $('.template-textarea-notification').first()[0];
        }

        if (textarea && (textarea.selectionStart || textarea.selectionStart === 0)) {
            let startPos = textarea.selectionStart;
            let endPos = textarea.selectionEnd;
            let scrollTop = textarea.scrollTop;
            textarea.value = textarea.value.substring(0, startPos) + formattedText + textarea.value.substring(endPos, textarea.value.length);
            textarea.focus();
            textarea.selectionStart = startPos + formattedText.length;
            textarea.selectionEnd = startPos + formattedText.length;
            textarea.scrollTop = scrollTop;
        } else if (textarea) {
            textarea.value += formattedText;
            textarea.focus();
        }
    });
</script>