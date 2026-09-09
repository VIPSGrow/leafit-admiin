<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Category Details Model
 * 
 * Handles database operations for translated category details
 * Stores multi-language translations for category fields
 */
class TranslatedCategoryDetails_model extends Model
{
    protected $table = 'translated_category_details';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'category_id',
        'language_code',
        'name',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'category_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'name' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'category_id' => [
            'required' => 'Category ID is required',
            'integer' => 'Category ID must be an integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated details for a specific category and language
     * 
     * @param int $categoryId Category ID
     * @param string $languageCode Language code
     * @return array|null Translated details or null if not found
     */
    public function getTranslatedDetails(int $categoryId, string $languageCode): ?array
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
     * @return array Array of translated details indexed by language_code
     */
    public function getAllTranslationsForCategory(int $categoryId): array
    {
        $translations = $this->where('category_id', $categoryId)->findAll();

        // Index translations by language_code for easier access
        $indexedTranslations = [];
        foreach ($translations as $translation) {
            $indexedTranslations[$translation['language_code']] = $translation;
        }

        return $indexedTranslations;
    }

    /**
     * Save or update translated details for a category
     * 
     * @param int $categoryId Category ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing name, description
     * @return bool Success status
     */
    public function saveTranslatedDetails(int $categoryId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'category_id' => $categoryId,
            'language_code' => $languageCode
        ])->first();

        // Merge existing data with new data to preserve other fields
        $data = [
            'category_id' => $categoryId,
            'language_code' => $languageCode,
            'name' => $existing['name'] ?? null,
        ];

        // Update with new translated data
        foreach ($translatedData as $field => $value) {
            if (in_array($field, ['name'])) {
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
     * Delete all translations for a specific category
     * 
     * @param int $categoryId Category ID
     * @return bool Success status
     */
    public function deleteCategoryTranslations(int $categoryId): bool
    {
        return $this->where('category_id', $categoryId)->delete() !== false;
    }

    /**
     * Get translations for multiple categories
     * 
     * @param array $categoryIds Array of category IDs
     * @param string $languageCode Language code
     * @return array Array of translations indexed by category_id
     */
    public function getTranslationsForMultipleCategories(array $categoryIds, string $languageCode): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $result = $this->whereIn('category_id', $categoryIds)
            ->where('language_code', $languageCode)
            ->findAll();

        $translations = [];
        foreach ($result as $translation) {
            $translations[$translation['category_id']] = $translation;
        }

        return $translations;
    }
}
