<script src="<?= base_url('public/backend/assets/js/vendor/popper.min.js') ?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.js"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/bootstrap.min.js') ?>">
</script>
<script src="<?= base_url('public/backend/assets/js/vendor/jquery.nicescroll.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/moment.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/stisla.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/iziToast.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/bootstrap-table.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/select2.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/sweetalert.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/iconify.min.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/cropper.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/dropzone.js') ?>"></script>
<script type="text/javascript" src="<?= base_url('public/backend/assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>
<!-- start :: include FilePond library -->
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
<!-- <script src="http://abpetkov.github.io/switchery/dist/switchery.min.js"></script> -->
<script src="<?= base_url('public/backend/assets/js/switchery.min.js') ?>"></script>
<!-- for swithchery js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.js"></script>
<script src="https://js.stripe.com/v3/"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.8.0/dist/chart.min.js"></script>
<script src="<?= base_url('public/backend/assets/js/vendor/daterangepicker.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/googleMap.js') ?>"></script>
<!-- Slug auto-fill from title (service/partner forms) -->
<script src="<?= base_url('public/backend/assets/js/SlugHelper.js') ?>"></script>
<?php
echo '<script src="' . base_url('public/backend/assets/js/vendor/chart.min.js') . '"></script>';
?>
<?php
switch ($main_page) {
	case "../../text_to_speech":
		echo '<script src="' . base_url('public/backend/assets/js/page/tts.js') . '"></script>';
		break;
	case "forms/service_form":
		echo '<script src="' . base_url('public/backend/assets/js/service-stepper.js') . '?v=' . get_system_version() . '"></script>';
		break;
	case "profile":
	case "working_hours":
		echo '<script src="' . base_url('public/backend/assets/js/provider-stepper.js') . '?v=' . get_system_version() . '"></script>';
		break;
	case "checkout":
		echo '<script src="https://checkout.razorpay.com/v1/checkout-frame.js"></script>';
		echo '<script src="' . base_url('public/backend/assets/js/vendor/paystack-v1.js') . '"></script>';
		echo '<script src="' . base_url('public/backend/assets/js/page/checkout.js') . '"></script>';
		echo '<script src="https://js.stripe.com/v3/"></script>';
		echo '<script src="https://js.paystack.co/v1/inline.js"></script>';
		break;
	case "plans":
		echo '<script src="' . base_url('public/backend/assets/js/page/admin_plans.js') . '"></script>';
		break;
}
?>
<?php
$api_key = get_settings('api_key_settings', true);

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
<script>
	let are_your_sure = "<?php echo labels('are_your_sure', 'Are you sure?') ?>";
	let yes_proceed = "<?php echo labels('yes_proceed', 'Yes, Proceed!') ?>";
	let you_wont_be_able_to_revert_this = "<?php echo labels('you_wont_be_able_to_revert_this', "You won't be able to revert this!") ?>";
	let are_you_sure_you_want_to_deactivate_this_user = "<?php echo labels('are_you_sure_you_want_to_deactivate_this_user', 'Are you sure you want to deactivate this user') ?>"
	let are_you_sure_you_want_to_delete_this_user = "<?php echo labels('are_you_sure_you_want_to_delete_this_user', 'Are you sure you want to delete this user') ?>"
	let are_you_sure_you_want_to_activate_this_user = "<?php echo labels('are_you_sure_you_want_to_activate_this_user', 'Are you sure you want to activate this user') ?>"
	let cancel = "<?php echo labels('cancel', 'Cancel') ?>";
	let enter_otp_here = "<?php echo labels('enter_otp_here', 'Enter OTP here'); ?>";
	let be_aware_this_shall_fordid_the_data = "<?php echo labels('be_aware_this_shall_fordid_the_data', 'Be aware this shall fordid the data'); ?>";
	// FilePond custom message variables
	let select_files = "<?php echo labels('select_files', 'Select Files') ?>"
	let or = "<?php echo labels('or', 'Or') ?>"
	let browse_files = "<?php echo labels('browse_files', 'Browse Files') ?>"
	let drag_and_drop_files_here = "<?php echo labels('drag_and_drop_files_here', 'Drag & Drop files here') ?>"
	let file_of_invalid_type = "<?php echo labels('file_of_invalid_type', 'File of invalid type') ?>"
	let file_is_too_large = "<?php echo labels('file_is_too_large', 'File is too large') ?>"
	let maximum_file_size_is = "<?php echo labels('maximum_file_size_is', 'Maximum file size is') ?>"
	let invalid_file_type_please_upload_an_excel_or_csv_file = "<?php echo labels('invalid_file_type_please_upload_an_excel_or_csv_file', 'Invalid file type. Please upload an Excel or CSV file.') ?>"
	let on = "<?php echo labels('on', 'On') ?>"
	let off = "<?php echo labels('off', 'Off') ?>"
	let you_want_to_update_order_status = "<?php echo labels('you_want_to_update_order_status', 'You want to update order status!') ?>"
	let call_now = "<?php echo labels('call_now', 'Call Now') ?>"
	let could_not_find_sub_categories_on_this_category_please_change_categories = "<?php echo labels('could_not_find_sub_categories_on_this_category_please_change_categories', 'Could not find sub categories on this category Please change categories') ?>"
	let select_category = "<?php echo labels('select_category', 'Select Category') ?>"
	let select_sub_category = "<?php echo labels('select_sub_category', 'Select sub Category') ?>"
	// Loader text shared with JS so button states respect translations.
	let please_wait_text = "<?php echo labels('please_wait', 'Please wait...') ?>"

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
		email: "<?php echo labels('fv_email', 'Please enter a valid email address') ?>",
		min: "<?php echo labels('fv_min', 'Must be at least %d characters') ?>",
		max: "<?php echo labels('fv_max', 'Must not exceed %d characters') ?>",
		pwdMin: "<?php echo labels('fv_pwd_min', 'At least %d characters') ?>",
		field: "<?php echo labels('field', 'Field') ?>",
		pwdRequirements: "<?php echo labels('fv_pwd_requirements', 'Password does not meet the requirements. Please check the rules above.') ?>",
	};
</script>

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

<script src="<?= base_url('public/backend/assets/js/partner_events.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/map-provider.js') . '?v=' . get_system_version() ?>"></script>
<script src="<?= base_url('public/backend/assets/js/live-handyman-map.js') . '?v=' . get_system_version() ?>"></script>
<script src="<?= base_url('public/backend/assets/js/live-tracking-map.js') . '?v=' . get_system_version() ?>"></script>
<script src="<?= base_url('public/backend/assets/js/scripts.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/bootstrap-translations.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/switch-translations.js') ?>"></script>
<script src="<?= base_url('public/frontend/retro/js/password-strength.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/form-validator.js') ?>"></script>
<script src="<?= base_url('public/backend/assets/js/partner.js') ?>"></script>
<!-- Central notification click redirect handler (partner-first, admin-ready) -->
<script src="<?= base_url('public/backend/assets/js/notification_redirects.js') ?>"></script>
<!-- Microsoft Clarity Event Tracking -->
<script src="<?= base_url('public/backend/assets/js/partner-clarity-events.js') ?>"></script>
<script>
	// Initialize Bootstrap table translations when the page loads
	$(document).ready(function () {
		if (typeof updateSwitchText === 'function') {
			updateSwitchText();
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

<script>
	/*
		Partner panel (mobile/tablet) layout fix: prevent navbar overlap.

		Why:
		- `.main-content` uses a static `padding-top: 80px` from the theme.
		- The partner navbar can become taller on small screens (wrapping items).
		- So the fixed navbar covers the first part of the page.

		What we do:
		- Measure `.main-navbar` height and store it in a CSS variable:
			`--partner-navbar-height`
		- CSS (in `include-css.php`) uses that variable to pad `.main-content`.
	*/
	(function () {
		function updatePartnerNavbarHeightVar() {
			var $nav = $(".main-navbar");
			if (!$nav.length) return;

			// Ensure we never set a smaller value than the theme's default (80px).
			var h = Math.ceil($nav.outerHeight() || 0);
			h = Math.max(h, 80);

			document.documentElement.style.setProperty("--partner-navbar-height", h + "px");
		}

		// Update on initial render.
		$(document).ready(function () {
			updatePartnerNavbarHeightVar();

			// Re-run after layout settles (fonts, badges, etc.).
			setTimeout(updatePartnerNavbarHeightVar, 250);
			setTimeout(updatePartnerNavbarHeightVar, 1000);
		});

		// Update on resize/orientation changes.
		$(window).on("load resize orientationchange", function () {
			updatePartnerNavbarHeightVar();
		});
	})();
</script>

<script src="https://unpkg.com/@yaireo/tagify"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script type="text/javascript" src="<?= base_url('public/backend/assets/js/tableExport.min.js') ?>"></script>
<script>
	// The DOM element you wish to replace with Tagify
	if (document.getElementById("service_tags") != null) {
		$(document).ready(function () {
			var input = document.querySelector('input[id=service_tags]');
			new Tagify(input)
		});
	}
	if (document.getElementById("service_tags_update") != null) {
		$(document).ready(function () {
			var input = document.querySelector('input[id=service_tags_update]');
			new Tagify(input)
		});
	}
	// initialize Tagify on the above input node reference
</script>
<script>
</script>
<!-- <script src="http://localhost/edemand/public/backend/assets/js/window_event.js"></script> -->
<script
	src="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.3/dist/extensions/fixed-columns/bootstrap-table-fixed-columns.min.js"></script>
</head>
<script src="https://www.gstatic.com/firebasejs/8.2.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.2.0/firebase-messaging.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.2.0/firebase-analytics.js"></script>

<?php
$firebase_setting = get_settings('firebase_settings', true);
?>
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
	// Initialize Firebase Analytics using v8 namespaced API
	// Note: getAnalytics() is from Firebase v9+ modular SDK, but we're using v8.2.0
	// In v8, use firebase.analytics() instead
	const analytics = firebase.analytics();
	const fcm = firebase.messaging();
	/**
	 * Partner Panel: ensure Firebase service worker is registered via our CI route.
	 *
	 * Problem:
	 * - When a page is under `/partner/...`, any *relative* SW registration like
	 *   `firebase-messaging-sw.js` becomes `/partner/firebase-messaging-sw.js`.
	 * - That makes the browser "look for a file" under the partner path and fails.
	 *
	 * Fix:
	 * - Always register the service worker using the absolute routed URL:
	 *   `/firebase-messaging-sw.js` (served by `Frontend::firebaseServiceWorker`).
	 * - Then request the FCM token using that specific SW registration.
	 *
	 * Notes:
	 * - This mirrors the admin panel behavior (using the routed SW), but keeps
	 *   the change local to the partner panel to avoid regressions.
	 */
	const vapidKey = "<?= isset($firebase_setting['vapidKey']) ? $firebase_setting['vapidKey'] : 0 ?>";
	const serviceWorkerUrl = "<?= base_url('firebase-messaging-sw.js') ?>";

	// Extracted to a function to keep the flow simple and readable.
	function savePartnerWebToken(token) {
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
			url: baseUrl + '/partner/save_web_token',
			type: 'POST',
			data: {
				token: token,
				language_code: languageCode
			},
			dataType: 'JSON'
		});
	}

	// Register the SW via our route first, then ask Firebase for the token.
	// If registration fails for any reason, fall back to the old behavior.
	if ('serviceWorker' in navigator) {
		navigator.serviceWorker.register(serviceWorkerUrl)
			.then((registration) => {
				return fcm.getToken({
					vapidKey: vapidKey,
					serviceWorkerRegistration: registration
				});
			})
			.then((token) => {
				return savePartnerWebToken(token);
			})
			.catch(() => {
				// Fallback: keep old behavior to avoid breaking token generation
				// on environments where SW registration is blocked.
				return fcm.getToken({
					vapidKey: vapidKey
				}).then((token) => savePartnerWebToken(token));
			});
	} else {
		// Very old browsers: no service worker support.
		// Keep the previous behavior (token may not work for background pushes).
		fcm.getToken({
			vapidKey: vapidKey
		}).then((token) => savePartnerWebToken(token));
	}

	/**
	 * Foreground FCM handler (Provider/Partner panel).
	 *
	 * Notes (very important for debugging):
	 * - `fcm.onMessage()` fires ONLY when the page is open (foreground).
	 * - UI auto-append must NOT depend on browser notification permission.
	 *   Even if notifications are blocked, chat should still update.
	 * - `Notification.requestPermission(callback)` is deprecated and may not
	 *   invoke the callback in modern browsers. Use the Promise API instead.
	 */
	function requestBrowserNotificationPermissionIfNeeded() {
		// Keep this helper tiny and safe. We only request permission when needed.
		if (!('Notification' in window)) return Promise.resolve('unsupported');
		if (Notification.permission === 'granted') return Promise.resolve('granted');
		if (Notification.permission === 'denied') return Promise.resolve('denied');
		// Permission is 'default' (not decided yet)
		return Notification.requestPermission().catch(() => 'default');
	}

	/**
	 * Escape HTML to prevent XSS when we need a lightweight append.
	 * Keep it tiny and predictable.
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
	 * Chat auto-scroll helper (Partner panel).
	 *
	 * Problem we are fixing:
	 * - When messages are auto-appended (FCM foreground push), we used
	 *   `$('.myscroll').animate({ scrollTop: ... })` for every message.
	 * - When multiple messages arrive quickly, jQuery animations queue/overlap,
	 *   and the scroll position "jitters" (looks like a glitch).
	 * - Also, message height can change after append (images/videos load),
	 *   so scrolling before the browser paints can land at the wrong position.
	 *
	 * Solution:
	 * - Only auto-scroll if the user is already near the bottom.
	 * - Scroll after layout/paint using `requestAnimationFrame` (twice).
	 * - Avoid smooth animations for auto-append; direct scroll is most stable.
	 */
	function getChatScrollerElement() {
		// NOTE: Different chat templates in this project use different containers.
		// We pick the first element that is truly scrollable.
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

		// If nothing looks scrollable (small chat), just use the main container.
		return chatMain;
	}

	function isNearChatBottom(scroller, thresholdPx) {
		// Threshold: within this many pixels from bottom = "near bottom".
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

		// Scroll after layout paint (twice) for stability.
		if (typeof requestAnimationFrame === 'function') {
			requestAnimationFrame(function () {
				doScroll();
				requestAnimationFrame(doScroll);
			});
		} else {
			doScroll();
		}

		// Fallback for slower rendering (fonts/media/layout).
		setTimeout(doScroll, 250);
	}

	function autoScrollChatToBottomIfUserWasNearBottom(userWasNearBottom) {
		// We only auto-scroll for "auto append" when the user is already
		// reading the latest messages. If they scrolled up, do nothing.
		if (!userWasNearBottom) return;
		scrollChatToBottomAfterPaint(getChatScrollerElement());
	}

	/**
	 * Where to insert new message bubbles. `provider_chats.php` nests the actual
	 * list in `#chat-messages-list` inside `.chat-area-main`; `admin_chat.php`
	 * has no such nested list and uses `.chat-area-main` directly. Prefer the
	 * nested list when present so we never insert as a sibling of the list.
	 */
	function getChatAppendTarget() {
		var $nested = $('#chat-messages-list');
		return $nested.length ? $nested : $('.chat-area-main');
	}

	/**
	 * Fallback renderer for `new_message` pushes that do not include the full
	 * chat payload (sender_details/file/etc). We append a simple text bubble.
	 *
	 * This prevents "foreground push received but nothing appended" issues.
	 */
	function appendSimpleChatMessage(messageText, profileImageUrl) {
		// Capture scroll intent BEFORE we append. (Append changes scrollHeight.)
		var scrollerBefore = getChatScrollerElement();
		var userWasNearBottom = isNearChatBottom(scrollerBefore);

		const timeAgo = new Date().toLocaleTimeString();
		const safeMsg = escapeHtml(messageText);
		let html = '';
		html += '<div class="chat-msg">';
		html += '<div class="chat-msg-content">';
		html += '<div class="chat-msg-text">' + safeMsg + '</div>';
		html += '<div class="chat-msg-profile">';
		if (profileImageUrl) {
			html += '<img class="chat-msg-img" src="' + escapeHtml(profileImageUrl) + '" alt="" />';
		} else {
			// Minimal placeholder. (We avoid complex DOM here on purpose.)
			html += '<div class="chat-msg-img-placeholder color-1">U</div>';
		}
		html += '<div class="chat-msg-date">' + escapeHtml(timeAgo) + '</div>';
		html += '</div>';
		html += '</div>';
		html += '</div>';

		getChatAppendTarget().append(html);
		// Stable auto-scroll: no animation, only if user was at bottom.
		autoScrollChatToBottomIfUserWasNearBottom(userWasNearBottom);
	}

	fcm.onMessage((payload) => {
		// Always log the payload first. This confirms if `onMessage` is firing at all.
		// console.log('FCM foreground message (Provider panel):', payload);

		// FCM web payload typically contains `payload.data` for custom keys.
		// Guard everything so one missing key doesn't break the whole handler.
		let data = (payload && payload.data) ? payload.data : {};

		// --- Lean NotificationService payload support ---
		// The new NotificationService sends all chat fields inside a stringified
		// `chat_message` key (+ `chat_user` which is null for admin-sent messages).
		// Parse `chat_message` and merge into `data` so downstream code
		// (hasRichChatPayload, processMessage, appendSimpleChatMessage) works as-is.
		if (data.chat_message && typeof data.chat_message === 'string') {
			try {
				const parsed = JSON.parse(data.chat_message);
				if (parsed && typeof parsed === 'object') {
					// Merge parsed chat fields into data. Existing top-level keys
					// (e.g. booking_id, type) take precedence to avoid overwriting.
					data = Object.assign({}, parsed, data);
					// Also update payload.data so processMessage() (which reads
					// payload.data directly) picks up the unwrapped fields.
					payload.data = data;
				}
			} catch (_e) {
				// chat_message was not valid JSON — continue with original data.
			}
		}

		try {
			// Support both snake_case and camelCase payloads.
			const receiverId = data.receiver_id || data.receiverId;
			const senderId = data.sender_id || data.senderId || data.user_id;
			const bookingId = data.booking_id || data.bookingId;
			const enquiryId = data.e_id || data.eId; // Used for "enquire_*" chat threads
			const viewerType = data.viewer_type || data.viewerType;
			const senderType = data.sender_type || data.senderType;

			// Notify partner chat pages (badge update + auto mark-as-read).
			// Hooks are defined in:
			//   - app/Views/backend/partner/pages/provider_chats.php (customer chats)
			//   - app/Views/backend/partner/pages/admin_chat.php (admin support)
			// Both are safe no-ops when not present.
			if (typeof window.onPartnerChatMessageReceived === 'function') {
				try { window.onPartnerChatMessageReceived(data); } catch (_e) { }
			}
			if (typeof window.onPartnerAdminChatMessageReceived === 'function') {
				try { window.onPartnerAdminChatMessageReceived(data); } catch (_e) { }
			}

			/**
			 * Sidebar unread badges (Admin Support + Chat) — refresh on any chat-related push.
			 *
			 * Why here:
			 * - Page-level hooks above only fire when the partner is on a chat page.
			 * - The sidebar badges live in `top_and_sidebar.php` and are rendered on every page,
			 *   so they need a global trigger when a new chat push arrives (dashboard, settings, etc).
			 * - We call the canonical helper exposed in `top_and_sidebar.php`, which queries
			 *   the server (the only source of truth for distinct sender / conversation counts).
			 */
			const isChatPush = !!(data.chat_message)
				|| data.type === 'new_message'
				|| viewerType === 'admin'
				|| viewerType === 'provider'
				|| viewerType === 'provider_booking';
			if (isChatPush) {
				// Instant feedback on pages without their own row-based badge sync
				// (provider_chats.php defines onPartnerChatMessageReceived and keeps
				// the badge exact locally via bumpUnreadBadge/recomputePartnerChatSidebarBadge —
				// skip here to avoid double-counting the same push).
				if (String(senderType) === '2' && typeof window.onPartnerChatMessageReceived !== 'function'
					&& typeof window.bumpPartnerChatSidebarBadgeOptimistic === 'function') {
					try { window.bumpPartnerChatSidebarBadgeOptimistic(); } catch (_e) { }
				}
				if (typeof window.refreshPartnerChatSidebarBadges === 'function') {
					try { window.refreshPartnerChatSidebarBadges(); } catch (_e) { }
				}
			}

			const receiverIdMatch = receiverId == $('#sender_id').val();
			const senderIdMatch = senderId == $('#receiver_id').val();
			const currentOrderId = ($('#order_id').length ? $('#order_id').val() : '');

			// Booking chat: bookingId exists and should match the current order id.
			const bookingIdMatch = (bookingId != "" && bookingId != null) && currentOrderId == bookingId;

			// Enquiry chat: currentOrderId looks like "enquire_<enquiryId>_<chatId>" and bookingId is empty.
			// This is how provider panel stores "pre-booking" chat threads.
			let enquiryIdMatch = false;
			if (typeof currentOrderId === 'string' && currentOrderId.indexOf('enquire_') === 0) {
				const parts = currentOrderId.split('_');
				// Format: enquire_{en_id}_{order_id}
				const enquiryIdFromOrderId = (parts.length >= 2) ? parts[1] : '';
				enquiryIdMatch = enquiryIdFromOrderId != '' && enquiryIdFromOrderId == enquiryId;
			}

			// Auto-append chat messages if they belong to the currently open conversation.
			// NOTE: `data.message` is intentionally not required here — attachment-only
			// messages (image/file, no caption) send an empty string and must still
			// render via the rich renderer (which draws the attachment).
			const hasRichChatPayload = !!(data.sender_details);

			if ((receiverIdMatch && senderIdMatch) && bookingIdMatch && viewerType == "provider_booking") {
				// Booking chat thread
				if (hasRichChatPayload) {
					processMessage(payload);
				} else {
					// Fallback: append simple text (notification body/body)
					const msgText = data.message || (payload.notification ? payload.notification.body : '') || data.body || '';
					appendSimpleChatMessage(msgText, data.profile_image || data.profile || data.image || '');
				}
			} else if ((receiverIdMatch && senderIdMatch) && enquiryIdMatch && viewerType == "provider") {
				// Customer → Provider (enquiry / pre-booking thread)
				if (hasRichChatPayload) {
					processMessage(payload);
				} else {
					const msgText = data.message || (payload.notification ? payload.notification.body : '') || data.body || '';
					appendSimpleChatMessage(msgText, data.profile_image || data.profile || data.image || '');
				}
			} else if (receiverIdMatch && viewerType == "admin") {
				// Provider ↔ Admin support thread
				if (hasRichChatPayload) {
					processMessage(payload);
				} else {
					const msgText = data.message || (payload.notification ? payload.notification.body : '') || data.body || '';
					appendSimpleChatMessage(msgText, data.profile_image || data.profile || data.image || '');
				}
			} else if (data.type == "new_message" && senderIdMatch) {
				// Generic chat push (some templates only send title/body + senderId).
				const msgText = data.message || (payload.notification ? payload.notification.body : '') || data.body || '';
				appendSimpleChatMessage(msgText, data.profile_image || data.profile || data.image || '');
			}

			// Notifications are optional. Show them only if allowed AND chat is NOT open.
			// If chat is open, message auto-appends so no notification popup needed.
			// (Do NOT block chat updates when notifications are denied.)
			if (data && (data.type == "job_notification" || data.type == "new_message" || data.chat_message)) {
				// Check if chat is open for this message (re-use same logic as auto-append above).
				let shouldShowNotification = true;
				if ((receiverIdMatch && senderIdMatch) && bookingIdMatch && viewerType == "provider_booking") {
					// Booking chat thread is open — suppress notification.
					shouldShowNotification = false;
				} else if ((receiverIdMatch && senderIdMatch) && enquiryIdMatch && viewerType == "provider") {
					// Customer chat (enquiry/pre-booking) is open — suppress notification.
					shouldShowNotification = false;
				} else if (receiverIdMatch && senderId == $('#receiver_id').val() && viewerType == "admin") {
					// Admin support thread is open — suppress only if message sender matches the admin in the open chat.
					shouldShowNotification = false;
				} else if (data.type == "new_message" && senderIdMatch) {
					// Generic new_message auto-append path: suppress if message is from currently open customer.
					shouldShowNotification = false;
				}

				if (shouldShowNotification) {
					const title = (payload.notification && payload.notification.title) ? payload.notification.title : (data.title || 'Notification');
					const body = (payload.notification && payload.notification.body) ? payload.notification.body : (data.body || data.message || '');

					requestBrowserNotificationPermissionIfNeeded().then((status) => {
						if (status === 'granted') {
							try {
								new Notification(title, {
									body: body,
									icon: payload.notification ? payload.notification.icon : undefined
								});
							} catch (e) {
								console.error('[FCM Notification] Error showing notification:', e);
							}
						} else {
							console.log('[FCM Notification] Permission not granted, status:', status);
						}
					});
				}
			}
		} catch (err) {
			console.error('FCM onMessage handler error (Provider panel):', err, payload);
		}
	});

	function processMessage(data) {
		// `data` here is the full FCM payload. Chat fields are in `data.data`.
		// IMPORTANT:
		// - FCM payloads are not always perfectly consistent (especially when some templates
		//   are used, or when a message has no attachment).
		// - We must be defensive here. One missing key should NOT break auto-append.

		const payloadData = (data && data.data) ? data.data : {};

		// sender_details is stored as JSON (usually an array). Handle empty/invalid safely.
		let senderDetails = {};
		try {
			const parsedSender = JSON.parse(payloadData.sender_details || '[]');
			senderDetails = Array.isArray(parsedSender) ? (parsedSender[0] || {}) : (parsedSender || {});
		} catch (e) {
			senderDetails = {};
		}

		const message = payloadData.message || '';

		// file can be a JSON string or already-encoded. Default to an empty array.
		let files = [];
		try {
			const parsedFiles = JSON.parse(payloadData.file || '[]');
			// Legacy format in this project is often `[ [ ...files ] ]`
			if (Array.isArray(parsedFiles) && Array.isArray(parsedFiles[0])) {
				files = parsedFiles[0];
			} else if (Array.isArray(parsedFiles)) {
				files = parsedFiles;
			} else {
				files = [];
			}
		} catch (e) {
			files = [];
		}

		// file_type may be missing for normal (non-attachment) messages.
		const fileType = (payloadData.file_type || '').toString().toLowerCase();

		const profileImage = senderDetails.image || payloadData.profile_image || payloadData.profile || payloadData.image || '';
		const senderName = payloadData.sender_name || senderDetails.username || senderDetails.name || 'U';
		const messageDate = new Date();
		let hours = messageDate.getHours();
		let minutes = messageDate.getMinutes();
		let ampm = hours >= 12 ? "PM" : "AM";
		hours = hours % 12;
		hours = hours ? hours : 12;
		minutes = minutes < 10 ? "0" + minutes : minutes;
		const timeAgo = hours + ":" + minutes + " " + ampm;
		let dateStr = '';
		let lastDisplayedDate = new Date(payloadData.last_message_date);

		if (!lastDisplayedDate || messageDate.toDateString() !== lastDisplayedDate.toDateString()) {
			dateStr = getMessageDateHeading(messageDate);
			lastDisplayedDate = messageDate;
		}

		let html = dateStr;
		html += '<div class="chat-msg">';
		html += '<div class="chat-msg-content">';
		html += '<div class="chat-msg-bubble-wrap">';
		const chatMessageHTML = renderChatMessage(payloadData, files);
		html += chatMessageHTML;
		if (files && files.length > 0) {
			files.forEach(function (file) {
				html += generateFileHTML(file);
			});
		}
		html += '</div>';
		html += '<div class="chat-msg-profile">';
		if (!profileImage || profileImage === '' || profileImage === 'null' || profileImage === 'undefined' || !isValidImageUrlClient(profileImage)) {
			const firstLetter = senderName.charAt(0).toUpperCase();
			const colorClass = 'color-' + ((senderName.charCodeAt(0) % 8) + 1);
			html += '<div class="chat-msg-img-placeholder ' + colorClass + '">' + firstLetter + '</div>';
		} else {
			html += '<img class="chat-msg-img" src="' + escapeHtml(profileImage) + '" alt="' + escapeHtml(senderName) + '" onerror="handleImageError(this, \'' + escapeHtml(senderName) + '\', \'chat-msg-img-placeholder\')" />';
		}
		html += '<div class="chat-msg-date">' + timeAgo + '</div>';
		html += '</div>';
		html += '</div>';
		html += '</div>';
		// Capture scroll intent BEFORE we append. (Append changes scrollHeight.)
		// Note: We compute this *again* here because `processMessage` can be called
		// directly (bypassing `appendSimpleChatMessage`).
		var scrollerBefore = getChatScrollerElement();
		var userWasNearBottom = isNearChatBottom(scrollerBefore);

		getChatAppendTarget().append(html);
		// Stable auto-scroll: no animation, only if user was at bottom.
		autoScrollChatToBottomIfUserWasNearBottom(userWasNearBottom);
	}

	function getFileName(file) {
		return file.substring(file.lastIndexOf('/') + 1);
	}





	function getMessageDateHeading(date) {
		var today = new Date();
		var yesterday = new Date(today);
		yesterday.setDate(today.getDate() - 1);
		if (date.toDateString() === today.toDateString()) {
			return '<div class="chat_divider"><div><?= labels('today', 'Today') ?></div></div>';
		} else if (date.toDateString() === yesterday.toDateString()) {
			return '<div class="chat_divider"><div><?= labels('yesterday', 'Yesterday') ?></div></div>';
		} else {
			return '<div class="chat_divider"><div>' + date.toLocaleDateString() + '</div></div>'; // Display full date if not today or yesterday
		}
	}
</script>