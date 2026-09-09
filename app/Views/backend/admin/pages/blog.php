<?php
// $user1 = fetch_details('users', ["phone" => $_SESSION['identity']],);
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
        <!-- blog header -->
        <div class="section-header mt-2">
            <h1><?= labels('blog', "Blog") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('blog', 'Blog') ?></a></div>
            </div>
        </div>

        <!-- blog list -->
        <div class="container-fluid card">
            <!-- Removed language selector tabs - simplified to single language display -->
            <div class="row mt-4 mb-3">
                <!-- search button -->
                <div class="col-md-4 col-sm-2 mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" id="blog_search_button">
                                <i class="fa fa-search d-inline"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- filter button -->
                <button class="btn btn-secondary  ml-2 filter_button" id="filterButton">
                    <span class="material-symbols-outlined mt-1">
                        filter_alt
                    </span>
                </button>

                <!-- download button -->
                <div class="dropdown d-inline ml-2">
                    <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <?= labels('download', 'Download') ?>
                    </button>
                    <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                        <a class="dropdown-item" onclick="custome_export('pdf','blog list','blog_list');"><?= labels('pdf', 'PDF') ?></a>
                        <a class="dropdown-item" onclick="custome_export('excel','blog list','blog_list');"><?= labels('excel', 'Excel') ?></a>
                        <a class="dropdown-item" onclick="custome_export('csv','blog list','blog_list')"><?= labels('csv', 'CSV') ?></a>
                    </div>
                </div>

                <!-- add blog button -->
                <div class="col col d-flex justify-content-end">
                    <?php if ($permissions['create']['blog'] == 1) { ?>
                        <div class="text-center">
                            <a href="<?= base_url("admin/blog/add-blog"); ?>" class="btn btn-primary" style="height: 39px;font-size:14px">
                                <i class="fa fa-plus-circle mr-1 mt-2"></i><?= labels('add_blog', 'Add Blog') ?>
                            </a>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php if ($permissions['read']['blog'] == 1) { ?>
                <div class="row ">
                    <!-- blog list table -->
                    <div class="col-md-12">
                        <table class="table " data-fixed-columns="true" id="blog_list" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/blog/list") ?>" data-detail-formatter="blog_formater" data-query-params="blog_query_params" data-side-pagination="server" data-pagination="true" data-pagination-successively-size="1" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns-search="true" data-sort-name="id" data-sort-order="desc">
                            <thead>
                                <tr>
                                    <th data-field="id" class="text-left" data-sortable="true" data-visible="false"><?= labels('id', 'ID') ?></th>
                                    <th data-field="image" class="text-center" data-formatter="blogImageFormatter"><?= labels('image ', 'Image') ?></th>
                                    <th data-field="translated_title" class="text-left"><?= labels('title', 'Title') ?></th>
                                    <th data-field="translated_tags" class="text-left" data-visible="true"><?= labels('tags ', 'Tags') ?></th>
                                    <th data-field="translated_short_description" class="text-left" data-visible="true"><?= labels('short_description', 'Short Description') ?></th>
                                    <th data-field="translated_description" class="text-left" data-visible="true" data-formatter="descriptionFormatter"><?= labels('description', 'Description') ?></th>
                                    <th data-field="category_id" class="text-left" data-sortable="true" data-visible="false"><?= labels('category_id', 'Category ID') ?></th>
                                    <th data-field="translated_category_name" class="text-left" data-sortable="true" data-visible="true"><?= labels('category_name', 'Category Name') ?></th>
                                    <th data-field="created_at" class="text-left" data-sortable="true" data-visible="true"><?= labels('created_at', 'Created At') ?></th>

                                    <th data-field="operations" class="text-center" data-events="action_events"><?= labels('operations', 'Operations') ?></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>

    <!-- blog filter -->
    <div id="filterBackdrop"></div>
    <div class="drawer" id="filterDrawer">
        <section class="section">
            <div class="row">
                <div class="col-md-12">
                    <!-- blog filter header -->
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
                    <!-- blog filter body -->
                    <div class="row mt-4 mx-2">
                        <div class="col-md-12 mb-2">
                            <div class="form-group ">
                                <label for="category_item" class=""><?= labels('choose_a_category', 'Choose a Category') ?></label>
                                <select id="blog_category_custom_filter" class="form-control select2" name="categories" style="margin-bottom: 20px;">
                                    <option value=""> <?= labels('select', 'Select') ?> <?= labels('category', 'Category') ?> </option>
                                    <?php
                                    // Get current language for translations
                                    $currentLanguage = get_current_language();

                                    // Load blog category model to get categories with translations
                                    $blogCategoryModel = new \App\Models\Blog_category_model();

                                    // Fetch categories with translations using the model method
                                    // This method handles fallback: current language -> default language -> main table
                                    $categories_name = $blogCategoryModel->getCategoriesWithTranslations($currentLanguage);

                                    // Display categories with translated names
                                    foreach ($categories_name as $category) :
                                        // Use translated_name which has proper fallback logic
                                        // Priority: current language -> default language -> main table name
                                        $displayName = !empty($category['translated_name']) ? $category['translated_name'] : ($category['name'] ?? '');
                                    ?>
                                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($displayName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
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
    const BASE_URL = "<?= base_url() ?>";

    $(document).ready(function() {
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        var dynamicColumns = fetchColumns('blog_list');
        setupColumnToggle('blog_list', dynamicColumns, 'columnToggleContainer');
    });

    $('#apply_filter').on('click', function() {
        $('#blog_list').bootstrapTable('refresh');
    });

    // Function to format blog details with image preview
    function blog_formater(index, row) {
        var html = [];

        // Add image preview section
        if (row.image && row.image !== '') {
            var imgSrc = BASE_URL + '/public/uploads/blogs/images/' + row.image;
            html.push('<div class="text-center mb-3">');
            html.push('<img src="' + imgSrc + '" alt="Blog Image" style="max-width: 300px; max-height: 200px; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">');
            html.push('</div>');
        }

        // Add other blog details
        $.each(row, function(key, value) {
            if (key !== "image" && key !== "operations" && key !== "seo_title" && key !== "seo_description" && key !== "seo_keywords" && key !== "seo_image" && key !== "seo_schema_markup" && key !== "seo_created_at" && key !== "seo_updated_at") {
                // Format the key name for better display
                var displayKey = key.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
                    return l.toUpperCase();
                });
                html.push("<p><b>" + displayKey + ":</b> " + value + "</p>");
            }
        });

        return html.join("");
    }

    window.action_events = {
        'click .delete-blog': function(e, value, row, index) {
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
                        BASE_URL + "/admin/blog/delete_blog", {
                            [csrfName]: csrfHash,
                            id: row.id,
                        },
                        function(data) {
                            csrfName = data.csrfName;
                            csrfHash = data.csrfHash;
                            if (data.error == false) {
                                showToastMessage(data.message, "success");
                                $('#blog_list').bootstrapTable('refresh');
                            }
                        }
                    );
                }
            });
        }
    };

    // Formatter to display blog image as an actual image in the table
    // Note: The image is now formatted with lightbox link in the backend model
    function blogImageFormatter(value, row) {
        // The value now contains the full HTML with lightbox link from the backend
        // Just return it as is since it's already formatted
        return value;
    }


    // Formatter for description field to handle long text with "view more" functionality
    function descriptionFormatter(value, row) {
        // If value is empty or null, return empty string
        if (!value) {
            return '';
        }

        // Set character limit for description display
        var charLimit = 100;

        // If description is shorter than limit, return as is
        if (value.length <= charLimit) {
            return '<div class="description-content">' + value + '</div>';
        }

        // Create unique ID for this row to handle multiple descriptions
        var uniqueId = 'desc_' + row.id;

        // Trim the description and add "view more" button
        var trimmedText = value.substring(0, charLimit) + '...';

        return '<div class="description-content" id="' + uniqueId + '">' +
            '<span class="description-text">' + trimmedText + '</span>' +
            '<br><button class="btn btn-link btn-sm p-0 mt-1 text-primary view-more-btn" ' +
            'onclick="toggleDescription(\'' + uniqueId + '\', \'' + btoa(unescape(encodeURIComponent(value))) + '\')">' +
            '<i class="fas fa-eye"></i> <?= labels("view_more", "View More") ?></button>' +
            '</div>';
    }

    // Function to toggle between truncated and full description
    function toggleDescription(elementId, fullTextEncoded) {
        var element = document.getElementById(elementId);
        var button = element.querySelector('.view-more-btn');
        var textSpan = element.querySelector('.description-text');

        // Decode the full text from base64
        var fullText = decodeURIComponent(escape(atob(fullTextEncoded)));

        // Check current state by button text
        if (button.innerHTML.includes('<?= labels("view_more", "View More") ?>')) {
            // Show full description
            textSpan.innerHTML = fullText;
            button.innerHTML = '<i class="fas fa-eye-slash"></i> <?= labels("view_less", "View Less") ?>';
        } else {
            // Show trimmed description
            var charLimit = 100;
            var trimmedText = fullText.substring(0, charLimit) + '...';
            textSpan.innerHTML = trimmedText;
            button.innerHTML = '<i class="fas fa-eye"></i> <?= labels("view_more", "View More") ?>';
        }
    }

    $('#blog_search_button').click(function() {
        $('#blog_list').bootstrapTable('refresh');
    });

    // Language selector functionality for blog table (similar to blog categories)
    // This section is no longer needed as language switching is removed.
    // The blog_query_params function now handles the language parameter.

    // Blog query params function to include language parameter
    function blog_query_params(params) {
        // Use system default language since language switching is removed
        const currentLanguage = '<?= get_current_language() ?>';

        // Add language parameter to query
        params.language_code = currentLanguage;

        // Add search parameter if search input has value
        const searchValue = $('#customSearch').val();
        if (searchValue && searchValue.trim() !== '') {
            params.search = searchValue.trim();
        }

        // Add category filter if selected
        // Use category_id to match what the model expects (Blog_model.php expects category_id parameter)
        const categoryFilter = $('#blog_category_custom_filter').val();
        if (categoryFilter && categoryFilter !== '') {
            params.category_id = categoryFilter;
        }

        return params;
    }
</script>