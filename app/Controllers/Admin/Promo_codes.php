<?php

namespace App\Controllers\Admin;

use App\Models\Promo_code_model;
use App\Models\TranslatedPromocodeModel;
use App\Services\utility\FileService;

class Promo_codes extends Admin
{
    public $orders, $creator_id, $ionAuth;
    protected $superadmin;
    protected Promo_code_model $promo_codes;
    protected TranslatedPromocodeModel $translatedPromocodeModel;

    protected FileService $fileService;

    protected $db;
    protected $validation;

    public function __construct()
    {
        parent::__construct();
        $this->promo_codes = new Promo_code_model();
        $this->translatedPromocodeModel = new TranslatedPromocodeModel();
        $this->creator_id = $this->userId;
        $this->db = \Config\Database::connect();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->validation = \Config\Services::validation();
        $this->fileService = new FileService();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
    }
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('promo_codes', 'Promo Codes') . ' | ' . labels('admin_panel', 'Admin Panel'), 'promo_codes');
        $partner_data = $this->db->table('users u')
            ->select('u.id,u.username,pd.company_name')
            ->join('partner_details pd', 'pd.partner_id = u.id')
            ->where('is_approved', '1')
            ->get()
            ->getResultArray();

        // Get current language for translations
        $current_language = get_current_language();
        $default_language = get_default_language();

        // OPTIMIZATION: Batch fetch all partner translations at once instead of individual queries
        $partnerTranslations = [];
        if (!empty($partner_data)) {
            $partner_ids = array_column($partner_data, 'id');

            try {
                $translationModel = new \App\Models\TranslatedPartnerDetails_model();
                $partnerTranslations = $translationModel->getAllTranslationsForPartners($partner_ids);

                // Store original values for fallback and apply translations for current language
                foreach ($partner_data as &$partner) {
                    $partner['original_company_name'] = $partner['company_name'] ?? '';
                    $partner['original_username'] = $partner['username'] ?? '';

                    // Apply translations for the current language
                    if (isset($partnerTranslations[$partner['id']][$current_language])) {
                        $currentTranslation = $partnerTranslations[$partner['id']][$current_language];

                        if (!empty($currentTranslation['company_name'])) {
                            $partner['company_name'] = $currentTranslation['company_name'];
                        }

                        if (!empty($currentTranslation['username'])) {
                            $partner['username'] = $currentTranslation['username'];
                        }
                    }
                }
                unset($partner);
            } catch (\Exception $e) {
                // Log error but continue with original data
                log_message('error', 'Error getting batch partner translations: ' . $e->getMessage());
            }
        }

        // Build translated display string for current language (same as add form)
        foreach ($partner_data as &$partner) {
            $partnerId = $partner['id'];
            $companyName = $partner['original_company_name'] ?? $partner['company_name'] ?? '';
            $username = $partner['original_username'] ?? $partner['username'] ?? '';

            // Use current language translation if available
            if (isset($partnerTranslations[$partnerId][$current_language])) {
                $translationEntry = $partnerTranslations[$partnerId][$current_language];
                if (!empty($translationEntry['company_name'])) {
                    $companyName = $translationEntry['company_name'];
                }
                if (!empty($translationEntry['username'])) {
                    $username = $translationEntry['username'];
                }
            }

            $partner['display_name'] = trim($companyName . ' - ' . $username);
        }
        unset($partner);

        $this->data['partner_name'] = $partner_data;
        $this->data['partner_translations'] = $partnerTranslations;
        $this->data['current_language'] = $current_language;
        $this->data['default_language'] = $default_language;

        // fetch languages
        $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
        $this->data['languages'] = $languages;
        return view('backend/admin/template', $this->data);
    }
    public function list()
    {
        try {
            $promocode_model = new Promo_code_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            // Map frontend field names to actual database column names
            $sortable_fields = [
                'id' => 'pc.id',
                'promo_code' => 'pc.promo_code',
                'translated_partner_name' => 'pd.company_name',  // Map computed field to actual column (sort by company name)
                'partner_name' => 'pd.company_name',
                'start_date' => 'pc.start_date',
                'end_date' => 'pc.end_date',
                'discount' => 'pc.discount',
                'status_badge' => 'pc.status',  // Map computed field to actual column
                'status' => 'pc.status',
                'created_at' => 'pc.created_at'
            ];

            // Check if the requested sort field is in our mapping
            if (isset($sortable_fields[$sort])) {
                $sort = $sortable_fields[$sort];
            } else {
                // Default to id if unknown field
                $sort = 'pc.id';
            }

            $data = $promocode_model->admin_list(false, $search, $limit, $offset, $sort, $order);
            return json_encode($data);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Promo_codes.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function delete_promo_code()
    {
        try {
            $permission = is_permitted($this->creator_id, 'delete', 'promo_code');
            if (!$permission) {
                return NoPermission();
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $id = $this->request->getPost('id');
            $db = \Config\Database::connect();
            $builder = $db->table('promo_codes');
            $old_image = fetch_details('promo_codes', ['id' => $id], ['image']);
            $old_image = !empty($old_image) ? $old_image[0]['image'] : null;

            $this->fileService->delete(folder: 'promocodes', path: $old_image);

            // Delete translations first
            $this->translatedPromocodeModel->deleteTranslations($id);

            if ($builder->delete(['id' => $id])) {
                return successResponse("Promo Codes section deleted successfully", false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse("An error occurred during deleting this item", true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Promo_codes.php - delete_promo_code()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function add()
    {
        try {
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return redirect('admin/login');
            } else {
                setPageInfo($this->data, labels('promo_codes', 'Promo Codes') . ' | ' . labels('admin_panel', 'Admin Panel'), 'add_promocode');
                // Get partner data with translations
                $partner_data = $this->db->table('users u')
                    ->select('u.id,u.username,pd.company_name')
                    ->join('partner_details pd', 'pd.partner_id = u.id')
                    ->where('is_approved', '1')
                    ->get()->getResultArray();

                // Get current language for translations
                $current_language = get_current_language();

                $partnerTranslations = [];
                $partnerTranslations = [];
                // OPTIMIZATION: Batch fetch all partner translations at once instead of individual queries
                if (!empty($partner_data)) {
                    $partner_ids = array_column($partner_data, 'id');

                    try {
                        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
                        $partnerTranslations = $translationModel->getAllTranslationsForPartners($partner_ids);

                        // Apply translations for the current language & keep originals for fallbacks
                        foreach ($partner_data as &$partner) {
                            $partner['original_company_name'] = $partner['company_name'] ?? '';
                            $partner['original_username'] = $partner['username'] ?? '';

                            if (isset($partnerTranslations[$partner['id']][$current_language])) {
                                $currentTranslation = $partnerTranslations[$partner['id']][$current_language];

                                if (!empty($currentTranslation['company_name'])) {
                                    $partner['company_name'] = $currentTranslation['company_name'];
                                }

                                if (!empty($currentTranslation['username'])) {
                                    $partner['username'] = $currentTranslation['username'];
                                }
                            }
                        }
                        unset($partner);
                    } catch (\Exception $e) {
                        // Log error but continue with original data
                        log_message('error', 'Error getting batch partner translations: ' . $e->getMessage());
                    }
                }

                // fetch languages so UI tabs + labels keep consistent ordering
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
                $languageCodes = array_column($languages, 'code');
                if (empty($languageCodes)) {
                    $languageCodes = [$current_language];
                }

                // Build translated display string only for the current language (per requirement)
                foreach ($partner_data as &$partner) {
                    $partnerId = $partner['id'];
                    $companyName = $partner['original_company_name'] ?? $partner['company_name'] ?? '';
                    $username = $partner['original_username'] ?? $partner['username'] ?? '';

                    if (isset($partnerTranslations[$partnerId][$current_language])) {
                        $translationEntry = $partnerTranslations[$partnerId][$current_language];
                        if (!empty($translationEntry['company_name'])) {
                            $companyName = $translationEntry['company_name'];
                        }
                        if (!empty($translationEntry['username'])) {
                            $username = $translationEntry['username'];
                        }
                    }

                    $partner['display_name'] = trim($companyName . ' - ' . $username);
                }
                unset($partner);

                $this->data['partner_name'] = $partner_data;
                $this->data['current_language'] = $current_language;
                $this->data['languages'] = $languages;
                return view('backend/admin/template', $this->data);
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Promo_codes.php - add()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function save()
    {
        try {
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return redirect('unauthorised');
            } else {
                if (isset($_POST) && !empty($_POST)) {
                    $repeat_usage = isset($_POST['repeat_usage']) ? $_POST['repeat_usage'] : '';
                    $id = isset($_POST['promo_id']) ? $_POST['promo_id'] : '';
                    // Get default language code for validation
                    $default_language = $this->getDefaultLanguageCode();

                    $validationRules = [
                        // Make provider selection mandatory so promo codes always belong to someone
                        'partner' => ["rules" => 'required', "errors" => ["required" => labels('please_select_provider', 'Please select a provider')]],
                        'promo_code' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_PROMO_CODE_NAME, "Please enter promo code name")]],
                        'start_date' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_START_DATE, "Please select start date")]],
                        'end_date' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_END_DATE, "Please select end date")]],
                        'no_of_users' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_NUMBER_OF_USERS, "Please enter number of users"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_NUMBER_OF_USERS, "Please enter numeric value for number of users"),    "greater_than" => labels(NUMBER_OF_USERS_MUST_BE_GREATER_THAN_0, "number of users must be greater than 0"),]],
                        'minimum_order_amount' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_MINIMUM_ORDER_AMOUNT, "Please enter minimum order amount"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MINIMUM_ORDER_AMOUNT, "Please enter numeric value for minimum order amount"),    "greater_than" => labels(MINIMUM_ORDER_AMOUNT_MUST_BE_GREATER_THAN_0, "minimum order amount must be greater than 0"),]],
                        'discount' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_DISCOUNT, "Please enter discount"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DISCOUNT, "Please enter numeric value for discount"),    "greater_than" => labels(DISCOUNT_MUST_BE_GREATER_THAN_0, "discount must be greater than 0"),]],
                        'max_discount_amount' => ["rules" => 'permit_empty|numeric|greater_than[0]', "errors" => ["numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MAX_DISCOUNT_AMOUNT, "Please enter numeric value for max discount amount"),    "greater_than" => labels(DISCOUNT_AMOUNT_MUST_BE_GREATER_THAN_0, "discount amount must be greater than 0"),]],
                        "message.{$default_language}" => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_MESSAGE_FOR_DEFAULT_LANGUAGE, "Please enter message for default language")]],
                    ];
                    if ($repeat_usage == 'on' && empty($id) && $id == '') {
                        $validationRules = array_merge($validationRules, [
                            'no_of_repeat_usage' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_NUMBER_OF_REPEAT_USAGE, "Please enter number of repeat usage"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_NUMBER_OF_REPEAT_USAGE, "Please enter numeric value for number of repeat usage"),    "greater_than" => labels(NUMBER_OF_REPEAT_USAGE_MUST_BE_GREATER_THAN_0, "number of repeat usage must be greater than 0"),]],
                        ]);
                    }
                    $old_image_path = $this->request->getPost('old_image');
                    if (empty($id) && empty($old_image_path)) {
                        $validationRules['image'] = ["rules" => 'uploaded[image]', "errors" => ["uploaded" => labels(PLEASE_UPLOAD_AN_IMAGE_FILE, "Please upload an image file")]];
                    }

                    if (!$this->validate($validationRules)) {
                        $errors = $this->validator->getErrors();
                        return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    // Check for duplicate promocode
                    // This prevents saving duplicate promocodes when duplicating without changes
                    $promo_code_value = $this->request->getVar('promo_code');
                    $existing_promocode = fetch_details('promo_codes', ['promo_code' => $promo_code_value], ['id']);

                    // If a promocode with the same code exists and it's not the current one being updated
                    if (!empty($existing_promocode) && (empty($id) || $existing_promocode[0]['id'] != $id)) {
                        return ErrorResponse([
                            'promo_code' => labels(
                                'promo_code_already_exists',
                                'A promocode with this code already exists. Please use a different code.'
                            )
                        ], true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    // Extract fields
                    $data = $this->request->getPost();

                    $discountType    = $data['discount_type'] ?? '';
                    $discount        = floatval($data['discount'] ?? 0);
                    $minOrder        = floatval($data['minimum_order_amount'] ?? 0);
                    $maxDiscount     = floatval($data['max_discount_amount'] ?? 0);


                    // ----------------------------------------------------
                    //  SCENARIO A: AMOUNT TYPE
                    // ----------------------------------------------------
                    if ($discountType === 'amount') {

                        // Discount cannot be greater than Minimum Booking Amount
                        if ($discount > $minOrder) {
                            return ErrorResponse([
                                'discount' => labels(
                                    'discount_cannot_exceed_minimum_order_amount',
                                    'Discount amount cannot be greater than the minimum booking amount'
                                )
                            ], true, [], [], 200, csrf_token(), csrf_hash());
                        }

                        // Max Discount Amount should NOT be required → ignore it
                        unset($data['max_discount_amount']);
                    }


                    // ----------------------------------------------------
                    //  SCENARIO B: PERCENTAGE TYPE
                    // ----------------------------------------------------
                    if ($discountType === 'percentage') {

                        // Discount cannot exceed 100%
                        if ($discount > 100) {
                            return ErrorResponse([
                                'discount' => labels(
                                    'percentage_discount_cannot_exceed_100',
                                    'Percentage discount cannot be greater than 100%'
                                )
                            ], true, [], [], 200, csrf_token(), csrf_hash());
                        }

                        // // Max Discount Amount must not exceed Minimum Booking Amount
                        // if ($maxDiscount > $minOrder) {
                        //     return ErrorResponse([
                        //         'max_discount_amount' => labels(
                        //             'max_discount_amount_cannot_exceed_minimum_order_amount',
                        //             'Max discount amount cannot be greater than the minimum booking amount'
                        //         )
                        //     ], true, [], [], 200, csrf_token(), csrf_hash());
                        // }
                    }
                    $promo_id = isset($_POST['promo_id']) ? $_POST['promo_id'] : '';
                    $uploadedFile = $this->request->getFile('image');

                    if ($uploadedFile !== null && $uploadedFile->isValid()) {
                        $uploadResult = $this->fileService->upload(file: $uploadedFile, folder: 'promocodes');
                        if ($uploadResult['error']) {
                            return ErrorResponse($uploadResult['message'], true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $image = $uploadResult['path'] ?? null;
                    } elseif (!empty($old_image_path)) {
                        $image = $this->fileService->copy('promocodes', $old_image_path);
                    } else {
                        $image = null;
                    }

                    if (empty($image)) {
                        return ErrorResponse(labels(PLEASE_UPLOAD_AN_IMAGE_FILE, "Please upload an image file"), true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    $promocode_model = new Promo_code_model();
                    $repeat_usage = isset($_POST['repeat_usage']) ? "1" : "0";
                    $status = isset($_POST['status']) ? "1" : "0";
                    $users = isset($_POST['no_of_users']) ?  $this->request->getVar('no_of_users') : "1";
                    // Get default language message for base table
                    $defaultLanguage = $this->getDefaultLanguageCode();
                    $messages = $this->request->getPost('message');
                    $defaultMessage = isset($messages[$defaultLanguage]) ? $this->removeScript(trim($messages[$defaultLanguage])) : '';

                    $promocode = array(
                        'id' => $promo_id,
                        'partner_id' => $this->request->getVar('partner'),
                        'promo_code' => $this->request->getVar('promo_code'),
                        'start_date' => $this->request->getVar('start_date'),
                        'end_date' => $this->request->getVar('end_date'),
                        'no_of_users' => $users,
                        'minimum_order_amount' => $this->request->getVar('minimum_order_amount'),
                        'max_discount_amount' => $this->request->getVar('max_discount_amount'),
                        'discount' => $this->request->getVar('discount'),
                        'discount_type' => $this->request->getVar('discount_type'),
                        'repeat_usage' => $repeat_usage,
                        'no_of_repeat_usage' => $this->request->getVar('no_of_repeat_usage'),
                        'image' => $image,
                        'status' => $status,
                        // Store default language message in main table (following Blogs pattern)
                        'message' => $defaultMessage,
                    );

                    // Save the promocode first to get the ID
                    $promocode_model->save($promocode);

                    // Get the promocode ID (either existing or newly created)
                    $promocode_id = $promo_id ?: $promocode_model->getInsertID();

                    // Save translations
                    $this->savePromocodeTranslations($promocode_id);

                    // Send notifications only for new promo codes (not updates)
                    // When admin adds a promo code, notify the provider
                    if (empty($promo_id)) {
                        try {
                            $language = get_current_language_from_request();

                            // Get provider details with translation support
                            $provider_id = $this->request->getVar('partner');
                            $provider_name = 'Provider';
                            $partner_details = fetch_details('partner_details', ['partner_id' => $provider_id], ['company_name']);
                            if (!empty($partner_details)) {
                                $defaultLanguage = get_default_language();
                                $translationModel = new \App\Models\TranslatedPartnerDetails_model();
                                $translatedPartnerDetails = $translationModel->getTranslatedDetails($provider_id, $defaultLanguage);
                                if (!empty($translatedPartnerDetails) && !empty($translatedPartnerDetails['company_name'])) {
                                    $provider_name = $translatedPartnerDetails['company_name'];
                                } else {
                                    $provider_name = $partner_details[0]['company_name'] ?? $provider_name;
                                }
                            }

                            // Prepare context data for notification templates
                            $notificationContext = [
                                'provider_name' => $provider_name,
                                'provider_id' => $provider_id,
                                'promo_code' => $this->request->getVar('promo_code'),
                                'promo_code_id' => $promocode_id,
                                'discount' => $this->request->getVar('discount'),
                                'discount_type' => $this->request->getVar('discount_type'),
                                'minimum_order_amount' => $this->request->getVar('minimum_order_amount'),
                                'max_discount_amount' => $this->request->getVar('max_discount_amount'),
                                'start_date' => $this->request->getVar('start_date'),
                                'end_date' => $this->request->getVar('end_date'),
                                'no_of_users' => $users,
                                'include_logo' => true, // Include logo in email templates
                            ];

                            // Queue notifications to the provider via all channels
                            queue_notification_service(
                                eventType: 'promo_code_added',
                                recipients: ['user_id' => $provider_id],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'], // All channels
                                    'language' => $language,
                                    'platforms' => ['android', 'ios', 'web', 'provider_panel'] // Provider platforms
                                ]
                            );
                            // log_message('info', '[PROMO_CODE_ADDED] Provider notification result (admin): ' . json_encode($result));
                        } catch (\Throwable $notificationError) {
                            // Log error but don't fail the promo code creation
                            log_message('error', '[PROMO_CODE_ADDED] Notification error trace (admin): ' . $notificationError->getTraceAsString());
                        }
                    }

                    return successResponse("Promocode saved successfully", false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return redirect()->back();
                }
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Promo_codes.php - save()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function update()
    {
        try {
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return redirect('unauthorised');
            } else {
                if (isset($_POST) && !empty($_POST)) {
                    $repeat_usage = isset($_POST['repeat_usage']) ? $_POST['repeat_usage'] : '';
                    $id = isset($_POST['promo_id']) ? $_POST['promo_id'] : '';
                    // Get default language code for validation
                    $default_language = $this->getDefaultLanguageCode();

                    $validation_rules = [
                        // Guard against orphan promo codes by enforcing provider selection
                        'partner' => ["rules" => 'required', "errors" => ["required" => labels('please_select_provider', 'Please select a provider')]],
                        'promo_code' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_PROMO_CODE_NAME, "Please enter promo code name")]],
                        'start_date' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_START_DATE, "Please select start date")]],
                        'end_date' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_END_DATE, "Please select end date")]],
                        'no_of_users' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_NUMBER_OF_USERS, "Please enter number of users"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_NUMBER_OF_USERS, "Please enter numeric value for number of users"),    "greater_than" => labels(NUMBER_OF_USERS_MUST_BE_GREATER_THAN_0, "number of users must be greater than 0"),]],
                        'minimum_order_amount' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_MINIMUM_ORDER_AMOUNT, "Please enter minimum order amount"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MINIMUM_ORDER_AMOUNT, "Please enter numeric value for minimum order amount"),    "greater_than" => labels(MINIMUM_ORDER_AMOUNT_MUST_BE_GREATER_THAN_0, "minimum order amount must be greater than 0"),]],
                        'discount' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_DISCOUNT, "Please enter discount"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DISCOUNT, "Please enter numeric value for discount"),    "greater_than" => labels(DISCOUNT_MUST_BE_GREATER_THAN_0, "discount must be greater than 0"),]],
                        'max_discount_amount' => ["rules" => 'permit_empty|numeric|greater_than[0]', "errors" => ["numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MAX_DISCOUNT_AMOUNT, "Please enter numeric value for max discount amount"),    "greater_than" => labels(DISCOUNT_AMOUNT_MUST_BE_GREATER_THAN_0, "discount amount must be greater than 0"),]],
                        "message.{$default_language}" => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_MESSAGE_FOR_DEFAULT_LANGUAGE, "Please enter message for default language")]],
                    ];
                    if ($repeat_usage == 'on') {
                        $validation_rules = array_merge($validation_rules, [
                            'no_of_repeat_usage' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_NUMBER_OF_REPEAT_USAGE, "Please enter number of repeat usage"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_NUMBER_OF_REPEAT_USAGE, "Please enter numeric value for number of repeat usage"),    "greater_than" => labels(NUMBER_OF_REPEAT_USAGE_MUST_BE_GREATER_THAN_0, "number of repeat usage must be greater than 0"),]],
                        ]);
                    }

                    $this->validation->setRules($validation_rules);
                    if (!$this->validation->withRequest($this->request)->run()) {
                        $errors = $this->validation->getErrors();
                        return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    // Check for duplicate promocode
                    // This prevents saving duplicate promocodes when duplicating without changes
                    $promo_code_value = $this->request->getVar('promo_code');
                    $existing_promocode = fetch_details('promo_codes', ['promo_code' => $promo_code_value], ['id']);

                    // If a promocode with the same code exists and it's not the current one being updated
                    if (!empty($existing_promocode) && (empty($id) || $existing_promocode[0]['id'] != $id)) {
                        return ErrorResponse([
                            'promo_code' => labels(
                                'promo_code_already_exists',
                                'A promocode with this code already exists. Please use a different code.'
                            )
                        ], true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    // Extract fields
                    $data = $this->request->getPost();

                    $discountType    = $data['discount_type'] ?? '';
                    $discount        = floatval($data['discount'] ?? 0);
                    $minOrder        = floatval($data['minimum_order_amount'] ?? 0);
                    $maxDiscount     = floatval($data['max_discount_amount'] ?? 0);


                    // ----------------------------------------------------
                    //  SCENARIO A: AMOUNT TYPE
                    // ----------------------------------------------------
                    if ($discountType === 'amount') {

                        // Discount cannot be greater than Minimum Booking Amount
                        if ($discount > $minOrder) {
                            return ErrorResponse([
                                'discount' => labels(
                                    'discount_cannot_exceed_minimum_order_amount',
                                    'Discount amount cannot be greater than the minimum booking amount'
                                )
                            ], true, [], [], 200, csrf_token(), csrf_hash());
                        }

                        // Max Discount Amount should NOT be required → ignore it
                        unset($data['max_discount_amount']);
                    }


                    // ----------------------------------------------------
                    //  SCENARIO B: PERCENTAGE TYPE
                    // ----------------------------------------------------
                    if ($discountType === 'percentage') {

                        // Discount cannot exceed 100%
                        if ($discount > 100) {
                            return ErrorResponse([
                                'discount' => labels(
                                    'percentage_discount_cannot_exceed_100',
                                    'Percentage discount cannot be greater than 100%'
                                )
                            ], true, [], [], 200, csrf_token(), csrf_hash());
                        }

                        // // Max Discount Amount must not exceed Minimum Booking Amount
                        // if ($maxDiscount > $minOrder) {
                        //     return ErrorResponse([
                        //         'max_discount_amount' => labels(
                        //             'max_discount_amount_cannot_exceed_minimum_order_amount',
                        //             'Max discount amount cannot be greater than the minimum booking amount'
                        //         )
                        //     ], true, [], [], 200, csrf_token(), csrf_hash());
                        // }
                    }

                    $promo_id = isset($_POST['promo_id']) ? $_POST['promo_id'] : '';
                    $old_image_rows = fetch_details('promo_codes', ['id' => $promo_id], ['image']);
                    $old_image = $old_image_rows[0]['image'] ?? null;
                    $uploadedImage = $this->request->getFile('image');

                    if ($uploadedImage !== null && $uploadedImage->isValid() && !$uploadedImage->hasMoved()) {
                        $uploadResult = $this->fileService->replace(oldPath: $old_image, file: $uploadedImage, folder: 'promocodes');
                        if ($uploadResult['error']) {
                            return ErrorResponse($uploadResult['message'], true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $image_name = $uploadResult['path'];
                    } else {
                        $image_name = $old_image;
                    }

                    if (empty($image_name)) {
                        return ErrorResponse(labels(PLEASE_UPLOAD_AN_IMAGE_FILE, "Please upload an image file"), true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    $promocode_model = new Promo_code_model();
                    $repeat_usage = isset($_POST['repeat_usage']) ? "1" : "0";
                    $status = isset($_POST['status']) ? "1" : "0";
                    $users = isset($_POST['no_of_users']) ? $this->request->getVar('no_of_users') : "1";
                    // Get default language message for base table
                    $defaultLanguage = $this->getDefaultLanguageCode();
                    $messages = $this->request->getPost('message');
                    $defaultMessage = isset($messages[$defaultLanguage]) ? $this->removeScript(trim($messages[$defaultLanguage])) : '';

                    $promocode = array(
                        'id' => $promo_id,
                        'partner_id' => $this->request->getVar('partner'),
                        'promo_code' => $this->request->getVar('promo_code'),
                        'start_date' => (format_date($this->request->getVar('start_date'), 'Y-m-d')),
                        'end_date' => (format_date($this->request->getVar('end_date'), 'Y-m-d')),
                        'no_of_users' => $users,
                        'minimum_order_amount' => $this->request->getVar('minimum_order_amount'),
                        'max_discount_amount' => $this->request->getVar('max_discount_amount'),
                        'discount' => $this->request->getVar('discount'),
                        'discount_type' => $this->request->getVar('discount_type'),
                        'repeat_usage' => $repeat_usage,
                        'no_of_repeat_usage' => isset($_POST['no_of_repeat_usage']) ? $this->request->getVar('no_of_repeat_usage') : "0",
                        'image' => $image_name,
                        'status' => $status,
                        // Store default language message in main table (following Blogs pattern)
                        'message' => $defaultMessage,
                    );

                    // Save the promocode
                    $promocode_model->save($promocode);

                    // Save translations
                    $this->savePromocodeTranslations($promo_id);
                    return successResponse("Promocode saved successfully", false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return redirect()->back();
                }
            }
        } catch (\Throwable $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Promo_codes.php - update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function duplicate()
    {
        try {
            helper('function');
            $uri = service('uri');
            $promocode_id = $uri->getSegments()[3];
            if ($this->isLoggedIn) {
                // Get partner data with translations
                $partner_data = $this->db->table('users u')
                    ->select('u.id,u.username,pd.company_name')
                    ->join('partner_details pd', 'pd.partner_id = u.id')
                    ->where('is_approved', '1')
                    ->get()->getResultArray();

                // Get current language for translations
                $current_language = get_current_language();

                // OPTIMIZATION: Batch fetch all partner translations at once instead of individual queries
                if (!empty($partner_data)) {
                    $partner_ids = array_column($partner_data, 'id');

                    try {
                        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
                        $allTranslations = $translationModel->getBatchTranslations($partner_ids, $current_language);

                        // Apply translations to partner data efficiently
                        foreach ($partner_data as &$partner) {
                            if (isset($allTranslations[$partner['id']]['company_name'])) {
                                $partner['company_name'] = $allTranslations[$partner['id']]['company_name'];
                            }
                        }
                    } catch (\Exception $e) {
                        // Log error but continue with original data
                        log_message('error', 'Error getting batch partner translations: ' . $e->getMessage());
                    }
                }

                $this->data['partner_name'] = $partner_data;

                // Get promocode data
                $promocode = fetch_details('promo_codes', ['id' => $promocode_id])[0];

                $promocode['raw_image'] = $promocode['image'];
                $promocode['image'] = $this->fileService->url(path: $promocode['image'], folder: 'promocodes');

                // Get translated promocode data for all languages
                try {
                    $translatedData = $this->translatedPromocodeModel->getAllTranslations($promocode_id);
                    // Merge translated data with original promocode data
                    if ($translatedData) {
                        foreach ($translatedData as $key => $translation) {
                            if (isset($translation)) {
                                $promocode['translated_messages'][$key] = $translation;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but continue with original data
                    log_message('error', 'Error getting promocode translations: ' . $e->getMessage());
                }

                $this->data['promocode'] = $promocode;

                // Fetch languages for the message tabs
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                setPageInfo($this->data, labels('promo_codes', 'Promo Codes') . ' | ' . labels('admin_panel', 'Admin Panel'), 'duplicate_promocode');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Promo_codes.php - duplicate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get the default language code from the database
     * 
     * @return string The default language code
     */
    private function getDefaultLanguageCode()
    {
        $languages = fetch_details('languages', ['is_default' => 1], ['code']);
        return $languages[0]['code'] ?? 'en';
    }

    /**
     * Save promocode translations for all languages - OPTIMIZED for performance
     * 
     * @param int $promocode_id The promocode ID
     * @return bool True if successful, false otherwise
     */
    private function savePromocodeTranslations($promocode_id)
    {
        try {
            // Get messages from POST data
            $messages = $this->request->getPost('message');

            if (!$messages || !is_array($messages)) {
                return false;
            }

            // OPTIMIZATION: Use transaction for better performance and data integrity
            $db = \Config\Database::connect();
            $db->transStart();

            try {
                // Save translations using the optimized model method
                $result = $this->translatedPromocodeModel->saveTranslationsOptimized($promocode_id, $messages);

                $db->transComplete();
                return $result && $db->transStatus();
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Error saving promocode translations: ' . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            log_message('error', 'Error in savePromocodeTranslations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get promocode data with translations for editing
     * 
     * @return \CodeIgniter\HTTP\Response JSON response with promocode data
     */
    public function get_promocode_data()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $promocode_id = $this->request->getPost('id');

            if (!$promocode_id) {
                return ErrorResponse("Promocode ID is required", true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get basic promocode data without translation (more efficient)
            $promocode = $this->promo_codes->find($promocode_id);

            if (!$promocode) {
                return ErrorResponse("Promocode not found", true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Convert status to text format for JavaScript compatibility
            $promocode['status'] = ($promocode['status'] == 1) ? 'Active' : 'Deactive';

            $promocode['image'] = $this->fileService->url(path: $promocode['image'], folder: 'promocodes');

            // Get all translations for this promocode
            $translations = $this->translatedPromocodeModel->getAllTranslations($promocode_id);

            $response_data = [
                'promocode' => $promocode,
                'translations' => $translations
            ];

            return successResponse("Promocode data retrieved successfully", false, $response_data, [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Promo_codes.php - get_promocode_data()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
