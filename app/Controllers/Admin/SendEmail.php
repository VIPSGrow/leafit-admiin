<?php

namespace App\Controllers\Admin;

use App\Models\Email_model;

class SendEmail extends Admin
{
    public   $validation, $faqs, $creator_id;
    protected $superadmin;
    protected Email_model $email;
    public function __construct()
    {
        parent::__construct();
        helper(['form', 'url']);
        $this->email = new Email_model();
        $this->validation = \Config\Services::validation();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
    }
    public function index()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $emailPermission = is_permitted($this->creator_id, 'read', 'email_notifications');
            if (!$emailPermission) {
                // Keep the email console hidden unless the role explicitly has read access.
                return NoPermission();
            }
            $this->data['users'] = fetch_details('users', [], ['id', 'username']);

            // Get current language and default language for translation
            $currentLanguage = get_current_language();
            $defaultLanguage = get_default_language();

            // Fetch partner data with translated company names based on current language
            $db = \Config\Database::connect();
            $partners = $db->table('users u')
                ->select('u.id as partner_id,pd.company_name,tpd.company_name as translated_company_name,tdpd.company_name as default_translated_company_name')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->join('translated_partner_details tpd', 'tpd.partner_id = pd.partner_id AND tpd.language_code = "' . $currentLanguage . '"', 'left')
                ->join('translated_partner_details tdpd', 'tdpd.partner_id = pd.partner_id AND tdpd.language_code = "' . $defaultLanguage . '"', 'left')
                ->where('pd.is_approved', '1')
                ->get()->getResultArray();

            // Process partner data to use translated company names with proper fallback logic
            foreach ($partners as &$partner) {
                // Fallback hierarchy: requested language -> default language -> main table
                if (!empty($partner['translated_company_name'])) {
                    // Use requested language translation if available and not empty
                    $partner['company_name'] = $partner['translated_company_name'];
                } elseif (!empty($partner['default_translated_company_name'])) {
                    // Fall back to default language translation if requested language is not available
                    $partner['company_name'] = $partner['default_translated_company_name'];
                }
                // If both translations are empty, keep the original company_name from main table

                // Remove the translation fields as they're no longer needed
                unset($partner['translated_company_name']);
                unset($partner['default_translated_company_name']);
            }

            $this->data['partners'] = $partners;
            $db      = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', "2");
            if (isset($_GET['customer_filter']) && $_GET['customer_filter'] != '') {
                $builder->where('u.active',  $_GET['customer_filter']);
            }
            $customers = $builder->get()->getResultArray();
            $this->data['customers'] =   $customers;
            setPageInfo($this->data, labels('Send Email', 'Send Email') . ' | ' . labels('admin_panel', 'Admin Panel'), 'send_emails');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/SendEmail.php - index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function send_email()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $emailPermission = is_permitted($this->creator_id, 'create', 'email_notifications');
            if (!$emailPermission) {
                // Sending emails is treated as a create action to avoid unwanted blasts.
                return NoPermission();
            }
            // Validate input
            $rules = [
                'subject' => ['rules' => 'required|trim', 'errors' => ['required' => 'Please enter subject']],
                'template' => ['rules' => 'required|trim', 'errors' => ['required' => 'Please enter template content']]
            ];
            $this->validation->setRules($rules);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get form data
            $template = $this->request->getVar('template', FILTER_UNSAFE_RAW);
            $subject = $this->request->getPost('subject');
            $type = $this->request->getPost('email_user_type');
            $bcc = $this->request->getPost('bcc');
            $cc = $this->request->getPost('cc');

            // Process BCC and CC emails from form data
            $bcc_emails = [];
            $cc_emails = [];
            if (isset($_POST['bcc'][0]) && !empty($_POST['bcc'][0])) {
                $bcc_emails = $this->processBccEmails($_POST['bcc']);
            }
            if (isset($_POST['cc'][0]) && !empty($_POST['cc'][0])) {
                $cc_emails = $this->processCcEmails($_POST['cc']);
            }
            // Determine user IDs based on type
            $user_ids = [];
            if ($type == "provider") {
                $user_ids = $this->request->getPost('provider_id');
                if (!is_array($user_ids) || empty($user_ids)) {
                    return ErrorResponse(['provider_id' => labels('Please select provider', 'Please select provider')], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($type == "customer") {
                $user_ids = $this->request->getPost('customer_id');
                if (!is_array($user_ids) || empty($user_ids)) {
                    return ErrorResponse(['customer_id' => labels('Please select customer', 'Please select customer')], true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Ensure user IDs are integers
            if (!empty($user_ids)) {
                $user_ids = array_map('intval', $user_ids);
                $user_ids = array_filter($user_ids, function ($id) {
                    return $id > 0;
                });
                $user_ids = array_values($user_ids); // Re-index array
            }

            // Prepare options for NotificationService
            // Similar to how FCM notifications are sent from admin panel
            $options = [
                'channels' => ['email'], // Only send email notifications for admin custom emails
                'title' => $subject, // Subject is used as title
                'message' => $template, // Template content is used as message
                'bcc' => $bcc_emails, // BCC emails array
                'cc' => $cc_emails, // CC emails array
            ];

            // Add user IDs to options if we have them
            if (!empty($user_ids)) {
                $options['user_ids'] = $user_ids;
            } else {
                // If no specific user IDs, determine how to send based on type
                if ($type === 'customer') {
                    // Send to all customers (group_id = 2)
                    $options['user_groups'] = [2];
                } elseif ($type === 'provider') {
                    // Send to all providers (group_id = 3)
                    $options['user_groups'] = [3];
                } else if ($type === 'all_users') {
                    $options['user_groups'] = [2, 3];
                    $options['send_to_all'] = true;
                }
            }

            // Prepare context for NotificationService
            $context = [
                'email_type' => $type,
                'admin_email' => true, // Flag to indicate this is an admin custom email
            ];

            // Queue email notification using NotificationService
            // Use a generic event type for admin custom emails (similar to admin_custom_notification for FCM)
            $eventType = 'admin_custom_notification';
            queue_notification_service($eventType, [], $context, $options, 'high');

            // Save email data to database for history (similar to how it was done before)
            $email_data = [
                'content' => $template,
                'type' => $type,
                'bcc' => json_encode($bcc),
                'cc' => json_encode($cc),
                'subject' => $subject,
                'user_id' => json_encode($user_ids)
            ];
            insert_details($email_data, 'emails');

            // Return success response
            return successResponse("Emails queued successfully", false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage() . "\n" . $th->getTraceAsString());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    private function processBccEmails($bcc_data)
    {
        $bcc = [];
        if (!empty($bcc_data[0])) {
            $val = explode(',', str_replace([']', '['], '', $bcc_data[0]));
            foreach ($val as $s) {
                $email = json_decode($s, true);
                if (isset($email['value']) && filter_var($email['value'], FILTER_VALIDATE_EMAIL)) {
                    $bcc[] = $email['value'];
                }
            }
        }
        return $bcc;
    }
    private function processCcEmails($cc_data)
    {
        $cc = [];
        if (!empty($cc_data[0])) {
            $val = explode(',', str_replace([']', '['], '', $cc_data[0]));
            foreach ($val as $s) {
                $email = json_decode($s, true);
                if (isset($email['value']) && filter_var($email['value'], FILTER_VALIDATE_EMAIL)) {
                    $cc[] = $email['value'];
                }
            }
        }
        return $cc;
    }
    private function processEmailTemplate($template, $user, $settings)
    {
        $replacements = [
            '[[unsubscribe_link]]' => base_url('unsubscribe_link/' . unsubscribe_link_user_encrypt($user['id'], $user['email'])),
            '[[user_id]]' => $user['id'],
            '[[user_name]]' => $user['username'],
            '[[company_name]]' => getTranslatedSetting('general_settings', 'company_title'),
            '[[site_url]]' => base_url(),
            '[[company_contact_info]]' => getTranslatedSetting('contact_us', 'contact_us') ?? '',
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    private function processInlineImages($email, $template)
    {
        preg_match_all('/<img[^>]+src=["\'](.*?)["\'][^>]*>/i', $template, $matches);
        $imagePaths = $matches[1];
        foreach ($imagePaths as $imagePath) {
            if (file_exists($imagePath)) {
                $email->attach($imagePath);
                $cid = $email->setAttachmentCID(basename($imagePath));
                $template = str_replace($imagePath, "cid:$cid", $template);
            }
        }
        return $template;
    }
    public function list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $data = $this->email->list(false, $search, $limit, $offset, $sort, $order);
            return $data;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/SendEmail.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function delete_email()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $id = $this->request->getPost('id');
            $db      = \Config\Database::connect();
            $builder = $db->table('emails');
            if ($builder->delete(['id' => $id])) {
                return successResponse("Email deleted successfully", false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occured"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/SendEmail.php - delete_email()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function unsubscribe_link_view()
    {
        $uri = service('uri');
        $data = $uri->getSegments()[1];
        setPageInfo($this->data, labels('Unsubscribe Email', 'Unsubscribe Email') . ' | ' . labels('admin_panel', 'Admin Panel'), 'unsubscribe_email');
        return view('/backend/admin/pages/unsubscribe_email.php', $this->data);
    }
    public function unsubscription_email_operation()
    {
        try {
            $decrypted = unsubscribe_link_user_decrypt($_POST['data']);
            $user_id = $decrypted[0];
            $email = $decrypted[1];
            $user = fetch_details('users', ['id' => $user_id, 'email' => $email], ['id']);
            if (!empty($user)) {
                $update = update_details(['unsubscribe_email' => 1], ['id' => $user_id, 'email' => $email], 'users');
                if ($update) {
                    $successMessage = labels('You have successfully unsubscribed', 'You have successfully unsubscribed');
                    session()->setFlashdata('success', $successMessage);
                } else {
                    $errorMessage = labels('Failed to unsubscribe. Please try again.', 'Failed to unsubscribe. Please try again') . '.';
                    session()->setFlashdata('error', $errorMessage);
                }
            } else {
                $errorMessage = labels('Invalid user or email', 'Invalid user or email');
                session()->setFlashdata('error', $errorMessage);
            }
            return redirect()->back();
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/SendEmail.php - unsubscription_email_operation()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
