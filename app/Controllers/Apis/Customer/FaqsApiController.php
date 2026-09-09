<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\Faqs_model;
use App\Models\TranslatedFaqsModel;
use App\Libraries\JWT;

class FaqsApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1"
    ];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_faqs()
    {
        try {
            // Get language code from request header
            $requested_language = get_current_language_from_request();
            $default_language = get_default_language();

            $Faqs_model = new Faqs_model();
            $TranslatedFaqsModel = new TranslatedFaqsModel();

            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';

            // Get base FAQ data
            $data = $Faqs_model->list(true, $search, $limit, $offset, $sort, $order);

            if (!empty($data['data'])) {
                // Get all FAQ IDs for batch translation lookup
                $faq_ids = array_column($data['data'], 'id');

                // Initialize translation lookup array
                $translation_lookup = [];

                // Try to fetch translations if translation table exists (backward compatibility)
                try {
                    $db = \Config\Database::connect();
                    $builder = $db->table('translated_faq_details');

                    // Get unique language codes to avoid duplicates
                    $language_codes = array_unique([$default_language, $requested_language]);

                    $translations = $builder->select('faq_id, language_code, question, answer')
                        ->whereIn('faq_id', $faq_ids)
                        ->whereIn('language_code', $language_codes)
                        ->get()
                        ->getResultArray();

                    // Organize translations by FAQ ID and language for easy lookup
                    foreach ($translations as $translation) {
                        // Ensure FAQ ID is treated as integer for consistent array key matching
                        $faq_id_key = (int)$translation['faq_id'];
                        $translation_lookup[$faq_id_key][$translation['language_code']] = [
                            'question' => $translation['question'],
                            'answer' => $translation['answer']
                        ];
                    }
                } catch (\Exception $e) {
                    // Translation table doesn't exist yet, continue without translations
                    log_message('debug', 'Translation table not found, using main table values only. Error: ' . $e->getMessage());
                }

                // Process each FAQ to add translation support
                $processed_data = [];

                foreach ($data['data'] as $faq) {
                    $faq_id = $faq['id'];

                    // Get translations from lookup (avoid individual database queries)
                    $default_translation = isset($translation_lookup[$faq_id][$default_language])
                        ? $translation_lookup[$faq_id][$default_language]
                        : null;
                    $requested_translation = isset($translation_lookup[$faq_id][$requested_language])
                        ? $translation_lookup[$faq_id][$requested_language]
                        : null;

                    // Build response with proper fallback logic
                    $processed_faq = [
                        'id' => $faq['id'],
                        'status' => $faq['status'],
                        'created_at' => $faq['created_at']
                    ];

                    // Question field: Always use default language translation or fallback to main table
                    // This ensures consistent default language content in the main question field
                    if ($default_translation && !empty($default_translation['question'])) {
                        $processed_faq['question'] = $default_translation['question'];
                    } else {
                        $processed_faq['question'] = $faq['question'];
                    }

                    // Answer field: Always use default language translation or fallback to main table
                    // This ensures consistent default language content in the main answer field
                    if ($default_translation && !empty($default_translation['answer'])) {
                        $processed_faq['answer'] = $default_translation['answer'];
                    } else {
                        $processed_faq['answer'] = $faq['answer'];
                    }

                    // Translated question: Fallback hierarchy for requested language
                    // 1. Requested language translation (if exists and not empty)
                    // 2. Default language translation (if exists and not empty)
                    // 3. Main table value (final fallback)
                    if ($requested_translation && !empty($requested_translation['question'])) {
                        $processed_faq['translated_question'] = $requested_translation['question'];
                    } elseif ($default_translation && !empty($default_translation['question'])) {
                        $processed_faq['translated_question'] = $default_translation['question'];
                    } else {
                        $processed_faq['translated_question'] = $faq['question'];
                    }

                    // Translated answer: Fallback hierarchy for requested language
                    // 1. Requested language translation (if exists and not empty)
                    // 2. Default language translation (if exists and not empty)
                    // 3. Main table value (final fallback)
                    if ($requested_translation && !empty($requested_translation['answer'])) {
                        $processed_faq['translated_answer'] = $requested_translation['answer'];
                    } elseif ($default_translation && !empty($default_translation['answer'])) {
                        $processed_faq['translated_answer'] = $default_translation['answer'];
                    } else {
                        $processed_faq['translated_answer'] = $faq['answer'];
                    }

                    $processed_data[] = $processed_faq;
                }

                return response_helper(labels(FAQS_FETCHED_SUCCESSFULLY, 'faqs fetched successfully'), false, remove_null_values($processed_data), 200, ['total' => $data['total']]);
            } else {
                return response_helper(labels(FAQS_NOT_FOUND, 'faqs not found'), false, [], 200, ['total' => 0]);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_faqs()');
            return $this->response->setJSON($response);
        }
    }
}
