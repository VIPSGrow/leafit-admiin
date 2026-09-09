<?php
$sorted_languages = sort_languages_with_default_first($languages);
$current_language_seo = '';
foreach ($sorted_languages as $_lang) {
    if ($_lang['is_default'] == 1) {
        $current_language_seo = $_lang['code'];
        break;
    }
}
?>
<style>
    .tagify {
        max-width: 100% !important;
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('seo_settings', 'SEO Settings') ?></h1>
        </div>
        <div class="card">
            <div class="card-body">
                <?= form_open('/partner/seo/update', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'partner_seo_form', 'enctype' => "multipart/form-data", 'novalidate' => 'novalidate']); ?>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex flex-wrap align-items-center gap-4">
                            <?php foreach ($sorted_languages as $language): ?>
                                <div class="language-option-seo position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                    id="language-seo-<?= $language['code'] ?>"
                                    data-language="<?= $language['code'] ?>"
                                    style="cursor: pointer; padding: 0.5rem 0;">
                                    <span class="language-text-seo px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                        style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                        <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                    </span>
                                    <div class="language-underline-seo"
                                        style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php foreach ($sorted_languages as $language): ?>
                    <div id="translationDivSeo-<?= $language['code'] ?>" <?= $language['code'] == $current_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ' (' . strtoupper($language['code']) . ')' ?></label>
                                    <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input id="meta_title<?= $language['code'] ?>" class="form-control" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255" value="<?= isset($partner_seo_settings['translated_' . $language['code']]['title']) ? esc($partner_seo_settings['translated_' . $language['code']]['title']) : (isset($partner_seo_settings['title']) ? esc($partner_seo_settings['title']) : '') ?>">
                                    <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                    <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <input id="meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>" value="<?= isset($partner_seo_settings['translated_' . $language['code']]['keywords']) ? esc($partner_seo_settings['translated_' . $language['code']]['keywords']) : (isset($partner_seo_settings['keywords']) ? esc($partner_seo_settings['keywords']) : '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                    <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                    <textarea id="meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500"><?= isset($partner_seo_settings['translated_' . $language['code']]['description']) ? esc($partner_seo_settings['translated_' . $language['code']]['description']) : (isset($partner_seo_settings['description']) ? esc($partner_seo_settings['description']) : '') ?></textarea>
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
                                    <textarea id="schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"><?= isset($partner_seo_settings['translated_' . $language['code']]['schema_markup']) ? esc($partner_seo_settings['translated_' . $language['code']]['schema_markup']) : (isset($partner_seo_settings['schema_markup']) ? esc($partner_seo_settings['schema_markup']) : '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                            <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                            <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                            <input type="file" class="filepond" name="meta_image" id="meta_image" accept="image/*">
                            <?php if (!empty($partner_seo_settings['image'])): ?>
                                <div class="position-relative d-inline-block mt-2">
                                    <img src="<?= esc($partner_seo_settings['image']) ?>" alt="SEO Image" style="max-width: 120px; max-height: 80px; border-radius: 8px;">
                                    <button type="button" class="btn btn-sm btn-danger remove-partner-profile-seo-image"
                                        data-partner-id="<?= isset($partner_details['partner_id']) ? $partner_details['partner_id'] : '' ?>"
                                        data-seo-id="<?= isset($partner_seo_settings['id']) ? $partner_seo_settings['id'] : '' ?>"
                                        style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; padding: 0; border-radius: 50%; font-size: 10px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                            <small class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                        </div>
                    </div>
                </div>

                <div class="form-group d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary submit_btn">
                        <i class="fas fa-check"></i> <?= labels('save', 'Save') ?>
                    </button>
                </div>

                <?= form_close() ?>
            </div>
        </div>
    </section>
</div>

<script>
    $(document).ready(function() {
        <?php foreach ($sorted_languages as $language): ?>
            var metaKeywordsInput<?= $language['code'] ?> = document.querySelector('input[id=meta_keywords<?= $language['code'] ?>]');
            if (metaKeywordsInput<?= $language['code'] ?> != null) {
                new Tagify(metaKeywordsInput<?= $language['code'] ?>);
            }
        <?php endforeach; ?>

        let default_language_seo = '<?= $current_language_seo ?>';

        $(document).on('click', '.language-option-seo', function() {
            const language = $(this).data('language');

            $('.language-underline-seo').css('width', '0%');
            $('#language-seo-' + language).find('.language-underline-seo').css('width', '100%');

            $('.language-text-seo').removeClass('text-primary fw-medium');
            $('.language-text-seo').addClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').removeClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').addClass('text-primary');

            if (language != default_language_seo) {
                $('#translationDivSeo-' + language).show();
                $('#translationDivSeo-' + default_language_seo).hide();
            }

            default_language_seo = language;
        });

        $(document).on('click', '.remove-partner-profile-seo-image', function() {
            const button = $(this);
            const seoId = button.data('seo-id');

            if (confirm('<?= labels('are_you_sure_to_remove_seo_image', 'Are you sure you want to remove this SEO image?') ?>')) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: '<?= base_url('partner/seo/remove_seo_image') ?>',
                    type: 'POST',
                    data: {
                        seo_id: seoId,
                        <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error === false) {
                            button.closest('.position-relative').remove();
                            showToastMessage(response.message || '<?= labels('seo_image_removed_successfully', 'SEO image removed successfully') ?>', 'success');
                            button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        } else {
                            showToastMessage(response.message || '<?= labels('failed_to_remove_seo_image', 'Failed to remove SEO image') ?>', 'error');
                            button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        }
                    },
                    error: function() {
                        showToastMessage('<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
                        button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                    }
                });
            }
        });

        $(document).on('change', '#meta_image', function() {
            $('.remove-partner-profile-seo-image').prop('disabled', false).html('<i class="fas fa-times"></i>');
        });
    });
</script>

<script>
    $(function() {
        $('[data-toggle="popover"]').popover({
            html: true,
            trigger: 'hover',
            container: 'body'
        });
    });
</script>
