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
    <script src="<?= base_url('public/frontend/retro/vendor/jQuery/jquery-min.js');  ?>"></script>
    <?php
    isset($data['primary_color']) && $data['primary_color'] != "" ?  $primary_color = $data['primary_color'] : $primary_color =  '#05a6e8';
    isset($data['secondary_color']) && $data['secondary_color'] != "" ?  $secondary_color = $data['secondary_color'] : $secondary_color =  '#003e64';
    isset($data['primary_shadow']) && $data['primary_shadow'] != "" ?  $primary_shadow = $data['primary_shadow'] : $primary_shadow =  '#05A6E8';

    $login_image_url = (new \App\Services\utility\FileService())->url(
        $data['login_image'] ?? null,
        'login_image',
        'public/frontend/retro/Login_BG.jpg'
    );
    ?>
    <style>
        body {
            --primary: <?= $primary_color ?>;
            --secondary: <?= $secondary_color ?>;
            --nav-link: <?= $secondary_color ?>;
            --primary-shadow: 0px 5px 30px <?= $primary_shadow ?>;
        }
    </style>
    <script>
        var baseUrl = "<?= base_url() ?>";
        var csrfName = "<?= csrf_token() ?>";
        var csrfHash = "<?= csrf_hash() ?>";
    </script>
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
            $(document).ready(function() {
                showToastMessage(<?= json_encode((string)$toastMessage) ?>, <?= json_encode($toastType ?: 'info') ?>);
            });
        </script>
    <?php } ?>

</body>

</html>