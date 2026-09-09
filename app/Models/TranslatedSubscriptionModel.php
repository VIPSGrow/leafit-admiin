<?php

namespace App\Models;

use CodeIgniter\Model;

class TranslatedSubscriptionModel extends Model
{
    protected $table = 'translated_subscription_details';
    protected $primaryKey = 'id';
    protected $allowedFields = ['subscription_id', 'language_code', 'name', 'description'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Save subscription translations for multiple languages
     * 
     * @param int $subscription_id The subscription ID
     * @param array $names Array of names with language codes as keys
     * @param array $descriptions Array of descriptions with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveTranslations($subscription_id, $names, $descriptions)
    {
        try {
            // Delete existing translations for this subscription
            $this->where('subscription_id', $subscription_id)->delete();

            // Insert new translations
            $translationData = [];
            foreach ($names as $language_code => $name) {
                // Skip empty names (name is required)
                if (empty(trim($name))) {
                    continue;
                }

                $description = $descriptions[$language_code] ?? '';

                $translationData[] = [
                    'subscription_id' => $subscription_id,
                    'language_code' => $language_code,
                    'name' => trim($name),
                    'description' => trim($description)
                ];
            }

            // Insert all translations at once
            if (!empty($translationData)) {
                return $this->insertBatch($translationData);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving subscription translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save subscription translations for multiple languages - OPTIMIZED VERSION
     * 
     * @param int $subscription_id The subscription ID
     * @param array $names Array of names with language codes as keys
     * @param array $descriptions Array of descriptions with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveTranslationsOptimized($subscription_id, $names, $descriptions)
    {
        try {
            // Filter out empty names first to reduce database operations
            $validTranslations = [];
            foreach ($names as $language_code => $name) {
                $trimmedName = trim($name);
                if (!empty($trimmedName)) {
                    $description = $descriptions[$language_code] ?? '';
                    $validTranslations[$language_code] = [
                        'name' => $trimmedName,
                        'description' => trim($description)
                    ];
                }
            }

            // If no valid translations, just delete existing translations and return
            if (empty($validTranslations)) {
                $this->where('subscription_id', $subscription_id)->delete();
                return true;
            }

            // OPTIMIZATION: Use upsert approach instead of delete + insert
            // First, get existing translations to avoid unnecessary operations
            $existingTranslations = $this->where('subscription_id', $subscription_id)
                ->select('language_code, name, description')
                ->findAll();

            $existingByLang = [];
            foreach ($existingTranslations as $existing) {
                $existingByLang[$existing['language_code']] = [
                    'name' => $existing['name'],
                    'description' => $existing['description']
                ];
            }

            // Prepare data for batch operations
            $toInsert = [];
            $toUpdate = [];
            $toDelete = [];

            // Determine what needs to be inserted, updated, or deleted
            foreach ($validTranslations as $language_code => $translation) {
                if (!isset($existingByLang[$language_code])) {
                    // New translation - insert
                    $toInsert[] = [
                        'subscription_id' => $subscription_id,
                        'language_code' => $language_code,
                        'name' => $translation['name'],
                        'description' => $translation['description']
                    ];
                } elseif (
                    $existingByLang[$language_code]['name'] !== $translation['name'] ||
                    $existingByLang[$language_code]['description'] !== $translation['description']
                ) {
                    // Translation changed - update
                    $toUpdate[] = [
                        'subscription_id' => $subscription_id,
                        'language_code' => $language_code,
                        'name' => $translation['name'],
                        'description' => $translation['description']
                    ];
                }
                // If translation is the same, do nothing
            }

            // Find languages to delete (exist in database but not in new data)
            foreach ($existingByLang as $language_code => $translation) {
                if (!isset($validTranslations[$language_code])) {
                    $toDelete[] = $language_code;
                }
            }

            // Execute operations
            if (!empty($toDelete)) {
                $this->where('subscription_id', $subscription_id)
                    ->whereIn('language_code', $toDelete)
                    ->delete();
            }

            if (!empty($toUpdate)) {
                foreach ($toUpdate as $updateData) {
                    $this->where([
                        'subscription_id' => $subscription_id,
                        'language_code' => $updateData['language_code']
                    ])->set([
                        'name' => $updateData['name'],
                        'description' => $updateData['description']
                    ])->update();
                }
            }

            if (!empty($toInsert)) {
                $this->insertBatch($toInsert);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving subscription translations (optimized): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translated name and description for a specific subscription and language
     * 
     * @param int $subscription_id The subscription ID
     * @param string $language_code The language code
     * @return array|null The translated data or null if not found
     */
    public function getTranslation($subscription_id, $language_code)
    {
        $result = $this->where([
            'subscription_id' => $subscription_id,
            'language_code' => $language_code
        ])->first();

        return $result ? [
            'name' => $result['name'],
            'description' => $result['description']
        ] : null;
    }

    /**
     * Get all translations for a specific subscription
     * 
     * @param int $subscription_id The subscription ID
     * @return array Array of translations with language codes as keys
     */
    public function getAllTranslations($subscription_id)
    {
        $results = $this->where('subscription_id', $subscription_id)->findAll();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result['language_code']] = [
                'name' => $result['name'],
                'description' => $result['description']
            ];
        }

        return $translations;
    }

    /**
     * Delete all translations for a specific subscription
     * 
     * @param int $subscription_id The subscription ID
     * @return bool True if successful, false otherwise
     */
    public function deleteTranslations($subscription_id)
    {
        try {
            return $this->where('subscription_id', $subscription_id)->delete();
        } catch (\Exception $e) {
            log_message('error', 'Error deleting subscription translations: ' . $e->getMessage());
            return false;
        }
    }
}
