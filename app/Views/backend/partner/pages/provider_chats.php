<!-- Main Content -->
<style>
    .msg {
        position: relative;
    }

    .msg .unread-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 10px;
        background: #dc3545;
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        line-height: 1;
        margin-left: auto;
        flex-shrink: 0;
    }

    .msg .msg-detail {
        flex: 1;
        min-width: 0;
    }

    .msg .unread-badge.hidden {
        display: none;
    }

    /* Other-party message time: normal-flow row under the bubble (never
       overlaps attachments), right-aligned to the bubble's own width. A
       min-width on the bubble keeps short messages (e.g. "test") wide
       enough that the time never has to spill past the bubble's edge. */
    .chat-msg-bubble-wrap {
        display: flex;
        flex-direction: column;
        max-width: 100%;
    }

    .chat-msg-content .chat-msg-text {
        min-width: 110px;
    }

    /* Give the bubble breathing room from the avatar column so the
       bottom-right time never collides with it on narrow bubbles. */
    .chat-msg:not(.owner) .chat-msg-content {
        margin-left: 8px;
    }

    /* Base .chat-msg-img uses top:-27px, which floats the avatar up near
       the top of the row instead of level with the bottom-aligned time.
       Pin it to the bottom of the profile box instead, compensating for
       .chat-msg-profile's margin-bottom:-20px (which lets the box's own
       border-edge sit 20px below the row's visible bottom), so avatar and
       timestamp end up on the same line. */
    .chat-msg:not(.owner) .chat-msg-img,
    .chat-msg:not(.owner) .chat-msg-img-placeholder {
        top: auto;
        bottom: 20px;
    }

    .chat-msg-date-bubble {
        /* Reset the base .chat-msg-date absolute-offset positioning — this
           variant lives in normal flow inside .chat-msg-bubble-wrap. */
        position: static;
        right: auto;
        bottom: auto;
        align-self: flex-end;
        text-align: right;
        margin-top: 2px;
        white-space: nowrap;
    }

    /* Profile row below bubble */
    .chat-msg {
        flex-direction: column !important;
        align-items: flex-start !important; /* shrink-wrap the non-owner messages */
    }
    .chat-msg.owner {
        align-items: flex-end !important; /* shrink-wrap the owner messages */
    }
    .chat-msg-content {
        margin-right: 0 !important;
        margin-left: 0 !important;
        width: fit-content;
    }
    .chat-msg:not(.owner) .chat-msg-content {
        align-items: flex-start;
    }
    .chat-msg-profile {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        width: 100% !important;
        margin-top: 5px !important;
        margin-bottom: 0 !important;
        position: static !important;
    }
    .chat-msg-img, .chat-msg-img-placeholder {
        position: static !important;
        width: 20px !important;
        height: 20px !important;
        margin: 0 !important;
        margin-right: 8px !important;
        font-size: 10px !important;
        line-height: 20px !important;
    }
    .chat-msg-date {
        position: static !important;
        margin-left: auto !important;
        font-size: 11px !important;
        color: #999 !important;
    }

    /* Sticky "lead handyman" card shown inside the chat bubbles container,
       just below the customer header, above the message list. */
    .lead-handyman-card {
        position: sticky;
        top: 0;
        z-index: 5;
        display: flex;
        align-items: center;
        background: #cfe2ff;
        border-radius: 8px;
        padding: 10px 14px;
        margin: 12px 16px;
    }

    .lead-handyman-card .msg-profile,
    .lead-handyman-card .msg-profile-placeholder {
        width: 36px !important;
        height: 36px !important;
        font-size: 14px !important;
        margin-left: 0 !important;
        margin-right: 12px !important;
    }

    .lead-handyman-info {
        min-width: 0;
    }

    .lead-handyman-username {
        font-weight: 600;
        font-size: 14px;
        color: #181818;
    }

    .lead-handyman-subtitle {
        font-size: 12px;
        color: #5f6b7a;
    }
</style>
<?php
// Detect RTL for proper UI element positioning
$session = \Config\Services::session();
$is_rtl = $session->get('is_rtl');
$language = $session->get('language');
$default_language = fetch_details('languages', ['is_default' => '1']);

// Only check default language's RTL status if no language is set in session
// Otherwise, use the explicit is_rtl value from session
if (empty($language) && !isset($is_rtl)) {
    $is_rtl = $default_language[0]['is_rtl'];
} elseif ($is_rtl === null) {
    // Fallback if is_rtl is not set but language is
    $is_rtl = 0;
}

// Convert to integer value for consistency
$is_rtl = (int) $is_rtl;

$current_url = current_url();
$url = strpos($current_url, "provider-booking-chats");
$requestUri = $_SERVER['REQUEST_URI'];
$segments = explode('/', $requestUri);
$lastSegment = end($segments);
if (is_numeric($lastSegment)) {
    $bookingId = intval($lastSegment);
}

// Function to validate image URLs more thoroughly
function isValidImageUrl($url)
{
    if (empty($url))
        return false;

    // Check for default.png images - treat them as invalid
    if (strpos($url, 'default.png') !== false) {
        return false;
    }

    // Check for common broken URL patterns
    $brokenPatterns = [
        '/^https?:\/\/$/',  // Just protocol
        '/^https?:\/\/[^\/]*$/',  // Protocol with domain but no path
        '/^https?:\/\/[^\/]*\/$/',  // Protocol with domain and trailing slash only
        '/^https?:\/\/[^\/]*\/[^\/]*$/',  // Protocol with domain and single path segment
        '/^https?:\/\/[^\/]*\/[^\/]*\/$/',  // Protocol with domain and single path segment with trailing slash
    ];

    foreach ($brokenPatterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return false;
        }
    }

    // Check if URL has proper structure
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
        return false;
    }

    // Check for common image file extensions
    $path = $parsed['path'] ?? '';
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    // If no extension, it might still be valid (some APIs don't use extensions)
    if (empty($extension)) {
        return true;
    }

    return in_array($extension, $imageExtensions);
}

/**
 * Chat attachments (images/files) are controlled by Admin settings.
 * We read them here so Provider chat shows only the allowed upload options.
 *
 * Admin path:
 * - Settings -> General Settings -> Chat Settings
 * Toggles:
 * - enable_chat_image_upload
 * - enable_chat_file_upload
 */
$general_settings = get_settings('general_settings', true);
$enable_chat_image_upload = !empty($general_settings['enable_chat_image_upload']) ? (int) $general_settings['enable_chat_image_upload'] : 0;
$enable_chat_file_upload = !empty($general_settings['enable_chat_file_upload']) ? (int) $general_settings['enable_chat_file_upload'] : 0;
?>
<div class="main-content">
    <section class="section" id="pill-about_us" role="tabpanel">
        <div class="section-header mt-2">
            <h1> <?= labels('chat', "Chat") ?>
                <span class="breadcrumb-item p-3 pt-2 text-primary">
                </span>
            </h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"></i> <?= labels('chat', "Chat") ?></div>
            </div>
        </div>
        <div id="notification_div" class="alert alert-warning alert-has-icon">
            <div class="alert-icon"><i class="fa-solid fa-circle-exclamation mr-2"></i></div>
            <div class="alert-body">
                <div class="alert-title"><?= labels('note', 'Note') ?></div>
                <div id="status" class=""></div>
            </div>
        </div>
        <div class="card" style="border:0!important;border-radius:0!important">
            <div class="card-body" style="padding: 0!important;">
                <div class="chat-app">
                    <button id="toggleConversationAreaBtn"><?= labels('chat_list', "Chat List") ?></button>

                    <div class="wrapper">
                        <div class="conversation-area" id="">
                            <div class="customer_list_heading"><?= labels('customer_list', 'Customer List') ?></div>
                            <div id="customer" class="tabcontent">
                                <div class="search-bar">
                                    <input type="text" id="customer-search"
                                        placeholder="<?= labels('search_customer', 'Search customer') ?>..." />
                                </div>
                                <hr class="mb-0">
                            </div>
                            <div id="customer" class="">
                                <div id="customer-list">
                                    <?php foreach ($customers as $user): ?>
                                        <div class="msg"
                                            onclick="setallMessage(<?= $user['id'] ?>, this, 'customer','<?= $user['order_id'] ?>')"
                                            data-customer-id="<?= $user['id'] ?>" data-order-id="<?= $user['order_id'] ?>">
                                            <?php
                                            // Check if profile image exists and is not empty
                                            $profileImage = $user['profile_image'] ?? '';
                                            $username = $user['username'] ?? 'User';
                                            $unreadCount = (int) ($user['unread_count'] ?? 0);

                                            if (empty($profileImage) || $profileImage === 'null' || $profileImage === 'undefined' || !filter_var($profileImage, FILTER_VALIDATE_URL) || !isValidImageUrl($profileImage)) {
                                                // Create placeholder with first letter of username
                                                $firstLetter = strtoupper(substr($username, 0, 1));
                                                $colorClass = 'color-' . ((ord($firstLetter) % 8) + 1);
                                                echo '<div class="msg-profile-placeholder ' . $colorClass . '">' . $firstLetter . '</div>';
                                            } else {
                                                // Use the profile image with error handling
                                                $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
                                                echo '<img class="msg-profile" src="' . htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8') . '" alt="' . $safeUsername . '" data-username="' . $safeUsername . '" onerror="handleImageError(this, \'' . $safeUsername . '\', \'msg-profile-placeholder\')" />';
                                            }
                                            ?>
                                            <div class="msg-detail">
                                                <div class="msg-username"><?= $user['username']; ?></div>
                                                <div class="featured_tag">
                                                    <?php
                                                    if (strpos($user['order_id'], "enquire") !== false) { ?>
                                                        <div class="featured_lable"><?= labels('enquiry', 'Enquiry') ?> ::
                                                        </div>
                                                    <?php } else { ?>
                                                        <div class="featured_lable"><?= labels('booking_no', 'Booking No') ?>
                                                            ::<?= $user['order_id'] ?></div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <span
                                                class="unread-badge<?= $unreadCount > 0 ? '' : ' hidden' ?>"><?= $unreadCount ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="overlay"></div>
                        </div>
                        <div class="chat-area myscroll">
                            <div class="chat_header d-none" style="padding:16px">
                                <img alt="" id="receiver_user_profile" class="img-circle medium-image" src="">
                                <div>
                                    <b id="receiver_username"> </b>
                                    <br>
                                    <b id="receiver_booking_id"></b>
                                </div>
                                <div class="ml-auto">
                                    <div class="dropdown">
                                        <button class="btn btn-secondary dropdown-toggle" type="button"
                                            id="chatActionsDropdown" data-toggle="dropdown" aria-haspopup="true"
                                            aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu <?= ($is_rtl == 1) ? 'dropdown-menu-left' : 'dropdown-menu-right' ?>"
                                            aria-labelledby="chatActionsDropdown"
                                            style="<?= ($is_rtl == 1) ? 'max-width: 100%; white-space: normal; word-wrap: break-word;' : '' ?>">
                                            <a class="dropdown-item text-danger block-option" href="#"
                                                data-toggle="modal" data-target="#reportBlockModal"
                                                style="display:none; <?= ($is_rtl == 1) ? 'text-align: right;' : '' ?>">
                                                <i class="fas fa-ban"></i>
                                                <?= labels('report_and_block_user', 'Report & Block User') ?>
                                            </a>
                                            <a class="dropdown-item unblock-option" href="#" onclick="unblockUser();"
                                                style="display:none; <?= ($is_rtl == 1) ? 'text-align: right;' : '' ?>">
                                                <i class="fas fa-unlock"></i>
                                                <?= labels('unblock_user', 'Unblock User') ?>
                                            </a>
                                            <div class="dropdown-divider delete-chat-divider" style="display:none;">
                                            </div>
                                            <a class="dropdown-item text-danger delete-chat-option" href="#"
                                                onclick="deleteChat();"
                                                style="display:none; <?= ($is_rtl == 1) ? 'text-align: right;' : '' ?>">
                                                <i class="fas fa-trash"></i> <?= labels('delete_chat', 'Delete Chat') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="chat-area-main myscroll" id="chat-area-main">
                                <div id="lead_handyman_card" class="lead-handyman-card d-none"></div>
                                <div id="chat-messages-list" style="height: 100%;">
                                    <div class="welcome-card">
                                        <p>
                                            <img width="200" height="200"
                                                src="<?= base_url('public/uploads/site/black chat section img.svg') ?>"
                                                alt="Welcome Image">
                                        </p>
                                        <?php $data = $general_settings; ?>
                                        <h1 class="welcome-title"><?= labels('welcome_to', 'Welcome to ') ?>&nbsp;
                                            <?= get_company_title_with_fallback($data); ?>
                                        </h1>
                                        <h6 class="welcome-subtitle">
                                            <?= labels('chat_welcome_card_subtitle', 'Pick a person from the left menu and start your conversation') ?>
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <div id="filePreviewContainer"></div>
                            <div class="chat-area-footer1 d-none"></div>
                            <div class="chat-area-footer d-none" style="display: flex; align-items: center;">
                                <form action="<?= base_url('admin/store_chat') ?>" method="post"
                                    style="flex: 1; display: flex; align-items: center;" enctype="multipart/form-data">
                                    <?php if ($enable_chat_image_upload == 1): ?>
                                        <!-- Image upload trigger -->
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" class="feather feather-image" id="svgImageInput"
                                            style="margin-right: 5px; cursor: pointer;">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                            <circle cx="8.5" cy="8.5" r="1.5" />
                                            <path d="M21 15l-5-5L5 21" />
                                        </svg>
                                        <input id="imageInput" name="attachment[]" multiple type="file" accept="image/*"
                                            style="display: none; margin-right: 5px;" />
                                    <?php endif; ?>

                                    <?php if ($enable_chat_file_upload == 1): ?>
                                        <!-- File upload trigger (non-image files) -->
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" class="feather feather-paperclip" id="svgFileInput"
                                            style="margin-right: 5px; cursor: pointer;">
                                            <path
                                                d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.82l8.49-8.48" />
                                        </svg>
                                        <input id="fileInput" name="attachment[]" multiple type="file"
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.rtf,.txt,.zip,.rar,.7z,.tar,.gz,.csv,.json,.xml,.mp4,.mov,.avi,.mkv,.wmv,.flv,.webm,.m4v,.3gp"
                                            style="display: none; margin-right: 5px;" />
                                    <?php endif; ?>

                                    <textarea class="two" id="message" name="message"
                                        placeholder="Type something here..." style="flex: 1; margin-right: 5px;"
                                        rows="1">
                                    </textarea>
                                    <!-- <input type="text" class="one" id="message" name="message" placeholder="Type something here..." style="flex: 1; margin-right: 5px;" /> -->
                                    <input type="hidden" id="sender_id" name="sender_id" value="" />
                                    <input type="hidden" id="receiver_id" name="receiver_id" value="" />
                                    <input type="hidden" id="order_id" name="order_id" value="" />
                                    <input type="hidden" id="user_type_for_send_message"
                                        name="user_type_for_send_message" value="" />
                                    <button id="send_button" class="btn bg-primary text-white"
                                        onclick="OnsendMessage();" disabled>
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include "images_preview_cards.php"; ?>
</div>
</section>
<style>
    /* RTL support for dropdown menu - prevent overflow */
    <?php if ($is_rtl == 1): ?>
        body[dir="rtl"] .dropdown-menu-right,
        body[dir="rtl"] .dropdown-menu-left {
            max-width: 100%;
            white-space: normal;
            word-wrap: break-word;
        }

        body[dir="rtl"] .dropdown-item {
            text-align: right;
        }

        /* RTL support for file preview close button */
        body[dir="rtl"] .file-preview .close-btn {
            left: auto !important;
            right: -8px !important;
        }

        /* RTL support for file preview container - non-image files */
        body[dir="rtl"] .file-preview>div {
            flex-direction: row-reverse;
        }

    <?php endif; ?>
</style>
<div class="modal fade" id="imageModal" role="dialog" aria-labelledby="view-video" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel"><?= labels('images', 'Images') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="imageContainer" class="row"></div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
<script>
    /**
     * Generate a color class based on username for consistent placeholder colors
     * @param {string} username - The username to generate color for
     * @returns {string} - Color class name
     */
    function getColorClass(username) {
        if (!username) return 'color-1';
        const firstLetter = username.charAt(0).toUpperCase();
        const colorIndex = (firstLetter.charCodeAt(0) % 8) + 1;
        return 'color-' + colorIndex;
    }

    /**
     * Create a placeholder element for profile image
     * @param {string} username - The username to display
     * @param {string} className - CSS class for the placeholder
     * @returns {string} - HTML string for the placeholder
     */
    function createProfilePlaceholder(username, className = 'msg-profile-placeholder') {
        if (!username) return '';
        const firstLetter = username.charAt(0).toUpperCase();
        const colorClass = getColorClass(username);
        return `<div class="${className} ${colorClass}">${firstLetter}</div>`;
    }

    /**
     * Handle image error and replace with placeholder
     * Define in global scope to ensure it's available for inline onerror handlers
     * @param {HTMLElement} imgElement - The image element that failed to load
     * @param {string} username - The username for the placeholder
     * @param {string} placeholderClass - CSS class for the placeholder
     */
    window.handleImageError = function (imgElement, username, placeholderClass = 'msg-profile-placeholder') {
        if (!imgElement || !username) return;

        // Prevent multiple error handling calls
        if (imgElement.dataset.errorHandled === 'true') return;
        imgElement.dataset.errorHandled = 'true';

        // Create placeholder element
        const placeholder = createProfilePlaceholder(username, placeholderClass);

        // Replace the image with placeholder
        imgElement.outerHTML = placeholder;
    }

    /**
     * Enhanced function to validate image URLs on the client side
     * @param {string} url - The URL to validate
     * @returns {boolean} - Whether the URL is likely to be a valid image
     */
    function isValidImageUrlClient(url) {
        if (!url || url === '' || url === 'null' || url === 'undefined') return false;

        // Check for default.png images - treat them as invalid
        if (url.includes('default.png')) {
            return false;
        }

        // Check for common broken URL patterns
        const brokenPatterns = [
            /^https?:\/\/$/, // Just protocol
            /^https?:\/\/[^\/]*$/, // Protocol with domain but no path
            /^https?:\/\/[^\/]*\/$/, // Protocol with domain and trailing slash only
            /^https?:\/\/[^\/]*\/[^\/]*$/, // Protocol with domain and single path segment
            /^https?:\/\/[^\/]*\/[^\/]*\/$/, // Protocol with domain and single path segment with trailing slash
        ];

        for (const pattern of brokenPatterns) {
            if (pattern.test(url)) {
                return false;
            }
        }

        // Check if URL has proper structure
        try {
            const urlObj = new URL(url);
            if (!urlObj.protocol || !urlObj.hostname) {
                return false;
            }
        } catch (e) {
            return false;
        }

        return true;
    }

    $(document).ready(function () {
        $("#filePreviewContainer").hide();

        <?php if (!empty($auto_open_booking_id) && !empty($auto_open_user_id)): ?>
            // Auto-open chat for the booking navigated from bookings list
            var autoOpenEl = $('#customer-list .msg[data-customer-id="<?= (int) $auto_open_user_id ?>"][data-order-id="<?= esc($auto_open_booking_id) ?>"]').first();
            if (autoOpenEl.length) {
                setallMessage(<?= (int) $auto_open_user_id ?>, autoOpenEl[0], 'customer', '<?= esc($auto_open_booking_id) ?>');
            }
        <?php endif; ?>

        /**
         * Count selected attachments from both inputs (if enabled).
         * This is important because (when uploads are enabled) we allow sending
         * attachments even if the message text is empty.
         */
        function getSelectedAttachmentsCount() {
            const imageInputEl = document.getElementById('imageInput');
            const fileInputEl = document.getElementById('fileInput');
            const imageCount = (imageInputEl && imageInputEl.files) ? imageInputEl.files.length : 0;
            const fileCount = (fileInputEl && fileInputEl.files) ? fileInputEl.files.length : 0;
            return imageCount + fileCount;
        }

        $('#message').on('input', function () {
            var maxLength = <?= $maxCharactersInATextMessage ?>;
            var message = $(this).val().trim();
            var messageLength = message.length;
            var hasAttachments = getSelectedAttachmentsCount() > 0;

            if (messageLength > maxLength) {
                $(this).val(message.substring(0, maxLength));
                showToastMessage("<?= labels('maximum_length_of', 'Maximum length of') ?> " + <?= $maxCharactersInATextMessage ?> + " <?= labels('characters_exceeded_message_trimmed', 'characters exceeded. Message trimmed') ?>", "error");
            }
            // Allow sending if we have attachments selected, even if message is empty.
            if ((message === '' && !hasAttachments) || messageLength >= maxLength) {
                $('#send_button').prop('disabled', true);
            } else {
                $('#send_button').prop('disabled', false);
            }
        });
    });

    // Define OnsendMessage function in global scope to ensure it's available for onclick events
    window.OnsendMessage = function () {
        var message = $('#message').val();
        var receiver_id = $('#receiver_id').val();
        var order_id = $('#order_id').val();
        var user_type_for_send_message = $('#user_type_for_send_message').val();
        $('#send_button').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#send_button').prop('disabled', true);
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        var fd = new FormData();
        fd.append('message', message);
        fd.append('sender_id', <?= $current_user_id ?>);
        fd.append('receiver_id', receiver_id);
        fd.append('order_id', order_id);
        fd.append('user_type_for_send_message', user_type_for_send_message);
        // Attach files from both inputs (if enabled). Both are appended as `attachment[]`.
        var imageInputEl = document.getElementById('imageInput');
        var fileInputEl = document.getElementById('fileInput');
        if (imageInputEl && imageInputEl.files) {
            for (var i = 0; i < imageInputEl.files.length; i++) {
                fd.append('attachment[]', imageInputEl.files[i]);
            }
        }
        if (fileInputEl && fileInputEl.files) {
            for (var j = 0; j < fileInputEl.files.length; j++) {
                fd.append('attachment[]', fileInputEl.files[j]);
            }
        }
        $.ajax({
            xhr: function () {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function (evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = ((evt.loaded / evt.total) * 100);
                        $(".progress-bar").width(percentComplete + '%');
                        $(".progress-bar").html(percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            url: baseUrl + '/partner/store_booking_chat',
            enctype: 'multipart/form-data',
            type: "POST",
            dataType: 'json',
            data: fd,
            processData: false,
            contentType: false,
            async: true,
            cache: false,
            success: function (data) {
                if (data.error == true) {
                    showToastMessage(data.message, "error");
                }

                if (data.error == false) {
                    $('.welcome-card').remove();
                    $('#chat-messages-list').css('padding-top', '20px');
                    $('.delete-chat-option, .delete-chat-divider').show();
                    $('#message').val('');
                    $('#fileInput').val('');
                    $('#imageInput').val('');
                    $("#filePreviewContainer").html('');
                    $("#filePreviewContainer").hide();
                    setTimeout(function () {
                        $('#send_button').html('<i class="fas fa-paper-plane"></i>');
                        $('#send_button').prop('disabled', true);
                    }, 2000);
                    var message = data.message;
                    appendMessageToChatArea(data.data);

                    // Track Microsoft Clarity chat events
                    if (data.clarity_event_data) {
                        var eventData = data.clarity_event_data;
                        if (eventData.clarity_event === 'chat_message_sent' && typeof trackChatMessageSent === 'function') {
                            trackChatMessageSent(
                                eventData.message_id,
                                eventData.receiver_id,
                                eventData.booking_id,
                                eventData.message_type
                            );
                        }
                        if (eventData.chat_started && typeof trackChatStarted === 'function') {
                            trackChatStarted(
                                eventData.receiver_id,
                                eventData.booking_id
                            );
                        }
                    }
                }
                if (data.error == true) {
                    submitButton.text('Send');
                    alert('there is No Chat');
                }
            },
            error: function (response) {
                return showToastMessage(response.message, "error");
            }
        });
    }
    $('#message').keypress(function (event) {
        var maxLength = <?= $maxCharactersInATextMessage ?>;
        var message = $(this).val().trim();
        var messageLength = message.length;
        var imageInputEl = document.getElementById('imageInput');
        var fileInputEl = document.getElementById('fileInput');
        var hasAttachments =
            ((imageInputEl && imageInputEl.files) ? imageInputEl.files.length : 0) +
            ((fileInputEl && fileInputEl.files) ? fileInputEl.files.length : 0) > 0;

        if (event.which === 13 && !event.shiftKey) { // Enter key pressed without Shift
            event.preventDefault();

            // Allow sending attachments with empty text, but still respect max text length.
            if ((message === '' && !hasAttachments) || messageLength >= maxLength) {
                $('#send_button').prop('disabled', true);
                if (messageLength >= maxLength) {
                    showToastMessage("<?= labels('maximum_length_of', 'Maximum length of') ?> " + maxLength + " <?= labels('characters_exceeded_message_not_sent', 'characters exceeded. Message not sent') ?>", "error");
                }
            } else {
                $('#send_button').prop('disabled', false);
                OnsendMessage();
            }
        }
    });
    var lastDisplayedDate = null;

    // Function to scroll to bottom of chat area
    function scrollToBottom() {
        /**
         * IMPORTANT:
         * We must scroll the element that actually owns the scrollbar.
         * In this UI both `.chat-area` and `.chat-area-main` may have `myscroll`.
         * So we detect the first element that is truly scrollable and scroll that.
         *
         * Also: messages can include images/videos. Their height is not final
         * until media finishes loading. So we re-scroll once media loads.
         */
        var chatMain = document.getElementById('chat-area-main') || document.querySelector('.chat-area-main');
        if (!chatMain) return;

        function isScrollable(el) {
            return !!(el && el.scrollHeight > el.clientHeight + 5);
        }

        // Candidate list ordered from most specific to most general.
        // We keep it simple and safe. We do not assume which container scrolls.
        var candidates = [
            chatMain,
            chatMain.closest ? chatMain.closest('.chat-area') : null,
            chatMain.parentElement || null,
            document.querySelector('.chat-area-main.myscroll'),
            document.querySelector('.chat-area.myscroll')
        ];

        var scroller = null;
        for (var i = 0; i < candidates.length; i++) {
            if (isScrollable(candidates[i])) {
                scroller = candidates[i];
                break;
            }
        }
        if (!scroller) scroller = chatMain;

        function doScroll() {
            // Direct scroll is the most reliable here.
            scroller.scrollTop = scroller.scrollHeight;
        }

        // Run after layout. This avoids "scrolls to a weird spot first".
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(function () {
                doScroll();
                requestAnimationFrame(doScroll);
            });
        } else {
            doScroll();
        }

        // If media loads later, the bottom moves. Scroll again when it happens.
        var mediaNodes = chatMain.querySelectorAll('img, video');
        for (var j = 0; j < mediaNodes.length; j++) {
            (function (node) {
                if (!node) return;

                // Skip already-loaded images.
                if (node.tagName === 'IMG' && node.complete) return;

                var handler = function () {
                    doScroll();
                    // Remove handlers to avoid repeated calls and leaks.
                    node.removeEventListener('load', handler);
                    node.removeEventListener('error', handler);
                    node.removeEventListener('loadedmetadata', handler);
                };

                node.addEventListener('load', handler);
                node.addEventListener('error', handler);
                node.addEventListener('loadedmetadata', handler);
            })(mediaNodes[j]);
        }

        // Final fallback for slow rendering (fonts, layout, etc).
        setTimeout(doScroll, 250);
    }

    // Define generateFileHTML function in global scope to ensure it's available
    window.generateFileHTML = function (file) {
        var html = "";
        var isRTL = <?= $is_rtl ?>;
        if (file && file.file) {
            var fileName = file.file.substring(file.file.lastIndexOf("/") + 1);
            var fileType = file.file_type ? file.file_type.toLowerCase() : "";
            if (
                fileType.includes("excel") ||
                fileType.includes("word") ||
                fileType.includes("text") ||
                fileType.includes("zip") ||
                fileType.includes("sql") ||
                fileType.includes("php") ||
                fileType.includes("json") ||
                fileType.includes("doc") ||
                fileType.includes("octet-stream") ||
                fileType.includes("pdf")
            ) {
                // Keep same order for both LTR and RTL: file name, then download icon
                // Set direction: ltr on container to prevent RTL from reversing the order
                // This ensures download icon stays on the right side in both directions
                html += '<div class="chat-msg-text" style="display: flex; align-items: center; direction: ltr;">';
                html +=
                    '<a href="' +
                    file.file +
                    '" download="' +
                    fileName +
                    '" class="text-white">' +
                    fileName +
                    "</a>";
                // Use appropriate margin based on direction to maintain spacing
                html += '<i class="fa-solid fa-circle-down text-white ml-2"></i>';
                html += "</div>";
            } else if (fileType.includes("video")) {
                html += '<div class="chat-msg-text ">';
                html +=
                    '<video controls class="w-100 h-100" style="height:200px!important;;width:200px!important;">';
                html +=
                    '<source src="' +
                    file.file +
                    '" type="' +
                    fileType +
                    '" class="text-white">';
                html += '<i class="fa-solid fa-circle-down text-white ' + (isRTL ? 'mr-2' : 'ml-2') + '"></i>';
                html += "</video>";
                html += "</div>";
            }
        }
        return html;
    }

    function appendMessageToChatArea(message) {
        var html = '';
        var messageDate = new Date(message.created_at);
        var hours = messageDate.getHours();
        var minutes = messageDate.getMinutes();
        var ampm = hours >= 12 ? "PM" : "AM";
        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? "0" + minutes : minutes;
        var timeAgo = hours + ":" + minutes + " " + ampm;
        var lastDisplayedDate = new Date(message.last_message_date);
        var dateStr = '';
        if (!lastDisplayedDate || messageDate.toDateString() !== lastDisplayedDate.toDateString()) {
            dateStr = getMessageDateHeading(messageDate);
            lastDisplayedDate = messageDate;
        }
        html += dateStr;
        html += '<div class="chat-msg owner">';
        html += '<div class="chat-msg-content">';
        html += '<div class="chat-msg-bubble-wrap">';
        var files = message.file;
        const chatMessageHTML = renderChatMessage(message, files);
        html += chatMessageHTML;
        if (files && files.length > 0) {
            files.forEach(function (file) {
                html += generateFileHTML(file);
            });
        }
        html += '</div>';
        
        // Profile below content
        html += '<div class="chat-msg-profile">';
        html += '<div class="chat-msg-date">' + timeAgo + '</div>';
        html += '</div>'; // End chat-msg-profile
        
        html += '</div>'; // End chat-msg-content
        html += '</div>'; // End chat-msg
        $('#chat-messages-list').append(html);
        // Use the new scroll function for consistent behavior
        scrollToBottom();
    }

    // Define checkBookingStatus function in global scope to ensure it's available
    window.checkBookingStatus = function (order_id, user_type, callback) {
        $('#order_id').val(order_id);
        $('#user_type_for_send_message').val(user_type);
        $.ajax({
            url: baseUrl + '/partner/check_booking_status',
            type: "POST",
            dataType: 'json',
            data: {
                order_id: order_id,
                user_type: user_type
            },
            success: function (data) {
                callback(data.status);
            },
            error: function (xhr, status, error) {

            }
        });
    }

    // Hover effect for chat question bubbles (delegated for dynamically created bubbles)
    $(document).on('mouseenter', '.chat-question-bubble', function () { $(this).css('background-color', '#e4e3f5'); });
    $(document).on('mouseleave', '.chat-question-bubble', function () { $(this).css('background-color', '#f1f2f6'); });

    function sendChatQuestion(el) {
        var questionText = el.textContent.trim();
        $('#message').val(questionText);
        $('#send_button').prop('disabled', false);
        OnsendMessage();
    }

    var leadHandymanAddedToChatLabel = "<?= esc(labels('handyman_added_to_chat', 'Handyman added to chat')) ?>";

    /**
     * Render (or hide) the sticky "lead handyman" card at the top of the
     * chat bubbles container, based on data returned by /partner/get_lead_handyman.
     */
    function renderLeadHandymanCard(data) {
        var $card = $('#lead_handyman_card');
        $card.empty();
        if (!data || !data.username) {
            $card.addClass('d-none');
            return;
        }

        if (data.image && data.image !== '' && data.image !== 'null' && isValidImageUrlClient(data.image)) {
            var $img = $('<img>').addClass('msg-profile').attr('src', data.image).attr('alt', data.username).attr('data-username', data.username);
            $img.on('error', function () {
                handleImageError(this, data.username, 'msg-profile-placeholder');
            });
            $card.append($img);
        } else {
            $card.append(createProfilePlaceholder(data.username, 'msg-profile-placeholder'));
        }

        var $info = $('<div class="lead-handyman-info"></div>');
        $('<div class="lead-handyman-username"></div>').text(data.username).appendTo($info);
        $('<div class="lead-handyman-subtitle"></div>').text(leadHandymanAddedToChatLabel).appendTo($info);
        $card.append($info).removeClass('d-none');
    }

    /**
     * Fetch the lead handyman assigned to the given booking and render the card.
     * Pre-booking enquiries (order_id like "enquire_...") have no assigned handyman.
     */
    function loadLeadHandymanCard(orderId) {
        if (!orderId || String(orderId).indexOf('enquire_') === 0) {
            renderLeadHandymanCard(null);
            return;
        }
        $.ajax({
            url: baseUrl + '/partner/get_lead_handyman',
            type: 'POST',
            dataType: 'json',
            data: {
                order_id: orderId
            },
            success: function (response) {
                renderLeadHandymanCard((response && response.error === false) ? response.data : null);
            },
            error: function () {
                renderLeadHandymanCard(null);
            }
        });
    }

    // Define setallMessage function in global scope to ensure it's available for onclick events
    window.setallMessage = function (id, element, user_type, order_id) {

        $("#filePreviewContainer").hide();
        var allProfiles = document.querySelectorAll('.msg');
        allProfiles.forEach(function (profile) {
            profile.classList.remove('active');
        });
        element.classList.add('active');
        var receiver_id = id;
        $('#receiver_id').val(receiver_id);
        $('#order_id').val(order_id);
        $('#sender_id').val(<?= $current_user_id ?>);
        $('#user_type_for_send_message').val(user_type);
        markChatAsRead(receiver_id, 'customer', order_id);
        $('#chat-messages-list').text('');
        $('#receiver_username').text('');
        $('#receiver_user_profile').attr('src', '');
        $('#receiver_booking_id').text('');
        loadLeadHandymanCard(order_id);

        checkBookingStatus(order_id, user_type, function (status) {
            $.ajax({
                url: baseUrl + '/partner/provider_booking_chat_list',
                type: "POST",
                dataType: 'json',
                data: {
                    receiver_id: receiver_id,
                    offset: 0,
                    limit: 10,
                    user_type: user_type,
                    order_id: order_id,
                },
                success: function (data) {
                    if (data.error == true) {
                        showToastMessage(data.message, "error");
                    }

                    checkBlockStatus(receiver_id, function (isBlocked) {
                        $('.chat_header').removeClass('d-none');
                        if (isBlocked == 1) {
                            $('.chat-area-footer').prop('disabled', true);
                            $('.chat-area-footer').addClass('d-none');
                            $('.chat-area-footer1').removeClass('d-none');
                            $('.chat-area-footer1').html(`
                                    <div class="card m-3">
                                        <div class="card-body">
                                            <p class="card-text"><?= labels('you_have_blocked_this_user_you_cannot_send_messages_until_you_unblock_them', 'You have blocked this user. You cannot send messages until you unblock them.') ?></p>
                                        </div>
                                    </div>
                                `);
                        } else {
                            if (status === 'completed' || status === 'cancelled') {
                                $('.chat-area-footer').prop('disabled', true);
                                $('.chat-area-footer').addClass('d-none');
                                $('.chat-area-footer1').removeClass('d-none');
                                $('.chat-area-footer1').html(`
                                            <div class="card m-3">
                                                <div class="card-body">
                                                    <p class="card-text"><?= labels('sorry_you_cant_send_a_message_to_the_provider_since_the_booking_has_been_cancelled_or_completed_if_you_have_any_further_questions_or_need_assistance_please_feel_free_to_contact_our_customer_support_team', 'Sorry, you can\'t send a message to the provider since the booking has been cancelled or completed. If you have any further questions or need assistance, please feel free to contact our customer support team.') ?></p>
                                                </div>
                                            </div>
                                        `);
                            } else {
                                $('.chat-area-footer').prop('disabled', false);
                                $('.chat-area-footer1').addClass('d-none');
                                $('.chat-area-footer').removeClass('d-none');
                            }
                        }
                    });

                    var html = '';

                    if (data.rows && data.rows.length > 0 && parseInt(data.total) > 0) {
                        $('.delete-chat-option, .delete-chat-divider').show();
                        var lastDisplayedDate = null;
                        $('#receiver_username').text(data.receiver_name);
                        var receiverName = data.receiver_name || 'U';
                        var $img = $('#receiver_user_profile');
                        var profileHtml = '';
                        if (!data.receiver_profile_image || data.receiver_profile_image === '' || !isValidImageUrlClient(data.receiver_profile_image)) {
                            profileHtml = createProfilePlaceholder(receiverName, 'msg-profile-placeholder').replace('<div', '<div id="receiver_user_profile" style="margin-right: 15px !important; margin-left: 0 !important;"');
                            $img.replaceWith(profileHtml);
                        } else {
                            profileHtml = '<img alt="" id="receiver_user_profile" class="img-circle medium-image" src="' + data.receiver_profile_image + '" data-username="' + receiverName + '" style="margin-right: 15px;">';
                            $img.replaceWith(profileHtml);
                            $('#receiver_user_profile').on('error', function () {
                                $(this).replaceWith(createProfilePlaceholder(receiverName, 'msg-profile-placeholder').replace('<div', '<div id="receiver_user_profile" style="margin-right: 15px !important; margin-left: 0 !important;"'));
                            });
                        }
                        $('#receiver_booking_id').text(data.rows.booking_id);
                        data.rows.forEach(function (message) {
                            if (message.booking_id != null) {
                                $('#receiver_booking_id').text("<?= labels('booking_id', 'Booking id') ?> -" + message.booking_id);
                            } else {
                                $('#receiver_booking_id').text("<?= labels('enquiry', 'Enquiry') ?>");
                            }
                            if (message.hasOwnProperty('sender_id') && message.sender_id !== null && message.sender_id !== "") {
                                var messageDate = new Date(message.created_at);
                                var dateStr = '';
                                if (!lastDisplayedDate || messageDate.toDateString() !== lastDisplayedDate.toDateString()) {
                                    dateStr = getMessageDateHeading(messageDate);
                                    lastDisplayedDate = messageDate;
                                }
                                html += dateStr;
                                if (message.sender_id == <?= $current_user_id ?>) {
                                    html += '<div class="chat-msg owner">';
                                } else {
                                    html += '<div class="chat-msg">';
                                }
                                html += '<div class="chat-msg-content">';
                                html += '<div class="chat-msg-bubble-wrap">';
                                const chatMessageHTML = renderChatMessage(message, message.file);
                                html += chatMessageHTML;
                                if (message.file && message.file.length > 0) {
                                    message.file.forEach(function (file) {
                                        html += generateFileHTML(file);
                                    });
                                }
                                html += '</div>'; // End chat-msg-bubble-wrap

                                html += '<div class="chat-msg-profile">';
                                if (message.sender_id != <?= $current_user_id ?>) {
                                    var senderName = message.sender_name || message.username || 'U';
                                    if (!message.profile_image || message.profile_image === '' || message.profile_image === 'null' || message.profile_image === 'undefined' || !isValidImageUrlClient(message.profile_image)) {
                                        var firstLetter = senderName.charAt(0).toUpperCase();
                                        var colorClass = 'color-' + ((senderName.charCodeAt(0) % 8) + 1);
                                        html += '<div class="chat-msg-img-placeholder ' + colorClass + '">' + firstLetter + '</div>';
                                    } else {
                                        html += '<img class="chat-msg-img" src="' + message.profile_image + '" alt="' + senderName + '" data-username="' + senderName + '" onerror="handleImageError(this, \'' + senderName + '\', \'chat-msg-img-placeholder\')" />';
                                    }
                                }
                                let createdAt = new Date(message.created_at);
                                let hours = createdAt.getHours();
                                let minutes = createdAt.getMinutes();
                                let ampm = hours >= 12 ? "PM" : "AM";
                                hours = hours % 12;
                                hours = hours ? hours : 12;
                                minutes = minutes < 10 ? "0" + minutes : minutes;
                                let formattedTime = hours + ":" + minutes + " " + ampm;
                                html += '<div class="chat-msg-date">' + formattedTime + "</div>";
                                html += '</div>'; // End chat-msg-profile

                                html += '</div>'; // End chat-msg-content
                                html += '</div>'; // End chat-msg
                            }
                        });
                    } else {
                        $('.delete-chat-option, .delete-chat-divider').hide();
                        if (data.receiver_name) {
                            $('#receiver_username').text(data.receiver_name);
                        }
                        var receiverName = data.receiver_name || 'U';
                        var $img = $('#receiver_user_profile');
                        var profileHtml = '';
                        if (!data.receiver_profile_image || data.receiver_profile_image === '' || !isValidImageUrlClient(data.receiver_profile_image)) {
                            profileHtml = createProfilePlaceholder(receiverName, 'msg-profile-placeholder').replace('<div', '<div id="receiver_user_profile" style="margin-right: 15px !important; margin-left: 0 !important;"');
                            $img.replaceWith(profileHtml);
                        } else {
                            profileHtml = '<img alt="" id="receiver_user_profile" class="img-circle medium-image" src="' + data.receiver_profile_image + '" data-username="' + receiverName + '" style="margin-right: 15px;">';
                            $img.replaceWith(profileHtml);
                            $('#receiver_user_profile').on('error', function () {
                                $(this).replaceWith(createProfilePlaceholder(receiverName, 'msg-profile-placeholder').replace('<div', '<div id="receiver_user_profile" style="margin-right: 15px !important; margin-left: 0 !important;"'));
                            });
                        }
                        if (order_id) {
                            $('#receiver_booking_id').text("<?= labels('booking_id', 'Booking id') ?> -" + order_id);
                        }
                        <?php if (!empty($booking_chat_questions)): ?>
                            if (status !== 'completed' && status !== 'cancelled') {
                                html += '<div class="welcome-card" style="height: auto; min-height: 100%; justify-content: flex-start; padding-top: 40px;">';
                                html += '<p><img width="200" height="200" src="<?= base_url('public/uploads/site/black chat section img.svg') ?>" alt="Welcome Image"></p>';
                                html += '<h1 class="welcome-title"><?= labels('start_your_conversation', 'Start your conversation') ?></h1>';
                                html += '<h6 class="welcome-subtitle"><?= labels('chat_welcome_card_subtitle_booking', 'Select a question below or type your own message') ?></h6>';
                                html += '<div class="chat-questions-bubbles" style="display: flex; flex-direction: column; gap: 8px; align-items: flex-start; margin-top: 20px; width: 100%; max-width: 500px;">';
                                html += '<span style="font-size: 12px; color: #a2a2a2; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 4px;"><?= labels('quick_questions', 'Quick Questions') ?></span>';
                                <?php foreach ($booking_chat_questions as $question): ?>
                                    html += '<div class="chat-question-bubble" onclick="sendChatQuestion(this)" style="background-color: #f1f2f6; padding: 8px 14px; border-radius: 5px 5px 5px 0; font-size: 14px; font-weight: 500; color: var(--primary-color); cursor: pointer; max-width: 80%; transition: background-color 0.15s; line-height: 1.4;"><?= esc($question['question']) ?></div>';
                                <?php endforeach; ?>
                                html += '</div>';
                                html += '</div>';
                            } else {
                                html += '<div class="no-message"></div>';
                            }
                        <?php else: ?>
                            html += '<div class="no-message"><?= labels('no_messages_found', 'No messages found') ?></div>';
                        <?php endif; ?>
                    }
                    $('#chat-messages-list').html(html);
                    setTimeout(function () {
                        if (parseInt(data.total) > 0) { scrollToBottom(); }
                        fixTextareaCursorPosition();
                    }, 200);
                },
                error: function (xhr, status, error) { }
            });
            // }
        });
    }

    function getMessageDateHeading(date) {
        var today = new Date();
        var yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        if (date.toDateString() === today.toDateString()) {
            return '<div class="chat-msg-date-heading highlight"><?= labels('today', 'Today') ?></div>';
        } else if (date.toDateString() === yesterday.toDateString()) {
            return '<div class="chat-msg-date-heading highlight"><?= labels('yesterday', 'Yesterday') ?></div>';
        } else {
            return '<div class="chat-msg-date-heading highlight">' + date.toLocaleDateString() + '</div>'; // Display full date if not today or yesterday
        }
    }

    function extractTime(dateTimeString) {
        var dateTimeParts = dateTimeString.split(" ");
        return dateTimeParts[1];
    }

    /**
     * Sync the partner sidebar "Chat" badge with the count of customer rows
     * still showing an unread badge. Called after any badge change.
     */
    function recomputePartnerChatSidebarBadge() {
        var $sidebar = $('#partner-chat-sidebar-unread-badge');
        if (!$sidebar.length) return;
        var count = 0;
        $('#customer-list .unread-badge').each(function () {
            if (!$(this).hasClass('hidden')) count++;
        });
        $sidebar.text(count);
        if (count > 0) $sidebar.removeClass('hidden');
        else $sidebar.addClass('hidden');
    }

    /**
     * Mark all unread incoming chats from the given customer (optionally scoped to
     * a booking/enquiry order_id) as read. Clears the unread badge on success.
     */
    function markChatAsRead(senderId, userType, orderId) {
        if (!senderId || !userType) return;
        var $row = $('#customer-list .msg[data-customer-id="' + senderId + '"][data-order-id="' + orderId + '"]');
        var $badge = $row.find('.unread-badge');
        // No UI early-return: when a new FCM push arrives for the currently-open
        // chat, the row badge stays hidden (we don't bump open threads), but the
        // DB row is still is_read=0. Always hit the server so the sidebar poll
        // doesn't resurface it as unread.
        $.ajax({
            url: baseUrl + '/partner/mark_chat_as_read',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { sender_id: senderId, user_type: userType, order_id: orderId },
            success: function () {
                $badge.text('0').addClass('hidden');
                recomputePartnerChatSidebarBadge();
            }
        });
    }

    /**
     * Increment unread badge for a (customer, order_id) row, or move them to top.
     * Called when a foreground FCM push arrives but the partner is not on that conversation.
     */
    function bumpUnreadBadge(senderId, orderId) {
        if (!senderId) return;
        var $list = $('#customer-list');
        var selector = '.msg[data-customer-id="' + senderId + '"]';
        if (orderId) selector += '[data-order-id="' + orderId + '"]';
        var $row = $list.find(selector).first();
        if ($row.length === 0) {
            // Row doesn't exist yet (brand-new conversation not present at page load /
            // last search refresh). Refetch the sidebar list so it appears with the
            // correct unread count instead of silently dropping the notification.
            fetchCustomerData($('#customer-search').val() || '');
            return;
        }
        var $badge = $row.find('.unread-badge');
        var current = parseInt($badge.text() || '0', 10) || 0;
        $badge.text(current + 1).removeClass('hidden');
        $list.prepend($row);
        recomputePartnerChatSidebarBadge();
    }

    /**
     * Hook called by the partner FCM onMessage handler in include-scripts.php
     * whenever a chat push arrives. Auto-mark-as-read when the conversation is open,
     * or bump the unread badge when it isn't.
     */
    window.onPartnerChatMessageReceived = function (data) {
        try {
            if (!data) return;
            var senderId = data.sender_id || data.senderId || data.user_id;
            var senderType = data.sender_type || data.senderType;
            var bookingId = data.booking_id || data.bookingId;
            var enquiryId = data.e_id || data.eId;
            if (!senderId) return;
            // Only customers (sender_type=2) drive badges on this page.
            if (String(senderType) !== '2') return;

            var currentReceiverId = $('#receiver_id').val();
            var currentOrderId = ($('#order_id').length ? $('#order_id').val() : '');
            var currentChatUserType = $('#user_type_for_send_message').val();

            // Resolve which row this push belongs to.
            var rowOrderId = '';
            if (bookingId != null && bookingId !== '') {
                rowOrderId = String(bookingId);
            } else if (enquiryId != null && enquiryId !== '') {
                // Pre-booking: order_id format is "enquire_<en_id>_<chatId>".
                // We don't have chatId here, so match by data-order-id prefix.
                var $match = $('#customer-list .msg[data-customer-id="' + senderId + '"]').filter(function () {
                    return String($(this).attr('data-order-id') || '').indexOf('enquire_' + enquiryId + '_') === 0;
                }).first();
                if ($match.length) rowOrderId = String($match.attr('data-order-id') || '');
            }

            var isOpen = String(currentReceiverId) === String(senderId)
                && currentChatUserType === 'customer'
                && String(currentOrderId) === rowOrderId
                && rowOrderId !== '';
            if (isOpen) {
                markChatAsRead(senderId, 'customer', rowOrderId);
            } else if (rowOrderId) {
                bumpUnreadBadge(senderId, rowOrderId);
            }
        } catch (e) {
            console.error('onPartnerChatMessageReceived error:', e);
        }
    };

    // Define deleteChat function in global scope to ensure it's available for onclick events
    window.deleteChat = function () {
        Swal.fire({
            title: "<?= labels('are_your_sure', 'Are you sure?') ?>",
            text: "<?= labels('you_want_to_delete_this_chat', 'You want to delete this chat?') ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: "<?= labels('yes', 'Yes') ?>" + "!",
            cancelButtonText: "<?= labels('no', 'No') ?>"
        }).then((result) => {
            if (result.isConfirmed) {
                const receiver_id = $('#receiver_id').val();
                const order_id = $('#order_id').val();
                const user_type = $('#user_type_for_send_message').val();
                const element = document.querySelector('.msg.active');

                $.ajax({
                    url: baseUrl + '/partner/delete-chat',
                    type: 'POST',
                    data: {
                        sender_id: <?= $current_user_id ?>,
                        receiver_id: receiver_id,
                        order_id: order_id,
                    },
                    dataType: 'json',
                    beforeSend: function () {
                        // Show loading state
                        $('#chat-messages-list').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i></div>');
                    },
                    success: function (response) {
                        if (response.error === false) {
                            // Show success message
                            iziToast.success({
                                title: '',
                                message: response.message,
                                position: 'topRight'
                            });

                            // Remove chat item from the list instantly
                            if (element) {
                                $(element).remove();
                            }

                            // Clear chat area
                            $('#chat-messages-list').empty();
                            
                            // Add welcome card back
                            let welcomeHtml = `
                                <div class="welcome-card">
                                    <p>
                                        <img width="200" height="200"
                                            src="<?= base_url('public/uploads/site/black chat section img.svg') ?>"
                                            alt="Welcome Image">
                                    </p>
                                    <?php $data = $general_settings; ?>
                                    <h1 class="welcome-title"><?= labels('welcome_to', 'Welcome to ') ?>&nbsp;
                                        <?= get_company_title_with_fallback($data); ?>
                                    </h1>
                                    <h6 class="welcome-subtitle">
                                        <?= labels('chat_welcome_card_subtitle', 'Pick a person from the left menu and start your conversation') ?>
                                    </h6>
                                </div>
                            `;
                            $('#chat-messages-list').html(welcomeHtml);

                            // Hide chat header, footer and action options
                            $('.chat_header').addClass('d-none');
                            $('.chat-area-footer').addClass('d-none');
                            $('.delete-chat-option, .delete-chat-divider').hide();
                            $('.block-option').hide();

                            // Hide handyman card if visible
                            $('#lead_handyman_card').empty().addClass('d-none');

                            // If chat list is empty, show empty state
                            if ($('.msg').length === 0) {
                                $('#customer-list').html('<div class="text-center p-3"><?= labels('no_chats_found', 'No chats found') ?></div>');
                            }

                            // Reset chat header
                            $('#receiver_username').text('');
                            $('#receiver_user_profile').attr('src', '');
                            $('#receiver_booking_id').text('');

                        } else {
                            // Show error message
                            iziToast.error({
                                title: '',
                                message: response.message,
                                position: 'topRight'
                            });
                        }
                    },
                    error: function () {
                        iziToast.error({
                            title: '',
                            message: "<?= labels('something_went_wrong', 'Something went wrong') ?>",
                            position: 'topRight'
                        });
                    }
                });
            }
        });
    }

    // Define unblockUser function in global scope to ensure it's available for onclick events
    window.unblockUser = function () {
        const receiverId = $('#receiver_id').val();
        const orderId = $('#order_id').val();

        if (!receiverId) {
            showToastMessage("<?= labels('user_id_not_found', 'User ID not found') ?>", "error");
            return;
        }

        Swal.fire({
            title: "<?= labels('are_your_sure', 'Are you sure?') ?>",
            text: "<?= labels('you_want_to_unblock_this_user', 'You want to unblock this user?') ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: "<?= labels('yes', 'Yes') ?>" + "!",
            cancelButtonText: "<?= labels('no', 'No') ?>"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: baseUrl + '/partner/unblock-user',
                    type: 'POST',
                    data: {
                        user_id: receiverId,
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.error === false) {
                            // Show success message
                            iziToast.success({
                                title: '',
                                message: response.message,
                                position: 'topRight'
                            });

                            // Track Microsoft Clarity user unblocked event
                            if (response.data && response.data.clarity_event === 'user_unblocked' && typeof trackUserUnblocked === 'function') {
                                trackUserUnblocked(response.data.user_id);
                            }

                            // Update UI to show block option and hide unblock option
                            $('.block-option').show();
                            $('.unblock-option').hide();

                            // Enable chat functionality
                            $('.chat-area-footer').removeClass('d-none');
                            $('.chat-area-footer1').addClass('d-none');
                        } else {
                            // Show error message
                            iziToast.error({
                                title: '',
                                message: response.message,
                                position: 'topRight'
                            });
                        }
                    },
                    error: function () {
                        iziToast.error({
                            title: '',
                            message: "<?= labels('something_went_wrong', 'Something went wrong') ?>",
                            position: 'topRight'
                        });
                    }
                });

                checkBlockStatus(receiverId);
            }
        });
    }

    // Define checkBlockStatus function in global scope to ensure it's available
    window.checkBlockStatus = function (receiverId, callback) {
        $.ajax({
            url: baseUrl + '/partner/check-block-status',
            type: 'POST',
            data: {
                user_id: receiverId
            },
            dataType: 'json',
            success: function (response) {
                if (response.error === false) {
                    // Update dropdown options based on block status
                    if (response.data == 1) {
                        $('.block-option').hide();
                        $('.unblock-option').show();
                        $('.chat-area-footer').addClass('d-none');
                        $('.chat-area-footer1').removeClass('d-none');
                        callback(response.data);
                    } else {
                        $('.block-option').show();
                        $('.unblock-option').hide();
                        $('.chat-area-footer').removeClass('d-none');
                        $('.chat-area-footer1').addClass('d-none');
                        callback(response.data);
                    }
                }
            }
        });
    }
</script>
<script>
        /**
         * Attachments UI (images/files) depends on Admin settings.
         * If both are enabled, we show two triggers and keep selections in two inputs.
         * We render a single combined preview list and send everything as `attachment[]`.
         */
        (function () {
            const svgImageInput = document.getElementById('svgImageInput');
            const imageInput = document.getElementById('imageInput');
            const svgFileInput = document.getElementById('svgFileInput');
            const fileInput = document.getElementById('fileInput');

            const filePreviewContainer = document.getElementById('filePreviewContainer');
            const isRTL = <?= $is_rtl ?>;
            const maxFileAllowed = <?= $maxFilesOrImagesInOneMessage ?>;
            const maxFileSizeBytes = <?= $maxFileSizeInBytesCanBeSent ?>;

            function setInputFiles(inputEl, filesArray) {
                // DataTransfer lets us remove individual files from an <input type="file"> selection.
                // This keeps UI and actual uploaded files in sync.
                try {
                    const dt = new DataTransfer();
                    filesArray.forEach((f) => dt.items.add(f));
                    inputEl.files = dt.files;
                } catch (e) {
                    // If DataTransfer is unavailable, fall back to clearing the input.
                    // This is safer than sending unexpected attachments.
                    inputEl.value = '';
                }
            }

            function getAllSelectedFiles() {
                const selected = [];
                if (imageInput && imageInput.files) {
                    Array.from(imageInput.files).forEach((f, idx) => selected.push({
                        file: f,
                        source: 'image',
                        index: idx
                    }));
                }
                if (fileInput && fileInput.files) {
                    Array.from(fileInput.files).forEach((f, idx) => selected.push({
                        file: f,
                        source: 'file',
                        index: idx
                    }));
                }
                return selected;
            }

            function formatMaxFileSizeReadable() {
                const maxFileSizeMB = maxFileSizeBytes / (1024 * 1024);
                return maxFileSizeMB >= 1 ? (maxFileSizeMB.toFixed(2) + " MB") : ((maxFileSizeMB * 1024).toFixed(2) + " KB");
            }

            function validateSelectedFiles(selected) {
                if (selected.length > maxFileAllowed) {
                    // Clear both to avoid partial state confusion.
                    if (imageInput) imageInput.value = '';
                    if (fileInput) fileInput.value = '';
                    return {
                        ok: false,
                        message: "<?= labels('file_size_exceeds_the_maximum_limit_of', 'File size exceeds the maximum limit of') ?>" + " " + maxFileAllowed
                    };
                }

                for (let i = 0; i < selected.length; i++) {
                    const f = selected[i].file;
                    const source = selected[i].source;

                    if (f && f.size > maxFileSizeBytes) {
                        // Clear both to avoid partial state confusion.
                        if (imageInput) imageInput.value = '';
                        if (fileInput) fileInput.value = '';
                        return {
                            ok: false,
                            message: "<?= labels('file_size_exceeds_the_maximum_limit_of', 'File size exceeds the maximum limit of') ?>" + " " + formatMaxFileSizeReadable() + ". " + "<?= labels('please_select_a_smaller_file', 'Please select a smaller file') ?>"
                        };
                    }

                    const extension = f.name.split('.').pop().toLowerCase();
                    if (source === 'image') {
                        const allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
                        if (!allowedImageExtensions.includes(extension)) {
                            if (imageInput) imageInput.value = '';
                            return {
                                ok: false,
                                message: "<?= labels('only_image_files_are_allowed', 'Only image files are allowed') ?> (" + extension + ")"
                            };
                        }
                    } else {
                        const allowedFileExtensions = [
                            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                            'odt', 'ods', 'odp', 'rtf', 'txt',
                            'zip', 'rar', '7z', 'tar', 'gz',
                            'csv', 'json', 'xml',
                            'mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm', 'm4v', '3gp'
                        ];
                        if (!allowedFileExtensions.includes(extension)) {
                            if (fileInput) fileInput.value = '';
                            return {
                                ok: false,
                                message: "<?= labels('this_file_type_is_not_allowed', 'This file type is not allowed') ?> (" + extension + ")"
                            };
                        }
                    }
                }

                return {
                    ok: true
                };
            }

            function removeSelectedFile(source, index) {
                const inputEl = (source === 'image') ? imageInput : fileInput;
                if (!inputEl || !inputEl.files) return;
                const currentFiles = Array.from(inputEl.files);
                currentFiles.splice(index, 1);
                setInputFiles(inputEl, currentFiles);
                rebuildPreview();
            }

            function rebuildPreview() {
                if (!filePreviewContainer) return;

                const selected = getAllSelectedFiles();
                filePreviewContainer.innerHTML = '';

                if (selected.length === 0) {
                    $("#filePreviewContainer").hide();
                    return;
                }

                const validation = validateSelectedFiles(selected);
                if (!validation.ok) {
                    filePreviewContainer.innerHTML = '';
                    $("#filePreviewContainer").hide();
                    showToastMessage(validation.message, "error");
                    return;
                }

                $("#filePreviewContainer").show();
                // Use the new scroll function for consistent behavior
                scrollToBottom();

                // If user selects attachments, allow sending even if message is empty.
                $('#send_button').html('<i class="fas fa-paper-plane"></i>');
                $('#send_button').prop('disabled', false);

                selected.forEach(function (item) {
                    const file = item.file;
                    const filePreview = document.createElement('div');
                    filePreview.classList.add('file-preview');

                    if (file && file.type && file.type.includes('image')) {
                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        filePreview.appendChild(img);
                    } else {
                        // For non-image files, create a flex container for proper RTL layout
                        const fileContainer = document.createElement('div');
                        fileContainer.style.display = 'flex';
                        fileContainer.style.alignItems = 'center';
                        fileContainer.style.gap = '8px';
                        if (isRTL) {
                            fileContainer.style.flexDirection = 'row-reverse';
                        }
                        const fileName = document.createElement('span');
                        fileName.textContent = file ? file.name : '';
                        fileContainer.appendChild(fileName);
                        filePreview.appendChild(fileContainer);
                    }

                    const closeBtn = document.createElement('span');
                    closeBtn.classList.add('close-btn');
                    closeBtn.textContent = '×';
                    // Position close button based on RTL
                    if (isRTL) {
                        closeBtn.style.left = 'auto';
                        closeBtn.style.right = '-8px';
                    } else {
                        closeBtn.style.left = '-8px';
                        closeBtn.style.right = 'auto';
                    }
                    closeBtn.addEventListener('click', function () {
                        removeSelectedFile(item.source, item.index);
                    });
                    filePreview.appendChild(closeBtn);
                    filePreviewContainer.appendChild(filePreview);
                });
            }

            if (svgImageInput && imageInput) {
                svgImageInput.addEventListener('click', function () {
                    imageInput.click();
                });
                imageInput.addEventListener('change', rebuildPreview);
            }

            if (svgFileInput && fileInput) {
                svgFileInput.addEventListener('click', function () {
                    fileInput.click();
                });
                fileInput.addEventListener('change', rebuildPreview);
            }
        })();
</script>
<script src="<?= base_url('public/backend/assets/js/vanillaEmojiPicker.js') ?>"></script>
<script>
    const conversationArea = document.querySelector('.conversation-area');
    const toggleConversationAreaBtn = document.getElementById('toggleConversationAreaBtn');
    const profileElements = document.querySelectorAll('.msg');
    toggleConversationAreaBtn.addEventListener('click', () => {
        conversationArea.classList.toggle('show');
    });
    profileElements.forEach(profileElement => {
        profileElement.addEventListener('click', () => {
            conversationArea.classList.remove('show');
        });
    });
</script>
<script>
    <?php if ($url !== false): ?>
        // Get the booking ID from PHP
        const bookingId = <?= $bookingId ?>;
        // Find the customer element with the matching booking ID
        const customerList = document.getElementById('customer-list');
        const customerElements = customerList.querySelectorAll('.msg');
        let customerElement;
        customerElements.forEach(el => {
            const bookingNo = el.querySelector('.featured_lable').textContent.split('::')[1].trim();
            if (bookingNo === bookingId.toString()) {
                customerElement = el;
            }
        });
        if (customerElement) {
            const customerId = customerElement.dataset.customerId;
            $('#receiver_id').val(receiver_id);
            $('#order_id').val(order_id);
            $('#sender_id').val(<?= $current_user_id ?>);
            setallMessage(customerId, customerElement, 'customer', bookingId);

            function renderChatMessage(message, files) {
                let html = "";
                const totalImages = files.filter((image) => {
                    const fileType = image ? image.file_type.toLowerCase() : "";
                    return fileType.includes("image");
                }).length;
                // Filter files where type is image
                files = files.filter((file) => {
                    const fileType = file ? file.file_type.toLowerCase() : "";
                    return fileType.includes("image");
                });
                if (message.message !== "" && totalImages === 0) {
                    html += '<div class="chat-msg-text">' + message.message + "</div>";
                }
                let templateDiv;
                if (totalImages >= 5) {
                    html += generateChatMessageHTML(
                        message,
                        files,
                        "five_plus_img_div",
                        totalImages
                    );
                } else if (totalImages === 4) {
                    html += generateChatMessageHTML(
                        message,
                        files,
                        "four_img_div",
                        totalImages
                    );
                } else if (totalImages === 3) {
                    html += generateChatMessageHTML(
                        message,
                        files,
                        "three_img_div",
                        totalImages
                    );
                } else if (totalImages === 2) {
                    html += generateChatMessageHTML(message, files, "two_img_div", totalImages);
                } else if (totalImages === 1) {
                    html += generateSingleImageHTML(message, files);
                }
                return html;
            }

            function generateChatMessageHTML(message, files, templateClass, totalImages) {
                let templateDivHTML = '<div class="chat-msg-text">';
                let templateDiv = $(`.${templateClass}`).clone().removeClass("d-none");
                let templateDiv1 = $("<div></div>");
                let imageLimit =
                    templateClass === "five_plus_img_div" ? 5 : templateClass.split("_")[0];
                if (imageLimit == "two") {
                    imageLimit = 2;
                } else if (imageLimit == "three") {
                    imageLimit = 3;
                } else if (imageLimit == "four") {
                    imageLimit = 4;
                }
                $.each(files, function (index, value) {
                    if (index < imageLimit) {
                        templateDiv.find("img").eq(index).attr("src", value.file);
                        templateDiv.find("a").eq(index).attr("href", value.file);
                    }
                });
                if (totalImages > imageLimit) {
                    // If there are more images than the limit, add a "Show More" button
                    templateDiv.find(".img_count").removeClass("d-none");
                    let countFile = totalImages - imageLimit;
                    templateDiv.find(".img_count").html(`<h2>+${countFile}</h2>`);
                    $(document).on("click", ".img_count", function () {
                        const images = files.map(
                            (
                                file
                            ) => `<div class="col-md-3"><a href="${file.file}" data-lightbox="image-1"><img height="200px" width="200px" style="    padding: 8px;
                            border-radius: 11px;
                            box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
                            margin: 8px;" src="${file.file}" alt=""></a></div>`
                        );
                        const rowHtml = `<div class="row">${images.join("")}</div>`;
                        $("#imageContainer").html(rowHtml);
                        $("#imageModal").modal("show");
                    });
                }
                if (message.message !== "") {
                    templateDiv1.append(
                        '<div style="display: block;">' + message.message + "</div>"
                    );
                }
                templateDivHTML += templateDiv.prop("outerHTML");
                templateDivHTML += templateDiv1.prop("outerHTML");
                templateDivHTML += "</div>";
                return templateDivHTML;
            }

            function generateSingleImageHTML(message, files) {
                let html = "";
                $.each(files, function (index, value) {
                    if (index < 1) {
                        html += '<div class="chat-msg-text">';
                        html +=
                            '<a href="' +
                            value.file +
                            '" data-lightbox="image-1"><img height="80px" src="' +
                            value.file +
                            '" alt=""></a>';
                        if (message.message !== "") {
                            html += '<div class="">' + message.message + "</div>";
                        }
                        html += "</div>";
                    }
                });
                return html;
            }

            function generateFileHTML(file) {
                var html = "";
                var isRTL = <?= $is_rtl ?>;
                if (file && file.file) {
                    var fileName = file.file.substring(file.file.lastIndexOf("/") + 1);
                    var fileType = file.file_type ? file.file_type.toLowerCase() : "";
                    if (
                        fileType.includes("excel") ||
                        fileType.includes("word") ||
                        fileType.includes("text") ||
                        fileType.includes("zip") ||
                        fileType.includes("sql") ||
                        fileType.includes("php") ||
                        fileType.includes("json") ||
                        fileType.includes("doc") ||
                        fileType.includes("octet-stream") ||
                        fileType.includes("pdf")
                    ) {
                        // Keep same order for both LTR and RTL: file name, then download icon
                        // Set direction: ltr on container to prevent RTL from reversing the order
                        // This ensures download icon stays on the right side in both directions
                        html += '<div class="chat-msg-text" style="display: flex; align-items: center; direction: ltr;">';
                        html +=
                            '<a href="' +
                            file.file +
                            '" download="' +
                            fileName +
                            '" class="text-white">' +
                            fileName +
                            "</a>";
                        // Use appropriate margin based on direction to maintain spacing
                        html += '<i class="fa-solid fa-circle-down text-white ml-2"></i>';
                        html += "</div>";
                    } else if (fileType.includes("video")) {
                        html += '<div class="chat-msg-text ">';
                        html +=
                            '<video controls class="w-100 h-100" style="height:200px!important;;width:200px!important;">';
                        html +=
                            '<source src="' +
                            file.file +
                            '" type="' +
                            fileType +
                            '" class="text-white">';
                        html += '<i class="fa-solid fa-circle-down text-white ' + (isRTL ? 'mr-2' : 'ml-2') + '"></i>';
                        html += "</video>";
                        html += "</div>";
                    }
                }
                return html;
            }

            function renderMessage(message, currentUserId) {
                var html = "";
                var messageDate = new Date(message.created_at);
                var messageDateStr = "";
                if (
                    !lastDisplayedDate ||
                    messageDate.toDateString() !== lastDisplayedDate.toDateString()
                ) {
                    messageDateStr = getMessageDateHeading(messageDate);
                    lastDisplayedDate = messageDate;
                }
                html += messageDateStr;
                var messageClass = message.sender_id == currentUserId ? "owner" : "";
                html += '<div class="chat-msg ' + messageClass + '">';
                
                html += '<div class="chat-msg-content">';
                const chatMessageHTML = renderChatMessage(message, message.file);
                html += chatMessageHTML;
                if (message.file && message.file.length > 0) {
                    message.file.forEach(function (file) {
                        html += generateFileHTML(file);
                    });
                }

                html += '<div class="chat-msg-profile">';
                if (message.sender_id != currentUserId) {
                    html += '<img class="chat-msg-img" src="' + message.profile_image + '" alt="" />';
                }
                html += '<div class="chat-msg-date"> ' + message.created_at + "</div>";
                html += "</div>";
                
                html += "</div>"; // End chat-msg-content
                html += "</div>"; // End chat-msg
                return html;
            }
        } else { }
    <?php else: ?>
    <?php endif; ?>
</script>
<script>
    function checkNotificationPermission() {
        if (!('Notification' in window)) {
            document.getElementById('status').innerHTML = "<?= labels('browser_doesnt_support_notifications', 'This browser does not support desktop notifications') ?>. ";
        } else {
            if (Notification.permission === 'granted') {
                document.getElementById('status').innerHTML = '';
                $('#notification_div').hide();
            } else if (Notification.permission === 'denied') {
                $('#notification_div').show();
                document.getElementById('status').innerHTML = "<?= labels('didnt_allow_notification_permission', "You didn't allow Notification Permission. To get live messages please allow notification permission") ?>. ";
            } else {
                $('#notification_div').show();
                document.getElementById('status').innerHTML = "<?= labels('didnt_allow_notification_permission', "You didn't allow Notification Permission. To get live messages please allow notification permission") ?>. ";
            }
        }
    }
    // Check notification permission on page load
    window.onload = function () {
        checkNotificationPermission();
        // Initialize textarea cursor positioning fix
        initializeTextareaCursorFix();
        // Initialize profile image handlers for placeholders
        initializeProfileImageHandlers();
    };

    /**
     * Initialize image error handlers for all profile images
     * This function should be called after DOM is loaded
     */
    function initializeProfileImageHandlers() {
        // Handle existing profile images in the chat list
        document.querySelectorAll('.msg-profile').forEach(function (img) {
            img.addEventListener('error', function () {
                // Get username from the parent msg element
                const msgElement = this.closest('.msg');
                const usernameElement = msgElement.querySelector('.msg-username');
                const username = usernameElement ? usernameElement.textContent.trim() : '';
                handleImageError(this, username, 'msg-profile-placeholder');
            });
        });

        // Handle chat message profile images
        document.querySelectorAll('.chat-msg-img').forEach(function (img) {
            img.addEventListener('error', function () {
                // For chat messages, we need to get username from the message data
                // This will be handled when messages are rendered
                const username = this.getAttribute('data-username') || '';
                handleImageError(this, username, 'chat-msg-img-placeholder');
            });
        });
    }

    /**
     * Enhanced function to create profile image with fallback
     * @param {string} imageSrc - The image source URL
     * @param {string} username - The username for fallback
     * @param {string} className - CSS class for the image
     * @param {string} placeholderClass - CSS class for the placeholder
     * @returns {string} - HTML string for the image with error handling
     */
    function createProfileImageWithFallback(imageSrc, username, className = 'msg-profile', placeholderClass = 'msg-profile-placeholder') {
        if (!imageSrc || imageSrc === '' || imageSrc === 'null' || imageSrc === 'undefined') {
            return createProfilePlaceholder(username, placeholderClass);
        }

        const colorClass = getColorClass(username);
        return `<img class="${className}" src="${imageSrc}" alt="${username}" data-username="${username}" onerror="handleImageError(this, '${username}', '${placeholderClass}')" />`;
    }
    $('#customer-search').on('keyup', function () {
        var searchTerm = $(this).val();
        fetchCustomerData(searchTerm);
    });

    function fetchCustomerData(searchTerm) {
        $.ajax({
            url: baseUrl + '/partner/get_customer',
            method: 'POST',
            data: {
                search: searchTerm
            },
            dataType: 'json',
            success: function (response) {

                if (response && response.length > 0) {
                    $('#customer-list').empty();
                    $.each(response, function (index, provider) {
                        var listItem = '<div class="msg" data-customer-id="' + provider.id + '" data-order-id="' + provider.order_id + '" onclick="setallMessage(' + provider.id + ', this, \'customer\', \'' + provider.order_id + '\', \'customer\')">';

                        // Check if profile image exists and is not empty
                        if (!provider.profile_image || provider.profile_image === '' || provider.profile_image === 'null' || provider.profile_image === 'undefined' || !isValidImageUrlClient(provider.profile_image)) {
                            // Create placeholder with first letter of username
                            var firstLetter = provider.username.charAt(0).toUpperCase();
                            var colorClass = 'color-' + ((provider.username.charCodeAt(0) % 8) + 1);
                            listItem += '<div class="msg-profile-placeholder ' + colorClass + '">' + firstLetter + '</div>';
                        } else {
                            // Use the profile image with error handling
                            listItem += '<img class="msg-profile" src="' + provider.profile_image + '" alt="' + provider.username + '" data-username="' + provider.username + '" onerror="handleImageError(this, \'' + provider.username + '\', \'msg-profile-placeholder\')" />';
                        }

                        listItem += '<div class="msg-detail">';
                        listItem += '<div class="msg-username">' + provider.username + '</div>';
                        var featuredLabel = '';
                        if (provider.order_id && provider.order_id.toString().startsWith('enquire_')) {
                            featuredLabel = "<?= labels('enquiry', 'Enquiry') ?>";
                        } else if (provider.order_id) {
                            featuredLabel = "<?= labels('booking_id', 'Booking id') ?>" + ' - ' + provider.order_id;
                        }
                        if (featuredLabel !== '') {
                            listItem += '<div class="featured_tag"><div class="featured_lable">' + featuredLabel + '</div></div>';
                        }
                        listItem += '</div>';
                        var custUnread = parseInt(provider.unread_count || 0, 10);
                        listItem += '<span class="unread-badge' + (custUnread > 0 ? '' : ' hidden') + '">' + custUnread + '</span>';
                        listItem += '</div>';
                        $('#customer-list').append(listItem);
                    });
                } else {
                    $('#customer-list').empty();
                }
            },
            error: function (xhr, status, errorThrown) {
                console.error(errorThrown);
            }
        });
    }
</script>

<!-- Report & Block Modal -->
<div class="modal fade" id="reportBlockModal" tabindex="-1" role="dialog" aria-labelledby="reportBlockModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportBlockModalLabel">
                    <?= labels('report_and_block_user', 'Report & Block User') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="reportBlockForm">
                    <input type="hidden" id="reported_user_id" name="reported_user_id">
                    <input type="hidden" id="enquiry_id" name="enquiry_id">

                    <div class="form-group">
                        <label><?= labels('select_reason', 'Select Reason') ?></label>
                        <select class="form-control" id="report_reason" name="report_reason" required>
                            <option value=""><?= labels('select_reason', 'Select Reason') ?></option>
                        </select>
                    </div>
                    <div class="form-group" id="additional_info_group" style="display: none;">
                        <label><?= labels('additional_info', 'Additional Info') ?></label>
                        <textarea class="form-control" id="additional_info" name="additional_info" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
                <button type="button" class="btn btn-danger"
                    id="submitReport"><?= labels('submit', 'Submit') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
    // Load report reasons when modal is shown
    $('#reportBlockModal').on('show.bs.modal', function () {
        // Clear previous data
        $('#report_reason').empty().append('<option value=""><?= labels('select_reason', 'Select Reason') ?></option>');
        $('#additional_info_group').hide();

        // Load reasons from server
        $.ajax({
            url: baseUrl + '/partner/get_report_reasons',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.error === false && response.data) {
                    response.data.forEach(function (reason) {
                        // Truncate long text to prevent overflow
                        var truncatedText = reason.reason;
                        var maxLength = 50; // Maximum characters to display

                        if (truncatedText.length > maxLength) {
                            truncatedText = truncatedText.substring(0, maxLength) + '...';
                        }

                        $('#report_reason').append(
                            $('<option></option>')
                                .val(reason.id)
                                .text(truncatedText)
                                .attr('data-needs-info', reason.needs_additional_info)
                                .attr('title', reason.reason) // Show full text on hover
                        );
                    });
                }
            }
        });
    });

    // Handle reason selection
    $('#report_reason').on('change', function () {
        var selectedOption = $(this).find('option:selected');
        var needsInfo = selectedOption.data('needs-info');

        if (needsInfo == 1) {
            $('#additional_info_group').show();
            $('#additional_info').prop('required', true);
        } else {
            $('#additional_info_group').hide();
            $('#additional_info').prop('required', false);
        }
    });

    // Handle form submission
    $('#submitReport').on('click', function () {
        var form = $('#reportBlockForm');
        var formData = {
            reported_user_id: $('#receiver_id').val(),
            reason_id: $('#report_reason').val(),
            additional_info: $('#additional_info').val(),
            order_id: $('#order_id').val()
        };

        $.ajax({
            url: baseUrl + '/partner/submit_report',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.error === false) {
                    showToastMessage(response.message, "success");
                    $('#reportBlockModal').modal('hide');

                    // Track Microsoft Clarity user reported and blocked events
                    if (response.data) {
                        if (response.data.clarity_event === 'user_reported' && typeof trackUserReported === 'function') {
                            trackUserReported(response.data.user_id, response.data.reason_id);
                        }
                        if (response.data.user_blocked && typeof trackUserBlocked === 'function') {
                            trackUserBlocked(response.data.blocked_user_id);
                        }
                    }
                } else {
                    showToastMessage(response.message, "error");
                }
            },
            error: function () {
                showToastMessage("<?= labels('something_went_wrong', 'Something went wrong') ?>", "error");
            }
        });
        checkBlockStatus(formData.reported_user_id);
    });
</script>