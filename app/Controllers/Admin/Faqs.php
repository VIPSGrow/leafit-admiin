<?php

namespace App\Controllers\Admin;

use App\Models\Faqs_model;
use App\Models\TranslatedFaqsModel;

class Faqs extends Admin
{
    public   $validation, $faqs, $creator_id, $translatedFaqsModel;
    protected $superadmin;

    public function __construct()
    {
        parent::__construct();
        helper(['form', 'url']);
        $this->faqs = new Faqs_model();
        $this->translatedFaqsModel = new TranslatedFaqsModel();
        $this->validation = \Config\Services::validation();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
    }
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        $permission = is_permitted($this->creator_id, 'read', 'faq');
        if (!$permission) {
            return redirect('unauthorised');
        }
        setPageInfo($this->data, labels('FAQs', 'FAQs') . ' | ' . labels('admin_panel', 'Admin Panel'), 'faqs');
        $this->data['faqs'] = fetch_details('faqs');
        // fetch languages
        $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
        $this->data['languages'] = $languages;
        return view('backend/admin/template', $this->data);
    }
    public function add_faqs()
    {
        try {
            $permission = is_permitted($this->creator_id, 'create', 'faq');
            if (!$permission) {
                return NoPermission();
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('unauthorised');
            }

            // Get translation data from POST first
            $questions = $this->request->getPost('question');
            $answers = $this->request->getPost('answer');

            // Get default language for validation
            $defaultLanguageCode = $this->getDefaultLanguageCode();

            // Debug logging to help troubleshoot
            // log_message('debug', 'FAQ Add Debug - Default Language: ' . $defaultLanguageCode);
            // log_message('debug', 'FAQ Add Debug - Questions: ' . json_encode($questions));
            // log_message('debug', 'FAQ Add Debug - Answers: ' . json_encode($answers));

            // Extract default language data for validation
            $defaultQuestion = isset($questions[$defaultLanguageCode]) ? trim($questions[$defaultLanguageCode]) : '';
            $defaultAnswer = isset($answers[$defaultLanguageCode]) ? trim($answers[$defaultLanguageCode]) : '';

            // Manual validation for default language fields (following Blog controller pattern)
            $errors = [];
            if (empty($defaultQuestion)) {
                $errors['question'] = labels(PLEASE_ENTER_QUESTION_FOR_FAQ_IN_DEFAULT_LANGUAGE, "Please enter question for FAQ in default language");
            }
            if (empty($defaultAnswer)) {
                $errors['answer'] = labels(PLEASE_ENTER_ANSWER_FOR_FAQ_IN_DEFAULT_LANGUAGE, "Please enter answer for FAQ in default language");
            }

            if (!empty($errors)) {
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Prepare main FAQ data
            $data = [
                'question' => $defaultQuestion,
                'answer' => $defaultAnswer
            ];

            // Save the main FAQ record first
            if ($this->faqs->save($data)) {
                // Get the FAQ ID (newly created)
                $faq_id = $this->faqs->getInsertID();

                // Save translations using optimized method
                $this->saveFaqTranslations($faq_id, $questions, $answers);

                return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Data saved successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels("please_try_again", "Please try again...."), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Faqs.php - add_faqs()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [])
    {
        try {
            $permission = is_permitted($this->creator_id, 'read', 'faq');
            if (!$permission) {
                return ErrorResponse(labels("Sorry! You're not permitted to take this action", "Sorry! You're not permitted to take this action"), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $db = \Config\Database::connect();
            $builder = $db->table('faqs f');

            // Get current language for translations (following promo code pattern)
            $currentLang = function_exists('get_current_language') ? get_current_language() : 'en';

            $sortable_fields = ['id' => 'id', 'question' => 'question', 'answer' => 'answer'];
            $offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
            $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
            $sort = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_fields) ? $_GET['sort'] : 'id';
            $order = isset($_GET['order']) && in_array($_GET['order'], ['ASC', 'DESC']) ? $_GET['order'] : 'ASC';
            $search = isset($_GET['search']) ? $_GET['search'] : '';

            // Check if translation table exists (backward compatibility)
            $translationTableExists = false;
            try {
                $db->query("SELECT 1 FROM translated_faq_details LIMIT 1");
                $translationTableExists = true;
            } catch (\Exception $e) {
                // Translation table doesn't exist yet, use original implementation
                log_message('debug', 'Translation table not found, using original FAQ list implementation');
            }

            if ($translationTableExists) {
                // New implementation with translations
                // Build search conditions for both original and translated fields
                $multipleWhere = [];
                if ($search) {
                    $multipleWhere = [
                        'f.id' => $search,
                        'f.question' => $search,
                        'f.answer' => $search,
                        'tfd.question' => $search,  // Include translated question in search
                        'tfd.answer' => $search     // Include translated answer in search
                    ];
                }

                // Count query with translations join
                $builder->select('COUNT(DISTINCT f.id) as `total`')
                    ->join('translated_faq_details tfd', "tfd.faq_id = f.id AND tfd.language_code = '$currentLang'", 'left');

                if ($multipleWhere) {
                    $builder->groupStart();
                    $builder->orLike($multipleWhere);
                    $builder->groupEnd();
                }
                if ($where) {
                    $builder->where($where);
                }

                $offer_count = $builder->get()->getRowArray();
                $total = $offer_count['total'];

                // Main query with translations
                $builder->select('f.*, 
                                 tfd.question as translated_question,
                                 tfd.answer as translated_answer')
                    ->join('translated_faq_details tfd', "tfd.faq_id = f.id AND tfd.language_code = '$currentLang'", 'left');

                if ($multipleWhere) {
                    $builder->groupStart();
                    $builder->orLike($multipleWhere);
                    $builder->groupEnd();
                }
                if ($where) {
                    $builder->where($where);
                }

                $offer_recored = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
            } else {
                // Fallback to original implementation (backward compatibility)
                $multipleWhere = $search ? ['`id`' => $search, '`question`' => $search, '`answer`' => $search, '`status`' => $search] : '';

                $builder->select('COUNT(id) as `total`');
                if ($multipleWhere) {
                    $builder->orWhere($multipleWhere);
                }
                if ($where) {
                    $builder->where($where);
                }

                $offer_count = $builder->get()->getRowArray();
                $total = $offer_count['total'];

                $builder->select();
                if ($multipleWhere) {
                    $builder->orLike($multipleWhere);
                }
                if ($where) {
                    $builder->where($where);
                }

                $offer_recored = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
            }

            $bulkData = array();
            $bulkData['total'] = $total;
            $rows = array();

            foreach ($offer_recored as $row) {
                $operations = '<div class="dropdown">
                    <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <button class="btn btn-secondary   btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
                    </a><div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
                $operations .= '<a class="dropdown-item edit_faqs " data-id="' . $row['id'] . '"  data-toggle="modal" data-target="#update_modal" onclick="faqs_id(this)"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit', 'Edit') . '</a>';
                $operations .= '<a class="dropdown-item remove_faqs" data-id="' . $row['id'] . '" onclick="faqs_id(this)" data-toggle="modal" data-target="#delete_modal" title = "Delete the Faqs"> <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
                $operations .= '</div></div>';

                $tempRow['id'] = $row['id'];
                $tempRow['created_at'] = format_date($row['created_at'], 'd-m-Y');

                if ($translationTableExists) {
                    // Apply translation fallback logic (following promo code pattern)
                    // Use translated question if available, otherwise use original question
                    $tempRow['question'] = !empty($row['translated_question']) ? $row['translated_question'] : $row['question'];
                    // Use translated answer if available, otherwise use original answer  
                    $tempRow['answer'] = !empty($row['translated_answer']) ? $row['translated_answer'] : $row['answer'];
                } else {
                    // Use original data (backward compatibility)
                    $tempRow['question'] = $row['question'];
                    $tempRow['answer'] = $row['answer'];
                }

                $tempRow['operations'] = $operations;
                $rows[] = $tempRow;
            }

            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Faqs.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function remove_faqs()
    {
        try {
            $permission = is_permitted($this->creator_id, 'delete', 'faq');
            if (!$permission) {
                return NoPermission();
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('unauthorised');
            }
            $id = $this->request->getPost('id');
            $db = \Config\Database::connect();
            $builder = $db->table('faqs');

            // Delete translations first (with backward compatibility)
            try {
                // Check if translation table exists
                $db->query("SELECT 1 FROM translated_faq_details LIMIT 1");

                // Translation table exists, delete translations
                $this->translatedFaqsModel->deleteTranslations($id);
            } catch (\Exception $e) {
                // Translation table doesn't exist yet, skip translation deletion
                log_message('debug', 'Translation table not found, skipping FAQ translation deletion');
            }

            if ($builder->delete(['id' => $id])) {
                return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Data Deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Faqs.php - remove_faqs()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function edit_faqs()
    {
        try {
            $permission = is_permitted($this->creator_id, 'update', 'faq');
            if (!$permission) {
                return NoPermission();
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('unauthorised');
            }

            // Get translation data from POST first
            $questions = $this->request->getPost('question');
            $answers = $this->request->getPost('answer');

            // Get default language for validation
            $defaultLanguageCode = $this->getDefaultLanguageCode();

            // Extract default language data for validation
            $defaultQuestion = isset($questions[$defaultLanguageCode]) ? trim($questions[$defaultLanguageCode]) : '';
            $defaultAnswer = isset($answers[$defaultLanguageCode]) ? trim($answers[$defaultLanguageCode]) : '';

            // Manual validation for default language fields (following Blog controller pattern)
            $errors = [];
            if (empty($defaultQuestion)) {
                $errors['question'] = labels(PLEASE_ENTER_QUESTION_FOR_FAQ_IN_DEFAULT_LANGUAGE, "Please enter question for FAQ in default language");
            }
            if (empty($defaultAnswer)) {
                $errors['answer'] = labels(PLEASE_ENTER_ANSWER_FOR_FAQ_IN_DEFAULT_LANGUAGE, "Please enter answer for FAQ in default language");
            }

            if (!empty($errors)) {
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            $db = \Config\Database::connect();
            $builder = $db->table('faqs');

            $id = $this->request->getPost('id');

            // Prepare main FAQ data (using default language)
            $data = [
                'question' => $defaultQuestion,
                'answer' => $defaultAnswer
            ];

            if ($builder->update($data, ['id' => $id])) {
                // Save translations using optimized method
                $this->saveFaqTranslations($id, $questions, $answers);

                return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Data Updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Faqs.php - edit_faqs()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get FAQ data with translations for editing
     * 
     * @return \CodeIgniter\HTTP\Response JSON response with FAQ data
     */
    public function get_faq_data()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $permission = is_permitted($this->creator_id, 'read', 'faq');
            if (!$permission) {
                return NoPermission();
            }

            $faq_id = $this->request->getPost('id');

            if (!$faq_id) {
                return ErrorResponse(labels(FAQ_ID_IS_REQUIRED, "FAQ ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get basic FAQ data
            $faq = $this->faqs->find($faq_id);

            if (!$faq) {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get all translations for this FAQ (with backward compatibility)
            $translations = [];
            try {
                // Check if translation table exists
                $db = \Config\Database::connect();
                $db->query("SELECT 1 FROM translated_faq_details LIMIT 1");

                // Translation table exists, get translations
                $translations = $this->translatedFaqsModel->getAllTranslations($faq_id);
            } catch (\Exception $e) {
                // Translation table doesn't exist yet, return empty translations
                log_message('debug', 'Translation table not found, returning empty translations for FAQ edit');
            }

            $response_data = [
                'faq' => $faq,
                'translations' => $translations
            ];

            return successResponse(labels(DATA_RETRIEVED_SUCCESSFULLY, "Data Retrived Successfully"), false, $response_data, [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Faqs.php - get_faq_data()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get default language code from database
     * 
     * @return string Default language code
     */
    private function getDefaultLanguageCode()
    {
        $languages = fetch_details('languages', ['is_default' => 1], ['code']);
        return $languages[0]['code'] ?? 'en';
    }

    /**
     * Save FAQ translations for all languages - OPTIMIZED for performance
     * 
     * @param int $faq_id The FAQ ID
     * @param array $questions Array of questions with language codes as keys
     * @param array $answers Array of answers with language codes as keys
     * @return bool True if successful, false otherwise
     */
    private function saveFaqTranslations($faq_id, $questions, $answers)
    {
        try {
            // Validate input arrays
            if (!$questions || !is_array($questions) || !$answers || !is_array($answers)) {
                return false;
            }

            // Check if translation table exists (backward compatibility)
            $db = \Config\Database::connect();
            try {
                $db->query("SELECT 1 FROM translated_faq_details LIMIT 1");
            } catch (\Exception $e) {
                // Translation table doesn't exist yet, skip translation saving
                log_message('debug', 'Translation table not found, skipping FAQ translation saving');
                return true; // Return true to not break the flow
            }

            // OPTIMIZATION: Use transaction for better performance and data integrity
            $db->transStart();

            try {
                // Save translations using the optimized model method
                $result = $this->translatedFaqsModel->saveFaqTranslationsOptimized($faq_id, $questions, $answers);

                $db->transComplete();
                return $result && $db->transStatus();
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Error saving FAQ translations: ' . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            log_message('error', 'Error in saveFaqTranslations: ' . $e->getMessage());
            return false;
        }
    }
}
