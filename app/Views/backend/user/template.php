<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <title><?= $title ?> &mdash; <?= get_company_title_with_fallback($data); ?></title>

    <?= view('backend/user/include-css') ?>
    <?php
    $data = get_settings('general_settings', true);
    isset($data['primary_color']) && $data['primary_color'] != "" ?  $primary_color = $data['primary_color'] : $primary_color =  '#05a6e8';
    isset($data['secondary_color']) && $data['secondary_color'] != "" ?  $secondary_color = $data['secondary_color'] : $secondary_color =  '#003e64';
    isset($data['primary_shadow']) && $data['primary_shadow'] != "" ?  $primary_shadow = $data['primary_shadow'] : $primary_shadow =  '#05A6E8';
    ?>
    <style>
        body {
            --primary-color: <?= $primary_color ?>;
            --secondary-color: <?= $secondary_color ?>;
        }
    </style>

    <!-- PWA -->
    <meta name="theme-color" content="#05a6e8">
    <link rel="manifest" href="/public/manifest.json" crossorigin="use-credentials">
    <link rel="icon" type="image/png" href="/public/images/icons/favicon.png">
    <link rel="apple-touch-icon" href="/public/images/icons/ios/180.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/public/sw.js')
                    .then((reg) => console.log('PWA SW Active:', reg.scope))
                    .catch((err) => console.error('PWA SW Failed:', err));
            });
        }
    </script>
    <!-- End PWA -->
</head>

<body>

    <div id="app">
        <div class="main-wrapper">
            <?= view('backend/user/top_and_sidebar') ?>
            <?= view('backend/user/pages/' . $main_page) ?>
            <?= view('backend/user/footer') ?>
            <?= view('backend/user/include-scripts') ?>
            <?php if (isset($_SESSION['toastMessage'])) { ?>
                <script>
                    $(document).ready(function() {
                        showToastMessage("<?= $_SESSION['toastMessage'] ?>", "<?= $_SESSION['toastMessageType'] ?>")
                    });
                </script>";
            <?php } ?>
        </div>
    </div>

</body>

</html>