<?php
$data = get_settings('general_settings', true);

// Function to get copyright details based on current language
function getCopyrightDetails($data) {
    // Get current language from session or default
    $session = \Config\Services::session();
    $current_language = $session->get('language_code');
    
    // If no current language, get default language
    if (!$current_language) {
        $default_lang = fetch_details('languages', ['is_default' => 1], ['code']);
        $current_language = !empty($default_lang) ? $default_lang[0]['code'] : 'en';
    }
    
    // Check if copyright_details is multilingual (array format)
    if (isset($data['copyright_details']) && is_array($data['copyright_details'])) {
        // New multilingual format
        if (isset($data['copyright_details'][$current_language]) && !empty($data['copyright_details'][$current_language])) {
            return $data['copyright_details'][$current_language];
        }
        
        // Fallback to first available translation
        foreach ($data['copyright_details'] as $lang => $copyright) {
            if (!empty($copyright)) {
                return $copyright;
            }
        }
    } 
    // Check if copyright_details is old single string format
    else if (isset($data['copyright_details']) && is_string($data['copyright_details']) && !empty($data['copyright_details'])) {
        return $data['copyright_details'];
    }
    
    // Final fallback
    return "edemand copyright";
}

$copyright_details = getCopyrightDetails($data);
?>
<footer class="main-footer new-footer m-0 p-0 mt-5">
    <div class="mt-4">
    <?= $copyright_details ?>
    </div>
    </div>
</footer>