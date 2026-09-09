<?php

if (!function_exists('getSeoWithTranslations')) {
    /**
     * Get SEO settings with translations for a specific entity
     * 
     * @param string $entityType The type of entity (providers, services, etc.)
     * @param int $entityId The ID of the entity
     * @param string $requestedLanguage The requested language code from header
     * @param string $defaultLanguage The default language code
     * @param array $fallbackData Fallback data when no SEO settings exist
     * @return array SEO settings with translations
     */
    function getproviderSeoTranslations(string $entityType, int $entityId, string $requestedLanguage, string $defaultLanguage, array $fallbackData = []): array
    {
        try {
            // Initialize SEO model
            $seoModel = new \App\Models\Seo_model();
            $seoModel->setTableContext($entityType);

            // Get base SEO settings
            $baseSeoSettings = $seoModel->getSeoSettingsByReferenceId($entityId, 'meta');

            // Initialize SEO translation model
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

            // Get all SEO translations for this entity
            $seoTranslations = $seoTranslationModel->getAllTranslationsForPartner($entityId);

            // Initialize result with base settings
            $result = $baseSeoSettings ?: [];

            // If no base SEO settings exist, use fallback data
            if (empty($result) || (empty($result['title']) && empty($result['description']))) {
                $result = array_merge($result, $fallbackData);
            }

            // Process translations
            $translatedFields = [];

            // Find translation for requested language
            $requestedTranslation = null;
            foreach ($seoTranslations as $translation) {
                if ($translation['language_code'] === $requestedLanguage) {
                    $requestedTranslation = $translation;
                    break;
                }
            }

            // Find translation for default language
            $defaultTranslation = null;
            foreach ($seoTranslations as $translation) {
                if ($translation['language_code'] === $defaultLanguage) {
                    $defaultTranslation = $translation;
                    break;
                }
            }

            // Determine which translation to use
            $selectedTranslation = $requestedTranslation ?: $defaultTranslation;

            if ($selectedTranslation) {
                // Use translation data
                $translatedFields = [
                    'translated_title' => $selectedTranslation['seo_title'] ?: '',
                    'translated_description' => $selectedTranslation['seo_description'] ?: '',
                    'translated_keywords' => $selectedTranslation['seo_keywords'] ?: '',
                    'translated_schema_markup' => $selectedTranslation['seo_schema_markup'] ?: ''
                ];
            } else {
                // No translation available, use base table data
                $translatedFields = [
                    'translated_title' => $result['title'] ?? '',
                    'translated_description' => $result['description'] ?? '',
                    'translated_keywords' => $result['keywords'],
                    'translated_schema_markup' => $result['schema_markup']
                ];
            }

            // Merge base settings with translated fields
            $result = array_merge($result, $translatedFields);

            return $result;
        } catch (\Throwable $th) {
            log_message('error', 'Error in getSeoWithTranslations: ' . $th->getMessage());

            // Return fallback data on error
            return array_merge($fallbackData, [
                'translated_title' => $fallbackData['title'] ?? '',
                'translated_description' => $fallbackData['description'] ?? '',
                'translated_keywords' => $fallbackData['keywords'] ?? '',
                'translated_schema_markup' => $fallbackData['schema_markup'] ?? ''
            ]);
        }
    }
}

if (!function_exists('getProviderSeoWithTranslations')) {
    /**
     * Get provider SEO settings with translations
     * 
     * @param int $providerId The provider ID
     * @param string $requestedLanguage The requested language code from header
     * @param string $defaultLanguage The default language code
     * @return array Provider SEO settings with translations
     */
    function getProviderSeoWithTranslations(int $providerId, string $requestedLanguage, string $defaultLanguage): array
    {
        try {
            // Get provider details for fallback
            $provider = fetch_details('partner_details', ['partner_id' => $providerId], ['company_name', 'long_description']);

            if (empty($provider)) {
                return [
                    'translated_title' => '',
                    'translated_description' => '',
                    'translated_keywords' => '',
                    'translated_schema_markup' => ''
                ];
            }

            $providerData = $provider[0];

            // Create fallback data
            $fallbackData = [
                'title' => $providerData['company_name'],
                'description' => stripHtmlTags($providerData['long_description']),
                'keywords' => '',
                'schema_markup' => ''
            ];

            // Get SEO with translations
            return getproviderSeoTranslations('providers', $providerId, $requestedLanguage, $defaultLanguage, $fallbackData);
        } catch (\Throwable $th) {
            log_message('error', 'Error in getProviderSeoWithTranslations: ' . $th->getMessage());

            // Return empty data on error
            return [
                'translated_title' => '',
                'translated_description' => '',
                'translated_keywords' => '',
                'translated_schema_markup' => '',
            ];
        }
    }
}

if (!function_exists('getServiceSeoWithTranslations')) {
    /**
     * Get service SEO settings with translations
     * - Mirrors provider SEO translation pattern
     * - Uses translations table first, then falls back to service fields
     *
     * @param int $serviceId Service ID
     * @param string $requestedLanguage Requested language code from header
     * @param string $defaultLanguage Default language code
     * @return array SEO settings with translated_* fields
     */
    function getServiceSeoWithTranslations(int $serviceId, string $requestedLanguage, string $defaultLanguage): array
    {
        try {
            // Fallback: fetch service title and description
            $serviceRows = fetch_details('services', ['id' => $serviceId], ['title', 'description']);
            if (empty($serviceRows)) {
                return [
                    'translated_title' => '',
                    'translated_description' => '',
                    'translated_keywords' => '',
                    'translated_schema_markup' => ''
                ];
            }

            $service = $serviceRows[0];

            // Base SEO from services table
            $seoModel = new \App\Models\Seo_model();
            $seoModel->setTableContext('services');
            $baseSeoSettings = $seoModel->getSeoSettingsByReferenceId($serviceId, 'meta');

            // Translations for services
            $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
            $seoTranslations = $seoTranslationModel->getAllTranslationsForService($serviceId);

            // Choose requested -> default translation
            $requestedTranslation = null;
            foreach ($seoTranslations as $t) {
                if (($t['language_code'] ?? '') === $requestedLanguage) {
                    $requestedTranslation = $t;
                    break;
                }
            }
            $defaultTranslation = null;
            foreach ($seoTranslations as $t) {
                if (($t['language_code'] ?? '') === $defaultLanguage) {
                    $defaultTranslation = $t;
                    break;
                }
            }
            $selected = $requestedTranslation ?: $defaultTranslation;

            // Build translated_* fields
            if ($selected) {
                $translatedFields = [
                    'translated_title' => $selected['seo_title'] ?: '',
                    'translated_description' => $selected['seo_description'] ?: '',
                    'translated_keywords' => $selected['seo_keywords'] ?: '',
                    'translated_schema_markup' => $selected['seo_schema_markup'] ?: ''
                ];
            } else {
                // Fallback to base SEO then to service fields
                $translatedFields = [
                    'translated_title' => $baseSeoSettings['title'] ?? ($service['title'] ?? ''),
                    'translated_description' => $baseSeoSettings['description'] ?? (stripHtmlTags($service['description'] ?? '') ?? ''),
                    'translated_keywords' => $baseSeoSettings['keywords'] ?? '',
                    'translated_schema_markup' => $baseSeoSettings['schema_markup'] ?? ''
                ];
            }

            // Merge base settings with translated fields (base may be empty)
            $result = is_array($baseSeoSettings) ? $baseSeoSettings : [];
            $result = array_merge($result, $translatedFields);

            // Ensure minimal fallback when base SEO is empty
            if (empty($result) || ((string)($result['title'] ?? '') === '' && (string)($result['description'] ?? '') === '')) {
                $result['title'] = $service['title'] ?? '';
                $result['description'] = stripHtmlTags($service['description'] ?? '');
                $result['keywords'] = '';
                $result['schema_markup'] = '';
            }

            return $result;
        } catch (\Throwable $th) {
            log_message('error', 'Error in getServiceSeoWithTranslations: ' . $th->getMessage());
            return [
                'translated_title' => '',
                'translated_description' => '',
                'translated_keywords' => '',
                'translated_schema_markup' => ''
            ];
        }
    }
}

if (!function_exists('stripHtmlTags')) {
    /**
     * Strip HTML tags from text
     * 
     * @param string $text The text to strip HTML from
     * @return string Cleaned text
     */
    function stripHtmlTags(string $text): string
    {
        return strip_tags($text);
    }
}

if (!function_exists('getServiceSeoForManageServiceResponse')) {
    /**
     * Assemble service SEO for manage_service response.
     * - Builds per-language seo_* maps from translations
     * - Backfills seo_title/seo_description per language from service translated fields when missing
     * - Computes legacy fields using requested -> default -> base SEO (only if base SEO exists)
     *
     * @param int $serviceId
     * @param array $baseSeoSettings Base SEO from seo_settings (may be empty)
     * @param array $serviceTranslatedFields Service translated_fields (expects title/description maps)
     * @param string $defaultLang Default language code (fallback)
     * @return array [ 'translated_fields_patch' => [...], 'legacy_override' => [...] ]
     */
    function getServiceSeoForManageServiceResponse(int $serviceId, array $baseSeoSettings, array $serviceTranslatedFields, string $defaultLang): array
    {
        try {
            $requestedLang = function_exists('get_current_language_from_request') ? (get_current_language_from_request() ?: $defaultLang) : $defaultLang;
            $effectiveDefault = function_exists('get_default_language') ? get_default_language() : $defaultLang;

            // Fetch translations for services
            $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
            $seoTranslations = $seoTranslationModel->getAllTranslationsForService($serviceId);

            // Build per-language maps
            $tfSeoTitle = [];
            $tfSeoDesc = [];
            $tfSeoKeywords = [];
            $tfSeoSchema = [];
            foreach ($seoTranslations as $row) {
                $lang = $row['language_code'] ?? '';
                if ($lang === '') {
                    continue;
                }
                if (!empty($row['seo_title'])) {
                    $tfSeoTitle[$lang] = $row['seo_title'];
                }
                if (!empty($row['seo_description'])) {
                    $tfSeoDesc[$lang] = $row['seo_description'];
                }
                if (!empty($row['seo_keywords'])) {
                    $tfSeoKeywords[$lang] = $row['seo_keywords'];
                }
                if (!empty($row['seo_schema_markup'])) {
                    $tfSeoSchema[$lang] = $row['seo_schema_markup'];
                }
            }

            // Backfill from service translated title/description where missing
            $serviceTitles = $serviceTranslatedFields['title'] ?? [];
            $serviceDescs  = $serviceTranslatedFields['description'] ?? [];
            $langs = array_unique(array_merge(array_keys($serviceTitles), array_keys($serviceDescs), array_keys($tfSeoTitle), array_keys($tfSeoDesc)));
            foreach ($langs as $lang) {
                if (!isset($tfSeoTitle[$lang]) && isset($serviceTitles[$lang])) {
                    $tfSeoTitle[$lang] = $serviceTitles[$lang];
                }
                if (!isset($tfSeoDesc[$lang]) && isset($serviceDescs[$lang])) {
                    $tfSeoDesc[$lang] = $serviceDescs[$lang];
                }
            }

            // Translated fields patch to merge into response
            $translatedFieldsPatch = [
                'seo_title' => $tfSeoTitle,
                'seo_description' => $tfSeoDesc,
                'seo_keywords' => $tfSeoKeywords,
                'seo_schema_markup' => $tfSeoSchema,
            ];

            // Legacy override only when base SEO exists
            $legacyOverride = [];
            if (!empty($baseSeoSettings)) {
                $legacyOverride['seo_title'] = $tfSeoTitle[$requestedLang] ?? $tfSeoTitle[$effectiveDefault] ?? ($baseSeoSettings['title'] ?? '');
                $legacyOverride['seo_description'] = $tfSeoDesc[$requestedLang] ?? $tfSeoDesc[$effectiveDefault] ?? ($baseSeoSettings['description'] ?? '');
                $legacyOverride['seo_keywords'] = $tfSeoKeywords[$requestedLang] ?? $tfSeoKeywords[$effectiveDefault] ?? ($baseSeoSettings['keywords'] ?? '');
                $legacyOverride['seo_schema_markup'] = $tfSeoSchema[$requestedLang] ?? $tfSeoSchema[$effectiveDefault] ?? ($baseSeoSettings['schema_markup'] ?? '');
            }

            return [
                'translated_fields_patch' => $translatedFieldsPatch,
                'legacy_override' => $legacyOverride,
            ];
        } catch (\Throwable $th) {
            log_message('error', 'Error in getServiceSeoForManageServiceResponse: ' . $th->getMessage());
            return [
                'translated_fields_patch' => [
                    'seo_title' => [],
                    'seo_description' => [],
                    'seo_keywords' => [],
                    'seo_schema_markup' => [],
                ],
                'legacy_override' => [],
            ];
        }
    }
}

if (!function_exists('getCategorySeoWithTranslations')) {
    /**
     * Get category SEO settings with translations
     * - Mirrors service/provider SEO translation pattern
     * - Uses translations table first, then falls back to category name
     *
     * @param int $categoryId Category ID
     * @param string $requestedLanguage Requested language code from header
     * @param string $defaultLanguage Default language code
     * @return array SEO settings with translated_* fields
     */
    function getCategorySeoWithTranslations(int $categoryId, string $requestedLanguage, string $defaultLanguage): array
    {
        try {
            // Fallback: fetch category name (from translations if available, otherwise base table)
            // Get category with translated name
            $categoryData = get_categories_with_translated_names(['id' => $categoryId]);

            if (empty($categoryData)) {
                return [
                    'translated_title' => '',
                    'translated_description' => '',
                    'translated_keywords' => '',
                    'translated_schema_markup' => ''
                ];
            }

            $category = $categoryData[0];
            $categoryName = $category['name'] ?? '';

            // Base SEO from categories table
            $seoModel = new \App\Models\Seo_model();
            $seoModel->setTableContext('categories');
            $baseSeoSettings = $seoModel->getSeoSettingsByReferenceId($categoryId, 'meta');

            // Translations for categories
            $seoTranslationModel = model('TranslatedCategorySeoSettings_model');
            $seoTranslations = $seoTranslationModel->getAllTranslationsForCategory($categoryId);

            // Choose requested -> default translation
            $requestedTranslation = null;
            foreach ($seoTranslations as $t) {
                if (($t['language_code'] ?? '') === $requestedLanguage) {
                    $requestedTranslation = $t;
                    break;
                }
            }
            $defaultTranslation = null;
            foreach ($seoTranslations as $t) {
                if (($t['language_code'] ?? '') === $defaultLanguage) {
                    $defaultTranslation = $t;
                    break;
                }
            }
            $selected = $requestedTranslation ?: $defaultTranslation;

            // Build translated_* fields
            if ($selected) {
                $translatedFields = [
                    'translated_title' => $selected['seo_title'] ?: '',
                    'translated_description' => $selected['seo_description'] ?: '',
                    'translated_keywords' => $selected['seo_keywords'] ?: '',
                    'translated_schema_markup' => $selected['seo_schema_markup'] ?: ''
                ];
            } else {
                // Fallback to base SEO then to category name
                $translatedFields = [
                    'translated_title' => $baseSeoSettings['title'] ?? $categoryName,
                    'translated_description' => $baseSeoSettings['description'] ?? '',
                    'translated_keywords' => $baseSeoSettings['keywords'] ?? '',
                    'translated_schema_markup' => $baseSeoSettings['schema_markup'] ?? ''
                ];
            }

            // Merge base settings with translated fields (base may be empty)
            $result = is_array($baseSeoSettings) ? $baseSeoSettings : [];
            $result = array_merge($result, $translatedFields);

            // Ensure minimal fallback when base SEO is empty
            if (empty($result) || ((string)($result['title'] ?? '') === '' && (string)($result['description'] ?? '') === '')) {
                $result['title'] = $categoryName;
                $result['description'] = '';
                $result['keywords'] = '';
                $result['schema_markup'] = '';
            }

            return $result;
        } catch (\Throwable $th) {
            log_message('error', 'Error in getCategorySeoWithTranslations: ' . $th->getMessage());
            return [
                'translated_title' => '',
                'translated_description' => '',
                'translated_keywords' => '',
                'translated_schema_markup' => ''
            ];
        }
    }
}

if (!function_exists('getBlogSeoWithTranslations')) {
    /**
     * Get blog SEO settings with translations
     * - Mirrors service/category SEO translation pattern
     * - Uses translations table first, then falls back to blog fields
     *
     * @param int $blogId Blog ID
     * @param string $requestedLanguage Requested language code from header
     * @param string $defaultLanguage Default language code
     * @return array SEO settings with translated_* fields
     */
    function getBlogSeoWithTranslations(int $blogId, string $requestedLanguage, string $defaultLanguage): array
    {
        try {
            // Fallback: fetch blog title and description
            $blogRows = fetch_details('blogs', ['id' => $blogId], ['title', 'description']);
            if (empty($blogRows)) {
                return [
                    'translated_title' => '',
                    'translated_description' => '',
                    'translated_keywords' => '',
                    'translated_schema_markup' => ''
                ];
            }

            $blog = $blogRows[0];

            // Base SEO from blogs_seo_settings table
            $seoModel = new \App\Models\Seo_model();
            $seoModel->setTableContext('blogs');
            $baseSeoSettings = $seoModel->getSeoSettingsByReferenceId($blogId, 'meta');

            // Translations for blogs
            $seoTranslationModel = model('TranslatedBlogSeoSettings_model');
            $seoTranslations = $seoTranslationModel->getAllTranslationsForBlog($blogId);

            // Choose requested -> default translation
            $requestedTranslation = null;
            foreach ($seoTranslations as $t) {
                if (($t['language_code'] ?? '') === $requestedLanguage) {
                    $requestedTranslation = $t;
                    break;
                }
            }
            $defaultTranslation = null;
            foreach ($seoTranslations as $t) {
                if (($t['language_code'] ?? '') === $defaultLanguage) {
                    $defaultTranslation = $t;
                    break;
                }
            }
            $selected = $requestedTranslation ?: $defaultTranslation;

            // Build translated_* fields
            if ($selected) {
                $translatedFields = [
                    'translated_title' => $selected['seo_title'] ?: '',
                    'translated_description' => $selected['seo_description'] ?: '',
                    'translated_keywords' => $selected['seo_keywords'] ?: '',
                    'translated_schema_markup' => $selected['seo_schema_markup'] ?: ''
                ];
            } else {
                // Fallback to base SEO then to blog fields
                $translatedFields = [
                    'translated_title' => $baseSeoSettings['title'] ?? ($blog['title'] ?? ''),
                    'translated_description' => $baseSeoSettings['description'] ?? (stripHtmlTags($blog['description'] ?? '') ?? ''),
                    'translated_keywords' => $baseSeoSettings['keywords'] ?? '',
                    'translated_schema_markup' => $baseSeoSettings['schema_markup'] ?? ''
                ];
            }

            // Merge base settings with translated fields (base may be empty)
            $result = is_array($baseSeoSettings) ? $baseSeoSettings : [];
            $result = array_merge($result, $translatedFields);

            // Ensure minimal fallback when base SEO is empty
            if (empty($result) || ((string)($result['title'] ?? '') === '' && (string)($result['description'] ?? '') === '')) {
                $result['title'] = $blog['title'] ?? '';
                $result['description'] = stripHtmlTags($blog['description'] ?? '');
                $result['keywords'] = '';
                $result['schema_markup'] = '';
            }

            return $result;
        } catch (\Throwable $th) {
            log_message('error', 'Error in getBlogSeoWithTranslations: ' . $th->getMessage());
            return [
                'translated_title' => '',
                'translated_description' => '',
                'translated_keywords' => '',
                'translated_schema_markup' => ''
            ];
        }
    }
}

if (!function_exists('getGeneralSeoWithTranslations')) {
    /**
     * Get general SEO settings with translations for a specific page
     * - Uses translations table first, then falls back to base SEO settings
     * 
     * @param string $page Page identifier (e.g., 'home', 'about-us', etc.)
     * @param string $requestedLanguage Requested language code from header
     * @param string $defaultLanguage Default language code
     * @return array SEO settings with translated_* fields
     */
    function getGeneralSeoWithTranslations(string $page, string $requestedLanguage, string $defaultLanguage): array
    {
        try {
            // Get base SEO settings by page
            $seoModel = new \App\Models\Seo_model();
            $seoModel->setTableContext('general');
            $baseSeoSettings = $seoModel->getSeoSettingsByPage($page, 'full');

            // If no base SEO settings exist, return empty
            if (!$baseSeoSettings || empty($baseSeoSettings)) {
                return [
                    'translated_title' => '',
                    'translated_description' => '',
                    'translated_keywords' => '',
                    'translated_schema_markup' => ''
                ];
            }

            // Get seo_id from base settings
            $seoId = $baseSeoSettings['id'] ?? null;
            if (!$seoId) {
                // If no ID, return base settings as-is
                return array_merge($baseSeoSettings, [
                    'translated_title' => $baseSeoSettings['title'] ?? '',
                    'translated_description' => $baseSeoSettings['description'] ?? '',
                    'translated_keywords' => $baseSeoSettings['keywords'] ?? '',
                    'translated_schema_markup' => $baseSeoSettings['schema_markup'] ?? ''
                ]);
            }

            // Get translations for this SEO setting
            $seoTranslationModel = model('TranslatedGeneralSeoSettings_model');
            $seoTranslations = $seoTranslationModel->getAllTranslationsForSeo($seoId);

            // Choose requested -> default translation
            $requestedTranslation = null;
            foreach ($seoTranslations as $t) {
                if (($t['language_code'] ?? '') === $requestedLanguage) {
                    $requestedTranslation = $t;
                    break;
                }
            }
            $defaultTranslation = null;
            foreach ($seoTranslations as $t) {
                if (($t['language_code'] ?? '') === $defaultLanguage) {
                    $defaultTranslation = $t;
                    break;
                }
            }
            $selected = $requestedTranslation ?: $defaultTranslation;

            // Build translated_* fields
            if ($selected) {
                $translatedFields = [
                    'translated_title' => $selected['seo_title'] ?: '',
                    'translated_description' => $selected['seo_description'] ?: '',
                    'translated_keywords' => $selected['seo_keywords'] ?: '',
                    'translated_schema_markup' => $selected['seo_schema_markup'] ?: ''
                ];
            } else {
                // Fallback to base SEO settings
                $translatedFields = [
                    'translated_title' => $baseSeoSettings['title'] ?? '',
                    'translated_description' => $baseSeoSettings['description'] ?? '',
                    'translated_keywords' => $baseSeoSettings['keywords'] ?? '',
                    'translated_schema_markup' => $baseSeoSettings['schema_markup'] ?? ''
                ];
            }

            // Merge base settings with translated fields
            $result = is_array($baseSeoSettings) ? $baseSeoSettings : [];
            $result = array_merge($result, $translatedFields);

            return $result;
        } catch (\Throwable $th) {
            log_message('error', 'Error in getGeneralSeoWithTranslations: ' . $th->getMessage());
            return [
                'translated_title' => '',
                'translated_description' => '',
                'translated_keywords' => '',
                'translated_schema_markup' => ''
            ];
        }
    }
}
