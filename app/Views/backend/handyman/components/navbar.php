<?php
// Resolve handyman user from session identity (group_id = 4).
$db = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 4);

$userId = $_SESSION['user_id'] ?? 0;
$builder->where('u.id', $userId);

$handyman = $builder->get()->getRowArray();
$handymanId = (int) ($handyman['id'] ?? 0);
$handymanName = trim(($handyman['username'] ?? '') ?: (($handyman['first_name'] ?? '') . ' ' . ($handyman['last_name'] ?? '')));
if (empty($handymanName)) {
    $handymanName = labels('handyman', 'Handyman');
}

// Profile image.
$handymanImage = (new \App\Services\utility\FileService())->url($handyman['image'] ?? '', 'profile', 'public/backend/assets/user_default_image.png');

// Unread notifications badge — admin chat (handyman ← admin direction).
$unreadAdminMsgCount = 0;
if ($handymanId > 0) {
    try {
        $unreadAdminMsgCount = $db->table('chats')
            ->where('sender_type', 0)         // 0 = admin
            ->where('receiver_id', $handymanId)
            ->where('receiver_type', 4)        // 4 = handyman
            ->where('is_read', 0)
            ->countAllResults();
    } catch (\Throwable $e) {
        $unreadAdminMsgCount = 0;
    }
}

// Unread general notifications count (for the bell icon).
$unreadNotificationCount = 0;
if ($handymanId > 0) {
    try {
        $notificationModel = new \App\Models\Notification_model();
        $unreadNotificationCount = $notificationModel->countUnreadForAudience($handymanId, 'handyman');
    } catch (\Throwable $e) {
        $unreadNotificationCount = 0;
    }
}

// Firebase settings for global FCM foreground listener (registered below), so
// push popups fire on every handyman page, not just while chat.php is open.
$firebase_setting = get_settings('firebase_settings', true);

$currentUrl = current_url();

// Language list for selector.
$defaultLang = fetch_details('languages', ['is_default' => '1']);
$defaultLangId = !empty($defaultLang) ? $defaultLang[0]['id'] : null;
$defaultLangCode = !empty($defaultLang) ? $defaultLang[0]['code'] : 'en';
$sessionLang = session()->get('lang') ?: $defaultLangCode;

// Demo CTA.
$showDemoCta = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0;
$demoBannerHeight = 42;
?>
<?php if ($showDemoCta): ?>
    <style>
        .demo-cta-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            height:
                <?= $demoBannerHeight ?>
                px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            box-sizing: border-box;
            overflow: hidden;
        }

        .demo-cta-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(-55deg, transparent 0, transparent 10px, rgba(255, 255, 255, .1) 10px, rgba(255, 255, 255, .1) 12px);
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
                <?= $demoBannerHeight ?>
                px !important;
        }

        body.has-demo-cta.sidebar-mini .main-sidebar:after {
            top:
                <?= $demoBannerHeight ?>
                px !important;
        }

        body.has-demo-cta .navbar {
            top:
                <?= $demoBannerHeight ?>
                px !important;
        }

        body.has-demo-cta .main-content {
            padding-top:
                <?= 80 + $demoBannerHeight ?>
                px !important;
        }

        @media (max-width:1024px) {
            .demo-cta-banner {
                height: auto;
                min-height:
                    <?= $demoBannerHeight ?>
                    px;
                padding: 10px 12px;
                flex-direction: column;
                gap: 8px;
            }

            .demo-cta-banner .demo-cta-banner-text {
                font-size: .9rem;
                line-height: 1.3;
                text-align: center;
                margin: 0;
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

        .demo-cta-buy-btn {
            background-color: #fff !important;
            color: var(--primary-color) !important;
            padding-left: .6rem !important;
            padding-right: .6rem !important;
        }

        .demo-cta-buy-btn:hover {
            background-color: #f0f0f0 !important;
        }
    </style>
    <div class="demo-cta-banner w-100 text-center py-2 px-3" style="background-color:var(--primary-color);color:#eee;">
        <span
            class="demo-cta-banner-text me-2"><?= labels('demo_mode_cta', 'Demo mode is on. Your live business version is one click away.') ?></span>
        <a href="<?= defined('DEMO_PURCHASE_URL') ? DEMO_PURCHASE_URL : '#' ?>" target="_blank" rel="noopener"
            class="btn btn-sm ms-1 demo-cta-buy-btn fw-bolder"><?= labels('buy_now', 'Buy Now') ?></a>
    </div>
<?php endif; ?>

<nav class="navbar new_nav_bar navbar-expand-lg main-navbar">
    <div class="d-flex align-items-center me-auto">
        <ul class="navbar-nav me-3">
            <li>
                <a href="#" data-toggle="sidebar" class="nav-link nav-link-lg text-new-primary">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <?= view('backend/partials/demo_reset_timer') ?>
            <div class="nav-item search-element" id="handyman-search-element" data-turbo-permanent x-data="handymanMenuSearch()"
                x-on:handyman-search-close.window="close()"
                x-on:handyman-search-open.window="open = true">
                <input class="form-control" type="search" id="menu-search" x-model="query"
                    x-on:input="open = true" x-on:click="open = true" x-on:keydown.enter.prevent=""
                    placeholder="<?= labels('search', 'Search') ?>" aria-label="Search">
                <button class="btn" type="button"><i class="fa fa-search d-inline text-dark"></i></button>
                <div class="search-backdrop"></div>
                <div class="search-result" x-show="open && results().length > 0" x-cloak
                    x-bind:style="'height:' + Math.min(results().length * 40, 500) + 'px'">
                    <template x-for="(entry, idx) in results()" :key="idx">
                        <div class="search-item">
                            <template x-if="entry.type === 'heading'">
                                <div class="heading_lable" x-text="entry.label"></div>
                            </template>
                            <template x-if="entry.type === 'item'">
                                <a class="nav-link" :href="entry.url">
                                    <span class="material-symbols-outlined" x-text="entry.icon"></span>
                                    <span x-text="entry.label"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </ul>
    </div>

    <style>[x-cloak] { display: none !important; }</style>

    <ul class="navbar-nav navbar-right">
        <!-- Notifications dropdown -->
        <!-- Intentionally NOT data-turbo-permanent: keeping this element across Turbo
             navigations carried forward stale in-memory unreadCount, causing the badge
             to flash the old value before self-correcting. Each page already computes
             $unreadNotificationCount correctly server-side, so re-rendering fresh on every
             navigation (like the rest of the page) is both simpler and always accurate. -->
        <li class="dropdown navbar_dropdown me-2" id="handyman-notification-dropdown"
            data-initial-unread-count="<?= (int)$unreadNotificationCount ?>"
            x-data="handymanNotifDropdown()" x-on:show.bs.dropdown="load(false)">
            <a href="#" data-bs-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user"
                id="handyman-notification-toggle" style="position:relative;">
                <span style="position:relative;display:inline-block;">
                    <i class="fas fa-bell"></i>
                    <span class="badge bg-danger" id="handyman-notification-count"
                        x-show="unreadCount > 0" x-text="unreadCount"
                        style="position:absolute;top:-15px;right:-30px;border-radius:10px;font-size:10px;z-index:2; <?= $unreadNotificationCount > 0 ? '' : 'display:none;' ?>"><?= $unreadNotificationCount > 0 ? (int)$unreadNotificationCount : '' ?></span>
                </span>
            </a>
            <div class="dropdown-menu dropdown-menu-end" style="min-width:340px;">
                <div class="dropdown-header d-flex justify-content-between align-items-center">
                    <span><?= labels('notifications', 'Notifications') ?></span>
                    <a href="#" class="small" x-on:click.prevent="markAllRead()"
                        id="handyman-notification-mark-all"><?= labels('mark_all_as_read', 'Mark all as read') ?></a>
                </div>
                <div class="dropdown-divider"></div>
                <div id="handyman-notification-items" style="max-height:320px;overflow:auto;">
                    <template x-if="loading">
                        <div class="px-3 py-2 text-muted small" x-text="i18n.loading"></div>
                    </template>
                    <template x-if="!loading">
                        <div>
                            <template x-if="items.length === 0">
                                <div class="px-3 py-2 text-muted small" x-text="i18n.noNotifications"></div>
                            </template>
                            <template x-for="(n, idx) in items" :key="String(n.id || idx)">
                                <div>
                                    <a class="dropdown-item d-block" style="white-space:normal;"
                                        :href="(n.url && String(n.url).trim()) ? n.url : i18n.viewAllUrl"
                                        :data-notification-context="encodeURIComponent(JSON.stringify(n.context_data || {}))"
                                        data-notification-panel="handyman"
                                        :data-notification-event-type="n.event_type || ''"
                                        :data-notification-event-key="n.event_key || ''"
                                        :data-notification-type="n.notification_type || ''"
                                        :data-notification-id="n.id !== undefined ? String(n.id) : ''">
                                        <div class="fw-bold" x-text="(n.title || '').slice(0, 60) || i18n.fallbackTitle"></div>
                                        <div class="text-muted small" x-text="(n.message || '').slice(0, 90)"></div>
                                        <div class="text-muted small mt-1" x-show="n.duration || n.date_sent"
                                            x-text="n.duration || n.date_sent || ''"></div>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="dropdown-divider"></div>
                <a href="<?= base_url('handyman/notifications') ?>"
                    class="dropdown-item text-center text-new-primary fw-bold">
                    <?= labels('view_all_notifications', 'View all notifications') ?>
                </a>
            </div>
        </li>

        <!-- Language selector -->
        <?php if (count($languages_locale) > 1): ?>
            <li class="dropdown navbar_dropdown me-2">
                <a href="#" data-bs-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                    <div class="d-inline-block"><?= strtoupper($sessionLang) ?></div>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($languages_locale as $language): ?>
                        <span onclick="handymanSetLocale('<?= esc($language['code']) ?>')"
                            class="dropdown-item has-icon <?= ($language['code'] === $sessionLang) ? 'text-primary' : '' ?>"
                            <?= ($language['id'] == $defaultLangId) ? 'selected' : '' ?>>
                            <?= strtoupper(esc($language['code'])) ?> &ndash; <?= ucwords(esc($language['language'])) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </li>
        <?php else: ?>
            <li class="dropdown navbar_dropdown me-2" id="single-language-container">
                <span class="nav-link nav-link-lg nav-link-user" style="cursor:default;">
                    <div class="d-inline-block">
                        <?= strtoupper(esc(!empty($languages_locale) ? $languages_locale[0]['code'] : $defaultLangCode)) ?>
                    </div>
                </span>
            </li>
        <?php endif; ?>

        <!-- Profile dropdown -->
        <li class="dropdown navbar_dropdown" id="handyman-profile-dropdown" data-turbo-permanent>
            <a href="#" data-bs-toggle="dropdown"
                class="nav-link dropdown-toggle nav-link-lg nav-link-user nav-link-profile gap-2">
                <span class="navbar_image_wrap"><img src="<?= esc($handymanImage) ?>" class="navbar_image"
                        alt="<?= esc($handymanName) ?>"></span>
                <div class="d-inline-block">
                    <?= labels('hello', 'Hi') ?>,
                    <?= mb_strlen($handymanName) > 15 ? mb_substr($handymanName, 0, 15) . '...' : esc($handymanName) ?>
                </div>
            </a>
            <div class="dropdown-menu dropdown-menu-end">
                <a href="<?= base_url('handyman/profile') ?>" class="dropdown-item has-icon">
                    <i class="far fa-user"></i> <?= labels('profile', 'Profile') ?>
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?= base_url('auth/logout') ?>" class="dropdown-item has-icon text-danger"
                    id="handyman-logout-link" data-turbo="false">
                    <i class="fas fa-sign-out-alt"></i> <?= labels('logout', 'Logout') ?>
                </a>
            </div>
        </li>
    </ul>
</nav>

<script data-turbo-eval="false">
    // Alpine component backing the notification dropdown (x-data="handymanNotifDropdown()" above).
    // Must be defined before Alpine's deferred script evaluates x-data on the element — plain inline
    // scripts run at parse time, so declaring it anywhere before </body> is sufficient.
    //
    // IMPORTANT: this <script> has data-turbo-eval="false", so Turbo only executes it ONCE
    // (the first hard load) and skips it on every later Turbo navigation — same trick used for
    // custom.js etc. to avoid redefinition. That means any PHP value baked directly into this
    // function body would be frozen at whatever it was on that first load, forever — every
    // later page would mount a fresh <li>/component but call this same stale function, showing
    // the ORIGINAL count before init()'s load() fetch corrected it (visible "1 then 3" flash on
    // every sidebar navigation). So the initial count is read from the element's own
    // data-initial-unread-count attribute instead — that attribute IS re-rendered fresh by PHP
    // on every navigation, unlike this script.
    function handymanNotifDropdown() {
        var FETCH_COOLDOWN_MS = 15000;
        var POLL_MS = 60000;

        return {
            loading: false,
            hasFetched: false,
            lastFetchAt: 0,
            items: [],
            unreadCount: 0,

            i18n: {
                loading: <?= json_encode(labels('loading', 'Loading...')) ?>,
                noNotifications: <?= json_encode(labels('no_notifications_found', 'No notifications found')) ?>,
                fallbackTitle: <?= json_encode(labels('notification', 'Notification')) ?>,
                failedFetch: <?= json_encode(labels('failed_to_fetch_notifications', 'Failed to fetch notifications')) ?>,
                viewAllUrl: <?= json_encode(base_url('handyman/notifications')) ?>
            },

            init() {
                var initialCount = parseInt(this.$el.getAttribute('data-initial-unread-count'), 10);
                this.unreadCount = isNaN(initialCount) ? 0 : initialCount;

                if (!this.hasFetched) {
                    this.load(true);
                }
                if (!window.__handymanNotificationPollStarted) {
                    window.__handymanNotificationPollStarted = true;
                    setInterval(() => this.load(true), POLL_MS);
                }
            },

            load(force) {
                var now = Date.now();
                if (!force && (now - this.lastFetchAt) < FETCH_COOLDOWN_MS) return;
                this.lastFetchAt = now;

                if (this.items.length === 0) this.loading = true;

                var url = (typeof baseUrl !== 'undefined' ? baseUrl.replace(/\/$/, '') : '') + '/handyman/notifications/recent';

                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Network response was not ok');
                    return res.json();
                })
                .then(r => {
                    this.loading = false;
                    this.hasFetched = true;
                    if (r && r.error === false) {
                        this.unreadCount = parseInt(r.unread_count, 10) || 0;
                        this.items = r.data || [];
                    } else if (typeof showToastMessage === 'function') {
                        showToastMessage(this.i18n.failedFetch, 'error');
                    }
                })
                .catch(err => {
                    this.loading = false;
                    // fetch throws AbortError if cancelled, but we aren't using AbortController here
                    // If it's a network error from navigating away, we might want to ignore it
                    if (err.name === 'TypeError' && err.message === 'Failed to fetch') return; // Typically means navigated away
                    console.error("Failed to fetch notifications:", err);
                    if (typeof showToastMessage === 'function') showToastMessage(this.i18n.failedFetch, 'error');
                });
            },

            markAllRead() {
                var payload = new FormData();
                payload.append(csrfName, csrfHash);
                
                var url = (typeof baseUrl !== 'undefined' ? baseUrl.replace(/\/$/, '') : '') + '/handyman/notifications/mark_all_read';
                
                fetch(url, {
                    method: 'POST',
                    body: payload,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Network response was not ok');
                    return res.json();
                })
                .then(r => {
                    if (r && r.csrfHash) csrfHash = r.csrfHash;
                    if (r && r.error === false) {
                        this.unreadCount = parseInt(r.unread_count, 10) || 0;
                        this.load(true);
                    } else if (typeof showToastMessage === 'function') {
                        showToastMessage((r && r.message) || this.i18n.failedFetch, 'error');
                    }
                })
                .catch(err => {
                    if (err.name === 'TypeError' && err.message === 'Failed to fetch') return;
                    console.error("Failed to mark notifications as read:", err);
                    if (typeof showToastMessage === 'function') showToastMessage(this.i18n.failedFetch, 'error');
                });
            }
        };
    }

    // Custom language switcher for Handyman panel to properly integrate with Turbo JS
    function handymanSetLocale(language_code) {
        var url = (typeof baseUrl !== 'undefined' ? baseUrl : '') + '/lang/' + language_code;
        // Fix double slash if baseUrl already has trailing slash
        url = url.replace(/([^:])\/\//g, '$1/');
        
        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(result => {
            if (result && result.is_rtl !== undefined) {
                localStorage.setItem("is_rtl", JSON.stringify(result.is_rtl));
                localStorage.setItem("language", JSON.stringify(result.language));
            }
            if (typeof Turbo !== 'undefined') {
                Turbo.cache.clear();
            }
            // Force a hard reload to completely rebuild the page in the new language
            // bypassing any Idiomorph state or network caches.
            window.location.href = window.location.pathname + '?_t=' + new Date().getTime();
        })
        .catch(err => {
            console.error("Failed to fetch language details.", err);
            if (typeof Turbo !== 'undefined') {
                Turbo.cache.clear();
            }
            window.location.href = window.location.pathname + '?_t=' + new Date().getTime();
        });
    }

    /* ── FCM: web token registration + foreground listener — global (every handyman page) ──
     * Previously scoped to chat.php's Alpine component (only ran while that page was open),
     * so the popup never fired while the handyman was on another tab. Registered once here
     * (data-turbo-eval="false" keeps this block from re-running on Turbo soft navigations)
     * so it's active for the whole panel session. When the chat page IS open,
     * getHandymanChatComponent()/handymanChatPost() (defined in chat.php) are used if present
     * to auto-append the message inline instead of just popping a browser notification.
     */
    function initHandymanFcm() {
        if (window.__handymanFcmInitialized) return;
        window.__handymanFcmInitialized = true;

        var firebaseConfig = {
            apiKey: <?= json_encode($firebase_setting['apiKey'] ?? '') ?>,
            authDomain: <?= json_encode($firebase_setting['authDomain'] ?? '') ?>,
            projectId: <?= json_encode($firebase_setting['projectId'] ?? '') ?>,
            storageBucket: <?= json_encode($firebase_setting['storageBucket'] ?? '') ?>,
            messagingSenderId: <?= json_encode($firebase_setting['messagingSenderId'] ?? '') ?>,
            appId: <?= json_encode($firebase_setting['appId'] ?? '') ?>,
            measurementId: <?= json_encode($firebase_setting['measurementId'] ?? '') ?>
        };
        var vapidKey = <?= json_encode($firebase_setting['vapidKey'] ?? '') ?>;
        var serviceWorkerUrl = <?= json_encode(base_url('firebase-messaging-sw.js')) ?>;
        var handymanFcmUserId = <?= (int) $handymanId ?>;

        if (typeof firebase === 'undefined' || !firebaseConfig.apiKey) return;

        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }

        if (!firebase.messaging.isSupported()) {
            console.log("Firebase Messaging is not supported in this browser.");
            return;
        }

        var fcm = firebase.messaging();

        function saveHandymanWebToken(token) {
            var body = new URLSearchParams({
                token: token,
                language_code: <?= json_encode(get_current_language()) ?>,
                [csrfName]: csrfHash
            });
            fetch(baseUrl.replace(/\/$/, '') + '/handyman/chat/save_web_token', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).then(function (r) { return r.json(); }).then(function (json) {
                if (json && json.csrfName) csrfName = json.csrfName;
                if (json && json.csrfHash) csrfHash = json.csrfHash;
            }).catch(function () { });
        }

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(serviceWorkerUrl)
                .then(function (registration) {
                    return fcm.getToken({ vapidKey: vapidKey, serviceWorkerRegistration: registration });
                })
                .then(saveHandymanWebToken)
                .catch(function () {
                    fcm.getToken({ vapidKey: vapidKey }).then(saveHandymanWebToken).catch(e => console.warn("FCM Token fallback error:", e));
                });
        } else {
            fcm.getToken({ vapidKey: vapidKey }).then(saveHandymanWebToken);
        }

        function requestBrowserNotificationPermissionIfNeeded() {
            if (!('Notification' in window)) return Promise.resolve('unsupported');
            if (Notification.permission === 'granted') return Promise.resolve('granted');
            if (Notification.permission === 'denied') return Promise.resolve('denied');
            return Notification.requestPermission().catch(function () { return 'default'; });
        }

        function getHandymanNotifDropdownComponent() {
            var el = document.getElementById('handyman-notification-dropdown');
            return el ? Alpine.$data(el) : null;
        }

        fcm.onMessage(function (payload) {
            var data = (payload && payload.data) ? payload.data : {};
            var component = (typeof getHandymanChatComponent === 'function') ? getHandymanChatComponent() : null;
            var receiverIdMatch = false;
            var bookingIdMatch = false;

            // Chat messages ('new_message'/'chat') aren't stored as bell notifications
            // (see Handyman\Chat::notifyChatRecipients — excluded from DB storage), so only
            // refresh the bell dropdown for genuine notification pushes (booking_assigned,
            // payment_successful, etc.), not chat.
            if (data.type !== 'chat' && data.type !== 'new_message') {
                var notifDropdown = getHandymanNotifDropdownComponent();
                if (notifDropdown) notifDropdown.load(true);
            }

            try {
                receiverIdMatch = String(data.receiver_id || '') === String(handymanFcmUserId);
                bookingIdMatch = !!component && component.currentOrderId !== null && String(data.booking_id || '') === String(component.currentOrderId);

                if (receiverIdMatch && bookingIdMatch) {
                    var msg = null;
                    if (data.chat_message) {
                        try {
                            var parsed = JSON.parse(data.chat_message);
                            msg = {
                                sender_id: parsed.sender_id,
                                created_at: parsed.created_at,
                                message: parsed.message,
                                file: Array.isArray(parsed.file) ? parsed.file : [],
                                sender_name: parsed.username || parsed.sender_name || '',
                                profile_image: parsed.profile_image || parsed.image || ''
                            };
                        } catch (_e) {
                            msg = null;
                        }
                    }
                    if (!msg) {
                        msg = {
                            sender_id: data.sender_id,
                            created_at: data.created_at || new Date().toISOString(),
                            message: data.message || (payload.notification ? payload.notification.body : ''),
                            file: [],
                            sender_name: '',
                            profile_image: ''
                        };
                    }

                    if (parseInt(msg.sender_id) !== handymanFcmUserId) {
                        component.appendMessage(msg);

                        if (typeof handymanChatPost === 'function') {
                            handymanChatPost(baseUrl + '/handyman/chat/mark_as_read', {
                                sender_id: component.currentReceiverId,
                                order_id: component.currentOrderId
                            }).then(function () {
                                if (typeof refreshHandymanChatSidebarBadge === 'function') refreshHandymanChatSidebarBadge();
                            });
                        }
                    }
                } else if (receiverIdMatch) {
                    if (component && typeof component.loadCustomerList === 'function') component.loadCustomerList(null, true);
                    if (typeof bumpHandymanChatSidebarBadgeOptimistic === 'function') bumpHandymanChatSidebarBadgeOptimistic();
                    if (typeof refreshHandymanChatSidebarBadge === 'function') refreshHandymanChatSidebarBadge();
                }
            } catch (err) {
                console.error('FCM onMessage handler error (Handyman panel):', err, payload);
            }

            if (payload.notification || data.message) {
                var shouldShowNotification = !(receiverIdMatch && bookingIdMatch);

                if (shouldShowNotification) {
                    var title = (payload.notification && payload.notification.title) ? payload.notification.title : (data.title || '');
                    var body = (payload.notification && payload.notification.body) ? payload.notification.body : (data.message || '');
                    requestBrowserNotificationPermissionIfNeeded().then(function (status) {
                        if (status === 'granted' && title) {
                            try { new Notification(title, { body: body }); } catch (_e) { }
                        }
                    });
                }
            }
        });
    }

    // navbar.php renders (and this inline script runs) BEFORE scripts.php's Firebase SDK
    // <script src> tags later in <body> — so `firebase` isn't defined yet at parse time here.
    // Those SDK scripts aren't async/defer, so they've finished executing by 'load'.
    if (document.readyState === 'complete') {
        initHandymanFcm();
    } else {
        window.addEventListener('load', initHandymanFcm);
    }
</script>