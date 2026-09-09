<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Libraries\Flutterwave;
use App\Libraries\Razorpay;
use App\Libraries\Cashfree;
use App\Libraries\Paypal;
use App\Libraries\Paystack;
use App\Libraries\Stripe;
use App\Libraries\StripeMoney;
use App\Libraries\Xendit;
use App\Models\Transaction_model;
use App\Models\Subscription_model;
use App\Models\TranslatedSubscriptionModel;
use Exception;

class SubscriptionApiController extends BaseController
{
    protected $excluded_routes =
    [
        "partner/api/v1/index",
        "partner/api/v1",
        "partner/api/v1/paypal_return",
        "partner/api/v1/app_payment_status",
        "partner/api/v1/paystack_transaction_webview",
        "partner/api/v1/app_paystack_payment_status",
        "partner/api/v1/flutterwave_payment_status",
        "partner/api/v1/xendit_payment_status",
    ];

    private  $user_details = [];
    protected Razorpay $razorpay;
    protected Cashfree $cashfree;
    protected Stripe $stripe;
    protected $data, $validation, $paypal_lib;

    function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $current_uri = uri_string();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
            die();
        }
        $this->razorpay = new Razorpay();
        $this->cashfree = new Cashfree();
        // Stripe SDK wrapper (used by partner subscription payment intent endpoints).
        // This is initialized here (like customer APIs) so controllers can call `$this->stripe`
        // without re-creating the client each request.
        $this->stripe = new Stripe();
        helper('session');
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('api-doc.txt')));
    }

    /**
     * Helper to get the actual payable price of a subscription.
     * This mirrors how payment gateways calculate the amount:
     * - If a discount price is set (non-zero) we use that.
     * - Otherwise we fall back to the main price.
     *
     * NOTE:
     * We keep this logic in one place so that:
     * - Partner panel subscription purchase flow
     * - Provider APIs subscription purchase flow
     * always agree on when a plan is really "free" and when payment is required.
     *
     * @param array $subscription Single subscription row from DB.
     * @return string Payable price as string (to stay consistent with existing code).
     */
    private function getEffectiveSubscriptionPrice(array $subscription): string
    {
        // Use discount_price when it is set and non-zero, otherwise use price.
        // This is the same behaviour we already use in payment setup (e.g. Razorpay).
        if (isset($subscription['discount_price']) && $subscription['discount_price'] !== "0") {
            return $subscription['discount_price'];
        }

        return $subscription['price'] ?? "0";
    }

    /**
     * Helper to decide if a subscription is truly free.
     * A subscription is free only when the effective payable price is zero.
     *
     * Keeping this as a separate helper makes the intent very clear
     * and avoids subtle bugs where price=0 but discount_price>0 (or vice‑versa).
     *
     * @param array $subscription
     * @return bool
     */
    private function isFreeSubscription(array $subscription): bool
    {
        return $this->getEffectiveSubscriptionPrice($subscription) === "0";
    }

    public function get_subscription()
    {
        try {
            $where = [];
            $subscription_id = $this->request->getPost('subscription_id');
            if (null !== $subscription_id) {
                $where['id'] = $subscription_id;
            }
            $where['status'] = 1;
            $where['publish'] = 1;
            $subscription_details = fetch_details('subscriptions', $where);

            // Get current language from request header for translations
            $currentLanguage = get_current_language_from_request();

            // Get default language from database
            $defaultLanguage = 'en';
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            if (!empty($languages)) {
                $defaultLanguage = $languages[0]['code'];
            }

            // Initialize subscription translation model
            $subscriptionTranslationModel = new TranslatedSubscriptionModel();

            foreach ($subscription_details as $row) {
                $tempRow['id'] = $row['id'];

                // PRIORITY LOGIC FOR NAME AND DESCRIPTION:
                // Get translations for requested language and default language
                $translation = $subscriptionTranslationModel->getTranslation($row['id'], $currentLanguage);
                if (!$translation && $currentLanguage !== $defaultLanguage) {
                    $translation = $subscriptionTranslationModel->getTranslation($row['id'], $defaultLanguage);
                }
                $defaultTranslation = $subscriptionTranslationModel->getTranslation($row['id'], $defaultLanguage);

                // Set main fields: use default language translations or fallback to main table
                $tempRow['name'] = $defaultTranslation['name'] ?? $row['name'];
                $tempRow['description'] = $defaultTranslation['description'] ?? $row['description'];

                // Set translated fields: use requested language, fallback to default language, then main table
                $tempRow['translated_name'] = $translation['name'] ?? $defaultTranslation['name'] ?? $row['name'];
                $tempRow['translated_description'] = $translation['description'] ?? $defaultTranslation['description'] ?? $row['description'];

                $tempRow['duration'] = $row['duration'];
                $tempRow['price'] = $row['price'];
                $tempRow['discount_price'] = $row['discount_price'];
                $tempRow['publish'] = $row['publish'];
                $tempRow['order_type'] = $row['order_type'];
                $tempRow['max_order_limit'] = ($row['order_type'] == "limited") ? $row['max_order_limit'] : "-";
                $tempRow['service_type'] = $row['service_type'];
                $tempRow['max_service_limit'] = $row['max_service_limit'];
                $tempRow['tax_type'] = $row['tax_type'];
                $tempRow['tax_id'] = $row['tax_id'];
                $tempRow['is_commision'] = $row['is_commision'];
                $tempRow['commission_threshold'] = $row['commission_threshold'];
                $tempRow['commission_percentage'] = $row['commission_percentage'];
                $tempRow['status'] = $row['status'];
                $taxPercentageData = fetch_details('taxes', ['id' => $row['tax_id']], ['percentage']);
                if (!empty($taxPercentageData)) {
                    $taxPercentage = $taxPercentageData[0]['percentage'];
                } else {
                    $taxPercentage = 0;
                }
                $tempRow['tax_percentage'] = $taxPercentage;
                if ($row['discount_price'] == "0") {
                    if ($row['tax_type'] == "excluded") {
                        $tempRow['tax_value'] = number_format((intval(($row['price'] * ($taxPercentage) / 100))), 2);
                        $tempRow['price_with_tax']  = strval($row['price'] + ($row['price'] * ($taxPercentage) / 100));
                        $tempRow['original_price_with_tax'] = strval($row['price'] + ($row['price'] * ($taxPercentage) / 100));
                    } else {
                        $tempRow['tax_value'] = "";
                        $tempRow['price_with_tax']  = strval($row['price']);
                        $tempRow['original_price_with_tax'] = strval($row['price']);
                    }
                } else {
                    if ($row['tax_type'] == "excluded") {
                        $tempRow['tax_value'] = number_format((intval(($row['discount_price'] * ($taxPercentage) / 100))), 2);
                        $tempRow['price_with_tax']  = strval($row['discount_price'] + ($row['discount_price'] * ($taxPercentage) / 100));
                        $tempRow['original_price_with_tax'] = strval($row['price'] + ($row['discount_price'] * ($taxPercentage) / 100));
                    } else {
                        $tempRow['tax_value'] = "";
                        $tempRow['price_with_tax']  = strval($row['discount_price']);
                        $tempRow['original_price_with_tax'] = strval($row['price']);
                    }
                }
                $rows[] = $tempRow;
            }
            if (!empty($rows)) {
                return response_helper(labels(SUBSCRIPTIONS_FETCHED_SUCCESSFULLY, 'Subscriptions fetched successfully'), false, $rows, 200, ['total' => count($subscription_details)]);
            } else {
                return response_helper(labels(SUBSCRIPTIONS_NOT_FOUND, 'Subscriptions not found'), false);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_subscription()');
            return $this->response->setJSON($response);
        }
    }

    public function buy_subscription()
    {
        try {
            $validation =  \Config\Services::validation();
            $validation->setRules(
                [
                    'subscription_id' => 'required',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $partner_id = $this->user_details['id'];
            $subscription_id = $this->request->getPost('subscription_id');
            $is_already_subscribe = fetch_details('partner_subscriptions', ['partner_id' => $partner_id, 'status' => 'active']);
            if (!empty($is_already_subscribe)) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(ALREADY_HAVE_AN_ACTIVE_SUBSCRIPTION, "Already have an active subscription"),
                    'data' => []
                ]);
            }
            $subscription_details = fetch_details('subscriptions', ['id' => $subscription_id]);

            // Get current language from request header for translations
            $currentLanguage = get_current_language_from_request();

            // Get default language from database
            $defaultLanguage = 'en';
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            if (!empty($languages)) {
                $defaultLanguage = $languages[0]['code'];
            }

            // Initialize subscription translation model
            $subscriptionTranslationModel = new TranslatedSubscriptionModel();

            // Get subscription translations
            $translation = $subscriptionTranslationModel->getTranslation($subscription_id, $currentLanguage);
            if (!$translation && $currentLanguage !== $defaultLanguage) {
                $translation = $subscriptionTranslationModel->getTranslation($subscription_id, $defaultLanguage);
            }
            $defaultTranslation = $subscriptionTranslationModel->getTranslation($subscription_id, $defaultLanguage);

            // Get translated name and description for storage
            $translatedName = $translation['name'] ?? $defaultTranslation['name'] ?? $subscription_details[0]['name'];
            $translatedDescription = $translation['description'] ?? $defaultTranslation['description'] ?? $subscription_details[0]['description'];

            // IMPORTANT:
            // Decide whether this subscription is actually free based on the effective price:
            // - If discount_price is non-zero, that is the payable amount.
            // - Otherwise price is used.
            // This avoids the bug where price=0 but discount_price>0:
            // in that case we must treat it as a paid plan and NOT activate immediately.
            $is_commission_based = $subscription_details[0]['is_commision'] == "yes";
            $isFreePlan = $this->isFreeSubscription($subscription_details[0]);

            if ($isFreePlan) {
                $partner_subscriptions = [
                    'partner_id' =>  $partner_id,
                    'subscription_id' => $subscription_id,
                    'is_payment' => "1",
                    'status' => "active",
                    'purchase_date' => date('Y-m-d'),
                    'expiry_date' => date('Y-m-d'),
                    'name' => $translatedName,
                    'description' => $translatedDescription,
                    'duration' => $subscription_details[0]['duration'],
                    'price' => $subscription_details[0]['price'],
                    'discount_price' => $subscription_details[0]['discount_price'],
                    'publish' => $subscription_details[0]['publish'],
                    'order_type' => $subscription_details[0]['order_type'],
                    'max_order_limit' => $subscription_details[0]['max_order_limit'],
                    'service_type' => $subscription_details[0]['service_type'],
                    'max_service_limit' => $subscription_details[0]['max_service_limit'],
                    'tax_type' => $subscription_details[0]['tax_type'],
                    'tax_id' => $subscription_details[0]['tax_id'],
                    'is_commision' => $subscription_details[0]['is_commision'],
                    'commission_threshold' => $subscription_details[0]['commission_threshold'],
                    'commission_percentage' => $subscription_details[0]['commission_percentage'],
                ];
                insert_details($partner_subscriptions, 'partner_subscriptions');
                $commission = $is_commission_based ? $subscription_details[0]['commission_percentage'] : 0;
                update_details(['admin_commission' => $commission], ['partner_id' => $partner_id], 'partner_details');
            } else {
                $subscriptionDuration = $subscription_details[0]['duration'];
                $purchaseDate = date('Y-m-d');
                $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days')); // Add the duration to the purchase date
                $details_for_subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
                $subscriptionDuration = $details_for_subscription[0]['duration'];
                $partner_subscriptions = [
                    'partner_id' =>  $partner_id,
                    'subscription_id' => $subscription_id,
                    'is_payment' => "0",
                    'status' => "pending",
                    'purchase_date' => $purchaseDate,
                    'expiry_date' => $expiryDate,
                    'name' => $translatedName,
                    'description' => $translatedDescription,
                    'duration' => $details_for_subscription[0]['duration'],
                    'price' => $details_for_subscription[0]['price'],
                    'discount_price' => $details_for_subscription[0]['discount_price'],
                    'publish' => $details_for_subscription[0]['publish'],
                    'order_type' => $details_for_subscription[0]['order_type'],
                    'max_order_limit' => $details_for_subscription[0]['max_order_limit'],
                    'service_type' => $details_for_subscription[0]['service_type'],
                    'max_service_limit' => $details_for_subscription[0]['max_service_limit'],
                    'tax_type' => $details_for_subscription[0]['tax_type'],
                    'tax_id' => $details_for_subscription[0]['tax_id'],
                    'is_commision' => $details_for_subscription[0]['is_commision'],
                    'commission_threshold' => $details_for_subscription[0]['commission_threshold'],
                    'commission_percentage' => $details_for_subscription[0]['commission_percentage'],
                ];
                $data = insert_details($partner_subscriptions, 'partner_subscriptions');
            }
            $response = [
                'error' => false,
                'message' => labels(CONGRATULATIONS_ON_YOUR_SUBSCRIPTION_NOW_IS_THE_TIME_TO_SHINE_ON_EDEMAND_AND_SEIZE_NEW_BUSINESS_OPPORTUNITIES_WELCOME_ABOARD_AND_BEST_OF_LUCK, 'Congratulations on your subscription! Now is the time to shine on eDEmand and seize new business opportunities. Welcome aboard and best of luck!'),
                'data' => []
            ];
        } catch (Exception $th) {
            $response['error'] = true;
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - buy_subscription()');
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
        }
        return $this->response->setJSON($response);
    }

    public function add_transaction()
    {
        try {
            $validation = service('validation');
            $validation->setRules([
                'subscription_id' => 'required|numeric',
                'status' => 'required',
                'message' => 'required',
                'type' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $transaction_model = new Transaction_model();
            $subscription_id = (int) $this->request->getVar('subscription_id');
            $status = $this->request->getVar('status');
            $message = $this->request->getVar('message');
            $type = $this->request->getVar('type');
            $user = fetch_details('users', ['id' => $this->user_details['id']]);
            if (empty($user)) {
                $response = [
                    'error' => true,
                    'message' => labels(USER_NOT_FOUND, "User not found!"),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $subscription = fetch_details('subscriptions', ['id' => $this->request->getVar('subscription_id')]);
            $transaction_id = fetch_details('transactions', ['id' => $this->request->getVar('transaction_id')]);
            $price = $subscription[0]['price'];
            $discount_price = $subscription[0]['discount_price'];
            $is_commission_based = $subscription[0]['is_commision'] == "yes";
            if ($status != "success") {
                $is_already_subscribe = fetch_details('partner_subscriptions', ['partner_id' => $this->user_details['id'], 'status' => 'active']);
                if (!empty($is_already_subscribe)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(ALREADY_HAVE_AN_ACTIVE_SUBSCRIPTION, "Already have an active subscription"),
                        'data' => []
                    ]);
                }
            }
            if (!empty($subscription)) {
                if (!empty($transaction_id)) {
                    $data1['status'] = $status;
                    $data1['type'] = $type;
                    $data1['message'] = $message;

                    // For subscription purchases, activation is handled exclusively by payment webhooks.
                    // Here we only mark the subscription as failed when status == "failed",
                    // and otherwise leave it in its existing (usually 'pending') state.
                    $subscription_data = [];
                    if ($status == "failed") {
                        $subscription_data['status'] = 'deactive';
                        $subscription_data['is_payment'] = '2';
                    }

                    $condition = [
                        'subscription_id' => $subscription_id,
                        'partner_id' => $this->user_details['id'],
                        'transaction_id' => $this->request->getVar('transaction_id'),
                    ];

                    if (!empty($subscription_data)) {
                        update_details($subscription_data, $condition, 'partner_subscriptions');
                    }

                    // Always update the transaction record with the latest payment status and message
                    update_details($data1, ['id' => $this->request->getVar('transaction_id')], 'transactions');
                    $data['transaction'] = fetch_details('transactions', ['id' => $this->request->getVar('transaction_id') ?? null])[0];
                    $subscription = fetch_details('partner_subscriptions', ['partner_id' => $transaction_id[0]['user_id'], 'subscription_id' => $transaction_id[0]['subscription_id']]);
                    $subscription_information['subscription_id'] = isset($subscription[0]['subscription_id']) ? $subscription[0]['subscription_id'] : "";
                    $subscription_information['isSubscriptionActive'] = isset($subscription[0]['status']) ? $subscription[0]['status'] : "deactive";
                    $subscription_information['created_at'] = isset($subscription[0]['created_at']) ? $subscription[0]['created_at'] : "";
                    $subscription_information['updated_at'] = isset($subscription[0]['updated_at']) ? $subscription[0]['updated_at'] : "";
                    $subscription_information['is_payment'] = isset($subscription[0]['is_payment']) ? $subscription[0]['is_payment'] : "";
                    $subscription_information['id'] = isset($subscription[0]['id']) ? $subscription[0]['id'] : "";
                    $subscription_information['partner_id'] = isset($subscription[0]['partner_id']) ? $subscription[0]['partner_id'] : "";
                    $subscription_information['purchase_date'] = isset($subscription[0]['purchase_date']) ? $subscription[0]['purchase_date'] : "";
                    $subscription_information['expiry_date'] = isset($subscription[0]['expiry_date']) ? $subscription[0]['expiry_date'] : "";
                    $subscription_information['name'] = isset($subscription[0]['name']) ? $subscription[0]['name'] : "";
                    $subscription_information['description'] = isset($subscription[0]['description']) ? $subscription[0]['description'] : "";
                    $subscription_information['duration'] = isset($subscription[0]['duration']) ? $subscription[0]['duration'] : "";
                    $subscription_information['price'] = isset($subscription[0]['price']) ? $subscription[0]['price'] : "";
                    $subscription_information['discount_price'] = isset($subscription[0]['discount_price']) ? $subscription[0]['discount_price'] : "";
                    $subscription_information['order_type'] = isset($subscription[0]['order_type']) ? $subscription[0]['order_type'] : "";
                    $subscription_information['max_order_limit'] = isset($subscription[0]['max_order_limit']) ? $subscription[0]['max_order_limit'] : "";
                    $subscription_information['is_commision'] = isset($subscription[0]['is_commision']) ? $subscription[0]['is_commision'] : "";
                    $subscription_information['commission_threshold'] = isset($subscription[0]['commission_threshold']) ? $subscription[0]['commission_threshold'] : "";
                    $subscription_information['commission_percentage'] = isset($subscription[0]['commission_percentage']) ? $subscription[0]['commission_percentage'] : "";
                    $subscription_information['publish'] = isset($subscription[0]['publish']) ? $subscription[0]['publish'] : "";
                    $subscription_information['tax_id'] = isset($subscription[0]['tax_id']) ? $subscription[0]['tax_id'] : "";
                    $subscription_information['tax_type'] = isset($subscription[0]['tax_type']) ? $subscription[0]['tax_type'] : "";
                    if (!empty($subscription[0])) {
                        $price = calculate_partner_subscription_price($subscription[0]['partner_id'], $subscription[0]['subscription_id'], $subscription[0]['id']);
                    }
                    $subscription_information['tax_value'] = isset($price[0]['tax_percentage']) ? $price[0]['tax_percentage'] : "";
                    $subscription_information['price_with_tax']  = isset($price[0]['price_with_tax']) ? $price[0]['price_with_tax'] : "";
                    $subscription_information['original_price_with_tax'] = isset($price[0]['original_price_with_tax']) ? $price[0]['original_price_with_tax'] : "";
                    $data['subscription_information'] = json_decode(json_encode($subscription_information), true);
                    $response['error'] = false;
                    $response['data'] = $data;
                    $response['message'] = labels(TRANSACTION_UPDATED_SUCCESSFULLY, 'Transaction Updated successfully');
                } else {
                    $taxPercentageData = fetch_details('taxes', ['id' => $subscription[0]['tax_id']], ['percentage']);
                    if (!empty($taxPercentageData)) {
                        $taxPercentage = $taxPercentageData[0]['percentage'];
                    } else {
                        $taxPercentage = 0;
                    }
                    if (!empty($subscription[0])) {
                        $price = calculate_subscription_price($subscription[0]['id']);
                    }
                    $trsansction_data = [
                        'transaction_type' => 'transaction',
                        'user_id' => $this->user_details['id'],
                        'partner_id' => "",
                        'order_id' => "0",
                        'type' => $type,
                        'txn_id' => "0",
                        'amount' =>  $price[0]['price_with_tax'],
                        'status' => $status,
                        'currency_code' => "",
                        'subscription_id' => $subscription_id,
                        'message' => $message,
                    ];
                    $insert = add_transaction($trsansction_data);

                    // Use the same effective-price logic here as in buy_subscription():
                    // a subscription is free ONLY when the effective payable price is zero.
                    // This prevents activating a subscription before payment when
                    // price=0 but discount_price>0.
                    $isFreePlan = $this->isFreeSubscription($subscription[0]);

                    if ($isFreePlan) {
                        $subscriptionDuration = $subscription[0]['duration'];
                        if ($subscriptionDuration == "unlimited") {
                            $subscriptionDuration = 0;
                        }
                        $purchaseDate = date('Y-m-d');
                        $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));
                        if ($subscriptionDuration == "unlimited") {
                            $subscriptionDuration = 0;
                        }
                        $partner_subscriptions = [
                            'partner_id' =>   $this->user_details['id'],
                            'subscription_id' => $subscription_id,
                            'is_payment' => "1",
                            'status' => "active",
                            'purchase_date' => date('Y-m-d'),
                            'expiry_date' => $expiryDate,
                            'name' => $subscription[0]['name'],
                            'description' => $subscription[0]['description'],
                            'duration' => $subscription[0]['duration'],
                            'price' => $subscription[0]['price'],
                            'discount_price' => $subscription[0]['discount_price'],
                            'publish' => $subscription[0]['publish'],
                            'order_type' => $subscription[0]['order_type'],
                            'max_order_limit' => $subscription[0]['max_order_limit'],
                            'service_type' => $subscription[0]['service_type'],
                            'max_service_limit' => $subscription[0]['max_service_limit'],
                            'tax_type' => $subscription[0]['tax_type'],
                            'tax_id' => $subscription[0]['tax_id'],
                            'is_commision' => $subscription[0]['is_commision'],
                            'commission_threshold' => $subscription[0]['commission_threshold'],
                            'commission_percentage' => $subscription[0]['commission_percentage'],
                            'transaction_id' => 0,
                            'tax_percentage' => $price[0]['tax_percentage'],
                        ];
                        $insert_subscription =  insert_details($partner_subscriptions, 'partner_subscriptions');
                        $commission = $is_commission_based ? $subscription[0]['commission_percentage'] : 0;
                        update_details(['admin_commission' => $commission], ['partner_id' =>   $this->user_details['id']], 'partner_details');
                    } else {
                        $subscriptionDuration = $subscription[0]['duration'];
                        if ($subscriptionDuration == "unlimited") {
                            $subscriptionDuration = 0;
                        }
                        $purchaseDate = date('Y-m-d');
                        $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));
                        if ($subscriptionDuration == "unlimited") {
                            $subscriptionDuration = 0;
                        }
                        $details_for_subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
                        $partner_subscriptions = [
                            'partner_id' =>    $this->user_details['id'],
                            'subscription_id' => $subscription_id,
                            'is_payment' => "0",
                            'status' => "pending",
                            'purchase_date' => $purchaseDate,
                            'expiry_date' => $expiryDate,
                            'name' => $details_for_subscription[0]['name'],
                            'description' => $details_for_subscription[0]['description'],
                            'duration' => $details_for_subscription[0]['duration'],
                            'price' => $details_for_subscription[0]['price'],
                            'discount_price' => $details_for_subscription[0]['discount_price'],
                            'publish' => $details_for_subscription[0]['publish'],
                            'order_type' => $details_for_subscription[0]['order_type'],
                            'max_order_limit' => $details_for_subscription[0]['max_order_limit'],
                            'service_type' => $details_for_subscription[0]['service_type'],
                            'max_service_limit' => $details_for_subscription[0]['max_service_limit'],
                            'tax_type' => $details_for_subscription[0]['tax_type'],
                            'tax_id' => $details_for_subscription[0]['tax_id'],
                            'is_commision' => $details_for_subscription[0]['is_commision'],
                            'commission_threshold' => $details_for_subscription[0]['commission_threshold'],
                            'commission_percentage' => $details_for_subscription[0]['commission_percentage'],
                            'transaction_id' => $insert,
                            'tax_percentage' => $price[0]['tax_percentage'],
                        ];
                        $insert_subscription = insert_details($partner_subscriptions, 'partner_subscriptions');
                        if ($details_for_subscription[0]['is_commision'] == "yes") {
                            $commission = $details_for_subscription[0]['commission_percentage'];
                        } else {
                            $commission = 0;
                        }
                        update_details(['admin_commission' => $commission], ['partner_id' => $this->user_details['id']], 'partner_details');
                    }
                    $data['transaction'] = fetch_details('transactions', ['id' => $insert ?? null])[0];
                    $subscription = fetch_details('partner_subscriptions', ['id' => $insert_subscription['id']]);
                    $subscription_information['subscription_id'] = isset($subscription[0]['subscription_id']) ? $subscription[0]['subscription_id'] : "";
                    $subscription_information['isSubscriptionActive'] = isset($subscription[0]['status']) ? $subscription[0]['status'] : "deactive";
                    $subscription_information['created_at'] = isset($subscription[0]['created_at']) ? $subscription[0]['created_at'] : "";
                    $subscription_information['updated_at'] = isset($subscription[0]['updated_at']) ? $subscription[0]['updated_at'] : "";
                    $subscription_information['is_payment'] = isset($subscription[0]['is_payment']) ? $subscription[0]['is_payment'] : "";
                    $subscription_information['id'] = isset($subscription[0]['id']) ? $subscription[0]['id'] : "";
                    $subscription_information['partner_id'] = isset($subscription[0]['partner_id']) ? $subscription[0]['partner_id'] : "";
                    $subscription_information['purchase_date'] = isset($subscription[0]['purchase_date']) ? $subscription[0]['purchase_date'] : "";
                    $subscription_information['expiry_date'] = isset($subscription[0]['expiry_date']) ? $subscription[0]['expiry_date'] : "";
                    $subscription_information['name'] = isset($subscription[0]['name']) ? $subscription[0]['name'] : "";
                    $subscription_information['description'] = isset($subscription[0]['description']) ? $subscription[0]['description'] : "";
                    $subscription_information['duration'] = isset($subscription[0]['duration']) ? $subscription[0]['duration'] : "";
                    $subscription_information['price'] = isset($subscription[0]['price']) ? $subscription[0]['price'] : "";
                    $subscription_information['discount_price'] = isset($subscription[0]['discount_price']) ? $subscription[0]['discount_price'] : "";
                    $subscription_information['order_type'] = isset($subscription[0]['order_type']) ? $subscription[0]['order_type'] : "";
                    $subscription_information['max_order_limit'] = isset($subscription[0]['max_order_limit']) ? $subscription[0]['max_order_limit'] : "";
                    $subscription_information['is_commision'] = isset($subscription[0]['is_commision']) ? $subscription[0]['is_commision'] : "";
                    $subscription_information['commission_threshold'] = isset($subscription[0]['commission_threshold']) ? $subscription[0]['commission_threshold'] : "";
                    $subscription_information['commission_percentage'] = isset($subscription[0]['commission_percentage']) ? $subscription[0]['commission_percentage'] : "";
                    $subscription_information['publish'] = isset($subscription[0]['publish']) ? $subscription[0]['publish'] : "";
                    $subscription_information['tax_id'] = isset($subscription[0]['tax_id']) ? $subscription[0]['tax_id'] : "";
                    $subscription_information['tax_type'] = isset($subscription[0]['tax_type']) ? $subscription[0]['tax_type'] : "";
                    if (!empty($subscription[0])) {
                        $price = calculate_partner_subscription_price($subscription[0]['partner_id'], $subscription[0]['subscription_id'], $subscription[0]['id']);
                    }
                    $subscription_information['tax_value'] = isset($price[0]['tax_percentage']) ? $price[0]['tax_percentage'] : "";
                    $subscription_information['price_with_tax']  = isset($price[0]['price_with_tax']) ? $price[0]['price_with_tax'] : "";
                    $subscription_information['original_price_with_tax'] = isset($price[0]['original_price_with_tax']) ? $price[0]['original_price_with_tax'] : "";
                    $subscription_information['tax_percentage'] = isset($price[0]['tax_percentage']) ? $price[0]['tax_percentage'] : "";
                    $data['subscription_information'] = json_decode(json_encode($subscription_information), true);
                    $param['client_id'] = $this->userId;
                    $param['insert_id'] = $insert;
                    $param['package_id'] =  isset($subscription[0]['subscription_id']) ? $subscription[0]['subscription_id'] : "";
                    $param['net_amount'] =  isset($price[0]['price_with_tax']) ? $price[0]['price_with_tax'] : "";
                    $from_app = $this->request->getVar('from_app');
                    $is_from_app = ($from_app == 1 || $from_app === '1' || $from_app === true || $from_app === 'true') ? 1 : 0;
                    if ($type == "paypal") {
                        // Create the PayPal Orders v2 checkout inline and hand the
                        // buyer-facing approval URL straight to the client.
                        $payer_email = (string) ($user[0]['email'] ?? '');
                        $paypal_checkout = (new \App\Services\PaypalPaymentService())->createCheckout([
                            'transaction_id' => $insert,
                            'custom_id'      => $insert . '|' . $payer_email . '|subscription',
                            'item_name'      => 'Subscription #' . $subscription[0]['subscription_id'],
                            'invoice_prefix' => 'subs',
                            'return_url'     => base_url('partner/api/v1/paypal_return'),
                            'cancel_url'     => base_url('partner/api/v1/paypal_return?cancelled=1'),
                        ]);
                        $data['paypal_link'] = !empty($paypal_checkout['error']) ? '' : $paypal_checkout['approval_url'];
                    } else {
                        $data['paypal_link'] = "";
                    }
                    $data['paystack_link'] = ($type == "paystack") ? (($is_from_app == 1) ? $this->paystack_transaction_webview($this->user_details['id'], $insert, $price[0]['price_with_tax'], 1) : base_url() . '/partner/api/v1/paystack_transaction_webview?client_id=' . $this->user_details['id'] . '&insert_id=' . $insert . '&package_id=' . $subscription[0]['subscription_id'] . '&net_amount=' . $price[0]['price_with_tax']) : "";
                    $data['flutterwave_link'] = ($type == "flutterwave") ? $this->flutterwave_webview($this->user_details['id'], $insert, $price[0]['price_with_tax']) : "";

                    $data['xendit_link'] = ($type == "xendit") ? $this->xendit_transaction_webview($this->user_details['id'], $insert, $subscription[0]['subscription_id'], $price[0]['price_with_tax']) : "";

                    $response['error'] = false;
                    $response['data'] = $data;
                    $response['message'] = labels(TRANSACTION_ADDED_SUCCESSFULLY, 'Transaction addedd successfully');
                }
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - add_transaction()');
            return $this->response->setJSON($response);
        }
    }

    /**
     * Create Stripe PaymentIntent for partner subscription purchase.
     *
     * IMPORTANT DIFFERENCE vs customer `create_stripe_payment_intent`:
     * - Partner API accepts ONLY `transaction_id`.
     * - That transaction must be created earlier via partner `add_transaction`.
     * - We fetch `subscription_id` from that transaction and calculate the payable price
     *   based on the subscription (and partner_subscriptions row when available).
     *
     * Why:
     * - We do not trust client-sent amounts.
     * - We attach `transaction_id` into Stripe metadata so `api/Webhooks::stripe`
     *   can activate the subscription on `charge.succeeded`.
     */
    public function create_stripe_payment_intent()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'transaction_id' => 'required|numeric',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ]);
            }

            $transaction_id = (int) $this->request->getPost('transaction_id');
            $partner_id = (int) ($this->user_details['id'] ?? 0);

            // Security: ensure the transaction belongs to the logged-in partner.
            $tx = fetch_details('transactions', [
                'id' => $transaction_id,
                'user_id' => $partner_id,
            ]);
            if (empty($tx) || empty($tx[0])) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $subscription_id = (int) ($tx[0]['subscription_id'] ?? 0);
            if ($subscription_id <= 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $settings = get_settings('payment_gateways_settings', true);
            if (empty($settings)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            // Stripe currency comes from Stripe gateway settings (same as customer APIs).
            $currency = $settings['stripe_currency'] ?? 'USD';

            /**
             * Determine payable price.
             *
             * Preferred:
             * - Use partner_subscriptions row for this transaction, so we respect the exact
             *   tax configuration that was stored when the transaction was created.
             *
             * Fallback:
             * - Compute from subscriptions table (calculate_subscription_price), or fall back
             *   to stored transaction.amount if required.
             */
            $price = 0;
            $partnerSubscription = fetch_details('partner_subscriptions', [
                'partner_id' => $partner_id,
                'subscription_id' => $subscription_id,
                'transaction_id' => $transaction_id,
            ]);

            if (!empty($partnerSubscription) && !empty($partnerSubscription[0]['id'])) {
                $priceInfo = calculate_partner_subscription_price($partner_id, $subscription_id, $partnerSubscription[0]['id']);
            } else {
                $priceInfo = calculate_subscription_price($subscription_id);
            }

            if (!empty($priceInfo) && !empty($priceInfo[0]) && isset($priceInfo[0]['price_with_tax'])) {
                $price = $priceInfo[0]['price_with_tax'];
            } else {
                // Last-resort fallback: use stored amount (still validated and converted safely).
                $price = $tx[0]['amount'] ?? 0;
            }

            if (!is_numeric($price) || (float) $price <= 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            // Stripe expects amount in minor units.
            $amount_minor = StripeMoney::toMinorUnits($price, $currency);

            // Metadata is used by `api/Webhooks::stripe` to reconcile subscription transactions.
            $metadata = [
                'transaction_id' => (string) $transaction_id,
                'partner_id' => (string) $partner_id,
                'subscription_id' => (string) $subscription_id,
            ];

            $company_title = getTranslatedSetting('general_settings', 'company_title');
            $company_title = !empty($company_title) ? $company_title : 'eDemand';
            $description = 'Subscription payment - Provider #' . $partner_id . ' on ' . $company_title;

            $intent = $this->stripe->create_payment_intent([
                'amount' => $amount_minor,
                'metadata' => $metadata,
                'description' => $description,
            ]);

            if (isset($intent['error']) || empty($intent['client_secret'])) {
                $message = $intent['error']['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong');
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $message,
                    'data' => $intent,
                ]);
            }

            // Billing details for client-side SDK prefill.
            $billing_details = [
                'name'  => $this->user_details['username'] ?? '',
                'email' => $this->user_details['email'] ?? '',
                'phone' => $this->user_details['phone'] ?? '',
            ];

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Stripe payment intent created'),
                'data' => $intent,
                'billing_details' => $billing_details,
            ]);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - create_stripe_payment_intent()'
            );
            return $this->response->setJSON($response);
        }
    }

    /**
     * Get Stripe PaymentIntent status/details (partner).
     *
     * Required:
     * - payment_intent_id (POST)
     */
    public function get_stripe_payment_status()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'payment_intent_id' => 'required',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ]);
            }

            $payment_intent_id = (string) $this->request->getPost('payment_intent_id');
            $intent = $this->stripe->get_payment_intent($payment_intent_id);

            if (isset($intent['error'])) {
                $message = $intent['error']['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong');
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $message,
                    'data' => $intent,
                ]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Stripe payment intent fetched'),
                'data' => $intent,
            ]);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_stripe_payment_status()'
            );
            return $this->response->setJSON($response);
        }
    }

    /**
     * PayPal return handler — capture-on-return for provider subscriptions.
     *
     * PayPal redirects the provider here with `?token=<order id>` after approval
     * (or `cancelled=1` from the cancel URL). The order is captured server-side
     * and the subscription activated via PaypalPaymentService.
     */
    public function paypal_return()
    {
        try {
            $this->paypal_lib = new Paypal();
            $paypal_order_id  = (string) ($_GET['token'] ?? '');
            $cancelled        = isset($_GET['cancelled']);

            if ($paypal_order_id === '') {
                return $this->redirectToPaymentStatus('failed');
            }

            $transaction = fetch_details('transactions', ['type' => 'paypal', 'reference' => $paypal_order_id]);
            if (empty($transaction)) {
                return $this->redirectToPaymentStatus('failed');
            }
            $transaction = $transaction[0];

            $service = new \App\Services\PaypalPaymentService();

            if ($cancelled) {
                $service->finalizeFailure($transaction);
                return $this->redirectToPaymentStatus('cancelled');
            }

            // Idempotency: webhook may have already finalised this payment.
            if (($transaction['status'] ?? '') === 'success') {
                return $this->redirectToPaymentStatus('success');
            }

            $capture_response = $this->paypal_lib->captureOrder($paypal_order_id);
            $capture          = \App\Libraries\Paypal::getCaptureDetails($capture_response);

            // If the capture call did not clearly complete (e.g. the order was
            // already captured on a duplicate return hit), confirm the order's
            // real state with PayPal before treating it as a failure.
            if (!empty($capture_response['error']) || $capture['status'] !== 'COMPLETED') {
                $order_state = $this->paypal_lib->getOrder($paypal_order_id);
                if (empty($order_state['error']) && ($order_state['status'] ?? '') === 'COMPLETED') {
                    $capture = \App\Libraries\Paypal::getCaptureDetails($order_state);
                }
            }

            if ($capture['status'] === 'COMPLETED' && $capture['capture_id'] !== '') {
                $service->finalizeSuccess($transaction, $capture);
                return $this->redirectToPaymentStatus('success');
            }

            $service->finalizeFailure($transaction, $capture['capture_id']);
            return $this->redirectToPaymentStatus('failed');
        } catch (\Throwable $th) {
            log_the_responce(
                'paypal_return Params :: ' . json_encode($_GET) . ' Issue => ' . $th,
                date("Y-m-d H:i:s") . '--> SubscriptionApiController - paypal_return()'
            );
            return $this->redirectToPaymentStatus('failed');
        }
    }

    /**
     * Redirect the PayPal return to the unified status endpoint, carrying the
     * resolved status in the query string. Both the provider app (which
     * intercepts the URL inside its webview) and the web (which renders the
     * status page) key off `payment_status`.
     *
     * @param string $status success | failed | cancelled
     */
    private function redirectToPaymentStatus(string $status)
    {
        return redirect()->to(
            base_url('partner/api/v1/app_payment_status') . '?payment_status=' . rawurlencode($status)
        );
    }

    public function paystack_transaction_webview($user_id = null, $insert_id = null, $net_amount = null, $from_app = null)
    {
        // Endpoint mode: invoked as the web HTTP route (no args, params come from query string).
        // Function mode: invoked from add_transaction with from_app = 1 (app flow) — returns the
        // authorization URL string instead of redirecting.
        $is_endpoint = false;
        if ($user_id === null && isset($_GET['client_id'])) {
            $is_endpoint = true;
            header("Content-Type: text/html");
            $insert_id = $_GET['insert_id'];
            $user_id = $_GET['client_id'];
            $net_amount = $_GET['net_amount'];
            $from_app = 0;
        }
        $from_app = ($from_app == 1) ? 1 : 0;
        $user_data = fetch_details('users', ['id' => $user_id])[0];
        $paystack = new Paystack();
        $paystack_credentials = $paystack->get_credentials();
        $secret_key = $paystack_credentials['secret'];
        $url = "https://api.paystack.co/transaction/initialize";

        if ($from_app == 1) {
            $callback_url = base_url() . '/partner/api/v1/app_paystack_payment_status?payment_status=Completed&from_app=1';
            $cancel_url = base_url() . '/partner/api/v1/app_paystack_payment_status?payment_status=Failed&from_app=1&transaction_id=' . $insert_id;
        } else {
            $callback_url = base_url() . '/partner/api/v1/app_paystack_payment_status?payment_status=Completed';
            $cancel_url = base_url() . '/partner/api/v1/app_paystack_payment_status?payment_status=Failed';
        }

        $fields = [
            'email' =>  $user_data['email'],
            'amount' =>  $net_amount * 100,
            'currency' => $paystack_credentials['currency'],
            'callback_url' => $callback_url,
            'metadata' => ["cancel_action" => $cancel_url, 'transaction_id' => $insert_id]
        ];
        $fields_string = http_build_query($fields);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $secret_key,
            "Cache-Control: no-cache",
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        unset($ch);
        $result_data = json_decode($result, true);
        if (isset($result_data['data']['authorization_url'])) {
            // Stamp the Paystack reference on the subscription transaction row created
            // by add_transaction. Webhook charge.success uses metadata.transaction_id
            // primarily but also relies on the row carrying the reference for audit
            // and idempotent lookups.
            $ps_reference = (string) ($result_data['data']['reference'] ?? '');
            if ($ps_reference !== '' && !empty($insert_id)) {
                update_details(
                    ['reference' => $ps_reference, 'type' => 'paystack'],
                    ['id' => $insert_id],
                    'transactions'
                );
            }
            if ($is_endpoint) {
                header('Location: ' . $result_data['data']['authorization_url']);
                exit;
            }
            return $result_data['data']['authorization_url'];
        }
        if ($is_endpoint) {
            $response = [
                'error' => true,
                'message' => labels(FAILED_TO_INITIALIZE_TRANSACTION, 'Failed to initialize transaction'),
                'data' => $result_data,
            ];
            return $this->response->setJSON($response);
        }
        log_message('error', 'Partner Paystack Error Creating Authorization URL: ' . json_encode($result_data, true));
        return "";
    }

    public function app_paystack_payment_status()
    {
        $data = $_GET;
        $response = [];
        $platform = (isset($data['from_app']) && $data['from_app'] == 1) ? 'app' : 'web';
        $general_settings = get_settings('general_settings', true);
        $app_settings     = get_settings('app_settings', true);
        $app_scheme = $general_settings['schema_for_deeplink'] ?? 'tasko';

        if (isset($data['reference']) && isset($data['trxref']) && isset($data['payment_status'])) {
            $response['error'] = false;
            $response['message'] = labels(PAYMENT_COMPLETED_SUCCESSFULLY, "Payment Completed Successfully");
            $response['payment_status'] = "Completed";
            $response['data'] = $data;
        } elseif (isset($data['transaction_id']) && isset($data['payment_status'])) {
            $response['error'] = true;
            $response['message'] = labels(PAYMENT_CANCELLED_DECLINED, "Payment Cancelled / Declined ");
            $response['payment_status'] = "Failed";
            $response['data'] = $_GET;
        } else {
            $response['error'] = true;
            $response['message'] = 'Invalid request';
            $response['payment_status'] = 'Error';
        }

        // Prepare deeplink using the same pattern as deep_link.php
        $deeplink = $app_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

        $view_data = [
            'error' => $response['error'],
            'message' => $response['message'],
            'payment_status' => $response['payment_status'],
            'platform' => $platform,
            'deeplink' => $deeplink,
            'appName' => $app_scheme,
            'customerPlaystoreUrl' => $app_settings['provider_playstore_url'] ?? '',
            'customerAppStoreUrl'  => $app_settings['provider_appstore_url'] ?? ''
        ];

        return view('api/payment_status', $view_data);
    }

    /**
     * Unified PayPal payment-status landing page for provider subscriptions.
     *
     * `paypal_return` redirects here after capture with `?payment_status=...`
     * in the query string. The provider app intercepts this URL inside its
     * webview to read the status; web buyers see the rendered status page.
     */
    public function app_payment_status()
    {
        $payment_status = strtolower((string) ($_GET['payment_status'] ?? ''));

        $outcomes = [
            'success'   => [false, labels(PAYMENT_COMPLETED_SUCCESSFULLY, 'Payment Completed Successfully'), 'Completed'],
            'cancelled' => [true,  labels(PAYMENT_CANCELLED_DECLINED, 'Payment Cancelled / Declined'), 'Cancelled'],
            'failed'    => [true,  labels(PAYMENT_CANCELLED_DECLINED, 'Payment Cancelled / Declined'), 'Failed'],
        ];
        [$error, $message, $status_label] = $outcomes[$payment_status]
            ?? [true, labels('error_occured', 'An error occurred'), 'Failed'];

        $app_settings = get_settings('app_settings', true);

        return view('api/payment_status', [
            'error'                => $error,
            'message'              => $message,
            'payment_status'       => $status_label,
            'platform'             => 'web',
            'deeplink'             => '',
            'appName'              => '',
            'customerPlaystoreUrl' => $app_settings['provider_playstore_url'] ?? '',
            'customerAppStoreUrl'  => $app_settings['provider_appstore_url'] ?? '',
        ]);
    }

    public function razorpay_create_order()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'subscription_id' => 'required|numeric',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $subscription_id = $this->request->getPost('subscription_id');
            if ($this->request->getPost('subscription_id') && !empty($this->request->getPost('subscription_id'))) {
                $where['s.id'] = $this->request->getPost('subscription_id');
            }
            $subscription = new Subscription_model();
            $subscription_detail = $subscription->list(true, '', 10, 0, 's.id', 'DESC', $where);
            $settings = get_settings('payment_gateways_settings', true);
            if (!empty($subscription_detail) && !empty($settings)) {
                $currency = $settings['razorpay_currency'];
                $price = calculate_subscription_price($subscription_id);
                $amount = intval($price[0]['price_with_tax'] * 100);
                $create_order = $this->razorpay->create_order($amount, $subscription_id, $currency);
                if (!empty($create_order)) {
                    $response = [
                        'error' => false,
                        'message' => labels(RAZORPAY_ORDER_CREATED, 'razorpay order created'),
                        'data' => $create_order,
                    ];
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(RAZORPAY_ORDER_NOT_CREATED, 'razorpay order not created'),
                        'data' => [],
                    ];
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ];
            }
            return $this->response->setJSON($response);
        } catch (Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - razorpay_create_order()');
            return $this->response->setJSON($response);
        }
    }

    public function cashfree_create_order()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'subscription_id' => 'required|numeric',
                    'transaction_id' => 'permit_empty|numeric',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => [],
                ]);
            }

            $subscription_id = (int) $this->request->getPost('subscription_id');
            $transaction_id = (int) ($this->request->getPost('transaction_id') ?? 0);
            $where['s.id'] = $subscription_id;

            $subscription = new Subscription_model();
            $subscription_detail = $subscription->list(true, '', 10, 0, 's.id', 'DESC', $where);
            $settings = get_settings('payment_gateways_settings', true);
            $credentials = $this->cashfree->get_credentials();

            if (
                empty($subscription_detail) ||
                empty($subscription_detail['data'][0]) ||
                empty($settings) ||
                ($credentials['status'] ?? 'disable') !== 'enable'
            ) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            // Use calculate_subscription_price to get the price with tax (consistent with Razorpay/Stripe)
            $partner_id_cf = (int) ($this->user_details['id'] ?? 0);
            $transaction_id_cf = (int) ($this->request->getPost('transaction_id') ?? 0);

            $priceInfo = null;
            if (!empty($transaction_id_cf)) {
                $partnerSub = fetch_details('partner_subscriptions', [
                    'partner_id' => $partner_id_cf,
                    'subscription_id' => $subscription_id,
                    'transaction_id' => $transaction_id_cf,
                ]);
                if (!empty($partnerSub) && !empty($partnerSub[0]['id'])) {
                    $priceInfo = calculate_partner_subscription_price($partner_id_cf, $subscription_id, $partnerSub[0]['id']);
                }
            }

            if (empty($priceInfo) || empty($priceInfo[0]['price_with_tax'])) {
                $priceInfo = calculate_subscription_price($subscription_id);
            }

            $price = $priceInfo[0]['price_with_tax'] ?? 0;

            if (!is_numeric($price) || (float) $price <= 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $currency = $settings['cashfree_currency'] ?? ($credentials['currency'] ?? 'INR');
            $partner_id = (int) ($this->user_details['id'] ?? 0);
            $partner = fetch_details('users', ['id' => $partner_id], ['username', 'email', 'phone']);
            $partner = $partner[0] ?? [];

            $phone_raw = $partner['phone'] ?? '';
            $phone = preg_replace('/\D+/', '', (string) $phone_raw);
            if (empty($phone)) {
                $phone = '9999999999';
            }

            $gateway_order_id = !empty($transaction_id)
                ? ('subs_' . $transaction_id . '_' . $subscription_id . '_' . time())
                : ('subs_' . $subscription_id . '_' . $partner_id . '_' . time());

            $return_url_base = !empty($credentials['website_url'])
                ? rtrim($credentials['website_url'], '/')
                : rtrim(base_url(), '/');

            $customer_name = trim($partner['username'] ?? '');

            if ($customer_name !== '') {
                $customer_name = (strlen($customer_name) < 3) ? $partner['username'] . '_' . $partner_id : $customer_name;
            } else {
                $customer_name = 'Partner_' . $partner_id;
            }

            $payload = [
                'order_id' => $gateway_order_id,
                'order_amount' => (float) number_format((float) $price, 2, '.', ''),
                'order_currency' => strtoupper($currency),
                'customer_details' => [
                    'customer_id' => 'partner_' . $partner_id,
                    'customer_name' => $customer_name,
                    'customer_email' => $partner['email'] ?? ('partner' . $partner_id . '@example.com'),
                    'customer_phone' => $phone,
                ],
                'order_meta' => [
                    'return_url' => $return_url_base . '/partner/stripe_success?payment_status={payment_status}',
                ],
                'order_note' => 'Subscription payment for package #' . $subscription_id,
                'order_tags' => [
                    'payment_for' => 'subscription',
                    'subscription_id' => (string) $subscription_id,
                    'partner_id' => (string) $partner_id,
                ],
            ];

            if (!empty($transaction_id)) {
                $payload['order_tags']['transaction_id'] = (string) $transaction_id;
            }

            $create_order = $this->cashfree->create_order($payload);
            if (!empty($create_order['error']) || empty($create_order['payment_session_id'])) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $create_order['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                    'data' => $create_order,
                ]);
            }

            if (!empty($transaction_id)) {
                $tx = fetch_details('transactions', [
                    'id' => $transaction_id,
                    'user_id' => $partner_id,
                    'subscription_id' => $subscription_id
                ]);
                if (!empty($tx[0]['id'])) {
                    update_details(['reference' => $gateway_order_id], ['id' => $transaction_id], 'transactions');
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Cashfree order created'),
                'data' => $create_order,
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - cashfree_create_order()'
            );
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    /**
     * Get the status of a Cashfree order (partner).
     *
     * Required:
     * - order_id (POST) — the Cashfree gateway order ID (stored in `transactions.reference`).
     *
     * Flow:
     * 1. Look up the local transaction row by `reference = order_id`.
     * 2. If found with a terminal status (success / failed), return it immediately.
     * 3. Otherwise call the Cashfree SDK (`GET /pg/orders/{order_id}`) to fetch
     *    the live status and return the API response.
     */
    public function get_cashfree_order_status()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'order_id' => 'required',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => [],
                ]);
            }

            $order_id = (string) $this->request->getPost('order_id');

            // 1. Try to find the transaction locally
            $transaction = fetch_details(
                'transactions',
                ['reference' => $order_id],
                ['id', 'order_id', 'user_id', 'subscription_id', 'type', 'txn_id', 'amount', 'status', 'message', 'transaction_date', 'currency_code', 'reference'],
                1,
                0,
                'id',
                'DESC'
            );

            if (!empty($transaction[0]) && in_array($transaction[0]['status'], ['success', 'failed'])) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Transaction fetched successfully'),
                    'data' => [
                        'source' => 'local',
                        'transaction' => $transaction[0],
                    ],
                ]);
            }

            // 2. Transaction not found or still pending — query Cashfree API
            $credentials = $this->cashfree->get_credentials();
            if (
                ($credentials['status'] ?? 'disable') !== 'enable' ||
                empty($credentials['app_id']) ||
                empty($credentials['secret_key'])
            ) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'Payment gateway not configured'),
                    'data' => [],
                ]);
            }

            $cf_order = $this->cashfree->fetch_order($order_id);

            if (!empty($cf_order['error'])) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $cf_order['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                    'data' => $cf_order,
                ]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Cashfree order status fetched'),
                'data' => [
                    'source' => 'cashfree',
                    'order' => $cf_order,
                    'transaction' => $transaction[0] ?? null,
                ],
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') .
                    ' Params :: ' . json_encode($_POST) .
                    ' Issue => ' . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_cashfree_order_status()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    public function get_subscription_history()
    {
        try {
            $request = \Config\Services::request();
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('sort'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'DESC';
            $user_id = $this->user_details['id'];
            if (!exists(['id' => $user_id], 'users')) {
                $response = [
                    'error' => true,
                    'message' => labels(INVALID_USER_ID, 'Invalid User Id.'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $res = fetch_details('partner_subscriptions', ['partner_id' => $user_id, 'status' => 'deactive', 'is_payment' => '1'], '', $limit, $offset, $sort, $order);

            // Get current language from request header for translations
            $currentLanguage = get_current_language_from_request();

            // Get default language from database
            $defaultLanguage = 'en';
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            if (!empty($languages)) {
                $defaultLanguage = $languages[0]['code'];
            }

            // Initialize subscription translation model
            $subscriptionTranslationModel = new TranslatedSubscriptionModel();

            foreach ($res as $key => $row) {
                // Get subscription translations for this subscription
                $translation = null;
                $defaultTranslation = null;
                if (!empty($row['subscription_id'])) {
                    // Get translations for requested language and default language
                    $translation = $subscriptionTranslationModel->getTranslation($row['subscription_id'], $currentLanguage);
                    if (!$translation && $currentLanguage !== $defaultLanguage) {
                        $translation = $subscriptionTranslationModel->getTranslation($row['subscription_id'], $defaultLanguage);
                    }
                    $defaultTranslation = $subscriptionTranslationModel->getTranslation($row['subscription_id'], $defaultLanguage);
                }

                // Apply translation logic to name and description fields
                if (!empty($row['subscription_id'])) {
                    // Set main fields: use default language translations or fallback to main table
                    $res[$key]['name'] = $defaultTranslation['name'] ?? $row['name'];
                    $res[$key]['description'] = $defaultTranslation['description'] ?? $row['description'];

                    // Set translated fields: use requested language, fallback to default language, then main table
                    $res[$key]['translated_name'] = $translation['name'] ?? $defaultTranslation['name'] ?? $row['name'];
                    $res[$key]['translated_description'] = $translation['description'] ?? $defaultTranslation['description'] ?? $row['description'];
                } else {
                    // No subscription ID, set translated fields to main table data
                    $res[$key]['translated_name'] = $row['name'];
                    $res[$key]['translated_description'] = $row['description'];
                }

                $price = calculate_partner_subscription_price($row['partner_id'], $row['subscription_id'], $row['id']);
                $res[$key]['tax_value'] = $price[0]['tax_value'];
                $res[$key]['price_with_tax'] = $price[0]['price_with_tax'];
                $res[$key]['original_price_with_tax'] = $price[0]['original_price_with_tax'];
                $res[$key]['tax_percentage'] = $price[0]['tax_percentage'];
                $res[$key]['isSubscriptionActive'] = $row['status'];
                $res[$key]['translated_status'] = getTranslatedValue($row['status'], 'panel');
                unset($res[$key]['status']);
            }
            $total = fetch_details('partner_subscriptions', ['partner_id' => $user_id, 'status' => 'deactive', 'is_payment' => '1']);
            $total = count($total);
            if (!empty($res)) {
                $response = [
                    'error' => false,
                    'message' => labels(SUBSCRIPTION_HISTORY_RECEIVED_SUCCESSFULLY, 'Subscription history recieved successfully.'),
                    'total' => $total,
                    'data' => $res,
                ];
                return $this->response->setJSON($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND, 'No data found'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_subscription_history()');
            return $this->response->setJSON($response);
        }
    }

    private function flutterwave_webview($user_id, $insert_id, $net_amount)
    {
        $settings = get_settings('general_settings', true);
        $logo = base_url("public/uploads/site/" . $settings['logo']);
        $user = fetch_details('users', ['id' => $user_id]);
        if (empty($user)) {
            return "";
        }
        $flutterwave = new Flutterwave();
        $flutterwave_credentials = $flutterwave->get_credentials();
        $currency = $flutterwave_credentials['currency_code'] ?? "NGN";
        $company_title = getTranslatedSetting('general_settings', 'company_title');
        $tx_ref = "eDemand-" . time() . "-" . rand(1000, 9999);
        $data = [
            'tx_ref' => $tx_ref,
            'amount' => $net_amount,
            'currency' => $currency,
            'redirect_url' => base_url('partner/api/v1/flutterwave_payment_status'),
            'payment_options' => 'card',
            'meta' => [
                'user_id' => $user_id,
                'transaction_id' => $insert_id,
            ],
            'customer' => [
                'email' => (!empty($user[0]['email'])) ? $user[0]['email'] : $settings['support_email'],
                'phonenumber' => $user[0]['phone'] ?? '',
                'name' => $user[0]['username'] ?? '',
            ],
            'customizations' => [
                'title' => $company_title . " Payments",
                'description' => "Online payments on " . $company_title,
                'logo' => (!empty($logo)) ? $logo : "",
            ],
        ];
        $payment = $flutterwave->create_payment($data);
        if (!empty($payment)) {
            $payment = json_decode($payment, true);
            if (isset($payment['status']) && $payment['status'] == 'success' && isset($payment['data']['link'])) {
                // Stamp tx_ref on the pending subscription tx row so webhook lookup by
                // (type=flutterwave, reference=tx_ref) finds it. Webhook still uses
                // meta.transaction_id as the primary key — this is belt-and-braces.
                if (!empty($insert_id)) {
                    update_details(
                        ['reference' => $tx_ref, 'type' => 'flutterwave'],
                        ['id' => $insert_id],
                        'transactions'
                    );
                }
                return $payment['data']['link'];
            }
        }
        return "";
    }

    public function flutterwave_payment_status()
    {
        if (isset($_GET['transaction_id']) && !empty($_GET['transaction_id'])) {
            $transaction_id = $_GET['transaction_id'];
            $flutterwave = new Flutterwave();
            $transaction = $flutterwave->verify_transaction($transaction_id);
            if (!empty($transaction)) {
                $transaction = json_decode($transaction, true);
                if ($transaction['status'] == 'error') {
                    $response['error'] = true;
                    $response['message'] = $transaction['message'];
                    $response['amount'] = 0;
                    $response['status'] = "failed";
                    $response['currency'] = "NGN";
                    $response['transaction_id'] = $transaction_id;
                    $response['reference'] = "";
                    print_r(json_encode($response));
                    return false;
                }
                if ($transaction['status'] == 'success' && $transaction['data']['status'] == 'successful') {
                    $response['error'] = false;
                    $response['message'] = "Payment has been completed successfully";
                    $response['amount'] = $transaction['data']['amount'];
                    $response['currency'] = $transaction['data']['currency'];
                    $response['status'] = $transaction['data']['status'];
                    $response['transaction_id'] = $transaction['data']['id'];
                    $response['reference'] = $transaction['data']['tx_ref'];
                    print_r(json_encode($response));
                    return false;
                } else if ($transaction['status'] == 'success' && $transaction['data']['status'] != 'successful') {
                    $response['error'] = true;
                    $response['message'] = labels(PAYMENT_IS, "Payment is") . " " . $transaction['data']['status'];
                    $response['amount'] = $transaction['data']['amount'];
                    $response['currency'] = $transaction['data']['currency'];
                    $response['status'] = $transaction['data']['status'];
                    $response['transaction_id'] = $transaction['data']['id'];
                    $response['reference'] = $transaction['data']['tx_ref'];
                    print_r(json_encode($response));
                    return false;
                }
            } else {
                $response['error'] = true;
                $response['message'] = labels(TRANSACTION_NOT_FOUND, "Transaction not found");
                print_r(json_encode($response));
            }
        } else {
            $response['error'] = true;
            $response['message'] = labels(INVALID_REQUEST, "Invalid request!");
            print_r(json_encode($response));
            return false;
        }
    }

    public function xendit_payment_status()
    {
        try {
            // Log the incoming request parameters for debugging
            log_the_responce(
                'Xendit Payment Status - Incoming request: ' . json_encode($_GET),
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
            );

            $external_id = $_GET['external_id'] ?? '';
            $status = $_GET['status'] ?? 'failed';
            $subscription_id = $_GET['subscription_id'] ?? '';

            // Log the extracted parameters
            log_the_responce(
                'Xendit Payment Status - Extracted params - external_id: ' . $external_id . ', status: ' . $status . ', subscription_id: ' . $subscription_id,
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
            );

            // Try to find transaction by external_id
            $transaction = fetch_details('transactions', ['txn_id' => $external_id], ['id', 'user_id', 'subscription_id', 'status']);

            log_the_responce(
                'Xendit Payment Status - Found transaction by external_id: ' . json_encode($transaction),
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
            );

            if ($status === 'success') {
                log_the_responce(
                    'Xendit Payment Status - Processing successful payment',
                    date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                );

                $response = [
                    'error' => false,
                    'message' => labels(PAYMENT_COMPLETED_SUCCESSFULLY, 'Payment Completed Successfully'),
                    'payment_status' => "Completed",
                    'data' => $_GET
                ];
            } else {
                log_the_responce(
                    'Xendit Payment Status - Processing failed payment',
                    date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                );

                if (!empty($transaction)) {
                    $transaction_id = $transaction[0]['id'];
                    $user_id = $transaction[0]['user_id'];
                    $txn_subscription_id = $transaction[0]['subscription_id'];

                    log_the_responce(
                        'Xendit Payment Status - Updating transaction to failed - transaction_id: ' . $transaction_id . ', user_id: ' . $user_id . ', subscription_id: ' . $txn_subscription_id,
                        date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                    );

                    // Update transaction status to failed
                    $update_result = update_details([
                        'status' => 'failed',
                        'message' => labels(PAYMENT_FAILED_OR_CANCELLED_BY_USER, 'Payment failed or cancelled by user')
                    ], ['id' => $transaction_id], 'transactions');

                    log_the_responce(
                        'Xendit Payment Status - Transaction update result: ' . json_encode($update_result),
                        date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                    );

                    // Try to find subscription by transaction_id
                    $subscription_data = fetch_details('partner_subscriptions', [
                        'transaction_id' => $transaction_id
                    ], ['id', 'status', 'is_payment']);

                    log_the_responce(
                        'Xendit Payment Status - Found subscription by transaction_id: ' . json_encode($subscription_data),
                        date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                    );

                    if (!empty($subscription_data)) {
                        log_the_responce(
                            'Xendit Payment Status - Updating subscription to failed - subscription_record_id: ' . $subscription_data[0]['id'],
                            date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                        );

                        // Update subscription status to failed
                        $subscription_update_result = update_details([
                            'status' => 'failed',
                            'is_payment' => '2',
                            'updated_at' => date('Y-m-d H:i:s')
                        ], ['id' => $subscription_data[0]['id']], 'partner_subscriptions');

                        log_the_responce(
                            'Xendit Payment Status - Subscription update result: ' . json_encode($subscription_update_result),
                            date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                        );
                    } else {
                        log_the_responce(
                            'Xendit Payment Status - No subscription found for transaction_id: ' . $transaction_id,
                            date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                        );

                        // Let's also check for any transaction with this subscription_id
                        if (!empty($subscription_id)) {
                            $all_transactions = fetch_details('transactions', [
                                'subscription_id' => $subscription_id
                            ], ['id', 'user_id', 'txn_id', 'status']);

                            log_the_responce(
                                'Xendit Payment Status - All transactions for subscription_id: ' . json_encode($all_transactions),
                                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                            );
                        }
                    }

                    log_the_responce(
                        'Xendit Payment Status - Payment failure processed successfully for transaction_id: ' . $transaction_id,
                        date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                    );
                } else {
                    log_the_responce(
                        'Xendit Payment Status - No transaction found for external_id: ' . $external_id,
                        date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                    );

                    // Let's also check for any transaction with this subscription_id
                    if (!empty($subscription_id)) {
                        $all_transactions = fetch_details('transactions', [
                            'subscription_id' => $subscription_id
                        ], ['id', 'user_id', 'txn_id', 'status']);

                        log_the_responce(
                            'Xendit Payment Status - All transactions for subscription_id: ' . json_encode($all_transactions),
                            date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
                        );
                    }
                }

                $response = [
                    'error' => true,
                    'message' => labels(PAYMENT_FAILED_OR_CANCELLED, 'Payment Failed or Cancelled'),
                    'payment_status' => "Failed",
                    'data' => $_GET
                ];
            }

            log_the_responce(
                'Xendit Payment Status - Final response: ' . json_encode($response),
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
            );

            print_r(json_encode($response));
        } catch (\Exception $th) {
            log_the_responce(
                'Xendit Payment Status - Exception occurred: ' . $th->getMessage() . ' - Stack trace: ' . $th->getTraceAsString(),
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()'
            );

            $response = [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'payment_status' => 'Failed'
            ];
            log_the_responce('Xendit Payment Status Error: ' . $th->getMessage(), date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - xendit_payment_status()');
            print_r(json_encode($response));
        }
    }

    // Helper functions
    private function xendit_transaction_webview($user_id, $transaction_id, $subscription_id, $amount)
    {
        try {
            $user_data = fetch_details('users', ['id' => $user_id])[0];
            $xendit = new Xendit();
            $xendit_credentials = $xendit->get_credentials();
            // Prepare Xendit invoice data
            $external_id = 'subscription_' . $subscription_id . '_' . $user_id . '_' . time();
            $invoice_data = [
                'external_id' => $external_id,
                'amount' => floatval($amount),
                'customer_name' => $user_data['username'],
                'customer_email' => !empty($user_data['email']) ? $user_data['email'] : '',
                'customer_phone' => $user_data['phone'] ?? '',
                'success_url' => base_url('partner/api/v1/xendit_payment_status?external_id=' . $external_id . '&status=success&subscription_id=' . $subscription_id),
                'failure_url' => base_url('partner/api/v1/xendit_payment_status?external_id=' . $external_id . '&status=failed&subscription_id=' . $subscription_id),
                'description' => 'Subscription Payment for Partner #' . $user_id,
                'metadata' => [
                    'subscription_id' => $subscription_id,
                    'partner_id' => $user_id,
                    'transaction_id' => $transaction_id,
                    'payment_type' => 'subscription'
                ]
            ];

            $invoice = $xendit->create_invoice($invoice_data);

            if ($invoice && isset($invoice['invoice_url'])) {
                // Stamp external_id as `reference` on the pending subscription transaction row.
                // Webhook resolves by (type=xendit, reference). txn_id stays empty until webhook
                // lands with the actual Xendit payment_id.
                $transaction_update = [
                    'reference' => $invoice['external_id'],
                    'type'      => 'xendit',
                ];
                update_details($transaction_update, ['id' => $transaction_id], 'transactions');

                log_the_responce('Xendit subscription payment link generated successfully for partner: ' . $user_id, 'app/Controllers/partner/Partner.php - XenditgeneratePaymentLink()');
                return $invoice['invoice_url'];
            } else {
                log_the_responce('Failed to create Xendit invoice for partner subscription', 'app/Controllers/partner/Partner.php - XenditgeneratePaymentLink()');
                throw new Exception('Failed to create payment invoice');
            }
        } catch (\Exception $th) {
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_GET) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - xendit_transaction_webview()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => []
            ]);
        }
    }
}