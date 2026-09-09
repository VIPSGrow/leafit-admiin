<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Tax Details Model
 * 
 * Handles database operations for translated tax details
 * Stores multi-language translations for tax title field
 */
class TranslatedTaxDetails_model extends Model
{
    protected $table = 'translated_tax_details';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    // Fields that can be inserted/updated
    protected $allowedFields = [
        'tax_id',
        'language_code',
        'title',
    ];

    // Enable timestamps for created_at and updated_at
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation rules for data integrity
    protected $validationRules = [
        'tax_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'title' => 'permit_empty|max_length[255]',
    ];

    // Custom validation error messages
    protected $validationMessages = [
        'tax_id' => [
            'required' => 'Tax ID is required',
            'integer' => 'Tax ID must be an integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated details for a specific tax and language
     * 
     * @param int $taxId Tax ID
     * @param string $languageCode Language code (e.g., 'en', 'hi', 'ar')
     * @return array|null Translated details or null if not found
     */
    public function getTranslatedDetails(int $taxId, string $languageCode): ?array
    {
        $result = $this->where([
            'tax_id' => $taxId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific tax
     * 
     * @param int $taxId Tax ID
     * @return array Array of translated details for all languages
     */
    public function getAllTranslationsForTax(int $taxId): array
    {
        return $this->where('tax_id', $taxId)->findAll();
    }

    /**
     * Save or update translated details for a tax
     * 
     * This method will insert a new record if translation doesn't exist
     * or update existing record if translation already exists
     * 
     * @param int $taxId Tax ID
     * @param string $languageCode Language code (e.g., 'en', 'hi', 'ar')
     * @param array $translatedData Array containing title
     * @return bool Success status
     */
    public function saveTranslatedDetails(int $taxId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists for this tax and language
        $existing = $this->where([
            'tax_id' => $taxId,
            'language_code' => $languageCode
        ])->first();

        // Prepare data for saving
        $data = [
            'tax_id' => $taxId,
            'language_code' => $languageCode,
            'title' => $existing['title'] ?? null,
        ];

        // Update with new translated data
        foreach ($translatedData as $field => $value) {
            if (in_array($field, ['title'])) {
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
     * Delete all translations for a specific tax
     * 
     * Useful when deleting a tax record
     * 
     * @param int $taxId Tax ID
     * @return bool Success status
     */
    public function deleteTaxTranslations(int $taxId): bool
    {
        return $this->where('tax_id', $taxId)->delete() !== false;
    }

    /**
     * Get translations for multiple taxes
     * 
     * Returns translations for specified tax IDs and language
     * Useful for bulk operations
     * 
     * @param array $taxIds Array of tax IDs
     * @param string $languageCode Language code (e.g., 'en', 'hi', 'ar')
     * @return array Array of translated details indexed by tax_id
     */
    public function getTranslationsForMultipleTaxes(array $taxIds, string $languageCode): array
    {
        if (empty($taxIds)) {
            return [];
        }

        $translations = $this->whereIn('tax_id', $taxIds)
            ->where('language_code', $languageCode)
            ->findAll();

        // Index by tax_id for easier lookup
        $indexedTranslations = [];
        foreach ($translations as $translation) {
            $indexedTranslations[$translation['tax_id']] = $translation;
        }

        return $indexedTranslations;
    }

    /**
     * Save translations optimized for bulk operations
     * 
     * This method accepts all translations for all languages at once
     * and efficiently saves them in the database
     * 
     * @param int $taxId Tax ID
     * @param array $titles Associative array [language_code => title]
     * @return bool Success status
     */
    public function saveTranslationsOptimized(int $taxId, array $titles): bool
    {
        $successCount = 0;
        $totalLanguages = count($titles);

        foreach ($titles as $languageCode => $title) {
            // Skip if title is empty
            if (empty(trim($title))) {
                continue;
            }

            $result = $this->saveTranslatedDetails(
                $taxId,
                $languageCode,
                ['title' => trim($title)]
            );

            if ($result) {
                $successCount++;
            }
        }

        // Return true if at least one translation was saved successfully
        return $successCount > 0;
    }
}

