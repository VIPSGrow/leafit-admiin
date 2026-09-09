<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Reasons For Report And Block Chat Model
 * 
 * Handles database operations for translated reason details
 * Stores multi-language translations for reason fields
 */
class TranslatedReasonsForReportAndBlockChat_model extends Model
{
    protected $table = 'translated_reasons_for_report_and_block_chat';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'reason_id',
        'language_code',
        'reason',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'reason_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'reason' => 'permit_empty',
    ];

    protected $validationMessages = [
        'reason_id' => [
            'required' => 'Reason ID is required',
            'integer' => 'Reason ID must be an integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated details for a specific reason and language
     * 
     * @param int $reasonId Reason ID
     * @param string $languageCode Language code
     * @return array|null Translated details or null if not found
     */
    public function getTranslatedDetails(int $reasonId, string $languageCode): ?array
    {
        $result = $this->where([
            'reason_id' => $reasonId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific reason
     * 
     * @param int $reasonId Reason ID
     * @return array Array of translated details
     */
    public function getAllTranslationsForReason(int $reasonId): array
    {
        return $this->where('reason_id', $reasonId)->findAll();
    }

    /**
     * Save or update translated details for a reason
     * 
     * @param int $reasonId Reason ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing reason
     * @return bool Success status
     */
    public function saveTranslatedDetails(int $reasonId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'reason_id' => $reasonId,
            'language_code' => $languageCode
        ])->first();

        // Merge existing data with new data to preserve other fields
        $data = [
            'reason_id' => $reasonId,
            'language_code' => $languageCode,
            'reason' => $existing['reason'] ?? null,
        ];

        // Update with new translated data
        foreach ($translatedData as $field => $value) {
            if (in_array($field, ['reason'])) {
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
     * Delete all translations for a specific reason
     * 
     * @param int $reasonId Reason ID
     * @return bool Success status
     */
    public function deleteReasonTranslations(int $reasonId): bool
    {
        return $this->where('reason_id', $reasonId)->delete() !== false;
    }

    /**
     * Get translations for multiple reasons
     * 
     * @param array $reasonIds Array of reason IDs
     * @param string $languageCode Language code (optional)
     * @return array Array of translated details
     */
    public function getTranslationsForReasons(array $reasonIds, ?string $languageCode = null): array
    {
        $this->whereIn('reason_id', $reasonIds);
        
        if ($languageCode) {
            $this->where('language_code', $languageCode);
        }
        
        return $this->findAll();
    }

    /**
     * Get translated reason text with fallback logic
     * 
     * @param int $reasonId Reason ID
     * @param string $languageCode Language code
     * @param string $defaultLanguageCode Default language code for fallback
     * @return string|null Translated reason text or null if not found
     */
    public function getTranslatedReasonText(int $reasonId, string $languageCode, string $defaultLanguageCode = 'en'): ?string
    {
        // First try to get translation for the requested language
        $translation = $this->getTranslatedDetails($reasonId, $languageCode);
        
        if ($translation && !empty($translation['reason'])) {
            return $translation['reason'];
        }
        
        // If not found and not already requesting default language, try default language
        if ($languageCode !== $defaultLanguageCode) {
            $defaultTranslation = $this->getTranslatedDetails($reasonId, $defaultLanguageCode);
            if ($defaultTranslation && !empty($defaultTranslation['reason'])) {
                return $defaultTranslation['reason'];
            }
        }
        
        // If still not found, try to get from main table as final fallback
        if ($languageCode === $defaultLanguageCode) {
            $mainTableReason = fetch_details('reasons_for_report_and_block_chat', ['id' => $reasonId], ['reason']);
            if (!empty($mainTableReason) && !empty($mainTableReason[0]['reason'])) {
                return $mainTableReason[0]['reason'];
            }
        }
        
        return null;
    }
}
