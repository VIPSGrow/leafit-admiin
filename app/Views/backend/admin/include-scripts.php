<script src="<?= base_url('public/backend/assets/js/vendor/bootstrap-table.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/popper.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/summernote.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/bootstrap.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/jquery.nicescroll.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/moment.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/stisla.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/iziToast.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/select2.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/cropper.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/bootstrap-colorpicker.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/daterangepicker.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/dropzone.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/sweetalert.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/lottie.js') ?>"></script>
<script type="text/javascript" src="<?= base_url('public/backend/assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>
<script src="<?= base_url('public/frontend/retro/js/password-strength.js') ?>"></script>

<?php
switch ($main_page) {
    case "dashboard":
        echo '<script  src="' . base_url('public/backend/assets/js/vendor/chart.min.js') . '"></script>';
        echo '<script  src="' . base_url('public/backend/assets/js/vendor/iconify.min.js') . '"></script>';
        break;
    case "subscription":
        echo '<script src="' . base_url('public/backend/assets/js/page/subscription.js') . '"></script>';
        break;
    case "plans":
        break;
    case "../../text_to_speech":
        echo '<script src="' . base_url('public/backend/assets/js/page/tts.js') . '"></script>';
        break;
    case "add_partner":
    case "edit_partner":
    case "duplicate_provider":
        echo '<script src="' . base_url('public/backend/assets/js/provider-stepper.js') . '?v=' . get_system_version() . '"></script>';
        break;
    case "service_form":
        echo '<script src="' . base_url('public/backend/assets/js/service-stepper.js') . '?v=' . get_system_version() . '"></script>';
        break;
}
$api_key = get_settings('api_key_settings', true);
$firebase_setting = get_settings('firebase_settings', true);

$map_api_key = isset($api_key['google_map_api']) && !empty($api_key['google_map_api'])
    ? $api_key['google_map_api']
    : '';

$places_api_key = isset($api_key['google_places_api']) && !empty($api_key['google_places_api'])
    ? $api_key['google_places_api']
    : '';

$map_provider = get_map_provider();
?>
<script>
    var MAP_PROVIDER = '<?= esc($map_provider) ?>';
    window.MAP_PROVIDER = MAP_PROVIDER;
</script>
<?php if ($map_provider === 'openstreetmap'): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tagify/4.15.2/tagify.min.js"></script>

<!-- Translations START -->
<script>
    let are_your_sure = "<?php echo  labels('are_your_sure', 'Are you sure?') ?>";
    let yes_proceed = "<?php echo  labels('yes_proceed', 'Yes, Proceed!') ?>";
    let you_wont_be_able_to_revert_this = "<?php echo  labels('you_wont_be_able_to_revert_this', "You won't be able to revert this!") ?>";
    let are_you_sure_you_want_to_deactivate_this_user = "<?php echo labels('are_you_sure_you_want_to_deactivate_this_user', 'Are you sure you want to deactivate this user') ?>"
    let are_you_sure_you_want_to_delete_this_user = "<?php echo  labels('are_you_sure_you_want_to_delete_this_user', 'Are you sure you want to delete this user') ?>"
    let are_you_sure_you_want_to_activate_this_user = "<?php echo  labels('are_you_sure_you_want_to_activate_this_user', 'Are you sure you want to activate this user') ?>"
    let cancel = "<?php echo  labels('cancel', 'Cancel') ?>";
    let enter_otp_here = "<?php echo labels('enter_otp_here', 'Enter OTP here') ?>";
    let subcategories_and_services_will_be_deactivated = "<?php echo labels('subcategories_and_services_will_be_deactivated', 'Subcategories and services of this category will be deactivated'); ?>"
    let blog_categories_and_related_blogs_will_be_deleted = "<?php echo labels('blog_categories_and_related_blogs_will_be_deleted', 'Blog categories and related blogs will be deleted'); ?>"
    let are_you_sure_you_want_to_delete_this_provider = "<?php echo labels('are_you_sure_you_want_to_delete_this_provider', 'Are you sure you want to delete this provider') ?>"
    let are_you_sure_you_want_to_approve_this_provider = "<?php echo labels('are_you_sure_you_want_to_approve_this_provider', 'Are you sure you want to approve this provider') ?>"
    let are_you_sure_you_want_to_disapprove_this_provider = "<?php echo labels('are_you_sure_you_want_to_disapprove_this_provider', 'Are you sure you want to disapprove this provider') ?>"
    let are_you_sure_you_want_to_activate_this_provider = "<?php echo labels('are_you_sure_you_want_to_activate_this_provider', 'Are you sure you want to activate this provider') ?>"
    let are_you_sure_you_want_to_deactivate_this_provider = "<?php echo labels('are_you_sure_you_want_to_deactivate_this_provider', 'Are you sure you want to deactivate this provider') ?>"
    let only_one_file_can_be_uploaded_at_a_time = "<?php echo labels('only_one_file_can_be_uploaded_at_a_time', 'Only one file can be uploaded at a time') ?>"
    let error = "<?php echo labels('error', 'Error') ?>"
    let select_files = "<?php echo labels('select_files', 'Select Files') ?>"
    let or = "<?php echo labels('or', 'Or') ?>"
    let drag_and_drop_system_update_installable_plugins_zip_file_here = "<?php echo labels('drag_and_drop_system_update_installable_plugins_zip_file_here', 'Drag & Drop System Update / Installable / Plugin\'s .zip file Here') ?>"
    let browse_files = "<?php echo labels('browse_files', 'Browse Files') ?>"
    let drag_and_drop_files_here = "<?php echo labels('drag_and_drop_files_here', 'Drag & Drop files here') ?>"
    let file_of_invalid_type = "<?php echo labels('file_of_invalid_type', 'File of invalid type') ?>"
    let file_is_too_large = "<?php echo labels('file_is_too_large', 'File is too large') ?>"
    let maximum_file_size_is = "<?php echo labels('maximum_file_size_is', 'Maximum file size is') ?>"
    let invalid_file_type_please_upload_an_excel_or_csv_file = "<?php echo labels('invalid_file_type_please_upload_an_excel_or_csv_file', 'Invalid file type. Please upload an Excel or CSV file.') ?>"
    let do_you_want_to_assign_subscription_plan = "<?php echo labels('do_you_want_to_assign_subscription_plan', 'Do you want to assign subscription plan?') ?>"
    let yes = "<?php echo labels('yes', 'Yes') ?>"
    let no = "<?php echo labels('no', 'No') ?>"
    let on = "<?php echo labels('on', 'On') ?>"
    let off = "<?php echo labels('off', 'Off') ?>"
    let included = "<?php echo labels('included', 'Included') ?>"
    let excluded = "<?php echo labels('excluded', 'Excluded') ?>"
    let are_you_sure_you_want_to_delete_this_rating = "<?php echo labels('are_you_sure_you_want_to_delete_this_rating', 'Are you sure you want to delete this rating') ?>"
    let are_you_sure_you_want_to_cancel_this_service = "<?php echo labels('are_you_sure_you_want_to_cancel_this_service', 'Are you sure you want to cancel this service') ?>"
    let make_sure_you_have_collected_cash_amount_before_completing_the_booking = "<?php echo labels('make_sure_you_have_collected_cash_amount_before_completing_the_booking', 'Make sure you have collected cash amount before completing the booking.') ?>"
    let are_you_sure_you_want_to_disapprove_this_service = "<?php echo labels('are_you_sure_you_want_to_disapprove_this_service', 'Are you sure you want to disapprove this service') ?>"
    let are_you_sure_you_want_to_approve_this_service = "<?php echo labels('are_you_sure_you_want_to_approve_this_service', 'Are you sure you want to approve this service') ?>"
    let are_you_sure_you_want_to_clone_this_service = "<?php echo labels('are_you_sure_you_want_to_clone_this_service', 'Are you sure you want to clone this service') ?>"
    let oops = "<?php echo labels('oops', 'Oops') ?>"
    let please_enter_otp_before_proceeding_any_further = "<?php echo labels('please_enter_otp_before_proceeding_any_further', 'Please Enter OTP before proceeding any further') ?>"
    let entered_otp_is_wrong_please_confirm_it_and_try_again = "<?php echo labels('entered_otp_is_wrong_please_confirm_it_and_try_again', 'Entered OTP is wrong please confirm it and try again') ?>"
    let select_country_code = "<?php echo labels('select_country_code', 'Select Country Code') ?>"
    let could_not_find_sub_categories_on_this_category_please_change_categories = "<?php echo labels('could_not_find_sub_categories_on_this_category_please_change_categories', 'Could not find sub categories on this category Please change categories') ?>"
    // Checkbox text variables for working days
    let checkbox_on_text = "<?php echo labels('on', 'On') ?>"
    let checkbox_off_text = "<?php echo labels('off', 'Off') ?>"
    let select_category = "<?php echo labels('select_category', 'Select Category') ?>"
    let select_sub_category = "<?php echo labels('select_sub_category', 'Select sub Category') ?>"
    let select_provider = "<?php echo labels('select_provider', 'Select Provider') ?>"
    let select_tag = "<?php echo labels('select_tag', 'Select Tag') ?>"
    let select_user = "<?php echo labels('please_select_user', 'Please select User') ?>"
    let select_ticket_status = "<?php echo labels('select_ticket_status', 'Select Ticket Status') ?>"
    let empty_password = "<?php echo labels('empty_password', 'Empty Password') ?>"
    let empty_confirm_password = "<?php echo labels('empty_confirm_password', 'Empty Confirm Password') ?>"
    let password_mismatch = "<?php echo labels('password_mismatch', 'Password Mismatch') ?>"

    // Switch text translations
    let switchTextMap = {
        "Approved": "<?php echo labels('approved', 'Approved') ?>",
        "Disapproved": "<?php echo labels('disapproved', 'Disapproved') ?>",
        "Enable": "<?php echo labels('enable', 'Enable') ?>",
        "Disable": "<?php echo labels('disable', 'Disable') ?>",
        "Active": "<?php echo labels('active', 'Active') ?>",
        "Deactive": "<?php echo labels('deactive', 'Deactive') ?>",
        "Inactive": "<?php echo labels('inactive', 'Inactive') ?>",
        "Yes": "<?php echo labels('yes', 'Yes') ?>",
        "No": "<?php echo labels('no', 'No') ?>",
        "On": "<?php echo labels('on', 'On') ?>",
        "Off": "<?php echo labels('off', 'Off') ?>",
        "Allowed": "<?php echo labels('allowed', 'Allowed') ?>",
        "Not Allowed": "<?php echo labels('not_allowed', 'Not Allowed') ?>"
    };
    // Bootstrap table translation labels
    let bootstrapTableLabels = {
        "formatShowingRows": "<?php echo labels('formatShowingRows', 'Showing {from} to {to} of {total} entries') ?>",
        "formatRecordsPerPage": "<?php echo labels('formatRecordsPerPage', '{0} entries per page') ?>",
        "formatNoMatches": "<?php echo labels('formatNoMatches', 'No matching records found') ?>",
        "formatSearch": "<?php echo labels('formatSearch', 'Search') ?>",
        "formatLoadingMessage": "<?php echo labels('formatLoadingMessage', 'Loading, please wait...') ?>",
        "formatRefresh": "<?php echo labels('formatRefresh', 'Refresh') ?>",
        "formatToggle": "<?php echo labels('formatToggle', 'Toggle') ?>",
        "formatColumns": "<?php echo labels('formatColumns', 'Columns') ?>",
        "formatAllRows": "<?php echo labels('formatAllRows', 'All') ?>",
        "formatPaginationSwitch": "<?php echo labels('formatPaginationSwitch', 'Hide/Show pagination') ?>",
        "formatDetailPagination": "<?php echo labels('formatDetailPagination', 'Detail pagination') ?>",
        "formatClearFilters": "<?php echo labels('formatClearFilters', 'Clear filters') ?>",
        "formatJumpTo": "<?php echo labels('formatJumpTo', 'GO') ?>",
        "formatAdvancedSearch": "<?php echo labels('formatAdvancedSearch', 'Advanced search') ?>",
        "formatAdvancedCloseButton": "<?php echo labels('formatAdvancedCloseButton', 'Close') ?>"
    };
    let fvLabels = {
        required: "<?php echo labels('fv_required', '%s is required') ?>",
        email:    "<?php echo labels('fv_email',    'Please enter a valid email address') ?>",
        min:      "<?php echo labels('fv_min',      'Must be at least %d characters') ?>",
        max:      "<?php echo labels('fv_max',      'Must not exceed %d characters') ?>",
        pwdMin:   "<?php echo labels('fv_pwd_min',  'At least %d characters') ?>",
        field:    "<?php echo labels('field',        'Field') ?>",
        pwdRequirements: "<?php echo labels('fv_pwd_requirements', 'Password does not meet the requirements. Please check the rules above.') ?>",
    };
</script>

<script>
    // Initialize working days checkbox text when the page loads
    $(document).ready(function() {
        if (typeof updateWorkingDaysCheckboxText === 'function') {
            updateWorkingDaysCheckboxText();
        }

        // Initialize switch text translation when the page loads
        if (typeof updateSwitchText === 'function') {
            updateSwitchText();
        }

        // Initialize Bootstrap table translations when the page loads
        if (typeof initializeBootstrapTableTranslations === 'function') {
            initializeBootstrapTableTranslations();
        }
    });

    // Listen for language changes and update switch/checkbox text
    $(document).on('localeChanged', function() {
        // Update switch text when language changes
        if (typeof updateSwitchText === 'function') {
            updateSwitchText();
        }

        // Update working days checkbox text when language changes
        if (typeof updateWorkingDaysCheckboxText === 'function') {
            updateWorkingDaysCheckboxText();
        }

        // Clear translation cache and update Bootstrap table translations when language changes
        if (typeof clearTranslationCache === 'function') {
            clearTranslationCache();
        }
        if (typeof initializeBootstrapTableTranslations === 'function') {
            initializeBootstrapTableTranslations();
        }
        // Update loading messages when language changes
        if (typeof updateLoadingMessages === 'function') {
            updateLoadingMessages();
        }
    });
</script>

<!-- Translations END -->

<!-- start :: include FilePond library -->
<script src="https://unpkg.com/filepond/dist/filepond.js"></script>
<script src="<?= base_url('public/backend/assets/js/filepond/dist/filepond.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-image-preview.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-pdf-preview.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-file-validate-size.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-file-validate-type.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-image-validate-size.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/filepond/dist/filepond.jquery.js') ?>"></script>
<!-- for media preview -->
<!-- <script src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-media-preview.esm.js') ?>"></script> -->
<!-- <script src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-media-preview.esm.min.js') ?>"></script> -->
<!-- for end  media preview -->
<!-- end :: include FilePond library -->
<script src="<?= base_url('public/backend/assets/js/map-provider.js') . '?v=' . get_system_version() ?>"></script>
<script src="<?= base_url('public/backend/assets/js/live-tracking-map.js') . '?v=' . get_system_version() ?>"></script>
<script src="<?= base_url('public/backend/assets/js/scripts.js') ?>"></script>
<!-- Central notification click redirect handler (shared with partner panel) -->
<script src="<?= base_url('public/backend/assets/js/notification_redirects.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/bootstrap-translations.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/switch-translations.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/select2_register.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/switch_component.js') ?>"></script>
<!-- for swithchery js start -->
<script src="<?= base_url('public/backend/assets/js/switchery.min.js') ?>"></script>
<!-- table reorder rows start -->
<script src="https://unpkg.com/bootstrap-table@1.21.4/dist/extensions/reorder-rows/bootstrap-table-reorder-rows.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tablednd@1.0.5/dist/jquery.tablednd.min.js"></script>
<!-- table reorder rows end -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.css">
<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.8.0/dist/chart.min.js"></script>
<?php
echo '<script src="' . base_url('public/backend/assets/js/vendor/chart.min.js') . '"></script>';
?>

<script>
    /*
        Admin panel (mobile/tablet) layout fix: prevent navbar overlap.

        Why:
        - `.main-content` uses a static `padding-top: 80px` from the theme.
        - The admin navbar can become taller on small screens (wrapping items).
        - So the fixed navbar covers the first part of the page.

        What we do:
        - Measure `.main-navbar` height and store it in a CSS variable:
          `--admin-navbar-height`
        - CSS (in `include-css.php`) uses that variable to pad `.main-content`.
    */
    (function() {
        function updateAdminNavbarHeightVar() {
            var $nav = $(".main-navbar");
            if (!$nav.length) return;

            // Ensure we never set a smaller value than the theme's default (80px).
            var h = Math.ceil($nav.outerHeight() || 0);
            h = Math.max(h, 80);

            document.documentElement.style.setProperty("--admin-navbar-height", h + "px");
        }

        // Update on initial render.
        $(document).ready(function() {
            updateAdminNavbarHeightVar();

            // Re-run after layout settles (fonts, badges, etc.).
            setTimeout(updateAdminNavbarHeightVar, 250);
            setTimeout(updateAdminNavbarHeightVar, 1000);
        });

        // Update on resize/orientation changes.
        $(window).on("load resize orientationchange", function() {
            updateAdminNavbarHeightVar();
        });
    })();
</script>

</head>
<script src="https://www.gstatic.com/firebasejs/8.2.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.2.0/firebase-messaging.js"></script>
<script src="<?= base_url('public/backend/assets/js/window_event.js') ?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
<script src="<?= base_url('public/backend/assets/js/form-validator.js') ?>"></script>
<script type="text/javascript" src="<?= base_url('public/backend/assets/js/custom.js') ?>?v=<?= get_system_version() ?>"></script>
<?= '<script src="' . base_url('public/backend/assets/js/page/admin.js') . '"></script>' ?>
<script type="text/javascript" src="<?= base_url('public/backend/assets/js/AllQueryParams.js') ?>"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script type="text/javascript" src="<?= base_url('public/backend/assets/js/tableExport.min.js') ?>"></script>

<?php if ($map_provider === 'google'): ?>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=<?= $map_api_key ?>&libraries=places&callback=initAll&loading=async"
        async defer>
        </script>
<?php else: ?>
    <script>
        // No Google callback hook available without the Google Maps script; bootstrap manually.
        $(function () {
            if (typeof initAll === 'function') initAll();
        });
    </script>
<?php endif; ?>

<script>
    var firebaseConfig = {
        apiKey: '<?= isset($firebase_setting['apiKey']) ? $firebase_setting['apiKey'] : '1' ?>',
        authDomain: '<?= isset($firebase_setting['authDomain']) ? ($firebase_setting['authDomain']) : 0 ?>',
        projectId: '<?= isset($firebase_setting['projectId']) ? $firebase_setting['projectId'] : 0 ?>',
        storageBucket: '<?= isset($firebase_setting['storageBucket']) ? $firebase_setting['storageBucket'] : 0 ?>',
        messagingSenderId: '<?= isset($firebase_setting['messagingSenderId']) ? $firebase_setting['messagingSenderId'] : 0 ?>',
        appId: '<?= isset($firebase_setting['appId']) ? $firebase_setting['appId'] : 0 ?>',
        measurementId: '<?= isset($firebase_setting['measurementId']) ? $firebase_setting['measurementId'] : 0 ?>'
    };

    firebase.initializeApp(firebaseConfig);
    const fcm = firebase.messaging();

    /**
     * Admin Panel: ensure Firebase service worker is registered via our CI route.
     *
     * Why this matters:
     * - Admin pages live under `/admin/...`.
     * - If a service worker is registered using a *relative* URL, the browser will
     *   look for it under `/admin/...` and registration fails.
     *
     * Fix:
     * - Always register using the absolute routed URL:
     *   `/firebase-messaging-sw.js` (served by `Frontend::firebaseServiceWorker`).
     * - Then ask Firebase for the token using that specific SW registration.
     *
     * This mirrors the partner panel approach. It ensures FCM works reliably,
     * which is required for chat foreground pushes (auto-append).
     */
    const vapidKey = "<?= isset($firebase_setting['vapidKey']) ? $firebase_setting['vapidKey'] : 0 ?>";
    const serviceWorkerUrl = "<?= base_url('firebase-messaging-sw.js') ?>";

    // Keep token saving logic isolated and readable.
    function saveAdminWebToken(token) {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        // Get current language code from session or default
         <?php
$currentLanguage = get_current_language();
?>
        var languageCode = '<?= $currentLanguage ?>';
        return $.ajax({
            url: baseUrl + '/save-web-token',
            type: 'POST',
            data: {
                token: token,
                language_code: languageCode
            },
            dataType: 'JSON',
            success: function(response) {
                console.log('Device Token saved successfully');
            },
            error: function(err) {
                console.log('User Chat Token Error' + err);
            },
        });
    }

    // Register the SW via our route first, then request the token.
    // If SW registration is blocked, fall back to the previous behavior.
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(serviceWorkerUrl)
            .then((registration) => {
                return fcm.getToken({
                    vapidKey: vapidKey,
                    serviceWorkerRegistration: registration
                });
            })
            .then((token) => {
                return saveAdminWebToken(token);
            })
            .catch(() => {
                // Fallback: keep old behavior to avoid breaking token generation
                // on environments where SW registration is blocked.
                return fcm.getToken({
                    vapidKey: vapidKey
                }).then((token) => saveAdminWebToken(token));
            });
    } else {
        // Very old browsers: no service worker support.
        // Keep the previous behavior (token may not work for background pushes).
        fcm.getToken({
            vapidKey: vapidKey
        }).then((token) => saveAdminWebToken(token));
    }

    /**
     * Foreground FCM handler (Admin panel).
     *
     * Requirements:
     * - `fcm.onMessage()` fires ONLY when the page is open (foreground).
     * - Chat auto-append must NOT depend on browser notification permission.
     *   Even if notifications are blocked/denied, chat should still update.
     *
     * Why we changed this:
     * - Customer app payloads send `sender_details` / `receiver_details` as JSON objects
     *   (example: `{"0":{...},"image":"..."}`), not always as arrays.
     * - Old logic parsed them as arrays (`JSON.parse(...)[0]`) and failed.
     */
    function requestBrowserNotificationPermissionIfNeeded() {
        // Keep this helper tiny and safe. We only request permission when needed.
        if (!('Notification' in window)) return Promise.resolve('unsupported');
        if (Notification.permission === 'granted') return Promise.resolve('granted');
        if (Notification.permission === 'denied') return Promise.resolve('denied');
        return Notification.requestPermission().catch(() => 'default');
    }

    /**
     * Escape HTML to prevent XSS when appending a minimal fallback bubble.
     */
    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Partner-like stable auto-scroll: only scroll if user is near bottom.
     * (Prevents scroll "jitter" when multiple pushes arrive quickly.)
     */
    function getChatScrollerElement() {
        var chatMain = document.querySelector('.chat-area-main');
        if (!chatMain) return null;

        function isScrollable(el) {
            return !!(el && el.scrollHeight > el.clientHeight + 5);
        }

        // Prefer `.myscroll` if it is the scroller, otherwise fall back.
        var candidates = [
            document.querySelector('.myscroll'),
            chatMain,
            chatMain.closest ? chatMain.closest('.chat-area') : null,
            chatMain.parentElement || null,
            document.querySelector('.chat-area-main.myscroll'),
            document.querySelector('.chat-area.myscroll')
        ];

        for (var i = 0; i < candidates.length; i++) {
            if (isScrollable(candidates[i])) return candidates[i];
        }
        return chatMain;
    }

    function isNearChatBottom(scroller, thresholdPx) {
        thresholdPx = (typeof thresholdPx === 'number') ? thresholdPx : 120;
        if (!scroller) return true;
        var distance = scroller.scrollHeight - (scroller.scrollTop + scroller.clientHeight);
        return distance <= thresholdPx;
    }

    function scrollChatToBottomAfterPaint(scroller) {
        if (!scroller) return;

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
        setTimeout(doScroll, 250);
    }

    function autoScrollChatToBottomIfUserWasNearBottom(userWasNearBottom) {
        if (!userWasNearBottom) return;
        scrollChatToBottomAfterPaint(getChatScrollerElement());
    }

    /**
     * Parse user details JSON robustly.
     *
     * Supported formats we see in the project:
     * - `[{id, username, image}]`
     * - `{id, username, image}`
     * - `{"0": {id, username, image}, "image": "..."}`  (customer app)
     */
    function parseUserDetails(detailsJson) {
        if (!detailsJson) return {};
        try {
            var parsed = JSON.parse(detailsJson);
            if (Array.isArray(parsed)) {
                return parsed[0] || {};
            }
            if (parsed && typeof parsed === 'object') {
                var inner = parsed['0'] || parsed[0] || null;
                if (inner && typeof inner === 'object') {
                    // Merge image from root if inner image is missing.
                    return Object.assign({}, inner, {
                        image: inner.image || parsed.image || parsed.profile_image || ''
                    });
                }
                return parsed;
            }
        } catch (e) {}
        return {};
    }

           /**
     * Parse `file` payload robustly. Can be "[]", "[[]]" or already an array.
     */
    function parseFiles(fileRaw) {
        if (!fileRaw) return [];
        // Sometimes the payload already provides an array.
        if (Array.isArray(fileRaw)) return fileRaw;
        try {
            var parsed = JSON.parse(fileRaw);
            if (Array.isArray(parsed) && Array.isArray(parsed[0])) return parsed[0];
            if (Array.isArray(parsed)) return parsed;
        } catch (e) {}
        return [];
    }

           /**
     * Minimal fallback append (text-only) when we cannot render rich chat HTML.
     * This ensures "foreground push received but nothing appended" never happens.
     */
    function appendSimpleChatMessage(messageText, profileImageUrl) {
        if (!$('.chat-area-main').length) return;

        var scrollerBefore = getChatScrollerElement();
        var userWasNearBottom = isNearChatBottom(scrollerBefore);

        var timeAgo = new Date().toLocaleTimeString();
        var safeMsg = escapeHtml(messageText);
        var html = '';
        html += '<div class="chat-msg">';
        html += '<div class="chat-msg-content">';
        html += '<div class="chat-msg-text">' + safeMsg + '</div>';
        html += '<div class="chat-msg-profile">';
        if (profileImageUrl) {
            html += '<img class="chat-msg-img" src="' + escapeHtml(profileImageUrl) + '" alt="" />';
        }
        html += '<div class="chat-msg-date">' + escapeHtml(timeAgo) + '</div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        $('.chat-area-main').append(html);
        autoScrollChatToBottomIfUserWasNearBottom(userWasNearBottom);
    }

    function processIncomingChatMessage(payload) {
        var payloadData = (payload && payload.data) ? payload.data : {};
        if (!$('.chat-area-main').length) return;

        var senderDetails = parseUserDetails(payloadData.sender_details);
        var messageText = payloadData.message || '';
        var files = parseFiles(payloadData.file);

        // Try to use existing rich renderers if this page has them.
        var canRenderRich =
            (typeof renderChatMessage === 'function') &&
            (typeof generateFileHTML === 'function');

        // Capture scroll intent BEFORE append.
        var scrollerBefore = getChatScrollerElement();
        var userWasNearBottom = isNearChatBottom(scrollerBefore);

        var profileImage =
            senderDetails.image ||
            payloadData.profile_image ||
            payloadData.profile ||
            payloadData.image ||
            '';

        var timeAgo = new Date().toLocaleTimeString();
        var messageDate = new Date();
        var dateStr = '';
        var lastDisplayedDate = new Date(payloadData.last_message_date);
        if (!lastDisplayedDate || messageDate.toDateString() !== lastDisplayedDate.toDateString()) {
            dateStr = getMessageDateHeading(messageDate);
            lastDisplayedDate = messageDate;
        }

        if (!canRenderRich) {
            // No rich renderer on this page. Append minimal text bubble.
            // (We still show notifications separately below.)
            appendSimpleChatMessage(messageText || payloadData.body || '', profileImage);
            return;
        }

        var html = '';
        html += dateStr;
        html += '<div class="chat-msg">';
        html += '<div class="chat-msg-content">';

        var chatMessageHTML = renderChatMessage(payloadData, files);
        html += chatMessageHTML;

        if (files && files.length > 0) {
            files.forEach(function(file) {
                html += generateFileHTML(file);
            });
        }

        html += '<div class="chat-msg-profile">';
        if (profileImage) {
            html += '<img class="chat-msg-img" src="' + profileImage + '" alt="" />';
        }
        html += '<div class="chat-msg-date">' + timeAgo + '</div>';
        html += '</div>';

        html += '</div>';
        html += '</div>';

        $('.chat-area-main').append(html);
        autoScrollChatToBottomIfUserWasNearBottom(userWasNearBottom);
    }

    fcm.onMessage((payload) => {
        console.log('RAW FCM NOTIFICATION PAYLOAD', payload);
        var data = (payload && payload.data) ? payload.data : {};

        // --- Lean NotificationService payload support ---
        // The new NotificationService sends all chat fields inside a stringified
        // `chat_message` key (+ `chat_user` which is null for admin-sent messages).
        // Parse `chat_message` and merge into `data` so downstream code
        // (processIncomingChatMessage, appendSimpleChatMessage) works as-is.
        if (data.chat_message && typeof data.chat_message === 'string') {
            try {
                var parsed = JSON.parse(data.chat_message);
                if (parsed && typeof parsed === 'object') {
                    // Merge parsed chat fields into data. Existing top-level keys
                    // (e.g. booking_id, type) take precedence to avoid overwriting.
                    data = Object.assign({}, parsed, data);
                    // Also update payload.data so processIncomingChatMessage() (which
                    // reads payload.data directly) picks up the unwrapped fields.
                    payload.data = data;
                }
            } catch (_e) {
                // chat_message was not valid JSON — continue with original data.
            }
        }

        try {
            // Support both snake_case and camelCase payloads.
            var receiverId = data.receiver_id || data.receiverId;
            var senderId = data.sender_id || data.senderId || data.user_id;
            var bookingId = data.booking_id || data.bookingId;
            var enquiryId = data.e_id || data.eId;
            var viewerType = data.viewer_type || data.viewerType;
            var senderType = data.sender_type || data.senderType; // Numeric: 0=admin, 1=provider, 2=customer

            // Current open chat context (if user is on chat page).
            var currentSenderId = $('#sender_id').val(); // logged-in admin id
            var currentReceiverId = $('#receiver_id').val(); // selected customer id
            var currentOrderId = ($('#order_id').length ? $('#order_id').val() : '');
            var currentChatUserType = ($('#user_type_for_send_message').length ? $('#user_type_for_send_message').val() : '');

            var receiverIdMatch = receiverId == currentSenderId;
            var senderIdMatch = senderId == currentReceiverId;

            // Booking chat match (booking id present and matches current thread).
            var bookingIdMatch = (bookingId != "" && bookingId != null) && (currentOrderId == bookingId);

            // Enquiry chat match (currentOrderId like "enquire_<enquiryId>_<chatId>").
            var enquiryIdMatch = false;
            if (typeof currentOrderId === 'string' && currentOrderId.indexOf('enquire_') === 0) {
                var parts = currentOrderId.split('_');
                var enquiryIdFromOrderId = (parts.length >= 2) ? parts[1] : '';
                enquiryIdMatch = enquiryIdFromOrderId != '' && enquiryIdFromOrderId == enquiryId;
            }

            // Auto-append ONLY when this push belongs to the currently open conversation.
            // This mirrors partner panel behavior and prevents appending into the wrong chat.
            var isChatOpen = $('.chat-area-main').length > 0 && currentSenderId && currentReceiverId;

            /**
             * Safety: Avoid cross-type auto-append.
             *
             * Why:
             * - Admin can chat with both providers and customers.
             * - IDs can overlap across tables in some systems.
             * - Payload includes numeric `sender_type` (0/1/2). Admin chat UI stores
             *   the active chat user type as a string: "provider" or "customer".
             */
            var senderTypeMatchesOpenChat = true;
            if (currentChatUserType === 'provider' && senderType != null && senderType !== '' && senderType != '1') {
                senderTypeMatchesOpenChat = false;
            }
            if (currentChatUserType === 'customer' && senderType != null && senderType !== '' && senderType != '2') {
                senderTypeMatchesOpenChat = false;
            }

            // Notify admin chat page (badge update + auto mark-as-read).
            // Defined in app/Views/backend/admin/pages/chat.php — only present
            // when admin is on the chat page. Safe no-op elsewhere.
            if (typeof window.onAdminChatMessageReceived === 'function' && viewerType == "admin") {
                try { window.onAdminChatMessageReceived(data); } catch (_e) {}
            } else if (viewerType == "admin" && (data.type == "new_message" || data.chat_message)) {
                // Admin not on chat page — chat page hook is missing, but the
                // sidebar Chat badge still needs to reflect the new unread *user*
                // count so the admin sees activity from any other page.
                // Pull the canonical count from the server so multiple messages
                // from the same user don't inflate the badge.
                if (typeof window.refreshAdminChatSidebarBadge === 'function') {
                    window.refreshAdminChatSidebarBadge();
                }
            }

            if (isChatOpen && receiverIdMatch && senderIdMatch && senderTypeMatchesOpenChat && (bookingIdMatch || enquiryIdMatch) && viewerType == "admin") {
                processIncomingChatMessage(payload);
            } else if (isChatOpen && receiverIdMatch && senderIdMatch && senderTypeMatchesOpenChat && data.type == "new_message" && viewerType == "admin") {
                // Some templates may not include booking_id/e_id. Still append if this is the active chat.
                processIncomingChatMessage(payload);
            } else if (isChatOpen && receiverIdMatch && senderIdMatch && senderTypeMatchesOpenChat && data.chat_message) {
                // Lean NotificationService payload: chat_message + chat_user only.
                // No viewer_type or type key — just match sender/receiver and append.
                processIncomingChatMessage(payload);
            }

            // Notifications are optional. Show them only if allowed AND chat is NOT open.
            // If chat is open, message auto-appends so no notification popup needed.
            // (Do NOT block chat updates when notifications are denied.)
            if (data && (data.type == "job_notification" || data.type == "new_message")) {
                // Check if chat is open for this message (re-use same logic as auto-append above).
                var shouldShowNotification = true;
                if (isChatOpen && receiverIdMatch && senderIdMatch && senderTypeMatchesOpenChat) {
                    // Chat is open for this message — suppress notification since message auto-appends.
                    shouldShowNotification = false;
                }

                if (shouldShowNotification) {
                    var title = (payload.notification && payload.notification.title) ? payload.notification.title : (data.title || 'Notification');
                    var body = (payload.notification && payload.notification.body) ? payload.notification.body : (data.body || data.message || '');

                    requestBrowserNotificationPermissionIfNeeded().then((status) => {
                        if (status === 'granted') {
                            try {
                                new Notification(title, {
                                    body: body,
                                    icon: payload.notification ? payload.notification.icon : undefined
                                });
                            } catch (e) {}
                        }
                    });
                }
            }
        } catch (err) {
            console.error('FCM onMessage handler error (Admin panel):', err, payload);
        }
    });

    function getMessageDateHeading(date) {
        var today = new Date();
        var yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        if (date.toDateString() === today.toDateString()) {
            return '<div class="chat_divider"><div>Today</div></div>';
        } else if (date.toDateString() === yesterday.toDateString()) {
            return '<div class="chat_divider"><div>Yesterday</div></div>';
        } else {
            return '<div class="chat_divider"><div>' + date.toLocaleDateString() + '</div></div>'; // Display full date if not today or yesterday
        }
    }
</script>
<script>
    // Only try to render Recaptcha on pages that:
    // 1) Have the "rec" container present, and
    // 2) Have firebase + firebase.auth loaded.
    // This avoids JS errors on pages that do not use phone / Recaptcha.
    if (
        typeof firebase !== 'undefined' && // firebase script is loaded
        firebase.auth && // auth module is available
        document.getElementById('rec') // container exists on this page
    ) {
        render();
    }

    function render() {
        // Extra safety: check again inside the function.
        if (
            typeof firebase === 'undefined' ||
            !firebase.auth ||
            !document.getElementById('rec')
        ) {
            // If any requirement is missing, do nothing.
            // This keeps the code safe and quiet on non-Recaptcha pages.
            return;
        }

        // Create and render the Recaptcha widget.
        window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier('rec');
        recaptchaVerifier.render();
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.3/dist/extensions/fixed-columns/bootstrap-table-fixed-columns.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script src="<?= base_url('public/backend/assets/js/SlugHelper.js') ?>"></script>

<!-- Queue Progress Tray -->
<script>var queueStatusUrl = "<?= site_url('admin/notification/queue-status') ?>";</script>
<script src="<?= base_url('public/backend/assets/js/queue-tray.js') ?>"></script>