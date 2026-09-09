<?php

/**
 * Provider panel maintenance view.
 * Shown when provider app maintenance mode is active (current time between start and end).
 * Prevents navigation to other pages until the end date-time has passed.
 * Same concept as the KYC/profile blocking view until admin approves the provider.
 *
 * Date/time from the database is in UTC. The end time is displayed in the client's
 * local timezone via JavaScript (toLocaleString / Intl); countdown logic uses
 * Unix timestamps and remains timezone-agnostic.
 */
$endAt = isset($maintenance_end_at) ? (int) $maintenance_end_at : 0;
$message = isset($maintenance_message) && $maintenance_message !== '' ? $maintenance_message : labels('provider_panel_under_maintenance', 'The provider panel is currently under maintenance. Please try again later.');
?>
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1><?= labels('provider_panel_maintenance', 'Maintenance') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><?= labels('provider_panel', 'Provider Panel') ?></a></div>
                <div class="breadcrumb-item"><?= labels('provider_panel_maintenance', 'Maintenance') ?></div>
            </div>
        </div>
        <div class="section-body">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-6">
                    <div class="card shadow">
                        <div class="card-body text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-tools fa-4x text-warning" aria-hidden="true"></i>
                            </div>
                            <h2 class="h4 mb-3"><?= labels('provider_panel_under_maintenance', 'The provider panel is currently under maintenance. Please try again later.') ?></h2>
                            <div class="alert alert-info text-left mb-4" role="alert">
                                <?= !empty($message) ? esc($message) : labels('provider_panel_under_maintenance', 'The provider panel is currently under maintenance. Please try again later.') ?>
                            </div>
                            <?php if ($endAt > 0) : ?>
                                <p class="text-muted mb-2"><?= labels('maintenance_expected_until', 'Expected to be back by') ?>:</p>
                                <!-- End time is stored in UTC; displayed in client's local timezone via JS below -->
                                <p class="h5 mb-3" id="maintenance-end-time"><span class="text-muted">--</span></p>
                                <div id="maintenance-countdown" class="mb-0">
                                    <span id="countdown-display">--</span>
                                </div>
                                <p class="small text-muted mt-3"><?= labels('maintenance_page_refresh_note', 'This page will refresh automatically when maintenance ends, or you can refresh manually.') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php if ($endAt > 0) : ?>
    <script>
        (function() {
            // End time from server is UTC (Unix timestamp in seconds). Display in client's local timezone.
            var endAt = <?= (int) $endAt ?>;
            var endDate = new Date(endAt * 1000);
            var timeEl = document.getElementById('maintenance-end-time');
            if (timeEl) {
                try {
                    timeEl.innerHTML = endDate.toLocaleString(undefined, {
                        dateStyle: 'medium',
                        timeStyle: 'short'
                    });
                } catch (e) {
                    timeEl.textContent = endDate.toLocaleString();
                }
            }

            // Countdown and auto-refresh when provider panel maintenance ends.
            function updateCountdown() {
                var now = Math.floor(Date.now() / 1000);
                if (now >= endAt) {
                    document.getElementById('countdown-display').textContent = '<?= labels('maintenance_ended_refreshing', 'Maintenance ended. Refreshing...') ?>';
                    window.location.reload();
                    return;
                }
                var left = endAt - now;
                var h = Math.floor(left / 3600);
                var m = Math.floor((left % 3600) / 60);
                var s = left % 60;
                var text = (h > 0 ? h + 'h ' : '') + (m < 10 ? '0' : '') + m + 'm ' + (s < 10 ? '0' : '') + s + 's';
                var el = document.getElementById('countdown-display');
                if (el) el.textContent = text;
            }
            updateCountdown();
            setInterval(updateCountdown, 1000);
        })();
    </script>
<?php endif; ?>