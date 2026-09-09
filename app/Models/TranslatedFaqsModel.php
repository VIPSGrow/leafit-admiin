<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * TranslatedFaqsModel
 * 
 * Handles database operations for FAQ translations
 * Stores multi-language translations for FAQ questions and answers
 * Following the same pattern as TranslatedPromocodeModel
 */
class TranslatedFaqsModel extends Model
{
    protected $table = 'translated_faq_details';
    protected $primaryKey = 'id';
    protected $allowedFields = ['faq_id', 'language_code', 'question', 'answer'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Save FAQ translations for multiple languages
     * 
     * @param int $faq_id The FAQ ID
     * @param array $questions Array of questions with language codes as keys
     * @param array $answers Array of answers with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveFaqTranslations($faq_id, $questions, $answers)
    {
        try {
            // Delete existing translations for this FAQ
            $this->where('faq_id', $faq_id)->delete();

            // Prepare translation data
            $translationData = [];

            // Combine questions and answers by language code
            $languages = array_unique(array_merge(array_keys($questions), array_keys($answers)));

            foreach ($languages as $language_code) {
                $question = isset($questions[$language_code]) ? trim($questions[$language_code]) : '';
                $answer = isset($answers[$language_code]) ? trim($answers[$language_code]) : '';

                // Skip if both question and answer are empty
                if (empty($question) && empty($answer)) {
                    continue;
                }

                $translationData[] = [
                    'faq_id' => $faq_id,
                    'language_code' => $language_code,
                    'question' => $question,
                    'answer' => $answer
                ];
            }

            // Insert all translations at once
            if (!empty($translationData)) {
                return $this->insertBatch($translationData);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving FAQ translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save FAQ translations for multiple languages - OPTIMIZED VERSION
     * 
     * @param int $faq_id The FAQ ID
     * @param array $questions Array of questions with language codes as keys
     * @param array $answers Array of answers with language codes as keys
     * @return bool True if successful, false otherwise
     */
    public function saveFaqTranslationsOptimized($faq_id, $questions, $answers)
    {
        try {
            // Filter out empty translations first to reduce database operations
            $validTranslations = [];
            $languages = array_unique(array_merge(array_keys($questions), array_keys($answers)));

            foreach ($languages as $language_code) {
                $question = isset($questions[$language_code]) ? trim($questions[$language_code]) : '';
                $answer = isset($answers[$language_code]) ? trim($answers[$language_code]) : '';

                // Skip if both question and answer are empty
                if (!empty($question) || !empty($answer)) {
                    $validTranslations[$language_code] = [
                        'question' => $question,
                        'answer' => $answer
                    ];
                }
            }

            // If no valid translations, just delete existing translations and return
            if (empty($validTranslations)) {
                $this->where('faq_id', $faq_id)->delete();
                return true;
            }

            // OPTIMIZATION: Use upsert approach instead of delete + insert
            // First, get existing translations to avoid unnecessary operations
            $existingTranslations = $this->where('faq_id', $faq_id)
                ->select('language_code, question, answer')
                ->findAll();

            $existingByLang = [];
            foreach ($existingTranslations as $existing) {
                $existingByLang[$existing['language_code']] = [
                    'question' => $existing['question'],
                    'answer' => $existing['answer']
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
                        'faq_id' => $faq_id,
                        'language_code' => $language_code,
                        'question' => $translation['question'],
                        'answer' => $translation['answer']
                    ];
                } elseif (
                    $existingByLang[$language_code]['question'] !== $translation['question'] ||
                    $existingByLang[$language_code]['answer'] !== $translation['answer']
                ) {
                    // Changed translation - update
                    $toUpdate[] = [
                        'faq_id' => $faq_id,
                        'language_code' => $language_code,
                        'question' => $translation['question'],
                        'answer' => $translation['answer']
                    ];
                }
                // If unchanged, do nothing
            }

            // Find translations to delete (languages that exist but are not in new data)
            foreach ($existingByLang as $language_code => $translation) {
                if (!isset($validTranslations[$language_code])) {
                    $toDelete[] = $language_code;
                }
            }

            // Execute operations in order: delete, update, insert
            if (!empty($toDelete)) {
                $this->where('faq_id', $faq_id)
                    ->whereIn('language_code', $toDelete)
                    ->delete();
            }

            if (!empty($toUpdate)) {
                foreach ($toUpdate as $updateData) {
                    $this->where([
                        'faq_id' => $faq_id,
                        'language_code' => $updateData['language_code']
                    ])->set([
                        'question' => $updateData['question'],
                        'answer' => $updateData['answer']
                    ])->update();
                }
            }

            if (!empty($toInsert)) {
                $this->insertBatch($toInsert);
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error saving FAQ translations (optimized): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get translated FAQ for a specific FAQ and language
     * 
     * @param int $faq_id The FAQ ID
     * @param string $language_code The language code
     * @return array|null The translated FAQ data or null if not found
     */
    public function getTranslation($faq_id, $language_code)
    {
        $result = $this->where([
            'faq_id' => $faq_id,
            'language_code' => $language_code
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific FAQ
     * 
     * @param int $faq_id The FAQ ID
     * @return array Array of translations with language codes as keys
     */
    public function getAllTranslations($faq_id)
    {
        $results = $this->where('faq_id', $faq_id)->findAll();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result['language_code']] = [
                'question' => $result['question'],
                'answer' => $result['answer']
            ];
        }

        return $translations;
    }

    /**
     * Delete all translations for a specific FAQ
     * 
     * @param int $faq_id The FAQ ID
     * @return bool True if successful, false otherwise
     */
    public function deleteTranslations($faq_id)
    {
        try {
            return $this->where('faq_id', $faq_id)->delete();
        } catch (\Exception $e) {
            log_message('error', 'Error deleting FAQ translations: ' . $e->getMessage());
            return false;
        }
    }
}
