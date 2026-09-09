<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Category SEO Settings Model
 * 
 * Handles database operations for translated category SEO settings
 * Stores multi-language translations for SEO fields
 */
class TranslatedCategorySeoSettings_model extends Model
{
    protected $table = 'translated_category_seo_settings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'category_id',
        'language_code',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_schema_markup'
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'category_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'seo_title' => 'permit_empty|max_length[255]',
        'seo_description' => 'permit_empty',
        'seo_keywords' => 'permit_empty',
        'seo_schema_markup' => 'permit_empty'
    ];

    protected $validationMessages = [
        'category_id' => [
            'required' => 'Category ID is required',
            'integer' => 'Category ID must be a valid integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated SEO settings for a specific category and language
     * 
     * @param int $categoryId Category ID
     * @param string $languageCode Language code
     * @return array|null Translated SEO settings or null if not found
     */
    public function getTranslatedSeoSettings(int $categoryId, string $languageCode): ?array
    {
        $result = $this->where([
            'category_id' => $categoryId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific category
     * 
     * @param int $categoryId Category ID
     * @return array Array of translated SEO settings
     */
    public function getAllTranslationsForCategory(int $categoryId): array
    {
        return $this->where('category_id', $categoryId)->findAll();
    }

    /**
     * Save or update translated SEO settings for a category
     * 
     * @param int $categoryId Category ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing seo_title, seo_description, seo_keywords, seo_schema_markup
     * @return bool Success status
     */
    public function saveTranslatedSeoSettings(int $categoryId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'category_id' => $categoryId,
            'language_code' => $languageCode
        ])->first();

        // Prepare data for insertion/update
        $data = [
            'category_id' => $categoryId,
            'language_code' => $languageCode,
            'seo_title' => $translatedData['seo_title'] ?? null,
            'seo_description' => $translatedData['seo_description'] ?? null,
            'seo_keywords' => $translatedData['seo_keywords'] ?? null,
            'seo_schema_markup' => $translatedData['seo_schema_markup'] ?? null
        ];

        if ($existing) {
            // Update existing translation
            return $this->update($existing['id'], $data);
        } else {
            // Create new translation
            return $this->insert($data) !== false;
        }
    }

    /**
     * Delete all SEO translations for a specific category
     * 
     * @param int $categoryId Category ID
     * @return bool Success status
     */
    public function deleteCategorySeoTranslations(int $categoryId): bool
    {
        return $this->where('category_id', $categoryId)->delete() !== false;
    }

    /**
     * Process SEO translations from form data
     * 
     * @param int $categoryId Category ID
     * @param array $translatedFields Translated fields data
     * @return array Result with success status and any errors
     */
    public function processSeoTranslations(int $categoryId, array $translatedFields): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        // Validate category ID
        if (empty($categoryId)) {
            $result['success'] = false;
            $result['errors'][] = 'Category ID is required';
            return $result;
        }

        // Process each language
        foreach ($translatedFields as $languageCode => $fields) {
            if (empty($languageCode) || !is_array($fields)) {
                continue;
            }

            // Filter only SEO fields
            $seoFields = [];
            foreach ($fields as $field => $value) {
                if (in_array($field, ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'])) {
                    $seoFields[$field] = $value;
                }
            }

            // Only save if there are SEO fields to save
            if (!empty($seoFields)) {
                $saveResult = $this->saveTranslatedSeoSettings($categoryId, $languageCode, $seoFields);

                if ($saveResult) {
                    $result['processed_languages'][] = $languageCode;
                } else {
                    $result['success'] = false;
                    $result['errors'][] = "Failed to save SEO translations for language: {$languageCode}";
                }
            }
        }

        return $result;
    }
}
