<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('edit_email_template', "Edit Email template") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('/admin/settings/email_template_list') ?>"><?= labels('email_configuration', "Email Configuration") ?></a></div>
                <div class="breadcrumb-item"><?= labels('edit_email_configuration', "Edit Email Configuration") ?></div>
            </div>
        </div>

        <div class="card">
            <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                <div class="toggleButttonPostition"><?= labels('email_configuration', "Email Configuration") ?></div>
            </div>
            <div class="card-body">
                <!-- Language Tabs -->
                <?php if (count($languages) > 1): ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                // Sort languages so default language appears first for better UI
                                $sorted_languages = sort_languages_with_default_first($languages);
                                foreach ($sorted_languages as $index => $language) {
                                    if ($language['is_default'] == 1) {
                                        $current_language = $language['code'];
                                    }
                                ?>
                                    <div class="language-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-<?= $language['code'] ?>"
                                        data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span class="language-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?><?= $language['is_default'] ? ' (Default)' : '' ?>
                                        </span>
                                        <div class="language-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    // If only one language, set current language
                    $current_language = !empty($languages[0]['code']) ? $languages[0]['code'] : 'en';
                    $sorted_languages = $languages;
                    ?>
                <?php endif; ?>

                <?= form_open_multipart(base_url('admin/settings/edit_email_template_operation'), array('class' => 'form-submit-event', 'id' => 'edit_email_template_form')) ?>

                <input type="hidden" name="template_id" value="<?= $template['id'] ?>" />

                <!-- Type field outside tabs (readonly, similar to event_key in notification templates) -->
                <!-- Display translated label in readonly input, send actual type value via hidden field -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email_type_display" class="required"><?= labels('type', "Type") ?></label>
                            <!-- Readonly input showing translated label (not submitted in form) -->
                            <input type="text" id="email_type_display" class="form-control"
                                value="<?= htmlspecialchars($typeLabel ?? $template['type'] ?? '') ?>"
                                placeholder="<?= labels('email_type', 'Email type') ?>" readonly>
                            <!-- Hidden field with actual type value (submitted in form) -->
                            <input type="hidden" name="email_type" value="<?= htmlspecialchars($template['type'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Language-specific subject fields -->
                <div class="row mt-3">
                    <?php
                    // Use sorted languages for content divs
                    foreach ($sorted_languages as $index => $language) {
                        // Get translation data for this language
                        $translation_data = isset($translations[$language['code']]) ? $translations[$language['code']] : [];
                        $subject_value = !empty($translation_data['subject']) ? $translation_data['subject'] : ($language['is_default'] == 1 ? $template['subject'] : '');
                    ?>
                        <div class="col-md-6" id="subjectDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="form-group">
                                <label for="subject_<?= $language['code'] ?>" class="required"><?= labels('subject', 'Subject') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                <input id="subject_<?= $language['code'] ?>" class="form-control" type="text" name="translations[<?= $language['code'] ?>][subject]" placeholder="<?= labels('enter_subject_here', 'Enter subject here') ?>" value="<?= $subject_value ?>">
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- Parameters section (same for all languages) -->
                <!-- Parameters are dynamically generated based on what's stored in the database -->
                <div class="row">
                    <div class="col-md-12 parameters-section">
                        <label><?= labels('parameters', "Parameters") ?></label>
                        <div class="form-group">
                            <?php if (!empty($parameters) && is_array($parameters)): ?>
                                <!-- Dynamically generate parameter buttons based on database data -->
                                <!-- Parameters are displayed in a flexible layout that wraps automatically -->
                                <div class="mb-2">
                                    <?php foreach ($parameters as $paramKey): ?>
                                        <?php
                                        // Get label for this parameter, or use the key as fallback
                                        // If label mapping exists, use it; otherwise create a readable label from the key
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
                </div>

                <!-- BCC and CC fields (same for all languages) -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?= labels('bcc', "BCC") ?></label>
                            <input id="bcc" style="border-radius: 0.25rem!important" class="w-100" type="text" value="<?= ($template['bcc']) ?>" name="bcc[]" placeholder="<?= labels('press_enter_to_add_bcc', 'Press enter to add BCC') ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?= labels('cc', "CC") ?></label>
                            <input id="cc" style="border-radius: 0.25rem" class="w-100" type="text" name="cc[]" value="<?= $template['cc'] ?>" placeholder="<?= labels('press_enter_to_add_cc', 'Press enter to add CC') ?>">
                        </div>
                    </div>
                </div>

                <!-- Language-specific template fields -->
                <div class="row">
                    <?php
                    foreach ($sorted_languages as $index => $language) {
                        // Get translation data for this language
                        $translation_data = isset($translations[$language['code']]) ? $translations[$language['code']] : [];
                        $template_value = !empty($translation_data['template']) ? $translation_data['template'] : ($language['is_default'] == 1 ? $template['template'] : '');
                    ?>
                        <div class="col-md-12" id="templateDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="form-group">
                                <label for="template_<?= $language['code'] ?>" class="required"><?= labels('template', 'Template') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                <textarea rows="10" id="template_<?= $language['code'] ?>" class="form-control h-50 summernotes custome_reset template-editor" name="translations[<?= $language['code'] ?>][template]"><?= $template_value ?></textarea>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- Submit button -->
                <div class="row mt-3 mb-3">
                    <div class="col-md d-flex justify-content-end">
                        <button type="submit" class="btn btn-lg bg-new-primary submit_btn"><?= labels('save_changes', 'Save Changes') ?></button>
                    </div>
                </div>

                <?= form_close() ?>
            </div>
        </div>
    </section>
</div>

<script>
    $(document).ready(function() {
        // Initialize BCC field with Tagify
        if (document.getElementById("bcc") != null) {
            var input = document.querySelector('input[id=bcc]');
            new Tagify(input);
        }

        // Initialize CC field with Tagify
        if (document.getElementById("cc") != null) {
            var input = document.querySelector('input[id=cc]');
            new Tagify(input);
        }

        // Handle parameter button clicks to insert variables into active template editor
        // Parameters are now in .parameters-section class (matching notification template pattern)
        $(document).on('click', '.parameters-section .btn', function() {
            let variableName = $(this).data('variable');
            let formattedText = `[[${variableName}]]`;

            // Insert into active TinyMCE editor
            if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                tinymce.activeEditor.execCommand('mceInsertContent', false, formattedText);
            }
        });
    });

    // Language switching functionality
    $(document).ready(function() {
        let default_language = '<?= $current_language ?>';

        $(document).on('click', '.language-option', function() {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            // Update text styling
            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            // Show/hide subject and template divs for all languages
            $('div[id^="subjectDiv-"]').hide();
            $('div[id^="templateDiv-"]').hide();
            $('#subjectDiv-' + language).show();
            $('#templateDiv-' + language).show();

            default_language = language;
        });
    });
</script>