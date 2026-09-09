<!-- Queue Progress Tray — floating pill + expandable panel for notification dispatch progress -->
<div id="queue-progress-tray" class="queue-tray queue-tray--hidden" aria-label="Notification Queue Progress">
    <!-- Collapsed pill -->
    <div class="queue-tray__pill" id="queue-tray-pill">
        <span class="queue-tray__pill-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
        </span>
        <span class="queue-tray__pill-text" id="queue-tray-pill-text">Sending...</span>
        <span class="queue-tray__pill-chevron" id="queue-tray-chevron">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="18 15 12 9 6 15"></polyline>
            </svg>
        </span>
    </div>

    <!-- Expanded panel -->
    <div class="queue-tray__panel" id="queue-tray-panel">
        <div class="queue-tray__panel-header">
            <span><?= labels('notification_queue', 'Notification Queue') ?></span>
            <button class="queue-tray__close" id="queue-tray-close" type="button" aria-label="Collapse">&times;</button>
        </div>
        <div class="queue-tray__panel-body" id="queue-tray-list">
            <!-- Batch rows rendered by queue-tray.js -->
        </div>
    </div>
</div>
