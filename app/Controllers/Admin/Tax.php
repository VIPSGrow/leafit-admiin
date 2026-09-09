<?php

namespace App\Controllers\Admin;

use App\Models\Tax_model;
use App\Models\TranslatedTaxDetails_model;
use App\Models\Settings;

class Tax extends Admin
{
    public $validation, $taxes, $creator_id, $translatedTaxModel;
    protected $superadmin;
    private Settings $settingsModel;

    public function __construct()
    {
        parent::__construct();
        helper(['form', 'url']);
        $this->taxes              = new Tax_model();
        $this->translatedTaxModel = new TranslatedTaxDetails_model();
        $this->settingsModel      = new Settings();
        $this->validation         = \Config\Services::validation();
        $this->creator_id         = $this->userId;
        $this->superadmin         = $this->session->get('email');
        helper(['form', 'url', 'ResponceServices']);
    }

    /**
     * Display the tax management page
     * Loads languages for multi-language support
     */
    public function index()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('tax', 'Tax') . ' | ' . labels('admin_panel', 'Admin Panel'), 'tax');
            $this->data['taxes'] = fetch_details('taxes');

            // Fetch languages for translation support
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
            $this->data['languages'] = $languages;

            return view('backend/admin/template', $this->data);
        } else {
            return redirect('unauthorised');
        }
    }
    /**
     * Add a new tax with multi-language support
     * Stores tax title in both main table (default language) and translations table (all languages)
     */
    public function add_tax()
    {
        try {
            $permission = is_permitted($this->creator_id, 'create', 'tax');
            if ($permission) {
                if ($this->isLoggedIn && $this->userIsAdmin) {
                    // Process translated fields first
                    $translatedFields = $this->processTranslatedFields($_POST);

                    // Get default language title for validation
                    $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';
                    $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';

                    // Set validation rules with custom error messages
                    $this->validation->setRules(
                        [
                            'percentage' => [
                                'label' => 'percentage',
                                'rules' => 'required|trim',
                                'errors' => [
                                    'required' => labels('percentage_is_required', 'Percentage is required')
                                ]
                            ],
                        ]
                    );

                    // Collect all validation errors
                    $errors = [];

                    // Validate using CodeIgniter validation
                    if (!$this->validation->withRequest($this->request)->run()) {
                        $errors = $this->validation->getErrors();
                    }

                    // Manually validate title (since it comes from translated fields)
                    if (empty(trim($defaultTitle))) {
                        $errors['title'] = labels('title_is_required_for_default_language', 'Title is required for default language');
                    }

                    // Return all errors together if any exist
                    if (!empty($errors)) {
                        return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    // Check for unique tax name
                    $existingTax = fetch_details('taxes', ['title' => trim($defaultTitle)]);
                    if (!empty($existingTax)) {
                        return ErrorResponse(labels('tax_name_already_exists', 'Tax name already exists'), true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    $percentage = ($_POST['percentage']);

                    if ($percentage < 0 || $percentage > 100) {
                        return ErrorResponse(labels('percentage_must_be_between_0_and_100', 'Percentage must be between 0 and 100'), true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    // Store in main table (default language title for backward compatibility)
                    $data['title'] = trim($defaultTitle);
                    $data['percentage'] = $percentage;
                    $data['status'] = ($this->request->getPost('tax_status') == "on") ? 1 : 0;

                    if ($this->taxes->save($data)) {
                        $taxId = $this->taxes->getInsertID();

                        // Store translations for all languages
                        try {
                            $translationResult = $this->storeTaxTranslations($taxId, $translatedFields);

                            if (!$translationResult) {
                                log_message('warning', 'Some tax translations may not have been saved for tax ID: ' . $taxId);
                            }
                        } catch (\Exception $e) {
                            log_message('error', 'Exception in tax translation processing: ' . $e->getMessage());
                            // Don't fail the tax creation if translation processing fails
                        }

                        return successResponse(labels('tax_added_successfully', "Tax added successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                    } else {
                        return ErrorResponse(labels('please_try_again', "Please try again...."), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } else {
                    return redirect('unauthorised');
                }
            } else {
                return ErrorResponse(labels('not_permitted', "Sorry! You are not permitted to take this action"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Tax.php - add_tax()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'asc', $where = [])
    {

        try {

            $multipleWhere = '';
            $db = \Config\Database::connect();
            $currentLanguage = get_current_language();

            // Use the helper function to get taxes with translated names
            $whereConditions = [];
            if (isset($where) && !empty($where)) {
                $whereConditions = array_merge($whereConditions, $where);
            }

            // Get all taxes with translated names
            $allTaxes = get_taxes_with_translated_names($whereConditions, ['id', 'title', 'percentage', 'status', 'created_at']);

            // Apply search filter if provided
            if (isset($_GET['search']) and $_GET['search'] != '') {
                $search = $_GET['search'];
                $allTaxes = array_filter($allTaxes, function ($tax) use ($search) {
                    return stripos($tax['id'], $search) !== false ||
                        stripos($tax['title'], $search) !== false ||
                        stripos($tax['percentage'], $search) !== false;
                });
            }

            $total = count($allTaxes);

            // Apply sorting
            $sort = 'id';
            if (isset($_GET['sort'])) {
                if (in_array($_GET['sort'], ['id', 'title', 'percentage'])) {
                    $sort = $_GET['sort'];
                }
            }
            $order = "asc";
            if (isset($_GET['order'])) {
                $order = $_GET['order'];
            }

            // Sort the array
            usort($allTaxes, function ($a, $b) use ($sort, $order) {
                $aVal = $a[$sort];
                $bVal = $b[$sort];

                if (is_numeric($aVal) && is_numeric($bVal)) {
                    $result = $aVal <=> $bVal;
                } else {
                    $result = strcmp($aVal, $bVal);
                }

                return ($order === 'desc') ? -$result : $result;
            });

            // Apply pagination
            $limit = 10;
            $offset = 0;
            if (isset($_GET['limit'])) {
                $limit = $_GET['limit'];
            }
            if (isset($_GET['offset'])) {
                $offset = $_GET['offset'];
            }

            $offer_recored = array_slice($allTaxes, $offset, $limit);

            $bulkData = array();
            $bulkData['total'] = $total;
            $rows = array();
            $tempRow = array();

            // Get all tax IDs for batch translation fetching
            $taxIds = array_column($offer_recored, 'id');

            // Fetch all translations for these taxes in one query
            $allTranslations = [];
            if (!empty($taxIds)) {
                $translations = $this->translatedTaxModel->whereIn('tax_id', $taxIds)->findAll();
                foreach ($translations as $translation) {
                    $allTranslations[$translation['tax_id']][$translation['language_code']] = [
                        'title' => $translation['title']
                    ];
                }
            }

            // Fetch default language titles from main taxes table for edit modal
            // The main table stores titles in the default language
            $defaultLanguage = get_default_language();
            $defaultTitles = [];
            if (!empty($taxIds)) {
                $taxRecords = $this->taxes->whereIn('id', $taxIds)->findAll();
                foreach ($taxRecords as $taxRecord) {
                    $defaultTitles[$taxRecord['id']] = $taxRecord['title'];
                }
            }

            foreach ($offer_recored as $row) {
                // Operations dropdown: same pattern as partners list (ellipsis button + dropdown menu)
                $operations = '<div class="dropdown">
                    <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <button class="btn btn-secondary btn-sm px-3" type="button"><i class="fas fa-ellipsis-v"></i></button>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                        <a class="dropdown-item edit_taxes" href="#" data-id="' . (int) $row['id'] . '" data-toggle="modal" data-target="#update_modal" onclick="taxes_id(this)"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit', 'Edit') . '</a>
                        <a class="dropdown-item remove_taxes" href="#"><i class="fa fa-trash mr-1 text-danger"></i>' . labels('delete', 'Delete') . '</a>
                    </div>
                </div>';
                $status = ($row['status'] == 0) ?
                    '<span class="badge badge-danger">' . labels('deactive', 'Deactive') . '</span>' :
                    ' <span class="badge badge-success">' . labels('active', 'Active') . '</span>';

                $tempRow['id'] = $row['id'];
                $tempRow['title'] = $row['title']; // This now contains the translated title for current language
                $tempRow['percentage'] = $row['percentage'];
                $tempRow['status'] = $status;
                $tempRow['og_status'] = $row['status'];
                $tempRow['operations'] = $operations;

                // Include translation data for this tax
                $tempRow['translations'] = $allTranslations[$row['id']] ?? [];

                // Include default language title from main table for edit modal
                // This ensures the edit modal always shows the correct default language title
                $tempRow['default_title'] = $defaultTitles[$row['id']] ?? '';

                $rows[] = $tempRow;
            }
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Tax.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function remove_taxes()
    {
        try {
            $permission = is_permitted($this->creator_id, 'delete', 'tax');
            if ($permission) {
                if ($this->isLoggedIn && $this->userIsAdmin) {
                    $id = $this->request->getPost('id');
                    $db = \Config\Database::connect();
                    $builder = $db->table('taxes');

                    // First, delete all associated translations
                    try {
                        $translationResult = $this->translatedTaxModel->deleteTaxTranslations($id);
                        if (!$translationResult) {
                            log_message('warning', "Failed to delete some translations for tax ID: {$id}");
                        }
                    } catch (\Exception $e) {
                        log_message('error', "Exception while deleting tax translations for ID {$id}: " . $e->getMessage());
                        // Continue with tax deletion even if translation deletion fails
                    }

                    // Then delete the main tax record
                    if ($builder->delete(['id' => $id])) {
                        return successResponse(labels('tax_deleted_successfully', "Tax deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                    } else {
                        return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } else {
                    return redirect('unauthorised');
                }
            } else {
                return ErrorResponse(labels('not_permitted', "Sorry! You are not permitted to take this action"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Tax.php - remove_taxes()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    /**
     * Edit existing tax with multi-language support
     * Updates tax in main table and translations in translations table
     */
    public function edit_taxes()
    {
        try {
            $permission = is_permitted($this->creator_id, 'update', 'tax');
            if ($permission) {
                $id = $this->request->getPost('id');
                $db = \Config\Database::connect();
                $builder = $db->table('taxes');
                if ($this->isLoggedIn && $this->userIsAdmin) {
                    $id = $this->request->getPost('id');
                    $percentage = $this->request->getPost('percentage');

                    // Process translated fields
                    $translatedFields = $this->processTranslatedFields($_POST);

                    // Get default language title for the main table
                    $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';
                    $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';

                    // Validate that default language title is provided
                    if (empty(trim($defaultTitle))) {
                        return ErrorResponse(labels('title_is_required_for_default_language', 'Title is required for default language'), true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    // Check for unique tax name (exclude current record)
                    $existingTax = fetch_details('taxes', ['title' => trim($defaultTitle)]);
                    $existingTax = array_filter($existingTax, function ($tax) use ($id) {
                        return $tax['id'] != $id;
                    });
                    if (!empty($existingTax)) {
                        return ErrorResponse(labels('tax_name_already_exists', 'Tax name already exists'), true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    if ($percentage < 0 || $percentage > 100) {
                        return ErrorResponse(labels('percentage_must_be_between_0_and_100', 'Percentage must be between 0 and 100'), true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    // Update main table with default language title
                    $data['title'] = trim($defaultTitle);
                    $data['percentage'] = $percentage;
                    $data['status'] = ($this->request->getPost('tax_status_edit') == "on") ? 1 : 0;

                    if ($builder->update($data, ['id' => $id])) {
                        // Update translations for all languages
                        try {
                            $translationResult = $this->storeTaxTranslations($id, $translatedFields);

                            if (!$translationResult) {
                                log_message('warning', 'Some tax translations may not have been updated for tax ID: ' . $id);
                            }
                        } catch (\Exception $e) {
                            log_message('error', 'Exception in tax translation processing during update: ' . $e->getMessage());
                            // Don't fail the tax update if translation processing fails
                        }

                        return successResponse(labels('tax_updated_successfully', "Tax updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                    } else {
                        return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } else {
                    return redirect('unauthorised');
                }
            } else {
                return ErrorResponse(labels('not_permitted', "Sorry! You are not permitted to take this action"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Tax.php - edit_taxes()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Process translated fields from POST data
     * Extracts title translations for all languages
     * 
     * @param array $postData POST data containing title[language_code]
     * @return array Associative array with structure: ['title' => [language_code => value]]
     */
    private function processTranslatedFields(array $postData): array
    {
        $translatedFields = [
            'title' => [],
        ];

        // Check if the data is already in the correct format (as objects with language keys)
        if (isset($postData['title']) && is_array($postData['title'])) {
            // Copy the data directly since it's already in the right structure
            $translatedFields['title'] = $postData['title'] ?? [];
            return $translatedFields;
        }

        // Fallback: Process form data in the old format (field[language] format)
        // Get languages from database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            // Process title
            $titleField = 'title[' . $languageCode . ']';
            $titleValue = $postData[$titleField] ?? null;
            if (!empty($titleValue)) {
                $translatedFields['title'][$languageCode] = trim($titleValue);
            }
        }

        return $translatedFields;
    }

    public function get_tax_translations()
    {
        try {
            $taxId = $this->request->getPost('tax_id');

            if (empty($taxId)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('tax_id_required', 'Tax ID is required'),
                    'data' => []
                ]);
            }

            // Get all translations for this tax
            $translations = $this->translatedTaxModel->getAllTranslationsForTax($taxId);

            // Organize translations by language code for easier access
            $translationsByLanguage = [];

            foreach ($translations as $translation) {
                $translationsByLanguage[$translation['language_code']] = [
                    'title' => $translation['title']
                ];
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('translations_fetched_successfully', 'Translations fetched successfully'),
                'data' => [
                    'translations' => $translationsByLanguage
                ],
                'csrfTokenName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Tax.php - get_tax_translations()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, "Something Went Wrong"),
                'data' => [],
                'csrfTokenName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        }
    }

    /**
     * Store tax translations in the database
     * 
     * Saves translated tax titles for all languages in the translated_tax_details table
     * Also stores default language translation in main taxes table
     * 
     * @param int $taxId The tax ID to store translations for
     * @param array $translatedFields Array containing translated data organized by field and language
     * @return bool Success status
     * @throws Exception If translation storage fails
     */
    private function storeTaxTranslations(int $taxId, array $translatedFields): bool
    {
        try {
            // Validate that we have a valid tax ID
            if (empty($taxId)) {
                throw new \Exception(labels('tax_id_is_required_for_storing_translations', 'Tax ID is required for storing translations'));
            }

            // Get all available languages from database
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            if (empty($languages)) {
                throw new \Exception(labels('no_languages_found_in_database', 'No languages found in database'));
            }

            $successCount = 0;
            $totalLanguages = count($languages);

            // Process each language
            foreach ($languages as $language) {
                $languageCode = $language['code'];

                // Prepare translation data for this language
                $translationData = [];

                // Add title translation if available
                if (isset($translatedFields['title'][$languageCode]) && !empty(trim($translatedFields['title'][$languageCode]))) {
                    $translationData['title'] = trim($translatedFields['title'][$languageCode]);
                }

                // Only save if we have translation data for this language
                if (!empty($translationData)) {
                    $result = $this->translatedTaxModel->saveTranslatedDetails(
                        $taxId,
                        $languageCode,
                        $translationData
                    );

                    if ($result) {
                        $successCount++;
                    } else {
                        // Log the failure but continue with other languages
                        log_message('error', "Failed to save translation for tax {$taxId}, language {$languageCode}");
                    }
                }
            }

            // Return true if at least one translation was saved successfully
            return $successCount > 0;
        } catch (\Exception $e) {
            log_message('error', 'Exception in storeTaxTranslations: ' . $e->getMessage());
            throw $e;
        }
    }

    public function system_tax_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage']     = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType'] = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }
                $updatedData = $this->request->getPost();
                unset($updatedData['files']);
                unset($updatedData[csrf_token()]);
                unset($updatedData['update']);
                if ($this->settingsModel->upsert('system_tax_settings', json_encode($updatedData))) {
                    $_SESSION['toastMessage']     = labels('System Tax settings successfully updated', 'System Tax settings successfully updated');
                    $_SESSION['toastMessageType'] = 'success';
                } else {
                    $_SESSION['toastMessage']     = labels('Unable to update system tax settings', 'Unable to update system tax settings');
                    $_SESSION['toastMessageType'] = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/system_tax_settings')->withCookies();
            }
            $row = $this->settingsModel->where('variable', 'system_tax_settings')->first();
            if ($row) {
                $settings   = json_decode($row['value'], true);
                $this->data = array_merge($this->data, $settings ?? []);
            }
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], '', '0', 'id', 'ASC');
            $this->data['languages']        = $languages;
            $taxSettings                    = json_decode($row['value'] ?? '{}', true) ?: [];
            $this->data['show_on_checkout'] = $taxSettings['show_on_checkout'] ?? '0';
            setPageInfo($this->data, labels('System Tax Settings', 'System Tax Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'system_tax_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/Tax.php - system_tax_settings()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function update_tax_settings()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $permission = is_permitted($this->userId, 'update', 'tax');
            if ($permission) {
                if ($this->isLoggedIn && $this->userIsAdmin) {
                    $show_on_checkout = $this->request->getPost('show_on_checkout');
                    if ($this->settingsModel->upsert('system_tax_settings', json_encode(['show_on_checkout' => $show_on_checkout]))) {
                        return JsonSuccess(labels('tax_settings_updated_successfully', 'Tax settings updated successfully'));
                    } else {
                        return JsonError(labels(ERROR_OCCURED, 'An error occurred'));
                    }
                } else {
                    return redirect('unauthorised');
                }
            } else {
                return JsonError(labels('not_permitted', 'Sorry! You are not permitted to take this action'));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/Tax.php - update_tax_settings()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
}
