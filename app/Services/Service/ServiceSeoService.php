<?php

namespace App\Services\Service;

use App\Models\Seo_model;
use App\Services\utility\FileService;
use CodeIgniter\HTTP\IncomingRequest;
use Exception;

/**
 * Owns all SEO persistence logic for services.
 *
 * Extracted from Admin\Services private helpers (saveServiceSeoSettings,
 * processSeoTranslations, cleanupSeoTranslations, restructureTranslatedFieldsForSeoModel,
 * extractSeoDataFromPost, parseKeywords, saveBulkServiceSeoSettings).
 */
class ServiceSeoService
{
    private Seo_model   $seoModel;
    private FileService $fileService;

    public function __construct()
    {
        $this->seoModel    = new Seo_model();
        $this->fileService = new FileService();
    }

    /**
     * Save SEO settings from a form submission (create or update flow).
     * Equivalent to the old private saveServiceSeoSettings().
     */
    public function saveForService(int $serviceId, IncomingRequest $request): void
    {
        try {
            $defaultLanguage = get_default_language();
            $allPostData     = $request->getPost();
            $translatedFields = $request->getPost('translated_fields');

            if (is_string($translatedFields)) {
                $translatedFields = json_decode($translatedFields, true);
            }

            if (empty($translatedFields)) {
                $translatedFields = $this->extractSeoDataFromPost($allPostData);
            }

            $defaultSeoTitle       = '';
            $defaultSeoDescription = '';
            $defaultSeoKeywords    = '';
            $defaultSeoSchema      = '';

            if (!empty($translatedFields['seo_title'][$defaultLanguage])) {
                $defaultSeoTitle = trim($translatedFields['seo_title'][$defaultLanguage]);
            }
            if (!empty($translatedFields['seo_description'][$defaultLanguage])) {
                $defaultSeoDescription = trim($translatedFields['seo_description'][$defaultLanguage]);
            }
            if (!empty($translatedFields['seo_keywords'][$defaultLanguage])) {
                $keywordsData = $translatedFields['seo_keywords'][$defaultLanguage];
                if (is_array($keywordsData)) {
                    $defaultSeoKeywords = (count($keywordsData) === 1 && is_string($keywordsData[0]))
                        ? $keywordsData[0]
                        : implode(',', $keywordsData);
                } else {
                    $defaultSeoKeywords = $keywordsData;
                }
            }
            if (!empty($translatedFields['seo_schema_markup'][$defaultLanguage])) {
                $defaultSeoSchema = trim($translatedFields['seo_schema_markup'][$defaultLanguage]);
            }

            $keywords = $defaultSeoKeywords ? self::parseKeywords($defaultSeoKeywords) : '';

            $seoData = [
                'title'         => $defaultSeoTitle,
                'description'   => $defaultSeoDescription,
                'keywords'      => $keywords,
                'schema_markup' => $defaultSeoSchema,
                'service_id'    => $serviceId,
            ];

            $hasSeoData      = array_filter($seoData, fn($v) => !empty($v) && $v !== $serviceId);
            $allFieldsCleared = empty($seoData['title']) && empty($seoData['description'])
                && empty($seoData['keywords']) && empty($seoData['schema_markup']);

            $seoImage = $request->getFile('meta_image');
            $hasImage = $seoImage && $seoImage->isValid();

            $this->seoModel->setTableContext('services');
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($serviceId);

            $newSeoData = $seoData;
            if ($hasImage) {
                $uploadResult = $this->fileService->upload($seoImage, 'service_seo_settings');
                if ($uploadResult['error']) {
                    throw new Exception('SEO image upload failed: ' . $uploadResult['message']);
                }
                $newSeoData['image'] = $uploadResult['path'];
            } else {
                $newSeoData['image'] = $existingSettings['image'] ?? '';
            }

            if (!$existingSettings) {
                if ($hasSeoData || $hasImage) {
                    $result = $this->seoModel->createSeoSettings($newSeoData);
                    if (!empty($result['error'])) {
                        $errors = $result['validation_errors'] ?? [];
                        throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
                    }
                    $this->processSeoTranslations($serviceId, $translatedFields);
                }
                return;
            }

            if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
                $result = $this->seoModel->delete($existingSettings['id']);
                if ($result && !empty($existingSettings['image'])) {
                    $this->fileService->delete('service_seo_settings', $existingSettings['image']);
                }
                $this->cleanupSeoTranslations($serviceId);
                return;
            }

            $emptyDefaults = [
                'title'        => '',
                'description'  => '',
                'keywords'     => '',
                'schema_markup' => '',
                'image'        => $existingSettings['image'] ?? '',
            ];
            foreach ($emptyDefaults as $key => $defaultVal) {
                if (!array_key_exists($key, $newSeoData) || empty($newSeoData[$key])) {
                    $newSeoData[$key] = $defaultVal;
                }
            }

            $settingsChanged = false;
            foreach ($newSeoData as $key => $value) {
                if (($existingSettings[$key] ?? '') !== ($value ?? '')) {
                    $settingsChanged = true;
                    break;
                }
            }
            if (!$settingsChanged && $hasImage) {
                $settingsChanged = true;
            }

            if (!$settingsChanged) {
                $this->processSeoTranslations($serviceId, $translatedFields);
                return;
            }

            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
            if (!empty($result['error'])) {
                $errors = $result['validation_errors'] ?? [];
                throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
            }

            $this->processSeoTranslations($serviceId, $translatedFields);
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> ServiceSeoService::saveForService()');
            throw $th;
        }
    }

    /**
     * Delete all SEO data for a service (used in delete_service flow).
     */
    public function cleanupForService(int $serviceId): void
    {
        $this->cleanupSeoTranslations($serviceId);
    }

    /**
     * Save SEO settings from bulk import row data.
     * Equivalent to the old private saveBulkServiceSeoSettings().
     *
     * @param array $seoTranslations Language-keyed SEO data from extractSeoTranslationsFromRow()
     * @param array $languages       All languages from the DB
     */
    public function saveForBulk(int $serviceId, array $seoTranslations, array $languages): bool
    {
        try {
            $defaultLanguage = '';
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            if (empty($defaultLanguage)) {
                log_message('error', "No default language found for saving SEO settings for service {$serviceId}");
                return false;
            }

            $defaultSeoTitle       = trim($seoTranslations[$defaultLanguage]['seo_title'] ?? '');
            $defaultSeoDescription = trim($seoTranslations[$defaultLanguage]['seo_description'] ?? '');
            $defaultSeoKeywords    = trim($seoTranslations[$defaultLanguage]['seo_keywords'] ?? '');
            $defaultSeoSchema      = trim($seoTranslations[$defaultLanguage]['seo_schema_markup'] ?? '');

            $hasSeoData = !empty($defaultSeoTitle) || !empty($defaultSeoDescription)
                || !empty($defaultSeoKeywords) || !empty($defaultSeoSchema);

            if ($hasSeoData) {
                $this->seoModel->setTableContext('services');
                $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($serviceId);

                $seoData = [
                    'service_id'   => $serviceId,
                    'title'        => $defaultSeoTitle,
                    'description'  => $defaultSeoDescription,
                    'keywords'     => $defaultSeoKeywords,
                    'schema_markup' => $defaultSeoSchema,
                    'image'        => '',
                ];

                if ($existingSettings) {
                    $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $seoData);
                    if (!empty($result['error'])) {
                        log_message('error', "Failed to update SEO settings for service {$serviceId}: " . $result['message']);
                        return false;
                    }
                } else {
                    $result = $this->seoModel->createSeoSettings($seoData);
                    if (!empty($result['error'])) {
                        log_message('error', "Failed to create SEO settings for service {$serviceId}: " . $result['message']);
                        return false;
                    }
                }
            }

            $seoFields        = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];
            $restructuredSeoData = [];
            foreach ($seoFields as $field) {
                $restructuredSeoData[$field] = [];
                foreach ($seoTranslations as $languageCode => $fields) {
                    $restructuredSeoData[$field][$languageCode] = $fields[$field] ?? '';
                }
            }

            $this->processSeoTranslations($serviceId, $restructuredSeoData);
            return true;
        } catch (\Exception $e) {
            log_message('error', 'Exception in ServiceSeoService::saveForBulk: ' . $e->getMessage());
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function processSeoTranslations(int $serviceId, ?array $translatedFields): void
    {
        try {
            if (empty($translatedFields) || !is_array($translatedFields)) {
                return;
            }
            $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
            $restructuredData    = $this->restructureTranslatedFieldsForSeoModel($translatedFields);
            $result              = $seoTranslationModel->processSeoTranslations($serviceId, $restructuredData);
            if (!$result['success']) {
                throw new Exception('SEO Translation processing failed: ' . json_encode($result['errors']));
            }
        } catch (\Exception $e) {
            throw new Exception('Exception in processSeoTranslations for service ' . $serviceId . ': ' . $e->getMessage());
        }
    }

    private function cleanupSeoTranslations(int $serviceId): void
    {
        try {
            $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
            $seoTranslationModel->deleteServiceSeoTranslations($serviceId);
        } catch (\Exception $e) {
            throw new Exception('Exception in cleanupSeoTranslations for service ' . $serviceId . ': ' . $e->getMessage());
        }
    }

    private function restructureTranslatedFieldsForSeoModel(array $translatedFields): array
    {
        $restructured = [];
        $seoFields    = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        $languages = [];
        foreach ($seoFields as $field) {
            if (isset($translatedFields[$field]) && is_array($translatedFields[$field])) {
                $languages = array_merge($languages, array_keys($translatedFields[$field]));
            }
        }
        $languages = array_unique($languages);

        foreach ($languages as $languageCode) {
            $restructured[$languageCode] = [];
            foreach ($seoFields as $field) {
                if (!empty($translatedFields[$field][$languageCode])) {
                    $value = $translatedFields[$field][$languageCode] ?? '';
                    $restructured[$languageCode][$field] = ($field === 'seo_keywords')
                        ? self::parseKeywords($value)
                        : $value;
                }
            }
            if (implode('', $restructured[$languageCode]) === '') {
                unset($restructured[$languageCode]);
            }
        }

        return $restructured;
    }

    private function extractSeoDataFromPost(array $postData): array
    {
        $seoData     = [];
        $seoFields   = [
            'meta_title'       => 'seo_title',
            'meta_description' => 'seo_description',
            'meta_keywords'    => 'seo_keywords',
            'schema_markup'    => 'seo_schema_markup',
        ];

        foreach ($seoFields as $formField => $seoField) {
            if (!isset($postData[$formField]) || !is_array($postData[$formField])) {
                continue;
            }
            foreach ($postData[$formField] as $languageCode => $value) {
                if (empty($languageCode) || $languageCode === '0') {
                    continue;
                }
                $seoData[$seoField][$languageCode] = ($seoField === 'seo_keywords')
                    ? self::parseKeywords($value)
                    : trim($value);
            }
        }

        return $seoData;
    }

    /**
     * Validate that default language SEO is filled when any non-default language has SEO data.
     * Returns an array of error strings (empty = valid).
     */
    public static function validateDefaultLanguageSeo(array $postData, string $defaultLanguage): array
    {
        $seoFormFields = ['meta_title', 'meta_description', 'meta_keywords', 'schema_markup'];

        $hasNonDefaultSeo = false;
        foreach ($seoFormFields as $field) {
            if (!isset($postData[$field]) || !is_array($postData[$field])) {
                continue;
            }
            foreach ($postData[$field] as $lang => $value) {
                if ($lang === $defaultLanguage) {
                    continue;
                }
                $val = is_array($value) ? implode('', $value) : (string) $value;
                if (!empty(trim($val))) {
                    $hasNonDefaultSeo = true;
                    break 2;
                }
            }
        }

        if (!$hasNonDefaultSeo) {
            return [];
        }

        foreach ($seoFormFields as $field) {
            if (!isset($postData[$field]) || !is_array($postData[$field])) {
                continue;
            }
            $value = $postData[$field][$defaultLanguage] ?? null;
            $val   = is_array($value) ? implode('', $value) : (string) ($value ?? '');
            if (!empty(trim($val))) {
                return [];
            }
        }

        return [labels('seo_default_language_required', 'SEO details for the default language are required when adding SEO for other languages')];
    }

    /**
     * Parse keywords from Tagify JSON, plain array, or comma-separated string.
     * Public so ServiceTranslationHelper can call it without circular dependency.
     */
    public static function parseKeywords(mixed $input): string
    {
        if (empty($input)) {
            return '';
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if ($decoded !== null && is_array($decoded)) {
                $tags = array_map(
                    fn($item) => is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item),
                    $decoded
                );
                return implode(',', $tags);
            }
            return trim($input);
        }

        if (is_array($input)) {
            if (count($input) === 1 && is_string($input[0])) {
                $decoded = json_decode($input[0], true);
                if ($decoded !== null && is_array($decoded)) {
                    $tags = array_map(
                        fn($item) => is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item),
                        $decoded
                    );
                    return implode(',', $tags);
                }
            }
            $tags = array_map(
                fn($item) => is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item),
                $input
            );
            return implode(',', $tags);
        }

        return '';
    }
}
