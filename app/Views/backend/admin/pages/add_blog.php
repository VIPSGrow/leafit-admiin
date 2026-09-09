<?php
$check_payment_gateway = get_settings('payment_gateways_settings', true);
$cod_setting =  $check_payment_gateway['cod_setting'];
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('blog', "Blog") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/blog') ?>"><i class="fa-brands fa-blogger-b text-warning"></i> <?= labels('blog', 'Blog') ?></a></div>
                <div class="breadcrumb-item"><?= labels('add_blog', 'Add Blog') ?></a></div>
            </div>
        </div>
        <?= form_open('/admin/blog/insert_blog', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_blog', 'enctype' => "multipart/form-data"]); ?>
        <div class="row mb-3">
            <!-- add blog details -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <!-- add blog details header -->
                    <div class="row  border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('add_blog', 'Add Blog') ?></div>
                        </div>
                    </div>
                    <div class="card-body">
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
                        <?php
                        foreach ($languages as $index => $language) {
                        ?>
                            <div class="row" id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <!-- add blog title -->
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="blog_title<?= $language['is_default'] ? '' : $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('title', 'Title') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?> </label>
                                        <input class="form-control" type="text" name="title[<?= $language['code'] ?>]" id="blog_title<?= $language['is_default'] ? '' : $language['code'] ?>" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" <?= $language['is_default'] ? 'data-slug-source data-slug-target="#blog_slug"' : '' ?>>
                                    </div>
                                </div>

                                <!-- add blog tags -->
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="tags<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('tags', 'Tags') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <i data-content=" <?= labels('data_content_for_blog_tags', 'These tags will help find the blogs while users search for the blogs.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <input id="tags<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100 translation-tags" type="text" name="tags[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_tag', 'press enter to add tag') ?>">
                                    </div>
                                </div>

                                <!-- add short description -->
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="short_description<?= $language['code'] ?>"><?= labels('short_description', 'Short Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <textarea class="form-control" name="short_description[<?= $language['code'] ?>]" id="short_description<?= $language['code'] ?>" rows="3" maxlength="500" placeholder="<?= labels('enter_a_short_summary', 'Enter a short summary') ?>"></textarea>
                                        <small class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                    </div>
                                </div>

                                <!-- add blog description -->
                                <div class="col-md-12">
                                    <label for="long_description<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('description', 'Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <textarea rows=10 class='form-control h-50 summernotes custome_reset' name="description[<?= $language['code'] ?>]"></textarea>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <!-- other blog details -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <!-- add blog details header -->
                    <div class="row  border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('other_blog_details', 'Other Blog Details') ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- add blog slug -->
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="blog_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                    <input id="blog_slug" class="form-control" type="text" name="slug" placeholder="<?= labels('enter_the_slug', 'Enter the slug') ?> " required>
                                </div>
                            </div>

                            <!-- add blog category -->
                            <div class="col-md-12">
                                <div class="jquery-script-clear"></div>
                                <div class="categories form-group" id="categories">
                                    <label for="category" class="required"><?= labels('select_category', 'Select Category') ?></label> <br>
                                    <select id="category" class="form-control w-100 select2" name="category_id" required>
                                        <option value=""><?= labels('select_category', 'Select Category') ?></option>
                                        <?php foreach ($categories_name as $cn) : ?>
                                            <option value="<?= $cn['id'] ?>">
                                                <?= $cn['translated_name'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- add blog image -->
                            <div class="col-md-12">
                                <div class="form-group"> <label for="blog_image_selector" class="required"><?= labels('image', 'Image') ?></label>
                                    <small>(<?= labels('blog_image_recommended_size', 'We recommend 392x210 pixels') ?>)</small>
                                    <input type="image" name="blog_image_selector" class="filepond logo" id="blog_image_selector" accept="image/*" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- add blog seo details -->
            <div class="col-md-12">
                <div class="card h-100">
                    <!-- add blog seo details header -->
                    <div class="row  border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('blog_seo_settings', 'Blog SEO Settings') ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    // Sort languages so default language appears first for better UI
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
                        <?php
                        // Use sorted languages for content divs as well
                        foreach ($sorted_languages as $index => $language) {
                        ?>
                            <div id="translationDivSeo-<?= $language['code'] ?>" <?= $language['code'] == $current_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ' (' . strtoupper($language['code']) . ')' ?></label>
                                            <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="meta_title<?= $language['code'] ?>" class="form-control" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255">
                                            <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                            <i data-content=" <?= labels('data_content_for_keywords', 'These keywords will help find the blogs while users search for the blogs.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <input id="meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100 seo-keywords-tagify" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'press enter to add keyword') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                            <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                            <textarea id="meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500"></textarea>
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
                                            <textarea id="schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"></textarea>
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- add blog button -->
            <div class="col-md mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-lg bg-new-primary submit_btn"><?= labels('add_blog', 'Add Blog') ?></button>
                <?= form_close() ?>
            </div>
        </div>
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

            // Hide other popovers
            if (currentPopover && currentPopover[0] !== $this[0]) {
                currentPopover.popover('hide');
            }

            currentPopover = $this;
            $this.popover('show');

        }).on('mouseleave', function() {
            isOverTrigger = false;
            startHideTimer();
        });

        // Handle popover content hover
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
        // select default language
        let default_language = '<?= $current_language ?>';

        $(document).on('click', '.language-option', function() {
            const language = $(this).data('language');

            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            if (language != default_language) {
                $('#translationDiv-' + language).show();
                $('#translationDiv-' + default_language).hide();
            }

            default_language = language;
        });
    });

    // for translation tags
    $(document).ready(function() {
        var input_tags = document.querySelectorAll('.translation-tags');

        // initialize Tagify on the above input node reference
        input_tags.forEach(function(input_tag) {
            if (input_tag != null) {
                new Tagify(input_tag);
            }
        });
    });

    // SEO language tab switching
    $(document).ready(function() {
        // select default language for SEO
        let default_language_seo = '<?= $current_language_seo ?? (isset($sorted_languages[0]['code']) ? $sorted_languages[0]['code'] : 'en') ?>';

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
    });
</script>