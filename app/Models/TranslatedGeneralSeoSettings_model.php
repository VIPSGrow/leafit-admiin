<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated General SEO Settings Model
 * 
 * Handles database operations for translated general SEO settings
 * Stores multi-language translations for SEO fields
 */
class TranslatedGeneralSeoSettings_model extends Model
{
    protected $table = 'translated_seo_settings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'seo_id',
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
        'seo_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'seo_title' => 'permit_empty|max_length[255]',
        'seo_description' => 'permit_empty',
        'seo_keywords' => 'permit_empty',
        'seo_schema_markup' => 'permit_empty'
    ];

    protected $validationMessages = [
        'seo_id' => [
            'required' => 'SEO ID is required',
            'integer' => 'SEO ID must be a valid integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated SEO settings for a specific seo_id and language
     * 
     * @param int $seoId SEO ID from seo_settings table
     * @param string $languageCode Language code
     * @return array|null Translated SEO settings or null if not found
     */
    public function getTranslatedSeoSettings(int $seoId, string $languageCode): ?array
    {
        $result = $this->where([
            'seo_id' => $seoId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific seo_id
     * 
     * @param int $seoId SEO ID from seo_settings table
     * @return array Array of translated SEO settings
     */
    public function getAllTranslationsForSeo(int $seoId): array
    {
        return $this->where('seo_id', $seoId)->findAll();
    }

    /**
     * Save or update translated SEO settings
     * 
     * @param int $seoId SEO ID from seo_settings table
     * @param string $languageCode Language code
     * @param array $translatedData Array containing seo_title, seo_description, seo_keywords, seo_schema_markup
     * @return bool Success status
     */
    public function saveTranslatedSeoSettings(int $seoId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'seo_id' => $seoId,
            'language_code' => $languageCode
        ])->first();

        // Prepare data for insertion/update
        $data = [
            'seo_id' => $seoId,
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
     * Delete all SEO translations for a specific seo_id
     * 
     * @param int $seoId SEO ID from seo_settings table
     * @return bool Success status
     */
    public function deleteSeoTranslations(int $seoId): bool
    {
        return $this->where('seo_id', $seoId)->delete() !== false;
    }

    /**
     * Delete specific SEO translation for a seo_id and language
     * 
     * @param int $seoId SEO ID from seo_settings table
     * @param string $languageCode Language code
     * @return bool Success status
     */
    public function deleteSeoTranslation(int $seoId, string $languageCode): bool
    {
        return $this->where([
            'seo_id' => $seoId,
            'language_code' => $languageCode
        ])->delete() !== false;
    }

    /**
     * Process SEO translations from form data
     * 
     * Accepts data in lang[field] format (restructured from field[lang] format)
     * 
     * @param int $seoId SEO ID from seo_settings table
     * @param array $translatedFields Translated fields data in lang[field] format
     * @return array Result with success status and any errors
     */
    public function processSeoTranslations(int $seoId, array $translatedFields): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        // Validate seo_id
        if (empty($seoId)) {
            $result['success'] = false;
            $result['errors'][] = 'SEO ID is required';
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
                $saveResult = $this->saveTranslatedSeoSettings($seoId, $languageCode, $seoFields);

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

