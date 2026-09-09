<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Seo_model;
use Exception;

/**
 * Provider SEO Settings API
 *
 * Dedicated endpoints for provider SEO management, decoupled from the
 * AuthApiController register flow. The register endpoint still accepts SEO
 * fields for backward compatibility and will be cleaned up later.
 *
 *  - get_seo_settings: returns base SEO record + per-language translations
 *    with the default-language fallback used elsewhere in the system.
 *  - manage_seo_settings: creates/updates base SEO settings and per-language
 *    translations (and deletes the row when all fields are cleared).
 */
class SeoSettingsApiController extends BaseController
{
    private const SEO_FIELDS = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

    protected $request;
    protected Seo_model $seoModel;
    protected array $user_details = [];

    public function __construct()
    {
        helper('api');
        helper('function');
        helper('ResponceServices');

        $this->request  = \Config\Services::request();
        $this->seoModel = new Seo_model();
        $this->seoModel->setTableContext('providers');

        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode([
                'error'   => true,
                'message' => $token['message'],
                'status'  => 401,
            ]));
            die();
        }
    }

    /**
     * Fetch provider SEO settings + per-language translations.
     *
     * Per-language fallback (matches partner panel SeoController):
     *   - Use translated_partner_seo_settings row when present.
     *   - Otherwise fall back to base partners_seo_settings values for the
     *     default language, and empty strings for non-default languages.
     */
    public function get_seo_settings()
    {
        try {
            $partnerId = (int) ($this->user_details['id'] ?? 0);
            if ($partnerId <= 0) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'),
                ]);
            }

            $baseSeo = $this->seoModel->getSeoSettingsByReferenceId($partnerId, 'full') ?: [];
            $seo = [
                'title'         => $baseSeo['title'] ?? '',
                'description'   => $baseSeo['description'] ?? '',
                'keywords'      => $baseSeo['keywords'] ?? '',
                'schema_markup' => $baseSeo['schema_markup'] ?? '',
                'image'         => $baseSeo['image'] ?? '',
            ];

            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], '', '0', 'id', 'ASC');
            $rows = model('TranslatedPartnerSeoSettings_model')->getAllTranslationsForPartner($partnerId);
            $byLang = array_column($rows, null, 'language_code');

            $translations = [];
            foreach ($languages as $language) {
                $code = $language['code'];
                $row  = $byLang[$code] ?? null;
                if ($row) {
                    $translations[$code] = [
                        'seo_title'         => $row['seo_title'] ?? '',
                        'seo_description'   => $row['seo_description'] ?? '',
                        'seo_keywords'      => $row['seo_keywords'] ?? '',
                        'seo_schema_markup' => $row['seo_schema_markup'] ?? '',
                    ];
                    continue;
                }
                $isDefault = (int) $language['is_default'] === 1;
                $translations[$code] = [
                    'seo_title'         => $isDefault ? $seo['title'] : '',
                    'seo_description'   => $isDefault ? $seo['description'] : '',
                    'seo_keywords'      => $isDefault ? $seo['keywords'] : '',
                    'seo_schema_markup' => $isDefault ? $seo['schema_markup'] : '',
                ];
            }

            return $this->response->setJSON([
                'error'   => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                'data'    => [
                    'seo'          => $seo,
                    'translations' => $translations,
                ],
            ]);
        } catch (\Throwable $th) {
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . ' Issue => ' . $th, date('Y-m-d H:i:s') . '--> Apis/Provider/SeoSettingsApiController - get_seo_settings()');
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /**
     * Create or update provider SEO settings (and translations).
     *
     * POST payload:
     *   - translations: JSON keyed by language code, each value an object
     *     with seo_title / seo_description / seo_keywords /
     *     seo_schema_markup. Default-language entry populates base
     *     partners_seo_settings; every entry populates
     *     translated_partner_seo_settings.
     *   - meta_image (file, optional): OG image for the base record.
     *
     * If the default-language fields are all empty AND no image is present
     * (and none stored), the base row + every translation row are deleted.
     */
    public function manage_seo_settings()
    {
        try {
            $partnerId = (int) ($this->user_details['id'] ?? 0);
            if ($partnerId <= 0) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'),
                ]);
            }

            $translations    = $this->parseTranslations($this->request->getPost('translations'));
            $defaultLanguage = get_default_language();
            $default         = $translations[$defaultLanguage] ?? [];

            $base = [
                'title'         => trim((string) ($default['seo_title'] ?? '')),
                'description'   => trim((string) ($default['seo_description'] ?? '')),
                'keywords'      => $this->normalizeKeywords($default['seo_keywords'] ?? ''),
                'schema_markup' => trim((string) ($default['seo_schema_markup'] ?? '')),
                'partner_id'    => $partnerId,
            ];
            $defaultEmpty = $base['title'] === '' && $base['description'] === ''
                && $base['keywords'] === '' && $base['schema_markup'] === '';

            $metaImage = $this->request->getFile('meta_image');
            $hasImage  = $metaImage && $metaImage->isValid();

            $existing = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

            $fileService = service('fileService');

            // Image: upload new one, otherwise keep stored value.
            if ($hasImage) {
                $upload = $fileService->upload($metaImage, 'provider_seo_settings');
                if (!empty($upload['error'])) {
                    throw new Exception(labels(SEO_IMAGE_UPLOAD_FAILED, 'SEO image upload failed') . ': ' . ($upload['message'] ?? labels(FAILED_TO_UPLOAD_SEO_IMAGE, 'Failed to upload SEO image')));
                }
                $base['image'] = $upload['path'];
            } else {
                $base['image'] = $existing['image'] ?? '';
            }

            // Full clear: delete base row + translations + stored image.
            if ($existing && $defaultEmpty && !$hasImage) {
                if ($this->seoModel->delete($existing['id']) && !empty($existing['image'])) {
                    $fileService->delete('provider_seo_settings', $existing['image']);
                }
                model('TranslatedPartnerSeoSettings_model')->deletePartnerSeoTranslations($partnerId);
                return $this->success();
            }

            // Create base row when first save has any content.
            if (!$existing) {
                if (!$defaultEmpty || $hasImage) {
                    $this->assertOk($this->seoModel->createSeoSettings($base));
                }
                $this->upsertTranslations($partnerId, $translations);
                return $this->success();
            }

            // Update base row only when something actually changed.
            $changed = $hasImage;
            foreach ($base as $k => $v) {
                if (($existing[$k] ?? '') !== ($v ?? '')) {
                    $changed = true;
                    break;
                }
            }
            if ($changed) {
                $this->assertOk($this->seoModel->updateSeoSettings($existing['id'], $base));
            }

            $this->upsertTranslations($partnerId, $translations);
            return $this->success();
        } catch (\Throwable $th) {
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . ' Issue => ' . $th, date('Y-m-d H:i:s') . '--> Apis/Provider/SeoSettingsApiController - manage_seo_settings()');
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    private function success()
    {
        return $this->response->setJSON([
            'error'   => false,
            'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'),
        ]);
    }

    /**
     * Decode the `translations` POST field and keep only known SEO fields
     * per language.
     */
    private function parseTranslations(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $lang => $entry) {
            if (!is_string($lang) || !is_array($entry)) {
                continue;
            }
            $row = array_intersect_key($entry, array_flip(self::SEO_FIELDS));
            if (!empty($row)) {
                $out[$lang] = $row;
            }
        }
        return $out;
    }

    /**
     * Normalize keywords to a comma-separated string. Accepts either a
     * string (returned trimmed) or a flat array of strings.
     */
    private function normalizeKeywords(mixed $input): string
    {
        if (is_array($input)) {
            return implode(',', array_map(fn($v) => trim((string) $v), $input));
        }
        return trim((string) $input);
    }

    private function upsertTranslations(int $partnerId, array $translations): void
    {
        if (empty($translations)) {
            return;
        }

        $payload = [];
        foreach ($translations as $lang => $entry) {
            $row = [];
            foreach (self::SEO_FIELDS as $field) {
                if (!array_key_exists($field, $entry)) {
                    continue;
                }
                $row[$field] = $field === 'seo_keywords'
                    ? $this->normalizeKeywords($entry[$field])
                    : trim((string) $entry[$field]);
            }
            if (!empty($row)) {
                $payload[$lang] = $row;
            }
        }
        if (empty($payload)) {
            return;
        }

        $result = model('TranslatedPartnerSeoSettings_model')->processSeoTranslations($partnerId, $payload);
        if (empty($result['success'])) {
            throw new Exception('SEO translation processing failed: ' . json_encode($result['errors'] ?? []));
        }
    }

    private function assertOk(array $result): void
    {
        if (empty($result['error'])) {
            return;
        }
        $errors = $result['validation_errors'] ?? [];
        throw new Exception(($result['message'] ?? '') . (!empty($errors) ? ': ' . json_encode($errors) : ''));
    }
}
