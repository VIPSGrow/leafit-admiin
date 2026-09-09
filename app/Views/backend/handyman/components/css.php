<?php
$session   = \Config\Services::session();
$is_rtl    = $session->get('is_rtl');
$language  = $session->get('language');
$defaultLang = fetch_details('languages', ['is_default' => '1']);

if (empty($language) && !isset($is_rtl)) {
    $is_rtl = $defaultLang[0]['is_rtl'] ?? 0;
} elseif ($is_rtl === null) {
    $is_rtl = 0;
}
$is_rtl = (int) $is_rtl;

$siteData   = get_settings('general_settings', true);
$faviconUrl = (new \App\Services\utility\FileService())->url($siteData['handyman_favicon'] ?? '', 'site', 'public/backend/assets/img/news/img01.jpg');
?>
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" as="style">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
<?php if ($is_rtl === 1): ?>
    <link rel="stylesheet" href="<?= base_url('public/frontend/retro/vendor/bootstrap/css/bootstrap.rtl.min.css') ?>">
<?php else: ?>
    <link rel="stylesheet" href="<?= base_url('public/frontend/retro/vendor/bootstrap/css/bootstrap.min.css') ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/vendor/bootstrap-table.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/vendor/iziToast.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/vendor/daterangepicker.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/vendor/select2.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/vendor/dropzone.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/vendor/cropper.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/googleMap.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/switchery.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/chat.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/backend/assets/css/handyman-panel.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/fontawesome/css/all.css') ?>">
<link rel="stylesheet" href="<?= base_url('public/frontend/retro/css/password-strength.css') ?>">
<link rel="stylesheet" href="https://unpkg.com/@yaireo/tagify/dist/tagify.css">
<link href="https://unpkg.com/filepond/dist/filepond.css" rel="stylesheet">
<link href="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css" rel="stylesheet">
<link href="<?= base_url('public/backend/assets/js/filepond/dist/filepond.css') ?>" rel="stylesheet">
<link href="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-image-preview.css') ?>" rel="stylesheet">
<link href="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-pdf-preview.min.css') ?>" rel="stylesheet">
<link href="<?= base_url('public/backend/assets/js/filepond/dist/filepond-plugin-media-preview.css') ?>" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.3/dist/extensions/fixed-columns/bootstrap-table-fixed-columns.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.css">
<link href="<?= $faviconUrl ?>" rel="icon">
<link href="<?= base_url('public/frontend/retro/img/site/apple-touch-icon.png') ?>" rel="apple-touch-icon">
<script data-turbo-eval="false" src="<?= base_url('public/backend/assets/js/vendor/jquery.min.js') ?>"></script>
<style>
    :root { --partner-navbar-height: 120px; }
    @media (max-width: 1024px) {
        body.handyman-panel .main-content {
            padding-top: calc(var(--partner-navbar-height, 120px) + 12px) !important;
        }
    }
</style>
