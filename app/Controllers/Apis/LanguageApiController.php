<?php

namespace App\Controllers\Apis;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use App\Models\Language_model;

/**
 * Unified language API controller for both Customer and Provider platforms.
 *
 * Serves get_language_list and get_language_json_data with platform-specific
 * behaviour driven by Platform header (get_language_list) or platform parameter
 * (get_language_json_data). Keeps the same response shape as the previous
 * Customer (LanguageApiController) and Provider (V1) implementations.
 *
 * Placed alongside PlacesController under App\Controllers\Apis.
 */
class LanguageApiController extends BaseController
{
    protected $request;
    protected $user_details = [];

    /** Routes that do not require auth; token is still read when present for FCM/preferred_language. */
    protected $excluded_routes = [
        'api/v1/get_language_list',
        'api/v1/get_language_json_data',
        'partner/api/v1/get_language_list',
        'partner/api/v1/get_language_json_data',
    ];

    /** Platform values supported for language list (header) and JSON data (POST). */
    private const PLATFORMS = ['web', 'customer_app', 'provider_app'];

    public function __construct()
    {
        helper('api');
        helper('function');
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->response = \Config\Services::response();

        $current_uri = uri_string();
        $token = verify_app_request();

        if (!in_array($current_uri, $this->excluded_routes)) {
            if ($token['error']) {
                header('Content-Type: application/json');
                http_response_code($token['status']);
                print_r(json_encode($token));
                exit;
            }
            $this->user_details = $token['data'] ?? [];
        } else {
            if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
                $this->user_details = $token['data'];
            }
        }
    }

    /**
     * Get available languages from database (unified for Customer and Provider).
     *
     * Platform is taken from the "Platform" request header:
     *   - web          → only languages that have web language file
     *   - customer_app → only languages that have customer_app language file
     *   - provider_app → only languages that have provider_app language file (same as partner API)
     *   - missing/empty → legacy: only languages that have BOTH web and customer_app files
     *
     * Response shape is unchanged from previous Customer/Provider implementations.
     *
     * @return \CodeIgniter\HTTP\Response JSON response with data and default_language
     */
    public function get_language_list()
    {
        try {
            $platform = strtolower(trim((string) $this->request->getHeaderLine('Platform')));
            // Partner API clients do not send Platform header; default to provider_app so response stays as before
            if ($platform === '' && strpos(uri_string(), 'partner') !== false) {
                $platform = 'provider_app';
            }
            $languageModel = new Language_model();

            $languages = $languageModel->select('id, language, code, is_rtl, is_default, image, updated_at, created_at');
            $result = $languages->get()->getResultArray();

            $data = [];
            $default_language = [];

            $fileService = service('fileService');

            if (!empty($result)) {
                foreach ($result as $row) {
                    if ($row['is_default'] == 1) {
                        $default_language['code'] = $row['code'];
                        $default_language['name'] = $row['language'];
                        $default_language['is_rtl'] = $row['is_rtl'];
                        $default_language['image'] = $fileService->url($row['image'], 'language_image');
                        $default_language['created_at'] = $row['created_at'];
                        $default_language['updated_at'] = $row['updated_at'];
                    }

                    $fileExists = false;
                    if (in_array($platform, self::PLATFORMS)) {
                        if ($platform === 'provider_app') {
                            $fileExists = $languageModel->hasLanguageFilesForProviderApp($row['code']);
                        } else {
                            $fileExists = $languageModel->hasLanguageFileForPlatform($row['code'], $platform);
                        }
                    } else {
                        // Legacy: require both web and customer_app files (Customer behaviour when header missing)
                        $fileExists = $languageModel->hasLanguageFileForPlatform($row['code'], 'web')
                            && $languageModel->hasLanguageFileForPlatform($row['code'], 'customer_app');
                    }

                    if ($fileExists) {
                        $data[] = [
                            'id' => $row['id'],
                            'language' => $row['language'],
                            'code' => $row['code'],
                            'is_rtl' => $row['is_rtl'],
                            'image' => !empty($row['image']) ? $fileService->url($row['image'], 'language_image') : '',
                            'created_at' => $row['created_at'],
                            'updated_at' => $row['updated_at'],
                        ];
                    }
                }
            }

            $response = [
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                'data' => $data,
                'default_language' => $default_language,
            ];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed :: ' . json_encode($_POST) . ' Issue => ' . $th->getMessage(),
                date('Y-m-d H:i:s') . '--> app/Controllers/Apis/LanguageController.php - get_language_list()'
            );
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    /**
     * Get language JSON data for a given platform and language code (unified for Customer and Provider).
     *
     * Platform is taken from POST "platform"; if missing and request is to partner API,
     * platform defaults to provider_app so existing Provider clients keep working.
     * Supported platforms: web, customer_app, provider_app.
     *
     * Optional: updates FCM token and user preferred_language when user is logged in.
     * Response shape is unchanged from previous implementations.
     *
     * @return \CodeIgniter\HTTP\Response JSON response with language data
     */
    public function get_language_json_data()
    {
        try {
            $platform = $this->request->getPost('platform');
            $languageCode = $this->request->getPost('language_code');
            $fcm_token = $this->request->getPost('fcm_token');

            // Provider API (partner) does not send platform; default to provider_app for backward compatibility
            if (empty($platform) && strpos(uri_string(), 'partner') !== false) {
                $platform = 'provider_app';
            }

            if (empty($languageCode)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(LANGUAGE_CODE_IS_REQUIRED, 'Language code is required'),
                    'data' => [],
                ]);
            }

            if (empty($platform) || !in_array($platform, self::PLATFORMS)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('invalid_platform', 'Invalid platform. Supported: ' . implode(', ', self::PLATFORMS)),
                    'data' => [],
                ]);
            }

            if (!empty($fcm_token) && !empty($this->user_details['id'])) {
                store_users_fcm_id($this->user_details['id'], $fcm_token, $platform, null, $languageCode);
            }

            if (!empty($this->user_details['id'])) {
                update_details(['preferred_language' => $languageCode], ['id' => $this->user_details['id']], 'users');
            }

            $filePath = FCPATH . 'public/uploads/languages/' . $platform . '/' . strtolower($languageCode) . '/' . strtolower($languageCode) . '.json';

            if (!file_exists($filePath)) {
                $msg = $platform === 'provider_app'
                    ? 'Language file not found for provider_app platform and language: ' . strtolower($languageCode)
                    : (labels(DATA_NOT_FOUND, 'Data not found') . labels('for', 'for') . ' ' . $platform);
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $msg,
                    'data' => [],
                ]);
            }

            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('unable_to_read_language_file', 'Unable to read language file'),
                    'data' => [],
                ]);
            }

            $languageData = json_decode($fileContent, true);
            if (json_last_error() !== JSON_ERROR_NONE
                || ($languageData === null && trim($fileContent) !== '' && trim($fileContent) !== 'null')
                || (isset($languageData['error']) && $languageData['error'] == true)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('file_does_not_exist', 'File Not Exists, Please Upload'),
                    'data' => [],
                ]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                'data' => $languageData,
                'platform' => $platform,
                'language_code' => strtolower($languageCode),
                'file_path' => str_replace(FCPATH, '', base_url($filePath)),
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed :: ' . json_encode($_POST) . ' Issue => ' . $th->getMessage(),
                date('Y-m-d H:i:s') . '--> app/Controllers/Apis/LanguageController.php - get_language_json_data()'
            );
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }
}
