<?php
$user1 = fetch_details('users', ["phone" => $_SESSION['identity']],);
$permissions = get_permission($user1[0]['id']);
?>
<style>
    .drag-handle {
        cursor: grab;
    }

    .sortable-ghost {
        opacity: 0.5;
    }

    /* While dragging */
    .sortable-ghost {
        opacity: 0.4 !important;
        background: rgba(var(--secondary-color), 0.05) !important;
    }

    .sortable-chosen {
        background: rgba(var(--secondary-color), 0.05) !important;
        box-shadow: 0 8px 20px rgba(var(--secondary-color), 0.15);
    }

    .drag-handle:active {
        cursor: grabbing;
    }
</style>

<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('featured_section', "Featured Section") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('featured_section', "Featured Section") ?></a></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-5">
                <div class=" card">
                    <?= form_open('/admin/featured_sections/add_featured_section', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add', 'enctype' => "multipart/form-data"]); ?>
                    <div class="row pl-3 m-0 border_bottom_for_cards">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('add_featured_section', 'Add Featured Section') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end mr-3 mt-4">
                            <input type="checkbox" id="status" name="status" class="status-switch" checked>
                        </div>
                    </div>
                    <?= helper('form'); ?>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    $current_language = '';
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == "1" || $language['is_default'] == 1) {
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
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="service" class="required"><?= labels('section_type', "Section Type") ?></label>
                                    <select id="section_type" class="form-control select2" name="section_type">
                                        <option value=" "><?= labels('section_type', "Section Type") ?></option>
                                        <option value="categories"><?= labels('categories', "Categories") ?></option>
                                        <option value="partners"><?= labels('custom_provider', "Custom Provider ") ?> </option>
                                        <option value="top_rated_partner"><?= labels('top_rated_provider ', "Top Rated Provider") ?> </option>
                                        <option value="previous_order"><?= labels('previous_order ', "Previous Booking") ?> </option>
                                        <option value="ongoing_order"><?= labels('ongoing_order ', "Ongoing Booking") ?> </option>
                                        <option value="near_by_provider"><?= labels('near_by_providers ', "Near By Providers") ?> </option>
                                        <option value="banner"><?= labels('banner ', "Banner") ?> </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <!-- <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="name" class="required"><?= labels('description', "Title") ?></label>
                                        <textarea name="description" required class="form-control" id="description" placeholder="<?= labels('enter', "Enter") ?> <?= labels('description', "Description") ?>"></textarea>
                                    </div>
                                </div>
                            </div> -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group Category_item d-none">
                                    <label for="feature_category_item" class="required"><?= labels('categories', "Categories") ?></label> <br>
                                    <select id="feature_category_item" class="" name="category_item[]" multiple>
                                        <?php foreach ($categories_name as $Category) : ?>
                                            <option value="<?= $Category['id'] ?>"><?= $Category['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group top_rated_providers d-none">
                                    <label for="top_rated_provider" class="required"><?= labels('no_of_top_rated_provider_to_show', "No. of Top Rated Provider to show") ?></label> <br>
                                    <div class="form-group">
                                        <input id="limit" class="form-control" type="number" name="limit" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter_no_of_provider_to_show', 'Enter number of providers you want to show') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group near_by_providers d-none">
                                    <label for="near_by_providers" class="required"><?= labels('no_of_near_by_provider_to_show', "No. of Near By Providers to show") ?></label> <br>
                                    <div class="form-group">
                                        <input id="limit_for_near_by_providers" class="form-control" type="number" name="limit_for_near_by_providers" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter_no_of_nearby_provider_to_show', 'Enter number of near by providers you want to show') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group previous_order d-none">
                                    <label for="previoud_order_limit" class="required"><?= labels('no_of_previous_booking', "No. of Previous Bookings") ?></label> <br>
                                    <div class="form-group">
                                        <input id="previoud_order_limit" class="form-control" type="number" name="previous_order_limit" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter_no_of_previous_bookings_to_show', 'Enter number of previous bookings you want to show') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group ongoing_order d-none">
                                    <label for="ongoing_order_limit" class="required"><?= labels('no_of_ongoing_booking', "No. of ongoing bookings") ?></label> <br>
                                    <div class="form-group">
                                        <input id="ongoing_order_limit" class="form-control" type="number" name="ongoing_order_limit" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter_no_of_ongoing_booking_to_show', 'Enter number of ongoing bookings you want to show') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group partners_ids d-none">
                                    <label for="partners_ids" class="required"><?= labels('custom_provider', "Custom Provider ") ?> </label> <br>
                                    <select id="partners_ids" class="form-control" name="partners_ids[]" multiple>
                                        <?php foreach ($partners as $partner) : ?>
                                            <option value="<?= $partner['partner_id'] ?>"><?= $partner['display_company_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row banner_section d-none ">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="banner_type" class="required"><?= labels('banner_type', 'Banner Type') ?> <small> <?= labels('banner_type', 'Banner Type') ?></small> </label>
                                    <select id="banner_type" class="form-control select2" name="banner_type">
                                        <option value=""><?= labels('select_banner_type', 'Select Banner Type') ?> </option>
                                        <option value="banner_default"><?= labels('default', 'Default') ?> </option>
                                        <option value="banner_category"><?= labels('category', 'Category') ?> </option>
                                        <option value="banner_provider"><?= labels('provider', 'Provider') ?> </option>
                                        <option value="banner_url"><?= labels('url', 'URL') ?> </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 categories" id="banner_categories_select">
                                <label class="required" for="Category_item"><?= labels('category', 'Category') ?></label>
                                <select id="Category_item" class="form-control select2" name="banner_category_item">
                                    <option value=""><?= labels('select', 'Select') ?> <?= labels('category', 'Category') ?></option>
                                    <?php foreach ($categories_name as $Category) : ?>
                                        <option value="<?= $Category['id'] ?>"><?= $Category['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12 services" id="banner_providers_select">
                                <label for="service_item" class="required"><?= labels('provider', 'Provider') ?></label>
                                <select id="service_item" class="form-control select2" name="banner_providers">
                                    <option value=""><?= labels('select', 'Select') ?> <?= labels('provider', 'Provider') ?></option>
                                    <?php foreach ($provider_title as $provider) : ?>
                                        <option value="<?= $provider['partner_id'] ?>"><?= $provider['company_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12" id="banner_url_section">
                                <label for="url" class="required"><?= labels('url', 'URL') ?></label>
                                <input type="url" class="form-control" id="slider_url" name="url">
                            </div>
                        </div>
                        <div class="row mt-2 banner_section d-none">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="" class="required"><?= labels('app_image', 'App Image') ?></label>
                                    <small>(<?= labels('app_size_recommend', 'We recommend to use 345 X 105 pixels') ?>)</small>

                                    <input type="file" name="app_image" class="filepond-image-only" id="file_app_image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/svg+xml">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="web_image" class="required"><?= labels('web_image', 'Web Image') ?></label>
                                    <small>(<?= labels('web_size_recommend', 'We recommend to use 1320 x 400 pixels') ?>)</small>

                                    <input type="file" name="web_image" class="filepond-image-only" id="file_web_image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/svg+xml">
                                </div>
                            </div>
                        </div>

                        <?php
                        foreach ($languages as $index => $language) {
                        ?>
                            <div id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="row title ">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="title<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <input id="title<?= $language['code'] ?>" class="form-control" type="text" name="title[<?= $language['code'] ?>]" placeholder="<?= labels('enter', "Enter") ?> <?= labels('title', "Title") ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="description<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('description', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>

                                            <textarea name="description[<?= $language['code'] ?>]" class="form-control" id="description<?= $language['code'] ?>" placeholder="<?= labels('enter', "Enter") ?> <?= labels('description', "Description") ?>"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="row mt-2">
                            <div class="col-md d-flex justify-content-end">
                                <button type="submit" class="btn bg-new-primary submit_btn" id="add_slider"><?= labels('add_featured_section', 'Add Featured Section') ?></button>
                            </div>
                            <?= form_close() ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class=" card">
                    <div class="row pl-3 m-0 border_bottom_for_cards">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('all_featured', 'All Featured') ?></div>
                        </div>
                    </div>
                    <div class="row ml-4 mt-3">
                        <div class='btn bg-emerald-blue tag text-emerald-blue mr-2 ml-3 mb-2 filters_table cursor-pointer' id="feature_section_filter" name="feature_section_filter" value=""><?= labels('all ', "All") ?> </div>
                        <div class='btn bg-emerald-success tag text-emerald-success mr-2 filters_table cursor-pointer' id="feature_section_filter_active" name="feature_section_filter_active" value="feature_section_filter"><?= labels('active ', "Active") ?> </div>
                        <div class='btn bg-emerald-danger tag text-emerald-danger mr-2 filters_table cursor-pointer' id="feature_section_filter_deactive" name="feature_section_filter_deactive" value="feature_section_filter"><?= labels('deactive ', "Deactive") ?></div>
                        <div class="mb-2">
                            <div class="input-group">
                                <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="button" id="customSearchBtn">
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
                            <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?= labels('download ', "Download") ?>
                            </button>
                            <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                <a class="dropdown-item" onclick="custome_export('pdf','Feature Section list','user_list');"><?= labels('pdf ', "PDF") ?></a>
                                <a class="dropdown-item" onclick="custome_export('excel','Feature Section list','user_list');"><?= labels('excel ', "Excel") ?></a>
                                <a class="dropdown-item" onclick="custome_export('csv','Feature Section list','user_list')"><?= labels('csv ', "CSV") ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg">
                            <div class="card-body">
                                <div class="row ">
                                    <div class="col-12">
                                        <div class="col-md">
                                            <table class="table " data-fixed-columns="true" data-pagination-successively-size="2" id="user_list" data-unique-id="id" data-detail-formatter="user_formater" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/featured_sections/list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="rank" data-sort-order="asc" data-use-row-attr-func="true" data-reorderable-rows="false" data-query-params="feature_section_query_params">
                                                <thead>
                                                    <tr>
                                                        <th data-field="id" class="text-center " data-sortable="true"><?= labels('id', 'ID') ?></th>
                                                        <th data-field="title" class="text-center" data-sortable="false"><?= labels('title', 'Title') ?></th>
                                                        <th data-field="description" class="text-center" data-sortable="false"><?= labels('description', 'Description') ?></th>

                                                        <th data-field="category_ids" class="text-center" data-visible="false" data-sortable="true"><?= labels('category_id', 'Category id') ?></th>
                                                        <th data-field="section_type_badge" class="text-center" data-sortable="true"><?= labels('section_type', 'Section Type') ?></th>
                                                        <th data-field="partners_ids" class="text-center" data-visible="false" data-sortable="true"><?= labels('provider_id', 'Provider Id') ?></th>
                                                        <th data-field="created_at" class="text-center" data-visible="false" data-sortable="true"><?= labels('created_at', 'Created At') ?></th>
                                                        <th data-field="status_badge" class="text-center"><?= labels('status', 'Status') ?></th>
                                                        <th data-field="icon" class="text-center"><?= labels('reorder', 'Reorder') ?></th>
                                                        <th data-field="rank" class="text-center" data-sortable="true"><?= labels('rank', 'Rank') ?></th>
                                                        <th data-field="app_banner_image" class="text-center" data-visible="false"><?= labels('app_banner_image', 'App Banner Image') ?></th>
                                                        <th data-field="web_banner_image" class="text-center" data-visible="false"><?= labels('web_banner_image', 'Web Banner Image') ?></th>
                                                        <th data-field="banner_type_badge" class="text-center"><?= labels('banner_type', 'Banner Type') ?></th>
                                                        <th data-field="banner_url" data-visible="false" class="text-center"><?= labels('banner_url', 'Banner URL') ?></th>
                                                        <th data-field="operations" class="text-center" data-events="featured_section_events"><?= labels('operations', 'Operations') ?></th>
                                                    </tr>
                                                </thead>
                                            </table>
                                            <div class="col-md d-flex justify-content-end">
                                                <button id="button" class="btn btn-primary"><?= labels('update_rank', 'Update Rank') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- update modal -->
    <div class="modal fade" id="update_modal" tabindex="-1" aria-labelledby="update_modal_thing" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <?= form_open('admin/featured_sections/update_featured_section', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'edit_feature', 'enctype' => "multipart/form-data"]); ?>
                <div class="modal-header m-0 p-0" style="border-bottom: solid 1px #e5e6e9;">
                    <div class="row pl-3">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('update_feature_section', 'Update Featured Section') ?></div>
                        </div>
                    </div>
                    <div class="col d-flex justify-content-end mr-3 mt-4">
                        <input type="checkbox" id="edit_status" name="edit_status" class="status-switch editInModel">
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="id">
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
                                        <span class="language-modal-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-modal-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="service " class="required"><?= labels('section_type', "Section Type") ?></label>
                                <select id="edit_section_type" class="form-control" name="section_type">
                                    <option value=" "><?= labels('section_type', "Section Type") ?></option>
                                    <option value="categories"><?= labels('categories', "Categories") ?></option>
                                    <option value="partners"><?= labels('custom_provider', "Custom Provider ") ?></option>
                                    <option value="top_rated_partner"><?= labels('top_rated_provider ', "Top Rated Provider") ?> </option>
                                    <option value="previous_order"><?= labels('previous_order ', "Previous Booking") ?> </option>
                                    <option value="ongoing_order"><?= labels('ongoing_order ', "Ongoing Booking") ?> </option>
                                    <option value="near_by_provider"><?= labels('near_by_provider ', "Near By Provider") ?> </option>
                                    <option value="banner"><?= labels('banner ', "Banner") ?> </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php
                    foreach ($languages as $index => $language) {
                    ?>
                        <div id="translationModalDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_modal_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <div class="col-md-12 edit_title">
                                    <div class="form-group">
                                        <label for="edit_title<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('title', "Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <input id="edit_title<?= $language['code'] ?>" class="form-control" type="text" name="title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="edit_description<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('description', "Description") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <textarea name="description[<?= $language['code'] ?>]" class="form-control" id="edit_description<?= $language['code'] ?>" placeholder="<?= labels('enter', "Enter") ?> <?= labels('description', "Description") ?>"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group edit_category_item d-none">
                                <label for="name" class="required"><?= labels('categories', "Categories") ?></label> <br>
                                <select id="edit_Category_item" class="form-control" name="edit_Category_item[]" multiple>
                                    <?php foreach ($categories_name as $Category) : ?>
                                        <option value="<?= $Category['id'] ?>"><?= $Category['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group edit_partners_ids d-none">
                                <label for="name" class="required"><?= labels('provider', "Provider") ?></label>
                                <select id="edit_partners_ids" class="form-control" name="edit_partners_ids[]" multiple>
                                    <?php foreach ($partners as $partner) : ?>
                                        <option value="<?= $partner['partner_id'] ?>"><?= $partner['company_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group edit_previous_order d-none">
                                <label for="previoud_order_limit" class="required"><?= labels('no_of_previous_order_to_show', "No. of Previous Order") ?></label> <br>
                                <div class="form-group">
                                    <input id="edit_previoud_order_limit" class="form-control" type="number" name="previous_order_limit" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter_no_of_previous_bookings_to_show', 'Enter number of previous bookings you want to show') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group edit_near_by_providers d-none">
                                <label for="edit_limit_for_near_by_providers" class="required"><?= labels('no_of_near_by_provider_to_show', "No. of Near By Provider to show") ?></label> <br>
                                <div class="form-group">
                                    <input id="edit_limit_for_near_by_providers" class="form-control" type="number" name="limit_for_near_by_providers" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter_no_of_nearby_provider_to_show', 'Enter number of near by providers you want to show') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group edit_ongoing_order d-none">
                                <label for="ongoing_order_limit" class="required"> <?= labels('no_of_ongoing_order_to_show', "No. of Ongoing Order") ?></label> <br>
                                <div class="form-group">
                                    <input id="edit_ongoing_order_limit" class="form-control" type="number" name="ongoing_order_limit" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter_no_of_ongoing_booking_to_show', 'Enter number of ongoing bookings you want to show') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group edit_top_rated_providers d-none">
                                <label for="top_rated_provider" class="required"><?= labels('no_of_top_rated_provider_to_show', "No. of Top Rated Provider to show") ?></label> <br>
                                <div class="form-group">
                                    <input id="edit_top_rated_providers" class="form-control" type="number" name="limit" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter_no_of_provider_to_show', 'Enter number of providers you want to show') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row edit_banner_section d-none ">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="edit_banner_type" class="required"><?= labels('banner_type', 'Banner Type') ?> <small> <?= labels('banner_type', 'Banner Type') ?></small> </label>
                                <select id="edit_banner_type" class="form-control select2" name="banner_type">
                                    <option value=""><?= labels('select_banner_type', 'Select Banner Type') ?> </option>
                                    <option value="banner_default"><?= labels('default', 'Default') ?> </option>
                                    <option value="banner_category"><?= labels('category', 'Category') ?> </option>
                                    <option value="banner_provider"><?= labels('provider', 'Provider') ?> </option>
                                    <option value="banner_url"><?= labels('url', 'URL') ?> </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 edit_banner_section" id="edit_banner_categories_select">
                            <label class="required" for="Category_item"><?= labels('category', 'Category') ?></label>
                            <select id="edit_category_item" class="form-control select2" name="banner_category_item">
                                <option value=""><?= labels('select', 'Select') ?> <?= labels('category', 'Category') ?></option>
                                <?php foreach ($categories_name as $Category) : ?>
                                    <option value="<?= $Category['id'] ?>"><?= $Category['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 edit_banner_section" id="edit_banner_providers_select">
                            <label for="edit_banner_providers_select" class="required"><?= labels('provider', 'Provider') ?></label>
                            <select id="edit_banner_providers" class="form-control select2" name="banner_providers">
                                <option value=""><?= labels('select', 'Select') ?> <?= labels('provider', 'Provider') ?></option>
                                <?php foreach ($provider_title as $provider) : ?>
                                    <option value="<?= $provider['partner_id'] ?>"><?= $provider['company_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 edit_banner_section" id="edit_banner_url_section">
                            <label for="edit_banner_url" class="required"><?= labels('url', 'URL') ?></label>
                            <input type="url" class="form-control" id="edit_banner_url" name="url">
                        </div>
                    </div>
                    <div class="row mt-2 edit_banner_section d-none">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="" class="required"><?= labels('app_image', 'App Image') ?></label>
                                <small>(<?= labels('app_size_recommend', 'We recommend to use 345 X 163') ?>)</small>
                                <input type="file" name="app_image" class="filepond-image-only" id="file_edit_app_image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/svg+xml">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <img src="" style="border-radius: 8px;height: 100px;width: 100px!important;" alt="preview_app_image" id="preview_app_image" class="w-50">

                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="web_image" class="required"><?= labels('web_image', 'Web Image') ?></label>
                                <small>(<?= labels('web_size_recommend', 'We recommend to use 1320 x 400 pixels') ?>)</small>
                                <input type="file" name="web_image" class="filepond-image-only" id="file_edit_web_image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/svg+xml">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <img src="" style="border-radius: 8px;height: 100px;width: 100px!important;" alt="preview_banner_image" id="preview_banner_image" class="w-50">

                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" name="submit"><?= labels('update_section', "Update Section") ?></button>
                    <?= form_close() ?>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', "Close") ?></button>
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
                            <div class="bg-white m-3 text-new-primary" style="box-shadow: 0px 8px 26px #00b9f02e; display: inline-block; padding: 10px; height: 45px; width: 45px; border-radius: 15px;">
                                <span class="material-symbols-outlined">
                                    filter_alt
                                </span>
                            </div>
                            <h3 class="mb-0" style="display: inline-block; font-size: 16px; margin-left: 10px;"><?= labels('filters', 'Filters') ?></h3>
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
                                <button class="btn btn-primary d-block mt-3" id="apply_filter"><?= labels('apply', 'Apply') ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    $(function() {
        // Function to check row count and disable/hide update rank button
        function checkRowCountAndToggleButton() {
            var rowCount = $('#user_list').bootstrapTable('getData').length;
            var updateRankButton = $('#button');

            if (rowCount <= 1) {
                // Hide the button when there are 0 or 1 rows
                updateRankButton.hide();
            } else {
                // Show and enable the button when there are 2 or more rows
                updateRankButton.show();
                updateRankButton.prop('disabled', false);
            }
        }

        // Check on table load
        $('#user_list').on('load-success.bs.table', function() {
            checkRowCountAndToggleButton();
        });

        // Check on table refresh
        $('#user_list').on('refresh.bs.table', function() {
            setTimeout(function() {
                checkRowCountAndToggleButton();
            }, 100);
        });

        // Initial check after table is initialized
        setTimeout(function() {
            checkRowCountAndToggleButton();
        }, 500);

        $('#button').click(function() {
            let ids = [];

            $('#user_list tbody tr').each(function() {
                const id = $(this).data('uniqueid');
                if (id) ids.push(id);
            });

            let data = new FormData();
            data.append('ids', JSON.stringify(ids));
            $.ajax({
                type: "POST",
                url: baseUrl + "/admin/featured-section/change-order",
                data: data,
                dataType: "json",
                beforeSend: function() {
                    $("#button").attr("disabled", true);
                    $("#button").removeClass("btn-primary");
                    $("#button").addClass("btn-secondary");
                    $("#button").html('<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>');
                },
                processData: false,
                contentType: false,
                success: function(result) {
                    csrfName = result.csrfName;
                    csrfHash = result.csrfHash;
                    if (result.error == false) {
                        iziToast.success({
                            title: "",
                            message: result.message,
                            position: "topRight",
                        })
                        $('.close').click();
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        iziToast.error({
                            title: "",
                            message: result.message,
                            position: "topRight",
                        })
                    }
                },
            });
        })
    })
</script>
<script>
    $(document).ready(function() {
        $('#status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');

        function handleSwitchChange(checkbox) {
            var switchery = checkbox.nextElementSibling;
            if (checkbox.checked) {
                switchery.classList.add('active-content');
                switchery.classList.remove('deactive-content');
            } else {
                switchery.classList.add('deactive-content');
                switchery.classList.remove('active-content');
            }
        }
        var status = document.querySelector('#status');
        if (status) {
            status.addEventListener('change', function() {
                handleSwitchChange(status);
            });
        }

        var edit_status = document.querySelector('#edit_status');
        if (edit_status) {
            edit_status.addEventListener('change', function() {
                handleSwitchChange(edit_status);
            });
        }
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        var columns = [{
                field: 'id',
                label: '<?= labels('id', 'ID') ?>',
                visible: false
            },
            {
                field: 'title',
                label: '<?= labels('title', 'Title') ?>'
            },
            {
                field: 'description',
                label: '<?= labels('description', 'Description') ?>'
            },
            {
                field: 'category_ids',
                label: '<?= labels('category_id', 'Category id') ?>',
                visible: false
            },
            {
                field: 'section_type_badge',
                label: '<?= labels('section_type', 'Section Type') ?>'
            },
            {
                field: 'partners_ids',
                label: '<?= labels('provider_id', 'Provider Id') ?>',
                visible: false
            },
            {
                field: 'created_at',
                label: '<?= labels('created_at', 'Created At') ?>',
                visible: false
            },
            {
                field: 'status_badge',
                label: '<?= labels('status', 'Status') ?>'
            },
            {
                field: 'icon',
                label: '<?= labels('reorder', 'Reorder') ?>'
            },
            {
                field: 'rank',
                label: '<?= labels('rank', 'Rank') ?>'
            },
            {
                field: 'app_banner_image',
                label: '<?= labels('app_banner_image', 'App Banner Image') ?>',
                visible: false
            },
            {
                field: 'web_banner_image',
                label: '<?= labels('web_banner_image', 'Web Banner Image') ?>',
                visible: false
            },
            {
                field: 'banner_type_badge',
                label: '<?= labels('banner_type', 'Banner Type') ?>',
            },
            {
                field: 'banner_url',
                label: '<?= labels('banner_url', 'Banner URL') ?>',
                visible: false
            },
            {
                field: 'operations',
                label: '<?= labels('operations', 'Operations') ?>',
            },
        ];
        setupColumnToggle('user_list', columns, 'columnToggleContainer');
        var container = $('#columnToggleContainer');
    });
    var feature_section_filter = "";
    $("#feature_section_filter").on("click", function() {
        feature_section_filter = "";
        $("#user_list").bootstrapTable("refresh");
    });
    $("#feature_section_filter_active").on("click", function() {
        feature_section_filter = "1";
        $("#user_list").bootstrapTable("refresh");
    });
    $("#feature_section_filter_deactive").on("click", function() {
        feature_section_filter = "0";
        $("#user_list").bootstrapTable("refresh");
    });
    // Search on Enter key press
    $("#customSearch").on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            $('#user_list').bootstrapTable('refresh');
        }
    });

    // Search on button click
    $("#customSearchBtn").on('click', function() {
        $('#user_list').bootstrapTable('refresh');
    });

    function feature_section_query_params(p) {
        return {
            search: $("#customSearch").val() ? $("#customSearch").val() : p.search,
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            feature_section_filter: feature_section_filter,
        };
    }
</script>
<script>
    // select default language
    $(document).ready(function() {
        let default_language = '<?= $current_language ?>';
        let current_modal_language = '<?= $current_modal_language ?>';

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

        $(document).on('click', '.language-modal-option', function() {
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
    });
</script>

<script>
    let sortable = null;
    let reorderEnabled = false;

    function initSortable() {
        const tbody = document.querySelector('#user_list tbody');

        if (!tbody || sortable) return;

        sortable = new Sortable(tbody, {
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

    // Init after bootstrap-table loads data
    $('#user_list').on('post-body.bs.table', function() {
        initSortable();
    });
</script>