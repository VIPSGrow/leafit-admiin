<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Service SEO Settings Model
 * 
 * Handles database operations for translated service SEO settings
 * Stores multi-language translations for SEO fields
 */
class TranslatedServiceSeoSettings_model extends Model
{
    protected $table = 'translated_service_seo_settings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'service_id',
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
        'service_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'seo_title' => 'permit_empty|max_length[255]',
        'seo_description' => 'permit_empty',
        'seo_keywords' => 'permit_empty',
        'seo_schema_markup' => 'permit_empty'
    ];

    protected $validationMessages = [
        'service_id' => [
            'required' => 'Service ID is required',
            'integer' => 'Service ID must be a valid integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated SEO settings for a specific service and language
     * 
     * @param int $serviceId Service ID
     * @param string $languageCode Language code
     * @return array|null Translated SEO settings or null if not found
     */
    public function getTranslatedSeoSettings(int $serviceId, string $languageCode): ?array
    {
        $result = $this->where([
            'service_id' => $serviceId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific service
     * 
     * @param int $serviceId Service ID
     * @return array Array of translated SEO settings
     */
    public function getAllTranslationsForService(int $serviceId): array
    {
        return $this->where('service_id', $serviceId)->findAll();
    }

    /**
     * Save or update translated SEO settings for a service
     * 
     * @param int $serviceId Service ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing seo_title, seo_description, seo_keywords, seo_schema_markup
     * @return bool Success status
     */
    public function saveTranslatedSeoSettings(int $serviceId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'service_id' => $serviceId,
            'language_code' => $languageCode
        ])->first();

        // Prepare data for insertion/update
        $data = [
            'service_id' => $serviceId,
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
     * Delete all SEO translations for a specific service
     * 
     * @param int $serviceId Service ID
     * @return bool Success status
     */
    public function deleteServiceSeoTranslations(int $serviceId): bool
    {
        return $this->where('service_id', $serviceId)->delete() !== false;
    }

    /**
     * Process SEO translations from form data
     * 
     * @param int $serviceId Service ID
     * @param array $translatedFields Translated fields data
     * @return array Result with success status and any errors
     */
    public function processSeoTranslations(int $serviceId, array $translatedFields): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        // Validate service ID
        if (empty($serviceId)) {
            $result['success'] = false;
            $result['errors'][] = 'Service ID is required';
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
                $saveResult = $this->saveTranslatedSeoSettings($serviceId, $languageCode, $seoFields);

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
