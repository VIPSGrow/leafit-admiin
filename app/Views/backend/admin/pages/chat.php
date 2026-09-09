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

    .unread-badge.hidden,
    .chat-sidebar-unread-badge.hidden {
        display: none !important;
    }

    .nav-tabs .nav-link .chat-sidebar-unread-badge {
        display: inline-flex !important;
        width: auto !important;
        margin-left: 6px;
        vertical-align: middle !important;
    }

    .nav-tabs .nav-link .chat-sidebar-unread-badge.hidden {
        display: none !important;
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

    .chat-msg:not(.owner) .chat-msg-content {
        margin-left: 8px;
    }

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
</style>
<?php
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

// Number of customers/providers with at least one unread message — drives the
// per-tab unread indicator next to the Customer/Provider tab labels. Must
// mirror the render-time skip rules below (e.g. customers without a phone are
// not rendered) so the badge can never exceed the visible list.
$customerUnreadUsersCount = 0;
foreach ($customers as $row) {
    $rowPhone = $row['phone'] ?? '';
    $rowPhone = is_string($rowPhone) ? trim($rowPhone) : '';
    if ($rowPhone === '' || $rowPhone === 'null' || $rowPhone === 'undefined') {
        continue;
    }
    if ((int) ($row['unread_count'] ?? 0) > 0) {
        $customerUnreadUsersCount++;
    }
}
$providerUnreadUsersCount = 0;
foreach ($providers as $row) {
    if ((int) ($row['unread_count'] ?? 0) > 0) {
        $providerUnreadUsersCount++;
    }
}

/**
 * Chat attachments (images/files) are controlled by Admin settings.
 * Settings -> General Settings -> Chat Settings
 * Toggles: enable_chat_image_upload, enable_chat_file_upload.
 * Render only the upload triggers that are enabled; the backend
 * (Admin\Chats::validateChatAttachments) enforces the same rules.
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
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/partners') ?>"><i
                            class="fas fa-handshake text-warning"></i> </i> <?= labels('provider', 'Provider') ?></a>
                </div>
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
                    <div class="wrapper">
                        <button id="toggleConversationAreaBtn"><?= labels('chat_list', "Chat List") ?></button>
                        <div class="conversation-area" id="">
                            <ul class="nav nav-tabs fixed-tabs" style="padding: 1.25rem!important;">
                                <div class="row w-100 ml-1 mr-1">
                                    <div class="col-md-6 m-0 p-0">
                                        <li class="nav-item">
                                            <a class="nav-link test active" href="#"
                                                onclick="openTab(event, 'customer')"><?= labels('customer', "Customer") ?><span
                                                    id="customer-tab-unread-badge"
                                                    class="chat-sidebar-unread-badge<?= $customerUnreadUsersCount > 0 ? '' : ' hidden' ?>"><?= (int) $customerUnreadUsersCount ?></span></a>
                                        </li>
                                    </div>
                                    <div class="col-md-6 m-0 p-0">
                                        <li class="nav-item">
                                            <a class="nav-link test" href="#"
                                                onclick="openTab(event, 'provider')"><?= labels('provider', "Provider") ?><span
                                                    id="provider-tab-unread-badge"
                                                    class="chat-sidebar-unread-badge<?= $providerUnreadUsersCount > 0 ? '' : ' hidden' ?>"><?= (int) $providerUnreadUsersCount ?></span></a>
                                        </li>
                                    </div>
                                </div>
                            </ul>
                            <div id="customer" class="tabcontent">
                                <div class="search-bar">
                                    <input type="text" id="customer-search"
                                        placeholder="<?= labels('search_customer', 'Search Customer') ?>..." />
                                </div>
                                <hr class="mb-0">
                                <div id="customer-list">
                                    <?php foreach ($customers as $user): ?>
                                        <?php
                                        // Normalize phone: treat null, empty, or string "null"/"undefined" as missing
                                        $phone = $user['phone'] ?? '';
                                        $phone = (is_string($phone) && trim($phone) !== '') ? trim($phone) : '';
                                        if ($phone === null || $phone === '' || $phone === 'null' || $phone === 'undefined') {
                                            continue;
                                        }
                                        $countryCode = $user['country_code'] ?? '';
                                        ?>
                                        <div class="msg" data-user-id="<?= $user['id'] ?>" data-user-type="customer"
                                            onclick="setallMessage(<?= $user['id'] ?>, this, 'customer')">
                                            <?php
                                            // Check if profile image exists and is not empty
                                            $profileImage = $user['profile_image'] ?? '';
                                            $username = $user['username'] ?? 'User';
                                            $unreadCount = (int) ($user['unread_count'] ?? 0);

                                            // Debug output
                                            echo "<!-- Debug: profileImage = " . htmlspecialchars($profileImage) . ", username = " . htmlspecialchars($username) . " -->";

                                            if (empty($profileImage) || $profileImage === 'null' || $profileImage === 'undefined' || !filter_var($profileImage, FILTER_VALIDATE_URL) || !isValidImageUrl($profileImage)) {
                                                $firstLetter = strtoupper(substr($username, 0, 1));
                                                $colorClass = 'color-' . ((ord($firstLetter) % 8) + 1);
                                                echo '<div class="msg-profile-placeholder ' . $colorClass . '">' . $firstLetter . '</div>';
                                            } else {
                                                $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
                                                echo '<img class="msg-profile" src="' . htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8') . '" alt="' . $safeUsername . '" data-username="' . $safeUsername . '" onerror="handleImageError(this, \'' . $safeUsername . '\', \'msg-profile-placeholder\')" />';
                                            }

                                            // Build display phone safely (cast to string before substr operations)
                                            if ((defined('ALLOW_VIEW_KEYS') && ALLOW_VIEW_KEYS == 0)) {
                                                $displayPhone = substr_replace((string) $phone, '********', 3, 8);
                                            } else {
                                                $displayPhone = $countryCode . $phone;
                                            }
                                            ?>
                                            <div class="msg-detail">
                                                <div class="msg-username"><?= $user['username']; ?></div>
                                                <?php if (!empty($phone) && $phone !== 'null' && $phone !== 'undefined'): ?>
                                                    <div class="msg-phone">
                                                        <?= $displayPhone ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <span
                                                class="unread-badge<?= $unreadCount > 0 ? '' : ' hidden' ?>"><?= $unreadCount ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div id="provider" class="tabcontent" style="display:none;">
                                <div class="search-bar">
                                    <input type="text" id="provider-search"
                                        placeholder="<?= labels('search_provider', 'Search Provider') ?>..." />
                                </div>
                                <hr class="mb-0">
                                <div id="provider-list">
                                    <?php foreach ($providers as $user): ?>
                                        <div class="msg" data-user-id="<?= $user['id'] ?>" data-user-type="provider"
                                            onclick="setallMessage(<?= $user['id'] ?>, this, 'provider')">
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
                                                <?php
                                                $pPhone = $user['phone'] ?? '';
                                                if ($pPhone !== '' && $pPhone !== 'null' && $pPhone !== 'undefined'):
                                                    ?>
                                                    <div class="msg-phone"><?= $user['country_code'] ?? '' ?>
                                                        <?= $user['phone']; ?>
                                                    </div>
                                                <?php endif; ?>
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
                                </div>
                            </div>
                            <div class="chat-area-main myscroll" id="chat-area-main">
                                <div class="welcome-card">
                                    <p>
                                        <img width="200" height="200"
                                            src="<?= base_url('public/uploads/site/black chat section img.svg') ?>" alt="Welcome Image
                                        ">
                                    </p>
                                    <?php $data = get_settings('general_settings', true); ?>
                                    <h1 class="welcome-title">
                                        <?= labels('welcome_to', 'Welcome to') ?>&nbsp;<?= get_company_title_with_fallback($data); ?>
                                    </h1>
                                    <h6 class="welcome-subtitle">
                                        <?= labels('chat_welcome_card_subtitle', 'Pick a person from the left menu and start your conversation') ?>
                                    </h6>
                                </div>
                            </div>
                            <div id="filePreviewContainer"></div>
                            <div class="chat-area-footer d-none" style="display: flex; align-items: center;">
                                <form action="<?= base_url('admin/store_chat') ?>" method="post"
                                    style="flex: 1; display: flex; align-items: center;" enctype="multipart/form-data">
                                    <?php if ($enable_chat_image_upload == 1): ?>
                                        <!-- Image upload trigger (only images) -->
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
                                        placeholder="<?= labels('type_something_here', 'Type something here') ?>..."
                                        style="flex: 1; margin-right: 5px;" rows="1">
                                    </textarea>
                                    <!-- <input type="text" class="two" id="message" name="message" placeholder="Type something here..." style="flex: 1; margin-right: 5px;" /> -->
                                    <!--
                                        Sender id is the currently logged-in admin.
                                        This is used by the admin panel foreground FCM handler
                                        (in `include-scripts.php`) to auto-append only messages
                                        that belong to the currently open conversation.
                                    -->
                                    <input type="hidden" id="sender_id" name="sender_id"
                                        value="<?= $current_user_id ?>" />
                                    <input type="hidden" id="receiver_id" name="receiver_id" value="" />
                                    <input type="hidden" id="booking_id" name="booking_id" value="" />
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
     * Set the chat header avatar for the active conversation, falling back
     * to the initial-letter placeholder (same as the sidebar list / message
     * bubbles) when there is no valid profile image.
     * @param {string} username - The receiver's display name
     * @param {string} imageUrl - The receiver's profile image URL
     */
    function setReceiverProfileImage(username, imageUrl) {
        var name = username || 'U';
        var placeholderHtml = createProfilePlaceholder(name, 'msg-profile-placeholder').replace('<div', '<div id="receiver_user_profile"');
        if (!imageUrl || imageUrl === '' || imageUrl === 'null' || imageUrl === 'undefined' || !isValidImageUrlClient(imageUrl)) {
            $('#receiver_user_profile').replaceWith(placeholderHtml);
        } else {
            var safeName = $('<div>').text(name).html();
            var imgHtml = '<img alt="" id="receiver_user_profile" class="img-circle medium-image" src="' + imageUrl + '" data-username="' + safeName + '">';
            $('#receiver_user_profile').replaceWith(imgHtml);
            $('#receiver_user_profile').on('error', function () {
                $(this).replaceWith(placeholderHtml);
            });
        }
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
        $('#message').on('input', function () {
            var maxLength = <?= $maxCharactersInATextMessage ?>;
            var message = $(this).val().trim();
            var messageLength = message.length;
            var hasAttachments = getSelectedAttachmentsCount() > 0;

            if (messageLength > maxLength) {
                $(this).val(message.substring(0, maxLength));
                showToastMessage("<?= labels('maximum_length_of', 'Maximum length of') ?> " + <?= $maxCharactersInATextMessage ?> + " <?= labels('characters_exceeded_message_trimmed', 'characters exceeded. Message trimmed') ?>", "error");
            }
            // Allow sending if attachments selected, even when message is empty.
            if ((message === '' && !hasAttachments) || messageLength >= maxLength) {
                $('#send_button').prop('disabled', true);
            } else {
                $('#send_button').prop('disabled', false);
            }
        });
    });

    /**
     * Count selected attachments across both inputs (image + file).
     * Either input may be absent depending on Admin chat settings.
     */
    function getSelectedAttachmentsCount() {
        var imageInputEl = document.getElementById('imageInput');
        var fileInputEl = document.getElementById('fileInput');
        var imageCount = (imageInputEl && imageInputEl.files) ? imageInputEl.files.length : 0;
        var fileCount = (fileInputEl && fileInputEl.files) ? fileInputEl.files.length : 0;
        return imageCount + fileCount;
    }

    function OnsendMessage() {
        var message = $('#message').val();
        var receiver_id = $('#receiver_id').val();
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
        fd.append('user_type_for_send_message', user_type_for_send_message);
        // Attach files from both inputs (if enabled). Both post as `attachment[]`.
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
            url: baseUrl + '/admin/store_chat',
            enctype: 'multipart/form-data',
            type: "POST",
            dataType: 'json',
            data: fd,
            processData: false,
            contentType: false,
            async: true,
            cache: false,
            success: function (data) {
                if (data.error == false) {
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
                }
                if (data.error == true) {
                    showToastMessage(data.message, "error");
                    $('#send_button').html('<i class="fas fa-paper-plane"></i>');
                    $('#send_button').prop('disabled', false);
                }
            }
        });
    }
    $('#message').keypress(function (event) {
        var maxLength = <?= $maxCharactersInATextMessage ?>;
        var message = $(this).val().trim();
        var messageLength = message.length;
        var hasAttachments = getSelectedAttachmentsCount() > 0;

        if (event.which === 13 && !event.shiftKey) { // Enter key pressed without Shift
            event.preventDefault();

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

    // Function to scroll to bottom of chat area (same approach as partner chat page)
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

    function appendMessageToChatArea(message) {
        var html = '';
        var profileImage = message.profile_image ? message.profile_image : '';
        var timeAgo = message.created_at ? extractTime(message.created_at) : '';
        var messageDate = new Date(message.created_at);
        var lastDisplayedDate = new Date(message.last_message_date);
        var dateStr = '';
        if (!lastDisplayedDate || messageDate.toDateString() !== lastDisplayedDate.toDateString()) {
            dateStr = getMessageDateHeading(messageDate);
            lastDisplayedDate = messageDate;
        }
        html += dateStr;
        html += '<div class="chat-msg owner">';
        html += '<div class="chat-msg-content">';
        var files = message.file;
        const chatMessageHTML = renderChatMessage(message, files);
        html += chatMessageHTML;
        if (files && files.length > 0) {
            files.forEach(function(file) {
                html += generateFileHTML(file);
            });
        }
        
        // Profile below content
        html += '<div class="chat-msg-profile">';

        // Check if profile image exists and is not empty
        if (!profileImage || profileImage === '' || profileImage === 'null' || profileImage === 'undefined' || !isValidImageUrlClient(profileImage)) {
            // Create placeholder with first letter of username
            var firstLetter = (message.username || 'U').charAt(0).toUpperCase();
            var colorClass = 'color-' + (((message.username || 'U').charCodeAt(0) % 8) + 1);
            html += '<div class="chat-msg-img-placeholder ' + colorClass + '">' + firstLetter + '</div>';
        } else {
            // Use the profile image with error handling
            html += '<img class="chat-msg-img" src="' + profileImage + '" alt="' + (message.username || '') + '" data-username="' + (message.username || '') + '" onerror="handleImageError(this, \'' + (message.username || '') + '\', \'chat-msg-img-placeholder\')" />';
        }

        html += '<div class="chat-msg-date">' + timeAgo + '</div>';
        html += '</div>'; // End chat-msg-profile
        
        html += '</div>'; // End chat-msg-content
        html += '</div>'; // End chat-msg';
        $('.chat-area-main').append(html);
        // Use the stable scroll helper (same behavior as partner chat page)
        scrollToBottom();
    }

    function setallMessage(id, element, user_type) {
        $("#filePreviewContainer").hide();
        var allProfiles = document.querySelectorAll('.msg');
        allProfiles.forEach(function (profile) {
            profile.classList.remove('active');
        });
        element.classList.add('active');
        var receiver_id = id;
        $('#receiver_id').val(receiver_id);
        $('#user_type_for_send_message').val(user_type);
        markChatAsRead(receiver_id, user_type);
        $('.chat-area-main').text('');
        $('#receiver_username').text('');
        $('#receiver_user_profile').attr('src', '');
        $.ajax({
            url: baseUrl + '/admin/chat_get_all_messages',
            type: "POST",
            dataType: 'json',
            data: {
                receiver_id: receiver_id,
                offset: 0,
                limit: 10,
                user_type: user_type,
            },
            success: function (data) {
                if (data.error == true) {
                    showToastMessage(data.message, "error");
                }
                $('.chat_header').removeClass('d-none');
                $('.chat-area-footer').removeClass('d-none');
                var html = '';
                if (data.rows && data.rows.length > 0) {
                    var lastDisplayedDate = null;
                    $('#receiver_username').text(data.receiver_name);
                    setReceiverProfileImage(data.receiver_name, data.receiver_profile_image);
                    data.rows.forEach(function (message) {
                        if (message.hasOwnProperty('sender_id') && message.sender_id !== null && message.sender_id !== "") {
                            html += renderMessage(message, <?= $current_user_id ?>);
                        }
                    });
                } else {
                    html += '<div class="no-message">No messages found.</div>';
                }
                $('.chat-area-main').html(html);
                // Ensure that the chat scrolls to the bottom (stable + media-aware)
                scrollToBottom();
                // Reinitialize cursor positioning fix for the textarea
                fixTextareaCursorPosition();
                // $('.myscroll').animate({
                //     scrollTop: $('.myscroll').get(0).scrollHeight
                // }, 1500)
            },
            error: function (xhr, status, error) { }
        });
    }

    function getMessageDateHeading(date) {
        var today = new Date();
        var yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        if (date.toDateString() === today.toDateString()) {
            return '<div class="chat_divider">Today</div>';
        } else if (date.toDateString() === yesterday.toDateString()) {
            return '<div class="chat_divider">Yesterday</div>';
        } else {
            return '<div class="chat_divider">' + date.toLocaleDateString() + '</div>'; // Display full date if not today or yesterday
        }
    }

    function extractTime(dateTimeString) {
        var dateTimeParts = dateTimeString.split(" ");
        return dateTimeParts[1];
    }

    /**
     * Recompute the per-tab unread-user counts (Customer/Provider) and the
     * sidebar Chat badge from the current state of the chat list. Called
     * after any badge change so all indicators stay in sync without a reload.
     */
    function recomputeChatUnreadIndicators() {
        function countVisibleUnread($list) {
            var count = 0;
            $list.find('.unread-badge').each(function () {
                if (!$(this).hasClass('hidden')) count++;
            });
            return count;
        }
        var customerCount = countVisibleUnread($('#customer-list'));
        var providerCount = countVisibleUnread($('#provider-list'));

        function applyBadge($badge, value) {
            if (!$badge.length) return;
            $badge.text(value);
            if (value > 0) $badge.removeClass('hidden');
            else $badge.addClass('hidden');
        }
        applyBadge($('#customer-tab-unread-badge'), customerCount);
        applyBadge($('#provider-tab-unread-badge'), providerCount);
        applyBadge($('#admin-chat-sidebar-unread-badge'), customerCount + providerCount);
    }

    /**
     * Mark all unread chats from the given sender (customer/provider) to admin as read.
     * Clears the unread badge on success.
     */
    function markChatAsRead(senderId, userType) {
        if (!senderId || !userType) return;
        var $row = $('.msg[data-user-id="' + senderId + '"][data-user-type="' + userType + '"]');
        var $badge = $row.find('.unread-badge');
        // No UI early-return: when a new FCM push arrives for the currently-open
        // chat, the row badge stays hidden (we don't bump open threads), but the
        // DB row is still is_read=0. Always hit the server so the sidebar poll
        // doesn't resurface it as unread.
        $.ajax({
            url: baseUrl + '/admin/mark_chat_as_read',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { sender_id: senderId, user_type: userType },
            success: function () {
                $badge.text('0').addClass('hidden');
                recomputeChatUnreadIndicators();
            }
        });
    }

    /**
     * Increment unread badge for a sender row, or move them to top if missing.
     * Called when a foreground FCM push arrives but admin is not on that conversation.
     */
    function bumpUnreadBadge(senderId, userType) {
        if (!senderId || !userType) return;
        var $list = $('#' + (userType === 'provider' ? 'provider-list' : 'customer-list'));
        var $row = $list.find('.msg[data-user-id="' + senderId + '"][data-user-type="' + userType + '"]');
        if ($row.length === 0) return; // user not in current list — full reload would be needed
        var $badge = $row.find('.unread-badge');
        var current = parseInt($badge.text() || '0', 10) || 0;
        $badge.text(current + 1).removeClass('hidden');
        // Move row to top of its list
        $list.prepend($row);
        recomputeChatUnreadIndicators();
    }

    /**
     * Hook called by the global FCM onMessage handler in include-scripts.php
     * whenever a chat push arrives. Lets us auto-mark-as-read when the chat
     * is open, or bump the unread badge when it isn't.
     */
    window.onAdminChatMessageReceived = function (data) {
        try {
            if (!data) return;
            var senderId = data.sender_id || data.senderId || data.user_id;
            var senderType = data.sender_type || data.senderType;
            if (!senderId) return;
            var userType = (String(senderType) === '1') ? 'provider'
                : (String(senderType) === '2') ? 'customer'
                    : null;
            if (!userType) return;

            var currentReceiverId = $('#receiver_id').val();
            var currentChatUserType = $('#user_type_for_send_message').val();
            var isOpen = String(currentReceiverId) === String(senderId)
                && currentChatUserType === userType;
            if (isOpen) {
                markChatAsRead(senderId, userType);
            } else {
                bumpUnreadBadge(senderId, userType);
            }
        } catch (e) {
            console.error('onAdminChatMessageReceived error:', e);
        }
    };
</script>
<script>
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tabcontent");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("nav-link");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById(tabName).style.display = "flex";
        evt.currentTarget.classList.add("active");
    }
    document.addEventListener("DOMContentLoaded", function () {
        var activeTabLink = document.querySelector(".nav-link.active");
        if (activeTabLink) {
            var activeTab = activeTabLink.getAttribute("href").substring(1);
            var activeTabContent = document.getElementById(activeTab);
            if (activeTabContent) {
                activeTabContent.style.display = "flex";
            }
        }
        // Reconcile tab + sidebar badges from the actually-rendered list,
        // so any server/render drift can never leave a stale count behind.
        if (typeof recomputeChatUnreadIndicators === 'function') {
            recomputeChatUnreadIndicators();
        }
    });
    /**
     * Attachments UI (images/files) depends on Admin chat settings.
     * Either, both, or neither trigger may be rendered. We keep selections
     * in separate inputs (image vs file), render a single combined preview,
     * and post everything as `attachment[]`. The backend
     * (Admin\Chats::validateChatAttachments) re-enforces the same rules.
     */
    (function () {
        const svgImageInput = document.getElementById('svgImageInput');
        const imageInput = document.getElementById('imageInput');
        const svgFileInput = document.getElementById('svgFileInput');
        const fileInput = document.getElementById('fileInput');

        const filePreviewContainer = document.getElementById('filePreviewContainer');
        const isRTL = <?= (int) (session()->get('is_rtl') ?? 0) ?>;
        const maxFileAllowed = <?= $maxFilesOrImagesInOneMessage ?>;
        const maxFileSizeBytes = <?= $maxFileSizeInBytesCanBeSent ?>;

        function setInputFiles(inputEl, filesArray) {
            // DataTransfer lets us remove individual files from an
            // <input type="file"> selection, keeping UI and uploads in sync.
            try {
                const dt = new DataTransfer();
                filesArray.forEach((f) => dt.items.add(f));
                inputEl.files = dt.files;
            } catch (e) {
                // If DataTransfer is unavailable, clearing is safer than
                // sending unexpected attachments.
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
                if (imageInput) imageInput.value = '';
                if (fileInput) fileInput.value = '';
                return {
                    ok: false,
                    message: "<?= labels('note_max_file_or_image_allowed_in_one_message', 'Note: Maximum File or image allowed in one message') ?>" + " " + maxFileAllowed
                };
            }

            for (let i = 0; i < selected.length; i++) {
                const f = selected[i].file;
                const source = selected[i].source;

                if (f && f.size > maxFileSizeBytes) {
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
                $('#send_button').prop('disabled', $('#message').val().trim() === '');
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
            scrollToBottom();

            // With attachments selected, allow sending even if message empty.
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
    $(document).ready(function () {
        var baseUrl = '<?php echo base_url(); ?>';

        function fetchCustomerData(searchTerm) {
            $.ajax({
                url: baseUrl + '/admin/get_customers',
                method: 'POST',
                data: {
                    search: searchTerm
                },
                dataType: 'json',
                success: function (response) {
                    if (response && response.length > 0) {
                        $('#customer-list').empty();
                        $.each(response, function (index, customer) {
                            var listItem = '<div class="msg " data-user-id="' + customer.id + '" data-user-type="customer" onclick="setallMessage(' + customer.id + ', this, \'customer\')">';

                            // Check if profile image exists and is not empty
                            if (!customer.profile_image || customer.profile_image === '' || customer.profile_image === 'null' || customer.profile_image === 'undefined' || !isValidImageUrlClient(customer.profile_image)) {
                                // Create placeholder with first letter of username
                                var firstLetter = customer.username.charAt(0).toUpperCase();
                                var colorClass = 'color-' + ((customer.username.charCodeAt(0) % 8) + 1);
                                listItem += '<div class="msg-profile-placeholder ' + colorClass + '">' + firstLetter + '</div>';
                            } else {
                                // Use the profile image with error handling
                                listItem += '<img class="msg-profile" src="' + customer.profile_image + '" alt="' + customer.username + '" data-username="' + customer.username + '" onerror="handleImageError(this, \'' + customer.username + '\', \'msg-profile-placeholder\')" />';
                            }

                            listItem += '<div class="msg-detail">';
                            listItem += '<div class="msg-username">' + customer.username + '</div>';
                            // Only show phone line when customer has a real phone (avoid showing "null")
                            var phone = customer.phone;
                            if (phone != null && phone !== '' && phone !== 'null' && phone !== 'undefined') {
                                listItem += '<div class="msg-phone">' + (customer.country_code || '') + ' ' + phone + '</div>';
                            }
                            listItem += '</div>';
                            var custUnread = parseInt(customer.unread_count || 0, 10);
                            listItem += '<span class="unread-badge' + (custUnread > 0 ? '' : ' hidden') + '">' + custUnread + '</span>';
                            listItem += '</div>';
                            $('#customer-list').append(listItem);
                        });
                    } else {
                        $('#customer-list').empty();
                    }
                    recomputeChatUnreadIndicators();
                },
                error: function (xhr, status, errorThrown) {
                    console.error(errorThrown);
                }
            });
        }
        $('#customer-search').on('keyup', function () {
            var searchTerm = $(this).val();
            fetchCustomerData(searchTerm);
        });

        function fetchProviderData(searchTerm) {
            $.ajax({
                url: baseUrl + '/admin/get_providers',
                method: 'POST',
                data: {
                    search: searchTerm
                },
                dataType: 'json',
                success: function (response) {
                    if (response && response.length > 0) {
                        $('#provider-list').empty();
                        $.each(response, function (index, provider) {
                            var listItem = '<div class="msg " data-user-id="' + provider.id + '" data-user-type="provider" onclick="setallMessage(' + provider.id + ', this,\'provider\')">';

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
                            // Only show phone line when provider has a real phone (avoid showing "null")
                            var provPhone = provider.phone;
                            if (provPhone != null && provPhone !== '' && provPhone !== 'null' && provPhone !== 'undefined') {
                                listItem += '<div class="msg-phone">' + (provider.country_code || '') + ' ' + provPhone + '</div>';
                            }
                            listItem += '</div>';
                            var provUnread = parseInt(provider.unread_count || 0, 10);
                            listItem += '<span class="unread-badge' + (provUnread > 0 ? '' : ' hidden') + '">' + provUnread + '</span>';
                            listItem += '</div>';
                            $('#provider-list').append(listItem);
                        });
                    } else {
                        $('#provider-list').empty();
                    }
                    recomputeChatUnreadIndicators();
                },
                error: function (xhr, status, errorThrown) {
                    console.error(errorThrown);
                }
            });
        }
        $('#provider-search').on('keyup', function () {
            var searchTerm = $(this).val();
            fetchProviderData(searchTerm);
        });
    });
</script>
<script src="<?= base_url('public/backend/assets/js/vanillaEmojiPicker.js') ?>"></script>
<script>
    new EmojiPicker({
        trigger: [{
            selector: '.first-btn',
            insertInto: ['.one', '.two']
        },],
        closeButton: true,
    });
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
</script>