<?php

namespace App\Controllers\Apis;

use App\Controllers\BaseController;
use App\Models\ChatQuestions_model;
use App\Models\TranslatedChatQuestions_model;

/**
 * Unified chat questions API controller for both Customer and Provider platforms.
 *
 * Serves get_chat_questions for both api/v1 (customer) and partner/api/v1 (provider).
 * Follows the same pattern as LanguageApiController.
 */
class ChatQuestionsApiController extends BaseController
{
    protected $request;
    protected $user_details = [];

    protected $excluded_routes = [
        'api/v1/get_chat_questions',
        'partner/api/v1/get_chat_questions',
    ];

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
     * Get chat questions filtered by type with translation resolution.
     * POST param: type (pre_booking|customer_post_booking|provider_post_booking|customer_admin_support|provider_admin_support)
     */
    public function get_chat_questions()
    {
        try {
            $type = $this->request->getPost('type') ?? '';
            $typeMap = [
                'pre_booking'            => 'pre_booking',
                'post_booking'           => 'customer_post_booking',
                'provider_post_booking'  => 'provider_post_booking',
                'customer_admin_support' => 'customer_admin_support',
                'provider_admin_support' => 'provider_admin_support',
            ];

            if (!isset($typeMap[$type])) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('invalid_type', 'Invalid type')
                ]);
            }

            $type = $typeMap[$type];

            $languageCode = get_current_language_from_request();

            $chatQuestionsModel = new ChatQuestions_model();
            $translatedModel = new TranslatedChatQuestions_model();

            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            $defaultLangCode = !empty($languages) ? $languages[0]['code'] : 'en';

            $questions = $chatQuestionsModel->getByType($type);
            $resolved = [];

            if (!empty($questions)) {
                $ids = array_column($questions, 'id');

                $requestedTranslations = $translatedModel->getForMultipleQuestions($ids, $languageCode);

                $defaultTranslations = ($languageCode !== $defaultLangCode)
                    ? $translatedModel->getForMultipleQuestions($ids, $defaultLangCode)
                    : $requestedTranslations;

                foreach ($questions as $q) {
                    $questionText = $requestedTranslations[$q['id']]
                        ?? $defaultTranslations[$q['id']]
                        ?? $q['question'];

                    $resolved[] = [
                        'id'       => (int) $q['id'],
                        'question' => $questionText,
                    ];
                }
            }

            return response_helper(labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'), false, $resolved);
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', 'get_chat_questions: ' . $th->getMessage());
            return response_helper(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
        }
    }
}
