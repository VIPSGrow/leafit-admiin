<?php

namespace App\Controllers\Partner;

use App\Models\Seo_model;
use Exception;

class SeoController extends Partner
{
    protected $seoModel;

    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
        $this->seoModel = new Seo_model();
    }

    public function index()
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }

        setPageInfo($this->data, labels('seo_settings', 'SEO Settings') . ' | ' . labels('provider_panel', 'Provider Panel'), 'seo');

        $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
        $this->data['languages'] = $languages;

        $this->seoModel->setTableContext('providers');
        $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($this->userId, 'full');

        $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');
        $seoTranslations = $seoTranslationModel->getAllTranslationsForPartner($this->userId);

        $mergedSeoSettings = $seo_settings;

        foreach ($languages as $language) {
            $languageCode = $language['code'];
            $isDefault = $language['is_default'] == 1;

            $seoTranslation = null;
            if (!empty($seoTranslations)) {
                foreach ($seoTranslations as $translation) {
                    if ($translation['language_code'] === $languageCode) {
                        $seoTranslation = $translation;
                        break;
                    }
                }
            }

            if ($seoTranslation) {
                $mergedSeoSettings['translated_' . $languageCode] = [
                    'title' => $seoTranslation['seo_title'] ?? '',
                    'description' => $seoTranslation['seo_description'] ?? '',
                    'keywords' => $seoTranslation['seo_keywords'] ?? '',
                    'schema_markup' => $seoTranslation['seo_schema_markup'] ?? ''
                ];
            } else {
                $mergedSeoSettings['translated_' . $languageCode] = [
                    'title' => $isDefault ? ($seo_settings['title'] ?? '') : '',
                    'description' => $isDefault ? ($seo_settings['description'] ?? '') : '',
                    'keywords' => $isDefault ? ($seo_settings['keywords'] ?? '') : '',
                    'schema_markup' => $isDefault ? ($seo_settings['schema_markup'] ?? '') : ''
                ];
            }
        }

        $this->data['partner_seo_settings'] = $mergedSeoSettings;
        $partner_details = !empty(fetch_details('partner_details', ['partner_id' => $this->userId])) ? fetch_details('partner_details', ['partner_id' => $this->userId])[0] : [];
        $this->data['partner_details'] = $partner_details;

        return view('backend/partner/template', $this->data);
    }

    public function update()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $this->saveSeoSettings($this->userId);
            $this->handleSeoTranslations();

            return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Seo.php - update()');
            return ErrorResponse($th->getMessage() ?: labels(SOMETHING_WENT_WRONG, "Something went wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function remove_seo_image()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $partnerId = $this->userId;

            if (!$partnerId) {
                return ErrorResponse(labels(PARTNER_ID_IS_REQUIRED, "Partner ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $this->seoModel->setTableContext('providers');
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

            if (!$existingSettings) {
                return ErrorResponse(labels(SEO_SETTINGS_NOT_FOUND_FOR_THIS_PARTNER, "SEO settings not found for this partner"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            if (empty($existingSettings['image'])) {
                return ErrorResponse(labels(NO_SEO_IMAGE_FOUND_TO_REMOVE, "No SEO image found to remove"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $imageToDelete = $existingSettings['image'];

            $updateData = [
                'title' => $existingSettings['title'] ?? '',
                'description' => $existingSettings['description'] ?? '',
                'keywords' => $existingSettings['keywords'] ?? '',
                'schema_markup' => $existingSettings['schema_markup'] ?? '',
                'image' => '',
                'partner_id' => $partnerId,
            ];

            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $updateData);

            if (!empty($result['error'])) {
                return ErrorResponse(labels($result['message'], $result['message']), true, [], [], 200, csrf_token(), csrf_hash());
            }

            if (!empty($imageToDelete)) {
                $disk = fetch_current_file_manager();
                delete_file_based_on_server('provider_seo_settings', $imageToDelete, $disk);
            }

            return successResponse(labels(SEO_IMAGE_REMOVED_SUCCESSFULLY, "SEO image removed successfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Seo.php - remove_seo_image()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something went wrong while removing SEO image"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    private function saveSeoSettings(int $partnerId): void
    {
        $defaultLanguage = get_default_language();
        $postData = $this->request->getPost();
        $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage);

        $defaultSeoTitle = '';
        $defaultSeoDescription = '';
        $defaultSeoKeywords = '';
        $defaultSeoSchema = '';

        if (!empty($translatedFields['seo_title'][$defaultLanguage])) {
            $defaultSeoTitle = trim($translatedFields['seo_title'][$defaultLanguage]);
        }

        if (!empty($translatedFields['seo_description'][$defaultLanguage])) {
            $defaultSeoDescription = trim($translatedFields['seo_description'][$defaultLanguage]);
        }

        if (!empty($translatedFields['seo_keywords'][$defaultLanguage])) {
            $keywordsData = $translatedFields['seo_keywords'][$defaultLanguage];
            if (is_array($keywordsData)) {
                if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                    $defaultSeoKeywords = $keywordsData[0];
                } else {
                    $defaultSeoKeywords = implode(',', $keywordsData);
                }
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

        $hasSeoData = array_filter($seoData, fn($v) => !empty($v) && $v !== $partnerId);
        $allFieldsCleared = empty($seoData['title']) &&
            empty($seoData['description']) &&
            empty($seoData['keywords']) &&
            empty($seoData['schema_markup']);

        $seoImage = $this->request->getFile('meta_image');
        $hasImage = $seoImage && $seoImage->isValid();

        $this->seoModel->setTableContext('providers');
        $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

        $newSeoData = $seoData;
        if ($hasImage) {
            try {
                $uploadResult = upload_file(
                    $seoImage,
                    'public/uploads/seo_settings/provider_seo_settings/',
                    labels(FAILED_TO_UPLOAD_SEO_IMAGE, "Failed to upload SEO image"),
                    'seo_settings'
                );
                if (!empty($uploadResult['error'])) {
                    throw new Exception($uploadResult['message']);
                }
                $newSeoData['image'] = $uploadResult['file_name'];
            } catch (\Throwable $t) {
                throw new Exception(labels(SEO_IMAGE_UPLOAD_FAILED, "SEO image upload failed: " . $t->getMessage()));
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

        if ($existingSettings && $allFieldsCleared && !$hasImage && !empty($existingSettings['image'])) {
            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
            $result = $this->seoModel->deleteSeoSettings($existingSettings['id']);
            if (!empty($result['error'])) {
                throw new Exception(labels(FAILED_TO_DELETE_SEO_SETTINGS, "Failed to delete SEO settings: " . $result['message']));
            }
            $this->cleanupSeoTranslations($partnerId);
            return;
        }

        $emptyDefaults = [
            'title' => '',
            'description' => '',
            'keywords' => '',
            'schema_markup' => '',
            'image' => $existingSettings['image'] ?? '',
        ];

        foreach ($emptyDefaults as $key => $defaultVal) {
            if (!array_key_exists($key, $newSeoData) || empty($newSeoData[$key])) {
                $newSeoData[$key] = $defaultVal;
            }
        }

        $settingsChanged = false;
        foreach ($newSeoData as $key => $value) {
            $existingValue = $existingSettings[$key] ?? '';
            $newValue = $value ?? '';
            if ($existingValue !== $newValue) {
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

    private function handleSeoTranslations(): void
    {
        try {
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
            if (empty($languages)) {
                return;
            }

            $postData = $this->request->getPost();
            $translatedFields = $this->transformFormDataToTranslatedFields($postData, get_default_language());

            if (!empty($translatedFields) && is_array($translatedFields)) {
                $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($this->userId, $translatedFields);

                if (!$seoTranslationResult['success']) {
                    log_message('error', 'SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error handling SEO translations: ' . $e->getMessage());
        }
    }

    private function transformFormDataToTranslatedFields(array $postData, string $defaultLanguage): array
    {
        $translatedFields = [
            'seo_title' => [],
            'seo_description' => [],
            'seo_keywords' => [],
            'seo_schema_markup' => [],
        ];

        if (isset($postData['meta_title']) && is_array($postData['meta_title'])) {
            $translatedFields['seo_title'] = $postData['meta_title'] ?? [];
            $translatedFields['seo_description'] = $postData['meta_description'] ?? [];

            $metaKeywords = $postData['meta_keywords'] ?? [];
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
            $translatedFields['seo_keywords'] = $processedKeywords;
            $translatedFields['seo_schema_markup'] = $postData['schema_markup'] ?? [];

            return $translatedFields;
        }

        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            $seoTitleField = 'meta_title[' . $languageCode . ']';
            if (array_key_exists($seoTitleField, $postData)) {
                $translatedFields['seo_title'][$languageCode] = trim((string)$postData[$seoTitleField]);
            }

            $seoDescriptionField = 'meta_description[' . $languageCode . ']';
            if (array_key_exists($seoDescriptionField, $postData)) {
                $translatedFields['seo_description'][$languageCode] = trim((string)$postData[$seoDescriptionField]);
            }

            $seoKeywordsField = 'meta_keywords[' . $languageCode . ']';
            if (array_key_exists($seoKeywordsField, $postData)) {
                $seoKeywordsValue = $postData[$seoKeywordsField];
                if (is_array($seoKeywordsValue)) {
                    if (count($seoKeywordsValue) === 1 && is_string($seoKeywordsValue[0])) {
                        $translatedFields['seo_keywords'][$languageCode] = $seoKeywordsValue[0];
                    } else {
                        $translatedFields['seo_keywords'][$languageCode] = implode(',', $seoKeywordsValue);
                    }
                } else {
                    $translatedFields['seo_keywords'][$languageCode] = trim((string)$seoKeywordsValue);
                }
            }

            $seoSchemaField = 'schema_markup[' . $languageCode . ']';
            if (array_key_exists($seoSchemaField, $postData)) {
                $translatedFields['seo_schema_markup'][$languageCode] = trim((string)$postData[$seoSchemaField]);
            }
        }

        return $translatedFields;
    }

    private function parseKeywords($input): string
    {
        if (empty($input)) {
            return '';
        }

        if (is_string($input)) {
            if (json_decode($input, true) !== null) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            return trim($input);
        }

        if (is_array($input)) {
            if (count($input) === 1 && is_string($input[0]) && json_decode($input[0], true) !== null) {
                $decoded = json_decode($input[0], true);
                if (is_array($decoded)) {
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            $tags = array_map(function ($item) {
                return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
            }, $input);
            return implode(',', $tags);
        }

        return '';
    }

    private function processSeoTranslations(int $partnerId, ?array $translatedFields = null): void
    {
        try {
            if ($translatedFields === null) {
                $translatedFields = $this->request->getPost('translated_fields');
                if (is_string($translatedFields)) {
                    $translatedFields = json_decode($translatedFields, true);
                }
            }

            if (!empty($translatedFields) && is_array($translatedFields)) {
                $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($partnerId, $restructuredData);

                if (!$seoTranslationResult['success']) {
                    throw new Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            throw new Exception('Exception in processSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    private function cleanupSeoTranslations(int $partnerId): void
    {
        try {
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');
            $seoTranslationModel->deletePartnerSeoTranslations($partnerId);
        } catch (\Exception $e) {
            throw new Exception('Exception in cleanupSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

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
                    $restructured[$languageCode][$field] = !empty($value) ? $this->parseKeywords($value) : '';
                } else {
                    $restructured[$languageCode][$field] = $value !== null ? $value : '';
                }
            }
        }

        return $restructured;
    }
}
