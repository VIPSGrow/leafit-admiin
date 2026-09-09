<?php

namespace App\Controllers\Admin;

class Users extends Admin
{
    public $user_model, $admin_id;
    protected $superadmin;
    protected $defaultLanguage;

    public function __construct()
    {
        parent::__construct();
        $this->user_model = new \App\Models\Users_model();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->admin_id = ($this->ionAuth->isAdmin()) ? $this->ionAuth->user()->row()->id : 0;
        $this->superadmin = $this->session->get('email');
        $this->defaultLanguage = get_default_language();
        helper(['ResponceServices']);
    }
    public function index()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('user_list', 'User List') . ' | ' . labels('admin_panel', 'Admin Panel'), 'users');
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }
    public function list_user()
    {
        $limit  = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort   = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order  = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

        $result = $this->user_model->list(false, $search, $limit, $offset, $sort, $order);

        // --- Minimal UTF-8 sanitization ---
        array_walk_recursive($result, function (&$item) {
            if (is_string($item)) {
                $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            }
        });

        // --- Fix json_encode options (instead of "true") ---
        $data = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $data;
    }

    public function deactivate()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                $result = checkModificationInDemoMode($this->superadmin);
                if ($result !== true) {
                    return $this->response->setJSON($result);
                }
                $id = $this->request->getVar('user_id');
                $userdata = fetch_details('users', ['id' => $id], ['email', 'username']);

                // Send notifications before deactivating the user
                // NotificationService handles FCM, Email, and SMS notifications using templates
                // This unified approach sends notifications to the respective user
                // Note: We send notifications before deactivation so the user can still receive them
                try {
                    // Prepare context data for notification templates
                    // This context will be used to populate template variables like [[user_name]], [[user_id]], [[company_name]], etc.
                    $notificationContext = [
                        'user_id' => $id
                    ];

                    // Queue all notifications (FCM, Email, SMS) to the specific user
                    // NotificationService automatically handles:
                    // - Translation of templates based on user language
                    // - Variable replacement in templates
                    // - Notification settings checking for each channel
                    // - Fetching user email/phone/FCM tokens
                    // - Unsubscribe status checking for email
                    queue_notification_service(
                        eventType: 'user_account_deactive',
                        recipients: [
                            'user_id' => $id,
                            'email' => $userdata[0]['email'] ?? null
                        ],
                        context: $notificationContext,
                        options: [
                            'channels' => ['fcm', 'email', 'sms'] // All channels handled by NotificationService
                        ]
                    );

                    // log_message('info', '[USER_ACCOUNT_DEACTIVE] Notification result: ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // Log notification error but don't fail the deactivation
                    log_message('error', '[USER_ACCOUNT_DEACTIVE] Notification error trace: ' . $notificationError->getTraceAsString());
                }

                // Manual deactivation logic (without IonAuth call).
                // We keep this simple and explicit to avoid vendor/library side effects.
                $db = db_connect();
                $operations = false;
                if ((int) $id !== (int) $this->admin_id) {
                    $db->table('users')->where('id', (int) $id)->update([
                        'active' => 0,
                    ]);
                    $operations = ($db->affectedRows() >= 0);
                }
                if ($operations) {
                    delete_details(['user_id' => $id], 'users_tokens');

                    return successResponse("Email sent to the user successfully and user is disabled", false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse("Could not deactivate user", true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Users.php - deactivate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function activate()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                $result = checkModificationInDemoMode($this->superadmin);
                if ($result !== true) {
                    return $this->response->setJSON($result);
                }
                $id = $this->request->getVar('user_id');
                // Manual activation logic (without IonAuth call).
                // We explicitly clear activation fields and mark user as active.
                $db = db_connect();
                $db->table('users')->where('id', (int) $id)->update([
                    'activation_selector' => null,
                    'activation_code' => null,
                    'active' => 1,
                ]);
                $operations = ($db->affectedRows() >= 0);
                $userdata = fetch_details('users', ['id' => $id], ['email', 'username']);
                if ($operations) {
                    // Send notifications when user account is activated
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // This unified approach sends notifications to the respective user
                    try {
                        // Prepare context data for notification templates
                        // This context will be used to populate template variables like [[user_name]], [[user_id]], [[company_name]], etc.
                        $notificationContext = [
                            'user_id' => $id
                        ];

                        // Queue all notifications (FCM, Email, SMS) to the specific user
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking for each channel
                        // - Fetching user email/phone/FCM tokens
                        // - Unsubscribe status checking for email
                        queue_notification_service(
                            eventType: 'user_account_active',
                            recipients: [
                                'user_id' => $id,
                                'email' => $userdata[0]['email'] ?? null
                            ],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'] // All channels handled by NotificationService
                            ]
                        );

                        // log_message('info', '[USER_ACCOUNT_ACTIVE] Notification result: ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log notification error but don't fail the activation
                        log_message('error', '[USER_ACCOUNT_ACTIVE] Notification error trace: ' . $notificationError->getTraceAsString());
                    }

                    return successResponse("Email sent to the user successfully and user is active", false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse("Some error occurred", true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Users.php - activate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
