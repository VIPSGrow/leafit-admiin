<?php
$data = get_settings('general_settings', true);

// Use the helper function for company title with proper language fallback
$company_title = get_company_title_with_fallback($data);
?>
<!DOCTYPE html>
<html lang="en">
<?php
isset($data['primary_color']) && $data['primary_color'] != "" ?  $primary_color = $data['primary_color'] : $primary_color =  '#05a6e8';
isset($data['secondary_color']) && $data['secondary_color'] != "" ?  $secondary_color = $data['secondary_color'] : $secondary_color =  '#003e64';
isset($data['primary_shadow']) && $data['primary_shadow'] != "" ?  $primary_shadow = $data['primary_shadow'] : $primary_shadow =  '#05A6E8';
?>

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <title><?= $title ?> &mdash; <?= $company_title; ?></title>

    <?= view('backend/partner/include-css') ?>

    <style>
        body {
            --primary-color: <?= $primary_color ?>;
            --secondary-color: <?= $secondary_color ?>;

        }
    </style>
    <?php
    // Microsoft Clarity Integration - Load only if enabled and project ID is set
    $api_key_settings = get_settings('api_key_settings', true);
    $clarity_project_id = isset($api_key_settings['microsoft_clarity_project_id']) ? $api_key_settings['microsoft_clarity_project_id'] : '';
    $clarity_enabled = isset($api_key_settings['microsoft_clarity_enabled']) && $api_key_settings['microsoft_clarity_enabled'] == '1';

    // Only load Clarity script if project ID exists and is enabled
    if (!empty($clarity_project_id) && $clarity_enabled) {
    ?>
        <!-- Microsoft Clarity Tracking Script -->
        <script type="text/javascript">
            (function(c, l, a, r, i, t, y) {
                c[a] = c[a] || function() {
                    (c[a].q = c[a].q || []).push(arguments)
                };
                t = l.createElement(r);
                t.async = 1;
                t.src = "https://www.clarity.ms/tag/" + i;
                y = l.getElementsByTagName(r)[0];
                y.parentNode.insertBefore(t, y);
            })(window, document, "clarity", "script", "<?= htmlspecialchars($clarity_project_id) ?>");
        </script>
    <?php
    }
    ?>
    <script>
        var baseUrl = "<?= base_url() ?>";
        var siteUrl = "<?= site_url() ?>";
        var csrfName = "<?= csrf_token(); ?>";
        var csrfHash = "<?= csrf_hash();  ?>";
    </script>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
</head>

<!--
    Partner panel body marker class.
    Why:
    - We need a safe way to scope "partner-only" CSS fixes.
    - This prevents changes from leaking into admin/front pages.
-->

<body class="partner-panel<?= (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) ? ' has-demo-cta' : '' ?>">
    <div id="app">
        <div class="main-wrapper">
            <?= view('backend/partner/top_and_sidebar') ?>
            <?= view('backend/partner/pages/' . $main_page) ?>
            <?= view('backend/partner/footer') ?>
            <?= view('backend/partner/include-scripts') ?>
            <?php if (isset($_SESSION['toastMessage'])) { ?>
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
        </div>
    </div>

</body>

</html>