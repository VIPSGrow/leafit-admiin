<?php

namespace App\Models;

use CodeIgniter\Model;

class TranslatedPromocodeModel extends Model
{
    protected $table = 'translated_promocode_details';
    protected $primaryKey = 'id';
    protected $allowedFields = ['promocode_id', 'language_code', 'message'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Save promocode translations for multiple languages
     * 
     * @param int $promocode_id The promocode ID
     * @param array $messages Array of messages with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveTranslations($promocode_id, $messages)
    {
        try {
            // Delete existing translations for this promocode
            $this->where('promocode_id', $promocode_id)->delete();

            // Insert new translations
            $translationData = [];
            foreach ($messages as $language_code => $message) {
                // Skip empty messages
                if (empty(trim($message))) {
                    continue;
                }

                $translationData[] = [
                    'promocode_id' => $promocode_id,
                    'language_code' => $language_code,
                    'message' => trim($message)
                ];
            }

            // Insert all translations at once
            if (!empty($translationData)) {
                return $this->insertBatch($translationData);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving promocode translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save promocode translations for multiple languages - OPTIMIZED VERSION
     * 
     * @param int $promocode_id The promocode ID
     * @param array $messages Array of messages with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveTranslationsOptimized($promocode_id, $messages)
    {
        try {
            // Filter out empty messages first to reduce database operations
            $validMessages = [];
            foreach ($messages as $language_code => $message) {
                $trimmedMessage = trim($message);
                if (!empty($trimmedMessage)) {
                    $validMessages[$language_code] = $trimmedMessage;
                }
            }

            // If no valid messages, just delete existing translations and return
            if (empty($validMessages)) {
                $this->where('promocode_id', $promocode_id)->delete();
                return true;
            }

            // OPTIMIZATION: Use upsert approach instead of delete + insert
            // First, get existing translations to avoid unnecessary operations
            $existingTranslations = $this->where('promocode_id', $promocode_id)
                ->select('language_code, message')
                ->findAll();

            $existingByLang = [];
            foreach ($existingTranslations as $existing) {
                $existingByLang[$existing['language_code']] = $existing['message'];
            }

            // Prepare data for batch operations
            $toInsert = [];
            $toUpdate = [];
            $toDelete = [];

            // Determine what needs to be inserted, updated, or deleted
            foreach ($validMessages as $language_code => $message) {
                if (!isset($existingByLang[$language_code])) {
                    // New translation - insert
                    $toInsert[] = [
                        'promocode_id' => $promocode_id,
                        'language_code' => $language_code,
                        'message' => $message
                    ];
                } elseif ($existingByLang[$language_code] !== $message) {
                    // Changed translation - update
                    $toUpdate[] = [
                        'promocode_id' => $promocode_id,
                        'language_code' => $language_code,
                        'message' => $message
                    ];
                }
                // If unchanged, do nothing
            }

            // Find translations to delete (languages that exist but are not in new data)
            foreach ($existingByLang as $language_code => $message) {
                if (!isset($validMessages[$language_code])) {
                    $toDelete[] = $language_code;
                }
            }

            // Execute operations in order: delete, update, insert
            if (!empty($toDelete)) {
                $this->where('promocode_id', $promocode_id)
                    ->whereIn('language_code', $toDelete)
                    ->delete();
            }

            if (!empty($toUpdate)) {
                foreach ($toUpdate as $updateData) {
                    $this->where([
                        'promocode_id' => $promocode_id,
                        'language_code' => $updateData['language_code']
                    ])->set(['message' => $updateData['message']])->update();
                }
            }

            if (!empty($toInsert)) {
                $this->insertBatch($toInsert);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving promocode translations (optimized): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translated message for a specific promocode and language
     * 
     * @param int $promocode_id The promocode ID
     * @param string $language_code The language code
     * @return string|null The translated message or null if not found
     */
    public function getTranslation($promocode_id, $language_code)
    {
        $result = $this->where([
            'promocode_id' => $promocode_id,
            'language_code' => $language_code
        ])->first();

        return $result ? $result['message'] : null;
    }

    /**
     * Get all translations for a specific promocode
     * 
     * @param int $promocode_id The promocode ID
     * @return array Array of translations with language codes as keys
     */
    public function getAllTranslations($promocode_id)
    {
        $results = $this->where('promocode_id', $promocode_id)->findAll();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result['language_code']] = $result['message'];
        }

        return $translations;
    }

    /**
     * Delete all translations for a specific promocode
     * 
     * @param int $promocode_id The promocode ID
     * @return bool True if successful, false otherwise
     */
    public function deleteTranslations($promocode_id)
    {
        try {
            return $this->where('promocode_id', $promocode_id)->delete();
        } catch (\Exception $e) {
            log_message('error', 'Error deleting promocode translations: ' . $e->getMessage());
            return false;
        }
    }
}
