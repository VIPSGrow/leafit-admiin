<style>
    .custom-nav-tabs {
        padding: 8px;
        border-radius: 12px;
        border: none !important;
        display: flex;
        gap: 8px;
        margin: 0.5rem;
    }

    /* View toggle */
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
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('categories', "Categories") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/partner/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"> <?= labels('category', 'Categories') ?></a></div>
            </div>
        </div>
        <div class="container-fluid card mt-2">
            <div class="row">
                <div class="col-md">
                    <div class="card-body p-0">

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

                        <!-- List view -->
                        <div id="list-view-container">
                            <div class="row pb-3 pl-3">
                                <div class="col-12">
                                    <div class=" mb-3 row mt-3">
                                        <div class="col-md-4 col-sm-2 mb-2">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="customSearch"
                                                    placeholder="<?= labels('search_here', 'Search here!') ?>"
                                                    aria-label="Search" aria-describedby="customSearchBtn">
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
                                            <button class="btn export_download dropdown-toggle" type="button"
                                                id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                                aria-expanded="false">
                                                <?= labels('download', 'Download') ?>
                                            </button>
                                            <div class="dropdown-menu" x-placement="bottom-start"
                                                style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                                <a class="dropdown-item"
                                                    onclick="custome_export('pdf','Category list','cash_collection');"><?= labels('pdf', 'PDF') ?>
                                                </a>
                                                <a class="dropdown-item"
                                                    onclick="custome_export('excel','Category list','cash_collection');"><?= labels('excel', 'Excel') ?>
                                                </a>
                                                <a class="dropdown-item"
                                                    onclick="custome_export('csv','Category list','cash_collection')"><?= labels('csv', 'CSV') ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-borderd" data-fixed-columns="true"
                                            id="cash_collection"
                                            data-query-params="partner_category_tree_query_params"
                                            data-row-attributes="partnerCategoryRowAttributes"
                                            data-pagination-successively-size="2" data-show-export="false"
                                            data-export-types="['txt','excel','csv']"
                                            data-export-options='{"fileName": "category-list","ignoreColumn": []}'
                                            data-auto-refresh="true" data-show-columns="false" data-search="false"
                                            data-show-refresh="false" data-toggle="table"
                                            data-page-list="[5, 10, 25, 50, 100, 200, All]" data-side-pagination="server"
                                            data-pagination="true" data-url="<?= base_url("partner/categories/list") ?>"
                                            data-sort-name="id" data-sort-order="desc">
                                            <thead>
                                                <tr>
                                                    <th data-field="id" class="text-center" data-sortable="true">
                                                        <?= labels('id', 'ID') ?></th>
                                                    <th data-field="category_image" class="text-center">
                                                        <?= labels('image', 'Image') ?></th>
                                                    <th data-field="name" class="text-center"
                                                        data-formatter="partnerCategoryNameFormatter">
                                                        <?= labels('name', 'Name') ?></th>
                                                    <th data-field="children_count" data-switchable="false"
                                                        class="text-center"
                                                        data-formatter="partnerCategoryChildrenCountFormatter">
                                                        <?= labels('subcategories_count', 'Subcategories Count') ?></th>
                                                    <th data-field="created_at" class="text-center" data-sortable="true">
                                                        <?= labels('created_at', 'Created At') ?></th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /#list-view-container -->

                        <!-- Tree view -->
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
            </div>
        </div>
    </section>
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
                            <?= labels('filters', 'Filters') ?></h3>
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
    $(document).ready(function () {
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        var columns = [{
            field: 'id',
            label: '<?= labels('id', 'ID') ?>',
        },
        {
            field: 'category_image',
            label: '<?= labels('image', 'Image') ?>'
        },
        {
            field: 'name',
            label: '<?= labels('name', 'Name') ?>'
        },
        {
            field: 'children_count',
            label: '<?= labels('subcategories_count', 'Subcategories Count') ?>'
        },
        {
            field: 'created_at',
            label: "<?= labels('created_at', 'Created At') ?>",
        }
        ];
        setupColumnToggle('cash_collection', columns, 'columnToggleContainer');
    });
</script>
<script>
    // Search button click handler
    $("#customSearchBtn").on('click', function () {
        $('#cash_collection').bootstrapTable('refresh');
    });

    // Allow Enter key to trigger search
    $("#customSearch").on('keypress', function (e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#customSearchBtn').click();
        }
    });

    // Root-only initial load; bypass parent_id filter when searching
    function partner_category_tree_query_params(p) {
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

    function partnerCategoryRowAttributes(row, index) {
        return {
            'data-id': row.id,
            'data-parent-id': row.parent_id || '0',
            'data-depth': row._depth || 0
        };
    }

    function partnerCategoryChildrenCountFormatter(value, row, index) {
        var n = parseInt(value || 0, 10);
        return '<span class="subcategory-count-sticker">' + n + '</span>';
    }

    function partnerCategoryNameFormatter(value, row, index) {
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

    function buildPartnerCategoryChildRow(row, depth, parentId) {
        row._depth = depth;
        var $tbl = $('#cash_collection');
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

    // Expand / collapse handler
    $(document).on('click', '#cash_collection .expand-btn', function (e) {
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
            if (expanded) {
                $('#cash_collection tbody tr.parent-' + parentId).hide()
                    .each(function () {
                        $(this).attr('data-expanded', 'false');
                        $(this).find('.expand-btn i').removeClass('fa-minus').addClass('fa-plus');
                    });
                $row.attr('data-expanded', 'false');
                $icon.removeClass('fa-minus').addClass('fa-plus');
            } else {
                $('#cash_collection tbody tr.parent-' + parentId).show();
                $row.attr('data-expanded', 'true');
                $icon.removeClass('fa-plus').addClass('fa-minus');
            }
            return;
        }

        // First expansion — fetch children via AJAX
        $btn.prop('disabled', true);
        $icon.removeClass('fa-plus').addClass('fa-spinner fa-spin');

        $.ajax({
            url: '<?= base_url('partner/categories/list') ?>',
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
            var $insertAfter = $row;
            $.each(rows, function (_, child) {
                if ($('#cash_collection tbody tr[data-id="' + child.id + '"][data-parent-id="' + parentId + '"]').length) {
                    return;
                }
                var $childRow = buildPartnerCategoryChildRow(child, depth + 1, parentId);
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

    // Keep child-row cell visibility in sync with column toggling
    $('#cash_collection').on('column-switch.bs.table', function (_, field, checked) {
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

    // View toggle (list / tree)
    (function () {
        var STORAGE_KEY = 'partner_categories_view';
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
</script>
