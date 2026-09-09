<?php
$siteData = get_settings('general_settings', true);

$session         = \Config\Services::session();
$currentLangCode = $session->get('language_code');
if (!$currentLangCode) {
    $defaultLang     = fetch_details('languages', ['is_default' => 1], ['code']);
    $currentLangCode = !empty($defaultLang) ? $defaultLang[0]['code'] : 'en';
}

$copyright = '';
if (isset($siteData['copyright_details'])) {
    if (is_array($siteData['copyright_details'])) {
        $copyright = $siteData['copyright_details'][$currentLangCode]
            ?? reset($siteData['copyright_details'])
            ?: '';
    } else {
        $copyright = (string) $siteData['copyright_details'];
    }
}
$copyright = $copyright ?: 'edemand copyright';
?>
<footer class="main-footer new-footer m-0 p-0 mt-5">
    <div class="mt-4"><?= $copyright ?></div>
</footer>
