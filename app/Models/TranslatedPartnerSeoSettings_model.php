<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Partner SEO Settings Model
 * 
 * Handles database operations for translated partner SEO settings
 * Stores multi-language translations for SEO fields
 */
class TranslatedPartnerSeoSettings_model extends Model
{
    protected $table = 'translated_partner_seo_settings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'partner_id',
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
        'partner_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'seo_title' => 'permit_empty|max_length[255]',
        'seo_description' => 'permit_empty',
        'seo_keywords' => 'permit_empty',
        'seo_schema_markup' => 'permit_empty'
    ];

    protected $validationMessages = [
        'partner_id' => [
            'required' => 'Partner ID is required',
            'integer' => 'Partner ID must be a valid integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated SEO settings for a specific partner and language
     * 
     * @param int $partnerId Partner ID
     * @param string $languageCode Language code
     * @return array|null Translated SEO settings or null if not found
     */
    public function getTranslatedSeoSettings(int $partnerId, string $languageCode): ?array
    {
        $result = $this->where([
            'partner_id' => $partnerId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific partner
     * 
     * @param int $partnerId Partner ID
     * @return array Array of translated SEO settings
     */
    public function getAllTranslationsForPartner(int $partnerId): array
    {
        return $this->where('partner_id', $partnerId)->findAll();
    }

    /**
     * Save or update translated SEO settings for a partner
     * 
     * @param int $partnerId Partner ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing seo_title, seo_description, seo_keywords, seo_schema_markup
     * @return bool Success status
     */
    public function saveTranslatedSeoSettings(int $partnerId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'partner_id' => $partnerId,
            'language_code' => $languageCode
        ])->first();

        // Merge existing data with new data to preserve other fields
        $data = [
            'partner_id' => $partnerId,
            'language_code' => $languageCode,
            'seo_title' => $existing['seo_title'] ?? null,
            'seo_description' => $existing['seo_description'] ?? null,
            'seo_keywords' => $existing['seo_keywords'] ?? null,
            'seo_schema_markup' => $existing['seo_schema_markup'] ?? null
        ];

        // Update with new translated data
        foreach ($translatedData as $field => $value) {
            if (in_array($field, ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'])) {
                $data[$field] = $value;
            }
        }

        if ($existing) {
            // Update existing record
            return $this->update($existing['id'], $data);
        } else {
            // Insert new record
            return $this->insert($data) !== false;
        }
    }

    /**
     * Delete all SEO translations for a specific partner
     * 
     * @param int $partnerId Partner ID
     * @return bool Success status
     */
    public function deletePartnerSeoTranslations(int $partnerId): bool
    {
        return $this->where('partner_id', $partnerId)->delete() !== false;
    }

    /**
     * Delete specific SEO translation for a partner and language
     * 
     * @param int $partnerId Partner ID
     * @param string $languageCode Language code
     * @return bool Success status
     */
    public function deleteSeoTranslation(int $partnerId, string $languageCode): bool
    {
        return $this->where([
            'partner_id' => $partnerId,
            'language_code' => $languageCode
        ])->delete() !== false;
    }

    /**
     * Process SEO translations from form data
     * 
     * @param int $partnerId Partner ID
     * @param array $translatedFields Translated fields data
     * @return array Result with success status and any errors
     */
    public function processSeoTranslations(int $partnerId, array $translatedFields): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        // Validate partner ID
        if (empty($partnerId)) {
            $result['success'] = false;
            $result['errors'][] = 'Partner ID is required';
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
                $saveResult = $this->saveTranslatedSeoSettings($partnerId, $languageCode, $seoFields);

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
