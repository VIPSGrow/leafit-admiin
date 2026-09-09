<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Service Details Model
 * 
 * Handles database operations for translated service details
 * Stores multi-language translations for service fields
 */
class TranslatedServiceDetails_model extends Model
{
    protected $table = 'translated_service_details';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'service_id',
        'language_code',
        'title',
        'description',
        'long_description',
        'tags',
        'faqs'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'service_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'title' => 'permit_empty|max_length[255]',
        'description' => 'permit_empty',
        'long_description' => 'permit_empty',
        'tags' => 'permit_empty',
        'faqs' => 'permit_empty'
    ];

    protected $validationMessages = [
        'service_id' => [
            'required' => 'Service ID is required',
            'integer' => 'Service ID must be an integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated details for a specific service and language
     * 
     * @param int $serviceId Service ID
     * @param string $languageCode Language code
     * @return array|null Translated details or null if not found
     */
    public function getTranslatedDetails(int $serviceId, string $languageCode): ?array
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
     * @return array Array of translated details
     */
    public function getAllTranslationsForService(int $serviceId): array
    {
        return $this->where('service_id', $serviceId)->findAll();
    }

    /**
     * Save or update translated details for a service
     * 
     * @param int $serviceId Service ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing title, description, long_description, tags, faqs
     * @return bool Success status
     */
    public function saveTranslatedDetails(int $serviceId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'service_id' => $serviceId,
            'language_code' => $languageCode
        ])->first();


        // Merge existing data with new data to preserve other fields
        $data = [
            'service_id' => $serviceId,
            'language_code' => $languageCode,
            'title' => $existing['title'] ?? null,
            'description' => $existing['description'] ?? null,
            'long_description' => $existing['long_description'] ?? null,
            'tags' => $existing['tags'] ?? null,
            'faqs' => $existing['faqs'] ?? null
        ];

        // Update with new translated data
        foreach ($translatedData as $field => $value) {
            if (in_array($field, ['title', 'description', 'long_description', 'tags', 'faqs'])) {
                $data[$field] = $value;
            } else {
                log_message('debug', '[TRANSLATION_MODEL] Skipping unknown field: ' . $field);
            }
        }

        $result = false;
        if ($existing) {
            // Update existing record
            $result = $this->update($existing['id'], $data);
        } else {
            // Insert new record
            $insertResult = $this->insert($data);
            $result = $insertResult !== false;
        }

        if (!$result) {
            log_message('error', '[TRANSLATION_MODEL] Database operation failed for service ' . $serviceId . ', language: ' . $languageCode);
        }

        return $result;
    }

    /**
     * Delete all translations for a specific service
     * 
     * @param int $serviceId Service ID
     * @return bool Success status
     */
    public function deleteServiceTranslations(int $serviceId): bool
    {
        return $this->where('service_id', $serviceId)->delete() !== false;
    }

    /**
     * Get translations for multiple services
     * 
     * @param array $serviceIds Array of service IDs
     * @param string $languageCode Language code
     * @return array Array of translations indexed by service_id
     */
    public function getTranslationsForMultipleServices(array $serviceIds, string $languageCode): array
    {
        if (empty($serviceIds)) {
            return [];
        }

        $results = $this->whereIn('service_id', $serviceIds)
            ->where('language_code', $languageCode)
            ->findAll();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result['service_id']] = $result;
        }

        return $translations;
    }

    /**
     * Check if translation exists for a service and language
     * 
     * @param int $serviceId Service ID
     * @param string $languageCode Language code
     * @return bool True if translation exists
     */
    public function translationExists(int $serviceId, string $languageCode): bool
    {
        return $this->where([
            'service_id' => $serviceId,
            'language_code' => $languageCode
        ])->countAllResults() > 0;
    }

    /**
     * Get count of translations for a service
     * 
     * @param int $serviceId Service ID
     * @return int Number of translations
     */
    public function getTranslationCount(int $serviceId): int
    {
        return $this->where('service_id', $serviceId)->countAllResults();
    }

    /**
     * Get all translations for multiple services in a single query
     * Optimized method to prevent N+1 queries when processing multiple services
     * 
     * @param array $serviceIds Array of service IDs
     * @return array Array of translations indexed by service_id, then by language_code
     */
    public function getAllTranslationsForMultipleServices(array $serviceIds): array
    {
        if (empty($serviceIds)) {
            return [];
        }

        // Fetch all translations for all services in a single query
        $results = $this->whereIn('service_id', $serviceIds)->findAll();

        // Index results by service_id, then by language_code for efficient lookup
        $translations = [];
        foreach ($results as $result) {
            $serviceId = $result['service_id'];
            $languageCode = $result['language_code'];

            if (!isset($translations[$serviceId])) {
                $translations[$serviceId] = [];
            }

            $translations[$serviceId][$languageCode] = $result;
        }

        return $translations;
    }
}
