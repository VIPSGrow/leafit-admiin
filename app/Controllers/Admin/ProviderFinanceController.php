<?php

namespace App\Controllers\Admin;

use App\Models\Cash_collection_model;
use App\Models\Partners_model;
use App\Models\Payment_request_model;
use App\Models\Settlement_model;

class ProviderFinanceController extends Admin
{
    public $partner, $cash_collection, $settle_commission, $db, $defaultLanguage, $superadmin;

    public function __construct()
    {
        parent::__construct();
        $this->partner = new Partners_model();
        $this->cash_collection = new Cash_collection_model();
        $this->settle_commission = new Settlement_model();
        $this->db = \Config\Database::connect();
        $this->defaultLanguage = get_default_language();
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
    }

    public function payment_request()
    {
        try {
            helper('function');
            setPageInfo($this->data, labels(PROVIDERS, 'Providers') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'payment_request');

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            return view('backend/admin/template', $this->data);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - payment_request()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function payment_request_list()
    {
        try {
            $payment_requests = new Payment_request_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'p.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $data = $payment_requests->list(false, $search, $limit, $offset, $sort, $order);
            return $data;
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - payment_request_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function pay_partner()
    {
        try {
            $result = (new \App\Services\Provider\ProviderFinanceService())->processPaymentRequest(
                requestId: (int) $this->request->getPost('request_id'),
                userId: (int) $this->request->getPost('user_id'),
                adminId: (int) $this->userId,
                reason: $this->request->getPost('reason'),
                amount: $this->request->getPost('amount'),
                status: (int) $this->request->getPost('status'),
                defaultLanguage: $this->defaultLanguage
            );

            if ($result['error']) {
                return JsonError($result['message']);
            }
            return JsonSuccess($result['message']);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - pay_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function delete_request()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            try {
                $id = $this->request->getPost('id');
                $builder = $this->db->table('payment_request')->delete(['id' => $id]);
                if ($builder) {
                    return JsonSuccess(labels(DELETED_PAYMENT_REQUEST_SUCCESS, "Deleted payment request success"));
                } else {
                    return JsonError(labels(COULD_NOT_DELETE_PAYMENT_REQUEST, "Couldnt delete payment request"));
                }
            } catch (\Exception $th) {
                return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - delete_request()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function settle_commission()
    {
        try {
            helper('function');
            setPageInfo($this->data, labels(COMMISSION_SETTLEMENT, 'Commission Settlement') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'manage_commission');

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            return view('backend/admin/template', $this->data);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - settle_commission()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function cash_collection()
    {
        try {
            helper('function');
            setPageInfo($this->data, labels('cash_collection', 'Cash Collection') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'cash_collection');
            return view('backend/admin/template', $this->data);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cash_collection()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function commission_list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            // Get current language for translations
            // This ensures translations are returned for the currently selected language in admin panel
            $current_language = get_current_language();

            // Hide providers with non-positive balance — nothing to settle for them.
            $positiveBalanceFilter = ['u.balance >' => 0];

            // Pass current language to model method to ensure proper translation fallback:
            // current language → default language → base table
            return json_encode($this->partner->unsettled_commission_list(false, $search, $limit, $offset, $sort, $order, $positiveBalanceFilter, 'pd.id', [], [], $current_language));
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - commission_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function cash_collection_list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            // Decode URL-encoded search term to handle spaces and special characters
            // This ensures "BrightFix%20Services" becomes "BrightFix Services"
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? urldecode($_GET['search']) : '';
            // Trim the decoded search term to remove any extra whitespace
            $search = trim($search);

            // Get current language for translations
            $current_language = get_current_language();

            // Exclude partners whose payable_commision is zero or negative to keep the table focused on actionable cash collections.
            $positiveCommissionFilter = ['u.payable_commision >' => 0];
            $data = json_encode($this->partner->list(false, $search, $limit, $offset, $sort, $order, $positiveCommissionFilter, 'pd.id', [], [], null, $current_language));
            print_r($data);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cash_collection_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function commission_pay_out()
    {
        try {
            $partner_id = (int) $this->request->getPost('partner_id');
            $amount     = $this->request->getPost('amount');

            if (empty($partner_id) || empty($amount)) {
                return JsonError(labels(PLEASE_ENTER_COMMISSION, 'Please enter commission'));
            }

            $usersModel      = new \App\Models\Users_model();
            $current_balance = $usersModel->select(['balance', 'email'])->find($partner_id);

            if (empty($current_balance)) {
                return JsonError(labels(USER_NOT_FOUND, 'User not found'));
            }
            if ($current_balance['balance'] <= 0) {
                return JsonError(labels(CANNOT_WITHDRAW_WHEN_BALANCE_IS_0_OR_LESS, 'Cannot withdraw when balance is 0 or less'));
            }
            if ($amount < 0) {
                return JsonError(labels(AMOUNT_MUST_BE_GREATER_THAN_0, 'Amount must be greater than 0'));
            }

            log_message('info', "Commission settlement attempt - Partner ID: {$partner_id}, Amount: {$amount}, Current Balance: {$current_balance['balance']}");

            $this->validation->setRules([
                'amount' => [
                    'rules'  => 'required|numeric|greater_than[0]|less_than_equal_to[' . $current_balance['balance'] . ']',
                    'errors' => [
                        'required'           => labels(PLEASE_ENTER_COMMISSION, 'Please enter commission'),
                        'numeric'            => labels(PLEASE_ENTER_A_NUMERIC_VALUE_FOR_COMMISSION, 'Please enter a numeric value for commission'),
                        'greater_than'       => labels(AMOUNT_MUST_BE_GREATER_THAN_0, 'Amount must be greater than 0'),
                        'less_than_equal_to' => labels(AMOUNT_MUST_BE_LESS_THAN_OR_EQUAL_TO_CURRENT_BALANCE, 'Amount must be less than or equal to current balance'),
                    ],
                ],
            ]);
            if (!$this->validation->withRequest($this->request)->run()) {
                return JsonError($this->validation->getErrors());
            }

            $result = (new \App\Services\Provider\ProviderFinanceService())->payOutCommission(
                partnerId: $partner_id,
                adminId: (int) $this->userId,
                amount: (float) $amount,
                message: $this->request->getPost('message'),
                defaultLanguage: $this->defaultLanguage
            );

            if ($result['error']) {
                return JsonError($result['message']);
            }
            return JsonSuccess($result['message']);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - commission_pay_out()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function cash_collection_deduct()
    {
        try {
            $partner_id = (int) $this->request->getPost('partner_id');
            $amount     = $this->request->getPost('amount');
            $message    = (string) $this->request->getPost('message');

            $usersModel      = new \App\Models\Users_model();
            $current_balance = $usersModel->select(['payable_commision', 'email'])->find($partner_id);

            $this->validation->setRules([
                'amount' => [
                    'rules'  => 'required|numeric|greater_than[0]|less_than_equal_to[' . ($current_balance['payable_commision'] ?? 0) . ']',
                    'errors' => [
                        'required'     => labels(PLEASE_ENTER_COMMISSION, 'Please enter commission'),
                        'numeric'      => labels(PLEASE_ENTER_A_NUMERIC_VALUE_FOR_COMMISSION, 'Please enter numeric value for commission'),
                        'greater_than' => labels('amount_can_not_be_zero', 'Amount must be greater than zero'),
                        'less_than'    => labels(AMOUNT_MUST_BE_LESS_THAN_CURRENT_PAYABLE_COMMISSION, 'Amount must be less than current payable commision'),
                    ],
                ],
                'message' => [
                    'rules'  => 'required|min_length[1]',
                    'errors' => [
                        'required'   => labels('message_required', 'Message is required'),
                        'min_length' => labels('message_required', 'Message is required'),
                    ],
                ],
            ]);
            if (!$this->validation->withRequest($this->request)->run()) {
                return JsonError($this->validation->getErrors());
            }

            $result = (new \App\Services\Provider\ProviderFinanceService())->deductCashCollection(
                partnerId: $partner_id,
                adminId: (int) $this->userId,
                amount: (float) $amount,
                message: $message
            );

            if ($result['error']) {
                return JsonError($result['message']);
            }
            return JsonSuccess($result['message']);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cash_collection_deduct()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function cash_collection_history()
    {
        setPageInfo($this->data, labels('cash_collection', 'Cash Collection') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'cash_collection_history');

        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/admin/template', $this->data);
    }
    public function settle_commission_history()
    {
        setPageInfo($this->data, labels(COMMISSION_SETTLEMENT, 'Commision Settlement') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'commision_history');

        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/admin/template', $this->data);
    }
    public function manage_commission_history_list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        print_r(json_encode($this->settle_commission->list(false, $search, $limit, $offset, $sort, $order)));
    }
    public function cash_collection_history_list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        // Decode URL-encoded search term to handle spaces and special characters
        // This ensures "BrightFix%20Services" becomes "BrightFix Services"
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? urldecode($_GET['search']) : '';
        // Trim the decoded search term to remove any extra whitespace
        $search = trim($search);
        print_r(json_encode($this->cash_collection->list(false, $search, $limit, $offset, $sort, $order)));
    }
    public function payment_request_multiple_update()
    {
        try {
            $requestIds = (array) $this->request->getPost('request_ids');
            $newStatus  = (string) $this->request->getPost('status');
            $paymentRequests = new \App\Models\Payment_request_model();
            $count = true;

            foreach ($requestIds as $rid) {
                $row = $paymentRequests->find((int) $rid);
                if (empty($row)) {
                    continue;
                }
                if ($row['status'] != $newStatus) {
                    if ($row['status'] == '0' && in_array($newStatus, ['1', '2', '3'], true)) {
                        $paymentRequests->update((int) $row['id'], ['status' => $newStatus]);
                        $count = false;
                    } elseif ($row['status'] == '1' && $newStatus == '3') {
                        $paymentRequests->update((int) $row['id'], ['status' => $newStatus]);
                        $count = false;
                    }
                }
                if ($count == true) {
                    return JsonError('Cannot Update');
                } else {
                    return JsonSuccess(labels(BULK_UPDATE_SUCCESSFULLY, 'Bulk update successfully'));
                }
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - payment_request_multiple_update()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function payment_request_settement_status()
    {
        try {
            $db     = \Config\Database::connect();
            $builder = $db->table('payment_request');
            $builder->where('id', $_POST['id']);
            $builder->update(['status' => '3']);
            return JsonSuccess(labels(PAYMENT_REQUEST_SETTLED_SUCCESSFULLY, "Payment Request Settled Succssfully"));
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - payment_request_settement_status()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function bulk_commission_settelement()
    {
        try {
            $result = (new \App\Services\Provider\ProviderFinanceService())->bulkSettleCommission(
                partnerIds: (array) $this->request->getPost('request_ids'),
                message: $this->request->getPost('message')
            );

            if ($result['error']) {
                return JsonError($result['message']);
            }
            return JsonSuccess($result['message']);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - bulk_commission_settelement()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function bulk_cash_collection()
    {
        try {
            $result = (new \App\Services\Provider\ProviderFinanceService())->bulkCollectCash(
                partnerIds: (array) $this->request->getPost('request_ids'),
                adminId: (int) $this->userId,
                message: $this->request->getPost('message')
            );

            if ($result['error']) {
                return JsonError($result['message']);
            }
            return JsonSuccess($result['message']);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - bulk_cash_collection()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
}
