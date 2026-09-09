<?php
$edit_mode = isset($edit_mode) && $edit_mode === true;
$can_create = isset($can_create) ? $can_create : false;
$can_update = isset($can_update) ? $can_update : false;
$page = isset($custom_page) ? $custom_page : [];
$translations = isset($translations) ? $translations : [];
$seo_settings = isset($custom_page_seo_settings) ? $custom_page_seo_settings : [];
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('custom_pages', "Custom Pages") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/custom-pages') ?>"><?= labels('custom_pages', 'Custom Pages') ?></a></div>
                <div class="breadcrumb-item"><?= $edit_mode ? labels('edit_custom_page', 'Edit Custom Page') : labels('add_custom_page', 'Add Custom Page') ?></a></div>
            </div>
        </div>
        <form class="<?= $edit_mode ? 'update-form' : 'form-submit-event' ?>" method="POST" action="<?= $edit_mode ? base_url('admin/custom-pages/update/' . $page['id']) : base_url('admin/custom-pages/insert') ?>" id="<?= $edit_mode ? 'update_custom_page' : 'add_custom_page' ?>" enctype="multipart/form-data">
            <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
            <?php if ($edit_mode) { ?>
                <input type="hidden" name="custom_page_id" id="custom_page_id" value="<?= $page['id'] ?>">
                <?php if (isset($seo_settings['id'])) { ?>
                    <input type="hidden" name="custom_page_seo_id" id="custom_page_seo_id" value="<?= $seo_settings['id'] ?>">
                <?php } ?>
            <?php } ?>

            <div class="row mb-3">
                <!-- Card 1: Page Content -->
                <div class="col-md-12 mb-3">
                    <div class="card h-100">
                        <div class="row border_bottom_for_cards m-0">
                            <div class="col-auto">
                                <div class="toggleButttonPostition"><?= $edit_mode ? labels('edit_custom_page', 'Edit Custom Page') : labels('add_custom_page', 'Add Custom Page') ?></div>
                            </div>
                            <input type="hidden" name="status" value="1">
                        </div>
                        <div class="card-body">
                            <!-- Language tabs -->
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                        <?php
                                        foreach ($languages as $index => $language) {
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
                                                    <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                                </span>
                                                <div class="language-underline"
                                                    style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Per-language fields -->
                            <?php
                            foreach ($languages as $index => $language) {
                            ?>
                                <div id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <!-- Title and Slug in a single row -->
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label for="custom_page_title<?= $language['is_default'] ? '' : $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                                <input class="form-control" type="text" name="title[<?= $language['code'] ?>]" id="custom_page_title<?= $language['is_default'] ? '' : $language['code'] ?>"
                                                    value="<?= $edit_mode && isset($translations[$language['code']]['title']) ? htmlspecialchars($translations[$language['code']]['title'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                                    placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>"
                                                    <?= $language['is_default'] ? 'required data-slug-source data-slug-target="#custom_page_slug"' : '' ?>>
                                            </div>
                                        </div>
                                        <?php if ($language['is_default']) { ?>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="custom_page_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                                    <input id="custom_page_slug" class="form-control" type="text" name="slug" placeholder="<?= labels('enter_the_slug', 'Enter the slug') ?>" value="<?= $edit_mode && isset($page['slug']) ? htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8') : '' ?>" required>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>

                                    <!-- Content (rich text editor) -->
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="content<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('content', 'Content') . ($language['is_default'] ? '' : ' (' . strtoupper($language['code']) . ')') ?></label>
                                                <textarea rows="10" class="form-control h-50 summernotes custome_reset" name="content[<?= $language['code'] ?>]"><?= $edit_mode && isset($translations[$language['code']]['content']) ? $translations[$language['code']]['content'] : '' ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- Card 2: SEO Settings -->
                <div class="col-md-12 mb-3">
                    <div class="card h-100">
                        <div class="row border_bottom_for_cards m-0">
                            <div class="col-auto">
                                <div class="toggleButttonPostition"><?= labels('custom_page_seo_settings', 'Custom Page SEO Settings') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- SEO Language tabs -->
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                        <?php
                                        $sorted_languages = sort_languages_with_default_first($languages);
                                        foreach ($sorted_languages as $index => $language) {
                                            if ($language['is_default'] == 1) {
                                                $current_language_seo = $language['code'];
                                            }
                                        ?>
                                            <div class="language-option-seo position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                id="language-seo-<?= $language['code'] ?>"
                                                data-language="<?= $language['code'] ?>"
                                                style="cursor: pointer; padding: 0.5rem 0;">
                                                <span class="language-text-seo px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                    style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                    <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                                </span>
                                                <div class="language-underline-seo"
                                                    style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Per-language SEO fields -->
                            <?php
                            foreach ($sorted_languages as $index => $language) {
                            ?>
                                <div id="translationDivSeo-<?= $language['code'] ?>" <?= $language['code'] == $current_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ' (' . strtoupper($language['code']) . ')' ?></label>
                                                <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                                <input id="meta_title<?= $language['code'] ?>" class="form-control" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255" value="<?= $edit_mode && isset($seo_settings['translated_' . $language['code']]['title']) ? esc($seo_settings['translated_' . $language['code']]['title']) : '' ?>">
                                                <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                                <i data-content=" <?= labels('data_content_for_keywords', 'These keywords will help find the blogs while users search for the blogs.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                                <input id="meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100 seo-keywords-tagify" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'press enter to add keyword') ?>" value="<?= $edit_mode && isset($seo_settings['translated_' . $language['code']]['keywords']) ? esc($seo_settings['translated_' . $language['code']]['keywords']) : '' ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                                <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                                <textarea id="meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500"><?= $edit_mode && isset($seo_settings['translated_' . $language['code']]['description']) ? esc($seo_settings['translated_' . $language['code']]['description']) : '' ?></textarea>
                                                <small class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="schema_markup<?= $language['code'] ?>"><?= labels('schema_markup', 'Schema Markup') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                                <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                                    data-toggle="popover"
                                                    class="fa fa-question-circle"
                                                    data-original-title=""
                                                    title=""></i>
                                                <textarea id="schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"><?= $edit_mode && isset($seo_settings['translated_' . $language['code']]['schema_markup']) ? esc($seo_settings['translated_' . $language['code']]['schema_markup']) : '' ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <!-- SEO Image (single, not per language) -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                                        <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i> <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                        <input type="file" class="filepond" name="meta_image" id="meta_image" accept="image/*">
                                        <small class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                                        <?php if ($edit_mode && isset($seo_settings['image']) && !empty($seo_settings['image']) && !str_ends_with($seo_settings['image'], 'custom_page_seo_settings/')) { ?>
                                            <div class="position-relative d-inline-block mt-2">
                                                <img src="<?= $seo_settings['image'] ?>" alt="Custom Page SEO Meta Image" class="img-fluid" style="width: 130px; height: 100px;">
                                                <button type="button" class="btn btn-sm btn-danger remove-seo-image"
                                                    data-custom-page-id="<?= $page['id'] ?>"
                                                    data-seo-id="<?= isset($seo_settings['id']) ? $seo_settings['id'] : '' ?>"
                                                    style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; padding: 0; border-radius: 50%; font-size: 10px;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit button -->
                <div class="col-md-12 mt-3 d-flex justify-content-end">
                    <?php if ((!$edit_mode && $can_create) || ($edit_mode && $can_update)) { ?>
                        <button type="submit" class="btn btn-lg bg-new-primary submit_btn"><?= $edit_mode ? labels('save', 'Save') : labels('add_custom_page', 'Add Custom Page') ?></button>
                    <?php } ?>
                </div>
            </div>
        </form>
    </section>
</div>

<script>
    $(function() {
        let popoverTimer;
        let currentPopover = null;
        let isOverPopover = false;
        let isOverTrigger = false;

        $('[data-toggle="popover"]').popover({
            html: true,
            trigger: 'manual',
            container: 'body'
        }).on('mouseenter', function() {
            const $this = $(this);
            isOverTrigger = true;
            clearTimeout(popoverTimer);

            if (currentPopover && currentPopover[0] !== $this[0]) {
                currentPopover.popover('hide');
            }

            currentPopover = $this;
            $this.popover('show');

        }).on('mouseleave', function() {
            isOverTrigger = false;
            startHideTimer();
        });

        $(document).on('mouseenter', '.popover', function() {
            isOverPopover = true;
            clearTimeout(popoverTimer);
        }).on('mouseleave', '.popover', function() {
            isOverPopover = false;
            startHideTimer();
        });

        function startHideTimer() {
            clearTimeout(popoverTimer);
            popoverTimer = setTimeout(function() {
                if (!isOverTrigger && !isOverPopover && currentPopover) {
                    currentPopover.popover('hide');
                    currentPopover = null;
                }
            }, 150);
        }
    });

    // Initialize Tagify for SEO keywords fields for each language
    $(document).ready(function() {
        var seoKeywordsInputs = document.querySelectorAll('.seo-keywords-tagify');
        seoKeywordsInputs.forEach(function(input) {
            if (input != null) {
                new Tagify(input);
            }
        });
    });
</script>
<script>
    $(document).ready(function() {
        // Language tab switching for page content
        let default_language = '<?= $current_language ?>';

        $(document).on('click', '.language-option', function() {
            const language = $(this).data('language');

            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            // Hide all translation divs, then show the selected one
            $('[id^="translationDiv-"]').hide();
            $('#translationDiv-' + language).show();

            default_language = language;
        });
    });

    // SEO language tab switching
    $(document).ready(function() {
        let default_language_seo = '<?= $current_language_seo ?? (isset($sorted_languages[0]['code']) ? $sorted_languages[0]['code'] : 'en') ?>';

        $(document).on('click', '.language-option-seo', function() {
            const language = $(this).data('language');

            $('.language-underline-seo').css('width', '0%');
            $('#language-seo-' + language).find('.language-underline-seo').css('width', '100%');

            $('.language-text-seo').removeClass('text-primary fw-medium');
            $('.language-text-seo').addClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').removeClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').addClass('text-primary');

            // Hide all SEO translation divs, then show the selected one
            $('[id^="translationDivSeo-"]').hide();
            $('#translationDivSeo-' + language).show();

            default_language_seo = language;
        });
    });

    // Initialize slug auto-fill
    $(document).ready(function() {
        initSlugAutoFill();
    });

    <?php if ($edit_mode) { ?>
    // Handle SEO image removal
    $(document).on('click', '.remove-seo-image', function() {
        const button = $(this);
        const customPageId = button.data('custom-page-id');
        const seoId = button.data('seo-id');

        if (confirm('<?= labels('are_you_sure_to_remove_seo_image', 'Are you sure you want to remove this SEO image?') ?>')) {
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: '<?= base_url('admin/custom-pages/remove-seo-image') ?>',
                type: 'POST',
                data: {
                    custom_page_id: customPageId,
                    seo_id: seoId,
                    <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.error === false) {
                        button.closest('.position-relative').remove();
                        alert(response.message || 'SEO image removed successfully');
                    } else {
                        alert(response.message || 'Failed to remove SEO image');
                        button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                    }
                },
                error: function(xhr, status, error) {
                    alert('An error occurred while removing the SEO image');
                    button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                }
            });
        }
    });
    <?php } ?>
</script>
