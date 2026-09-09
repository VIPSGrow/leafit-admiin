<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;


class ContactUsApiController extends BaseController
{
    protected $request, $trans, $db, $data;

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function contact_us_api()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'name' => 'required',
                    'subject' => 'required',
                    'message' => 'required',
                    'email' => 'required'
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
            $name = $_POST['name'];
            $subject = $_POST['subject'];
            $message = $_POST['message'];
            $email = $_POST['email'];
            $admin_contact_query = [
                'name' => $name,
                'subject' => $subject,
                'message' => $message,
                'email' => isset($email) ? $email : "0",
            ];
            insert_details($admin_contact_query, 'admin_contact_query');

            // Send notifications to admin users about the new query
            // Queue notifications using NotificationService for all channels (FCM, Email, SMS)
            try {
                $language = get_current_language_from_request();

                // Prepare context data for notification templates
                // Include logo for email templates
                $notificationContext = [
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'query_subject' => $subject,
                    'query_message' => $message,
                    'include_logo' => true, // Include logo in email templates
                ];

                // Queue notifications to admin users (group_id = 1) via all channels
                queue_notification_service(
                    eventType: 'user_query_submitted',
                    recipients: [],
                    context: $notificationContext,
                    options: [
                        'user_groups' => [1], // Admin user group
                        'channels' => ['fcm', 'email', 'sms'], // All channels
                        'language' => $language,
                        'platforms' => ['admin_panel'] // Admin panel platform for FCM
                    ]
                );
                // log_message('info', '[USER_QUERY] Admin notification result: ' . json_encode($result));
            } catch (\Throwable $notificationError) {
                // Log error but don't fail the query submission
                log_message('error', '[USER_QUERY] Notification error trace: ' . $notificationError->getTraceAsString());
            }

            $response['error'] = false;
            $response['message'] = labels(QUERY_SEND_SUCCESSFULLY, "Query send successfully");
            $response['data'] = $admin_contact_query;
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - contact_us_api()');
            return $this->response->setJSON($response);
        }
    }
}
