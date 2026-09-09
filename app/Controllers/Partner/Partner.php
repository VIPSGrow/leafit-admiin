<?php

namespace App\Controllers\Partner;

use Stripe\Exception\AuthenticationException;
use App\Controllers\BaseController;
use App\Libraries\Flutterwave;
use App\Libraries\Paypal;
use App\Libraries\Paystack;
use App\Libraries\Razorpay;
use App\Libraries\Cashfree;
use App\Libraries\Xendit;
use App\Models\Cash_collection_model;
use App\Models\Partners_model;
use App\Models\Service_ratings_model;
use App\Models\Settlement_model;
use App\Libraries\Stripe;
use App\Models\Partner_subscription_model;
use App\Models\Settlement_CashCollection_history_model;
use Exception;

require APPPATH . 'Views/backend/partner/Razorpay.php';

use Razorpay\Api\Api;

class Partner extends BaseController
{
    protected $db;
    protected $builder;
    protected $stripe_secret_key;
    protected string $stripe_currency;
    protected $validation;
    protected $data;
    protected $stripe;
    protected $session;
    protected $paypal_lib;
    protected Settlement_model $settle_commission;
    protected Cash_collection_model $cash_collection;
    protected Partners_model $partner;
    protected Partner_subscription_model $subscription;

    public function __construct()
    {
        helper('function, form, url, filesystem, ResponceServices');
        $this->validation = \Config\Services::validation();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $user = $this->ionAuth->user()->row();
        $this->data['admin'] = $this->userIsAdmin;
        $this->data['partner'] = $this->userIsPartner;
        $this->settle_commission = new Settlement_model();
        $this->cash_collection = new Cash_collection_model();
        $this->data['settings'] = $this->settings;
        $this->partner = new Partners_model();
        $this->subscription = new Partner_subscription_model();
        $session = session();
        $lang = $session->get('lang');
        if (empty($lang)) {
            $lang = 'en';
        }
        $this->data['current_lang'] = $lang;
        $languages_locale = fetch_details('languages', [], [], null, '0', 'id', 'ASC');
        $available_languages = [];
        $languageModel = new \App\Models\Language_model();

        foreach ($languages_locale as $row) {
            $code = $row['code'];
            // Check if language has files for provider_app platform
            if ($languageModel->hasLanguageFilesForProviderApp($code)) {
                $available_languages[] = $row;
            }
        }
        $this->data['languages_locale'] = $available_languages;
        $profile = '';
        if (!empty($data)) {
            $data = $data[0];
            if ($data['image'] != '') {
                if (check_exists(base_url($data['image']))) {
                    $profile = '<img alt="image" src="' .  base_url($data['image']) . '" class="rounded-circle mr-1">';
                } else {
                    $profile = '<figure class="avatar mb-2 avatar-sm mt-1" data-initial="' . strtoupper($data['username'][0]) . '"></figure>';
                }
            } else {
                $profile = '<figure class="avatar mb-2 avatar-sm mt-1" data-initial="' . strtoupper($data['username'][0]) . '"></figure>';
            }
            $this->data['profile_picture'] = $profile;
        }
        $this->data['profile_picture'] = $profile;
        $this->db      = \Config\Database::connect();
        $this->builder = $this->db->table('settings');
        $this->builder->select('value');
        $this->builder->where('variable', 'payment_gateways_settings');
        $query = $this->builder->get()->getResultArray();
        if (count($query) == 1) {
            $settings = $query[0]['value'];
            $settings = json_decode($settings, true);
        }
        $this->stripe_secret_key = $settings['stripe_secret_key'];
        $this->stripe_currency = $settings['stripe_currency'];
    }

    /**
     * Show provider panel maintenance view when provider app maintenance mode is active.
     * When current time is between start and end of the maintenance schedule, partners
     * are redirected here and cannot access other pages until the end time has passed.
     * If maintenance is not active, redirect to dashboard.
     */
    public function maintenance()
    {
        $maintenance = is_provider_app_maintenance_mode();
        if (empty($maintenance['active'])) {
            return redirect()->to(base_url('partner/dashboard'));
        }
        setPageInfo($this->data, labels('provider_panel_maintenance', 'Maintenance') . ' | ' . labels('provider_panel', 'Provider Panel'), 'maintenance');
        $this->data['maintenance_message'] = $maintenance['message'] ?: labels('provider_panel_under_maintenance', 'The provider panel is currently under maintenance. Please try again later.');
        $this->data['maintenance_end_at'] = (int) $maintenance['end_at'];
        return view('backend/partner/template', $this->data);
    }

    public function review()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        setPageInfo($this->data, labels('reviews', 'Reviews') . ' | ' . labels('provider_panel', 'Provider Panel'), 'reviews');
        return view('backend/partner/template', $this->data);
    }
    public function review_list()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        $uri = service('uri');
        $partner_id = $this->userId;
        $ratings_model = new Service_ratings_model();
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

        $where = ["( s.user_id = {$partner_id} OR pb.partner_id = {$partner_id} )"];

        return json_encode($ratings_model->ratings_list(false, $search, $limit, $offset, $sort, $order, $where));
    }
    public function cash_collection()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        setPageInfo($this->data, labels('cash_collection', 'Cash Collection') . ' | ' . labels('provider_panel', 'Provider Panel'), 'cash_collection_history');

        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/partner/template', $this->data);
    }
    public function cash_collection_history_list()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $where['c.partner_id'] = $this->userId;
        print_r(json_encode($this->cash_collection->list(false, $search, $limit, $offset, $sort, $order, $where)));
    }
    public function settlement()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        setPageInfo($this->data, labels('commission_settlement', 'Commission Settlement') . ' | ' . labels('provider_panel', 'Provider Panel'), 'settlement_history');

        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/partner/template', $this->data);
    }
    public function settlement_list()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where['provider_id'] = $this->userId;
            print_r(json_encode($this->settle_commission->list(false, $search, $limit, $offset, $sort, $order, $where)));
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = 'Something went wrong';
            return $this->response->setJSON($response);
        }
    }
    public function payment()
    {
        if ($this->isLoggedIn) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            setPageInfo($this->data, labels('payment', 'Payment') . ' | ' . labels('provider_panel', 'Provider Panel'), 'payment');
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }
    public function subscription_list()
    {
        if ($this->ionAuth->loggedIn()) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }

            $user = $this->ionAuth->user()->row();

            // First get the active partner subscription record
            // $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $user->id, 'status' => 'active']);

            // // Then fetch the subscription details with translations from the main subscriptions table
            // $active_subscription_details = [];
            // if (!empty($active_partner_subscription)) {
            //     $subscriptionModel = new \App\Models\Subscription_model();
            //     $subscription_with_translations = $subscriptionModel->getWithTranslation($active_partner_subscription[0]['subscription_id'], get_current_language());

            //     if ($subscription_with_translations) {
            //         // Merge the partner subscription data with translated subscription data
            //         $active_subscription_details = array_merge($active_partner_subscription[0], $subscription_with_translations);
            //     } else {
            //         $active_subscription_details = $active_partner_subscription;
            //     }
            // }

            // // Only fetch available subscriptions if user doesn't have an active subscription
            // // This ensures we show only the active subscription when one exists
            // $subscription_details = [];
            // if (empty($active_subscription_details)) {
            //     // Fetch available subscriptions with translations for current language
            //     $subscriptionModel = new \App\Models\Subscription_model();
            //     $subscription_details = $subscriptionModel->getAllWithTranslations(get_current_language(), ['status' => 1, 'publish' => 1]);
            // }

            $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $this->userId, 'status' => 'active']);

            // Then fetch the subscription details with translations from the main subscriptions table
            $active_subscription_details = [];
            if (!empty($active_partner_subscription)) {
                $subscriptionModel = new \App\Models\Subscription_model();
                $subscription_with_translations = $subscriptionModel->getWithTranslation(
                    $active_partner_subscription[0]['subscription_id'],
                    get_current_language()
                );

                if ($subscription_with_translations) {
                    // Keep partner subscription as base
                    $active_subscription_details[0] = $active_partner_subscription[0];

                    // Add translations under a separate namespace
                    $active_subscription_details[0]['translations'] = $subscription_with_translations;
                } else {
                    $active_subscription_details[0] = $active_partner_subscription[0];
                }
            }
            // Fetch available subscriptions with translations for current language
            // Only show published subscriptions (status = 1 and publish = 1)
            $subscriptionModel = new \App\Models\Subscription_model();
            $subscription_details = $subscriptionModel->getAllWithTranslations(get_current_language(), ['status' => 1, 'publish' => 1]);

            $this->data['subscription_details'] = $subscription_details;
            $this->data['active_subscription_details'] = $active_subscription_details;

            $symbol =   get_currency();
            $razorpay = new Razorpay;
            $credentials = $razorpay->get_credentials();
            $key_id = $credentials['key'];
            $secret = $credentials['secret'];
            $data = get_settings('general_settings', true);
            $partner = fetch_details('partner_details', ['partner_id' => $this->userId])[0];
            $this->stripe = new Stripe;
            $stripe_credentials = $this->stripe->get_credentials();

            // Get Xendit credentials for consistency
            $xendit = new Xendit();
            $xendit_credentials = $xendit->get_credentials();

            $this->data['currency'] = $symbol;
            setPageInfo($this->data, labels('subscription', 'Subscription') . ' | ' . labels('provider_panel', 'Provider Panel'), 'subscription');
            $this->data['partner'] = $partner;
            $this->data['data'] = $data;
            $this->data['key_id'] = $key_id;
            $this->data['secret'] = $secret;
            $this->data['stripe_credentials'] = $stripe_credentials;
            $this->data['xendit_credentials'] = $xendit_credentials;
            $current_active_payment_gateway = get_settings('payment_gateways_settings', true);
            // Initialize payment gateway array to avoid undefined variable error when all gateways are disabled
            $payment_gateway = [];
            if (isset($current_active_payment_gateway['paypal_status']) && $current_active_payment_gateway['paypal_status'] === 'enable') {
                $payment_gateway[] = "paypal";
            }
            if (isset($current_active_payment_gateway['razorpayApiStatus']) && $current_active_payment_gateway['razorpayApiStatus'] === 'enable') {
                $payment_gateway[] = "razorpay";
            }
            if (isset($current_active_payment_gateway['paystack_status']) &&  $current_active_payment_gateway['paystack_status'] === 'enable') {
                $payment_gateway[] = "paystack";
            }
            if (isset($current_active_payment_gateway['stripe_status']) &&  $current_active_payment_gateway['stripe_status'] === 'enable') {
                $payment_gateway[] = "stripe";
            }
            if (isset($current_active_payment_gateway['flutterwave_status']) && $current_active_payment_gateway['flutterwave_status']  === 'enable') {
                $payment_gateway[] = "flutterwave";
            }
            if (isset($current_active_payment_gateway['xendit_status']) && $current_active_payment_gateway['xendit_status'] === 'enable') {
                $payment_gateway[] = "xendit";
            }
            if (isset($current_active_payment_gateway['cashfree_status']) && $current_active_payment_gateway['cashfree_status'] === 'enable') {
                $payment_gateway[] = "cashfree";
            }
            $check_payment_gateway = get_settings('payment_gateways_settings', true);
            $payment_gateway_setting =  $check_payment_gateway['payment_gateway_setting'];
            // if ($payment_gateway_setting == 0 || count($payment_gateway) == 0) {
            //     if ($payment_gateway_setting == 0) {
            //         $msg = "online payment option is disabled";
            //     } else if (count($payment_gateway) == 0) {
            //         $msg = "all payment gateways are currently disabled";
            //     }
            //     $this->session = \Config\Services::session();
            //     $_SESSION['toastMessage']  = 'Please contact the admin as ' . $msg;
            //     $_SESSION['toastMessageType']  = 'error';
            //     $this->session->markAsFlashdata('toastMessage');
            //     $this->session->markAsFlashdata('toastMessageType');
            //     return redirect()->to('partner')->withCookies();
            // }
            // echo "<pre>";
            // print_r($subscription_details);
            // exit;
            $this->data['payment_gateway'] = $payment_gateway;
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }
    public function make_payment_for_subscription()
    {
        try {
            $subscription_id = $_POST['subscription_id'];
            $subscription_details = fetch_details('subscriptions', ['id' => $subscription_id]);
            $partner_id = $this->ionAuth->user()->row()->id;
            $is_already_subscribe = fetch_details('partner_subscriptions', ['partner_id' => $partner_id, 'status' => 'active']);
            if (!empty($is_already_subscribe)) {
                $errorMessage = labels('already_have_active_subscription', 'Already have an active subscription');

                // Set session variables for toast message display
                $_SESSION['toastMessage'] = $errorMessage;
                $_SESSION['toastMessageType'] = 'error';
                return redirect()->back();
            }

            // Check if this is a free subscription (price = 0)
            // Use strict comparison and check both price and calculated price to be safe
            $subscription_price = floatval($subscription_details[0]['price']);
            $calculated_price = calculate_subscription_price($subscription_details[0]['id']);
            $final_price = floatval($calculated_price[0]['price_with_tax']);

            if ($subscription_price == 0 && $final_price == 0) {
                // Free subscriptions can be assigned even when payment gateway is disabled
                add_subscription($subscription_id, $partner_id);
                $errorMessage = labels('subscription_activated', 'Subscription Activated.');

                // Set session variables for toast message display
                $_SESSION['toastMessage'] = $errorMessage;
                $_SESSION['toastMessageType'] = 'success';
                return redirect()->back();
            } else {
                // For paid subscriptions, providers can always make payments
                // The online payment disabled setting only applies to customers, not providers
                $payment_gateway = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';

                // Validate that a payment method is selected
                if (empty($payment_gateway)) {
                    $errorMessage = labels('please_select_payment_gateway', 'Please select a payment gateway.');
                    session()->setFlashdata('error', $errorMessage);
                    return redirect()->back();
                }

                $price = calculate_subscription_price($subscription_details[0]['id']);
                $data['client_id'] = $this->userId;
                $data['package_id'] = $subscription_details[0]['id'];
                $data['net_amount'] = $price[0]['price_with_tax'];
                if ($payment_gateway == "stripe") {
                    try {
                        \Stripe\Stripe::setApiKey($this->stripe_secret_key);
                        $paymentLink = $this->generatePaymentLink($data);
                        return redirect()->to($paymentLink);
                    } catch (AuthenticationException $e) {
                        $errorMessage = labels('invalid_api_key_provided', 'Invalid API Key provided.');
                        session()->setFlashdata('error', $errorMessage);
                        return redirect()->back();
                    }
                } else if ($payment_gateway == "razorpay") {
                    try {
                        $paymentLink = $this->RazorpaygeneratePaymentLink($data);
                        return redirect()->to($paymentLink);
                    } catch (Exception $e) {
                        $errorMessage = labels('invalid_api_key_provided', 'Invalid API Key provided.');
                        session()->setFlashdata('error', $errorMessage);
                        return redirect()->back();
                    }
                } else if ($payment_gateway == "cashfree") {
                    try {
                        $paymentLink = $this->CashfreegeneratePaymentLink($data);
                        return redirect()->to($paymentLink);
                    } catch (Exception $e) {
                        $errorMessage = $e->getMessage();
                        if (empty($errorMessage)) {
                            $errorMessage = labels('something_went_wrong', 'Something went wrong');
                        }
                        session()->setFlashdata('error', $errorMessage);
                        return redirect()->back();
                    }
                } else if ($payment_gateway == "paystack") {
                    try {
                        $paymentLink = $this->PaystackgeneratePaymentLink($data);
                        return redirect()->to($paymentLink);
                    } catch (\Exception $e) {
                        $errorMessage = $e->getMessage() ?: labels('something_went_wrong', 'Something went wrong');
                        session()->setFlashdata('error', $errorMessage);
                        return redirect()->back();
                    }
                } else if ($payment_gateway == "paypal") {
                    try {
                        $paymentLink = $this->PaypalgeneratePaymentLink($data);
                        return redirect()->to($paymentLink);
                    } catch (Exception $e) {
                        $errorMessage = $e->getMessage();
                        if (empty($errorMessage)) {
                            $errorMessage = labels('something_went_wrong', 'Something went wrong');
                        }
                        session()->setFlashdata('error', $errorMessage);
                        return redirect()->back();
                    }
                } else if ($payment_gateway == "flutterwave") {
                    try {
                        $paymentLink = $this->FlutterwavegeneratePaymentLink($data);
                        return redirect()->to($paymentLink);
                    } catch (AuthenticationException $e) {
                        $errorMessage = labels('invalid_api_key_provided', 'Invalid API Key provided.');
                        session()->setFlashdata('error', $errorMessage);
                        return redirect()->back();
                    }
                } else if ($payment_gateway == "xendit") {
                    try {
                        $paymentLink = $this->XenditgeneratePaymentLink($data);
                        return redirect()->to($paymentLink);
                    } catch (Exception $e) {
                        $errorMessage = "Invalid API Key provided.";
                        session()->setFlashdata('error', $errorMessage);
                        return redirect()->back();
                    }
                } else {
                    // Handle case where payment gateway is not recognized
                    $errorMessage = labels('invalid_payment_gateway_selected', 'Invalid payment gateway selected. Please try again.');
                    session()->setFlashdata('error', $errorMessage);
                    return redirect()->back();
                }
            }
        } catch (\Throwable $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - make_payment_for_subscription()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    /**
     * Create or refresh a pending subscription record for paid plans.
     * This keeps the subscription in "pending" until the payment webhook confirms success.
     */
    private function createPendingSubscriptionRecord(int $subscriptionId, int $transactionId): void
    {
        $settings = get_settings('general_settings', true);
        if (!empty($settings['system_timezone'])) {
            date_default_timezone_set($settings['system_timezone']);
        }

        $subscriptionDetails = fetch_details('subscriptions', ['id' => $subscriptionId]);
        if (empty($subscriptionDetails)) {
            throw new \RuntimeException('Subscription not found.');
        }

        $subscription = $subscriptionDetails[0];
        $taxPercentage = 0;
        if (!empty($subscription['tax_id'])) {
            $taxDetails = fetch_details('taxes', ['id' => $subscription['tax_id']], ['percentage']);
            $taxPercentage = !empty($taxDetails) ? ($taxDetails[0]['percentage'] ?? 0) : 0;
        }

        $timestamp = date("Y-m-d H:i:s");
        $purchaseDate = date('Y-m-d');
        $subscriptionDuration = $subscription['duration'];
        $expiryDate = $purchaseDate;
        if ($subscriptionDuration !== "unlimited") {
            $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));
        }
        $pendingPayload = [
            'partner_id' => $this->userId,
            'subscription_id' => $subscriptionId,
            'is_payment' => "0",
            'status' => "pending",
            // Store placeholder dates so UI can show upcoming expiry while payment is pending.
            'purchase_date' => $purchaseDate,
            'expiry_date' => $expiryDate,
            'name' => $subscription['name'],
            'description' => $subscription['description'],
            'duration' => $subscription['duration'],
            'price' => $subscription['price'],
            'discount_price' => $subscription['discount_price'],
            'publish' => $subscription['publish'],
            'order_type' => $subscription['order_type'],
            'max_order_limit' => $subscription['max_order_limit'],
            'service_type' => $subscription['service_type'],
            'max_service_limit' => $subscription['max_service_limit'],
            'tax_type' => $subscription['tax_type'],
            'tax_id' => $subscription['tax_id'],
            'is_commision' => $subscription['is_commision'],
            'commission_threshold' => $subscription['commission_threshold'],
            'commission_percentage' => $subscription['commission_percentage'],
            'transaction_id' => $transactionId,
            'tax_percentage' => $taxPercentage,
            'updated_at' => $timestamp,
        ];

        $existingPending = fetch_details('partner_subscriptions', [
            'transaction_id' => $transactionId,
            'partner_id' => $this->userId,
        ]);

        if (!empty($existingPending)) {
            // Refresh pending record to guarantee the latest metadata and pending flags.
            update_details($pendingPayload, ['id' => $existingPending[0]['id']], 'partner_subscriptions');
        } else {
            $pendingPayload['created_at'] = $timestamp;
            insert_details($pendingPayload, 'partner_subscriptions');
        }

        // Persist commission context so limits stay in sync while the payment is pending.
        $commission = ($subscription['is_commision'] === "yes") ? $subscription['commission_percentage'] : 0;
        update_details(['admin_commission' => $commission], ['partner_id' => $this->userId], 'partner_details');
    }
    private function FlutterwavegeneratePaymentLink($param)
    {
        $user_data = fetch_details('users', ['id' => $this->userId])[0];
        $flutterwave = new Flutterwave();
        $flutterwave_credentials = $flutterwave->get_credentials();
        $secret_key = $flutterwave_credentials['secret_key'];
        $data = [
            'transaction_type' => 'transaction',
            'user_id' => $this->userId,
            'partner_id' =>  $this->userId,
            'order_id' =>  "0",
            'type' => 'flutterwave',
            'txn_id' => "0",
            'amount' => $param['net_amount'],
            'status' => 'pending',
            'currency_code' => NULL,
            'subscription_id' => $param['package_id'],
            'message' => 'txn_subscription_success'
        ];
        $insert_id = add_transaction($data);
        // Store a pending subscription record so the webhook can safely activate it later.
        $this->createPendingSubscriptionRecord($param['package_id'], $insert_id);
        $email = $user_data['email'];
        $amount = $param['net_amount'];
        $request = [
            'tx_ref' => time(),
            'amount' => $amount,
            'currency' => $flutterwave_credentials['currency_code'],
            'payment_options' => 'card',
            'redirect_url' => base_url() . '/partner/flutterwave_callback',
            'customer' => [
                'email' => $email,
                'name' =>  $user_data['username'],
            ],
            'meta' => [
                'subscription_id' => $param['package_id'],
                'price' => $param['net_amount'],
                'transaction_id' => $insert_id,
            ],
            'customizations' => [
                'title' => 'Paying for a subscription',
                'description' => 'subscription'
            ]
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($request),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $secret_key,
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        unset($curl);
        $res = json_decode($response);
        if ($res->status == 'success') {
            $link = $res->data->link;
        } else {
            $link = "";
        }
        return $link;
    }
    public function flutterwave_callback()
    {
        if ($_GET['status'] == 'cancelled') {
            $settings = get_settings('general_settings', true);
            $this->data['company'] = (isset($settings['company_title']) && $settings['company_title'] != "") ? $settings['company_title'] : "eDemand Services";
            setPageInfo($this->data, labels('payment_cancel', 'Payment Cancel') . ' | ' . labels('provider_panel', 'Provider Panel'), 'payment-cancel');
            $this->data['keywords'] = 'Payment Cancel, ';
            $this->data['description'] = 'Payment Cancel | ';
            $this->data['meta_description'] = '';
            return view('backend/partner/template', $this->data);
        } elseif ($_GET['status'] == 'successful') {
            $txid = $_GET['transaction_id'];
            $flutterwave = new Flutterwave();
            $flutterwave_credentials = $flutterwave->get_credentials();
            $secret_key = $flutterwave_credentials['secret_key'];
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/{$txid}/verify",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $secret_key
                ),
            ));
            $response = curl_exec($curl);
            unset($curl);
            $res = json_decode($response);
            if ($res->status && $res->status != 'error') {
                $amountPaid = $res->data->charged_amount;
                $amountToPay = $res->data->meta->price;
                if ($amountPaid >= $amountToPay) {
                    $this->data['company'] = getTranslatedSetting('general_settings', 'company_title');
                    setPageInfo($this->data, labels('payment_success', 'Payment Success') . ' | ' . labels('provider_panel', 'Provider Panel'), 'payment-success');
                    $this->data['keywords'] = 'Payment Success, ';
                    $this->data['description'] = 'Payment Success | ';
                    $this->data['meta_description'] = '';
                    header('Refresh: 2; URL=' . base_url() . '/partner/subscription');
                    return view('backend/partner/template', $this->data);
                } else {
                    $this->data['company'] = getTranslatedSetting('general_settings', 'company_title');
                    setPageInfo($this->data, labels('payment_cancel', 'Payment Cancel') . ' | ' . labels('provider_panel', 'Provider Panel'), 'payment-cancel');
                    $this->data['keywords'] = 'Payment Cancel, ';
                    $this->data['description'] = 'Payment Cancel | ';
                    $this->data['meta_description'] = '';
                    return view('backend/partner/template', $this->data);
                }
            } else {
                $this->data['company'] = getTranslatedSetting('general_settings', 'company_title');
                setPageInfo($this->data, labels('payment_cancel', 'Payment Cancel') . ' | ' . labels('provider_panel', 'Provider Panel'), 'payment-cancel');
                $this->data['keywords'] = 'Payment Cancel, ';
                $this->data['description'] = 'Payment Cancel | ';
                $this->data['meta_description'] = '';
                return view('backend/partner/template', $this->data);
            }
        }
    }
    private function PaystackgeneratePaymentLink($param)
    {
        try {
            $user_data = fetch_details('users', ['id' => $this->userId])[0];
            $paystack = new Paystack();
            $paystack_credentials = $paystack->get_credentials();
            $secret_key = $paystack_credentials['secret'];

            $txn_data = [
                'transaction_type' => 'transaction',
                'user_id'          => $this->userId,
                'partner_id'       => $this->userId,
                'order_id'         => "0",
                'type'             => 'paystack',
                'txn_id'           => "0",
                'amount'           => $param['net_amount'],
                'status'           => 'pending',
                'currency_code'    => null,
                'subscription_id'  => $param['package_id'],
                'message'          => 'txn_subscription_success',
            ];
            $insert_id = add_transaction($txn_data);
            $this->createPendingSubscriptionRecord($param['package_id'], $insert_id);

            $fields = [
                'email'        => $user_data['email'],
                'amount'       => $param['net_amount'] * 100,
                'currency'     => $paystack_credentials['currency'],
                'callback_url' => base_url('partner/paystack_return'),
                'metadata'     => json_encode([
                    'transaction_id' => $insert_id,
                    'cancel_action'  => base_url('partner/cancel'),
                ]),
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/initialize');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $secret_key,
                'Cache-Control: no-cache',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            unset($ch);

            $result_data = json_decode($result, true);
            if (!empty($result_data['data']['authorization_url'])) {
                $ps_reference = (string) ($result_data['data']['reference'] ?? '');
                if ($ps_reference !== '') {
                    update_details(['reference' => $ps_reference, 'type' => 'paystack'], ['id' => $insert_id], 'transactions');
                }
                return $result_data['data']['authorization_url'];
            }

            log_message('error', 'PaystackgeneratePaymentLink: no authorization_url: ' . json_encode($result_data));
            throw new Exception($result_data['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        } catch (Exception $e) {
            throw $e;
        } catch (\Throwable $th) {
            log_message('error', 'PaystackgeneratePaymentLink unexpected error: ' . $th->getMessage() . ' in ' . $th->getFile() . ':' . $th->getLine());
            throw new Exception($th->getMessage() ?: labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    private function RazorpaygeneratePaymentLink($param)
    {
        try {
            $razorpay = new Razorpay;
            $credentials = $razorpay->get_credentials();
            $key_id = $credentials['key'];
            $secret = $credentials['secret'];
            $api = new Api($key_id, $secret);
            $data = [
                'transaction_type' => 'transaction',
                'user_id' => $this->userId,
                'partner_id' =>  $this->userId,
                'order_id' =>  "0",
                'type' => 'razorpay',
                'txn_id' => "0",
                'amount' => $param['net_amount'] * 100,
                'status' => 'pending',
                'currency_code' => NULL,
                'subscription_id' => $param['package_id'],
                'message' => 'txn_subscription_success'
            ];
            $insert_id = add_transaction($data);
            $checkout = $api->paymentLink->create(array(
                'amount' =>  floatval($param['net_amount']) * 100,
                'currency' => $credentials['currency'],
                'accept_partial' => false,
                'notify' => array('sms' => true, 'email' => true),
                'reminder_enable' => true,
                'notes' => array('policy_name' => 'Subscription', 'transaction_id' => $insert_id),
                'callback_url' => base_url() . '/partner/stripe_success',
                'callback_method' => 'get'
            ));
            $this->createPendingSubscriptionRecord($param['package_id'], $insert_id);
            return $checkout['short_url'];
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - RazorpaygeneratePaymentLink()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    private function CashfreegeneratePaymentLink($param)
    {
        try {
            $cashfree = new Cashfree();
            $credentials = $cashfree->get_credentials();

            $data = [
                'transaction_type' => 'transaction',
                'user_id' => $this->userId,
                'partner_id' =>  $this->userId,
                'order_id' => "0",
                'type' => 'cashfree',
                'txn_id' => "0",
                'amount' => $param['net_amount'],
                'status' => 'pending',
                'currency_code' => strtoupper($credentials['currency'] ?? 'INR'),
                'subscription_id' => $param['package_id'],
                'message' => 'txn_subscription_success'
            ];
            $insert_id = add_transaction($data);
            $this->createPendingSubscriptionRecord($param['package_id'], $insert_id);

            $user_data = fetch_details('users', ['id' => $this->userId], ['username', 'email', 'phone']);
            $user_data = $user_data[0] ?? [];
            $phone_raw = $user_data['phone'] ?? '';
            $phone = preg_replace('/\D+/', '', (string) $phone_raw);
            if (empty($phone)) {
                $phone = '9999999999';
            }

            $gateway_order_id = 'subs_' . $insert_id . '_' . $param['package_id'] . '_' . time();

            $customer_name = trim($user_data['username'] ?? '');

            if ($customer_name !== '') {
                $customer_name = (strlen($customer_name) < 3) ? $user_data['username'] . '_' . $this->userId : $customer_name;
            } else {
                $customer_name = 'Partner_' . $this->userId;
            }
            $payload = [
                'order_id' => $gateway_order_id,
                'order_amount' => (float) number_format((float) $param['net_amount'], 2, '.', ''),
                'order_currency' => strtoupper($credentials['currency'] ?? 'INR'),
                'customer_details' => [
                    'customer_id' => 'partner_' . $this->userId,
                    'customer_name' => $customer_name,
                    'customer_email' => $user_data['email'] ?? ('partner' . $this->userId . '@example.com'),
                    'customer_phone' => $phone,
                ],
                'order_meta' => [
                    'return_url' => base_url('partner/cashfree_return?order_id={order_id}&payment_status={payment_status}'),
                ],
                'order_note' => 'Subscription payment for package #' . $param['package_id'],
                'order_tags' => [
                    'payment_for' => 'subscription',
                    'partner_id' => (string) $this->userId,
                    'subscription_id' => (string) $param['package_id'],
                    'transaction_id' => (string) $insert_id,
                ],
            ];

            $create_order = $cashfree->create_order($payload);
            if (!empty($create_order['error'])) {
                throw new \RuntimeException($create_order['message'] ?? 'Cashfree order could not be created');
            }

            update_details(['reference' => $gateway_order_id], ['id' => $insert_id], 'transactions');

            $payment_session_id = (string) ($create_order['payment_session_id'] ?? '');
            if (empty($payment_session_id)) {
                throw new \RuntimeException('Cashfree payment session id not available from create order response');
            }

            return base_url('partner/cashfree_checkout?payment_session_id=' . rawurlencode($payment_session_id));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - CashfreegeneratePaymentLink()');
            throw $th;
        }
    }

    public function cashfree_checkout()
    {
        try {
            if (!$this->isLoggedIn) {
                return redirect('partner/login');
            }

            $payment_session_id = trim((string) $this->request->getGet('payment_session_id'));
            if ($payment_session_id === '') {
                session()->setFlashdata('error', 'Cashfree payment session is missing.');
                return redirect()->to(base_url('partner/subscription'));
            }

            $cashfree = new Cashfree();
            $credentials = $cashfree->get_credentials();
            $mode = (($credentials['mode'] ?? 'test') === 'live') ? 'production' : 'sandbox';
            $fallback_url = base_url('partner/subscription');

            $html = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Redirecting to Cashfree Checkout</title>
                    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
                    <style>
                        body{font-family:Arial,sans-serif;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fb;color:#1f2937}
                        .card{background:#fff;padding:24px 28px;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.08);max-width:420px;text-align:center}
                        .muted{color:#6b7280;font-size:14px;margin-top:8px}
                        .link{display:inline-block;margin-top:14px;color:#0d6efd;text-decoration:none}
                    </style>
                </head>
                <body>
                    <div class="card">
                        <div>Redirecting to secure payment page...</div>
                        <div class="muted">If you are not redirected, please use the link below.</div>
                        <a class="link" href="' . esc($fallback_url, 'attr') . '">Back to subscriptions</a>
                    </div>
                    <script>
                        (function () {
                            var cashfree = Cashfree({ mode: "' . esc($mode, 'js') . '" });
                            cashfree.checkout({
                                paymentSessionId: "' . esc($payment_session_id, 'js') . '",
                                redirectTarget: "_self"
                            }).catch(function () {
                                window.location.href = "' . esc($fallback_url, 'js') . '";
                            });
                        })();
                    </script>
                </body>
            </html>';

            return $this->response->setBody($html);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - cashfree_checkout()');
            session()->setFlashdata('error', 'Unable to initialize Cashfree checkout.');
            return redirect()->to(base_url('partner/subscription'));
        }
    }

    public function cashfree_return()
    {
        try {
            if (!$this->isLoggedIn) {
                return redirect('partner/login');
            }

            $order_id = trim((string) $this->request->getGet('order_id'));

            // If we have an order_id, store it as the transaction reference.
            // The order_id format is: subs_{transaction_id}_{package_id}_{timestamp}
            // We save it now so the webhook can match and update the correct transaction later.
            if ($order_id !== '' && preg_match('/^subs_(\d+)_(\d+)_\d+$/', $order_id, $matches)) {
                $transaction_id = (int) $matches[1];
                if ($transaction_id > 0) {
                    update_details(['reference' => $order_id], ['id' => $transaction_id], 'transactions');
                }
            }

            // We do NOT try to decide success/failure here.
            // Cashfree's callback only returns order_id — the actual payment status
            // may not be finalized yet. The webhook will update the DB asynchronously.
            // Instead, show a pending/processing page and redirect to the subscription
            // page, which will reflect the correct status once the webhook fires.
            $subscription_url = base_url('partner/subscription');

            $html = '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>'.labels('processing_your_payment', 'Processing your payment').'</title>
                <style>
                    body{font-family:Arial,sans-serif;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fb;color:#1f2937}
                    .card{background:#fff;padding:32px 36px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.08);max-width:420px;text-align:center}
                    .spinner{width:42px;height:42px;border:4px solid #e5e7eb;border-top:4px solid #0d6efd;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 20px}
                    @keyframes spin{to{transform:rotate(360deg)}}
                    h2{margin:0 0 8px;font-size:20px}
                    .muted{color:#6b7280;font-size:14px;margin-top:6px;line-height:1.5}
                    .link{display:inline-block;margin-top:16px;color:#0d6efd;text-decoration:none;font-size:14px}
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="spinner"></div>
                    <h2>'.labels('processing_your_payment', 'Processing your payment').'&hellip;</h2>
                    <p class="muted">'.labels('please_wait_you_will_be_redirected_to_your_subscription_page_in_a_moment', 'Please wait. You will be redirected to your subscription page in a moment.').'</p>
                    <a class="link" href="' . esc($subscription_url, 'attr') . '">'.labels('click_here_if_not_redirected_automatically', 'Click here if not redirected automatically').'</a>
                </div>
                <script>
                    // Redirect to subscription page after 4 seconds.
                    // The subscription page shows the live status from the database,
                    // which is updated once the payment gateway webhook fires.
                    setTimeout(function () {
                        window.location.href = "' . esc($subscription_url, 'js') . '";
                    }, 4000);
                </script>
            </body>
            </html>';

            return $this->response->setBody($html);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - cashfree_return()');
            // On any unexpected error, go straight to the subscription page.
            return redirect()->to(base_url('partner/subscription'));
        }
    }

    public function paystack_return()
    {
        try {
            if (!$this->isLoggedIn) {
                return redirect('partner/login');
            }

            $subscription_url = base_url('partner/subscription');

            $html = '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>' . labels('processing_your_payment', 'Processing your payment') . '</title>
                <style>
                    body{font-family:Arial,sans-serif;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fb;color:#1f2937}
                    .card{background:#fff;padding:32px 36px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.08);max-width:420px;text-align:center}
                    .spinner{width:42px;height:42px;border:4px solid #e5e7eb;border-top:4px solid #0d6efd;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 20px}
                    @keyframes spin{to{transform:rotate(360deg)}}
                    h2{margin:0 0 8px;font-size:20px}
                    .muted{color:#6b7280;font-size:14px;margin-top:6px;line-height:1.5}
                    .link{display:inline-block;margin-top:16px;color:#0d6efd;text-decoration:none;font-size:14px}
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="spinner"></div>
                    <h2>' . labels('processing_your_payment', 'Processing your payment') . '&hellip;</h2>
                    <p class="muted">' . labels('please_wait_you_will_be_redirected_to_your_subscription_page_in_a_moment', 'Please wait. You will be redirected to your subscription page in a moment.') . '</p>
                    <a class="link" href="' . esc($subscription_url, 'attr') . '">' . labels('click_here_if_not_redirected_automatically', 'Click here if not redirected automatically') . '</a>
                </div>
                <script>
                    setTimeout(function () {
                        window.location.href = "' . esc($subscription_url, 'js') . '";
                    }, 4000);
                </script>
            </body>
            </html>';

            return $this->response->setBody($html);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - paystack_return()');
            return redirect()->to(base_url('partner/subscription'));
        }
    }

    private function deriveCashfreeStatus(array $order_response, array $payments_response): string
    {
        // Prefer payment-level status when available.
        $payments = $payments_response;
        if (isset($payments_response['data']) && is_array($payments_response['data'])) {
            $payments = $payments_response['data'];
        }
        if (isset($payments_response[0]) && is_array($payments_response[0])) {
            $payments = $payments_response;
        }

        if (is_array($payments)) {
            foreach ($payments as $payment) {
                if (!is_array($payment)) {
                    continue;
                }
                $payment_status = strtoupper((string) ($payment['payment_status'] ?? ''));
                if ($payment_status === 'SUCCESS') {
                    return 'success';
                }
            }
            foreach ($payments as $payment) {
                if (!is_array($payment)) {
                    continue;
                }
                $payment_status = strtoupper((string) ($payment['payment_status'] ?? ''));
                if ($payment_status === 'FAILED') {
                    return 'failed';
                }
            }
        }

        $order_status = strtoupper((string) ($order_response['order_status'] ?? ''));
        if (in_array($order_status, ['PAID', 'SUCCESS', 'COMPLETED'], true)) {
            return 'success';
        }
        if (in_array($order_status, ['FAILED', 'CANCELLED', 'EXPIRED', 'TERMINATED'], true)) {
            return 'failed';
        }

        return 'pending';
    }

    private function extractCashfreePaymentId(array $payments_response): string
    {
        $payments = $payments_response;
        if (isset($payments_response['data']) && is_array($payments_response['data'])) {
            $payments = $payments_response['data'];
        }
        if (!is_array($payments)) {
            return '';
        }
        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            if (!empty($payment['cf_payment_id'])) {
                return (string) $payment['cf_payment_id'];
            }
        }
        return '';
    }

    /**
     * Create a PayPal Orders v2 checkout for a partner-panel subscription
     * purchase and return the buyer-facing approval URL.
     *
     * The pending `transactions` row carries the PayPal order id in
     * `reference`; `paypal_return()` and the webhook finalise it idempotently.
     */
    public function PaypalgeneratePaymentLink($param)
    {
        try {
            $this->paypal_lib = new \App\Libraries\Paypal();
            if (!$this->paypal_lib->is_enabled()) {
                throw new \RuntimeException(labels('error_occured', 'An error occurred'));
            }

            $user        = fetch_details('users', ['id' => $this->userId]);
            $payer_email = $user[0]['email'] ?? '';
            $currency    = $this->paypal_lib->get_currency();

            $insert_id = add_transaction([
                'transaction_type' => 'transaction',
                'user_id'          => $this->userId,
                'partner_id'       => $this->userId,
                'order_id'         => "0",
                'type'             => 'paypal',
                'txn_id'           => "0",
                'amount'           => $param['net_amount'],
                'status'           => 'pending',
                'currency_code'    => strtoupper($currency),
                'subscription_id'  => $param['package_id'],
                'message'          => 'txn_subscription_success',
            ]);
            $this->createPendingSubscriptionRecord($param['package_id'], $insert_id);

            $order = $this->paypal_lib->createOrder([
                'amount'     => $param['net_amount'],
                'currency'   => $currency,
                'custom_id'  => $insert_id . '|' . $payer_email . '|subscription',
                'invoice_id' => 'subs_' . $insert_id . '_' . time(),
                'item_name'  => 'Subscription #' . $param['package_id'],
                'return_url' => base_url('partner/paypal_return'),
                'cancel_url' => base_url('partner/paypal_return?cancelled=1'),
            ]);

            $approval_url = $this->paypal_lib->getApprovalUrl($order);
            if (!empty($order['error']) || empty($order['id']) || empty($approval_url)) {
                log_the_responce(
                    'paypal createOrder failed :: ' . json_encode($order),
                    date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - PaypalgeneratePaymentLink()'
                );
                throw new \RuntimeException(labels('error_occured', 'An error occurred'));
            }

            update_details(['reference' => $order['id']], ['id' => $insert_id], 'transactions');

            return $approval_url;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - PaypalgeneratePaymentLink()');
            throw $th;
        }
    }

    /**
     * PayPal return handler for partner-panel subscriptions.
     *
     * PayPal redirects the provider here with `?token=<order id>` after approval
     * (or `cancelled=1` from the cancel URL). Orders v2 requires an explicit
     * capture, so the approved order IS captured here — but the subscription is
     * deliberately NOT activated here. Capturing triggers the
     * PAYMENT.CAPTURE.COMPLETED webhook, and `PaypalWebhook` is the single
     * source of truth for subscription activation — consistent with the
     * Cashfree / Stripe / Razorpay / Flutterwave panel flows.
     */
    public function paypal_return()
    {
        try {
            if (!$this->isLoggedIn) {
                return redirect('partner/login');
            }

            $paypal_order_id = trim((string) $this->request->getGet('token'));
            $cancelled       = $this->request->getGet('cancelled') !== null;

            if ($cancelled || $paypal_order_id === '') {
                return redirect()->to(base_url('partner/cancel'));
            }

            $transaction = fetch_details('transactions', ['type' => 'paypal', 'reference' => $paypal_order_id]);
            if (empty($transaction)) {
                return redirect()->to(base_url('partner/cancel'));
            }

            // Capture the approved order so the payment completes. This fires
            // PAYMENT.CAPTURE.COMPLETED; PaypalWebhook then activates the
            // subscription. No subscription state is written here.
            $paypal  = new \App\Libraries\Paypal();
            $capture = $paypal->captureOrder($paypal_order_id);

            if (!empty($capture['error'])) {
                // A duplicate return hit on an already-captured order is still a success.
                $order_state = $paypal->getOrder($paypal_order_id);
                if (empty($order_state['error']) && ($order_state['status'] ?? '') === 'COMPLETED') {
                    return redirect()->to(base_url('partner/stripe_success'));
                }
                log_the_responce(
                    'paypal capture failed :: ' . json_encode($capture),
                    date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - paypal_return()'
                );
                return redirect()->to(base_url('partner/cancel'));
            }

            return redirect()->to(base_url('partner/stripe_success'));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - paypal_return()');
            return redirect()->to(base_url('partner/cancel'));
        }
    }
    private function XenditgeneratePaymentLink($param)
    {
        try {
            // Get user data for payment
            $user_data = fetch_details('users', ['id' => $this->userId])[0];

            // Initialize Xendit library
            $xendit = new Xendit();
            $xendit_credentials = $xendit->get_credentials();

            // Create transaction record first
            $data = [
                'transaction_type' => 'transaction',
                'user_id' => $this->userId,
                'partner_id' => $this->userId,
                'order_id' => "0",
                'type' => 'xendit',
                'txn_id' => "0",
                'amount' => $param['net_amount'],
                'status' => 'pending',
                'currency_code' => NULL,
                'subscription_id' => $param['package_id'],
                'message' => 'txn_subscription_success'
            ];
            $insert_id = add_transaction($data);

            // Log transaction creation
            log_message('error', 'Xendit subscription - Transaction created with ID: ' . $insert_id . ' for partner: ' . $this->userId . ', subscription: ' . $param['package_id']);

            // Send payment pending notification to provider
            if ($insert_id) {
                send_subscription_payment_status_notification($insert_id, 'pending');
            }

            // Add subscription record with pending payment
            $this->createPendingSubscriptionRecord($param['package_id'], $insert_id);

            // Log subscription creation
            log_message('error', 'Xendit subscription - Subscription record created for partner: ' . $this->userId . ', subscription: ' . $param['package_id']);

            // Prepare Xendit invoice data
            $external_id = 'subscription_' . $param['package_id'] . '_' . $this->userId . '_' . time();
            $invoice_data = [
                'external_id' => $external_id,
                'amount' => floatval($param['net_amount']),
                'customer_name' => $user_data['username'],
                'customer_email' => !empty($user_data['email']) ? $user_data['email'] : 'partner@edemand.com',
                'customer_phone' => $user_data['phone'] ?? '',
                'success_url' => base_url() . 'partner/xendit_subscription_success?external_id=' . $external_id . '&status=success',
                'failure_url' => base_url() . 'partner/xendit_subscription_success?external_id=' . $external_id . '&status=failed',
                'description' => 'Subscription Payment for Partner #' . $this->userId,
                'metadata' => [
                    'subscription_id' => $param['package_id'],
                    'partner_id' => $this->userId,
                    'transaction_id' => $insert_id,
                    'payment_type' => 'subscription'
                ]
            ];

            // Log invoice data preparation
            log_message('error', 'Xendit subscription - Creating invoice with external_id: ' . $external_id . ', transaction_id: ' . $insert_id);

            // Create Xendit invoice
            $invoice = $xendit->create_invoice($invoice_data);

            if ($invoice && isset($invoice['invoice_url'])) {
                // Update transaction with external_id and invoice_id for tracking
                $transaction_update = [
                    'txn_id' => $invoice['external_id']
                ];
                update_details($transaction_update, ['id' => $insert_id], 'transactions');

                // Log successful payment link generation using log_the_responce for consistency
                log_message('error', 'Xendit subscription - Payment link generated successfully for partner: ' . $this->userId . ', external_id: ' . $external_id . ', invoice_url: ' . $invoice['invoice_url']);
                return $invoice['invoice_url'];
            } else {
                // Log failed invoice creation using log_the_responce for consistency
                log_message('error', 'Xendit subscription - Failed to create Xendit invoice for partner: ' . $this->userId);
                throw new Exception('Failed to create payment invoice');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - XenditgeneratePaymentLink()');
            throw new Exception("Xendit payment generation failed: " . $th->getMessage());
        }
    }
    private function generatePaymentLink($param)
    {
        try {
            $this->session       = \Config\Services::session();
            $client_id = $param['client_id'];
            $package_id = $param['package_id'];
            $amount = floatval($param['net_amount']) * 100;
            $this->db      = \Config\Database::connect();

            // Use query builder with parameter binding to prevent SQL injection
            // Get subscription package name using safe parameterized query
            $package_name = $this->db->table('subscriptions')
                ->where('id', $package_id)
                ->get()
                ->getFirstRow();
            $package_name = ($package_name) ? $package_name->name : '';

            // Use query builder with parameter binding to prevent SQL injection
            // Get user details using safe parameterized query
            $result = $this->db->table('users')
                ->where('id', $client_id)
                ->get()
                ->getFirstRow();
            if ($result->strip_id == '') {
                $customer = \Stripe\Customer::create(array(
                    'email' => $result->email,
                    'description' => $result->id
                ));
                $this->db->table('users')->update(['strip_id' => $customer['id']], ['id' => $client_id]);
                $stripid = $customer['id'];
                $email = $result->email;
            } else {
                $stripid = $result->strip_id;
                $email = $result->email;
            }
            $this->session->remove('POSTDATA');
            $this->session->set('POSTDATA', $param);
            $data = [
                'transaction_type' => 'transaction',
                'user_id' => $this->userId,
                'partner_id' =>  $this->userId,
                'order_id' =>  "0",
                'type' => 'stripe',
                'txn_id' => "0",
                'amount' => $param['net_amount'],
                'status' => 'pending',
                'currency_code' => NULL,
                'subscription_id' => $package_id,
                'message' => 'txn_subscription_success'
            ];
            $insert_id = add_transaction($data);

            // Send payment pending notification to provider
            if ($insert_id) {
                send_subscription_payment_status_notification($insert_id, 'pending');
            }

            $metadata = ['transaction_id' => $insert_id];
            $checkout_payment = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $this->stripe_currency,
                        'unit_amount' => $amount,
                        'product_data' => [
                            'name' => $package_name,
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'customer' => $stripid,
                'client_reference_id' => $client_id,
                'mode' => 'payment',
                'success_url' => base_url() . '/partner/stripe_success',
                'cancel_url' => base_url() . '/partner/cancel',
                'payment_intent_data' => [
                    'metadata' => $metadata
                ],
            ]);
            $payment_id = $checkout_payment['payment_intent'];
            $this->session->remove('payment_intent');
            $this->session->set('payment_intent', $payment_id);
            $this->createPendingSubscriptionRecord($package_id, $insert_id);
            return $checkout_payment['url'];
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - generatePaymentLink()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function success()
    {
        $this->data['company'] = getTranslatedSetting('general_settings', 'company_title');
        setPageInfo($this->data, labels('payment_success', 'Payment Success') . ' | ' . labels('provider_panel', 'Provider Panel'), 'payment-success');
        $this->data['keywords'] = 'Payment Success, ';
        $this->data['description'] = 'Payment Success | ';
        $this->data['meta_description'] = '';

        // Get latest successful subscription transaction for event tracking
        $transaction = fetch_details('transactions', [
            'user_id' => $this->userId,
            'status' => 'success',
            'transaction_type' => 'transaction'
        ], '*', 1, 0, 'id', 'DESC');

        if (!empty($transaction) && !empty($transaction[0]['subscription_id'])) {
            $subscriptionData = fetch_details('subscriptions', ['id' => $transaction[0]['subscription_id']]);
            $this->data['clarity_event_data'] = [
                'clarity_event' => 'subscription_purchase',
                'subscription_id' => $transaction[0]['subscription_id'],
                'subscription_name' => !empty($subscriptionData) ? $subscriptionData[0]['name'] ?? '' : '',
                'price' => $transaction[0]['amount'] ?? '',
                'payment_method' => $transaction[0]['type'] ?? ''
            ];
        }

        header('Refresh: 2; URL=' . base_url() . 'partner/subscription');
        return view('backend/partner/template', $this->data);
    }
    public function cancel()
    {
        $this->data['company'] = getTranslatedSetting('general_settings', 'company_title');
        setPageInfo($this->data, labels('payment_cancel', 'Payment Cancel') . ' | ' . labels('provider_panel', 'Provider Panel'), 'payment-cancel');
        $this->data['keywords'] = 'Payment Cancel, ';
        $this->data['description'] = 'Payment Cancel | ';
        $this->data['meta_description'] = '';

        // Get latest pending subscription transaction for event tracking
        // Explicitly pass 0 as offset so BaseBuilder::limit receives valid numeric arguments.
        $transaction = fetch_details('transactions', [
            'user_id' => $this->userId,
            'status' => 'pending',
            'transaction_type' => 'transaction'
        ], '*', 1, 0, 'id', 'DESC');

        if (!empty($transaction) && !empty($transaction[0]['subscription_id'])) {
            $this->data['clarity_event_data'] = [
                'clarity_event' => 'subscription_cancelled',
                'subscription_id' => $transaction[0]['subscription_id']
            ];
        }

        return view('backend/partner/template', $this->data);
    }

    /**
     * Xendit invoice return URL for partner-panel subscriptions.
     *
     * Xendit confirms invoice payments asynchronously via `XenditWebhook`,
     * which is the single source of truth for subscription activation
     * (consistent with the PayPal / Cashfree / Stripe / Razorpay / Flutterwave
     * panel flows). This handler only shows the outcome page — it performs no
     * transaction or subscription writes.
     */
    public function xendit_subscription_success()
    {
        try {
            if ($this->request->getGet('status') === 'failed') {
                return $this->cancel();
            }
            return $this->success();
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - xendit_subscription_success()');
            return $this->cancel();
        }
    }

    public function subscription_history()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        $this->data['company'] = getTranslatedSetting('general_settings', 'company_title');
        setPageInfo($this->data, labels('subscription_history', 'Subscription History') . ' | ' . labels('provider_panel', 'Provider Panel'), 'subscription_history');
        $this->data['keywords'] = 'Subscription History , ';
        $this->data['description'] = 'Subscription History   ';
        $this->data['meta_description'] = '';
        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];
        return view('backend/partner/template', $this->data);
    }
    public function subscription_history_list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $where['ps.partner_id'] = $this->userId;
        print_r(json_encode($this->subscription->list(false, $search, $limit, $offset, $sort, $order, $where)));
    }
    public function razorpay_payment()
    {
        try {
            $subscription_details = fetch_details('subscriptions', ['status' => 1, 'publish' => 1]);
            $this->data['subscription_details'] = $subscription_details;
            $user = $this->ionAuth->user()->row();
            $db      = \Config\Database::connect();
            $builder = $db->table('partner_subscriptions ps');

            // First get the active partner subscription record
            $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $user->id, 'status' => 'active']);

            // Then fetch the subscription details with translations from the main subscriptions table
            $active_subscription_details = [];
            if (!empty($active_partner_subscription)) {
                $subscriptionModel = new \App\Models\Subscription_model();
                $subscription_with_translations = $subscriptionModel->getWithTranslation($active_partner_subscription[0]['subscription_id'], get_current_language());

                if ($subscription_with_translations) {
                    // Merge the partner subscription data with translated subscription data
                    // Wrap in array to maintain the expected [0] index structure
                    $active_subscription_details = [array_merge($active_partner_subscription[0], $subscription_with_translations)];
                } else {
                    $active_subscription_details = $active_partner_subscription;
                }
            }

            $this->data['active_subscription_details'] = $active_subscription_details;
            $razorpay = new Razorpay;
            $credentials = $razorpay->get_credentials();
            $key_id = $credentials['key'];
            $secret = $credentials['secret'];
            $api = new Api($key_id, $secret);
            $order = $api->order->create([
                'receipt' => 'order_receipt_01',
                'amount' => 500,
                'currency' => "INR",
            ]);
            $data = get_settings('general_settings', true);
            $partner = fetch_details('partner_details', ['partner_id' => $this->userId])[0];
            $symbol =   get_currency();
            $this->data['currency'] = $symbol;
            setPageInfo($this->data, labels('subscription', 'Subscription') . ' | ' . labels('provider_panel', 'Provider Panel'), 'subscription');
            $this->data['partner'] = $partner;
            $this->data['order'] = $order;
            $this->data['data'] = $data;
            $this->data['key_id'] = $key_id;
            $this->data['secret'] = $secret;
            return view('backend/partner/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - razorpay_payment()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function settlement_cashcollection_history()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        $this->data['company'] = getTranslatedSetting('general_settings', 'company_title');
        setPageInfo($this->data, labels('booking_payment_management', 'Booking payment management') . ' | ' . labels('provider_panel', 'Provider Panel'), 'settlement_cashcollection_history');
        $this->data['keywords'] = 'Booking payment management , ';
        $this->data['description'] = 'Booking payment management   ';
        $this->data['meta_description'] = '';
        $partner_data = $this->db->table('users u')
            ->select('u.id,u.username,pd.company_name')
            ->join('partner_details pd', 'pd.partner_id = u.id')
            ->where('u.id',   $this->userId)
            ->get()->getResultArray();
        $this->data['partner'] = $partner_data;
        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];
        return view('backend/partner/template', $this->data);
    }
    public function settlement_cashcollection_history_list()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $this->userId;
            $Settlement_CashCollection_history_model = new Settlement_CashCollection_history_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where = ['sc.provider_id' => $partner_id];
            $data = $Settlement_CashCollection_history_model->list($where, 'no', false, $limit, $offset, $sort, $order, $search);
            return $data;
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = 'Something went wrong';
            return $this->response->setJSON($response);
        }
    }
    public function save_web_token()
    {
        try {
            $token = $this->request->getPost('token');
            // Get language_code from POST data, or use user's language_code from database, or null
            $languageCode = get_current_language();

            store_users_fcm_id($this->userId, $token, 'provider_panel', null, $languageCode);
            // update_details(['panel_fcm_id' => $token,], ['id' => $user[0]['id']], 'users');
            print_r(json_encode("token saved"));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Partner.php - save_web_token()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    private function getRowData(
        string $table,
        array $conditions = [],
        $columns = '*',
        ?int $limit = null,
        ?string $orderBy = null,
        string $orderDir = 'DESC'
    ): array {
        $db = \Config\Database::connect();
        $builder = $db->table($table);

        // Select columns
        $builder->select($columns);

        // Apply conditions
        foreach ($conditions as $key => $value) {
            if (strpos($key, '!=') !== false) {
                // Handle "!=" conditions
                $field = trim(str_replace('!=', '', $key));
                $builder->where($field . ' !=', $value);
            } else {
                $builder->where($key, $value);
            }
        }

        // Apply ordering
        if (!empty($orderBy)) {
            $builder->orderBy($orderBy, $orderDir);
        }

        // Apply limit
        if (!empty($limit)) {
            $builder->limit($limit);
        }

        return $builder->get()->getResultArray();
    }
}
