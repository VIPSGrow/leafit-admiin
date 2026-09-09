<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('sms_gateways', "SMS Gateways") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('/admin/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item">
                    <a href="<?= base_url('/admin/settings/system-settings') ?>">
                        <?= labels('system_settings', "System Settings") ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= labels('sms_gateways', "SMS Gateways") ?></div>
            </div>
        </div>
        <?php
        $settings = get_settings('system_settings', true);
        $sms_gateway_setting = get_settings('sms_gateway_setting');
        $sms_gateway_data = is_string($sms_gateway_setting) ? json_decode($sms_gateway_setting, true) : [];
        ?>
        <div class="card">
            <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                <div class="toggleButttonPostition"><?= labels('sms_templates', "SMS Templates") ?></div>
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

                <form method="POST" class="form-submit-event" id="edit_sms_template_form" action="<?= base_url('admin/settings/edit-sms-templates') ?>">
                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>" />

                    <!-- Type field outside tabs (readonly, similar to email template edit page) -->
                    <!-- Display translated label in readonly input, send actual type value via hidden field -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sms_type_display" class="required"><?= labels('type', "Type") ?></label>
                                <!-- Readonly input showing translated label (not submitted in form) -->
                                <input type="text" id="sms_type_display" class="form-control"
                                    value="<?= htmlspecialchars($typeLabel ?? $template['type'] ?? '') ?>"
                                    placeholder="<?= labels('sms_type', 'SMS type') ?>" readonly>
                                <!-- Hidden field with actual type value (submitted in form) -->
                                <input type="hidden" name="type" value="<?= htmlspecialchars($template['type'] ?? '') ?>">
                            </div>
                        </div>
                    </div>


                    <!-- Language-specific content -->
                    <div class="row mt-3">
                        <?php
                        // Use sorted languages for content divs as well
                        foreach ($sorted_languages as $index => $language) {
                            // Get translation data for this language
                            $translation_data = isset($translations[$language['code']]) ? $translations[$language['code']] : [];
                            $title_value = !empty($translation_data['title']) ? $translation_data['title'] : ($language['is_default'] == 1 ? $template['title'] : '');
                            $template_value = !empty($translation_data['template']) ? $translation_data['template'] : ($language['is_default'] == 1 ? $template['template'] : '');
                        ?>
                            <div class="col-md-6" id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="form-group">
                                    <label for="title_<?= $language['code'] ?>" class="required"><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input id="title_<?= $language['code'] ?>" class="form-control" type="text" name="translations[<?= $language['code'] ?>][title]" placeholder="<?= labels('enter_title_here', 'Enter title here') ?>" value="<?= $title_value ?>">
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

                    <!-- Gateway Template IDs (optional) -->
                    <!-- Separate field per gateway, side by side (3 per row). Storage: gateway_template_ids JSON in DB. -->
                    <?php
                    $template_based_gateways = $template_based_gateways ?? ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91', 'combirds' => 'Combirds'];
                    $gateway_template_ids = $gateway_template_ids ?? [];
                    ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <label><?= labels('gateway_template_ids', 'Gateway Template IDs') ?> <span class="text-muted">(<?= labels('optional', 'optional') ?>)</span></label>
                            <div class="alert alert-light border mb-2 py-2 px-3" role="region">
                                <small class="text-body d-block mb-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <?= labels('gateway_template_ids_help', 'For gateways that use verified template IDs (e.g. Fast2SMS DLT, MSG91, Combirds), enter the template ID from your provider. Leave empty for gateways that send the full message (e.g. Twilio).') ?>
                                </small>
                            </div>
                        </div>
                        <?php foreach ($template_based_gateways as $gw_key => $gw_label): ?>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="gateway_template_ids_<?= htmlspecialchars($gw_key) ?>"><?= htmlspecialchars($gw_label) ?> <?= labels('template_id', 'Template ID') ?></label>
                                <input type="text" id="gateway_template_ids_<?= htmlspecialchars($gw_key) ?>" name="gateway_template_ids[<?= htmlspecialchars($gw_key) ?>]" class="form-control" placeholder="<?= labels('enter_template_id', 'Enter template ID') ?>" value="<?= htmlspecialchars($gateway_template_ids[$gw_key] ?? '') ?>" maxlength="64">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Template content for each language -->
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
                                    <textarea id="template_<?= $language['code'] ?>" rows="50" placeholder="<?= labels('enter_message_here', 'Enter Message Here') ?>" class="form-control template-textarea" name="translations[<?= $language['code'] ?>][template]"><?= $template_value ?></textarea>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="row">
                        <div class="col-md d-flex justify-content-lg-end m-1">
                            <div class="form-group">
                                <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Save Changes") ?>' class='btn btn-primary' />
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<script>
    // Update parameter button clicks to work with active language textarea
    $('.parameters-section .btn').click(function() {
        let variableName = $(this).data('variable');
        let formattedText = `[[${variableName}]]`;

        // Find the currently visible template textarea
        let textarea;
        let visibleTemplateDiv = $('div[id^="templateDiv-"]:visible');

        if (visibleTemplateDiv.length > 0) {
            // We're in a language-specific template
            textarea = visibleTemplateDiv.find('.template-textarea')[0];
        } else {
            // Fallback to first available template textarea
            textarea = $('.template-textarea').first()[0];
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


    // Handle form submission to ensure all tab data is collected
    $('.form-submit-event').on('submit', function(e) {
        // The form will be submitted normally with all data
        // No need to prevent default or collect data manually
        // as the form fields are already properly named
    });

    // Language switching functionality (same as categories)
    $(document).ready(function() {
        let default_language = '<?= $current_language ?>';

        $(document).on('click', '.language-option', function() {
            const language = $(this).data('language');

            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            // Show/hide title and template divs for all languages
            $('div[id^="translationDiv-"]').hide();
            $('div[id^="templateDiv-"]').hide();
            $('#translationDiv-' + language).show();
            $('#templateDiv-' + language).show();

            default_language = language;
        });
    });
</script>