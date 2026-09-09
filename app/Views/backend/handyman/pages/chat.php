<?= $this->extend('backend/handyman/layouts/main') ?>

<?= $this->section('page_styles') ?>
<style>
    [x-cloak] { display: none !important; }

    /* Chat bubble sizing/timestamp — no BS5 utility covers arbitrary min-width or an 11px font size */
    .handyman-chat-bubble {
        min-width: 160px;
    }

    .handyman-chat-bubble-time {
        font-size: 11px;
    }

    /* Fixed height layout for chat panel to prevent vertical stretching and giant footer gaps */
    .handyman-chat-card {
        height: 75vh;
        min-height: 480px;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div id="handyman-chat-app" x-data="handymanChatPage()" x-init="init()">
    <div class="section-body">
        <!-- Notification permission notice — chat auto-appends incoming messages via browser notifications while this page is open -->
        <div id="notification_permission_notice" class="alert alert-warning alert-has-icon mb-3" x-show="notice.visible" x-cloak>
            <div class="alert-icon"><i class="fa-solid fa-circle-exclamation me-2"></i></div>
            <div class="alert-body">
                <div class="alert-title"><?= labels('note', 'Note') ?></div>
                <div id="notification_permission_status" x-text="notice.text"></div>
            </div>
        </div>
        <div class="row">
            <!-- Chat List Sidebar -->
            <div class="col-12 col-lg-4 mb-4 mb-lg-0">
                <div class="card shadow-sm border-0 handyman-chat-card">
                    <div class="card-header bg-white border-bottom py-3 d-flex flex-column align-items-start">
                        <h5 class="card-title mb-3 font-weight-bold"><?= labels('messages') ?></h5>
                        <div class="d-flex align-items-center bg-light rounded-pill border w-100 px-3 py-1 shadow-sm">
                            <span class="input-group-append align-items-center me-2 flex-shrink-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="chat-search" class="form-control bg-transparent border-0 shadow-none p-1"
                                placeholder="<?= labels('search_conversations') ?>"
                                x-model="search" x-on:input.debounce.300ms="loadCustomerList()">
                        </div>
                    </div>
                    <div class="card-body p-0 overflow-hidden">
                        <div id="customer-list-loading" class="d-flex justify-content-center align-items-center p-4 mt-3" x-show.important="listLoading" x-cloak>
                            <i class="fas fa-spinner fa-spin text-primary fa-2x"></i>
                        </div>
                        <div id="customer-list" class="h-100 mh-100" x-show="!listLoading && customers.length" x-cloak>
                            <template x-for="c in customers" :key="c.order_id + '-' + c.id">
                                <div class="chat-customer-row d-flex align-items-center p-3 border-bottom bg-white" style="cursor:pointer;min-height:82px;"
                                    :class="{ 'bg-light border-left border-primary': currentReceiverId === c.id && currentOrderId === c.order_id }"
                                    @click="openConversation(c.id, c.order_id, c.username, c.profile_image, parseInt(c.is_lead || 0) === 1, c.booking_status)">
                                    <div class="position-relative flex-shrink-0 me-3" style="width:50px;height:50px;">
                                        <img x-show="!showAvatarFallback(c.profile_image, c._imgError)" :src="c.profile_image" class="rounded-circle" width="50" height="50"
                                            style="position:absolute;top:0;left:0;width:50px;height:50px;object-fit:cover;color:transparent;" :alt="c.username"
                                            @error="handleAvatarError($event, c)">
                                        <div
                                            class="rounded-circle justify-content-center align-items-center text-white font-weight-bold"
                                            :style="'position:absolute;top:0;left:0;width:50px;height:50px;font-size:20px;display:' + (showAvatarFallback(c.profile_image, c._imgError) ? 'flex' : 'none') + ';background:' + avatarColor(c.username)"
                                            x-text="avatarLetter(c.username)"></div>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="mb-0 text-truncate font-weight-bold">
                                                <span x-text="c.username"></span>
                                                <span class="text-muted font-weight-normal">(#<span x-text="c.order_id"></span>)</span>
                                            </h6>
                                            <small class="ms-2" :class="parseInt(c.unread_count || 0) > 0 ? 'text-primary font-weight-bold' : 'text-muted'"
                                                x-text="formatChatTime(c.last_message_time)"></small>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <p class="mb-0 text-muted text-truncate small flex-grow-1" x-text="c.last_message || ''"></p>
                                            <span class="badge bg-primary rounded-pill ms-2 unread-badge" x-show="parseInt(c.unread_count || 0) > 0"
                                                x-text="c.unread_count"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div id="customer-list-empty"
                            class="d-flex flex-column justify-content-center align-items-center text-center p-4 mt-5"
                            x-show.important="!listLoading && !customers.length" x-cloak>
                            <div class="mb-3 bg-light rounded-circle p-4 border shadow-sm d-inline-block">
                                <i class="fas fa-inbox text-muted fa-3x fa-fw"></i>
                            </div>
                            <h6 class="font-weight-bold text-dark mb-2">
                                <?= labels('no_chats_available', 'No Chats Available') ?>
                            </h6>
                            <p class="text-muted small mb-0">
                                <?= labels('please_come_back_later', 'Please come back later.') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat Window -->
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm d-flex flex-column border-0 handyman-chat-card">

                    <!-- Chat Header — hidden until a conversation is opened -->
                    <div id="chat-header"
                        class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between"
                        x-show.important="activeConversation" x-cloak>
                        <div class="d-flex align-items-center">
                            <div id="chat-header-avatar" class="me-3 position-relative" style="width:45px;height:45px;">
                                <img x-show="!showAvatarFallback(currentImg, currentImgError)" :src="currentImg" class="rounded-circle" width="45" height="45"
                                    style="position:absolute;top:0;left:0;width:45px;height:45px;object-fit:cover;color:transparent;" :alt="currentName"
                                    @error="handleAvatarError($event, null)">
                                <div
                                    class="rounded-circle justify-content-center align-items-center text-white font-weight-bold"
                                    :style="'position:absolute;top:0;left:0;width:45px;height:45px;font-size:18px;display:' + (showAvatarFallback(currentImg, currentImgError) ? 'flex' : 'none') + ';background:' + avatarColor(currentName)"
                                    x-text="avatarLetter(currentName)"></div>
                            </div>
                            <div>
                                <h5 id="chat-header-name" class="mb-0 ms-2 font-weight-bold" x-text="currentName"></h5>
                                <small id="chat-header-booking-id" class="ms-2 text-muted">
                                    <?= labels('booking_id', 'Booking ID') ?> #<span x-text="currentOrderId"></span>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Messages Area — hidden until a conversation is opened -->
                    <div id="chat-messages-area" class="card-body p-4 overflow-auto bg-light d-flex flex-column"
                        x-show.important="activeConversation" x-cloak x-ref="messagesArea">

                        <div x-show="messagesLoading" class="text-center py-3">
                            <i class="fas fa-spinner fa-spin text-primary fa-2x"></i>
                        </div>

                        <template x-for="(msg, idx) in messages" :key="msg._key">
                            <div>
                                <div x-show="msg._dateHeading" style="text-align:center;color:#aaa;font-size:12px;margin:10px 0;" x-text="msg._dateHeading"></div>
                                <div class="d-flex mb-4 w-100" :class="msg._isOwn ? 'justify-content-end' : 'justify-content-start'">
                                    <div class="d-flex w-75" :class="msg._isOwn ? 'justify-content-end' : ''">
                                        <div class="flex-shrink-0 align-self-start" x-show="!msg._isOwn" style="width:30px;height:30px;position:relative;">
                                            <img x-show="!showAvatarFallback(msg.profile_image, msg._imgError)" :src="msg.profile_image" class="rounded-circle" width="30" height="30"
                                                style="position:absolute;top:0;left:0;width:30px;height:30px;object-fit:cover;color:transparent;" :alt="msg.sender_name"
                                                @error="handleAvatarError($event, msg)">
                                            <div
                                                class="rounded-circle justify-content-center align-items-center text-white font-weight-bold"
                                                :style="'position:absolute;top:0;left:0;width:30px;height:30px;font-size:12px;display:' + (showAvatarFallback(msg.profile_image, msg._imgError) ? 'flex' : 'none') + ';background:' + avatarColor(msg.sender_name)"
                                                x-text="avatarLetter(msg.sender_name)"></div>
                                        </div>
                                        <div class="d-flex flex-column" :class="msg._isOwn ? 'align-items-end' : 'align-items-start ms-2'">
                                            <div class="rounded p-2 shadow-sm d-flex flex-column handyman-chat-bubble"
                                                :class="msg._isOwn ? 'bg-primary bg-opacity-75 text-white me-2' : 'bg-white text-dark'">
                                                <template x-for="f in (Array.isArray(msg.file) ? msg.file : [])" :key="f.file">
                                                    <div>
                                                        <a x-show="isImageAttachment(f.file_type)" :href="f.file" target="_blank" rel="noopener">
                                                            <img :src="f.file" class="rounded mb-1" style="max-width:200px;max-height:200px;object-fit:cover;">
                                                        </a>
                                                        <a x-show="!isImageAttachment(f.file_type)" :href="f.file" :download="f.file_name" class="d-flex align-items-center mb-1"
                                                            :class="msg._isOwn ? 'text-white' : ''" style="color:inherit;">
                                                            <i class="fas fa-file-alt me-2"></i>
                                                            <span class="text-truncate" style="max-width:180px;" x-text="f.file_name"></span>
                                                        </a>
                                                    </div>
                                                </template>
                                                <div x-show="msg.message" x-text="msg.message"></div>
                                                <small class="text-end mt-1 lh-1 handyman-chat-bubble-time"
                                                    :class="msg._isOwn ? 'text-white-50' : 'text-muted'" x-text="msg._timeStr"></small>
                                            </div>
                                        </div>
                                        <div class="align-self-start" x-show="msg._isOwn" style="width:30px;height:30px;position:relative;">
                                            <img x-show="!showAvatarFallback(msg.profile_image, msg._imgError)" :src="msg.profile_image" class="rounded-circle" width="30" height="30"
                                                style="position:absolute;top:0;left:0;width:30px;height:30px;object-fit:cover;color:transparent;" :alt="msg.sender_name"
                                                @error="handleAvatarError($event, msg)">
                                            <div
                                                class="rounded-circle justify-content-center align-items-center text-white font-weight-bold"
                                                :style="'position:absolute;top:0;left:0;width:30px;height:30px;font-size:12px;display:' + (showAvatarFallback(msg.profile_image, msg._imgError) ? 'flex' : 'none') + ';background:' + avatarColor(msg.sender_name)"
                                                x-text="avatarLetter(msg.sender_name)"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="!messagesLoading && !messages.length && !currentBookingClosed && quickQuestions.length"
                            style="display:flex;flex-direction:column;align-items:flex-start;padding:20px 0;">
                            <span style="font-size:12px;color:#a2a2a2;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:12px;">
                                <?= labels('quick_questions', 'Quick Questions') ?>
                            </span>
                            <template x-for="q in quickQuestions" :key="q">
                                <a href="#" class="rounded p-2 mb-2 text-decoration-none d-block w-100" style="background-color: color-mix(in srgb, var(--primary-color) 20%, transparent); color: var(--primary-color);" @click.prevent="sendChatQuestion(q)" x-text="q"></a>
                            </template>
                        </div>
                    </div>

                    <!-- Chat Input Footer — hidden until a conversation is opened, and only shown when this handyman is the booking lead -->
                    <div id="chat-footer" class="card-footer bg-white border-top py-2 px-3"
                        x-show="activeConversation && currentIsLead && !currentBookingClosed" x-cloak>
                        <div id="chat-attachment-preview" class="d-flex flex-wrap gap-2 mb-2" x-show.important="selectedAttachments.length" x-cloak>
                            <template x-for="(item, idx) in selectedAttachments" :key="idx">
                                <div>
                                    <div x-show="item.kind === 'image'" class="position-relative d-inline-block">
                                        <img :src="item.previewUrl" style="width:56px;height:56px;object-fit:cover;border-radius:6px;">
                                        <button type="button" class="btn btn-sm btn-danger rounded-circle p-0 position-absolute d-flex align-items-center justify-content-center"
                                            style="top:-6px;right:-6px;width:18px;height:18px;font-size:10px;" @click="removeAttachment(idx)">&times;</button>
                                    </div>
                                    <div x-show="item.kind !== 'image'" class="d-inline-flex align-items-center bg-light border rounded px-2 py-1" style="font-size:12px;">
                                        <i class="fas fa-file-alt me-1"></i>
                                        <span class="text-truncate" style="max-width:100px;" x-text="item.file.name"></span>
                                        <button type="button" class="btn btn-sm text-danger p-0 ms-2" @click="removeAttachment(idx)">&times;</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="input-group align-items-center shadow-sm rounded p-1 bg-light border">
                            <?php
                            $bothAttachmentTypesEnabled = !empty($enable_chat_image_upload) && !empty($enable_chat_file_upload);
                            $anyAttachmentTypeEnabled = !empty($enable_chat_image_upload) || !empty($enable_chat_file_upload);
                            ?>
                            <div class="input-group-prepend d-flex">
                                <?php if ($anyAttachmentTypeEnabled): ?>
                                    <?php if ($bothAttachmentTypesEnabled): ?>
                                        <div class="dropup">
                                            <button type="button" id="chat-attachment-trigger"
                                                class="btn btn-link text-muted rounded-circle" data-bs-toggle="dropdown"
                                                aria-expanded="false" title="<?= labels('attachments', 'Attachments') ?>">
                                                <i class="fas fa-paperclip"></i>
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="chat-attachment-trigger">
                                                <li><a class="dropdown-item" href="#" @click.prevent="$refs.chatImageInput.click()">
                                                        <i class="fas fa-image me-2"></i><?= labels('image', 'Image') ?>
                                                    </a></li>
                                                <li><a class="dropdown-item" href="#" @click.prevent="$refs.chatFileInput.click()">
                                                        <i class="fas fa-file-alt me-2"></i><?= labels('file', 'File') ?>
                                                    </a></li>
                                            </ul>
                                        </div>
                                    <?php elseif (!empty($enable_chat_image_upload)): ?>
                                        <button type="button" class="btn btn-link text-muted rounded-circle"
                                            title="<?= labels('image', 'Image') ?>" @click.prevent="$refs.chatImageInput.click()">
                                            <i class="fas fa-image"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-link text-muted rounded-circle"
                                            title="<?= labels('file', 'File') ?>" @click.prevent="$refs.chatFileInput.click()">
                                            <i class="fas fa-paperclip"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($enable_chat_image_upload)): ?>
                                        <input type="file" id="chat-image-input" x-ref="chatImageInput" class="d-none" accept="image/*" multiple
                                            @change="onFilesSelected($event, 'image')">
                                    <?php endif; ?>
                                    <?php if (!empty($enable_chat_file_upload)): ?>
                                        <input type="file" id="chat-file-input" x-ref="chatFileInput" class="d-none" multiple
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.rtf,.txt,.zip,.rar,.7z,.tar,.gz,.csv,.json,.xml,.mp4,.mov,.avi,.mkv,.wmv,.flv,.webm,.m4v,.3gp"
                                            @change="onFilesSelected($event, 'file')">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <input type="text" id="message-input" class="form-control bg-light border-0 shadow-none px-2"
                                placeholder="<?= labels('type_your_message_here') ?>"
                                x-ref="messageInput" @keydown.enter.prevent="sendMessage()">
                            <div class="input-group-append">
                                <button id="send-btn" class="btn btn-primary rounded px-4 font-weight-bold" type="button"
                                    :disabled="sending" @click="sendMessage()">
                                    <template x-if="!sending">
                                        <span><i class="fas fa-paper-plane me-2"></i><?= labels('send') ?></span>
                                    </template>
                                    <template x-if="sending">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </template>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Read-only notice — shown instead of the footer when this handyman is not the current booking lead -->
                    <div id="chat-readonly-notice"
                        class="card-footer bg-white border-top py-2 px-3 text-center text-muted small"
                        x-show="activeConversation && !currentIsLead && !currentBookingClosed" x-cloak>
                        <i
                            class="fas fa-eye me-1"></i><?= labels('only_lead_handyman_can_chat', 'Only the lead handyman can chat with the customer for this booking') ?>
                    </div>

                    <!-- Booking-closed notice — shown instead of the footer once the booking is completed/cancelled -->
                    <div id="chat-closed-notice"
                        class="card-footer bg-white border-top py-2 px-3 text-center text-muted small"
                        x-show="activeConversation && currentBookingClosed" x-cloak>
                        <i
                            class="fas fa-lock me-1"></i><?= labels('cant_chat_booking_completed_or_cancelled', "Sorry, you can't send a message since this booking has been completed or cancelled.") ?>
                    </div>

                    <!-- Welcome State — shown by default -->
                    <div id="chat-welcome-state"
                        class="d-flex flex-column justify-content-center align-items-center h-100 p-5 text-center w-100 bg-white rounded"
                        x-show.important="!activeConversation">
                        <div
                            class="mb-4 d-flex justify-content-center align-items-center bg-light rounded-circle p-5 shadow-sm border">
                            <i class="fas fa-comments text-primary fa-5x"></i>
                        </div>
                        <h4 class="font-weight-bold text-dark mb-3"><?= labels('welcome_to', 'Welcome to') ?>
                            <?= esc($company_title) ?>
                        </h4>
                        <p class="text-muted font-weight-bold">
                            <?= labels('chat_welcome_card_subtitle', 'Pick a person from the left menu and start your conversation') ?>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
    /* ── Server-side config ────────────────────────────────────── */
    var currentUserId = <?= (int) $current_user_id ?>;
    var currentUserName = <?= json_encode($current_user_name ?? '') ?>;
    var currentUserImage = <?= json_encode($current_user_image ?? '') ?>;
    var maxChatAttachments = <?= (int) ($maxFilesOrImagesInOneMessage ?? 10) ?>;
    var maxChatAttachmentBytes = <?= (int) ($maxFileSizeInBytesCanBeSent ?? 20000000) ?>;
    var serverTzOffset = <?= json_encode($server_tz_offset ?? '+00:00') ?>;
    var notificationUnsupportedLabel = <?= json_encode(labels('browser_doesnt_support_notifications', 'This browser does not support desktop notifications')) ?>;
    var notificationDeniedLabel = <?= json_encode(labels('didnt_allow_notification_permission', "You didn't allow Notification Permission. To get live messages please allow notification permission")) ?>;
    var handymanChatQuickQuestions = <?= json_encode(array_map(function ($q) { return $q['question']; }, $booking_chat_questions ?? [])) ?>;
    var handymanChatTodayLabel = <?= json_encode(labels('today', 'Today')) ?>;
    var handymanChatYesterdayLabel = <?= json_encode(labels('yesterday', 'Yesterday')) ?>;
    var avatarColors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'];

    /* ── Minimal fetch/CSRF helpers, replacing $.ajax for this page ── */
    function handymanChatPost(url, data) {
        var body = new URLSearchParams(Object.assign({}, data, { [csrfName]: csrfHash }));
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (json && json.csrfName) csrfName = json.csrfName;
            if (json && json.csrfHash) csrfHash = json.csrfHash;
            return json;
        });
    }

    function handymanChatPostForm(url, formData) {
        formData.set(csrfName, csrfHash);
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (json && json.csrfName) csrfName = json.csrfName;
            if (json && json.csrfHash) csrfHash = json.csrfHash;
            return json;
        });
    }

    function getHandymanChatComponent() {
        var el = document.getElementById('handyman-chat-app');
        return el ? Alpine.$data(el) : null;
    }

    /* ── Alpine component backing the whole chat page ─────────────── */
    function handymanChatPage() {
        return {
            search: '',
            listLoading: false,
            customers: [],
            currentReceiverId: null,
            currentOrderId: null,
            currentIsLead: false,
            currentBookingClosed: false,
            currentName: '',
            currentImg: '',
            currentImgError: false,
            messages: [],
            messagesLoading: false,
            messageInput: '',
            selectedAttachments: [],
            sending: false,
            quickQuestions: handymanChatQuickQuestions,
            notice: { visible: false, text: '' },
            _msgKeyCounter: 0,

            get activeConversation() {
                return this.currentReceiverId !== null;
            },

            init() {
                this.requestNotificationPermissionOnChatOpen();
                this.loadCustomerList(() => this.openConversationFromUrl());
            },

            /* ── Notification permission notice ─────────── */
            refreshNotificationPermissionNotice() {
                if (!('Notification' in window)) {
                    this.notice = { visible: true, text: notificationUnsupportedLabel };
                    return;
                }
                if (Notification.permission === 'granted') {
                    this.notice.visible = false;
                    return;
                }
                this.notice = { visible: true, text: notificationDeniedLabel };
            },

            requestNotificationPermissionOnChatOpen() {
                if (!('Notification' in window)) {
                    this.refreshNotificationPermissionNotice();
                    return;
                }
                if (Notification.permission === 'default') {
                    Notification.requestPermission()
                        .then(() => this.refreshNotificationPermissionNotice())
                        .catch(() => this.refreshNotificationPermissionNotice());
                } else {
                    this.refreshNotificationPermissionNotice();
                }
            },

            /* ── Time helpers ─────────────────────────────── */
            parseServerDate(dateStr) {
                if (!dateStr) return null;
                var iso = dateStr.replace(' ', 'T');
                if (!/[Zz]|[+-]\d{2}:?\d{2}$/.test(iso)) iso += serverTzOffset;
                var d = new Date(iso);
                return isNaN(d) ? null : d;
            },

            formatChatTime(dateStr) {
                var d = this.parseServerDate(dateStr);
                if (!d) return '';
                var now = new Date();
                var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                var yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
                var msgDay = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                if (msgDay.getTime() === today.getTime()) {
                    var h = d.getHours(), m = d.getMinutes(), ap = h >= 12 ? 'PM' : 'AM';
                    h = h % 12 || 12;
                    return h + ':' + (m < 10 ? '0' : '') + m + ' ' + ap;
                }
                if (msgDay.getTime() === yesterday.getTime()) return 'Yesterday';
                var dayDiff = Math.floor((today - msgDay) / 86400000);
                if (dayDiff < 7) return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][d.getDay()];
                return (d.getMonth() + 1) + '/' + d.getDate();
            },

            formatMessageTime(dateStr) {
                var d = this.parseServerDate(dateStr);
                if (!d) return '';
                var h = d.getHours(), m = d.getMinutes(), ap = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return h + ':' + (m < 10 ? '0' : '') + m + ' ' + ap;
            },

            messageDateHeading(dateStr) {
                var d = this.parseServerDate(dateStr) || new Date();
                var now = new Date();
                var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                var yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
                var msgDay = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                if (msgDay.getTime() === today.getTime()) return handymanChatTodayLabel;
                if (msgDay.getTime() === yesterday.getTime()) return handymanChatYesterdayLabel;
                return d.toLocaleDateString();
            },

            /* ── Avatar helpers ────────────────────────────── */
            avatarColor(name) {
                if (!name) return avatarColors[0];
                var hash = 0;
                for (var i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
                return avatarColors[Math.abs(hash) % avatarColors.length];
            },

            avatarLetter(name) {
                return name ? name.charAt(0).toUpperCase() : '?';
            },

            isValidAvatar(imgUrl) {
                return !!imgUrl && imgUrl !== '' && imgUrl.indexOf('default.png') === -1;
            },

            /* Retry once w/ cache-buster before falling back to avatar —
             * chat history loads many <img> at once, browser per-host
             * connection limits can abort a valid image on first try.
             * `source` is the reactive row/message object backing this avatar
             * (or null for the header, which uses currentImgError instead) —
             * error state must live in Alpine-tracked data, not a raw DOM
             * write, since the fallback div's :style is recomputed on every
             * reactive tick and would otherwise silently undo a manual write. */
            handleAvatarError(event, source) {
                var img = event.target;
                if (!img.dataset.avatarRetried) {
                    img.dataset.avatarRetried = '1';
                    var src = img.src.split('?')[0];
                    img.src = src + '?retry=' + Math.random().toString(36).slice(2);
                    return;
                }
                if (source) {
                    source._imgError = true;
                } else {
                    this.currentImgError = true;
                }
            },

            showAvatarFallback(imgUrl, errored) {
                return !this.isValidAvatar(imgUrl) || !!errored;
            },

            isImageAttachment(fileType) {
                return !!fileType && fileType.toLowerCase().indexOf('image/') === 0;
            },

            /* ── Sidebar customer list ────────────────────── */
            // `silent` skips the listLoading spinner flash — used for background refreshes
            // (e.g. an FCM push for a different conversation) where the list just needs its
            // badge/preview data updated in place, not a visible reload of the whole sidebar.
            loadCustomerList(onLoaded, silent) {
                if (!silent) this.listLoading = true;
                handymanChatPost(baseUrl + '/handyman/chat/get_customer_list', { search: this.search || '' })
                    .then((data) => {
                        this.listLoading = false;
                        this.customers = (Array.isArray(data) ? data : []).map((c) => Object.assign({ _imgError: false }, c));
                        if (typeof onLoaded === 'function') onLoaded();
                    })
                    .catch(() => {
                        this.listLoading = false;
                        if (!silent) this.customers = [];
                    });
            },

            openConversationFromUrl() {
                var params = new URLSearchParams(window.location.search);
                var orderId = params.get('order_id');
                var customerId = params.get('customer_id');
                if (!orderId || !customerId) return;

                var match = this.customers.find((c) => String(c.id) === String(customerId) && String(c.order_id) === String(orderId));
                if (match) {
                    this.openConversation(match.id, match.order_id, match.username, match.profile_image, parseInt(match.is_lead || 0) === 1, match.booking_status);
                    return;
                }
                handymanChatPost(baseUrl + '/handyman/chat/get_booking_customer_details', { order_id: orderId })
                    .then((response) => {
                        if (!response.error && response.data) {
                            var booking = response.data;
                            this.openConversation(customerId, orderId, booking.username, booking.profile_image, booking.is_lead, booking.booking_status);
                        }
                    });
            },

            /* ── Open a conversation ──────────────────────── */
            openConversation(customerId, orderId, name, imgUrl, isLead, bookingStatus) {
                this.currentReceiverId = customerId;
                this.currentOrderId = orderId;
                this.currentIsLead = isLead;
                this.currentName = name;
                this.currentImg = imgUrl;
                this.currentImgError = false;
                this.selectedAttachments = [];

                var isClosed = (bookingStatus === 'completed' || bookingStatus === 'cancelled');
                this.currentBookingClosed = isClosed;
                this.messages = [];
                this.messagesLoading = true;

                handymanChatPost(baseUrl + '/handyman/chat/mark_as_read', { sender_id: customerId, order_id: orderId })
                    .then(() => {
                        var row = this.customers.find((c) => String(c.id) === String(customerId) && String(c.order_id) === String(orderId));
                        if (row) row.unread_count = 0;
                        if (typeof refreshHandymanChatSidebarBadge === 'function') refreshHandymanChatSidebarBadge();
                    });

                handymanChatPost(baseUrl + '/handyman/chat/booking_chat_list', { receiver_id: customerId, order_id: orderId })
                    .then((data) => {
                        this.messagesLoading = false;
                        this.messages = this.buildMessages((data && data.rows) || []);
                        this.scrollToBottom();
                    })
                    .catch(() => {
                        this.messagesLoading = false;
                        this.messages = [];
                    });
            },

            /* ── Message list build/append ────────────────── */
            buildMessages(rows) {
                var lastDate = null;
                var built = [];
                rows.forEach((msg) => {
                    if (!msg.sender_id) return;
                    var msgDate = msg.created_at ? msg.created_at.substring(0, 10) : '';
                    var heading = false;
                    if (msgDate && msgDate !== lastDate) {
                        heading = this.messageDateHeading(msg.created_at);
                        lastDate = msgDate;
                    }
                    built.push(this.decorateMessage(msg, heading));
                });
                return built;
            },

            decorateMessage(msg, dateHeading) {
                return Object.assign({}, msg, {
                    _key: 'm' + (this._msgKeyCounter++),
                    _isOwn: parseInt(msg.sender_id) === currentUserId,
                    _timeStr: this.formatMessageTime(msg.created_at),
                    _dateHeading: dateHeading || false,
                    _imgError: false
                });
            },

            appendMessage(msg) {
                var msgDate = msg.created_at ? String(msg.created_at).substring(0, 10) : '';
                var lastMsg = this.messages.length ? this.messages[this.messages.length - 1] : null;
                var lastDate = lastMsg && lastMsg.created_at ? String(lastMsg.created_at).substring(0, 10) : null;
                var heading = (msgDate && msgDate !== lastDate) ? this.messageDateHeading(msg.created_at) : false;
                this.messages.push(this.decorateMessage(msg, heading));
                this.scrollToBottom();
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    var el = this.$refs.messagesArea;
                    if (el) requestAnimationFrame(() => { el.scrollTop = el.scrollHeight; });
                });
            },

            /* ── Quick question bubbles ──────────────────── */
            sendChatQuestion(text) {
                if (!text || !this.currentReceiverId || !this.currentOrderId) return;
                this.$refs.messageInput.value = text;
                this.sendMessage();
            },

            /* ── Attachment selection & preview ───────────── */
            onFilesSelected(event, kind) {
                var fileList = event.target.files;
                for (var i = 0; i < fileList.length; i++) {
                    if (this.selectedAttachments.length >= maxChatAttachments) {
                        showToastMessage('<?= labels('note_max_file_or_image_allowed_in_one_message', 'Note: Maximum File or image allowed in one message') ?> ' + maxChatAttachments, 'error');
                        break;
                    }
                    if (fileList[i].size > maxChatAttachmentBytes) {
                        showToastMessage('<?= labels('file_size_exceeds_the_maximum_limit_of', 'File size exceeds the maximum limit of') ?> ' + (maxChatAttachmentBytes / (1024 * 1024)).toFixed(0) + 'MB', 'error');
                        continue;
                    }
                    var file = fileList[i];
                    this.selectedAttachments.push({ file: file, kind: kind, previewUrl: kind === 'image' ? URL.createObjectURL(file) : null });
                }
                event.target.value = '';
            },

            removeAttachment(idx) {
                this.selectedAttachments.splice(idx, 1);
            },

            /* ── Send message ─────────────────────────────── */
            sendMessage() {
                var message = this.$refs.messageInput.value.trim();
                if ((!message && !this.selectedAttachments.length) || !this.currentReceiverId || !this.currentOrderId || this.sending) return;

                this.sending = true;
                var fd = new FormData();
                fd.append('message', message);
                fd.append('receiver_id', this.currentReceiverId);
                fd.append('order_id', this.currentOrderId);
                this.selectedAttachments.forEach((item) => fd.append('attachment[]', item.file));

                handymanChatPostForm(baseUrl + '/handyman/chat/store_chat', fd)
                    .then((response) => {
                        if (response.error) {
                            showToastMessage(response.message, 'error');
                            return;
                        }
                        this.$refs.messageInput.value = '';
                        this.selectedAttachments = [];
                        this.appendMessage(Object.assign({}, response.data, { sender_name: currentUserName }));
                        this.updateSidebarPreview(this.currentReceiverId, this.currentOrderId, message || '<?= labels('attachments', 'Attachments') ?>');
                    })
                    .catch(() => {
                        showToastMessage('<?= labels(SOMETHING_WENT_WRONG, 'Something Went Wrong') ?>', 'error');
                    })
                    .finally(() => { this.sending = false; });
            },

            updateSidebarPreview(customerId, orderId, message) {
                var row = this.customers.find((c) => String(c.id) === String(customerId) && String(c.order_id) === String(orderId));
                if (row) {
                    row.last_message = message;
                    row.last_message_time = new Date().toISOString();
                    
                    // Move the row to the top of the list
                    this.customers = this.customers.filter((c) => c !== row);
                    this.customers.unshift(row);
                } else {
                    // Prepend a new customer row using Alpine's reactivity
                    var newRow = {
                        id: customerId,
                        order_id: orderId,
                        username: this.currentName,
                        profile_image: this.currentImg,
                        _imgError: this.currentImgError,
                        is_lead: this.currentIsLead ? 1 : 0,
                        booking_status: this.currentBookingClosed ? 'completed' : 'pending',
                        last_message: message,
                        last_message_time: new Date().toISOString(),
                        unread_count: 0
                    };
                    this.customers.unshift(newRow);
                }
            }
        };
    }
</script>
<?= $this->endSection() ?>
