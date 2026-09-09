<?php
$db      = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$permissions = get_permission($user1[0]['id']);
?>
<div class="main-content">
    <section class="section">
        <!-- blog categories header -->
        <div class="section-header mt-2">
            <h1><?= labels('blog_categories', "Blog Categories") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"> <?= labels('blog_categories', 'Blog Categories') ?></a></div>
            </div>
        </div>
        <div class="row">
            <?php
            if ($permissions['create']['blog'] == 1) { ?>
                <div class="col-md-6 ">
                    <div class="card">
                        <?= helper('form'); ?>
                        <?= form_open('/admin/blog/category/add_category', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_Category', 'enctype' => "multipart/form-data", 'novalidate' => 'novalidate']); ?>


                        <div class="row m-0">
                            <div class="col mb-3 " style="border-bottom: solid 1px #e5e6e9;">
                                <div class="toggleButttonPostition"><?= labels('category', 'Category') ?></div>
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
                            foreach ($sorted_languages as $index => $language) {
                            ?>
                                <div class="mt-3" id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="row">
                                        <!-- name -->
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="category_name<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('name', 'Name') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <input id="category_name<?= $language['code'] ?>" class="form-control" type="text" name="name[<?= $language['code'] ?>]" placeholder="<?= labels('enter_name_of_category', 'Enter the name of the Category here') ?>" <?= $language['is_default'] ? 'required' : '' ?> <?= $language['is_default'] ? 'data-slug-source data-slug-target="#category_slug"' : '' ?>>
                                            </div>
                                        </div>


                                    </div>
                                </div>
                            <?php } ?>

                            <!-- slug field - non-translatable, stored in main table -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="category_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                        <input id="category_slug" class="form-control" type="text" name="slug" placeholder="<?= labels('enter_category_slug_here', 'Enter the slug of the Category here') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- submit button -->
                            <div class="row ">
                                <div class="col-md d-flex justify-content-end">
                                    <div>
                                        <button type="submit" class="btn bg-new-primary submit_btn"><?= labels('add_category', 'Add Category') ?></button>
                                        <?= form_close(); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php }
            ?>
            <?php
            if ($permissions['read']['blog'] == 1) { ?>
                <div class="col-md-6 ">
                    <div class="card ">

                        <!-- blog categories list header -->
                        <div class="row m-0">
                            <div class="col mb-3 " style="border-bottom: solid 1px #e5e6e9;">
                                <div class="toggleButttonPostition"><?= labels('category_list', 'Category List') ?></div>
                            </div>
                        </div>
                        <div class="row pb-3 pl-3">
                            <div class="col-12">
                                <!-- search and filter button -->
                                <div class="row mb-3 mt-3">
                                    <!-- search button -->
                                    <div class="col-md-4 col-sm-2 mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" type="button" id="customSearchBtn">
                                                    <i class="fa fa-search d-inline"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- download button -->
                                    <div class="dropdown d-inline ml-2">
                                        <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <?= labels('download', 'Download') ?>
                                        </button>
                                        <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                            <a class="dropdown-item" onclick="custome_export('pdf','Category list','category_list');"> <?= labels('pdf', 'PDF') ?></a>
                                            <a class="dropdown-item" onclick="custome_export('excel','Category list','category_list');"> <?= labels('excel', 'Excel') ?></a>
                                            <a class="dropdown-item" onclick="custome_export('csv','Category list','category_list')"> <?= labels('csv', 'CSV') ?></a>
                                        </div>
                                    </div>
                                </div>

                                <!-- blog categories list table -->
                                <table class="table " data-fixed-columns="true" id="category_list" data-pagination-successively-size="2"
                                    data-detail-formatter="category_formater" data-query-params="category_query_params" data-auto-refresh="true"
                                    data-toggle="table" data-url="<?= base_url("admin/blog/categories/list") ?>" data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true"
                                    data-show-refresh="false" data-sort-name="id" data-sort-order="desc">
                                    <thead>
                                        <tr>
                                            <th data-field="id" data-visible="true" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                            <th data-field="translated_name" class="text-center"><?= labels('name', 'Name') ?></th>
                                            <th data-field="slug" class="text-center"><?= labels('slug', 'Slug') ?></th>

                                            <th data-field="created_at" data-visible="false" class="text-center" data-sortable="true"><?= labels('created_at', 'Created At') ?></th>
                                            <th data-field="operations" class="text-center" data-events="action_events"><?= labels('operations', 'Operations') ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>

    <!-- update modal -->
    <div class="modal fade" id="update_modal" tabindex="-1" aria-labelledby="update_modal_thing" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <!-- update modal header -->
                <div class="modal-header m-0 p-0">
                    <div class="row pl-3 w-100">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('update_category', 'Update Category') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end  mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <?= form_open('admin/blog/category/update_category', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_Category', 'enctype' => "multipart/form-data", 'novalidate' => 'novalidate']); ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                // Sort languages so default language appears first for better UI (same as main form)
                                $sorted_languages = sort_languages_with_default_first($languages);
                                foreach ($sorted_languages as $index => $language) {
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
                    <?php
                    foreach ($sorted_languages as $index => $language) {
                    ?>
                        <div id="translationModalDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_modal_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <!-- name -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('name', 'Name') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <input id="edit_name<?= $language['code'] ?>" class="form-control" type="text" name="name[<?= $language['code'] ?>]" placeholder="Enter the name of the Category here" <?= $language['is_default'] ? 'required' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <!-- slug field - non-translatable, stored in main table -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_category_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                <input id="edit_category_slug" class="form-control" type="text" name="slug" placeholder="<?= labels('enter_category_slug_here', 'Enter the slug of the Category here') ?>">
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="id" id="id">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn bg-new-primary submit_btn"><?= labels('update_category', 'Update Category') ?></button>
                    <?php form_close() ?>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', "Close") ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $("#customSearchBtn").on('click', function() {
        $('#category_list').bootstrapTable('refresh');
    });
</script>
<script>
    window.action_events = {
        'click .edit-category': function(e, value, row, index) {
            // Get category data with translations
            $.post(
                baseUrl + "/admin/blog/get_blog_category_data", {
                    [csrfName]: csrfHash,
                    id: row.id,
                },
                function(data) {
                    csrfName = data.csrfName;
                    csrfHash = data.csrfHash;
                    if (data.error == false) {
                        // Populate the form with category data
                        $('#edit_category_slug').val(data.data.slug);
                        $('#id').val(data.data.id);

                        // Populate translation fields
                        if (data.data.translations) {
                            Object.keys(data.data.translations).forEach(function(languageCode) {
                                const nameValue = data.data.translations[languageCode];
                                $(`#edit_name${languageCode}`).val(nameValue);
                            });
                        }

                        // Show the modal
                        $('#update_modal').modal('show');
                    } else {
                        console.log(data.message, "error");
                    }
                }
            );
        },

        'click .delete-blog-category': function(e, value, row, index) {
            Swal.fire({
                title: '<?= labels('are_your_sure', 'Are you sure?') ?>',
                text: "<?= labels('you_wont_be_able_to_revert_this', "You won't be able to revert this!") ?>",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!') ?>',
                cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post(
                        baseUrl + "/admin/blog/category/remove_category", {
                            [csrfName]: csrfHash,
                            id: row.id,
                        },
                        function(data) {
                            csrfName = data.csrfName;
                            csrfHash = data.csrfHash;
                            if (data.error == false) {
                                showToastMessage(data.message, "success");
                                $('#category_list').bootstrapTable('refresh');
                                $('#update_modal').modal('hide');
                            } else {
                                // Show error message when deletion fails
                                showToastMessage(data.message, "error");
                            }
                        }
                    );
                }
            });
        }
    };
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

            // Update the hidden field with current language
            $('#current_language_for_table').val(language);

            // Refresh the categories table to show names in the new language
            $('#category_list').bootstrapTable('refresh');
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