<?php

namespace App\Controllers\Admin;

use App\Controllers\Admin\Admin;
use App\Models\Settings;
use App\Models\ChatQuestions_model;
use App\Models\TranslatedChatQuestions_model;

class ChatSettings extends Admin
{
    protected $superadmin;
    private $settingsModel;

    public function __construct()
    {
        parent::__construct();
        $this->superadmin = $this->session->get('email');
        $this->settingsModel = new Settings();
        helper('ResponceServices');
        helper('function');
    }

    public function index()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            // Load settings for the view
            $chatFields = [
                'maxFilesOrImagesInOneMessage',
                'maxFileSizeInMBCanBeSent',
                'maxCharactersInATextMessage',
                'allow_pre_booking_chat',
                'allow_post_booking_chat',
                'enable_chat_image_upload',
                'enable_chat_file_upload',
            ];

            // Prefer chat_settings row, fall back to general_settings
            $chatSettings = get_settings('chat_settings', true);
            if (empty($chatSettings)) {
                $chatSettings = get_settings('general_settings', true);
            }

            if (!empty($chatSettings)) {
                foreach ($chatFields as $field) {
                    if (isset($chatSettings[$field])) {
                        $this->data[$field] = $chatSettings[$field];
                    }
                }
            }

            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
            $this->data['languages'] = $languages;

            setPageInfo($this->data, labels('chat_settings', 'Chat Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'chat_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ChatSettings.php - index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Save general configurations (message limits + chat feature toggles).
     */
    public function save_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            if (!$this->checkDemoMode()) {
                return redirect()->to('admin/settings/chat-settings')->withCookies();
            }

            $generalData = get_settings('general_settings', true);

            $textKeys = [
                'maxFilesOrImagesInOneMessage',
                'maxFileSizeInMBCanBeSent',
                'maxCharactersInATextMessage',
            ];

            $chatData = [];
            foreach ($textKeys as $key) {
                $value = array_key_exists($key, $_POST) ? $this->request->getPost($key) : ($generalData[$key] ?? '');
                $chatData[$key] = $value;
                $generalData[$key] = $value;
            }

            // Checkbox keys: present in POST = enabled (1), absent = disabled (0)
            $checkboxKeys = [
                'allow_pre_booking_chat',
                'allow_post_booking_chat',
                'enable_chat_image_upload',
                'enable_chat_file_upload',
            ];

            foreach ($checkboxKeys as $key) {
                $value = array_key_exists($key, $_POST) ? '1' : '0';
                $chatData[$key] = $value;
                $generalData[$key] = $value;
            }

            // Dual-write: save to both chat_settings and general_settings
            $this->settingsModel->upsert('chat_settings', json_encode($chatData));
            $this->settingsModel->upsert('general_settings', json_encode($generalData));

            $_SESSION['toastMessage'] = labels('Settings has been successfuly updated', 'Settings has been successfuly updated');
            $_SESSION['toastMessageType'] = 'success';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/settings/chat-settings')->withCookies();
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ChatSettings.php - save_settings()');
            $_SESSION['toastMessage'] = labels(SOMETHING_WENT_WRONG, 'Something Went Wrong');
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/settings/chat-settings')->withCookies();
        }
    }

    /**
     * Add a single question via AJAX.
     * Expects JSON body: { question_type, translations: {lang: text} }
     */
    public function add_question()
    {
        $response = \Config\Services::response();

        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $response->setJSON(['error' => true, 'message' => 'Unauthorized'])->setStatusCode(401);
            }

            if (!$this->checkDemoMode()) {
                return $response->setJSON(['error' => true, 'message' => labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR)]);
            }

            $body = $this->request->getJSON(true);
            $type = $body['question_type'] ?? '';
            $translations = $body['translations'] ?? [];
            $validTypes = ['pre_booking', 'customer_post_booking', 'provider_post_booking', 'customer_admin_support', 'provider_admin_support'];

            if (!in_array($type, $validTypes)) {
                return $response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
            }

            // Determine default language code
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            $defaultLangCode = !empty($languages) ? $languages[0]['code'] : 'en';
            $defaultText = $translations[$defaultLangCode] ?? '';

            if (empty(trim($defaultText))) {
                return $response->setJSON(['error' => true, 'message' => labels('default_language_required', 'The default language is required.')]);
            }

            $chatQuestionsModel = new ChatQuestions_model();
            $translatedModel = new TranslatedChatQuestions_model();

            // Get next sort_order for this type
            $maxOrder = $chatQuestionsModel->where('type', $type)->selectMax('sort_order')->first();
            $nextOrder = ($maxOrder['sort_order'] ?? -1) + 1;

            $questionId = $chatQuestionsModel->insert([
                'type'       => $type,
                'question'   => $defaultText,
                'sort_order' => $nextOrder,
            ]);

            if ($questionId && !empty($translations)) {
                $translatedModel->saveTranslations($questionId, $translations);
            }

            return $response->setJSON([
                'error'   => false,
                'message' => labels(DATA_SAVED_SUCCESSFULLY, 'Data saved successfully'),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ChatSettings.php - add_question()');
            return $response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
        }
    }

    /**
     * Update an existing question via AJAX.
     * Expects JSON body: { id, translations: {lang: text} }
     */
    public function update_question()
    {
        $response = \Config\Services::response();

        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $response->setJSON(['error' => true, 'message' => 'Unauthorized'])->setStatusCode(401);
            }

            if (!$this->checkDemoMode()) {
                return $response->setJSON(['error' => true, 'message' => labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR)]);
            }

            $body = $this->request->getJSON(true);
            $id = (int) ($body['id'] ?? 0);
            $translations = $body['translations'] ?? [];

            if ($id <= 0) {
                return $response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
            }

            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            $defaultLangCode = !empty($languages) ? $languages[0]['code'] : 'en';
            $defaultText = $translations[$defaultLangCode] ?? '';

            if (empty(trim($defaultText))) {
                return $response->setJSON(['error' => true, 'message' => labels('default_language_required', 'The default language is required.')]);
            }

            $chatQuestionsModel = new ChatQuestions_model();
            $translatedModel = new TranslatedChatQuestions_model();

            // Update base table
            $chatQuestionsModel->update($id, ['question' => $defaultText]);

            // Replace translations: delete old, insert new
            $translatedModel->where('chat_question_id', $id)->delete();
            if (!empty($translations)) {
                $translatedModel->saveTranslations($id, $translations);
            }

            return $response->setJSON([
                'error'   => false,
                'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ChatSettings.php - update_question()');
            return $response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
        }
    }

    /**
     * Delete a single question via AJAX.
     * Expects JSON body: { id }
     */
    public function delete_question()
    {
        $response = \Config\Services::response();

        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $response->setJSON(['error' => true, 'message' => 'Unauthorized'])->setStatusCode(401);
            }

            if (!$this->checkDemoMode()) {
                return $response->setJSON(['error' => true, 'message' => labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR)]);
            }

            $body = $this->request->getJSON(true);
            $id = (int) ($body['id'] ?? 0);

            if ($id <= 0) {
                return $response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
            }

            $chatQuestionsModel = new ChatQuestions_model();
            $chatQuestionsModel->delete($id); // CASCADE deletes translations

            return $response->setJSON([
                'error'   => false,
                'message' => labels(DATA_DELETED_SUCCESSFULLY, 'Data deleted successfully'),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ChatSettings.php - delete_question()');
            return $response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
        }
    }

    /**
     * List questions for bootstrap-table (server-side).
     * GET param: type (pre_booking|customer_post_booking|provider_post_booking|customer_admin_support|provider_admin_support)
     * Returns: { total, rows: [{id, question, languages, operations}] }
     */
    public function list_questions()
    {
        $response = \Config\Services::response();

        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $response->setJSON(['total' => 0, 'rows' => []]);
            }

            $type = $this->request->getGet('type') ?? '';
            $validTypes = ['pre_booking', 'customer_post_booking', 'provider_post_booking', 'customer_admin_support', 'provider_admin_support'];

            if (!in_array($type, $validTypes)) {
                return $response->setJSON(['total' => 0, 'rows' => []]);
            }

            $limit  = (int) ($this->request->getGet('limit') ?? 10);
            $offset = (int) ($this->request->getGet('offset') ?? 0);
            $sort   = $this->request->getGet('sort') ?? 'sort_order';
            $order  = strtoupper($this->request->getGet('order') ?? 'ASC');

            $allowedSorts = ['id', 'question', 'sort_order'];
            if (!in_array($sort, $allowedSorts)) $sort = 'sort_order';
            if (!in_array($order, ['ASC', 'DESC'])) $order = 'ASC';

            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            $defaultLangCode = !empty($languages) ? $languages[0]['code'] : 'en';

            $chatQuestionsModel = new ChatQuestions_model();
            $translatedModel = new TranslatedChatQuestions_model();

            $total = $chatQuestionsModel->where('type', $type)->countAllResults();

            $questions = $chatQuestionsModel->where('type', $type)
                ->orderBy($sort, $order)
                ->findAll($limit, $offset);

            $rows = [];
            if (!empty($questions)) {
                $ids = array_column($questions, 'id');
                $allTranslations = $translatedModel->getAllForMultipleQuestions($ids);

                foreach ($questions as $q) {
                    $translations = $allTranslations[$q['id']] ?? [];
                    $questionText = $translations[$defaultLangCode] ?? $q['question'];

                    $rows[] = [
                        'id'           => (int) $q['id'],
                        'question'     => $questionText,
                        'translations' => $translations,
                        'reorder_icon' => '<i class="fas fa-sort text-new-primary drag-handle"></i>',
                        'sort_order'   => (int) $q['sort_order'],
                        'operations'   => '<button type="button" class="btn btn-sm btn-outline-primary btn-edit-question mr-1" title="' . labels('edit', 'Edit') . '"><i class="fas fa-pencil-alt"></i></button>'
                            . '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-question" title="' . labels('delete', 'Delete') . '"><i class="fas fa-trash-alt"></i></button>',
                    ];
                }
            }

            return $response->setJSON(['total' => $total, 'rows' => $rows]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ChatSettings.php - list_questions()');
            return $response->setJSON(['total' => 0, 'rows' => []]);
        }
    }

    /**
     * Reorder questions via AJAX.
     * Expects JSON body: { ids: [id1, id2, ...] } in desired order.
     */
    public function reorder_questions()
    {
        $response = \Config\Services::response();

        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $response->setJSON(['error' => true, 'message' => 'Unauthorized'])->setStatusCode(401);
            }

            $body = $this->request->getJSON(true);
            $ids = $body['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                return $response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
            }

            $chatQuestionsModel = new ChatQuestions_model();

            foreach ($ids as $index => $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $chatQuestionsModel->update($id, ['sort_order' => $index]);
                }
            }

            return $response->setJSON([
                'error'   => false,
                'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/ChatSettings.php - reorder_questions()');
            return $response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
        }
    }

    /**
     * Check demo mode restriction. Returns false if blocked.
     */
    private function checkDemoMode(): bool
    {
        if ($this->superadmin == "superadmin@gmail.com") {
            return true;
        }

        if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
            $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return false;
        }

        return true;
    }
}
