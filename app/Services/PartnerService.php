<?php

namespace App\Services;

use App\Models\TranslatedPartnerDetails_model;
use Exception;

/**
 * Partner Service
 * 
 * Handles business logic for partner operations including translations
 * Similar to the implementation in V1.php register method
 */
class PartnerService
{
    protected TranslatedPartnerDetails_model $translationModel;

    public function __construct()
    {
        $this->translationModel = new TranslatedPartnerDetails_model();
    }

    /**
     * Handle partner creation with translations
     * 
     * @param array $postData POST data from the form
     * @param array $partnerData Partner data array
     * @param int $partnerId Partner ID
     * @param string $defaultLanguage Default language code
     * @return array Result with success status and any errors
     */
    public function handlePartnerCreationWithTranslations(array $postData, array $partnerData, int $partnerId, string $defaultLanguage): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        try {
            // Process translations if provided
            $translationResult = $this->processPartnerTranslations($partnerId, $postData, $defaultLanguage);

            if (!$translationResult['success']) {
                $result['success'] = false;
                $result['errors'] = array_merge($result['errors'], $translationResult['errors']);
            }

            $result['processed_languages'] = $translationResult['processed_languages'] ?? [];
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = 'Exception in translation processing: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Process partner translations from form data
     * 
     * @param int $partnerId Partner ID
     * @param array $postData POST data from the form
     * @param string $defaultLanguage Default language code
     * @return array Result with success status and any errors
     */
    private function processPartnerTranslations(int $partnerId, array $postData, string $defaultLanguage): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        // Validate partner ID
        if (empty($partnerId)) {
            $result['success'] = false;
            $result['errors'][] = 'Partner ID is required';
            return $result;
        }

        // Check if translated_fields is provided in the POST data
        $translatedFields = $postData['translated_fields'] ?? null;

        // If translated_fields is provided, use it directly
        if (!empty($translatedFields) && is_array($translatedFields)) {
            $saveResult = $this->saveTranslatedFields($partnerId, $translatedFields);

            if ($saveResult['success']) {
                $result['processed_languages'] = $saveResult['processed_languages'];
            } else {
                $result['success'] = false;
                $result['errors'] = array_merge($result['errors'], $saveResult['errors']);
            }

            return $result;
        }

        // Fallback: Process form data in the old format
        // Get languages from database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        if (empty($languages)) {
            $result['errors'][] = 'No languages found in database';
            return $result;
        }

        // Define translatable fields
        $translatableFields = [
            'username',
            'company_name',
            'about_provider', // Maps to 'about' in database
            'long_description'
        ];

        // Build the translated_fields structure
        $translatedFields = [];
        foreach ($translatableFields as $fieldName) {
            $translatedFields[$fieldName] = [];
        }

        // Process each language
        foreach ($languages as $language) {
            $languageCode = $language['code'];

            foreach ($translatableFields as $fieldName) {
                // Get the field value for this language from POST data
                // Form sends data as: company_name[en], about[en], etc.
                $fieldKey = $fieldName . '[' . $languageCode . ']';
                $fieldValue = $postData[$fieldKey] ?? null;

                // For default language, also check if there's a direct field value (fallback)
                if ($languageCode === $defaultLanguage && empty($fieldValue)) {
                    $fieldValue = $postData[$fieldName] ?? null;
                }

                // Only add non-empty values
                if (!empty($fieldValue)) {
                    $translatedFields[$fieldName][$languageCode] = trim($fieldValue);
                }
            }
        }

        // Save translations using the new structure
        $saveResult = $this->saveTranslatedFields($partnerId, $translatedFields);

        if ($saveResult['success']) {
            $result['processed_languages'] = $saveResult['processed_languages'];
        } else {
            $result['success'] = false;
            $result['errors'] = array_merge($result['errors'], $saveResult['errors']);
        }

        // If there are any errors, mark as not fully successful
        if (!empty($result['errors'])) {
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * Save translated fields in the new structure
     * 
     * @param int $partnerId Partner ID
     * @param array $translatedFields Translated fields structure
     * @return array Result with success status and any errors
     */
    private function saveTranslatedFields(int $partnerId, array $translatedFields): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        // Define field mapping to database columns
        $fieldMapping = [
            'username' => 'username',
            'company_name' => 'company_name',
            'about_provider' => 'about',
            'long_description' => 'long_description'
        ];

        // Process each field
        foreach ($translatedFields as $fieldName => $languageData) {
            if (!isset($fieldMapping[$fieldName])) {
                continue; // Skip unknown fields
            }

            $dbFieldName = $fieldMapping[$fieldName];

            // Process each language for this field
            foreach ($languageData as $languageCode => $translatedText) {
                try {
                    // Prepare data for this specific field and language
                    $translationData = [
                        $dbFieldName => $translatedText
                    ];

                    $saveResult = $this->translationModel->saveTranslatedDetails(
                        $partnerId,
                        $languageCode,
                        $translationData
                    );

                    if ($saveResult) {
                        $result['processed_languages'][] = [
                            'field' => $fieldName,
                            'language' => $languageCode,
                            'status' => 'saved'
                        ];
                    } else {
                        $result['errors'][] = "Failed to save translation for field '{$fieldName}' in language '{$languageCode}'";
                    }
                } catch (Exception $e) {
                    $result['errors'][] = "Exception while saving translation for field '{$fieldName}' in language '{$languageCode}': " . $e->getMessage();
                }
            }
        }

        // If there are any errors, mark as not fully successful
        if (!empty($result['errors'])) {
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * Handle partner update with translations
     * 
     * @param array $postData POST data from the form
     * @param array $partnerData Partner data array
     * @param int $partnerId Partner ID
     * @param string $defaultLanguage Default language code
     * @return array Result with success status and any errors
     */
    public function handlePartnerUpdateWithTranslations(array $postData, array $partnerData, int $partnerId, string $defaultLanguage): array
    {
        // For updates, we can reuse the same logic as creation
        return $this->handlePartnerCreationWithTranslations($postData, $partnerData, $partnerId, $defaultLanguage);
    }

    /**
     * Get partner translations for a specific language
     * 
     * @param int $partnerId Partner ID
     * @param string $languageCode Language code
     * @return array|null Translated details or null if not found
     */
    public function getPartnerTranslations(int $partnerId, string $languageCode): ?array
    {
        return $this->translationModel->getTranslatedDetails($partnerId, $languageCode);
    }

    /**
     * Get all translations for a partner
     * 
     * @param int $partnerId Partner ID
     * @return array All translations for the partner
     */
    public function getAllPartnerTranslations(int $partnerId): array
    {
        return $this->translationModel->getAllTranslationsForPartner($partnerId);
    }

    /**
     * Delete all translations for a partner
     * 
     * @param int $partnerId Partner ID
     * @return bool Success status
     */
    public function deletePartnerTranslations(int $partnerId): bool
    {
        return $this->translationModel->deletePartnerTranslations($partnerId);
    }

    /**
     * Get partner with all translations
     * 
     * @param int $partnerId Partner ID
     * @return array Result with success status and translated data
     */
    public function getPartnerWithTranslations(int $partnerId): array
    {
        $result = [
            'success' => true,
            'translated_data' => [],
            'errors' => []
        ];

        try {
            // Get all translations for this partner
            $allTranslations = $this->translationModel->getAllTranslationsForPartner($partnerId);

            // Organize translations by language code
            foreach ($allTranslations as $translation) {
                $languageCode = $translation['language_code'];
                $result['translated_data'][$languageCode] = [
                    'username' => $translation['username'] ?? '',
                    'company_name' => $translation['company_name'] ?? '',
                    'about' => $translation['about'] ?? '',
                    'long_description' => $translation['long_description'] ?? ''
                ];
            }
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = 'Exception while getting partner translations: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Get current language from session
     * 
     * @return string Current language code
     */
    public function getCurrentLanguage(): string
    {
        return get_current_language();
    }

    /**
     * Apply translations to partner data
     * 
     * @param array $partnerData Original partner data
     * @param int $partnerId Partner ID
     * @return array Partner data with translated fields
     */
    public function applyTranslations(array $partnerData, int $partnerId): array
    {
        $currentLang = $this->getCurrentLanguage();
        $defaultLangCode = get_default_language();

        // If current language is the default language, return original data
        if ($currentLang === $defaultLangCode) {
            return $partnerData;
        }

        try {
            // Get translated data for current language
            $translatedData = $this->translationModel->getTranslatedDetails($partnerId, $currentLang);

            if ($translatedData) {
                // Replace translatable fields with translated versions
                $partnerData['company_name'] = !empty($translatedData['company_name']) ? $translatedData['company_name'] : $partnerData['company_name'];
                $partnerData['about'] = !empty($translatedData['about']) ? $translatedData['about'] : $partnerData['about'];
                $partnerData['long_description'] = !empty($translatedData['long_description']) ? $translatedData['long_description'] : $partnerData['long_description'];
            }
        } catch (Exception $e) {
            // Log error but don't break the function
            log_message('error', 'Translation processing failed in applyTranslations: ' . $e->getMessage());
        }

        return $partnerData;
    }

    /**
     * Apply translations to multiple partners
     * 
     * @param array $partners Array of partner data
     * @return array Partners with translated fields
     */
    public function applyTranslationsToMultiple(array $partners): array
    {
        $currentLang = $this->getCurrentLanguage();
        $defaultLangCode = get_default_language();

        // If current language is the default language, return original data
        if ($currentLang === $defaultLangCode) {
            return $partners;
        }

        foreach ($partners as &$partner) {
            if (isset($partner['partner_id'])) {
                $partner = $this->applyTranslations($partner, $partner['partner_id']);
            }
        }

        return $partners;
    }

    /**
     * Create Partner Entity with translations
     * 
     * @param array $partnerData Partner data
     * @return \App\Entities\PartnerEntity Partner entity with translations
     */
    public function createPartnerEntity(array $partnerData): \App\Entities\PartnerEntity
    {
        $entity = new \App\Entities\PartnerEntity($partnerData);
        return $entity;
    }

    /**
     * Get search conditions for translated fields
     * 
     * @param string $searchTerm Search term
     * @return array Search conditions
     */
    public function getTranslatedSearchConditions(string $searchTerm): array
    {
        $currentLang = $this->getCurrentLanguage();
        $defaultLangCode = get_default_language();

        // If current language is the default language, return empty array
        if ($currentLang === $defaultLangCode) {
            return [];
        }

        return [
            '`tpd.company_name`' => $searchTerm,
            '`tpd.about`' => $searchTerm,
            '`tpd.long_description`' => $searchTerm
        ];
    }

    /**
     * Save or update partner translations
     * 
     * @param int $partnerId Partner ID
     * @param string $languageCode Language code
     * @param array $translatedData Translation data
     * @return bool Success status
     */
    public function saveTranslations(int $partnerId, string $languageCode, array $translatedData): bool
    {
        return $this->translationModel->saveTranslatedDetails($partnerId, $languageCode, $translatedData);
    }

    /**
     * Get partners with default language details
     * 
     * @param string $defaultLanguage Default language code
     * @param array $where Additional where conditions
     * @param int $limit Limit for results
     * @param int $offset Offset for pagination
     * @return array Partners with default language details
     */
    public function getPartnersWithDefaultLanguage(string $defaultLanguage, array $where = [], int $limit = 10, int $offset = 0): array
    {
        return $this->translationModel->getPartnersWithDefaultLanguage($defaultLanguage, $where, $limit, $offset);
    }
}
