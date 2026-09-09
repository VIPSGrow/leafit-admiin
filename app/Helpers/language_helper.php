<?php

/**
 * Language Helper Functions
 * 
 * Helper functions for working with language files and translations
 * Supports both legacy and new category-based language file structures
 */

/**
 * Load language file from new directory structure
 * 
 * @param string $languageCode Language code
 * @param string $category File category (panel, web, customer_app, provider_app)
 * @return array|null Language data or null if not found
 */
function load_language_file($languageCode, $category = 'panel')
{
    $languageFileService = new \App\Services\LanguageFileService();
    $filePath = $languageFileService->getLanguageFilePath($languageCode, $category);

    if ($filePath && file_exists($filePath)) {
        $content = file_get_contents($filePath);
        if ($content !== false) {
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
    }

    return null;
}

/**
 * Get language label from new file structure
 * 
 * @param string $key Label key
 * @param string $languageCode Language code
 * @param string $category File category
 * @param string $default Default value if not found
 * @return string Label value
 */
function get_language_label($key, $languageCode = null, $category = 'panel', $default = '')
{
    if ($languageCode === null) {
        $session = session();
        $languageCode = $session->get('lang') ?? 'en';
    }

    $languageData = load_language_file($languageCode, $category);

    if ($languageData && isset($languageData[$key])) {
        return $languageData[$key];
    }

    return $default;
}

/**
 * Get all language files for a specific language
 * 
 * @param string $languageCode Language code
 * @return array Available files information
 */
function get_language_files_info($languageCode)
{
    $languageFileService = new \App\Services\LanguageFileService();
    return $languageFileService->getLanguageFiles($languageCode);
}

/**
 * Check if language has any uploaded files
 * 
 * @param string $languageCode Language code
 * @return bool True if language has files
 */
function has_language_files($languageCode)
{
    $files = get_language_files_info($languageCode);
    foreach ($files as $category => $fileInfo) {
        if ($fileInfo['exists'] ?? false) {
            return true;
        }
    }

    return false;
}

/**
 * Migrate legacy language files to new directory structure
 * 
 * @param string $languageCode Language code
 * @return array Migration result with success status and details
 */
function migrate_legacy_language_files($languageCode)
{
    $result = [
        'success' => false,
        'migrated_files' => [],
        'errors' => [],
        'message' => ''
    ];

    $legacyFilePath = FCPATH . '/public/uploads/languages/' . $languageCode . '.json';

    // Check if legacy file exists
    if (!file_exists($legacyFilePath)) {
        $result['message'] = "No legacy file found for language code: {$languageCode}";
        return $result;
    }

    try {
        // Validate the legacy file
        $validation = validate_language_file_json($legacyFilePath);
        if (!$validation['success']) {
            $result['errors'][] = "Invalid JSON in legacy file: " . $validation['error'];
            return $result;
        }

        // Create new directory structure for panel category
        $languageFileService = new \App\Services\LanguageFileService();
        $uploadPath = $languageFileService->createUploadDirectory('panel', $languageCode);

        if (!$uploadPath['success']) {
            $result['errors'][] = "Failed to create directory structure: " . $uploadPath['error'];
            return $result;
        }

        // Generate new filename
        $newFilename = strtolower($languageCode) . '.json';
        $newFilePath = $uploadPath['path'] . '/' . $newFilename;

        // Copy file to new location
        if (copy($legacyFilePath, $newFilePath)) {
            // Verify the copied file
            if (file_exists($newFilePath)) {
                $result['migrated_files'][] = [
                    'from' => $legacyFilePath,
                    'to' => $newFilePath,
                    'category' => 'panel'
                ];

                // Remove the legacy file
                if (unlink($legacyFilePath)) {
                    $result['success'] = true;
                    $result['message'] = "Successfully migrated legacy file for language: {$languageCode}";
                } else {
                    $result['errors'][] = "Failed to remove legacy file after migration";
                    $result['success'] = true; // Migration succeeded, just couldn't remove old file
                }
            } else {
                $result['errors'][] = "Failed to verify migrated file";
            }
        } else {
            $result['errors'][] = "Failed to copy file to new location";
        }
    } catch (\Exception $e) {
        $result['errors'][] = "Migration failed: " . $e->getMessage();
    }

    return $result;
}

/**
 * Migrate all legacy language files to new structure
 * 
 * @return array Migration results for all languages
 */
function migrate_all_legacy_language_files()
{
    $results = [];
    $legacyDir = FCPATH . '/public/uploads/languages/';

    if (!is_dir($legacyDir)) {
        return [
            'success' => false,
            'message' => 'Legacy languages directory does not exist',
            'results' => []
        ];
    }

    // Get all JSON files in the legacy directory
    $files = glob($legacyDir . '*.json');

    if (empty($files)) {
        return [
            'success' => true,
            'message' => 'No legacy files found to migrate',
            'results' => []
        ];
    }

    foreach ($files as $file) {
        $languageCode = basename($file, '.json');
        $results[$languageCode] = migrate_legacy_language_files($languageCode);
    }

    $successCount = 0;
    $errorCount = 0;

    foreach ($results as $result) {
        if ($result['success']) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }

    return [
        'success' => $errorCount === 0,
        'message' => "Migration completed: {$successCount} successful, {$errorCount} failed",
        'results' => $results
    ];
}

/**
 * Check if legacy files exist for a language
 * 
 * @param string $languageCode Language code
 * @return bool True if legacy file exists
 */
function has_legacy_language_file($languageCode)
{
    $legacyFilePath = FCPATH . '/public/uploads/languages/' . $languageCode . '.json';
    return file_exists($legacyFilePath);
}

/**
 * Get list of all legacy language files
 * 
 * @return array List of legacy file paths
 */
function get_legacy_language_files()
{
    $legacyDir = FCPATH . '/public/uploads/languages/';
    $files = [];

    if (is_dir($legacyDir)) {
        $jsonFiles = glob($legacyDir . '*.json');
        foreach ($jsonFiles as $file) {
            $languageCode = basename($file, '.json');
            $files[$languageCode] = [
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }
    }

    return $files;
}

/**
 * Get supported language categories
 * 
 * @return array List of supported categories
 */
function get_supported_language_categories()
{
    $languageFileService = new \App\Services\LanguageFileService();
    return $languageFileService->getSupportedCategories();
}

/**
 * Validate language file JSON content
 * 
 * @param string $filePath Path to JSON file
 * @return array Validation result
 */
function validate_language_file_json($filePath)
{
    try {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [
                'success' => false,
                'error' => 'Unable to read file content'
            ];
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON format: ' . json_last_error_msg()
            ];
        }

        // Additional validation: ensure it's an object/array
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'error' => 'JSON content must be an object or array'
            ];
        }

        return [
            'success' => true,
            'data' => $decoded
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => 'JSON validation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Get language file path for a specific language and category
 * 
 * @param string $languageCode Language code
 * @param string $category File category
 * @return string|null File path or null if not found
 */
function get_language_file_path($languageCode, $category = 'panel')
{
    $languageFileService = new \App\Services\LanguageFileService();
    return $languageFileService->getLanguageFilePath($languageCode, $category);
}

/**
 * Check if language file exists
 * 
 * @param string $languageCode Language code
 * @param string $category File category
 * @return bool True if file exists
 */
function language_file_exists($languageCode, $category = 'panel')
{
    $filePath = get_language_file_path($languageCode, $category);
    return $filePath !== null && file_exists($filePath);
}

/**
 * Get language file size
 * 
 * @param string $languageCode Language code
 * @param string $category File category
 * @return int|false File size in bytes or false if file doesn't exist
 */
function get_language_file_size($languageCode, $category = 'panel')
{
    $filePath = get_language_file_path($languageCode, $category);
    if ($filePath && file_exists($filePath)) {
        return filesize($filePath);
    }
    return false;
}

/**
 * Get language file modification time
 * 
 * @param string $languageCode Language code
 * @param string $category File category
 * @return int|false File modification time or false if file doesn't exist
 */
function get_language_file_modified_time($languageCode, $category = 'panel')
{
    $filePath = get_language_file_path($languageCode, $category);
    if ($filePath && file_exists($filePath)) {
        return filemtime($filePath);
    }
    return false;
}

/**
 * Format language file size for display
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function format_language_file_size($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Get formatted language file information
 * 
 * @param string $languageCode Language code
 * @param string $category File category
 * @return array|null File information or null if not found
 */
function get_formatted_language_file_info($languageCode, $category = 'panel')
{
    $filePath = get_language_file_path($languageCode, $category);
    if ($filePath && file_exists($filePath)) {
        return [
            'path' => $filePath,
            'relative_path' => str_replace(FCPATH, '', $filePath),
            'exists' => true,
            'size' => filesize($filePath),
            'size_formatted' => format_language_file_size(filesize($filePath)),
            'modified' => filemtime($filePath),
            'modified_formatted' => date('Y-m-d H:i:s', filemtime($filePath))
        ];
    }

    return [
        'exists' => false
    ];
}

/**
 * Get current language code from session or default
 * 
 * @return string Current language code
 */
function get_current_language_code()
{
    $session = session();
    $languageCode = $session->get('lang') ?? 'en';
    
    // If no language set in session, try to get from database
    if ($languageCode === 'en') {
        $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code']);
        if (!empty($defaultLanguage)) {
            $languageCode = $defaultLanguage[0]['code'];
        }
    }
    
    return $languageCode;
}
