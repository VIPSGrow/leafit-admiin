<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Blog Tag Details Model
 * 
 * Handles database operations for translated blog tag details
 * Stores multi-language translations for blog tag fields
 * Follows the same pattern as TranslatedBlogCategoryDetailsModel
 */
class TranslatedBlogTagDetailsModel extends Model
{
    protected $table = 'translated_blog_tag_details';
    protected $primaryKey = 'id';
    protected $allowedFields = ['tag_id', 'language_code', 'name'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'tag_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'name' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'tag_id' => [
            'required' => 'Tag ID is required',
            'integer' => 'Tag ID must be an integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    /**
     * Save blog tag translations for multiple languages
     * 
     * @param int $tag_id The blog tag ID
     * @param array $names Array of names with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveTranslations($tag_id, $names)
    {
        try {
            // Delete existing translations for this blog tag
            $this->where('tag_id', $tag_id)->delete();

            // Insert new translations
            $translationData = [];
            foreach ($names as $language_code => $name) {
                // Skip empty names
                if (empty(trim($name))) {
                    continue;
                }

                $translationData[] = [
                    'tag_id' => $tag_id,
                    'language_code' => $language_code,
                    'name' => trim($name)
                ];
            }

            // Insert all translations at once
            if (!empty($translationData)) {
                return $this->insertBatch($translationData);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving blog tag translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save blog tag translations for multiple languages - OPTIMIZED VERSION
     * Uses upsert approach instead of delete + insert for better performance
     * 
     * @param int $tag_id The blog tag ID
     * @param array $names Array of names with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveTranslationsOptimized($tag_id, $names)
    {
        try {
            // Filter out empty names first to reduce database operations
            $validNames = [];
            foreach ($names as $language_code => $name) {
                $trimmedName = trim($name);
                if (!empty($trimmedName)) {
                    $validNames[$language_code] = $trimmedName;
                }
            }

            // If no valid names, just delete existing translations and return
            if (empty($validNames)) {
                $this->where('tag_id', $tag_id)->delete();
                return true;
            }

            // Get existing translations to avoid unnecessary operations
            $existingTranslations = $this->where('tag_id', $tag_id)
                ->select('language_code, name')
                ->findAll();

            $existingByLang = [];
            foreach ($existingTranslations as $existing) {
                $existingByLang[$existing['language_code']] = $existing['name'];
            }

            // Prepare data for batch operations
            $toInsert = [];
            $toUpdate = [];
            $toDelete = [];

            // Determine what needs to be inserted, updated, or deleted
            foreach ($validNames as $language_code => $name) {
                if (!isset($existingByLang[$language_code])) {
                    // New translation - insert
                    $toInsert[] = [
                        'tag_id' => $tag_id,
                        'language_code' => $language_code,
                        'name' => $name
                    ];
                } elseif ($existingByLang[$language_code] !== $name) {
                    // Changed translation - update
                    $toUpdate[] = [
                        'tag_id' => $tag_id,
                        'language_code' => $language_code,
                        'name' => $name
                    ];
                }
                // If unchanged, do nothing
            }

            // Find translations to delete (languages that exist but are not in new data)
            foreach ($existingByLang as $language_code => $name) {
                if (!isset($validNames[$language_code])) {
                    $toDelete[] = $language_code;
                }
            }

            // Execute operations in order: delete, update, insert
            if (!empty($toDelete)) {
                $this->where('tag_id', $tag_id)
                    ->whereIn('language_code', $toDelete)
                    ->delete();
            }

            if (!empty($toUpdate)) {
                foreach ($toUpdate as $updateData) {
                    $this->where([
                        'tag_id' => $tag_id,
                        'language_code' => $updateData['language_code']
                    ])->set(['name' => $updateData['name']])->update();
                }
            }

            if (!empty($toInsert)) {
                $this->insertBatch($toInsert);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving blog tag translations (optimized): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translated name for a specific blog tag and language
     * 
     * @param int $tag_id The blog tag ID
     * @param string $language_code The language code
     * @return string|null The translated name or null if not found
     */
    public function getTranslation($tag_id, $language_code)
    {
        $result = $this->where([
            'tag_id' => $tag_id,
            'language_code' => $language_code
        ])->first();

        return $result ? $result['name'] : null;
    }

    /**
     * Get all translations for a specific blog tag
     * 
     * @param int $tag_id The blog tag ID
     * @return array Array of translations with language codes as keys
     */
    public function getAllTranslations($tag_id)
    {
        $results = $this->where('tag_id', $tag_id)->findAll();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result['language_code']] = $result['name'];
        }

        return $translations;
    }

    /**
     * Delete all translations for a specific blog tag
     * 
     * @param int $tag_id The blog tag ID
     * @return bool True if successful, false otherwise
     */
    public function deleteTranslations($tag_id)
    {
        try {
            return $this->where('tag_id', $tag_id)->delete();
        } catch (\Exception $e) {
            log_message('error', 'Error deleting blog tag translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translations for multiple blog tags
     * 
     * @param array $tag_ids Array of blog tag IDs
     * @param string $language_code The language code
     * @return array Array of translations indexed by tag_id
     */
    public function getBatchTranslations($tag_ids, $language_code)
    {
        if (empty($tag_ids)) {
            return [];
        }

        $result = $this->whereIn('tag_id', $tag_ids)
            ->where('language_code', $language_code)
            ->findAll();

        $translations = [];
        foreach ($result as $translation) {
            $translations[$translation['tag_id']] = $translation;
        }

        return $translations;
    }

    /**
     * Get translated details for a specific tag and language
     * Follows the same pattern as other translation models
     * 
     * @param int $tagId Tag ID
     * @param string $languageCode Language code
     * @return array|null Translated details or null if not found
     */
    public function getTranslatedDetails(int $tagId, string $languageCode): ?array
    {
        $result = $this->where([
            'tag_id' => $tagId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific tag
     * Follows the same pattern as other translation models
     * 
     * @param int $tagId Tag ID
     * @return array Array of translated details
     */
    public function getAllTranslationsForTag(int $tagId): array
    {
        return $this->where('tag_id', $tagId)->findAll();
    }

    /**
     * Save or update translated details for a tag
     * Follows the same pattern as other translation models
     * 
     * @param int $tagId Tag ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing name
     * @return bool Success status
     */
    public function saveTranslatedDetails(int $tagId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'tag_id' => $tagId,
            'language_code' => $languageCode
        ])->first();

        // Merge existing data with new data to preserve other fields
        $data = [
            'tag_id' => $tagId,
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
     * Delete all translations for a specific tag
     * Follows the same pattern as other translation models
     * 
     * @param int $tagId Tag ID
     * @return bool Success status
     */
    public function deleteTagTranslations(int $tagId): bool
    {
        return $this->where('tag_id', $tagId)->delete() !== false;
    }

    /**
     * Get translations for multiple tags
     * Follows the same pattern as other translation models
     * 
     * @param array $tagIds Array of tag IDs
     * @param string $languageCode Language code
     * @return array Array of translations indexed by tag_id
     */
    public function getTranslationsForMultipleTags(array $tagIds, string $languageCode): array
    {
        if (empty($tagIds)) {
            return [];
        }

        $result = $this->whereIn('tag_id', $tagIds)
            ->where('language_code', $languageCode)
            ->findAll();

        $translations = [];
        foreach ($result as $translation) {
            $translations[$translation['tag_id']] = $translation;
        }

        return $translations;
    }
}
