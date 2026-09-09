<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Custom Page Details Model
 *
 * Handles database operations for translated custom page details.
 * Stores multi-language translations for custom page fields (title, content).
 * Follows the same pattern as TranslatedBlogDetailsModel.
 */
class TranslatedCustomPageDetailsModel extends Model
{
    protected $table = 'custom_page_translations';
    protected $primaryKey = 'id';
    protected $allowedFields = ['custom_page_id', 'language_code', 'title', 'content'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'custom_page_id' => 'required|integer',
        'language_code' => 'required|max_length[16]',
        'title' => 'permit_empty|max_length[255]',
        'content' => 'permit_empty',
    ];

    protected $validationMessages = [
        'custom_page_id' => [
            'required' => 'Custom page ID is required',
            'integer' => 'Custom page ID must be an integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 16 characters'
        ]
    ];

    /**
     * Save custom page translations for multiple languages.
     * Uses delete + insertBatch approach (follows TranslatedBlogDetailsModel pattern).
     *
     * @param int $customPageId The custom page ID
     * @param array $translations Array keyed by language_code:
     *                            ['en' => ['title' => '...', 'content' => '...'], 'fr' => [...]]
     * @return bool True if successful, false otherwise
     */
    public function saveTranslations($customPageId, $translations)
    {
        try {
            // Delete existing translations for this custom page
            $this->where('custom_page_id', $customPageId)->delete();

            // Build batch insert data
            $translationData = [];
            foreach ($translations as $language_code => $fields) {
                // Skip if no fields provided for this language
                if (empty($fields) || !is_array($fields)) {
                    continue;
                }

                // Check if at least one field has content
                $hasContent = false;
                $cleanFields = [];

                foreach (['title', 'content'] as $field) {
                    $value = isset($fields[$field]) ? trim($fields[$field]) : '';
                    $cleanFields[$field] = $value;
                    if (!empty($value)) {
                        $hasContent = true;
                    }
                }

                // Only save if at least one field has content
                if ($hasContent) {
                    $translationData[] = [
                        'custom_page_id' => $customPageId,
                        'language_code' => $language_code,
                        'title' => $cleanFields['title'],
                        'content' => $cleanFields['content'],
                    ];
                }
            }

            // Insert all translations at once
            if (!empty($translationData)) {
                return $this->insertBatch($translationData);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving custom page translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translated fields for a specific custom page and language
     *
     * @param int $customPageId The custom page ID
     * @param string $languageCode The language code
     * @return array|null The translated fields or null if not found
     */
    public function getTranslation($customPageId, $languageCode)
    {
        $result = $this->where([
            'custom_page_id' => $customPageId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific custom page
     *
     * @param int $customPageId The custom page ID
     * @return array Array of translations with language codes as keys
     */
    public function getAllTranslations($customPageId)
    {
        $results = $this->where('custom_page_id', $customPageId)->findAll();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result['language_code']] = [
                'title' => $result['title'],
                'content' => $result['content'],
            ];
        }

        return $translations;
    }

    /**
     * Delete all translations for a specific custom page
     *
     * @param int $customPageId The custom page ID
     * @return bool True if successful, false otherwise
     */
    public function deleteTranslations($customPageId)
    {
        try {
            return $this->where('custom_page_id', $customPageId)->delete();
        } catch (\Exception $e) {
            log_message('error', 'Error deleting custom page translations: ' . $e->getMessage());
            return false;
        }
    }
}
