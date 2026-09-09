<script data-turbo-eval="false"
    src="<?= base_url('public/frontend/retro/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/moment.min.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/vendor/jquery.nicescroll.min.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/handyman-panel.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/iziToast.min.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/vendor/bootstrap-table.min.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/select2.min.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/sweetalert.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/iconify.min.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/cropper.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/dropzone.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/filepond/dist/filepond.min.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-image-preview.min.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-pdf-preview.min.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-file-validate-size.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-file-validate-type.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-image-validate-size.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/filepond/dist/filepond.jquery.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/switchery.min.js') ?>"></script>
<script data-turbo-eval="false" src="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.js"></script>
<script data-turbo-eval="false" src="https://js.stripe.com/v3/"></script>
<script data-turbo-eval="false" src="https://cdn.jsdelivr.net/npm/chart.js@3.8.0/dist/chart.min.js"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/daterangepicker.js') ?>"></script>
<?php
$apiKey = get_settings('api_key_settings', true);
$mapApiKey = $apiKey['google_map_api'] ?? '';
$placesApiKey = $apiKey['google_places_api'] ?? '';
$mapProvider = get_map_provider();
?>
<script>
    var MAP_PROVIDER = '<?= esc($mapProvider) ?>';
    window.MAP_PROVIDER = MAP_PROVIDER;
</script>
<?php if ($mapProvider === 'openstreetmap'): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script data-turbo-eval="false" src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/googleMap.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/SlugHelper.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/chart.min.js') ?>"></script>

<!-- Firebase SDK — loaded once here (not page-scoped) so it's present before any Turbo soft-navigation.
     Script elements Turbo re-inserts on a morph load asynchronously (browser default for JS-appended
     <script src>), unlike parser-inserted tags on a hard load — a page-scoped loader would race with
     the page's own init code that expects `firebase` to already be defined. -->
<script data-turbo-eval="false" src="https://www.gstatic.com/firebasejs/8.2.0/firebase-app.js"></script>
<script data-turbo-eval="false" src="https://www.gstatic.com/firebasejs/8.2.0/firebase-messaging.js"></script>

<!-- Turbo (Hotwire) for SPA feel -->
<script type="module" data-turbo-eval="false"
    src="https://unpkg.com/@hotwired/turbo@8.0.4/dist/turbo.es2017-esm.js"></script>

<script data-turbo-eval="false">

    var BootstrapTableTurbo = (function () {
        var SELECTOR = '[data-toggle="table"]';

        function abortPendingRequest($t) {
            var instance = $t.data('bootstrap.table');
            if (instance && instance._xhr && instance._xhr.readyState !== 4) {
                instance._xhr.abort();
            }
        }

        function initTable($t) {
            if (!$.fn.bootstrapTable) return;

            // Prevent double-init collisions when turbo:load and turbo:frame-load fire simultaneously
            if ($t.data('turbo-initializing')) return;
            $t.data('turbo-initializing', true);
            setTimeout(function () { $t.data('turbo-initializing', false); }, 50);

            // Clean up any lingering internal state from previous morphed visits
            if ($t.data('bootstrap.table')) {
                abortPendingRequest($t);
                $t.bootstrapTable('destroy');
            }

            // CRITICAL FOR TURBO 8 IDIOMORPH:
            // Idiomorph preserves <tbody> structures. If it leaves behind a stale <tbody> from a previous visit, 
            // Bootstrap Table assumes the table is pre-populated and skips the initial AJAX fetch!
            // We must clear the <tbody> before initialization so it correctly auto-fetches.
            $t.find('tbody').empty();

            // Explicitly extract all data-* attributes to build the options object.
            var options = {};
            $.each($t[0].attributes, function (i, attr) {
                if (attr.name.startsWith('data-')) {
                    var optName = attr.name.substring(5).replace(/-([a-z])/g, function (g) { return g[1].toUpperCase(); });
                    var val = attr.value;

                    if (val === 'true') val = true;
                    else if (val === 'false') val = false;
                    else if (val.startsWith('[') && val.endsWith(']')) {
                        try { val = JSON.parse(val); } catch (e) { }
                    }

                    if (optName === 'queryParams' && typeof window[val] === 'function') {
                        var originalQueryFn = window[val];
                        val = function (params) {
                            try {
                                return originalQueryFn(params);
                            } catch (e) {
                                return params;
                            }
                        };
                    }

                    options[optName] = val;
                }
            });

            // Intercept the AJAX call and use native fetch to bypass jQuery's XHR crashing bug
            options.ajax = function(request) {
                var instance = $t.data('bootstrap.table');
                if (instance) instance._ajaxFired = true;
                
                // Construct URL with query parameters
                var url = request.url;
                if (request.data && Object.keys(request.data).length > 0) {
                    var urlObj = new URL(url, window.location.origin);
                    Object.keys(request.data).forEach(key => urlObj.searchParams.append(key, request.data[key]));
                    url = urlObj.toString();
                }

                fetch(url, {
                    method: request.type || 'GET',
                    headers: {
                        'Content-Type': request.contentType || 'application/json',
                        'Accept': 'application/json, text/javascript, */*; q=0.01',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network error: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (request.success) {
                        try {
                            request.success(data);
                        } catch(e) {}
                    }
                })
                .catch(error => {
                    if (request.error) {
                        try {
                            request.error(error);
                        } catch(e) {}
                    }
                });
            };
            
            // Re-bind retry listener
            $t.off('load-error.bs.table.retry')
              .one('load-error.bs.table.retry', function (e, status, res) {
                  if (status === 0 || (res && res._intentionalAbort)) return;
                  if ($.fn.bootstrapTable && document.body.contains($t[0])) {
                      $t.bootstrapTable('refresh');
                  }
              });
              
            $t.bootstrapTable(options);

            // Final fallback: if bootstrap-table internal state machine completely froze and didn't fire our ajax interceptor,
            // we will force it with a manual ajax call and direct load().
            setTimeout(function() {
                var instance = $t.data('bootstrap.table');
                if (instance && !instance._ajaxFired) {
                    $.ajax({
                        url: options.url,
                        data: typeof options.queryParams === 'function' ? options.queryParams({limit: options.pageSize || 10, offset: 0}) : {},
                        success: function(res) {
                            $t.bootstrapTable('load', res);
                        }
                    });
                }
            }, 500);
        }

        function destroyTable($t) {
            if (!$.fn.bootstrapTable) return;
            if ($t.data('bootstrap.table')) {
                abortPendingRequest($t);
                $t.bootstrapTable('destroy');
                // Crucial: wipe jQuery's internal data cache for this node so that when Turbo restores it, 
                // $.data() is forced to re-read all fresh data-* attributes from the raw HTML!
                $t.removeData();
            }
        }

        // Scope defaults to the whole document; pass a <turbo-frame> element (or any
        // container) to limit initialization to tables inside it, e.g. from a
        // turbo:frame-load handler for a specific frame.
        function initAll(scope) {
            $(scope || document).find(SELECTOR).addBack(SELECTOR).each(function () {
                initTable($(this));
            });
        }

        function destroyAll(scope) {
            $(scope || document).find(SELECTOR).addBack(SELECTOR).each(function () {
                destroyTable($(this));
            });
        }

        return { initAll: initAll, destroyAll: destroyAll, initTable: initTable, destroyTable: destroyTable };
    })();

    document.addEventListener("turbo:load", function () {
        // CSRF tokens are no longer synced here — ajaxRequest() (scripts.js) reads the
        // csrf-token/csrf-name <meta> tags fresh on every call, so AJAX requests never
        // depend on Turbo navigation timing for a valid token.

        // Ensure sidebar state logic runs again
        if (typeof restore_sidebar_state !== 'undefined') restore_sidebar_state();
        if (typeof setNavigation !== 'undefined') setNavigation();

        // Re-init translations & custom toggles if they exist
        if (typeof initializeBootstrapTableTranslations === 'function') initializeBootstrapTableTranslations();

        // bootstrap-table.min.js runs its own one-time native auto-init
        // ($('[data-toggle="table"]').bootstrapTable()) on document-ready. On the very first
        // hard load, turbo:load can fire before that ready callback (Turbo's module script
        // isn't tied to DOMContentLoaded timing), so our init would run first and then get
        // clobbered when the vendor's native init fires right after — corrupting the toolbar
        // (and any select2 widget inside it) since it only ever happens once per hard load.
        // Deferring via $(fn) guarantees we always run AFTER that native init (jQuery executes
        // ready callbacks in registration order, and runs immediately if the DOM is already
        // ready — true for every subsequent Turbo soft-navigation), so we're always the final,
        // authoritative rebuild.
        $(function () {
            // Re-initialize Bootstrap tables living directly in the page body.
            BootstrapTableTurbo.initAll(document);

            // Re-init Select2 — turbo:before-render below destroys every select2 instance before
            // the outgoing page is torn down, but nothing re-created them on the incoming page until
            // now. Scoped to "select.select2" (not ".select2") — Select2's own generated wrapper
            // (<span class="select2 select2-container ...">) also carries the "select2" class, so a
            // bare ".select2" selector would match that wrapper too and re-wrap it on every visit.
            if ($.fn.select2) {
                $('select.select2').each(function () {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2();
                    }
                });
            }
        });
    });

    // Tables rendered inside a <turbo-frame> (as opposed to the main body) get their
    // content replaced independently of full-page turbo:load — Turbo fires
    // turbo:frame-load on the frame itself once its content finishes loading. Scope
    // init to that frame only, so an unrelated frame's reload doesn't reinit every
    // table on the page.
    document.addEventListener("turbo:frame-load", function (event) {
        BootstrapTableTurbo.initAll(event.target);
    });

    // Use turbo:before-render instead of before-cache. Because we have <meta name="turbo-cache-control" content="no-cache">
    // on these pages, Turbo bypasses the cache entirely and NEVER fires turbo:before-cache.
    // If we don't clean up here, Idiomorph will attempt to morph the pristine network HTML against 
    // the deeply mutated plugin DOM, permanently corrupting the table structure.
    document.addEventListener("turbo:before-render", function () {
        BootstrapTableTurbo.destroyAll(document);

        if ($.fn.select2) {
            $('select.select2').each(function () {
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }
            });
        }
    });
</script>

<!-- Alpine.js — POC scope: navbar notification dropdown only. defer = runs after DOM parse, coexists with jQuery. -->
<script defer data-turbo-eval="false" src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

<!-- App-level shared utilities: showToastMessage (iziToast), sweetConfirm, etc. -->
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/custom.js') ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/map-provider.js') . '?v=' . get_system_version() ?>"></script>
<script data-turbo-eval="false"
    src="<?= base_url('public/backend/assets/js/live-tracking-map.js') . '?v=' . get_system_version() ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/scripts.js') ?>"></script>
<script>
    window.fvLabels = {
        required: "<?= labels('fv_required', '%s is required') ?>",
        email: "<?= labels('fv_email', 'Please enter a valid email address') ?>",
        min: "<?= labels('fv_min', 'Must be at least %d characters') ?>",
        max: "<?= labels('fv_max', 'Must not exceed %d characters') ?>",
        field: "<?= labels('field', 'Field') ?>",
        pwdRequirements: "<?= labels('fv_pwd_requirements', 'Password does not meet the requirements. Please check the rules above.') ?>",
    };
    window.i18n_validation_failed = "<?= labels('validation_failed', 'Validation failed') ?>";
</script>
<script data-turbo-eval="false" src="<?= base_url('public/frontend/retro/js/password-strength.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/form-validator.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/bootstrap-translations.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/switch-translations.js') ?>"></script>
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/notification_redirects.js') ?>"></script>

<script>
    // FilePond label strings consumed by custom.js.
    var file_is_too_large = "<?= labels('file_is_too_large', 'File is too large') ?>";
    var maximum_file_size_is = "<?= labels('maximum_file_size_is', 'Maximum file size is') ?>";
    var file_of_invalid_type = "<?= labels('file_of_invalid_type', 'File of invalid type') ?>";
    var drag_and_drop_files_here = "<?= labels('drag_and_drop_files_here', 'Drag & Drop files here') ?>";
    var browse_files = "<?= labels('browse_files', 'Browse Files') ?>";
    var or = "<?= labels('or', 'Or') ?>";
    var invalid_file_type_please_upload_an_excel_or_csv_file = "<?= labels('invalid_file_type_please_upload_an_excel_or_csv_file', 'Invalid file type. Please upload an Excel or CSV file.') ?>";

    // i18n strings consumed by shared JS utilities.
    var are_your_sure = "<?= labels('are_your_sure', 'Are you sure?') ?>";
    var yes_proceed = "<?= labels('yes_proceed', 'Yes, Proceed!') ?>";
    var you_wont_be_able_to_revert_this = "<?= labels('you_wont_be_able_to_revert_this', "You won't be able to revert this!") ?>";
    var cancel = "<?= labels('cancel', 'Cancel') ?>";
    var please_wait_text = "<?= labels('please_wait', 'Please wait...') ?>";
    var on = "<?= labels('on', 'On') ?>";
    var off = "<?= labels('off', 'Off') ?>";

    var switchTextMap = {
        "Approved": "<?= labels('approved', 'Approved') ?>",
        "Disapproved": "<?= labels('disapproved', 'Disapproved') ?>",
        "Enable": "<?= labels('enable', 'Enable') ?>",
        "Disable": "<?= labels('disable', 'Disable') ?>",
        "Active": "<?= labels('active', 'Active') ?>",
        "Deactive": "<?= labels('deactive', 'Deactive') ?>",
        "Inactive": "<?= labels('inactive', 'Inactive') ?>",
        "Yes": "<?= labels('yes', 'Yes') ?>",
        "No": "<?= labels('no', 'No') ?>",
        "On": "<?= labels('on', 'On') ?>",
        "Off": "<?= labels('off', 'Off') ?>",
        "Allowed": "<?= labels('allowed', 'Allowed') ?>",
        "Not Allowed": "<?= labels('not_allowed', 'Not Allowed') ?>"
    };

    var bootstrapTableLabels = {
        "formatShowingRows": "<?= labels('formatShowingRows', 'Showing {from} to {to} of {total} entries') ?>",
        "formatRecordsPerPage": "<?= labels('formatRecordsPerPage', '{0} entries per page') ?>",
        "formatNoMatches": "<?= labels('formatNoMatches', 'No matching records found') ?>",
        "formatSearch": "<?= labels('formatSearch', 'Search') ?>",
        "formatLoadingMessage": "<?= labels('formatLoadingMessage', 'Loading, please wait...') ?>",
        "formatRefresh": "<?= labels('formatRefresh', 'Refresh') ?>",
        "formatToggle": "<?= labels('formatToggle', 'Toggle') ?>",
        "formatColumns": "<?= labels('formatColumns', 'Columns') ?>",
        "formatAllRows": "<?= labels('formatAllRows', 'All') ?>",
        "formatPaginationSwitch": "<?= labels('formatPaginationSwitch', 'Hide/Show pagination') ?>",
        "formatDetailPagination": "<?= labels('formatDetailPagination', 'Detail pagination') ?>",
        "formatClearFilters": "<?= labels('formatClearFilters', 'Clear filters') ?>",
        "formatJumpTo": "<?= labels('formatJumpTo', 'GO') ?>",
        "formatAdvancedSearch": "<?= labels('formatAdvancedSearch', 'Advanced search') ?>",
        "formatAdvancedCloseButton": "<?= labels('formatAdvancedCloseButton', 'Close') ?>"
    };

    $(document).ready(function () {
        // Measure real navbar height so main-content padding-top never overlaps.
        var h = $('.main-navbar').outerHeight(true) || 80;
        document.documentElement.style.setProperty('--partner-navbar-height', h + 'px');

        if (typeof updateSwitchText === 'function') updateSwitchText();
        if (typeof clearTranslationCache === 'function') clearTranslationCache();
        if (typeof initializeBootstrapTableTranslations === 'function') initializeBootstrapTableTranslations();
    });
</script>

<?php if ($mapProvider === 'google' && !empty($mapApiKey)): ?>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=<?= esc($mapApiKey) ?>&libraries=places&callback=initAll&loading=async"
        async defer></script>
<?php elseif ($mapProvider === 'openstreetmap'): ?>
    <script>
        // No Google callback hook available without the Google Maps script; bootstrap manually.
        $(function () {
            if (typeof initAll === 'function') initAll();
        });
    </script>
<?php endif; ?>