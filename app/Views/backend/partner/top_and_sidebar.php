<?php
$data = get_settings('general_settings', true);
$user1 = fetch_details('users', ["phone" => $_SESSION['identity']], );
$db = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('u.phone', $_SESSION['identity'])
    ->where('ug.group_id', "3");
if (!empty($_SESSION['loginType']) && in_array($_SESSION['loginType'], ['phone', 'email'], true)) {
    $builder->where('u.loginType', $_SESSION['loginType']);
}
$user1 = $builder->get()->getResultArray();
$provider = fetch_details('partner_details', ["partner_id" => $user1[0]['id']], );

// Build company name based on language with fallback logic
$company_name = '';
if (!empty($provider)) {
    // Get current language and default language
    $current_language = get_current_language();
    $default_language = get_default_language();

    // Initialize translation model
    $translationModel = new \App\Models\TranslatedPartnerDetails_model();

    // Try to get translation for current language first
    $current_translation = $translationModel->getTranslatedDetails($user1[0]['id'], $current_language);

    if ($current_translation && !empty($current_translation['company_name'])) {
        // Use current language translation if available
        $company_name = $current_translation['company_name'];
    } else {
        // If no current language translation, try default language
        if ($current_language !== $default_language) {
            $default_translation = $translationModel->getTranslatedDetails($user1[0]['id'], $default_language);
            if ($default_translation && !empty($default_translation['company_name'])) {
                // Use default language translation as fallback
                $company_name = $default_translation['company_name'];
            } else {
                // If no translation found, use company name from main table
                $company_name = $provider[0]['company_name'] ?? '';
            }
        } else {
            // If current language is default language, use main table
            $company_name = $provider[0]['company_name'] ?? '';
        }
    }
}

$current_url = current_url();

// Sidebar unread badges:
//   - Admin Support: distinct admin senders with unread messages to this partner.
//   - Chat (customer threads): distinct (customer, booking_id) conversations with unread messages.
$partnerSidebarId = (int) ($user1[0]['id'] ?? 0);
$partnerAdminSupportUnreadUsersCount = 0;
$partnerChatUnreadUsersCount = 0;
if ($partnerSidebarId > 0) {
    try {
        // Admin Support badge: total unread MESSAGE count (not distinct senders).
        $partnerAdminSupportUnreadUsersCount = $db->table('chats')
            ->where('sender_type', 0)
            ->where('receiver_id', $partnerSidebarId)
            ->where('receiver_type', 1)
            ->where('is_read', 0)
            ->countAllResults();

        // Chat badge: distinct USER count with unread activity on bookings owned by
        // this partner. Customer-authored messages use the native `is_read` flag
        // (receiver_type=1 already targets the provider); handyman-authored ones
        // use is_read_by_provider (the provider isn't the native receiver on those
        // rows — the booking's chat thread is shared between provider and their
        // assigned lead handyman, both able to chat with the same customer).
        // Inner-join `users` so orders whose customer account no longer exists
        // (deleted/seed accounts) don't inflate the badge — the chat list
        // applies the same filter, so those rows are never shown there either.
        $bookingUserRows = $db->table('orders o')
            ->select('o.user_id as sender_id')
            ->join('users u', 'u.id = o.user_id', 'inner')
            ->where('o.partner_id', $partnerSidebarId)
            ->where(
                "EXISTS (SELECT 1 FROM chats c WHERE c.booking_id = o.id AND ((c.sender_type = 2 AND c.is_read = 0) OR (c.sender_type = 3 AND c.is_read_by_provider = 0)))",
                null,
                false
            )
            ->groupBy('o.user_id')
            ->get()->getResultArray();

        $preBookingUserRows = $db->table('chats c')
            ->select('c.sender_id')
            ->join('users u', 'u.id = c.sender_id')
            ->where('c.sender_type', 2)
            ->where('c.receiver_id', $partnerSidebarId)
            ->where('c.receiver_type', 1)
            ->where('c.is_read', 0)
            ->where('c.booking_id IS NULL', null, false)
            ->groupBy('c.sender_id')
            ->get()->getResultArray();

        // Union distinct sender_ids across both buckets so a user with both a
        // booking chat and a pre-booking query is counted once.
        $distinctUserIds = [];
        foreach ($bookingUserRows as $r) {
            $distinctUserIds[(int) $r['sender_id']] = true;
        }
        foreach ($preBookingUserRows as $r) {
            $distinctUserIds[(int) $r['sender_id']] = true;
        }
        $partnerChatUnreadUsersCount = count($distinctUserIds);
    } catch (\Throwable $e) {
        $partnerAdminSupportUnreadUsersCount = 0;
        $partnerChatUnreadUsersCount = 0;
    }
}

// Suppress Admin Support badge while on its own page (messages are being read).
// Chat badge is NOT suppressed on the chat page: the page may list multiple
// customer conversations and the sidebar should reflect any that are still unread.
$isOnAdminSupportPage = (strpos($current_url, base_url('partner/admin-support')) === 0);
if ($isOnAdminSupportPage) {
    $partnerAdminSupportUnreadUsersCount = 0;
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

    /* Sidebar context: push badge to far right of the link.
       Admin Support label is longer than Chat, so it needs a smaller
       left margin to avoid overflowing the sidebar. */
    .sidebar-menu li a.nav-link #partner-admin-support-sidebar-unread-badge {
        margin-left: 77px;
    }

    .sidebar-menu li a.nav-link #partner-chat-sidebar-unread-badge {
        margin-left: 100px;
    }

    .chat-sidebar-unread-badge.hidden {
        display: none;
    }
</style>
<script>
    /**
     * Partner sidebar chat badges: refresh from server.
     *
     * Why a server poll:
     * - Partner has TWO sidebar badges: Admin Support + Chat (customer).
     * - Both represent distinct sender/conversation counts (not raw message counts),
     *   so a purely client-side increment would drift on rapid messages.
     * - This helper hits a small endpoint that runs the same SQL used to render
     *   the badges on initial page load, so the value stays canonical.
     *
     * Exposed globally so page-level scripts (admin_chat.php, provider_chats.php)
     * can trigger an immediate refresh after sending / reading messages.
     */
    (function () {
        var POLL_MS = 30 * 1000; // refresh every 30s

        function applyBadge($badge, count) {
            if (!$badge.length) return;
            var safe = parseInt(count, 10);
            if (isNaN(safe) || safe < 0) safe = 0;
            $badge.text(safe);
            if (safe > 0) $badge.removeClass('hidden');
            else $badge.addClass('hidden');
        }

        // Suppress Admin Support badge on its own page — partner is reading those.
        // Chat badge always applies the server value: it's the canonical source
        // (open-thread reads are committed synchronously via mark_chat_as_read
        // well before this 30s poll fires), and it's the only safety net for
        // unread threads other than the one currently open — the client-side
        // `recomputePartnerChatSidebarBadge()` only reflects rows it already
        // knows about via FCM push, so it can't recover a missed push.
        var ADMIN_SUPPORT_PATH = '<?= rtrim(parse_url(base_url('partner/admin-support'), PHP_URL_PATH) ?? '/partner/admin-support', '/') ?>';

        function isOnAdminSupportPage() {
            var p = window.location.pathname.replace(/\/+$/, '');
            return p === ADMIN_SUPPORT_PATH || p.indexOf(ADMIN_SUPPORT_PATH + '/') === 0;
        }

        /**
         * Optimistic +1 on the Chat badge, applied instantly on FCM push — before
         * the `refreshPartnerChatSidebarBadges()` AJAX round-trip resolves.
         *
         * Why: on pages other than provider_chats.php there is no local row state
         * to bump (that page's own `bumpUnreadBadge()` + `recomputePartnerChatSidebarBadge()`
         * already keeps the badge exact there). Everywhere else, without this the
         * badge only moves once the server round-trip completes, and if that call
         * is slow/dropped the count sits stale until the next 30s poll.
         * Safe to over-count briefly: the AJAX response below always overwrites
         * with the true server count right after.
         */
        window.bumpPartnerChatSidebarBadgeOptimistic = function () {
            var $chat = $('#partner-chat-sidebar-unread-badge');
            if (!$chat.length) return;
            var current = parseInt($chat.text() || '0', 10) || 0;
            applyBadge($chat, current + 1);
        };

        window.refreshPartnerChatSidebarBadges = function () {
            var $admin = $('#partner-admin-support-sidebar-unread-badge');
            var $chat = $('#partner-chat-sidebar-unread-badge');
            if (!$admin.length && !$chat.length) return;
            $.ajax({
                url: (typeof baseUrl !== 'undefined' ? baseUrl : '') + 'partner/sidebar_unread_users_count',
                type: 'GET',
                dataType: 'json',
                success: function (resp) {
                    if (!resp || resp.error) return;
                    var d = resp.data || {};
                    applyBadge($admin, isOnAdminSupportPage() ? 0 : d.admin_support);
                    applyBadge($chat, d.chat);
                }
            });
        };

        $(document).ready(function () {
            if (!window.__partnerSidebarChatBadgePollStarted) {
                window.__partnerSidebarChatBadgePollStarted = true;
                setInterval(window.refreshPartnerChatSidebarBadges, POLL_MS);
            }
        });
    })();
</script>
<?php
// Full-width demo CTA: show only when demo mode is on (ALLOW_MODIFICATION == 0).
// Banner is fixed at top; body.has-demo-cta pushes sidebar and navbar below it via CSS.
$show_demo_cta = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0;
if ($show_demo_cta): ?>
    <?php
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
<nav class="navbar new_nav_bar navbar-expand-lg main-navbar">
    <form class="form-inline mr-auto">
        <ul class="navbar-nav mr-3">
            <li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg text-new-primary"><i
                        class="fas fa-bars"></i></a></li>

            <?php
            $is_already_subscribe = fetch_details('partner_subscriptions', ['partner_id' => $user1[0]['id']]);
            ?>
            <a href="<?= base_url('partner/subscription') ?>" data-toggle="search" class="nav-link nav-link-lg  mt-1">
                <?php
                if (isset($is_already_subscribe[0]['status']) && $is_already_subscribe[0]['status'] == "active") { ?>
                    <span class="text-dark">
                        <span class="badge badge-info"
                            style="border-radius: 8px!important"><?= labels('subscription_active', 'Subscription active') ?>
                        </span>
                    </span>
                <?php } else { ?>
                    <span class="text-dark">
                        <span class="badge badge-danger"
                            style="border-radius: 8px!important"><?= labels('subscription_deactive', 'Subscription Deactive') ?></span>
                    </span>
                <?php } ?>
            </a>
            <?= view('backend/partials/demo_reset_timer') ?>
            <div class="nav-item search-element">
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
        <ul class="navbar-nav navbar-right">
            <?php
            // Fetch the default language
            $default_language = fetch_details('languages', ['is_default' => '1']);
            $default_language_id = (!empty($default_language)) ? $default_language[0]['id'] : null;
            ?>
            <!--
                Partner notifications dropdown (web panel)
                ------------------------------------------------
                Why:
                - Partners asked for a notification dropdown besides (just before) the language selector.
                - We keep the dropdown lightweight and fetch only the latest 5 notifications.
                - A "View all notifications" button sends the partner to the full list page.
            -->
            <li class="dropdown navbar_dropdown mr-2 mt-2" id="partner-notification-dropdown">
                <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user"
                    id="partner-notification-toggle" style="position: relative;">
                    <!-- IMPORTANT:
                        `.navbar_dropdown` has a primary background color.
                        Using `.text-new-primary` here makes the bell the same color as the background (invisible).
                        So we let it inherit the link color (white) for proper contrast.
                    -->
                    <!--
                        Bell + badge wrapper
                        - Badge is positioned relative to the bell, not the dropdown toggle.
                        - This prevents overlapping the dropdown caret arrow.
                    -->
                    <span style="position: relative; display: inline-block; margin-right: 10px;">
                        <i class="fas fa-bell"></i>
                        <!--
                            Unread badge:
                            Previous behavior:
                            - Always visible (even when 0), per earlier requirement.

                            New requirement:
                            - Hide the badge when unread count is empty/0.

                            Notes:
                            - We keep the element in the DOM so JS can show it when needed.
                            - Shows ONLY the unread count (when > 0).
                            - Positioned over the bell icon.
                        -->
                        <span class="badge badge-danger" id="partner-notification-count"
                            style="position:absolute; top:-15px; right:-30px; border-radius: 10px; font-size: 10px; z-index: 2; display:none;"></span>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-right" style="min-width: 340px;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                        <span><?= labels('notifications', 'Notifications') ?></span>
                        <!--
                            "Mark all as read" (provider panel)
                            - Marks all provider-visible notifications as read.
                            - We keep "View all notifications" as the footer action.
                        -->
                        <a href="#" class="small"
                            id="partner-notification-mark-all"><?= labels('mark_all_as_read', 'Mark all as read') ?></a>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div id="partner-notification-items" style="max-height: 320px; overflow: auto;">
                        <div class="px-3 py-2 text-muted small"><?= labels('loading', 'Loading...') ?></div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="<?= base_url('partner/notifications') ?>"
                        class="dropdown-item text-center text-new-primary font-weight-bold">
                        <?= labels('view_all_notifications', 'View all notifications') ?>
                    </a>
                </div>
            </li>
            <?php
            if (count($languages_locale) > 1) { ?>
                <li class="dropdown navbar_dropdown mr-2 mt-2">
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
                        <div class="d-inline-block"><?= strtoupper($default_language) ?>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
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

                <li class="nav-item mt-2 ml-2 mr-2" id="single-language-container">
                    <span class="badge badge-primary" style="border-radius: 8px!important;">
                        <p class="p-0 m-0"><?= strtoupper($languages_locale[0]['code']) ?></p>
                    </span>
                </li>

            <?php } else { // Fallback when no available languages were detected (e.g., files missing)
                $fallbackCode = (!empty($default_language) && isset($default_language[0]['code'])) ? $default_language[0]['code'] : 'en'; ?>

                <li class="nav-item mr-2 mt-2" id="single-language-container">
                    <span class="badge badge-primary" style="border-radius: 8px!important;">
                        <p class="mb-0"><?= strtoupper($fallbackCode) ?></p>
                    </span>
                </li>
            <?php } ?>

            <!-- <li class="dropdown navbar_dropdown mr-2 mt-2">
                <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                    <div class="d-inline-block"><?= strtoupper($current_lang) ?>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <?php foreach ($languages_locale as $language) { ?>
                        <span onclick="set_locale('<?= $language['code'] ?>')" class="dropdown-item has-icon <?= ($language['code'] == $current_lang) ? "text-primary" : "" ?>">
                            <?= strtoupper($language['code']) . " - " . ucwords($language['language']) ?>
                        </span>
                    <?php } ?>
                </div>
            </li> -->
            <li class="dropdown navbar_dropdown mt-2">
                <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                    <img src="<?= base_url($provider[0]['banner']) ?>" class="sidebar_logo h-max-60px navbar_image"
                        alt="no image">
                    <div class="d-inline-block"><?= labels('hello', 'Hi') ?>,
                        <?= mb_strlen($company_name) > 15 ? mb_substr($company_name, 0, 15) . "..." : $company_name; ?>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="<?= base_url('partner/profile') ?>" class="dropdown-item has-icon">
                        <i class="far fa-user"></i> <?= labels('profile', "Profile") ?>
                    </a>
                    <a href="<?= base_url('auth/logout') ?>" class="dropdown-item has-icon text-danger"
                        id="logout-link">
                        <i class="fas fa-sign-out-alt"></i> <?= labels('logout', "Logout") ?>
                    </a>
                </div>
            </li>
        </ul>
</nav>
<div class="main-sidebar">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">


            <?php
            $disk = fetch_current_file_manager();

            if (isset($disk) && $disk == "local_server") {
                $partner_logo = $data['partner_logo'] != "" ? base_url("public/uploads/site/" . $data['partner_logo']) : base_url('public/backend/assets/img/news/img01.jpg');
            } elseif (isset($disk) && $disk == "aws_s3") {
                $partner_logo = fetch_cloud_front_url('site', $data['partner_logo']);
            } else {
                $partner_logo = $data['partner_logo'] != "" ? base_url("public/uploads/site/" . $data['partner_logo']) : base_url('public/backend/assets/img/news/img01.jpg');
            }


            if (isset($disk) && $disk == "local_server") {
                $partner_half_logo = $data['partner_half_logo'] != "" ? base_url("public/uploads/site/" . $data['partner_half_logo']) : base_url('public/backend/assets/img/news/img01.jpg');
            } elseif (isset($disk) && $disk == "aws_s3") {
                $partner_half_logo = fetch_cloud_front_url('site', $data['partner_half_logo']);
            } else {
                $partner_half_logo = $data['partner_half_logo'] != "" ? base_url("public/uploads/site/" . $data['partner_half_logo']) : base_url('public/backend/assets/img/news/img01.jpg');
            }
            ?>


            <a href="<?= base_url('partner') ?>">
                <img src="<?= $partner_logo; ?>" class="sidebar_logo h-max-60px" alt="Logo">
            </a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="<?= base_url('partner') ?>">
                <img src="<?= $partner_half_logo; ?>" height="40px" alt="logo">
            </a>
        </div>
        <ul class="sidebar-menu">
            <li class="nav-item"><a class="nav-link" href="<?= base_url('/partner') ?>"> <span
                        class="material-symbols-outlined mr-1">
                        home
                    </span>
                    <span class="span"><?= labels('Dashboard', "Dashboard") ?></span></span></a></li>
            <label for="provider management"
                class="heading_lable"><?= labels('booking_management', 'BOOKING MANAGEMENT') ?></label>
            <li>
                <a class="nav-link" href="<?= base_url('partner/orders') ?>">
                    <span class="material-symbols-outlined">
                        list_alt
                    </span>
                    <span class="span"><?= labels('bookings', "Bookings") ?></span></span></a>
            </li>

            <li class="<?= ($current_url == base_url('partner/orders/live_map')) ? 'active' : '' ?>">
                <a class="nav-link" href="<?= base_url('partner/orders/live_map') ?>">
                    <span class="material-symbols-outlined">
                        location_on
                    </span>
                    <span class="span"><?= labels('live_tracking', "Live Tracking") ?></span></span></a>
            </li>

            <li>
                <a class="nav-link" href="<?= base_url('partner/JobRequests') ?>">
                    <span class="material-symbols-outlined">
                        pending_actions
                    </span>
                    <span class="span"><?= labels('job_requests', "Job Request's") ?></span></span></a>
            </li>

            <label for="provider management"
                class="heading_lable"><?= labels('service_management', 'SERVICE MANAGEMENT') ?></label>
            <li
                class="dropdown <?= ($current_url == base_url('partner/services') || $current_url == base_url('partner/services/add') || $current_url == base_url('partner/services/bulk_import_services')) ? 'active' : '' ?>">
                <a href="#" class="nav-link has-dropdown" data-toggle="dropdown">
                    <span class="material-symbols-outlined">
                        list
                    </span><span class="span"><?= labels('service', 'Service') ?></span>
                </a>
                <ul class="dropdown-menu  <?= ($current_url == base_url('partner/services') || $current_url == base_url('partner/services/add')) ? 'dropdown-active-open-menu' : '' ?>"
                    style="display: none;">
                    <li>
                        <a class="nav-link" href="<?= base_url('partner/services') ?>">-
                            <span><?= labels('service_list', 'Services List') ?></span></span></a>
                    </li>
                        <!-- Provider service creation is temporarily disabled. -->
                        <!--
                        <li class="nav-item"><a class="nav-link" href="<?= base_url('partner/services/add'); ?>">-
                            <span><?= labels('add_new_service', 'Add New Service') ?></span></a></li>
                        -->
                    <!-- Provider bulk service creation and updates are temporarily disabled. -->
                    <!--
                    <li>
                        <a class="nav-link" href="<?= base_url('partner/services/bulk_import_services') ?>">-
                            <span><?= labels('bulk_service_update', 'Bulk Service Update') ?></span></span></a>
                    </li>
                    -->
                </ul>
            </li>
            <li>
                <a class="nav-link" href="<?= base_url('partner/categories') ?>">
                    <span class="material-symbols-outlined">
                        category
                    </span>
                    <span class="span"><?= labels('service_categories', 'Service Categories') ?></span></span></a>
            </li>
            <label for="handyman management"
                class="heading_lable"><?= strtoupper(labels('handyman_management', 'HANDYMAN MANAGEMENT')) ?></label>
            <li class="<?= ($current_url == base_url('partner/handymen')) ? 'active' : '' ?>">
                <a class="nav-link" href="<?= base_url('partner/handymen') ?>">
                    <span class="material-symbols-outlined">
                        person_add
                    </span>
                    <span class="span"><?= labels('handymen', 'Handymen') ?></span>
                </a>
            </li>
            <label for="slot management"
                class="heading_lable"><?= labels('slot_management', 'SLOT MANAGEMENT') ?></label>
            <li>
                <a class="nav-link" href="<?= base_url('partner/working-hours') ?>">
                    <span class="material-symbols-outlined">
                        schedule
                    </span>
                    <span class="span"><?= labels('working_hours', 'Working Hours') ?></span></a>
            </li>
            <li>
                <a class="nav-link" href="<?= base_url('partner/slot-configuration') ?>">
                    <span class="material-symbols-outlined">
                        tune
                    </span>
                    <span class="span"><?= labels('slot_configuration', 'Slot Configuration') ?></span></a>
            </li>
            <li>
                <a class="nav-link" href="<?= base_url('partner/leaves') ?>">
                    <span class="material-symbols-outlined">
                        event_busy
                    </span>
                    <span class="span"><?= labels('leaves', 'Leaves') ?></span></a>
            </li>
            <label for="provider management"
                class="heading_lable"><?= labels('promotional_management', 'PROMOTIONAL MANAGEMENT') ?></label>
            <li
                class="dropdown <?= ($current_url == base_url('partner/promo_codes') || $current_url == base_url('partner/promo_codes/add')) ? 'active' : '' ?>">
                <a href="#" class="nav-link has-dropdown" data-toggle="dropdown">
                    <span class="material-symbols-outlined">
                        sell
                    </span><span class="span"><?= labels('promocode', "Promo Codes") ?></span>
                </a>
                <ul class="dropdown-menu <?= ($current_url == base_url('partner/promo_codes') || $current_url == base_url('partner/promo_codes/add')) ? 'dropdown-active-open-menu' : '' ?>"
                    style="display: none;">
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('partner/promo_codes') ?>">-
                            <span><?= labels('promocode', "Promo Codes") ?></span></a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('partner/promo_codes/add'); ?>">-
                            <span><?= labels('add_promocodes', 'Add Promocodes') ?></span></a></li>
                </ul>
            </li>
            <label for="provider management"
                class="heading_lable"><?= labels('review_management', 'REVIEW MANAGEMENT') ?></label>
            <li>
                <a class="nav-link" href="<?= base_url('partner/review') ?>"><span class="material-symbols-outlined">
                        star
                    </span><span class="span"><?= labels('review', "Reviews") ?></span></span></a>
            </li>
            <label for="provider management"
                class="heading_lable"><?= labels('financial_management', 'FINANCIAL MANAGEMENT') ?></label>
            <li>
                <a class="nav-link" href="<?= base_url('partner/withdrawal_requests') ?>"><span
                        class="material-symbols-outlined">
                        account_balance_wallet
                    </span><span class="span"><?= labels('withdraw_requests', "Withdraw Requests") ?></span></span></a>
            </li>
            <li>
                <a class="nav-link" href="<?= base_url('partner/cash_collection') ?>"><span
                        class="material-symbols-outlined">
                        add_card
                    </span><span class="span"><?= labels('cash_collection', "Cash Collection ") ?></span></span></a>
            </li>
            <li class="<?= ($current_url == base_url('partner/handyman-cash-collection')) ? 'active' : '' ?>">
                <a class="nav-link" href="<?= base_url('partner/handyman-cash-collection') ?>"><span
                        class="material-symbols-outlined">
                        payments
                    </span><span class="span"><?= labels('handymen_cash_collections', "Handymen Cash Collections") ?></span></span></a>
            </li>
            <li>
                <a class="nav-link" href="<?= base_url('partner/settlement') ?>"><span
                        class="material-symbols-outlined">
                        handshake
                    </span><span class="span"><?= labels('settlement', "Settlement") ?></span></span></a>
            </li>
            <li>
                <a class="nav-link" href="<?= base_url('partner/settlement_cashcollection_history') ?>"><span
                        class="material-symbols-outlined">
                        monetization_on
                    </span><span
                        class="span"><?= labels('booking_payment_management', 'Booking Payment Management') ?></span></span></a>
            </li>
            <label for="provider management"
                class="heading_lable"><?= labels('subscription_management', 'SUBSCRIPTION MANAGEMENT') ?></label>
            <li
                class="dropdown  <?= ($current_url == base_url('partner/subscription') || $current_url == base_url('partner/subscription_history')) ? 'active' : '' ?>">
                <a href="#" class="nav-link has-dropdown" data-toggle="dropdown">
                    <span class="material-symbols-outlined">
                        package_2
                    </span><span class="span"><?= labels('subscription', "Subscription") ?></span>
                </a>
                <ul class="dropdown-menu <?= ($current_url == base_url('partner/subscription') || $current_url == base_url('partner/subscription_history')) ? 'dropdown-active-open-menu' : '' ?>"
                    style="display: none;">
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('partner/subscription') ?>">-
                            <span><?= labels('subscription', "Subscription") ?></span></a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('partner/subscription_history') ?>">-
                            <span><?= labels('subscription_history', "Subscription History") ?></span></a></li>
                </ul>
            </li>
            <label for="provider management"
                class="heading_lable"><?= labels('media_section_management', 'MEDIA SECTION MANAGEMENT') ?></label>
            <li>
                <a class="nav-link" href="<?= base_url('partner/gallery-view') ?>">
                    <span class="material-symbols-outlined">
                        gallery_thumbnail
                    </span>
                    <span class="span"><?= labels('gallery', "Gallery") ?></span></a>
            </li>
            <label for="provider management"
                class="heading_lable"><?= labels('support_management', 'SUPPORT MANANGEMENT') ?></label>
            <li>
                <a class="nav-link" href="<?= base_url('/partner/admin-support'); ?>"><span
                        class="material-symbols-outlined">
                        contact_support
                    </span><span class="span"><?= labels('admin_support', "Admin Support") ?></span><span
                        id="partner-admin-support-sidebar-unread-badge"
                        class="chat-sidebar-unread-badge<?= $partnerAdminSupportUnreadUsersCount > 0 ? '' : ' hidden' ?>"><?= (int) $partnerAdminSupportUnreadUsersCount ?></span></a>
            </li>
            <li>
                <a class="nav-link" href="<?= base_url('/partner/provider-chats'); ?>"><span
                        class="material-symbols-outlined">
                        chat
                    </span><span class="span"><?= labels('chat', "Chat") ?></span><span
                        id="partner-chat-sidebar-unread-badge"
                        class="chat-sidebar-unread-badge<?= $partnerChatUnreadUsersCount > 0 ? '' : ' hidden' ?>"><?= (int) $partnerChatUnreadUsersCount ?></span></a>
            </li>
            <li>
                <a class="nav-link" href="<?= base_url('/partner/reported_users'); ?>"><span
                        class="material-symbols-outlined">
                        report
                    </span><span class="span"><?= labels('blocked_users', "Blocked Users") ?></span></span></a>
            </li>
            <label for="seo management" class="heading_lable"><?= labels('seo_management', 'SEO MANAGEMENT') ?></label>
            <li>
                <a class="nav-link" href="<?= base_url('/partner/seo'); ?>"><span class="material-symbols-outlined">
                        manage_search
                    </span><span class="span"><?= labels('seo_settings', "SEO Settings") ?></span></a>
            </li>
        </ul>
    </aside>
</div>
<script>
    function filterMenuItems() {
        var searchInput = document.getElementById('menu-search').value.toLowerCase();
        var staticMenuItems = [
            "<div class='heading_lable'><?= labels('Dashboard', 'Dashboard') ?></div>",
            "<a class='nav-link' href='<?= base_url('/partner') ?>'><span class='material-symbols-outlined'>home</span><?= labels('Dashboard', 'Dashboard') ?></a>",
            "<div class='heading_lable'><?= labels('booking_management', 'BOOKING MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('partner/orders') ?>'><span class='material-symbols-outlined'>list_alt</span><?= labels('bookings', "Bookings") ?></a>",
            "<a class='nav-link' href='<?= base_url('partner/JobRequests') ?>'><span class='material-symbols-outlined'>pending_actions</span><?= labels('job_requests', "Job Request's") ?></a>",

            "<div class='heading_lable'><?= labels('service_management', 'SERVICE MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('partner/services') ?>'><span class='material-symbols-outlined'>list</span><?= labels('service_list', 'Services List') ?></a>",
            // Provider service creation is temporarily disabled.
            "<a class='nav-link' href='<?= base_url('partner/categories') ?>'><span class='material-symbols-outlined'>category</span><?= labels('service_categories', 'Service Categories') ?></a>",
            // Provider bulk service creation and updates are temporarily disabled.
            "<div class='heading_lable'><?= labels('handyman_management', 'HANDYMAN MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('partner/handymen/add') ?>'><span class='material-symbols-outlined'>person_add</span><?= labels('add_handyman', 'Add Handyman') ?></a>",
            "<div class='heading_lable'><?= labels('slot_management', 'SLOT MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('partner/working-hours') ?>'><span class='material-symbols-outlined'>schedule</span><?= labels('working_hours', 'Working Hours') ?></a>",
            "<a class='nav-link' href='<?= base_url('partner/slot-configuration') ?>'><span class='material-symbols-outlined'>tune</span><?= labels('slot_configuration', 'Slot Configuration') ?></a>",
            "<a class='nav-link' href='<?= base_url('partner/leaves') ?>'><span class='material-symbols-outlined'>event_busy</span><?= labels('leaves', 'Leaves') ?></a>",
            "<div class='heading_lable'><?= labels('promotional_management', 'PROMOTIONAL MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('partner/promo_codes') ?>'><span class='material-symbols-outlined'>sell</span><?= labels('promocode', "Promo Codes") ?></a>",
            "<a class='nav-link' href='<?= base_url('partner/promo_codes/add'); ?>'><span class='material-symbols-outlined'>add</span><?= labels('add_promocodes', 'Add Promocodes') ?></a>",
            "<div class='heading_lable'><?= labels('review_management', 'REVIEW MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('partner/review') ?>'><span class='material-symbols-outlined'>star</span><?= labels('review', "Reviews") ?></a>",
            "<div class='heading_lable'><?= labels('financial_management', 'FINANCIAL MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('partner/withdrawal_requests') ?>'><span class='material-symbols-outlined'>account_balance_wallet</span><?= labels('withdraw_requests', "Withdraw Requests") ?></a>",
            "<a class='nav-link' href='<?= base_url('partner/cash_collection') ?>'><span class='material-symbols-outlined'>add_card</span><?= labels('cash_collection', "Cash Collection ") ?></a>",
            "<a class='nav-link' href='<?= base_url('partner/handyman-cash-collection') ?>'><span class='material-symbols-outlined'>payments</span><?= labels('handymen_cash_collections', "Handymen Cash Collections") ?></a>",
            "<a class='nav-link' href='<?= base_url('partner/settlement') ?>'><span class='material-symbols-outlined'>handshake</span><?= labels('settlement', "Settlement") ?></a>",
            "<a class='nav-link' href='<?= base_url('partner/settlement_cashcollection_history') ?>'><span class='material-symbols-outlined'>monetization_on</span><?= labels('booking_payment_management', 'Booking Payment Management') ?></a>",
            "<div class='heading_lable'><?= labels('subscription_management', 'SUBSCRIPTION MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('partner/subscription') ?>'><span class='material-symbols-outlined'>package_2</span><?= labels('subscription', "Subscription") ?></a>",
            "<a class='nav-link' href='<?= base_url('partner/subscription_history') ?>'><span class='material-symbols-outlined'>list</span><?= labels('subscription_history', "Subscription History") ?></a>",
            "<div class='heading_lable'><?= labels('media_section_management', 'MEDIA SECTION MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('partner/gallery-view') ?>'><span class='material-symbols-outlined'>gallery_thumbnail</span><?= labels('gallery', "Gallery") ?></a>",
            "<div class='heading_lable'><?= labels('support_management', 'SUPPORT MANANGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('/partner/admin-support'); ?>'><span class='material-symbols-outlined'>contact_support</span><?= labels('admin_support', "Admin Support") ?></a>",
            "<a class='nav-link' href='<?= base_url('/partner/provider-chats'); ?>'><span class='material-symbols-outlined'>chat</span><?= labels('chat', "Chat") ?></a>",
            "<a class='nav-link' href='<?= base_url('/partner/reported_users'); ?>'><span class='material-symbols-outlined'>report</span><?= labels('blocked_users', "Blocked Users") ?></a>",
            "<div class='heading_lable'><?= labels('seo_management', 'SEO MANAGEMENT') ?></div>",
            "<a class='nav-link' href='<?= base_url('/partner/seo'); ?>'><span class='material-symbols-outlined'>manage_search</span><?= labels('seo_settings', "SEO Settings") ?></a>",
        ];
        var searchResultContainer = document.querySelector('.search-result');
        searchResultContainer.innerHTML = ''; // Clear previous results
        if (searchInput.trim() === '') {
            staticMenuItems.forEach(item => {
                // Check if the item is not a heading
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
                // Exclude items that are headings
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
</script>

<script>
    // Function to refresh language dropdown
    function refreshLanguageDropdown() {
        $.ajax({
            url: baseUrl + "/partner/api/v1/get_language_list",
            type: "GET",
            dataType: "json",
            success: function (response) {
                if (response.error === false) {
                    updateLanguageDropdown(response.data, response.default_language);
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
        var currentLanguage = session || (defaultLanguage ? defaultLanguage : 'en');

        if (languages.length > 1) {
            // Multiple languages - show dropdown
            var dropdownHtml = '<li class="dropdown navbar_dropdown mr-2 mt-2" id="language-dropdown-container">' +
                '<a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">' +
                '<div class="d-inline-block" id="current-language-display">' + currentLanguage.toUpperCase() + '</div>' +
                '</a>' +
                '<div class="dropdown-menu dropdown-menu-right" id="language-dropdown-menu">';

            languages.forEach(function (language) {
                var isActive = (language.code === currentLanguage) ? 'text-primary' : '';
                var isDefault = (language.code === defaultLanguage) ? 'selected' : '';
                dropdownHtml += '<span onclick="set_locale(\'' + language.code + '\')" class="dropdown-item has-icon ' + isActive + '" ' + isDefault + '>' +
                    language.code.toUpperCase() + ' - ' + language.language.charAt(0).toUpperCase() + language.language.slice(1) +
                    '</span>';
            });

            dropdownHtml += '</div></li>';

            // Replace single language display with dropdown
            $('#single-language-container').replaceWith(dropdownHtml);
        } else {
            // Single language - show badge
            var singleLanguageHtml = '<li class="nav-item my-auto ml-2 mr-2" id="single-language-container">' +
                '<span class="badge badge-primary mt-2" style="border-radius: 8px!important;">' +
                '<p class="p-0 m-0">' + languages[0].code.toUpperCase() + '</p>' +
                '</span></li>';

            // Replace dropdown with single language display
            $('#language-dropdown-container').replaceWith(singleLanguageHtml);
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

        // Track logout event
        $('#logout-link').on('click', function () {
            if (typeof trackLogout === 'function') {
                trackLogout();
            }
        });
    });
</script>

<script>
    /**
     * Partner web notifications dropdown
     * - Fetches the latest 5 notifications from `partner/notifications/recent`.
     * - Renders them safely (basic HTML escaping) to avoid injection issues.
     */
    (function () {
        var lastFetchAt = 0;
        var FETCH_COOLDOWN_MS = 15000; // avoid spamming endpoint if user opens dropdown repeatedly
        var POLL_MS = 60 * 1000; // refresh notifications every minute (requirement)
        var I18N = {
            loading: <?= json_encode(labels('loading', 'Loading...')) ?>,
            noNotifications: <?= json_encode(labels('no_notifications_found', 'No notifications found')) ?>,
            fallbackTitle: <?= json_encode(labels('notification', 'Notification')) ?>,
            failedFetch: <?= json_encode(labels('failed_to_fetch_notifications', 'Failed to fetch notifications')) ?>,
            viewAllUrl: <?= json_encode(base_url('partner/notifications')) ?>
        };

        function setUnreadBadge(count) {
            var $badge = $('#partner-notification-count');
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
            var $container = $('#partner-notification-items');
            $container.empty();

            if (!rows || !rows.length) {
                $container.append('<div class="px-3 py-2 text-muted small">' + escapeHtml(I18N.noNotifications) + '</div>');
                return;
            }

            rows.forEach(function (n) {
                var title = escapeHtml(truncate(n.title || '', 60));
                var message = escapeHtml(truncate(n.message || '', 90));
                // Backend already returns a human-friendly duration for the dropdown.
                var date = escapeHtml(n.duration || n.date_sent || n.created_at || '');
                var href = (n.url && String(n.url).trim() !== '') ? escapeHtml(n.url) : I18N.viewAllUrl;

                /**
                 * Notification click redirect (provider panel)
                 * -------------------------------------------
                 * Why:
                 * - Notifications can contain "context" (order_id / booking_id / subscription_id / category_id).
                 * - We attach ONLY this minimal context as a data attribute.
                 * - A centralized JS file reads it and performs the redirect on click.
                 *
                 * Security:
                 * - We URL-encode the JSON so it is safe to store inside HTML attributes.
                 * - We do NOT inject raw JSON into the HTML.
                 */
                // Always attach a context payload (even if empty).
                // Why:
                // - Admin-sent notifications (event_type=sent_by_admin) may not have context_data,
                //   but still need click redirects (based on notification_type).
                // - Our centralized click handler relies on these data attributes being present.
                var contextAttr = '';
                try {
                    // `context_data` is already a *filtered* subset returned by the backend controller.
                    // We still guard JSON stringify to be extra safe.
                    contextAttr = encodeURIComponent(JSON.stringify(n.context_data || {}));
                } catch (e) {
                    // Safe fallback: empty object
                    contextAttr = encodeURIComponent('{}');
                }
                var contextHtmlAttr = ' data-notification-context="' + contextAttr + '" data-notification-panel="partner"';

                // Meta needed for admin-sent notifications redirect rules.
                // We keep them as data-attributes so the centralized JS can decide the redirect.
                var eventTypeAttr = ' data-notification-event-type="' + escapeHtml(n.event_type || '') + '"';
                // For system_generated, redirect uses template key (event_key), not "system_generated".
                var eventKeyAttr = ' data-notification-event-key="' + escapeHtml(n.event_key || '') + '"';
                var notificationTypeAttr = ' data-notification-type="' + escapeHtml(n.notification_type || '') + '"';
                // Notification id used for "mark as read" API on click.
                // IMPORTANT:
                // - Do NOT use `n.id || ''` here because `0` is falsy.
                // - While IDs should never be 0, keeping this robust avoids silent failures.
                var nid = (n && n.id !== undefined && n.id !== null) ? String(n.id) : '';
                var notificationIdAttr = ' data-notification-id="' + escapeHtml(nid) + '"';

                // Keep markup simple and consistent with bootstrap dropdown items.
                var itemHtml = '' +
                    '<a class="dropdown-item d-block" href="' + href + '" style="white-space: normal;"' + contextHtmlAttr + eventTypeAttr + eventKeyAttr + notificationTypeAttr + notificationIdAttr + '>' +
                    '  <div class="font-weight-bold">' + (title || escapeHtml(I18N.fallbackTitle)) + '</div>' +
                    '  <div class="text-muted small">' + message + '</div>' +
                    (date ? ('  <div class="text-muted small mt-1">' + date + '</div>') : '') +
                    '</a>' +
                    '<div class="dropdown-divider"></div>';

                $container.append(itemHtml);
            });

            // Remove the last divider for a cleaner look.
            $container.find('.dropdown-divider:last').remove();
        }

        function loadRecentNotifications(force) {
            var now = Date.now();
            if (!force && (now - lastFetchAt) < FETCH_COOLDOWN_MS) return;
            lastFetchAt = now;

            $('#partner-notification-items').html(
                '<div class="px-3 py-2 text-muted small">' + escapeHtml(I18N.loading) + '</div>'
            );

            $.ajax({
                url: baseUrl + "partner/notifications/recent",
                type: "GET",
                dataType: "json",
                success: function (resp) {
                    if (resp && resp.error === false) {
                        // Always update unread badge (0 if none).
                        setUnreadBadge(resp.unread_count);
                        renderDropdownItems(resp.data || []);
                    } else {
                        $('#partner-notification-items').html(
                            '<div class="px-3 py-2 text-danger small">' + escapeHtml(I18N.failedFetch) + '</div>'
                        );
                    }
                },
                error: function () {
                    $('#partner-notification-items').html(
                        '<div class="px-3 py-2 text-danger small">' + escapeHtml(I18N.failedFetch) + '</div>'
                    );
                }
            });
        }

        // Load notifications when the dropdown is opened.
        $(document).on('show.bs.dropdown', '#partner-notification-dropdown', function () {
            loadRecentNotifications(false);
        });

        // Mark all as read action (POST with CSRF).
        $(document).on('click', '#partner-notification-mark-all', function (e) {
            e.preventDefault();

            $.ajax({
                url: baseUrl + "/partner/notifications/mark_all_read",
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
                        // Refresh dropdown list so it stays consistent.
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
        if (!window.__partnerNotificationPollStarted) {
            window.__partnerNotificationPollStarted = true;
            setInterval(function () {
                loadRecentNotifications(true);
            }, POLL_MS);
        }
    })();
</script>