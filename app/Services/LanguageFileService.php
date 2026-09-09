<?php

namespace App\Services;

use CodeIgniter\Files\File;

/**
 * Language File Service
 * 
 * Handles business logic for language file uploads and management
 * Supports different file types (panel, web, customer_app, provider_app)
 * and saves files to appropriate directory structure
 */
class LanguageFileService
{
    /**
     * Allowed file types for language uploads
     */
    private const ALLOWED_FILE_TYPES = [
        'application/json'
    ];

    /**
     * Supported language file categories
     */
    private const SUPPORTED_CATEGORIES = [
        'panel',
        'web',
        'customer_app',
        'provider_app'
    ];

    /**
     * Base upload path for language files
     */
    private const BASE_UPLOAD_PATH = 'public/uploads/languages/';

    /**
     * Upload language files for different categories
     * 
     * @param array $files Array of uploaded files with category keys
     * @param string $languageCode Language code (e.g., 'en', 'ar', 'tr')
     * @return array Result with success status and file paths
     */
    public function uploadLanguageFiles(array $files, string $languageCode): array
    {
        $result = [
            'success' => true,
            'uploaded_files' => [],
            'errors' => []
        ];

        // Validate language code
        if (empty($languageCode)) {
            $result['success'] = false;
            $result['errors'][] = LANGUAGE_CODE_IS_REQUIRED;
            return $result;
        }

        // Process each uploaded file
        foreach ($files as $category => $file) {
            if (!$file || !$file->isValid()) {
                continue; // Skip invalid files
            }

            $uploadResult = $this->uploadSingleLanguageFile($file, $category, $languageCode);

            if ($uploadResult['success']) {
                $result['uploaded_files'][$category] = $uploadResult['file_path'];
            } else {
                $message = labels('file_upload_failed', 'File upload failed') . " {$category} " . labels('file', 'file') . ": " . $uploadResult['error'];
                $result['errors'][] = $message;
                $result['success'] = false;
            }
        }

        return $result;
    }

    /**
     * Upload a single language file for a specific category
     * 
     * @param File $file Uploaded file object
     * @param string $category File category (panel, web, customer_app, provider_app)
     * @param string $languageCode Language code
     * @return array Upload result
     */
    private function uploadSingleLanguageFile(File $file, string $category, string $languageCode): array
    {
        // Validate category
        if (!in_array($category, self::SUPPORTED_CATEGORIES)) {
            return [
                'success' => false,
                'error' => labels('unsupported_category', 'Unsupported category') . ": {$category}"
            ];
        }

        // Validate file type
        if (!$this->isValidFileType($file)) {
            return [
                'success' => false,
                'error' => labels('invalid_file_type', 'Invalid file type') . '. ' . labels('only_json_files_allowed', 'Only JSON files are allowed') . '.'
            ];
        }

        // Create directory structure
        $uploadPath = $this->createUploadDirectory($category, $languageCode);
        if (!$uploadPath['success']) {
            return $uploadPath;
        }

        // Generate filename
        $filename = $this->generateFilename($languageCode);
        $fullPath = $uploadPath['path'] . '/' . $filename;

        // Move file to destination
        try {
            if ($file->move($uploadPath['path'], $filename)) {
                // Validate JSON content
                $jsonValidation = $this->validateJsonContent($fullPath);
                if (!$jsonValidation['success']) {
                    // Remove invalid file
                    unlink($fullPath);
                    return $jsonValidation;
                }

                return [
                    'success' => true,
                    'file_path' => $fullPath,
                    'relative_path' => str_replace(FCPATH, '', $fullPath),
                    'filename' => $filename
                ];
            } else {
                return [
                    'success' => false,
                    'error' => labels(
                        'failed_to_move_file',
                        'Failed to move uploaded file'
                    ) . ': ' . $file->getError()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => labels(
                    'file_upload_failed',
                    'File upload failed'
                ) . ': ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create upload directory structure
     * 
     * @param string $category File category
     * @param string $languageCode Language code
     * @return array Directory creation result
     */
    public function createUploadDirectory(string $category, string $languageCode): array
    {
        $basePath = FCPATH . self::BASE_UPLOAD_PATH;
        $categoryPath = $basePath . $category;
        $languagePath = $categoryPath . '/' . $languageCode;

        // Create base directory if it doesn't exist
        if (!is_dir($basePath)) {
            if (!mkdir($basePath, 0755, true)) {
                return [
                    'success' => false,
                    'error' => labels(
                        'failed_to_create_base_upload_directory',
                        'Failed to create base upload directory'
                    )
                ];
            }
        }

        // Create category directory if it doesn't exist
        if (!is_dir($categoryPath)) {
            if (!mkdir($categoryPath, 0755, true)) {
                $message = labels('failed_to_create_category_directory', 'Failed to create category directory') . ": {$category}";
                return [
                    'success' => false,
                    'error' => $message
                ];
            }
        }

        // Create language directory if it doesn't exist
        if (!is_dir($languagePath)) {
            if (!mkdir($languagePath, 0755, true)) {
                return [
                    'success' => false,
                    'error' => labels(
                        'failed_to_create_language_directory',
                        'Failed to create language directory'
                    ) . ": {$languageCode}"
                ];
            }
        }

        return [
            'success' => true,
            'path' => $languagePath
        ];
    }

    /**
     * Generate filename for language file
     * 
     * @param string $languageCode Language code
     * @return string Generated filename
     */
    private function generateFilename(string $languageCode): string
    {
        return strtolower($languageCode) . '.json';
    }

    /**
     * Validate file type
     * 
     * @param File $file Uploaded file
     * @return bool True if valid file type
     */
    private function isValidFileType(File $file): bool
    {
        $mimeType = $file->getMimeType();
        return in_array($mimeType, self::ALLOWED_FILE_TYPES);
    }

    /**
     * Validate JSON content of uploaded file
     * 
     * @param string $filePath Full path to the file
     * @return array Validation result
     */
    private function validateJsonContent(string $filePath): array
    {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return [
                    'success' => false,
                    'error' => labels(
                        'unable_to_read_file_content',
                        'Unable to read file content'
                    )
                ];
            }

            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => labels(
                        'invalid_json_format',
                        'Invalid JSON format'
                    ) . ': ' . json_last_error_msg()
                ];
            }

            // Additional validation: ensure it's an object/array
            if (!is_array($decoded)) {
                return [
                    'success' => false,
                    'error' => labels(
                        'json_content_must_be_an_object_or_array',
                        'JSON content must be an object or array'
                    )
                ];
            }

            return [
                'success' => true,
                'data' => $decoded
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => labels(
                    'json_validation_failed',
                    'JSON validation failed'
                ) . ': ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get file path for a specific language and category
     * 
     * @param string $languageCode Language code
     * @param string $category File category
     * @return string|null File path or null if not found
     */
    public function getLanguageFilePath(string $languageCode, string $category): ?string
    {
        if (!in_array($category, self::SUPPORTED_CATEGORIES)) {
            return null;
        }

        $filePath = $this->buildLanguageFilePath($languageCode, $category);
        return file_exists($filePath) ? $filePath : null;
    }

    /**
     * Build the absolute path for a language file even if it does not exist yet
     * This helps controller logic rename files when the language code changes.
     */
    public function buildLanguageFilePath(string $languageCode, string $category): string
    {
        $sanitizedCode = strtolower($languageCode);
        return $this->buildLanguageDirectoryPath($languageCode, $category) . '/' . $sanitizedCode . '.json';
    }

    /**
     * Build the directory path for a language/category combination.
     */
    public function buildLanguageDirectoryPath(string $languageCode, string $category): string
    {
        $sanitizedCode = strtolower($languageCode);
        return FCPATH . self::BASE_UPLOAD_PATH . $category . '/' . $sanitizedCode;
    }

    /**
     * Delete language files for a specific language
     * 
     * @param string $languageCode Language code
     * @return array Deletion result
     */
    public function deleteLanguageFiles(string $languageCode): array
    {
        $deletedFiles = [];
        $errors = [];

        foreach (self::SUPPORTED_CATEGORIES as $category) {
            $filePath = $this->getLanguageFilePath($languageCode, $category);
            if ($filePath && file_exists($filePath)) {
                if (unlink($filePath)) {
                    $deletedFiles[] = $filePath;
                } else {
                    $errors[] = labels(
                        'failed_to_delete_file',
                        'Failed to delete file'
                    ) . ": {$filePath}";
                }
            }
        }

        return [
            'success' => empty($errors),
            'deleted_files' => $deletedFiles,
            'errors' => $errors
        ];
    }

    /**
     * Get all language files for a specific language
     * 
     * @param string $languageCode Language code
     * @return array Available files
     */
    public function getLanguageFiles(string $languageCode): array
    {
        $files = [];

        foreach (self::SUPPORTED_CATEGORIES as $category) {
            $filePath = $this->getLanguageFilePath($languageCode, $category);
            if ($filePath) {
                $files[$category] = [
                    'path' => $filePath,
                    'relative_path' => str_replace(FCPATH, '', $filePath),
                    'exists' => true,
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath)
                ];
            } else {
                $files[$category] = [
                    'exists' => false
                ];
            }
        }

        return $files;
    }

    /**
     * Get supported categories
     * 
     * @return array List of supported categories
     */
    public function getSupportedCategories(): array
    {
        return self::SUPPORTED_CATEGORIES;
    }

    /**
     * Check if a language file exists for a single specific platform.
     *
     * Use this when the caller already knows which platform to check
     * (e.g. the request carries a Platform header with value 'web' or 'customer_app').
     * Avoids unnecessary file-system checks for unrelated platforms.
     *
     * @param string $languageCode The language code (e.g. 'en', 'ar')
     * @param string $platform     One of: 'web', 'customer_app', 'provider_app', 'panel'
     * @return bool True only if the file for that platform exists
     */
    public function hasLanguageFileForPlatform(string $languageCode, string $platform): bool
    {
        // Reject unknown platforms early — avoids ambiguous true/false results.
        if (!in_array($platform, self::SUPPORTED_CATEGORIES)) {
            return false;
        }

        $filePath = $this->buildLanguageFilePath($languageCode, $platform);
        return file_exists($filePath);
    }

    /**
     * Check if language has files for provider app platform only
     * 
     * This method verifies that language files exist for provider_app platform
     * Used by the partner API endpoint
     * 
     * @param string $languageCode Language code
     * @return bool True if language has files for provider_app platform
     */
    public function hasLanguageFilesForProviderApp(string $languageCode): bool
    {
        // Check if file exists for provider_app platform only
        $filePath = $this->getLanguageFilePath($languageCode, 'provider_app');
        return $filePath !== null && file_exists($filePath);
    }

    /**
     * Check if language has panel JSON file and corresponding PHP translation file
     * 
     * This method verifies that both JSON file exists in panel directory
     * and corresponding PHP translation file exists for admin panel use
     * 
     * @param string $languageCode Language code
     * @return bool True if both JSON and PHP files exist
     */
    public function hasPanelLanguageFiles(string $languageCode): bool
    {
        // Check if JSON file exists in panel directory
        $jsonFilePath = $this->getLanguageFilePath($languageCode, 'panel');
        if (!$jsonFilePath || !file_exists($jsonFilePath)) {
            return false;
        }

        // Check if corresponding PHP translation file exists
        $phpFilePath = APPPATH . 'Language/' . $languageCode . '/Text.php';
        if (!file_exists($phpFilePath)) {
            return false;
        }

        return true;
    }
}
