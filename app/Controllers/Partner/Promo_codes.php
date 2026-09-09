<?php

namespace App\Controllers\Partner;

use App\Models\Promo_code_model;
use App\Models\TranslatedPromocodeModel;

class Promo_codes extends Partner
{
    public $orders;
    protected Promo_code_model $promo_codes;
    protected TranslatedPromocodeModel $translatedPromocodeModel;

    public function __construct()
    {
        parent::__construct();
        $this->promo_codes = new Promo_code_model();
        $this->translatedPromocodeModel = new TranslatedPromocodeModel();
        $this->validation = \Config\Services::validation();
        helper('ResponceServices');
    }
    public function index()
    {
        if ($this->isLoggedIn && $this->userIsPartner) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            setPageInfo($this->data, labels('promo_codes', 'Promo codes') . ' | ' . labels('provider_panel', 'Provider Panel'), 'promo_codes');

            // fetch languages
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
            $this->data['languages'] = $languages;
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }
    public function add()
    {
        if (!$this->isLoggedIn && !$this->userIsPartner) {
            return redirect('partner/login');
        } else {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }

            // fetch languages for multi-language message support
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
            $this->data['languages'] = $languages;

            setPageInfo($this->data, labels('promo_codes', 'Promo codes') . ' | ' . labels('provider_panel', 'Provider Panel'), FORMS . 'add_promocode');
            return view('backend/partner/template', $this->data);
        }
    }
    public function save()
    {
        try {
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return redirect('unauthorised');
            } else {

                $disk = fetch_current_file_manager();

                if (isset($_POST) && !empty($_POST)) {
                    $repeat_usage = isset($_POST['repeat_usage']) ? $_POST['repeat_usage'] : '';
                    $id = isset($_POST['promo_id']) ? $_POST['promo_id'] : '';

                    // Get default language code for validation
                    $default_language = $this->getDefaultLanguageCode();

                    $validationRules = [
                        'promo_code' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_PROMO_CODE_NAME, "Please enter promo code name")]],
                        'start_date' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_START_DATE, "Please select start date")]],
                        'end_date' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_END_DATE, "Please select end date")]],
                        'no_of_users' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_NUMBER_OF_USERS, "Please enter number of users"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_NUMBER_OF_USERS, "Please enter numeric value for number of users"),    "greater_than" => labels(NUMBER_OF_USERS_MUST_BE_GREATER_THAN_0, "number of users must be greater than 0"),]],
                        'minimum_order_amount' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_MINIMUM_ORDER_AMOUNT, "Please enter minimum order amount"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MINIMUM_ORDER_AMOUNT, "Please enter numeric value for minimum order amount"),    "greater_than" => labels(MINIMUM_ORDER_AMOUNT_MUST_BE_GREATER_THAN_0, "minimum order amount must be greater than 0"),]],
                        'discount' => ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_DISCOUNT, "Please enter discount"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DISCOUNT, "Please enter numeric value for discount"),    "greater_than" => labels(DISCOUNT_MUST_BE_GREATER_THAN_0, "discount must be greater than 0"),]],
                        'max_discount_amount' => ["rules" => 'permit_empty|numeric|greater_than[0]', "errors" => ["numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MAX_DISCOUNT_AMOUNT, "Please enter numeric value for max discount amount"),    "greater_than" => labels(DISCOUNT_AMOUNT_MUST_BE_GREATER_THAN_0, "discount amount must be greater than 0"),]],
                        "message.{$default_language}" => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_MESSAGE_FOR_DEFAULT_LANGUAGE, "Please enter message for default language")]],
                    ];

                    if ($repeat_usage == 'on' && empty($id)) {
                        $validationRules['no_of_repeat_usage'] = ["rules" => 'required|numeric|greater_than[0]', "errors" => ["required" => labels(PLEASE_ENTER_NUMBER_OF_REPEAT_USAGE, "Please enter number of repeat usage"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_NUMBER_OF_REPEAT_USAGE, "Please enter numeric value for number of repeat usage"),    "greater_than" => labels(NUMBER_OF_REPEAT_USAGE_MUST_BE_GREATER_THAN_0, "number of repeat usage must be greater than 0"),]];
                    }

                    if (empty($id)) {
                        $validationRules['image'] = ["rules" => 'uploaded[image]', "errors" => ["uploaded" => labels(PLEASE_UPLOAD_AN_IMAGE_FILE, "Please upload an image file")]];
                    }

                    $this->validation->setRules($validationRules);
                    if (!$this->validation->withRequest($this->request)->run()) {
                        $errors  = $this->validation->getErrors();
                        $response['error'] = true;
                        $response['message'] = $errors;
                        $response['csrfName'] = csrf_token();
                        $response['csrfHash'] = csrf_hash();
                        $response['data'] = [];
                        return $this->response->setJSON($response);
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

                        // Max Discount Amount must not exceed Minimum Booking Amount
                        if ($maxDiscount > $minOrder) {
                            return ErrorResponse([
                                'max_discount_amount' => labels(
                                    'max_discount_amount_cannot_exceed_minimum_order_amount',
                                    'Max discount amount cannot be greater than the minimum booking amount'
                                )
                            ], true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                    if (isset($_POST['promo_id']) && !empty($_POST['promo_id'])) {
                        $promo_id = $_POST['promo_id'];
                        $old_image = fetch_details('promo_codes', ['id' => $_POST['promo_id']]);
                    } else {
                        $promo_id = '';
                        $old_image = '';
                    }
                    $image = "";

                    $paths = [
                        'image' => [
                            'file' => $this->request->getFile('image'),
                            'path' => 'public/uploads/promocodes/',
                            'error' => labels(FAILED_TO_CREATE_PROMOCODES_FOLDERS, "Failed to create promocodes folders"),
                            'folder' => 'promocodes',
                            'old_file' => $old_image[0]['image'] ?? null,
                            'disk' => $disk,
                        ],
                    ];
                    $uploadedFiles = [];
                    foreach ($paths as $key => $upload) {
                        if ($upload['file'] && $upload['file']->isValid()) {
                            if (!empty($upload['old_file'])) {
                                delete_file_based_on_server($upload['folder'], $upload['old_file'], $upload['disk']);
                            }
                            $result = upload_file($upload['file'], $upload['path'], $upload['error'], $upload['folder']);
                            if ($result['error'] === false) {
                                $uploadedFiles[$key] = [
                                    'url' => $result['file_name'],
                                    'disk' => $result['disk']
                                ];
                            } else {
                                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                            }
                        }
                    }
                    $image_name = $uploadedFiles['image']['url'] ?? $old_image[0]['image'] ?? null;
                    $image_disk = $uploadedFiles['image']['disk'] ?? $disk ?? null;
                    // Default to local_server path if applicable
                    $image = isset($uploadedFiles['image']['disk']) && $uploadedFiles['image']['disk'] == 'local_server'
                        ? 'public/uploads/promocodes/' . $uploadedFiles['image']['url']
                        : $uploadedFiles['image']['url'] ?? null;
                    // Handle scenario when no file is uploaded
                    if (empty($image)) {
                        $image = $old_image[0]['image'] ?? null;
                    }
                    if (empty($image)) {
                        return ErrorResponse(labels(PLEASE_UPLOAD_AN_IMAGE_FILE, "Please upload an image file"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $promocode_model = new Promo_code_model();
                    $partner_id = $_SESSION['user_id'];
                    if (isset($_POST['repeat_usage'])) {
                        $repeat_usage = "1";
                    } else {
                        $repeat_usage = "0";
                    }
                    if (isset($_POST['status'])) {
                        $status = "1";
                    } else {
                        $status = "0";
                    }
                    if (isset($_POST['no_of_users'])) {
                        $users = $this->request->getVar('no_of_users');
                    } else {
                        $users = "1";
                    }
                    $promocode = array(
                        'id' => $promo_id,
                        'partner_id' => $partner_id,
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
                    );
                    // Save the promocode first to get the ID
                    $promocode_model->save($promocode);

                    // Get the promocode ID (either existing or newly created)
                    $promocode_id = $promo_id ?: $promocode_model->getInsertID();

                    // Save translations
                    $this->savePromocodeTranslations($promocode_id);

                    // Get promocode details for event tracking
                    $promocodeData = fetch_details('promo_codes', ['id' => $promocode_id]);
                    $isUpdate = !empty($promo_id);

                    // Prepare event data
                    $eventData = [
                        'clarity_event' => $isUpdate ? 'promocode_updated' : 'promocode_created',
                        'promocode_id' => $promocode_id,
                        'promocode_name' => $promocodeData[0]['promo_code'] ?? '',
                        'discount' => $promocodeData[0]['discount'] ?? '',
                        'discount_type' => $promocodeData[0]['discount_type'] ?? ''
                    ];

                    return successResponse(labels(PROMOCODE_SAVED_SUCCESSFULLY, "Promocode saved successfully"), false, $eventData, [], 200, csrf_token(), csrf_hash());
                } else {
                    return redirect()->back();
                }
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Promo_codes.php - save()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function list()
    {
        $promocode_model = new Promo_code_model();
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $where['pc.partner_id'] = $_SESSION['user_id'];
        $promo_codes =  $promocode_model->list(false, $search, $limit, $offset, $sort, $order, $where);
        return $promo_codes;
    }
    public function delete()
    {
        try {
            $id = $this->request->getPost('id');
            $db = \Config\Database::connect();
            $old_image = fetch_details('promo_codes', ['id' => $id], ['image']);
            $disk = fetch_current_file_manager();

            if (!empty($old_image)) {
                delete_file_based_on_server('promocodes', $old_image[0]['image'], $disk);
            }
            // Get promocode details before deletion for event tracking
            $promocodeData = fetch_details('promo_codes', ['id' => $id]);

            $builder = $db->table('promo_codes')->delete(['id' => $id]);
            if ($builder) {
                // Prepare event data
                $eventData = [
                    'clarity_event' => 'promocode_deleted',
                    'promocode_id' => $id
                ];

                return successResponse(labels(PROMOCODE_DELETED_SUCCESSFULLY, "Promocode deleted successfully"), false, $eventData, [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(PROMOCODE_CANNOT_BE_DELETED, "Promocode cannot be deleted!"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage() . "\n" . $th->getTraceAsString());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something went wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function duplicate()
    {
        try {
            helper('function');
            $uri = service('uri');
            $promocode_id = $uri->getSegments()[3];
            if ($this->isLoggedIn && $this->userIsPartner) {
                if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                    return redirect('partner/profile');
                }

                // Get promocode data
                $promocode = fetch_details('promo_codes', ['id' => $promocode_id])[0];

                // Get translated promocode data for all languages
                try {
                    $translatedPromocodeModel = new \App\Models\TranslatedPromocodeModel();
                    $translatedData = $translatedPromocodeModel->getAllTranslations($promocode_id);
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

                // Get current language for default selection
                $this->data['current_language'] = get_current_language();

                setPageInfo($this->data, labels('promo_codes', 'Promo codes') . ' | ' . labels('provider_panel', 'Provider Panel'), FORMS . 'duplicate_promocode');
                return view('backend/partner/template', $this->data);
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Promo_codes.php - duplicate()');
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
     * Save promocode translations for all languages
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

            // Save translations using the model method
            $result = $this->translatedPromocodeModel->saveTranslationsOptimized($promocode_id, $messages);
            return $result;
        } catch (\Exception $e) {
            log_message('error', 'Error saving promocode translations: ' . $e->getMessage());
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
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return redirect('partner/login');
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

            // Verify that this promocode belongs to the current partner
            if ($promocode['partner_id'] != $this->userId) {
                return ErrorResponse("Unauthorized access to this promocode", true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Convert status to text format for JavaScript compatibility
            $promocode['status'] = ($promocode['status'] == 1) ? 'Active' : 'Deactive';

            // Handle image URL formatting for proper display
            // Use get_file_url() to check if file exists and return default if missing
            // This matches the admin panel implementation for consistency
            $disk = fetch_current_file_manager();
            if (!empty($promocode['image'])) {
                // Build the full file path for the promocode image
                // Check if image path already includes 'public/uploads/promocodes/' to avoid duplication
                if (strpos($promocode['image'], 'public/uploads/promocodes/') !== false) {
                    $image_path = $promocode['image'];
                } else {
                    $image_path = 'public/uploads/promocodes/' . $promocode['image'];
                }
                // Use get_file_url() to check if file exists and return default if missing
                $promocode['image'] = get_file_url($disk, $image_path, 'public/backend/assets/default.png', 'promocodes');
            } else {
                // If no image path in database, show default image
                $promocode['image'] = base_url('public/backend/assets/default.png');
            }

            // Get all translations for this promocode
            $translations = $this->translatedPromocodeModel->getAllTranslations($promocode_id);

            $response_data = [
                'promocode' => $promocode,
                'translations' => $translations
            ];

            return successResponse("Promocode data retrieved successfully", false, $response_data, [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Promo_codes.php - get_promocode_data()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
