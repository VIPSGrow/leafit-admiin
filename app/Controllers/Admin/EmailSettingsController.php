<?php

namespace App\Controllers\Admin;

use App\Models\Email_template_model;
use App\Models\Translated_email_template_model;
use App\Models\Language_model;
use App\Models\Sms_template_model;
use App\Models\Translated_sms_template_model;
use App\Models\NotificationTemplateModel;
use App\Models\TranslatedNotificationTemplateModel;
use App\Models\Settings;

class EmailSettingsController extends Admin
{
    protected $superadmin;
    protected $validation;
    private Email_template_model $emailTemplateModel;
    private Translated_email_template_model $translatedEmailTemplateModel;
    private Language_model $languageModel;
    private Settings $settingsModel;

    public function __construct()
    {
        parent::__construct();
        $this->validation = \Config\Services::validation();
        $this->superadmin = $this->session->get('email');
        $this->emailTemplateModel = new Email_template_model();
        $this->translatedEmailTemplateModel = new Translated_email_template_model();
        $this->languageModel = new Language_model();
        $this->settingsModel = new Settings();
        helper('ResponceServices');
        helper('function');
    }

    public function email_settings()
    {
        try {
            if ($this->request->getPost('update')) {
                $result = checkModificationInDemoMode($this->superadmin);
                if (isset($result['error']) && $result['error']) {
                    return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
                }

                $this->validation->setRules([
                    'smtpHost' => ['rules' => 'required', 'errors' => ['required' => 'Please enter SMTP Host']],
                    'smtpUsername' => ['rules' => 'required', 'errors' => ['required' => 'Please enter SMTP Username']],
                    'smtpPassword' => ['rules' => 'required', 'errors' => ['required' => 'Please enter SMTP Password']],
                    'smtpPort' => ['rules' => 'required|numeric', 'errors' => ['required' => 'Please enter SMTP Port Number', 'numeric' => 'Please enter numeric value for SMTP Port Number']],
                ]);
                if (!$this->validation->withRequest($this->request)->run()) {
                    $errors = $this->validation->getErrors();
                    return JsonError(labels($errors, $errors));
                }
                $updatedData = $this->request->getPost();
                $json_string = json_encode($updatedData);
                if ($this->settingsModel->upsert('email_settings', $json_string)) {
                    return JsonSuccess(labels('Email settings has been successfuly updated', 'Email settings has been successfuly updated'));
                }

                return JsonError(labels('Unable to update the email settings', 'Unable to update the email settings'));
            }
            $row = $this->settingsModel->where('variable', 'email_settings')->first();
            if ($row) {
                $settings = json_decode($row['value'], true);
                $this->data = array_merge($this->data, $settings);
            }
            setPageInfo($this->data, labels('Email Settings', 'Email Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'email_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/EmailSettingsController.php - email_settings()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function email_template_configuration()
    {
        if (!$this->isLoggedIn && !$this->userIsPartner) {
            return redirect('unauthorised');
        }
        setPageInfo($this->data, labels('Email Configuration', 'Email Configuration') . '  | ' . labels('admin_panel', 'Admin Panel'), 'email_template_configuration');
        return view('backend/admin/template', $this->data);
    }

    public function email_template_configuration_update()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
            }

            $validationRules = [
                'subject' => ["rules" => 'required', "errors" => ["required" => "Please enter Subject"]],
                'email_type' => ["rules" => 'required', "errors" => ["required" => "Please select type"]],
                'template' => ["rules" => 'required', "errors" => ["required" => "Please select Template"]],
            ];
            if (!$this->validate($validationRules)) {
                $errors = $this->validator->getErrors();
                return JsonError($errors);
            }

            $updatedData = $this->request->getPost('template');
            $email_type = $this->request->getPost('email_type');
            $subject = $this->request->getPost('subject');
            $email_to = $this->request->getPost('email_to');
            $bcc = $this->request->getPost('bcc');
            $cc = $this->request->getPost('cc');
            $template = htmlspecialchars($updatedData);
            $parameters = extractVariables($updatedData);
            $data = [
                'type' => $email_type,
                'subject' => $subject,
                'to' => json_encode($email_to),
                'template' => $template,
                'bcc' => $bcc,
                'cc' => $cc,
                'parameters' => json_encode($parameters),
            ];
            $insert = $this->emailTemplateModel->insert($data);
            if ($insert) {
                return JsonSuccess(labels('Template Saved successfully!', 'Template Saved successfully!'));
            } else {
                return JsonError(labels('error_occured', "An Error occurred"));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/EmailSettingsController.php - email_template_configuration_update()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function email_template_list()
    {
        setPageInfo($this->data, labels('Email Templates', 'Email Templates') . ' | ' . labels('admin_panel', 'Admin Panel'), 'email_template_list');
        return view('backend/admin/template', $this->data);
    }

    public function email_template_list_fetch()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $where = [];
        $from_app = false;
        return $this->emailTemplateModel->list($from_app, $search, $limit, $offset, $sort, $order, $where);
    }

    public function edit_email_template()
    {
        $uri = service('uri');
        $template_id = $uri->getSegments()[3];

        $templates = $this->emailTemplateModel->find($template_id);

        $languages = $this->languageModel->orderBy('is_default', 'DESC')->findAll();

        $translations = [];
        if (!empty($template_id)) {
            $translationResults = $this->translatedEmailTemplateModel->getTemplateTranslations($template_id);
            foreach ($translationResults as $translation) {
                $translations[$translation['language_code']] = $translation;
            }
        }

        $parameters = $this->normalizeEmailTemplateParameters($templates['parameters'] ?? '');
        $parameterLabels = $this->getParameterLabels();
        $typeLabel = $this->getEmailTypeLabel($templates['type'] ?? '');

        $this->data['template'] = $templates;
        $this->data['languages'] = $languages;
        $this->data['translations'] = $translations;
        $this->data['parameters'] = $parameters;
        $this->data['parameterLabels'] = $parameterLabels;
        $this->data['typeLabel'] = $typeLabel;

        setPageInfo($this->data, labels('Email Templates', 'Email Templates') . ' | ' . labels('admin_panel', 'Admin Panel'), 'email_template_edit');
        return view('backend/admin/template', $this->data);
    }

    public function edit_email_template_operation()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
            }

            $validationRules = [
                'email_type' => ["rules" => 'required', "errors" => ["required" => labels('please_select_type', 'Please select type')]],
                'template_id' => ["rules" => 'required', "errors" => ["required" => 'Template ID is required']],
            ];
            if (!$this->validate($validationRules)) {
                $errors = $this->validator->getErrors();
                return JsonError($errors);
            }

            $translations = $this->request->getPost('translations', FILTER_UNSAFE_RAW);

            $default_language = $this->languageModel->where('is_default', 1)->first();
            $default_lang_code = $default_language ? $default_language['code'] : 'en';

            if (empty($translations)) {
                return JsonError(['translations' => labels('translations_required', 'Translations are required')]);
            }
            if (!isset($translations[$default_lang_code])) {
                return JsonError(['default_language' => labels('default_language_translations_missing', 'Default language translations are required')]);
            }

            $default_translation = $translations[$default_lang_code];

            if (empty($default_translation['subject'])) {
                return JsonError(['default_language_subject' => labels('default_language_subject_required', 'Default language subject is required')]);
            }
            if (empty($default_translation['template'])) {
                return JsonError(['default_language_template' => labels('default_language_template_required', 'Default language template is required')]);
            }

            $id = $this->request->getPost('template_id');
            $email_type = $this->request->getPost('email_type');
            $email_to = $this->request->getPost('email_to');

            $default_subject = $default_translation['subject'];
            $default_template = $default_translation['template'];
            $parameters = extractVariables($default_template);

            $data = [
                'type' => $email_type,
                'subject' => $default_subject,
                'to' => json_encode($email_to),
                'template' => $default_template,
                'parameters' => json_encode($parameters),
            ];

            if (isset($_POST['bcc'][0]) && !empty($_POST['bcc'][0])) {
                $base_tags = $this->request->getPost('bcc');
                $s_t = $base_tags;
                $val = explode(',', str_replace(']', '', str_replace('[', '', $s_t[0])));
                $bcc_array = [];
                foreach ($val as $s) {
                    $bcc_array[] = json_decode($s, true)['value'];
                }
                $data['bcc'] = implode(',', $bcc_array);
            }

            if (isset($_POST['cc'][0]) && !empty($_POST['cc'][0])) {
                $base_tags = $this->request->getPost('cc');
                $s_t = $base_tags;
                $val = explode(',', str_replace(']', '', str_replace('[', '', $s_t[0])));
                $cc_array = [];
                foreach ($val as $s) {
                    $cc_array[] = json_decode($s, true)['value'];
                }
                $data['cc'] = implode(',', $cc_array);
            }

            $update = $this->emailTemplateModel->where('id', $id)->set($data)->update();

            if ($update && !empty($translations)) {
                foreach ($translations as $lang_code => $translation_data) {
                    if (empty($translation_data['subject']) && empty($translation_data['template'])) {
                        continue;
                    }
                    $this->translatedEmailTemplateModel->saveTranslation($id, $lang_code, [
                        'subject' => $translation_data['subject'] ?? '',
                        'template' => $translation_data['template'] ?? '',
                    ]);
                }
            }

            if ($update) {
                return JsonSuccess(message: labels('template_updated_successfully', 'Template updated successfully!'), extra: ['redirect_url' => base_url('admin/settings/email_template_list')]);
            }
            return JsonError(labels('error_occured', 'An error occurred'));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/EmailSettingsController.php - edit_email_template_operation()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function delete_email_template()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                return JsonError($result['message']);
            }

            $id = $this->request->getPost('id');
            if ($this->emailTemplateModel->delete($id)) {
                return JsonSuccess(labels('email_template_deleted_successfully', 'Email template deleted successfully'));
            }
            return JsonError(labels(ERROR_OCCURED, 'An error occured'));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/EmailSettingsController.php - delete_email_template()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function sms_email_preview()
    {
        try {
            $uri = service('uri');
            $type = $uri->getSegments()[3];

            $smsTemplateModel = new Sms_template_model();
            $translatedSmsTemplateModel = new Translated_sms_template_model();
            $notificationTemplateModel = new NotificationTemplateModel();
            $translatedNotificationTemplateModel = new TranslatedNotificationTemplateModel();

            $email_template = $this->emailTemplateModel->where('type', $type)->findAll();
            $sms_template = $smsTemplateModel->where('type', $type)->findAll();
            $notification_template = $notificationTemplateModel->where('event_key', $type)->findAll();

            if (empty($notification_template)) {
                $notification_template = [['id' => null, 'event_key' => $type, 'title' => '', 'body' => '', 'parameters' => '']];
            }

            $languages = $this->languageModel->orderBy('is_default', 'DESC')->findAll();

            $email_translations = [];
            if (!empty($email_template[0]['id'])) {
                $emailTranslationResults = $this->translatedEmailTemplateModel->getTemplateTranslations($email_template[0]['id']);
                foreach ($emailTranslationResults as $translation) {
                    $email_translations[$translation['language_code']] = $translation;
                }
            }

            $sms_translations = [];
            if (!empty($sms_template[0]['id'])) {
                $smsTranslationResults = $translatedSmsTemplateModel->getTemplateTranslations($sms_template[0]['id']);
                foreach ($smsTranslationResults as $translation) {
                    $sms_translations[$translation['language_code']] = $translation;
                }
            }

            $notification_translations = [];
            if (!empty($notification_template[0]['id'])) {
                $notificationTranslationResults = $translatedNotificationTemplateModel->getTemplateTranslations($notification_template[0]['id']);
                foreach ($notificationTranslationResults as $translation) {
                    $notification_translations[$translation['language_code']] = $translation;
                }
            }

            $email_parameters = $this->normalizeEmailTemplateParameters($email_template[0]['parameters'] ?? '');
            $sms_parameters = $this->normalizeEmailTemplateParameters($sms_template[0]['parameters'] ?? '');
            $notification_parameters = $this->normalizeEmailTemplateParameters($notification_template[0]['parameters'] ?? '');

            $parameterLabels = $this->getParameterLabels();

            $email_typeLabel = $this->getEmailTypeLabel($email_template[0]['type'] ?? '');
            $sms_typeLabel = $this->getEmailTypeLabel($sms_template[0]['type'] ?? '');
            $notification_typeLabel = $this->getEmailTypeLabel($notification_template[0]['event_key'] ?? '');

            $this->data['email_template'] = $email_template[0] ?? [];
            $this->data['sms_template'] = $sms_template[0] ?? [];
            $this->data['notification_template'] = $notification_template[0];
            $this->data['languages'] = $languages;
            $this->data['email_translations'] = $email_translations;
            $this->data['sms_translations'] = $sms_translations;
            $this->data['notification_translations'] = $notification_translations;
            $this->data['email_parameters'] = $email_parameters;
            $this->data['sms_parameters'] = $sms_parameters;
            $this->data['notification_parameters'] = $notification_parameters;
            $this->data['parameterLabels'] = $parameterLabels;
            $this->data['email_typeLabel'] = $email_typeLabel;
            $this->data['sms_typeLabel'] = $sms_typeLabel;
            $this->data['notification_typeLabel'] = $notification_typeLabel;

            $sms_gateway_template_ids = [];
            $default_lang_code_sms = '';
            foreach ($languages as $lang) {
                if ($lang['is_default'] == 1) {
                    $default_lang_code_sms = $lang['code'];
                    break;
                }
            }
            $default_sms_translation = !empty($sms_template[0]['id']) && !empty($default_lang_code_sms) && isset($sms_translations[$default_lang_code_sms])
                ? $sms_translations[$default_lang_code_sms]
                : null;
            $raw_ids = $default_sms_translation['gateway_template_ids'] ?? $sms_template[0]['gateway_template_ids'] ?? null;
            if (!empty($raw_ids)) {
                $decoded = is_string($raw_ids) ? json_decode($raw_ids, true) : $raw_ids;
                $sms_gateway_template_ids = is_array($decoded) ? $decoded : [];
            }
            $this->data['sms_gateway_template_ids'] = $sms_gateway_template_ids;
            $this->data['sms_template_based_gateways'] = config('Sms')->templateBasedGateways ?? ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91'];

            setPageInfo($this->data, labels('preview_of_templates', 'Preview Of Templates') . ' | ' . labels('admin_panel', 'Admin Panel'), 'sms_email_preview');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/EmailSettingsController.php - sms_email_preview()');
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

    private function getEmailTypeLabel(string $type): string
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

    private function normalizeEmailTemplateParameters($parameters)
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
