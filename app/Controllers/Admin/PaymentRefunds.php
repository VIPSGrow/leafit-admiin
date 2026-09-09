<?php

namespace App\Controllers\Admin;

use App\Models\RefundTransaction_model;
use App\Services\utility\FileService;

/**
 * PaymentRefunds Controller
 * 
 * Displays and manages refund transactions from the existing transactions table
 * Shows only transactions where transaction_type = 'refund'
 */
class PaymentRefunds extends Admin
{
    protected $refundModel;
    protected $validation;

    public function __construct()
    {
        parent::__construct();
        $this->refundModel = new RefundTransaction_model();
        $this->validation = \Config\Services::validation();
        helper('ResponceServices');
        helper('function');
    }

    /**
     * Display the payment refunds page
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface|string
     */
    public function index()
    {
        try {
            // Check admin authentication
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            // Set page information for admin template
            setPageInfo($this->data, labels('payment_refunds', 'Payment Refunds') . ' | ' . labels('admin_panel', 'Admin Panel'), 'payment_refunds');

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/PaymentRefunds.php - index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get refund transactions data for Bootstrap Table
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function list()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON(['error' => true, 'message' => labels(UNAUTHORIZED_ACCESS, 'Unauthorized access')]);
            }

            $request = \Config\Services::request();

            $currentLang = get_current_language();
            $defaultLang = get_default_language();

            // Get Bootstrap Table parameters
            $limit = (int)($request->getGet('limit') ?: 10);
            $offset = (int)($request->getGet('offset') ?: 0);
            $search = $request->getGet('search') ?? '';
            $sort = $request->getGet('sort') ?: 'id';
            $order = $request->getGet('order') ?: 'desc';

            // Validate parameters
            if ($limit < 1) $limit = 10;
            if ($offset < 0) $offset = 0;

            // Map sort fields to actual database columns
            $sortFields = [
                'id' => 't.id',
                'order_id' => 't.order_id',
                'customer_name' => 'u.username',
                'refund_amount' => 't.amount',
                'payment_method' => 't.type',
                'refund_status' => 't.status',
                'created_at' => 't.created_at'
            ];
            $orderColumn = $sortFields[$sort] ?? 't.id';

            // No additional filters needed
            $where = [];

            // Get refunds with details
            $result = $this->refundModel->getRefundTransactions($where, $limit, $offset, $orderColumn, $order, $search);

            // Validate result
            if (!isset($result['total']) || !isset($result['data'])) {
                throw new \Exception(labels('invalid_result_from_refundtransaction_model', 'Invalid result from RefundTransaction_model'));
            }

            $allTranslations = [];
            if (!empty($result['data'])) {
                $partnerIds = array_column($result['data'], 'partner_id');
                $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();
                // Get all translations for all partners (not just current language)
                $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
            }

            // Format data for Bootstrap Table
            $rows = [];
            foreach ($result['data'] as $row) {
                // Safely handle potentially null values
                $customerName = !empty($row['customer_name']) ? $row['customer_name'] : labels('unknown', 'Unknown');
                $partnerName = !empty($row['partner_name']) ? $row['partner_name'] : labels('unknown', 'Unknown');
                $amount = isset($row['amount']) && is_numeric($row['amount']) ? number_format($row['amount'], 2) : '0.00';
                $paymentMethod = !empty($row['type']) ? ucfirst($row['type']) : labels('unknown', 'Unknown');
                $status = !empty($row['status']) ? $row['status'] : labels('unknown', 'Unknown');
                $orderStatus = !empty($row['order_status']) ? $row['order_status'] : labels('unknown', 'Unknown');
                $txnId = !empty($row['txn_id']) ? $row['txn_id'] : '-';
                $message = !empty($row['message']) ? $row['message'] : '-';

                // Handle date formatting safely
                $dateOfService = '-';
                if (!empty($row['date_of_service'])) {
                    try {
                        $dateOfService = date('d-m-Y', strtotime($row['date_of_service']));
                    } catch (\Exception $e) {
                        $dateOfService = '-';
                    }
                }

                $createdAt = '-';
                if (!empty($row['created_at'])) {
                    try {
                        $createdAt = date('d-m-Y H:i', strtotime($row['created_at']));
                    } catch (\Exception $e) {
                        $createdAt = '-';
                    }
                }

                // echo "<pre>";
                // print_r($row);
                // die();
                $partner_details = fetch_details('partner_details', ['partner_id' => $row['partner_id']]);

                // Get translations for this specific partner
                $partnerTranslations = $allTranslations[$row['partner_id']] ?? [];

                // Re-index translations by language_code for easier access
                $translationsByLang = [];
                if (!empty($partnerTranslations)) {
                    foreach ($partnerTranslations as $langCode => $translation) {
                        $translationsByLang[$langCode] = $translation;
                    }
                }

                // Get translated company name with fallback logic
                // Priority: current language → default language → base table
                $companyName = $partner_details[0]['company_name'] ?? ''; // fallback to base table
                $partner_name = $row['partner_name'] ?? ''; // fallback to base table
                if (!empty($translationsByLang)) {
                    // Try current language first
                    if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['company_name'])) {
                        $companyName = $translationsByLang[$currentLang]['company_name'];
                    }
                    // Fallback to default language
                    elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['company_name'])) {
                        $companyName = $translationsByLang[$defaultLang]['company_name'];
                    }

                    if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['username'])) {
                        $partner_name = $translationsByLang[$currentLang]['username'];
                    } elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['username'])) {
                        $partner_name = $translationsByLang[$defaultLang]['username'];
                    }
                }

                $fileService = new FileService();
                $imageSrc = $fileService->url($row['partner_image'], 'profile');

                $profile = '<div class="o-media o-media--middle">
                            <a href="' . $imageSrc . '" data-lightbox="image-1">
                                <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $partner_name . '">
                            </a>';
                $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
                    <div class="provider_name_table" >' . $partner_name .'</span></div>
                    <div class="provider_email_table">' . $companyName . '</div>
                    <div class="provider_email_table">' . $row['partner_email'] . '(' . $row['partner_phone'] . ')</div>
                    </div>
                    </div></a>';

                $rows[] = [
                    'id' => $row['id'] ?? 0,
                    'order_id' => $row['order_id'] ?? '-',
                    'customer_name' => $customerName,
                    'partner_name' => $profile,
                    'refund_amount' => $amount,
                    'payment_method' => labels(strtolower($paymentMethod), $paymentMethod),
                    'refund_status' => $this->getStatusBadge($status),
                    'order_status' => $this->getOrderStatusBadge($orderStatus),
                    'txn_id' => $txnId,
                    'date_of_service' => $dateOfService,
                    'created_at' => $createdAt,
                    // 'message' => $message,
                    'message' => labels(strtolower($message), $message),
                    'actions' => $this->generateActionButtons($row)
                ];
            }

            // Format response exactly like other admin controllers
            $response = [
                'total' => $result['total'],
                'rows' => $rows
            ];

            // Set proper content type and return JSON
            $this->response->setContentType('application/json');
            return json_encode($response);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/PaymentRefunds.php - list()');
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')]);
        }
    }

    /**
     * Get refund details for modal view
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function getRefundDetails()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setStatusCode(401)->setBody(labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'));
            }

            $request = \Config\Services::request();
            $refundId = $request->getGet('id');

            if (!$refundId) {
                return $this->response->setStatusCode(400)->setBody(labels(REFUND_ID_IS_REQUIRED, 'Refund ID is required'));
            }

            // Get refund details
            $refund = $this->refundModel->getRefundById($refundId);

            if (!$refund) {
                return $this->response->setStatusCode(404)->setBody(labels(DATA_NOT_FOUND, 'Refund not found'));
            }

            // Prepare the HTML response
            $html = '
            <div class="row">
                <div class="col-md-6">
                    <h6>' . labels('refund_information', 'Refund Information') . '</h6>
                    <table class="table table-sm">
                        <tr><td><strong>' . labels('refund_id', 'Refund ID') . ':</strong></td><td>' . $refund['id'] . '</td></tr>
                        <tr><td><strong>' . labels('transaction_id', 'Transaction ID') . ':</strong></td><td>' . $refund['txn_id'] . '</td></tr>
                        <tr><td><strong>' . labels('status', 'Status') . ':</strong></td><td>' . $this->getStatusBadge($refund['status']) . '</td></tr>
                        <tr><td><strong>' . labels('amount', 'Amount') . ':</strong></td><td>' . $refund['currency_code'] . ' ' . number_format($refund['amount'], 2) . '</td></tr>
                        <tr><td><strong>' . labels('payment_method', 'Payment Method') . ':</strong></td><td>' . ucfirst($refund['type']) . '</td></tr>
                        <tr><td><strong>' . labels('processed_at', 'Processed At') . ':</strong></td><td>' . date('d-m-Y H:i', strtotime($refund['created_at'])) . '</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>' . labels('order_information', 'Order Information') . '</h6>
                    <table class="table table-sm">
                        <tr><td><strong>' . labels('order_id', 'Order ID') . ':</strong></td><td>' . $refund['order_id'] . '</td></tr>
                        <tr><td><strong>' . labels('order_total', 'Order Total') . ':</strong></td><td>' . $refund['currency_code'] . ' ' . number_format($refund['order_total'], 2) . '</td></tr>
                        <tr><td><strong>' . labels('order_status', 'Order Status') . ':</strong></td><td>' . $this->getOrderStatusBadge($refund['order_status']) . '</td></tr>
                        <tr><td><strong>' . labels('service_date', 'Service Date') . ':</strong></td><td>' . ($refund['date_of_service'] ? date('d-m-Y', strtotime($refund['date_of_service'])) : '-') . '</td></tr>
                        <tr><td><strong>' . labels('customer', 'Customer') . ':</strong></td><td>' . $refund['customer_name'] . '</td></tr>
                        <tr><td><strong>' . labels('customer_email', 'Customer Email') . ':</strong></td><td>' . $refund['customer_email'] . '</td></tr>
                        <tr><td><strong>' . labels('partner', 'Partner') . ':</strong></td><td>' . $refund['partner_name'] . '</td></tr>
                    </table>
                </div>
            </div>';

            if (!empty($refund['message'])) {
                $html .= '
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>' . labels('refund_message', 'Refund Message') . '</h6>
                        <div class="alert alert-info">' . htmlspecialchars(labels($refund['message'], $refund['message'])) . '</div>
                    </div>
                </div>';
            }

            return $this->response->setStatusCode(200)->setBody($html);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/PaymentRefunds.php - getRefundDetails()');
            return $this->response->setStatusCode(500)->setBody(labels(ERROR_OCCURED, "Error loading refund details"));
        }
    }

    /**
     * Update refund transaction status to success
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function updateStatus()
    {
        try {
            // Check admin authentication
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON(['error' => true, 'message' => labels('unauthorized_access', 'Unauthorized access')]);
            }

            $request = \Config\Services::request();

            // Get transaction ID from request
            $transactionId = $request->getPost('id');

            if (empty($transactionId) || !is_numeric($transactionId)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('transaction_id_is_required', 'Transaction ID is required')]);
            }

            // Verify the transaction exists and is a refund
            $refund = $this->refundModel->getRefundById($transactionId);
            if (!$refund) {
                return $this->response->setJSON(['error' => true, 'message' => labels(DATA_NOT_FOUND, 'Data not found')]);
            }

            // Check if already successful
            $currentStatus = strtolower($refund['status'] ?? '');
            if (in_array($currentStatus, ['success', 'completed', 'succeeded'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels(REFUND_IS_ALREADY_PROCESSED, 'Refund is already processed')]);
            }

            $updated = $this->refundModel->updateTransactionStatus($transactionId, 'success', 'txn_manual_refund');

            if ($updated) {
                // Send notifications to user and admin when refund is manually approved by admin
                // NotificationService handles FCM, Email, and SMS notifications using templates
                // Single generalized template works for both user and admin
                try {
                    // Get refund details again after update to ensure we have latest data
                    $updatedRefund = $this->refundModel->getRefundById($transactionId);
                    
                    if (!empty($updatedRefund)) {
                        $order_id = $updatedRefund['order_id'] ?? null;
                        $user_id = $updatedRefund['user_id'] ?? null;
                        $refund_amount = $updatedRefund['amount'] ?? 0;
                        $currency = $updatedRefund['currency_code'] ?? 'USD';
                        $refund_id = $updatedRefund['id'] ?? $transactionId;
                        $transaction_id = $updatedRefund['txn_id'] ?? '';
                        
                        // Get user and order details for notification context
                        $user_details = fetch_details('users', ['id' => $user_id], ['username', 'email']);
                        $order_details = fetch_details('orders', ['id' => $order_id], ['total']);
                        
                        $customer_name = !empty($user_details) && !empty($user_details[0]['username']) ? $user_details[0]['username'] : 'Customer';
                        $customer_email = !empty($user_details) && !empty($user_details[0]['email']) ? $user_details[0]['email'] : '';
                        
                        // Prepare context data for notification templates (generalized for both user and admin)
                        // This context will be used to populate template variables like [[order_id]], [[amount]], [[currency]], etc.
                        $notificationContext = [
                            'order_id' => $order_id,
                            'amount' => number_format($refund_amount, 2),
                            'currency' => $currency,
                            'refund_id' => (string)$refund_id,
                            'transaction_id' => $transaction_id,
                            'customer_name' => $customer_name,
                            'customer_email' => $customer_email,
                            'customer_id' => $user_id,
                            'processed_date' => date('d-m-Y H:i:s')
                        ];

                        // Queue all notifications (FCM, Email, SMS) to user using NotificationService
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking for each channel
                        // - Fetching user email/phone/FCM tokens
                        // - Unsubscribe status checking for email
                        queue_notification_service(
                            eventType: 'payment_refund_successful',
                            recipients: ['user_id' => $user_id],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'platforms' => ['android', 'ios', 'web'], // User platforms for FCM
                                'type' => 'refund', // Notification type for app routing
                                'data' => [
                                    'order_id' => (string)$order_id,
                                    'refund_id' => (string)$refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[MANUAL_REFUND_APPROVAL_USER_NOTIFICATION] Notification queued for user: ' . $user_id . ', Result: ' . json_encode($userResult));

                        // Queue all notifications (FCM, Email, SMS) to admin using NotificationService
                        // Send to admin panel users (user group 1 is typically admin)
                        // Uses the same generalized template and context
                        queue_notification_service(
                            eventType: 'payment_refund_successful',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'user_groups' => [1], // Admin user group
                                'platforms' => ['admin_panel'], // Admin platform for FCM
                                'type' => 'refund', // Notification type for app routing
                                'data' => [
                                    'order_id' => (string)$order_id,
                                    'refund_id' => (string)$refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[MANUAL_REFUND_APPROVAL_ADMIN_NOTIFICATION] Notification queued for admin, Result: ' . json_encode($adminResult));
                    }
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the refund approval
                    log_message('error', '[MANUAL_REFUND_APPROVAL_NOTIFICATION] Notification error trace: ' . $notificationError->getTraceAsString());
                }

                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels('refund_successful', 'Refund successful'),
                    'data' => ['id' => $transactionId, 'status' => 'success']
                ]);
            } else {
                return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED, 'An Error Occurred')]);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/PaymentRefunds.php - updateStatus()');
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')]);
        }
    }

    /**
     * Generate status badge HTML
     * 
     * @param string $status
     * @return string
     */
    private function getStatusBadge($status)
    {
        $status = strtolower($status ?? 'unknown');
        $badges = [
            'pending' => '<span class="badge badge-warning">' . labels('pending', 'Pending') . '</span>',
            'processing' => '<span class="badge badge-info">' . labels('processing', 'Processing') . '</span>',
            'success' => '<span class="badge badge-success">' . labels('success', 'Success') . '</span>',
            'completed' => '<span class="badge badge-success">' . labels('completed', 'Completed') . '</span>',
            'succeeded' => '<span class="badge badge-success">' . labels('succeeded', 'Succeeded') . '</span>',
            'processed' => '<span class="badge badge-success">' . labels('processed', 'Processed') . '</span>',
            'failed' => '<span class="badge badge-danger">' . labels('failed', 'Failed') . '</span>',
            'cancelled' => '<span class="badge badge-secondary">' . labels('cancelled', 'Cancelled') . '</span>',
            'unknown' => '<span class="badge badge-secondary">' . labels('unknown', 'Unknown') . '</span>'
        ];

        return $badges[$status] ?? '<span class="badge badge-light">' . ucfirst($status) . '</span>';
    }

    /**
     * Generate order status badge HTML
     * 
     * @param string $status
     * @return string
     */
    private function getOrderStatusBadge($status)
    {
        $status = strtolower($status ?? 'unknown');
        $badges = [
            'pending' => '<span class="badge badge-warning">' . labels('pending', 'Pending') . '</span>',
            'confirmed' => '<span class="badge badge-info">' . labels('confirmed', 'Confirmed') . '</span>',
            'ongoing' => '<span class="badge badge-primary">' . labels('ongoing', 'Ongoing') . '</span>',
            'completed' => '<span class="badge badge-success">' . labels('completed', 'Completed') . '</span>',
            'cancelled' => '<span class="badge badge-danger">' . labels('cancelled', 'Cancelled') . '</span>',
            'refund_pending' => '<span class="badge badge-orange">' . labels('refund_pending', 'Refund Pending') . '</span>',
            'unknown' => '<span class="badge badge-secondary">' . labels('unknown', 'Unknown') . '</span>'
        ];

        return $badges[$status] ?? '<span class="badge badge-light">' . ucfirst($status) . '</span>';
    }

    /**
     * Generate action buttons for each refund
     * 
     * @param array $row
     * @return string
     */
    private function generateActionButtons($row)
    {
        $buttons = [];
        $id = $row['id'] ?? 0;
        $status = strtolower($row['status'] ?? '');

        // // View details button
        // $buttons[] = '<button class="btn btn-sm btn-info view-details" data-id="' . $id . '" title="View Details">
        //                 <i class="fas fa-eye"></i>
        //               </button>';

        // Define successful states that don't need the button
        $successfulStates = ['success', 'completed', 'succeeded', 'processed'];
        $failedStates = ['failed', 'cancelled'];

        // Mark as success button - only enable for pending/processing refunds
        if ($status === 'pending' || $status === 'processing') {
            // Enable button for pending and processing refunds
            $buttons[] = '<button class="btn btn-sm btn-success mark-success" data-id="' . $id . '" title="' . labels('mark_as_success', 'Mark as Success') . '">
                                <i class="fas fa-check"></i>
                              </button>';
        } elseif (in_array($status, $successfulStates)) {
            // Show disabled button with success icon for already successful refunds
            $buttons[] = '<button class="btn btn-sm btn-success" disabled title="' . labels('already_successful', 'Already Successful') . '">
                                <i class="fas fa-check-circle"></i>
                              </button>';
        } elseif (in_array($status, $failedStates)) {
            // Show disabled button with failed icon for failed refunds
            $buttons[] = '<button class="btn btn-sm btn-danger" disabled title="' . labels('refund_failed', 'Refund Failed') . '">
                                <i class="fas fa-times-circle"></i>
                              </button>';
        } else {
            // Show disabled button for unknown states
            $buttons[] = '<button class="btn btn-sm btn-secondary" disabled title="' . labels('action_not_available', 'Action Not Available') . '">
                                <i class="fas fa-question-circle"></i>
                              </button>';
        }

        return implode(' ', $buttons);
    }
}
