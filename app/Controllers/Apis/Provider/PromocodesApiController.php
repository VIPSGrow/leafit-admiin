<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Promo_code_model;

class PromocodesApiController extends BaseController
{
    protected $request, $validation, $db, $data;
    protected $toDateTime;
    protected $builder;
    protected $user_details = [];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->validation = \Config\Services::validation();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode([
                'error' => true,
                'message' => $token['message'],
                'status' => 401,
            ]));
            die();
        }
    }

    public function get_promocodes()
    {
        try {
            $model = new Promo_code_model();
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $where = [];
            if ($this->user_details['id'] != '') {
                $where['pc.partner_id'] = $this->user_details['id'];
            }

            $languageCode = get_current_language_from_request();
            $data = $model->list(true, $search, $limit, $offset, $sort, $order, $where, $languageCode);
            if (!empty($data['data'])) {
                return response_helper(labels(PROMOCODE_FETCHED_SUCCESSFULLY, 'Promocode fetched successfully'), false, $data['data'], 200, ['total' => $data['total']]);
            } else {
                return response_helper(labels(PROMOCODE_NOT_FOUND, 'Promocode not found'), false);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_promocodes()');
            return $this->response->setJSON($response);
        }
    }

    public function manage_promocode()
    {
        try {
            $db = \Config\Database::connect();
            $this->validation = \Config\Services::validation();

            // Get the default language from database
            $defaultLanguage = 'en'; // fallback
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            // Validate translated_fields format
            $postData = $this->request->getPost();
            $validationErrors = [];

            // Check if translated_fields is provided and handle JSON string format
            $translatedFields = $postData['translated_fields'] ?? null;

            // If translated_fields is provided as JSON string, decode it
            if (is_string($translatedFields)) {
                $translatedFields = json_decode($translatedFields, true);

                // Check if JSON decoding was successful
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $validationErrors[] = "translated_fields contains invalid JSON format: " . json_last_error_msg();
                } else {
                    // Update postData with decoded value for further processing
                    $postData['translated_fields'] = $translatedFields;
                }
            }

            // Check if translated_fields is provided and is an array
            if (!isset($postData['translated_fields']) || !is_array($postData['translated_fields'])) {
                $validationErrors[] = "translated_fields is required and must be an object";
            } else {
                $translatedFields = $postData['translated_fields'];

                // Check if message field is present and is an array
                if (!isset($translatedFields['message']) || !is_array($translatedFields['message'])) {
                    $validationErrors[] = "translated_fields.message is required and must be an object";
                } else {
                    // Check if default language is provided for message field
                    if (!isset($translatedFields['message'][$defaultLanguage]) || empty($translatedFields['message'][$defaultLanguage])) {
                        $validationErrors[] = "translated_fields.message.{$defaultLanguage} is required";
                    }
                }
            }

            if (!empty($validationErrors)) {
                $response = [
                    'error' => true,
                    'message' => $validationErrors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }

            // Set validation rules for basic fields (translatable fields are handled in translation validation)
            $this->validation->setRules([
                'promo_code' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
                'minimum_order_amount' => 'required|numeric',
                'discount' => 'required|numeric',
                'discount_type' => 'required',
                'max_discount_amount' => 'permit_empty|numeric',
                'status' => 'required',
            ]);

            $partner_id = $this->user_details['id'];
            $fileService = service('fileService');
            $old_image = [];

            // Check if this is an update operation
            $is_update = isset($_POST['promo_id']) && !empty($_POST['promo_id']);
            if ($is_update) {
                $where['id'] = $_POST['promo_id'];
                $old_image = fetch_details('promo_codes', $where, ['image']);
            }

            // Handle image upload
            $image = $old_image[0]['image'] ?? "";
            $uploadedFile = $this->request->getFile('image');
            if ($uploadedFile && $uploadedFile->isValid()) {
                $result = $fileService->replace($old_image[0]['image'] ?? null, $uploadedFile, 'promocodes');
                if ($result['error']) {
                    return ErrorResponse($result['message'] ?? labels(FAILED_TO_CREATE_PROMOCODES_FOLDERS, 'Failed to create promocodes folders'), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $image = $result['path'];
            }

            if (!$this->validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $this->validation->getErrors(),
                    'data' => []
                ]);
            }

            $promocode_model = new Promo_code_model();

            // Prepare promocode data
            $promocode = [
                'partner_id' => $partner_id,
                'promo_code' => $this->request->getVar('promo_code'),
                'message' => $this->request->getVar('message'),
                'start_date' => $this->request->getVar('start_date'),
                'end_date' => $this->request->getVar('end_date'),
                'no_of_users' => $this->request->getPost('no_of_users') ?: 1,
                'minimum_order_amount' => $this->request->getVar('minimum_order_amount'),
                'max_discount_amount' => $this->request->getVar('max_discount_amount'),
                'discount' => $this->request->getVar('discount'),
                'discount_type' => $this->request->getVar('discount_type'),
                'repeat_usage' => $this->request->getPost('repeat_usage') ?: 0,
                'no_of_repeat_usage' => $this->request->getPost('no_of_repeat_usage') ?: 0,
                'image' => $image,
                'status' => $this->request->getPost('status') ?: 0,
            ];

            // Add ID for update operation
            if ($is_update) {
                $promocode['id'] = $_POST['promo_id'];
            }

            // Save the promocode
            if (!$promocode_model->save($promocode)) {
                throw new \Exception('Failed to save promocode');
            }

            // Get the correct ID based on operation type
            $promo_id = $is_update ? $_POST['promo_id'] : $promocode_model->getInsertID();

            // Process promocode translations
            try {
                $this->processPromocodeTranslations($promo_id, $postData, $defaultLanguage);
            } catch (\Throwable $th) {
                log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - manage_promocode() - Promocode translations');
                // Don't fail the entire request for translation errors, just log them
            }

            // Send notifications only for new promo codes (not updates)
            // When provider adds a promo code, notify admin
            if (!$is_update) {
                try {
                    $language = get_current_language_from_request();

                    // Get provider details with translation support
                    $provider_name = 'Provider';
                    $partner_details = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                    if (!empty($partner_details)) {
                        $defaultLanguage = get_default_language();
                        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
                        $translatedPartnerDetails = $translationModel->getTranslatedDetails($partner_id, $defaultLanguage);
                        if (!empty($translatedPartnerDetails) && !empty($translatedPartnerDetails['company_name'])) {
                            $provider_name = $translatedPartnerDetails['company_name'];
                        } else {
                            $provider_name = $partner_details[0]['company_name'] ?? $provider_name;
                        }
                    }

                    // Prepare context data for notification templates
                    $notificationContext = [
                        'provider_name' => $provider_name,
                        'provider_id' => $partner_id,
                        'promo_code' => $this->request->getVar('promo_code'),
                        'promo_code_id' => $promo_id,
                        'discount' => $this->request->getVar('discount'),
                        'discount_type' => $this->request->getVar('discount_type'),
                        'minimum_order_amount' => $this->request->getVar('minimum_order_amount'),
                        'max_discount_amount' => $this->request->getVar('max_discount_amount'),
                        'start_date' => $this->request->getVar('start_date'),
                        'end_date' => $this->request->getVar('end_date'),
                        'no_of_users' => $this->request->getPost('no_of_users') ?: 1,
                        'include_logo' => true, // Include logo in email templates
                    ];

                    // Send notifications to admin users (group_id = 1) via all channels
                    queue_notification_service(
                        eventType: 'promo_code_added',
                        recipients: [],
                        context: $notificationContext,
                        options: [
                            'user_groups' => [1], // Admin user group
                            'channels' => ['fcm', 'email', 'sms'], // All channels
                            'language' => $language,
                            'platforms' => ['admin_panel'] // Admin panel platform for FCM
                        ]
                    );
                    // log_message('info', '[PROMO_CODE_ADDED] Admin notification result (provider): ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the promo code creation
                    log_message('error', '[PROMO_CODE_ADDED] Notification error trace (provider): ' . $notificationError->getTraceAsString());
                }
            }

            // Fetch the saved promocode details
            $data = fetch_details('promo_codes', ['id' => $promo_id], [
                'id',
                'promo_code',
                'start_date',
                'end_date',
                'minimum_order_amount',
                'discount',
                'discount_type',
                'max_discount_amount',
                'repeat_usage',
                'no_of_repeat_usage',
                'no_of_users',
                'message',
                'status',
                'image',
                '(SELECT COUNT(*) FROM orders WHERE promo_code = promo_codes.promo_code) AS total_used_number',
            ]);

            if (!empty($data)) {
                $data[0]['image'] = !empty($data[0]['image'])
                    ? $fileService->url($data[0]['image'], 'promocodes')
                    : '';

                // Apply translations to promocode data based on Content-Language header
                $data[0] = $this->applyPromocodeTranslations($data[0]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => $is_update ? 'Promocode updated successfully' : 'Promocode saved successfully',
                'data' => $data
            ]);
        } catch (\Exception $th) {
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: '
                    . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - manage_promocode()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')
            ]);
        }
    }

    public function delete_promocode()
    {
        try {
            $validation =  \Config\Services::validation();
            $validation->setRules(
                [
                    'promo_id' => 'required|numeric',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $promo_id = $this->request->getPost('promo_id');
            $is_exist =  exists(['id' => $promo_id], 'promo_codes');
            if (!$is_exist) {
                $response = [
                    'error' => true,
                    'message' => labels(PROMOCODE_DOES_NOT_EXIST, 'Promo code does not exist!'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $db      = \Config\Database::connect();
            $old_image = fetch_details('promo_codes', ['id' => $promo_id], ['image',]);
            service('fileService')->delete('promocodes', $old_image[0]['image'] ?? null);
            $builder = $db->table('promo_codes')->delete(['id' => $promo_id]);
            if ($builder) {
                $response = [
                    'error' => false,
                    'message' => labels(PROMOCODE_DELETED_SUCCESSFULLY, 'Promocode deleted successfully!'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(PROMOCODE_DOES_NOT_EXIST, 'Promocode does not exist!'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - delete_promocode()');
            return $this->response->setJSON($response);
        }
    }

    // Private Helper Methods

    /**
     * Apply translations to promocode data based on Content-Language header
     * 
     * This function adds translated fields to the promocode response data
     * based on the language specified in the Content-Language header
     * 
     * @param array $promocodeData The promocode data from the main table
     * @return array Promocode data with translated fields added
    */
    private function applyPromocodeTranslations(array $promocodeData): array
    {
        try {
            // Get current language from request header
            $requestedLanguage = get_current_language_from_request();

            // Get default language
            $defaultLanguage = get_default_language();

            // Initialize translation model
            $translationModel = new \App\Models\TranslatedPromocodeModel();

            // Fetch all translations for this promo
            $allTranslations = $translationModel->getAllTranslations($promocodeData['id']);

            // Build consistent structure
            $promocodeData['translated_fields'] = [
                'message' => $allTranslations
            ];

            // For backward compatibility: also expose translated_message
            if ($requestedLanguage === $defaultLanguage) {
                $promocodeData['translated_message'] = $promocodeData['message'];
            } else {
                $promocodeData['translated_message'] = $allTranslations[$requestedLanguage] ?? '';
            }

            return $promocodeData;
        } catch (\Exception $e) {
            // Log error but don't break the response
            log_message('error', 'Error applying promocode translations: ' . $e->getMessage());

            // Fallback: keep structure but empty
            $promocodeData['translated_fields'] = [
                'message' => []
            ];
            $promocodeData['translated_message'] = '';

            return $promocodeData;
        }
    }

    /**
     * Process promocode translations and store them in the translation table
     * 
     * Expected API format for translatable fields:
     * {
     *   "translated_fields": {
     *     "message": {
     *       "en": "Promo message in English",
     *       "hi": "Promo message in Hindi",
     *       "ar": "Promo message in Arabic"
     *     }
     *   }
     * }
     * 
     * @param int $promoId Promocode ID
     * @param array $postData POST data from the form
     * @param string $defaultLanguage Default language code
    */
    private function processPromocodeTranslations(int $promoId, array $postData, string $defaultLanguage): void
    {
        try {
            // Get translated fields from post data
            $translatedFields = $postData['translated_fields'] ?? null;

            // Handle case where translated_fields might still be a JSON string
            if (is_string($translatedFields)) {
                $translatedFields = json_decode($translatedFields, true);
            }

            if (isset($translatedFields) && is_array($translatedFields) && isset($translatedFields['message'])) {
                // Initialize translation model
                $translationModel = new \App\Models\TranslatedPromocodeModel();

                // Process message translations
                $messages = $translatedFields['message'];

                // Save translations using the optimized method
                $saveResult = $translationModel->saveTranslationsOptimized($promoId, $messages);

                if ($saveResult) {
                    log_message('info', 'Successfully processed promocode translations for promo ' . $promoId . ': ' . json_encode(array_keys($messages)));
                } else {
                    log_message('error', 'Failed to save promocode translations for promo ' . $promoId);
                }
            } else {
                log_message('warning', 'No valid translated_fields found for promocode ' . $promoId);
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the promocode creation
            log_message('error', 'Exception in processPromocodeTranslations for promocode ' . $promoId . ': ' . $e->getMessage());
        }
    }
}
