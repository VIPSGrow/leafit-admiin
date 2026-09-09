<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('edit_notification_template', "Edit Notification Template") ?></h1>
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
                <div class="breadcrumb-item">
                    <a href="<?= base_url('/admin/settings/notification-templates') ?>">
                        <?= labels('notification_templates', "Notification Templates") ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= labels('edit_notification_template', "Edit Notification Template") ?></div>
            </div>
        </div>

        <div class="card">
            <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                <div class="toggleButttonPostition"><?= labels('notification_templates', "Notification Templates") ?></div>
            </div>
            <div class="card-body">
                <!-- Language Tabs (if multiple languages) -->
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

                <form method="POST" class="form-submit-event" id="edit_notification_template_form" action="<?= base_url('admin/settings/edit-notification-template-operation') ?>">
                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>" />

                    <!-- Event Key field (outside tabs) -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="event_key" class="required"><?= labels('event_key', "Event Key") ?></label>
                                <input type="text" id="event_key" class="form-control" name="event_key"
                                    value="<?= htmlspecialchars($template['event_key'] ?? '') ?>"
                                    placeholder="<?= labels('enter_event_key', 'Enter event key') ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Language-specific title fields -->
                    <div class="row">
                        <?php
                        // Use sorted languages for content divs
                        foreach ($sorted_languages as $index => $language) {
                            // Get translation data for this language (if translations exist)
                            $translation_data = isset($translations[$language['code']]) ? $translations[$language['code']] : [];
                            $title_value = !empty($translation_data['title']) ? $translation_data['title'] : ($language['is_default'] == 1 ? ($template['title'] ?? '') : '');
                        ?>
                            <div class="col-md-6" id="titleDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="form-group">
                                    <label for="title_<?= $language['code'] ?>" class="required"><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input id="title_<?= $language['code'] ?>" class="form-control" type="text"
                                        name="title[<?= $language['code'] ?>]"
                                        placeholder="<?= labels('enter_title_here', 'Enter title here') ?>"
                                        value="<?= htmlspecialchars($title_value) ?>">
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

                    <!-- Language-specific body fields -->
                    <div class="row">
                        <?php
                        foreach ($sorted_languages as $index => $language) {
                            // Get translation data for this language (if translations exist)
                            $translation_data = isset($translations[$language['code']]) ? $translations[$language['code']] : [];
                            $body_value = !empty($translation_data['body']) ? $translation_data['body'] : ($language['is_default'] == 1 ? ($template['body'] ?? '') : '');
                        ?>
                            <div class="col-md-12" id="bodyDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="form-group">
                                    <label for="body_<?= $language['code'] ?>" class="required"><?= labels('body', 'Body') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <textarea id="body_<?= $language['code'] ?>" rows="10"
                                        class="form-control template-textarea"
                                        name="body[<?= $language['code'] ?>]"
                                        placeholder="<?= labels('enter_message_here', 'Enter Message Here') ?>"><?= htmlspecialchars($body_value) ?></textarea>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Submit button -->
                    <div class="row mt-3">
                        <div class="col-md d-flex justify-content-lg-end m-1">
                            <div class="form-group">
                                <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Save Changes") ?>' class='btn btn-primary btn-lg' />
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<script>
    $(document).ready(function() {
        // Handle parameter button clicks to insert variables into body textarea
        $('.parameters-section .btn').click(function() {
            let variableName = $(this).data('variable');
            let formattedText = `[[${variableName}]]`;

            // Find the currently visible body textarea
            let textarea;
            let visibleBodyDiv = $('div[id^="bodyDiv-"]:visible');

            if (visibleBodyDiv.length > 0) {
                // We're in a language-specific body field
                textarea = visibleBodyDiv.find('.template-textarea')[0];
            } else {
                // Fallback to first available body textarea
                textarea = $('.template-textarea').first()[0];
            }

            if (textarea && (textarea.selectionStart || textarea.selectionStart === 0)) {
                // Insert at cursor position
                let startPos = textarea.selectionStart;
                let endPos = textarea.selectionEnd;
                let scrollTop = textarea.scrollTop;

                textarea.value = textarea.value.substring(0, startPos) + formattedText + textarea.value.substring(endPos, textarea.value.length);
                textarea.focus();
                textarea.selectionStart = startPos + formattedText.length;
                textarea.selectionEnd = startPos + formattedText.length;
                textarea.scrollTop = scrollTop;
            } else if (textarea) {
                // Append to end if no cursor position
                textarea.value += formattedText;
                textarea.focus();
            }
        });

        // Language switching functionality (if multiple languages)
        <?php if (count($languages) > 1): ?>
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

                // Show/hide title and body divs for all languages
                $('div[id^="titleDiv-"]').hide();
                $('div[id^="bodyDiv-"]').hide();
                $('#titleDiv-' + language).show();
                $('#bodyDiv-' + language).show();

                default_language = language;
            });
        <?php endif; ?>

        // Handle form submission
        $('.form-submit-event').on('submit', function(e) {
            // Form will be submitted normally with all data
            // Validation will be handled on server side
        });
    });
</script>