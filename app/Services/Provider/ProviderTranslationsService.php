<?php

namespace App\Services\Provider;

use App\Models\Language_model;
use App\Models\Seo_model;
use App\Models\TranslatedPartnerSeoSettings_model;
use App\Services\utility\FileService;
use CodeIgniter\HTTP\IncomingRequest;
use Exception;

/**
 * Shared translation + SEO plumbing for ProviderCreateService +
 * ProviderUpdateService.
 *
 * Owns:
 *  - Form-data → translated_fields normalization (new object form + legacy
 *    `field[lang]` flat form).
 *  - SEO base row create/update/delete (delegates to Seo_model), including
 *    image upload/delete via FileService.
 *  - SEO translations sync via TranslatedPartnerSeoSettings_model.
 *
 * Phase 3 step 6 of the Provider refactor — finalizes the helper duplication
 * intentionally left during steps 1 + 2.
 */
class ProviderTranslationsService
{
    private Seo_model $seoModel;
    private TranslatedPartnerSeoSettings_model $translatedSeoSettings;
    private Language_model $languages;
    private FileService $fileService;

    public function __construct()
    {
        // function_helper owns labels() / log_message-friendly constants used below.
        helper('function');
        $this->seoModel              = new Seo_model();
        $this->translatedSeoSettings = new TranslatedPartnerSeoSettings_model();
        $this->languages             = new Language_model();
        $this->fileService           = service('fileService');
    }

    /**
     * Convert posted form data to the `translated_fields` structure
     * PartnerService + the SEO translation model expect.
     *
     * Supports both the new object form (`field[langCode]`) and the legacy
     * flat-key form (`"field[langCode]"`).
     */
    public function transformFormDataToTranslatedFields(array $postData, string $defaultLanguage): array
    {
        $translatedFields = [
            'username'          => [],
            'company_name'      => [],
            'about_provider'    => [],
            'long_description'  => [],
            'seo_title'         => [],
            'seo_description'   => [],
            'seo_keywords'      => [],
            'seo_schema_markup' => [],
        ];

        // New object form: $postData['company_name'][$langCode] etc.
        if (isset($postData['company_name']) && is_array($postData['company_name'])) {
            $translatedFields['username']         = $postData['username'] ?? [];
            $translatedFields['company_name']     = $postData['company_name'] ?? [];
            $translatedFields['about_provider']   = $postData['about_provider'] ?? [];
            $translatedFields['long_description'] = $postData['long_description'] ?? [];
            $translatedFields['seo_title']        = $postData['meta_title'] ?? [];
            $translatedFields['seo_description']  = $postData['meta_description'] ?? [];

            $metaKeywords      = $postData['meta_keywords'] ?? [];
            $processedKeywords = [];
            foreach ($metaKeywords as $langCode => $keywordsData) {
                if (is_array($keywordsData)) {
                    if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                        $processedKeywords[$langCode] = $keywordsData[0];
                    } else {
                        $processedKeywords[$langCode] = implode(',', $keywordsData);
                    }
                } else {
                    $processedKeywords[$langCode] = $keywordsData;
                }
            }
            $translatedFields['seo_keywords']      = $processedKeywords;
            $translatedFields['seo_schema_markup'] = $postData['schema_markup'] ?? [];

            return $translatedFields;
        }

        // Legacy flat form: $postData["field[langCode]"].
        $languages = $this->languages
            ->select(['id', 'language', 'code', 'is_default'])
            ->orderBy('id', 'ASC')
            ->findAll();

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            $usernameValue = $postData['username[' . $languageCode . ']'] ?? null;
            if (!empty($usernameValue)) {
                $translatedFields['username'][$languageCode] = trim($usernameValue);
            }

            $companyNameValue = $postData['company_name[' . $languageCode . ']'] ?? null;
            if (!empty($companyNameValue)) {
                $translatedFields['company_name'][$languageCode] = trim($companyNameValue);
            }

            $aboutValue = $postData['about_provider[' . $languageCode . ']'] ?? null;
            if (!empty($aboutValue)) {
                $translatedFields['about_provider'][$languageCode] = trim($aboutValue);
            }

            $descriptionValue = $postData['long_description[' . $languageCode . ']'] ?? null;
            if (!empty($descriptionValue)) {
                $translatedFields['long_description'][$languageCode] = trim($descriptionValue);
            }

            $seoTitleField = 'meta_title[' . $languageCode . ']';
            if (array_key_exists($seoTitleField, $postData)) {
                $translatedFields['seo_title'][$languageCode] = trim((string) $postData[$seoTitleField]);
            }

            $seoDescriptionField = 'meta_description[' . $languageCode . ']';
            if (array_key_exists($seoDescriptionField, $postData)) {
                $translatedFields['seo_description'][$languageCode] = trim((string) $postData[$seoDescriptionField]);
            }

            $seoKeywordsField = 'meta_keywords[' . $languageCode . ']';
            if (array_key_exists($seoKeywordsField, $postData)) {
                $seoKeywordsValue = $postData[$seoKeywordsField];
                if (is_array($seoKeywordsValue)) {
                    $translatedFields['seo_keywords'][$languageCode] = implode(',', $seoKeywordsValue);
                } else {
                    $translatedFields['seo_keywords'][$languageCode] = trim((string) $seoKeywordsValue);
                }
            }

            $seoSchemaField = 'schema_markup[' . $languageCode . ']';
            if (array_key_exists($seoSchemaField, $postData)) {
                $translatedFields['seo_schema_markup'][$languageCode] = trim((string) $postData[$seoSchemaField]);
            }
        }

        return $translatedFields;
    }

    /**
     * Persist base SEO settings + translations for a provider. Idempotent —
     * handles create / update / clear-all-fields-delete paths.
     */
    public function saveSeoSettings(IncomingRequest $request, int $partnerId): void
    {
        $defaultLanguage  = get_default_language();
        $postData         = $request->getPost();
        $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage);

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

        $keywords = $defaultSeoKeywords ? $this->parseKeywords($defaultSeoKeywords) : '';

        $seoData = [
            'title'         => $defaultSeoTitle,
            'description'   => $defaultSeoDescription,
            'keywords'      => $keywords,
            'schema_markup' => $defaultSeoSchema,
            'partner_id'    => $partnerId,
        ];

        $hasSeoData = array_filter($seoData, fn ($v) => !empty($v) && $v !== $partnerId);

        $allFieldsCleared = empty($seoData['title'])
            && empty($seoData['description'])
            && empty($seoData['keywords'])
            && empty($seoData['schema_markup']);

        $seoImage = $request->getFile('meta_image');
        $hasImage = $seoImage && $seoImage->isValid();

        $this->seoModel->setTableContext('providers');
        $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

        $newSeoData = $seoData;
        if ($hasImage) {
            try {
                $uploadResult        = $this->uploadFile($seoImage, 'provider_seo_settings');
                $newSeoData['image'] = $uploadResult['url'];
            } catch (\Throwable $t) {
                throw new Exception(labels(SEO_IMAGE_UPLOAD_FAILED, 'SEO image upload failed: ' . $t->getMessage()));
            }
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
            }

            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        // All SEO fields cleared, no image — drop the record + translations.
        if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
            $deleted = $this->seoModel->delete($existingSettings['id']);
            if ($deleted && !empty($existingSettings['image'])) {
                $this->fileService->delete('provider_seo_settings', $existingSettings['image']);
            }
            $this->cleanupSeoTranslations($partnerId);
            return;
        }

        // Force-clear removed SEO fields without nuking the image.
        $emptyDefaults = [
            'title'         => '',
            'description'   => '',
            'keywords'      => '',
            'schema_markup' => '',
            'image'         => $existingSettings['image'] ?? '',
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
        if (!$settingsChanged) {
            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
        if (!empty($result['error'])) {
            $errors = $result['validation_errors'] ?? [];
            throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
        }

        $this->processSeoTranslations($partnerId, $translatedFields);
    }

    /**
     * Sync per-language SEO translation rows for a provider.
     */
    public function processSeoTranslations(int $partnerId, array $translatedFields): void
    {
        try {
            if (!empty($translatedFields) && is_array($translatedFields)) {
                $restructured = $this->restructureTranslatedFieldsForSeoModel($translatedFields);
                $result       = $this->translatedSeoSettings->processSeoTranslations($partnerId, $restructured);

                if (!$result['success']) {
                    throw new Exception('SEO Translation processing failed: ' . json_encode($result['errors']));
                }
            }
        } catch (\Exception $e) {
            throw new Exception('Exception in processSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Delete all SEO translation rows for a provider.
     */
    public function cleanupSeoTranslations(int $partnerId): void
    {
        try {
            $this->translatedSeoSettings->deletePartnerSeoTranslations($partnerId);
        } catch (\Exception $e) {
            throw new Exception('Exception in cleanupSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Re-shape translated fields from [field => [lang => value]] to
     * [lang => [field => value]] for the SEO translation model.
     */
    private function restructureTranslatedFieldsForSeoModel(array $translatedFields): array
    {
        $restructured = [];

        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

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
                $value = $translatedFields[$field][$languageCode] ?? '';

                if ($field === 'seo_keywords') {
                    $restructured[$languageCode][$field] = !empty($value)
                        ? $this->parseKeywords($value)
                        : '';
                } else {
                    $restructured[$languageCode][$field] = $value !== null ? $value : '';
                }
            }
        }

        return $restructured;
    }

    /**
     * Normalize Tagify / comma-separated keyword input to a comma-separated string.
     */
    private function parseKeywords($input): string
    {
        if (empty($input)) {
            return '';
        }

        if (is_string($input)) {
            if (json_decode($input, true) !== null) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    $tags = array_map(
                        fn ($item) => is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item),
                        $decoded
                    );
                    return implode(',', $tags);
                }
            }
            return trim($input);
        }

        if (is_array($input)) {
            if (count($input) === 1 && is_string($input[0]) && json_decode($input[0], true) !== null) {
                $decoded = json_decode($input[0], true);
                if (is_array($decoded)) {
                    $tags = array_map(
                        fn ($item) => is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item),
                        $decoded
                    );
                    return implode(',', $tags);
                }
            }
            $tags = array_map(
                fn ($item) => is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item),
                $input
            );
            return implode(',', $tags);
        }

        return '';
    }

    /**
     * Thin FileService adapter for SEO image upload, kept private because
     * the SEO save flow is the only consumer here.
     */
    private function uploadFile($file, string $folder): array
    {
        if (!$file || !$file->isValid()) {
            return ['url' => '', 'disk' => ''];
        }

        $result = $this->fileService->upload($file, $folder);
        if (!empty($result['error'])) {
            throw new Exception($result['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }

        return [
            'url'  => $result['path'] ?? '',
            'disk' => $result['disk'] ?? '',
        ];
    }
}
