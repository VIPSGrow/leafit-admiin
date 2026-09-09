<?php $data = get_settings('general_settings', true);
// $user1 = fetch_details('users', ["phone" => $_SESSION['identity']],);
$db = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$disk = fetch_current_file_manager();
$user1[0]['image'] = get_file_url($disk, $user1[0]['image'], 'public/backend/assets/user_default_image.png', 'profile');
$permissions = get_permission($user1[0]['id']);
$permissionService = new \App\Services\utility\PermissionService();
$sidebarUserId = (int) $user1[0]['id'];
// Superadmin must always have full sidebar access, even if user_permissions row is missing.
if (!empty($_SESSION['email']) && $_SESSION['email'] === 'superadmin@gmail.com') {
    foreach ($permissions as $permissionType => $modulePermissions) {
        if (!is_array($modulePermissions)) {
            continue;
        }
        foreach ($modulePermissions as $module => $value) {
            $permissions[$permissionType][$module] = 1;
        }
    }
}
$current_url = current_url();
$version = $db->table('updates')->select('*')->orderBy('id', 'DESC')->get(1)->getResult();

// Count of distinct users (customers + providers) who have unread chats with admin.
// Drives the "Chat" sidebar badge so admins can see unread activity at a glance.
//
// Filters must mirror the chat page so the badge can never count senders that
// would not appear in the chat list:
//   - Sender must still exist in `users` (no orphan chats from deleted users).
//   - Sender must be in the matching group (customer=2, provider=3) — group
//     changes shouldn't keep counting old chats.
//   - Customers must have a usable phone (chat page skips rows without one).
$adminChatUnreadUsersCount = 0;
try {
    $unreadCustomerSenders = $db->table('chats c')
        ->select('c.sender_id')
        ->join('users u', 'u.id = c.sender_id')
        ->join('users_groups ug', 'ug.user_id = u.id AND ug.group_id = 2')
        ->where('c.sender_type', 2)
        ->where('c.receiver_type', 0)
        ->where('c.booking_id', null)
        ->where('c.is_read', 0)
        ->where("TRIM(COALESCE(u.phone, '')) NOT IN ('', 'null', 'undefined')", null, false)
        ->groupBy('c.sender_id')
        ->get()->getResultArray();
    $unreadProviderSenders = $db->table('chats c')
        ->select('c.sender_id')
        ->join('users u', 'u.id = c.sender_id')
        ->join('users_groups ug', 'ug.user_id = u.id AND ug.group_id = 3')
        ->where('c.sender_type', 1)
        ->where('c.receiver_type', 0)
        ->where('c.is_read', 0)
        ->groupBy('c.sender_id')
        ->get()->getResultArray();
    $adminChatUnreadUsersCount = count($unreadCustomerSenders) + count($unreadProviderSenders);
} catch (\Throwable $e) {
    $adminChatUnreadUsersCount = 0;
}
?>
<style>
    /* Push the unread badge to the far right of the sidebar link, while keeping
       it vertically centered with the link text. The anchor is flex so
       margin-left:auto pushes the badge to the end. */
    .sidebar-menu li a.nav-link {
        display: flex;
        align-items: center;
    }

    .chat-sidebar-unread-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 6px;
        border-radius: 9px;
        background: #dc3545;
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        line-height: 1;
        flex-shrink: 0;
    }

    /* Sidebar context: push badge to far right of the link. */
    .sidebar-menu li a.nav-link .chat-sidebar-unread-badge {
        margin-left: 100px;
    }

    .chat-sidebar-unread-badge.hidden {
        display: none;
    }
</style>
<script>
    /**
     * Sidebar Chat unread badge: refresh from the server.
     *
     * Why a server call:
     * - The badge represents the number of *distinct users* with unread chats,
     *   not the number of unread messages. A purely client-side increment would
     *   over-count when the same user sends many messages in a row.
     * - This helper hits a small endpoint that runs the same SQL used to render
     *   the badge on initial page load, so the value always stays canonical.
     */
    window.refreshAdminChatSidebarBadge = function () {
        var $badge = $('#admin-chat-sidebar-unread-badge');
        if (!$badge.length) return;
        $.ajax({
            url: (typeof baseUrl !== 'undefined' ? baseUrl : '') + '/admin/chat_unread_users_count',
            type: 'GET',
            dataType: 'json',
            success: function (resp) {
                if (!resp || resp.error) return;
                var count = (resp.data && typeof resp.data.count !== 'undefined')
                    ? parseInt(resp.data.count, 10)
                    : 0;
                if (isNaN(count) || count < 0) count = 0;
                $badge.text(count);
                if (count > 0) $badge.removeClass('hidden');
                else $badge.addClass('hidden');
            }
        });
    };
</script>
<link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
<?php
// Full-width demo CTA: show only when demo mode is on (ALLOW_MODIFICATION == 0).
// Banner is fixed at top; body.has-demo-cta pushes sidebar and navbar below it via CSS.
$show_demo_cta = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0;
if ($show_demo_cta): ?>
    <?php
    // Height used by CSS to offset sidebar/navbar/main-content. Keep in sync with .demo-cta-banner height.
    $demo_cta_height_px = 42;
    ?>
    <style>
        /* Demo CTA bar: first element at top, full width; sidebar and navbar start below it */
        .demo-cta-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            height:
                <?= $demo_cta_height_px ?>
                px;
            min-height:
                <?= $demo_cta_height_px ?>
                px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            box-sizing: border-box;
            overflow: hidden;
        }

        /* Full-background angled lines (white 50% opacity); more spacing between each line */
        .demo-cta-banner::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            bottom: 0;
            background: repeating-linear-gradient(-55deg,
                    transparent 0,
                    transparent 10px,
                    rgba(255, 255, 255, 0.1) 10px,
                    rgba(255, 255, 255, 0.1) 12px);
            pointer-events: none;
            z-index: 0;
        }

        .demo-cta-banner .demo-cta-banner-text,
        .demo-cta-banner .demo-cta-buy-btn {
            position: relative;
            z-index: 1;
        }

        body.has-demo-cta .main-sidebar {
            top:
                <?= $demo_cta_height_px ?>
                px !important;
            height: calc(100vh -
                    <?= $demo_cta_height_px ?>
                    px) !important;
        }

        body.has-demo-cta.sidebar-mini .main-sidebar:after {
            top:
                <?= $demo_cta_height_px ?>
                px !important;
        }

        body.has-demo-cta .navbar {
            top:
                <?= $demo_cta_height_px ?>
                px !important;
        }

        body.has-demo-cta .main-content {
            padding-top:
                <?= 80 + $demo_cta_height_px ?>
                px !important;
        }

        /* Mobile: allow banner to grow when content wraps; use a safe top offset so no overlap */
        @media (max-width: 1024px) {
            .demo-cta-banner {
                height: auto;
                min-height:
                    <?= $demo_cta_height_px ?>
                    px;
                padding: 10px 12px;
                flex-direction: column;
                gap: 8px;
                align-items: center;
                justify-content: center;
            }

            .demo-cta-banner .demo-cta-banner-text {
                font-size: 0.9rem;
                line-height: 1.3;
                text-align: center;
                margin: 0;
            }

            .demo-cta-banner .demo-cta-buy-btn {
                margin: 0;
                flex-shrink: 0;
            }

            body.has-demo-cta .main-sidebar {
                top: 92px !important;
                height: calc(100vh - 92px) !important;
            }

            body.has-demo-cta.sidebar-mini .main-sidebar:after {
                top: 92px !important;
            }

            body.has-demo-cta .navbar {
                top: 92px !important;
            }

            body.has-demo-cta .main-content {
                padding-top: 172px !important;
            }
        }

        /* Buy now button: white background, primary-color text */
        .demo-cta-banner .demo-cta-buy-btn {
            background-color: #ffffff !important;
            color: var(--primary-color) !important;
            padding-left: 0.6rem !important;
            padding-right: 0.6rem !important;
        }

        .demo-cta-banner .demo-cta-buy-btn:hover {
            background-color: #f0f0f0 !important;
            color: var(--primary-color) !important;
        }
    </style>
    <div class="demo-cta-banner w-100 text-center py-2 px-3" style="background-color: var(--primary-color); color: #eee;">
        <span class="demo-cta-banner-text mr-2">Demo mode is on. Your live business version is one click away.</span>
        <a href="<?= defined('DEMO_PURCHASE_URL') ? DEMO_PURCHASE_URL : '#' ?>" target="_blank" rel="noopener"
            class="btn btn-sm ml-1 demo-cta-buy-btn font-weight-bolder">Buy Now</a>
    </div>
<?php endif; ?>
<!-- <div class="navbar-bg"></div> -->
<nav class="navbar new_nav_bar navbar-expand-lg main-navbar">
    <form class="form-inline mr-auto">
        <?php
        $is_demo = (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) && ($_SESSION['email'] != "superadmin@gmail.com");
        ?>
        <ul class="navbar-nav mr-3 <?= $is_demo ? 'demo-mode-active' : '' ?>">
            <li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg"><i
                        class="fas fa-bars text-new-primary"></i></a></li>

            <li class="nav-item my-auto ml-2 mr-2">
                <span class="badge badge-primary" style="border-radius: 8px!important">
                    <?php foreach ($version as $ver): ?>
                        <?= $ver->version ?>
                    <?php endforeach; ?></span>
            </li>
            <?= view('backend/partials/demo_reset_timer') ?>

            <li><a href="#" data-toggle="search" class="nav-link nav-link-lg"><i class="fas fa-search"></i></a></li>
            <div class=" nav-item search-element">
                <input class="form-control" type="search" id="menu-search" oninput="filterMenuItems()"
                    onclick="showAllMenuItems()" placeholder="<?= labels('search', 'Search') ?>" aria-label="Search">
                <button class="btn " type="button">
                    <i class="fa fa-search d-inline text-dark"></i>
                </button>
                <div class="search-backdrop"></div>
                <div class="search-result">
                </div>
            </div>
        </ul>
    </form>
    <ul class="navbar-nav navbar-right">
        <?php
        // Fetch the default language
        $default_language = fetch_details('languages', ['is_default' => '1']);
        // print_R($default_language);
        // die;
        
        $default_language_id = (!empty($default_language)) ? $default_language[0]['id'] : null;
        ?>

        <!--
            Admin notifications dropdown (web panel)
            ------------------------------------------------
            Why:
            - Providers already have a bell dropdown + full notifications page.
            - Admin users need the same UX to view their own system-generated notifications
              (plus any all_users broadcasts).
            
            Endpoints:
            - GET  admin/notifications/recent        (latest 5 + unread_count)
            - POST admin/notifications/mark_all_read (mark all visible as read)
            - GET  admin/notifications               (view all page)
        -->
        <li class="dropdown navbar_dropdown mr-2 mt-2" id="admin-notification-dropdown">
            <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user"
                id="admin-notification-toggle" style="position: relative;">
                <span style="position: relative; display: inline-block; margin-right: 10px;">
                    <i class="fas fa-bell"></i>
                    <!--
                        Unread badge counter (Admin panel)
                        ---------------------------------
                        Previous behavior: always visible (even "0").
                        New requirement: hide the badge when unread count is empty/0.
                        Why:
                        - An empty/0 badge bubble looks like a UI bug.
                        - We keep the element in DOM so JS can show it when needed.
                    -->
                    <span class="badge badge-danger" id="admin-notification-count"
                        style="position:absolute; top:-15px; right:-30px; border-radius: 10px; font-size: 10px; z-index: 2; display:none;"></span>
                </span>
            </a>
            <div class="dropdown-menu dropdown-menu-right" style="min-width: 340px;">
                <div class="dropdown-header d-flex justify-content-between align-items-center">
                    <span><?= labels('notifications', 'Notifications') ?></span>
                    <a href="#" class="small"
                        id="admin-notification-mark-all"><?= labels('mark_all_as_read', 'Mark all as read') ?></a>
                </div>
                <div class="dropdown-divider"></div>
                <div id="admin-notification-items" style="max-height: 320px; overflow: auto;">
                    <div class="px-3 py-2 text-muted small"><?= labels('loading', 'Loading...') ?></div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="<?= base_url('admin/notifications') ?>"
                    class="dropdown-item text-center text-new-primary font-weight-bold">
                    <?= labels('view_all_notifications', 'View all notifications') ?>
                </a>
            </div>
        </li>


        <?php
        // Render language UI
        if (isset($languages_locale) && count($languages_locale) > 1) { ?>
            <li class="dropdown navbar_dropdown mr-2 mt-2" id="language-dropdown-container">
                <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                    <?php
                    $session = session();
                    $lang = $session->get('lang');

                    if (!empty($lang)) {
                        $default_language = $lang;
                    } else {
                        $default_language = $default_language[0]['code'];
                    }
                    ?>
                    <div class="d-inline-block" id="current-language-display"><?= strtoupper($default_language) ?>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right" id="language-dropdown-menu">
                    <?php foreach ($languages_locale as $language) { ?>
                        <?php
                        $is_default = ($language['id'] == $default_language_id);
                        ?>
                        <span onclick="set_locale('<?= $language['code'] ?>')"
                            class="dropdown-item has-icon <?= ($language['code'] == $default_language) ? 'text-primary' : '' ?>"
                            <?= ($is_default) ? 'selected' : '' ?>>
                            <?= strtoupper($language['code']) . " - " . ucwords($language['language']) ?>
                        </span>
                    <?php } ?>
                </div>
            </li>

        <?php } elseif (isset($languages_locale) && count($languages_locale) === 1) { ?>

            <li class="nav-item mt-2 ml-2 mr-3" id="single-language-container">
                <span class="badge badge-primary" style="border-radius: 8px!important;">
                    <p class="p-0 m-0"><?= strtoupper($languages_locale[0]['code']) ?></p>
                </span>
            </li>

        <?php } else { // Fallback when no available languages were detected (e.g., files missing)
            $fallbackCode = (!empty($default_language) && isset($default_language[0]['code'])) ? $default_language[0]['code'] : 'en'; ?>

            <li class="nav-item mt-2 ml-2 mr-3" id="single-language-container">
                <span class="badge badge-primary" style="border-radius: 8px!important;">
                    <p class="mb-0"><?= strtoupper($fallbackCode) ?></p>
                </span>
            </li>

        <?php } ?>



        <li class="dropdown navbar_dropdown mt-2">
            <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                <img src="<?= $user1[0]['image'] ?>" class="sidebar_logo h-max-60px navbar_image" alt="no image">
                <div class="d-inline-block"><?= labels('hello', 'Hi') ?> , <?= $user1[0]['username'] ?>
                </div>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <a href="<?= base_url('admin/profile') ?>" class="dropdown-item has-icon">
                    <i class="far fa-user"></i> <?= labels('profile', "Profile") ?>
                </a>
                <a href="<?= base_url('admin/profile/password') ?>" class="dropdown-item has-icon">
                    <i class="fas fa-key"></i> <?= labels('change_password', "Change Password") ?>
                </a>
                <a href="<?= base_url('auth/logout') ?>" class="dropdown-item has-icon text-danger">
                    <i class="fas fa-sign-out-alt"></i> <?= labels('logout', "Logout") ?>
                </a>
            </div>
        </li>
    </ul>
</nav>


<div class="main-sidebar">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="<?= base_url('admin/dashboard') ?>">

                <?php
                $disk = fetch_current_file_manager();

                if (isset($disk) && $disk == "local_server") {
                    $logo = $data['logo'] != "" ? base_url("public/uploads/site/" . $data['logo']) : base_url('public/backend/assets/img/news/img01.jpg');
                } elseif (isset($disk) && $disk == "aws_s3") {
                    $logo = fetch_cloud_front_url('site', $data['logo']);
                } else {
                    $logo = $data['logo'] != "" ? base_url("public/uploads/site/" . $data['logo']) : base_url('public/backend/assets/img/news/img01.jpg');
                }


                if (isset($disk) && $disk == "local_server") {
                    $half_logo = $data['half_logo'] != "" ? base_url("public/uploads/site/" . $data['half_logo']) : base_url('public/backend/assets/img/news/img01.jpg');
                } elseif (isset($disk) && $disk == "aws_s3") {
                    $half_logo = fetch_cloud_front_url('site', $data['logo']);
                } else {
                    $half_logo = $data['half_logo'] != "" ? base_url("public/uploads/site/" . $data['half_logo']) : base_url('public/backend/assets/img/news/img01.jpg');
                }
                ?>
                <img src=" <?= $logo; ?>" class="sidebar_logo h-max-60px" alt="">
            </a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="<?= base_url('admin/dashboard') ?>">
                <img src="<?= $half_logo; ?>" height="40px" alt="">
            </a>
        </div>
        <ul class="sidebar-menu">
            <li class="nav-item">
                <a class="nav-link" href="<?= base_url('/admin/dashboard') ?>">
                    <span class="material-symbols-outlined mr-1 ">
                        home
                    </span>
                    <span class="span"><?= labels('Dashboard', 'Dashboard') ?></span>
                </a>
            </li>

            <?php if ($permissionService->can($sidebarUserId, 'read', 'custom_fields')) { ?>
                <label for="custom fields section"
                    class="heading_lable"><?= labels('custom_fields_section', 'CUSTOM FIELDS') ?></label>
                <li class="nav-item <?= ($current_url == base_url('/admin/custom-fields')) ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= base_url('/admin/custom-fields') ?>">
                        <span class="material-symbols-outlined">
                            tune
                        </span>
                        <span class="span"><?= labels('custom_fields', 'Custom Fields') ?></span>
                    </a>
                </li>
            <?php } ?>
            <?php if ($permissions['read']['partner'] == 1 || $permissions['create']['partner'] == 1 || $permissions['update']['partner'] == 1) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('provider_management', 'PROVIDER MANAGEMENT') ?></label>
                <li
                    class="dropdown <?= ($current_url == base_url('/admin/partners') || $current_url == base_url('/admin/partners/add_partner') || $current_url == base_url('/admin/partners/provider_leaves') || $current_url == base_url('/admin/partners/provider_ledger')) ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown " data-toggle="dropdown">
                        <span class="material-symbols-outlined ">
                            engineering
                        </span>
                        <span class="span hide-on-mini"><?= labels('providers', 'Providers') ?></span>
                    </a>
                    <ul
                        class="dropdown-menu <?= ($current_url == base_url('/admin/partners') || $current_url == base_url('/admin/partners/add_partner') || $current_url == base_url('/admin/partners/bulk_import') || $current_url == base_url('/admin/partners/provider_leaves') || $current_url == base_url('/admin/partners/provider_ledger')) ? 'dropdown-active-open-menu' : '' ?>">
                        <?php if ($permissions['read']['partner'] == 1) { ?>
                            <li><a class="nav-link" href="<?= base_url('/admin/partners'); ?>">-
                                    <span><?= labels('provider_list', 'Provider List') ?></span></a></li>
                        <?php } ?>
                        <?php if ($permissions['create']['partner'] == 1) { ?>
                            <li><a class="nav-link" href="<?= base_url('/admin/partners/add_partner'); ?>">-
                                    <span><?= labels('add_new_provider', 'Add New Providers') ?></span></a></li>
                        <?php } ?>
                        <?php if ($permissions['read']['partner'] == 1) { ?>
                            <li><a class="nav-link" href="<?= base_url('/admin/partners/provider_leaves'); ?>">-
                                    <span><?= labels('provider_leaves', 'Provider Leaves') ?></span></a></li>
                        <?php } ?>
                        <?php if ($permissionService->isSuperAdmin((int) $sidebarUserId)) { ?>
                            <li><a class="nav-link" href="<?= base_url('/admin/partners/provider_ledger'); ?>">-
                                    <span><?= labels('provider_ledger', 'Provider Ledger') ?></span></a></li>
                        <?php } ?>
                        <?php if ($permissions['update']['partner'] == 1) { ?>
                            <li><a class="nav-link" href="<?= base_url('/admin/partners/bulk_import'); ?>">-
                                    <span><?= labels('bulk_provider_update', ' Bulk Provider Update') ?></span></a></li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <?php if ($permissionService->can($sidebarUserId, 'read', 'handyman')) { ?>
                <li class="nav-item <?= ($current_url == base_url('admin/handymen')) ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= base_url('admin/handymen') ?>">
                        <span class="material-symbols-outlined">handyman</span>
                        <span class="span"><?= labels('handymen', 'Handymen') ?></span>
                    </a>
                </li>
            <?php } ?>
            <?php if ($permissions['read']['payment_request'] == 1) { ?>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('admin/partners/payment_request'); ?>">
                        <span class="material-symbols-outlined">
                            payments
                        </span><span class="span"><?= labels('payment_request', "Payment Request") ?></span></a>
                </li>
            <?php } ?>
            <?php if ($permissions['read']['settlement'] == 1) { ?>
                <li
                    class="dropdown <?= ($current_url == base_url('admin/partners/settle_commission') || $current_url == base_url('admin/partners/manage_commission_history')) ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown" data-toggle="dropdown">
                        <span class="material-symbols-outlined">
                            receipt_long
                        </span><span class="span"><?= labels('manage_commission', "Settlements") ?></span>
                    </a>
                    <ul
                        class="dropdown-menu <?= ($current_url == base_url('admin/partners/settle_commission') || $current_url == base_url('admin/partners/manage_commission_history')) ? 'dropdown-active-open-menu' : '' ?>">
                        <?php if ($permissions['read']['settlement'] == 1) { ?>
                            <li>
                                <a class="nav-link" href="<?= base_url('admin/partners/settle_commission'); ?>">
                                    <span class="span">- <?= labels('manage_commission', "Settlements") ?></span></a>
                            </li>
                        <?php } ?>
                        <?php if ($permissions['read']['settlement'] == 1) { ?>
                            <li>
                                <a class="nav-link" href="<?= base_url('admin/partners/manage_commission_history') ?>">
                                    <span class="span">- <?= labels('settlement_history', ' Settlement History') ?></span></a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <?php if ($permissions['read']['cash_collection'] == 1) { ?>
                <li
                    class="dropdown <?= ($current_url == base_url('admin/partners/cash_collection') || $current_url == base_url('admin/partners/cash_collection_history')) ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown" data-toggle="dropdown">
                        <span class="material-symbols-outlined">
                            universal_currency_alt</span>
                        <span class="span"><?= labels('cash_collection', "Cash Collection") ?></span>
                    </a>
                    <ul class="dropdown-menu <?= ($current_url == base_url('admin/partners/cash_collection') || $current_url == base_url('admin/partners/cash_collection_history')) ? 'dropdown-active-open-menu' : '' ?>"
                        style="display: none;">
                        <?php if ($permissions['read']['cash_collection'] == 1) { ?>
                            <li>
                                <a class="nav-link" href="<?= base_url('admin/partners/cash_collection') ?>">
                                    <span class="span">- <?= labels('cash_collection', "Cash Collection") ?></span></a>
                            </li>
                        <?php } ?>
                        <?php if ($permissions['read']['cash_collection'] == 1) { ?>
                            <li>
                                <a class="nav-link" href="<?= base_url('admin/partners/cash_collection_history') ?>">
                                    <span class="span">-
                                        <?= labels('cash_collection_list', "Cash Collection List") ?></span></a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <?php if ($permissions['read']['orders'] == 1) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('booking_management', 'BOOKING MANAGEMENT') ?></label>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/admin/orders') ?>"><span
                            class="material-symbols-outlined">
                            list_alt
                        </span><span class="span"> <?= labels('bookings', 'Bookings') ?></span></span></a></li>
                <?php if ($permissions['read']['booking_payment'] == 1) { ?>
                    <li class="nav-item"><a class="nav-link"
                            href="<?= base_url('admin/all_settlement_cashcollection_history'); ?>">
                            <span class="material-symbols-outlined">
                                monetization_on
                            </span><span class="span"><?= labels('booking_payment', "Booking's Payment") ?></span></a>
                    </li>
                <?php } ?>
                <?php if ($permissions['read']['custom_job_requests'] == 1) { ?>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('admin/custom-job-requests'); ?>">
                            <span class="material-symbols-outlined">
                                work
                            </span><span class="span"><?= labels('custom_job_requests', "Custom Job Requests") ?></span></a>
                    </li>
                <?php } ?>

            <?php } ?>
            <?php if ($permissions['read']['services'] == 1) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('service_management', 'SERVICE MANAGEMENT') ?></label>
                <li
                    class="dropdown <?= ($current_url == base_url('/admin/services/add_service') || $current_url == base_url("admin/services")) ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown" data-toggle="dropdown">
                        <span class="material-symbols-outlined">
                            list
                        </span><span class="span"><?= labels('service', 'Service') ?></span>
                    </a>
                    <ul
                        class="dropdown-menu <?= ($current_url == base_url('/admin/services/add_service') || $current_url == base_url("admin/services") || $current_url == base_url('/admin/services/bulk_import_services')) ? 'dropdown-active-open-menu' : '' ?>">
                        <?php if ($permissions['read']['services'] == 1) { ?>
                            <li class="nav-item"><a class="nav-link" href="<?= base_url("admin/services"); ?>">-
                                    <span><?= labels('service_list', 'Services List') ?></span></a></li>
                        <?php } ?>
                        <?php if ($permissions['create']['services'] == 1) { ?>
                            <li class="nav-item"><a class="nav-link" href="<?= base_url('/admin/services/add_service'); ?>">-
                                    <span><?= labels('add_new_service', 'Add New Service') ?></span></a></li>
                        <?php } ?>

                        <?php if ($permissions['update']['services'] == 1) { ?>
                            <li class="nav-item"><a class="nav-link"
                                    href="<?= base_url('/admin/services/bulk_import_services'); ?>">-
                                    <span><?= labels('bulk_service_update', 'Bulk Service Update') ?></span></a></li>
                        <?php } ?>
                    </ul>
                </li>
                <?php if ($permissions['read']['categories'] == 1) { ?>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url("admin/categories"); ?>">
                            <span class="material-symbols-outlined">
                                category
                            </span><span class="span"><?= labels('service_categories', 'Service Categories') ?></span></a>
                    </li>
                <?php } ?>
            <?php } ?>
            <?php if ($permissions['read']['featured_section'] == 1 || $permissions['read']['sliders'] == 1) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('home_screen_management', 'HOME SCREEN MANAGEMENT') ?></label>
                <?php if ($permissions['read']['sliders'] == 1) { ?>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('/admin/sliders'); ?>"><span
                                class="material-symbols-outlined">
                                view_day
                            </span><span class="span"><?= labels('sliders', 'Sliders') ?></span></span></a></li>
                <?php } ?>
                <?php if ($permissions['read']['featured_section'] == 1) { ?>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('/admin/Featured_sections') ?>"><span
                                class="material-symbols-outlined">
                                view_comfy
                            </span> <span class="span"><?= labels('featured', 'Featured Section') ?></span></span></a>
                    </li>
                <?php } ?>
            <?php } ?>

            <?php if ($permissions['read']['customers'] == 1) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('customer_management', 'CUSTOMER MANAGEMENT') ?></label>
                <li><a class="nav-link" href="<?= base_url('/admin/users'); ?>"><span class="material-symbols-outlined">
                            tv_signin
                        </span><span class="span"><?= labels('customers', "Customers") ?></span></span></a></li>
                <li><a class="nav-link" href="<?= base_url('/admin/transactions'); ?>"><span
                            class="material-symbols-outlined">
                            receipt
                        </span><span class="span"><?= labels('transactions', "Transactions") ?></span></span></a></li>
                <li><a class="nav-link" href="<?= base_url('/admin/payment_refunds'); ?>"><span
                            class="material-symbols-outlined">
                            assignment_return
                        </span><span class="span"><?= labels('payment_refunds', 'Payment Refunds') ?></span></a></li>
                <li><a class="nav-link" href="<?= base_url('/admin/addresses'); ?>"><span class="material-symbols-outlined">
                            pin_drop
                        </span><span class="span"><?= labels('addresses', 'Addresses') ?></span></a></li>
            <?php } ?>
            <?php if ($permissions['read']['customer_queries'] == 1) { ?>
                <label for="support management"
                    class="heading_lable"><?= labels('support_management', 'SUPPORT MANAGEMENT') ?></label>
                <li><a class="nav-link" href="<?= base_url('/admin/customer_queris'); ?>"><span
                            class="material-symbols-outlined">
                            info
                        </span><span class="span"><?= labels('user_queries', 'User Queries') ?></span></a></li>
            <?php } ?>
            <?php if ($permissions['read']['chat'] == 1) { ?>
                <li>
                    <a class="nav-link" href="<?= base_url('/admin/chat'); ?>"><span class="material-symbols-outlined">
                            chat_bubble
                        </span><span class="span"><?= labels('chat', "Chat") ?></span><span
                            id="admin-chat-sidebar-unread-badge"
                            class="chat-sidebar-unread-badge<?= $adminChatUnreadUsersCount > 0 ? '' : ' hidden' ?>"><?= (int) $adminChatUnreadUsersCount ?></span></a>
                </li>
            <?php } ?>

            <?php if ($permissions['read']['reporting_reasons'] == 1) { ?>
                <li>
                    <a class="nav-link" href="<?= base_url('/admin/reason_for_report_and_block_chat'); ?>"><span
                            class="material-symbols-outlined">
                            comment
                        </span><span class="span"><?= labels('reporting_reasons', "Reporting Reasons") ?></span></span></a>
                </li>
            <?php } ?>

            <?php if ($permissions['read']['user_reports'] == 1) { ?>
                <li>
                    <a class="nav-link" href="<?= base_url('/admin/user_reports'); ?>"><span
                            class="material-symbols-outlined">
                            report
                        </span><span class="span"><?= labels('blocked_users', "Blocked Users") ?></span></span></a>
                </li>
            <?php } ?>

            <?php if ($permissionService->can($sidebarUserId, 'read', 'cancellation_reasons')) { ?>
                <li>
                    <a class="nav-link" href="<?= base_url('/admin/cancellation_reasons'); ?>"><span
                            class="material-symbols-outlined">
                            cancel
                        </span><span
                            class="span"><?= labels('cancellation_reasons', "Cancellation Reasons") ?></span></span></a>
                </li>
            <?php } ?>

            <!-- <php if ($permissionService->can($sidebarUserId, 'read', 'reschedule_reasons')) { ?>
                <li>
                    <a class="nav-link" href="<?= base_url('/admin/reschedule_reasons'); ?>"><span class="material-symbols-outlined">
                            event  
                        </span><span class="span"><?= labels('reschedule_reasons', "Reschedule Reasons") ?></span></span></a>
                </li>
            <php } ?> -->

            <?php if ($permissions['read']['promo_code'] == 1 || $permissions['create']['promo_code'] == 1) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('promotional_management', 'PROMOTIONAL MANAGEMENT') ?></label>
                <li
                    class="dropdown <?= ($current_url == base_url('/admin/promo_codes/add') || $current_url == base_url('/admin/promo_codes')) ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown " data-toggle="dropdown">
                        <span class="material-symbols-outlined ">
                            sell
                        </span>
                        <span class="span hide-on-mini"><?= labels('promocode', 'Promo codes') ?></span>
                    </a>
                    <ul
                        class="dropdown-menu <?= ($current_url == base_url('/admin/promo_codes/add') || $current_url == base_url("admin/promo_codes")) ? 'dropdown-active-open-menu' : '' ?>">
                        <?php if ($permissions['read']['promo_code'] == 1) { ?>
                            <li class="nav-item"><a class="nav-link" href="<?= base_url("admin/promo_codes"); ?>">-
                                    <span><?= labels('promocode', 'Promo codes') ?></span></a></li>
                        <?php } ?>
                        <?php if ($permissions['create']['promo_code'] == 1) { ?>
                            <li class="nav-item"><a class="nav-link" href="<?= base_url('/admin/promo_codes/add'); ?>">-
                                    <span><?= labels('add_promocodes', 'Add Promo Codes') ?></span></a></li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <?php if ($permissions['read']['send_notification'] == 1) { ?>
                <li>
                    <a class="nav-link" href="<?= base_url('/admin/notification'); ?>"><span
                            class="material-symbols-outlined">
                            phone_iphone
                        </span><span
                            class="span"><?= labels('send_notifications', "Send Notifications") ?></span></span></a>
                </li>
            <?php } ?>
            <?php if ($permissions['read']['email_notifications'] == 1) { ?>
                <li>
                    <a class="nav-link" href="<?= base_url('/admin/send_email_page'); ?>"><span
                            class="material-symbols-outlined">
                            mail
                        </span><span class="span"><?= labels('send_email', "Send Email") ?></span></span></a>
                </li>
            <?php } ?>
            <?php if ($permissions['read']['subscription'] == 1 || $permissions['create']['subscription'] == 1) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('subscription_management', 'SUBSCRIPTION MANAGEMENT') ?></label>
                <li
                    class="dropdown  <?= ($current_url == base_url('admin/subscription') || $current_url == base_url('admin/subscription/subscriber_list') || $current_url == base_url('admin/subscription/add_subscription')) ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown" data-toggle="dropdown"><span
                            class="material-symbols-outlined">
                            package_2
                        </span> <span class="span"><?= labels('subscription', "Subscription") ?></span></a>
                    <ul class="dropdown-menu <?= ($current_url == base_url('admin/subscription') || $current_url == base_url('admin/subscription/subscriber_list') || $current_url == base_url('admin/subscription/add_subscription')) ? 'dropdown-active-open-menu' : '' ?>"
                        style="display: none;">
                        <?php if ($permissions['read']['subscription'] == 1) { ?>
                            <li><a class="nav-link"
                                    href="<?= base_url('admin/subscription') ?>"><span>-<?= labels('list_subscription', "List Subscription") ?></span></span></a>
                            </li>
                        <?php } ?>
                        <?php if ($permissions['read']['subscription'] == 1) { ?>
                            <li><a class="nav-link"
                                    href="<?= base_url('admin/subscription/subscriber_list'); ?>">-<span><?= labels('subscriber_list', "Subscriber List") ?></span></span></a>
                            </li>
                        <?php } ?>
                        <?php if ($permissions['create']['subscription'] == 1) { ?>
                            <li><a class="nav-link"
                                    href="<?= base_url('admin/subscription/add_subscription'); ?>">-<span><?= labels('add_subscription', "Add Subscription") ?></span></span></a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <!-- custom pages moved to system management -->
            <!-- blog management -->
            <?php if ($permissions['read']['blog'] == 1 || $permissions['create']['blog'] == 1) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('blog_management', 'BLOG MANAGEMENT') ?></label>
                <li
                    class="dropdown <?= ($current_url == base_url('/admin/blog/add-blog') || $current_url == base_url("admin/blog")) ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown" data-toggle="dropdown">
                        <span class="material-symbols-outlined">
                            post
                        </span><span class="span"><?= labels('blog', 'Blog') ?></span>
                    </a>
                    <ul
                        class="dropdown-menu <?= ($current_url == base_url('/admin/blog/add-blog') || $current_url == base_url("admin/blog") || $current_url == base_url('/admin/blog/add-categories')) ? 'dropdown-active-open-menu' : '' ?>">
                        <?php if ($permissions['read']['blog'] == 1) { ?>
                            <li class="nav-item"><a class="nav-link" href="<?= base_url("admin/blog"); ?>">-
                                    <span><?= labels('blog_list', 'Blog List') ?></span></a></li>
                        <?php } ?>
                        <?php if ($permissions['create']['blog'] == 1) { ?>
                            <li class="nav-item"><a class="nav-link" href="<?= base_url('/admin/blog/add-blog'); ?>">-
                                    <span><?= labels('add_new_blog', 'Add New Blog') ?></span></a></li>
                        <?php } ?>
                        <?php if ($permissions['create']['blog'] == 1) { ?>
                            <li class="nav-item"><a class="nav-link" href="<?= base_url('/admin/blog/add-categories'); ?>">-
                                    <span><?= labels('blog_categories', 'Blog Categories') ?></span></a></li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <!-- end blog management -->
            <?php if ($permissions['read']['gallery'] == 1) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('media_section_management', 'MEDIA SECTION MANAGEMENT') ?></label>
                <li>
                    <a class="nav-link" href="<?= base_url('admin/gallery-view') ?>"><span
                            class="material-symbols-outlined">
                            gallery_thumbnail
                        </span><span class="span"> <?= labels('gallery', "Gallery") ?></span></span></a>
                </li>
            <?php } ?>
            <?php if ($permissions['read']['settings'] == 1 || $permissionService->can($sidebarUserId, 'read', 'custom_pages') || $permissions['read']['faq'] == 1 || $permissions['read']['system_user'] == 1 || $permissions['read']['database_backup'] == 1 || (!empty($_SESSION['email']) && $_SESSION['email'] === 'superadmin@gmail.com')) { ?>
                <label for="provider management"
                    class="heading_lable"><?= labels('system_management', 'SYSTEM MANAGEMENT') ?></label>
                <?php if ($permissions['read']['settings'] == 1) { ?>
                    <li>
                        <a class="nav-link" href="<?= base_url('admin/settings/system-settings') ?>"><span
                                class="material-symbols-outlined">
                                settings
                            </span><span class="span"><?= labels('system_settings', "System Settings") ?></span></span></a>
                    </li>
                <?php } ?>
                <?php if ($permissionService->can($sidebarUserId, 'read', 'custom_pages')) { ?>
                    <li>
                        <a class="nav-link" href="<?= base_url('admin/custom-pages') ?>"><span
                                class="material-symbols-outlined">
                                description
                            </span><span class="span"><?= labels('custom_pages', "Custom Pages") ?></span></span></a>
                    </li>
                <?php } ?>
                <?php if ($permissions['read']['faq'] == 1) { ?>
                    <li>
                        <a class="nav-link" href="<?= base_url('admin/faqs') ?>"><span class="material-symbols-outlined">
                                help
                            </span><span class="span"><?= labels('faqs', "FAQs") ?></span></span></a>
                    </li>
                <?php } ?>
                <?php if ($permissions['read']['system_user'] == 1) { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('/admin/system_users'); ?>"><span
                                class="material-symbols-outlined">
                                contact_emergency
                            </span><span class="span"><?= labels('system_user', 'System Users') ?></span></span></a>
                    </li>
                <?php } ?>
                <?php if ($permissions['read']['database_backup'] == 1) { ?>
                    <li>
                        <a class="nav-link" href="<?= base_url('/admin/database_backup'); ?>"><span
                                class="material-symbols-outlined">
                                cloud_download
                            </span><span class="span"> <?= labels('database_backup', 'Database backup') ?></span></span></a>
                    </li>
                <?php } ?>
                <?php if (!empty($_SESSION['email']) && $_SESSION['email'] === 'superadmin@gmail.com') { ?>
                    <li>
                        <a class="nav-link" href="javascript:void(0);" onclick="confirmTriggerDemoReset();"><span
                                class="material-symbols-outlined">
                                restart_alt
                            </span><span class="span">
                                <?= labels('trigger_demo_reset', 'Trigger Demo Reset') ?></span></span></a>
                    </li>
                <?php } ?>
            <?php } ?>
        </ul>
    </aside>
</div>
<script>
    function filterMenuItems() {
        var searchInput = document.getElementById('menu-search').value.toLowerCase().trim();
        var staticMenuItems = [
            "<div class='heading_lable'><?= labels('Dashboard', 'Dashboard') ?></div>",
            "<a class='nav-link' href='<?= base_url('/admin/dashboard') ?>'><span class='material-symbols-outlined'>home</span><?= labels('Dashboard', 'Dashboard') ?></a>",
            <?php if ($permissionService->can($sidebarUserId, 'read', 'custom_fields')): ?>
                    "<div class='heading_lable'><?= labels('custom_fields_section', 'CUSTOM FIELDS') ?></div>",
                "<a class='nav-link' href='<?= base_url('/admin/custom-fields') ?>'><span class='material-symbols-outlined'>tune</span><?= labels('provider_custom_fields', 'Custom Fields') ?></a>",
            <?php endif; ?>
            "<div class='heading_lable'><?= labels('provider_management', 'PROVIDER MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('/admin/partners'); ?>'><span class='material-symbols-outlined'>list</span> <?= labels('provider_list', 'Provider List') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/partners/add_partner'); ?>'><span class='material-symbols-outlined'>person_add</span> <?= labels('add_new_provider', 'Add New Providers') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/partners/provider_leaves'); ?>'><span class='material-symbols-outlined'>event_busy</span> <?= labels('provider_leaves', 'Provider Leaves') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/partners/bulk_import'); ?>'><span class='material-symbols-outlined'>upload</span><?= labels('bulk_provider_update', ' Bulk Provider Update') ?></a>",
            <?php if ($permissionService->can($sidebarUserId, 'read', 'handyman')) { ?>"<a class='nav-link' href='<?= base_url('admin/handymen'); ?>'><span class='material-symbols-outlined'>handyman</span><?= labels('handymen', 'Handymen') ?></a>", <?php } ?>
            "<a class='nav-link' href='<?= base_url('admin/partners/payment_request'); ?>'><span class='material-symbols-outlined'>payments</span><?= labels('payment_request', 'Payment Request') ?></a>",
            "<a class='nav-link' href='<?= base_url('admin/partners/settle_commission'); ?>'><span class='material-symbols-outlined'>receipt_long</span> <?= labels('manage_commission', 'Settlements') ?></a>",
            "<a class='nav-link' href='<?= base_url('admin/partners/manage_commission_history'); ?>'><span class='material-symbols-outlined'>history</span> <?= labels('settlement_history', 'Settlement History') ?></a>",
            "<a class='nav-link' href='<?= base_url('admin/partners/cash_collection') ?>'><span class='material-symbols-outlined'>universal_currency_alt</span> <?= labels('cash_collection', "Cash Collection") ?></a>",
            "<a class='nav-link' href='<?= base_url('admin/partners/cash_collection_history') ?>'><span class='material-symbols-outlined'>history</span><?= labels('cash_collection_list', "Cash Collection List") ?></a>",
            "<div class='heading_lable'><?= labels('booking_management', 'BOOKING MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('/admin/orders') ?>'><span class='material-symbols-outlined'>list_alt</span> <?= labels('bookings', 'Bookings') ?></a>",
            "<a class='nav-link' href='<?= base_url('admin/all_settlement_cashcollection_history'); ?>'><span class='material-symbols-outlined'>monetization_on</span> <?= labels('booking_payment', "Booking's Payment") ?></a>",
            "<a class='nav-link' href='<?= base_url('admin/custom-job-requests'); ?>'><span class='material-symbols-outlined'>work</span> <?= labels('custom_job_requests', "Custom Job Requests") ?></a>",
            "<div class='heading_lable'><?= labels('service_management', 'SERVICE MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url("admin/services"); ?>'><span class='material-symbols-outlined'>list_alt</span> <?= labels('service_list', 'Services List') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/services/add_service'); ?>'><span class='material-symbols-outlined'>add</span> <?= labels('add_new_service', 'Add New Service') ?></a>",
            "<a class='nav-link' href='<?= base_url("admin/categories"); ?>'><span class='material-symbols-outlined'>grid_view</span><?= labels('service_categories', 'Service Categories') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/services/bulk_import_services'); ?>'><span class='material-symbols-outlined'>upload</span><?= labels('bulk_service_update', 'Bulk Service Update') ?></a>",
            "<div class='heading_lable'><?= labels('home_screen_management', 'HOME SCREEN MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('/admin/sliders'); ?>'><span class='material-symbols-outlined'>view_day</span><?= labels('sliders', 'Sliders') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/Featured_sections') ?>'><span class='material-symbols-outlined'>view_comfy</span><?= labels('featured', 'Featured Section') ?></a>",
            "<div class='heading_lable'><?= labels('customer_management', 'CUSTOMER MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('/admin/users/'); ?>'><span class='material-symbols-outlined'>tv_signin</span><?= labels('customers', "Customers") ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/transactions'); ?>'><span class='material-symbols-outlined'>receipt</span><?= labels('transactions', "Transactions") ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/payment_refunds'); ?>'><span class='material-symbols-outlined'>assignment_return</span><?= labels('payment_refunds', 'Payment Refunds') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/addresses'); ?>'><span class='material-symbols-outlined'>pin_drop</span><?= labels('addresses', 'Addresses') ?></a>",
            "<div class='heading_lable'><?= labels('support_management', 'SUPPORT MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('/admin/customer_queris'); ?>'><span class='material-symbols-outlined'>info</span><?= labels('user_queries', 'User Queries') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/reason_for_report_and_block_chat'); ?>'><span class='material-symbols-outlined'>comment</span><?= labels('reporting_reasons', 'Reporting Reasons') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/user_reports'); ?>'><span class='material-symbols-outlined'>report</span><?= labels('blocked_users', 'Blocked Users') ?></a>",
            <?php if ($permissionService->can($sidebarUserId, 'read', 'cancellation_reasons')): ?>
                "<a class='nav-link' href='<?= base_url('/admin/cancellation_reasons'); ?>'><span class='material-symbols-outlined'>cancel</span><?= labels('cancellation_reasons', 'Cancellation Reasons') ?></a>",
            <?php endif; ?>
            "<a class='nav-link' href='<?= base_url('/admin/chat'); ?>'><span class='material-symbols-outlined'>chat_bubble</span><?= labels('chat', "Chat") ?></a>",
            "<div class='heading_lable'><?= labels('promotional_management', 'PROMOTIONAL MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('/admin/promo_codes'); ?>'><span class='material-symbols-outlined'>sell</span><?= labels('promocode', 'Promo codes') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/promo_codes/add'); ?>'><span class='material-symbols-outlined'>add</span><?= labels('add_promocodes', 'Add Promo Codes') ?></a>",
            <?php if ($permissions['read']['send_notification'] == 1): ?> "<a class='nav-link' href='<?= base_url('/admin/notification'); ?>'><span class='material-symbols-outlined'>phone_iphone</span><?= labels('send_notifications', "Send Notifications") ?></a>",
            <?php endif; ?>
            <?php if ($permissions['read']['email_notifications'] == 1): ?> "<a class='nav-link' href='<?= base_url('/admin/send_email_page'); ?>'><span class='material-symbols-outlined'>mail</span><?= labels('send_email', "Send Email") ?></a>",
            <?php endif; ?> "<div class='heading_lable'><?= labels('subscription_management', 'SUBSCRIPTION MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('admin/subscription') ?>'><span class='material-symbols-outlined'>package_2</span><?= labels('list_subscription', "List Subscription") ?></a>",
            "<a class='nav-link' href='<?= base_url('admin/subscription/subscriber_list'); ?>'><span class='material-symbols-outlined'>groups</span><?= labels('subscriber_list', "Subscriber List") ?></a>",
            "<a class='nav-link' href='<?= base_url('admin/subscription/add_subscription'); ?>'><span class='material-symbols-outlined'>add</span><?= labels('add_subscription', "Add Subscription") ?></a>",
            "<div class='heading_lable'><?= labels('blog_management', 'BLOG MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url("admin/blog"); ?>'><span class='material-symbols-outlined'>post</span><?= labels('blog_list', 'Blog List') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/blog/add-blog'); ?>'><span class='material-symbols-outlined'>add</span><?= labels('add_new_blog', 'Add New Blog') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/blog/add-categories'); ?>'><span class='material-symbols-outlined'>category</span><?= labels('blog_categories', 'Blog Categories') ?></a>",
            "<div class='heading_lable'><?= labels('media_section_management', 'MEDIA SECTION MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('admin/gallery-view') ?>'><span class='material-symbols-outlined'>gallery_thumbnail</span><?= labels('gallery', "Gallery") ?></a>",
            "<div class='heading_lable'><?= labels('system_management', 'SYSTEM MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('admin/settings/system-settings') ?>'><span class='material-symbols-outlined'>settings</span><?= labels('system_settings', "System Settings") ?></a>",
            <?php if ($permissionService->can($sidebarUserId, 'read', 'custom_pages')) { ?> "<a class='nav-link' href='<?= base_url('admin/custom-pages') ?>'><span class='material-symbols-outlined'>description</span><?= labels('custom_pages', "Custom Pages") ?></a>",
            <?php } ?>
            "<a class='nav-link' href='<?= base_url('admin/faqs') ?>'><span class='material-symbols-outlined'>help</span><?= labels('faqs', "FAQs") ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/system_users'); ?>'><span class='material-symbols-outlined'>contact_emergency</span><?= labels('system_user', 'System Users') ?></a>",
            "<a class='nav-link' href='<?= base_url('/admin/database_backup'); ?>'><span class='material-symbols-outlined'>cloud_download</span><?= labels('database_backup', 'Database Backup') ?></a>",
            <?php if (!empty($_SESSION['email']) && $_SESSION['email'] === 'superadmin@gmail.com'): ?>
                "<a class='nav-link' href='javascript:void(0);' onclick='confirmTriggerDemoReset();'><span class='material-symbols-outlined'>restart_alt</span><?= labels('trigger_demo_reset', 'Trigger Demo Reset') ?></a>",
            <?php endif; ?>
        ];
        var searchResultContainer = document.querySelector('.search-result');
        searchResultContainer.innerHTML = ''; // Clear previous results
        if (searchInput === '') {
            // Show all menu items when search input is empty
            staticMenuItems.forEach(item => {
                // if (!item.includes('heading_lable')) {
                var searchItem = document.createElement('div');
                searchItem.classList.add('search-item');
                searchItem.innerHTML = item;
                searchResultContainer.appendChild(searchItem);
                // }
            });
            // Reset the height to the default value
            searchResultContainer.style.height = '500px';
        } else {
            // Filter menu items based on the search input
            var matchingItems = staticMenuItems.filter(item => {
                if (!item.includes('heading_lable')) {
                    return item.toLowerCase().includes(searchInput);
                }
                return false;
            });
            if (matchingItems.length > 0) {
                // Display matching menu items
                matchingItems.forEach(item => {
                    var searchItem = document.createElement('div');
                    searchItem.classList.add('search-item');
                    searchItem.innerHTML = item;
                    searchResultContainer.appendChild(searchItem);
                });
                // Calculate and set the height based on the number of results
                var resultHeight = matchingItems.length * 40; // Adjust 40 based on your styling
                searchResultContainer.style.height = resultHeight + 'px';
            } else {
                // If no results, set a default height
                searchResultContainer.style.height = '500px';
            }
        }
        // Show or hide the search results container
        searchResultContainer.style.display = matchingItems.length > 0 ? 'block' : 'none';
    }

    function showAllMenuItems() {
        // Display the entire menu when the input box is clicked for the first time
        var searchInput = document.getElementById('menu-search').value.trim();
        if (searchInput === '') {
            filterMenuItems();
        }
    }
    document.getElementById('menu-search').addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    <?php if (!empty($_SESSION['email']) && $_SESSION['email'] === 'superadmin@gmail.com'): ?>
        function confirmTriggerDemoReset() {
            Swal.fire({
                title: '<?= labels('are_your_sure', 'Are you sure?') ?>',
                text: '<?= labels('confirm_trigger_demo_reset', 'Are you sure you want to trigger demo reset?') ?>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!') ?>',
                cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
            }).then((result) => {
                if (!result.isConfirmed) return;

                var timerEl = document.getElementById('demo-reset-countdown');
                var badgeEl = document.getElementById('demo-reset-timer');
                if (timerEl) timerEl.textContent = 'Resetting...';
                if (badgeEl) badgeEl.classList.add('timer-urgent');

                Swal.fire({
                    title: '<?= labels('resetting_demo', 'Resetting demo...') ?>',
                    text: '<?= labels('resetting_demo_wait', 'Please wait, this may take a moment.') ?>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                fetch('<?= base_url('/admin/trigger_demo_reset') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.json().catch(function () { return { error: !res.ok }; });
                }).then(function (data) {
                    if (data && data.error) {
                        Swal.fire({
                            icon: 'error',
                            title: '<?= labels('error', 'Error') ?>',
                            text: data.message || '<?= labels('demo_reset_trigger_failed', 'Failed to trigger demo reset') ?>'
                        });
                        return;
                    }
                    window.location.reload();
                }).catch(function () {
                    Swal.fire({
                        icon: 'error',
                        title: '<?= labels('error', 'Error') ?>',
                        text: '<?= labels('demo_reset_trigger_failed', 'Failed to trigger demo reset') ?>'
                    });
                });
            });
        }
    <?php endif; ?>
</script>

<script>
    // Function to refresh language dropdown
    function refreshLanguageDropdown() {
        $.ajax({
            url: baseUrl + "/admin/language/get_dropdown_data",
            type: "GET",
            dataType: "json",
            success: function (response) {
                if (response.error === false) {
                    updateLanguageDropdown(response.languages, response.default_language);
                }
            },
            error: function () {
                console.log('Failed to refresh language dropdown');
            }
        });
    }

    // Function to update the language dropdown HTML
    function updateLanguageDropdown(languages, defaultLanguage) {
        var session = '<?= session()->get('lang') ?>';
        var currentLanguage = session || (defaultLanguage ? defaultLanguage.code : 'en');

        if (Array.isArray(languages) && languages.length > 1) {
            // Multiple languages - show dropdown
            var dropdownHtml = '<li class="dropdown navbar_dropdown mr-2 mt-2" id="language-dropdown-container">' +
                '<a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">' +
                '<div class="d-inline-block" id="current-language-display">' + currentLanguage.toUpperCase() + '</div>' +
                '</a>' +
                '<div class="dropdown-menu dropdown-menu-right" id="language-dropdown-menu">';

            languages.forEach(function (language) {
                var isActive = (language.code === currentLanguage) ? 'text-primary' : '';
                var isDefault = (language.id == defaultLanguage.id) ? 'selected' : '';
                dropdownHtml += '<span onclick="set_locale(\'' + language.code + '\')" class="dropdown-item has-icon ' + isActive + '" ' + isDefault + '>' +
                    language.code.toUpperCase() + ' - ' + language.language.charAt(0).toUpperCase() + language.language.slice(1) +
                    '</span>';
            });

            dropdownHtml += '</div></li>';

            // Replace single language display with dropdown
            $('#single-language-container').replaceWith(dropdownHtml);
        } else if (Array.isArray(languages) && languages.length === 1) {
            // Single language - show badge
            var singleLanguageHtml = '<li class="nav-item my-auto ml-2 mr-2" id="single-language-container">' +
                '<span class="badge badge-primary mt-2" style="border-radius: 8px!important;">' +
                '<p class="p-0 m-0">' + languages[0].code.toUpperCase() + '</p>' +
                '</span></li>';

            // Replace dropdown with single language display
            $('#language-dropdown-container').replaceWith(singleLanguageHtml);
        } else {
            // No languages available - fallback to default or EN
            var fallback = (defaultLanguage && defaultLanguage.code) ? defaultLanguage.code : 'en';
            var fallbackHtml = '<li class="nav-item my-auto ml-2 mr-2" id="single-language-container">' +
                '<span class="badge badge-primary mt-2" style="border-radius: 8px!important;">' +
                '<p class="p-0 m-0">' + fallback.toUpperCase() + '</p>' +
                '</span></li>';

            $('#language-dropdown-container').replaceWith(fallbackHtml);
        }
    }

    // Listen for language changes and refresh dropdown
    $(document).ready(function () {
        // Refresh dropdown when language is changed
        $(document).on('localeChanged', function () {
            setTimeout(function () {
                refreshLanguageDropdown();
            }, 500);
        });
    });
</script>

<script>
    /**
     * Admin web notifications dropdown
     * - Fetches the latest 5 notifications from `admin/notifications/recent`.
     * - Renders them safely (basic HTML escaping) to avoid injection issues.
     */
    (function () {
        var lastFetchAt = 0;
        var FETCH_COOLDOWN_MS = 15000; // avoid spamming endpoint if user opens dropdown repeatedly
        var POLL_MS = 60 * 1000; // refresh notifications every minute (same as provider panel)
        var I18N = {
            loading: <?= json_encode(labels('loading', 'Loading...')) ?>,
            noNotifications: <?= json_encode(labels('no_notifications_found', 'No notifications found')) ?>,
            fallbackTitle: <?= json_encode(labels('notification', 'Notification')) ?>,
            failedFetch: <?= json_encode(labels('failed_to_fetch_notifications', 'Failed to fetch notifications')) ?>,
            viewAllUrl: <?= json_encode(base_url('admin/notifications')) ?>
        };

        function setUnreadBadge(count) {
            var $badge = $('#admin-notification-count');
            var safe = parseInt(count, 10);
            if (isNaN(safe) || safe < 0) safe = 0;
            // Requirement:
            // - Hide when unread count is empty/0.
            // - Show and display the number only when > 0.
            if (safe <= 0) {
                $badge.text('');
                $badge.hide();
            } else {
                $badge.text(String(safe));
                $badge.show();
            }
            $badge.attr('aria-label', 'Unread notifications: ' + safe);
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function truncate(str, maxLen) {
            str = str || '';
            if (str.length <= maxLen) return str;
            return str.substring(0, maxLen) + '...';
        }

        function renderDropdownItems(rows) {
            var $container = $('#admin-notification-items');
            $container.empty();

            if (!rows || !rows.length) {
                $container.append('<div class="px-3 py-2 text-muted small">' + escapeHtml(I18N.noNotifications) + '</div>');
                return;
            }

            rows.forEach(function (n) {
                var title = escapeHtml(truncate(n.title || '', 60));
                var message = escapeHtml(truncate(n.message || '', 90));
                var date = escapeHtml(n.duration || n.date_sent || n.created_at || '');
                var href = (n.url && String(n.url).trim() !== '') ? escapeHtml(n.url) : I18N.viewAllUrl;

                /**
                 * Notification click redirect (Admin panel)
                 * ----------------------------------------
                 * Why:
                 * - Notifications can contain redirect context (booking/order, subscription, service).
                 * - We attach ONLY a minimal, safe subset from backend `context_data`.
                 *
                 * Security:
                 * - Context is URL-encoded JSON to keep HTML attributes safe.
                 * - We do NOT inject raw JSON into the DOM.
                 *
                 * NOTE:
                 * - The actual redirect + mark-as-read logic is implemented in:
                 *   `public/backend/assets/js/notification_redirects.js`
                 */
                var contextAttr = '';
                try {
                    contextAttr = encodeURIComponent(JSON.stringify(n.context_data || {}));
                } catch (e) {
                    contextAttr = encodeURIComponent('{}');
                }

                var contextHtmlAttr = ' data-notification-context="' + contextAttr + '" data-notification-panel="admin"';

                // Meta used by redirect handler (event_key = template key for system_generated).
                var eventTypeAttr = ' data-notification-event-type="' + escapeHtml(n.event_type || '') + '"';
                var eventKeyAttr = ' data-notification-event-key="' + escapeHtml(n.event_key || '') + '"';
                var notificationTypeAttr = ' data-notification-type="' + escapeHtml(n.notification_type || '') + '"';

                // Notification id used for "mark as read" API call on click.
                var nid = (n && n.id !== undefined && n.id !== null) ? String(n.id) : '';
                var notificationIdAttr = ' data-notification-id="' + escapeHtml(nid) + '"';

                var itemHtml = '' +
                    '<a class="dropdown-item d-block" href="' + href + '" style="white-space: normal;"' + contextHtmlAttr + eventTypeAttr + eventKeyAttr + notificationTypeAttr + notificationIdAttr + '>' +
                    '  <div class="font-weight-bold">' + (title || escapeHtml(I18N.fallbackTitle)) + '</div>' +
                    '  <div class="text-muted small">' + message + '</div>' +
                    (date ? ('  <div class="text-muted small mt-1">' + date + '</div>') : '') +
                    '</a>' +
                    '<div class="dropdown-divider"></div>';

                $container.append(itemHtml);
            });

            $container.find('.dropdown-divider:last').remove();
        }

        function loadRecentNotifications(force) {
            var now = Date.now();
            if (!force && (now - lastFetchAt) < FETCH_COOLDOWN_MS) return;
            lastFetchAt = now;

            $('#admin-notification-items').html(
                '<div class="px-3 py-2 text-muted small">' + escapeHtml(I18N.loading) + '</div>'
            );

            $.ajax({
                url: baseUrl + "/admin/notifications/recent",
                type: "GET",
                dataType: "json",
                success: function (resp) {
                    if (resp && resp.error === false) {
                        setUnreadBadge(resp.unread_count);
                        renderDropdownItems(resp.data || []);
                    } else {
                        $('#admin-notification-items').html(
                            '<div class="px-3 py-2 text-danger small">' + escapeHtml(I18N.failedFetch) + '</div>'
                        );
                    }
                },
                error: function () {
                    $('#admin-notification-items').html(
                        '<div class="px-3 py-2 text-danger small">' + escapeHtml(I18N.failedFetch) + '</div>'
                    );
                }
            });
        }

        // Load notifications when the dropdown is opened.
        $(document).on('show.bs.dropdown', '#admin-notification-dropdown', function () {
            loadRecentNotifications(false);
        });

        // Mark all as read action (POST with CSRF).
        $(document).on('click', '#admin-notification-mark-all', function (e) {
            e.preventDefault();

            $.ajax({
                url: baseUrl + "/admin/notifications/mark_all_read",
                type: "POST",
                dataType: "json",
                data: (function () {
                    var payload = {};
                    payload[csrfName] = csrfHash;
                    return payload;
                })(),
                success: function (resp) {
                    // Keep CSRF hash in sync if backend returns rotated token.
                    if (resp && resp.csrfHash) {
                        csrfHash = resp.csrfHash;
                    }

                    if (resp && resp.error === false) {
                        setUnreadBadge(resp.unread_count);
                        loadRecentNotifications(true);
                    } else {
                        if (typeof showToastMessage === 'function') {
                            showToastMessage((resp && resp.message) ? resp.message : I18N.failedFetch, 'error');
                        }
                    }
                },
                error: function () {
                    if (typeof showToastMessage === 'function') {
                        showToastMessage(I18N.failedFetch, 'error');
                    }
                }
            });
        });

        // Initial fetch so the badge is correct on page load.
        $(document).ready(function () {
            loadRecentNotifications(true);
        });

        // Poll every minute (force refresh).
        // Guard against double intervals if this script is evaluated more than once.
        if (!window.__adminNotificationPollStarted) {
            window.__adminNotificationPollStarted = true;
            setInterval(function () {
                loadRecentNotifications(true);
            }, POLL_MS);
        }
    })();
</script>