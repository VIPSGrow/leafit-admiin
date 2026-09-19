<?php
helper('function');
$data = [];
try {
    $data = get_settings('general_settings', true);
} catch (Exception $e) {
    log_message('error', 'Template Error: ' . $e->getMessage());
    echo "<script>console.log('Error in template.php! See logs for details.')</script>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />

    <title><?= $title ?> - Get Services On Demand</title>
    <?= view("frontend/retro/include-css"); ?>
    <script src="<?= base_url('public/frontend/retro/vendor/jQuery/jquery-min.js'); ?>"></script>
    <?php
    isset($data['primary_color']) && $data['primary_color'] != "" ? $primary_color = $data['primary_color'] : $primary_color = '#05a6e8';
    isset($data['secondary_color']) && $data['secondary_color'] != "" ? $secondary_color = $data['secondary_color'] : $secondary_color = '#003e64';
    isset($data['primary_shadow']) && $data['primary_shadow'] != "" ? $primary_shadow = $data['primary_shadow'] : $primary_shadow = '#05A6E8';

    $login_image_url = (new \App\Services\utility\FileService())->url(
        $data['login_image'] ?? null,
        'login_image',
        'public/frontend/retro/Login_BG.jpg'
    );
    ?>
    <style>
        body {
            --primary:
                <?= $primary_color ?>
            ;
            --secondary:
                <?= $secondary_color ?>
            ;
            --nav-link:
                <?= $secondary_color ?>
            ;
            --primary-shadow: 0px 5px 30px
                <?= $primary_shadow ?>
            ;
        }
    </style>
    <script>
        var baseUrl = "<?= base_url() ?>";
        var csrfName = "<?= csrf_token() ?>";
        var csrfHash = "<?= csrf_hash() ?>";
    </script>

    <!-- ==================== PWA SETUP START ==================== -->
    <meta name="theme-color" content="<?= $primary_color ?>">
    <meta name="description" content="PAN India technology-enabled Service Marketplace Platform owned and operated by Activepro Technology Private Limited. Discover, book, and manage professional technical and home services across India.">
    <meta name="keywords" content="LeafIT, service marketplace, technical services, home services, CCTV installation, networking, IT support, AMC services, pan india">
    <meta name="author" content="Activepro Technology Private Limited">

    <link rel="manifest" href="/public/manifest.json" crossorigin="use-credentials">
    <link rel="icon" type="image/png" href="/public/images/icons/favicon.png">
    <link rel="apple-touch-icon" href="/public/images/icons/ios/180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/public/images/icons/ios/152.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/public/images/icons/ios/167.png">
    <link rel="mask-icon" href="/public/images/icons/android/launchericon-192x192.png" color="#05a6e8">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="LeafIT">
    <meta name="application-name" content="LeafIT">
    <meta name="msapplication-TileColor" content="#05a6e8">
    <meta name="msapplication-TileImage" content="/public/images/icons/windows/StoreLogo.scale-100.png">
    <meta name="msapplication-config" content="/public/images/icons/browserconfig.xml">

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= base_url('public/sw.js') ?>')
                    .then((registration) => {
                        console.log('PWA Service Worker Active:', registration.scope);
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            if (newWorker) {
                                newWorker.addEventListener('statechange', () => {
                                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                        if (confirm('A new version is available! Reload to update?')) {
                                            window.location.reload();
                                        }
                                    }
                                });
                            }
                        });
                    })
                    .catch((err) => {
                        console.error('PWA Registration Failed:', err);
                    });
            });
        }
    </script>
    <!-- ==================== PWA SETUP END ==================== -->
</head>

<body>
    <?= view("frontend/retro/header"); ?>
    <?= view("frontend/retro/pages/$main_page", ['login_image_url' => $login_image_url]); ?>
    <?= view("frontend/retro/footer"); ?>
    <?php
    // Show toast from flash data (set by Auth create_partner, Settings, etc.). Safe for JS (quotes in message).
    $toastMessage = session()->getFlashdata('toastMessage');
    $toastType = session()->getFlashdata('toastMessageType');
    if ($toastMessage !== null && $toastMessage !== '') { ?>
        <script>
            $(document).ready(function () {
                showToastMessage(<?= json_encode((string) $toastMessage) ?>, <?= json_encode($toastType ?: 'info') ?>);
            });
        </script>
    <?php } ?>

</body>

</html>