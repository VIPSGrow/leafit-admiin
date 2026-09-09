<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Blog Category Details Model
 * 
 * Handles database operations for translated blog category details
 * Stores multi-language translations for blog category fields
 * Similar to TranslatedPromocodeModel but for blog categories
 */
class TranslatedBlogCategoryDetailsModel extends Model
{
    protected $table = 'translated_blog_category_details';
    protected $primaryKey = 'id';
    protected $allowedFields = ['blog_category_id', 'language_code', 'name'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Save blog category translations for multiple languages
     * 
     * @param int $blog_category_id The blog category ID
     * @param array $names Array of names with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveTranslations($blog_category_id, $names)
    {
        try {
            // Delete existing translations for this blog category
            $this->where('blog_category_id', $blog_category_id)->delete();

            // Insert new translations
            $translationData = [];
            foreach ($names as $language_code => $name) {
                // Skip empty names
                if (empty(trim($name))) {
                    continue;
                }

                $translationData[] = [
                    'blog_category_id' => $blog_category_id,
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
            log_message('error', 'Error saving blog category translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save blog category translations for multiple languages - OPTIMIZED VERSION
     * 
     * @param int $blog_category_id The blog category ID
     * @param array $names Array of names with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveTranslationsOptimized($blog_category_id, $names)
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
                $this->where('blog_category_id', $blog_category_id)->delete();
                return true;
            }

            // OPTIMIZATION: Use upsert approach instead of delete + insert
            // First, get existing translations to avoid unnecessary operations
            $existingTranslations = $this->where('blog_category_id', $blog_category_id)
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
                        'blog_category_id' => $blog_category_id,
                        'language_code' => $language_code,
                        'name' => $name
                    ];
                } elseif ($existingByLang[$language_code] !== $name) {
                    // Changed translation - update
                    $toUpdate[] = [
                        'blog_category_id' => $blog_category_id,
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
                $this->where('blog_category_id', $blog_category_id)
                    ->whereIn('language_code', $toDelete)
                    ->delete();
            }

            if (!empty($toUpdate)) {
                foreach ($toUpdate as $updateData) {
                    $this->where([
                        'blog_category_id' => $blog_category_id,
                        'language_code' => $updateData['language_code']
                    ])->set(['name' => $updateData['name']])->update();
                }
            }

            if (!empty($toInsert)) {
                $this->insertBatch($toInsert);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving blog category translations (optimized): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translated name for a specific blog category and language
     * 
     * @param int $blog_category_id The blog category ID
     * @param string $language_code The language code
     * @return string|null The translated name or null if not found
     */
    public function getTranslation($blog_category_id, $language_code)
    {
        $result = $this->where([
            'blog_category_id' => $blog_category_id,
            'language_code' => $language_code
        ])->first();

        return $result ? $result['name'] : null;
    }

    /**
     * Get all translations for a specific blog category
     * 
     * @param int $blog_category_id The blog category ID
     * @return array Array of translations with language codes as keys
     */
    public function getAllTranslations($blog_category_id)
    {
        $results = $this->where('blog_category_id', $blog_category_id)->findAll();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result['language_code']] = $result['name'];
        }

        return $translations;
    }

    /**
     * Delete all translations for a specific blog category
     * 
     * @param int $blog_category_id The blog category ID
     * @return bool True if successful, false otherwise
     */
    public function deleteTranslations($blog_category_id)
    {
        try {
            return $this->where('blog_category_id', $blog_category_id)->delete();
        } catch (\Exception $e) {
            log_message('error', 'Error deleting blog category translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translations for multiple blog categories
     * 
     * @param array $blog_category_ids Array of blog category IDs
     * @param string $language_code The language code
     * @return array Array of translations indexed by blog_category_id
     */
    public function getBatchTranslations($blog_category_ids, $language_code)
    {
        if (empty($blog_category_ids)) {
            return [];
        }

        $result = $this->whereIn('blog_category_id', $blog_category_ids)
            ->where('language_code', $language_code)
            ->findAll();

        $translations = [];
        foreach ($result as $translation) {
            $translations[$translation['blog_category_id']] = $translation;
        }

        return $translations;
    }
}
