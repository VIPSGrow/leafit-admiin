<?php
/**
 * Demo Reset Countdown Timer (shared partial)
 *
 * Included in both admin and partner navbar.
 * Shows a live countdown to the next automatic demo reset.
 *
 * Requirements:
 *   - ALLOW_MODIFICATION == 0 (demo mode on)
 *   - demo_reset_logs table exists
 *
 * The reset interval is the same hardcoded constant used by DemoAutoReset.php
 * (RESET_INTERVAL_SECONDS = 7200 = 2 hours), so the timer always matches
 * what the cron job will actually do.
 *
 * Countdown anchor: the last finished_at from demo_reset_logs (success or running).
 * If no log row exists yet, the timer starts from a full 2-hour interval.
 */

// Never render HTML in CLI mode — this partial is web-only
if (is_cli()) {
    return;
}

// Only show when demo mode is active
$_demoTimerShow = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0;

if ($_demoTimerShow):
    // Single source of truth: app/Config/DemoReset.php
    $_demoIntervalSeconds = (int) config('DemoReset')->intervalSeconds;
    $_demoDB = \Config\Database::connect();

    // Show time until the actual next reset event = next cron wall-clock boundary,
    // accounting for the recent-finish buffer (if a reset just happened, the next
    // boundary will be skipped by the cron, so use the boundary after that).
    $_now    = time();
    $_offset = (int) date('Z');
    $_demoCfg = config('DemoReset');

    $_scheduledRem = $_demoIntervalSeconds - (($_now + $_offset) % $_demoIntervalSeconds);
    $_nextBoundary = $_now + $_scheduledRem;

    $_lastFinishedRow = $_demoDB->table('demo_reset_logs')
        ->where('status', 'success')
        ->where('finished_at IS NOT NULL')
        ->orderBy('finished_at', 'DESC')
        ->limit(1)
        ->get()
        ->getRow();

    $_buffer = (int) $_demoCfg->recentFinishBufferSeconds;
    if ($_lastFinishedRow && !empty($_lastFinishedRow->finished_at)) {
        $_lastFinishedAt = strtotime($_lastFinishedRow->finished_at . ' UTC');
        if ($_lastFinishedAt && ($_nextBoundary - $_lastFinishedAt) < $_buffer) {
            $_nextBoundary += $_demoIntervalSeconds;
        }
    }

    $_remainingSecs = max(0, $_nextBoundary - $_now);
    $_remainingSecs = min($_remainingSecs, $_demoIntervalSeconds);

    // Tooltip content for hover: "Demo Mode Notice" + full notice (HTML for line breaks)
    $_demoTooltipTitle = labels('demo_mode_notice', 'Demo Mode Notice');
    $_demoTooltipBody = labels('demo_tooltip_body_1', 'This is a public demo environment created for exploration and testing purposes.')
        . '<br><br>' . labels('demo_tooltip_body_2', 'The system automatically resets every 2 hours to maintain a clean demo experience for all users. Any newly added categories, services, subscriptions, or other test data will be removed during the reset.')
        . '<br><br>' . labels('demo_tooltip_body_3', 'Feel free to explore all features and test the complete system.');
    $_demoTooltipHtml = '<strong>' . htmlspecialchars($_demoTooltipTitle) . '</strong><br><br>' . $_demoTooltipBody;
?>
<!-- Same as original Demo mode badge (badge-danger, border-radius 8px) with timer beside it -->
<li class="nav-item my-auto ml-2 mr-2 demo-reset-timer-wrapper">
    <span class="badge badge-danger demo-reset-timer-badge" id="demo-reset-timer" style="border-radius: 8px!important">
        <?=  labels('demo_mode', 'Demo mode') ?> <span id="demo-reset-countdown" class="ml-1">--:--</span>
    </span>
</li>
<!-- Popup content (moved to body by JS so it can sit outside navbar) -->
<script type="text/html" id="demo-notice-popup-html"><?= $_demoTooltipHtml ?></script>
<style>
/* Urgent state when timer is about to expire */
.demo-reset-timer-badge.timer-urgent {
    animation: demoTimerPulse 1s ease-in-out infinite;
}
@keyframes demoTimerPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
/* Custom demo notice: small box, below navbar; not full width */
.demo-notice-popup {
    position: fixed;
    z-index: 9999;
    max-width: 320px;
    padding: 0;
    pointer-events: auto;
}
.demo-notice-popup-inner {
    background: #fff;
    color: #000;
    padding: 12px 14px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    text-align: left;
    line-height: 1.45;
    font-size: 0.9rem;
}
</style>
<script>
// (function () {
//     var remaining = <?= (int) $_remainingSecs ?>;
//     var timerEl   = document.getElementById('demo-reset-countdown');
//     var badgeEl   = document.getElementById('demo-reset-timer');
//     if (!timerEl || !badgeEl) return;

//     if (document.body.classList.contains('partner-panel')) {
//         document.querySelectorAll('.demo-reset-timer-wrapper')
//             .forEach(el => {
//                 el.classList.remove('my-auto');
//                 el.classList.add('mt-1');
//             });
//     }

//     function pad(n) {
//         return n < 10 ? '0' + n : '' + n;
//     }

//     function updateTimer() {
//         if (remaining <= 0) {
//             timerEl.textContent = 'Resetting...';
//             badgeEl.classList.add('timer-urgent');
//             clearInterval(ticker);
//             setTimeout(function () {
//                 // window.location.reload();
//             }, 5000);
//             return;
//         }
//         var h = Math.floor(remaining / 3600);
//         var m = Math.floor((remaining % 3600) / 60);
//         var s = remaining % 60;
//         timerEl.textContent = h > 0
//             ? pad(h) + ':' + pad(m) + ':' + pad(s)
//             : pad(m) + ':' + pad(s);

//         if (remaining <= 120) badgeEl.classList.add('timer-urgent');
//         else badgeEl.classList.remove('timer-urgent');

//         remaining--;
//     }

//     updateTimer();
//     var ticker = setInterval(function () {
//         updateTimer();
//     }, 1000);

//     function initDemoNoticePopup() {
//         var tpl = document.getElementById('demo-notice-popup-html');
//         if (!tpl) return;

//         var popup = document.createElement('div');
//         popup.id = 'demo-notice-popup';
//         popup.className = 'demo-notice-popup';
//         popup.setAttribute('role', 'tooltip');
//         popup.setAttribute('aria-hidden', 'true');
//         popup.style.display = 'none';
//         popup.innerHTML = '<div class="demo-notice-popup-inner">'
//             + (tpl.textContent || tpl.innerText || '')
//             + '</div>';
//         document.body.appendChild(popup);
//         tpl.parentNode.removeChild(tpl);

//         var hideTimeout = null;
//         var delay       = 200;

//         function positionPopup() {
//             var r = badgeEl.getBoundingClientRect();
//             popup.style.top  = (r.bottom + window.scrollY + 6) + 'px';
//             popup.style.left = (r.left + window.scrollX) + 'px';
//         }

//         function show() {
//             if (hideTimeout) {
//                 clearTimeout(hideTimeout);
//                 hideTimeout = null;
//             }
//             positionPopup();
//             popup.style.display = 'block';
//             popup.setAttribute('aria-hidden', 'false');
//         }

//         function scheduleHide() {
//             if (hideTimeout) return;
//             hideTimeout = setTimeout(function () {
//                 hideTimeout = null;
//                 popup.style.display = 'none';
//                 popup.setAttribute('aria-hidden', 'true');
//             }, delay);
//         }

//         badgeEl.addEventListener('mouseenter', show);
//         badgeEl.addEventListener('mouseleave', scheduleHide);
//         popup.addEventListener('mouseenter', show);
//         popup.addEventListener('mouseleave', scheduleHide);

//         window.addEventListener('scroll', function () {
//             if (popup.style.display === 'block') positionPopup();
//         }, true);

//         window.addEventListener('resize', function () {
//             if (popup.style.display === 'block') positionPopup();
//         });
//     }

//     if (document.readyState === 'loading') {
//         document.addEventListener('DOMContentLoaded', initDemoNoticePopup);
//     } else {
//         initDemoNoticePopup();
//     }
// })();

(function () {
    var remaining    = <?= (int) $_remainingSecs ?>;
    var timerEl      = document.getElementById('demo-reset-countdown');
    var badgeEl      = document.getElementById('demo-reset-timer');

    if (!timerEl || !badgeEl) return;

    var ticker       = null;
    var fetchPending = false;
    var targetTime   = Date.now() + (remaining * 1000);

    function formatTime(sec) {
        if (sec < 0) sec = 0;
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        return (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    }

    function syncWithServer() {
        if (fetchPending) return;
        fetchPending = true;

        fetch('/get-demo-reset-time')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                console.log('[TIMER] Resyncing with server:', data);
                remaining    = data.remaining;
                targetTime   = Date.now() + (remaining * 1000);
                fetchPending = false;
                
                if (data.status === 'counting' && remaining > 0) {
                    if (!ticker) ticker = setInterval(updateTimer, 1000);
                    updateTimer();
                } else {
                    if (ticker) {
                        clearInterval(ticker);
                        ticker = null;
                    }
                    
                    if (data.status === 'running') {
                        timerEl.textContent = 'Resetting...';
                    } else {
                        timerEl.textContent = 'Finishing...';
                    }
                    
                    setTimeout(syncWithServer, 5000);
                }
            })
            .catch(function (err) {
                console.error('[TIMER] Sync error:', err);
                fetchPending = false;
                setTimeout(syncWithServer, 10000);
            });
    }

    function updateTimer() {
        // Recalculate remaining based on actual clock relative to local system time
        remaining = Math.max(0, Math.round((targetTime - Date.now()) / 1000));

        if (remaining <= 0) {
            timerEl.textContent = 'Resetting...';
            badgeEl.classList.add('timer-urgent');

            if (ticker) {
                clearInterval(ticker);
                ticker = null;
            }

            syncWithServer();
            return;
        }

        timerEl.textContent = formatTime(remaining);
        if (remaining < 300) {
            badgeEl.classList.add('timer-urgent');
        } else {
            badgeEl.classList.remove('timer-urgent');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            syncWithServer();
        }
    });

    if (remaining > 0) {
        ticker = setInterval(updateTimer, 1000);
        updateTimer();
    } else {
        syncWithServer();
    }

    function initDemoNoticePopup() {
        var tpl = document.getElementById('demo-notice-popup-html');
        if (!tpl) return;

        var popup = document.createElement('div');
        popup.id = 'demo-notice-popup';
        popup.className = 'demo-notice-popup';
        popup.style.display = 'none';

        popup.innerHTML = '<div class="demo-notice-popup-inner">'
            + (tpl.textContent || tpl.innerText || '')
            + '</div>';

        document.body.appendChild(popup);
        tpl.parentNode.removeChild(tpl);

        var hideTimeout = null;

        function positionPopup() {
            var r = badgeEl.getBoundingClientRect();
            popup.style.top  = (r.bottom + window.scrollY + 6) + 'px';
            popup.style.left = (r.left + window.scrollX) + 'px';
        }

        function show() {
            if (hideTimeout) clearTimeout(hideTimeout);
            positionPopup();
            popup.style.display = 'block';
        }

        function hide() {
            popup.style.display = 'none';
        }

        badgeEl.addEventListener('mouseenter', show);
        badgeEl.addEventListener('mouseleave', hide);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDemoNoticePopup);
    } else {
        initDemoNoticePopup();
    }
})();
</script>
<?php
endif; // $_demoTimerShow
?>
