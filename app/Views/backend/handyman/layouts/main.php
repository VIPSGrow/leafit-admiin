<?php
$siteData = get_settings('general_settings', true);
$companyTitle = get_company_title_with_fallback($siteData);

$_session = \Config\Services::session();
$_is_rtl = $_session->get('is_rtl');
$_language = $_session->get('language');
$_defLang = fetch_details('languages', ['is_default' => '1']);
if (empty($_language) && !isset($_is_rtl)) {
    $_is_rtl = $_defLang[0]['is_rtl'] ?? 0;
} elseif ($_is_rtl === null) {
    $_is_rtl = 0;
}
$is_rtl = (int) $_is_rtl;

$primaryColor = !empty($siteData['primary_color']) ? $siteData['primary_color'] : '#05a6e8';
$secondaryColor = !empty($siteData['secondary_color']) ? $siteData['secondary_color'] : '#003e64';

// Microsoft Clarity
$apiKeySettings = get_settings('api_key_settings', true);
$clarityProjectId = $apiKeySettings['microsoft_clarity_project_id'] ?? '';
$clarityEnabled = !empty($apiKeySettings['microsoft_clarity_enabled']) && $apiKeySettings['microsoft_clarity_enabled'] === '1';
?>
<!DOCTYPE html>
<html lang="en" <?= ($is_rtl ?? 0) ? ' dir="rtl"' : '' ?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
    <title><?= esc($title ?? 'Handyman Panel') ?> &mdash; <?= esc($companyTitle) ?></title>

    <?= $this->include('backend/handyman/components/css') ?>

    <style>
        body {
            --primary-color:
                <?= esc($primaryColor) ?>
            ;
            --secondary-color:
                <?= esc($secondaryColor) ?>
            ;
        }
    </style>

    <?php if (!empty($clarityProjectId) && $clarityEnabled): ?>
        <script type="text/javascript">
            (function (c, l, a, r, i, t, y) {
                c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
                t = l.createElement(r); t.async = 1;
                t.src = "https://www.clarity.ms/tag/" + i;
                y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
            })(window, document, "clarity", "script", "<?= htmlspecialchars($clarityProjectId, ENT_QUOTES, 'UTF-8') ?>");
        </script>
    <?php endif; ?>

    <script>
        var baseUrl = "<?= base_url() ?>";
        var siteUrl = "<?= site_url() ?>";
        var csrfName = "<?= csrf_token() ?>";
        var csrfHash = "<?= csrf_hash() ?>";
    </script>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-name" content="<?= csrf_token() ?>">

    <meta name="turbo-refresh-method" content="morph">
    <meta name="turbo-refresh-scroll" content="preserve">
    <!-- Panel pages are full of stateful third-party JS widgets (bootstrap-table, select2,
         daterangepicker) that don't survive Turbo's snapshot-cache restore: on a cached
         revisit Turbo skips the network fetch entirely and just restores the old DOM, so
         list_data never fires again and tables stay empty. Disabling the cache forces a
         real server fetch (and therefore a real turbo:load-driven reinit) on every visit. -->
    <meta name="turbo-cache-control" content="no-cache">

    <?= $this->renderSection('page_styles') ?>
</head>

<body class="handyman-panel<?= (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) ? ' has-demo-cta' : '' ?>">
    <script>
        // Apply persisted sidebar-collapsed state before first paint to avoid a flash of the expanded sidebar.
        (function () {
            try {
                if (window.innerWidth > 991 && localStorage.getItem('handyman-sidebar-mini') === '1') {
                    document.body.classList.add('sidebar-mini');
                }
            } catch (e) { /* storage unavailable */ }
        })();
    </script>
    <div id="app">
        <div class="main-wrapper">

            <div class="sidebar-overlay"></div>
            <?= $this->include('backend/handyman/components/navbar') ?>
            <?= $this->include('backend/handyman/components/sidebar') ?>

            <div class="main-content">
                <section class="section">
                    <div class="section-header rounded-3 shadow-sm mb-4">
                        <h1><?= esc($title ?? 'Handyman Panel') ?></h1>
                        <?= $this->include('backend/handyman/components/breadcrumbs') ?>
                    </div>
                    <?= $this->renderSection('content') ?>
                </section>
            </div>

            <?= $this->include('backend/handyman/components/footer') ?>
            <?= $this->include('backend/handyman/components/scripts') ?>

            <?= $this->renderSection('page_scripts') ?>


        </div>
    </div>
</body>

</html>