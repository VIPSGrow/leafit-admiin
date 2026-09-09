<?php
$currentUrl = current_url();

// Logo URLs for sidebar header.
$_siteData = get_settings('general_settings', true);
$_fileService = new \App\Services\utility\FileService();
$sidebarLogoFull = $_fileService->exists('site', $_siteData['handyman_logo'] ?? null)
    ? $_fileService->url($_siteData['handyman_logo'], 'site')
    : null;
$sidebarLogoMini = $_fileService->exists('site', $_siteData['handyman_half_logo'] ?? null)
    ? $_fileService->url($_siteData['handyman_half_logo'], 'site')
    : null;

// Helper: is the current URL on or under a given base path?
function handymanUrlActive(string $path): bool
{
    return strpos(current_url(), base_url($path)) === 0;
}

// Initial chat unread-count badge — refreshed periodically via sidebar_unread_users_count().
$handymanId = (int) (session()->get('user_id') ?? 0);
$handymanChatUnreadCount = $handymanId > 0 ? (new \App\Models\BookingHandymenModel())->unreadChatBookingCountForHandyman($handymanId) : 0;

// Single source of truth for the sidebar menu — also drives the navbar search dropdown
// (handymanMenuSearch() below reads the same array via json_encode), so adding/removing/
// renaming an item here updates both automatically.
$handymanMenuSections = [
    [
        'items' => [
            ['route' => 'handyman', 'icon' => 'home', 'label_key' => 'dashboard', 'label_default' => 'Dashboard', 'active' => ($currentUrl === base_url('handyman') || $currentUrl === base_url('handyman/dashboard'))],
        ],
    ],
    [
        'heading_key' => 'booking_management', 'heading_default' => 'BOOKING MANAGEMENT',
        'items' => [
            ['route' => 'handyman/bookings', 'icon' => 'list_alt', 'label_key' => 'bookings', 'label_default' => 'Bookings'],
        ],
    ],
    [
        'heading_key' => 'financial_management', 'heading_default' => 'FINANCIAL MANAGEMENT',
        'items' => [
            ['route' => 'handyman/cash-collection', 'icon' => 'add_card', 'label_key' => 'cash_collection', 'label_default' => 'Cash Collection'],
        ],
    ],
    [
        'heading_key' => 'review_management', 'heading_default' => 'REVIEW MANAGEMENT',
        'items' => [
            ['route' => 'handyman/reviews', 'icon' => 'star_rate', 'label_key' => 'my_reviews', 'label_default' => 'My Reviews'],
        ],
    ],
    [
        'heading_key' => 'support_management', 'heading_default' => 'SUPPORT',
        'items' => [
            ['route' => 'handyman/chat', 'icon' => 'chat', 'label_key' => 'chat', 'label_default' => 'Chat', 'badge' => 'chat'],
            ['route' => 'handyman/notifications', 'icon' => 'notifications', 'label_key' => 'notifications', 'label_default' => 'Notifications'],
        ],
    ],
];

// Flattened list consumed by the navbar search dropdown (handymanMenuSearch() in navbar.php).
$handymanSearchEntries = [];
foreach ($handymanMenuSections as $section) {
    if (!empty($section['heading_key'])) {
        $handymanSearchEntries[] = ['type' => 'heading', 'label' => labels($section['heading_key'], $section['heading_default'])];
    }
    foreach ($section['items'] as $item) {
        $handymanSearchEntries[] = [
            'type' => 'item',
            'icon' => $item['icon'],
            'url' => base_url($item['route']),
            'label' => labels($item['label_key'], $item['label_default']),
        ];
    }
}
?>
<div class="main-sidebar">
    <!-- Sidebar logo header — height matches navbar so borders align -->
    <div class="handyman-sidebar-logo">
        <a href="<?= base_url('handyman') ?>">
            <?php if ($sidebarLogoFull !== null): ?>
                <img src="<?= esc($sidebarLogoFull) ?>" class="logo-full" alt="Logo">
            <?php endif; ?>
            <?php if ($sidebarLogoMini !== null): ?>
                <img src="<?= esc($sidebarLogoMini) ?>" class="logo-mini" alt="Logo">
            <?php endif; ?>
        </a>
        <!-- × close button — shown on mobile only via CSS -->
        <button type="button" id="handyman-sidebar-close" class="sidebar-close-btn"
            aria-label="<?= labels('close', 'Close') ?>">
            <i class="fas fa-times" style="font-size:1.2rem;"></i>
        </button>
    </div>

    <aside id="sidebar-wrapper">
        <ul class="sidebar-menu">
            <?php foreach ($handymanMenuSections as $section): ?>
                <?php if (!empty($section['heading_key'])): ?>
                    <label class="heading_lable"><?= labels($section['heading_key'], $section['heading_default']) ?></label>
                <?php endif; ?>
                <?php foreach ($section['items'] as $item): ?>
                    <?php $isActive = $item['active'] ?? handymanUrlActive($item['route']); ?>
                    <li class="<?= $isActive ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= base_url($item['route']) ?>" data-tooltip="<?= esc(labels($item['label_key'], $item['label_default'])) ?>">
                            <span class="material-symbols-outlined"><?= esc($item['icon']) ?></span>
                            <span class="span"><?= labels($item['label_key'], $item['label_default']) ?></span>
                            <?php if (($item['badge'] ?? null) === 'chat'): ?>
                                <!-- x-init re-syncs $store.handymanChat.count from this page's freshly
                                     computed $handymanChatUnreadCount on every mount (i.e. every Turbo
                                     navigation, since this element isn't turbo-permanent). Without it the
                                     store — seeded only once at Alpine's initial boot in the
                                     data-turbo-eval="false" script below — would keep showing whatever
                                     count it had from the first page load until the next 30s poll,
                                     same staleness bug as the notification badge (see navbar.php). -->
                                <span class="chat-sidebar-unread-badge" x-data
                                    data-initial-chat-unread-count="<?= (int) $handymanChatUnreadCount ?>"
                                    x-init="$store.handymanChat.count = parseInt($el.dataset.initialChatUnreadCount, 10) || 0"
                                    x-show="$store.handymanChat.count > 0" x-text="$store.handymanChat.count"
                                    style="<?= $handymanChatUnreadCount > 0 ? '' : 'display:none;' ?>"><?= $handymanChatUnreadCount > 0 ? (int) $handymanChatUnreadCount : '' ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </aside>
</div>

<script data-turbo-eval="false">
    // Fed by $handymanSearchEntries (PHP) — same array that renders the sidebar <ul> above,
    // so the navbar search dropdown can never drift from the actual menu.
    var handymanSearchEntries = <?= json_encode($handymanSearchEntries) ?>;

    // Alpine component backing the navbar search box (x-data="handymanMenuSearch()" in navbar.php).
    // Visibility/close is driven only through this component's `open`/`query` state — handyman-panel.js
    // dispatches `handyman-search-open`/`handyman-search-close` window events instead of touching
    // .search-result directly, so there's a single source of truth for the dropdown's display.
    function handymanMenuSearch() {
        return {
            query: '',
            open: false,
            results() {
                var q = this.query.trim().toLowerCase();
                return q === ''
                    ? handymanSearchEntries
                    : handymanSearchEntries.filter(function (e) { return e.type === 'item' && e.label.toLowerCase().includes(q); });
            },
            close() {
                this.open = false;
                this.query = '';
            }
        };
    }

    /* ── Chat sidebar unread-count badge ──────────────────── */
    // Alpine.store, not a scoped x-data — chat.php calls refreshHandymanChatSidebarBadge() directly
    // as a global function (its own send/read events), so state must live somewhere both it and the
    // sidebar badge element can reach.
    document.addEventListener('alpine:init', function () {
        Alpine.store('handymanChat', { count: <?= (int) $handymanChatUnreadCount ?> });
    });

    // Native fetch, not $.ajax — jQuery's XHR transport throws "Cannot read properties of
    // undefined (reading 'open')" when called after a Turbo morph navigation (same jQuery/Turbo
    // interaction BootstrapTableTurbo works around in scripts.php).
    function refreshHandymanChatSidebarBadge() {
        var body = new URLSearchParams();
        body.set(csrfName, csrfHash);
        fetch(baseUrl + '/handyman/chat/sidebar_unread_users_count', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (response) {
                if (response && response.csrfName) csrfName = response.csrfName;
                if (response && response.csrfHash) csrfHash = response.csrfHash;
                if (response.error) return;
                var count = (response.data && response.data.chat) ? parseInt(response.data.chat) : 0;
                if (window.Alpine) Alpine.store('handymanChat').count = count;
            })
            .catch(function () { });
    }

    function bumpHandymanChatSidebarBadgeOptimistic() {
        if (window.Alpine) {
            Alpine.store('handymanChat').count += 1;
        }
    }

    setInterval(refreshHandymanChatSidebarBadge, 30000);
</script>