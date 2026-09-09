<?php

namespace App\Controllers\Admin;

use App\Models\NotificationTemplateModel;
use App\Models\Settings;

class NotificationSettings extends Admin
{
    public $validation, $notificationTemplate, $db;
    protected $superadmin;
    private Settings $settingsModel;

    public function __construct()
    {
        parent::__construct();
        helper(['form', 'url', 'ResponceServices']);
        $this->notificationTemplate = new NotificationTemplateModel();
        $this->settingsModel = new Settings();
        $this->validation = \Config\Services::validation();
        $this->db = \Config\Database::connect();
        $this->superadmin = $this->session->get('email');
    }

    public function notificationTemplates()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('notification_templates', 'Notification Templates') . ' | ' . labels('admin_panel', 'Admin Panel'), 'notification_templates');
        return view('backend/admin/template', $this->data);
    }

    /**
     * Get notification templates list for Bootstrap Table
     * 
     * Handles server-side pagination for Bootstrap Table
     * Receives limit, offset, sort, and order parameters from Bootstrap Table
     * Returns paginated results in Bootstrap Table format
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response with templates data
     */
    public function notificationTemplatesList()
    {
        // Get Bootstrap Table pagination parameters from GET request
        // Bootstrap Table sends these parameters when using server-side pagination
        $request = \Config\Services::request();

        // Get limit (number of records per page) - default to 10 if not provided
        $limit = (int) ($request->getGet('limit') ?: 10);

        // Get offset (number of records to skip) - default to 0 if not provided
        $offset = (int) ($request->getGet('offset') ?: 0);

        // Get sort column name - default to 'id' if not provided
        $sort = $request->getGet('sort') ?: 'id';

        // Get sort order (asc or desc) - default to 'desc' if not provided
        $order = $request->getGet('order') ?: 'desc';

        // Validate parameters to ensure they are safe
        // Limit must be at least 1, offset must be non-negative
        if ($limit < 1) {
            $limit = 10;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        // Call model method with pagination parameters
        // Model will handle the actual database query with limit and offset
        $templates = $this->notificationTemplate->getNotificationTemplates($limit, $offset, $sort, $order);

        // Return JSON response in Bootstrap Table format
        // Response contains 'total' (total count) and 'rows' (paginated data)
        return $this->response->setJSON($templates);
    }

    /**
     * Edit notification template page
     * 
     * Loads the edit view for a specific notification template
     * Fetches template data and prepares it for the edit form
     * 
     * Gets template ID from URL segments (following same pattern as email templates)
     * 
     * @return mixed View response or JSON error response
     */
    public function editNotificationTemplate()
    {
        // Check if user is logged in and is admin
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        helper('function');

        // Get template ID from URL segments
        // Following the same pattern as edit_email_template method
        // Route: admin/settings/edit-notification-template/(:any)
        // Segments: [0] = admin, [1] = settings, [2] = edit-notification-template, [3] = id
        $uri = service('uri');
        $id = $uri->getSegments()[3] ?? null;

        // Validate that ID was provided
        if (empty($id)) {
            return redirect('admin/settings/notification-templates');
        }

        // Get template data by ID
        $template = $this->notificationTemplate->getNotificationTemplate($id);

        // If template not found, return error response instead of using session variables
        if (empty($template)) {
            // Check if request is AJAX/JSON request
            if ($this->request->isAJAX() || $this->request->getHeaderLine('Accept') === 'application/json') {
                // Return JSON error response for AJAX requests
                return ErrorResponse(labels('template_not_found', 'Template not found'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            // For regular page requests, redirect without using session variables
            // Error handling can be done via query parameter or frontend handling
            return redirect('admin/settings/notification-templates');
        }



        // Load language model for multi-language support (if needed in future)
        $languageModel = new \App\Models\Language_model();
        $languages = $languageModel->orderBy('is_default', 'DESC')->findAll();

        // Load existing translations for this template and organize by language code
        $translations = [];
        $translatedModel = new \App\Models\TranslatedNotificationTemplateModel();
        $translationResults = $translatedModel->getTemplateTranslations((int) $id);
        foreach ($translationResults as $translation) {
            $translations[$translation['language_code']] = $translation;
        }

        // Parse parameters from database (stored as JSON)
        // Parameters field contains JSON array of variable names like ["user_name", "booking_id"]
        $parameters = [];
        if (!empty($template['parameters'])) {

            // Decode JSON parameters
            $decodedParams = json_decode($template['parameters']);
            // If decoding successful and it's an array, use it
            if (is_array($decodedParams)) {
                $parameters = $decodedParams;
            }
        }

        // Create parameter mapping for user-friendly labels
        // Maps parameter keys (like "user_name") to human-readable labels
        $parameterLabels = $this->getParameterLabels();

        // Pass data to view
        $this->data['template'] = $template;
        $this->data['languages'] = $languages;
        $this->data['translations'] = $translations;
        $this->data['parameters'] = $parameters;
        $this->data['parameterLabels'] = $parameterLabels;


        // Set page info and load view
        setPageInfo($this->data, labels('edit_notification_template', 'Edit Notification Template') . ' | ' . labels('admin_panel', 'Admin Panel'), 'edit_notification_template');
        return view('backend/admin/template', $this->data);
    }

    /**
     * Save notification template translations (base table is read-only)
     *
     * Accepts title[lang] and body[lang] arrays with template_id.
     * Form structure: title[en], body[en], title[hi], body[hi], etc.
     * Writes only to translated_notification_templates via model methods.
     */
    public function editNotificationTemplateOperation()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }

            // Validate required fields
            $validationRules = [
                'template_id' => [
                    'rules' => 'required|integer',
                    'errors' => [
                        'required' => labels('template_id_is_required', 'Template ID is required'),
                        'integer' => labels('template_id_must_be_an_integer', 'Template ID must be an integer'),
                    ],
                ],
            ];

            if (!$this->validate($validationRules)) {
                $errors = $this->validator->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            $templateId = (int) $this->request->getPost('template_id');

            // Get title and body arrays from form (structure: title[en], body[en], etc.)
            $titles = (array) $this->request->getPost('title');
            $bodies = (array) $this->request->getPost('body');

            // Load default language code
            $db = \Config\Database::connect();
            $defaultLanguage = $db->table('languages')->where('is_default', 1)->get()->getRow();
            $defaultLangCode = $defaultLanguage ? $defaultLanguage->code : 'en';

            // Validate that we have at least some translations
            if (empty($titles) && empty($bodies)) {
                return ErrorResponse([
                    'translations' => labels('translations_required', 'Translations are required')
                ], true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Ensure default language has values
            $defaultTitle = trim((string) ($titles[$defaultLangCode] ?? ''));
            $defaultBody = trim((string) ($bodies[$defaultLangCode] ?? ''));

            if (empty($defaultTitle) || empty($defaultBody)) {
                return ErrorResponse([
                    'default_language' => labels('default_language_translations_missing', 'Default language title and body are required')
                ], true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get all unique language codes from both arrays
            $allLanguageCodes = array_unique(array_merge(array_keys($titles), array_keys($bodies)));

            // Save translations only (do not modify base table)
            $translatedModel = new \App\Models\TranslatedNotificationTemplateModel();

            foreach ($allLanguageCodes as $langCode) {
                $title = trim((string) ($titles[$langCode] ?? ''));
                $body = trim((string) ($bodies[$langCode] ?? ''));

                // Skip if both fields are empty
                if (empty($title) && empty($body)) {
                    continue;
                }

                // Save translation for this language
                $translatedModel->saveTranslation(
                    $templateId,
                    (string) $langCode,
                    [
                        'title' => $title,
                        'body' => $body,
                    ]
                );
            }

            $response = [
                'error' => false,
                'message' => labels('template_updated_successfully', 'Template updated successfully!'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'reload' => true,
                'data' => [],
            ];
            return json_encode($response);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/NotificationSettings.php - editNotificationTemplateOperation()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Load the notification settings page
     * 
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    private function getNotificationSettingKeys(): array
    {
        return [
            'provider_approved',
            'provider_disapproved',
            'new_provider_registerd',
            'provider_update_information',
            'provider_edits_service_details',
            'withdraw_request_approved',
            'withdraw_request_disapproved',
            'withdraw_request_received',
            'payment_settlement',
            'cash_collection_by_provider',
            'payment_refund_executed',
            'payment_refund_successful',
            'service_approved',
            'service_disapproved',
            'user_account_active',
            'user_account_deactive',
            'new_user_registered',
            'booking_confirmed',
            'booking_rescheduled',
            'booking_cancelled',
            'booking_completed',
            'booking_started',
            'booking_ended',
            'new_booking_confirmation_to_customer',
            'new_booking_received_for_provider',
            'booking_assigned',
            'booking_unassigned',
            'booking_on_the_way',
            'booking_arrived',
            'new_rating_given_by_customer',
            'rating_request_to_customer',
            'user_query_submitted',
            'user_blocked',
            'promo_code_added',
            'new_category_available',
            'category_removed',
            'subscription_changed',
            'subscription_removed',
            'subscription_purchased',
            'subscription_expired',
            'subscription_payment_successful',
            'subscription_payment_failed',
            'subscription_payment_pending',
            'online_payment_success',
            'online_payment_failed',
            'online_payment_pending',
            'bid_on_custom_job_request',
            'new_custom_job_request',
            'added_additional_charges',
            'privacy_policy_changed',
            'terms_and_conditions_changed',
            'maintenance_mode',
            'new_blog',
        ];
    }

    private function getNotificationCategories(): array
    {
        return [
            'Provider Management'      => ['provider_approved', 'provider_disapproved', 'new_provider_registerd', 'provider_update_information', 'provider_edits_service_details'],
            'Withdrawals & Payments'   => ['withdraw_request_approved', 'withdraw_request_disapproved', 'withdraw_request_received', 'payment_settlement', 'cash_collection_by_provider', 'payment_refund_executed', 'payment_refund_successful'],
            'Service Management'       => ['service_approved', 'service_disapproved'],
            'User Accounts'            => ['user_account_active', 'user_account_deactive', 'new_user_registered', 'user_blocked'],
            'Bookings'                 => ['booking_confirmed', 'booking_rescheduled', 'booking_cancelled', 'booking_completed', 'booking_started', 'booking_ended', 'new_booking_confirmation_to_customer', 'new_booking_received_for_provider', 'booking_assigned', 'booking_unassigned', 'booking_on_the_way', 'booking_arrived'],
            'Ratings & Reviews'        => ['new_rating_given_by_customer', 'rating_request_to_customer'],
            'Communication'            => ['user_query_submitted'],
            'Promotions'               => ['promo_code_added', 'new_category_available', 'category_removed'],
            'Subscriptions'            => ['subscription_changed', 'subscription_removed', 'subscription_purchased', 'subscription_expired', 'subscription_payment_successful', 'subscription_payment_failed', 'subscription_payment_pending'],
            'Payment Gateway'          => ['online_payment_success', 'online_payment_failed', 'online_payment_pending'],
            'Custom Job Requests'      => ['bid_on_custom_job_request', 'new_custom_job_request', 'added_additional_charges'],
            'System & Policy'          => ['privacy_policy_changed', 'terms_and_conditions_changed', 'maintenance_mode'],
            'Content'                  => ['new_blog'],
        ];
    }

    public function notification_settings()
    {
        if (!$this->isLoggedIn) {
            return redirect('unauthorised');
        }
        $row = $this->settingsModel->where('variable', 'notification_settings')->first();
        $current_settings = json_decode($row['value'] ?? '{}', true) ?: [];

        $notification_settings = $this->getNotificationSettingKeys();
        $notification_categories = $this->getNotificationCategories();

        // Apply default '1' to any settings not yet present in the DB
        foreach ($notification_settings as $setting) {
            foreach (['email', 'sms', 'notification'] as $channel) {
                $key = $setting . '_' . $channel;
                if (!isset($current_settings[$key])) {
                    $current_settings[$key] = '1';
                }
            }
        }

        $this->data['notification_settings'] = $notification_settings;
        $this->data['notification_categories'] = $notification_categories;
        $this->data['current_settings'] = $current_settings;
        setPageInfo($this->data, labels('Notification settings', 'Notification settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'notification_settings');
        return view('backend/admin/template', $this->data);
    }

    public function notification_setting_update()
    {
        try {
            if ($this->superadmin == "superadmin@gmail.com") {
                defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
            } else {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
                }
            }
            $postedData = $this->request->getPost();

            // Unchecked switches send no field at all — write every key
            // explicitly ('1'/'0') so an off toggle is actually persisted
            // instead of being dropped and re-defaulting to checked.
            $updatedData = [];
            foreach ($this->getNotificationSettingKeys() as $setting) {
                foreach (['email', 'sms', 'notification'] as $channel) {
                    $key = $setting . '_' . $channel;
                    $updatedData[$key] = isset($postedData[$key]) ? '1' : '0';
                }
            }

            $json_string = json_encode($updatedData);
            if ($this->settingsModel->upsert('notification_settings', $json_string)) {
                return JsonSuccess(labels('Notification settings has been successfully updated', 'Notification settings has been successfully updated'));
            } else {
                return JsonError(labels('Unable to update the Notification settings', 'Unable to update the Notification settings'));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/NotificationSettings.php - notification_setting_update()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    private function getParameterLabels(): array
    {
        // Map parameter keys to their labels
        // Keys should match the variable names used in templates (e.g., [[user_name]])
        return [
            // User-related parameters
            'user_id' => labels('user_id', 'User ID'),
            'user_name' => labels('user_name', 'User Name'),

            // Provider/Partner parameters
            'provider_name' => labels('provider_name', 'Provider Name'),
            'provider_id' => labels('provider_id', 'Provider ID'),
            'company_name' => labels('company_name', 'Company Name'),

            // Site/Company parameters
            'site_url' => labels('site_url', 'Site URL'),
            'company_contact_info' => labels('company_contact_info', 'Company Contact Info'),
            'company_logo' => labels('company_logo', 'Company Logo'),

            // Booking-related parameters
            'booking_id' => labels('booking_id', 'Booking ID'),
            'booking_date' => labels('booking_date', 'Booking Date'),
            'booking_time' => labels('booking_time', 'Booking Time'),
            'booking_service_names' => labels('booking_service_names', 'Booking Service Names'),
            'booking_address' => labels('booking_address', 'Booking Address'),
            'booking_status' => labels('status', 'Status'),

            // Payment parameters
            'amount' => labels('amount', 'Amount'),
            'currency' => labels('currency', 'Currency'),

            // Service parameters
            'service_id' => labels('service_id', 'Service ID'),
            'service_name' => labels('service_name', 'Service Name'),
        ];
    }
}
