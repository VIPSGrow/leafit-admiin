<?php
$data = get_settings('general_settings', true);
$company = get_company_title_with_fallback($data);
?>
<footer class="main-footer">
    <div class="footer-left">
        <?= labels('copyright', "Copyright") ?> &copy; <?= date('Y') ?> <a href="#"><?= $company ?></a>
    </div>
    <div class="footer-right"></div>
</footer>