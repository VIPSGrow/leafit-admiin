<?php

namespace App\Services;

use App\Models\TranslatedServiceDetails_model;
use Exception;

/**
 * Service Service
 * 
 * Handles business logic for service operations including translations
 * Similar to the implementation in V1.php manage_service method
 */
class ServicesService
{
    protected TranslatedServiceDetails_model $translationModel;

    public function __construct()
    {
        $this->translationModel = new TranslatedServiceDetails_model();
    }

    /**
     * Handle service creation with translations
     * 
     * @param array $postData POST data from the form
     * @param array $serviceData Service data array
     * @param int $serviceId Service ID
     * @param string $defaultLanguage Default language code
     * @return array Result with success status and any errors
     */
    public function handleServiceCreationWithTranslations(array $postData, array $serviceData, int $serviceId, string $defaultLanguage): array
    {
        // LOG: Start of translation handling

        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        try {
            // Validate that default language values are present
            $validationResult = $this->validateDefaultLanguageValues($postData, $defaultLanguage);

            if (!$validationResult['valid']) {
                $result['success'] = false;
                $result['errors'] = array_merge($result['errors'], $validationResult['errors']);
                return $result; // Stop processing if validation fails
            }

            // Process translations if provided
            $translationResult = $this->processServiceTranslations($serviceId, $postData, $defaultLanguage);

            if (!$translationResult['success']) {
                $result['success'] = false;
                $result['errors'] = array_merge($result['errors'], $translationResult['errors']);
            }

            $result['processed_languages'] = $translationResult['processed_languages'] ?? [];
        } catch (Exception $e) {
            log_message('error', '[SERVICE_SERVICE] Exception in translation processing: ' . $e->getMessage());
            $result['success'] = false;
            $result['errors'][] = 'Exception in translation processing: ' . $e->getMessage();
        }

        // log_message('debug', '[SERVICE_SERVICE] Final result: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }

    /**
     * Process service translations from form data
     * 
     * @param int $serviceId Service ID
     * @param array $postData POST data from the form
     * @param string $defaultLanguage Default language code
     * @return array Result with success status and any errors
     */
    private function processServiceTranslations(int $serviceId, array $postData, string $defaultLanguage): array
    {
        // LOG: Start of translation processing
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        // Validate service ID
        if (empty($serviceId)) {
            $result['success'] = false;
            $result['errors'][] = 'Service ID is required';
            return $result;
        }

        // Check if translated_fields is provided in the POST data
        $translatedFields = $postData['translated_fields'] ?? null;

        // If translated_fields is provided, use it directly
        if (!empty($translatedFields) && is_array($translatedFields)) {
            $saveResult = $this->saveTranslatedFields($serviceId, $translatedFields);

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
            'title',
            'description',
            'long_description',
            'tags',
            'faqs'
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
                // Form sends data as: title[en], description[en], etc.
                $fieldKey = $fieldName . '[' . $languageCode . ']';
                $fieldValue = $postData[$fieldKey] ?? null;

                // For default language, also check if there's a direct field value (fallback)
                if ($languageCode === $defaultLanguage && empty($fieldValue)) {
                    $fieldValue = $postData[$fieldName] ?? null;
                }

                // Handle special cases for tags and faqs
                if ($fieldName === 'tags') {
                    $fieldValue = $this->normalizeTags($fieldValue);
                } elseif ($fieldName === 'faqs' && is_array($fieldValue)) {
                    $processedFaqs = [];

                    foreach ($fieldValue as $faqData) {
                        if (!is_array($faqData)) {
                            continue;
                        }

                        foreach ($faqData as $faqLanguageCode => $languageFaq) {
                            if (
                                !empty($languageFaq['question']) &&
                                !empty($languageFaq['answer'])
                            ) {
                                $processedFaqs[$faqLanguageCode][] = [
                                    'question' => trim($languageFaq['question']),
                                    'answer'   => trim($languageFaq['answer']),
                                ];
                            }
                        }
                    }

                    $fieldValue = !empty($processedFaqs)
                        ? json_encode($processedFaqs, JSON_UNESCAPED_UNICODE)
                        : '';
                }

                // Only add non-empty values
                if (!empty($fieldValue)) {
                    $translatedFields[$fieldName][$languageCode] = trim($fieldValue);
                }
            }
        }

        // Save translations using the new structure
        $saveResult = $this->saveTranslatedFields($serviceId, $translatedFields);

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
     * @param int $serviceId Service ID
     * @param array $translatedFields Translated fields structure
     * @return array Result with success status and any errors
     */
    public function saveTranslatedFields(int $serviceId, array $translatedFields): array
    {
        // // LOG: Start of saving translated fields
        // log_message('debug', '[FAQ_DEBUG] Starting saveTranslatedFields for service ID: ' . $serviceId);
        // log_message('debug', '[FAQ_DEBUG] Translated fields to save: ' . json_encode($translatedFields, JSON_UNESCAPED_UNICODE));

        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        // Define field mapping to database columns
        $fieldMapping = [
            'title' => 'title',
            'description' => 'description',
            'long_description' => 'long_description',
            'tags' => 'tags',
            'faqs' => 'faqs'
        ];

        // Process each field
        foreach ($translatedFields as $fieldName => $languageData) {
            if (!isset($fieldMapping[$fieldName])) {
                continue; // Skip unknown fields
            }

            $dbFieldName = $fieldMapping[$fieldName];

            // Handle special FAQ processing for the new structure
            if ($fieldName === 'faqs' && is_array($languageData)) {
                $this->processFaqsFromTranslatedFields($serviceId, $languageData);
                $result['processed_languages'][] = [
                    'field' => $fieldName,
                    'status' => 'processed'
                ];
            } else {
                // Process each language for this field (for non-FAQ fields)
                foreach ($languageData as $languageCode => $translatedText) {

                    try {
                        // Prepare data for this specific field and language
                        $translationData = [
                            $dbFieldName => $translatedText
                        ];

                        $saveResult = $this->translationModel->saveTranslatedDetails(
                            $serviceId,
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
                        log_message('error', '[SERVICE_SERVICE] Exception saving ' . $fieldName . ' for language ' . $languageCode . ': ' . $e->getMessage());
                        $result['errors'][] = "Exception while saving translation for field '{$fieldName}' in language '{$languageCode}': " . $e->getMessage();
                    }
                }
            }
        }

        // If there are any errors, mark as not fully successful
        // if (!empty($result['errors'])) {
        //     $result['success'] = false;
        //     log_message('error', '[SERVICE_SERVICE] saveTranslatedFields completed with errors: ' . json_encode($result['errors']));
        // } else {
        //     log_message('debug', '[SERVICE_SERVICE] saveTranslatedFields completed successfully');
        // }

        return $result;
    }

    /**
     * Handle service update with translations
     * 
     * @param array $postData POST data from the form
     * @param array $serviceData Service data array
     * @param int $serviceId Service ID
     * @param string $defaultLanguage Default language code
     * @return array Result with success status and any errors
     */
    public function handleServiceUpdateWithTranslations(array $postData, array $serviceData, int $serviceId, string $defaultLanguage): array
    {
        // LOG: Service update translation handling
        // log_message('debug', '[SERVICE_SERVICE] Starting handleServiceUpdateWithTranslations for service ID: ' . $serviceId);
        // log_message('debug', '[SERVICE_SERVICE] Default language: ' . $defaultLanguage);

        // For updates, we can reuse the same logic as creation
        $result = $this->handleServiceCreationWithTranslations($postData, $serviceData, $serviceId, $defaultLanguage);

        // log_message('debug', '[SERVICE_SERVICE] Update result: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }

    /**
     * Get service translations for a specific language
     * 
     * @param int $serviceId Service ID
     * @param string $languageCode Language code
     * @return array|null Translated details or null if not found
     */
    public function getServiceTranslations(int $serviceId, string $languageCode): ?array
    {
        return $this->translationModel->getTranslatedDetails($serviceId, $languageCode);
    }

    /**
     * Get all translations for a service
     * 
     * @param int $serviceId Service ID
     * @return array All translations for the service
     */
    public function getAllServiceTranslations(int $serviceId): array
    {
        return $this->translationModel->getAllTranslationsForService($serviceId);
    }

    /**
     * Delete all translations for a service
     * 
     * @param int $serviceId Service ID
     * @return bool Success status
     */
    public function deleteServiceTranslations(int $serviceId): bool
    {
        return $this->translationModel->deleteServiceTranslations($serviceId);
    }

    /**
     * Get service with all translations
     * 
     * @param int $serviceId Service ID
     * @return array Result with success status and translated data
     */
    public function getServiceWithTranslations(int $serviceId): array
    {
        $result = [
            'success' => true,
            'translated_data' => [],
            'errors' => []
        ];

        try {
            // Get all translations for this service
            $allTranslations = $this->translationModel->getAllTranslationsForService($serviceId);

            // Organize translations by language code
            foreach ($allTranslations as $translation) {
                $languageCode = $translation['language_code'];
                $result['translated_data'][$languageCode] = [
                    'title' => $translation['title'] ?? '',
                    'description' => $translation['description'] ?? '',
                    'long_description' => $translation['long_description'] ?? '',
                    'tags' => $translation['tags'] ?? '',
                    'faqs' => $translation['faqs'] ?? ''
                ];
            }
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = 'Exception while getting service translations: ' . $e->getMessage();
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
     * Apply translations to service data
     * 
     * @param array $serviceData Original service data
     * @param int $serviceId Service ID
     * @return array Service data with translated fields
     */
    public function applyTranslations(array $serviceData, int $serviceId): array
    {
        $currentLang = $this->getCurrentLanguage();
        $defaultLangCode = get_default_language();

        // If current language is the default language, return original data
        if ($currentLang === $defaultLangCode) {
            return $serviceData;
        }

        try {
            // Get translated data for current language
            $translatedData = $this->translationModel->getTranslatedDetails($serviceId, $currentLang);

            if ($translatedData) {
                // Replace translatable fields with translated versions
                $serviceData['title'] = !empty($translatedData['title']) ? $translatedData['title'] : $serviceData['title'];
                $serviceData['description'] = !empty($translatedData['description']) ? $translatedData['description'] : $serviceData['description'];
                $serviceData['long_description'] = !empty($translatedData['long_description']) ? $translatedData['long_description'] : $serviceData['long_description'];
                $serviceData['tags'] = !empty($translatedData['tags']) ? $translatedData['tags'] : $serviceData['tags'];
                $serviceData['faqs'] = !empty($translatedData['faqs']) ? $translatedData['faqs'] : $serviceData['faqs'];
            }
        } catch (Exception $e) {
            // Log error but don't break the function
            log_message('error', 'Translation processing failed in applyTranslations: ' . $e->getMessage());
        }

        return $serviceData;
    }

    /**
     * Apply translations to multiple services
     * 
     * @param array $services Array of service data
     * @return array Services with translated fields
     */
    public function applyTranslationsToMultiple(array $services): array
    {
        $currentLang = $this->getCurrentLanguage();
        $defaultLangCode = get_default_language();

        // If current language is the default language, return original data
        if ($currentLang === $defaultLangCode) {
            return $services;
        }

        foreach ($services as &$service) {
            if (isset($service['id'])) {
                $service = $this->applyTranslations($service, $service['id']);
            }
        }

        return $services;
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
            '`tsd.title`' => $searchTerm,
            '`tsd.description`' => $searchTerm,
            '`tsd.long_description`' => $searchTerm,
            '`tsd.tags`' => $searchTerm
        ];
    }

    /**
     * Save or update service translations
     * 
     * @param int $serviceId Service ID
     * @param string $languageCode Language code
     * @param array $translatedData Translation data
     * @return bool Success status
     */
    public function saveTranslations(int $serviceId, string $languageCode, array $translatedData): bool
    {
        return $this->translationModel->saveTranslatedDetails($serviceId, $languageCode, $translatedData);
    }

    /**
     * Process FAQs from the new translated_fields structure
     * 
     * @param int $serviceId Service ID
     * @param array $faqsData FAQs data from translated_fields structure
     * @return void
     */
    private function processFaqsFromTranslatedFields(int $serviceId, array $faqsData): void
    {
        try {
            // Process FAQs for each language directly
            foreach ($faqsData as $languageCode => $languageFaqs) {
                // Check if languageFaqs is a string (JSON) and decode it
                if (is_string($languageFaqs)) {
                    $languageFaqs = json_decode($languageFaqs, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue;
                    }
                }

                // Ensure we have an array of FAQs
                if (!is_array($languageFaqs)) {
                    continue;
                }

                try {
                    // Prepare data for this specific language
                    $translationData = [
                        'faqs' => json_encode($languageFaqs)
                    ];

                    $this->translationModel->saveTranslatedDetails(
                        $serviceId,
                        $languageCode,
                        $translationData
                    );
                } catch (Exception $e) {
                    log_message('error', 'Exception while saving FAQs for service ' . $serviceId . ' in language ' . $languageCode . ': ' . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            log_message('error', 'Exception in processFaqsFromTranslatedFields for service ' . $serviceId . ': ' . $e->getMessage());
        }
    }

    /**
     * Validate that default language values are present in the request
     * This ensures that required translatable fields are provided for the default language
     * before allowing service creation or update
     * 
     * @param array $postData POST data from the form
     * @param string $defaultLanguage Default language code from database
     * @return array Validation result with success status and errors
     */
    private function validateDefaultLanguageValues(array $postData, string $defaultLanguage): array
    {
        // LOG: Start validation
        // log_message('debug', '[SERVICE_SERVICE] Starting validation for default language: ' . $defaultLanguage);

        $result = [
            'valid' => true,
            'errors' => []
        ];

        // Define required translatable fields
        $requiredFields = [
            'title' => 'Title',
            'description' => 'Description'
        ];

        // Check if translated_fields is provided in the POST data
        $translatedFields = $postData['translated_fields'] ?? null;

        // log_message('debug', '[SERVICE_SERVICE] Translated fields in validation: ' . (is_null($translatedFields) ? 'null' : json_encode($translatedFields, JSON_UNESCAPED_UNICODE)));

        // If translated_fields is provided as JSON string, decode it
        if (is_string($translatedFields)) {
            $translatedFields = json_decode($translatedFields, true);
        }

        if (!empty($translatedFields) && is_array($translatedFields)) {
            // Validate using translated_fields structure
            foreach ($requiredFields as $fieldName => $fieldLabel) {
                // Check if the field exists in translated_fields
                if (!isset($translatedFields[$fieldName])) {
                    $result['valid'] = false;
                    $result['errors'][] = "{$fieldLabel} is required in translated_fields";
                    continue;
                }

                // Check if default language value exists for this field
                if (!isset($translatedFields[$fieldName][$defaultLanguage])) {
                    $result['valid'] = false;
                    $result['errors'][] = "{$fieldLabel} is required for default language ({$defaultLanguage})";
                    continue;
                }

                // Check if the value is not empty
                $fieldValue = $translatedFields[$fieldName][$defaultLanguage];
                if (empty(trim($fieldValue))) {
                    $result['valid'] = false;
                    $result['errors'][] = "{$fieldLabel} cannot be empty for default language ({$defaultLanguage})";
                }
            }

            // Special validation for FAQs if present - FAQs are OPTIONAL
            if (isset($translatedFields['faqs']) && is_array($translatedFields['faqs'])) {
                // log_message('debug', '[SERVICE_SERVICE] FAQ validation - checking if FAQs have content');

                // Check if any language has actual FAQ content (not just empty arrays)
                $hasAnyFaqContent = false;

                foreach ($translatedFields['faqs'] as $languageCode => $faqContent) {
                    // Check if this language has actual FAQ data
                    if (is_string($faqContent)) {
                        $decodedFaqs = json_decode($faqContent, true);
                        if (is_array($decodedFaqs) && !empty($decodedFaqs)) {
                            // Check if any FAQ has both question and answer
                            foreach ($decodedFaqs as $faq) {
                                if (
                                    isset($faq['question']) && isset($faq['answer']) &&
                                    !empty(trim($faq['question'])) && !empty(trim($faq['answer']))
                                ) {
                                    $hasAnyFaqContent = true;
                                    break 2; // Break both loops
                                }
                            }
                        }
                    }
                }

                // log_message('debug', '[SERVICE_SERVICE] FAQ validation - has any content: ' . ($hasAnyFaqContent ? 'Yes' : 'No'));

                // Only validate default language FAQs if there's actual FAQ content somewhere
                if ($hasAnyFaqContent) {
                    // log_message('debug', '[SERVICE_SERVICE] FAQ validation - checking default language FAQs');

                    $hasDefaultLanguageFaqs = false;

                    // Check if default language has FAQ content
                    if (isset($translatedFields['faqs'][$defaultLanguage])) {
                        $defaultFaqContent = $translatedFields['faqs'][$defaultLanguage];
                        if (is_string($defaultFaqContent)) {
                            $decodedDefaultFaqs = json_decode($defaultFaqContent, true);
                            if (is_array($decodedDefaultFaqs) && !empty($decodedDefaultFaqs)) {
                                foreach ($decodedDefaultFaqs as $faq) {
                                    if (
                                        isset($faq['question']) && isset($faq['answer']) &&
                                        !empty(trim($faq['question'])) && !empty(trim($faq['answer']))
                                    ) {
                                        $hasDefaultLanguageFaqs = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if (!$hasDefaultLanguageFaqs) {
                        $result['valid'] = false;
                        $result['errors'][] = "If FAQs are provided in any language, at least one FAQ with question and answer is required for default language ({$defaultLanguage})";
                        // log_message('error', '[SERVICE_SERVICE] FAQ validation failed - no default language FAQs when other languages have FAQs');
                    }
                } else {
                    // log_message('debug', '[SERVICE_SERVICE] FAQ validation skipped - no FAQ content provided');
                }
            } else {
                // log_message('debug', '[SERVICE_SERVICE] FAQ validation skipped - no FAQ data in translated fields');
            }
        } else {
            // Validate using main POST data structure (legacy format)
            foreach ($requiredFields as $fieldName => $fieldLabel) {
                // Check if the field exists in main POST data
                if (!isset($postData[$fieldName])) {
                    $result['valid'] = false;
                    $result['errors'][] = "{$fieldLabel} is required";
                    continue;
                }

                // Check if the value is not empty
                $fieldValue = $postData[$fieldName];
                if (empty(trim($fieldValue))) {
                    $result['valid'] = false;
                    $result['errors'][] = "{$fieldLabel} cannot be empty";
                }
            }

            // Check for language-specific fields (e.g., title[en], description[en])
            foreach ($requiredFields as $fieldName => $fieldLabel) {
                $fieldKey = $fieldName . '[' . $defaultLanguage . ']';
                if (isset($postData[$fieldKey])) {
                    $fieldValue = $postData[$fieldKey];
                    if (empty(trim($fieldValue))) {
                        $result['valid'] = false;
                        $result['errors'][] = "{$fieldLabel} cannot be empty for default language ({$defaultLanguage})";
                    }
                }
            }
        }

        return $result;
    }

    private function normalizeTags($value): string
    {
        if (empty($value)) {
            return '';
        }

        // Step 1: If array like [0 => '...'], unwrap it
        if (is_array($value) && count($value) === 1) {
            $value = reset($value);
        }

        // Step 2: Decode HTML entities
        if (is_string($value)) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Step 3: Try JSON decode
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                return trim($value);
            }
        }

        // Step 4: Extract values
        $tags = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item) && isset($item['value'])) {
                    $tags[] = trim($item['value']);
                } elseif (is_string($item)) {
                    $tags[] = trim($item);
                }
            }
        }

        return implode(', ', array_filter($tags));
    }
}
