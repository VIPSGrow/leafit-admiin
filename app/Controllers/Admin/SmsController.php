<?php

namespace App\Controllers\Admin;

use App\Models\Settings;
use App\Models\Sms_template_model;
use App\Models\Translated_sms_template_model;
use App\Models\Language_model;

class SmsController extends Admin
{
    protected $superadmin;
    protected $validation;
    private Settings $settingsModel;
    private Sms_template_model $smsTemplateModel;
    private Translated_sms_template_model $translatedSmsTemplateModel;

    public function __construct()
    {
        parent::__construct();
        $this->validation = \Config\Services::validation();
        $this->superadmin = $this->session->get('email');
        $this->settingsModel = new Settings();
        $this->smsTemplateModel = new Sms_template_model();
        $this->translatedSmsTemplateModel = new Translated_sms_template_model();
        helper('ResponceServices');
        helper('function');
    }

    public function sms_gateway_setting_index()
    {
        $row = $this->settingsModel->where('variable', 'sms_gateway_setting')->first();
        if (!empty($row)) {
            $settings = json_decode($row['value'], true);
            if (!empty($settings)) {
                $this->data = array_merge($this->data, $settings);
            }
        }
        // Alias for view: key '2factor' cannot be used as PHP variable name
        $this->data['twofactor'] = $this->data['2factor'] ?? [];
      	$this->data['combirds']  = $this->data['combirds'] ?? []; // <-- ADD THIS
        setPageInfo($this->data, labels('SMS Gateway settings', 'SMS Gateway settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'sms_gateways');
        return view('backend/admin/template', $this->data);
    }

    public function sms_gateway_setting_update()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if (isset($result['error']) && $result['error']) {
            return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
        }
        $smsgateway_data = array();
        // Read requested statuses from POST (unchecked checkboxes are not submitted)
        $twilio_requested = isset($_POST['twilio_status']) ? '1' : '0';
        $fast2sms_requested = isset($_POST['fast2sms_status']) ? '1' : '0';
        $msg91_requested = isset($_POST['msg91_status']) ? '1' : '0';
        $twofactor_requested = isset($_POST['2factor_status']) ? '1' : '0';
        $combides_requested  = isset($_POST['combirds_status']) ? '1' : '0';

        // Enforce single active gateway: only one can be active at a time.
        // Priority: twilio > fast2sms > msg91 > 2factor (first requested wins)
        $active_gateway = '';
        if ($twilio_requested === '1') {
            $active_gateway = 'twilio';
        } elseif ($fast2sms_requested === '1') {
            $active_gateway = 'fast2sms';
        } elseif ($msg91_requested === '1') {
            $active_gateway = 'msg91';
        } elseif ($twofactor_requested === '1') {
            $active_gateway = '2factor';
        }elseif ($combides_requested === '1') { // <-- ADD THIS
            $active_gateway = 'combirds';
        }

        // Twilio: account SID + auth token + from numbers
        $smsgateway_data['twilio']['twilio_status'] = ($active_gateway === 'twilio') ? '1' : '0';
        $smsgateway_data['twilio']['twilio_account_sid'] = isset($_POST['twilio_account_sid']) ? $_POST['twilio_account_sid'] : '';
        $smsgateway_data['twilio']['twilio_auth_token'] = isset($_POST['twilio_auth_token']) ? $_POST['twilio_auth_token'] : '';
        $smsgateway_data['twilio']['twilio_from'] = isset($_POST['twilio_from']) ? $_POST['twilio_from'] : '';
        // Fast2SMS: authorization key + sender ID
        $smsgateway_data['fast2sms']['fast2sms_status'] = ($active_gateway === 'fast2sms') ? '1' : '0';
        $smsgateway_data['fast2sms']['fast2sms_api_key'] = isset($_POST['fast2sms_api_key']) ? $_POST['fast2sms_api_key'] : '';
        $smsgateway_data['fast2sms']['fast2sms_sender_id'] = isset($_POST['fast2sms_sender_id']) ? $_POST['fast2sms_sender_id'] : '';
        // MSG91: authkey only
        $smsgateway_data['msg91']['msg91_status'] = ($active_gateway === 'msg91') ? '1' : '0';
        $smsgateway_data['msg91']['msg91_authkey'] = isset($_POST['msg91_authkey']) ? $_POST['msg91_authkey'] : '';
        // 2Factor: API key + Sender ID (raw message, no template ID)
        $smsgateway_data['2factor']['2factor_status'] = ($active_gateway === '2factor') ? '1' : '0';
        $smsgateway_data['2factor']['2factor_api_key'] = isset($_POST['2factor_api_key']) ? $_POST['2factor_api_key'] : '';
        $smsgateway_data['2factor']['2factor_sender_id'] = isset($_POST['2factor_sender_id']) ? $_POST['2factor_sender_id'] : '';
        // Combides: API key + Sender ID (or relevant fields)
        $smsgateway_data['combirds']['combirds_status']    = ($active_gateway === 'combirds') ? '1' : '0';
        $smsgateway_data['combirds']['combirds_api_key']   = $_POST['combirds_api_key'] ?? '';
      	$smsgateway_data['combirds']['combirds_t_api_key']   = $_POST['combirds_t_api_key'] ?? '';
        $smsgateway_data['combirds']['combirds_sender_id'] = $_POST['combirds_sender_id'] ?? '';

        $smsgateway_data['current_sms_gateway'] = $active_gateway;
        $smsgateway_data = json_encode($smsgateway_data);

        if ($this->settingsModel->upsert('sms_gateway_setting', $smsgateway_data)) {
            return JsonSuccess(labels('SMS Gateway settings has been successfully updated', 'SMS Gateway settings has been successfully updated'));
        }
        return JsonError(labels('Unable to update the SMS Gateway settings', 'Unable to update the SMS Gateway settings'));
    }

    public function sms_templates()
    {
        try {
            $validationRules = [
                'title' => ["rules" => 'required', "errors" => ["required" => "Please enter Title"]],
                'type' => ["rules" => 'required', "errors" => ["required" => "Please select type"]],
                'template' => ["rules" => 'required', "errors" => ["required" => "Please select Template"]],
            ];
            if (!$this->validate($validationRules)) {
                $errors = $this->validator->getErrors();
                return JsonError($errors);
            }
            $updatedData = $this->request->getPost('template');
            $type = $this->request->getPost('type');
            $title = $this->request->getPost('title');
            $template = htmlspecialchars($updatedData);
            $parameters = extractVariables($updatedData);
            $data = [
                'type' => $type,
                'title' => $title,
                'template' => $template,
                'parameters' => json_encode($parameters),
            ];
            $insert = $this->smsTemplateModel->insert($data);
            if ($insert) {
                return JsonSuccess(labels('Template Saved successfully!', 'Template Saved successfully!'));
            }
            return JsonError(labels('error_occured', "An error occured"));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/SmsController.php - sms_templates()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function sms_template_list()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('sms_templates');
        $multipleWhere = [];
        $condition = $bulkData = $rows = $tempRow = [];
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
        $sort = ($_GET['sort'] ?? '') == 'id' ? 'id' : ($_GET['sort'] ?? 'id');
        $order = $_GET['order'] ?? 'DESC';
        $offset = $_GET['offset'] ?? '0';
        if (!empty($search)) {
            $multipleWhere = [
                'id' => $search,
                'type' => $search,
            ];
        }
        if (!empty($multipleWhere)) {
            $builder->groupStart()->orLike($multipleWhere)->groupEnd();
        }
        $total = $builder->countAllResults(false);
        $template_record = $builder->select('*')
            ->orderBy($sort, $order)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
        foreach ($template_record as $row) {
            $operations = '<div class="dropdown">
                    <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <button class="btn btn-secondary btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
            $operations .= '<a class="dropdown-item" href="' . base_url('/admin/settings/edit_sms_template/' . $row['id']) . '"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit_sms_template', 'Edit SMS Template') . '</a>';
            $operations .= '</div></div>';
            $tempRow['id'] = $row['id'];
            $tempRow['type'] = $row['type'];
            $tempRow['title'] = $row['title'];
            $tempRow['template'] = $row['template'];
            $tempRow['parameters'] = substr($row['parameters'], 0, 30) . '...';
            $tempRow['truncatedtemplate'] = substr($row['template'], 0, 30) . '...';
            $tempRow['operations'] = $operations;
            $rows[] = $tempRow;
        }
        $bulkData['total'] = $total;
        $bulkData['rows'] = $rows;
        return json_encode($bulkData);
    }

    public function edit_sms_template()
    {
        $uri = service('uri');
        $template_id = $uri->getSegments()[3];
        $languageModel = new Language_model();

        $template = $this->smsTemplateModel->getTemplateById($template_id);
        if (empty($template)) {
            $template = $this->smsTemplateModel->first();
        }

        $languages = $languageModel->orderBy('is_default', 'DESC')->findAll();

        $translations = [];
        if (!empty($template['id'])) {
            $translationResults = $this->translatedSmsTemplateModel->getTemplateTranslations($template['id']);
            foreach ($translationResults as $translation) {
                $translations[$translation['language_code']] = $translation;
            }
        }

        $parameters = $this->normalizeTemplateParameters($template['parameters'] ?? '');
        $parameterLabels = $this->getParameterLabels();
        $typeLabel = $this->getTypeLabel($template['type'] ?? '');

        // Get gateway_template_ids from default language translation, else base table fallback
        $gatewayTemplateIds = [];
        $db = \Config\Database::connect();
        $defaultLangRow = $db->table('languages')->where('is_default', 1)->get()->getRow();
        $defaultLangCode = $defaultLangRow ? $defaultLangRow->code : 'en';
        $defaultTranslation = !empty($template['id'])
            ? $this->translatedSmsTemplateModel->getTranslatedTemplate($template['id'], $defaultLangCode)
            : null;
        $raw = $defaultTranslation['gateway_template_ids'] ?? $template['gateway_template_ids'] ?? null;
        if (!empty($raw)) {
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $gatewayTemplateIds = is_array($decoded) ? $decoded : [];
        }

        $this->data['template'] = $template;
        $this->data['gateway_template_ids'] = $gatewayTemplateIds;
        $this->data['template_based_gateways'] = config('Sms')->templateBasedGateways ?? ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91', 'combirds' => 'Combirds'];
        $this->data['languages'] = $languages;
        $this->data['translations'] = $translations;
        $this->data['parameters'] = $parameters;
        $this->data['parameterLabels'] = $parameterLabels;
        $this->data['typeLabel'] = $typeLabel;
        setPageInfo($this->data, labels('SMS Templates', 'SMS Templates') . ' | ' . labels('admin_panel', 'Admin Panel'), 'edit_sms_template');
        return view('backend/admin/template', $this->data);
    }

    public function edit_sms_template_update()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                return JsonError(message: $result['message'], extra: ['redirect_url' => base_url('admin/settings/sms-gateways')]);
            }

            $validationMessages = $this->smsTemplateModel->getTranslatedValidationMessages();
            $validationRules = [
                'type' => ["rules" => 'required', "errors" => ["required" => $validationMessages['type']['required']]],
                'template_id' => ["rules" => 'required', "errors" => ["required" => 'Template ID is required']],
            ];

            if (!$this->validate($validationRules)) {
                $errors = $this->validator->getErrors();
                return JsonError($errors);
            }

            $translations = $this->request->getPost('translations');
            $db = \Config\Database::connect();
            $default_language = $db->table('languages')->where('is_default', 1)->get()->getRow();
            $default_lang_code = $default_language ? $default_language->code : 'en';

            if (empty($translations)) {
                $errors = ['translations' => labels('translations_required', 'Translations are required')];
                return JsonError($errors);
            }

            if (!isset($translations[$default_lang_code])) {
                $errors = ['default_language' => labels('default_language_translations_missing', 'Default language translations are required')];
                return JsonError($errors);
            }

            $default_translation = $translations[$default_lang_code];
            if (empty($default_translation['title'])) {
                $errors = ['default_language_title' => labels('default_language_title_required', 'Default language title is required')];
                return JsonError($errors);
            }

            if (empty($default_translation['template'])) {
                $errors = ['default_language_template' => labels('default_language_template_required', 'Default language template is required')];
                return JsonError($errors);
            }

            $id = $this->request->getPost('template_id');
            $type = $this->request->getPost('type');

            $default_title = $default_translation['title'];
            $default_template = $default_translation['template'];

            // Build gateway_template_ids from POST (stored in sms_templates base table)
            $gatewayTemplateIds = [];
            $allowedGateways = array_keys(config('Sms')->templateBasedGateways ?? ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91',
            'combirds' => 'Combirds'
            ]);
            $postedIds = $this->request->getPost('gateway_template_ids');
            if (is_array($postedIds)) {
                foreach ($postedIds as $key => $value) {
                    $key = trim((string) $key);
                    $value = trim((string) $value);
                    if ($key !== '' && $value !== '' && in_array($key, $allowedGateways, true)) {
                        $gatewayTemplateIds[$key] = $value;
                    }
                }
            }

            $template_escaped = htmlspecialchars($default_template);
            $parameters = extractVariables($default_template);
            $data = [
                'type' => $type,
                'title' => $default_title,
                'template' => $template_escaped,
                'parameters' => json_encode($parameters),
                'gateway_template_ids' => empty($gatewayTemplateIds) ? null : json_encode($gatewayTemplateIds),
            ];
            $update = $this->smsTemplateModel->updateTemplate($id, $data);

            if ($update && !empty($translations)) {
                foreach ($translations as $lang_code => $translation_data) {
                    if (empty($translation_data['title']) && empty($translation_data['template'])) {
                        continue;
                    }
                    $translation_data_to_save = [
                        'title' => $translation_data['title'] ?? '',
                        'template' => htmlspecialchars($translation_data['template'] ?? ''),
                    ];
                    $this->translatedSmsTemplateModel->saveTranslation($id, $lang_code, $translation_data_to_save);
                }
            }

            if ($update) {
                return JsonSuccess(message: labels('template_updated_successfully', 'Template updated successfully'), extra: ['redirect_url' => base_url('admin/settings/sms-gateways')]);
            }
            return JsonError(message: labels('error_occured', 'An Error occurred'), extra: ['redirect_url' => base_url('admin/settings/sms-gateways')]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/SmsController.php - edit_sms_template_update()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    private function getParameterLabels(): array
    {
        return [
            'user_id' => labels('user_id', 'User ID'),
            'user_name' => labels('user_name', 'User Name'),
            'provider_name' => labels('provider_name', 'Provider Name'),
            'provider_id' => labels('provider_id', 'Provider ID'),
            'company_name' => labels('company_name', 'Company Name'),
            'site_url' => labels('site_url', 'Site URL'),
            'company_contact_info' => labels('company_contact_info', 'Company Contact Info'),
            'company_logo' => labels('company_logo', 'Company Logo'),
            'booking_id' => labels('booking_id', 'Booking ID'),
            'booking_date' => labels('booking_date', 'Booking Date'),
            'booking_time' => labels('booking_time', 'Booking Time'),
            'booking_service_names' => labels('booking_service_names', 'Booking Service Names'),
            'booking_address' => labels('booking_address', 'Booking Address'),
            'booking_status' => labels('status', 'Status'),
            'amount' => labels('amount', 'Amount'),
            'currency' => labels('currency', 'Currency'),
            'service_id' => labels('service_id', 'Service ID'),
            'service_name' => labels('service_name', 'Service Name'),
        ];
    }

    private function getTypeLabel(string $type): string
    {
        $typeLabels = [
            'provider_approved' => labels('provider_approved', 'Provider Approved'),
            'provider_disapproved' => labels('provider_disapproved', 'Provider Disapproved'),
            'withdraw_request_approved' => labels('approved_withdraw_request', 'Approved Withdrawal Request'),
            'withdraw_request_disapproved' => labels('disapproved_withdraw_request', 'Disapproved Withdrawal Request'),
            'payment_settlement' => labels('payment_settled', 'Payment Settled'),
            'service_approved' => labels('service_approved', 'Service Approved'),
            'service_disapproved' => labels('service_disapproved', 'Service Disapproved'),
            'user_account_active' => labels('user_account_activated', 'User Account Activated'),
            'user_account_deactive' => labels('user_account_deactivated', 'User Account Deactivated'),
            'provider_update_information' => labels('provider_information_updated', 'Provider Information Updated'),
            'new_provider_registerd' => labels('new_provider_registered', 'New Provider Registered'),
            'withdraw_request_received' => labels('withdrawal_request_received', 'Withdrawal Request Received'),
            'cash_collection_by_provider' => labels('cash_collection_by_provider', 'Cash Collection by Provider'),
            'booking_status_updated' => labels('booking_status_updated', 'Booking Status Updated'),
            'new_booking_confirmation_to_customer' => labels('new_booking_confirmation_to_customer', 'New Booking Confirmation to Customer'),
            'new_booking_received_for_provider' => labels('new_booking_received_for_provider', 'New Booking Received for Provider'),
            'new_rating_given_by_customer' => labels('new_rating_given_by_customer', 'New Rating Given by Customer'),
            'rating_request_to_customer' => labels('rating_request_to_customer', 'Rating Request to Customer'),
            'user_query_submitted' => labels('user_query_submitted', 'User Query Submitted'),
            'new_message' => labels('new_message', 'New Message'),
            'user_blocked' => labels('user_blocked', 'User Blocked'),
            'promo_code_added' => labels('promo_code_added', 'Promo Code Added'),
            'new_category_available' => labels('new_category_available', 'New Category Available'),
            'category_removed' => labels('category_removed', 'Category Removed'),
            'subscription_changed' => labels('subscription_changed', 'Subscription Changed'),
            'subscription_removed' => labels('subscription_removed', 'Subscription Removed'),
            'privacy_policy_changed' => labels('privacy_policy_changed', 'Privacy Policy Changed'),
            'terms_and_conditions_changed' => labels('terms_and_conditions_changed', 'Terms and Conditions Changed'),
            'maintenance_mode' => labels('maintenance_mode', 'Maintenance Mode'),
            'new_blog' => labels('new_blog', 'New Blog'),
            'subscription_payment_successful' => labels('subscription_payment_successful', 'Subscription Payment Successful'),
            'subscription_payment_failed' => labels('subscription_payment_failed', 'Subscription Payment Failed'),
            'subscription_payment_pending' => labels('subscription_payment_pending', 'Subscription Payment Pending'),
            'subscription_purchased' => labels('subscription_purchased', 'Subscription Purchased'),
            'payment_refund_executed' => labels('payment_refund_executed', 'Payment Refund Executed'),
            'booking_confirmed' => labels('booking_confirmed', 'Booking Confirmed'),
            'booking_rescheduled' => labels('booking_rescheduled', 'Booking Rescheduled'),
            'booking_cancelled' => labels('booking_cancelled', 'Booking Cancelled'),
            'booking_completed' => labels('booking_completed', 'Booking Completed'),
            'booking_started' => labels('booking_started', 'Booking Started'),
            'booking_ended' => labels('booking_ended', 'Booking Ended'),
            'online_payment_success' => labels('online_payment_success', 'Online Payment Success'),
            'online_payment_failed' => labels('online_payment_failed', 'Online Payment Failed'),
            'online_payment_pending' => labels('online_payment_pending', 'Online Payment Pending'),
            'bid_on_custom_job_request' => labels('bid_on_custom_job_request', 'Bid on Custom Job Request'),
            'added_additional_charges' => labels('added_additional_charges', 'Added Additional Charges'),
            'new_user_registered' => labels('new_user_registered', 'New User Registered'),
            'new_custom_job_request' => labels('new_custom_job_request', 'New Custom Job Request'),
            'payment_refund_successful' => labels('payment_refund_successful', 'Payment Refund Successful'),
            'subscription_expired' => labels('subscription_expired', 'Subscription Expired'),
            'provider_edits_service_details' => labels('provider_edits_service_details', 'Provider Edits Service Details'),
        ];

        return $typeLabels[$type] ?? $type;
    }

    private function normalizeTemplateParameters($parameters)
    {
        if (is_array($parameters)) {
            return $parameters;
        }
        $parameters = trim(html_entity_decode($parameters));
        if (empty($parameters)) {
            return [];
        }
        $parameters = preg_replace('/\\\\+"/', '"', $parameters);
        $decoded = json_decode($parameters, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $cleaned = str_replace(["'", '\\\\"'], ['"', '"'], $parameters);
            $decoded = json_decode($cleaned, true);
        }
        if (!is_array($decoded)) {
            if (preg_match('/\[(.*?)\]/', $parameters, $matches)) {
                $items = explode(',', $matches[1]);
                $decoded = array_map(function ($v) {
                    return trim(str_replace(['"', "'"], '', $v));
                }, $items);
            } else {
                $decoded = [];
            }
        }
        return $decoded;
    }
}