/**
 * Queue Progress Tray
 * Polls /admin/notification/queue-status every 3 seconds and renders
 * a floating pill + expandable panel showing notification batch progress.
 *
 * Layout: batches are grouped by channel type (Email / Push / SMS).
 * Each channel section only appears when at least one batch uses it.
 *
 * Toasts are driven by the backend's `completed_channels` field so they
 * fire reliably regardless of poll timing.
 *
 * Dependencies: jQuery, showToastMessage() from custom.js,
 *               queueStatusUrl variable from include-scripts.php.
 */
(function ($) {
    'use strict';

    // ---- Constants ----
    var POLL_INTERVAL  = 3000;
    var HIDE_DELAY     = 5000;   // 5 s after completion — toast already provides feedback
    var STORAGE_STATE   = 'queueTray_state';
    var STORAGE_EXPAND  = 'queueTray_expanded';
    var STORAGE_TOASTED = 'queueTray_toasted';   // localStorage — survives sessionStorage clears
    var STORAGE_TOASTED_SS = 'queueTray_toasted_ss'; // sessionStorage fallback for same-tab reloads
    var TOASTED_TTL     = 24 * 60 * 60 * 1000;  // prune entries older than 24 h

    // Channel display order and labels
    var CHANNEL_ORDER  = ['email', 'fcm', 'sms'];
    var CHANNEL_LABELS = { fcm: 'Push Notification', email: 'Email', sms: 'SMS' };
    var CHANNEL_ICONS  = { email: '&#9993;', fcm: '&#128276;', sms: '&#9993;' };

    // ---- State ----
    var pollTimer      = null;
    var hideTimer      = null;
    var channelToasted = (function () {
        // Merge localStorage + sessionStorage so either alone is sufficient for dedup.
        var fromLS = {}, fromSS = {};
        try { fromLS = JSON.parse(localStorage.getItem(STORAGE_TOASTED)) || {}; } catch (e) {}
        try { fromSS = JSON.parse(sessionStorage.getItem(STORAGE_TOASTED_SS)) || {}; } catch (e) {}
        var merged = {};
        for (var k in fromLS) { if (fromLS[k]) merged[k] = fromLS[k]; }
        for (var k in fromSS) { if (fromSS[k] && (!merged[k] || fromSS[k] > merged[k])) merged[k] = fromSS[k]; }
        var now = new Date().getTime();
        var pruned = {};
        for (var k in merged) { if (merged[k] && (now - merged[k]) < TOASTED_TTL) pruned[k] = merged[k]; }
        return pruned;
    })();
    var isExpanded     = false;
    var isVisible      = false;
    var currentCsrf    = { name: window.csrfName || '', hash: window.csrfHash || '' };

    // ---- DOM refs ----
    var $tray, $pill, $pillText, $chevron, $panel, $list, $closeBtn, $pillIcon;

    // ====================================================================
    // Helpers
    // ====================================================================

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getActiveChannels(b) {
        if (b.channels && b.channels.length > 0) return b.channels.split(',');
        var ch = [];
        if (b.fcm_sent > 0 || b.fcm_failed > 0) ch.push('fcm');
        if (b.email_sent > 0 || b.email_failed > 0) ch.push('email');
        if (b.sms_sent > 0 || b.sms_failed > 0) ch.push('sms');
        return ch;
    }

    function getCompletedChannels(b) {
        if (b.completed_channels && b.completed_channels.length > 0) return b.completed_channels.split(',');
        return [];
    }

    // ====================================================================
    // Toasts — driven by backend completed_channels
    // ====================================================================

    function fireChannelToasts(batches) {
        for (var i = 0; i < batches.length; i++) {
            var b    = batches[i];
            var done = getCompletedChannels(b);

            // Fallback: batch is fully completed but completed_channels was
            // not populated — treat every active channel as done.
            if (b.status === 'completed' && done.length === 0) {
                done = getActiveChannels(b);
            }

            for (var j = 0; j < done.length; j++) {
                var ch  = done[j];
                var key = b.batch_id + '_' + ch;

                if (!channelToasted[key]) {
                    channelToasted[key] = new Date().getTime();
                    try { localStorage.setItem(STORAGE_TOASTED, JSON.stringify(channelToasted)); } catch (e) {}
                    try { sessionStorage.setItem(STORAGE_TOASTED_SS, JSON.stringify(channelToasted)); } catch (e) {}
                    var sent = b[ch + '_sent'] || 0;
                    if (typeof showToastMessage === 'function' && sent > 0) {
                        showToastMessage(
                            (CHANNEL_LABELS[ch] || ch) + ' notification delivered successfully to ' + sent + ' users',
                            'success'
                        );
                    }
                }
            }
        }
    }

    // ====================================================================
    // Rendering
    // ====================================================================

    function renderPill(batches) {
        var activeCount = 0;
        for (var i = 0; i < batches.length; i++) {
            if (batches[i].status !== 'completed') activeCount++;
        }
        if (activeCount > 0) {
            $pillText.text(activeCount + ' sending\u2026');
            $pillIcon.removeClass('queue-tray__pill-icon--done');
        } else {
            $pillText.text('All delivered');
            $pillIcon.addClass('queue-tray__pill-icon--done');
        }
    }

    /**
     * Group batches by channel and render a section per channel.
     *
     * Layout:
     *   Email
     *     Holiday Sale  2/5  Sending  [===40%===]
     *     Welcome       5/5  Done     [==100%===]
     *   Push Notification
     *     Holiday Sale  5/5  Done     [==100%===]
     */
    function renderBatchRows(batches) {
        if (!batches.length) {
            $list.html('<div class="queue-tray__empty">No active dispatches</div>');
            return;
        }

        // Build map: channel → [{batch, sent, failed, total, done}]
        var groups = {};
        for (var i = 0; i < batches.length; i++) {
            var b        = batches[i];
            var channels = getActiveChannels(b);
            var completed = getCompletedChannels(b);

            for (var j = 0; j < channels.length; j++) {
                var ch = channels[j];
                if (!groups[ch]) groups[ch] = [];

                var sent   = b[ch + '_sent']   || 0;
                var failed = b[ch + '_failed'] || 0;
                var total  = b.total_recipients || 0;
                var chDone = completed.indexOf(ch) !== -1 || b.status === 'completed';

                groups[ch].push({
                    title:    b.title,
                    sent:     sent,
                    failed:   failed,
                    total:    total,
                    done:     chDone,
                    status:   b.status,
                    progress: total > 0 ? Math.min(100, Math.round(((sent + failed) / total) * 100)) : 0
                });
            }
        }

        // Render in fixed order
        var html = '';
        for (var k = 0; k < CHANNEL_ORDER.length; k++) {
            var chKey = CHANNEL_ORDER[k];
            var items = groups[chKey];
            if (!items || !items.length) continue;

            var label = CHANNEL_LABELS[chKey] || chKey;

            html += '<div class="queue-tray__section">';
            html += '  <div class="queue-tray__section-header">' + escapeHtml(label) + '</div>';

            for (var m = 0; m < items.length; m++) {
                var it         = items[m];
                var statusText = it.done ? 'Done' : (it.status === 'queued' ? 'Queued' : 'Sending');
                var rowClass   = 'queue-tray__batch' + (it.done ? ' queue-tray__batch--completed' : '');
                var statusCls  = it.done ? ' queue-tray__batch-status--completed' : '';
                var fillCls    = it.done ? ' queue-tray__progress-fill--completed' : '';

                html += '<div class="' + rowClass + '">';
                html += '  <div class="queue-tray__batch-header">';
                html += '    <span class="queue-tray__batch-title" title="' + escapeHtml(it.title) + '">' + escapeHtml(it.title) + '</span>';
                html += '    <span class="queue-tray__batch-meta">' + it.sent + '/' + it.total + ' <span class="queue-tray__batch-status' + statusCls + '">' + statusText + '</span></span>';
                html += '  </div>';
                html += '  <div class="queue-tray__progress">';
                html += '    <div class="queue-tray__progress-fill' + fillCls + '" style="width:' + it.progress + '%"></div>';
                html += '  </div>';
                html += '</div>';
            }

            html += '</div>';
        }

        $list.html(html);
    }

    // ====================================================================
    // Visibility
    // ====================================================================

    function showTray() {
        if (isVisible) return;
        isVisible = true;
        clearTimeout(hideTimer); hideTimer = null;
        $tray.removeClass('queue-tray--hidden').addClass('queue-tray--visible');
    }

    function hideTray() {
        hideTimer = null;
        sessionStorage.removeItem(STORAGE_STATE);
        sessionStorage.removeItem(STORAGE_EXPAND);
        if (!isVisible) return;
        isVisible = false;
        $tray.removeClass('queue-tray--visible').addClass('queue-tray--hidden');
    }

    function scheduleHide() {
        if (hideTimer) return;          // already counting down — don't restart
        hideTimer = setTimeout(hideTray, HIDE_DELAY);
    }

    function setExpanded(open) {
        isExpanded = open;
        if (open) {
            $panel.addClass('queue-tray__panel--open');
            $chevron.addClass('queue-tray__pill-chevron--open');
        } else {
            $panel.removeClass('queue-tray__panel--open');
            $chevron.removeClass('queue-tray__pill-chevron--open');
        }
        try { sessionStorage.setItem(STORAGE_EXPAND, open ? '1' : '0'); } catch (e) {}
    }

    // ====================================================================
    // Polling
    // ====================================================================

    function startPolling() {
        if (pollTimer) return;
        poll();
        pollTimer = setInterval(poll, POLL_INTERVAL);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function poll() {
        if (typeof queueStatusUrl === 'undefined') return;

        var url = queueStatusUrl;
        if (currentCsrf.name && currentCsrf.hash) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + encodeURIComponent(currentCsrf.name) + '=' + encodeURIComponent(currentCsrf.hash);
        }

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            timeout: 8000,
            success: function (res) {
                if (res.error) {
                    console.warn('[QueueTray] poll returned error:', res.message);
                    return;
                }

                if (res.csrfName && res.csrfHash) {
                    currentCsrf.name = res.csrfName;
                    currentCsrf.hash = res.csrfHash;
                    window.csrfName  = res.csrfName;
                    window.csrfHash  = res.csrfHash;
                }

                var batches        = res.batches || [];
                var newlyCompleted = res.newly_completed || [];

                var allBatches = batches.concat(newlyCompleted.map(function (nc) {
                    nc.status = 'completed';
                    return nc;
                }));

                // Fire toasts from backend completed_channels
                fireChannelToasts(allBatches);

                // Check if any batch is still active
                var hasActive = false;
                for (var j = 0; j < allBatches.length; j++) {
                    if (allBatches[j].status !== 'completed') { hasActive = true; break; }
                }

                // Only persist active batches — completed entries must not survive page navigation
                // to prevent the tray re-appearing from stale cache after a page reload.
                if (hasActive) {
                    try { sessionStorage.setItem(STORAGE_STATE, JSON.stringify(batches)); } catch (e) {}
                } else {
                    try { sessionStorage.removeItem(STORAGE_STATE); } catch (e) {}
                }

                // Render
                if (allBatches.length > 0) {
                    if (hasActive) {
                        showTray();
                    }
                    renderPill(allBatches);
                    renderBatchRows(allBatches);
                }

                // Schedule hide once everything is done
                if (!hasActive && allBatches.length > 0) {
                    scheduleHide();
                }
            },
            error: function (xhr, status, err) {
                console.warn('[QueueTray] poll AJAX error:', status, err);
            }
        });
    }

    // ====================================================================
    // Init
    // ====================================================================

    function init() {
        $tray     = $('#queue-progress-tray');
        $pill     = $('#queue-tray-pill');
        $pillText = $('#queue-tray-pill-text');
        $pillIcon = $pill.find('.queue-tray__pill-icon');
        $chevron  = $('#queue-tray-chevron');
        $panel    = $('#queue-tray-panel');
        $list     = $('#queue-tray-list');
        $closeBtn = $('#queue-tray-close');

        if (!$tray.length) return;

        var cached = null;
        try { cached = JSON.parse(sessionStorage.getItem(STORAGE_STATE)); } catch (e) {}
        var wasExpanded = false;
        try { wasExpanded = sessionStorage.getItem(STORAGE_EXPAND) === '1'; } catch (e) {}

        if (cached && cached.length > 0) {
            var hasCachedActive = false;
            for (var i = 0; i < cached.length; i++) {
                if (cached[i].status !== 'completed') { hasCachedActive = true; break; }
            }
            if (hasCachedActive) {
                showTray();
                renderPill(cached);
                renderBatchRows(cached);
                if (wasExpanded) setExpanded(true);
            }
        }

        startPolling();

        $pill.on('click', function () { setExpanded(!isExpanded); });
        $closeBtn.on('click', function (e) { e.stopPropagation(); setExpanded(false); });
    }

    // ====================================================================
    // Public API
    // ====================================================================

    window.QueueTray = {
        poll: function () {
            if (!pollTimer) startPolling();
            else poll();
        }
    };

    $(document).ready(init);

})(jQuery);
