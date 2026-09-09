<?php

namespace App\Services\Service;

/**
 * Stateless helpers for transforming multilingual service form data.
 *
 * Extracted from Admin\Services (private methods) so ServiceCreateService,
 * ServiceUpdateService, and ServiceBulkImportService can share them without
 * duplicating the logic.
 */
class ServiceTranslationHelper
{
    /**
     * Convert tags value (Tagify JSON, plain array, or comma string) to comma-separated string.
     */
    public static function processTags(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        if (is_array($value) && count($value) === 1) {
            $value = reset($value);
        }

        if (is_string($value)) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                return trim($value);
            }
        }

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

    /**
     * Process the clean language-grouped FAQ structure sent by the new form:
     * {"en": [["q1","a1"],["q2","a2"]], "hi": [...]}
     */
    public static function processCleanFAQData(array $faqData): array
    {
        $translatedFields = [];
        $defaultLanguage  = get_default_language();

        foreach ($faqData as $languageCode => $languageFaqs) {
            if (empty($languageCode) || !is_array($languageFaqs)) {
                continue;
            }

            $processedFaqs = [];
            foreach ($languageFaqs as $faqPair) {
                if (!is_array($faqPair) || count($faqPair) !== 2) {
                    continue;
                }
                $question = trim($faqPair[0] ?? '');
                $answer   = trim($faqPair[1] ?? '');
                if (!empty($question) || !empty($answer)) {
                    $processedFaqs[] = ['question' => $question, 'answer' => $answer];
                }
            }

            $translatedFields['faqs'][$languageCode] = !empty($processedFaqs) ? $processedFaqs : [];
        }

        $languages = fetch_details('languages', [], ['code']);
        foreach ($languages as $language) {
            $lc = $language['code'];
            if (!isset($translatedFields['faqs'][$lc])) {
                $translatedFields['faqs'][$lc] = json_encode([], JSON_UNESCAPED_UNICODE);
            }
        }

        return $translatedFields;
    }

    /**
     * Transform raw POST data into the translated_fields structure expected by ServicesService.
     *
     * Also extracts SEO fields (meta_title, meta_description, meta_keywords, schema_markup)
     * keyed as seo_title, seo_description, seo_keywords, seo_schema_markup.
     */
    public static function transformFormDataToTranslatedFields(
        array  $postData,
        string $defaultLanguage,
        ?int   $serviceId             = null,
        array  $existingTranslations  = []
    ): array {
        $translatedFields   = [];
        $translatableFields = ['title', 'description', 'long_description', 'tags', 'faqs'];
        $seoFields          = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        // Handle FAQs from new clean-format string
        if (isset($postData['faqs']) && is_string($postData['faqs'])) {
            $faqData = json_decode(htmlspecialchars_decode($postData['faqs']), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($faqData)) {
                $cleanFaqFields  = self::processCleanFAQData($faqData);
                $translatedFields = array_merge($translatedFields, $cleanFaqFields);
                unset($postData['faqs']);
            }
        } elseif (isset($postData['faqs']) && is_array($postData['faqs'])) {
            foreach ($postData['faqs'] as $languageCode => $languageFaqs) {
                if (is_array($languageFaqs)) {
                    $translatedFields['faqs'][$languageCode] = $languageFaqs;
                }
            }
        }

        // Process remaining translatable fields
        foreach ($translatableFields as $field) {
            if ($field === 'faqs') {
                continue;
            }

            if (isset($postData[$field]) && is_array($postData[$field])) {
                foreach ($postData[$field] as $languageCode => $value) {
                    if (empty($languageCode) || $languageCode === '0') {
                        continue;
                    }
                    if ($field === 'tags') {
                        $translatedFields[$field][$languageCode] = self::processTags($value);
                    } else {
                        $translatedFields[$field][$languageCode] = trim($value);
                    }
                }
            } elseif ($serviceId && !empty($existingTranslations)) {
                foreach ($existingTranslations as $languageCode => $translation) {
                    if (isset($translation[$field]) && !empty($translation[$field])) {
                        $translatedFields[$field][$languageCode] = $translation[$field];
                    }
                }
            }
        }

        // Process SEO fields
        foreach ($seoFields as $field) {
            $formFieldName = str_replace('seo_', 'meta_', $field);
            if (isset($postData[$formFieldName]) && is_array($postData[$formFieldName])) {
                foreach ($postData[$formFieldName] as $languageCode => $value) {
                    if (empty($languageCode) || $languageCode === '0') {
                        continue;
                    }
                    if ($field === 'seo_keywords') {
                        $translatedFields[$field][$languageCode] = ServiceSeoService::parseKeywords($value);
                    } else {
                        $translatedFields[$field][$languageCode] = trim($value);
                    }
                }
            }
        }

        return $translatedFields;
    }

    /**
     * Extract translated fields (title, description, long_description, tags, faqs)
     * from a single bulk-import CSV row, keyed by field → language.
     */
    public static function extractTranslatedFieldsFromBulkRow(
        array  $row,
        array  $languageHeaders,
        string $defaultLanguage
    ): array {
        $translatedFields = [
            'title'            => [],
            'description'      => [],
            'long_description' => [],
            'tags'             => [],
            'faqs'             => [],
        ];

        foreach (['title', 'description', 'long_description', 'tags'] as $field) {
            if (!isset($languageHeaders[$field])) {
                continue;
            }
            foreach ($languageHeaders[$field] as $langCode => $columnIndex) {
                $value = isset($row[$columnIndex]) ? trim($row[$columnIndex]) : '';
                if (!empty($value)) {
                    $translatedFields[$field][$langCode] = ($field === 'tags')
                        ? self::processTags($value)
                        : $value;
                }
            }
        }

        if (isset($languageHeaders['faqs'])) {
            $faqsByLanguage = [];
            $faqsByNumber   = [];

            foreach ($languageHeaders['faqs'] as $langCode => $faqNumbers) {
                $languageFaqs = [];

                foreach ($faqNumbers as $faqNumber => $questionAnswer) {
                    $question = isset($questionAnswer['question'], $row[$questionAnswer['question']])
                        ? trim($row[$questionAnswer['question']]) : '';
                    $answer   = isset($questionAnswer['answer'], $row[$questionAnswer['answer']])
                        ? trim($row[$questionAnswer['answer']]) : '';

                    if (!empty($question) || !empty($answer)) {
                        $faqData         = ['question' => $question, 'answer' => $answer];
                        $languageFaqs[]  = $faqData;

                        if (!isset($faqsByNumber[$faqNumber])) {
                            $faqsByNumber[$faqNumber] = [];
                        }
                        $faqsByNumber[$faqNumber][$langCode] = $faqData;
                    }
                }

                $faqsByLanguage[$langCode] = !empty($languageFaqs)
                    ? json_encode($languageFaqs, JSON_UNESCAPED_UNICODE)
                    : json_encode([], JSON_UNESCAPED_UNICODE);
            }

            $translatedFields['faqs']          = $faqsByLanguage;
            $translatedFields['faqs_by_number'] = $faqsByNumber;
        }

        return $translatedFields;
    }

    /**
     * Extract SEO translation columns from a bulk CSV row.
     * Columns are formatted: "SEO Title (en)", "SEO Description (ar)", etc.
     *
     * @return array Language-keyed SEO data: ['en' => ['seo_title' => ..., ...], ...]
     */
    public static function extractSeoTranslationsFromRow(
        array $row,
        array $headers,
        array $languages
    ): array {
        $seoTranslations = [];
        foreach ($languages as $language) {
            $seoTranslations[$language['code']] = [
                'seo_title'        => null,
                'seo_description'  => null,
                'seo_keywords'     => null,
                'seo_schema_markup' => null,
            ];
        }

        $seoFieldMapping = [
            'SEO Title'        => 'seo_title',
            'SEO Description'  => 'seo_description',
            'SEO Keywords'     => 'seo_keywords',
            'SEO Schema Markup' => 'seo_schema_markup',
        ];

        foreach ($headers as $index => $header) {
            if (!preg_match('/^(.+?)\s*\(([a-z]{2,3})\)$/i', $header, $matches)) {
                continue;
            }
            $fieldName    = trim($matches[1]);
            $languageCode = strtolower(trim($matches[2]));

            $matchedField = null;
            foreach ($seoFieldMapping as $mappingField => $dbFieldName) {
                if (strcasecmp(trim($mappingField), $fieldName) === 0) {
                    $matchedField = $mappingField;
                    break;
                }
            }

            if ($matchedField === null) {
                continue;
            }

            $languageExists = false;
            foreach ($languages as $language) {
                if ($language['code'] === $languageCode) {
                    $languageExists = true;
                    break;
                }
            }

            if (!$languageExists || !isset($row[$index]) || $row[$index] === null || $row[$index] === '') {
                continue;
            }

            $dbField      = $seoFieldMapping[$matchedField];
            $value        = $row[$index];
            if ($dbField === 'seo_keywords' && !empty($value)) {
                $value = ServiceSeoService::parseKeywords($value);
            }
            $trimmedValue = trim((string) $value);
            if (!empty($trimmedValue)) {
                $seoTranslations[$languageCode][$dbField] = $trimmedValue;
            }
        }

        return $seoTranslations;
    }
}
