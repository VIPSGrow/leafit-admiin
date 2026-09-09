<?php

namespace App\Controllers\Admin;

use App\Models\ReasonsForReportAndBlockChat_model;
use App\Models\TranslatedReasonsForReportAndBlockChat_model;
use Exception;

class ReasonsForReportAndBlockChat extends Admin
{
    public $orders, $creator_id, $ionAuth;
    protected $superadmin;
    protected $reasonsForReportAndBlockChat;
    protected $translatedReasonModel;
    protected $db;
    protected $validation;

    public function __construct()
    {
        parent::__construct();
        $this->reasonsForReportAndBlockChat = new ReasonsForReportAndBlockChat_model();
        $this->translatedReasonModel = new TranslatedReasonsForReportAndBlockChat_model();
        $this->creator_id = $this->userId;
        $this->db = \Config\Database::connect();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->validation = \Config\Services::validation();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
    }

    public function index()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('reasons_for_report_and_block_chat', 'Reasons for Report and Block Chat') . ' | ' . labels('admin_panel', 'Admin Panel'), 'reasons_for_report_and_block_chat');
            // fetch languages
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
            $this->data['languages'] = $languages;
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }

    public function list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $data = $this->reasonsForReportAndBlockChat->list('', '', $search, $limit, $offset, $sort, $order);
        return $this->response->setJSON($data);
    }

    public function add()
    {
        try {
            $permission = is_permitted($this->creator_id, 'create', 'faq');
            if (!$permission) {
                return NoPermission();
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('unauthorised');
            }

            // Get all available languages to validate reason fields
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            // Set up validation rules for translated reason fields
            $rules = [];
            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $isDefaultLanguage = $language['is_default'] == 1;

                // For default language, reason is required
                if ($isDefaultLanguage) {
                    $rules["reason.{$languageCode}"] = [
                        "rules" => 'required|trim',
                        "errors" => ["required" => labels("reason_for_default_language_is_required", "Reason for default language is required")]
                    ];
                }
            }

            $this->validation->setRules($rules);

            if (!$this->validation->withRequest($this->request)->run()) {
                $errors  = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Store non-translatable fields in the main table
            $data['type'] = 'admin';
            $data['needs_additional_info'] = isset($_POST['needs_additional_info']) && ($_POST['needs_additional_info'] == "on") ? 1 : 0;

            // Get default language and store its reason in main table for backward compatibility
            $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';
            if (isset($_POST['reason'][$defaultLanguage]) && !empty($_POST['reason'][$defaultLanguage])) {
                $data['reason'] = esc($_POST['reason'][$defaultLanguage]);
            }

            if ($this->reasonsForReportAndBlockChat->save($data)) {
                $reasonId = $this->reasonsForReportAndBlockChat->getInsertID();

                // Process and store translations for all languages
                try {
                    // Process translated fields from POST data
                    $translatedFields = $this->processTranslatedFields($_POST);

                    // Store translations using the helper function
                    $translationResult = $this->storeReasonTranslations($reasonId, $translatedFields);

                    if (!$translationResult) {
                        log_message('warning', 'Some reason translations may not have been saved for reason ID: ' . $reasonId);
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Exception in reason translation processing: ' . $e->getMessage());
                    // Don't fail the reason creation if translation processing fails
                    // The reason is already created, so we just log the translation error
                }

                return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Reason added successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse("please try again....", true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ReasonsForReportAndBlockChat.php - add()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function remove()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('unauthorised');
            }
            $id = $this->request->getPost('id');

            // Clean up reason translations before deleting reason
            try {
                $this->translatedReasonModel->deleteReasonTranslations($id);
            } catch (\Exception $e) {
                log_message('error', 'Failed to delete reason translations for reason ID ' . $id . ': ' . $e->getMessage());
                // Continue with reason deletion even if translation cleanup fails
            }

            $db = \Config\Database::connect();
            $builder = $db->table('reasons_for_report_and_block_chat');
            if ($builder->delete(['id' => $id])) {
                return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Reason deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred during deleting this item"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ReasonsForReportAndBlockChat.php - remove()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function edit()
    {
        try {
            // Get all available languages to validate reason fields
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            // Set up validation rules for translated reason fields
            $rules = [];
            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $isDefaultLanguage = $language['is_default'] == 1;

                // For default language, reason is required
                if ($isDefaultLanguage) {
                    $rules["reason.{$languageCode}"] = [
                        "rules" => 'required|trim',
                        "errors" => ["required" => labels("reason_for_default_language_is_required", "Reason for default language is required")]
                    ];
                }
            }

            $this->validation->setRules($rules);

            if (!$this->validation->withRequest($this->request)->run()) {
                return ErrorResponse($this->validation->getErrors(), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $db = \Config\Database::connect();
            $builder = $db->table('reasons_for_report_and_block_chat');
            if ($this->isLoggedIn && $this->userIsAdmin) {
                $id = $this->request->getPost('id');

                // Store non-translatable fields in the main table
                $data['type'] = 'admin';
                $data['needs_additional_info'] = isset($_POST['needs_additional_info']) && ($_POST['needs_additional_info'] == "on") ? 1 : 0;

                // Get default language and store its reason in main table for backward compatibility
                $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';
                if (isset($_POST['reason'][$defaultLanguage]) && !empty($_POST['reason'][$defaultLanguage])) {
                    $data['reason'] = esc($_POST['reason'][$defaultLanguage]);
                }

                if ($builder->update($data, ['id' => $id])) {
                    // Process and store translations for all languages
                    try {
                        // Process translated fields from POST data
                        $translatedFields = $this->processTranslatedFields($_POST);

                        // Store translations using the helper function
                        $translationResult = $this->storeReasonTranslations($id, $translatedFields);

                        if (!$translationResult) {
                            log_message('warning', 'Some reason translations may not have been updated for reason ID: ' . $id);
                        }
                    } catch (\Exception $e) {
                        log_message('error', 'Exception in reason translation processing during update: ' . $e->getMessage());
                        // Don't fail the reason update if translation processing fails
                        // The reason is already updated, so we just log the translation error
                    }

                    return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Reason updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(ERROR_OCCURED, "Some error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ReasonsForReportAndBlockChat.php - edit()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get reason data with translations for edit modal
     * Since reason is no longer stored in main table, only use translations from translation table
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function get_reason_data()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return ErrorResponse(labels("unauthorized_access", "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $reasonId = $this->request->getPost('id');

            if (empty($reasonId)) {
                return ErrorResponse(labels("reason_id_is_required", "Reason ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get reason data from main table
            $reasonData = $this->reasonsForReportAndBlockChat->find($reasonId);

            if (!$reasonData) {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get all available languages
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            // Get all translations for this reason
            $translations = $this->translatedReasonModel->getAllTranslationsForReason($reasonId);

            // Organize existing translations by language code for quick lookup
            $existingTranslations = [];
            foreach ($translations as $translation) {
                $existingTranslations[$translation['language_code']] = $translation['reason'] ?? '';
            }

            // Build complete translations array with fallback to main table reason for default language
            $translatedData = [];
            $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';

            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $translatedData[$languageCode] = $existingTranslations[$languageCode] ?? '';

                // For default language, use main table reason as fallback if no translation exists
                if ($languageCode === $defaultLanguage && empty($translatedData[$languageCode])) {
                    $translatedData[$languageCode] = $reasonData['reason'] ?? '';
                }
            }

            $responseData = [
                'id' => $reasonData['id'],
                'needs_additional_info' => $reasonData['needs_additional_info'],
                'type' => $reasonData['type'],
                'translations' => $translatedData
            ];

            return successResponse(labels(DATA_FETCHED_SUCCESSFULLY, "Reason data fetched successfully"), false, $responseData, [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ReasonsForReportAndBlockChat.php - get_reason_data()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Process translated fields from POST data
     * 
     * @param array $postData POST data array
     * @return array Processed translated fields
     */
    private function processTranslatedFields(array $postData): array
    {
        $translatedFields = [
            'reason' => [],
        ];

        // Check if the data is already in the correct format (as objects with language keys)
        if (isset($postData['reason']) && is_array($postData['reason'])) {
            // Copy the data directly since it's already in the right structure
            $translatedFields['reason'] = $postData['reason'] ?? [];

            return $translatedFields;
        }

        // Fallback: Process form data in the old format (field[language] format)
        // Get languages from database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            // Process reason
            $reasonField = 'reason[' . $languageCode . ']';
            $reasonValue = $postData[$reasonField] ?? null;
            if (!empty($reasonValue)) {
                $translatedFields['reason'][$languageCode] = trim(esc($reasonValue));
            }
        }

        return $translatedFields;
    }

    /**
     * Store reason translations in the database
     * 
     * This private helper function uses the TranslatedReasonsForReportAndBlockChat_model
     * to store multi-language translations for reason fields
     * 
     * @param int $reasonId The reason ID to store translations for
     * @param array $translatedFields Array containing translated data organized by field and language
     * @return bool Success status
     * @throws Exception If translation storage fails
     */
    private function storeReasonTranslations(int $reasonId, array $translatedFields): bool
    {
        try {
            // Validate that we have a valid reason ID
            if (empty($reasonId)) {
                throw new Exception(labels("reason_id_is_required", "Reason ID is required for storing translations"));
            }

            // Get all available languages from database
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            if (empty($languages)) {
                throw new Exception(labels("no_languages_found_in_database", "No languages found in database"));
            }

            $successCount = 0;
            $totalLanguages = count($languages);

            // Process each language
            foreach ($languages as $language) {
                $languageCode = $language['code'];

                // Prepare translation data for this language
                $translationData = [];

                // Add reason translation if available
                if (isset($translatedFields['reason'][$languageCode]) && !empty(trim($translatedFields['reason'][$languageCode]))) {
                    $translationData['reason'] = trim(esc($translatedFields['reason'][$languageCode]));
                }

                // Only save if we have translation data for this language
                if (!empty($translationData)) {
                    $result = $this->translatedReasonModel->saveTranslatedDetails(
                        $reasonId,
                        $languageCode,
                        $translationData
                    );

                    if ($result) {
                        $successCount++;
                    } else {
                        // Log the failure but continue with other languages
                        log_message('error', "Failed to save translation for reason {$reasonId}, language {$languageCode}");
                    }
                } else {
                    // Count as success if no data to save (empty translations are valid)
                    $successCount++;
                }
            }

            // Return true if at least one translation was saved successfully
            return $successCount > 0;
        } catch (\Exception $e) {
            log_message('error', 'Exception in storeReasonTranslations: ' . $e->getMessage());
            throw $e;
        }
    }
}
