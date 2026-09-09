<?php
$db = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$permissions = get_permission($user1[0]['id']);
?>
<style>
    .custom-nav-tabs {
        padding: 8px;
        border-radius: 12px;
        border: none !important;
        display: flex;
        gap: 8px;
        margin: 0.5rem;
    }

    .custom-nav-tabs .nav-item {
        flex: 1;
    }

    .custom-nav-tabs .nav-link {
        border: none !important;
        border-radius: 10px !important;
        color: #007bff !important;
        font-weight: 700;
        text-align: center;
        padding: 12px 20px !important;
        transition: all 0.3s ease;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .custom-nav-tabs .nav-link i {
        margin-right: 10px;
        font-size: 16px;
    }

    .custom-nav-tabs .nav-link.active {
        background-color: color-mix(in srgb, var(--primary-color) 30%, transparent) !important;
        color: var(--primary-color) !important;
    }

    .custom-nav-tabs .nav-link.active i {
        color: #ffffff !important;
        background-color: var(--primary-color);
        padding: 6px;
        border-radius: 6px;
        margin-right: 10px;
    }

    .custom-nav-tabs .nav-link:hover:not(.active) {
        background-color: color-mix(in srgb, var(--primary-color) 10%, transparent) !important;
    }

    .subcategory-count-sticker {
        display: inline-block;
        padding: 6px 12px;
        font-size: 0.85rem;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 8px;
        background-color: #f8f9fa;
        color: #000;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
    }

    .expand-btn {
        color: color-mix(in srgb, var(--primary-color) 40%, transparent);
        padding: 0 !important;
        transition: all 0.2s ease;
    }

    .expand-btn i {
        display: flex !important;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        border: 2px solid color-mix(in srgb, var(--primary-color) 60%, transparent);
        border-radius: 4px;
        font-size: 10px;
        font-weight: 900;
        color: color-mix(in srgb, var(--primary-color) 55%, transparent)
    }

    .expand-btn:hover {
        background-color: color-mix(in srgb, var(--primary-color) 50%, transparent) !important;
    }

    .expand-btn:hover i {
        color: #fff !important;
        border-color: var(--primary-color) !important;
        background-color: var(--primary-color) !important;
    }

    .card .card-footer {
        border-top: 1px solid #F4F4F2 !important;
    }

    /* View toggle — mirrors .custom-nav-tabs styling */
    .view-toggle-group {
        display: inline-flex;
        padding: 8px;
        border-radius: 12px;
        gap: 8px;
    }

    .view-toggle-group .view-toggle-btn {
        border: none !important;
        background: transparent;
        color: #007bff !important;
        font-weight: 700;
        font-size: 14px;
        padding: 12px 20px !important;
        border-radius: 10px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .view-toggle-group .view-toggle-btn:focus,
    .view-toggle-group .view-toggle-btn:focus-visible,
    .view-toggle-group .view-toggle-btn:active {
        outline: none !important;
        box-shadow: none !important;
    }

    .view-toggle-group .view-toggle-btn i {
        margin-right: 10px;
        font-size: 16px;
    }

    .view-toggle-group .view-toggle-btn:hover:not(.active) {
        background-color: color-mix(in srgb, var(--primary-color) 10%, transparent) !important;
    }

    .view-toggle-group .view-toggle-btn.active {
        background-color: color-mix(in srgb, var(--primary-color) 20%, transparent) !important;
        color: var(--primary-color) !important;
    }

    .view-toggle-group .view-toggle-btn.active i {
        color: #ffffff !important;
        background-color: var(--primary-color);
        padding: 6px;
        border-radius: 6px;
        margin-right: 10px;
    }

    /* Tree view */
    .category-tree-wrapper {
        padding: 8px 4px 16px;
    }

    .category-tree,
    .category-tree ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .category-tree ul {
        padding-left: 75px;
        position: relative;
    }

    .category-tree ul::before {
        content: "";
        position: absolute;
        left: 55px;
        top: 0;
        bottom: 12px;
        width: 2px;
        background: color-mix(in srgb, var(--primary-color) 25%, transparent);
        border-radius: 2px;
    }

    .category-tree li {
        position: relative;
    }

    .category-tree li>.tree-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 7px 10px;
        border-radius: 8px;
        position: relative;
        transition: background 0.15s ease;
    }

    .category-tree li>.tree-row:hover {
        background: color-mix(in srgb, var(--primary-color) 8%, transparent);
    }

    .category-tree ul>li>.tree-row::before {
        content: "";
        position: absolute;
        left: -22px;
        top: 50%;
        width: 20px;
        height: 2px;
        background: color-mix(in srgb, var(--primary-color) 25%, transparent);
        border-radius: 2px;
    }

    .tree-toggle {
        width: 26px;
        height: 26px;
        border: none;
        background: color-mix(in srgb, var(--primary-color) 12%, transparent);
        color: var(--primary-color);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        transition: transform 0.2s ease, background 0.2s ease;
    }

    .tree-toggle:hover {
        background: var(--primary-color);
        color: #fff;
    }

    .tree-toggle i {
        font-size: 11px;
        transition: transform 0.2s ease;
    }

    .tree-node.expanded>.tree-row>.tree-toggle i {
        transform: rotate(90deg);
    }

    .tree-toggle-placeholder {
        width: 26px;
        height: 26px;
        flex-shrink: 0;
        display: inline-block;
    }

    .tree-img {
        width: 34px;
        height: 34px;
        object-fit: cover;
        border-radius: 8px;
        background: #f4f6fa;
        flex-shrink: 0;
        border: 1px solid #e9ecef;
    }

    .tree-name {
        font-size: 14px;
        color: #2c3e50;
        font-weight: 500;
    }

    .tree-row .subcategory-count-sticker {
        padding: 3px 9px;
        font-size: 0.75rem;
    }

    .tree-empty {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('categories', "Categories") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"> <?= labels('category', 'Categories') ?></a></div>
            </div>
        </div>
        <div class="row">
            <?php
            if ($permissions['create']['categories'] == 1 && $permissions['create']['seo_settings'] == 1) { ?>
                <div
                    class="<?= ($permissions['read']['categories'] == 1 && $permissions['read']['seo_settings'] == 1) ? 'col-md-4' : 'col-md-12' ?>">
                    <?= helper('form'); ?>
                    <?= form_open('/admin/category/add_category', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_Category', 'enctype' => "multipart/form-data"]); ?>
                    <div class="card">
                        <div class="card-header p-0">
                            <ul class="nav nav-tabs w-100 custom-nav-tabs" id="categoryTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab"
                                        aria-controls="general" aria-selected="true">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= labels('general_info', 'General Info') ?>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="seo-tab" data-toggle="tab" href="#seo" role="tab"
                                        aria-controls="seo" aria-selected="false">
                                        <i class="fas fa-globe mr-1"></i> <?= labels('seo_settings', 'SEO Settings') ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="categoryTabsContent">
                                <div class="tab-pane fade show active" id="general" role="tabpanel"
                                    aria-labelledby="general-tab">
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
                                                        <span
                                                            class="language-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                            <?= $language['language'] ?>
                                                            <?= $language['is_default'] ? '(Default)' : '' ?>
                                                        </span>
                                                        <div class="language-underline"
                                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <?php
                                        // Use sorted languages for content divs as well
                                        foreach ($sorted_languages as $index => $language) {
                                            ?>
                                            <div class="col-12" id="translationDiv-<?= $language['code'] ?>"
                                                <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                                <div class="form-group">
                                                    <label for="category_name"
                                                        class="required"><?= labels('name', 'Name') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                    <input id="category_name" class="form-control" type="text"
                                                        <?= $language['is_default'] ? 'data-slug-source data-slug-target="#category_slug"' : '' ?>
                                                        name="name[<?= $language['code'] ?>]"
                                                        placeholder="<?= labels('enter_name_of_category', 'Enter the name of the Category here') ?>">
                                                </div>
                                            </div>
                                        <?php } ?>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="category_slug"
                                                    class="required"><?= labels('slug', 'Slug') ?></label>
                                                <input id="category_slug" class="form-control" type="text"
                                                    name="category_slug"
                                                    placeholder="<?= labels('enter_category_slug_here', 'Enter the slug of the Category here') ?>">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="make_parent"
                                                    class="required"><?= labels('type', 'Type') ?></label><br>
                                                <select name="make_parent" id="make_parent" class="form-control">
                                                    <option value="0"><?= labels('category', 'Category') ?></option>
                                                    <option value="1"><?= labels('sub_category', 'Sub Category') ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row" id="parent">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="category_ids" class="required">
                                                    <?= labels('select_parent_category', 'Select Parent Category') ?></label><br>
                                                <select name="parent_id" id="category_ids" class="form-control">
                                                    <option value="">
                                                        <?= labels('select_parent_category', 'Select Parent Category') ?>
                                                    </option>
                                                    <?php echo render_categories_options($categories, 0, 0); ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group"> <label for="image"
                                                    class="required"><?= labels('image', 'Image') ?></label> <small
                                                    id="image-recommendation">(<?= labels('category_image_recommended_size', 'We recommend 60x60 pixels') ?>)</small><br>
                                                <input type="file" class="filepond" name="image" id="image"
                                                    accept="image/*">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="color"
                                                    class="required"><?= labels('dark_theme_color', 'Dark Theme Color') ?></label>
                                                <br>
                                                <input type="color" name="dark_theme_color" id="dark_theme_color"
                                                    title="<?= labels('choose_color', 'Choose Color') ?>" value="#000000" />
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="color"
                                                    class="required"><?= labels('light_theme_color', 'Light Theme Color') ?></label>
                                                <br>
                                                <input type="color" name="light_theme_color" id="light_theme_color"
                                                    title="<?= labels('choose_color', 'Choose Color') ?>" value="#FFFFFF" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="seo" role="tabpanel" aria-labelledby="seo-tab">
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <div class="d-flex flex-wrap align-items-center gap-4">
                                                <?php
                                                // Get default language for SEO section
                                                $default_language_seo = '';
                                                foreach ($sorted_languages as $lang) {
                                                    if ($lang['is_default'] == 1) {
                                                        $default_language_seo = $lang['code'];
                                                        break;
                                                    }
                                                }
                                                foreach ($sorted_languages as $index => $language) {
                                                    ?>
                                                    <div class="language-seo-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                        id="language-seo-<?= $language['code'] ?>"
                                                        data-language="<?= $language['code'] ?>"
                                                        style="cursor: pointer; padding: 0.5rem 0;">
                                                        <span
                                                            class="language-seo-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                            <?= $language['language'] ?>
                                                            <?= $language['is_default'] ? '(Default)' : '' ?>
                                                        </span>
                                                        <div class="language-seo-underline"
                                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SEO content for each language -->
                                    <?php foreach ($sorted_languages as $index => $language) { ?>
                                        <div id="translationDivSeo-<?= $language['code'] ?>"
                                            <?= $language['code'] == $default_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="form-group">
                                                        <label
                                                            for="meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                        <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>"
                                                            class="fa fa-question-circle" data-original-title="" title=""
                                                            data-toggle="popover"></i>
                                                        <input id="meta_title<?= $language['code'] ?>" class="form-control"
                                                            type="text" name="meta_title[<?= $language['code'] ?>]"
                                                            placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>"
                                                            maxlength="255">
                                                        <small
                                                            class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-group">
                                                        <label
                                                            for="meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                        <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>"
                                                            class="fa fa-question-circle" data-original-title="" title=""
                                                            data-toggle="popover"></i>
                                                        <textarea id="meta_description<?= $language['code'] ?>"
                                                            style="min-height:60px" class="form-control" type="text"
                                                            name="meta_description[<?= $language['code'] ?>]" rowspan="10"
                                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>"
                                                            maxlength="500"></textarea>
                                                        <small
                                                            class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="form-group">
                                                        <label
                                                            for="meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                        <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>"
                                                            class="fa fa-question-circle" data-original-title="" title=""
                                                            data-toggle="popover"></i>
                                                        <input id="meta_keywords<?= $language['code'] ?>"
                                                            style="border-radius: 0.25rem" class="w-100" type="text"
                                                            name="meta_keywords[<?= $language['code'] ?>][]"
                                                            placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>">
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-group">
                                                        <label
                                                            for="schema_markup<?= $language['code'] ?>"><?= labels('schema_markup', 'Schema Markup') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                        <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                                            data-toggle="popover" class="fa fa-question-circle"
                                                            data-original-title="" title=""></i>
                                                        <textarea id="schema_markup<?= $language['code'] ?>"
                                                            style="min-height:60px" class="form-control" type="text"
                                                            name="schema_markup[<?= $language['code'] ?>]" rowspan="10"
                                                            placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>

                                    <!-- Meta Image (shared across all languages) -->
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                                                <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>"
                                                    class="fa fa-question-circle" data-original-title="" title=""
                                                    data-toggle="popover"></i>
                                                <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                                <input type="file" class="filepond" name="meta_image" id="meta_image"
                                                    accept="image/*">
                                                <small
                                                    class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="row">
                                <div class="col-md d-flex justify-content-end">
                                    <button type="submit"
                                        class="btn bg-new-primary submit_btn"><?= labels('add_category', 'Add Category') ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?= form_close(); ?>
                </div>
            <?php }
            ?>
            <?php
            if ($permissions['read']['categories'] == 1 && $permissions['read']['seo_settings'] == 1) { ?>
                <div
                    class="<?= ($permissions['create']['categories'] == 1 && $permissions['create']['seo_settings'] == 1) ? 'col-md-8' : 'col-md-12' ?>">
                    <div class="card ">

                        <div class="row m-0">
                            <div class="col mb-3 d-flex justify-content-between align-items-center flex-wrap"
                                style="border-bottom: solid 1px #e5e6e9;">
                                <div class="toggleButttonPostition"><?= labels('category_list', 'Category List') ?></div>
                                <div class="view-toggle-group" role="group"
                                    aria-label="<?= labels('view_mode', 'View Mode') ?>">
                                    <button type="button" class="view-toggle-btn active" data-view="list"
                                        id="viewToggleList">
                                        <i class="fas fa-list"></i> <?= labels('list_view', 'List View') ?>
                                    </button>
                                    <button type="button" class="view-toggle-btn" data-view="tree" id="viewToggleTree">
                                        <i class="fas fa-sitemap"></i> <?= labels('tree_view', 'Tree View') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="list-view-container">
                            <div class="row pb-3 pl-3">
                                <div class="col-12">
                                    <div class="row mb-3 mt-3">
                                        <div class="col-md-4 col-sm-2 mb-2">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="customSearch"
                                                    placeholder="<?= labels('search_here', 'Search here!') ?>"
                                                    aria-label="Search" aria-describedby="customSearchBtn">
                                                <div class="input-group-append">
                                                    <button class="btn btn-primary" id="customSearchBtn" type="button">
                                                        <i class="fa fa-search d-inline"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-secondary  ml-2 filter_button" id="filterButton">
                                            <span class="material-symbols-outlined mt-1">
                                                filter_alt
                                            </span>
                                        </button>
                                        <div class="dropdown d-inline ml-2">
                                            <button class="btn export_download dropdown-toggle" type="button"
                                                id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                                aria-expanded="false">
                                                <?= labels('download', 'Download') ?>
                                            </button>
                                            <div class="dropdown-menu" x-placement="bottom-start"
                                                style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                                <a class="dropdown-item"
                                                    onclick="custome_export('pdf','Category list','category_list');">
                                                    <?= labels('pdf', 'PDF') ?></a>
                                                <a class="dropdown-item"
                                                    onclick="custome_export('excel','Category list','category_list');">
                                                    <?= labels('excel', 'Excel') ?></a>
                                                <a class="dropdown-item"
                                                    onclick="custome_export('csv','Category list','category_list')">
                                                    <?= labels('csv', 'CSV') ?></a>
                                            </div>
                                        </div>
                                    </div>
                                    <table class="table " data-fixed-columns="true" id="category_list"
                                        data-pagination-successively-size="2" data-detail-formatter="category_formater"
                                        data-query-params="category_tree_query_params" data-auto-refresh="true"
                                        data-toggle="table" data-url="<?= base_url("admin/categories/list") ?>"
                                        data-side-pagination="server" data-pagination="true"
                                        data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false"
                                        data-show-columns="false" data-show-columns-search="true" data-show-refresh="false"
                                        data-sort-name="id" data-sort-order="desc"
                                        data-row-attributes="categoryRowAttributes">
                                        <thead>
                                            <tr>
                                                <th data-field="id" data-visible="true" class="text-center"
                                                    data-sortable="true"><?= labels('id', 'ID') ?></th>
                                                <th data-field="category_image" class="text-center">
                                                    <?= labels('image', 'Image') ?>
                                                </th>
                                                <th data-field="parent_id" data-visible="false" class="text-center"
                                                    data-sortable="true"><?= labels('parent_Id', 'Parent Id') ?></th>
                                                <th data-field="parent_category_name" class="text-center"
                                                    data-visible="false">
                                                    <?= labels('parent_category_name', 'Parent Category Name') ?>
                                                </th>
                                                <th data-field="name" class="text-center"
                                                    data-formatter="categoryNameFormatter"><?= labels('name', 'Name') ?>
                                                </th>
                                                <th data-field="children_count" data-switchable="false" class="text-center"
                                                    data-formatter="categoryChildrenCountFormatter">
                                                    <?= labels('subcategories_count', 'Subcategories Count') ?>
                                                </th>
                                                <th data-field="slug" class="text-center" data-visible="false">
                                                    <?= labels('slug', 'Slug') ?>
                                                </th>

                                                <th data-field="dark_color_format" class="text-center" data-visible="false">
                                                    <?= labels('dark_theme_color', 'Dark Color') ?>
                                                </th>
                                                <th data-field="light_color_format" class="text-center"
                                                    data-visible="false"><?= labels('light_theme_color', 'Light Color') ?>
                                                </th>
                                                <th data-field="created_at" data-visible="false" class="text-center"
                                                    data-sortable="true"><?= labels('created_at', 'Created At') ?></th>
                                                <th data-field="operations" class="text-center"
                                                    data-events="Category_events"><?= labels('operations', 'Operations') ?>
                                                </th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div><!-- /#list-view-container -->
                        <div id="tree-view-container" style="display:none;">
                            <div class="category-tree-wrapper">
                                <?php
                                $disk_for_tree = function_exists('fetch_current_file_manager') ? fetch_current_file_manager() : 'local_server';
                                $default_cat_img = base_url('public/backend/assets/default.png');
                                $resolve_cat_image = function ($img) use ($disk_for_tree, $default_cat_img) {
                                    if (empty($img))
                                        return $default_cat_img;
                                    if ($disk_for_tree === 'aws_s3' && function_exists('fetch_cloud_front_url')) {
                                        return fetch_cloud_front_url('categories', $img);
                                    }
                                    return base_url('public/uploads/categories/' . basename($img));
                                };
                                $tree_map = [];
                                foreach (($categories ?? []) as $c) {
                                    $pid = (int) ($c['parent_id'] ?? 0);
                                    $tree_map[$pid][] = $c;
                                }
                                $render_tree = function ($parent_id) use (&$render_tree, $tree_map, $resolve_cat_image, $default_cat_img) {
                                    if (empty($tree_map[$parent_id]))
                                        return '';
                                    $html = '<ul' . ($parent_id === 0 ? ' class="category-tree"' : ' class="tree-children" style="display:none;"') . '>';
                                    foreach ($tree_map[$parent_id] as $node) {
                                        $nid = (int) $node['id'];
                                        $hasKids = !empty($tree_map[$nid]);
                                        $kidCount = $hasKids ? count($tree_map[$nid]) : 0;
                                        $img = $resolve_cat_image($node['image'] ?? '');
                                        $name = htmlspecialchars($node['name'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $html .= '<li class="tree-node" data-id="' . $nid . '" data-has-children="' . ($hasKids ? '1' : '0') . '">';
                                        $html .= '<div class="tree-row">';
                                        if ($hasKids) {
                                            $html .= '<button type="button" class="tree-toggle" aria-label="expand"><i class="fas fa-chevron-right"></i></button>';
                                        } else {
                                            $html .= '<span class="tree-toggle-placeholder"></span>';
                                        }
                                        $html .= '<img class="tree-img" src="' . $img . '" alt="" onerror="this.onerror=null;this.src=\'' . $default_cat_img . '\';">';
                                        $html .= '<span class="tree-name">' . $name . '</span>';
                                        if ($hasKids) {
                                            $html .= '<span class="subcategory-count-sticker">' . $kidCount . '</span>';
                                        }
                                        $html .= '</div>';
                                        if ($hasKids) {
                                            $html .= $render_tree($nid);
                                        }
                                        $html .= '</li>';
                                    }
                                    $html .= '</ul>';
                                    return $html;
                                };
                                $tree_html = $render_tree(0);
                                echo $tree_html !== '' ? $tree_html : '<div class="tree-empty"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i>' . labels('no_categories_found', 'No categories found') . '</div>';
                                ?>
                            </div>
                        </div><!-- /#tree-view-container -->
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>

    <div class="modal fade" id="update_modal" tabindex="-1" aria-labelledby="update_modal_thing" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <?= form_open('admin/category/update_category', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_Category', 'enctype' => "multipart/form-data"]); ?>
                <div class="modal-header m-0 p-0">
                    <div class="row pl-3 w-100">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('update_category', 'Update Category') ?>
                            </div>
                        </div>
                        <div class="col d-flex justify-content-end  mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs w-100 custom-nav-tabs mb-3" id="updateCategoryTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="update-general-tab" data-toggle="tab" href="#update-general"
                                role="tab" aria-controls="update-general" aria-selected="true">
                                <i class="fas fa-info-circle"></i> <?= labels('general_info', 'General Info') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="update-seo-tab" data-toggle="tab" href="#update-seo" role="tab"
                                aria-controls="update-seo" aria-selected="false">
                                <i class="fas fa-globe"></i> <?= labels('seo_settings', 'SEO Settings') ?>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content" id="updateCategoryTabsContent">
                        <div class="tab-pane fade show active" id="update-general" role="tabpanel"
                            aria-labelledby="update-general-tab">

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                        <?php
                                        foreach ($languages as $index => $language) {
                                            if ($language['is_default'] == 1) {
                                                $current_modal_language = $language['code'];
                                            }
                                            ?>
                                            <div class="language-modal-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                id="language-modal-<?= $language['code'] ?>"
                                                data-language="<?= $language['code'] ?>"
                                                style="cursor: pointer; padding: 0.5rem 0;">
                                                <span
                                                    class="language-modal-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                    style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                    <?= $language['language'] ?>
                                                    <?= $language['is_default'] ? '(Default)' : '' ?>
                                                </span>
                                                <div class="language-modal-underline"
                                                    style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="edit_make_parent"><?= labels('type', 'Type') ?></label><br>
                                        <select name="edit_make_parent" id="edit_make_parent" class="form-control">
                                            <option value="0"><?= labels('category', 'Category') ?></option>
                                            <option value="1"><?= labels('sub_category', 'Sub Category') ?></option>
                                        </select>
                                    </div>
                                </div>

                                <?php
                                foreach ($languages as $index => $language) {
                                    ?>
                                    <div class="col-12" id="translationModalDiv-<?= $language['code'] ?>"
                                        <?= $language['code'] == $current_modal_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="form-group">
                                            <label
                                                for="edit_name_modal<?= $language['code'] ?>"><?= labels('name', 'Name') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <input id="edit_name_modal<?= $language['code'] ?>" class="form-control"
                                                type="text" name="name[<?= $language['code'] ?>]"
                                                placeholder="Enter the name of the Category here" autocomplete="off">
                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="category_slug"
                                            class="required"><?= labels('slug', 'Slug') ?></label>
                                        <input id="edit_category_slug" class="form-control" type="text"
                                            name="category_slug"
                                            placeholder="<?= labels('enter_category_slug_here', 'Enter the slug of the Category here') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row" id="edit_parent">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label
                                            for="category_ids"><?= labels('select_parent_category', 'Select Parent Category') ?></label><br>
                                        <select name="edit_parent_id" id="edit_category_ids" class="form-control">
                                            <option value="">
                                                <?= labels('select_parent_category', 'Select Parent Category') ?>
                                            </option>
                                            <?php echo render_categories_options($categories, 0, 0); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="id" id="id">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="mb-3">
                                            <?= labels('image', "Image") ?>
                                            <input type="file" name="image" class="filepond" id="formFile"
                                                accept="image/*">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label
                                            for="edit_dark_theme_color"><?= labels('dark_theme_color', 'Dark Theme Color') ?></label>
                                        <input type="color" name="edit_dark_theme_color" id="edit_dark_theme_color"
                                            class="form-control" />
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label
                                            for="edit_light_theme_color"><?= labels('light_theme_color', 'Light Theme Color') ?></label>
                                        <input type="color" name="edit_light_theme_color" id="edit_light_theme_color"
                                            class="form-control" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div id="edit_categoryImage"
                                        style="width: 200px; height: 150px; border: 1px solid ;border-color: #e4e6fc;border-radius: 0.35rem;margin-bottom:25px ">
                                        <img src="" alt="old_image"
                                            style="display: block;margin-left: auto;margin-top: 25px;margin-right: auto;width: 80%;"
                                            width="50%" height="100px" id="category_image" id="update_service_image"
                                            onerror="this.onerror=null;this.src='<?= base_url('public/backend/assets/default.png') ?>';">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="update-seo" role="tabpanel" aria-labelledby="update-seo-tab">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                        <?php
                                        // Get default language for SEO section in modal
                                        $default_language_seo_modal = '';
                                        foreach ($languages as $lang) {
                                            if ($lang['is_default'] == 1) {
                                                $default_language_seo_modal = $lang['code'];
                                                break;
                                            }
                                        }
                                        foreach ($languages as $index => $language) {
                                            ?>
                                            <div class="language-seo-modal-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                id="language-seo-modal-<?= $language['code'] ?>"
                                                data-language="<?= $language['code'] ?>"
                                                style="cursor: pointer; padding: 0.5rem 0;">
                                                <span
                                                    class="language-seo-modal-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                    style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                    <?= $language['language'] ?>
                                                    <?= $language['is_default'] ? '(Default)' : '' ?>
                                                </span>
                                                <div class="language-seo-modal-underline"
                                                    style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;">
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>

                            <!-- SEO content for each language in edit modal -->
                            <?php foreach ($languages as $index => $language) { ?>
                                <div id="translationSeoDiv-<?= $language['code'] ?>"
                                    <?= $language['code'] == $default_language_seo_modal ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label
                                                    for="edit_meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>"
                                                    class="fa fa-question-circle" data-original-title="" title=""
                                                    data-toggle="popover"></i>
                                                <input id="edit_meta_title<?= $language['code'] ?>" class="form-control"
                                                    type="text" name="meta_title[<?= $language['code'] ?>]"
                                                    placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>"
                                                    maxlength="255">
                                                <small
                                                    class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label
                                                    for="edit_meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>"
                                                    class="fa fa-question-circle" data-original-title="" title=""
                                                    data-toggle="popover"></i>
                                                <textarea id="edit_meta_description<?= $language['code'] ?>"
                                                    style="min-height:60px" class="form-control" type="text"
                                                    name="meta_description[<?= $language['code'] ?>]" rowspan="10"
                                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>"
                                                    maxlength="500"></textarea>
                                                <small
                                                    class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label
                                                    for="edit_meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>"
                                                    class="fa fa-question-circle" data-original-title="" title=""
                                                    data-toggle="popover"></i>
                                                <input id="edit_meta_keywords<?= $language['code'] ?>"
                                                    style="border-radius: 0.25rem" class="w-100" type="text"
                                                    name="meta_keywords[<?= $language['code'] ?>][]"
                                                    placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label
                                                    for="edit_schema_markup<?= $language['code'] ?>"><?= labels('schema_markup', 'Schema Markup') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                                    data-toggle="popover" class="fa fa-question-circle"
                                                    data-original-title="" title=""></i>
                                                <textarea id="edit_schema_markup<?= $language['code'] ?>"
                                                    style="min-height:60px" class="form-control" type="text"
                                                    name="schema_markup[<?= $language['code'] ?>]" rowspan="10"
                                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <!-- Meta Image (shared across all languages) -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                                        <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>"
                                            class="fa fa-question-circle" data-original-title="" title=""
                                            data-toggle="popover"></i>
                                        <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                        <input type="file" class="filepond" name="meta_image" id="edit_meta_image"
                                            accept="image/*">
                                        <small
                                            class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                                        <div id="edit_categoryMetaImage"
                                            style="width: 200px; height: 150px; border: 1px solid ;border-color: #e4e6fc;border-radius: 0.35rem;margin-bottom:25px; display: none; position: relative;">
                                            <img src="" alt="old_image"
                                                style="display: block;margin-left: auto;margin-top: 25px;margin-right: auto;width: 80%;"
                                                width="50%" height="100px" id="edit_meta_image_preview">
                                            <button type="button"
                                                class="btn btn-sm btn-danger remove-category-seo-image"
                                                style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; padding: 0; border-radius: 50%; font-size: 10px;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        data-dismiss="modal"><?= labels('close', "Close") ?></button>
                    <button type="submit"
                        class="btn bg-new-primary submit_btn"><?= labels('update_category', 'Update Category') ?></button>
                </div>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
</div>
<div id="filterBackdrop"></div>
<div class="drawer" id="filterDrawer">
    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="bg-new-primary" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <div class="bg-white m-3 text-new-primary"
                            style="box-shadow: 0px 8px 26px #00b9f02e; display: inline-block; padding: 10px; height: 45px; width: 45px; border-radius: 15px;">
                            <span class="material-symbols-outlined">
                                filter_alt
                            </span>
                        </div>
                        <h3 class="mb-0" style="display: inline-block; font-size: 16px; margin-left: 10px;">
                            <?= labels('filters', "Filters") ?>
                        </h3>
                    </div>
                    <div id="cancelButton" style="cursor: pointer;">
                        <span class="material-symbols-outlined mr-2">
                            cancel
                        </span>
                    </div>
                </div>
                <div class="row mt-4 mx-2">
                    <div class="col-md-12">
                        <div class="form-group ">
                            <label for="table_filters"><?= labels('table_filters', 'Table filters') ?></label>
                            <div id="columnToggleContainer">
                            </div>
                            <button class="btn btn-primary d-block mt-3"
                                id="apply_filter"><?= labels('apply', 'Apply') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    // Global variables for AJAX requests
    var base_url = '<?= base_url() ?>';
    var csrf_token_name = '<?= csrf_token() ?>';
    var csrf_token_value = '<?= csrf_hash() ?>';

    var picker1 = document.getElementById('dark_theme_color');
    var box1 = document.getElementById('categoryImage');
    picker1.addEventListener('change', function () {
        box1.style.backgroundColor = this.value;
    })
    var picker2 = document.getElementById('light_theme_color');
    var box2 = document.getElementById('categoryImage');
    picker2.addEventListener('change', function () {
        box2.style.backgroundColor = this.value;
    })
    var picker3 = document.getElementById('edit_light_theme_color');
    var box3 = document.getElementById('edit_categoryImage');
    picker3.addEventListener('change', function () {
        box3.style.backgroundColor = this.value;
    })
    var picker4 = document.getElementById('edit_dark_theme_color');
    var box4 = document.getElementById('edit_categoryImage');
    picker4.addEventListener('change', function () {
        box4.style.backgroundColor = this.value;
    })
</script>

<script>
    $(document).ready(function () {
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        var dynamicColumns = fetchColumns('category_list');
        setupColumnToggle('category_list', dynamicColumns, 'columnToggleContainer');

        // Initialize Tagify for meta keywords fields (all language-specific inputs)
        $(document).ready(function () {
            // Target all keyword inputs: meta_keywords[lang] and edit_meta_keywords[lang]
            var metaKeywordsInputs = document.querySelectorAll('input[id^="meta_keywords"], input[id^="edit_meta_keywords"]');
            if (metaKeywordsInputs != null && metaKeywordsInputs.length > 0) {
                metaKeywordsInputs.forEach(input => {
                    if (input && !input.tagify) {
                        input.tagify = new Tagify(input);
                    }
                });
            }
        });

        // SEO language tab switching for Add Category form
        let default_language_seo = '<?= isset($default_language_seo) ? $default_language_seo : $current_language ?>';
        $(document).on('click', '.language-seo-option', function () {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-seo-underline').css('width', '0%');
            $('#language-seo-' + language).find('.language-seo-underline').css('width', '100%');

            // Update text styling
            $('.language-seo-text').removeClass('text-primary fw-medium');
            $('.language-seo-text').addClass('text-muted');
            $('#language-seo-' + language).find('.language-seo-text').removeClass('text-muted');
            $('#language-seo-' + language).find('.language-seo-text').addClass('text-primary fw-medium');

            // Show/hide translation divs
            if (language != default_language_seo) {
                $('#translationDivSeo-' + language).show();
                $('#translationDivSeo-' + default_language_seo).hide();
            }

            default_language_seo = language;
        });

        // SEO language tab switching for Edit Category modal
        let default_language_seo_modal = '<?= isset($default_language_seo_modal) ? $default_language_seo_modal : $current_modal_language ?>';
        $(document).on('click', '.language-seo-modal-option', function () {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-seo-modal-underline').css('width', '0%');
            $('#language-seo-modal-' + language).find('.language-seo-modal-underline').css('width', '100%');

            // Update text styling
            $('.language-seo-modal-text').removeClass('text-primary fw-medium');
            $('.language-seo-modal-text').addClass('text-muted');
            $('#language-seo-modal-' + language).find('.language-seo-modal-text').removeClass('text-muted');
            $('#language-seo-modal-' + language).find('.language-seo-modal-text').addClass('text-primary fw-medium');

            // Show/hide translation divs
            if (language != default_language_seo_modal) {
                $('#translationSeoDiv-' + language).show();
                $('#translationSeoDiv-' + default_language_seo_modal).hide();
            }

            default_language_seo_modal = language;
        });

        // Image recommendation text switch (Category / Subcategory)
        const $typeSelect = $('#make_parent');
        const $imageHint = $('#image-recommendation');

        if ($typeSelect.length && $imageHint.length) {
            const imageHintText = {
                category: "<?= labels('category_image_recommended_size', 'We recommend 60x60 pixels') ?>",
                subcategory: "<?= labels('subcategory_image_recommended_size', 'We recommend 260 x 345 pixels') ?>"
            };

            const updateImageHint = () => {
                $imageHint.text(
                    $typeSelect.val() === '1' ?
                        `(${imageHintText.subcategory})` :
                        `(${imageHintText.category})`
                );
            };

            updateImageHint(); // initial state
            $typeSelect.on('change', updateImageHint);
        }
    });
</script>

<script>
    $(function () {
        let popoverTimer;
        let currentPopover = null;
        let isOverPopover = false;
        let isOverTrigger = false;

        $('[data-toggle="popover"]').popover({
            html: true,
            trigger: 'manual',
            container: 'body'
        }).on('mouseenter', function () {
            const $this = $(this);
            isOverTrigger = true;
            clearTimeout(popoverTimer);

            // Hide other popovers
            if (currentPopover && currentPopover[0] !== $this[0]) {
                currentPopover.popover('hide');
            }

            currentPopover = $this;
            $this.popover('show');

        }).on('mouseleave', function () {
            isOverTrigger = false;
            startHideTimer();
        });

        // Handle popover content hover
        $(document).on('mouseenter', '.popover', function () {
            isOverPopover = true;
            clearTimeout(popoverTimer);
        }).on('mouseleave', '.popover', function () {
            isOverPopover = false;
            startHideTimer();
        });

        function startHideTimer() {
            clearTimeout(popoverTimer);
            popoverTimer = setTimeout(function () {
                if (!isOverTrigger && !isOverPopover && currentPopover) {
                    currentPopover.popover('hide');
                    currentPopover = null;
                }
            }, 150);
        }
    });


    // Search button click handler - triggers table refresh when search button is clicked
    $("#customSearchBtn").on('click', function () {
        $('#category_list').bootstrapTable('refresh');
    });

    // Allow Enter key to trigger search button click
    $("#customSearch").on('keypress', function (e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#customSearchBtn').click();
        }
    });

    // Handle category SEO image removal
    $(document).on('click', '.remove-category-seo-image', function () {
        const button = $(this);
        const categoryId = $('#id').val(); // Get category ID from hidden input
        Swal.fire({
            title: '<?= labels('are_your_sure', 'Are you sure?') ?>',
            text: '<?= labels('are_you_sure_to_remove_seo_image') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!') ?>',
            cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
        }).then((result) => {
            if (!result.isConfirmed) return;
            // Show loading state
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            // Make AJAX request to remove SEO image
            $.ajax({
                url: '<?= base_url('admin/categories/remove_seo_image') ?>',
                type: 'POST',
                data: {
                    category_id: categoryId,
                    <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                },
                dataType: 'json',
                success: function (response) {
                    if (response.error === false) {
                        // Hide the image container
                        $('#edit_categoryMetaImage').hide();
                        // Clear the image preview
                        $('#edit_meta_image_preview').attr('src', '');
                        // Show success message
                        showToastMessage(response.message, 'success');
                        // Reset button state (even on success, since the button will be hidden)
                        button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                    } else {
                        // Show error message
                        showToastMessage(response.message, 'error');
                        // Reset button
                        button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                    }
                },
                error: function (xhr, status, error) {
                    // Show error message
                    showToastMessage('<?= labels('error_occured', 'An error occurred while removing the SEO image') ?>', 'error');
                    // Reset button
                    button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                }
            });
        });
    });

    // Handle file input change to reset button state when new image is uploaded
    $(document).on('change', '#edit_meta_image', function () {
        // Reset any existing remove button to original state
        $('.remove-category-seo-image').prop('disabled', false).html('<i class="fas fa-times"></i>');
    });
</script>

<script>
    // select default language
    $(document).ready(function () {
        let default_language = '<?= $current_language ?>';
        let current_modal_language = '<?= $current_modal_language ?>';

        $(document).on('click', '.language-option', function () {
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

        $(document).on('click', '.language-modal-option', function () {
            const language = $(this).data('language');

            $('.language-modal-underline').css('width', '0%');
            $('#language-modal-' + language).find('.language-modal-underline').css('width', '100%');

            $('.language-modal-text').removeClass('text-primary fw-medium');
            $('.language-text-faqs').addClass('text-muted');
            $('#language-modal-' + language).find('.language-modal-text').removeClass('text-muted');
            $('#language-modal-' + language).find('.language-modal-text').addClass('text-primary');

            if (language != current_modal_language) {
                $('#translationModalDiv-' + language).show();
                $('#translationModalDiv-' + current_modal_language).hide();
            }

            current_modal_language = language;
        });

        // Handle parent category dropdown visibility in modal
        $(document).on('change', '#edit_make_parent', function () {
            if ($(this).val() == "1") {
                $("#edit_parent").show();
            } else {
                $("#edit_parent").hide();
            }
        });

        // Clear modal data when modal is hidden
        $('#update_modal').on('hidden.bs.modal', function () {
            // Clear form fields
            $('#update_modal input[type="text"], #update_modal textarea').val('');
            $('#update_modal select').prop('selectedIndex', 0);

            // Clear all multilanguage Tagify keyword inputs
            var tagifyInputs = document.querySelectorAll('#update_modal input[id^="edit_meta_keywords"]');
            if (tagifyInputs && tagifyInputs.length > 0) {
                tagifyInputs.forEach(function (input) {
                    if (input && input.tagify) {
                        input.tagify.removeAllTags();
                    }
                });
            }

            // Hide images
            $('#edit_categoryMetaImage').hide();
            $('#category_image').attr('src', '');

            // Reset parent dropdown visibility and restore all options
            $('#edit_parent').hide();
            $('#edit_make_parent').val('0');
            // Restore all parent category options that might have been hidden
            $('#edit_category_ids option').show();
        });
    });

    // ============================================================
    // Lazy-loaded hierarchical category tree (n-level)
    // ============================================================

    // Query params: roots-only initial load. Search bypasses tree filter.
    function category_tree_query_params(p) {
        var search = $("#customSearch").val() || p.search || '';
        var params = {
            search: search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset
        };
        if (!search) {
            params.parent_id = 'null';
        }
        return params;
    }

    function categoryRowAttributes(row, index) {
        return {
            'data-id': row.id,
            'data-parent-id': row.parent_id || '0',
            'data-depth': row._depth || 0
        };
    }

    function categoryChildrenCountFormatter(value, row, index) {
        var n = parseInt(value || 0, 10);
        if (n <= 0) {
            return '<span class="subcategory-count-sticker">0</span>';
        }
        return '<span class="subcategory-count-sticker">' + n + '</span>';
    }

    function categoryNameFormatter(value, row, index) {
        var depth = parseInt(row._depth || 0, 10);
        var pad = depth * 20;
        var name = (value == null) ? '' : value;
        var btn;
        if (row.has_children) {
            btn = '<button type="button" class="btn btn-sm btn-icon expand-btn mr-3" '
                + 'data-id="' + row.id + '" data-depth="' + depth + '" aria-label="expand">'
                + '<i class="fas fa-plus"></i></button>';
        } else {
            btn = '<span class="expand-spacer mr-2" style="display:inline-block;width:31px;"></span>';
        }
        return '<div class="d-flex align-items-center text-left" style="padding-left:' + pad + 'px;">'
            + btn + '<span>' + name + '</span></div>';
    }

    // Build a child <tr> using the live Bootstrap Table column metadata so it
    // honours formatters, visibility and the operations event hooks.
    function buildCategoryChildRow(row, depth, parentId) {
        row._depth = depth;
        var $tbl = $('#category_list');
        var options = $tbl.bootstrapTable('getOptions');
        var cols = options.columns[0];
        var html = '<tr class="child-row parent-' + parentId + '" '
            + 'data-id="' + row.id + '" '
            + 'data-parent-id="' + parentId + '" '
            + 'data-depth="' + depth + '" '
            + 'style="display:none;">';
        $.each(cols, function (_, col) {
            var visible = col.visible !== false;
            var styleAttr = visible ? '' : ' style="display:none;"';
            var fn = col.formatter;
            if (typeof fn === 'string') fn = window[fn];
            var v = row[col.field];
            if (typeof fn === 'function') {
                try { v = fn(v, row, 0); } catch (e) { }
            }
            if (v === undefined || v === null) v = '';
            html += '<td class="' + (col['class'] || '') + '"' + styleAttr + '>' + v + '</td>';
        });
        html += '</tr>';
        var $tr = $(html);
        $tr.data('rowData', row);
        return $tr;
    }

    // Expand / collapse handler — first click loads via AJAX, subsequent clicks toggle.
    $(document).on('click', '#category_list .expand-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        if ($btn.prop('disabled')) return;

        var $row = $btn.closest('tr');
        var parentId = String($row.attr('data-id'));
        var depth = parseInt($row.attr('data-depth') || '0', 10);
        var loaded = $row.attr('data-loaded') === 'true';
        var expanded = $row.attr('data-expanded') === 'true';
        var $icon = $btn.find('i');

        if (loaded) {
            // Toggle visibility of immediate children only; nested grandchildren
            // collapse with their parent because we hide all parent-{id} rows.
            if (expanded) {
                $('#category_list tbody tr.parent-' + parentId).hide()
                    .each(function () {
                        $(this).attr('data-expanded', 'false');
                        $(this).find('.expand-btn i').removeClass('fa-minus').addClass('fa-plus');
                    });
                $row.attr('data-expanded', 'false');
                $icon.removeClass('fa-minus').addClass('fa-plus');
            } else {
                $('#category_list tbody tr.parent-' + parentId).show();
                $row.attr('data-expanded', 'true');
                $icon.removeClass('fa-plus').addClass('fa-minus');
            }
            return;
        }

        // First expansion — fetch children
        $btn.prop('disabled', true);
        $icon.removeClass('fa-plus').addClass('fa-spinner fa-spin');

        $.ajax({
            url: '<?= base_url('admin/categories/list') ?>',
            type: 'GET',
            data: {
                parent_id: parentId,
                limit: 100000,
                offset: 0,
                sort: 'id',
                order: 'ASC',
                search: ''
            },
            dataType: 'json'
        }).done(function (resp) {
            var rows = (resp && resp.rows) ? resp.rows : [];
            // Insertion point: after parent row, before any pre-existing siblings/grandchildren block
            var $insertAfter = $row;
            $.each(rows, function (_, child) {
                // Guard against duplicate insertion
                if ($('#category_list tbody tr[data-id="' + child.id + '"][data-parent-id="' + parentId + '"]').length) {
                    return;
                }
                var $childRow = buildCategoryChildRow(child, depth + 1, parentId);
                $childRow.show();
                $insertAfter.after($childRow);
                $insertAfter = $childRow;
            });
            $row.attr('data-loaded', 'true').attr('data-expanded', 'true');
            $icon.removeClass('fa-spinner fa-spin').addClass('fa-minus');
        }).fail(function () {
            $icon.removeClass('fa-spinner fa-spin').addClass('fa-plus');
            if (typeof showToastMessage === 'function') {
                showToastMessage('<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
            }
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // Edit-button delegation for child rows (Bootstrap-Table data-events only fires for its own tracked rows).
    $(document).on('click', '#category_list tbody tr.child-row .edite-Category', function (e) {
        var row = $(this).closest('tr').data('rowData') || { id: $(this).data('id') };
        if (window.Category_events && typeof window.Category_events['click .edite-Category'] === 'function') {
            window.Category_events['click .edite-Category'](e, null, row, 0);
        }
    });

    // Delete-button delegation for child rows.
    $(document).on('click', '#category_list tbody tr.child-row .delete-Category', function (e) {
        var row = $(this).closest('tr').data('rowData') || { id: $(this).data('id') };
        if (window.Category_events && typeof window.Category_events['click .delete-Category'] === 'function') {
            window.Category_events['click .delete-Category'](e, null, row, 0);
        }
    });

    // ============================================================
    // View toggle (list / tree) + tree expand
    // ============================================================
    (function () {
        var STORAGE_KEY = 'admin_categories_view';
        var $listBtn = $('#viewToggleList');
        var $treeBtn = $('#viewToggleTree');
        var $listCt = $('#list-view-container');
        var $treeCt = $('#tree-view-container');

        function applyView(view) {
            if (view === 'tree') {
                $listCt.hide();
                $treeCt.show();
                $treeBtn.addClass('active');
                $listBtn.removeClass('active');
            } else {
                $treeCt.hide();
                $listCt.show();
                $listBtn.addClass('active');
                $treeBtn.removeClass('active');
            }
            try { localStorage.setItem(STORAGE_KEY, view); } catch (e) { }
        }

        var saved = 'list';
        try { saved = localStorage.getItem(STORAGE_KEY) || 'list'; } catch (e) { }
        applyView(saved);

        $listBtn.on('click', function () { applyView('list'); });
        $treeBtn.on('click', function () { applyView('tree'); });

        // Tree expand/collapse
        $(document).on('click', '#tree-view-container .tree-toggle', function (e) {
            e.preventDefault();
            var $li = $(this).closest('li.tree-node');
            var $children = $li.children('ul.tree-children');
            if (!$children.length) return;
            if ($li.hasClass('expanded')) {
                $children.slideUp(150);
                $li.removeClass('expanded');
            } else {
                $children.slideDown(150);
                $li.addClass('expanded');
            }
        });
    })();

    // Keep child-row cell visibility in sync with column toggling.
    $('#category_list').on('column-switch.bs.table', function (_, field, checked) {
        var $tbl = $(this);
        var cols = $tbl.bootstrapTable('getOptions').columns[0];
        var idx = -1;
        $.each(cols, function (i, c) { if (c.field === field) { idx = i; return false; } });
        if (idx === -1) return;
        $tbl.find('tbody tr.child-row').each(function () {
            var $td = $(this).children('td').eq(idx);
            if (checked) $td.show(); else $td.hide();
        });
    });
</script>