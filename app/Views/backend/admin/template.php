<?php
$data = get_settings('general_settings', true);

// Use the helper function for company title with proper language fallback
$company_title = get_company_title_with_fallback($data);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <title><?= $title ?> &mdash; <?= $company_title; ?></title>
    <?= view('backend/admin/include-css') ?>
    <?php
    isset($data['primary_color']) && $data['primary_color'] != "" ?  $primary_color = $data['primary_color'] : $primary_color =  '#0073f0';
    isset($data['secondary_color']) && $data['secondary_color'] != "" ?  $secondary_color = $data['secondary_color'] : $secondary_color =  '#003e64';
    isset($data['primary_shadow']) && $data['primary_shadow'] != "" ?  $primary_shadow = $data['primary_shadow'] : $primary_shadow =  '#05A6E8';
    ?>
    <style>
        body {
            --primary-color: <?= $primary_color ?>;
            --secondary-color: <?= $secondary_color ?>;
        }
    </style>
    <?php
    $session = \Config\Services::session();
    $is_rtl = $session->get('is_rtl');
    if (!isset($is_rtl)) {
        $is_rtl = fetch_details('languages', ['is_default' => 1], ['is_rtl'])[0]['is_rtl'];
    }
    if ($is_rtl == 1) { ?>
        <link rel="stylesheet" href="<?= base_url('public/backend/assets/css/rtl_css/rtl_custom.css') ?>" />
        <link rel="stylesheet" href="<?= base_url('public/backend/assets/css/vendor/cropper.css') ?>" />
    <?php } else {  ?>
        <link rel="stylesheet" href="<?= base_url('public/backend/assets/css/custom.css') ?>" />
        <link rel="stylesheet" href="<?= base_url('public/backend/assets/css/vendor/cropper.css') ?>" />
    <?php } ?>
</head>
<div id="loading">
    <div class="lds-ring">
        <div></div>
    </div>
</div>

<!--
    Admin panel body marker class.
    Why:
    - We need a safe way to scope "admin-only" CSS fixes.
    - This prevents changes from leaking into partner/front pages.
-->

<body class="admin-panel<?= (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) ? ' has-demo-cta' : '' ?>">
    <script>
        var siteUrl = "<?= site_url() ?>";
    </script>
    <?php
    if (isset($_SESSION['toastMessage'])) { ?>
        <script>
            $(document).ready(function() {
                showToastMessage("<?= $_SESSION['toastMessage'] ?>", "<?= $_SESSION['toastMessageType'] ?>")
            });
        </script>
        <?php
        // Clear the session variables after displaying the toast
        unset($_SESSION['toastMessage']);
        unset($_SESSION['toastMessageType']);
        ?>
    <?php } ?>
    <div id="app">
        <div class="main-wrapper">
            <?= view('backend/admin/top_and_sidebar') ?>
            <?= view('backend/admin/pages/' . $main_page) ?>
            <?= view('backend/admin/footer') ?>
            <?= view('backend/admin/include-scripts') ?>
            <?= view('backend/admin/partials/queue_tray') ?>
        </div>
    </div>
</body>

</html>