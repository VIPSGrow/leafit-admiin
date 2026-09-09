<?php

namespace App\Controllers\Apis;

use App\Controllers\BaseController;

/**
 * Unified page-settings API controller for both Customer and Provider platforms.
 *
 * Serves get_page_setting and get_custom_pages endpoints.
 * Placed alongside LanguageApiController under App\Controllers\Apis.
 */
class PageSettingsApiController extends BaseController
{
    protected $request;
    private const PAGE_MAPPING = [
        'about_us' => 'about_us',
        'contact_us' => 'contact_us',
        'customer_privacy_policy' => 'customer_privacy_policy',
        'customer_terms_conditions' => 'customer_terms_conditions',
        'handyman_privacy_policy' => 'handyman_privacy_policy',
        'handyman_terms_conditions' => 'handyman_terms_conditions',
        'privacy_policy' => 'privacy_policy',
        'terms_conditions' => 'terms_conditions',
    ];

    private const HANDYMAN_ONLY_PAGES = ['handyman_privacy_policy', 'handyman_terms_conditions'];

    public function __construct()
    {
        helper('api');
        helper('function');
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->response = \Config\Services::response();
    }

    /**
     * Get a single page setting (system page or custom page by slug).
     */
    public function get_page_setting()
    {
        try {
            $page = $this->request->getPost('page');

            if (empty($page)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('page_is_required', 'Page is required'),
                ]);
            }

            // If not a known system page, treat as custom page slug
            if (!isset(self::PAGE_MAPPING[$page])) {
                $customPageModel = new \App\Models\CustomPageModel();
                $requestedLanguage = get_current_language_from_request();
                $defaultLanguage = get_default_language();

                $pageData = $customPageModel->getBySlugWithTranslations($page, $requestedLanguage, $defaultLanguage);

                if (empty($pageData)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                    ]);
                }

                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                    'data' => [
                        'id' => $pageData['id'],
                        'title' => $pageData['translated_title'],
                        'slug' => $pageData['slug'],
                        'content' => $pageData['translated_content'],
                    ],
                ]);
            }

            $userType = strtolower((string) $this->request->getPost('user_type'));
            $isHandyman = strtolower($this->request->getHeaderLine('User-type')) === 'handyman' || str_contains($userType, 'handyman');

            if (in_array($page, self::HANDYMAN_ONLY_PAGES, true) && !$isHandyman) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                ]);
            }

            // If user_type indicates handyman, serve handyman legal variant for privacy_policy/terms_conditions.
            if ($isHandyman && isset(self::PAGE_MAPPING['handyman_' . $page])) {
                $page = 'handyman_' . $page;
            }

            $fieldName = self::PAGE_MAPPING[$page];

            // Fetch the setting from database (second param = true for JSON decode)
            $settingData = get_settings($fieldName, true);

            // Resolve to a single translated string
            $content = $this->resolveSettingContent($settingData, $fieldName);

            if (!empty($content)) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                    'data' => [$fieldName => $content],
                ]);
            }

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(DATA_NOT_FOUND, 'Data not found'),
            ]);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . ' Issue => ' . $th, date('Y-m-d H:i:s') . '--> app/Controllers/Apis/PageSettingsApiController.php - get_page_setting()');
            return $this->response->setJSON($response);
        }
    }

    /**
     * Get all active custom pages with translated titles.
     */
    public function get_custom_pages()
    {
        try {
            $customPageModel = new \App\Models\CustomPageModel();

            $requestedLanguage = get_current_language_from_request();
            $defaultLanguage = get_default_language();

            $pages = $customPageModel->getActivePagesWithTranslations($requestedLanguage, $defaultLanguage);

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                'data' => $pages,
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> PageSettingsApiController - get_custom_pages()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /**
     * Resolve a setting value to a single translated string.
     *
     * Handles all stored formats:
     *  - Plain string (old format) → returned as-is
     *  - Multilingual array {"en": "...", "hi": "..."} → fallback resolution
     *  - Nested wrapper {"field_name": {"en": "..."}} or {"field_name": "..."} → unwrap then resolve
     *
     * Fallback chain: requested lang → default lang → English → first available.
     */
    private function resolveSettingContent($settingData, string $fieldName): string
    {
        // Plain string (old single-language format)
        if (is_string($settingData)) {
            return $settingData;
        }

        if (!is_array($settingData) || empty($settingData)) {
            return '';
        }

        // Unwrap nested format: {"field_name": <translations or string>}
        $translations = $settingData;
        if (isset($settingData[$fieldName])) {
            $translations = $settingData[$fieldName];

            // Could be a plain string after unwrapping
            if (is_string($translations)) {
                return $translations;
            }

            // Double-nested: {"field_name": {"field_name": "..."}}
            if (is_array($translations) && isset($translations[$fieldName]) && is_string($translations[$fieldName])) {
                return $translations[$fieldName];
            }
        }

        // At this point $translations should be a multilingual array {"en": "...", "hi": "..."}
        if (!is_array($translations)) {
            return '';
        }

        $requestedLanguage = get_current_language_from_request();
        $defaultLanguage = get_default_language();

        return resolve_translation_fallback($translations, [$requestedLanguage, $defaultLanguage, 'en']);
    }
}
