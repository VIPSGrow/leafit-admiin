<!-- Main Content -->
<?php
// Function to validate image URLs more thoroughly
function isValidImageUrl($url)
{
    if (empty($url)) return false;

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
 * We read them here so Admin Support chat shows only the allowed upload options.
 *
 * Admin path:
 * - Settings -> General Settings -> Chat Settings
 * Toggles:
 * - enable_chat_image_upload
 * - enable_chat_file_upload
 */
$general_settings = get_settings('general_settings', true);
$enable_chat_image_upload = !empty($general_settings['enable_chat_image_upload']) ? (int)$general_settings['enable_chat_image_upload'] : 0;
$enable_chat_file_upload = !empty($general_settings['enable_chat_file_upload']) ? (int)$general_settings['enable_chat_file_upload'] : 0;
?>
<div class="main-content">
    <section class="section" id="pill-about_us" role="tabpanel">
        <div class="section-header mt-2">
            <h1> <?= labels('admin_support', "Admin Support") ?>
                <span class="breadcrumb-item p-3 pt-2 text-primary">
                </span>
            </h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/partner/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"></i> <?= labels('admin_support', "Admin Support") ?></div>
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
                        <div class="chat-area myscroll" style="background-color:white!important;">
                            <div class="chat_header d-none" style="padding:16px">
                                <img alt="" id="receiver_user_profile" class="img-circle medium-image" src="">
                                <div>
                                    <b id="receiver_username"> </b>
                                </div>
                            </div>
                            <div class="chat-area-main myscroll" id="chat-area-main">
                                <div class="welcome-card" style="height: auto; min-height: 100%; justify-content: flex-start; padding-top: 40px;">
                                    <p>
                                        <img width="200" height="200" src="<?= base_url('public/uploads/site/black chat section img.svg') ?>" alt="Welcome Image">
                                    </p>
                                    <?php $data = $general_settings; ?>
                                    <h1 class="welcome-title"><?= labels('welcome_to', 'Welcome to') ?>&nbsp;<?= get_company_title_with_fallback($data); ?></h1>
                                    <h6 class="welcome-subtitle">
                                        <?= labels('chat_welcome_card_subtitle_admin_support', 'How can we help you today?') ?>
                                    </h6>
                                    <?php if (!empty($chat_questions)): ?>
                                        <div class="chat-questions-bubbles" style="display: flex; flex-direction: column; gap: 8px; align-items: flex-start; margin-top: 20px; width: 100%; max-width: 500px;">
                                            <span style="font-size: 12px; color: #a2a2a2; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 4px;"><?= labels('quick_questions', 'Quick Questions') ?></span>
                                            <?php foreach ($chat_questions as $question): ?>
                                                <div class="chat-question-bubble" onclick="sendChatQuestion(this)" style="background-color: #f1f2f6; padding: 8px 14px; border-radius: 5px 5px 5px 0; font-size: 14px; font-weight: 500; color: var(--primary-color); cursor: pointer; max-width: 80%; transition: background-color 0.15s; line-height: 1.4;">
                                                    <?= esc($question['question']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div id="filePreviewContainer"></div>
                            <div class="chat-area-footer " style="display: flex; align-items: center;">
                                <form action="<?= base_url('partner/store_admin_chat') ?>" method="post" style="flex: 1; display: flex; align-items: center;" enctype="multipart/form-data">
                                    <?php if ($enable_chat_image_upload == 1) : ?>
                                        <!-- Image upload trigger -->
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-image" id="svgImageInput" style="margin-right: 5px; cursor: pointer;">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                            <circle cx="8.5" cy="8.5" r="1.5" />
                                            <path d="M21 15l-5-5L5 21" />
                                        </svg>
                                        <input id="imageInput" multiple type="file" accept="image/*" style="display: none; margin-right: 5px;" />
                                    <?php endif; ?>

                                    <?php if ($enable_chat_file_upload == 1) : ?>
                                        <!-- File upload trigger (non-image files) -->
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-paperclip" id="svgFileInput" style="margin-right: 5px; cursor: pointer;">
                                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.82l8.49-8.48" />
                                        </svg>
                                        <input id="fileInput" multiple type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.rtf,.txt,.zip,.rar,.7z,.tar,.gz,.csv,.json,.xml,.mp4,.mov,.avi,.mkv,.wmv,.flv,.webm,.m4v,.3gp" style="display: none; margin-right: 5px;" />
                                    <?php endif; ?>
                                    <textarea
                                        class="two"
                                        id="message"
                                        name="message"
                                        placeholder="Type something here..."
                                        style="flex: 1; margin-right: 5px;"
                                        rows="1"
                                    >
                                    </textarea>
                                    <!-- <input type="text" class="one" id="message" name="message" placeholder="Type something here..." style="flex: 1; margin-right: 5px;" /> -->
                                    <input type="hidden" id="sender_id" name="sender_id" value="" />
                                    <input type="hidden" id="receiver_id" name="receiver_id" value="" />
                                    <button id="send_button" class="btn bg-primary text-white" onclick="OnsendMessage();" disabled>
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
    window.handleImageError = function(imgElement, username, placeholderClass = 'msg-profile-placeholder') {
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

    // Hover effect for chat question bubbles (delegated for dynamically created bubbles)
    $(document).on('mouseenter', '.chat-question-bubble', function() { $(this).css('background-color', '#e4e3f5'); });
    $(document).on('mouseleave', '.chat-question-bubble', function() { $(this).css('background-color', '#f1f2f6'); });

    function sendChatQuestion(el) {
        var questionText = el.textContent.trim();
        $('#message').val(questionText);
        $('#send_button').prop('disabled', false);
        OnsendMessage();
    }

    $(document).ready(function() {
        $("#filePreviewContainer").hide();

        /**
         * Count selected attachments from both inputs (if enabled).
         * This is important because we allow sending attachments even if text is empty.
         */
        function getSelectedAttachmentsCount() {
            const imageInputEl = document.getElementById('imageInput');
            const fileInputEl = document.getElementById('fileInput');
            const imageCount = (imageInputEl && imageInputEl.files) ? imageInputEl.files.length : 0;
            const fileCount = (fileInputEl && fileInputEl.files) ? fileInputEl.files.length : 0;
            return imageCount + fileCount;
        }

        $('#message').on('input', function() {
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

    function OnsendMessage() {
        var message = $('#message').val();
        var receiver_id = 1;
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
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = ((evt.loaded / evt.total) * 100);
                        $(".progress-bar").width(percentComplete + '%');
                        $(".progress-bar").html(percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            url: baseUrl + '/partner/store_admin_chat',
            enctype: 'multipart/form-data',
            type: "POST",
            dataType: 'json',
            data: fd,
            processData: false,
            contentType: false,
            async: true,
            cache: false,
            success: function(data) {


                if (data.error == true) {
                    showToastMessage(data.message, "error");
                }
                if (data.error == false) {
                    $('.welcome-card').remove();
                    $('.chat-area-main').css('padding-top', '20px');
                    $('#message').val('');
                    $('#fileInput').val('');
                    $('#imageInput').val('');
                    $("#filePreviewContainer").html('');
                    $("#filePreviewContainer").hide();
                    setTimeout(function() {
                        $('#send_button').html('<i class="fas fa-paper-plane"></i>');
                        $('#send_button').prop('disabled', true);
                    }, 500);
                    var message = data.custom_data['message'];
                    appendMessageToChatArea(data.custom_data);

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
                    }
                }
                if (data.error == true) {
                    submitButton.text('Send');
                    alert('there is No Chat');
                }
            },
            error: function(xhr, status, error) {
                console.log(xhr, status, error);
            }
        });
    }
    $('#message').keypress(function(event) {
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

    /**
     * Always keep the latest chat message visible.
     *
     * Why this exists:
     * - This UI has multiple elements with `myscroll` (wrapper + inner list).
     * - New messages can contain images/videos, which change height after they load.
     *   That can make the UI "almost scroll" but not reach the true bottom.
     *
     * This helper:
     * - Scrolls the element that actually owns the scrollbar.
     * - Re-scrolls after layout and after media finishes loading.
     */
    function scrollToBottom() {
        var chatMain = document.getElementById('chat-area-main') || document.querySelector('.chat-area-main');
        if (!chatMain) return;

        function isScrollable(el) {
            return !!(el && el.scrollHeight > el.clientHeight + 5);
        }

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
            scroller.scrollTop = scroller.scrollHeight;
        }

        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(function() {
                doScroll();
                requestAnimationFrame(doScroll);
            });
        } else {
            doScroll();
        }

        var mediaNodes = chatMain.querySelectorAll('img, video');
        for (var j = 0; j < mediaNodes.length; j++) {
            (function(node) {
                if (!node) return;
                if (node.tagName === 'IMG' && node.complete) return;

                var handler = function() {
                    doScroll();
                    node.removeEventListener('load', handler);
                    node.removeEventListener('error', handler);
                    node.removeEventListener('loadedmetadata', handler);
                };

                node.addEventListener('load', handler);
                node.addEventListener('error', handler);
                node.addEventListener('loadedmetadata', handler);
            })(mediaNodes[j]);
        }

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
        html += '</div>';
        html += '<div class="chat-msg-content">';
        var files = message.file;
        const chatMessageHTML = renderChatMessage(message, files);
        html += chatMessageHTML;
        if (files && files.length > 0) {
            files.forEach(function(file) {
                html += generateFileHTML(file);
            });
        }
        html += '</div>';
        html += '</div>';
        $('.chat-area-main').append(html);
        scrollToBottom();
    }

    function setallMessage() {
        var allProfiles = document.querySelectorAll('.msg');
        allProfiles.forEach(function(profile) {
            profile.classList.remove('active');
        });
        var receiver_id = 1;
        $("#filePreviewContainer").hide();
        $('#receiver_id').val(receiver_id);
        $('#sender_id').val(<?= $current_user_id ?>);
        markAdminChatAsRead();
        $('#receiver_username').text('');
        $('#receiver_user_profile').attr('src', '');
        $.ajax({
            url: baseUrl + 'partner/chat_get_all_messages',
            type: "POST",
            dataType: 'json',
            data: {
                receiver_id: receiver_id,
                offset: 0,
                limit: 10,
            },
            success: function(data) {
                if (data.error == true) {
                    showToastMessage(data.message, "error");
                }

                if (data.rows && data.rows.length > 0 && parseInt(data.total) > 0) {
                    $('.chat_header').removeClass('d-none');
                    $('.chat-area-footer').removeClass('d-none');
                    var html = '';
                    var lastDisplayedDate = null;
                    $('#receiver_username').text(data.receiver_name);
                    $('#receiver_user_profile').attr('src', data.receiver_profile_image);
                    data.rows.forEach(function(message) {
                        html += renderMessage(message, <?= $current_user_id ?>);
                    });
                    $('.chat-area-main').html(html);
                    scrollToBottom();
                }
                // Reinitialize cursor positioning fix for the textarea
                fixTextareaCursorPosition();
            },
            error: function(xhr, status, error) {}
        });
    }

    function getMessageDateHeading(date) {
        var today = new Date();
        var yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        if (date.toDateString() === today.toDateString()) {
            return '<div class="chat_divider"><div>Today</div></div>';
        } else if (date.toDateString() === yesterday.toDateString()) {
            return '<div class="chat_divider"><div>Yesterday</div></div>';
        } else {
            return '<div class="chat_divider"><div>' + date.toLocaleDateString() + '</div></div>';
        }
    }

    function extractTime(dateTimeString) {
        var dateTimeParts = dateTimeString.split(" ");
        return dateTimeParts[1];
    }

    /**
     * Mark all unread admin → partner messages as read.
     * The admin support chat is a single conversation, so there is no badge to clear,
     * but we still flag messages as read for accurate unread tracking server-side.
     */
    function markAdminChatAsRead() {
        $.ajax({
            url: baseUrl + '/partner/mark_chat_as_read',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { user_type: 'admin' },
            success: function() {
                // Sidebar admin support badge reflects unread admin senders;
                // once we mark them as read here, hide it.
                var $sidebar = $('#partner-admin-support-sidebar-unread-badge');
                if ($sidebar.length) {
                    $sidebar.text('0').addClass('hidden');
                }
            }
        });
    }

    /**
     * FCM hook called by app/Views/backend/partner/include-scripts.php when any
     * chat push arrives. If the push is from the admin (sender_type=0) and we
     * are on this admin support page, the message is already visible — so we
     * mark it as read immediately.
     */
    window.onPartnerAdminChatMessageReceived = function(data) {
        try {
            if (!data) return;
            var senderType = data.sender_type || data.senderType;
            if (String(senderType) !== '0') return; // only admin → partner
            markAdminChatAsRead();
        } catch (e) {
            console.error('onPartnerAdminChatMessageReceived error:', e);
        }
    };
</script>
<script>
    /**
     * Attachments UI (images/files) depends on Admin settings.
     * If both are enabled, we show two triggers and keep selections in two inputs.
     * We render a single combined preview list and send everything as `attachment[]`.
     */
    (function() {
        const svgImageInput = document.getElementById('svgImageInput');
        const imageInput = document.getElementById('imageInput');
        const svgFileInput = document.getElementById('svgFileInput');
        const fileInput = document.getElementById('fileInput');

        const filePreviewContainer = document.getElementById('filePreviewContainer');
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
            scrollToBottom();

            // If user selects attachments, allow sending even if message is empty.
            $('#send_button').html('<i class="fas fa-paper-plane"></i>');
            $('#send_button').prop('disabled', false);

            selected.forEach(function(item) {
                const file = item.file;
                const filePreview = document.createElement('div');
                filePreview.classList.add('file-preview');

                if (file && file.type && file.type.includes('image')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    filePreview.appendChild(img);
                } else {
                    const fileName = document.createElement('span');
                    fileName.textContent = file ? file.name : '';
                    filePreview.appendChild(fileName);
                }

                const closeBtn = document.createElement('span');
                closeBtn.classList.add('close-btn');
                closeBtn.textContent = '×';
                closeBtn.addEventListener('click', function() {
                    removeSelectedFile(item.source, item.index);
                });
                filePreview.appendChild(closeBtn);
                filePreviewContainer.appendChild(filePreview);
            });
        }

        if (svgImageInput && imageInput) {
            svgImageInput.addEventListener('click', function() {
                imageInput.click();
            });
            imageInput.addEventListener('change', rebuildPreview);
        }

        if (svgFileInput && fileInput) {
            svgFileInput.addEventListener('click', function() {
                fileInput.click();
            });
            fileInput.addEventListener('change', rebuildPreview);
        }
    })();
    $(document).ready(function() {
        setallMessage();
    });
</script>
<script src="<?= base_url('public/backend/assets/js/vanillaEmojiPicker.js') ?>"></script>
<script>
    new EmojiPicker({
        trigger: [{
            selector: '.first-btn',
            insertInto: ['.one', '.two']
        }, ],
        closeButton: true,
    });
</script>
<script>
    function checkNotificationPermission() {
        if (!('Notification' in window)) {
            document.getElementById('status').innerHTML = "<?= labels('this_browser_does_not_support_desktop_notifications', 'This browser does not support desktop notifications') ?>";
        } else {
            if (Notification.permission === 'granted') {
                document.getElementById('status').innerHTML = '';
                $('#notification_div').hide();
            } else if (Notification.permission === 'denied') {
                $('#notification_div').show();
                document.getElementById('status').innerHTML = "<?= labels('you_didnt_allow_notification_permission_to_get_live_messages_please_allow_notification_permission', 'You didn\'t allow Notification Permission. To get live messages please allow notification permission') ?>";
            } else {
                $('#notification_div').show();
                document.getElementById('status').innerHTML = "<?= labels('you_didnt_allow_notification_permission_to_get_live_messages_please_allow_notification_permission', 'You didn\'t allow Notification Permission. To get live messages please allow notification permission') ?>";
            }
        }
    }
    window.onload = function() {
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
        document.querySelectorAll('.msg-profile').forEach(function(img) {
            img.addEventListener('error', function() {
                // Get username from the parent msg element
                const msgElement = this.closest('.msg');
                const usernameElement = msgElement.querySelector('.msg-username');
                const username = usernameElement ? usernameElement.textContent.trim() : '';
                handleImageError(this, username, 'msg-profile-placeholder');
            });
        });

        // Handle chat message profile images
        document.querySelectorAll('.chat-msg-img').forEach(function(img) {
            img.addEventListener('error', function() {
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