<?php

namespace App\Controllers\Admin;

use App\Models\Partner_subscription_model;
use App\Models\Subscription_model;
use App\Models\TranslatedSubscriptionModel;

class Subscription extends Admin
{
    public $cities,  $validation, $db;
    protected $superadmin;
    protected Subscription_model $subscription;
    protected Partner_subscription_model $partner_Subscription;
    protected TranslatedSubscriptionModel $translatedSubscriptionModel;

    public function __construct()
    {
        parent::__construct();
        $this->subscription = new Subscription_model();
        $this->validation = \Config\Services::validation();
        $this->db      = \Config\Database::connect();
        $this->superadmin = $this->session->get('email');
        $this->partner_Subscription = new Partner_subscription_model();
        $this->translatedSubscriptionModel = new TranslatedSubscriptionModel();
        helper('ResponceServices');
    }

    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('unauthorised');
        }
        setPageInfo($this->data, labels('subscription', 'Subscription') . '  | ' . labels('admin_panel', 'Admin Panel'), 'subscription');
        
        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/admin/template', $this->data);
    }

    public function add_ons_index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('unauthorised');
        }
        setPageInfo($this->data, labels('add_ons', 'Add Ons') . '  | ' . labels('admin_panel', 'Admin Panel'), 'add_on');
        return view('backend/admin/template', $this->data);
    }

    public function add_subscription()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('unauthorised');
        }
        setPageInfo($this->data, labels('add_subscription', 'Add Subscription') . '  | ' . labels('admin_panel', 'Admin Panel'), 'add_subscription');
        // Fetch taxes with translated names based on current language
        $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
        $this->data['tax_data'] = $tax_data;
        // fetch languages
        $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
        $this->data['languages'] = $languages;
        return view('backend/admin/template', $this->data);
    }

    public function edit_subscription_page()
    {

        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('unauthorised');
            }
            helper('function');
            $uri = service('uri');
            $subscription_id = $uri->getSegments()[3];
            $subscription_data = fetch_details('subscriptions', ['id' => $subscription_id]);

            // Fetch translations for this subscription to prefill form fields
            $translations = $this->translatedSubscriptionModel->getAllTranslations($subscription_id);

            setPageInfo($this->data, labels('edit_subscription', 'Edit Subscription') . '  | ' . labels('admin_panel', 'Admin Panel'), 'edit_subscription');
            $this->data['subscription_data'] = $subscription_data;
            $this->data['translations'] = $translations; // Add translations to view data
            // Fetch taxes with translated names based on current language
            $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['tax_data'] = $tax_data;
            // fetch languages
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Subscription.php - edit_subscription_page()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function edit_subscription()
    {
        try {
            $price = $this->request->getPost('price');
            // Get default language for validation
            $default_language = $this->getDefaultLanguageCode();
            $names = $this->request->getPost('name');
            $descriptions = $this->request->getPost('description');

            // Validate that default language has required fields
            if (empty($names[$default_language]) || empty(trim($names[$default_language]))) {
                return ErrorResponse(labels('name_in_default_language_is_required', 'Name in default language is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            if (empty($descriptions[$default_language]) || empty(trim($descriptions[$default_language]))) {
                return ErrorResponse(labels('description_in_default_language_is_required', 'Description in default language is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $this->validation->setRules(
                [
                    'price' => ["rules" => 'required|numeric', "errors" => ["required" => labels('please_enter_price', 'Please enter price'),    "numeric" => labels('please_enter_numeric_value_for_price', 'Please enter numeric value for price')]],
                    'discount_price' => ["rules" => 'required|numeric', "errors" => ["required" => labels('please_enter_discounted_price', 'Please enter discounted price'),    "numeric" => labels('please_enter_numeric_value_for_discounted_price', 'Please enter numeric value for discounted price')]],
                    'tax_id' => ["rules" => 'required|integer', "errors" => ["required" => labels('please_select_tax', 'Please select tax'),    "integer" => labels('please_select_tax', 'Please select tax')]],
                ],
            );
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors  = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }
            $discount_price = $this->request->getPost('discount_price');
            $price = $this->request->getPost('price');

            if ($discount_price >= $price && $discount_price != 0 && $price != 0) {
                return ErrorResponse(labels('discount_price_can_not_be_higher_than_or_equal_to_the_price', 'Discount price can not be higher than or equal to the price'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $order_type = $this->request->getVar('order_type') == "limited" ? "limited" : "unlimited";
            if ($order_type == "limited" && $this->request->getVar('max_order') == "") {
                return ErrorResponse(labels('please_add_maximum_number_of_order', 'Please add maximum number of order'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $commission_type = $this->request->getVar('commission_type') == "yes" ? "yes" : "no";
            $duration = $this->request->getVar('duration_type') != "unlimited" ? $this->request->getVar('duration') : "unlimited";
            $publish = $this->request->getVar('publish') == "on" ? "1" : "0";
            $status = $this->request->getVar('status') == "on" ? "1" : "0";

            $check_payment_gateway = get_settings('payment_gateways_settings', true);
            $cod_setting =  $check_payment_gateway['cod_setting'];

            if (($commission_type == "yes")) {

                if ($cod_setting == 1) {
                    if ((($this->request->getVar('threshold') == "") || ($this->request->getVar('percentage') == ""))) {
                        return ErrorResponse(labels('please_add_commission_field', 'Please add commission field'), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } else if ($cod_setting == 0) {

                    if ((($this->request->getVar('percentage') == ""))) {
                        return ErrorResponse(labels('please_add_commission_field', 'Please add commission field'), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
            }
            // Store default language translatable fields in base table (following Blogs pattern)
            // All translations (including default) will also be stored in translations table
            $default_language = $this->getDefaultLanguageCode();
            $names = $this->request->getPost('name');
            $descriptions = $this->request->getPost('description');

            // Get default language values for main subscription fields
            $default_name = isset($names[$default_language]) ? $names[$default_language] : '';
            $default_description = isset($descriptions[$default_language]) ? $descriptions[$default_language] : '';

            $subscription = [
                'name' => $this->removeScript($default_name),
                'description' => $this->removeScript($default_description),
                'duration' => $duration,
                'price' => $price,
                'discount_price' => $discount_price,
                'publish' => $publish,
                'order_type' => $order_type,
                'max_order_limit' => $this->request->getVar('max_order'),
                'service_type' => "unlimited",
                'max_service_limit' => $this->request->getVar('max_service'),
                'tax_type' => $this->request->getVar('tax_type'),
                'tax_id' => $this->request->getVar('tax_id'),
                'is_commision' => $commission_type,
                'commission_threshold' => $this->request->getVar('threshold'),
                'commission_percentage' => $this->request->getVar('percentage'),
                'status' => $status,
            ];
            $subscription_id = $this->request->getPost('subscription_id');

            // Update the main subscription
            if ($this->subscription->update($subscription_id, $subscription)) {
                // We send a redirect URL so the JS handler can guide admins back to the listing page after a successful save.
                $redirectPayload = ['redirect_url' => base_url('/admin/subscription')];
                // Save translations for all languages
                if ($this->saveSubscriptionTranslations($subscription_id)) {
                    return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"), false, [], $redirectPayload, 200, csrf_token(), csrf_hash());
                } else {
                    // If translations fail, still return success but log the issue
                    log_message('warning', 'Subscription updated but translations failed for ID: ' . $subscription_id);
                    return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"), false, [], $redirectPayload, 200, csrf_token(), csrf_hash());
                }
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Subscription.php - edit_subscription()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function delete_subscription()
    {
        try {

            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('partner/login');
            }
            $id = $this->request->getPost('id');
            $deletedTranslations = $this->db->table('translated_subscription_details')->delete(['subscription_id' => $id]);
            $deleted = $this->db->table('subscriptions')->delete(['id' => $id]);
            if ($deleted && $deletedTranslations) {
                return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Data deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Subscription.php - delete_subscription()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function add_store_subscription()
    {

        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('unauthorised');
            }
            // Get default language for storing in main table
            $defaultLanguage = $this->getDefaultLanguageCode();

            $this->validation->setRules([
                'price' => ["rules" => 'required|numeric', "errors" => ["required" => labels('please_enter_price', 'Please enter price'),     "numeric" => labels('please_enter_numeric_value_for_price', 'Please enter numeric value for price')]],
                'discount_price' => ["rules" => 'required|numeric', "errors" => ["required" => labels('please_enter_discounted_price', 'Please enter discounted price'),    "numeric" => labels('please_enter_numeric_value_for_discounted_price', 'Please enter numeric value for discounted price')]],
                'tax_id' => ["rules" => 'required|integer', "errors" => ["required" => labels('please_select_tax', 'Please select tax'),    "integer" => labels('please_select_tax', 'Please select tax')]],
            ]);

            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            if (empty($this->request->getVar("name[$defaultLanguage]"))) {
                return ErrorResponse(labels('name_in_default_language_is_required', 'Name in default language is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            if (empty($this->request->getVar("description[$defaultLanguage]"))) {
                return ErrorResponse(labels('description_in_default_language_is_required', 'Description in default language is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $price = $this->request->getPost('price');
            $discount_price = $this->request->getPost('discount_price');

            if ($discount_price >= $price && $discount_price != 0 && $price != 0) {
                return ErrorResponse(labels('discount_price_can_not_be_higher_than_or_equal_to_the_price', 'Discount price can not be higher than or equal to the price'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $order_type = $_POST['order_type'] == "limited" ? "limited" : "unlimited";
            $duration_type = $_POST['duration_type'] == "limited" ? "limited" : "unlimited";
            $duartion = $duration_type == "limited" ? $this->request->getVar('duration') : "unlimited";
            if ($order_type == "limited" && empty($this->request->getVar('max_order'))) {
                return ErrorResponse(labels('please_add_maximum_number_of_order', 'Please add maximum number of order'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            if ($duration_type == "limited" && (empty($this->request->getVar('duration')) || $this->request->getVar('duration') == 0)) {
                return ErrorResponse(labels('please_add_duration', 'Please add duration'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $commission_type = $_POST["commission_type"] == "yes" ? "yes" : "no";
            $publish = !empty($_POST["publish"]) && $_POST["publish"] == "on" ? "1" : "0";
            $status = !empty($_POST["status"]) && $_POST["status"] == "on" ? "1" : "0";

            $check_payment_gateway = get_settings('payment_gateways_settings', true);
            $cod_setting =  $check_payment_gateway['cod_setting'];

            if (($commission_type == "yes")) {

                if ($cod_setting == 1) {
                    if ((($this->request->getVar('threshold') == "") || ($this->request->getVar('percentage') == ""))) {
                        return ErrorResponse(labels('please_add_commission_field', 'Please add commission field'), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } else if ($cod_setting == 0) {

                    if ((($this->request->getVar('percentage') == ""))) {
                        return ErrorResponse(labels('please_add_commission_field', 'Please add commission field'), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
            }
            // Store default language translatable fields in base table (following Blogs pattern)
            // All translations (including default) will also be stored in translations table
            $defaultLanguage = $this->getDefaultLanguageCode();

            // Get name and description from default language for main table
            $defaultName = $this->removeScript($this->request->getVar("name[$defaultLanguage]"));
            $defaultDescription = $this->removeScript($this->request->getVar("description[$defaultLanguage]"));

            $subscription = [
                'name' => $defaultName,
                'description' => $defaultDescription,
                'duration' => $duartion,
                'price' => $price,
                'discount_price' => $discount_price,
                'publish' => $publish,
                'order_type' => $order_type,
                'max_order_limit' => !empty($this->request->getVar('max_order')) ? $this->request->getVar('max_order') : 0,
                'service_type' => "limited",
                'max_service_limit' => !empty($this->request->getVar('max_service')) ? $this->request->getVar('max_service') : 0,
                'tax_type' => $this->request->getVar('tax_type'),
                'tax_id' => $this->request->getVar('tax_id'),
                'is_commision' => $commission_type,
                'commission_threshold' => !empty($this->request->getVar('threshold')) ? $this->request->getVar('threshold') : 0,
                'commission_percentage' => !empty($this->request->getVar('percentage')) ? $this->request->getVar('percentage') : 0,
                'status' => $status,
            ];

            if ($this->subscription->save($subscription)) {
                $subscription_id = $this->subscription->getInsertID();

                // Save translations for all languages
                if ($this->saveSubscriptionTranslations($subscription_id)) {
                    return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Data saved successfully"), false, [], ['redirect_url' => base_url('admin/subscription')], 200, csrf_token(), csrf_hash());
                } else {
                    // If translations fail, still return success but log the issue
                    log_message('warning', 'Subscription saved but translations failed for ID: ' . $subscription_id);
                    return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Data saved successfully"), false, [], ['redirect_url' => base_url('admin/subscription')], 200, csrf_token(), csrf_hash());
                }
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Subscription.php - add_store_subscription()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

        // Use the new translation-aware list method
        print_r(json_encode($this->subscription->listWithTranslations(false, $search, $limit, $offset, $sort, $order)));
    }

    public function add_on_create_page()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('add_ons', 'Add Ons') . '  | ' . labels('admin_panel', 'Admin Panel'), 'create_add_ons');
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('unauthorised');
        }
    }

    public function subscriber_list()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('subscriber_list', 'Subscriber List') . '  | ' . labels('admin_panel', 'Admin Panel'), 'subscriber_list');
                $db      = \Config\Database::connect();
                $totalSubscriptionCount = $db->table('partner_subscriptions')->countAll();
                $activeSubscriptionCount = $db->table('partner_subscriptions')
                    ->where('status', 'active')
                    ->countAllResults();
                $expiredSubscriptionCount = $db->table('partner_subscriptions')
                    ->where('status', 'deactive')
                    ->countAllResults();
                $expiringSoonSubscriptionCount = $db->table('partner_subscriptions')
                    ->where('status', 'active')
                    ->where('expiry_date <=', date('Y-m-d', strtotime('+7 days')))
                    ->countAllResults();

                // Fetch timezone and currency so revenue reports follow the same locale admins expect.
                $generalSettings = get_settings('general_settings', true);
                $timezone = $generalSettings['system_timezone'] ?? date_default_timezone_get();

                // Use explicit DateTime objects to avoid changing global timezone yet keep month boundaries accurate.
                $startOfMonth = new \DateTime('first day of this month 00:00:00', new \DateTimeZone($timezone));
                $endOfMonth = new \DateTime('last day of this month 23:59:59', new \DateTimeZone($timezone));

                // Transactions are finalized by payment webhooks, so summing their successful subscription rows reflects cash actually received.
                $monthlyRevenueRow = $db->table('transactions')
                    ->selectSum('amount', 'monthly_revenue')
                    ->where('transaction_type', 'transaction')
                    ->where('status', 'success')
                    ->where('subscription_id IS NOT NULL', null, false)
                    ->where('subscription_id !=', '0')
                    ->where('created_at >=', $startOfMonth->format('Y-m-d H:i:s'))
                    ->where('created_at <=', $endOfMonth->format('Y-m-d H:i:s'))
                    ->get()
                    ->getRowArray();

                $monthlyRevenue = isset($monthlyRevenueRow['monthly_revenue']) ? (float)$monthlyRevenueRow['monthly_revenue'] : 0.0;
                // Format without currency text so JS counter animations keep working, and expose the symbol separately for the view.
                $monthlyRevenueFormatted = number_format($monthlyRevenue, 2, '.', '');
                
                // get currency symbole
                $this->data['currency'] = get_settings('general_settings', true)['currency'];


                $this->data['totalSubscriptionCount'] = $totalSubscriptionCount;
                $this->data['activeSubscriptionCount'] = $activeSubscriptionCount;
                $this->data['expiredSubscriptionCount'] = $expiredSubscriptionCount;
                $this->data['expiringSoonSubscriptionCount'] = $expiringSoonSubscriptionCount;
                $this->data['monthlySubscriptionRevenue'] = $monthlyRevenue;
                $this->data['monthlySubscriptionRevenueFormatted'] = $monthlyRevenueFormatted;
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Subscription.php - subscriber_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function partner_subscription_list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        print_r(json_encode($this->partner_Subscription->subscriber_list(false, $search, $limit, $offset, $sort, $order)));
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
     * Save subscription translations for all languages
     * 
     * @param int $subscription_id The subscription ID
     * @return bool True if successful, false otherwise
     */
    private function saveSubscriptionTranslations($subscription_id)
    {
        try {
            // Get names and descriptions from POST data
            $names = $this->request->getPost('name');
            $descriptions = $this->request->getPost('description');

            if (!$names || !is_array($names)) {
                return false;
            }

            // Save translations using the optimized model method
            $result = $this->translatedSubscriptionModel->saveTranslationsOptimized($subscription_id, $names, $descriptions);
            return $result;
        } catch (\Exception $e) {
            log_message('error', 'Error saving subscription translations: ' . $e->getMessage());
            return false;
        }
    }
}
