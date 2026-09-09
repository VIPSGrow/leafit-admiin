<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Blog Details Model
 * 
 * Handles database operations for translated blog details
 * Stores multi-language translations for blog fields (title, short_description, description, tags)
 * Follows the same pattern as TranslatedBlogCategoryDetailsModel
 */
class TranslatedBlogDetailsModel extends Model
{
    protected $table = 'translated_blog_details';
    protected $primaryKey = 'id';
    protected $allowedFields = ['blog_id', 'language_code', 'title', 'short_description', 'description', 'tags'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'blog_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'title' => 'permit_empty',
        'short_description' => 'permit_empty',
        'description' => 'permit_empty',
        'tags' => 'permit_empty',
    ];

    protected $validationMessages = [
        'blog_id' => [
            'required' => 'Blog ID is required',
            'integer' => 'Blog ID must be an integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    /**
     * Save blog translations for multiple languages
     * Follows the same pattern as TranslatedBlogCategoryDetailsModel
     * 
     * @param int $blog_id The blog ID
     * @param array $translations Array of translations with language codes as keys
     *                           Expected format: ['en' => ['title' => '...', 'description' => '...'], 'hi' => [...]]
     * @return bool True if successful, false otherwise
     */
    public function saveTranslations($blog_id, $translations)
    {
        try {
            // Delete existing translations for this blog
            $this->where('blog_id', $blog_id)->delete();

            // Insert new translations
            $translationData = [];
            foreach ($translations as $language_code => $fields) {
                // Skip if no fields provided for this language
                if (empty($fields) || !is_array($fields)) {
                    continue;
                }

                // Check if at least one field has content
                $hasContent = false;
                $cleanFields = [];

                foreach (['title', 'short_description', 'description', 'tags'] as $field) {
                    $value = isset($fields[$field]) ? trim($fields[$field]) : '';
                    $cleanFields[$field] = $value;
                    if (!empty($value)) {
                        $hasContent = true;
                    }
                }

                // Only save if at least one field has content
                if ($hasContent) {
                    $translationData[] = [
                        'blog_id' => $blog_id,
                        'language_code' => $language_code,
                        'title' => $cleanFields['title'],
                        'short_description' => $cleanFields['short_description'],
                        'description' => $cleanFields['description'],
                        'tags' => $cleanFields['tags']
                    ];
                }
            }

            // Insert all translations at once
            if (!empty($translationData)) {
                return $this->insertBatch($translationData);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving blog translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save blog translations for multiple languages - OPTIMIZED VERSION
     * Uses upsert approach instead of delete + insert for better performance
     * Follows the same pattern as TranslatedBlogCategoryDetailsModel
     * 
     * @param int $blog_id The blog ID
     * @param array $translations Array of translations with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveTranslationsOptimized($blog_id, $translations)
    {
        try {
            // Filter out empty translations first to reduce database operations
            $validTranslations = [];
            foreach ($translations as $language_code => $fields) {
                if (empty($fields) || !is_array($fields)) {
                    continue;
                }

                // Check if at least one field has content
                $hasContent = false;
                $cleanFields = [];

                foreach (['title', 'short_description', 'description', 'tags'] as $field) {
                    $value = isset($fields[$field]) ? trim($fields[$field]) : '';
                    $cleanFields[$field] = $value;
                    if (!empty($value)) {
                        $hasContent = true;
                    }
                }

                // Only include if at least one field has content
                if ($hasContent) {
                    $validTranslations[$language_code] = $cleanFields;
                }
            }

            // If no valid translations, just delete existing translations and return
            if (empty($validTranslations)) {
                $this->where('blog_id', $blog_id)->delete();
                return true;
            }

            // Get existing translations to avoid unnecessary operations
            $existingTranslations = $this->where('blog_id', $blog_id)
                ->select('language_code, title, short_description, description, tags')
                ->findAll();

            $existingByLang = [];
            foreach ($existingTranslations as $existing) {
                $existingByLang[$existing['language_code']] = $existing;
            }

            // Prepare data for batch operations
            $toInsert = [];
            $toUpdate = [];
            $toDelete = [];

            // Determine what needs to be inserted, updated, or deleted
            foreach ($validTranslations as $language_code => $fields) {
                if (!isset($existingByLang[$language_code])) {
                    // New translation - insert
                    $toInsert[] = [
                        'blog_id' => $blog_id,
                        'language_code' => $language_code,
                        'title' => $fields['title'],
                        'short_description' => $fields['short_description'],
                        'description' => $fields['description'],
                        'tags' => $fields['tags']
                    ];
                } else {
                    // Check if translation has changed
                    $existing = $existingByLang[$language_code];
                    $hasChanged = false;

                    foreach (['title', 'short_description', 'description', 'tags'] as $field) {
                        if (($existing[$field] ?? '') !== ($fields[$field] ?? '')) {
                            $hasChanged = true;
                            break;
                        }
                    }

                    if ($hasChanged) {
                        // Changed translation - update
                        $toUpdate[] = [
                            'blog_id' => $blog_id,
                            'language_code' => $language_code,
                            'title' => $fields['title'],
                            'short_description' => $fields['short_description'],
                            'description' => $fields['description'],
                            'tags' => $fields['tags']
                        ];
                    }
                }
            }

            // Find translations to delete (languages that exist but are not in new data)
            foreach ($existingByLang as $language_code => $existing) {
                if (!isset($validTranslations[$language_code])) {
                    $toDelete[] = $language_code;
                }
            }

            // Execute operations in order: delete, update, insert
            if (!empty($toDelete)) {
                $this->where('blog_id', $blog_id)
                    ->whereIn('language_code', $toDelete)
                    ->delete();
            }

            if (!empty($toUpdate)) {
                foreach ($toUpdate as $updateData) {
                    $this->where([
                        'blog_id' => $blog_id,
                        'language_code' => $updateData['language_code']
                    ])->set([
                        'title' => $updateData['title'],
                        'short_description' => $updateData['short_description'],
                        'description' => $updateData['description'],
                        'tags' => $updateData['tags']
                    ])->update();
                }
            }

            if (!empty($toInsert)) {
                $this->insertBatch($toInsert);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving blog translations (optimized): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translated fields for a specific blog and language
     * 
     * @param int $blog_id The blog ID
     * @param string $language_code The language code
     * @return array|null The translated fields or null if not found
     */
    public function getTranslation($blog_id, $language_code)
    {
        $result = $this->where([
            'blog_id' => $blog_id,
            'language_code' => $language_code
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific blog
     * 
     * @param int $blog_id The blog ID
     * @return array Array of translations with language codes as keys
     */
    public function getAllTranslations($blog_id)
    {
        $results = $this->where('blog_id', $blog_id)->findAll();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result['language_code']] = [
                'title' => $result['title'],
                'short_description' => $result['short_description'],
                'description' => $result['description'],
                'tags' => $result['tags']
            ];
        }

        return $translations;
    }

    /**
     * Delete all translations for a specific blog
     * 
     * @param int $blog_id The blog ID
     * @return bool True if successful, false otherwise
     */
    public function deleteTranslations($blog_id)
    {
        try {
            return $this->where('blog_id', $blog_id)->delete();
        } catch (\Exception $e) {
            log_message('error', 'Error deleting blog translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translations for multiple blogs
     * 
     * @param array $blog_ids Array of blog IDs
     * @param string $language_code The language code
     * @return array Array of translations indexed by blog_id
     */
    public function getBatchTranslations($blog_ids, $language_code)
    {
        if (empty($blog_ids)) {
            return [];
        }

        $result = $this->whereIn('blog_id', $blog_ids)
            ->where('language_code', $language_code)
            ->findAll();

        $translations = [];
        foreach ($result as $translation) {
            $translations[$translation['blog_id']] = $translation;
        }

        return $translations;
    }

    /**
     * Get translated details for a specific blog and language
     * Follows the same pattern as other translation models
     * 
     * @param int $blogId Blog ID
     * @param string $languageCode Language code
     * @return array|null Translated details or null if not found
     */
    public function getTranslatedDetails(int $blogId, string $languageCode): ?array
    {
        $result = $this->where([
            'blog_id' => $blogId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific blog
     * Follows the same pattern as other translation models
     * 
     * @param int $blogId Blog ID
     * @return array Array of translated details
     */
    public function getAllTranslationsForBlog(int $blogId): array
    {
        return $this->where('blog_id', $blogId)->findAll();
    }

    /**
     * Save or update translated details for a blog
     * Follows the same pattern as other translation models
     * 
     * @param int $blogId Blog ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing title, short_description, description, tags
     * @return bool Success status
     */
    public function saveTranslatedDetails(int $blogId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'blog_id' => $blogId,
            'language_code' => $languageCode
        ])->first();

        // Merge existing data with new data to preserve other fields
        $data = [
            'blog_id' => $blogId,
            'language_code' => $languageCode,
            'title' => $existing['title'] ?? null,
            'short_description' => $existing['short_description'] ?? null,
            'description' => $existing['description'] ?? null,
            'tags' => $existing['tags'] ?? null,
        ];

        // Update with new translated data
        foreach ($translatedData as $field => $value) {
            if (in_array($field, ['title', 'short_description', 'description', 'tags'])) {
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
     * Delete all translations for a specific blog
     * Follows the same pattern as other translation models
     * 
     * @param int $blogId Blog ID
     * @return bool Success status
     */
    public function deleteBlogTranslations(int $blogId): bool
    {
        return $this->where('blog_id', $blogId)->delete() !== false;
    }

    /**
     * Get translations for multiple blogs
     * Follows the same pattern as other translation models
     * 
     * @param array $blogIds Array of blog IDs
     * @param string $languageCode Language code
     * @return array Array of translations indexed by blog_id
     */
    public function getTranslationsForMultipleBlogs(array $blogIds, string $languageCode): array
    {
        if (empty($blogIds)) {
            return [];
        }

        $result = $this->whereIn('blog_id', $blogIds)
            ->where('language_code', $languageCode)
            ->findAll();

        $translations = [];
        foreach ($result as $translation) {
            $translations[$translation['blog_id']] = $translation;
        }

        return $translations;
    }
}
