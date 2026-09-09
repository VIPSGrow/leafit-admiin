<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Featured Sections Model
 * 
 * Handles database operations for translated featured section details
 * Stores multi-language translations for featured section fields
 */
class TranslatedFeaturedSections_model extends Model
{
    protected $table = 'translated_featured_sections';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'section_id',
        'language_code',
        'title',
        'description',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'section_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'title' => 'permit_empty|max_length[255]',
        'description' => 'permit_empty',
    ];

    protected $validationMessages = [
        'section_id' => [
            'required' => 'Section ID is required',
            'integer' => 'Section ID must be an integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated details for a specific section and language
     * 
     * @param int $sectionId Section ID
     * @param string $languageCode Language code
     * @return array|null Translated details or null if not found
     */
    public function getTranslatedDetails(int $sectionId, string $languageCode): ?array
    {
        $result = $this->where([
            'section_id' => $sectionId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific section
     * 
     * @param int $sectionId Section ID
     * @return array Array of translated details
     */
    public function getAllTranslationsForSection(int $sectionId): array
    {
        return $this->where('section_id', $sectionId)->findAll();
    }

    /**
     * Save or update translated details for a section
     * 
     * @param int $sectionId Section ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing title, description
     * @return bool Success status
     */
    public function saveTranslatedDetails(int $sectionId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'section_id' => $sectionId,
            'language_code' => $languageCode
        ])->first();

        // Merge existing data with new data to preserve other fields
        $data = [
            'section_id' => $sectionId,
            'language_code' => $languageCode,
            'title' => $existing['title'] ?? null,
            'description' => $existing['description'] ?? null,
        ];

        // Update with new translated data
        foreach ($translatedData as $field => $value) {
            if (in_array($field, ['title', 'description'])) {
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
     * Delete all translations for a specific section
     * 
     * @param int $sectionId Section ID
     * @return bool Success status
     */
    public function deleteSectionTranslations(int $sectionId): bool
    {
        return $this->where('section_id', $sectionId)->delete() !== false;
    }

    /**
     * Get translations for multiple sections
     * 
     * @param array $sectionIds Array of section IDs
     * @param string $languageCode Language code
     * @return array Array of translations indexed by section_id
     */
    public function getTranslationsForMultipleSections(array $sectionIds, string $languageCode): array
    {
        if (empty($sectionIds)) {
            return [];
        }

        $results = $this->whereIn('section_id', $sectionIds)
            ->where('language_code', $languageCode)
            ->findAll();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result['section_id']] = $result;
        }

        return $translations;
    }
}
